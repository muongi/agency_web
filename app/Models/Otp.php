<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Otp extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_unit_id',
        'account_id',
        'card_number',
        'account_number',
        'phone_number',
        'otp_code',
        'expires_at',
        'verified_at',
        'is_used',
        'purpose',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'is_used' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the business unit that owns the OTP.
     */
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * Get the account associated with this OTP.
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Scope a query to only include valid (not expired and not used) OTPs.
     */
    public function scopeValid($query)
    {
        return $query->where('is_used', false)
                    ->where('expires_at', '>', now());
    }

    /**
     * Scope a query to only include unused OTPs.
     */
    public function scopeUnused($query)
    {
        return $query->where('is_used', false);
    }

    /**
     * Scope a query to only include expired OTPs.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Check if OTP is expired.
     */
    public function isExpired()
    {
        return $this->expires_at <= now();
    }

    /**
     * Check if OTP is valid (not expired and not used).
     */
    public function isValid()
    {
        return !$this->is_used && !$this->isExpired();
    }

    /**
     * Mark OTP as used.
     */
    public function markAsUsed()
    {
        $this->update([
            'is_used' => true,
        ]);
    }

    /**
     * Mark OTP as verified.
     */
    public function markAsVerified()
    {
        $this->update([
            'verified_at' => now(),
            'is_used' => true,
        ]);
    }

    /**
     * Generate a unique 5-digit OTP code.
     */
    public static function generateCode()
    {
        do {
            $code = str_pad((string) rand(10000, 99999), 5, '0', STR_PAD_LEFT);
        } while (static::where('otp_code', $code)
                    ->where('expires_at', '>', now())
                    ->where('is_used', false)
                    ->exists());

        return $code;
    }
}
