<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Livewire\Livewire;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\BuildSitePage;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\ManageSitePages;
use Vortechron\FilamentCms\Filament\Resources\SitePages\SitePageResource;
use Vortechron\FilamentCms\FilamentCmsPlugin;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Tests\TestCase;

class PartsAreaTest extends TestCase
{
    public function test_the_pages_screen_has_a_header_and_footer_area_with_bina(): void
    {
        $this->actingAsClient();

        $this->get(SitePageResource::getUrl())
            ->assertOk()
            ->assertSeeInOrder(['Header &amp; footer', 'Header', e(BuildSitePage::getUrl(['record' => 'header'])), 'Footer', e(BuildSitePage::getUrl(['record' => 'footer']))], false);

        $this->assertSame(0, Document::query()->whereIn('kind', [Document::HEADER, Document::FOOTER])->count());
    }

    public function test_the_area_is_hidden_when_parts_are_switched_off(): void
    {
        $this->actingAsClient();
        FilamentCmsPlugin::get()->parts(false);

        Livewire::test(ManageSitePages::class)
            ->assertOk()
            ->assertDontSee(BuildSitePage::getUrl(['record' => 'header']));
    }
}
