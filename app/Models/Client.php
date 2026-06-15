<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Client extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'business_unit_id',
        'salutation',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'id_passport',
        'home_address',
        'phone_number',
        'photo',
        'gender',
        'marital_status',
        'year_of_birth',
        'region',
        'home_town',
        'nationality',
        'next_of_kin_first_name',
        'next_of_kin_middle_name',
        'next_of_kin_last_name',
        'next_of_kin_phone_number',
        'next_of_kin_address',
        'next_of_kin_gender',
        'fingerprint1',
        'fingerprint2',
        'fingerprint3',
        'fingerprint4',
        'biometrics_status',
        'kato_member_no',
        'kato_registration_error',
        'kato_api_response',
    ];

    protected $casts = [
        'year_of_birth' => 'string',
        'biometrics_status' => 'boolean',
        'kato_api_response' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('client');
    }

    /**
     * Get the business unit that owns the client.
     */
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * Get the client's full name.
     */
    public function getFullNameAttribute()
    {
        $name = trim($this->first_name . ' ' . $this->middle_name . ' ' . $this->last_name);
        return $name;
    }

    /**
     * Get the next of kin's full name.
     */
    public function getNextOfKinFullNameAttribute()
    {
        if (!$this->next_of_kin_first_name) {
            return null;
        }
        $name = trim($this->next_of_kin_first_name . ' ' . $this->next_of_kin_middle_name . ' ' . $this->next_of_kin_last_name);
        return $name;
    }

    /**
     * Get all transactions for this client.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get all accounts for this client.
     */
    public function accounts()
    {
        return $this->morphMany(Account::class, 'accountable');
    }

    /**
     * Get the total balance across all accounts.
     */
    public function getTotalAccountBalanceAttribute()
    {
        return $this->accounts()->sum('balance');
    }

    /**
     * Check if client has an account with the given account number.
     */
    public function hasAccount($accountNumber)
    {
        return $this->accounts()->where('account_number', $accountNumber)->exists();
    }

    /**
     * Get all SMS messages sent to this client.
     */
    public function smsMessages()
    {
        return $this->hasMany(SmsMessage::class, 'recipient_client_id');
    }

    /**
     * Get all groups this client belongs to.
     */
    public function clientGroups()
    {
        return $this->belongsToMany(ClientGroup::class, 'client_client_group')
                    ->withTimestamps();
    }
}
