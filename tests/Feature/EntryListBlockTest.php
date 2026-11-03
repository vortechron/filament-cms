<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentBlockEditor\Blocks\BlockRegistry;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Entries\EntryStore;
use Vortechron\FilamentCms\Entries\EntryTemplates;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\BuildSitePage;
use Vortechron\FilamentCms\FilamentCmsPlugin;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\Entry;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\TestCase;

class EntryListBlockTest extends TestCase
{
    private function registerTypes(): void
    {
        app(EntryTypes::class)
            ->register(EntryType::make('product')->label('Produk')->pluralLabel('Semua produk')->path('/produk')->fields([
                Field::text('price'),
                Field::image('photo'),
                Field::textarea('summary'),
                Field::textarea('details'),
            ]))
            ->register(EntryType::make('faq')->label('Soalan')->fields([Field::textarea('answer')]));
    }

    private function entry(string $type, string $title, array $data = [], ?string $at = null): Entry
    {
        if ($at !== null) {
            Carbon::setTestNow($at);
        }

        $entry = new Entry(['entry_type' => $type]);
        app(EntryStore::class)->save($entry, ['title' => $title, 'is_published' => true, ...$data], null);
        Carbon::setTestNow();

        return $entry->fresh();
    }

    /**
     * A published page at /senarai showing one list block.
     *
     * @param  array<string, mixed>  $values
     */
    private function listPage(array $values): SitePage
    {
        return SitePage::query()->create(['title' => 'Senarai', 'path' => '/senarai', 'is_published' => true, 'sections' => [
            ['id' => 'list0001', 'type' => 'rich', 'data' => ['document' => [Blocks::ownBlock('cms-entries', $values)]]],
        ]]);
    }

    private function design(string $type, array $content): Document
    {
        $card = EntryTemplates::findOrCreate(app(EntryTypes::class)->get($type), Document::CARD);
        app(DocumentStore::class)->save($card, ['sections' => [
            ['id' => 'card0001', 'type' => 'rich', 'data' => ['document' => [['type' => 'paragraph', 'props' => [], 'content' => $content, 'children' => []]]]],
        ]], null);

        return $card;
    }

    private function main(string $html): string
    {
        return str($html)->after('<main>')->before('</main>')->toString();
    }

    public function test_the_block_is_registered_only_when_a_type_exists(): void
    {
        $this->assertFalse(app(BlockRegistry::class)->has('cms-entries'));

        $this->registerTypes();
        $block = app(BlockRegistry::class)->get('cms-entries')?->toArray();

        $this->assertNotNull($block);
        $fields = collect($block['fields'])->keyBy('name');
        $this->assertSame(['type', 'limit', 'columns', 'order', 'empty_text'], $fields->keys()->all());
        $this->assertSame([['value' => 'product', 'label' => 'Semua produk'], ['value' => 'faq', 'label' => 'Soalan']], $fields['type']['options']);
        $this->assertSame(['number', 1, 48, 6], [$fields['limit']['type'], $fields['limit']['min'], $fields['limit']['max'], $fields['limit']['default']]);
        $this->assertSame(['1', '2', '3', '4'], array_column($fields['columns']['options'], 'value'));
        $this->assertSame(['newest', 'title', 'manual'], array_column($fields['order']['options'], 'value'));
        $this->assertSame('text', $fields['empty_text']['type']);

        app(EntryTypes::class)->forget('product')->forget('faq');
        $this->assertFalse(app(BlockRegistry::class)->has('cms-entries'));
    }

    public function test_it_draws_the_published_entries_newest_first_up_to_the_limit(): void
    {
        $this->registerTypes();
        $this->entry('product', 'Lama', at: '2026-11-01 10:00');
        $this->entry('product', 'Baru', at: '2026-11-03 10:00');
        $this->entry('product', 'Tengah', at: '2026-11-02 10:00');
        $this->entry('product', 'Draf', ['is_published' => false], at: '2026-11-04 10:00');
        $this->entry('faq', 'Soalan lain');
        $this->listPage(['type' => 'product', 'limit' => 2]);

        $html = $this->main($this->get('/senarai')->assertOk()->getContent());

        $this->assertStringContainsString('Baru', $html);
        $this->assertStringContainsString('Tengah', $html);
        $this->assertStringNotContainsString('Lama', $html);
        $this->assertStringNotContainsString('Draf', $html);
        $this->assertStringNotContainsString('Soalan lain', $html);
        $this->assertLessThan(strpos($html, 'Tengah'), strpos($html, 'Baru'));
    }

