<?php

namespace Vortechron\FilamentCms\Tests\Feature\Mcp;

use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Entries\EntryStore;
use Vortechron\FilamentCms\Entries\EntryTemplates;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Mcp\Tools\RenderDocument;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\Entry;

class RenderDocumentTest extends McpTestCase
{
    private function html(array $arguments): string
    {
        $html = $this->runTool(RenderDocument::class, $arguments)['_text'] ?? null;
        $this->assertFalse($this->lastToolFailed);
        $this->assertIsString($html);

        return $html;
    }

    public function test_it_draws_a_published_page_as_visitors_see_it(): void
    {
        $page = $this->makePage('/tentang', [$this->richSection('sec00001', 'Kami di Batu Pahat')], title: 'Tentang');

        $byId = $this->html(['id' => $page->getKey()]);

        $this->assertStringContainsString('data-test-page="/tentang"', $byId);
        $this->assertStringContainsString('Kami di Batu Pahat', $byId);
        $this->assertStringNotContainsString('data-item=', $byId);
        $this->assertSame($byId, $this->html(['path' => '/tentang']));
    }

    public function test_an_unpublished_page_is_drawn_only_when_asked_for(): void
    {
        $page = $this->makePage('/draf', [$this->richSection('sec00001', 'Belum siap')], published: false);

        $this->assertRefused($this->runTool(RenderDocument::class, ['id' => $page->getKey()]), 'include_unpublished');
        $this->assertStringContainsString('Belum siap', $this->html(['id' => $page->getKey(), 'include_unpublished' => true]));
    }

    public function test_the_header_is_public_and_a_synced_section_needs_include_unpublished(): void
    {
        $header = Document::findOrCreatePart(Document::HEADER);
        app(DocumentStore::class)->save($header, [$this->richSection('h1', 'Logo kedai')], null, developer: true);
        $synced = Document::query()->create(['kind' => Document::SECTION, 'title' => 'Promosi', 'content' => ['version' => 2, 'sections' => [$this->richSection('p', 'Diskaun raya')]]]);

        $this->assertStringContainsString('Logo kedai', $this->html(['id' => $header->getKey()]));
        $this->assertRefused($this->runTool(RenderDocument::class, ['id' => $synced->getKey()]), 'include_unpublished');
        $this->assertStringContainsString('Diskaun raya', $this->html(['id' => $synced->getKey(), 'include_unpublished' => true]));
    }

    /**
     * A product type with an entry page design that shows the price and the
     * page title, and a card that shows the title.
     */
    private function productWithDesigns(): void
    {
        app(EntryTypes::class)->register(EntryType::make('product')->label('Produk')->path('/produk')->body(true)->sort('title')->fields([Field::text('price')]));
        $dynamic = fn (string $id, string $key): array => ['id' => $id, 'type' => 'rich', 'data' => ['document' => [['type' => 'paragraph', 'props' => [], 'content' => [['type' => 'text', 'text' => 'Nilai: ', 'styles' => []], Blocks::dynamic($key)], 'children' => []]]]];
        $type = app(EntryTypes::class)->get('product');
        app(DocumentStore::class)->save(EntryTemplates::findOrCreate($type, Document::SINGLE), ['sections' => [$dynamic('sgl00001', 'entry.price'), $dynamic('sgl00002', 'page.title')]], null, developer: true);
        app(DocumentStore::class)->save(EntryTemplates::findOrCreate($type, Document::CARD), ['sections' => [$dynamic('card0001', 'entry.title')]], null, developer: true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function product(string $title, array $data = []): Entry
    {
        $entry = new Entry(['entry_type' => 'product']);
        $this->assertTrue(app(EntryStore::class)->save($entry, ['title' => $title, 'is_published' => true, ...$data], null)['ok']);

        return $entry->fresh();
    }

    public function test_an_entry_is_drawn_as_its_page_the_entry_page_design_then_its_own_sections(): void
    {
        $this->makePage('/', [$this->richSection('home0001', 'Isi laman utama')]);
        $this->productWithDesigns();
        $kopi = $this->product('Kopi', ['fields' => ['price' => 'RM 12'], 'sections' => [$this->richSection('body0001', 'Cerita kopi')]]);

        $html = $this->html(['id' => $kopi->getKey()]);

        $this->assertStringContainsString('data-test-page="/produk/kopi"', $html);
        $this->assertStringContainsString('Nilai: RM 12', $html);
        $this->assertStringContainsString('Nilai: Kopi', $html);
        $this->assertLessThan(strpos($html, 'Cerita kopi'), strpos($html, 'Nilai: RM 12'));
        $this->assertStringNotContainsString('Isi laman utama', $html);
        $this->assertSame($html, $this->html(['path' => '/produk/kopi']));
        $this->assertSame($this->get('/produk/kopi')->getContent(), $html);
    }

    public function test_the_card_and_entry_page_designs_are_drawn_with_the_first_entrys_values(): void
    {
        $this->makePage('/', [], title: 'Utama');
        $this->productWithDesigns();
        $this->product('Teh', ['fields' => ['price' => 'RM 5']]);
        $this->product('Air', ['fields' => ['price' => 'RM 1'], 'is_published' => false]);
        $card = EntryTemplates::find('product', Document::CARD);
        $single = EntryTemplates::find('product', Document::SINGLE);

        $this->assertRefused($this->runTool(RenderDocument::class, ['id' => $card->getKey()]), 'include_unpublished');
        $this->assertStringContainsString('Nilai: Teh', $this->html(['id' => $card->getKey(), 'include_unpublished' => true]));

        $html = $this->html(['id' => $single->getKey(), 'include_unpublished' => true]);
        $this->assertStringContainsString('Nilai: RM 5', $html);
        $this->assertStringContainsString('Nilai: Teh', $html);
    }

    public function test_a_long_page_is_cut_with_a_note(): void
    {
        $long = str_repeat('Sambal ikan bilis yang pedas. ', 2000);
        $page = $this->makePage('/', array_map(fn (int $n): array => $this->richSection("s{$n}", $long), range(1, 6)));

        $html = $this->html(['id' => $page->getKey()]);

        $this->assertLessThan(201_000, strlen($html));
        $this->assertStringContainsString('cut', strtok($html, "\n"));
        $this->assertTrue(mb_check_encoding($html, 'UTF-8'));
    }

    public function test_the_kit_and_unknown_documents_are_not_drawn(): void
    {
        $kit = Document::query()->create(['kind' => Document::KIT, 'title' => 'Kit', 'content' => []]);

        $this->assertRefused($this->runTool(RenderDocument::class, ['id' => $kit->getKey()]), 'id');
        $this->assertRefused($this->runTool(RenderDocument::class, ['id' => 999]), 'id');
    }
}
