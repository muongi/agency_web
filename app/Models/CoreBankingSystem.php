<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class CoreBankingSystem extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
        'base_url',
        'driver',
        'auth_type',
        'auth_config',
        'timeout_seconds',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auth_config' => 'array',
        'timeout_seconds' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('core_banking_system');
    }

    // Relationships
    public function businessUnits()
    {
        return $this->hasMany(BusinessUnit::class);
    }

    public function isKatoDriver(): bool
    {
        return $this->driver === 'kato' || strtolower((string) $this->code) === 'kato';
    }

    public function isConfigurableDriver(): bool
    {
        return $this->driver === 'configurable' && ! $this->isKatoDriver();
    }
}
