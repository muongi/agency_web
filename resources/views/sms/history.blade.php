@extends('layouts.app')

@section('title', __('common.sms_history'))
@section('page-title', __('common.sms_history'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent-color);">{{ __('common.sms_message_history') }}</h1>
    
    <!-- Filters -->
    <form method="GET" action="{{ route('business.sms.history') }}" style="margin-bottom: 20px; padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
            <div class="form-group">
                <label class="form-label">{{ __('common.status') }}</label>
                <select name="status" id="filter-status" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>{{ __('common.pending') }}</option>
                    <option value="sent" {{ request('status') == 'sent' ? 'selected' : '' }}>{{ __('common.sent') }}</option>
                    <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>{{ __('common.failed') }}</option>
                    <option value="delivered" {{ request('status') == 'delivered' ? 'selected' : '' }}>{{ __('common.delivered') }}</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.client') }}</label>
                <select name="client_id" id="filter-client" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    @php
                        $clients = \App\Models\Client::where('business_unit_id', $businessUnit->id)
                            ->whereIn('id', \App\Models\SmsMessage::where('business_unit_id', $businessUnit->id)
                                ->whereNotNull('recipient_client_id')
                                ->distinct()
                                ->pluck('recipient_client_id'))
                            ->orderBy('first_name')
                            ->orderBy('last_name')
                            ->get();
                    @endphp
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>
                            {{ $client->full_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.date_from') }}</label>
                <input type="date" name="date_from" id="filter-date-from" class="form-control" value="{{ request('date_from') }}">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.date_to') }}</label>
                <input type="date" name="date_to" id="filter-date-to" class="form-control" value="{{ request('date_to') }}">
            </div>
        </div>
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn">{{ __('common.filter') }}</button>
            <a href="{{ route('business.sms.history') }}" class="btn btn-secondary">{{ __('common.clear') }}</a>
        </div>
    </form>

    <!-- SMS History Table -->
    <div style="overflow-x: auto;">
        <table id="sms-history-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--bg-tertiary); border-bottom: 2px solid var(--border-color);">
                    <th style="padding: 12px; text-align: left; color: var(--text-primary); font-weight: 600;">{{ __('common.sent_at') }}</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-primary); font-weight: 600;">{{ __('common.sender_number') }}</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-primary); font-weight: 600;">{{ __('common.recipient_number') }}</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-primary); font-weight: 600;">{{ __('common.recipient_name') }}</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-primary); font-weight: 600;">{{ __('common.message_preview') }}</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-primary); font-weight: 600;">{{ __('common.status') }}</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-primary); font-weight: 600;">{{ __('common.sent_by') }}</th>
                    <th style="padding: 12px; text-align: left; color: var(--text-primary); font-weight: 600;">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <!-- DataTables will populate this -->
            </tbody>
        </table>
    </div>
</div>

<!-- Message Detail Modal -->
<div id="messageModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: var(--bg-primary); padding: 30px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="color: var(--accent-color); margin: 0;">{{ __('common.message_details') }}</h2>
            <button onclick="closeMessageModal()" style="background: none; border: none; font-size: 24px; color: var(--text-muted); cursor: pointer; padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">&times;</button>
        </div>
        <div id="messageModalContent">
            <!-- Content will be populated by JavaScript -->
        </div>
        <div style="margin-top: 20px; text-align: right;">
            <button onclick="closeMessageModal()" class="btn btn-secondary">{{ __('common.close') }}</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadSmsHistory();

    // Auto-refresh every 30 seconds if filters are applied
    const filterStatus = document.getElementById('filter-status');
    if (filterStatus.value === 'pending') {
        setInterval(loadSmsHistory, 30000);
    }
});

