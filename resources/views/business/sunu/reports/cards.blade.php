@extends('layouts.app')

@section('title', __('common.sunu_report_cards'))
@section('page-title', __('common.sunu_report_cards'))

@section('content')
@php $svc = app(\App\Services\SunuReportService::class); @endphp
<div class="card">
    <h1 style="margin:0 0 16px;color:var(--accent-color);">{{ __('common.sunu_report_cards') }}</h1>
    @include('business.sunu.reports._filters', ['route' => 'business.sunu.reports.cards', 'show' => ['agent']])

    @php $tiles = [
        [__('common.sunu_card_stock_status'), $data['stock'], null, route('cards.index', ['status' => 'unassigned'])],
        [__('common.sunu_report_cards_assigned'), $data['assigned'], null, route('cards.index')],
        [__('common.sunu_report_cards_written_total'), $data['written_total']],
        [__('common.sunu_report_cards_written_period'), $data['written_in_period']],
        [__('common.blocked'), $data['blocked'], null, route('cards.index', ['status' => 'blocked'])],
    ]; @endphp
    @include('business.sunu.reports._tiles')

    <div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:24px;">
        @include('business.sunu.reports._group', ['title' => __('common.sunu_report_cards_by_agent'), 'items' => $data['by_agent'], 'money' => false])
        @include('business.sunu.reports._group', ['title' => __('common.sunu_report_by_day'), 'items' => $data['by_day'], 'money' => false])
    </div>

    <h3 style="color:var(--accent-color);">{{ __('common.sunu_report_cards_written_period') }}</h3>
    <div class="data-table">
        <table>
            <thead><tr>
                <th>{{ __('common.card_number') }}</th><th>UID</th><th>{{ __('common.client') }}</th><th>{{ __('common.status') }}</th>
                <th>{{ __('common.sunu_card_written_on_tpe') }}</th><th>{{ __('common.agent') }}</th>
            </tr></thead>
            <tbody>
                @forelse($data['rows']->take(500) as $c)
                    @php $client = $c->sunuClient(); @endphp
                    <tr>
                        <td><a href="{{ route('cards.show', $c->id) }}" class="text-accent">{{ $c->card_number }}</a></td>
                        <td><code>{{ $c->card_serial }}</code></td>
                        <td>{{ $client ? trim($client->last_name.' '.$client->first_name) : '—' }}</td>
                        <td>{{ __('common.'.$c->status) }}</td>
                        <td>{{ optional($c->written_at)->format('Y-m-d H:i') }}</td>
                        <td>{{ $data['agents']->get($svc->writtenByAgentId($c->written_by))?->full_name ?? $c->written_by }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">{{ __('common.sunu_report_no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
