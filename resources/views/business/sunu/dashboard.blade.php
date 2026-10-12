@extends('layouts.app')

@section('title', __('common.dashboard'))

@section('content')
@php $money = fn ($v) => number_format($v, 0, ',', ' ') . ' XOF'; @endphp
<x-page-header :title="__('common.dashboard')" :subtitle="$businessUnit->name" />

<div class="card">
    @php $tiles = [
        [__('common.sunu_dash_premiums_month'), $money($d['premiums_month_amount']), __('common.sunu_report_n_collections', ['n' => $d['premiums_month_count']]), route('business.sunu.reports.premiums')],
        [__('common.sunu_dash_proposals_month'), $d['proposals_month'], null, route('business.sunu.reports.subscriptions')],
        [__('common.sunu_dash_active_policies'), $d['active_policies'], null, route('business.sunu.policies.index')],
        [__('common.sunu_report_overdue'), $d['overdue_policies'], null, route('business.sunu.reports.subscriptions')],
        [__('common.sunu_report_payins_pending'), $money($d['payins_pending_amount']), __('common.sunu_dash_n_payins', ['n' => $d['payins_pending_count']]), route('business.sunu.payins.index')],
        [__('common.sunu_card_stock_status'), $d['cards_stock'], null, route('business.sunu.reports.cards')],
    ]; @endphp
    @include('business.sunu.reports._tiles')

    <div style="display:flex;gap:24px;flex-wrap:wrap;">
        <div style="flex:1;min-width:280px;">
            <h2 style="color:var(--accent-color);margin:0 0 12px;">{{ __('common.sunu_dash_top_agents') }}</h2>
            <div class="data-table">
                <table>
                    <thead><tr><th>{{ __('common.agent') }}</th><th style="text-align:right;">{{ __('common.sunu_report_count') }}</th><th style="text-align:right;">{{ __('common.amount') }}</th></tr></thead>
                    <tbody>
                        @forelse($d['top_agents'] as $row)
                            <tr>
                                <td>{{ $row->agent?->full_name ?? '—' }}</td>
                                <td style="text-align:right;">{{ $row->n }}</td>
                                <td style="text-align:right;">{{ number_format((float) $row->total, 0, ',', ' ') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="muted">{{ __('common.sunu_report_no_data') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div style="flex:1;min-width:280px;">
            <h2 style="color:var(--accent-color);margin:0 0 12px;">{{ __('common.sunu_dash_monthly', ['year' => $d['year']]) }}</h2>
            @php $max = max(1, $d['monthly']->max('amount')); @endphp
            <div style="display:flex;flex-direction:column;gap:6px;">
                @foreach($d['monthly'] as $m)
                    <div style="display:grid;grid-template-columns:48px 1fr 120px;gap:8px;align-items:center;font-size:13px;">
                        <span class="muted">{{ $m['month'] }}</span>
                        <div style="background:var(--bg-tertiary);border-radius:4px;height:14px;overflow:hidden;">
                            <div style="width:{{ round($m['amount'] / $max * 100, 1) }}%;height:100%;background:var(--accent-color);"></div>
                        </div>
                        <span style="text-align:right;">{{ number_format($m['amount'], 0, ',', ' ') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
