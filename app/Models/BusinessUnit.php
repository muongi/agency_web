<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

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
        'kato_token',
        'kato_token_expires_at',
        'kato_admin_username',
        'kato_admin_name',
        'kato_admin_password',
        'logo',
        'is_active',
        'cnss_enabled',
        'sunu_collector_cap_amount',
        'sunu_collector_cap_warning_pct',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cnss_enabled' => 'boolean',
        'kato_api_response' => 'array',
        'kato_token' => 'encrypted',
        'kato_admin_password' => 'encrypted',
        'kato_token_expires_at' => 'datetime',
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

    public function cbsCredentials()
    {
        return $this->hasOne(BusinessUnitCbsCredential::class);
    }

    public function cbsTransactionMappings()
    {
        return $this->hasMany(BusinessUnitCbsTransactionMapping::class);
    }

    /**
     * Whether CNSS module is enabled for this business unit.
     */
    public function isCnssEnabled(): bool
    {
        return (bool) $this->cnss_enabled;
    }

    /**
     * Whether this business unit is configured for KATO agency API calls.
     */
    public function isKatoEnabled(): bool
    {
        $this->loadMissing('coreBankingSystem');

        return $this->coreBankingSystem
            && $this->coreBankingSystem->isKatoDriver()
            && ! empty($this->kato_company_id);
    }

    /**
     * Whether the stored KATO JWT is expired or within the refresh buffer.
     */
    public function isKatoTokenExpired(int $bufferSeconds = 60): bool
    {
        if (! $this->kato_token_expires_at) {
            return false;
        }

        return $this->kato_token_expires_at->lte(now()->addSeconds($bufferSeconds));
    }

    /**
     * Whether a KATO bearer token is stored locally.
     */
    public function hasKatoToken(): bool
    {
        if (! empty($this->kato_token)) {
            return true;
        }

        if (! is_array($this->kato_api_response)) {
            return false;
        }

        return ! empty($this->kato_api_response['data']['token'])
            || ! empty($this->kato_api_response['token']);
    }

    /**
     * Whether the token should be refreshed before an authenticated KATO request.
     */
    public function shouldRefreshKatoTokenBeforeRequest(): bool
    {
        if ($this->isKatoTokenExpired()) {
            return true;
        }

        if ($this->kato_token_expires_at === null && $this->hasKatoToken() && $this->hasKatoRefreshCredentials()) {
            return true;
        }

        return false;
    }

    /**
     * Whether the token expires within the given number of minutes (for scheduled refresh).
     */
    public function isKatoTokenExpiringWithin(int $minutes = 15): bool
    {
        if ($this->shouldRefreshKatoTokenBeforeRequest()) {
            return true;
        }

        if (! $this->kato_token_expires_at) {
            return false;
        }

        return $this->kato_token_expires_at->lte(now()->addMinutes($minutes));
    }

    /**
     * Default expiry when KATO omits expiresIn from login/registration responses.
     */
    public static function defaultKatoTokenExpiresAt(): \Illuminate\Support\Carbon
    {
        return now()->addSeconds((int) config('kato.default_token_ttl', 3600));
    }

    /**
     * Admin username from KATO registration response (flat or nested JSON).
     */
    public function getKatoRegisteredAdminUsername(): ?string
    {
        if (! is_array($this->kato_api_response)) {
            return null;
        }

        $response = $this->kato_api_response;

        return $response['data']['adminUsername']
            ?? $response['adminUsername']
            ?? null;
    }

    /**
     * Whether username and password are stored for KATO company/login refresh.
     */
    public function hasKatoRefreshCredentials(): bool
    {
        $username = $this->kato_admin_username ?: $this->getKatoRegisteredAdminUsername();

        return ! empty($username) && ! empty($this->kato_admin_password);
    }

    /**
     * Whether stored username differs from the username in the KATO registration response.
     */
    public function katoCredentialsMismatch(): bool
    {
        $registered = $this->getKatoRegisteredAdminUsername();
        if (! $registered || ! $this->kato_admin_username) {
            return false;
        }

        return $this->kato_admin_username !== $registered;
    }

    /**
     * Whether this business unit uses the configurable CBS adapter.
     */
    public function hasConfigurableCbs(): bool
    {
        $this->loadMissing('coreBankingSystem');

        return $this->core_banking_system_id
            && $this->coreBankingSystem
            && $this->coreBankingSystem->isConfigurableDriver();
    }

    /**
     * Active transaction types available to this business (assigned global + BU-specific).
     *
     * @return \Illuminate\Support\Collection<int, TransactionType>
     */
    public function availableTransactionTypes()
    {
        return TransactionType::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereHas('businessUnits', function ($q) {
                    $q->where('business_units.id', $this->id);
                })->orWhere('business_unit_id', $this->id);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Whether the given transaction type is available to this business.
     */
    public function hasTransactionType(int $transactionTypeId): bool
    {
        return $this->availableTransactionTypes()->contains('id', $transactionTypeId);
    }

    /**
     * Transaction types eligible for CBS mapping (assigned global + BU-specific).
     */
    public function eligibleCbsTransactionTypes()
    {
        return $this->availableTransactionTypes();
    }

    /**
     * Accounts where this BusinessUnit is the accountable entity
     * (used for internal ledger fee pools, like business_fee).
     */
    public function accounts()
    {
        return $this->morphMany(Account::class, 'accountable');
    }

    /**
     * Lazily create (and return) the internal fee ledger account for this BU.
     */
    public function businessFeeAccount(): Account
    {
        $account = $this->accounts()
            ->where('account_type', 'business_fee')
            ->first();

        if ($account) {
            return $account;
        }

        return Account::create([
            'business_unit_id' => $this->id,
            'accountable_type' => BusinessUnit::class,
            'accountable_id' => $this->id,
            'account_type' => 'business_fee',
            'account_number' => Account::generateAccountNumber($this->id, 'business_fee'),
            'balance' => 0.00,
            'currency' => config('currency.default', 'CFA'),
            'status' => 'active',
            'opening_date' => now()->toDateString(),
        ]);
    }
}
