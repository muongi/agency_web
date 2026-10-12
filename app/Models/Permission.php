<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Permission extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'group',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('permission');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($permission) {
            if (empty($permission->slug)) {
                $permission->slug = Str::slug($permission->name);
            }
        });
    }

    /** Permissions only a super admin holds; never assignable to a business role. */
    public const SUPER_ADMIN_ONLY = ['view-business-unit', 'edit-business-unit', 'configure-sms', 'load-sms-credits', 'view-all-sms-balances'];

    /** Permission groups not shown on the role pages of this deployment. */
    public static function hiddenGroups(): array
    {
        $hidden = config('sunu.core_banking') ? [] : config('sunu.banking_permission_groups', []);
        if (! config('sunu.show_cnss')) {
            $hidden[] = 'CNSS';
        }

        return $hidden;
    }

    /** Translated heading of a permission group (common.permission_group_<slug>), raw name as fallback. */
    public static function groupLabel(?string $group): string
    {
        if (! $group) {
            return __('common.other');
        }
        $key = 'common.permission_group_'.Str::slug($group, '_');
        $label = trans($key);

        return $label === $key ? $group : $label;
    }

    /**
     * Permissions that can be ticked on a role page. The super admin role pages
     * ($includeSuperAdminOnly) also offer the super-admin-only slugs.
     */
    public function scopeAssignable($query, bool $includeSuperAdminOnly = false)
    {
        if (! $includeSuperAdminOnly) {
            $query->whereNotIn('slug', self::SUPER_ADMIN_ONLY);
        }

        return $query->where(function ($q) {
            $q->whereNull('group')->orWhereNotIn('group', self::hiddenGroups());
        });
    }

    /**
     * Ids to sync on a role saved from a role page: the ticked assignable ones, plus
     * the permissions the role already has that the page did not show (kept, so
     * turning core banking / CNSS back on restores them).
     */
    public static function syncIdsForRole(?Role $role, array $postedIds, bool $includeSuperAdminOnly = false): array
    {
        $assignableIds = self::assignable($includeSuperAdminOnly)->pluck('id')->all();
        $allowed = array_values(array_intersect(array_map('intval', $postedIds), $assignableIds));
        $kept = $role
            ? $role->permissions()->whereNotIn('permissions.id', $assignableIds)->pluck('permissions.id')->all()
            : [];

        return array_values(array_unique(array_merge($allowed, $kept)));
    }

    // Relationships
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permission')
                    ->withTimestamps();
    }
}
