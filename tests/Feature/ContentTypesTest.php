<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Vortechron\FilamentBlockEditor\Blocks\BlockRegistry;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\ContentStore;
use Vortechron\FilamentCms\Entries\EntryStore;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Entries\StoredEntryTypes;
use Vortechron\FilamentCms\Filament\Pages\ContentTypes\CreateContentType;
use Vortechron\FilamentCms\Filament\Pages\ContentTypes\EditContentType;
use Vortechron\FilamentCms\Filament\Pages\ContentTypes\ListContentTypes;
use Vortechron\FilamentCms\Filament\Resources\Entries\EntryResource;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\Entry;
use Vortechron\FilamentCms\Tests\TestCase;

/**
 * Entry types made in the panel: a developer adds "Products" with its
 * fields and gets its screen, list block and pages with no code.
 */
class ContentTypesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');
        app(EntryTypes::class)->flushStored();
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private static function product(array $changes = []): array
    {
        return [
            'key' => 'product',
            'label' => 'Product',
            'plural_label' => 'Products',
            'path' => 'products',
            'sort' => 'manual',
            'icon' => 'heroicon-o-cube',
            'body' => false,
            'fields' => [
                ['label' => 'Price', 'name' => 'price', 'type' => 'number'],
                ['label' => 'Size', 'name' => 'size', 'type' => 'select', 'options' => "Small\nBig box\n\nSmall"],
                ['label' => 'Photo', 'name' => 'photo', 'type' => 'image'],
            ],
            ...$changes,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $types
     */
    private function store(array $types): void
    {
        app(ContentStore::class)->save(StoredEntryTypes::KEY, ['types' => $types], null);
        app(EntryTypes::class)->flushStored();
    }

    public function test_a_developer_adds_a_type_and_gets_its_screen_list_block_and_pages(): void
    {
        $this->actingAsDeveloper();

        $this->get(ListContentTypes::getUrl())->assertOk()->assertSee(['No content types yet.', 'New content type']);

        Livewire::test(CreateContentType::class)
            ->fillForm(self::product())
            ->call('save')
            ->assertNotified('Saved. The new types are in the menu now.')
            ->assertRedirect(ListContentTypes::getUrl());

        $type = app(EntryTypes::class)->get('product');
        $this->assertNotNull($type);
        $this->assertSame(['Products', '/products', 'manual'], [$type->getPluralLabel(), $type->getPath(), $type->getSort()]);
        $this->assertSame(['price', 'size', 'photo'], array_map(fn (Field $field): string => $field->name, $type->getFields()));
        $this->assertSame(['small' => 'Small', 'big_box' => 'Big box'], StoredEntryTypes::options("Small\nBig box\n\nSmall"));
        $this->assertTrue(app(BlockRegistry::class)->has('cms-entries'));
        $this->assertSame(1, CmsRevision::query()->where('key', StoredEntryTypes::KEY)->count());

        $this->get(EntryResource::getUrl('index', ['type' => 'product']))->assertOk()->assertSee('Products');

        $entry = new Entry(['entry_type' => 'product']);
        app(EntryStore::class)->save($entry, ['title' => 'Blue pen', 'is_published' => true, 'fields' => ['price' => 2]], null);
        $this->get('/products/blue-pen')->assertOk()->assertSee('Blue pen');
    }

    public function test_the_list_shows_each_type_and_opens_it_to_edit(): void
    {
        $this->store([self::product(), self::product(['key' => 'event', 'label' => 'Event', 'plural_label' => 'Events', 'path' => '/events', 'fields' => []])]);
        app(EntryTypes::class)->register(EntryType::make('faq')->label('FAQ')->fields([]));
        app(EntryStore::class)->save(new Entry(['entry_type' => 'product']), ['title' => 'Blue pen'], null);
        $this->actingAsDeveloper();

        Livewire::test(ListContentTypes::class)
            ->assertCanSeeTableRecords(['product', 'event', 'faq'])
            ->assertTableColumnStateSet('entries', 1, 'product')
            ->assertTableColumnStateSet('fields', 3, 'product')
            ->assertTableActionVisible('edit', 'product')
            ->assertTableActionHidden('edit', 'faq');

        $this->get(EditContentType::getUrl(['type' => 'product']))->assertOk()->assertSee(['Edit Product', 'Price', 'Big box']);
        $this->get(EditContentType::getUrl(['type' => 'ghost']))->assertNotFound();
        app(EntryTypes::class)->forget('faq');
    }

    public function test_editing_a_type_keeps_its_key_and_its_place(): void
    {
        $this->store([self::product(), self::product(['key' => 'event', 'label' => 'Event', 'path' => '/events'])]);
        $this->actingAsDeveloper();

        Livewire::test(EditContentType::class, ['type' => 'product'])
            ->assertFormSet(['key' => 'product', 'label' => 'Product'])
            ->fillForm(['key' => 'changed', 'label' => 'Stock item'])
            ->call('save')
            ->assertNotified('Saved. The new types are in the menu now.');

        $this->assertSame(['product', 'event'], array_column(StoredEntryTypes::definitions(), 'key'));
        $this->assertSame('Stock item', app(EntryTypes::class)->get('product')?->getLabel());
    }

    public function test_a_new_type_may_link_to_itself(): void
    {
        $this->actingAsDeveloper();

        Livewire::test(CreateContentType::class)
            ->fillForm(['key' => 'topic', 'label' => 'Topic', 'sort' => 'title', 'fields' => [
                ['label' => 'Parent', 'name' => 'parent', 'type' => 'entry', 'target' => 'topic'],
            ]])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Saved. The new types are in the menu now.');

        $this->assertSame('topic', StoredEntryTypes::definitions()[0]['fields'][0]['target']);
    }

    public function test_a_type_another_type_links_to_is_not_deleted(): void
    {
        $this->store([
            self::product(['fields' => [['label' => 'Maker', 'name' => 'maker', 'type' => 'entry', 'target' => 'maker']]]),
            ['key' => 'maker', 'label' => 'Maker', 'fields' => []],
        ]);
        $this->actingAsDeveloper();

        Livewire::test(ListContentTypes::class)
            ->callTableAction('delete', 'maker')
            ->assertNotified('Product links to it (its Maker field). Change or remove that field first.');

        $this->assertNotNull(app(EntryTypes::class)->get('maker'));
    }

    public function test_a_saved_type_that_no_longer_builds_is_listed_to_fix(): void
    {
        $this->store([self::product()]);
        // A code type now takes its address, as at the next boot.
        app(EntryTypes::class)->register(EntryType::make('faq')->label('FAQ')->path('/products')->fields([]));
        app(EntryTypes::class)->flushStored();
        $this->actingAsDeveloper();

        $this->assertNull(app(EntryTypes::class)->get('product'));
        Livewire::test(ListContentTypes::class)
            ->assertCanSeeTableRecords(['product', 'faq'])
            ->assertTableColumnStateSet('source', 'Needs a fix', 'product')
            ->assertTableActionVisible('edit', 'product');

        app(EntryTypes::class)->forget('faq');
    }

    public function test_a_client_can_not_open_or_use_the_create_page(): void
    {
        $this->actingAsClient();

        Livewire::test(CreateContentType::class)->assertForbidden();
        $this->store([self::product()]);
        Livewire::test(EditContentType::class, ['type' => 'product'])->assertForbidden();
        $this->assertSame(['product'], array_column(StoredEntryTypes::definitions(), 'key'));
    }

    public function test_a_type_that_still_has_entries_can_not_be_removed(): void
    {
        $this->store([self::product(), self::product(['key' => 'event', 'label' => 'Event', 'path' => '/events'])]);
        app(EntryStore::class)->save(new Entry(['entry_type' => 'product']), ['title' => 'Blue pen', 'is_published' => true], null);
        $this->actingAsDeveloper();

        Livewire::test(ListContentTypes::class)
            ->callTableAction('delete', 'product')
            ->assertNotified('Product still has 1 entries. Delete them first, or keep the type.');

        Livewire::test(EditContentType::class, ['type' => 'event'])
            ->callAction('delete')
            ->assertRedirect(ListContentTypes::getUrl());

        $this->assertNotNull(app(EntryTypes::class)->get('product'));
        $this->assertNull(app(EntryTypes::class)->get('event'));
    }

    public function test_bad_types_are_refused_with_the_reason(): void
    {
        app(EntryTypes::class)->register(EntryType::make('faq')->label('FAQ')->path('/faq')->fields([Field::textarea('answer')]));
        $this->actingAsDeveloper();

        foreach ([
            'The key faq is already a type made in code.' => self::product(['key' => 'faq']),
            'Two types use the address /faq.' => self::product(['path' => '/faq']),
        ] as $message => $type) {
            Livewire::test(CreateContentType::class)->fillForm($type)->call('save')->assertNotified($message);
        }

        $this->store([self::product()]);
        Livewire::test(CreateContentType::class)->fillForm(self::product(['path' => '/other']))->call('save')->assertNotified('Two types use the key product.');

        [, $problem] = StoredEntryTypes::clean([self::product(['path' => '/admin/things'])], []);
        $this->assertStringContainsString('cannot be the home page or start with', (string) $problem);

        $this->assertSame(['product'], array_column(StoredEntryTypes::definitions(), 'key'));
        app(EntryTypes::class)->forget('faq');
    }

    public function test_a_code_type_wins_over_a_stored_one_with_its_key(): void
    {
        $this->store([self::product(['label' => 'Stored'])]);
        app(EntryTypes::class)->register(EntryType::make('product')->label('Coded')->fields([]));

        $this->assertSame('Coded', app(EntryTypes::class)->get('product')?->getLabel());
        $this->assertCount(1, app(EntryTypes::class)->all());

        app(EntryTypes::class)->forget('product');
        $this->assertSame('Stored', app(EntryTypes::class)->get('product')?->getLabel());
    }

    public function test_types_are_read_again_when_the_database_could_not_be_read_at_first(): void
    {
        $this->store([self::product()]);
        Schema::rename('cms_contents', 'cms_contents_away');
        app(EntryTypes::class)->flushStored();

        $this->assertNull(app(EntryTypes::class)->get('product'));

        Schema::rename('cms_contents_away', 'cms_contents');

        $this->assertNotNull(app(EntryTypes::class)->get('product'));
    }

    public function test_a_client_does_not_open_the_screen_while_roles_are_off(): void
    {
        $this->actingAsClient();

        $this->get(ListContentTypes::getUrl())->assertForbidden();
        $this->get(CreateContentType::getUrl())->assertForbidden();
    }

    public function test_a_developer_opens_the_screen_while_roles_are_off(): void
    {
        $this->actingAsDeveloper();

        $this->get(ListContentTypes::getUrl())->assertOk()->assertSee('New content type');
    }
}
