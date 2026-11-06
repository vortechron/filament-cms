<?php

namespace Vortechron\FilamentCms\Tests\Feature\Mcp;

use PHPUnit\Framework\Attributes\DataProvider;
use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentBlockEditor\Blocks\BlockRegistry;
use Vortechron\FilamentBlockEditor\Blocks\CoreBlocks;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\DocumentTranslations;
use Vortechron\FilamentCms\Mcp\Tools\GetDocument;
use Vortechron\FilamentCms\Mcp\Tools\SaveDocument;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\SitePage;

class MissingDefinitionWritesTest extends McpTestCase
{
    private function value(string $question, string $paragraph): array
    {
        return ['version' => 2, 'sections' => [['id' => 'sec00001', 'type' => 'rich', 'data' => ['document' => [
            ['id' => 'faq00001', ...Blocks::ownBlock('fbe-faq', ['heading' => 'FAQ', 'items' => [['question' => $question, 'answer' => 'Answer']]])],
            ['id' => 'words001', ...Blocks::paragraph($paragraph)],
        ]]]]];
    }

    public function test_an_english_id_can_edit_known_words_while_missing_custom_data_and_maps_remain_frozen(): void
    {
        config(['filament-cms.locales' => ['ms' => 'Malay', 'en' => 'English'], 'filament-cms.default_locale' => 'ms']);
        $page = SitePage::create(['title' => 'Home', 'path' => '/', 'is_published' => true, 'content' => $this->value('Bila dibuka?', 'Melayu')]);
        $en = SitePage::create(['title' => 'English', 'path' => '/en', 'locale' => 'en', 'translation_of' => $page->id, 'is_published' => true, 'content' => $this->value('When open?', 'English')]);
        $this->assertTrue(app(DocumentTranslations::class)->convert($page)['ok']);
        $base = $this->runTool(GetDocument::class, ['id' => $page->id])['value'];
        app(BlockRegistry::class)->forget('fbe-faq');
        $value = $this->runTool(GetDocument::class, ['id' => $en->id])['value'];
        $value['sections'][0]['data']['document'][1]['content'][0]['text'] = 'English updated';

        $this->assertSaved($this->runTool(SaveDocument::class, ['id' => $en->id, 'value' => $value, 'expected_version' => 1]));
        $this->assertSame(2, $this->runTool(GetDocument::class, ['id' => $en->id])['edit_version']);
        $value['sections'][0]['data']['document'][0]['props']['values'] = '{"heading":"Tampered hidden heading","items":[]}';
        $value['sections'][0]['data']['document'][1]['content'][0]['text'] = 'English updated again';
        $this->assertSaved($this->runTool(SaveDocument::class, ['id' => $en->id, 'value' => $value, 'expected_version' => 2]));
        $value['sections'][0]['data']['padding'] = 'xl';
        $this->assertRefused($this->runTool(SaveDocument::class, ['id' => $en->id, 'value' => $value, 'expected_version' => 3]), 'content');
        CoreBlocks::register(app(BlockRegistry::class));

        $this->get('/en')->assertSee('When open?')->assertSee('English updated again')->assertDontSee('Tampered hidden heading');
        $after = $this->runTool(GetDocument::class, ['id' => $page->id]);
        $this->assertSame(3, $after['edit_version']);
        $this->assertSame($base['sections'], $after['value']['sections']);
        $faqMaps = fn (array $maps): array => array_filter($maps, fn ($key): bool => str_starts_with($key, 'block:faq00001/'), ARRAY_FILTER_USE_KEY);
        $this->assertSame($faqMaps($base['translations']['en']), $faqMaps($after['value']['translations']['en']));
    }

    public static function writers(): array
    {
        return ['client' => [false], 'developer' => [true]];
    }

    #[DataProvider('writers')]
    public function test_a_modern_text_restore_keeps_frozen_current_maps_and_restores_known_words(bool $developer): void
    {
        config(['filament-cms.locales' => ['ms' => 'Malay', 'en' => 'English'], 'filament-cms.default_locale' => 'ms']);
        $page = SitePage::create(['title' => 'Home', 'path' => '/', 'is_published' => true, 'content' => $this->value('Bila dibuka?', 'Melayu')]);
        $en = SitePage::create(['title' => 'English', 'path' => '/en', 'locale' => 'en', 'translation_of' => $page->id, 'is_published' => true, 'content' => $this->value('When open?', 'English')]);
        $translations = app(DocumentTranslations::class);
        $this->assertTrue($translations->convert($page)['ok']);
        $store = app(DocumentStore::class);
        $this->assertTrue($store->save($en, $this->value('Earlier FAQ?', 'Earlier paragraph'), null, true, 1)['ok']);
        $revision = CmsRevision::where('key', $en->revisionKey())->latest('id')->firstOrFail();
        $original = $revision->content;
        $this->assertTrue($store->save($en, $this->value('Current FAQ?', 'Current paragraph'), null, true, 2)['ok']);
        app(BlockRegistry::class)->forget('fbe-faq');

        $result = $store->restore($en, $revision, null, $developer, 3);

        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertSame(4, $result['edit_version']);
        CoreBlocks::register(app(BlockRegistry::class));
        $this->get('/en')->assertSee('Current FAQ?')->assertSee('Earlier paragraph')->assertDontSee('Earlier FAQ?');
        $this->assertSame($original, $revision->fresh()->content);
    }

