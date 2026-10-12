@php
    $currentTheme = session('theme', 'dark');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $currentTheme }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('common.dashboard')) - {{ config('app.name', 'Agency') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('images/sunu-mark.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=sora:600,700|public-sans:400,500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=2">
    @stack('styles')
</head>
<body>
    @if(auth()->check())
    <div class="app-wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                @php
                    $businessUnit = auth()->user()->currentBusinessUnit;
                @endphp
                @if($businessUnit && $businessUnit->logo)
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                        <img src="{{ asset('storage/' . $businessUnit->logo) }}" alt="{{ $businessUnit->name }}" class="business-logo">
                        <div class="logo" style="font-size: 18px; text-align: center;">{{ $businessUnit->name }}</div>
                    </div>
                @else
                    <div style="display: flex; flex-direction: column; align-items: center; gap: 12px;">
                        <img src="{{ asset('images/sunu-logo-sm.png') }}" alt="{{ config('app.name', 'Agency') }}" class="business-logo">
                        <div class="logo" style="font-size: 18px; text-align: center;">{{ config('app.name', 'Agency') }}</div>
                    </div>
                @endif
            </div>
            
            <nav class="sidebar-menu">
                @php
                    $user = auth()->user();
                    $isSuperAdmin = $user->isSuperAdmin();
                    $hasBusinessUnit = $user->current_business_unit_id !== null;
                @endphp
                
                @if($isSuperAdmin && !$hasBusinessUnit)
                    {{-- Super Admin Menu (when not switched to a business unit) --}}
                    <div class="nav-section">{{ __('common.overview') }}</div>
                    <a href="{{ route('superadmin.dashboard') }}" class="menu-item {{ request()->routeIs('superadmin.dashboard') ? 'active' : '' }}">
                        <span class="menu-icon"><x-icon name="home" /></span>
                        <span>{{ __('common.dashboard') }}</span>
                    </a>

                    <div class="nav-section">{{ __('common.organizations') }}</div>
                    <a href="{{ route('superadmin.business-units.index') }}" class="menu-item {{ request()->routeIs('superadmin.business-units.*') ? 'active' : '' }}">
                        <span class="menu-icon"><x-icon name="building" /></span>
                        <span>{{ __('common.business_units') }}</span>
                    </a>

                    @if(config('sunu.core_banking'))
                    <div class="nav-section">{{ __('common.global_config') }}</div>
                    <a href="{{ route('superadmin.core-banking-systems.index') }}" class="menu-item {{ request()->routeIs('superadmin.core-banking-systems.*') ? 'active' : '' }}">
                        <span class="menu-icon"><x-icon name="bank" /></span>
                        <span>{{ __('common.core_banking_systems') }}</span>
                    </a>
                    <a href="{{ route('superadmin.transaction-types.index') }}" class="menu-item {{ request()->routeIs('superadmin.transaction-types.*') ? 'active' : '' }}">
                        <span class="menu-icon"><x-icon name="card" /></span>
                        <span>{{ __('common.transaction_types') }}</span>
                    </a>
                    @endif

                    <div class="nav-section">{{ __('common.access_control') }}</div>
                    <div class="menu-group" data-group="sa-users">
                        <div class="menu-group-title" onclick="toggleSubmenu(this)">
                            <span class="menu-icon"><x-icon name="users" /></span>
                            <span>{{ __('common.user_management') }}</span>
                            <span class="menu-toggle">▼</span>
                        </div>
                        <div class="submenu expanded">
                            <a href="{{ route('superadmin.user-management.users.index') }}" class="submenu-item {{ request()->routeIs('superadmin.user-management.users.*') ? 'active' : '' }}">
                                {{ __('common.users') }}
                            </a>
                            <a href="{{ route('superadmin.user-management.roles.index') }}" class="submenu-item {{ request()->routeIs('superadmin.user-management.roles.*') ? 'active' : '' }}">
                                {{ __('common.roles') }}
                            </a>
                            <a href="{{ route('superadmin.user-management.permissions.index') }}" class="submenu-item {{ request()->routeIs('superadmin.user-management.permissions.*') ? 'active' : '' }}">
                                {{ __('common.permissions') }}
                            </a>
                        </div>
                    </div>

                    <div class="nav-section">{{ __('common.communications') }}</div>
                    <div class="menu-group" data-group="sa-sms">
                        <div class="menu-group-title {{ request()->routeIs('superadmin.sms.*') ? 'active' : '' }}" onclick="toggleSubmenu(this)">
                            <span class="menu-icon"><x-icon name="message" /></span>
                            <span>{{ __('common.sms_management') }}</span>
                            <span class="menu-toggle {{ request()->routeIs('superadmin.sms.*') ? '' : 'collapsed' }}">▼</span>
                        </div>
                        <div class="submenu {{ request()->routeIs('superadmin.sms.*') ? 'expanded' : 'collapsed' }}">
                            <a href="{{ route('superadmin.sms.config.index') }}" class="submenu-item {{ request()->routeIs('superadmin.sms.config.*') ? 'active' : '' }}">
                                {{ __('common.sms_configuration') }}
                            </a>
                            <a href="{{ route('superadmin.sms.load_credits') }}" class="submenu-item {{ request()->routeIs('superadmin.sms.load_credits', 'superadmin.sms.store_credits') ? 'active' : '' }}">
                                {{ __('common.load_sms_credits') }}
                            </a>
                            <a href="{{ route('superadmin.sms.all_balances') }}" class="submenu-item {{ request()->routeIs('superadmin.sms.all_balances') ? 'active' : '' }}">
                                {{ __('common.all_businesses_balance') }}
                            </a>
                        </div>
                    </div>
                @else
                    {{-- Business Menu (for regular users or super admins who switched to a business unit) --}}
                    <div class="nav-section">{{ __('common.overview') }}</div>
                    <a href="{{ route('business.dashboard') }}" class="menu-item {{ request()->routeIs('business.dashboard') ? 'active' : '' }}">
                        <span class="menu-icon"><x-icon name="home" /></span>
                        <span>{{ __('common.dashboard') }}</span>
                    </a>

                    @if($user->isSuperAdmin())
                        <a href="{{ route('superadmin.business-units.index') }}" class="menu-item {{ request()->routeIs('superadmin.business-units.*') ? 'active' : '' }}">
                            <span class="menu-icon"><x-icon name="building" /></span>
                            <span>{{ __('common.business_units') }}</span>
                        </a>
                    @endif

                    <div class="nav-section">{{ __('common.access_control') }}</div>
                    <div class="menu-group" data-group="biz-users">
                        <div class="menu-group-title" onclick="toggleSubmenu(this)">
                            <span class="menu-icon"><x-icon name="users" /></span>
                            <span>{{ __('common.user_management') }}</span>
                            <span class="menu-toggle">▼</span>
                        </div>
                        <div class="submenu expanded">
                            <a href="{{ route('business.users.index') }}" class="submenu-item {{ request()->routeIs('business.users.*') ? 'active' : '' }}">
                                {{ __('common.users') }}
                            </a>
                            <a href="{{ route('business.roles.index') }}" class="submenu-item {{ request()->routeIs('business.roles.*') ? 'active' : '' }}">
                                {{ __('common.roles') }}
                            </a>
                        </div>
                    </div>
                    
                    @php
                        $user = auth()->user();
                        $businessUnit = $user->currentBusinessUnit;
                        $canViewClients = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-clients', $businessUnit->id));
                        $canViewAgents = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-agents', $businessUnit->id));
                        $canViewTerminals = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-terminals', $businessUnit->id));
                        $canViewAccounts = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-accounts', $businessUnit->id));
                        $canViewSms = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-sms', $businessUnit->id));
                    @endphp
                    
                    <div class="nav-section">{{ __('common.operations') }}</div>
                    @if($canViewClients)
                        <div class="menu-group" data-group="biz-clients">
                            <div class="menu-group-title {{ request()->routeIs('clients.*') ? 'active' : '' }}" onclick="toggleSubmenu(this)">
                                <span class="menu-icon"><x-icon name="user" /></span>
                                <span>{{ __('common.clients') }}</span>
                                <span class="menu-toggle {{ request()->routeIs('clients.*') ? '' : 'collapsed' }}">▼</span>
                            </div>
                            <div class="submenu {{ request()->routeIs('clients.*') ? 'expanded' : 'collapsed' }}">
                                <a href="{{ route('clients.index') }}" class="submenu-item {{ request()->routeIs('clients.index', 'clients.show', 'clients.create', 'clients.edit') ? 'active' : '' }}">
                                    {{ __('common.clients') }}
                                </a>
                                <a href="{{ route('clients.groups.index') }}" class="submenu-item {{ request()->routeIs('clients.groups.*') ? 'active' : '' }}">
                                    {{ __('common.client_groups') }}
                                </a>
                            </div>
                        </div>
                    @endif
                    
                    @if($canViewAgents)
                        <a href="{{ route('agents.index') }}" class="menu-item {{ request()->routeIs('agents.*') ? 'active' : '' }}">
                            <span class="menu-icon"><x-icon name="handshake" /></span>
                            <span>{{ __('common.agents') }}</span>
                        </a>
                    @endif

                    @if($canViewTerminals)
                        <a href="{{ route('terminals.index') }}" class="menu-item {{ request()->routeIs('terminals.*') ? 'active' : '' }}">
                            <span class="menu-icon"><x-icon name="monitor" /></span>
                            <span>{{ __('common.terminals') }}</span>
                        </a>
                    @endif
                    @if($canViewAccounts && config('sunu.core_banking'))
                        <a href="{{ route('accounts.index') }}" class="menu-item {{ request()->routeIs('accounts.*') ? 'active' : '' }}">
                            <span class="menu-icon"><x-icon name="wallet" /></span>
                            <span>{{ __('common.accounts') }}</span>
                        </a>
                    @endif

                    @php
                        $canViewTransactions = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-transactions', $businessUnit->id));
                    @endphp
                    @if($canViewTransactions && config('sunu.core_banking'))
                        <a href="{{ route('transactions.index') }}" class="menu-item {{ request()->routeIs('transactions.*') ? 'active' : '' }}">
                            <span class="menu-icon"><x-icon name="card" /></span>
                            <span>{{ __('common.transactions') }}</span>
                        </a>
                    @endif

                    @php
                        $canViewCnssMembers = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-cnss-members', $businessUnit->id));
                    @endphp
                    @if($canViewCnssMembers && $businessUnit?->cnss_enabled)
                        <div class="nav-section">{{ __('common.services') }}</div>
                        <div class="menu-group" data-group="biz-cnss">
                            <div class="menu-group-title {{ request()->routeIs('business.cnss.*') ? 'active' : '' }}" onclick="toggleSubmenu(this)">
                                <span class="menu-icon"><x-icon name="receipt" /></span>
                                <span>{{ __('common.cnss_menu_title') }}</span>
                                <span class="menu-toggle {{ request()->routeIs('business.cnss.*') ? '' : 'collapsed' }}">▼</span>
                            </div>
                            <div class="submenu {{ request()->routeIs('business.cnss.*') ? 'expanded' : 'collapsed' }}">
                                <a href="{{ route('business.cnss.contributions.dashboard') }}" class="submenu-item {{ request()->routeIs('business.cnss.contributions.dashboard') ? 'active' : '' }}">
                                    {{ __('common.cnss_dashboard') }}
                                </a>
                                <a href="{{ route('business.cnss.members.index') }}" class="submenu-item {{ request()->routeIs('business.cnss.members.*') ? 'active' : '' }}">
                                    {{ __('common.cnss_members') }}
                                </a>
                                <a href="{{ route('business.cnss.contributions.index') }}" class="submenu-item {{ request()->routeIs('business.cnss.contributions.index') ? 'active' : '' }}">
                                    {{ __('common.cnss_all_contributions') }}
                                </a>
                            </div>
                        </div>
                    @endif

                    @php
                        $sunuCan = fn ($slug) => $user->isSuperAdmin() || ($businessUnit && $user->hasPermission($slug, $businessUnit->id));
                        $sunuLinks = collect([
                            ['view-sunu-proposals', 'business.sunu.proposals.index', 'business.sunu.proposals.*', 'sunu_proposals'],
                            ['view-sunu-policies', 'business.sunu.policies.index', 'business.sunu.policies.*', 'sunu_policies'],
                            ['view-sunu-premiums', 'business.sunu.premiums.index', 'business.sunu.premiums.*', 'sunu_premiums'],
                            ['view-sunu-payins', 'business.sunu.payins.index', 'business.sunu.payins.*', 'sunu_payins'],
                            ['view-sunu-corrections', 'business.sunu.corrections.index', 'business.sunu.corrections.*', 'sunu_corrections'],
                            ['view-sunu-products', 'business.sunu.products.index', 'business.sunu.products.*', 'sunu_products'],
                            ['import-sunu-policies', 'business.sunu.import', 'business.sunu.import*', 'sunu_import'],
                        ])->filter(fn ($l) => $sunuCan($l[0]));
                        $sunuReportLinks = collect([
                            ['view-sunu-report-premiums', 'business.sunu.reports.premiums', 'sunu_report_premiums'],
                            ['view-sunu-report-reconciliation', 'business.sunu.reports.reconciliation', 'sunu_report_reconciliation'],
                            ['view-sunu-report-subscriptions', 'business.sunu.reports.subscriptions', 'sunu_report_subscriptions'],
                            ['view-sunu-report-cards', 'business.sunu.reports.cards', 'sunu_report_cards'],
                        ])->filter(fn ($l) => $sunuCan($l[0]));
                    @endphp
                    @if($sunuLinks->isNotEmpty())
                    <div class="nav-section">{{ __('common.sunu_section') }}</div>
                    <div class="menu-group" data-group="biz-sunu">
                        <div class="menu-group-title {{ request()->routeIs('business.sunu.*') ? 'active' : '' }}" onclick="toggleSubmenu(this)">
                            <span class="menu-icon"><x-icon name="receipt" /></span>
                            <span>{{ __('common.sunu_menu_title') }}</span>
                            <span class="menu-toggle {{ request()->routeIs('business.sunu.*') ? '' : 'collapsed' }}">▼</span>
                        </div>
                        <div class="submenu {{ request()->routeIs('business.sunu.*') ? 'expanded' : 'collapsed' }}">
                            @php
                                $sunuPendingCorrections = ($businessUnit && $sunuCan('view-sunu-corrections'))
                                    ? \App\Models\SunuCorrectionRequest::where('business_unit_id', $businessUnit->id)->where('status', 'pending')->count()
                                    : 0;
                            @endphp
                            @foreach($sunuLinks as [$slug, $routeName, $activePattern, $label])
                                <a href="{{ route($routeName) }}" class="submenu-item {{ request()->routeIs($activePattern) ? 'active' : '' }}">{{ __('common.'.$label) }}@if($label === 'sunu_corrections' && $sunuPendingCorrections > 0) <span class="badge" style="background:#e67e22;color:#fff;">{{ $sunuPendingCorrections }}</span>@endif</a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @php
                        $canViewAuditTrail = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-audit-trail', $businessUnit->id));
                        $canViewReports = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-reports', $businessUnit->id));
                    @endphp
                    @if($canViewAuditTrail || $sunuReportLinks->isNotEmpty())
                        <div class="nav-section">{{ __('common.insights') }}</div>
                    @endif
                    @if($canViewAuditTrail)
                        <a href="{{ route('audit-trail.index') }}" class="menu-item {{ request()->routeIs('audit-trail.*') ? 'active' : '' }}">
                            <span class="menu-icon"><x-icon name="clipboard" /></span>
                            <span>{{ __('common.audit_trail') }}</span>
                        </a>
                    @endif

                    @if($sunuReportLinks->isNotEmpty())
                        <div class="menu-group" data-group="biz-sunu-reports">
                            <div class="menu-group-title {{ request()->routeIs('business.sunu.reports.*') ? 'active' : '' }}" onclick="toggleSubmenu(this)">
                                <span class="menu-icon"><x-icon name="chart" /></span>
                                <span>{{ __('common.sunu_reports_menu') }}</span>
                                <span class="menu-toggle {{ request()->routeIs('business.sunu.reports.*') ? '' : 'collapsed' }}">▼</span>
                            </div>
                            <div class="submenu {{ request()->routeIs('business.sunu.reports.*') ? 'expanded' : 'collapsed' }}">
                                @foreach($sunuReportLinks as [$slug, $routeName, $label])
                                    <a href="{{ route($routeName) }}" class="submenu-item {{ request()->routeIs($routeName) ? 'active' : '' }}">{{ __('common.'.$label) }}</a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($canViewReports && config('sunu.core_banking'))
                        <div class="menu-group" data-group="biz-reports">
                            <div class="menu-group-title {{ request()->routeIs('business.reports.*') ? 'active' : '' }}" onclick="toggleSubmenu(this)">
                                <span class="menu-icon"><x-icon name="chart" /></span>
                                <span>{{ __('common.reports') }}</span>
                                <span class="menu-toggle {{ request()->routeIs('business.reports.*') ? '' : 'collapsed' }}">▼</span>
                            </div>
                            <div class="submenu {{ request()->routeIs('business.reports.*') ? 'expanded' : 'collapsed' }}">
                                <a href="{{ route('business.reports.index') }}" class="submenu-item {{ request()->routeIs('business.reports.index') ? 'active' : '' }}">
                                    {{ __('common.reports') }}
                                </a>
                                <a href="{{ route('business.reports.agents') }}" class="submenu-item {{ request()->routeIs('business.reports.agents*') ? 'active' : '' }}">
                                    {{ __('common.agent_reports') }}
                                </a>
                                <a href="{{ route('business.reports.clients') }}" class="submenu-item {{ request()->routeIs('business.reports.clients*') ? 'active' : '' }}">
                                    {{ __('common.client_reports') }}
                                </a>
                                <a href="{{ route('business.reports.transactions') }}" class="submenu-item {{ request()->routeIs('business.reports.transactions*') ? 'active' : '' }}">
                                    {{ __('common.transaction_reports') }}
                                </a>
                                <a href="{{ route('business.reports.commissions') }}" class="submenu-item {{ request()->routeIs('business.reports.commissions*') ? 'active' : '' }}">
                                    {{ __('common.commission_reports') }}
                                </a>
                                <a href="{{ route('business.reports.costs') }}" class="submenu-item {{ request()->routeIs('business.reports.costs*') ? 'active' : '' }}">
                                    {{ __('common.cost_reports') }}
                                </a>
                            </div>
                        </div>
                    @endif
                    
                    @if($canViewSms)
                        <div class="nav-section">{{ __('common.communications') }}</div>
                        <div class="menu-group" data-group="biz-sms">
                            <div class="menu-group-title {{ request()->routeIs('business.sms.*') ? 'active' : '' }}" onclick="toggleSubmenu(this)">
                                <span class="menu-icon"><x-icon name="message" /></span>
                                <span>{{ __('common.sms_management') }}</span>
                                <span class="menu-toggle {{ request()->routeIs('business.sms.*') ? '' : 'collapsed' }}">▼</span>
                            </div>
                            <div class="submenu {{ request()->routeIs('business.sms.*') ? 'expanded' : 'collapsed' }}">
                                <a href="{{ route('business.sms.index') }}" class="submenu-item {{ request()->routeIs('business.sms.index') ? 'active' : '' }}">
                                    {{ __('common.sms_dashboard') }}
                                </a>
                                @php
                                    $canSendSingle = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('send-single-sms', $businessUnit->id));
                                    $canSendBulk = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('send-bulk-sms', $businessUnit->id));
                                    $canViewHistory = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-sms-history', $businessUnit->id));
                                @endphp
                                @if($canSendSingle)
                                    <a href="{{ route('business.sms.send-single') }}" class="submenu-item {{ request()->routeIs('business.sms.send-single*') ? 'active' : '' }}">
                                        {{ __('common.send_single_sms') }}
                                    </a>
                                @endif
                                @if($canSendBulk)
                                    <a href="{{ route('business.sms.send-bulk') }}" class="submenu-item {{ request()->routeIs('business.sms.send-bulk*') ? 'active' : '' }}">
                                        {{ __('common.send_bulk_sms') }}
                                    </a>
                                @endif
                                @if($canViewHistory)
                                    <a href="{{ route('business.sms.history') }}" class="submenu-item {{ request()->routeIs('business.sms.history*') ? 'active' : '' }}">
                                        {{ __('common.sms_history') }}
                                    </a>
                                @endif
                                @if($user->isSuperAdmin())
                                    <div style="padding: 10px 20px 10px 52px; border-top: 1px solid var(--border-color); margin-top: 5px; padding-top: 15px;">
                                        <div style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; padding-left: 0;">{{ __('common.superadmin') }}</div>
                                        <a href="{{ route('superadmin.sms.config.index') }}" class="submenu-item {{ request()->routeIs('superadmin.sms.config.*') ? 'active' : '' }}">
                                            {{ __('common.sms_configuration') }}
                                        </a>
                                        <a href="{{ route('superadmin.sms.load_credits') }}" class="submenu-item {{ request()->routeIs('superadmin.sms.load_credits*') ? 'active' : '' }}">
                                            {{ __('common.load_credits') }}
                                        </a>
                                        <a href="{{ route('superadmin.sms.all_balances') }}" class="submenu-item {{ request()->routeIs('superadmin.sms.all_balances') ? 'active' : '' }}">
                                            {{ __('common.all_businesses_balance') }}
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                    
                    @php
                        $canViewCards = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-cards', $businessUnit->id));
                    @endphp
                    @if($canViewCards)
                        <div class="nav-section">{{ __('common.cards') }}</div>
                        <div class="menu-group" data-group="biz-cards">
                            <div class="menu-group-title {{ request()->routeIs('cards.*') ? 'active' : '' }}" onclick="toggleSubmenu(this)">
                                <span class="menu-icon"><x-icon name="card" /></span>
                                <span>{{ __('common.cards') }}</span>
                                <span class="menu-toggle {{ request()->routeIs('cards.*') ? '' : 'collapsed' }}">▼</span>
                            </div>
                            <div class="submenu {{ request()->routeIs('cards.*') ? 'expanded' : 'collapsed' }}">
                                <a href="{{ route('cards.index') }}" class="submenu-item {{ request()->routeIs('cards.index') ? 'active' : '' }}">
                                    {{ __('common.list_cards') }}
                                </a>
                                @php
                                    $canCreateCard = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('create-card', $businessUnit->id));
                                @endphp
                                @if($canCreateCard)
                                    <a href="{{ route('cards.create') }}" class="submenu-item {{ request()->routeIs('cards.create') ? 'active' : '' }}">
                                        {{ __('common.add_card') }}
                                    </a>
                                @endif
                                @php
                                    $canManageSettings = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('manage-card-settings', $businessUnit->id));
                                @endphp
                                @if($canManageSettings)
                                    <a href="{{ route('cards.settings') }}" class="submenu-item {{ request()->routeIs('cards.settings*') ? 'active' : '' }}">
                                        {{ __('common.card_settings') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif
                    
                    @if(config('sunu.core_banking'))
                    <div class="nav-section">{{ __('common.configuration') }}</div>
                    @if($user->isSuperAdmin())
                        {{-- Super Admin Only Menu Items (visible even when viewing a business unit) --}}
                        <a href="{{ route('superadmin.core-banking-systems.index') }}" class="menu-item {{ request()->routeIs('superadmin.core-banking-systems.*') ? 'active' : '' }}">
                            <span class="menu-icon"><x-icon name="bank" /></span>
                            <span>{{ __('common.core_banking_systems') }}</span>
                        </a>
                    @endif
                    <div class="menu-group" data-group="biz-config">
                        <div class="menu-group-title" onclick="toggleSubmenu(this)">
                            <span class="menu-icon"><x-icon name="settings" /></span>
                            <span>{{ __('common.configuration') }}</span>
                            <span class="menu-toggle">▼</span>
                        </div>
                        <div class="submenu expanded">
                            <a href="{{ route('business.config.transaction-types.index') }}" class="submenu-item {{ request()->routeIs('business.config.transaction-types.*') ? 'active' : '' }}">
                                {{ __('common.transaction_types') }}
                            </a>
                            <a href="{{ route('business.config.transaction-costs.index') }}" class="submenu-item {{ request()->routeIs('business.config.transaction-costs.*') ? 'active' : '' }}">
                                {{ __('common.transaction_cost_setup') }}
                            </a>
                            <a href="{{ route('business.config.commissions.index') }}" class="submenu-item {{ request()->routeIs('business.config.commissions.*') ? 'active' : '' }}">
                                {{ __('common.commission_setup') }}
                            </a>
                        </div>
                    </div>
                    @endif
                @endif
            </nav>
        </div>
        
        <div class="sidebar-backdrop" onclick="toggleSidebar()"></div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="top-header">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <button type="button" class="theme-toggle-btn sidebar-toggle" onclick="toggleSidebar()" aria-label="Menu">
                        <x-icon name="menu" :size="18" />
                    </button>
                    @php
                        $businessUnit = auth()->user()->currentBusinessUnit;
                    @endphp
                    @if($businessUnit && $businessUnit->logo)
                        <img src="{{ asset('storage/' . $businessUnit->logo) }}" alt="{{ $businessUnit->name }}" class="business-logo-header">
                    @else
                        <img src="{{ asset('images/sunu-logo-sm.png') }}" alt="{{ config('app.name', 'Agency') }}" class="business-logo-header">
                    @endif
                    <h2>@yield('page-title', __('common.dashboard'))</h2>
                </div>
                <div class="user-info">
                    @include('components.theme-switcher')
                    @include('components.language-switcher')
                    <span style="color: var(--text-secondary);">{{ auth()->user()->name }}</span>
                    @php
                        $user = auth()->user();
                        $isSuperAdmin = $user->isSuperAdmin();
                        $hasBusinessUnit = $user->current_business_unit_id !== null;
                    @endphp
                    @if($isSuperAdmin && $hasBusinessUnit)
                        <form action="{{ route('superadmin.switch-back') }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-sm">{{ __('common.switch_to_superadmin') }}</button>
                        </form>
                    @endif
                    <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="btn btn-secondary btn-sm">
                        <x-icon name="log-out" :size="15" /> <span>{{ __('common.logout') }}</span>
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                </div>
            </div>
            
            <div class="container">
                @if(session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-error">
                        {{ session('error') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-error">
                        <ul style="margin-left: 20px;">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    </div>
    @else
    <div class="container">
        @yield('content')
    </div>
    @endif

    @stack('scripts')
    <script>
        const NAV_STATE_KEY = 'agency.nav.collapsed';

        function getCollapsedGroups() {
            try { return JSON.parse(localStorage.getItem(NAV_STATE_KEY)) || {}; }
            catch (e) { return {}; }
        }
        function setGroupState(key, collapsed) {
            if (!key) return;
            const state = getCollapsedGroups();
            if (collapsed) { state[key] = 1; } else { delete state[key]; }
            localStorage.setItem(NAV_STATE_KEY, JSON.stringify(state));
        }

        function applyGroup(menuGroup, collapsed) {
            const submenu = menuGroup.querySelector('.submenu');
            const toggle = menuGroup.querySelector('.menu-toggle');
            if (!submenu) return;
            submenu.classList.toggle('collapsed', collapsed);
            submenu.classList.toggle('expanded', !collapsed);
            if (toggle) toggle.classList.toggle('collapsed', collapsed);
        }

        function toggleSubmenu(element) {
            const menuGroup = element.closest('.menu-group');
            const submenu = element.nextElementSibling;
            const collapsed = !submenu.classList.contains('collapsed'); // becomes collapsed if currently expanded
            applyGroup(menuGroup, collapsed);
            setGroupState(menuGroup && menuGroup.dataset.group, collapsed);
        }

        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const backdrop = document.querySelector('.sidebar-backdrop');
            if (!sidebar) return;
            const open = sidebar.classList.toggle('open');
            if (backdrop) backdrop.classList.toggle('show', open);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const saved = getCollapsedGroups();

            // 1) Restore each group's saved state (user's last choice wins).
            document.querySelectorAll('.menu-group[data-group]').forEach(function(group) {
                const key = group.dataset.group;
                if (key in saved) applyGroup(group, true);
            });

            // 2) Always auto-expand a group that contains the active item (overrides saved collapse).
            document.querySelectorAll('.submenu-item.active').forEach(function(item) {
                const group = item.closest('.menu-group');
                if (group) applyGroup(group, false);
            });
        });
    </script>
</body>
</html>
