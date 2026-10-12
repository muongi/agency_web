@extends('layouts.app')

@section('title', __('common.sunu_proposal').' '.$proposal->proposal_number)
@section('page-title', __('common.sunu_proposal').' '.$proposal->proposal_number)

@section('content')
<div class="card">
    <h1 style="color:var(--accent-color);">{{ $proposal->proposal_number }} — {{ $proposal->last_name }} {{ $proposal->first_names }}</h1>

    <table class="table" style="max-width:720px; margin:16px 0;">
        <tr><th>{{ __('common.sunu_status_label') }}</th><td>{{ __('common.sunu_status_'.$proposal->status) }} @include('business.sunu.proposals._paid', ['proposal' => $proposal])</td></tr>
        <tr><th>{{ __('common.sunu_insured_number') }}</th><td>
            {{ $proposal->client?->insured_number ?? '—' }}
            @if($proposal->client?->insured_number && auth()->user()->hasPermission('accept-sunu-proposal'))
                <details style="display:inline-block; margin-left:12px;">
                    <summary style="cursor:pointer; font-size:13px;">{{ __('common.sunu_insured_number_edit') }}</summary>
                    <form method="POST" action="{{ route('business.sunu.proposals.insured-number', $proposal->id) }}" style="display:flex; gap:8px; margin-top:6px;">
                        @csrf
                        <input type="text" name="insured_number" value="{{ old('insured_number', $proposal->client->insured_number) }}" required maxlength="64" class="form-input">
                        <button class="btn btn-sm" type="submit">{{ __('common.save') }}</button>
                    </form>
                    <div style="font-size:12px; color:var(--text-muted);">{{ __('common.sunu_insured_number_edit_help') }}</div>
                </details>
            @endif
        </td></tr>
        <tr><th>{{ __('common.sunu_product') }}</th><td>{{ $proposal->product?->localizedName() }} ({{ $proposal->product?->code }})</td></tr>
        <tr><th>{{ __('common.sunu_proposal_number') }}</th><td>{{ $proposal->proposal_number }}</td></tr>
        <tr><th>{{ __('common.sunu_insured') }}</th><td>{{ $proposal->last_name }} {{ $proposal->first_names }}</td></tr>
        <tr><th>{{ __('common.phone') }}</th><td>{{ $proposal->phone ?: '—' }}</td></tr>
        <tr><th>{{ __('common.sunu_corr_done_by') }}</th><td>{{ $proposal->agent?->full_name }} ({{ $proposal->agent?->username }})
            — {{ __('common.sunu_corr_tpe') }} {{ $proposal->terminal?->serial_number ?? '—' }}
            — {{ optional($proposal->subscribed_at ?? $proposal->created_at)->format('d/m/Y H:i:s') }}</td></tr>
        <tr><th>{{ __('common.sunu_gps') }}</th><td>
            @if($proposal->latitude !== null && $proposal->longitude !== null)
                {{ $proposal->latitude }}, {{ $proposal->longitude }}
                — <a href="https://maps.google.com/?q={{ $proposal->latitude }},{{ $proposal->longitude }}" target="_blank" rel="noopener">Map</a>
            @else
                —
            @endif
        </td></tr>
        @if($proposal->policy)
            <tr><th>{{ __('common.sunu_policy') }}</th><td>{{ $proposal->policy->policy_number }}</td></tr>
        @endif
        @if($proposal->sunulife_policy_number)
            <tr><th>{{ __('common.sunu_existing_policy') }}</th><td>{{ $proposal->sunulife_policy_number }}</td></tr>
        @endif
        @if($otherProducts->isNotEmpty())
            <tr><th>{{ __('common.sunu_customer_other_products') }}</th><td>
                @foreach($otherProducts as $o)
                    <div>{{ $o['number'] }} — {{ $o['product'] }} ({{ $o['status'] }})
                        @if($o['proposal_id'])<a href="{{ route('business.sunu.proposals.show', $o['proposal_id']) }}">{{ __('common.view') }}</a>@endif</div>
                @endforeach
            </td></tr>
        @endif
    </table>

    @if($proposal->identity_mismatch)
        @php $mm = $proposal->identity_mismatch; @endphp
        <div style="border:1px solid #e67e22; background:rgba(230,126,34,.08); border-radius:6px; padding:12px; max-width:720px; margin-bottom:16px;">
            <strong style="color:#e67e22;">{{ __('common.sunu_identity_mismatch') }}</strong>
            <p style="font-size:13px; margin:6px 0;">{{ __('common.sunu_identity_mismatch_help', ['number' => $mm['number'] ?? $mm['policy_number'] ?? '']) }}</p>
            <table class="table" style="margin:0;">
                <tr><th></th><th>{{ __('common.sunu_identity_typed') }}</th><th>{{ __('common.sunu_identity_on_file') }}</th></tr>
                <tr><th>{{ __('common.sunu_corr_field_last_name') }}</th><td>{{ $mm['typed']['last_name'] ?? '—' }}</td><td>{{ $mm['on_file']['last_name'] ?? '—' }}</td></tr>
                <tr><th>{{ __('common.sunu_corr_field_first_names') }}</th><td>{{ $mm['typed']['first_names'] ?? '—' }}</td><td>{{ $mm['on_file']['first_names'] ?? '—' }}</td></tr>
                <tr><th>{{ __('common.sunu_corr_field_phone') }}</th><td>{{ $mm['typed']['phone'] ?? '—' }}</td><td>{{ $mm['on_file']['phone'] ?? '—' }}</td></tr>
            </table>
        </div>
    @endif

    @php
        $linkedCards = collect([$proposal->card])->filter()
            ->merge($proposal->policy?->cards ?? collect())
            ->unique('id');
    @endphp
    <h3 style="margin-top:24px;">{{ __('common.sunu_card') }}</h3>
    <p style="font-size:13px; color:var(--text-muted); margin:4px 0 12px;">{{ __('common.sunu_card_help') }}</p>
    <p>
        @forelse($linkedCards as $card)
            <span class="badge" style="font-family:monospace; margin-right:8px;">{{ $card->card_serial ?? $card->card_number }}</span>
            @if($card->written_at)
                <span style="font-size:12px; color:var(--text-muted); margin-right:12px;">{{ __('common.sunu_card_written_at', ['date' => $card->written_at->format('d/m/Y H:i')]) }}</span>
            @endif
        @empty
            <span style="color:var(--text-muted);">{{ __('common.sunu_no_card') }}</span>
        @endforelse
    </p>
    @if(!in_array($proposal->status, ['rejected', 'cancelled'], true) && auth()->user()->hasPermission('link-sunu-card'))
        <form method="POST" action="{{ route('business.sunu.proposals.link-card', $proposal->id) }}" style="display:flex; gap:8px; flex-wrap:wrap; max-width:520px;">
            @csrf
            <input type="text" name="card_uid" value="{{ old('card_uid') }}" required maxlength="64"
                   placeholder="{{ __('common.sunu_card_uid_placeholder') }}" class="form-input" style="flex:1; min-width:200px;">
            <button class="btn" type="submit">{{ __('common.sunu_link_card') }}</button>
        </form>
        @error('card_uid')<div style="color:#c0392b; font-size:13px; margin-top:4px;">{{ $message }}</div>@enderror
    @endif

    <h3 style="margin-top:24px;">{{ __('common.sunu_premiums_paid') }}</h3>
    <table class="table" style="max-width:720px;">
        <thead>
            <tr>
                <th>{{ __('common.sunu_reference') }}</th>
                <th>{{ __('common.sunu_premium') }}</th>
                <th>{{ __('common.sunu_collected_at') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($proposal->premiums->sortByDesc('collected_at') as $premium)
                <tr>
                    <td>{{ $premium->receipt_number ?? $premium->reference }}</td>
                    <td @if($premium->isCancelled()) style="text-decoration:line-through; color:var(--text-muted);" @endif>{{ number_format((float) $premium->amount, 0, ',', ' ') }} XOF — {{ __('common.sunu_kind_'.$premium->collection_kind) }}
                        @if($premium->isCancelled())<span class="badge">{{ __('common.sunu_premium_status_cancelled') }}</span>@endif</td>
                    <td>{{ optional($premium->collected_at)->format('d/m/Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="3">{{ __('common.sunu_no_premiums') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3 style="margin-top:24px;">{{ __('common.sunu_images') }}</h3>
    @if($proposal->idDocumentsSource)
        <p style="font-size:13px; color:var(--text-muted); margin:4px 0 12px;">
            {{ __('common.sunu_id_documents_reused') }}
            <a href="{{ route('business.sunu.proposals.show', $proposal->idDocumentsSource->id) }}">{{ $proposal->idDocumentsSource->proposal_number }}</a>
        </p>
    @endif
    <div style="display:flex; flex-wrap:wrap; gap:16px;">
        @foreach([
            'proposal_image_path' => __('common.sunu_proposal_image'),
            'id_document_front_path' => __('common.sunu_id_front'),
            'id_document_back_path' => __('common.sunu_id_back'),
        ] as $field => $label)
            <div style="width:220px;">
                <div style="font-size:12px; color:var(--text-muted); margin-bottom:6px;">{{ $label }}</div>
                @if($proposal->{$field})
                    <a href="{{ route('business.sunu.proposals.file', ['id' => $proposal->id, 'which' => $field]) }}" target="_blank" rel="noopener">
                        <img src="{{ route('business.sunu.proposals.file', ['id' => $proposal->id, 'which' => $field]) }}"
                             alt="{{ $label }}"
                             style="width:100%; max-height:280px; object-fit:cover; border:1px solid #ddd; border-radius:6px; background:#fafafa;">
                    </a>
                @else
                    <div style="padding:24px; border:1px dashed #ccc; color:var(--text-muted); text-align:center;">{{ __('common.sunu_no') }}</div>
                @endif
            </div>
        @endforeach
    </div>

    @if($proposal->correctionRequests->isNotEmpty())
        <h3 style="margin-top:24px;">{{ __('common.sunu_corrections') }}</h3>
        @foreach($proposal->correctionRequests as $cr)
            <p style="margin:8px 0 4px;">
                <a href="{{ route('business.sunu.corrections.show', $cr->id) }}"><strong>{{ $cr->reference }}</strong></a>
                — {{ __('common.sunu_corr_type_'.$cr->request_type) }} — @include('business.sunu.corrections._status', ['status' => $cr->status])
            </p>
            @include('business.sunu.corrections._timeline', ['events' => $cr->events])
        @endforeach
    @endif

    @if(!$proposal->policy && !in_array($proposal->status, ['accepted', 'cancelled'], true) && auth()->user()->hasPermission('accept-sunu-proposal'))
        <h3 style="margin-top:24px;">{{ __('common.sunu_accept_create_policy') }}</h3>
        <p style="font-size:13px; color:var(--text-muted); margin:4px 0 12px;">{{ __('common.sunu_accept_help') }}</p>
        <form method="POST" action="{{ route('business.sunu.proposals.accept', $proposal->id) }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; max-width:720px;">
            @csrf
            <label style="display:flex; flex-direction:column; font-size:13px;">{{ __('common.sunu_insured_number') }}
                @if($proposal->client?->insured_number)
                    <input type="text" value="{{ $proposal->client->insured_number }}" class="form-input" readonly>
                @else
                    <input type="text" name="insured_number" value="{{ old('insured_number') }}" required maxlength="64" class="form-input">
                @endif
            </label>
            <label style="display:flex; flex-direction:column; font-size:13px;">{{ __('common.sunu_policy_number') }}
                <input type="text" name="policy_number" value="{{ old('policy_number') }}" required maxlength="64" class="form-input">
            </label>
            <button class="btn" type="submit">{{ __('common.sunu_accept_create_policy') }}</button>
        </form>
        @error('insured_number')<div style="color:#c0392b; font-size:13px; margin-top:4px;">{{ $message }}</div>@enderror
        @error('policy_number')<div style="color:#c0392b; font-size:13px; margin-top:4px;">{{ $message }}</div>@enderror
    @endif
    <p style="margin-top:16px;"><a href="{{ route('business.sunu.proposals.index') }}">← {{ __('common.back') }}</a></p>
</div>
@endsection
