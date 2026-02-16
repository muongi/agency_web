<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class CardSettings extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'business_unit_id',
        'card_prefix',
        'auto_increment_length',
        'default_expiration_years',
        'default_daily_transaction_limit',
        'default_daily_withdrawal_limit',
    ];

    protected $casts = [
        'auto_increment_length' => 'integer',
        'default_expiration_years' => 'integer',
        'default_daily_transaction_limit' => 'decimal:2',
        'default_daily_withdrawal_limit' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('card_settings');
    }

    // Relationships
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    // Helper Methods
    public static function getOrCreateForBusinessUnit($businessUnitId)
    {
        return static::firstOrCreate(
            ['business_unit_id' => $businessUnitId],
            [
                'card_prefix' => '0000',
                'auto_increment_length' => 6,
                'default_expiration_years' => 3,
                'default_daily_transaction_limit' => null,
                'default_daily_withdrawal_limit' => null,
            ]
        );
    }

    public function getDefaultExpirationDate()
    {
        return now()->addYears($this->default_expiration_years);
    }
}
