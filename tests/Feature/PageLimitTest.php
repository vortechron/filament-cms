<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Filament\Actions\CreateAction;
use Livewire\Livewire;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\ManageSitePages;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\PageLimit;
use Vortechron\FilamentCms\Tests\TestCase;

class PageLimitTest extends TestCase
{
    private function pages(int $count): void
    {
        foreach (range(1, $count) as $number) {
            SitePage::query()->create(['title' => "Halaman {$number}", 'path' => $number === 1 ? '/' : "/halaman-{$number}", 'sections' => []]);
        }
    }

    public function test_the_limit_is_a_whole_number_from_the_config_and_anything_else_means_no_limit(): void
    {
        foreach ([5 => 5, '5' => 5, ' 7 ' => 7] as $setting => $max) {
            config(['filament-cms.max_pages' => $setting]);
            $this->assertSame($max, PageLimit::max());
        }

        foreach ([null, '', '0', 0, '-1', 'lima', '2.5'] as $setting) {
            config(['filament-cms.max_pages' => $setting]);
            $this->assertNull(PageLimit::max(), var_export($setting, true));
        }
    }

    public function test_every_page_counts_published_or_not_but_the_header_and_footer_do_not(): void
    {
        config(['filament-cms.max_pages' => 2]);
        SitePage::query()->create(['title' => 'Utama', 'path' => '/', 'is_published' => true, 'sections' => []]);
        Document::findOrCreatePart(Document::HEADER);

        $this->assertSame(1, PageLimit::used());
        $this->assertFalse(PageLimit::reached());

        SitePage::query()->create(['title' => 'Draf', 'path' => '/draf', 'is_published' => false, 'sections' => []]);

        $this->assertSame(2, PageLimit::used());
        $this->assertTrue(PageLimit::reached());
    }

    public function test_under_the_limit_the_client_sees_how_many_pages_are_left_and_can_add_one(): void
    {
        config(['filament-cms.max_pages' => 5]);
        $this->pages(4);
        $this->actingAsClient();

        Livewire::test(ManageSitePages::class)
            ->assertSee('4 daripada 5 halaman digunakan.')
            ->assertActionEnabled(CreateAction::class)
            ->callAction(CreateAction::class, ['title' => 'Promosi', 'path' => 'promosi'])
            ->assertHasNoActionErrors();

        $this->assertSame(5, SitePage::query()->count());
    }

    public function test_at_the_limit_the_new_page_button_is_off_and_says_why(): void
    {
        config(['filament-cms.max_pages' => 5]);
        $this->pages(5);
        $this->actingAsClient();

        Livewire::test(ManageSitePages::class)
            ->assertSee('Semua 5 halaman dalam pakej anda sudah digunakan.')
            ->assertActionDisabled(CreateAction::class)
            ->callAction(CreateAction::class, ['title' => 'Lebih', 'path' => 'lebih']);

        $this->assertSame(5, SitePage::query()->count());
        $this->assertNull(SitePage::query()->where('path', '/lebih')->first());
    }

    public function test_with_no_limit_nothing_is_counted_on_screen(): void
    {
        config(['filament-cms.max_pages' => null]);
        $this->pages(9);
        $this->actingAsClient();

        Livewire::test(ManageSitePages::class)
            ->assertDontSee('halaman digunakan')
            ->assertActionEnabled(CreateAction::class);
    }
}
