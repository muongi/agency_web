<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BusinessUnitCbsCredential extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'business_unit_id',
        'username',
        'password',
        'bearer_token',
        'token_expires_at',
        'extra_headers',
        'extra_fields',
    ];

    protected $casts = [
        'extra_headers' => 'array',
        'extra_fields' => 'array',
        'token_expires_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
        'bearer_token',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('cbs_credential');
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function setPasswordAttribute(?string $value): void
    {
        $this->attributes['password'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getPasswordAttribute(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setBearerTokenAttribute(?string $value): void
    {
        $this->attributes['bearer_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getBearerTokenAttribute(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function isTokenExpired(): bool
    {
        if (! $this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->isPast();
    }
}
