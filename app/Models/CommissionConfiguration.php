<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\TransactionCostConfiguration;

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
     * Calculate commission based on transaction cost (not transaction amount).
     * Commission is now calculated from the transaction cost, not the original amount.
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
     * Calculate commission for tiered type based on transaction cost.
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
            
            // Check if transaction cost falls within this tier
            if ($transactionCost >= $minAmount && ($maxAmount === null || $transactionCost <= $maxAmount)) {
                // For tiered_percentage, always use rate
                if ($this->commission_type === 'tiered_percentage' && $tier->rate !== null) {
                    return $transactionCost * ($tier->rate / 100);
                }
                
                // For tiered_fixed, always use amount
                if ($this->commission_type === 'tiered_fixed' && $tier->amount !== null) {
                    return $tier->amount;
                }
                
                // For legacy 'tiered' type, check both rate and amount
                if ($this->commission_type === 'tiered') {
                    if ($tier->rate !== null) {
                        return $transactionCost * ($tier->rate / 100);
                    }
                    return $tier->amount ?? 0;
                }
            }
        }
        
        return 0;
    }

    /**
     * Calculate commission from transaction amount (full flow: amount → cost → commission).
     * This is a convenience method that calculates cost first, then commission.
     *
     * @param float $transactionAmount The original transaction amount
     * @param int|null $transactionTypeId Optional transaction type ID (uses current if not provided)
     * @return float
     */
    public function calculateFromTransactionAmount($transactionAmount, $transactionTypeId = null)
    {
        $transactionTypeId = $transactionTypeId ?? $this->transaction_type_id;
        
        // Get transaction cost configuration
        $costConfig = TransactionCostConfiguration::where('transaction_type_id', $transactionTypeId)
            ->where('business_unit_id', $this->business_unit_id)
            ->where('is_active', true)
            ->first();
        
        if (!$costConfig) {
            // If no cost configuration, return 0 (no cost = no commission)
            return 0;
        }
        
        // Calculate transaction cost
        $transactionCost = $costConfig->calculateCost($transactionAmount);
        
        // Calculate commission from cost
        return $this->calculateCommission($transactionCost);
    }
}
