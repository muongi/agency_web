<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_unit_id',
        'accountable_type',
        'accountable_id',
        'account_type',
        'account_number',
        'balance',
        'currency',
        'status',
        'opening_date',
        'closing_date',
        'description',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'opening_date' => 'date',
        'closing_date' => 'date',
    ];

    /**
     * Get the parent accountable model (Client or Agent).
     */
    public function accountable()
    {
        return $this->morphTo();
    }

    /**
     * Get the business unit that owns the account.
     */
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * Scope a query to only include active accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include accounts for clients.
     */
    public function scopeForClient($query)
    {
        return $query->where('accountable_type', Client::class);
    }

    /**
     * Scope a query to only include accounts for agents.
     */
    public function scopeForAgent($query)
    {
        return $query->where('accountable_type', Agent::class);
    }

    /**
     * Scope a query to only include float accounts.
     */
    public function scopeFloatAccounts($query)
    {
        return $query->where('account_type', 'float');
    }

    /**
     * Scope a query to only include commission accounts.
     */
    public function scopeCommissionAccounts($query)
    {
        return $query->where('account_type', 'commission');
    }

    /**
     * Generate a unique account number for the given business unit.
     *
     * @param int $businessUnitId
     * @param string $accountType
     * @param int|null $accountableId
     * @return string
     */
    public static function generateAccountNumber($businessUnitId, $accountType, $accountableId = null)
    {
        $prefix = '';
        switch ($accountType) {
            case 'float':
                $prefix = 'FLT';
                break;
            case 'commission':
                $prefix = 'COM';
                break;
            case 'general':
                $prefix = 'GEN';
                break;
        }

        $baseNumber = $prefix . '-' . str_pad($businessUnitId, 4, '0', STR_PAD_LEFT);
        
        if ($accountableId) {
            $baseNumber .= '-' . str_pad($accountableId, 6, '0', STR_PAD_LEFT);
        }

        // Check if account number exists, if so, append a suffix
        $counter = 1;
        $accountNumber = $baseNumber;
        while (static::where('business_unit_id', $businessUnitId)
            ->where('account_number', $accountNumber)
            ->exists()) {
            $accountNumber = $baseNumber . '-' . str_pad($counter, 3, '0', STR_PAD_LEFT);
            $counter++;
        }

        return $accountNumber;
    }

    /**
     * Check if account is active.
     */
    public function isActive()
    {
        return $this->status === 'active';
    }

    /**
     * Check if account is for an agent.
     */
    public function isForAgent()
    {
        return $this->accountable_type === Agent::class;
    }

    /**
     * Check if account is for a client.
     */
    public function isForClient()
    {
        return $this->accountable_type === Client::class;
    }

    /**
     * Check if this is a required account (float or commission for agents).
     */
    public function isRequired()
    {
        return $this->isForAgent() && in_array($this->account_type, ['float', 'commission']);
    }

    public function cards()
    {
        return $this->hasMany(Card::class);
    }
}
