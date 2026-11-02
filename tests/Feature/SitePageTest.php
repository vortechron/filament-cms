<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\TestCase;

class SitePageTest extends TestCase
{
    public function test_a_published_page_is_shown_at_its_address(): void
    {
        SitePage::query()->create(['title' => 'Tentang kami', 'path' => '/tentang', 'is_published' => true, 'sections' => []]);

        $this->get('/tentang')
            ->assertOk()
            ->assertSee('Tentang kami')
            ->assertSee('data-test-page="/tentang"', false);
    }

    public function test_the_home_page_lives_at_the_root(): void
    {
        SitePage::query()->create(['title' => 'Utama', 'path' => '/', 'is_published' => true, 'sections' => []]);

        $this->get('/')->assertOk()->assertSee('data-test-page="/"', false);
    }

    public function test_a_draft_page_and_an_unknown_address_are_not_found(): void
    {
        SitePage::query()->create(['title' => 'Draf', 'path' => '/draf', 'is_published' => false, 'sections' => []]);

        $this->get('/draf')->assertNotFound();
        $this->get('/tiada')->assertNotFound();
    }

    public function test_paths_are_stored_in_one_shape(): void
    {
        $page = SitePage::query()->create(['title' => 'Promosi', 'path' => 'Promosi Raya/', 'is_published' => true, 'sections' => []]);

        $this->assertSame('/promosi-raya', $page->path);
        $this->get('/promosi-raya')->assertOk();
    }
}
