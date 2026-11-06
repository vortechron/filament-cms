<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentBlockEditor\Content\TextTranslations;
use Vortechron\FilamentBlockEditor\Rendering\RenderContext;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\DocumentTranslations;
use Vortechron\FilamentCms\Entries\EntryList;
use Vortechron\FilamentCms\Entries\EntryPageRenderer;
use Vortechron\FilamentCms\Entries\EntryStore;
use Vortechron\FilamentCms\Entries\EntryTemplates;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\Entry;
use Vortechron\FilamentCms\SiteLanguages;
use Vortechron\FilamentCms\Tests\TestCase;

class SharedEntryDesignsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['filament-cms.locales' => ['ms' => 'Malay', 'en' => 'English'], 'filament-cms.default_locale' => 'ms', 'filament-block-editor.content_locale' => 'ms']);
        app(EntryTypes::class)->register(EntryType::make('product')->path('/products'));
    }

    public static function kinds(): array
    {
        return ['card' => [Document::CARD], 'single' => [Document::SINGLE]];
    }

    private function value(string $text): array
    {
        return ['version' => 2, 'sections' => [['id' => 'design01', 'type' => 'rich', 'data' => [
            'layout' => 'poster', 'advanced' => ['class' => 'bespoke-product-design'],
            'document' => [
                ['id' => 'photo001', ...Blocks::image('/storage/design-photo.webp')],
                ['id' => 'words001', ...Blocks::paragraph($text)],
            ],
        ]]]];
    }

    private function renderDesign(string $kind, string $locale): string
    {
        $entry = new Entry(['entry_type' => 'product']);
        $this->assertTrue(app(EntryStore::class)->save($entry, ['title' => 'Kopi', 'is_published' => true], null)['ok']);

        return SiteLanguages::run($locale, fn (): string => $kind === Document::CARD
            ? app(EntryList::class)->render(['type' => 'product'], new RenderContext)
            : app(EntryPageRenderer::class)->view($entry)->render());
    }

    #[DataProvider('kinds')]
    public function test_english_visitors_keep_the_shared_design_and_media_when_no_english_row_exists(string $kind): void
    {
        $design = EntryTemplates::findOrCreate(app(EntryTypes::class)->get('product'), $kind);
        $this->assertTrue(app(DocumentStore::class)->save($design, $this->value('Reka bentuk asal'), null, true, 1)['ok']);

        $html = $this->renderDesign($kind, 'en');

        $this->assertStringContainsString('bespoke-product-design', $html);
        $this->assertStringContainsString('data-layout="poster"', $html);
        $this->assertStringContainsString('/storage/design-photo.webp', $html);
        $this->assertStringContainsString('Reka bentuk asal', $html);
    }

    public static function translatedDesigns(): array
    {
        return [
            'card English' => [Document::CARD, 'English design'],
            'single English' => [Document::SINGLE, 'English design'],
            'card blank' => [Document::CARD, ''],
            'single blank' => [Document::SINGLE, ''],
        ];
    }

    #[DataProvider('translatedDesigns')]
    public function test_the_current_language_words_or_explicit_blank_use_the_same_design(string $kind, string $english): void
    {
        $design = EntryTemplates::findOrCreate(app(EntryTypes::class)->get('product'), $kind);
        $value = $this->value('Original Malay words');
        $value['translations']['en'] = TextTranslations::extract($value, $this->value($english));
        $this->assertTrue(app(DocumentStore::class)->save($design, $value, null, true, 1)['ok']);

        $html = $this->renderDesign($kind, 'en');

        $this->assertStringContainsString('bespoke-product-design', $html);
        $this->assertStringContainsString('/storage/design-photo.webp', $html);
        $this->assertStringNotContainsString('Original Malay words', $html);
        if ($english !== '') {
            $this->assertStringContainsString('English design', $html);
        }
        $this->assertSame('en', SiteLanguages::run('en', fn () => EntryTemplates::find('product', $kind)->language()));
    }

    #[DataProvider('kinds')]
    public function test_opening_an_english_design_reuses_the_shared_root_without_creating_a_counterpart(string $kind): void
    {
        $type = app(EntryTypes::class)->get('product');
        $design = EntryTemplates::findOrCreate($type, $kind);
        $this->assertTrue(app(DocumentStore::class)->save($design, $this->value('Shared words'), null, true, 1)['ok']);

        $opened = SiteLanguages::run('en', fn () => EntryTemplates::findOrCreate($type, $kind));

        $this->assertSame($design->id, $opened->id);
        $this->assertSame([], app(DocumentTranslations::class)->editorValue($design->fresh())['translations']);
    }

    #[DataProvider('kinds')]
    public function test_a_legacy_design_in_another_language_is_not_used_or_converted_by_a_read(string $kind): void
    {
        Document::create(['kind' => $kind, 'entry_type' => 'product', 'title' => 'Old Malay design', 'content' => $this->value('Legacy Malay words')]);

        $html = $this->renderDesign($kind, 'en');

        $this->assertStringNotContainsString('bespoke-product-design', $html);
        $this->assertStringNotContainsString('Legacy Malay words', $html);
        $this->assertNull(SiteLanguages::run('en', fn () => EntryTemplates::find('product', $kind)));
    }

    #[DataProvider('kinds')]
    public function test_an_existing_english_variant_takes_priority_over_the_shared_root(string $kind): void
    {
        $root = EntryTemplates::findOrCreate(app(EntryTypes::class)->get('product'), $kind);
        $this->assertTrue(app(DocumentStore::class)->save($root, $this->value('Shared root words'), null, true, 1)['ok']);
        $variant = $this->value('Legacy English variant');
        $variant['sections'][0]['data']['advanced']['class'] = 'english-variant-design';
        $english = Document::create(['kind' => $kind, 'entry_type' => 'product', 'locale' => 'en', 'title' => 'English variant', 'content' => $variant]);

        $html = $this->renderDesign($kind, 'en');

        $this->assertStringContainsString('english-variant-design', $html);
        $this->assertStringContainsString('Legacy English variant', $html);
        $this->assertStringNotContainsString('Shared root words', $html);
        $this->assertSame($english->id, SiteLanguages::run('en', fn () => EntryTemplates::find('product', $kind))->id);
    }
}
