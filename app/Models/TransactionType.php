<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TransactionType extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    public const INTERNAL_LEDGER_FLOW_CASH_WITHDRAWAL = 'cash_withdrawal';

    public const INTERNAL_LEDGER_FLOW_CASH_DEPOSIT = 'cash_deposit';

    public const INTERNAL_LEDGER_FLOW_BALANCE_ENQUIRY = 'balance_enquiry';

    public const INTERNAL_LEDGER_FLOW_MINI_STATEMENT = 'mini_statement';

    protected $fillable = [
        'business_unit_id',
        'name',
        'code',
        'description',
        'internal_ledger_flow',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('transaction_type');
    }

    /**
     * Get the business unit that owns the transaction type (for backward compatibility).
     */
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * Get all business units that have this transaction type assigned.
     */
    public function businessUnits()
    {
        return $this->belongsToMany(BusinessUnit::class, 'business_unit_transaction_type')
                    ->withTimestamps();
    }

    /**
     * Get the commission configuration for this transaction type.
     */
    public function commissionConfiguration()
    {
        return $this->hasOne(CommissionConfiguration::class);
    }

    /**
     * Get the transaction cost configuration for this transaction type.
     */
    public function transactionCostConfiguration()
    {
        return $this->hasOne(TransactionCostConfiguration::class);
    }

    /**
     * Get all transactions for this transaction type.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Check if this is a global transaction type.
     */
    public function getIsGlobalAttribute()
    {
        return $this->business_unit_id === null;
    }

    /**
     * Scope a query to only include active transaction types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include global transaction types.
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('business_unit_id');
    }

    /**
     * Scope a query to only include business-specific transaction types.
     */
    public function scopeBusinessSpecific($query)
    {
        return $query->whereNotNull('business_unit_id');
    }
}
