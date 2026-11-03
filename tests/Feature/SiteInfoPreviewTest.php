<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Vortechron\FilamentCms\Cms;
use Vortechron\FilamentCms\ContentStore;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\SiteInfo;
use Vortechron\FilamentCms\Tests\TestCase;

class SiteInfoPreviewTest extends TestCase
{
    private const STORED = [
        'business' => ['name' => 'Kedai Adam', 'logo' => 'cms/logo.png', 'extra' => 'kekal'],
        'contact' => ['phones' => ['012-345 6789'], 'email' => 'kedai@example.com'],
        'seo' => ['title' => 'Tajuk SEO'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        app(ContentStore::class)->save('settings', self::STORED, null);
    }

    public function test_the_unsaved_shop_info_is_cleaned_over_the_stored_settings_and_nothing_is_saved(): void
    {
        $settings = app(SiteInfo::class)->previewSettings([
            'contact' => ['phones' => [['number' => ' 019-999 0000 ']], 'address' => 'No. 2, Jalan Dua'],
            'hours' => ['note' => 'Tutup Ahad'],
            'seo' => ['title' => 'Bukan dari builder'],
        ]);

        $this->assertSame(['019-999 0000'], $settings['contact']['phones']);
        $this->assertSame('No. 2, Jalan Dua', $settings['contact']['address']);
        $this->assertSame('kedai@example.com', $settings['contact']['email']);
        $this->assertSame('Tutup Ahad', $settings['hours']['note']);
        $this->assertSame(self::STORED['business'], $settings['business']);
        $this->assertSame('Tajuk SEO', $settings['seo']['title']);
        $this->assertSame(self::STORED, app(Cms::class)->saved(Cms::SETTINGS));
        $this->assertSame(1, CmsRevision::query()->where('key', 'settings')->count());
    }

    public function test_a_value_that_fails_the_checks_shows_the_stored_one_and_the_rest_still_shows(): void
    {
        $settings = app(SiteInfo::class)->previewSettings([
            'business' => ['name' => '', 'tagline' => 'Murah dan cepat', 'logo' => 'https://evil.example/logo.png'],
            'contact' => ['phones' => [['number' => '019-999 0000']], 'email' => 'bukan emel', 'map_url' => 'javascript:alert(1)'],
        ]);

        $this->assertSame('Kedai Adam', $settings['business']['name']);
        $this->assertSame('cms/logo.png', $settings['business']['logo']);
        $this->assertSame('Murah dan cepat', $settings['business']['tagline']);
        $this->assertSame('kedai@example.com', $settings['contact']['email']);
        $this->assertNull($settings['contact']['map_url']);
        $this->assertSame(['019-999 0000'], $settings['contact']['phones']);
    }
}