    public function test_it_orders_by_title_or_by_the_clients_own_order(): void
    {
        $this->registerTypes();
        $cempaka = $this->entry('product', 'Cempaka');
        $anggerik = $this->entry('product', 'Anggerik');
        $bakawali = $this->entry('product', 'Bakawali');
        $page = $this->listPage(['type' => 'product', 'order' => 'title']);

        $html = $this->main($this->get('/senarai')->getContent());
        $this->assertTrue(strpos($html, 'Anggerik') < strpos($html, 'Bakawali') && strpos($html, 'Bakawali') < strpos($html, 'Cempaka'));

        $bakawali->forceFill(['sort' => 1])->save();
        $cempaka->forceFill(['sort' => 2])->save();
        $anggerik->forceFill(['sort' => 3])->save();
        $page->forceFill(['sections' => [['id' => 'list0001', 'type' => 'rich', 'data' => ['document' => [Blocks::ownBlock('cms-entries', ['type' => 'product', 'order' => 'manual'])]]]]])->save();

        $html = $this->main($this->get('/senarai')->getContent());
        $this->assertTrue(strpos($html, 'Bakawali') < strpos($html, 'Cempaka') && strpos($html, 'Cempaka') < strpos($html, 'Anggerik'));
    }

    public function test_with_no_entries_it_draws_the_empty_text_escaped(): void
    {
        $this->registerTypes();
        $this->listPage(['type' => 'product', 'empty_text' => 'Tiada <b>produk</b> lagi']);

        $html = $this->main($this->get('/senarai')->getContent());

        $this->assertStringContainsString('Tiada &lt;b&gt;produk&lt;/b&gt; lagi', $html);
        $this->assertStringNotContainsString('cms-entries-item', $html);
    }

    public function test_with_no_entries_and_no_empty_text_it_draws_nothing(): void
    {
        $this->registerTypes();
        $this->listPage(['type' => 'product', 'empty_text' => '']);

        $this->assertStringNotContainsString('cms-entries', $this->main($this->get('/senarai')->getContent()));
    }

