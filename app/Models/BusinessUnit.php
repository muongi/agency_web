<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class BusinessUnit extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'email',
        'phone',
        'address',
        'account',
        'tax_number',
        'mailing_address',
        'relation_manager_phone',
        'relation_manager_email',
        'sms_sender_number',
        'core_banking_system_id',
        'kato_company_id',
        'kato_admin_user_id',
        'kato_registered_at',
        'kato_registration_error',
        'kato_api_response',
        'logo',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'kato_api_response' => 'array',
        'kato_registered_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('business_unit');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($businessUnit) {
            if (empty($businessUnit->slug)) {
                $businessUnit->slug = Str::slug($businessUnit->name);
            }
        });
    }

    // Relationships
    public function users()
    {
        return $this->belongsToMany(User::class, 'business_user')
                    ->withPivot('role_id', 'is_active')
                    ->withTimestamps();
    }

    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public function agents()
    {
        return $this->hasMany(Agent::class);
    }

    public function terminals()
    {
        return $this->hasMany(Terminal::class);
    }

    /**
     * Get business-specific transaction types (backward compatibility).
     */
    public function transactionTypes()
    {
        return $this->hasMany(TransactionType::class);
    }

    /**
     * Get assigned global transaction types via pivot table.
     */
    public function assignedTransactionTypes()
    {
        return $this->belongsToMany(TransactionType::class, 'business_unit_transaction_type')
                    ->withTimestamps();
    }

    public function commissionConfigurations()
    {
        return $this->hasMany(CommissionConfiguration::class);
    }

    public function cardSettings()
    {
        return $this->hasOne(CardSettings::class);
    }

    public function smsMessages()
    {
        return $this->hasMany(SmsMessage::class);
    }

    public function clientGroups()
    {
        return $this->hasMany(ClientGroup::class);
    }

    public function coreBankingSystem()
    {
        return $this->belongsTo(CoreBankingSystem::class);
    }
}
