@extends('layouts.app')

@section('title', __('common.add_cbs_mapping'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent);">{{ __('common.add_cbs_mapping') }} — {{ $businessUnit->name }}</h1>

    @if($transactionTypes->isEmpty())
        <div style="padding: 20px; background: var(--warning-bg); border-radius: 4px; color: var(--warning-text);">
            <p>{{ __('common.no_eligible_transaction_types') }}</p>
            <a href="{{ route('superadmin.business-units.transaction-types', $businessUnit->id) }}" class="btn" style="margin-top: 10px;">{{ __('common.assign_transaction_types') }}</a>
        </div>
    @else
        <form method="POST" action="{{ route('superadmin.business-units.cbs-mappings.store', $businessUnit->id) }}">
            @csrf

            <div class="form-group">
                <label class="form-label" for="transaction_type_id">{{ __('common.transaction_type') }} <span class="text-error">*</span></label>
                <select id="transaction_type_id" name="transaction_type_id" class="form-control" required>
                    <option value="">{{ __('common.select_transaction_type') }}</option>
                    @foreach($transactionTypes as $type)
                        <option value="{{ $type->id }}" {{ old('transaction_type_id') == $type->id ? 'selected' : '' }}>
                            {{ $type->name }} ({{ $type->code }})
                        </option>
                    @endforeach
                </select>
                @error('transaction_type_id')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            @php $mapping = new \App\Models\BusinessUnitCbsTransactionMapping(); @endphp
            @include('superadmin.business-units.cbs-mappings._form')

            <div class="btn-row mt-24">
                <button type="submit" class="btn">{{ __('common.create') }}</button>
                <a href="{{ route('superadmin.business-units.cbs-mappings.index', $businessUnit->id) }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
            </div>
        </form>
    @endif
</div>
@endsection
