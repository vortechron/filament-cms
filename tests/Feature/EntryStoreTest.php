<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Entries\EntryStore;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\Entry;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\TestCase;

class EntryStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(EntryTypes::class)
            ->register(EntryType::make('product')->path('/produk')->body(true)->fields([
                Field::text('price'),
                Field::number('stock')->min(0)->max(99),
                Field::select('size')->options(['s' => 'Kecil', 'l' => 'Besar']),
                Field::image('photo'),
                Field::link('shop'),
                Field::toggle('featured'),
                Field::list('notes')->fields([Field::text('line')])->maxItems(2),
            ]))
            ->register(EntryType::make('faq')->fields([Field::textarea('answer')]));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, errors?: array<string, string>}
     */
    private function save(Entry $entry, array $data): array
    {
        return app(EntryStore::class)->save($entry, $data, null);
    }

    private function product(string $title, array $data = []): Entry
    {
        $entry = new Entry(['entry_type' => 'product']);
        $this->assertTrue($this->save($entry, ['title' => $title, ...$data])['ok']);

        return $entry->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function section(string $id, string $text): array
    {
        return ['id' => $id, 'type' => 'rich', 'data' => ['document' => [Blocks::paragraph($text)]]];
    }

    public function test_an_entry_is_a_document_of_kind_entry_with_cleaned_fields(): void
    {
        $entry = $this->product('Kopi Kampung', ['is_published' => true, 'fields' => [
            'price' => 'RM 12',
            'stock' => '150',
            'size' => 'xl',
            'photo' => 'javascript:alert(1)',
            'shop' => 'https://kedai.test/kopi',
            'featured' => 'yes',
            'notes' => [['line' => 'Satu'], ['line' => ['bad']], ['line' => 'Tiga']],
            'secret' => 'not a field',
        ]]);

        $this->assertSame(Document::ENTRY, $entry->kind);
        $this->assertSame('product', $entry->entry_type);
        $this->assertSame('Kopi Kampung', $entry->title);
        $this->assertTrue($entry->is_published);
        $this->assertSame(['version' => 2, 'fields' => [
            'price' => 'RM 12',
            'stock' => 99,
            'size' => 's',
            'photo' => null,
            'shop' => 'https://kedai.test/kopi',
            'featured' => true,
            'notes' => [['line' => 'Satu'], ['line' => '']],
        ], 'sections' => []], $entry->content);
    }

    public function test_the_path_is_the_type_path_and_a_slug_of_the_title(): void
    {
        $this->assertSame('/produk/kopi-kampung', $this->product('Kopi Kampung')->path);
        $this->assertSame('/produk/kopi-kampung-2', $this->product('Kopi kampung!')->path);
        $this->assertSame('/produk/kopi-kampung-3', $this->product('Kopi Kampung')->path);
    }

    public function test_a_slug_never_takes_the_address_of_a_page(): void
    {
        SitePage::query()->create(['title' => 'Kopi', 'path' => '/produk/kopi', 'sections' => []]);

        $this->assertSame('/produk/kopi-2', $this->product('Kopi')->path);
    }

    public function test_a_title_with_no_letters_gets_the_type_key_as_slug(): void
    {
        $this->assertSame('/produk/product', $this->product('!!!')->path);
    }

    public function test_the_slug_stays_when_the_title_changes_and_moves_when_edited(): void
    {
        $entry = $this->product('Kopi');

        $this->save($entry, ['title' => 'Kopi Pekat']);
        $this->assertSame('/produk/kopi', $entry->fresh()->path);
        $this->assertSame('Kopi Pekat', $entry->fresh()->title);

        $this->save($entry, ['title' => 'Kopi Pekat', 'slug' => 'Kopi Pekat Asli']);
        $this->assertSame('/produk/kopi-pekat-asli', $entry->fresh()->path);

        $this->save($entry, ['title' => 'Kopi Pekat', 'slug' => '']);
        $this->assertSame('/produk/kopi-pekat', $entry->fresh()->path);
    }

    public function test_the_next_save_follows_a_new_type_path(): void
    {
        $entry = $this->product('Kopi');
        app(EntryTypes::class)->register(EntryType::make('product')->path('/barang')->body(true));

        $this->save($entry, ['title' => 'Kopi']);

        $this->assertSame('/barang/kopi', $entry->fresh()->path);
    }

    public function test_a_type_without_a_path_stores_none(): void
    {
        $entry = new Entry(['entry_type' => 'faq']);
        $this->save($entry, ['title' => 'Bila buka?', 'fields' => ['answer' => 'Setiap hari']]);

        $this->assertNull($entry->fresh()->path);
        $this->assertSame(['answer' => 'Setiap hari'], $entry->fresh()->content['fields']);
    }

    public function test_sections_are_kept_only_for_a_type_with_a_body(): void
    {
        $product = $this->product('Kopi', ['sections' => [$this->section('body0001', 'Cerita kopi')]]);
        $faq = new Entry(['entry_type' => 'faq']);
        $this->save($faq, ['title' => 'Soalan', 'sections' => [$this->section('body0001', 'Tidak disimpan')]]);

        $this->assertSame('body0001', $product->content['sections'][0]['id']);
        $this->assertSame([], $faq->fresh()->content['sections']);
    }

    public function test_a_builder_save_keeps_the_stored_fields_and_a_form_save_keeps_the_sections(): void
    {
        $entry = $this->product('Kopi', ['fields' => ['price' => 'RM 12'], 'sections' => [$this->section('body0001', 'Cerita')]]);

        app(DocumentStore::class)->save($entry, ['version' => 2, 'sections' => [$this->section('body0002', 'Cerita baru')]], null);
        $this->assertSame('RM 12', $entry->fresh()->content['fields']['price']);
        $this->assertSame('body0002', $entry->fresh()->content['sections'][0]['id']);

        $this->save($entry->fresh(), ['title' => 'Kopi', 'fields' => ['price' => 'RM 15']]);
        $this->assertSame('RM 15', $entry->fresh()->content['fields']['price']);
        $this->assertSame('body0002', $entry->fresh()->content['sections'][0]['id']);
    }

    public function test_every_save_keeps_a_version(): void
    {
        $entry = $this->product('Kopi', ['fields' => ['price' => 'RM 12']]);
        $this->save($entry, ['title' => 'Kopi', 'fields' => ['price' => 'RM 13']]);

        $this->assertSame('doc:'.$entry->getKey(), $entry->revisionKey());
        $this->assertSame(2, CmsRevision::query()->where('key', $entry->revisionKey())->count());
    }

    public function test_a_title_is_needed(): void
    {
        $entry = new Entry(['entry_type' => 'product']);

        $result = $this->save($entry, ['title' => '  ']);

        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('title', $result['errors']);
        $this->assertFalse($entry->exists);
    }

    public function test_an_unknown_type_is_refused(): void
    {
        $entry = new Entry(['entry_type' => 'ghost']);

        $result = $this->save($entry, ['title' => 'Hantu']);

        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('entry_type', $result['errors']);
        $this->assertArrayHasKey('entry_type', app(DocumentStore::class)->save(new Entry(['entry_type' => 'ghost', 'title' => 'Hantu']), ['fields' => []], null)['errors']);
        $this->assertSame(0, Document::query()->count());
    }

    public function test_fields_must_be_a_list_of_values(): void
    {
        $result = $this->save(new Entry(['entry_type' => 'product']), ['title' => 'Kopi', 'fields' => 'price']);

        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('fields', $result['errors']);
    }

    public function test_a_new_entry_of_a_manual_type_goes_last(): void
    {
        app(EntryTypes::class)->register(EntryType::make('product')->path('/produk')->sort('manual'));

        $first = $this->product('Satu');
        $second = $this->product('Dua');

        $this->assertGreaterThan($first->sort, $second->sort);
    }

    public function test_entries_are_not_site_pages(): void
    {
        $this->product('Kopi');

        $this->assertSame(0, SitePage::query()->count());
        $this->assertSame(1, Entry::query()->count());
    }
}
