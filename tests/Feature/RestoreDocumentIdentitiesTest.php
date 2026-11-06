<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentBlockEditor\Blocks\BlockRegistry;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentBlockEditor\Blocks\OwnBlock;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\DocumentTranslations;
use Vortechron\FilamentCms\Mcp\Tools\GetDocument;
use Vortechron\FilamentCms\Mcp\Tools\ListVersions;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\Feature\Mcp\McpTestCase;

class RestoreDocumentIdentitiesTest extends McpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['filament-cms.locales' => ['ms' => 'Malay', 'en' => 'English'], 'filament-cms.default_locale' => 'ms', 'filament-block-editor.content_locale' => 'ms']);
    }

    private function faq(array $items): array
    {
        return ['version' => 2, 'sections' => [['id' => 'sec00001', 'type' => 'rich', 'data' => ['document' => [
            ['id' => 'faq00001', ...Blocks::ownBlock('fbe-faq', ['heading' => 'FAQ', 'items' => $items])],
        ]]]]];
    }

    private function converted(array $base, array $english): array
    {
        $page = SitePage::create(['title' => 'Home', 'path' => '/', 'is_published' => true, 'content' => $base]);
        $en = SitePage::create(['title' => 'English', 'path' => '/en', 'is_published' => true, 'locale' => 'en', 'translation_of' => $page->id, 'content' => $english]);
        $revision = CmsRevision::create(['key' => $page->revisionKey(), 'content' => $base]);
        $this->assertTrue(app(DocumentTranslations::class)->convert($page)['ok']);

        return [$page, $en, $revision];
    }

    public function test_restoring_a_preconversion_faq_keeps_the_existing_english_row_text(): void
    {
        $old = $this->faq([['question' => 'Bila dibuka?', 'answer' => 'Isnin.']]);
        [$page, $english, $revision] = $this->converted($old, $this->faq([['question' => 'When open?', 'answer' => 'Monday.']]));
        $before = app(DocumentTranslations::class)->editorValue($page)['translations'];
        $this->get('/en')->assertSee('When open?');

        $result = app(DocumentStore::class)->restore($page, $revision, null, true, 1);

        $this->assertTrue($result['ok'], json_encode($result));
        $this->get('/en')->assertOk()->assertSee('When open?')->assertSee('Monday.')->assertDontSee('Bila dibuka?');
        $this->assertSame($before, app(DocumentTranslations::class)->editorValue($page->fresh())['translations']);
        $this->assertSame($old, $revision->fresh()->content);
    }

    public function test_restoring_legacy_form_options_keeps_their_english_labels(): void
    {
        $form = ['name' => 'Services', 'fields' => [['type' => 'select', 'name' => 'service', 'label' => 'Servis', 'options' => ['Baiki', 'Cuci']]]];
        $old = ['version' => 2, 'sections' => [['id' => 'sec00001', 'type' => 'rich', 'data' => ['document' => [['id' => 'frm00001', 'type' => 'form', 'props' => ['form' => json_encode($form)]]]]]]];
        $form['fields'][0]['label'] = 'Service';
        $form['fields'][0]['options'] = ['Repair', 'Clean'];
        $en = $old;
        $en['sections'][0]['data']['document'][0] = ['id' => 'frm00001', 'type' => 'form', 'props' => ['form' => json_encode($form)]];
        [$page, $english, $revision] = $this->converted($old, $en);
        $this->get('/en')->assertSee('Repair')->assertSee('Clean');

        $result = app(DocumentStore::class)->restore($page, $revision, null, true, 1);

        $this->assertTrue($result['ok'], json_encode($result));
        $this->get('/en')->assertSee('Service')->assertSee('Repair')->assertSee('Clean')->assertDontSee('Baiki');
        $this->assertSame($old, $revision->fresh()->content);
    }

    public function test_a_legacy_table_restore_keeps_identified_english_cells(): void
    {
        $cell = fn (string $text): array => ['type' => 'tableCell', 'content' => [['type' => 'text', 'text' => $text, 'styles' => []]]];
        $old = ['version' => 2, 'sections' => [['id' => 'sec00001', 'type' => 'rich', 'data' => ['document' => [
            ['id' => 'table001', 'type' => 'table', 'content' => ['type' => 'tableContent', 'rows' => [['cells' => [$cell('Satu'), $cell('Dua')]]]]],
        ]]]]];
        $en = $old;
        $en['sections'][0]['data']['document'][0]['content']['rows'][0]['cells'] = [$cell('One'), $cell('Two')];
        [$page, $english, $revision] = $this->converted($old, $en);
        $this->get('/en')->assertSee('One')->assertSee('Two');

        $result = app(DocumentStore::class)->restore($page, $revision, null, true, 1);

        $this->assertTrue($result['ok'], json_encode($result));
        $this->get('/en')->assertSee('One')->assertSee('Two')->assertDontSee('Satu');
    }

    public function test_exact_survivors_match_across_reorder_before_one_changed_row_is_recovered(): void
    {
        $old = $this->faq([['question' => 'Satu?', 'answer' => 'A'], ['question' => 'Dua?', 'answer' => 'B']]);
        [$page, $english, $revision] = $this->converted($old, $this->faq([['question' => 'First?', 'answer' => 'English A'], ['question' => 'Second?', 'answer' => 'English B']]));
        $value = app(DocumentTranslations::class)->editorValue($page);
        $values = json_decode($value['sections'][0]['data']['document'][0]['props']['values'], true);
        $values['items'] = array_reverse($values['items']);
        $values['items'][0]['question'] = 'Dua diubah?';
        $values['items'][0]['answer'] = 'Diubah';
        $value['sections'][0]['data']['document'][0]['props']['values'] = json_encode($values);
        $this->assertTrue(app(DocumentStore::class)->save($page, $value, null, true, 1)['ok']);

        $result = app(DocumentStore::class)->restore($page, $revision, null, true, 2);

        $this->assertTrue($result['ok'], json_encode($result));
        $this->get('/en')->assertSeeInOrder(['First?', 'Second?'])->assertSee('English A')->assertSee('English B');
    }

    public function test_ambiguous_duplicate_rows_refuse_atomically_in_the_panel_language(): void
    {
        $old = $this->faq([['question' => 'Sama?', 'answer' => 'Sama'], ['question' => 'Sama?', 'answer' => 'Sama']]);
        [$page, $english, $revision] = $this->converted($old, $this->faq([['question' => 'First distinct English?', 'answer' => 'One'], ['question' => 'Second distinct English?', 'answer' => 'Two']]));
        $before = $this->runTool(GetDocument::class, ['id' => $page->id]);
        $enBefore = $this->runTool(GetDocument::class, ['id' => $english->id]);
        $history = $this->runTool(ListVersions::class, ['document_id' => $page->id]);
        app()->setLocale('en');

        $result = app(DocumentStore::class)->restore($page, $revision, null, true, 1);

        $this->assertFalse($result['ok']);
        $this->assertSame(__('filament-cms::languages.restore_identity', [], 'en'), $result['errors']['revision']);
        $this->assertSame($before, $this->runTool(GetDocument::class, ['id' => $page->id]));
        $this->assertSame($enBefore, $this->runTool(GetDocument::class, ['id' => $english->id]));
        $this->assertSame($history, $this->runTool(ListVersions::class, ['document_id' => $page->id]));
        $this->assertSame($old, $revision->fresh()->content);
        $this->get('/en')->assertSee('First distinct English?')->assertSee('Second distinct English?');
    }

    public function test_nested_repeater_ids_are_recovered_without_copying_the_layout_to_english(): void
    {
        app(BlockRegistry::class)->register(
            OwnBlock::make('nested-restore')->fields([
                Field::list('groups')->fields([
                    Field::text('label'),
                    Field::list('items')->fields([Field::text('text')]),
                ]),
            ])
        );
        $value = fn (string $label, string $text): array => ['version' => 2, 'sections' => [['id' => 'sec00001', 'type' => 'rich', 'data' => ['document' => [
            ['id' => 'nested01', ...Blocks::ownBlock('nested-restore', ['groups' => [['label' => $label, 'items' => [['text' => $text]]]]])],
        ]]]]];
        $old = $value('Kumpulan', 'Isi');
        [$page, $english, $revision] = $this->converted($old, $value('English group', 'English nested text'));
        $before = app(DocumentTranslations::class)->editorValue($page)['translations'];

        $result = app(DocumentStore::class)->restore($page, $revision, null, true, 1);

        $this->assertTrue($result['ok'], json_encode($result));
        $resolved = app(DocumentTranslations::class)->resolvedValue($english->fresh());
        $this->assertStringContainsString('English group', json_encode($resolved));
        $this->assertStringContainsString('English nested text', json_encode($resolved));
        $this->assertSame($before, app(DocumentTranslations::class)->editorValue($page->fresh())['translations']);
    }

    public function test_unique_shared_row_facts_are_used_to_match_otherwise_identical_text(): void
    {
        app(BlockRegistry::class)->register(
            OwnBlock::make('facts-restore')->fields([
                Field::list('items')->fields([
                    Field::text('id')->translatable(false),
                    Field::text('text'),
                ]),
            ])
        );
        $value = fn (string $first, string $second): array => ['version' => 2, 'sections' => [['id' => 'sec00001', 'type' => 'rich', 'data' => ['document' => [
            ['id' => 'facts001', ...Blocks::ownBlock('facts-restore', ['items' => [['id' => 'SKU-A', 'text' => $first], ['id' => 'SKU-B', 'text' => $second]]])],
        ]]]]];
        [$page, $english, $revision] = $this->converted($value('Sama', 'Sama'), $value('English A', 'English B'));

        $result = app(DocumentStore::class)->restore($page, $revision, null, true, 1);

        $this->assertTrue($result['ok'], json_encode($result));
        $resolved = json_encode(app(DocumentTranslations::class)->resolvedValue($english->fresh()));
        $this->assertStringContainsString('English A', $resolved);
        $this->assertStringContainsString('English B', $resolved);
    }

    public static function unsupportedRevisions(): array
    {
        return ['missing definition' => [true], 'unknown historical field' => [false]];
    }

    #[DataProvider('unsupportedRevisions')]
    public function test_unknown_historical_schema_refuses_without_changing_any_saved_state(bool $missingDefinition): void
    {
        $old = $this->faq([['question' => 'Bila?', 'answer' => 'Isnin']]);
        [$page, $english, $revision] = $this->converted($old, $this->faq([['question' => 'When?', 'answer' => 'Monday']]));
        if ($missingDefinition) {
            app(BlockRegistry::class)->forget('fbe-faq');
        } else {
            $values = json_decode($old['sections'][0]['data']['document'][0]['props']['values'], true);
            $values['items'][0]['unsupported_data'] = 'Must not be silently removed';
            $old['sections'][0]['data']['document'][0]['props']['values'] = json_encode($values);
            $revision = CmsRevision::create(['key' => $page->revisionKey(), 'content' => $old]);
        }
        $before = $this->runTool(GetDocument::class, ['id' => $page->id]);
        $enBefore = $this->runTool(GetDocument::class, ['id' => $english->id]);
        $history = $this->runTool(ListVersions::class, ['document_id' => $page->id]);
        app()->setLocale('ms');
        $stale = app(DocumentStore::class)->restore($page, $revision, null, true, 0);
        $this->assertFalse($stale['ok']);
        $this->assertArrayHasKey('expected_version', $stale['errors']);

        $result = app(DocumentStore::class)->restore($page, $revision, null, true, 1);

        $this->assertFalse($result['ok']);
        $this->assertSame(__('filament-cms::languages.restore_identity', [], 'ms'), $result['errors']['revision']);
        $this->assertSame($before, $this->runTool(GetDocument::class, ['id' => $page->id]));
        $this->assertSame($enBefore, $this->runTool(GetDocument::class, ['id' => $english->id]));
        $this->assertSame($history, $this->runTool(ListVersions::class, ['document_id' => $page->id]));
        $this->assertSame($old, $revision->fresh()->content);
    }

    public function test_a_known_block_moved_to_another_section_keeps_its_current_row_identities(): void
    {
        $old = $this->faq([['question' => 'Bila?', 'answer' => 'Isnin']]);
        [$page, $english, $revision] = $this->converted($old, $this->faq([['question' => 'When?', 'answer' => 'Monday']]));
        $value = app(DocumentTranslations::class)->editorValue($page);
        $moved = $value['sections'][0];
        $moved['id'] = 'sec00002';
        $value['sections'][0]['data']['document'] = [];
        $value['sections'][] = $moved;
        $this->assertTrue(app(DocumentStore::class)->save($page, $value, null, true, 1)['ok']);
        $this->get('/en')->assertSee('When?');

        $result = app(DocumentStore::class)->restore($page, $revision, null, true, 2);

        $this->assertTrue($result['ok'], json_encode($result));
        $this->get('/en')->assertSee('When?')->assertSee('Monday')->assertDontSee('Bila?');
    }

    public function test_an_unsupported_historical_payload_shape_returns_a_localized_refusal(): void
    {
        $old = $this->faq([['question' => 'Bila?', 'answer' => 'Isnin']]);
        [$page, $english] = $this->converted($old, $this->faq([['question' => 'When?', 'answer' => 'Monday']]));
        $old['sections'][0]['data']['document'][0]['props']['values'] = ['unsupported' => 'old array payload'];
        $revision = CmsRevision::create(['key' => $page->revisionKey(), 'content' => $old]);
        $before = $this->runTool(GetDocument::class, ['id' => $page->id]);
        app()->setLocale('en');

        $result = app(DocumentStore::class)->restore($page, $revision, null, true, 1);

        $this->assertFalse($result['ok']);
        $this->assertSame(__('filament-cms::languages.restore_identity', [], 'en'), $result['errors']['revision']);
        $this->assertSame($before, $this->runTool(GetDocument::class, ['id' => $page->id]));
        $this->assertSame($old, $revision->fresh()->content);
        $this->get('/en')->assertSee('When?');
    }
}
