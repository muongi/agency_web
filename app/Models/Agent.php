<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Agent extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'business_unit_id',
        'first_name',
        'second_name',
        'photo',
        'email',
        'phone_number',
        'id_passport',
        'physical_address',
        'po_box',
        'username',
        'password',
        'is_first_time_pin',
        'kato_agent_id',
        'kato_registration_error',
    ];

    protected $casts = [
        'is_first_time_pin' => 'boolean',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Get the business unit that owns the agent.
     */
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * Get the agent's full name.
     */
    public function getFullNameAttribute()
    {
        $name = trim($this->first_name . ' ' . $this->second_name);
        return $name;
    }

    /**
     * Get the terminals assigned to this agent (active assignments only).
     */
    public function terminals()
    {
        return $this->belongsToMany(Terminal::class, 'agent_terminal')
                    ->withPivot('assigned_at', 'unassigned_at')
                    ->wherePivotNull('unassigned_at')
                    ->withTimestamps();
    }

    /**
     * Get all terminal assignments (including unassigned ones).
     */
    public function terminalAssignments()
    {
        return $this->belongsToMany(Terminal::class, 'agent_terminal')
                    ->withPivot('assigned_at', 'unassigned_at')
                    ->withTimestamps();
    }

    /**
     * Get all transactions for this agent.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get all accounts for this agent.
     */
    public function accounts()
    {
        return $this->morphMany(Account::class, 'accountable');
    }

    /**
     * Get the float account for this agent.
     */
    public function floatAccount()
    {
        return $this->accounts()->where('account_type', 'float')->first();
    }

    /**
     * Get the commission account for this agent.
     */
    public function commissionAccount()
    {
        return $this->accounts()->where('account_type', 'commission')->first();
    }

    /**
     * Get the float account balance.
     */
    public function getFloatBalanceAttribute()
    {
        $floatAccount = $this->floatAccount();
        return $floatAccount ? $floatAccount->balance : 0;
    }

    /**
     * Get the commission account balance.
     */
    public function getCommissionBalanceAttribute()
    {
        $commissionAccount = $this->commissionAccount();
        return $commissionAccount ? $commissionAccount->balance : 0;
    }

    /**
     * KATO float account number (e.g. AGT2605001FLOAT) for agentAccountNumber on CBS calls.
     */
    public function katoFloatAccountNumber(): ?string
    {
        $account = $this->floatAccount();

        return $account?->account_number;
    }

    /**
     * KATO commission account number (e.g. AGT2605001COMM).
     */
    public function katoCommissionAccountNumber(): ?string
    {
        $account = $this->commissionAccount();

        return $account?->account_number;
    }

    /**
     * Whether this agent has KATO CBS account numbers synced after registration.
     */
    public function hasKatoCbsAccounts(): bool
    {
        if (! $this->kato_agent_id) {
            return false;
        }

        $float = $this->katoFloatAccountNumber();
        if (! $float || ! str_ends_with(strtoupper($float), 'FLOAT')) {
            return false;
        }

        return true;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('agent')
            ->dontLogIfAttributesChangedOnly(['password']);
    }

    /**
     * Boot method to auto-create float and commission accounts.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($agent) {
            $businessUnit = $agent->businessUnit;
            
            // Create Float Account
            Account::create([
                'business_unit_id' => $businessUnit->id,
                'accountable_type' => Agent::class,
                'accountable_id' => $agent->id,
                'account_type' => 'float',
                'account_number' => Account::generateAccountNumber($businessUnit->id, 'float', $agent->id),
                'balance' => 0.00,
                'currency' => config('currency.default', 'CFA'),
                'status' => 'active',
                'opening_date' => now()->toDateString(),
            ]);

            // Create Commission Account
            Account::create([
                'business_unit_id' => $businessUnit->id,
                'accountable_type' => Agent::class,
                'accountable_id' => $agent->id,
                'account_type' => 'commission',
                'account_number' => Account::generateAccountNumber($businessUnit->id, 'commission', $agent->id),
                'balance' => 0.00,
                'currency' => config('currency.default', 'CFA'),
                'status' => 'active',
                'opening_date' => now()->toDateString(),
            ]);
        });
    }
}