    public function test_the_built_in_card_shows_the_first_picture_title_first_textarea_and_a_link(): void
    {
        $this->registerTypes();
        $this->entry('product', 'Kopi <script>alert(1)</script>', ['fields' => [
            'photo' => '/storage/cms/kopi.jpg',
            'summary' => 'Kopi <i>pekat</i>',
            'details' => 'Butiran lain',
        ]]);
        $this->entry('faq', 'Bila buka?', ['fields' => ['answer' => 'Setiap hari']]);
        $this->listPage(['type' => 'product', 'columns' => '2']);

        $html = $this->main($this->get('/senarai')->getContent());

        $this->assertStringContainsString('class="cms-entries cms-entries-2"', $html);
        $this->assertStringContainsString('src="/storage/cms/kopi.jpg"', $html);
        $this->assertStringContainsString('Kopi &lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('Kopi &lt;i&gt;pekat&lt;/i&gt;', $html);
        $this->assertStringNotContainsString('Butiran lain', $html);
        $this->assertStringContainsString('href="'.url('/produk/kopi-scriptalert1script').'"', $html);

        SitePage::query()->where('path', '/senarai')->update(['path' => '/lama']);
        $this->listPage(['type' => 'faq']);
        $faq = $this->main($this->get('/senarai')->getContent());
        $this->assertStringContainsString('Setiap hari', $faq);
        $this->assertStringNotContainsString('href=', $faq);
    }

    public function test_a_designed_card_is_drawn_with_each_entrys_values(): void
    {
        $this->registerTypes();
        $this->entry('product', 'Kopi', ['fields' => ['price' => 'RM 12']]);
        $this->entry('product', 'Teh <b>tarik</b>', ['fields' => ['price' => 'RM 5']]);
        $this->design('product', [Blocks::dynamic('entry.title'), ['type' => 'text', 'text' => ' — ', 'styles' => []], Blocks::dynamic('entry.price')]);
        $this->listPage(['type' => 'product']);

        $html = $this->main($this->get('/senarai')->getContent());

        $this->assertStringContainsString('Kopi — RM 12', $html);
        $this->assertStringContainsString('Teh &lt;b&gt;tarik&lt;/b&gt; — RM 5', $html);
        $this->assertSame(2, substr_count($html, 'cms-entries-item'));
        $this->assertStringNotContainsString('cms-entry-card', $html);
    }

    public function test_a_card_never_draws_a_list_inside_it(): void
    {
        $this->registerTypes();
        $this->entry('product', 'Kopi');
        $this->entry('product', 'Teh');
        $card = EntryTemplates::findOrCreate(app(EntryTypes::class)->get('product'), Document::CARD);
        app(DocumentStore::class)->save($card, ['sections' => [['id' => 'card0001', 'type' => 'rich', 'data' => ['document' => [
            ['type' => 'paragraph', 'props' => [], 'content' => [Blocks::dynamic('entry.title')], 'children' => []],
            Blocks::ownBlock('cms-entries', ['type' => 'product']),
        ]]]]], null);
        $this->listPage(['type' => 'product']);

        $html = $this->main($this->get('/senarai')->assertOk()->getContent());

        $this->assertSame(1, substr_count($html, 'class="cms-entries '));
        $this->assertSame(2, substr_count($html, 'cms-entries-item'));
    }

    public function test_in_the_builder_the_cards_are_not_sections_of_the_page(): void
    {
        $this->registerTypes();
        $this->entry('product', 'Kopi');
        $this->design('product', [Blocks::dynamic('entry.title')]);
        $page = $this->listPage(['type' => 'product']);
        $this->actingAsClient();

        $html = Livewire::test(BuildSitePage::class, ['record' => $page->getKey()])
            ->call('previewPageBuilder', ['sections' => $page->sections])
            ->effects['returns'][0];

        $this->assertStringContainsString('data-block-id="list0001"', $html);
        $this->assertStringNotContainsString('data-block-id="card0001"', $html);
        $this->assertStringContainsString('data-entry-block-id="card0001"', $html);
        $this->assertStringContainsString('Kopi', $html);
    }

    public function test_the_card_builder_shows_a_list_inside_the_card_as_a_note_only(): void
    {
        $this->registerTypes();
        $this->entry('product', 'Kopi');
        $card = EntryTemplates::findOrCreate(app(EntryTypes::class)->get('product'), Document::CARD);
        $this->actingAsClient();

        $html = Livewire::test(BuildSitePage::class, ['record' => $card->getKey()])
            ->call('previewPageBuilder', ['sections' => [['id' => 'card0001', 'type' => 'rich', 'data' => ['document' => [Blocks::ownBlock('cms-entries', ['type' => 'product'])]]]]])
            ->effects['returns'][0];

        $this->assertStringNotContainsString('cms-entries-item', $html);
        $this->assertStringContainsString(__('filament-cms::entries.list_in_card'), $html);
    }

    public function test_the_host_prints_the_built_in_card_styles_with_a_component(): void
    {
        $html = Blade::render('<x-filament-cms::entries-styles />');

        $this->assertStringStartsWith('<style data-cms-entries>', trim($html));
        $this->assertStringContainsString('.cms-entries {', $html);
        $this->assertStringContainsString('var(--sf-card', $html);
    }

    public function test_the_builder_offers_the_block_only_while_entries_are_on(): void
    {
        $this->registerTypes();
        $page = $this->listPage(['type' => 'product']);
        $this->actingAsClient();
        $kinds = fn (): array => array_column(Livewire::test(BuildSitePage::class, ['record' => $page->getKey()])->instance()->getPageBuilderOptions()['blocks'], 'kind');

        $this->assertContains('cms-entries', $kinds());

        FilamentCmsPlugin::get()->entries(false);
        $this->assertNotContains('cms-entries', $kinds());
    }
}
