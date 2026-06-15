@extends('layouts.app')

@section('title', __('common.add_cbs_mapping'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: #4a9eff;">{{ __('common.add_cbs_mapping') }} — {{ $businessUnit->name }}</h1>

    @if($transactionTypes->isEmpty())
        <div style="padding: 20px; background: #5a5a2d; border-radius: 4px; color: #eeee90;">
            <p>{{ __('common.no_eligible_transaction_types') }}</p>
            <a href="{{ route('superadmin.business-units.transaction-types', $businessUnit->id) }}" class="btn" style="margin-top: 10px;">{{ __('common.assign_transaction_types') }}</a>
        </div>
    @else
        <form method="POST" action="{{ route('superadmin.business-units.cbs-mappings.store', $businessUnit->id) }}">
            @csrf

            <div class="form-group">
                <label class="form-label" for="transaction_type_id">{{ __('common.transaction_type') }} <span style="color: #ff6b6b;">*</span></label>
                <select id="transaction_type_id" name="transaction_type_id" class="form-control" required>
                    <option value="">{{ __('common.select_transaction_type') }}</option>
                    @foreach($transactionTypes as $type)
                        <option value="{{ $type->id }}" {{ old('transaction_type_id') == $type->id ? 'selected' : '' }}>
                            {{ $type->name }} ({{ $type->code }})
                        </option>
                    @endforeach
                </select>
                @error('transaction_type_id')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            @php $mapping = new \App\Models\BusinessUnitCbsTransactionMapping(); @endphp
            @include('superadmin.business-units.cbs-mappings._form')

            <div style="display: flex; gap: 10px; margin-top: 24px;">
                <button type="submit" class="btn">{{ __('common.create') }}</button>
                <a href="{{ route('superadmin.business-units.cbs-mappings.index', $businessUnit->id) }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
            </div>
        </form>
    @endif
</div>
@endsection
