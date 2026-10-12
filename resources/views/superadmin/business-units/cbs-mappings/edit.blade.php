@extends('layouts.app')

@section('title', __('common.edit_cbs_mapping'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent);">{{ __('common.edit_cbs_mapping') }} — {{ $businessUnit->name }}</h1>

    <form method="POST" action="{{ route('superadmin.business-units.cbs-mappings.update', [$businessUnit->id, $mapping->id]) }}">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label class="form-label">{{ __('common.transaction_type') }}</label>
            <input type="text" class="form-control" value="{{ $mapping->transactionType->name ?? '' }} ({{ $mapping->transactionType->code ?? '' }})" disabled>
            <input type="hidden" name="transaction_type_id" value="{{ $mapping->transaction_type_id }}">
        </div>

        @include('superadmin.business-units.cbs-mappings._form')

        <div class="btn-row mt-24">
            <button type="submit" class="btn">{{ __('common.update') }}</button>
            <a href="{{ route('superadmin.business-units.cbs-mappings.index', $businessUnit->id) }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection
