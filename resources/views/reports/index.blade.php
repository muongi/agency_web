@extends('layouts.app')

@section('title', __('common.reports'))
@section('page-title', __('common.reports'))

@section('content')
@php
    $user = auth()->user();
    $businessUnit = $user->currentBusinessUnit;
    $canViewReports = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-reports', $businessUnit->id));
@endphp

@if(!$canViewReports)
    <div class="card">
        <div style="text-align: center; padding: 40px; color: var(--error-text);">
            <p>{{ __('common.unauthorized_access') }}</p>
            <a href="{{ route('business.dashboard') }}" class="btn">{{ __('common.back_to_dashboard') }}</a>
        </div>
    </div>
@else
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <h1 class="text-accent">{{ __('common.reports') }}</h1>
        <div class="btn-row">
            <a href="{{ route('business.reports.agents') }}" class="btn">{{ __('common.agent_reports') }}</a>
            <a href="{{ route('business.reports.clients') }}" class="btn">{{ __('common.client_reports') }}</a>
            <a href="{{ route('business.reports.transactions') }}" class="btn">{{ __('common.transaction_reports') }}</a>
            <a href="{{ route('business.reports.commissions') }}" class="btn">{{ __('common.commission_reports') }}</a>
            <a href="{{ route('business.reports.costs') }}" class="btn">{{ __('common.cost_reports') }}</a>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('business.reports.index') }}" style="margin-bottom: 20px; padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div class="form-group">
                <label class="form-label">{{ __('common.report_type') }}</label>
                <select name="type" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    <option value="agent" {{ request('type') == 'agent' ? 'selected' : '' }}>{{ __('common.agent_reports') }}</option>
                    <option value="client" {{ request('type') == 'client' ? 'selected' : '' }}>{{ __('common.client_reports') }}</option>
                    <option value="transaction" {{ request('type') == 'transaction' ? 'selected' : '' }}>{{ __('common.transaction_reports') }}</option>
                    <option value="commission" {{ request('type') == 'commission' ? 'selected' : '' }}>{{ __('common.commission_reports') }}</option>
                    <option value="cost" {{ request('type') == 'cost' ? 'selected' : '' }}>{{ __('common.cost_reports') }}</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.status') }}</label>
                <select name="status" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>{{ __('common.pending') }}</option>
                    <option value="processing" {{ request('status') == 'processing' ? 'selected' : '' }}>{{ __('common.processing') }}</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>{{ __('common.completed') }}</option>
                    <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>{{ __('common.failed') }}</option>
                </select>
            </div>
        </div>
        <div style="display: flex; gap: 10px; margin-top: 15px;">
            <button type="submit" class="btn">{{ __('common.filter') }}</button>
            <a href="{{ route('business.reports.index') }}" class="btn btn-secondary">{{ __('common.clear') }}</a>
        </div>
    </form>

    @if($reports->count() > 0)
        <div class="data-table">
            <table>
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th>{{ __('common.report_type') }}</th>
                        <th>{{ __('common.status') }}</th>
                        <th>{{ __('common.requested_by') }}</th>
                        <th>{{ __('common.created_at') }}</th>
                        <th>{{ __('common.completed_at') }}</th>
                        <th>{{ __('common.total_records') }}</th>
                        <th>{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reports as $report)
                        <tr style="border-bottom: 1px solid var(--border-color);" data-report-id="{{ $report->id }}">
                            <td style="padding: 12px; text-transform: capitalize;">
                                {{ __('common.' . $report->report_type . '_reports') }}
                            </td>
                            <td>
                                <span class="report-status" style="padding: 4px 8px; border-radius: 4px; font-size: 12px; text-transform: capitalize;
                                    @if($report->status === 'completed') background: var(--success-bg); color: var(--success-text);
                                    @elseif($report->status === 'processing') background: var(--warning-bg); color: var(--warning-text);
                                    @elseif($report->status === 'failed') background: var(--error-bg); color: var(--error-text);
                                    @else background: var(--bg-tertiary); color: var(--text-muted);
                                    @endif">
                                    {{ $report->status }}
                                </span>
                            </td>
                            <td>
                                {{ $report->user->name ?? __('common.unknown') }}
                            </td>
                            <td>
                                {{ $report->created_at->format('Y-m-d H:i') }}
                            </td>
                            <td>
                                {{ $report->completed_at ? $report->completed_at->format('Y-m-d H:i') : '-' }}
                            </td>
                            <td>
                                {{ $report->total_records ?? '-' }}
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    @if($report->isReady())
                                        <a href="{{ route('business.reports.download', $report->id) }}" class="btn btn-sm">{{ __('common.download') }}</a>
                                    @endif
                                    @if($report->canRetry())
                                        <form action="{{ route('business.reports.retry', $report->id) }}" method="POST" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-secondary btn-sm">{{ __('common.retry') }}</button>
                                        </form>
                                    @endif
                                    @if($user->isSuperAdmin() || ($businessUnit && $user->hasPermission('delete-reports', $businessUnit->id)))
                                        <form action="{{ route('business.reports.delete', $report->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('{{ __('common.confirm_delete') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">{{ __('common.delete') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @if($report->status === 'failed' && $report->error_message)
                            <tr>
                                <td colspan="7" style="padding: 12px; color: var(--error-text); font-size: 12px;">
                                    <strong>{{ __('common.error') }}:</strong> {{ $report->error_message }}
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-20">
            {{ $reports->links() }}
        </div>
    @else
        <div class="muted-center">
            <p class="mb-20">{{ __('common.no_reports_found') }}</p>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <a href="{{ route('business.reports.agents') }}" class="btn">{{ __('common.request_agent_report') }}</a>
                <a href="{{ route('business.reports.clients') }}" class="btn">{{ __('common.request_client_report') }}</a>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Poll for pending/processing reports
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
                
                // Update status display
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
                    // Reload page after 2 seconds to show download button
                    setTimeout(() => location.reload(), 2000);
                } else if (status === 'failed') {
                    statusElement.style.background = 'var(--error-bg)';
                    statusElement.style.color = 'var(--error-text)';
                    clearInterval(interval);
                    // Show error message if available
                    if (data.error_message) {
                        const errorRow = document.createElement('tr');
                        errorRow.innerHTML = `
                            <td colspan="7" style="padding: 12px; color: var(--error-text); font-size: 12px;">
                                <strong>{{ __('common.error') }}:</strong> ${data.error_message}
                            </td>
                        `;
                        row.parentNode.insertBefore(errorRow, row.nextSibling);
                    }
                } else if (status === 'processing') {
                    statusElement.style.background = 'var(--warning-bg)';
                    statusElement.style.color = 'var(--warning-text)';
                } else {
                    statusElement.style.background = 'var(--bg-tertiary)';
                    statusElement.style.color = 'var(--text-muted)';
                }
            })
            .catch(error => {
                console.error('Error polling report status:', error);
                clearInterval(interval);
            });
        }, 3000); // Poll every 3 seconds
        
        // Stop polling after 5 minutes
        setTimeout(() => clearInterval(interval), 300000);
    }
});
</script>
@endpush
@endif
@endsection

