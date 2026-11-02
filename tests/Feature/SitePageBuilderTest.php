<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\BuildSitePage;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\ManageSitePages;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\SitePageStore;
use Vortechron\FilamentCms\Tests\TestCase;

class SitePageBuilderTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function section(string $id, string $text): array
    {
        return ['id' => $id, 'type' => 'rich', 'data' => ['document' => [Blocks::paragraph($text)]]];
    }

    private function page(): SitePage
    {
        return SitePage::query()->create(['title' => 'Tentang', 'path' => '/tentang', 'is_published' => true, 'sections' => []]);
    }

    public function test_the_builder_opens_full_screen_for_a_page(): void
    {
        $this->actingAsClient();

        $this->get(BuildSitePage::getUrl(['record' => $this->page()]))
            ->assertOk()
            ->assertSee('mountPageBuilder', false)
            ->assertSee('page-builder.js', false);
    }

    public function test_saving_in_the_builder_puts_the_page_live_and_keeps_a_version(): void
    {
        $this->actingAsClient();
        $page = $this->page();

        Livewire::test(BuildSitePage::class, ['record' => $page->getKey()])
            ->call('savePageBuilder', ['sections' => [$this->section('sec00001', 'Kami kedai setempat')]])
            ->assertReturned(['ok' => true]);

        $this->get('/tentang')->assertSee('Kami kedai setempat');
        $this->assertSame(1, CmsRevision::query()->where('key', $page->revisionKey())->count());
    }

    public function test_the_preview_draws_the_page_in_the_project_view_with_builder_marks(): void
    {
        $this->actingAsClient();
        $page = $this->page();

        $html = Livewire::test(BuildSitePage::class, ['record' => $page->getKey()])
            ->call('previewPageBuilder', ['sections' => [$this->section('sec00002', 'Belum disimpan')]])
            ->effects['returns'][0];

        $this->assertStringContainsString('data-test-page="/tentang"', $html);
        $this->assertStringContainsString('data-block-id="sec00002"', $html);
        $this->assertStringContainsString('Belum disimpan', $html);
        $this->get('/tentang')->assertDontSee('Belum disimpan');
    }

    public function test_a_new_page_opens_in_the_builder(): void
    {
        $this->actingAsClient();

        Livewire::test(ManageSitePages::class)
            ->callAction('create', ['title' => 'Promosi', 'path' => 'promosi', 'is_published' => false])
            ->assertHasNoActionErrors()
            ->assertRedirect(BuildSitePage::getUrl(['record' => SitePage::query()->where('path', '/promosi')->firstOrFail()]));
    }

    public function test_two_pages_cannot_share_an_address(): void
    {
        $this->actingAsClient();
        $this->page();

        Livewire::test(ManageSitePages::class)
            ->callAction('create', ['title' => 'Lagi', 'path' => 'Tentang', 'is_published' => false])
            ->assertHasActionErrors(['path']);
    }

    public function test_only_a_developer_restores_an_older_version_of_a_page(): void
    {
        $page = $this->page();
        app(SitePageStore::class)->save($page, [$this->section('sec00003', 'Versi lama')], null);
        $old = CmsRevision::query()->where('key', $page->revisionKey())->latest('id')->first();
        app(SitePageStore::class)->save($page, [$this->section('sec00003', 'Versi baru')], null);

        $this->actingAsClient();
        Livewire::test(ManageSitePages::class)->assertActionHidden(TestAction::make('restore')->table($page));

        $this->actingAsDeveloper();
        Livewire::test(ManageSitePages::class)
            ->callAction(TestAction::make('restore')->table($page), ['revision' => $old->getKey()])
            ->assertHasNoActionErrors();

        $this->get('/tentang')->assertSee('Versi lama')->assertDontSee('Versi baru');
    }

    public function test_a_version_of_another_page_cannot_be_restored(): void
    {
        $page = $this->page();
        $other = SitePage::query()->create(['title' => 'Lain', 'path' => '/lain', 'is_published' => true, 'sections' => []]);
        app(SitePageStore::class)->save($other, [$this->section('sec00004', 'Halaman lain')], null);
        $foreign = CmsRevision::query()->where('key', $other->revisionKey())->firstOrFail();

        $this->actingAsDeveloper();
        Livewire::test(ManageSitePages::class)
            ->callAction(TestAction::make('restore')->table($page), ['revision' => $foreign->getKey()])
            ->assertHasActionErrors(['revision']);
    }
}
