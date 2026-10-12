@extends('layouts.app')

@section('title', __('common.transactions'))
@section('page-title', __('common.transactions'))

@section('content')
@php
    $user = auth()->user();
    $businessUnit = $user->currentBusinessUnit;
    $canViewTransactions = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-transactions', $businessUnit->id));
@endphp

@if(!$canViewTransactions)
    <div class="card">
        <div style="text-align: center; padding: 40px; color: var(--error-text);">
            <p>{{ __('common.unauthorized_access') }}</p>
            <a href="{{ route('business.dashboard') }}" class="btn">{{ __('common.back_to_dashboard') }}</a>
        </div>
    </div>
@else
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <h1 class="text-accent">{{ __('common.transactions') }}</h1>
        <div style="display: flex; gap: 10px; align-items: center;">
            <div style="position: relative;">
                <button onclick="toggleExportDropdown()" class="btn" style="display: flex; align-items: center; gap: 5px;">
                    <span>📥</span>
                    <span>{{ __('common.export') }}</span>
                    <span>▼</span>
                </button>
                <div id="exportDropdown" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 5px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); z-index: 1000; min-width: 150px;">
                    <a href="{{ route('transactions.export', array_merge(request()->all(), ['format' => 'csv'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">
                        {{ __('common.export_as_csv') }}
                    </a>
                    <a href="{{ route('transactions.export', array_merge(request()->all(), ['format' => 'excel'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">
                        {{ __('common.export_as_excel') }}
                    </a>
                    <a href="{{ route('transactions.export', array_merge(request()->all(), ['format' => 'pdf'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary);">
                        {{ __('common.export_as_pdf') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('transactions.index') }}" style="margin-bottom: 20px; padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
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
                <label class="form-label">{{ __('common.transaction_type') }}</label>
                <select name="transaction_type_id" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    @foreach($transactionTypes as $type)
                        <option value="{{ $type->id }}" {{ request('transaction_type_id') == $type->id ? 'selected' : '' }}>
                            {{ $type->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.agent') }}</label>
                <select name="agent_id" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}" {{ request('agent_id') == $agent->id ? 'selected' : '' }}>
                            {{ $agent->full_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.terminal') }}</label>
                <select name="terminal_id" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    @foreach($terminals as $terminal)
                        <option value="{{ $terminal->id }}" {{ request('terminal_id') == $terminal->id ? 'selected' : '' }}>
                            {{ $terminal->serial_number }}
                        </option>
                    @endforeach
                </select>
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
            <input type="text" name="search" class="form-control" placeholder="{{ __('common.search') }}..." value="{{ request('search') }}" style="flex: 1;">
            <button type="submit" class="btn">{{ __('common.filter') }}</button>
            <a href="{{ route('transactions.index') }}" class="btn btn-secondary">{{ __('common.clear') }}</a>
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
                        <th style="padding: 12px; text-align: right; color: var(--accent-color);">{{ __('common.transaction_cost') }}</th>
                        <th style="padding: 12px; text-align: right; color: var(--accent-color);">{{ __('common.transaction_commission') }}</th>
                        <th>{{ __('common.agent') }}</th>
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
                            <td style="padding: 12px; text-align: right;">
                                {{ number_format($transaction->transaction_cost, 2) }}
                            </td>
                            <td style="padding: 12px; text-align: right; color: var(--success-text);">
                                {{ number_format($transaction->commission, 2) }}
                            </td>
                            <td>
                                {{ $transaction->agent ? $transaction->agent->full_name : '-' }}
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

<script>
function toggleExportDropdown() {
    const dropdown = document.getElementById('exportDropdown');
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('exportDropdown');
    const button = event.target.closest('button[onclick="toggleExportDropdown()"]');
    if (!button && !dropdown.contains(event.target)) {
        dropdown.style.display = 'none';
    }
});
</script>
@endsection

