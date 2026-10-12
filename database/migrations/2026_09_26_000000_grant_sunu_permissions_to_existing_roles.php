<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * SUNU menus get their own permissions (role page groups "SUNU …", "Configuration",
 * "Reports"). The old banking Configuration / Reports permissions move to
 * "Banking Configuration" / "Banking Reports" (hidden while core banking is off).
 * Every existing role receives all SUNU permissions so nobody loses a menu on deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sunu = PermissionsSeeder::sunuPermissions();
        $sunuSlugs = array_column($sunu, 'slug');

        Permission::where('group', 'Configuration')->whereNotIn('slug', $sunuSlugs)
            ->update(['group' => 'Banking Configuration']);
        Permission::where('group', 'Reports')->whereNotIn('slug', $sunuSlugs)
            ->update(['group' => 'Banking Reports']);

        foreach ($sunu as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        $ids = Permission::whereIn('slug', $sunuSlugs)->pluck('id')->all();
        Role::query()->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids));
    }

    public function down(): void
    {
        $sunuSlugs = array_column(PermissionsSeeder::sunuPermissions(), 'slug');
        Permission::whereIn('slug', $sunuSlugs)->get()->each(function (Permission $p) {
            $p->roles()->detach();
            $p->forceDelete();
        });
        Permission::where('group', 'Banking Configuration')->update(['group' => 'Configuration']);
        Permission::where('group', 'Banking Reports')->update(['group' => 'Reports']);
    }
};
