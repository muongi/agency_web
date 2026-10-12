@extends('layouts.app')

@section('title', $corr->reference)
@section('page-title', __('common.sunu_corrections').' — '.$corr->reference)

@php
    $u = auth()->user();
    $can = fn ($slug) => $u->isSuperAdmin() || $u->hasPermission($slug, $corr->business_unit_id);
    $proposed = $corr->proposed_changes ?? [];
    $isPremium = $corr->target_type === 'premium';
@endphp

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <h1 style="margin:0;color:var(--accent-color);">{{ $corr->reference }}</h1>
        @include('business.sunu.corrections._status', ['status' => $corr->status])
    </div>

    <table class="table" style="max-width:820px; margin:16px 0;">
        <tr><th>{{ __('common.sunu_corr_operation') }}</th><td>{{ __('common.sunu_corr_target_'.$corr->target_type) }} <strong>{{ $corr->target_reference }}</strong></td></tr>
        <tr><th>{{ __('common.sunu_corr_request_type') }}</th><td>{{ __('common.sunu_corr_type_'.$corr->request_type) }}</td></tr>
        <tr><th>{{ __('common.sunu_corr_reason') }}</th><td>{{ __('common.sunu_corr_reason_'.$corr->reason_code) }}</td></tr>
        <tr><th>{{ __('common.sunu_corr_details') }}</th><td style="white-space:pre-line;">{{ $corr->details }}</td></tr>
        <tr><th>{{ __('common.sunu_corr_proposed') }}</th><td>
            @forelse($proposed as $k => $v)
                <div>{{ __('common.sunu_corr_field_'.$k) }} : <strong>{{ $v }}</strong></div>
            @empty
                —
            @endforelse
        </td></tr>
        <tr><th>{{ __('common.sunu_corr_requested_by') }}</th><td>
            {{ $corr->agent?->full_name ?? '—' }} ({{ $corr->agent?->username }})
            — {{ __('common.sunu_corr_tpe') }} {{ $corr->terminal?->device_alias }} {{ $corr->device_serial }}
            — {{ optional($corr->requested_at)->format('d/m/Y H:i:s') }}
        </td></tr>
        <tr><th>{{ __('common.sunu_corr_decided_by') }}</th><td>
            @if($corr->decided_at)
                {{ $corr->decider?->name ?? '—' }} — {{ $corr->decided_at->format('d/m/Y H:i:s') }}
                @if($corr->decision_note)<div style="white-space:pre-line;">{{ $corr->decision_note }}</div>@endif
            @else — @endif
        </td></tr>
        <tr><th>{{ __('common.sunu_corr_resolved_by') }}</th><td>
            @if($corr->resolved_at)
                {{ $corr->resolver?->name ?? '—' }} — {{ $corr->resolved_at->format('d/m/Y H:i:s') }}
                @if($corr->resolution_note)<div style="white-space:pre-line;">{{ $corr->resolution_note }}</div>@endif
            @else — @endif
        </td></tr>
    </table>

    <h3>{{ __('common.sunu_corr_current_operation') }}</h3>
    @if(!$target)
        <p>—</p>
    @elseif($isPremium)
        <table class="table" style="max-width:820px;">
            <tr><th>{{ __('common.sunu_reference') }}</th><td>{{ $target->reference }} ({{ $target->receipt_number }})</td></tr>
            <tr><th>{{ __('common.sunu_status_label') }}</th><td>{{ __('common.sunu_premium_status_'.$target->status) }}
                @if($target->cancelled_at) — {{ $target->cancelled_at->format('d/m/Y H:i') }} @endif</td></tr>
            <tr><th>{{ __('common.amount') }}</th><td>{{ number_format((float) $target->amount, 0, ',', ' ') }} XOF — {{ __('common.sunu_kind_'.$target->collection_kind) }}</td></tr>
            <tr><th>{{ __('common.sunu_policy_or_proposal') }}</th><td>
                {{ $target->policy?->policy_number ?? $target->proposal?->proposal_number }}
                — {{ $target->policy?->client ? trim($target->policy->client->last_name.' '.$target->policy->client->first_name) : trim(($target->proposal?->last_name ?? '').' '.($target->proposal?->first_names ?? '')) }}
            </td></tr>
            <tr><th>{{ __('common.sunu_corr_done_by') }}</th><td>{{ $target->agent?->full_name }} — {{ $target->terminal?->serial_number }} — {{ optional($target->collected_at)->format('d/m/Y H:i:s') }}</td></tr>
        </table>
    @else
        <table class="table" style="max-width:820px;">
            <tr><th>{{ __('common.sunu_proposal_number') }}</th><td><a href="{{ route('business.sunu.proposals.show', $target->id) }}">{{ $target->proposal_number }}</a></td></tr>
            <tr><th>{{ __('common.sunu_status_label') }}</th><td>{{ __('common.sunu_status_'.$target->status) }}</td></tr>
            <tr><th>{{ __('common.sunu_insured') }}</th><td>{{ $target->last_name }} {{ $target->first_names }} — {{ $target->phone ?: '—' }}</td></tr>
            <tr><th>{{ __('common.sunu_product') }}</th><td>{{ $target->product?->localizedName() }}</td></tr>
            <tr><th>{{ __('common.sunu_corr_done_by') }}</th><td>{{ $target->agent?->full_name }} — {{ $target->terminal?->serial_number }} — {{ optional($target->subscribed_at ?? $target->created_at)->format('d/m/Y H:i:s') }}</td></tr>
        </table>
    @endif

    {{-- 1. Decision --}}
    @if($corr->status === 'pending' && $can('approve-sunu-corrections'))
        <h3 style="margin-top:24px;">{{ __('common.sunu_corr_decision') }}</h3>
        <p style="font-size:13px; color:var(--text-muted);">{{ __('common.sunu_corr_decision_help') }}</p>
        <form method="POST" style="max-width:720px;">
            @csrf
            <textarea name="note" rows="3" class="form-input" style="width:100%;" maxlength="1000" placeholder="{{ __('common.sunu_corr_note_placeholder') }}">{{ old('note') }}</textarea>
            <div style="display:flex; gap:8px; margin-top:8px;">
                <button class="btn" type="submit" formaction="{{ route('business.sunu.corrections.approve', $corr->id) }}">{{ __('common.sunu_corr_approve') }}</button>
                <button class="btn btn-secondary" type="submit" formaction="{{ route('business.sunu.corrections.reject', $corr->id) }}">{{ __('common.sunu_corr_reject') }}</button>
            </div>
            @error('note')<div style="color:#c0392b; font-size:13px; margin-top:4px;">{{ $message }}</div>@enderror
        </form>
    @endif

    {{-- 2. Manual correction (approved requests only) --}}
    @if($corr->status === 'approved' && $can('apply-sunu-corrections') && $target)
        <h3 style="margin-top:24px;">{{ __('common.sunu_corr_apply') }}</h3>
        <p style="font-size:13px; color:var(--text-muted);">{{ __('common.sunu_corr_apply_help') }}</p>
        <div style="display:flex; flex-direction:column; gap:16px; max-width:720px;">
            @if($isPremium)
                <form method="POST" action="{{ route('business.sunu.corrections.apply', [$corr->id, 'cancel-premium']) }}" class="card" style="padding:12px;"
                      onsubmit="return confirm(@js(__('common.sunu_corr_confirm_cancel')))">
                    @csrf
                    <strong>{{ __('common.sunu_corr_action_cancel_premium') }}</strong>
                    <input type="text" name="note" required maxlength="1000" class="form-input" style="width:100%; margin:8px 0;" placeholder="{{ __('common.sunu_corr_note_placeholder') }}">
                    <button class="btn btn-secondary" type="submit">{{ __('common.sunu_corr_action_cancel_premium') }}</button>
                </form>
                <form method="POST" action="{{ route('business.sunu.corrections.apply', [$corr->id, 'premium-amount']) }}" class="card" style="padding:12px;">
                    @csrf
                    <strong>{{ __('common.sunu_corr_action_premium_amount') }}</strong>
                    <input type="number" name="amount" min="1" step="1" required class="form-input" style="width:100%; margin:8px 0;" value="{{ old('amount', $proposed['amount'] ?? $target->amount) }}">
                    <input type="text" name="note" required maxlength="1000" class="form-input" style="width:100%; margin-bottom:8px;" placeholder="{{ __('common.sunu_corr_note_placeholder') }}">
                    <button class="btn" type="submit">{{ __('common.save') }}</button>
                </form>
                <form method="POST" action="{{ route('business.sunu.corrections.apply', [$corr->id, 'premium-policy']) }}" class="card" style="padding:12px;">
                    @csrf
                    <strong>{{ __('common.sunu_corr_action_premium_policy') }}</strong>
                    <input type="text" name="policy_number" required maxlength="64" class="form-input" style="width:100%; margin:8px 0;" value="{{ old('policy_number', $proposed['policy_number'] ?? $target->policy?->policy_number) }}" placeholder="{{ __('common.sunu_corr_field_policy_number') }}">
                    <select name="insurance_product_id" class="form-select" style="width:100%; margin-bottom:8px;">
                        <option value="">{{ __('common.sunu_corr_product_any') }}</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}" @selected((int) old('insurance_product_id', $target->policy?->insurance_product_id) === $p->id)>{{ $p->localizedName() }} ({{ $p->code }})</option>
                        @endforeach
                    </select>
                    @if(!empty($proposed['product']))
                        <div style="font-size:12px; color:var(--text-muted); margin-bottom:8px;">{{ __('common.sunu_corr_proposed') }} : {{ $proposed['product'] }}</div>
                    @endif
                    <input type="text" name="note" required maxlength="1000" class="form-input" style="width:100%; margin-bottom:8px;" placeholder="{{ __('common.sunu_corr_note_placeholder') }}">
                    <button class="btn" type="submit">{{ __('common.save') }}</button>
                </form>
            @else
                <form method="POST" action="{{ route('business.sunu.corrections.apply', [$corr->id, 'cancel-proposal']) }}" class="card" style="padding:12px;"
                      onsubmit="return confirm(@js(__('common.sunu_corr_confirm_cancel')))">
                    @csrf
                    <strong>{{ __('common.sunu_corr_action_cancel_proposal') }}</strong>
                    <input type="text" name="note" required maxlength="1000" class="form-input" style="width:100%; margin:8px 0;" placeholder="{{ __('common.sunu_corr_note_placeholder') }}">
                    <button class="btn btn-secondary" type="submit">{{ __('common.sunu_corr_action_cancel_proposal') }}</button>
                </form>
                <form method="POST" action="{{ route('business.sunu.corrections.apply', [$corr->id, 'proposal-identity']) }}" class="card" style="padding:12px;">
                    @csrf
                    <strong>{{ __('common.sunu_corr_action_proposal_identity') }}</strong>
                    <label style="display:block; margin-top:8px;">{{ __('common.sunu_corr_field_last_name') }}</label>
                    <input type="text" name="last_name" maxlength="120" class="form-input" style="width:100%;" value="{{ old('last_name', $proposed['last_name'] ?? $target->last_name) }}">
                    <label style="display:block; margin-top:8px;">{{ __('common.sunu_corr_field_first_names') }}</label>
                    <input type="text" name="first_names" maxlength="200" class="form-input" style="width:100%;" value="{{ old('first_names', $proposed['first_names'] ?? $target->first_names) }}">
                    <label style="display:block; margin-top:8px;">{{ __('common.sunu_corr_field_phone') }}</label>
                    <input type="text" name="phone" maxlength="32" class="form-input" style="width:100%;" value="{{ old('phone', $proposed['phone'] ?? $target->phone) }}">
                    <label style="display:block; margin-top:8px;">{{ __('common.sunu_product') }}</label>
                    <select name="insurance_product_id" class="form-select" style="width:100%;">
                        @foreach($products as $p)
                            <option value="{{ $p->id }}" @selected((int) old('insurance_product_id', $target->insurance_product_id) === $p->id)>{{ $p->localizedName() }} ({{ $p->code }})</option>
                        @endforeach
                    </select>
                    @if(!empty($proposed['product']))
                        <div style="font-size:12px; color:var(--text-muted);">{{ __('common.sunu_corr_proposed') }} : {{ $proposed['product'] }}</div>
                    @endif
                    <input type="text" name="note" required maxlength="1000" class="form-input" style="width:100%; margin:8px 0;" placeholder="{{ __('common.sunu_corr_note_placeholder') }}">
                    <button class="btn" type="submit">{{ __('common.save') }}</button>
                </form>
            @endif
            <form method="POST" action="{{ route('business.sunu.corrections.close', $corr->id) }}" class="card" style="padding:12px;">
                @csrf
                <strong>{{ __('common.sunu_corr_action_close') }}</strong>
                <p style="font-size:12px; color:var(--text-muted); margin:4px 0;">{{ __('common.sunu_corr_action_close_help') }}</p>
                <input type="text" name="note" required maxlength="1000" class="form-input" style="width:100%; margin:8px 0;" placeholder="{{ __('common.sunu_corr_note_placeholder') }}">
                <button class="btn btn-secondary" type="submit">{{ __('common.sunu_corr_action_close') }}</button>
            </form>
        </div>
        @if($errors->any())
            <div style="color:#c0392b; font-size:13px; margin-top:8px;">{{ $errors->first() }}</div>
        @endif
    @endif

    <h3 style="margin-top:24px;">{{ __('common.sunu_corr_history') }}</h3>
    @include('business.sunu.corrections._timeline', ['events' => $corr->events])

    <p style="margin-top:16px;"><a href="{{ route('business.sunu.corrections.index') }}">← {{ __('common.back') }}</a></p>
</div>
@endsection
