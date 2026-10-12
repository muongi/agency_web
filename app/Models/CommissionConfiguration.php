<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommissionConfiguration extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_unit_id',
        'transaction_type_id',
        'commission_type',
        'fixed_amount',
        'percentage_rate',
        'is_active',
    ];

    protected $casts = [
        'fixed_amount' => 'decimal:2',
        'percentage_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the business unit that owns the commission configuration.
     */
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * Get the transaction type for this commission configuration.
     */
    public function transactionType()
    {
        return $this->belongsTo(TransactionType::class);
    }

    /**
     * Get the commission tiers for this configuration.
     */
    public function tiers()
    {
        return $this->hasMany(CommissionTier::class)->orderBy('order');
    }

    /**
     * Whether this configuration uses tiered commission.
     */
    public function isTieredCommission(): bool
    {
        return in_array($this->commission_type, ['tiered', 'tiered_percentage', 'tiered_fixed'], true);
    }

    /**
     * Calculate commission based on transaction cost (not transaction amount).
     *
     * @param float $transactionCost The transaction cost (calculated from transaction amount)
     * @return float
     */
    public function calculateCommission($transactionCost)
    {
        switch ($this->commission_type) {
            case 'fixed':
                return $this->fixed_amount ?? 0;

            case 'percentage':
                return $transactionCost * ($this->percentage_rate / 100);

            case 'tiered':
            case 'tiered_percentage':
            case 'tiered_fixed':
                return $this->calculateTieredCommission($transactionCost);

            default:
                return 0;
        }
    }

    /**
     * Calculate commission for tiered type based on transaction cost bands.
     * Used only for legacy configs; new tiered commissions inherit cost tiers via calculateFromTransactionAmount().
     *
     * @param float $transactionCost The transaction cost
     * @return float
     */
    protected function calculateTieredCommission($transactionCost)
    {
        $tiers = $this->tiers()->orderBy('order')->get();

        foreach ($tiers as $tier) {
            $minAmount = $tier->min_amount ?? 0;
            $maxAmount = $tier->max_amount;

            if ($transactionCost >= $minAmount && ($maxAmount === null || $transactionCost <= $maxAmount)) {
                return $this->applyCommissionTier($tier, $transactionCost);
            }
        }

        return 0;
    }

    /**
     * Calculate commission from transaction amount (full flow: amount → cost → commission).
     *
     * @param float $transactionAmount The original transaction amount
     * @param int|null $transactionTypeId Optional transaction type ID (uses current if not provided)
     * @return float
     */
    public function calculateFromTransactionAmount($transactionAmount, $transactionTypeId = null)
    {
        $transactionTypeId = $transactionTypeId ?? $this->transaction_type_id;

        $costConfig = TransactionCostConfiguration::where('transaction_type_id', $transactionTypeId)
            ->where('business_unit_id', $this->business_unit_id)
            ->where('is_active', true)
            ->with('tiers')
            ->first();

        if (!$costConfig) {
            return 0;
        }

        $transactionCost = $costConfig->calculateCost($transactionAmount);

        if ($this->isTieredCommission()) {
            if (!$costConfig->isTiered()) {
                return 0;
            }

            $tierOrder = $costConfig->findTierOrderForAmount($transactionAmount);
            if ($tierOrder === null) {
                return 0;
            }

            $commissionTier = $this->tiers()->where('order', $tierOrder)->first();
            if (!$commissionTier) {
                return 0;
            }

            return $this->applyCommissionTier($commissionTier, $transactionCost);
        }

        return $this->calculateCommission($transactionCost);
    }

    /**
     * Apply a commission tier rate or amount to a transaction cost.
     */
    protected function applyCommissionTier(CommissionTier $tier, float $transactionCost): float
    {
        if ($this->commission_type === 'tiered_percentage' && $tier->rate !== null) {
            return $transactionCost * ($tier->rate / 100);
        }

        if ($this->commission_type === 'tiered_fixed' && $tier->amount !== null) {
            return $tier->amount;
        }

        if ($this->commission_type === 'tiered') {
            if ($tier->rate !== null) {
                return $transactionCost * ($tier->rate / 100);
            }

            return $tier->amount ?? 0;
        }

        return 0;
    }
}
