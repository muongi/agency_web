@extends('layouts.app')

@section('title', __('common.sunu_premiums_title'))
@section('page-title', __('common.sunu_premiums_heading'))

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
        <h1 style="margin:0;color:var(--accent-color);">{{ __('common.sunu_premiums') }}</h1>
        @if(auth()->user()->hasPermission('export-sunu-premiums'))
        <div style="display:flex;gap:8px;">
            <a class="btn btn-secondary" href="{{ route('business.sunu.premiums.export', request()->query()) }}">CSV</a>
            <a class="btn" href="{{ route('business.sunu.premiums.export', array_merge(request()->query(), ['format' => 'excel'])) }}">{{ __('common.sunu_premiums_export_excel') }}</a>
        </div>
        @endif
    </div>
    <form method="GET" action="{{ route('business.sunu.premiums.index') }}" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
        <select name="agent_id" class="form-select">
            <option value="">{{ __('common.agent') }} — {{ __('common.all') }}</option>
            @foreach($agents as $a)
                <option value="{{ $a->id }}" @selected(request('agent_id') == $a->id)>{{ $a->full_name }}</option>
            @endforeach
        </select>
        <select name="status" class="form-select">
            <option value="">{{ __('common.sunu_status_label') }} — {{ __('common.all') }}</option>
            @foreach(['valid', 'cancelled'] as $st)
                <option value="{{ $st }}" @selected(request('status') === $st)>{{ __('common.sunu_premium_status_'.$st) }}</option>
            @endforeach
        </select>
        <input type="date" name="from" class="form-input" value="{{ request('from') }}" title="{{ __('common.start_date') }}">
        <input type="date" name="to" class="form-input" value="{{ request('to') }}" title="{{ __('common.end_date') }}">
        @if(request('collection_kind'))<input type="hidden" name="collection_kind" value="{{ request('collection_kind') }}">@endif
        <button class="btn btn-sm" type="submit">{{ __('common.filter') }}</button>
    </form>
    <table class="table">
        <thead>
            <tr>
                <th>{{ __('common.sunu_reference') }}</th>
                <th>{{ __('common.type') }}</th>
                <th>{{ __('common.amount') }}</th>
                <th>{{ __('common.sunu_policy_or_proposal') }}</th>
                <th>{{ __('common.agent') }}</th>
                <th>{{ __('common.sunu_corr_tpe') }}</th>
                <th>{{ __('common.date') }}</th>
                <th>{{ __('common.sunu_status_label') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($premiums as $c)
                @php
                    $kindKey = 'common.sunu_kind_'.$c->collection_kind;
                    $kindLabel = __($kindKey);
                    if ($kindLabel === $kindKey) {
                        $kindLabel = $c->collection_kind;
                    }
                @endphp
                <tr @if($c->isCancelled()) style="color:var(--text-muted);" @endif>
                    <td>{{ $c->reference }}</td>
                    <td>{{ $kindLabel }}</td>
                    <td @if($c->isCancelled()) style="text-decoration:line-through;" @endif>{{ number_format((float)$c->amount, 0, ',', ' ') }} XOF</td>
                    <td>{{ $c->policy?->policy_number ?? $c->proposal?->proposal_number }}</td>
                    <td>{{ $c->agent?->full_name }}</td>
                    <td>{{ $c->terminal?->serial_number ?? '—' }}</td>
                    <td>{{ optional($c->collected_at)->format('Y-m-d H:i') }}</td>
                    <td>
                        {{ __('common.sunu_premium_status_'.$c->status) }}
                        @foreach($c->correctionRequests as $cr)
                            <div><a href="{{ route('business.sunu.corrections.show', $cr->id) }}" style="font-size:12px;">{{ $cr->reference }}</a>
                                @include('business.sunu.corrections._status', ['status' => $cr->status])</div>
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">{{ __('common.sunu_no_premiums') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $premiums->links() }}
</div>
@endsection
