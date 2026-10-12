@extends('layouts.app')

@section('title', __('common.sunu_report_premiums'))
@section('page-title', __('common.sunu_report_premiums'))

@section('content')
@php $money = fn ($v) => number_format($v, 0, ',', ' ') . ' XOF'; $svc = app(\App\Services\SunuReportService::class); @endphp
<div class="card">
    <h1 style="margin:0 0 16px;color:var(--accent-color);">{{ __('common.sunu_report_premiums') }}</h1>
    @include('business.sunu.reports._filters', ['route' => 'business.sunu.reports.premiums'])

    @php
        $tiles = [[__('common.sunu_report_total_collected'), $money($data['total']), __('common.sunu_report_n_collections', ['n' => $data['count']])]];
        foreach ($data['by_kind'] as $kind => $k) {
            $tiles[] = [$svc->kindLabel($kind), $money($k['amount']), __('common.sunu_report_n_collections', ['n' => $k['count']])];
        }
    @endphp
    @include('business.sunu.reports._tiles')

    <div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:24px;">
        @include('business.sunu.reports._group', ['title' => __('common.sunu_report_by_agent'), 'items' => $data['by_agent']])
        @include('business.sunu.reports._group', ['title' => __('common.sunu_report_by_product'), 'items' => $data['by_product']])
        @include('business.sunu.reports._group', ['title' => __('common.sunu_report_by_day'), 'items' => $data['by_day']])
    </div>

    <h3 style="color:var(--accent-color);">{{ __('common.sunu_report_details') }} ({{ $data['count'] }})</h3>
    <div class="data-table">
        <table>
            <thead><tr>
                <th>{{ __('common.date') }}</th><th>{{ __('common.type') }}</th><th style="text-align:right;">{{ __('common.amount') }}</th>
                <th>{{ __('common.sunu_policy_or_proposal') }}</th><th>{{ __('common.client') }}</th><th>{{ __('common.agent') }}</th><th>{{ __('common.sunu_report_receipt') }}</th>
            </tr></thead>
            <tbody>
                @forelse($data['rows']->take(500) as $c)
                    <tr>
                        <td>{{ optional($c->collected_at)->format('Y-m-d H:i') }}</td>
                        <td>{{ $svc->kindLabel($c->collection_kind) }}</td>
                        <td style="text-align:right;">{{ number_format((float) $c->amount, 0, ',', ' ') }}</td>
                        <td>{{ $c->policy?->policy_number ?? $c->proposal?->proposal_number }}</td>
                        <td>{{ trim(($c->policy?->client?->last_name ?? $c->proposal?->last_name ?? '').' '.($c->policy?->client?->first_name ?? $c->proposal?->first_names ?? '')) }}</td>
                        <td>{{ $c->agent?->full_name }}</td>
                        <td>{{ $c->receipt_number }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">{{ __('common.sunu_report_no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($data['count'] > 500)<p class="muted">{{ __('common.sunu_report_truncated', ['n' => 500]) }}</p>@endif
</div>
@endsection
