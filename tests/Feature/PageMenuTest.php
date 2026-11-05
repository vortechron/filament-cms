<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Livewire\Livewire;
use Vortechron\FilamentCms\Documents\CmsKind;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\BuildSitePage;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\TestCase;

/**
 * A tap on the page title in the builder lists the site's pages, so the
 * owner opens another page's builder without going back to Pages.
 */
class PageMenuTest extends TestCase
{
    private function page(string $title, string $path, bool $published = true, int $sort = 0): SitePage
    {
        return SitePage::query()->create(['title' => $title, 'path' => $path, 'is_published' => $published, 'sort' => $sort, 'sections' => []]);
    }

    public function test_a_page_builder_lists_every_page_home_first_with_this_one_current(): void
    {
        $this->actingAsClient();
        $contact = $this->page('Hubungi', '/hubungi', sort: 2);
        $home = $this->page('Halaman utama', '/', sort: 5);
        $draft = $this->page('Draf promosi', '/promosi', published: false, sort: 1);

        $menu = Livewire::test(BuildSitePage::class, ['record' => $contact->getKey()])->instance()->getPageBuilderOptions()['pageMenu'];

        $this->assertSame(['Halaman utama', 'Draf promosi', 'Hubungi'], array_column($menu, 'title'));
        $this->assertSame([false, false, true], array_column($menu, 'current'));
        $this->assertSame(BuildSitePage::getUrl(['record' => $home->getKey()]), $menu[0]['url']);
        $this->assertSame(BuildSitePage::getUrl(['record' => $draft->getKey()]), $menu[1]['url']);
    }

    public function test_the_header_builder_has_no_page_menu(): void
    {
        $this->actingAsClient();
        $this->page('Halaman utama', '/');
        $this->page('Hubungi', '/hubungi');

        $options = Livewire::test(BuildSitePage::class, ['record' => CmsKind::Header->value])->instance()->getPageBuilderOptions();

        $this->assertArrayNotHasKey('pageMenu', $options);
    }
}