    #[DataProvider('writers')]
    public function test_a_modern_restore_restores_missing_text_presence_so_later_default_edits_still_fall_back(bool $developer): void
    {
        config(['filament-cms.locales' => ['ms' => 'Malay', 'en' => 'English'], 'filament-cms.default_locale' => 'ms']);
        $page = SitePage::create(['title' => 'Home', 'path' => '/', 'is_published' => true, 'content' => $this->value('Bila?', 'Salam')]);
        $en = SitePage::create(['title' => 'English', 'path' => '/en', 'locale' => 'en', 'translation_of' => $page->id, 'is_published' => true, 'content' => $this->value('When?', 'Welcome')]);
        $translations = app(DocumentTranslations::class);
        $this->assertTrue($translations->convert($page)['ok']);
        $revision = CmsRevision::create(['key' => $en->revisionKey(), 'content' => ['shared_format' => 1, 'text_overrides' => []]]);

        $result = app(DocumentStore::class)->restore($en, $revision, null, $developer, 1);

        $this->assertTrue($result['ok'], json_encode($result));
        $value = $translations->editorValue($page->fresh());
        $this->assertSame([], $value['translations']['en']);
        $value['sections'][0]['data']['document'][1]['content'][0]['text'] = 'New Malay default';
        $this->assertTrue(app(DocumentStore::class)->save($page, $value, null, true, 2)['ok']);
        $this->get('/en')->assertSee('New Malay default')->assertDontSee('Welcome');
    }

    #[DataProvider('writers')]
    public function test_missing_target_keys_restore_fallback_while_current_opaque_translations_stay_frozen(bool $developer): void
    {
        config(['filament-cms.locales' => ['ms' => 'Malay', 'en' => 'English'], 'filament-cms.default_locale' => 'ms']);
        $page = SitePage::create(['title' => 'Home', 'path' => '/', 'is_published' => true, 'content' => $this->value('Bila?', 'Salam')]);
        $en = SitePage::create(['title' => 'English', 'path' => '/en', 'locale' => 'en', 'translation_of' => $page->id, 'is_published' => true, 'content' => $this->value('When?', 'Welcome')]);
        $translations = app(DocumentTranslations::class);
        $this->assertTrue($translations->convert($page)['ok']);
        $revision = CmsRevision::create(['key' => $en->revisionKey(), 'content' => ['shared_format' => 1, 'text_overrides' => []]]);
        app(BlockRegistry::class)->forget('fbe-faq');

        $result = app(DocumentStore::class)->restore($en, $revision, null, $developer, 1);

        $this->assertTrue($result['ok'], json_encode($result));
        $value = $translations->editorValue($page->fresh());
        $value['sections'][0]['data']['document'][1]['content'][0]['text'] = 'Later Malay words';
        $this->assertTrue(app(DocumentStore::class)->save($page, $value, null, true, 2)['ok']);
        CoreBlocks::register(app(BlockRegistry::class));
        $this->get('/en')->assertSee('When?')->assertSee('Later Malay words')->assertDontSee('Welcome');
    }

    public function test_client_restore_keeps_protected_explicit_presence_even_when_it_equals_the_default_text(): void
    {
        config(['filament-cms.locales' => ['ms' => 'Malay', 'en' => 'English'], 'filament-cms.default_locale' => 'ms']);
        $base = $this->value('Bila?', 'Salam');
        $base['sections'][0]['lock'] = ['content' => true];
        $english = $this->value('When?', 'Salam');
        $english['sections'][0]['lock'] = ['content' => true];
        $page = SitePage::create(['title' => 'Home', 'path' => '/', 'is_published' => true, 'content' => $base]);
        $en = SitePage::create(['title' => 'English', 'path' => '/en', 'locale' => 'en', 'translation_of' => $page->id, 'is_published' => true, 'content' => $english]);
        $translations = app(DocumentTranslations::class);
        $this->assertTrue($translations->convert($page)['ok']);
        $before = $translations->editorValue($page)['translations']['en'];
        $revision = CmsRevision::create(['key' => $en->revisionKey(), 'content' => ['shared_format' => 1, 'text_overrides' => []]]);

        $result = app(DocumentStore::class)->restore($en, $revision, null, false, 1);

        $this->assertTrue($result['ok'], json_encode($result));
        $value = $translations->editorValue($page->fresh());
        $this->assertSame($before, $value['translations']['en']);
        $value['sections'][0]['data']['document'][1]['content'][0]['text'] = 'Changed default';
        $this->assertTrue(app(DocumentStore::class)->save($page, $value, null, true, 2)['ok']);
        $this->get('/en')->assertSee('When?')->assertSee('Salam')->assertDontSee('Changed default');
    }
}
