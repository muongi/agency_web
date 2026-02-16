<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionCostConfiguration extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_unit_id',
        'transaction_type_id',
        'cost_type',
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
     * Get the business unit that owns the transaction cost configuration.
     */
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * Get the transaction type for this cost configuration.
     */
    public function transactionType()
    {
        return $this->belongsTo(TransactionType::class);
    }

    /**
     * Get the cost tiers for this configuration.
     */
    public function tiers()
    {
        return $this->hasMany(TransactionCostTier::class)->orderBy('order');
    }

    /**
     * Calculate transaction cost based on transaction amount.
     *
     * @param float $transactionAmount
     * @return float
     */
    public function calculateCost($transactionAmount)
    {
        switch ($this->cost_type) {
            case 'fixed':
                return $this->fixed_amount ?? 0;
            
            case 'percentage':
                return $transactionAmount * ($this->percentage_rate / 100);
            
            case 'tiered_fixed':
            case 'tiered_percentage':
                return $this->calculateTieredCost($transactionAmount);
            
            default:
                return 0;
        }
    }

    /**
     * Calculate cost for tiered type.
     *
     * @param float $transactionAmount
     * @return float
     */
    protected function calculateTieredCost($transactionAmount)
    {
        $tiers = $this->tiers()->orderBy('order')->get();
        
        foreach ($tiers as $tier) {
            $minAmount = $tier->min_amount ?? 0;
            $maxAmount = $tier->max_amount;
            
            // Check if transaction amount falls within this tier
            if ($transactionAmount >= $minAmount && ($maxAmount === null || $transactionAmount <= $maxAmount)) {
                // For tiered_percentage, use rate
                if ($this->cost_type === 'tiered_percentage' && $tier->rate !== null) {
                    return $transactionAmount * ($tier->rate / 100);
                }
                
                // For tiered_fixed, use amount
                if ($this->cost_type === 'tiered_fixed' && $tier->amount !== null) {
                    return $tier->amount;
                }
            }
        }
        
        return 0;
    }
}

