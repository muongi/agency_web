<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Correction request permissions: created and given to every existing role (same rule as
 * 2026_09_26_000000 — nobody loses the new menu on deploy; narrow per role afterwards).
 */
return new class extends Migration
{
    protected array $slugs = ['view-sunu-corrections', 'approve-sunu-corrections', 'apply-sunu-corrections'];

    public function up(): void
    {
        foreach (PermissionsSeeder::sunuPermissions() as $permission) {
            if (in_array($permission['slug'], $this->slugs, true)) {
                Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
            }
        }

        $ids = Permission::whereIn('slug', $this->slugs)->pluck('id')->all();
        Role::query()->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids));
    }

    public function down(): void
    {
        Permission::whereIn('slug', $this->slugs)->get()->each(function (Permission $p) {
            $p->roles()->detach();
            $p->forceDelete();
        });
    }
};
