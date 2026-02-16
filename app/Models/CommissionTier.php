<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommissionTier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'commission_configuration_id',
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
        'order' => 'integer',
    ];

    /**
     * Get the commission configuration that owns this tier.
     */
    public function commissionConfiguration()
    {
        return $this->belongsTo(CommissionConfiguration::class);
    }
}
