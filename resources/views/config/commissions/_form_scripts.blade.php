@php
    $selectedTransactionTypeId = $selectedTransactionTypeId ?? null;
    $existingTiers = $existingTiers ?? [];
    $initialCommissionType = old('commission_type', $initialCommissionType ?? '');
@endphp

<div id="tiered_cost_notice" class="muted" style="display: none; margin-top: 15px; padding: 12px; background: var(--bg-tertiary); border-radius: 8px;">
    <p id="tiered_cost_notice_text" style="margin: 0 0 8px;">{{ __('common.configure_transaction_cost_first') }}</p>
    <a id="tiered_cost_edit_link" href="#" class="btn btn-secondary btn-sm" style="display: none;">{{ __('common.edit_transaction_cost') }}</a>
</div>

@push('scripts')
<script>
    const costConfigsByType = @json($costConfigsByType ?? []);
    const existingTiers = @json(collect($existingTiers)->values());
    const selectedTransactionTypeId = @json($selectedTransactionTypeId);
    let tierIndex = 0;

    const transactionTypeSelect = document.getElementById('transaction_type_id');
    const commissionTypeSelect = document.getElementById('commission_type');
    const fixedGroup = document.getElementById('fixed_amount_group');
    const percentageGroup = document.getElementById('percentage_rate_group');
    const tieredSection = document.getElementById('tiered_section');
    const tiersContainer = document.getElementById('tiers_container');
    const addTierBtn = document.getElementById('add_tier_btn');
    const tieredCostNotice = document.getElementById('tiered_cost_notice');
    const tieredCostNoticeText = document.getElementById('tiered_cost_notice_text');
    const tieredCostEditLink = document.getElementById('tiered_cost_edit_link');
    const tieredInheritNote = document.getElementById('tiered_inherit_note');

    function getSelectedCostConfig() {
        const typeId = transactionTypeSelect
            ? transactionTypeSelect.value
            : String(selectedTransactionTypeId);

        return costConfigsByType[typeId] || null;
    }

    function updateCommissionTypeOptions() {
        const costConfig = getSelectedCostConfig();
        const tieredOptions = commissionTypeSelect.querySelectorAll('option[value="tiered_percentage"], option[value="tiered_fixed"], option[value="tiered"]');

        tieredOptions.forEach((option) => {
            option.hidden = !(costConfig && costConfig.is_tiered);
            option.disabled = !(costConfig && costConfig.is_tiered);
        });

        if (commissionTypeSelect.value.includes('tiered') && !(costConfig && costConfig.is_tiered)) {
            commissionTypeSelect.value = '';
        }

        if (tieredCostNotice) {
            const hasNonTieredCost = costConfig && !costConfig.is_tiered;
            tieredCostNotice.style.display = hasNonTieredCost ? 'block' : 'none';

            if (tieredCostNoticeText) {
                tieredCostNoticeText.textContent = hasNonTieredCost
                    ? @json(__('common.edit_existing_transaction_cost_for_tiered_commission'))
                    : @json(__('common.configure_transaction_cost_first'));
            }

            if (tieredCostEditLink) {
                if (hasNonTieredCost && costConfig.edit_url) {
                    tieredCostEditLink.href = costConfig.edit_url;
                    tieredCostEditLink.style.display = 'inline-block';
                } else {
                    tieredCostEditLink.style.display = 'none';
                }
            }
        }
    }

    function resetTiers() {
        tiersContainer.innerHTML = '';
        tierIndex = 0;
    }

    function addInheritedTier(costTier, commissionTier = null) {
        const commissionType = commissionTypeSelect.value;
        const showRate = commissionType === 'tiered_percentage' || commissionType === 'tiered';
        const showAmount = commissionType === 'tiered_fixed' || commissionType === 'tiered';
        const tierDiv = document.createElement('div');
        tierDiv.className = 'tier-item';
        tierDiv.style.cssText = 'padding: 20px; background: var(--bg-tertiary); border-radius: 8px; margin-bottom: 15px;';
        tierDiv.dataset.index = tierIndex;

        tierDiv.innerHTML = `
            <div style="margin-bottom: 15px;">
                <h4 style="color: var(--accent-color); margin: 0;">{{ __('common.tier') }} ${tierIndex + 1}</h4>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="form-group">
                    <label class="form-label">{{ __('common.min_amount') }}</label>
                    <input type="number" class="form-control" value="${costTier.min_amount ?? ''}" step="0.01" min="0" readonly>
                    <input type="hidden" name="tiers[${tierIndex}][min_amount]" value="${costTier.min_amount ?? ''}">
                </div>
                <div class="form-group">
                    <label class="form-label">{{ __('common.max_amount') }}</label>
                    <input type="number" class="form-control" value="${costTier.max_amount ?? ''}" step="0.01" min="0" readonly>
                    <input type="hidden" name="tiers[${tierIndex}][max_amount]" value="${costTier.max_amount ?? ''}">
                </div>
                ${showRate ? `
                <div class="form-group">
                    <label class="form-label">{{ __('common.rate') }} (%) <span class="text-error">*</span></label>
                    <input type="number" name="tiers[${tierIndex}][rate]" class="form-control tier-rate" value="${commissionTier ? (commissionTier.rate ?? '') : ''}" step="0.01" min="0" max="100" required>
                </div>
                ` : ''}
                ${showAmount ? `
                <div class="form-group">
                    <label class="form-label">{{ __('common.amount') }} <span class="text-error">*</span></label>
                    <input type="number" name="tiers[${tierIndex}][amount]" class="form-control tier-amount" value="${commissionTier ? (commissionTier.amount ?? '') : ''}" step="0.01" min="0" required>
                </div>
                ` : ''}
            </div>
        `;

        tiersContainer.appendChild(tierDiv);
        tierIndex++;
    }

    function renderInheritedTiers() {
        const costConfig = getSelectedCostConfig();
        resetTiers();

        if (!costConfig || !costConfig.is_tiered) {
            return;
        }

        costConfig.tiers.forEach((costTier, index) => {
            addInheritedTier(costTier, existingTiers[index] || null);
        });
    }

    function calculateCostFromConfig(costConfig, amount) {
        if (!costConfig) {
            return 0;
        }

        if (costConfig.cost_type === 'fixed') {
            return parseFloat(costConfig.fixed_amount) || 0;
        }

        if (costConfig.cost_type === 'percentage') {
            return amount * ((parseFloat(costConfig.percentage_rate) || 0) / 100);
        }

        for (const tier of costConfig.tiers) {
            const minAmount = parseFloat(tier.min_amount) || 0;
            const maxAmount = tier.max_amount !== null && tier.max_amount !== '' ? parseFloat(tier.max_amount) : null;

            if (amount >= minAmount && (maxAmount === null || amount <= maxAmount)) {
                if (costConfig.cost_type === 'tiered_percentage' && tier.rate !== null) {
                    return amount * (parseFloat(tier.rate) / 100);
                }
                if (costConfig.cost_type === 'tiered_fixed' && tier.amount !== null) {
                    return parseFloat(tier.amount) || 0;
                }
            }
        }

        return 0;
    }

    function calculateCommissionPreview(amount) {
        const type = commissionTypeSelect.value;
        const costConfig = getSelectedCostConfig();
        const transactionCost = calculateCostFromConfig(costConfig, amount);
        let commission = 0;

        if (type === 'fixed') {
            commission = parseFloat(document.getElementById('fixed_amount').value) || 0;
        } else if (type === 'percentage') {
            const rate = parseFloat(document.getElementById('percentage_rate').value) || 0;
            commission = transactionCost * (rate / 100);
        } else if (type === 'tiered_percentage' || type === 'tiered_fixed' || type === 'tiered') {
            const tierItems = document.querySelectorAll('.tier-item');
            for (let item of tierItems) {
                const minAmount = parseFloat(item.querySelector('input[name*="[min_amount]"]').value) || 0;
                const maxAmountInput = item.querySelector('input[name*="[max_amount]"]');
                const maxAmount = maxAmountInput.value ? parseFloat(maxAmountInput.value) : null;

                if (amount >= minAmount && (maxAmount === null || amount <= maxAmount)) {
                    const rate = parseFloat(item.querySelector('input[name*="[rate]"]')?.value) || 0;
                    const tierAmount = parseFloat(item.querySelector('input[name*="[amount]"]')?.value) || 0;

                    if (type === 'tiered_percentage' && rate > 0) {
                        commission = transactionCost * (rate / 100);
                    } else if (type === 'tiered_fixed' && tierAmount > 0) {
                        commission = tierAmount;
                    } else if (type === 'tiered') {
                        commission = rate > 0 ? transactionCost * (rate / 100) : tierAmount;
                    }
                    break;
                }
            }
        }

        return { transactionCost, commission };
    }

    function handleCommissionTypeChange() {
        const type = commissionTypeSelect.value;

        fixedGroup.style.display = 'none';
        percentageGroup.style.display = 'none';
        tieredSection.style.display = 'none';

        if (type === 'fixed') {
            fixedGroup.style.display = 'block';
            document.getElementById('fixed_amount').required = true;
            document.getElementById('percentage_rate').required = false;
        } else if (type === 'percentage') {
            percentageGroup.style.display = 'block';
            document.getElementById('percentage_rate').required = true;
            document.getElementById('fixed_amount').required = false;
        } else if (type === 'tiered_percentage' || type === 'tiered_fixed' || type === 'tiered') {
            tieredSection.style.display = 'block';
            document.getElementById('fixed_amount').required = false;
            document.getElementById('percentage_rate').required = false;
            if (tieredInheritNote) {
                tieredInheritNote.style.display = 'block';
            }
            if (addTierBtn) {
                addTierBtn.style.display = 'none';
            }
            renderInheritedTiers();
        }

        if (addTierBtn && !type.includes('tiered')) {
            addTierBtn.style.display = 'none';
        }
    }

    if (transactionTypeSelect) {
        transactionTypeSelect.addEventListener('change', function() {
            updateCommissionTypeOptions();
            handleCommissionTypeChange();
        });
    }

    commissionTypeSelect.addEventListener('change', handleCommissionTypeChange);

    document.getElementById('calculate_btn').addEventListener('click', function() {
        const amount = parseFloat(document.getElementById('preview_amount').value);
        const resultDiv = document.getElementById('preview_result');

        if (!amount || amount <= 0) {
            resultDiv.textContent = '';
            return;
        }

        const result = calculateCommissionPreview(amount);
        resultDiv.textContent = `{{ __('common.transaction_cost') }}: ${result.transactionCost.toFixed(2)} | {{ __('common.commission') }}: ${result.commission.toFixed(2)}`;
    });

    updateCommissionTypeOptions();
    @if($initialCommissionType)
        handleCommissionTypeChange();
    @endif
</script>
@endpush
