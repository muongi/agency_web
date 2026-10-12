@extends('layouts.app')

@section('title', __('common.sunu_report_subscriptions'))
@section('page-title', __('common.sunu_report_subscriptions'))

@section('content')
<div class="card">
    <h1 style="margin:0 0 16px;color:var(--accent-color);">{{ __('common.sunu_report_subscriptions') }}</h1>
    @include('business.sunu.reports._filters', ['route' => 'business.sunu.reports.subscriptions', 'exportLabel' => __('common.sunu_report_export_overdue')])

    @php
        $tiles = [[__('common.sunu_report_proposals_period'), $data['proposal_count'], null, route('business.sunu.proposals.index')]];
        foreach ($data['proposals_by_status'] as $status => $n) {
            $tiles[] = [__('common.sunu_report_proposals').' · '.$status, $n];
        }
        foreach ($data['policies_by_status'] as $status => $n) {
            $tiles[] = [__('common.sunu_report_policies').' · '.$status, $n, null, route('business.sunu.policies.index')];
        }
        $tiles[] = [__('common.sunu_report_overdue'), $data['overdue']->count()];
    @endphp
    @include('business.sunu.reports._tiles')

    <div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:24px;">
        @include('business.sunu.reports._group', ['title' => __('common.sunu_report_proposals_by_agent'), 'items' => $data['proposals_by_agent'], 'money' => false])
        @include('business.sunu.reports._group', ['title' => __('common.sunu_report_proposals_by_product'), 'items' => $data['proposals_by_product'], 'money' => false])
    </div>

    <h3 style="color:var(--accent-color);">{{ __('common.sunu_report_overdue') }} ({{ $data['overdue']->count() }})</h3>
    <p class="muted" style="margin-top:0;">{{ __('common.sunu_report_overdue_help') }}</p>
    <div class="data-table">
        <table>
            <thead><tr>
                <th>{{ __('common.sunu_card_type_policy') }}</th><th>{{ __('common.client') }}</th><th>{{ __('common.phone') }}</th>
                <th>{{ __('common.sunu_report_product') }}</th><th style="text-align:right;">{{ __('common.sunu_report_premium') }}</th>
                <th>{{ __('common.sunu_report_due_date') }}</th><th style="text-align:right;">{{ __('common.sunu_report_days_late') }}</th><th>{{ __('common.sunu_report_last_payment') }}</th>
            </tr></thead>
            <tbody>
                @forelse($data['overdue']->take(500) as $p)
                    <tr>
                        <td>{{ $p->policy_number }}</td>
                        <td>{{ trim(($p->client?->last_name ?? '').' '.($p->client?->first_name ?? '')) }}</td>
                        <td>{{ $p->client?->phone_number }}</td>
                        <td>{{ $p->product?->localizedName() }}</td>
                        <td style="text-align:right;">{{ number_format((float) $p->premium_amount, 0, ',', ' ') }}</td>
                        <td>{{ optional($p->next_due_date)->format('Y-m-d') }}</td>
                        <td style="text-align:right;">{{ $p->next_due_date ? (int) $p->next_due_date->diffInDays(now()) : '' }}</td>
                        <td>{{ $p->premiums_max_collected_at ? \Illuminate\Support\Carbon::parse($p->premiums_max_collected_at)->format('Y-m-d') : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">{{ __('common.sunu_report_no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
