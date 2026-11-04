<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Filament\Actions\Testing\TestAction;
use Filament\Panel;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\BuildSitePage;
use Vortechron\FilamentCms\Filament\Resources\SiteSections\Pages\ManageSiteSections;
use Vortechron\FilamentCms\Filament\Resources\SiteSections\SiteSectionResource;
use Vortechron\FilamentCms\FilamentCmsPlugin;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\TestCase;

class SectionsScreenTest extends TestCase
{
    private function saved(string $kind, string $title): Document
    {
        return Document::query()->create(['kind' => $kind, 'title' => $title, 'content' => [
            'version' => 2,
            'sections' => [['id' => 'syn00001', 'type' => 'rich', 'data' => ['document' => [Blocks::paragraph("Isi {$title}")]]]],
        ]]);
    }

    private function pageUsing(string $title, string $path, Document $synced): SitePage
    {
        return SitePage::query()->create(['title' => $title, 'path' => $path, 'is_published' => true, 'sections' => [['id' => 'ref00001', 'type' => 'ref', 'ref' => $synced->getKey()]]]);
    }

    public function test_it_lists_synced_and_saved_sections_only(): void
    {
        $synced = $this->saved(Document::SECTION, 'Promosi');
        $template = $this->saved(Document::TEMPLATE, 'Kotak harga');
        $page = SitePage::query()->create(['title' => 'Utama', 'path' => '/', 'sections' => []]);
        $header = Document::findOrCreatePart(Document::HEADER);
        $this->actingAsClient();

        $this->get(SiteSectionResource::getUrl())->assertOk()->assertSee('Seksyen');

        Livewire::test(ManageSiteSections::class)
            ->assertCanSeeTableRecords([$synced, $template])
            ->assertCanNotSeeTableRecords([Document::query()->findOrFail($page->getKey()), $header])
            ->assertSee(['Segerak', 'Templat']);
    }

    public function test_the_menu_shows_sections_only_once_there_is_one(): void
    {
        $this->actingAsClient();

        $this->assertFalse(SiteSectionResource::shouldRegisterNavigation());
        $this->get(SiteSectionResource::getUrl())->assertOk()->assertSee('Jadikan segerak');

        $this->saved(Document::TEMPLATE, 'Kotak harga');

        $this->assertTrue(SiteSectionResource::shouldRegisterNavigation());
    }

    public function test_synced_and_saved_sections_both_have_bina(): void
    {
        $synced = $this->saved(Document::SECTION, 'Promosi');
        $template = $this->saved(Document::TEMPLATE, 'Kotak harga');
        $this->actingAsClient();

        Livewire::test(ManageSiteSections::class)
            ->assertActionVisible(TestAction::make('build')->table($synced))
            ->assertActionHasUrl(TestAction::make('build')->table($synced), BuildSitePage::getUrl(['record' => $synced]))
            ->assertActionVisible(TestAction::make('build')->table($template))
            ->assertActionHasUrl(TestAction::make('build')->table($template), BuildSitePage::getUrl(['record' => $template]));
    }

    public function test_a_saved_section_is_built_as_one_section_and_goes_back_to_this_screen(): void
    {
        $template = $this->saved(Document::TEMPLATE, 'Kotak harga');
        $this->actingAsClient();

        $this->get(BuildSitePage::getUrl(['record' => $template]))->assertOk()->assertSee('mountPageBuilder', false);
        $builder = Livewire::test(BuildSitePage::class, ['record' => $template->getKey()]);
        $options = $builder->instance()->getPageBuilderOptions();
        $this->assertSame(['section', 'Kotak harga', SiteSectionResource::getUrl()], [$options['kind'], $options['title'], $options['backUrl']]);

        $section = ['id' => 'syn00001', 'type' => 'rich', 'data' => ['document' => [Blocks::paragraph('Harga baru')]]];
        $this->assertTrue($builder->call('savePageBuilder', ['version' => 2, 'sections' => [$section]])->effects['returns'][0]['ok']);
        $this->assertStringContainsString('Harga baru', json_encode($template->fresh()->content));
        $this->assertFalse($builder->call('savePageBuilder', ['version' => 2, 'sections' => [$section, [...$section, 'id' => 'syn00002']]])->effects['returns'][0]['ok']);
    }

    public function test_a_section_can_be_renamed(): void
    {
        $synced = $this->saved(Document::SECTION, 'Promosi');
        $this->actingAsClient();

        Livewire::test(ManageSiteSections::class)
            ->callAction(TestAction::make('edit')->table($synced), ['title' => 'Promosi raya'])
            ->assertHasNoActionErrors();

        $this->assertSame('Promosi raya', $synced->fresh()->title);
        $this->assertSame(Document::SECTION, $synced->fresh()->kind);
    }

    public function test_deleting_a_synced_section_warns_with_the_pages_that_use_it(): void
    {
        $synced = $this->saved(Document::SECTION, 'Promosi');
        $this->pageUsing('Utama', '/', $synced);
        $this->pageUsing('Tentang kami', '/tentang', $synced);
        SitePage::query()->create(['title' => 'Lain', 'path' => '/lain', 'sections' => []]);
        $this->actingAsClient();

        Livewire::test(ManageSiteSections::class)
            ->mountAction(TestAction::make('delete')->table($synced))
            ->assertMountedActionModalSee(['Tentang kami, Utama'])
            ->callMountedAction();

        $this->assertNull(Document::query()->find($synced->getKey()));
        $this->get('/tentang')->assertOk()->assertDontSee('Isi Promosi');
    }

    public function test_someone_who_may_not_edit_pages_cannot_open_it(): void
    {
        Gate::policy(SitePage::class, DenyEditingPolicy::class);
        $this->actingAsClient();

        $this->get(SiteSectionResource::getUrl())->assertForbidden();
    }

    public function test_the_screen_can_be_switched_off(): void
    {
        $on = Panel::make()->id('on');
        FilamentCmsPlugin::make()->register($on);
        $off = Panel::make()->id('off');
        FilamentCmsPlugin::make()->sections(false)->register($off);

        $this->assertContains(SiteSectionResource::class, $on->getResources());
        $this->assertNotContains(SiteSectionResource::class, $off->getResources());
    }
}
