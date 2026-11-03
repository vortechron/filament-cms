<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Vortechron\FilamentBlockEditor\Design\Kit;
use Vortechron\FilamentBlockEditor\Design\KitSource;
use Vortechron\FilamentCms\CmsKitSource;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\TestCase;

class KitTest extends TestCase
{
    private function saveKit(array $kit): void
    {
        $document = Document::firstOfKind(Document::KIT) ?? new Document(['kind' => Document::KIT, 'title' => 'Kit']);

        $this->assertTrue(app(DocumentStore::class)->save($document, $kit, null)['ok']);
    }

    public function test_the_site_uses_the_package_defaults_until_a_kit_is_saved(): void
    {
        $this->assertInstanceOf(CmsKitSource::class, app(KitSource::class));
        $this->assertSame(Kit::defaults(), cms_kit());
    }

    public function test_the_stored_kit_is_merged_over_the_defaults(): void
    {
        $this->saveKit(['colors' => ['primary' => '#c59d6e'], 'radius' => '8px']);

        $this->assertSame('#c59d6e', cms_kit()['colors']['primary']);
        $this->assertSame('8px', cms_kit()['radius']);
        $this->assertSame(Kit::defaults()['colors']['text'], cms_kit()['colors']['text']);
    }

    public function test_a_change_saved_between_two_requests_shows_on_the_second(): void
    {
        SitePage::query()->create(['title' => 'Utama', 'path' => '/', 'is_published' => true, 'sections' => []]);
        $this->saveKit(['colors' => ['primary' => '#111111']]);
        $this->get('/')->assertSee('--kit-color-primary:#111111', false);

        $this->saveKit(['colors' => ['primary' => '#222222']]);

        $this->get('/')->assertSee('--kit-color-primary:#222222', false);
    }
}
