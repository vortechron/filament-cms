<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Entries\EntryStore;
use Vortechron\FilamentCms\Entries\EntryTemplates;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Filament\Resources\Entries\EntryResource;
use Vortechron\FilamentCms\Filament\Resources\Entries\Pages\ManageEntries;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\BuildSitePage;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\Entry;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\TestCase;

class EntryBuildersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(EntryTypes::class)
            ->register(EntryType::make('product')->label('Produk')->path('/produk')->body(true)->sort('title')->fields([
                Field::text('price')->label('Harga'),
                Field::textarea('summary')->label('Ringkasan'),
                Field::select('size')->label('Saiz')->options(['s' => 'Kecil', 'l' => 'Besar']),
                Field::number('stock')->label('Stok'),
                Field::toggle('featured')->label('Pilihan'),
                Field::link('shop')->label('Pautan kedai'),
                Field::image('photo')->label('Gambar'),
                Field::list('notes')->fields([Field::text('line')]),
            ]))
            ->register(EntryType::make('faq')->label('Soalan')->fields([Field::textarea('answer')]));
    }

    private function entry(string $type, string $title, array $data = []): Entry
    {
        $entry = new Entry(['entry_type' => $type]);
        app(EntryStore::class)->save($entry, ['title' => $title, ...$data], null);

        return $entry->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function section(string $id, array $content): array
    {
        return ['id' => $id, 'type' => 'rich', 'data' => ['document' => [['type' => 'paragraph', 'props' => [], 'content' => $content, 'children' => []]]]];
    }

    /**
     * @return array<string, mixed>
     */
    private function builderOptions(Document $document): array
    {
        return Livewire::test(BuildSitePage::class, ['record' => $document->getKey()])->instance()->getPageBuilderOptions();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function entryValues(array $options): array
    {
        return collect($options['dynamicValues'])->filter(fn (array $value): bool => str_starts_with($value['key'], 'entry.'))->keyBy('key')->all();
    }

    public function test_design_card_makes_the_card_once_and_opens_its_builder(): void
    {
        $this->actingAsClient();

        $screen = Livewire::test(ManageEntries::class, ['type' => 'product'])->callAction('designCard');
        $card = EntryTemplates::find('product', Document::CARD);
        $screen->assertRedirect(BuildSitePage::getUrl(['record' => $card]));

        Livewire::test(ManageEntries::class, ['type' => 'product'])->callAction('designCard');
        $this->assertSame(1, Document::query()->ofKind(Document::CARD)->count());
        $this->assertSame('product', $card->entry_type);
        $this->assertSame('Kad Produk', $card->title);
    }

    public function test_design_entry_page_makes_the_single_for_a_type_with_pages_only(): void
    {
        $this->actingAsClient();

        Livewire::test(ManageEntries::class, ['type' => 'product'])->callAction('designSingle');
        $single = EntryTemplates::find('product', Document::SINGLE);
        $this->assertSame('Halaman Produk', $single->title);

        Livewire::test(ManageEntries::class, ['type' => 'faq'])
            ->assertActionHidden('designSingle')
            ->assertActionVisible('designCard');
    }

    public function test_someone_who_may_not_edit_pages_cannot_design_cards(): void
    {
        Gate::policy(SitePage::class, ReadOnlyPolicy::class);
        $this->actingAsClient();

        Livewire::test(ManageEntries::class, ['type' => 'product'])->assertActionHidden('designCard');
        $this->assertSame(0, Document::query()->ofKind(Document::CARD)->count());
    }

    public function test_the_card_builder_offers_the_type_values_with_the_first_entry_as_preview(): void
    {
        $this->entry('product', 'Teh', ['is_published' => true, 'fields' => ['price' => 'RM 5']]);
        $this->entry('product', 'Kopi', ['is_published' => true, 'fields' => ['price' => 'RM 12', 'size' => 'l', 'stock' => 7, 'shop' => 'https://kedai.test/kopi', 'summary' => 'Pekat']]);
        $this->actingAsClient();

        $options = $this->builderOptions(EntryTemplates::findOrCreate(app(EntryTypes::class)->get('product'), Document::CARD));
        $values = $this->entryValues($options);

        $this->assertSame('card', $options['kind']);
        $this->assertSame(EntryResource::getUrl('index', ['type' => 'product']), $options['backUrl']);
        $this->assertSame(['entry.title', 'entry.url', 'entry.price', 'entry.summary', 'entry.size', 'entry.stock', 'entry.shop', 'entry.photo'], array_keys($values));
        $this->assertSame(['key' => 'entry.title', 'label' => 'Tajuk', 'type' => 'text', 'group' => 'Produk', 'preview' => 'Kopi'], $values['entry.title']);
        $this->assertSame(url('/produk/kopi'), $values['entry.url']['preview']);
        $this->assertSame('link', $values['entry.url']['type']);
        $this->assertSame('RM 12', $values['entry.price']['preview']);
        $this->assertSame('Besar', $values['entry.size']['preview']);
        $this->assertSame('7', $values['entry.stock']['preview']);
        $this->assertSame(['link', 'https://kedai.test/kopi'], [$values['entry.shop']['type'], $values['entry.shop']['preview']]);
        $this->assertSame(['image', null], [$values['entry.photo']['type'], $values['entry.photo']['preview']]);
        $this->assertContains('site.phone', array_column($options['dynamicValues'], 'key'));
    }

    public function test_the_single_builder_offers_the_values_too_and_pages_do_not(): void
    {
        $this->entry('product', 'Kopi');
        $home = SitePage::query()->create(['title' => 'Utama', 'path' => '/', 'sections' => []]);
        $this->actingAsClient();

        $options = $this->builderOptions(EntryTemplates::findOrCreate(app(EntryTypes::class)->get('product'), Document::SINGLE));

        $this->assertSame('single', $options['kind']);
        $this->assertSame('Kopi', $this->entryValues($options)['entry.title']['preview']);
        $this->assertSame([], $this->entryValues($this->builderOptions($home)));
    }

    public function test_the_builder_shows_the_page_values_of_what_is_built(): void
    {
        $this->entry('product', 'Kopi', ['is_published' => true]);
        SitePage::query()->create(['title' => 'Utama', 'path' => '/', 'sections' => []]);
        $about = SitePage::query()->create(['title' => 'Tentang kami', 'path' => '/tentang', 'sections' => []]);
        $this->actingAsClient();
        $page = fn (Document $document): array => collect($this->builderOptions($document)['dynamicValues'])->keyBy('key')->only(['page.title', 'page.url'])->map(fn (array $value): ?string => $value['preview'])->all();

        $this->assertSame(['page.title' => 'Tentang kami', 'page.url' => url('/tentang')], $page($about));
        $this->assertSame(['page.title' => 'Kopi', 'page.url' => url('/produk/kopi')], $page(EntryTemplates::findOrCreate(app(EntryTypes::class)->get('product'), Document::SINGLE)));
        $this->assertSame(['page.title' => 'Utama', 'page.url' => url('/')], $page(EntryTemplates::findOrCreate(app(EntryTypes::class)->get('product'), Document::CARD)));
    }

    public function test_a_card_with_no_entries_yet_has_no_preview_values(): void
    {
        $this->actingAsClient();

        $values = $this->entryValues($this->builderOptions(EntryTemplates::findOrCreate(app(EntryTypes::class)->get('faq'), Document::CARD)));

        $this->assertSame(['entry.title', 'entry.url', 'entry.answer'], array_keys($values));
        $this->assertNull($values['entry.title']['preview']);
    }

    public function test_the_card_preview_draws_the_first_entry(): void
    {
        $this->entry('product', 'Kopi <b>Pekat</b>', ['fields' => ['price' => 'RM 12']]);
        $card = EntryTemplates::findOrCreate(app(EntryTypes::class)->get('product'), Document::CARD);
        $this->actingAsClient();

        $html = Livewire::test(BuildSitePage::class, ['record' => $card->getKey()])
            ->call('previewPageBuilder', ['sections' => [$this->section('card0001', [Blocks::dynamic('entry.title'), Blocks::dynamic('entry.price')])]])
            ->effects['returns'][0];

        $this->assertStringContainsString('data-block-id="card0001"', $html);
        $this->assertStringContainsString('Kopi &lt;b&gt;Pekat&lt;/b&gt;', $html);
        $this->assertStringContainsString('RM 12', $html);
        $this->assertStringNotContainsString('<b>Pekat</b>', $html);
    }

    public function test_bina_opens_the_entry_body_as_a_page(): void
    {
        $entry = $this->entry('product', 'Kopi', ['is_published' => true, 'fields' => ['price' => 'RM 12']]);
        $this->actingAsClient();

        $this->get(BuildSitePage::getUrl(['record' => $entry]))->assertOk()->assertSee('mountPageBuilder', false);
        $options = $this->builderOptions($entry);

        $this->assertSame('page', $options['kind']);
        $this->assertSame('Kopi', $options['title']);
        $this->assertSame(url('/produk/kopi'), $options['viewUrl']);
        $this->assertSame(EntryResource::getUrl('index', ['type' => 'product']), $options['backUrl']);
        $this->assertSame('Kopi', $this->entryValues($options)['entry.title']['preview']);
    }

    public function test_saving_the_body_keeps_the_fields(): void
    {
        $entry = $this->entry('product', 'Kopi', ['fields' => ['price' => 'RM 12']]);
        $this->actingAsClient();

        Livewire::test(BuildSitePage::class, ['record' => $entry->getKey()])
            ->call('savePageBuilder', ['version' => 2, 'sections' => [$this->section('body0001', [['type' => 'text', 'text' => 'Cerita kopi', 'styles' => []]])]])
            ->assertReturned(['ok' => true]);

        $this->assertSame('RM 12', $entry->fresh()->fieldValues()['price']);
        $this->assertSame('body0001', $entry->fresh()->content['sections'][0]['id']);
    }

    public function test_the_body_preview_draws_the_entry_page_design_then_the_body(): void
    {
        $entry = $this->entry('product', 'Kopi');
        $single = EntryTemplates::findOrCreate(app(EntryTypes::class)->get('product'), Document::SINGLE);
        app(DocumentStore::class)->save($single, ['sections' => [$this->section('sgl00001', [['type' => 'text', 'text' => 'Atas: ', 'styles' => []], Blocks::dynamic('entry.title')])]], null);
        $this->actingAsClient();

        $html = Livewire::test(BuildSitePage::class, ['record' => $entry->getKey()])
            ->call('previewPageBuilder', ['sections' => [$this->section('body0001', [['type' => 'text', 'text' => 'Cerita baru', 'styles' => []]])]])
            ->effects['returns'][0];

        $this->assertStringContainsString('Atas: Kopi', $html);
        $this->assertStringContainsString('data-block-id="body0001"', $html);
        $this->assertStringNotContainsString('data-block-id="sgl00001"', $html);
        $this->assertLessThan(strpos($html, 'Cerita baru'), strpos($html, 'Atas: Kopi'));
    }

    public function test_an_entry_of_a_type_without_a_body_or_type_is_not_built(): void
    {
        $faq = $this->entry('faq', 'Soalan');
        $ghost = Entry::query()->create(['entry_type' => 'ghost', 'title' => 'Hantu', 'content' => ['version' => 2, 'fields' => [], 'sections' => []]]);
        $this->actingAsClient();

        $this->get(BuildSitePage::getUrl(['record' => $faq]))->assertNotFound();
        $this->get(BuildSitePage::getUrl(['record' => $ghost]))->assertNotFound();
    }

    public function test_someone_the_entry_policy_refuses_cannot_build_the_body(): void
    {
        $entry = $this->entry('product', 'Kopi');
        Gate::policy(Entry::class, ReadOnlyPolicy::class);
        $this->actingAsClient();

        $this->get(BuildSitePage::getUrl(['record' => $entry]))->assertForbidden();
        Livewire::test(BuildSitePage::class, ['record' => $entry->getKey()])->assertForbidden();
    }
}
