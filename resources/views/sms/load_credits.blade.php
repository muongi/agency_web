@extends('layouts.app')

@section('title', __('common.load_sms_credits'))
@section('page-title', __('common.load_sms_credits'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent-color);">{{ __('common.load_sms_credits') }}</h1>
    
    <form method="POST" action="{{ route('superadmin.sms.store_credits') }}" id="load-credits-form">
        @csrf
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label class="form-label" for="target_business_unit_id">{{ __('common.select_business_unit') }} <span style="color: #ff6b6b;">*</span></label>
                <select id="target_business_unit_id" name="target_business_unit_id" class="form-select" required>
                    <option value="">{{ __('common.select_business_unit') }}...</option>
                    @foreach($businessUnits as $businessUnit)
                        <option value="{{ $businessUnit->id }}" {{ (old('target_business_unit_id', $selectedBusinessUnitId ?? null) == $businessUnit->id) ? 'selected' : '' }}>
                            {{ $businessUnit->name }}
                        </option>
                    @endforeach
                </select>
                @error('target_business_unit_id')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="amount">{{ __('common.amount') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="number" id="amount" name="amount" class="form-control" value="{{ old('amount') }}" step="0.01" min="0.01" required>
                <small style="color: var(--text-muted); font-size: 12px;">{{ __('common.enter_sms_credits_amount') }}</small>
                @error('amount')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label" for="description">{{ __('common.description') }}</label>
                <textarea id="description" name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                @error('description')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 30px;">
            <button type="submit" class="btn">{{ __('common.load_credits') }}</button>
            <a href="{{ route('superadmin.sms.all_balances') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection

