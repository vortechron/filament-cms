<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Livewire\Livewire;
use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentBlockEditor\Content\TextTranslations;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\DocumentTranslations;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\BuildSitePage;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\SectionLibrary;
use Vortechron\FilamentCms\SiteLanguages;
use Vortechron\FilamentCms\Tests\TestCase;

class SharedSyncedSectionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['filament-cms.locales' => ['ms' => 'Malay', 'en' => 'English'], 'filament-cms.default_locale' => 'ms', 'filament-block-editor.content_locale' => 'ms']);
    }

    private function value(string $text): array
    {
        return ['version' => 2, 'sections' => [['id' => 'sec00001', 'type' => 'rich', 'data' => ['document' => [['id' => 'words001', ...Blocks::paragraph($text)]]]]]];
    }

    private function page(): SitePage
    {
        $page = new SitePage(['title' => 'Home', 'path' => '/', 'is_published' => true]);
        $value = $this->value('Promosi Melayu');
        $value['translations']['en'] = TextTranslations::extract($value, $this->value('English offer'));
        $store = app(DocumentStore::class);
        $this->assertTrue($store->save($page, $value, null, true)['ok']);
        $this->assertTrue($store->saveMetadata($page, ['translations' => ['en' => ['is_published' => true]]], null, 1)['ok']);

        return $page;
    }

    public function test_make_synced_keeps_both_published_languages_after_the_source_is_replaced_and_saved(): void
    {
        $page = $this->page();
        $this->get('/')->assertSee('Promosi Melayu');
        $this->get('/en')->assertSee('English offer');
        $this->actingAsClient();
        $builder = Livewire::test(BuildSitePage::class, ['record' => $page->id]);
        $value = app(DocumentTranslations::class)->editorValue($page);
        $saved = $builder->call('saveSectionFromPageBuilder', $value['sections'][0], 'Offer', true, $value['translations'])->effects['returns'][0];
        $this->assertTrue($saved['ok'], json_encode($saved));
        $replacement = ['version' => 2, 'sections' => [['id' => 'sec00001', 'type' => 'ref', 'ref' => $saved['id']]]];
        $builder->call('savePageBuilder', $replacement)->assertReturned(['ok' => true]);

        $this->get('/')->assertOk()->assertSee('Promosi Melayu');
        $this->get('/en')->assertOk()->assertSee('English offer')->assertDontSee('Promosi Melayu');
        $preview = $builder->call('previewPageBuilder', $replacement, null, null, 'en')->effects['returns'][0];
        $this->assertStringContainsString('English offer', $preview);
        $list = Livewire::test(BuildSitePage::class, ['record' => $page->id])->instance()->getPageBuilderOptions()['syncedSections'];
        $this->assertSame($saved['translations'], $list[0]['translations']);
    }

    public function test_missing_shared_text_falls_back_while_blank_text_and_legacy_missing_languages_stay_absent(): void
    {
        $page = $this->page();
        $library = app(SectionLibrary::class);
        $fallback = $library->save($this->value('Shared fallback')['sections'][0], 'Fallback', true, null, true);
        $blankBase = $this->value('Must stay blank');
        $blank = $library->save($blankBase['sections'][0], 'Blank', true, null, true, ['en' => TextTranslations::extract($blankBase, $this->value(''))]);
        $legacy = Document::create(['kind' => 'section', 'title' => 'Legacy', 'content' => $this->value('Legacy private')]);
        $this->assertTrue($fallback['ok']);
        $this->assertTrue($blank['ok']);
        $value = ['version' => 2, 'sections' => [
            ['id' => 'fallback1', 'type' => 'ref', 'ref' => $fallback['id']],
            ['id' => 'blank0001', 'type' => 'ref', 'ref' => $blank['id']],
            ['id' => 'legacy001', 'type' => 'ref', 'ref' => $legacy->id],
        ]];
        $this->assertTrue(app(DocumentStore::class)->save($page, $value, null, true, 2)['ok']);
        $this->get('/en')->assertOk()->assertSee('Shared fallback')->assertDontSee('Must stay blank')->assertDontSee('Legacy private');
        $this->get('/')->assertOk()->assertSee('Shared fallback')->assertSee('Must stay blank')->assertSee('Legacy private');
        $this->assertSame([], SectionLibrary::builderValue($library->synced()->find($fallback['id']))['translations']);
    }

    public function test_saving_a_template_from_the_english_builder_keeps_the_canonical_section_and_maps_on_reload(): void
    {
        $page = $this->page();
        $this->actingAsClient();
        $builder = Livewire::test(BuildSitePage::class, ['record' => $page->translation('en')->id]);
        $value = app(DocumentTranslations::class)->editorValue($page);
        $saved = $builder->call('saveSectionFromPageBuilder', $value['sections'][0], 'Bilingual copy', false, $value['translations'])->effects['returns'][0];
        $this->assertTrue($saved['ok'], json_encode($saved));
        $this->assertStringContainsString('Promosi Melayu', json_encode($saved['section']));
        $this->assertSame($saved['translations'], $saved['template']['translations']);
        $templates = SiteLanguages::run('en', fn () => app(SectionLibrary::class)->templates());
        $this->assertCount(1, $templates);
        $this->assertSame($saved['template'], $templates[0]);
        $options = Livewire::test(BuildSitePage::class, ['record' => $page->id])->instance()->getPageBuilderOptions();
        $reloaded = array_values(array_filter($options['templates'], fn ($template) => $template['key'] === $saved['template']['key']))[0];
        $copy = ['version' => 2, 'sections' => [$reloaded['section']], 'translations' => $reloaded['translations']];
        $preview = $builder->call('previewPageBuilder', $copy, null, null, 'en')->effects['returns'][0];
        $this->assertStringContainsString('English offer', $preview);
        $this->assertStringNotContainsString('Promosi Melayu', $preview);
        $builder->call('savePageBuilder', $copy)->assertReturned(['ok' => true]);
        $this->get('/en')->assertOk()->assertSee('English offer');
    }

    public function test_malformed_maps_and_refused_section_storage_leave_no_library_items_or_source_references(): void
    {
        $page = $this->page();
        $this->actingAsClient();
        $builder = Livewire::test(BuildSitePage::class, ['record' => $page->id]);
        $value = app(DocumentTranslations::class)->editorValue($page);
        $library = app(SectionLibrary::class);
        foreach ([['en' => 'not a map'], ['en' => ['bad' => 7]], ['zz' => $value['translations']['en']]] as $malformed) {
            $result = $builder->call('saveSectionFromPageBuilder', $value['sections'][0], 'Refused', true, $malformed)->effects['returns'][0];
            $this->assertFalse($result['ok'], json_encode($result));
            $this->assertCount(0, $library->synced());
            $this->assertSame([], $library->templates());
        }
        $refused = $library->save(['id' => 'bad00001', 'type' => 'ref', 'ref' => 999], 'Invalid stored section', false, null, true, $value['translations']);
        $this->assertFalse($refused['ok']);
        $this->assertSame([], $library->templates());
        $this->get('/')->assertSee('Promosi Melayu');
        $this->get('/en')->assertSee('English offer');
    }

    public function test_saving_a_library_item_does_not_refresh_the_stale_source_edit_version(): void
    {
        $page = $this->page();
        $this->actingAsClient();
        $builder = Livewire::test(BuildSitePage::class, ['record' => $page->id]);
        $value = app(DocumentTranslations::class)->editorValue($page);
        $this->assertTrue(app(DocumentStore::class)->saveMetadata($page, ['title' => 'Newer title'], null, 2)['ok']);
        $saved = $builder->call('saveSectionFromPageBuilder', $value['sections'][0], 'Saved offer', true, $value['translations'])->effects['returns'][0];
        $this->assertTrue($saved['ok']);
        $replacement = ['version' => 2, 'sections' => [['id' => 'sec00001', 'type' => 'ref', 'ref' => $saved['id']]]];
        $refused = $builder->call('savePageBuilder', $replacement)->effects['returns'][0];
        $this->assertFalse($refused['ok']);
        $this->assertArrayHasKey('expected_version', $refused['errors']);
        $this->get('/')->assertSee('Newer title')->assertSee('Promosi Melayu');
        $this->get('/en')->assertSee('English offer');
    }
}
