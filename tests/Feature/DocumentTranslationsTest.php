<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\DocumentTranslations;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\BuildSitePage;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\PageLimit;
use Vortechron\FilamentCms\SiteLanguages;
use Vortechron\FilamentCms\Tests\Concerns\MakesForms;
use Vortechron\FilamentCms\Tests\TestCase;

class DocumentTranslationsTest extends TestCase
{
    use MakesForms;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filament-cms.locales' => ['ms' => 'Bahasa Melayu', 'en' => 'English'], 'filament-cms.default_locale' => 'ms']);
    }

    public function test_a_translation_is_a_linked_draft_and_does_not_use_another_page_allowance(): void
    {
        config(['filament-cms.max_pages' => 1]);
        $page = SitePage::query()->create(['title' => 'Hubungi', 'path' => '/hubungi', 'is_published' => true, 'sections' => [
            ['id' => 'sec00001', 'type' => 'rich', 'data' => ['document' => [Blocks::paragraph('Salam')]]],
        ]]);

        $english = app(DocumentTranslations::class)->create($page, 'en');

        $this->assertSame('/en/hubungi', $english->path);
        $this->assertFalse($english->is_published);
        $this->assertSame('en', $english->language());
        $this->assertSame($page->getKey(), $english->translationRootId());
        $this->assertSame($page->getKey(), $english->translation('ms')->getKey());
        $this->assertSame($english->getKey(), $page->translation('en')->getKey());
        $this->assertSame($english->getKey(), app(DocumentTranslations::class)->create($page, 'en')->getKey());
        $this->assertStringContainsString('Salam', json_encode($english->content));
        $this->assertTrue($page->fresh()->is_published);
        $this->assertSame('/hubungi', $page->fresh()->path);
        $this->assertSame(1, PageLimit::used());
    }

    public function test_published_language_pages_use_matching_parts_and_drafts_are_not_advertised(): void
    {
        $page = SitePage::query()->create(['title' => 'Utama', 'path' => '/', 'is_published' => true, 'sections' => []]);
        $english = app(DocumentTranslations::class)->create($page, 'en');
        $english->update(['title' => 'Home', 'sections' => [['id' => 'sec00001', 'type' => 'rich', 'data' => ['document' => [Blocks::paragraph('English welcome')]]]]]);
        $this->get('/en')->assertNotFound();
        $this->assertCount(1, SiteLanguages::links($page));
        $header = Document::create(['kind' => Document::HEADER, 'title' => 'Header', 'content' => ['version' => 2, 'sections' => []]]);
        $translatedHeader = app(DocumentTranslations::class)->create($header, 'en');
        app(DocumentStore::class)->save($header, [['id' => 'head0001', 'type' => 'rich', 'data' => ['document' => [Blocks::paragraph('Kepala Melayu')]]]], null);
        app(DocumentStore::class)->save($translatedHeader, [['id' => 'head0001', 'type' => 'rich', 'data' => ['document' => [Blocks::paragraph('English header')]]]], null);
        $english->update(['is_published' => true]);

        $this->get('/en')->assertOk()->assertDontSee('English header')->assertDontSee('Kepala Melayu');
        $translatedHeader->update(['is_published' => true]);

        $this->get('/en')->assertOk()->assertSee('English welcome')->assertSee('English header')->assertDontSee('Kepala Melayu');
        $this->get('/')->assertOk()->assertSee('Kepala Melayu')->assertDontSee('English header');
        $this->assertCount(2, SiteLanguages::links($page));
    }

    public function test_saving_and_restoring_english_leaves_malay_content_and_history_alone(): void
    {
        $page = SitePage::query()->create(['title' => 'Utama', 'path' => '/', 'sections' => []]);
        $store = app(DocumentStore::class);
        $store->save($page, [['id' => 'sec00001', 'type' => 'rich', 'data' => ['document' => [Blocks::paragraph('Asal Melayu')]]]], null);
        $english = app(DocumentTranslations::class)->create($page, 'en');
        $first = CmsRevision::where('key', $english->revisionKey())->firstOrFail();
        $store->save($english, [['id' => 'sec00001', 'type' => 'rich', 'data' => ['document' => [Blocks::paragraph('Changed English')]]]], null);
        $store->restore($english, $first, null);

        $this->assertStringContainsString('Asal Melayu', json_encode($page->fresh()->content));
        $this->assertCount(1, CmsRevision::where('key', $page->revisionKey())->get());
        $this->assertStringContainsString('Asal Melayu', json_encode($english->fresh()->content));
        $this->assertCount(3, CmsRevision::where('key', $english->revisionKey())->get());
    }

    public function test_the_builder_offers_the_counterpart_and_creates_a_draft_without_changing_the_page(): void
    {
        $page = SitePage::create(['title' => 'Hubungi', 'path' => '/hubungi', 'sections' => []]);
        $this->actingAsClient();
        $builder = Livewire::test(BuildSitePage::class, ['record' => $page->getKey()]);
        $options = $builder->instance()->getPageBuilderOptions();
        $this->assertCount(2, $options['languages']);
        $this->assertNull($options['languages'][1]['url']);
        $builder->call('createPageBuilderTranslation', 'en')->assertSuccessful();
        $english = $page->translation('en');
        $this->assertFalse($english->is_published);
        $englishOptions = Livewire::test(BuildSitePage::class, ['record' => $english->getKey()])->instance()->getPageBuilderOptions();
        $this->assertSame('en', $englishOptions['contentLocale']);
        $this->assertSame('/hubungi', $page->fresh()->path);
    }

    public function test_a_form_submission_uses_its_document_language_and_rejects_a_draft(): void
    {
        $page = $this->pageWithForm(self::exampleForm());
        $english = app(DocumentTranslations::class)->create($page, 'en');
        $this->send($english, self::goodInput(['nama' => '']))->assertNotFound();
        $english->update(['is_published' => true]);
        $this->send($english, self::goodInput(['nama' => '']))->assertRedirect();
        $this->assertSame('Please fill in Nama.', session('errors')->first('form.frm00001.nama'));
        $this->send($page, self::goodInput(['nama' => '']))->assertRedirect();
        $this->assertSame('Nama perlu diisi.', session('errors')->first('form.frm00001.nama'));
    }

    public function test_editing_permission_does_not_allow_creating_a_missing_language_version(): void
    {
        $page = SitePage::create(['title' => 'Utama', 'path' => '/', 'sections' => []]);
        $this->actingAsClient();
        Gate::policy(SitePage::class, UpdateOnlyLanguagePolicy::class);

        Livewire::test(BuildSitePage::class, ['record' => $page->getKey()])->call('createPageBuilderTranslation', 'en')->assertForbidden();

        $this->assertNull($page->translation('en'));
    }

    public function test_saved_sections_in_english_remain_available_when_the_builder_reloads(): void
    {
        $page = SitePage::create(['title' => 'Utama', 'path' => '/', 'sections' => []]);
        $english = app(DocumentTranslations::class)->create($page, 'en');
        $this->actingAsClient();
        $builder = Livewire::test(BuildSitePage::class, ['record' => $english->getKey()]);
        $builder->call('saveSectionFromPageBuilder', ['id' => 'sec00001', 'type' => 'rich', 'data' => ['document' => [Blocks::paragraph('English offer')]]], 'English offer', false);
        $options = Livewire::test(BuildSitePage::class, ['record' => $english->getKey()])->instance()->getPageBuilderOptions();
        $titles = array_column($options['templates'], 'label');
        $this->assertContains('English offer', $titles);
    }

    public function test_a_draft_translated_header_form_cannot_be_submitted_through_a_published_page(): void
    {
        $page = SitePage::create(['title' => 'Utama', 'path' => '/', 'is_published' => true, 'sections' => []]);
        $header = Document::create(['kind' => Document::HEADER, 'title' => 'Header', 'content' => ['version' => 2, 'sections' => []]]);
        $this->assertTrue(app(DocumentStore::class)->save($header, [self::formSection()], null, developer: true)['ok']);
        $english = app(DocumentTranslations::class)->create($page, 'en');
        $english->update(['is_published' => true]);
        $englishHeader = app(DocumentTranslations::class)->create($header, 'en');

        $this->send($english)->assertNotFound();

        $englishHeader->update(['is_published' => true]);
        $this->send($english)->assertRedirect();
    }
}
