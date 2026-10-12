@extends('layouts.app')

@section('title', __('common.load_sms_credits'))
@section('page-title', __('common.load_sms_credits'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent-color);">{{ __('common.load_sms_credits') }}</h1>
    
    <form method="POST" action="{{ route('superadmin.sms.store_credits') }}" id="load-credits-form">
        @csrf
        
        <div class="detail-grid">
            <div class="form-group">
                <label class="form-label" for="target_business_unit_id">{{ __('common.select_business_unit') }} <span class="text-error">*</span></label>
                <select id="target_business_unit_id" name="target_business_unit_id" class="form-select" required>
                    <option value="">{{ __('common.select_business_unit') }}...</option>
                    @foreach($businessUnits as $businessUnit)
                        <option value="{{ $businessUnit->id }}" {{ (old('target_business_unit_id', $selectedBusinessUnitId ?? null) == $businessUnit->id) ? 'selected' : '' }}>
                            {{ $businessUnit->name }}
                        </option>
                    @endforeach
                </select>
                @error('target_business_unit_id')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="amount">{{ __('common.amount') }} <span class="text-error">*</span></label>
                <input type="number" id="amount" name="amount" class="form-control" value="{{ old('amount') }}" step="0.01" min="0.01" required>
                <small class="text-muted-sm">{{ __('common.enter_sms_credits_amount') }}</small>
                @error('amount')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group detail-full">
                <label class="form-label" for="description">{{ __('common.description') }}</label>
                <textarea id="description" name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                @error('description')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="btn-row mt-30">
            <button type="submit" class="btn">{{ __('common.load_credits') }}</button>
            <a href="{{ route('superadmin.sms.all_balances') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection

