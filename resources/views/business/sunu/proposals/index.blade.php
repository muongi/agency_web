@extends('layouts.app')

@section('title', __('common.sunu_proposals_title'))
@section('page-title', __('common.sunu_proposals_title'))

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
        <h1 style="margin:0;color:var(--accent-color);">{{ __('common.sunu_proposals_heading') }}</h1>
        <form method="GET" style="display:flex;gap:8px;">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('common.sunu_search_placeholder') }}" class="form-input">
            <select name="status" class="form-select">
                <option value="">{{ __('common.sunu_all_statuses') }}</option>
                @foreach(['captured','transmitted','under_review','accepted','rejected'] as $s)
                    <option value="{{ $s }}" @selected(request('status')===$s)>{{ __('common.sunu_status_'.$s) }}</option>
                @endforeach
            </select>
            <select name="paid" class="form-select">
                <option value="">{{ __('common.sunu_first_premium_all') }}</option>
                <option value="1" @selected(request('paid')==='1')>{{ __('common.sunu_first_premium_paid') }}</option>
                <option value="0" @selected(request('paid')==='0')>{{ __('common.sunu_first_premium_unpaid') }}</option>
            </select>
            <button class="btn" type="submit">{{ __('common.filter') }}</button>
        </form>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>{{ __('common.sunu_proposal_number') }}</th>
                <th>{{ __('common.sunu_insured_number') }}</th>
                <th>{{ __('common.sunu_insured') }}</th>
                <th>{{ __('common.sunu_product') }}</th>
                <th>{{ __('common.agent') }}</th>
                <th>{{ __('common.status') }}</th>
                <th>{{ __('common.date') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($proposals as $p)
                <tr>
                    <td>{{ $p->proposal_number }}
                        @if($p->policy)<div style="font-size:12px;color:var(--text-muted);">{{ __('common.sunu_policy') }} {{ $p->policy->policy_number }}</div>
                        @elseif($p->sunulife_policy_number)<div style="font-size:12px;color:var(--text-muted);">{{ __('common.sunu_existing_policy_short', ['number' => $p->sunulife_policy_number]) }}</div>@endif</td>
                    <td>{{ $p->client?->insured_number ?? '—' }}</td>
                    <td>{{ $p->last_name }} {{ $p->first_names }}
                        @if($p->identity_mismatch)<span class="badge" style="background:#e67e22;color:#fff;">{{ __('common.sunu_identity_mismatch') }}</span>@endif</td>
                    <td>{{ $p->product?->localizedName() }}</td>
                    <td>{{ $p->agent?->full_name }}</td>
                    <td>{{ __('common.sunu_status_'.$p->status) }} @include('business.sunu.proposals._paid', ['proposal' => $p])</td>
                    <td>{{ optional($p->subscribed_at)->format('Y-m-d H:i') }}</td>
                    <td><a class="btn btn-sm" href="{{ route('business.sunu.proposals.show', $p->id) }}">{{ __('common.view') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="8">{{ __('common.sunu_no_proposals') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $proposals->links() }}
</div>
@endsection
