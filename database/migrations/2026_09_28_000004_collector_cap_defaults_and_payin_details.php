<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Collector collection limit (TDR): business-unit default limit, manual release trace on the
 * collector balance, bank details of a deposit recorded in the back office, and the permission
 * to set limits / release a TPE (given to every existing role, same rule as 2026_09_26_000000).
 */
return new class extends Migration
{
    protected array $slugs = ['manage-sunu-collector-caps'];

    public function up(): void
    {
        Schema::table('business_units', function (Blueprint $table) {
            $table->decimal('sunu_collector_cap_amount', 15, 2)->nullable();
            $table->unsignedTinyInteger('sunu_collector_cap_warning_pct')->default(80);
        });

        Schema::table('collector_balances', function (Blueprint $table) {
            $table->unsignedBigInteger('released_by')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->text('release_note')->nullable();

            $table->foreign('released_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('sunu_pay_ins', function (Blueprint $table) {
            $table->string('source', 8)->default('tpe');
            $table->timestamp('deposited_at')->nullable();
            $table->string('bank_name', 128)->nullable();
            $table->string('bank_account', 64)->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();

            $table->foreign('recorded_by')->references('id')->on('users')->onDelete('set null');
        });

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

        Schema::table('sunu_pay_ins', function (Blueprint $table) {
            $table->dropForeign(['recorded_by']);
            $table->dropColumn(['source', 'deposited_at', 'bank_name', 'bank_account', 'recorded_by']);
        });

        Schema::table('collector_balances', function (Blueprint $table) {
            $table->dropForeign(['released_by']);
            $table->dropColumn(['released_by', 'released_at', 'release_note']);
        });

        Schema::table('business_units', function (Blueprint $table) {
            $table->dropColumn(['sunu_collector_cap_amount', 'sunu_collector_cap_warning_pct']);
        });
    }
};
