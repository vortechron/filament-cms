<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentBlockEditor\Blocks\BlockRegistry;
use Vortechron\FilamentBlockEditor\Blocks\CoreBlocks;
use Vortechron\FilamentBlockEditor\Content\TextTranslations;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\DocumentTranslations;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\TestCase;

class SharedDocumentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['filament-cms.locales' => ['ms' => 'Bahasa Melayu', 'en' => 'English'], 'filament-cms.default_locale' => 'ms']);
    }

    private function value(string $text): array
    {
        return ['version' => 2, 'sections' => [['id' => 'sec00001', 'type' => 'rich', 'data' => ['document' => [
            ['id' => 'paragraph1', ...Blocks::paragraph($text)],
        ]]]]];
    }

    public function test_conversion_keeps_existing_language_addresses_and_rendered_words(): void
    {
        $root = SitePage::create(['id' => 7, 'title' => 'Hubungi', 'path' => '/hubungi', 'is_published' => true, 'content' => $this->value('Hubungi kami')]);
        $english = SitePage::create(['id' => 39, 'title' => 'Contact', 'path' => '/en/contact', 'locale' => 'en', 'translation_of' => 7, 'is_published' => true, 'content' => $this->value('Contact us')]);

        $result = app(DocumentTranslations::class)->convert($root);

        $this->assertTrue($result['ok'], json_encode($result));
        $this->get('/hubungi')->assertOk()->assertSee('Hubungi kami')->assertDontSee('Contact us');
        $this->get('/en/contact')->assertOk()->assertSee('Contact us')->assertDontSee('Hubungi kami');
        $this->assertSame(39, $root->translation('en')->getKey());
        $this->assertSame([], $english->fresh()->content);
        $this->assertSame($result, ['ok' => true, 'converted' => true]);
        $this->assertSame(['ok' => true, 'converted' => false], app(DocumentTranslations::class)->convert($english));
    }

    public function test_check_is_read_only_and_conflicting_layouts_are_refused_without_conversion(): void
    {
        $root = SitePage::create(['title' => 'Utama', 'path' => '/', 'content' => $this->value('Salam')]);
        $english = SitePage::create(['title' => 'Home', 'path' => '/en', 'locale' => 'en', 'translation_of' => $root->id, 'content' => $this->value('Welcome')]);
        $translations = app(DocumentTranslations::class);
        $this->assertTrue($translations->checkConversion($root)['ok']);
        $this->assertNull($root->fresh()->shared_format);
        $this->assertStringContainsString('Welcome', json_encode($english->fresh()->content));
        $changed = $english->content;
        $changed['sections'][0]['data']['padding'] = 'xl';
        $english->update(['content' => $changed]);
        $this->assertFalse($translations->convert($root)['ok']);
        $this->assertNull($root->fresh()->shared_format);
        $this->assertSame('xl', $english->fresh()->content['sections'][0]['data']['padding']);
    }

    public function test_stale_save_errors_use_the_panel_language_and_owner_words(): void
    {
        $root = SitePage::create(['title' => 'Utama', 'path' => '/', 'content' => $this->value('Salam')]);
        app(DocumentTranslations::class)->convert($root);

        foreach ([
            'en' => 'This page changed since you opened it. Reload it before saving.',
            'ms' => 'Halaman ini telah berubah sejak anda membukanya. Muat semula sebelum menyimpan.',
        ] as $locale => $message) {
            app()->setLocale($locale);
            $result = app(DocumentStore::class)->save($root, $this->value('Stale'), null, true);
            $this->assertFalse($result['ok']);
            $this->assertSame($message, $result['errors']['expected_version']);
        }
    }

    public function test_shared_layout_save_keeps_english_words_and_refuses_old_or_unversioned_writers(): void
    {
        $root = SitePage::create(['title' => 'Utama', 'path' => '/', 'is_published' => true, 'content' => $this->value('Salam')]);
        $english = SitePage::create(['title' => 'Home', 'path' => '/en', 'locale' => 'en', 'translation_of' => $root->id, 'is_published' => true, 'content' => $this->value('Welcome')]);
        $translations = app(DocumentTranslations::class);
        $translations->convert($root);
        $value = $translations->editorValue($root);
        $value['sections'][0]['data']['padding'] = 'xl';
        $store = app(DocumentStore::class);
        $this->assertFalse($store->save($root, $value, null, true)['ok']);
        $result = $store->save($root, $value, null, true, expectedVersion: 1);
        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertSame(2, $result['edit_version']);
        $this->assertSame('xl', $translations->resolvedValue($english->fresh())['sections'][0]['data']['padding']);
        $this->get('/en')->assertSee('Welcome')->assertDontSee('Salam');
        $this->assertFalse($store->save($root, $this->value('Stale'), null, true, expectedVersion: 1)['ok']);
        $this->get('/')->assertSee('Salam')->assertDontSee('Stale');
    }

    public function test_clearing_malay_rich_words_keeps_published_english_words_after_a_shared_save(): void
    {
        $root = SitePage::create(['title' => 'Utama', 'path' => '/', 'is_published' => true, 'content' => $this->value('Selamat')]);
        SitePage::create(['title' => 'Home', 'path' => '/en', 'locale' => 'en', 'translation_of' => $root->id, 'is_published' => true, 'content' => $this->value('Welcome')]);
        $translations = app(DocumentTranslations::class);
        $this->assertTrue($translations->convert($root)['ok']);
        $incoming = $translations->editorValue($root);
        $incoming['sections'][0]['data']['document'][0]['content'] = [];

        $result = app(DocumentStore::class)->save($root, $incoming, null, true, expectedVersion: 1);

        $this->assertTrue($result['ok'], json_encode($result));
        $this->get('/')->assertOk()->assertDontSee('Selamat');
        $this->get('/en')->assertOk()->assertSee('Welcome')->assertDontSee('Selamat');
    }

    public function test_rich_formatting_and_inline_links_stay_independent_when_malay_text_runs_merge(): void
    {
        $base = $this->value('');
        $base['sections'][0]['data']['document'][0]['content'] = [
            ['type' => 'text', 'text' => 'Buka ', 'styles' => ['bold' => true]],
            ['type' => 'text', 'text' => 'hari ini', 'styles' => []],
        ];
        $translated = $base;
        $translated['sections'][0]['data']['document'][0]['content'] = [
            ['type' => 'link', 'href' => '/english-help', 'content' => [
                ['type' => 'text', 'text' => 'Open today', 'styles' => ['italic' => true]],
            ]],
        ];
        $root = SitePage::create(['title' => 'Utama', 'path' => '/', 'is_published' => true, 'content' => $base]);
        $english = SitePage::create(['title' => 'Home', 'path' => '/en', 'locale' => 'en', 'translation_of' => $root->id, 'is_published' => true, 'content' => $translated]);
        $translations = app(DocumentTranslations::class);
        $this->assertTrue($translations->convert($root)['ok']);
        $this->get('/')->assertSee('<strong>Buka </strong>', false);
        $this->get('/en')->assertSee('href="/english-help"', false)->assertSee('<em>Open today</em>', false);
        $incoming = $translations->editorValue($root);
        $incoming['sections'][0]['data']['document'][0]['content'] = [
            ['type' => 'text', 'text' => 'Buka hari ini', 'styles' => []],
        ];

        $result = app(DocumentStore::class)->save($root, $incoming, null, true, expectedVersion: 1);

        $this->assertTrue($result['ok'], json_encode($result));
        $this->get('/')->assertSee('Buka hari ini')->assertDontSee('<strong>', false);
        $this->get('/en')->assertSee('href="/english-help"', false)->assertSee('<em>Open today</em>', false);
        $englishValue = $translations->resolvedValue($english->fresh());
        $englishValue['sections'][0]['data']['document'][0]['content'][0]['href'] = '/english-support';
        $this->assertTrue(app(DocumentStore::class)->save($english, $englishValue, null, true, expectedVersion: 2)['ok']);
        $this->get('/en')->assertSee('href="/english-support"', false)->assertSee('<em>Open today</em>', false);
        $this->get('/')->assertSee('Buka hari ini')->assertDontSee('english-support');
    }

    public function test_metadata_and_translated_text_saves_share_the_root_version_without_replacing_other_words(): void
    {
        $root = SitePage::create(['title' => 'Utama', 'path' => '/', 'content' => $this->value('Salam')]);
        $english = SitePage::create(['title' => 'Home', 'path' => '/en', 'locale' => 'en', 'translation_of' => $root->id, 'content' => $this->value('Welcome')]);
        $translations = app(DocumentTranslations::class);
        $translations->convert($root);
        $store = app(DocumentStore::class);
        $this->assertTrue($store->saveMetadata($english, ['title' => 'English home', 'is_published' => true], null, 1)['ok']);
        $this->assertFalse($store->saveMetadata($english, ['title' => 'Stale'], null, 1)['ok']);
        $this->assertSame('English home', $english->fresh()->title);
        $this->assertTrue($store->save($english, $this->value('Hello'), null, true, 2)['ok']);
        $this->get('/en')->assertOk()->assertSee('Hello')->assertDontSee('Welcome');
        $this->assertSame('Salam', $translations->resolvedValue($root->fresh())['sections'][0]['data']['document'][0]['content'][0]['text']);
        $bad = $this->value('Wrong');
        $bad['sections'][0]['data']['padding'] = 'xl';
        $this->assertFalse($store->save($english, $bad, null, true, 3)['ok']);
        $this->get('/en')->assertSee('Hello')->assertDontSee('Wrong');
    }

    public function test_metadata_changes_both_languages_atomically_and_checks_final_paths(): void
    {
        $root = SitePage::create(['title' => 'Utama', 'path' => '/', 'content' => $this->value('Salam')]);
        app(DocumentTranslations::class)->convert($root);
        $store = app(DocumentStore::class);
        $result = $store->saveMetadata($root, ['title' => 'Baru', 'translations' => ['en' => ['title' => 'Home', 'path' => '/home', 'is_published' => true]]], null, 1);
        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertSame(2, $result['edit_version']);
        $this->assertSame('/en/home', $root->translation('en')->path);
        SitePage::create(['title' => 'Taken', 'path' => '/en/taken', 'content' => []]);
        $refused = $store->saveMetadata($root, ['title' => 'Must not save', 'translations' => ['en' => ['path' => '/taken']]], null, 2);
        $this->assertFalse($refused['ok']);
        $this->assertSame('Baru', $root->fresh()->title);
        $this->assertSame('/en/home', $root->translation('en')->path);
        $this->assertSame(2, $root->fresh()->edit_version);
    }

    public function test_translated_history_restores_words_without_reverting_shared_layout(): void
    {
        $root = SitePage::create(['title' => 'Utama', 'path' => '/', 'content' => $this->value('Salam')]);
        $english = SitePage::create(['title' => 'Home', 'path' => '/en', 'locale' => 'en', 'translation_of' => $root->id, 'content' => $this->value('Welcome')]);
        $old = CmsRevision::create(['key' => $english->revisionKey(), 'content' => $this->value('Old English')]);
        $translations = app(DocumentTranslations::class);
        $translations->convert($root);
        $store = app(DocumentStore::class);
        $this->assertTrue($store->restore($english, $old, null, true, expectedVersion: 1)['ok']);
        $this->assertStringContainsString('Old English', json_encode($translations->resolvedValue($english->fresh())));
        $this->assertTrue($store->save($english, $this->value('New English'), null, true, 2)['ok']);
        $textRevision = CmsRevision::where('key', $english->revisionKey())->orderBy('id')->skip(1)->firstOrFail();
        $value = $translations->editorValue($root->fresh());
        $value['sections'][0]['data']['padding'] = 'xl';
        $this->assertTrue($store->save($root, $value, null, true, 3)['ok']);
        $this->assertTrue($store->restore($english, $textRevision, null, true, 4)['ok']);
        $resolved = $translations->resolvedValue($english->fresh());
        $this->assertSame('xl', $resolved['sections'][0]['data']['padding']);
        $this->assertStringContainsString('Old English', json_encode($resolved));
        $this->assertFalse($store->restore($english, $old, null, true, 5)['ok']);
        $this->assertSame($this->value('Old English'), $old->fresh()->content);
    }

    public function test_new_page_initializes_shared_storage_but_legacy_save_never_converts(): void
    {
        $store = app(DocumentStore::class);
        $new = new SitePage(['title' => 'New', 'path' => '/new']);
        $this->assertTrue($store->save($new, $this->value('New words'), null)['ok']);
        $this->assertSame(1, $new->fresh()->shared_format);
        $this->assertSame(1, $new->fresh()->edit_version);
        $part = Document::findOrCreatePart('header');
        $this->assertSame(1, $part->fresh()->shared_format);
        $legacy = SitePage::create(['title' => 'Old', 'path' => '/old', 'content' => []]);
        $this->assertTrue($store->save($legacy, $this->value('Old words'), null)['ok']);
        $this->assertNull($legacy->fresh()->shared_format);
    }

    public function test_conversion_links_keep_query_and_anchor_and_never_rewrite_visitor_text(): void
    {
        $base = $this->value('/en/contact');
        $base['sections'][0]['data']['document'][0]['content'] = [['type' => 'link', 'href' => '/hubungi?ref=nav#form', 'content' => [['type' => 'text', 'text' => '/en/contact', 'styles' => []]]]];
        $root = SitePage::create(['title' => 'Hubungi', 'path' => '/hubungi', 'is_published' => true, 'content' => $base]);
        $translated = $base;
        $translated['sections'][0]['data']['document'][0]['content'][0]['href'] = '/en/contact?ref=nav#form';
        $english = SitePage::create(['title' => 'Contact', 'path' => '/en/contact', 'locale' => 'en', 'translation_of' => $root->id, 'is_published' => true, 'content' => $translated]);
        $result = app(DocumentTranslations::class)->convert($root);
        $this->assertTrue($result['ok'], json_encode($result));
        $this->get('/hubungi')->assertSee('href="/hubungi?ref=nav#form"', false)->assertSee('/en/contact');
        $this->get('/en/contact')->assertSee('href="/en/contact?ref=nav#form"', false);
        $this->assertSame('/en/contact', app(DocumentTranslations::class)->resolvedValue($english->fresh())['sections'][0]['data']['document'][0]['content'][0]['content'][0]['text']);
    }

    public function test_client_cannot_change_locked_english_words_through_the_root_envelope(): void
    {
        $value = $this->value('Locked Malay');
        $value['sections'][0]['lock'] = ['content' => true];
        $root = SitePage::create(['title' => 'Home', 'path' => '/', 'content' => $value]);
        $en = $value;
        $en['sections'][0]['data']['document'][0]['content'][0]['text'] = 'Locked English';
        $english = SitePage::create(['title' => 'English', 'path' => '/en', 'locale' => 'en', 'translation_of' => $root->id, 'content' => $en]);
        $translations = app(DocumentTranslations::class);
        $this->assertTrue($translations->convert($root)['ok']);
        $incoming = $translations->editorValue($root);
        $changed = $value;
        $changed['sections'][0]['data']['document'][0]['content'][0]['text'] = 'Not allowed';
        $incoming['translations']['en'] = TextTranslations::extract($value, $changed);
        $this->assertTrue(app(DocumentStore::class)->save($root, $incoming, null, false, 1)['ok']);
        $this->assertStringContainsString('Locked English', json_encode($translations->resolvedValue($english->fresh())));
        $this->assertArrayNotHasKey('translations', $root->fresh()->content);
    }

    public function test_client_can_save_an_unrelated_heading_while_a_block_definition_is_temporarily_missing(): void
    {
        $registry = app(BlockRegistry::class);
        CoreBlocks::register($registry);
        $base = $this->value('Tajuk asal');
        $base['sections'][0]['data']['document'][] = ['id' => 'faqblock', ...Blocks::ownBlock('fbe-faq', [
            'heading' => 'Soalan lazim',
            'items' => [['_id' => 'question1', 'question' => 'Waktu buka?', 'answer' => 'Setiap hari.']],
        ])];
        $english = $base;
        $english['sections'][0]['data']['document'][0]['content'][0]['text'] = 'Original heading';
        $english['sections'][0]['data']['document'][1]['props']['values'] = json_encode([
            'heading' => 'Frequently asked questions',
            'items' => [['_id' => 'question1', 'question' => 'Opening hours?', 'answer' => 'Every day.']],
        ]);
        $root = SitePage::create(['title' => 'Utama', 'path' => '/', 'is_published' => true, 'content' => $base]);
        SitePage::create(['title' => 'Home', 'path' => '/en', 'locale' => 'en', 'translation_of' => $root->id, 'is_published' => true, 'content' => $english]);
        $translations = app(DocumentTranslations::class);
        $this->assertTrue($translations->convert($root)['ok']);
        $definition = $registry->get('fbe-faq');
        $registry->forget('fbe-faq');
        try {
            $incoming = $translations->editorValue($root);
            $incoming['sections'][0]['data']['document'][0]['content'][0]['text'] = 'Tajuk baharu';
            $incoming['translations']['en']['block:faqblock/values/heading'] = 'Changed unknown text';
            $result = app(DocumentStore::class)->save($root, $incoming, null, false, expectedVersion: 1);
        } finally {
            $registry->register($definition);
        }

        $this->assertTrue($result['ok'], json_encode($result));
        $this->get('/')->assertOk()->assertSee('Tajuk baharu')->assertSee('Soalan lazim');
        $this->get('/en')->assertOk()->assertSee('Original heading')->assertSee('Frequently asked questions')
            ->assertSee('Opening hours?')->assertSee('Every day.')->assertDontSee('Changed unknown text');
    }

    public function test_document_creation_still_works_when_an_older_migration_runs_before_shared_columns_exist(): void
    {
        Schema::table('cms_documents', fn ($table) => $table->dropColumn(['shared_format', 'text_overrides', 'edit_version']));
        $page = new SitePage(['title' => 'Fresh install', 'path' => '/']);
        $result = app(DocumentStore::class)->save($page, $this->value('Welcome'), null, true);
        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('Welcome', json_encode($page->fresh()->content));
    }

    public function test_first_save_stores_translation_maps_on_language_rows_instead_of_dropping_them(): void
    {
        $value = $this->value('Salam');
        $value['translations']['en'] = TextTranslations::extract($value, $this->value('Welcome'));
        $page = new SitePage(['title' => 'Home', 'path' => '/']);
        $result = app(DocumentStore::class)->save($page, $value, null, true);
        $this->assertTrue($result['ok']);
        $this->assertNotNull($page->translation('en'));
        $this->assertStringContainsString('Welcome', json_encode(app(DocumentTranslations::class)->resolvedValue($page->translation('en'))));
        $this->assertArrayNotHasKey('translations', $page->fresh()->content);
        $this->assertSame(1, $page->fresh()->edit_version);
    }

    public function test_malformed_rich_translation_refuses_a_new_page_without_creating_rows_or_history(): void
    {
        $value = $this->value('Selamat');
        $value['translations']['en'] = ['block:paragraph1/rich' => '{"version":1,"content":'];
        $page = new SitePage(['title' => 'Home', 'path' => '/']);

        $result = app(DocumentStore::class)->save($page, $value, null, true);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']['content']);
        $this->assertSame(0, SitePage::count());
        $this->assertSame(0, CmsRevision::count());
    }

    public function test_conversion_retains_equal_and_intentionally_blank_english_text(): void
    {
        $base = $this->value('Same');
        $base['sections'][0]['data']['document'][] = ['id' => 'paragraph2', ...Blocks::paragraph('Unchanged')];
        $translated = $base;
        $translated['sections'][0]['data']['document'][0]['content'] = [];
        $root = SitePage::create(['title' => 'Home', 'path' => '/', 'content' => $base]);
        $english = SitePage::create(['title' => 'English', 'path' => '/en', 'locale' => 'en', 'translation_of' => $root->id, 'content' => $translated]);
        $result = app(DocumentTranslations::class)->convert($root);
        $this->assertTrue($result['ok'], json_encode($result));
        $before = json_encode(app(DocumentTranslations::class)->resolvedValue($english->fresh()));
        $this->assertStringNotContainsString('Same', $before);
        $this->assertStringContainsString('Unchanged', $before);
        $incoming = app(DocumentTranslations::class)->editorValue($root);
        $incoming['sections'][0]['data']['document'][0]['content'][0]['text'] = 'Changed Malay';
        $incoming['sections'][0]['data']['document'][1]['content'][0]['text'] = 'Other Malay';
        $this->assertTrue(app(DocumentStore::class)->save($root, $incoming, null, true, 1)['ok']);
        $resolved = json_encode(app(DocumentTranslations::class)->resolvedValue($english->fresh()));
        $this->assertStringNotContainsString('Changed Malay', $resolved);
        $this->assertStringContainsString('Unchanged', $resolved);
    }
}
