<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every Blade view must compile to valid PHP (a stray @endif in
 * superadmin/business-units/create broke that page, 2026-11-12).
 */
class BladeViewsCompileTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_blade_view_compiles_to_valid_php(): void
    {
        $bad = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $php = app('blade.compiler')->compileString(file_get_contents($file->getPathname()));
            $tmp = tempnam(sys_get_temp_dir(), 'blade');
            file_put_contents($tmp, $php);
            exec('php -l '.escapeshellarg($tmp).' 2>&1', $out, $rc);
            unlink($tmp);
            if ($rc !== 0) {
                $bad[] = str_replace(resource_path('views').'/', '', $file->getPathname());
            }
        }
        $this->assertSame([], $bad, 'Blade views with PHP syntax errors');
    }

    public function test_superadmin_business_unit_create_page_opens(): void
    {
        config(['sunu.single_business' => false]); // SUNU-only mode redirects this page (SunuSingleBusinessTest)
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->get(route('superadmin.business-units.create'))->assertOk();
    }
}
