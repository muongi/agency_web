@extends('layouts.app')

@section('title', __('common.edit_commission'))
@section('page-title', __('common.edit_commission'))

@section('content')
<div class="card">
    <div style="background: var(--bg-tertiary); padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid var(--accent-color);">
        <p style="margin: 0; color: var(--text-primary); font-size: 14px;">
            <strong>{{ __('common.commission_calculation_note') }}</strong>
        </p>
    </div>
    <h1 style="margin-bottom: 24px; color: var(--accent-color);">{{ __('common.edit_commission') }}</h1>
    
    <form method="POST" action="{{ route('business.config.commissions.update', $commission->id) }}" id="commissionForm">
        @csrf
        @method('PUT')

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label class="form-label" for="transaction_type_id">{{ __('common.transaction_type') }}</label>
                <input type="text" class="form-control" value="{{ $commission->transactionType->name }} ({{ $commission->transactionType->code }})" disabled>
                <small style="color: var(--text-muted); font-size: 12px;">{{ __('common.transaction_type_cannot_be_changed') }}</small>
            </div>

            <div class="form-group">
                <label class="form-label" for="commission_type">{{ __('common.commission_type') }} <span style="color: #ff6b6b;">*</span></label>
                <select id="commission_type" name="commission_type" class="form-select" required>
                    <option value="">{{ __('common.select_commission_type') }}</option>
                    <option value="fixed" {{ old('commission_type', $commission->commission_type) == 'fixed' ? 'selected' : '' }}>{{ __('common.fixed') }}</option>
                    <option value="percentage" {{ old('commission_type', $commission->commission_type) == 'percentage' ? 'selected' : '' }}>{{ __('common.percentage') }}</option>
                    <option value="tiered_percentage" {{ old('commission_type', $commission->commission_type) == 'tiered_percentage' ? 'selected' : '' }}>{{ __('common.tiered_percentage') }}</option>
                    <option value="tiered_fixed" {{ old('commission_type', $commission->commission_type) == 'tiered_fixed' ? 'selected' : '' }}>{{ __('common.tiered_fixed') }}</option>
                </select>
                @error('commission_type')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group" id="fixed_amount_group" style="display: none;">
                <label class="form-label" for="fixed_amount">{{ __('common.fixed_amount') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="number" id="fixed_amount" name="fixed_amount" class="form-control" value="{{ old('fixed_amount', $commission->fixed_amount) }}" step="0.01" min="0">
                @error('fixed_amount')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group" id="percentage_rate_group" style="display: none;">
                <label class="form-label" for="percentage_rate">{{ __('common.percentage_rate') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="number" id="percentage_rate" name="percentage_rate" class="form-control" value="{{ old('percentage_rate', $commission->percentage_rate) }}" step="0.01" min="0" max="100">
                <small style="color: var(--text-muted); font-size: 12px;">{{ __('common.percentage_rate_help') }}</small>
                @error('percentage_rate')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $commission->is_active) ? 'checked' : '' }}>
                    <label for="is_active">{{ __('common.is_active') }}</label>
                </div>
                @error('is_active')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <!-- Tiered Commission Section -->
        <div id="tiered_section" style="display: none; margin-top: 30px;">
            <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.commission_tiers') }}</h2>
            
            <div id="tiers_container">
                <!-- Tiers will be added here dynamically -->
            </div>

            <button type="button" id="add_tier_btn" class="btn btn-secondary" style="margin-top: 15px;">+ {{ __('common.add_tier') }}</button>

            <!-- Commission Preview -->
            <div style="margin-top: 30px; padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
                <h3 style="color: var(--accent-color); margin-bottom: 15px;">{{ __('common.commission_preview') }}</h3>
                <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <input type="number" id="preview_amount" class="form-control" placeholder="{{ __('common.enter_amount') }}" step="0.01" min="0" style="width: 200px;">
                    <button type="button" id="calculate_btn" class="btn">{{ __('common.calculate') }}</button>
                    <div id="preview_result" style="color: var(--accent-color); font-size: 18px; font-weight: bold;"></div>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 30px;">
            <button type="submit" class="btn">{{ __('common.update') }}</button>
            <a href="{{ route('business.config.commissions.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
    let tierIndex = 0;
    const existingTiers = @json($commission->tiers->sortBy('order')->values());

    document.getElementById('commission_type').addEventListener('change', function() {
        const type = this.value;
        const fixedGroup = document.getElementById('fixed_amount_group');
        const percentageGroup = document.getElementById('percentage_rate_group');
        const tieredSection = document.getElementById('tiered_section');

        // Hide all groups
        fixedGroup.style.display = 'none';
        percentageGroup.style.display = 'none';
        tieredSection.style.display = 'none';

        // Show relevant group
        if (type === 'fixed') {
            fixedGroup.style.display = 'block';
            document.getElementById('fixed_amount').required = true;
            document.getElementById('percentage_rate').required = false;
        } else if (type === 'percentage') {
            percentageGroup.style.display = 'block';
            document.getElementById('percentage_rate').required = true;
            document.getElementById('fixed_amount').required = false;
        } else if (type === 'tiered_percentage' || type === 'tiered_fixed') {
            tieredSection.style.display = 'block';
            document.getElementById('fixed_amount').required = false;
            document.getElementById('percentage_rate').required = false;
            // Load existing tiers
            if (tierIndex === 0 && existingTiers.length > 0) {
                existingTiers.forEach(tier => {
                    addTier(tier);
                });
            } else if (tierIndex === 0) {
                addTier();
            }
        }
    });

    // Initialize on page load
    document.getElementById('commission_type').dispatchEvent(new Event('change'));

    function addTier(tier = null) {
        const container = document.getElementById('tiers_container');
        const commissionType = document.getElementById('commission_type').value;
        const tierDiv = document.createElement('div');
        tierDiv.className = 'tier-item';
        tierDiv.style.cssText = 'padding: 20px; background: var(--bg-tertiary); border-radius: 8px; margin-bottom: 15px;';
        tierDiv.dataset.index = tierIndex;

        // Determine which fields to show based on commission type
        const showRate = commissionType === 'tiered_percentage';
        const showAmount = commissionType === 'tiered_fixed';
        const showBoth = commissionType === 'tiered'; // Legacy support

        tierDiv.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h4 style="color: var(--accent-color); margin: 0;">{{ __('common.tier') }} ${tierIndex + 1}</h4>
                <button type="button" class="btn btn-danger" onclick="removeTier(this)" style="padding: 6px 12px; font-size: 12px;">{{ __('common.remove') }}</button>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="form-group">
                    <label class="form-label">{{ __('common.min_amount') }}</label>
                    <input type="number" name="tiers[${tierIndex}][min_amount]" class="form-control" value="${tier ? tier.min_amount : ''}" step="0.01" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('common.max_amount') }}</label>
                    <input type="number" name="tiers[${tierIndex}][max_amount]" class="form-control" value="${tier ? tier.max_amount : ''}" step="0.01" min="0">
                </div>
                ${showRate || showBoth ? `
                <div class="form-group">
                    <label class="form-label">{{ __('common.rate') }} (%) <span style="color: #ff6b6b;">${showRate ? '*' : ''}</span></label>
                    <input type="number" name="tiers[${tierIndex}][rate]" class="form-control tier-rate" value="${tier ? tier.rate : ''}" step="0.01" min="0" max="100" ${showRate ? 'required' : ''}>
                </div>
                ` : ''}
                ${showAmount || showBoth ? `
                <div class="form-group">
                    <label class="form-label">{{ __('common.amount') }} <span style="color: #ff6b6b;">${showAmount ? '*' : ''}</span></label>
                    <input type="number" name="tiers[${tierIndex}][amount]" class="form-control tier-amount" value="${tier ? tier.amount : ''}" step="0.01" min="0" ${showAmount ? 'required' : ''}>
                </div>
                ` : ''}
            </div>
            <small style="color: var(--text-muted); font-size: 12px;">${showRate ? '{{ __('common.tier_percentage_help') }}' : showAmount ? '{{ __('common.tier_fixed_help') }}' : '{{ __('common.tier_help') }}'}</small>
        `;

        container.appendChild(tierDiv);
        tierIndex++;
    }

    function removeTier(btn) {
        btn.closest('.tier-item').remove();
    }

    document.getElementById('add_tier_btn').addEventListener('click', function() {
        addTier();
    });

    // Commission preview calculation
    document.getElementById('calculate_btn').addEventListener('click', function() {
        const amount = parseFloat(document.getElementById('preview_amount').value);
        const type = document.getElementById('commission_type').value;
        const resultDiv = document.getElementById('preview_result');

        if (!amount || amount <= 0) {
            resultDiv.textContent = '';
            return;
        }

        let commission = 0;

        if (type === 'fixed') {
            commission = parseFloat(document.getElementById('fixed_amount').value) || 0;
        } else if (type === 'percentage') {
            const rate = parseFloat(document.getElementById('percentage_rate').value) || 0;
            commission = amount * (rate / 100);
        } else if (type === 'tiered_percentage' || type === 'tiered_fixed') {
            // Find matching tier
            const tierItems = document.querySelectorAll('.tier-item');
            for (let item of tierItems) {
                const minAmount = parseFloat(item.querySelector('input[name*="[min_amount]"]').value) || 0;
                const maxAmountInput = item.querySelector('input[name*="[max_amount]"]');
                const maxAmount = maxAmountInput.value ? parseFloat(maxAmountInput.value) : null;
                const rate = parseFloat(item.querySelector('input[name*="[rate]"]').value) || 0;
                const tierAmount = parseFloat(item.querySelector('input[name*="[amount]"]').value) || 0;

                if (amount >= minAmount && (maxAmount === null || amount <= maxAmount)) {
                    if (type === 'tiered_percentage' && rate > 0) {
                        commission = amount * (rate / 100);
                    } else if (type === 'tiered_fixed' && tierAmount > 0) {
                        commission = tierAmount;
                    } else if (type === 'tiered_percentage' || type === 'tiered_fixed') {
                        // For new tiered types, use the appropriate field
                        if (rate > 0) {
                            commission = amount * (rate / 100);
                        } else if (tierAmount > 0) {
                            commission = tierAmount;
                        }
                    }
                    break;
                }
            }
        }

        resultDiv.textContent = `{{ __('common.commission') }}: ${commission.toFixed(2)}`;
    });
</script>
@endpush
@endsection