function loadSmsHistory() {
    const params = new URLSearchParams(window.location.search);
    params.append('draw', 1);
    params.append('start', 0);
    params.append('length', 100);

    fetch('{{ route("business.sms.history.data") }}?' + params.toString())
        .then(response => response.json())
        .then(data => {
            const tbody = document.querySelector('#sms-history-table tbody');
            tbody.innerHTML = '';
            
            if (data.data && data.data.length > 0) {
                data.data.forEach(message => {
                    const row = document.createElement('tr');
                    row.style.borderBottom = '1px solid var(--border-color)';
                    
                    const statusColors = {
                        'pending': { bg: 'var(--warning-bg, rgba(255, 193, 7, 0.2))', text: 'var(--warning-text, #ffc107)' },
                        'sent': { bg: 'var(--success-bg)', text: 'var(--success-text)' },
                        'failed': { bg: 'var(--error-bg)', text: 'var(--error-text)' },
                        'delivered': { bg: 'var(--success-bg)', text: 'var(--success-text)' }
                    };
                    
                    const statusColor = statusColors[message.status] || { bg: 'var(--bg-secondary)', text: 'var(--text-primary)' };
                    
                    row.innerHTML = `
                        <td style="padding: 12px; color: var(--text-secondary);">${message.sent_at}</td>
                        <td style="padding: 12px; color: var(--text-secondary);">${message.sender_number}</td>
                        <td style="padding: 12px; color: var(--text-secondary);">${message.recipient_number}</td>
                        <td style="padding: 12px; color: var(--text-primary);">${message.recipient_name}</td>
                        <td style="padding: 12px; color: var(--text-secondary);">
                            ${message.message_preview}
                            ${message.message.length > 50 ? '<button onclick="showMessageDetails(' + JSON.stringify(message).replace(/"/g, '&quot;') + ')" style="background: none; border: none; color: var(--accent-color); cursor: pointer; text-decoration: underline; margin-left: 5px;">{{ __("common.view_full") }}</button>' : ''}
                        </td>
                        <td style="padding: 12px;">
                            <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: ${statusColor.bg}; color: ${statusColor.text};">
                                ${message.status_label}
                            </span>
                        </td>
                        <td style="padding: 12px; color: var(--text-secondary);">${message.sent_by}</td>
                        <td style="padding: 12px;">
                            ${message.error_message ? '<button onclick="showErrorDetails(\'' + message.error_message.replace(/'/g, "\\'") + '\')" class="btn btn-secondary" style="padding: 5px 10px; font-size: 12px;">{{ __("common.view_error") }}</button>' : ''}
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="8" style="padding: 20px; text-align: center; color: var(--text-muted);">{{ __("common.no_sms_messages_found") }}</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error loading SMS history:', error);
            const tbody = document.querySelector('#sms-history-table tbody');
            tbody.innerHTML = '<tr><td colspan="8" style="padding: 20px; text-align: center; color: var(--error-text);">{{ __("common.error_loading_data") }}</td></tr>';
        });
}

function showMessageDetails(message) {
    const modal = document.getElementById('messageModal');
    const content = document.getElementById('messageModalContent');
    
    content.innerHTML = `
        <div style="margin-bottom: 15px;">
            <strong style="color: var(--text-primary);">{{ __('common.recipient') }}:</strong>
            <span style="color: var(--text-secondary);">${message.recipient_number} ${message.recipient_name !== '-' ? '(' + message.recipient_name + ')' : ''}</span>
        </div>
        <div style="margin-bottom: 15px;">
            <strong style="color: var(--text-primary);">{{ __('common.sender') }}:</strong>
            <span style="color: var(--text-secondary);">${message.sender_number}</span>
        </div>
        <div style="margin-bottom: 15px;">
            <strong style="color: var(--text-primary);">{{ __('common.status') }}:</strong>
            <span style="color: var(--text-secondary);">${message.status_label}</span>
        </div>
        <div style="margin-bottom: 15px;">
            <strong style="color: var(--text-primary);">{{ __('common.sent_at') }}:</strong>
            <span style="color: var(--text-secondary);">${message.sent_at}</span>
        </div>
        <div style="margin-bottom: 15px;">
            <strong style="color: var(--text-primary);">{{ __('common.message') }}:</strong>
        </div>
        <div style="padding: 15px; background: var(--bg-secondary); border-radius: 4px; color: var(--text-primary); white-space: pre-wrap;">${message.message}</div>
    `;
    
    modal.style.display = 'flex';
}

function showErrorDetails(errorMessage) {
    const modal = document.getElementById('messageModal');
    const content = document.getElementById('messageModalContent');
    
    content.innerHTML = `
        <div style="margin-bottom: 15px;">
            <strong style="color: var(--error-text);">{{ __('common.error_message') }}:</strong>
        </div>
        <div style="padding: 15px; background: var(--error-bg); border-radius: 4px; color: var(--error-text); white-space: pre-wrap;">${errorMessage}</div>
    `;
    
    modal.style.display = 'flex';
}

function closeMessageModal() {
    document.getElementById('messageModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('messageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeMessageModal();
    }
});
</script>
@endsection
