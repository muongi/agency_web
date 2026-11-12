<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Tests\TestCase;

/** Pagination must not use Laravel's Tailwind view (unsized SVG arrows filled /clients, 2026-11-11). */
class PaginationViewTest extends TestCase
{
    public function test_length_aware_links_use_app_view_without_svg(): void
    {
        $html = (new LengthAwarePaginator(range(1, 10), 146, 10, 2, ['path' => '/clients']))->links()->toHtml();

        $this->assertStringContainsString('app-pagination', $html);
        $this->assertStringNotContainsString('<svg', $html);
        $this->assertStringContainsString('11–20 / 146', $html);
        $this->assertStringContainsString('/clients?page=3', $html);
    }

    public function test_simple_links_render(): void
    {
        $html = (new Paginator(range(1, 11), 10, 1, ['path' => '/x']))->links()->toHtml();

        $this->assertStringContainsString('/x?page=2', $html);
        $this->assertStringNotContainsString('<svg', $html);
    }
}
