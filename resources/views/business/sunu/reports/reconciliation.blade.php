@extends('layouts.app')

@section('title', __('common.sunu_report_reconciliation'))
@section('page-title', __('common.sunu_report_reconciliation'))

@section('content')
@php $money = fn ($v) => number_format($v, 0, ',', ' ') . ' XOF'; $t = $data['totals']; @endphp
<div class="card">
    <h1 style="margin:0 0 8px;color:var(--accent-color);">{{ __('common.sunu_report_reconciliation') }}</h1>
    <p class="muted" style="margin:0 0 16px;">{{ __('common.sunu_report_reconciliation_help') }}</p>
    @include('business.sunu.reports._filters', ['route' => 'business.sunu.reports.reconciliation', 'show' => ['agent']])

    @php $tiles = [
        [__('common.sunu_report_total_collected'), $money($t['collected'])],
        [__('common.sunu_report_payins_confirmed'), $money($t['confirmed']), null, route('business.sunu.payins.index')],
        [__('common.sunu_report_payins_pending'), $money($t['pending']), null, route('business.sunu.payins.index')],
        [__('common.sunu_report_balance'), $money($t['balance'])],
    ]; @endphp
    @include('business.sunu.reports._tiles')

    <div class="data-table">
        <table>
            <thead><tr>
                <th>{{ __('common.agent') }}</th>
                <th style="text-align:right;">{{ __('common.sunu_report_count') }}</th>
                <th style="text-align:right;">{{ __('common.sunu_report_total_collected') }}</th>
                <th style="text-align:right;">{{ __('common.sunu_report_payins_pending') }}</th>
                <th style="text-align:right;">{{ __('common.sunu_report_payins_confirmed') }}</th>
                <th style="text-align:right;">{{ __('common.sunu_report_payins_rejected') }}</th>
                <th style="text-align:right;">{{ __('common.sunu_report_balance') }}</th>
                <th></th>
            </tr></thead>
            <tbody>
                @forelse($data['rows'] as $r)
                    <tr>
                        <td>{{ $r['agent'] }}</td>
                        <td style="text-align:right;">{{ $r['collections'] }}</td>
                        <td style="text-align:right;">{{ number_format($r['collected'], 0, ',', ' ') }}</td>
                        <td style="text-align:right;">{{ number_format($r['pending'], 0, ',', ' ') }}</td>
                        <td style="text-align:right;">{{ number_format($r['confirmed'], 0, ',', ' ') }}</td>
                        <td style="text-align:right;">{{ number_format($r['rejected'], 0, ',', ' ') }}</td>
                        <td style="text-align:right;font-weight:700;{{ $r['balance'] > 0 ? 'color:var(--error-text);' : '' }}">{{ number_format($r['balance'], 0, ',', ' ') }}</td>
                        <td><a class="btn btn-sm btn-secondary" href="{{ route('business.sunu.reports.premiums', ['agent_id' => $r['agent_id'], 'from' => $f['from']->format('Y-m-d'), 'to' => $f['to']->format('Y-m-d')]) }}">{{ __('common.sunu_report_premiums') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">{{ __('common.sunu_report_no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
