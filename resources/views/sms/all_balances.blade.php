@extends('layouts.app')

@section('title', __('common.all_businesses_sms_balance'))
@section('page-title', __('common.all_businesses_sms_balance'))

@section('content')
<div class="card">
    <div class="toolbar">
        <h1 class="text-accent">{{ __('common.all_businesses_sms_balance') }}</h1>
        <a href="{{ route('superadmin.sms.load_credits') }}" class="btn">{{ __('common.load_sms_credits') }}</a>
    </div>

    <div class="data-table">
        <table>
            <thead>
                <tr style="background: var(--bg-tertiary); border-bottom: 2px solid var(--border-color);">
                    <th style="padding: 12px; text-align: left; color: var(--text-primary); font-weight: 600;">{{ __('common.business_unit') }}</th>
                    <th style="padding: 12px; text-align: right; color: var(--text-primary); font-weight: 600;">{{ __('common.current_balance') }}</th>
                    <th style="padding: 12px; text-align: right; color: var(--text-primary); font-weight: 600;">{{ __('common.total_used') }}</th>
                    <th style="padding: 12px; text-align: right; color: var(--text-primary); font-weight: 600;">{{ __('common.total_credited') }}</th>
                    <th style="padding: 12px; text-align: center; color: var(--text-primary); font-weight: 600;">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($businessUnits as $businessUnit)
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 12px; color: var(--text-primary);">{{ $businessUnit->name }}</td>
                        <td style="padding: 12px; text-align: right; color: {{ $businessUnit->sms_balance > 0 ? 'var(--success-text)' : 'var(--error-text)' }}; font-weight: 600;">
                            {{ number_format($businessUnit->sms_balance, 2) }}
                        </td>
                        <td style="padding: 12px; text-align: right; color: var(--text-secondary);">{{ number_format($businessUnit->sms_used, 2) }}</td>
                        <td style="padding: 12px; text-align: right; color: var(--text-secondary);">{{ number_format($businessUnit->sms_credited, 2) }}</td>
                        <td style="padding: 12px; text-align: center;">
                            <a href="{{ route('superadmin.sms.load_credits') }}?business_unit={{ $businessUnit->id }}" class="btn btn-sm">{{ __('common.load_credits') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="padding: 20px; text-align: center; color: var(--text-muted);">{{ __('common.no_business_units') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

