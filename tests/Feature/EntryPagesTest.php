<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentBlockEditor\Content\DynamicValues;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Entries\EntryStore;
use Vortechron\FilamentCms\Entries\EntryTemplates;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Entries\EntryValues;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\Entry;
use Vortechron\FilamentCms\Tests\TestCase;

class EntryPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(EntryTypes::class)
            ->register(EntryType::make('product')->label('Produk')->path('/produk')->body(true)->fields([
                Field::text('price')->label('Harga'),
                Field::image('photo')->label('Gambar'),
                Field::textarea('summary')->label('Ringkasan'),
                Field::link('shop')->label('Beli di Shopee'),
            ]))
            ->register(EntryType::make('faq')->fields([Field::textarea('answer')]));
    }

    private function entry(string $title, array $data = []): Entry
    {
        $entry = new Entry(['entry_type' => 'product']);
        app(EntryStore::class)->save($entry, ['title' => $title, 'is_published' => true, ...$data], null);

        return $entry->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function section(string $id, array $content): array
    {
        return ['id' => $id, 'type' => 'rich', 'data' => ['document' => [['type' => 'paragraph', 'props' => [], 'content' => $content, 'children' => []]]]];
    }

    private function text(string $text): array
    {
        return ['type' => 'text', 'text' => $text, 'styles' => []];
    }

    public function test_a_published_entry_is_shown_at_its_address_in_the_page_view(): void
    {
        $this->entry('Kopi Kampung', ['sections' => [$this->section('body0001', [$this->text('Cerita kopi kami')])]]);

        $response = $this->get('/produk/kopi-kampung')->assertOk();

        $response->assertSee('data-test-page="/produk/kopi-kampung"', false);
        $response->assertSee('<h1>Kopi Kampung</h1>', false);
        $response->assertSee('Cerita kopi kami');
        $response->assertViewHas('page', fn (Document $page): bool => $page instanceof Entry && $page->title === 'Kopi Kampung');
    }

    public function test_an_unpublished_entry_is_not_found(): void
    {
        $this->entry('Kopi', ['is_published' => false]);

        $this->get('/produk/kopi')->assertNotFound();
    }

    public function test_an_entry_whose_type_has_no_pages_any_more_is_not_found(): void
    {
        $this->entry('Kopi');

        app(EntryTypes::class)->register(EntryType::make('product')->path(null));
        $this->get('/produk/kopi')->assertNotFound();

        app(EntryTypes::class)->forget('product');
        $this->get('/produk/kopi')->assertNotFound();
    }

    public function test_the_entry_page_design_is_drawn_with_the_entrys_values_then_its_body(): void
    {
        $entry = $this->entry('Kopi <b>Pekat</b>', ['fields' => ['price' => 'RM 12'], 'sections' => [$this->section('body0001', [$this->text('Cerita kopi')])]]);
        $single = EntryTemplates::findOrCreate(app(EntryTypes::class)->get('product'), Document::SINGLE);
        app(DocumentStore::class)->save($single, ['sections' => [$this->section('sgl00001', [$this->text('Harga: '), Blocks::dynamic('entry.price'), $this->text(' untuk '), Blocks::dynamic('entry.title')])]], null);

        $main = str($this->get($entry->path)->assertOk()->getContent())->after('<main>')->before('</main>')->toString();

        $this->assertStringContainsString('Harga: RM 12 untuk Kopi &lt;b&gt;Pekat&lt;/b&gt;', $main);
        $this->assertStringNotContainsString('<b>Pekat</b>', $main);
        $this->assertStringNotContainsString('cms-entry-title', $main);
        $this->assertLessThan(strpos($main, 'Cerita kopi'), strpos($main, 'Harga: RM 12'));
    }

    public function test_without_a_design_the_fields_are_drawn_plainly_then_the_body(): void
    {
        $entry = $this->entry('Kopi <i>Kampung</i>', [
            'fields' => ['price' => 'RM <b>12</b>', 'photo' => '/storage/cms/kopi.jpg', 'summary' => "Pekat.\n<script>x</script>", 'shop' => 'https://shopee.test/kopi'],
            'sections' => [$this->section('body0001', [$this->text('Cerita kopi')])],
        ]);

        $main = str($this->get($entry->path)->assertOk()->getContent())->after('<main>')->before('</main>')->toString();

        $this->assertStringContainsString('<h1 class="cms-entry-title">Kopi &lt;i&gt;Kampung&lt;/i&gt;</h1>', $main);
        $this->assertStringContainsString('src="/storage/cms/kopi.jpg"', $main);
        $this->assertStringContainsString('RM &lt;b&gt;12&lt;/b&gt;', $main);
        $this->assertStringContainsString('Pekat.<br>'."\n".'&lt;script&gt;x&lt;/script&gt;', $main);
        $this->assertStringContainsString('<a href="https://shopee.test/kopi" rel="noopener">Beli di Shopee</a>', $main);
        $this->assertStringNotContainsString('<script>x', $main);
        $this->assertLessThan(strpos($main, 'Cerita kopi'), strpos($main, 'cms-entry-title'));
    }

    public function test_short_codes_in_text_fields_are_filled_with_the_shop_value_and_stay_escaped(): void
    {
        app(DynamicValues::class)->register('site.test_phone', 'Telefon', fn (): string => '011-1 <b>2</b>');
        $entry = $this->entry('Kopi', [
            'fields' => ['price' => 'RM12 {site.test_phone}', 'summary' => "Hubungi {site.test_phone}.\n{site.gone} {nama}"],
        ]);

        $this->assertSame('RM12 011-1 <b>2</b>', EntryValues::of($entry)['entry.price']);
        $this->assertSame("Hubungi 011-1 <b>2</b>.\n {nama}", EntryValues::of($entry)['entry.summary']);

        $main = str($this->get($entry->path)->assertOk()->getContent())->after('<main>')->before('</main>')->toString();

        $this->assertStringContainsString('Hubungi 011-1 &lt;b&gt;2&lt;/b&gt;.', $main);
        $this->assertStringNotContainsString('{site.', $main);
    }

    public function test_the_seo_title_and_description_come_from_the_title_and_first_textarea(): void
    {
        $this->entry('Kopi', ['fields' => ['summary' => "Kopi pekat\n\ndari Johor. ".str_repeat('Sedap. ', 40)]]);

        $this->get('/produk/kopi')->assertViewHas('page', function (Entry $page): bool {
            return $page->seo_title === 'Kopi'
                && str_starts_with($page->seo_description, 'Kopi pekat dari Johor. Sedap.')
                && mb_strlen($page->seo_description) <= 163;
        });
        $this->assertNull(Entry::query()->sole()->seo_title);
    }

    public function test_the_header_and_footer_are_drawn_around_an_entry_page(): void
    {
        $this->entry('Kopi');
        app(DocumentStore::class)->save(Document::findOrCreatePart(Document::HEADER), ['sections' => [$this->section('hdr00001', [$this->text('Kedai kopi')])]], null);

        $this->get('/produk/kopi')->assertOk()->assertSee('<div data-part="header" class="sf-page">', false)->assertSee('Kedai kopi');
    }

    public function test_pages_are_still_shown_first(): void
    {
        $this->entry('Kopi');

        $this->get('/produk')->assertNotFound();
        $this->get('/produk/teh')->assertNotFound();
    }

    public function test_entry_values_never_leave_their_attribute(): void
    {
        $entry = $this->entry('Kopi', ['fields' => ['photo' => '/x.jpg" onerror="alert(1)', 'shop' => 'https://shopee.test/?a="><script>alert(2)</script>']]);
        $single = EntryTemplates::findOrCreate(app(EntryTypes::class)->get('product'), Document::SINGLE);
        app(DocumentStore::class)->save($single, ['sections' => [['id' => 'sgl00001', 'type' => 'rich', 'data' => ['document' => [
            Blocks::image('@entry.photo'),
            Blocks::button('Beli', '@entry.shop'),
        ]]]]], null);

        $html = $this->get($entry->path)->assertOk()->getContent();

        $this->assertStringNotContainsString('onerror="alert(1)', $html);
        $this->assertStringNotContainsString('<script>alert(2)', $html);
        $this->assertStringContainsString('/x.jpg&quot; onerror=&quot;alert(1)', $html);

        $single->forceFill(['content' => ['version' => 2, 'sections' => []]])->save();
        $plain = $this->get($entry->path)->getContent();
        $this->assertStringNotContainsString('onerror="alert(1)', $plain);
        $this->assertStringNotContainsString('<script>alert(2)', $plain);
    }
}
