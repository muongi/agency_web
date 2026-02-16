<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class SmsConfiguration extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'client_secret',
        'jwt_token',
        'api_endpoint',
        'sender_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'client_secret',
        'jwt_token',
    ];

    /**
     * Encrypt client_secret when setting
     */
    public function setClientSecretAttribute($value)
    {
        $this->attributes['client_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Decrypt client_secret when getting
     */
    public function getClientSecretAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    /**
     * Encrypt jwt_token when setting
     */
    public function setJwtTokenAttribute($value)
    {
        $this->attributes['jwt_token'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Decrypt jwt_token when getting
     */
    public function getJwtTokenAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    /**
     * Get active SMS configuration (singleton)
     */
    public static function getActive()
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Check if SMS is configured
     */
    public static function isConfigured()
    {
        $config = static::getActive();
        return $config && $config->client_id && $config->client_secret;
    }

    /**
     * Get credentials for API calls
     */
    public function getCredentials()
    {
        return [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'jwt_token' => $this->jwt_token,
            'api_endpoint' => $this->api_endpoint,
            'sender_name' => $this->sender_name,
        ];
    }

    /**
     * Scope for active configurations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
