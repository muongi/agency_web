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
    <style>
        :root[data-theme="dark"] {
            --bg-primary: #1a1a1a;
            --bg-secondary: #2d2d2d;
            --bg-tertiary: #3d3d3d;
            --text-primary: #e0e0e0;
            --text-secondary: #aaa;
            --text-muted: #888;
            --border-color: #404040;
            --accent-color: #4a9eff;
            --accent-hover: #357abd;
            --success-bg: #2d5a2d;
            --success-text: #90ee90;
            --error-bg: #5a2d2d;
            --error-text: #ee9090;
            --logo-bg: #ffffff;
            --logo-border: #404040;
        }
        
        :root[data-theme="light"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f5f5f5;
            --bg-tertiary: #e8e8e8;
            --text-primary: #1a1a1a;
            --text-secondary: #555;
            --text-muted: #888;
            --border-color: #ddd;
            --accent-color: #4a9eff;
            --accent-hover: #357abd;
            --success-bg: #d4edda;
            --success-text: #155724;
            --error-bg: #f8d7da;
            --error-text: #721c24;
            --logo-bg: #ffffff;
            --logo-border: #ddd;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            transition: background 0.3s, color 0.3s;
        }
        
        .app-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 260px;
            background: var(--bg-secondary);
            border-right: 1px solid var(--border-color);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: background 0.3s, border-color 0.3s;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .business-logo {
            max-width: 120px;
            max-height: 80px;
            border-radius: 6px;
            object-fit: contain;
            background: var(--logo-bg);
            padding: 8px;
            border: 2px solid var(--logo-border);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .business-logo-small {
            max-width: 80px;
            max-height: 60px;
            border-radius: 6px;
            object-fit: contain;
            background: var(--logo-bg);
            padding: 6px;
            border: 2px solid var(--logo-border);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .business-logo-header {
            max-width: 100px;
            max-height: 70px;
            border-radius: 6px;
            object-fit: contain;
            background: var(--logo-bg);
            padding: 8px;
            border: 2px solid var(--logo-border);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: var(--accent-color);
        }
        
        .sidebar-menu {
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu > * {
            margin: 0;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--text-primary);
            text-decoration: none;
            transition: background 0.3s;
            border-left: 3px solid transparent;
            font-size: 14px;
            font-weight: 400;
            margin: 0;
            line-height: 1.5;
        }
        
        .menu-item:hover {
            background: var(--bg-tertiary);
            border-left-color: var(--accent-color);
        }
        
        .menu-item.active {
            background: var(--bg-tertiary);
            border-left-color: var(--accent-color);
            color: var(--accent-color);
            font-weight: 500;
        }
        
        .menu-icon {
            width: 20px;
            height: 20px;
            margin-right: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        
        .menu-group {
            margin: 0;
            padding: 0;
        }
        
        .menu-group-title {
            padding: 12px 20px;
            font-size: 14px;
            color: var(--text-primary);
            font-weight: 400;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 12px;
            cursor: pointer;
            user-select: none;
            transition: background 0.3s;
            border-left: 3px solid transparent;
            margin: 0;
            line-height: 1.5;
        }
        
        .menu-group-title:hover {
            background: var(--bg-tertiary);
            border-left-color: var(--accent-color);
        }
        
        .menu-group-title .menu-icon {
            margin-right: 0;
        }
        
        .menu-toggle {
            font-size: 10px;
            transition: transform 0.3s;
            color: var(--text-muted);
            margin-left: auto;
        }
        
        .menu-toggle.collapsed {
            transform: rotate(-90deg);
        }
        
        .submenu {
            padding: 0;
            margin: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        
        .submenu.collapsed {
            max-height: 0;
        }
        
        .submenu.expanded {
            max-height: 500px;
        }
        
        .submenu-item {
            padding: 10px 20px 10px 52px;
            display: flex;
            align-items: center;
            color: var(--text-secondary);
            text-decoration: none;
            transition: background 0.3s;
            font-size: 14px;
            font-weight: 400;
        }
        
        .submenu-item:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .submenu-item.active {
            background: var(--bg-tertiary);
            color: var(--accent-color);
            border-left: 3px solid var(--accent-color);
            padding-left: 49px;
            font-weight: 500;
        }
        
        .main-content {
            flex: 1;
            margin-left: 260px;
            min-height: 100vh;
        }
        
        .top-header {
            background: var(--bg-secondary);
            padding: 15px 30px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            transition: background 0.3s, border-color 0.3s;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .theme-toggle-btn {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 8px 12px;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 18px;
        }
        
        .theme-toggle-btn:hover {
            background: var(--bg-secondary);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: var(--accent-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: var(--accent-hover);
        }
        
        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .btn-secondary:hover {
            background: var(--border-color);
        }
        
        .btn-danger {
            background: #d32f2f;
        }
        
        .btn-danger:hover {
            background: #b71c1c;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: var(--success-bg);
            color: var(--success-text);
            border: 1px solid var(--success-text);
        }
        
        .alert-error {
            background: var(--error-bg);
            color: var(--error-text);
            border: 1px solid var(--error-text);
        }
        
        .card {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            transition: background 0.3s, border-color 0.3s;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            color: var(--text-primary);
            font-size: 14px;
            transition: background 0.3s, border-color 0.3s, color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
        }
        
        .form-select {
            padding: 8px 12px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            color: var(--text-primary);
            font-size: 14px;
            width: 100%;
            transition: background 0.3s, border-color 0.3s, color 0.3s;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        th {
            color: var(--accent-color);
            font-weight: 600;
        }
    </style>
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
                    <div class="logo">{{ config('app.name', 'Agency') }}</div>
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
                    <a href="{{ route('superadmin.dashboard') }}" class="menu-item {{ request()->routeIs('superadmin.dashboard') ? 'active' : '' }}">
                        <span class="menu-icon">🏠</span>
                        <span>{{ __('common.dashboard') }}</span>
                    </a>
                    
                    <a href="{{ route('superadmin.business-units.index') }}" class="menu-item {{ request()->routeIs('superadmin.business-units.*') ? 'active' : '' }}">
                        <span class="menu-icon">🏢</span>
                        <span>{{ __('common.business_units') }}</span>
                    </a>
                    
                    <a href="{{ route('superadmin.core-banking-systems.index') }}" class="menu-item {{ request()->routeIs('superadmin.core-banking-systems.*') ? 'active' : '' }}">
                        <span class="menu-icon">🏦</span>
                        <span>{{ __('common.core_banking_systems') }}</span>
                    </a>
                    
                    <a href="{{ route('superadmin.transaction-types.index') }}" class="menu-item {{ request()->routeIs('superadmin.transaction-types.*') ? 'active' : '' }}">
                        <span class="menu-icon">💳</span>
                        <span>{{ __('common.transaction_types') }}</span>
                    </a>
                    
                    <div class="menu-group">
                        <div class="menu-group-title" onclick="toggleSubmenu(this)">
                            <span class="menu-icon">👥</span>
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
                    
                    <div class="menu-group">
                        <div class="menu-group-title {{ request()->routeIs('superadmin.sms.*') ? 'active' : '' }}" onclick="toggleSubmenu(this)">
                            <span class="menu-icon">📱</span>
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
                    <a href="{{ route('business.dashboard') }}" class="menu-item {{ request()->routeIs('business.dashboard') ? 'active' : '' }}">
                        <span class="menu-icon">🏠</span>
                        <span>{{ __('common.dashboard') }}</span>
                    </a>
                    
                    @if($user->isSuperAdmin())
                        <a href="{{ route('superadmin.business-units.index') }}" class="menu-item {{ request()->routeIs('superadmin.business-units.*') ? 'active' : '' }}">
                            <span class="menu-icon">🏢</span>
                            <span>{{ __('common.business_units') }}</span>
                        </a>
                    @endif
                    
                    <div class="menu-group">
                        <div class="menu-group-title" onclick="toggleSubmenu(this)">
                            <span class="menu-icon">👥</span>
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
                    
                    @if($canViewClients)
                        <div class="menu-group">
                            <div class="menu-group-title {{ request()->routeIs('clients.*') ? 'active' : '' }}" onclick="toggleSubmenu(this)">
                                <span class="menu-icon">👤</span>
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
                            <span class="menu-icon">🤝</span>
                            <span>{{ __('common.agents') }}</span>
                        </a>
                    @endif
                    
                    @if($canViewTerminals)
                        <a href="{{ route('terminals.index') }}" class="menu-item {{ request()->routeIs('terminals.*') ? 'active' : '' }}">
                            <span class="menu-icon">💻</span>
                            <span>{{ __('common.terminals') }}</span>
                        </a>
                    @endif
                    @if($canViewAccounts)
                        <a href="{{ route('accounts.index') }}" class="menu-item {{ request()->routeIs('accounts.*') ? 'active' : '' }}">
                            <span class="menu-icon">💰</span>
                            <span>{{ __('common.accounts') }}</span>
                        </a>
                    @endif
                    
                    @php
                        $canViewTransactions = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-transactions', $businessUnit->id));
                    @endphp
                    @if($canViewTransactions)
                        <a href="{{ route('transactions.index') }}" class="menu-item {{ request()->routeIs('transactions.*') ? 'active' : '' }}">
                            <span class="menu-icon">💳</span>
                            <span>{{ __('common.transactions') }}</span>
                        </a>
                    @endif

                    @php
                        $canViewCnssMembers = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-cnss-members', $businessUnit->id));
                    @endphp
                    @if($canViewCnssMembers)
                        <div class="menu-group">
                            <div class="menu-group-title {{ request()->routeIs('business.cnss.*') ? '' : '' }}" onclick="toggleSubmenu(this)">
                                <span class="menu-icon">🧾</span>
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
                        $canViewAuditTrail = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-audit-trail', $businessUnit->id));
                    @endphp
                    @if($canViewAuditTrail)
                        <a href="{{ route('audit-trail.index') }}" class="menu-item {{ request()->routeIs('audit-trail.*') ? 'active' : '' }}">
                            <span class="menu-icon">📋</span>
                            <span>{{ __('common.audit_trail') }}</span>
                        </a>
                    @endif
                    
                    @php
                        $canViewReports = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-reports', $businessUnit->id));
                    @endphp
                    @if($canViewReports)
                        <div class="menu-group">
                            <div class="menu-group-title {{ request()->routeIs('business.reports.*') ? '' : '' }}" onclick="toggleSubmenu(this)">
                                <span class="menu-icon">📊</span>
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
                        <div class="menu-group">
                            <div class="menu-group-title {{ request()->routeIs('business.sms.*') ? '' : '' }}" onclick="toggleSubmenu(this)">
                                <span class="menu-icon">📱</span>
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
                    
                    @if($user->isSuperAdmin())
                        {{-- Super Admin Only Menu Items (visible even when viewing a business unit) --}}
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--border-color);">
                            <div style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 15px; padding-left: 20px;">{{ __('common.superadmin') }}</div>
                            <a href="{{ route('superadmin.core-banking-systems.index') }}" class="menu-item {{ request()->routeIs('superadmin.core-banking-systems.*') ? 'active' : '' }}">
                                <span class="menu-icon">🏦</span>
                                <span>{{ __('common.core_banking_systems') }}</span>
                            </a>
                        </div>
                    @endif
                    
                    @php
                        $canViewCards = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-cards', $businessUnit->id));
                    @endphp
                    @if($canViewCards)
                        <div class="menu-group">
                            <div class="menu-group-title {{ request()->routeIs('cards.*') ? '' : '' }}" onclick="toggleSubmenu(this)">
                                <span class="menu-icon">💳</span>
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
                    
                    <div class="menu-group">
                        <div class="menu-group-title" onclick="toggleSubmenu(this)">
                            <span class="menu-icon">⚙️</span>
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
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="top-header">
                <div style="display: flex; align-items: center; gap: 15px;">
                    @php
                        $businessUnit = auth()->user()->currentBusinessUnit;
                    @endphp
                    @if($businessUnit && $businessUnit->logo)
                        <img src="{{ asset('storage/' . $businessUnit->logo) }}" alt="{{ $businessUnit->name }}" class="business-logo-header">
                    @endif
                    <h2 style="color: var(--accent-color); font-size: 18px;">@yield('page-title', __('common.dashboard'))</h2>
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
                            <button type="submit" class="btn" style="padding: 8px 16px; font-size: 12px; background: #4a9eff;">{{ __('common.switch_to_superadmin') }}</button>
                        </form>
                    @endif
                    <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="btn btn-secondary" style="padding: 8px 16px; font-size: 12px;">{{ __('common.logout') }}</a>
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
        function toggleSubmenu(element) {
            const submenu = element.nextElementSibling;
            const toggle = element.querySelector('.menu-toggle');
            
            if (submenu.classList.contains('collapsed')) {
                submenu.classList.remove('collapsed');
                submenu.classList.add('expanded');
                toggle.classList.remove('collapsed');
            } else {
                submenu.classList.remove('expanded');
                submenu.classList.add('collapsed');
                toggle.classList.add('collapsed');
            }
        }
        
        // Auto-expand submenu if active item is inside
        document.addEventListener('DOMContentLoaded', function() {
            const activeSubmenuItems = document.querySelectorAll('.submenu-item.active');
            activeSubmenuItems.forEach(function(item) {
                const submenu = item.closest('.submenu');
                const menuGroup = item.closest('.menu-group');
                if (submenu && menuGroup) {
                    submenu.classList.remove('collapsed');
                    submenu.classList.add('expanded');
                    const toggle = menuGroup.querySelector('.menu-toggle');
                    if (toggle) {
                        toggle.classList.remove('collapsed');
                    }
                }
            });
        });
    </script>
</body>
</html>
