<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Livewire\Livewire;
use Vortechron\FilamentBlockEditor\Blocks\BlockRegistry;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\ContentStore;
use Vortechron\FilamentCms\Entries\EntryStore;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Entries\StoredEntryTypes;
use Vortechron\FilamentCms\Filament\Pages\EditContentTypes;
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

        Livewire::test(EditContentTypes::class)
            ->fillForm(['types' => [self::product()]])
            ->call('save')
            ->assertNotified('Saved. The new types are in the menu now.');

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

    public function test_a_saved_key_can_not_change_and_the_screen_shows_the_saved_types(): void
    {
        $this->store([self::product()]);
        $this->actingAsDeveloper();

        Livewire::test(EditContentTypes::class)
            ->assertFormSet(fn (array $state): bool => ($state['types'][array_key_first($state['types'])]['saved_key'] ?? null) === 'product')
            ->assertSee('Product');
    }

    public function test_a_type_that_still_has_entries_can_not_be_removed(): void
    {
        $this->store([self::product()]);
        app(EntryStore::class)->save(new Entry(['entry_type' => 'product']), ['title' => 'Blue pen', 'is_published' => true], null);
        $this->actingAsDeveloper();

        Livewire::test(EditContentTypes::class)
            ->fillForm(['types' => []])
            ->call('save')
            ->assertNotified('Product still has 1 entries. Delete them first, or keep the type.');

        $this->assertNotNull(app(EntryTypes::class)->get('product'));
    }

    public function test_bad_types_are_refused_with_the_reason(): void
    {
        app(EntryTypes::class)->register(EntryType::make('faq')->label('FAQ')->path('/faq')->fields([Field::textarea('answer')]));
        $this->actingAsDeveloper();

        $cases = [
            'The key faq is already a type made in code.' => [self::product(['key' => 'faq'])],
            'Two types use the key product.' => [self::product(), self::product(['path' => '/other'])],
            'Two types use the address /faq.' => [self::product(['path' => '/faq'])],
        ];

        foreach ($cases as $message => $types) {
            Livewire::test(EditContentTypes::class)->fillForm(['types' => $types])->call('save')->assertNotified($message);
        }

        [, $problem] = StoredEntryTypes::clean([self::product(['path' => '/admin/things'])], []);
        $this->assertStringContainsString('cannot be the home page or start with', (string) $problem);

        $this->assertNull(app(EntryTypes::class)->get('product'));
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

    public function test_a_client_does_not_open_the_screen_while_roles_are_off(): void
    {
        $this->actingAsClient();

        $this->get(EditContentTypes::getUrl())->assertForbidden();
    }

    public function test_a_developer_opens_the_screen_while_roles_are_off(): void
    {
        $this->actingAsDeveloper();

        $this->get(EditContentTypes::getUrl())->assertOk()->assertSee('Add content type');
    }
}
