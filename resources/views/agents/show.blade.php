@extends('layouts.app')

@section('title', __('common.view_agent'))
@section('page-title', __('common.view_agent'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="color: var(--accent-color);">{{ __('common.agent_details') }}</h1>
        <div style="display: flex; gap: 10px;">
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
            @endif
            <a href="{{ route('agents.index') }}" class="btn btn-secondary">{{ __('common.back') }}</a>
        </div>
    </div>

    <!-- Agent Header with Photo -->
    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 30px; padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
        @if($agent->photo)
            <img src="{{ asset('storage/' . $agent->photo) }}" alt="{{ $agent->full_name }}" style="width: 120px; height: 120px; border-radius: 8px; object-fit: cover; border: 2px solid var(--border-color);">
        @else
            <div style="width: 120px; height: 120px; border-radius: 8px; background: var(--bg-primary); border: 2px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 48px; color: var(--text-muted);">
                👤
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
            <a href="{{ route('agents.show', ['agent' => $agent->id, 'tab' => 'accounts']) }}" 
               class="tab-link {{ ($activeTab ?? 'information') == 'accounts' ? 'active' : '' }}" 
               style="padding: 12px 24px; text-decoration: none; color: var(--text-secondary); border-bottom: 3px solid transparent; transition: all 0.3s; font-weight: 500;">
                {{ __('common.accounts') }}
            </a>
            <a href="{{ route('agents.show', ['agent' => $agent->id, 'tab' => 'terminals']) }}" 
               class="tab-link {{ ($activeTab ?? 'information') == 'terminals' ? 'active' : '' }}" 
               style="padding: 12px 24px; text-decoration: none; color: var(--text-secondary); border-bottom: 3px solid transparent; transition: all 0.3s; font-weight: 500;">
                {{ __('common.terminals') }} ({{ $agent->terminals->count() }})
            </a>
            <a href="{{ route('agents.show', ['agent' => $agent->id, 'tab' => 'transactions']) }}" 
               class="tab-link {{ ($activeTab ?? 'information') == 'transactions' ? 'active' : '' }}" 
               style="padding: 12px 24px; text-decoration: none; color: var(--text-secondary); border-bottom: 3px solid transparent; transition: all 0.3s; font-weight: 500;">
                {{ __('common.transaction_history') }}
            </a>
        </div>
    </div>

    <!-- Tab Content -->
    <div class="tab-content">
        @if(($activeTab ?? 'information') == 'information')
            <!-- Information Tab -->
            <div>
                <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.personal_information') }}</h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div>
                        <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.name') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $agent->full_name }}</p>
                    </div>
                    
                    <div>
                        <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.username') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $agent->username }}</p>
                    </div>

                    <div>
                        <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.email') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $agent->email }}</p>
                    </div>

                    @if($agent->phone_number)
                    <div>
                        <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.phone_number') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $agent->phone_number }}</p>
                    </div>
                    @endif

                    @if($agent->id_passport)
                    <div>
                        <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.id_passport') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $agent->id_passport }}</p>
                    </div>
                    @endif

                    @if($agent->physical_address)
                    <div>
                        <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.physical_address') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $agent->physical_address }}</p>
                    </div>
                    @endif

                    @if($agent->po_box)
                    <div>
                        <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.po_box') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $agent->po_box }}</p>
                    </div>
                    @endif
                </div>
            </div>

        @elseif(($activeTab ?? 'information') == 'accounts')
            <!-- Accounts Tab -->
            <div>
                <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.accounts') }}</h2>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <!-- Float Account -->
                    <div style="padding: 20px; background: var(--bg-tertiary); border-radius: 8px; border: 1px solid var(--border-color);">
                        <h3 style="color: var(--accent-color); margin-bottom: 15px;">{{ __('common.float_account') }}</h3>
                        @if($agent->floatAccount())
                            @php $floatAccount = $agent->floatAccount(); @endphp
                            <div style="display: grid; gap: 10px;">
                                <div>
                                    <strong style="color: var(--text-muted); font-size: 12px;">{{ __('common.account_number') }}:</strong>
                                    <p style="color: var(--text-primary); margin-top: 5px;"><code style="background: var(--bg-primary); padding: 4px 8px; border-radius: 4px;">{{ $floatAccount->account_number }}</code></p>
                                </div>
                                <div>
                                    <strong style="color: var(--text-muted); font-size: 12px;">{{ __('common.balance') }}:</strong>
                                    <p style="color: var(--text-primary); margin-top: 5px; font-size: 20px; font-weight: bold;">{{ number_format($floatAccount->balance, 2) }} {{ $floatAccount->currency }}</p>
                                </div>
                                <div>
                                    <strong style="color: var(--text-muted); font-size: 12px;">{{ __('common.status') }}:</strong>
                                    <p style="color: var(--text-primary); margin-top: 5px; text-transform: capitalize;">{{ $floatAccount->status }}</p>
                                </div>
                                <div>
                                    <strong style="color: var(--text-muted); font-size: 12px;">{{ __('common.opening_date') }}:</strong>
                                    <p style="color: var(--text-primary); margin-top: 5px;">{{ $floatAccount->opening_date->format('Y-m-d') }}</p>
                                </div>
                                <div style="margin-top: 10px;">
                                    <a href="{{ route('accounts.show', $floatAccount->id) }}" class="btn" style="padding: 8px 16px; font-size: 12px;">{{ __('common.view_details') }}</a>
                                </div>
                            </div>
                        @else
                            <p style="color: var(--text-muted);">{{ __('common.float_account_not_found') }}</p>
                        @endif
                    </div>

                    <!-- Commission Account -->
                    <div style="padding: 20px; background: var(--bg-tertiary); border-radius: 8px; border: 1px solid var(--border-color);">
                        <h3 style="color: var(--accent-color); margin-bottom: 15px;">{{ __('common.commission_account') }}</h3>
                        @if($agent->commissionAccount())
                            @php $commissionAccount = $agent->commissionAccount(); @endphp
                            <div style="display: grid; gap: 10px;">
                                <div>
                                    <strong style="color: var(--text-muted); font-size: 12px;">{{ __('common.account_number') }}:</strong>
                                    <p style="color: var(--text-primary); margin-top: 5px;"><code style="background: var(--bg-primary); padding: 4px 8px; border-radius: 4px;">{{ $commissionAccount->account_number }}</code></p>
                                </div>
                                <div>
                                    <strong style="color: var(--text-muted); font-size: 12px;">{{ __('common.balance') }}:</strong>
                                    <p style="color: var(--text-primary); margin-top: 5px; font-size: 20px; font-weight: bold;">{{ number_format($commissionAccount->balance, 2) }} {{ $commissionAccount->currency }}</p>
                                </div>
                                <div>
                                    <strong style="color: var(--text-muted); font-size: 12px;">{{ __('common.status') }}:</strong>
                                    <p style="color: var(--text-primary); margin-top: 5px; text-transform: capitalize;">{{ $commissionAccount->status }}</p>
                                </div>
                                <div>
                                    <strong style="color: var(--text-muted); font-size: 12px;">{{ __('common.opening_date') }}:</strong>
                                    <p style="color: var(--text-primary); margin-top: 5px;">{{ $commissionAccount->opening_date->format('Y-m-d') }}</p>
                                </div>
                                <div style="margin-top: 10px;">
                                    <a href="{{ route('accounts.show', $commissionAccount->id) }}" class="btn" style="padding: 8px 16px; font-size: 12px;">{{ __('common.view_details') }}</a>
                                </div>
                            </div>
                        @else
                            <p style="color: var(--text-muted);">{{ __('common.commission_account_not_found') }}</p>
                        @endif
                    </div>
                </div>
            </div>

        @elseif(($activeTab ?? 'information') == 'terminals')
            <!-- Terminals Tab -->
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="color: var(--accent-color);">{{ __('common.assigned_terminals') }} ({{ $agent->terminals->count() }})</h2>
                    @if($availableTerminals->count() > 0)
                        <button onclick="document.getElementById('assign-terminal-form').style.display = 'block';" class="btn" style="padding: 8px 16px; font-size: 12px;">+ {{ __('common.assign_terminal') }}</button>
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
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--border-color);">
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.serial_number') }}</th>
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.device_alias') }}</th>
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.model_make') }}</th>
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.assigned_date') }}</th>
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($agent->terminals as $terminal)
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 12px;">{{ $terminal->serial_number }}</td>
                                        <td style="padding: 12px;">{{ $terminal->device_alias }}</td>
                                        <td style="padding: 12px;">{{ $terminal->model_make }}</td>
                                        <td style="padding: 12px;">
                                            @if($terminal->pivot->assigned_at)
                                                {{ \Carbon\Carbon::parse($terminal->pivot->assigned_at)->format('Y-m-d H:i') }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td style="padding: 12px;">
                                            <form action="{{ route('agents.unassign-terminal', [$agent->id, $terminal->id]) }}" method="POST" style="display: inline;" onsubmit="return confirm('{{ __('common.confirm_unassign') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;">{{ __('common.unassign') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <p style="margin-bottom: 10px;">{{ __('common.no_terminals_assigned') }}</p>
                        @if($availableTerminals->count() > 0)
                            <button onclick="document.getElementById('assign-terminal-form').style.display = 'block';" class="btn" style="margin-top: 10px;">{{ __('common.assign_terminal') }}</button>
                        @else
                            <p style="margin-top: 10px; font-size: 12px;">{{ __('common.no_available_terminals') }}</p>
                        @endif
                    </div>
                @endif
            </div>

        @elseif(($activeTab ?? 'information') == 'transactions')
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
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn">{{ __('common.filter') }}</button>
                        <a href="{{ route('agents.show', ['agent' => $agent->id, 'tab' => 'transactions']) }}" class="btn btn-secondary">{{ __('common.clear') }}</a>
                    </div>
                </form>

                @if($transactions->count() > 0)
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--border-color);">
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.transaction_date') }}</th>
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.transaction_type') }}</th>
                                    <th style="padding: 12px; text-align: right; color: var(--accent-color);">{{ __('common.transaction_amount') }}</th>
                                    <th style="padding: 12px; text-align: right; color: var(--accent-color);">{{ __('common.transaction_commission') }}</th>
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.terminal_serial') }}</th>
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.client') }}</th>
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.status') }}</th>
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transactions as $transaction)
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 12px;">
                                            {{ $transaction->transaction_date->format('Y-m-d H:i') }}
                                        </td>
                                        <td style="padding: 12px;">
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
                                        <td style="padding: 12px;">
                                            {{ $transaction->terminal ? $transaction->terminal->serial_number : '-' }}
                                        </td>
                                        <td style="padding: 12px;">
                                            {{ $transaction->client ? $transaction->client->name : '-' }}
                                        </td>
                                        <td style="padding: 12px;">
                                            @if($transaction->status === 'completed')
                                                <span style="color: var(--success-text);">{{ __('common.completed') }}</span>
                                            @elseif($transaction->status === 'pending')
                                                <span style="color: #ffa500;">{{ __('common.pending') }}</span>
                                            @elseif($transaction->status === 'failed')
                                                <span style="color: var(--error-text);">{{ __('common.failed') }}</span>
                                            @else
                                                <span style="color: var(--text-muted);">{{ __('common.cancelled') }}</span>
                                            @endif
                                        </td>
                                        <td style="padding: 12px;">
                                            <a href="{{ route('transactions.show', $transaction->id) }}" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.view') }}</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 20px;">
                        {{ $transactions->links() }}
                    </div>
                @else
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <p style="margin-bottom: 20px;">{{ __('common.no_transactions') }}</p>
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
