@extends('layouts.app')

@section('title', __('common.sms_management'))
@section('page-title', __('common.sms_management'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <h1 style="color: var(--accent-color);">{{ __('common.sms_management') }}</h1>
        <div style="display: flex; gap: 10px;">
            @if(auth()->user()->isSuperAdmin())
                <a href="{{ route('superadmin.sms.config.index') }}" class="btn" style="background: var(--success-bg); color: var(--success-text); border: 1px solid var(--success-text);">{{ __('common.sms_configuration') }}</a>
                <a href="{{ route('superadmin.sms.load_credits') }}" class="btn">{{ __('common.load_sms_credits') }}</a>
                <a href="{{ route('superadmin.sms.all_balances') }}" class="btn btn-secondary">{{ __('common.all_businesses_balance') }}</a>
            @endif
        </div>
    </div>

    <!-- SMS Balance Card -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div style="padding: 20px; background: var(--bg-secondary); border-radius: 8px; border-left: 4px solid var(--accent-color);">
            <div style="color: var(--text-muted); font-size: 14px; margin-bottom: 8px;">{{ __('common.current_balance') }}</div>
            <div style="font-size: 32px; font-weight: bold; color: {{ $smsBalance > 0 ? 'var(--success-text)' : 'var(--error-text)' }};">
                {{ number_format($smsBalance, 2) }}
            </div>
            <div style="color: var(--text-muted); font-size: 12px; margin-top: 5px;">{{ __('common.sms_credits') }}</div>
        </div>
        
        <div style="padding: 20px; background: var(--bg-secondary); border-radius: 8px; border-left: 4px solid #4a9eff;">
            <div style="color: var(--text-muted); font-size: 14px; margin-bottom: 8px;">{{ __('common.total_used') }}</div>
            <div style="font-size: 32px; font-weight: bold; color: var(--text-primary);">
                {{ number_format($smsUsed, 2) }}
            </div>
            <div style="color: var(--text-muted); font-size: 12px; margin-top: 5px;">{{ __('common.sms_credits') }}</div>
        </div>
        
        <div style="padding: 20px; background: var(--bg-secondary); border-radius: 8px; border-left: 4px solid #90ee90;">
            <div style="color: var(--text-muted); font-size: 14px; margin-bottom: 8px;">{{ __('common.total_credited') }}</div>
            <div style="font-size: 32px; font-weight: bold; color: var(--text-primary);">
                {{ number_format($smsCredited, 2) }}
            </div>
            <div style="color: var(--text-muted); font-size: 12px; margin-top: 5px;">{{ __('common.sms_credits') }}</div>
        </div>
    </div>

    <!-- Quick Actions - Send SMS -->
    @php
        $user = auth()->user();
        $canSendSingle = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('send-single-sms', $businessUnit->id));
        $canSendBulk = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('send-bulk-sms', $businessUnit->id));
        $canViewHistory = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-sms-history', $businessUnit->id));
    @endphp
    <div style="margin-bottom: 30px; padding: 30px; background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-tertiary) 100%); border-radius: 12px; border: 2px solid var(--accent-color); box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
        <h2 style="color: var(--accent-color); margin-bottom: 20px; font-size: 24px; font-weight: 600;">{{ __('common.send_sms') }}</h2>
        @if($canSendSingle || $canSendBulk)
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">
                @if($canSendSingle)
                    <a href="{{ route('business.sms.send-single') }}" class="btn" style="padding: 20px; font-size: 18px; font-weight: 600; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 10px; text-decoration: none; background: var(--accent-color); color: white; border-radius: 8px; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                        <span style="font-size: 32px;">📧</span>
                        <span>{{ __('common.send_single_sms') }}</span>
                        <span style="font-size: 12px; opacity: 0.9;">{{ __('common.send_to_one_client') }}</span>
                    </a>
                @endif
                @if($canSendBulk)
                    <a href="{{ route('business.sms.send-bulk') }}" class="btn" style="padding: 20px; font-size: 18px; font-weight: 600; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 10px; text-decoration: none; background: var(--success-bg); color: var(--success-text); border: 2px solid var(--success-text); border-radius: 8px; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                        <span style="font-size: 32px;">📨</span>
                        <span>{{ __('common.send_bulk_sms') }}</span>
                        <span style="font-size: 12px; opacity: 0.9;">{{ __('common.send_to_multiple_clients') }}</span>
                    </a>
                @endif
            </div>
        @else
            <div style="padding: 20px; background: var(--warning-bg, rgba(255, 193, 7, 0.2)); border-radius: 8px; border-left: 4px solid var(--warning-text, #ffc107);">
                <p style="color: var(--text-primary); margin: 0; font-size: 16px;">
                    {{ __('common.no_send_sms_permission') }}
                    @if(!$user->isSuperAdmin() && $businessUnit)
                        <br><small style="color: var(--text-muted);">{{ __('common.contact_admin_for_permissions') }}</small>
                    @endif
                </p>
            </div>
        @endif
        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border-color);">
            <a href="{{ route('business.sms.index') }}" class="btn btn-secondary" style="padding: 10px 20px;">{{ __('common.sms_dashboard') }}</a>
            @if($canViewHistory)
                <a href="{{ route('business.sms.history') }}" class="btn btn-secondary" style="padding: 10px 20px;">{{ __('common.sms_history') }}</a>
            @endif
        </div>
    </div>

    <!-- Transaction History -->
    <div>
        <h2 style="color: var(--text-primary); margin-bottom: 20px; font-size: 20px;">{{ __('common.transaction_history') }}</h2>
        <div style="overflow-x: auto;">
            <table id="sms-transactions-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--bg-tertiary); border-bottom: 2px solid var(--border-color);">
                        <th style="padding: 12px; text-align: left; color: var(--text-primary); font-weight: 600;">{{ __('common.date') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--text-primary); font-weight: 600;">{{ __('common.type') }}</th>
                        <th style="padding: 12px; text-align: right; color: var(--text-primary); font-weight: 600;">{{ __('common.amount') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--text-primary); font-weight: 600;">{{ __('common.description') }}</th>
                        <th style="padding: 12px; text-align: right; color: var(--text-primary); font-weight: 600;">{{ __('common.balance_after') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--text-primary); font-weight: 600;">{{ __('common.user') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- DataTables will populate this -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Simple table implementation (can be enhanced with DataTables later)
    fetch('{{ route("business.sms.transactions") }}')
        .then(response => response.json())
        .then(data => {
            const tbody = document.querySelector('#sms-transactions-table tbody');
            tbody.innerHTML = '';
            
            if (data.data && data.data.length > 0) {
                data.data.forEach(transaction => {
                    const row = document.createElement('tr');
                    row.style.borderBottom = '1px solid var(--border-color)';
                    row.innerHTML = `
                        <td style="padding: 12px; color: var(--text-secondary);">${transaction.date}</td>
                        <td style="padding: 12px;">
                            <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; 
                                ${transaction.transaction_type === 'Credit' ? 'background: var(--success-bg); color: var(--success-text);' : 'background: var(--error-bg); color: var(--error-text);'}">
                                ${transaction.transaction_type}
                            </span>
                        </td>
                        <td style="padding: 12px; text-align: right; color: ${transaction.transaction_type === 'Credit' ? 'var(--success-text)' : 'var(--error-text)'}; font-weight: 600;">
                            ${transaction.transaction_type === 'Credit' ? '+' : '-'}${transaction.amount}
                        </td>
                        <td style="padding: 12px; color: var(--text-secondary);">${transaction.description || '-'}</td>
                        <td style="padding: 12px; text-align: right; color: var(--text-primary); font-weight: 600;">${transaction.balance_after}</td>
                        <td style="padding: 12px; color: var(--text-secondary);">${transaction.user}</td>
                    `;
                    tbody.appendChild(row);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="6" style="padding: 20px; text-align: center; color: var(--text-muted);">{{ __("common.no_transactions") }}</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error loading transactions:', error);
        });
});
</script>
@endsection

