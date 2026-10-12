@extends('layouts.app')

@section('title', __('common.add_commission'))
@section('page-title', __('common.add_commission'))

@section('content')
<div class="card">
    <div style="background: var(--bg-tertiary); padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid var(--accent-color);">
        <p style="margin: 0; color: var(--text-primary); font-size: 14px;">
            <strong>{{ __('common.commission_calculation_note') }}</strong>
        </p>
    </div>
    <h1 style="margin-bottom: 24px; color: var(--accent-color);">{{ __('common.add_commission') }}</h1>
    
    <form method="POST" action="{{ route('business.config.commissions.store') }}" id="commissionForm">
        @csrf

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label class="form-label" for="transaction_type_id">{{ __('common.transaction_type') }} <span class="text-error">*</span></label>
                <select id="transaction_type_id" name="transaction_type_id" class="form-select" required>
                    <option value="">{{ __('common.select_transaction_type') }}</option>
                    @foreach($availableTypes as $type)
                        <option value="{{ $type->id }}" {{ old('transaction_type_id') == $type->id ? 'selected' : '' }}>
                            {{ $type->name }} ({{ $type->code }})
                        </option>
                    @endforeach
                </select>
                @error('transaction_type_id')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="commission_type">{{ __('common.commission_type') }} <span class="text-error">*</span></label>
                <select id="commission_type" name="commission_type" class="form-select" required>
                    <option value="">{{ __('common.select_commission_type') }}</option>
                    <option value="fixed" {{ old('commission_type') == 'fixed' ? 'selected' : '' }}>{{ __('common.fixed') }}</option>
                    <option value="percentage" {{ old('commission_type') == 'percentage' ? 'selected' : '' }}>{{ __('common.percentage') }}</option>
                    <option value="tiered_percentage" {{ old('commission_type') == 'tiered_percentage' ? 'selected' : '' }}>{{ __('common.tiered_percentage') }}</option>
                    <option value="tiered_fixed" {{ old('commission_type') == 'tiered_fixed' ? 'selected' : '' }}>{{ __('common.tiered_fixed') }}</option>
                </select>
                @error('commission_type')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group" id="fixed_amount_group" style="display: none;">
                <label class="form-label" for="fixed_amount">{{ __('common.fixed_amount') }} <span class="text-error">*</span></label>
                <input type="number" id="fixed_amount" name="fixed_amount" class="form-control" value="{{ old('fixed_amount') }}" step="0.01" min="0">
                @error('fixed_amount')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group" id="percentage_rate_group" style="display: none;">
                <label class="form-label" for="percentage_rate">{{ __('common.percentage_rate') }} <span class="text-error">*</span></label>
                <input type="number" id="percentage_rate" name="percentage_rate" class="form-control" value="{{ old('percentage_rate') }}" step="0.01" min="0" max="100">
                <small class="text-muted-sm">{{ __('common.percentage_rate_help') }}</small>
                @error('percentage_rate')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                    <label for="is_active">{{ __('common.is_active') }}</label>
                </div>
                @error('is_active')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>

        @include('config.commissions._form_scripts', [
            'costConfigsByType' => $costConfigsByType,
            'initialCommissionType' => old('commission_type'),
            'existingTiers' => old('tiers', []),
        ])

        <div id="tiered_section" style="display: none; margin-top: 30px;">
            <h2 style="color: var(--accent-color); margin-bottom: 10px;">{{ __('common.commission_tiers') }}</h2>
            <p id="tiered_inherit_note" class="text-muted-sm" style="display: none; margin-bottom: 20px;">{{ __('common.commission_tiers_inherit_from_cost') }}</p>
            
            <div id="tiers_container"></div>

            <button type="button" id="add_tier_btn" class="btn btn-secondary" style="margin-top: 15px; display: none;">+ {{ __('common.add_tier') }}</button>

            <div style="margin-top: 30px; padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
                <h3 style="color: var(--accent-color); margin-bottom: 15px;">{{ __('common.commission_preview') }}</h3>
                <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <input type="number" id="preview_amount" class="form-control" placeholder="{{ __('common.enter_amount') }}" step="0.01" min="0" style="width: 200px;">
                    <button type="button" id="calculate_btn" class="btn">{{ __('common.calculate') }}</button>
                    <div id="preview_result" style="color: var(--accent-color); font-size: 18px; font-weight: bold;"></div>
                </div>
            </div>
        </div>

        <div class="btn-row mt-30">
            <button type="submit" class="btn">{{ __('common.create') }}</button>
            <a href="{{ route('business.config.commissions.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection
