@extends('layouts.app')

@section('title', __('common.transaction_reports'))
@section('page-title', __('common.transaction_reports'))

@section('content')
@php
    $user = auth()->user();
    $businessUnit = $user->currentBusinessUnit;
    $canRequestReports = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('request-transaction-reports', $businessUnit->id));
@endphp

@if(!$canRequestReports)
    <div class="card">
        <div style="text-align: center; padding: 40px; color: var(--error-text);">
            <p>{{ __('common.unauthorized_access') }}</p>
            <a href="{{ route('business.dashboard') }}" class="btn">{{ __('common.back_to_dashboard') }}</a>
        </div>
    </div>
@else
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="color: var(--accent-color);">{{ __('common.transaction_reports') }}</h1>
        <a href="{{ route('business.reports.index') }}" class="btn btn-secondary">{{ __('common.back') }}</a>
    </div>

    <!-- Request Form -->
    <div style="margin-bottom: 30px; padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
        <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.request_new_report') }}</h2>
        <form method="POST" action="{{ route('business.reports.transactions.request') }}">
            @csrf
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label class="form-label">{{ __('common.start_date') }}</label>
                    <input type="date" name="start_date" class="form-control" value="{{ old('start_date') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('common.end_date') }}</label>
                    <input type="date" name="end_date" class="form-control" value="{{ old('end_date') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('common.transaction_type') }}</label>
                    <select name="transaction_type_id" class="form-select">
                        <option value="">{{ __('common.all') }}</option>
                        @foreach($transactionTypes as $type)
                            <option value="{{ $type->id }}" {{ old('transaction_type_id') == $type->id ? 'selected' : '' }}>
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
                            <option value="{{ $agent->id }}" {{ old('agent_id') == $agent->id ? 'selected' : '' }}>
                                {{ $agent->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('common.client') }}</label>
                    <select name="client_id" class="form-select">
                        <option value="">{{ __('common.all') }}</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>
                                {{ $client->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('common.terminal') }}</label>
                    <select name="terminal_id" class="form-select">
                        <option value="">{{ __('common.all') }}</option>
                        @foreach($terminals as $terminal)
                            <option value="{{ $terminal->id }}" {{ old('terminal_id') == $terminal->id ? 'selected' : '' }}>
                                {{ $terminal->serial_number }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('common.status') }}</label>
                    <select name="status" class="form-select">
                        <option value="">{{ __('common.all') }}</option>
                        <option value="completed" {{ old('status') == 'completed' ? 'selected' : '' }}>{{ __('common.completed') }}</option>
                        <option value="pending" {{ old('status') == 'pending' ? 'selected' : '' }}>{{ __('common.pending') }}</option>
                        <option value="failed" {{ old('status') == 'failed' ? 'selected' : '' }}>{{ __('common.failed') }}</option>
                        <option value="cancelled" {{ old('status') == 'cancelled' ? 'selected' : '' }}>{{ __('common.cancelled') }}</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn">{{ __('common.generate_report') }}</button>
        </form>
    </div>

    <!-- Previous Reports -->
    <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.previous_reports') }}</h2>
    @if($reports->count() > 0)
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.status') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.requested_by') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.created_at') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.total_records') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reports as $report)
                        <tr style="border-bottom: 1px solid var(--border-color);" data-report-id="{{ $report->id }}">
                            <td style="padding: 12px;">
                                <span class="report-status" style="padding: 4px 8px; border-radius: 4px; font-size: 12px; text-transform: capitalize;
                                    @if($report->status === 'completed') background: var(--success-bg); color: var(--success-text);
                                    @elseif($report->status === 'processing') background: var(--warning-bg); color: var(--warning-text);
                                    @elseif($report->status === 'failed') background: var(--error-bg); color: var(--error-text);
                                    @else background: var(--bg-tertiary); color: var(--text-muted);
                                    @endif">
                                    {{ $report->status }}
                                </span>
                            </td>
                            <td style="padding: 12px;">{{ $report->user->name ?? __('common.unknown') }}</td>
                            <td style="padding: 12px;">{{ $report->created_at->format('Y-m-d H:i') }}</td>
                            <td style="padding: 12px;">{{ $report->total_records ?? '-' }}</td>
                            <td style="padding: 12px;">
                                <div style="display: flex; gap: 5px;">
                                    @if($report->isReady())
                                        <a href="{{ route('business.reports.download', $report->id) }}" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.download') }}</a>
                                    @endif
                                    @if($report->canRetry())
                                        <form action="{{ route('business.reports.retry', $report->id) }}" method="POST" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">{{ __('common.retry') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top: 20px;">
            {{ $reports->links() }}
        </div>
    @else
        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
            <p>{{ __('common.no_reports_found') }}</p>
        </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const pendingReports = document.querySelectorAll('tr[data-report-id]');
    pendingReports.forEach(function(row) {
        const statusElement = row.querySelector('.report-status');
        if (!statusElement) return;
        const status = statusElement.textContent.trim().toLowerCase();
        if (status === 'pending' || status === 'processing') {
            const reportId = row.getAttribute('data-report-id');
            pollReportStatus(reportId, row);
        }
    });
    
    function pollReportStatus(reportId, row) {
        const interval = setInterval(function() {
            fetch(`{{ route('business.reports.status', '') }}/${reportId}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                const statusElement = row.querySelector('.report-status');
                const status = data.status.toLowerCase();
                statusElement.textContent = status;
                statusElement.className = 'report-status';
                statusElement.style.padding = '4px 8px';
                statusElement.style.borderRadius = '4px';
                statusElement.style.fontSize = '12px';
                statusElement.style.textTransform = 'capitalize';
                
                if (status === 'completed') {
                    statusElement.style.background = 'var(--success-bg)';
                    statusElement.style.color = 'var(--success-text)';
                    clearInterval(interval);
                    setTimeout(() => location.reload(), 2000);
                } else if (status === 'failed') {
                    statusElement.style.background = 'var(--error-bg)';
                    statusElement.style.color = 'var(--error-text)';
                    clearInterval(interval);
                } else if (status === 'processing') {
                    statusElement.style.background = 'var(--warning-bg)';
                    statusElement.style.color = 'var(--warning-text)';
                }
            })
            .catch(error => {
                console.error('Error polling report status:', error);
                clearInterval(interval);
            });
        }, 3000);
        setTimeout(() => clearInterval(interval), 300000);
    }
});
</script>
@endpush
@endif
@endsection
