<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class ApiKey extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_unit_id',
        'name',
        'key',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'key',
    ];

    /**
     * Get the business unit that owns the API key.
     */
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * Generate a new API key.
     *
     * @param int $businessUnitId
     * @param string $name
     * @return static
     */
    public static function generate($businessUnitId, $name)
    {
        $plainKey = 'ak_' . Str::random(48);
        $hashedKey = Hash::make($plainKey);

        $apiKey = static::create([
            'business_unit_id' => $businessUnitId,
            'name' => $name,
            'key' => $hashedKey,
            'is_active' => true,
        ]);

        // Store plain key temporarily for return (in real app, show once)
        $apiKey->plain_key = $plainKey;

        return $apiKey;
    }

    /**
     * Find API key by plain key.
     *
     * @param string $plainKey
     * @return static|null
     */
    public static function findByKey($plainKey)
    {
        $apiKeys = static::where('is_active', true)->get();

        foreach ($apiKeys as $apiKey) {
            if (Hash::check($plainKey, $apiKey->key)) {
                return $apiKey;
            }
        }

        return null;
    }

    /**
     * Update last used timestamp.
     */
    public function updateLastUsed()
    {
        $this->update(['last_used_at' => now()]);
    }
}

