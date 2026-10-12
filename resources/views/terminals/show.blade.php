@extends('layouts.app')

@section('title', __('common.view_terminal'))
@section('page-title', __('common.view_terminal'))

@section('content')
<div class="card">
    <div class="toolbar">
        <h1 class="text-accent">{{ __('common.terminal_details') }}</h1>
        <div class="btn-row">
            @php
                $user = auth()->user();
                $businessUnit = $user->currentBusinessUnit;
                $canEdit = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('edit-terminal', $businessUnit->id));
            @endphp
            @if($canEdit)
                <a href="{{ route('terminals.edit', $terminal->id) }}" class="btn">{{ __('common.edit') }}</a>
            @endif
            <a href="{{ route('terminals.index') }}" class="btn btn-secondary">{{ __('common.back') }}</a>
        </div>
    </div>

    <!-- Terminal Header -->
    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 30px; padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
        <div style="width: 120px; height: 120px; border-radius: 8px; background: var(--bg-primary); border: 2px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 48px; color: var(--text-muted);">
            💻
        </div>
        <div>
            <h2 style="color: var(--accent-color); margin-bottom: 5px;">{{ $terminal->device_alias }}</h2>
            <p style="color: var(--text-secondary); margin: 5px 0;">{{ __('common.serial_number') }}: {{ $terminal->serial_number }}</p>
            <p style="color: var(--text-secondary); margin: 5px 0;">{{ __('common.simcard_number') }}: {{ $terminal->simcard_number }}</p>
            @if($terminal->model_make)
                <p style="color: var(--text-secondary); margin: 5px 0;">{{ __('common.model_make') }}: {{ $terminal->model_make }}</p>
            @endif
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div style="border-bottom: 2px solid var(--border-color); margin-bottom: 30px;">
        <div style="display: flex; gap: 0;">
            <a href="{{ route('terminals.show', ['terminal' => $terminal->id, 'tab' => 'information']) }}" 
               class="tab-link {{ ($activeTab ?? 'information') == 'information' ? 'active' : '' }}" 
               style="padding: 12px 24px; text-decoration: none; color: var(--text-secondary); border-bottom: 3px solid transparent; transition: all 0.3s; font-weight: 500;">
                {{ __('common.information') }}
            </a>
            <a href="{{ route('terminals.show', ['terminal' => $terminal->id, 'tab' => 'agents']) }}" 
               class="tab-link {{ ($activeTab ?? 'information') == 'agents' ? 'active' : '' }}" 
               style="padding: 12px 24px; text-decoration: none; color: var(--text-secondary); border-bottom: 3px solid transparent; transition: all 0.3s; font-weight: 500;">
                {{ __('common.agents') }} ({{ $terminal->agents->count() }})
            </a>
            @if(config('sunu.core_banking'))
            <a href="{{ route('terminals.show', ['terminal' => $terminal->id, 'tab' => 'transactions']) }}" 
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
                <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.terminal_information') }}</h2>
                
                <div class="detail-grid">
                    <div>
                        <strong class="detail-label">{{ __('common.serial_number') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;"><code style="background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px;">{{ $terminal->serial_number }}</code></p>
                    </div>
                    
                    <div>
                        <strong class="detail-label">{{ __('common.simcard_number') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $terminal->simcard_number }}</p>
                    </div>

                    <div>
                        <strong class="detail-label">{{ __('common.device_alias') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $terminal->device_alias }}</p>
                    </div>

                    @if($terminal->model_make)
                    <div>
                        <strong class="detail-label">{{ __('common.model_make') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $terminal->model_make }}</p>
                    </div>
                    @endif
                </div>
            </div>

        @elseif(($activeTab ?? 'information') == 'agents')
            <!-- Agents Tab -->
            <div>
                <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.assigned_agents') }}</h2>
                
                <!-- Current Assignment Section -->
                <div style="margin-bottom: 30px;">
                    <h3 style="color: var(--accent-color); margin-bottom: 15px;">{{ __('common.current_assignment') }}</h3>
                    
                    @if($terminal->agents->count() > 0)
                        @foreach($terminal->agents as $agent)
                            <div style="padding: 15px; background: var(--bg-tertiary); border-radius: 4px; border: 1px solid var(--border-color); margin-bottom: 15px;">
                                <div style="display: grid; gap: 10px;">
                                    <div>
                                        <strong class="text-accent">{{ __('common.assigned_to') }}:</strong>
                                        <p style="color: var(--text-primary); margin-top: 5px;">
                                            <a href="{{ route('agents.show', $agent->id) }}" style="color: var(--accent-color); text-decoration: none;">
                                                {{ $agent->full_name }} ({{ $agent->username }})
                                            </a>
                                        </p>
                                    </div>
                                    <div>
                                        <strong class="text-accent">{{ __('common.assigned_date') }}:</strong>
                                        <p style="color: var(--text-primary); margin-top: 5px;">
                                            @if($agent->pivot->assigned_at)
                                                {{ \Carbon\Carbon::parse($agent->pivot->assigned_at)->format('Y-m-d H:i') }}
                                            @else
                                                -
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div style="padding: 20px; background: var(--bg-tertiary); border-radius: 4px; border: 1px solid var(--border-color); text-align: center; color: var(--text-muted);">
                            <p>{{ __('common.no_current_assignment') }}</p>
                        </div>
                    @endif
                </div>

                <!-- Assignment History Section -->
                <div style="margin-top: 30px;">
                    <h3 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.assignment_history') }}</h3>
                    
                    @if($terminal->agentAssignments->count() > 0)
                        <div class="data-table">
                            <table>
                                <thead>
                                    <tr style="border-bottom: 2px solid var(--border-color);">
                                        <th>{{ __('common.agent') }}</th>
                                        <th>{{ __('common.assigned_date') }}</th>
                                        <th>{{ __('common.unassigned_date') }}</th>
                                        <th>{{ __('common.status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($terminal->agentAssignments as $agent)
                                        <tr style="border-bottom: 1px solid var(--border-color);">
                                            <td>
                                                <a href="{{ route('agents.show', $agent->id) }}" style="color: var(--accent-color); text-decoration: none;">
                                                    {{ $agent->full_name }} ({{ $agent->username }})
                                                </a>
                                            </td>
                                            <td>
                                                @if($agent->pivot->assigned_at)
                                                    {{ \Carbon\Carbon::parse($agent->pivot->assigned_at)->format('Y-m-d H:i') }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                @if($agent->pivot->unassigned_at)
                                                    {{ \Carbon\Carbon::parse($agent->pivot->unassigned_at)->format('Y-m-d H:i') }}
                                                @else
                                                    <span class="muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($agent->pivot->unassigned_at)
                                                    <span class="text-error">{{ __('common.unassigned') }}</span>
                                                @else
                                                    <span class="badge badge-success">{{ __('common.assigned') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div style="padding: 20px; background: var(--bg-tertiary); border-radius: 4px; border: 1px solid var(--border-color); text-align: center; color: var(--text-muted);">
                            <p>{{ __('common.no_assignment_history') }}</p>
                        </div>
                    @endif
                </div>
            </div>

        @elseif(($activeTab ?? 'information') == 'transactions' && config('sunu.core_banking'))
            <!-- Transaction History Tab -->
            <div>
                <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.transaction_history') }}</h2>

                <!-- Filters -->
                <form method="GET" action="{{ route('terminals.show', ['terminal' => $terminal->id, 'tab' => 'transactions']) }}" style="margin-bottom: 20px; padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
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
                        <a href="{{ route('terminals.show', ['terminal' => $terminal->id, 'tab' => 'transactions']) }}" class="btn btn-secondary">{{ __('common.clear') }}</a>
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
                                    <th>{{ __('common.agent') }}</th>
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
                                        <td>
                                            {{ $transaction->agent ? $transaction->agent->full_name : '-' }}
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
                                                <span class="text-error">{{ __('common.failed') }}</span>
                                            @else
                                                <span class="muted">{{ __('common.cancelled') }}</span>
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
