<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\Cms;
use Vortechron\FilamentCms\CmsSettingsTranslations;
use Vortechron\FilamentCms\ContentStore;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\DocumentTranslations;
use Vortechron\FilamentCms\Entries\EntryStore;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\EntryTranslations;
use Vortechron\FilamentCms\Filament\Pages\EditSettings;
use Vortechron\FilamentCms\Filament\Resources\Entries\Pages\ManageEntries;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\BuildSitePage;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\ManageSitePages;
use Vortechron\FilamentCms\Filament\Resources\SiteSections\Pages\ManageSiteSections;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\Entry;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\SiteLanguages;
use Vortechron\FilamentCms\Tests\TestCase;

class FieldLanguageFormsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['filament-cms.locales' => ['ms' => 'Bahasa Melayu', 'en' => 'English'], 'filament-cms.default_locale' => 'ms']);
    }

    public function test_entry_text_has_local_language_controls_and_saves_both_languages_without_another_entry(): void
    {
        app(EntryTypes::class)->register(EntryType::make('item')->fields([Field::number('price'), Field::textarea('description'), Field::image('photo')]));
        $entry = new Entry(['entry_type' => 'item']);
        app(EntryStore::class)->save($entry, ['title' => 'Pensel', 'fields' => ['price' => 2, 'description' => 'Pensel kayu']], null);
        $this->actingAsClient();

        $screen = Livewire::test(ManageEntries::class, ['type' => 'item'])
            ->mountAction(TestAction::make('edit')->table($entry));
        $html = $screen->instance()->getSchema('mountedActionSchema0')->toHtml();
        $this->assertStringContainsString('data-cms-field-language="title"', $html);
        $dom = new \DOMDocument;
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $languageGroups = (new \DOMXPath($dom))->query('//*[@data-cms-field-language="title"]//*[@data-cms-locale]');
        $this->assertCount(2, $languageGroups);
        $this->assertSame("cmsFieldLocale === 'ms'", $languageGroups->item(0)->getAttribute('x-show'));
        $this->assertSame("cmsFieldLocale === 'en'", $languageGroups->item(1)->getAttribute('x-show'));
        $this->assertStringContainsString('data-cms-field-language="fields.description"', $html);
        $this->assertStringNotContainsString('data-cms-field-language="fields.price"', $html);
        $this->assertStringNotContainsString('data-cms-field-language="fields.photo"', $html);
        $screen->fillForm(['title' => 'Pensel baru', 'translations' => ['en' => ['title' => 'New pencil', 'fields' => ['description' => 'Wooden pencil']]]])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $saved = $entry->fresh();
        $this->assertSame('Pensel baru', $saved->title);
        $this->assertSame('New pencil', $saved->translatedTitle('en'));
        $this->assertSame('Wooden pencil', $saved->translatedFieldValues('en')['description']);
        $this->assertSame(1, Entry::query()->count());
    }

    public function test_entry_checks_hidden_language_lengths_before_saving_any_words(): void
    {
        app(EntryTypes::class)->register(EntryType::make('item')->fields([Field::text('description')]));
        $this->actingAsClient();
        Livewire::test(ManageEntries::class, ['type' => 'item'])
            ->callAction('create', ['title' => 'Pensel', 'translations' => ['en' => ['title' => str_repeat('a', 121), 'fields' => ['description' => str_repeat('a', 501)]]]])
            ->assertHasActionErrors(['translations.en.title' => 'max', 'translations.en.fields.description' => 'max']);
        $this->assertSame(0, Entry::query()->count());
    }

    public function test_settings_edit_translated_words_beside_the_original_field_with_the_same_limit(): void
    {
        config(['filament-cms.translatable_settings' => ['hours' => ['note']]]);
        $this->actingAsClient();
        $screen = Livewire::test(EditSettings::class)
            ->assertSeeHtml('data-cms-field-language="hours.note"')
            ->assertActionDoesNotExist('translate_en')
            ->fillForm(['business' => ['name' => 'Kedai Pensel'], 'hours' => ['note' => 'Tutup Ahad'], 'translations' => ['en' => ['hours' => ['note' => str_repeat('a', 161)]]]])
            ->call('save')->assertHasFormErrors(['translations.en.hours.note' => 'max']);
        $screen->fillForm(['translations' => ['en' => ['hours' => ['note' => 'Closed Sunday']]]])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('Closed Sunday', CmsSettingsTranslations::saved('en')['hours']['note']);
        $this->assertSame('Tutup Ahad', SiteLanguages::run('ms', fn () => app(Cms::class)->saved('settings'))['hours']['note']);
    }

    public function test_pages_list_contains_five_roots_and_no_english_rows(): void
    {
        config(['filament-cms.max_pages' => 5]);
        $roots = [];
        $translated = [];
        foreach (range(1, 5) as $number) {
            $root = SitePage::create(['title' => "Halaman {$number}", 'path' => "/halaman-{$number}", 'sections' => []]);
            $roots[] = $root;
            $translated[] = app(DocumentTranslations::class)->create($root, 'en');
        }
        $this->actingAsClient();
        Livewire::test(ManageSitePages::class)
            ->assertCanSeeTableRecords($roots)
            ->assertCanNotSeeTableRecords($translated)
            ->assertCountTableRecords(5)
            ->assertActionDisabled('create');
    }

    public function test_page_details_edit_titles_seo_and_english_publication_inside_one_root(): void
    {
        $page = SitePage::create(['title' => 'Hubungi', 'path' => '/hubungi', 'sections' => []]);
        $this->assertTrue(app(DocumentTranslations::class)->convert($page)['ok']);
        $this->actingAsClient();
        $screen = Livewire::test(ManageSitePages::class)
            ->mountAction(TestAction::make('edit')->table($page));
        $html = $screen->instance()->getSchema('mountedActionSchema0')->toHtml();
        $this->assertStringContainsString('data-cms-field-language="title"', $html);
        $this->assertStringContainsString('data-cms-field-language="seo_description"', $html);
        $screen->fillForm(['title' => 'Hubungi kami', 'translations' => ['en' => ['title' => 'Contact us', 'path' => '/en/contact', 'is_published' => true, 'seo_description' => 'Talk to our team']]])
            ->callMountedAction()->assertHasNoActionErrors();
        $english = $page->translation('en');
        $this->assertSame('Hubungi kami', $page->fresh()->title);
        $this->assertSame('Contact us', $english->title);
        $this->assertTrue($english->is_published);
        $this->assertFalse($page->fresh()->is_published);
        $this->assertSame('Talk to our team', $english->seo_description);
    }

    public function test_settings_an_old_form_keeps_newer_facts_and_refuses_changed_translation(): void
    {
        config(['filament-cms.translatable_settings' => ['hours' => ['note']]]);
        app(ContentStore::class)->save('settings', ['business' => ['name' => 'Kedai Pensel'], 'contact' => ['phones' => ['0123456789']]], null);
        app(CmsSettingsTranslations::class)->save('en', ['hours' => ['note' => 'Old note']]);
        $this->actingAsClient();
        $screen = Livewire::test(EditSettings::class);
        app(ContentStore::class)->save('settings', ['business' => ['name' => 'Kedai Pensel'], 'contact' => ['phones' => ['0199999999']]], null);
        $screen->fillForm(['translations' => ['en' => ['hours' => ['note' => 'English note']]]])->call('save')->assertHasNoFormErrors();
        $this->assertSame(['0199999999'], SiteLanguages::run('ms', fn () => app(Cms::class)->saved('settings'))['contact']['phones']);
        app(CmsSettingsTranslations::class)->save('en', ['hours' => ['note' => 'Newer note']]);
        $screen->fillForm(['business' => ['name' => 'Old edit'], 'translations' => ['en' => ['hours' => ['note' => 'Stale edit']]]])->call('save')->assertHasErrors();
        $this->assertSame('Kedai Pensel', SiteLanguages::run('ms', fn () => app(Cms::class)->saved('settings'))['business']['name']);
    }

    public function test_entry_clearing_english_words_keeps_an_intentional_blank(): void
    {
        app(EntryTypes::class)->register(EntryType::make('item')->fields([Field::textarea('description')]));
        $entry = new Entry(['entry_type' => 'item']);
        app(EntryStore::class)->save($entry, ['title' => 'Pensel', 'translations' => ['en' => ['title' => 'Pencil', 'fields' => ['description' => 'Wooden pencil']]]], null);
        $this->actingAsClient();
        Livewire::test(ManageEntries::class, ['type' => 'item'])
            ->mountAction(TestAction::make('edit')->table($entry))
            ->fillForm(['translations' => ['en' => ['title' => '', 'fields' => ['description' => '']]]])
            ->callMountedAction()->assertHasNoActionErrors();
        $this->assertSame('', $entry->fresh()->translatedTitle('en'));
        $this->assertSame('', $entry->fresh()->translatedFieldValues('en')['description']);
    }

    public function test_old_language_bookmark_opens_one_shared_editor_with_root_menu_and_rejects_stale_save(): void
    {
        $page = SitePage::create(['title' => 'Hubungi', 'path' => '/hubungi', 'sections' => []]);
        $english = app(DocumentTranslations::class)->create($page, 'en');
        $this->assertTrue(app(DocumentTranslations::class)->convert($page)['ok']);
        SitePage::create(['title' => 'Utama', 'path' => '/', 'sections' => []]);
        $this->actingAsClient();
        $screen = Livewire::test(BuildSitePage::class, ['record' => $english->getKey()]);
        $options = $screen->instance()->getPageBuilderOptions();
        $this->assertSame($page->getKey(), $screen->instance()->record->getKey());
        $this->assertSame('en', $options['contentLocale']);
        $this->assertSame('ms', $options['fieldLanguages']['defaultLocale']);
        $this->assertArrayHasKey('translations', $options['value']);
        $this->assertCount(2, $options['pageMenu']);
        $value = $options['value'];
        $newer = $page->fresh();
        $saved = app(DocumentStore::class)->saveMetadata($newer, ['title' => 'Tajuk baru'], null, expectedVersion: (int) $newer->edit_version);
        $this->assertTrue($saved['ok']);
        $screen->call('savePageBuilder', $value);
        $this->assertFalse($screen->effects['returns'][0]['ok']);
        $this->assertArrayHasKey('expected_version', $screen->effects['returns'][0]['errors']);
        $this->assertSame('Tajuk baru', $page->fresh()->title);
    }

    public function test_entry_stale_translation_baseline_is_kept_on_the_server(): void
    {
        app(EntryTypes::class)->register(EntryType::make('item')->fields([]));
        $entry = new Entry(['entry_type' => 'item']);
        app(EntryStore::class)->save($entry, ['title' => 'Pensel', 'translations' => ['en' => ['title' => 'Pencil']]], null);
        $this->actingAsClient();
        $screen = Livewire::test(ManageEntries::class, ['type' => 'item'])->mountAction(TestAction::make('edit')->table($entry));
        app(EntryTranslations::class)->save($entry, 'en', ['title' => 'New pencil']);
        $screen->fillForm(['translations' => ['en' => ['title' => 'Stale edit']], 'translation_baseline' => json_encode(['en' => ['title' => 'New pencil']])])
            ->callMountedAction();
        $this->assertSame('New pencil', $entry->fresh()->translatedTitle('en'));
    }

    public function test_shared_builder_saves_both_note_languages_and_a_second_save_keeps_a_new_phone(): void
    {
        config(['filament-cms.translatable_settings' => ['hours' => ['note']]]);
        app(ContentStore::class)->save('settings', ['hours' => ['note' => 'Nota asal'], 'contact' => ['phones' => ['0123456789']]], null);
        $page = SitePage::create(['title' => 'Utama', 'path' => '/', 'sections' => []]);
        app(DocumentTranslations::class)->convert($page);
        $this->actingAsClient();
        $builder = Livewire::test(BuildSitePage::class, ['record' => $page->getKey()]);
        $info = $builder->instance()->getPageBuilderOptions()['siteInfo'];
        $values = $info['values'];
        $values['hours']['note'] = 'Nota Melayu';
        $translations = ['en' => ['hours' => ['note' => 'English note']]];
        $builder->call('saveSiteInfoFromPageBuilder', $values, $translations)->assertSuccessful();
        $this->assertTrue($builder->effects['returns'][0]['ok']);
        $saved = SiteLanguages::run('ms', fn () => app(Cms::class)->saved('settings'));
        $this->assertSame('Nota Melayu', $saved['hours']['note']);
        $this->assertSame('English note', CmsSettingsTranslations::saved('en')['hours']['note']);
        $saved['contact']['phones'] = ['0199999999'];
        app(ContentStore::class)->save('settings', $saved, null);
        $translations['en']['hours']['note'] = 'English note again';
        $builder->call('saveSiteInfoFromPageBuilder', $values, $translations)->assertSuccessful();
        $this->assertTrue($builder->effects['returns'][0]['ok']);
        $this->assertSame(['0199999999'], SiteLanguages::run('ms', fn () => app(Cms::class)->saved('settings'))['contact']['phones']);
        $this->assertSame('English note again', CmsSettingsTranslations::saved('en')['hours']['note']);
    }

    public function test_page_metadata_open_before_a_builder_save_cannot_replace_newer_words(): void
    {
        $page = SitePage::create(['title' => 'Hubungi', 'path' => '/hubungi', 'sections' => []]);
        app(DocumentTranslations::class)->convert($page);
        $this->actingAsClient();
        $screen = Livewire::test(ManageSitePages::class)->mountAction(TestAction::make('edit')->table($page));
        $current = $page->fresh();
        app(DocumentStore::class)->saveMetadata($current, ['title' => 'Terkini'], null, expectedVersion: (int) $current->edit_version);
        $screen->fillForm(['title' => 'Lama'])->callMountedAction()->assertHasActionErrors(['expected_version']);
        $this->assertSame('Terkini', $page->fresh()->title);
    }

    public function test_a_stale_saved_section_rename_uses_the_shared_version_check(): void
    {
        $section = Document::create(['kind' => 'template', 'title' => 'Bahagian', 'content' => ['version' => 2, 'sections' => []]]);
        app(DocumentTranslations::class)->convert($section);
        $this->actingAsClient();
        $screen = Livewire::test(ManageSiteSections::class)->mountAction(TestAction::make('edit')->table($section));
        $current = $section->fresh();
        app(DocumentStore::class)->saveMetadata($current, ['title' => 'Nama terkini'], null, expectedVersion: (int) $current->edit_version);
        $screen->fillForm(['title' => 'Nama lama'])->callMountedAction();
        $this->assertSame('Nama terkini', $section->fresh()->title);
    }

    public function test_publication_toggle_after_an_own_metadata_save_uses_the_advanced_version(): void
    {
        $page = SitePage::create(['title' => 'Hubungi', 'path' => '/hubungi', 'sections' => []]);
        app(DocumentTranslations::class)->convert($page);
        $this->actingAsClient();
        Livewire::test(ManageSitePages::class)
            ->mountAction(TestAction::make('edit')->table($page))
            ->fillForm(['title' => 'Hubungi kami'])->callMountedAction()->assertHasNoActionErrors()
            ->call('updateTableColumnState', 'is_published', (string) $page->getKey(), true)->assertHasNoErrors();
        $this->assertTrue($page->fresh()->is_published);
    }
}
