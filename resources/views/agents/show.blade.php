@extends('layouts.app')

@section('title', __('common.view_agent'))
@section('page-title', __('common.view_agent'))

@section('content')
<div class="card">
    <div class="toolbar">
        <h1 class="text-accent">{{ __('common.agent_details') }}</h1>
        <div class="btn-row">
            @php
                $user = auth()->user();
                $businessUnit = $user->currentBusinessUnit;
                $canEdit = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('edit-agent', $businessUnit->id));
            @endphp
            @if($canEdit)
                <a href="{{ route('agents.edit', $agent->id) }}" class="btn">{{ __('common.edit') }}</a>
                <form method="POST" action="{{ route('agents.reset-pin-otp', $agent->id) }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn" style="background: var(--warning-bg);"
                            onclick="return confirm(@json(__('common.confirm_reset_agent_pin')));">
                        {{ __('common.reset_agent_pin') }}
                    </button>
                </form>
                @if(preg_match('/^\d{4}$/', (string) config('sunu.default_agent_pin')))
                    <form method="POST" action="{{ route('agents.reset-pin-otp', $agent->id) }}" style="display: inline;">
                        @csrf
                        <input type="hidden" name="use_default" value="1">
                        <button type="submit" class="btn btn-secondary"
                                onclick="return confirm(@json(__('common.sunu_pin_use_default_confirm', ['pin' => config('sunu.default_agent_pin')])));">
                            {{ __('common.sunu_pin_use_default', ['pin' => config('sunu.default_agent_pin')]) }}
                        </button>
                    </form>
                @endif
            @endif
            <a href="{{ route('agents.index') }}" class="btn btn-secondary">{{ __('common.back') }}</a>
        </div>
    </div>

    @if(session('warning'))
        <div style="padding: 12px 16px; margin-bottom: 16px; border-radius: 8px; background: var(--warning-bg); color: var(--text-primary);">{{ session('warning') }}</div>
    @endif
    @if(session('pin_reveal'))
        <div style="padding: 16px; margin-bottom: 16px; border-radius: 8px; border: 2px solid var(--accent-color); background: var(--bg-tertiary);">
            <strong>{{ __('common.sunu_pin_reveal_title') }}</strong>
            <div style="font-size: 32px; font-weight: 700; letter-spacing: 8px; color: var(--accent-color); margin: 6px 0;">{{ session('pin_reveal') }}</div>
            <small class="muted">{{ __('common.sunu_pin_reveal_help') }}</small>
        </div>
    @endif

    <div style="margin-bottom: 16px; font-size: 14px;">
        <strong>{{ __('common.sunu_pin_status') }} :</strong>
        @if(! $agent->hasCollectionPin())
            <span class="muted">{{ __('common.sunu_pin_status_none') }}</span>
        @elseif($agent->isPinLocked())
            <span style="color: var(--error-text); font-weight: 600;">{{ __('common.sunu_pin_status_locked', ['date' => $agent->pin_locked_at->format('Y-m-d H:i')]) }}</span>
        @else
            <span>{{ __('common.sunu_pin_status_set') }}</span>
            @if($agent->pin_failed_attempts > 0)<span class="muted">· {{ __('common.sunu_pin_status_failed', ['n' => $agent->pin_failed_attempts]) }}</span>@endif
            @if($agent->pin_must_change)<span class="muted">· {{ __('common.sunu_pin_status_must_change') }}</span>@endif
        @endif
    </div>

    <!-- Agent Header with Photo -->
    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 30px; padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
        @if($agent->photo)
            <img src="{{ asset('storage/' . $agent->photo) }}" alt="{{ $agent->full_name }}" style="width: 120px; height: 120px; border-radius: 8px; object-fit: cover; border: 2px solid var(--border-color);">
        @else
            <div style="width: 120px; height: 120px; border-radius: 8px; background: var(--bg-primary); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
                <x-icon name="user" :size="48" />
            </div>
        @endif
        <div>
            <h2 style="color: var(--accent-color); margin-bottom: 5px;">{{ $agent->full_name }}</h2>
            <p style="color: var(--text-secondary); margin: 5px 0;">{{ __('common.username') }}: {{ $agent->username }}</p>
            <p style="color: var(--text-secondary); margin: 5px 0;">{{ __('common.email') }}: {{ $agent->email }}</p>
            @if($agent->phone_number)
                <p style="color: var(--text-secondary); margin: 5px 0;">{{ __('common.phone_number') }}: {{ $agent->phone_number }}</p>
            @endif
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div style="border-bottom: 2px solid var(--border-color); margin-bottom: 30px;">
        <div style="display: flex; gap: 0;">
            <a href="{{ route('agents.show', ['agent' => $agent->id, 'tab' => 'information']) }}" 
               class="tab-link {{ ($activeTab ?? 'information') == 'information' ? 'active' : '' }}" 
               style="padding: 12px 24px; text-decoration: none; color: var(--text-secondary); border-bottom: 3px solid transparent; transition: all 0.3s; font-weight: 500;">
                {{ __('common.information') }}
            </a>
            @if(config('sunu.core_banking'))
            <a href="{{ route('agents.show', ['agent' => $agent->id, 'tab' => 'accounts']) }}" 
               class="tab-link {{ ($activeTab ?? 'information') == 'accounts' ? 'active' : '' }}" 
               style="padding: 12px 24px; text-decoration: none; color: var(--text-secondary); border-bottom: 3px solid transparent; transition: all 0.3s; font-weight: 500;">
                {{ __('common.accounts') }}
            </a>
            @endif
            <a href="{{ route('agents.show', ['agent' => $agent->id, 'tab' => 'terminals']) }}" 
               class="tab-link {{ ($activeTab ?? 'information') == 'terminals' ? 'active' : '' }}" 
               style="padding: 12px 24px; text-decoration: none; color: var(--text-secondary); border-bottom: 3px solid transparent; transition: all 0.3s; font-weight: 500;">
                {{ __('common.terminals') }} ({{ $agent->terminals->count() }})
            </a>
            @if(config('sunu.core_banking'))
            <a href="{{ route('agents.show', ['agent' => $agent->id, 'tab' => 'transactions']) }}" 
               class="tab-link {{ ($activeTab ?? 'information') == 'transactions' ? 'active' : '' }}" 
               style="padding: 12px 24px; text-decoration: none; color: var(--text-secondary); border-bottom: 3px solid transparent; transition: all 0.3s; font-weight: 500;">
                {{ __('common.transaction_history') }}
            </a>
            @endif
        </div>
    </div>

    <!-- Tab Content -->
    <div class="tab-content">
        @if(($activeTab ?? 'information') == 'information')
            <!-- Information Tab -->
            <div>
                <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.personal_information') }}</h2>
                
                <div class="detail-grid">
                    <div>
                        <strong class="detail-label">{{ __('common.name') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $agent->full_name }}</p>
                    </div>
                    
                    <div>
                        <strong class="detail-label">{{ __('common.username') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $agent->username }}</p>
                    </div>

                    <div>
                        <strong class="detail-label">{{ __('common.email') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $agent->email }}</p>
                    </div>

                    @if($agent->phone_number)
                    <div>
                        <strong class="detail-label">{{ __('common.phone_number') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $agent->phone_number }}</p>
                    </div>
                    @endif

                    @if($agent->id_passport)
                    <div>
                        <strong class="detail-label">{{ __('common.id_passport') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $agent->id_passport }}</p>
                    </div>
                    @endif

                    @if($agent->physical_address)
                    <div>
                        <strong class="detail-label">{{ __('common.physical_address') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $agent->physical_address }}</p>
                    </div>
                    @endif

                    @if($agent->po_box)
                    <div>
                        <strong class="detail-label">{{ __('common.po_box') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $agent->po_box }}</p>
                    </div>
                    @endif
                </div>
            </div>

        @elseif(($activeTab ?? 'information') == 'accounts' && config('sunu.core_banking'))
            <!-- Accounts Tab -->
            <div>
                <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.accounts') }}</h2>

                @if(!empty($isKato))
                    @if(!empty($katoFloatSync['success']))
                        <div style="margin-bottom: 20px; padding: 12px 16px; background: var(--success-bg); color: var(--success-text); border-radius: 6px; border: 1px solid var(--border-color);">
                            {{ __('common.kato_balances_synced') }}
                            @if(!empty($katoFloatSyncedAt))
                                <span class="text-muted-sm">({{ $katoFloatSyncedAt->format('Y-m-d H:i:s') }})</span>
                            @endif
                        </div>
                    @elseif(isset($katoFloatSync) && !($katoFloatSync['success'] ?? false))
                        <div style="margin-bottom: 20px; padding: 12px 16px; background: var(--warning-bg, #fff3cd); color: var(--warning-text, #856404); border-radius: 6px; border: 1px solid var(--border-color);">
                            {{ __('common.kato_balances_sync_failed') }}: {{ $katoFloatSync['error'] ?? __('common.error') }}
                        </div>
                    @endif
                @endif

                <div class="detail-grid">
                    <!-- Float Account -->
                    <div style="padding: 20px; background: var(--bg-tertiary); border-radius: 8px; border: 1px solid var(--border-color);">
                        <h3 style="color: var(--accent-color); margin-bottom: 15px;">{{ __('common.float_account') }}</h3>
                        @if($agent->floatAccount())
                            @php $floatAccount = $agent->floatAccount(); @endphp
                            <div style="display: grid; gap: 10px;">
                                <div>
                                    <strong class="text-muted-sm">{{ __('common.account_number') }}:</strong>
                                    <p style="color: var(--text-primary); margin-top: 5px;"><code style="background: var(--bg-primary); padding: 4px 8px; border-radius: 4px;">{{ $floatAccount->account_number }}</code></p>
                                </div>
                                <div>
                                    <strong class="text-muted-sm">{{ __('common.balance') }}:</strong>
                                    <p style="color: var(--text-primary); margin-top: 5px; font-size: 20px; font-weight: bold;">{{ number_format($floatAccount->balance, 2) }} {{ $floatAccount->currency }}</p>
                                </div>
                                <div>
                                    <strong class="text-muted-sm">{{ __('common.status') }}:</strong>
                                    <p style="color: var(--text-primary); margin-top: 5px; text-transform: capitalize;">{{ $floatAccount->status }}</p>
                                </div>
                                <div>
                                    <strong class="text-muted-sm">{{ __('common.opening_date') }}:</strong>
                                    <p style="color: var(--text-primary); margin-top: 5px;">{{ $floatAccount->opening_date->format('Y-m-d') }}</p>
                                </div>
                                <div style="margin-top: 10px;">
                                    <a href="{{ route('accounts.show', $floatAccount->id) }}" class="btn btn-sm">{{ __('common.view_details') }}</a>
                                </div>
                            </div>
                        @else
                            <p class="muted">{{ __('common.float_account_not_found') }}</p>
                        @endif
                    </div>

                    <!-- Commission Account -->
                    <div style="padding: 20px; background: var(--bg-tertiary); border-radius: 8px; border: 1px solid var(--border-color);">
                        <h3 style="color: var(--accent-color); margin-bottom: 15px;">{{ __('common.commission_account') }}</h3>
                        @if($agent->commissionAccount())
                            @php $commissionAccount = $agent->commissionAccount(); @endphp
                            <div style="display: grid; gap: 10px;">
                                <div>
                                    <strong class="text-muted-sm">{{ __('common.account_number') }}:</strong>
                                    <p style="color: var(--text-primary); margin-top: 5px;"><code style="background: var(--bg-primary); padding: 4px 8px; border-radius: 4px;">{{ $commissionAccount->account_number }}</code></p>
                                </div>
                                <div>
                                    <strong class="text-muted-sm">{{ __('common.balance') }}:</strong>
                                    <p style="color: var(--text-primary); margin-top: 5px; font-size: 20px; font-weight: bold;">{{ number_format($commissionAccount->balance, 2) }} {{ $commissionAccount->currency }}</p>
                                </div>
                                <div>
                                    <strong class="text-muted-sm">{{ __('common.status') }}:</strong>
                                    <p style="color: var(--text-primary); margin-top: 5px; text-transform: capitalize;">{{ $commissionAccount->status }}</p>
                                </div>
                                <div>
                                    <strong class="text-muted-sm">{{ __('common.opening_date') }}:</strong>
                                    <p style="color: var(--text-primary); margin-top: 5px;">{{ $commissionAccount->opening_date->format('Y-m-d') }}</p>
                                </div>
                                <div style="margin-top: 10px;">
                                    <a href="{{ route('accounts.show', $commissionAccount->id) }}" class="btn btn-sm">{{ __('common.view_details') }}</a>
                                </div>
                            </div>
                        @else
                            <p class="muted">{{ __('common.commission_account_not_found') }}</p>
                        @endif
                    </div>
                </div>
            </div>

        @elseif(($activeTab ?? 'information') == 'terminals')
            <!-- Terminals Tab -->
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 class="text-accent">{{ __('common.assigned_terminals') }} ({{ $agent->terminals->count() }})</h2>
                    @if($availableTerminals->count() > 0)
                        <button onclick="document.getElementById('assign-terminal-form').style.display = 'block';" class="btn btn-sm">+ {{ __('common.assign_terminal') }}</button>
                    @endif
                </div>

                <!-- Assign Terminal Form -->
                @if($availableTerminals->count() > 0)
                <div id="assign-terminal-form" style="display: none; margin-bottom: 20px; padding: 15px; background: var(--bg-tertiary); border-radius: 4px; border: 1px solid var(--border-color);">
                    <form method="POST" action="{{ route('agents.assign-terminal', $agent->id) }}">
                        @csrf
                        <div style="display: flex; gap: 10px; align-items: end;">
                            <div style="flex: 1;">
                                <label class="form-label" for="terminal_id">{{ __('common.select_terminal') }}</label>
                                <select id="terminal_id" name="terminal_id" class="form-select" required>
                                    <option value="">{{ __('common.select_terminal') }}</option>
                                    @foreach($availableTerminals as $terminal)
                                        <option value="{{ $terminal->id }}">{{ $terminal->device_alias }} ({{ $terminal->serial_number }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn">{{ __('common.assign') }}</button>
                            <button type="button" onclick="document.getElementById('assign-terminal-form').style.display = 'none';" class="btn btn-secondary">{{ __('common.cancel') }}</button>
                        </div>
                    </form>
                </div>
                @endif

                @if($agent->terminals->count() > 0)
                    <div class="data-table">
                        <table>
                            <thead>
                                <tr style="border-bottom: 2px solid var(--border-color);">
                                    <th>{{ __('common.serial_number') }}</th>
                                    <th>{{ __('common.device_alias') }}</th>
                                    <th>{{ __('common.model_make') }}</th>
                                    <th>{{ __('common.assigned_date') }}</th>
                                    <th>{{ __('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($agent->terminals as $terminal)
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td>{{ $terminal->serial_number }}</td>
                                        <td>{{ $terminal->device_alias }}</td>
                                        <td>{{ $terminal->model_make }}</td>
                                        <td>
                                            @if($terminal->pivot->assigned_at)
                                                {{ \Carbon\Carbon::parse($terminal->pivot->assigned_at)->format('Y-m-d H:i') }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            <form action="{{ route('agents.unassign-terminal', [$agent->id, $terminal->id]) }}" method="POST" style="display: inline;" onsubmit="return confirm('{{ __('common.confirm_unassign') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm">{{ __('common.unassign') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="muted-center">
                        <p style="margin-bottom: 10px;">{{ __('common.no_terminals_assigned') }}</p>
                        @if($availableTerminals->count() > 0)
                            <button onclick="document.getElementById('assign-terminal-form').style.display = 'block';" class="btn" style="margin-top: 10px;">{{ __('common.assign_terminal') }}</button>
                        @else
                            <p style="margin-top: 10px; font-size: 12px;">{{ __('common.no_available_terminals') }}</p>
                        @endif
                    </div>
                @endif

                <!-- SUNU card write rights (TPE "Écrire la carte") -->
                @php($canManageCardRights = auth()->user()->hasPermission('manage-agent-card-rights', $agent->business_unit_id))
                <div style="margin-top: 30px; padding: 15px; background: var(--bg-tertiary); border-radius: 4px; border: 1px solid var(--border-color);">
                    <h2 class="text-accent" style="margin-bottom: 6px;">{{ __('common.card_rights') }}</h2>
                    <p class="muted" style="margin-bottom: 15px; font-size: 13px;">{{ __('common.card_rights_help') }}</p>
                    @if($canManageCardRights)
                        <form method="POST" action="{{ route('agents.card-rights', $agent->id) }}">
                            @csrf
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" name="can_write_cards" value="1" {{ $agent->can_write_cards ? 'checked' : '' }}>
                                <strong>{{ __('common.card_right_write') }}</strong>
                                <span class="muted" style="font-size: 12px;">— {{ __('common.card_right_write_help') }}</span>
                            </label>
                            <label style="display: block; margin-bottom: 12px;">
                                <input type="checkbox" name="can_rewrite_cards" value="1" {{ $agent->can_rewrite_cards ? 'checked' : '' }}>
                                <strong>{{ __('common.card_right_rewrite') }}</strong>
                                <span class="muted" style="font-size: 12px;">— {{ __('common.card_right_rewrite_help') }}</span>
                            </label>
                            <button type="submit" class="btn btn-sm">{{ __('common.save') }}</button>
                        </form>
                    @else
                        <p>{{ __('common.card_right_write') }}: <strong>{{ $agent->can_write_cards ? __('common.yes') : __('common.no') }}</strong></p>
                        <p>{{ __('common.card_right_rewrite') }}: <strong>{{ $agent->can_rewrite_cards ? __('common.yes') : __('common.no') }}</strong></p>
                    @endif
                </div>

                @include('agents._collector_cap')
            </div>

        @elseif(($activeTab ?? 'information') == 'transactions' && config('sunu.core_banking'))
            <!-- Transaction History Tab -->
            <div>
                <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.transaction_history') }}</h2>

                <!-- Filters -->
                <form method="GET" action="{{ route('agents.show', ['agent' => $agent->id, 'tab' => 'transactions']) }}" style="margin-bottom: 20px; padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                        <div class="form-group">
                            <label class="form-label">{{ __('common.start_date') }}</label>
                            <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ __('common.end_date') }}</label>
                            <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ __('common.status') }}</label>
                            <select name="status" class="form-select">
                                <option value="">{{ __('common.all') }}</option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>{{ __('common.pending') }}</option>
                                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>{{ __('common.completed') }}</option>
                                <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>{{ __('common.failed') }}</option>
                                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>{{ __('common.cancelled') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="btn-row">
                        <button type="submit" class="btn">{{ __('common.filter') }}</button>
                        <a href="{{ route('agents.show', ['agent' => $agent->id, 'tab' => 'transactions']) }}" class="btn btn-secondary">{{ __('common.clear') }}</a>
                    </div>
                </form>

                @if($transactions->count() > 0)
                    <div class="data-table">
                        <table>
                            <thead>
                                <tr style="border-bottom: 2px solid var(--border-color);">
                                    <th>{{ __('common.transaction_date') }}</th>
                                    <th>{{ __('common.transaction_type') }}</th>
                                    <th style="padding: 12px; text-align: right; color: var(--accent-color);">{{ __('common.transaction_amount') }}</th>
                                    <th style="padding: 12px; text-align: right; color: var(--accent-color);">{{ __('common.transaction_commission') }}</th>
                                    <th>{{ __('common.terminal_serial') }}</th>
                                    <th>{{ __('common.client') }}</th>
                                    <th>{{ __('common.status') }}</th>
                                    <th>{{ __('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transactions as $transaction)
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td>
                                            {{ $transaction->transaction_date->format('Y-m-d H:i') }}
                                        </td>
                                        <td>
                                            <strong>{{ $transaction->transactionType->name }}</strong>
                                            <br>
                                            <code style="background: var(--bg-tertiary); padding: 2px 6px; border-radius: 4px; font-size: 11px;">{{ $transaction->transactionType->code }}</code>
                                        </td>
                                        <td style="padding: 12px; text-align: right; font-weight: bold;">
                                            {{ number_format($transaction->transaction_amount, 2) }}
                                        </td>
                                        <td style="padding: 12px; text-align: right; color: var(--success-text);">
                                            {{ number_format($transaction->commission, 2) }}
                                        </td>
                                        <td>
                                            {{ $transaction->terminal ? $transaction->terminal->serial_number : '-' }}
                                        </td>
                                        <td>
                                            {{ $transaction->client ? $transaction->client->name : '-' }}
                                        </td>
                                        <td>
                                            @if($transaction->status === 'completed')
                                                <span class="badge badge-success">{{ __('common.completed') }}</span>
                                            @elseif($transaction->status === 'pending')
                                                <span class="badge badge-warning">{{ __('common.pending') }}</span>
                                            @elseif($transaction->status === 'failed')
                                                <span class="badge badge-danger">{{ __('common.failed') }}</span>
                                            @else
                                                <span class="badge">{{ __('common.cancelled') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('transactions.show', $transaction->id) }}" class="btn btn-sm">{{ __('common.view') }}</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-20">
                        {{ $transactions->links() }}
                    </div>
                @else
                    <div class="muted-center">
                        <p class="mb-20">{{ __('common.no_transactions') }}</p>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>

@push('styles')
<style>
    .tab-link {
        position: relative;
    }
    
    .tab-link:hover {
        color: var(--accent-color);
        background: var(--bg-tertiary);
    }
    
    .tab-link.active {
        color: var(--accent-color);
        border-bottom-color: var(--accent-color) !important;
    }
</style>
@endpush
@endsection
