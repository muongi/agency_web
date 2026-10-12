@extends('layouts.app')

@section('title', __('common.sunu_policies_title'))
@section('page-title', __('common.sunu_policies_heading'))

@section('content')
<div class="card">
    <h1 style="color:var(--accent-color);">{{ __('common.sunu_policies_heading') }}</h1>
    <form method="GET" style="margin-bottom:16px;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('common.sunu_search_policy_placeholder') }}" class="form-input">
        <button class="btn" type="submit">{{ __('common.search') }}</button>
    </form>
    <table class="table">
        <thead>
            <tr>
                <th>{{ __('common.sunu_policy_number') }}</th>
                <th>{{ __('common.sunu_insured_number') }}</th>
                <th>{{ __('common.sunu_insured') }}</th>
                <th>{{ __('common.sunu_product') }}</th>
                <th>{{ __('common.sunu_premium') }}</th>
                <th>{{ __('common.status') }}</th>
                <th>{{ __('common.sunu_source') }}</th>
                <th>{{ __('common.sunu_card') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($policies as $p)
                @php
                    $statusKey = 'common.sunu_status_'.$p->status;
                    $statusLabel = __($statusKey);
                    if ($statusLabel === $statusKey) {
                        $statusLabel = $p->status;
                    }
                @endphp
                <tr>
                    <td>{{ $p->policy_number }}</td>
                    <td>{{ $p->client?->insured_number ?? '—' }}</td>
                    <td>{{ $p->client?->last_name }} {{ $p->client?->first_name }}</td>
                    <td>{{ $p->product?->localizedName() }}</td>
                    <td>{{ number_format((float)$p->premium_amount, 0, ',', ' ') }} / {{ \App\Models\InsuranceProduct::frequencyLabel($p->premium_frequency) }}</td>
                    <td>{{ $statusLabel }}</td>
                    <td>{{ $p->source }}</td>
                    <td>
                        @foreach($p->cards as $card)
                            <div style="font-family:monospace;">{{ $card->card_serial ?? $card->card_number }}</div>
                        @endforeach
                        @if(auth()->user()->hasPermission('link-sunu-card'))
                        <form method="POST" action="{{ route('business.sunu.policies.link-card', $p->id) }}" style="display:flex; gap:4px; margin-top:4px;">
                            @csrf
                            <input type="text" name="card_uid" required maxlength="64" placeholder="{{ __('common.sunu_card_uid') }}" class="form-input" style="width:140px; padding:4px 6px;">
                            <button class="btn" type="submit" style="padding:4px 8px;">{{ __('common.sunu_link_card') }}</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9">{{ __('common.sunu_no_policies') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    {{ $policies->links() }}
</div>
@endsection
