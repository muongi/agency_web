@extends('layouts.app')

@section('title', __('common.sunu_corrections'))
@section('page-title', __('common.sunu_corrections'))

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
        <h1 style="margin:0;color:var(--accent-color);">{{ __('common.sunu_corrections') }}</h1>
        @if($pendingCount > 0)
            <a class="btn" href="{{ route('business.sunu.corrections.index', ['status' => 'pending']) }}">{{ trans_choice('common.sunu_corr_pending_count', $pendingCount, ['count' => $pendingCount]) }}</a>
        @endif
    </div>
    <p style="font-size:13px; color:var(--text-muted); margin:0 0 16px;">{{ __('common.sunu_corr_help') }}</p>

    <form method="GET" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;">
        <select name="status" class="form-select">
            <option value="">{{ __('common.sunu_status_label') }}</option>
            @foreach(['pending', 'approved', 'rejected', 'done'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ __('common.sunu_corr_status_'.$s) }}</option>
            @endforeach
        </select>
        <select name="target_type" class="form-select">
            <option value="">{{ __('common.sunu_corr_operation') }}</option>
            @foreach(['premium', 'proposal'] as $t)
                <option value="{{ $t }}" @selected(request('target_type') === $t)>{{ __('common.sunu_corr_target_'.$t) }}</option>
            @endforeach
        </select>
        <select name="request_type" class="form-select">
            <option value="">{{ __('common.sunu_corr_request_type') }}</option>
            @foreach(['cancel', 'modify'] as $t)
                <option value="{{ $t }}" @selected(request('request_type') === $t)>{{ __('common.sunu_corr_type_'.$t) }}</option>
            @endforeach
        </select>
        <select name="agent_id" class="form-select">
            <option value="">{{ __('common.agent') }}</option>
            @foreach($agents as $a)
                <option value="{{ $a->id }}" @selected((string) request('agent_id') === (string) $a->id)>{{ $a->full_name }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ request('from') }}" class="form-input">
        <input type="date" name="to" value="{{ request('to') }}" class="form-input">
        <input type="text" name="q" value="{{ request('q') }}" class="form-input" placeholder="{{ __('common.sunu_corr_search') }}">
        <button class="btn" type="submit">{{ __('common.filter') }}</button>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>{{ __('common.sunu_reference') }}</th>
                <th>{{ __('common.sunu_corr_requested_at') }}</th>
                <th>{{ __('common.sunu_corr_operation') }}</th>
                <th>{{ __('common.sunu_corr_request_type') }}</th>
                <th>{{ __('common.sunu_corr_requested_by') }}</th>
                <th>{{ __('common.sunu_corr_decided_by') }}</th>
                <th>{{ __('common.sunu_corr_resolved_by') }}</th>
                <th>{{ __('common.sunu_status_label') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($requests as $r)
                <tr>
                    <td><a href="{{ route('business.sunu.corrections.show', $r->id) }}">{{ $r->reference }}</a></td>
                    <td>{{ optional($r->requested_at)->format('d/m/Y H:i') }}</td>
                    <td>{{ __('common.sunu_corr_target_'.$r->target_type) }} {{ $r->target_reference }}</td>
                    <td>{{ __('common.sunu_corr_type_'.$r->request_type) }} — {{ __('common.sunu_corr_reason_'.$r->reason_code) }}</td>
                    <td>{{ $r->agent?->full_name ?? '—' }}<div style="font-size:12px;color:var(--text-muted);">{{ $r->device_serial }}</div></td>
                    <td>{{ $r->decider?->name ?? '—' }}<div style="font-size:12px;color:var(--text-muted);">{{ optional($r->decided_at)->format('d/m/Y H:i') }}</div></td>
                    <td>{{ $r->resolver?->name ?? '—' }}<div style="font-size:12px;color:var(--text-muted);">{{ optional($r->resolved_at)->format('d/m/Y H:i') }}</div></td>
                    <td>@include('business.sunu.corrections._status', ['status' => $r->status])</td>
                </tr>
            @empty
                <tr><td colspan="8">{{ __('common.sunu_corr_none') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $requests->links() }}
</div>
@endsection
