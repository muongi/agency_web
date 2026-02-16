<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'preferred_language',
        'is_super_admin',
        'current_business_unit_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_super_admin' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('user')
            ->dontLogIfAttributesChangedOnly(['remember_token', 'current_business_unit_id']);
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin()
    {
        return $this->is_super_admin === true;
    }

    // Relationships
    public function businessUnits()
    {
        return $this->belongsToMany(BusinessUnit::class, 'business_user')
                    ->withPivot('role_id', 'is_active')
                    ->withTimestamps();
    }

    public function currentBusinessUnit()
    {
        return $this->belongsTo(BusinessUnit::class, 'current_business_unit_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'business_user')
                    ->withPivot('business_unit_id', 'is_active')
                    ->withTimestamps();
    }

    // Helper methods
    public function hasBusinessUnit($businessUnitId)
    {
        return $this->businessUnits()->where('business_units.id', $businessUnitId)->exists();
    }

    public function getRoleInBusiness($businessUnitId)
    {
        $pivot = $this->businessUnits()
            ->where('business_units.id', $businessUnitId)
            ->first();
        
        return $pivot ? Role::find($pivot->pivot->role_id) : null;
    }

    /**
     * Check if user has a specific permission in a business unit
     */
    public function hasPermission($permissionSlug, $businessUnitId = null)
    {
        // Super admin has all permissions
        if ($this->isSuperAdmin()) {
            return true;
        }

        $businessUnitId = $businessUnitId ?? $this->current_business_unit_id;
        
        if (!$businessUnitId) {
            return false;
        }

        $role = $this->getRoleInBusiness($businessUnitId);
        
        if (!$role) {
            return false;
        }

        return $role->permissions()->where('slug', $permissionSlug)->exists();
    }
}
