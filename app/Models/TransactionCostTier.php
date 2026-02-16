<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionCostTier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_cost_configuration_id',
        'min_amount',
        'max_amount',
        'rate',
        'amount',
        'order',
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    /**
     * Get the transaction cost configuration that owns this tier.
     */
    public function transactionCostConfiguration()
    {
        return $this->belongsTo(TransactionCostConfiguration::class);
    }
}

