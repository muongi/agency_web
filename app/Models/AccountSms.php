<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountSms extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'account_sms';

    protected $fillable = [
        'business_unit_id',
        'debit',
        'credit',
        'description',
        'user_id',
        'reference',
        'metadata',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Get the business unit that owns the SMS account
     */
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * Get the user who performed the transaction
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculate and get balance for a business unit
     */
    public static function getBalance($businessUnitId)
    {
        $credits = self::where('business_unit_id', $businessUnitId)->sum('credit');
        $debits = self::where('business_unit_id', $businessUnitId)->sum('debit');
        return $credits - $debits;
    }

    /**
     * Check if business unit has SMS balance
     */
    public static function checkBalance($businessUnitId)
    {
        $balance = self::getBalance($businessUnitId);
        return $balance > 0;
    }

    /**
     * Insert SMS account transaction
     */
    public static function insertAccountSms($businessUnitId, $debit, $credit, $description, $userId = null, $reference = null, $metadata = null)
    {
        return self::create([
            'business_unit_id' => $businessUnitId,
            'debit' => $debit,
            'credit' => $credit,
            'description' => $description,
            'user_id' => $userId,
            'reference' => $reference,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Scope for specific business unit
     */
    public function scopeForBusinessUnit($query, $businessUnitId)
    {
        return $query->where('business_unit_id', $businessUnitId);
    }

    /**
     * Scope for credit transactions
     */
    public function scopeCredits($query)
    {
        return $query->where('credit', '>', 0);
    }

    /**
     * Scope for debit transactions
     */
    public function scopeDebits($query)
    {
        return $query->where('debit', '>', 0);
    }
}
