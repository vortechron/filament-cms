<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Livewire\Livewire;
use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentBlockEditor\Content\SectionResolver;
use Vortechron\FilamentCms\CmsSectionResolver;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\BuildSitePage;
use Vortechron\FilamentCms\FilamentCmsPlugin;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\TestCase;

class SyncedSectionsTest extends TestCase
{
    /**
     * @param  array<string, true>  $lock
     * @return array<string, mixed>
     */
    private function section(string $id, string $text, array $lock = []): array
    {
        return ['id' => $id, 'type' => 'rich', 'data' => ['document' => [Blocks::paragraph($text)]], ...($lock === [] ? [] : ['lock' => $lock])];
    }

    private function synced(string $title, string $text, string $kind = Document::SECTION): Document
    {
        return Document::query()->create(['kind' => $kind, 'title' => $title, 'content' => ['version' => 2, 'sections' => [$this->section('syn00001', $text)]]]);
    }

    private function page(array $sections = []): SitePage
    {
        return SitePage::query()->create(['title' => 'Utama', 'path' => '/', 'is_published' => true, 'sections' => $sections]);
    }

    public function test_the_resolver_finds_a_synced_section_published_or_not(): void
    {
        $synced = $this->synced('Promosi', 'Promosi raya');
        $template = $this->synced('Salinan', 'Bukan segerak', Document::TEMPLATE);

        $this->assertInstanceOf(CmsSectionResolver::class, app(SectionResolver::class));
        $this->assertFalse($synced->fresh()->is_published);
        $this->assertSame('syn00001', app(SectionResolver::class)->resolve($synced->getKey())['id']);
        $this->assertSame('syn00001', app(SectionResolver::class)->resolve((string) $synced->getKey())['id']);
        $this->assertNull(app(SectionResolver::class)->resolve($template->getKey()));
        $this->assertNull(app(SectionResolver::class)->resolve(999));
        $this->assertNull(app(SectionResolver::class)->resolve('promosi'));
    }

    public function test_a_page_draws_a_synced_section_and_draws_nothing_once_it_is_gone(): void
    {
        $synced = $this->synced('Promosi', 'Promosi raya');
        $this->page([['id' => 'ref00001', 'type' => 'ref', 'ref' => $synced->getKey()], $this->section('sec00001', 'Isi')]);

        $this->get('/')->assertOk()->assertSee('Promosi raya');

        $synced->delete();

        $this->get('/')->assertOk()->assertDontSee('Promosi raya')->assertSee('Isi');
    }

    public function test_the_builder_makes_a_synced_section(): void
    {
        $this->actingAsClient();
        $page = $this->page();

        $result = Livewire::test(BuildSitePage::class, ['record' => $page->getKey()])
            ->call('saveSectionFromPageBuilder', $this->section('sec00001', 'Segerak', ['remove' => true]), 'Promosi', true)
            ->effects['returns'][0];

        $this->assertTrue($result['ok']);
        $this->assertNull($result['template']);
        $synced = Document::query()->findOrFail($result['id']);
        $this->assertSame(Document::SECTION, $synced->kind);
        $this->assertSame('Promosi', $synced->title);
        $this->assertArrayNotHasKey('lock', $synced->content['sections'][0]);
        $this->assertSame(1, CmsRevision::query()->where('key', $synced->revisionKey())->count());
    }

    public function test_the_builder_saves_a_copy_as_a_template_for_the_add_panel(): void
    {
        $this->actingAsClient();
        $page = $this->page();

        $result = Livewire::test(BuildSitePage::class, ['record' => $page->getKey()])
            ->call('saveSectionFromPageBuilder', $this->section('sec00001', 'Templat saya'), 'Kotak harga', false)
            ->effects['returns'][0];

        $this->assertTrue($result['ok']);
        $this->assertSame(Document::TEMPLATE, Document::query()->findOrFail($result['id'])->kind);
        $this->assertSame('saved', $result['template']['category']);
        $this->assertSame('Kotak harga', $result['template']['label']);

        $templates = Livewire::test(BuildSitePage::class, ['record' => $page->getKey()])->instance()->getPageBuilderOptions()['templates'];
        $saved = array_values(array_filter($templates, fn (array $template): bool => $template['category'] === 'saved'));
        $this->assertCount(1, $saved);
        $this->assertSame('Kotak harga', $saved[0]['label']);
        $this->assertStringContainsString('Templat saya', json_encode($saved[0]['section']));
    }

    public function test_the_builder_lists_the_synced_sections_with_a_link_to_build_each(): void
    {
        $this->actingAsClient();
        $synced = $this->synced('Promosi', 'Promosi raya');
        $this->synced('Salinan', 'Bukan segerak', Document::TEMPLATE);

        $list = Livewire::test(BuildSitePage::class, ['record' => $this->page()->getKey()])->instance()->getPageBuilderOptions()['syncedSections'];

        $this->assertCount(1, $list);
        $this->assertSame($synced->getKey(), $list[0]['id']);
        $this->assertSame('Promosi', $list[0]['title']);
        $this->assertSame(BuildSitePage::getUrl(['record' => $synced]), $list[0]['editUrl']);
        $this->assertSame('syn00001', $list[0]['section']['id']);
    }

    public function test_with_sections_switched_off_the_builder_offers_none_of_it(): void
    {
        $this->actingAsClient();
        $page = $this->page();
        $this->synced('Promosi', 'Promosi raya');
        $this->synced('Salinan', 'Templat', Document::TEMPLATE);
        FilamentCmsPlugin::get()->sections(false);

        $builder = Livewire::test(BuildSitePage::class, ['record' => $page->getKey()]);
        $options = $builder->instance()->getPageBuilderOptions();

        $this->assertSame([], $options['syncedSections'] ?? []);
        $this->assertSame([], array_filter($options['templates'], fn (array $template): bool => $template['category'] === 'saved'));
        $this->assertFalse($builder->instance()->getPageBuilderSupports()['saveSection']);
        $this->assertFalse($builder->call('saveSectionFromPageBuilder', $this->section('sec00001', 'X'), 'X', true)->effects['returns'][0]['ok']);
    }
}
