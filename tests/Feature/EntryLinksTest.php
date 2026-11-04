<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Vortechron\FilamentCms\ContentStore;
use Vortechron\FilamentCms\Entries\EntryStore;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Entries\EntryValues;
use Vortechron\FilamentCms\Entries\StoredEntryTypes;
use Vortechron\FilamentCms\Filament\Pages\EditContentTypes;
use Vortechron\FilamentCms\Filament\Resources\Entries\Pages\ManageEntries;
use Vortechron\FilamentCms\Models\Entry;
use Vortechron\FilamentCms\Tests\TestCase;

/**
 * A "link to an entry" field: an item points at its category, a type made
 * in the panel like the other.
 */
class EntryLinksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function types(string $target = 'item-category'): array
    {
        return [
            ['key' => 'item', 'label' => 'Item', 'plural_label' => 'Items', 'sort' => 'manual', 'fields' => [
                ['label' => 'Category', 'name' => 'category', 'type' => 'entry', 'target' => $target],
                ['label' => 'Price', 'name' => 'price', 'type' => 'number'],
            ]],
            ['key' => 'item-category', 'label' => 'Item category', 'plural_label' => 'Item categories', 'sort' => 'manual', 'fields' => []],
        ];
    }

    private function storeTypes(): void
    {
        [$definitions] = StoredEntryTypes::clean(self::types(), []);
        app(ContentStore::class)->save(StoredEntryTypes::KEY, ['types' => $definitions], null);
        app(EntryTypes::class)->flushStored();
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function entry(string $type, string $title, array $fields = []): Entry
    {
        $entry = new Entry(['entry_type' => $type]);
        $result = app(EntryStore::class)->save($entry, ['title' => $title, 'is_published' => true, 'fields' => $fields], null);
        $this->assertTrue($result['ok'], json_encode($result));

        return $entry->fresh();
    }

    public function test_an_item_links_to_its_category_and_shows_the_category_name(): void
    {
        $this->storeTypes();
        $pens = $this->entry('item-category', 'Pens');

        $pen = $this->entry('item', 'Blue pen', ['category' => $pens->getKey(), 'price' => '2.50']);

        $this->assertSame((string) $pens->getKey(), $pen->fieldValues()['category']);
        $this->assertSame('Pens', EntryValues::of($pen)['entry.category']);
    }

    public function test_a_link_to_an_entry_that_is_gone_or_of_another_type_is_left_empty(): void
    {
        $this->storeTypes();
        $pens = $this->entry('item-category', 'Pens');
        $other = $this->entry('item', 'Not a category');

        $this->assertSame('', $this->entry('item', 'A', ['category' => 99999])->fieldValues()['category']);
        $this->assertSame('', $this->entry('item', 'B', ['category' => $other->getKey()])->fieldValues()['category']);

        $pen = $this->entry('item', 'Blue pen', ['category' => $pens->getKey()]);
        $pens->delete();

        $this->assertNull(EntryValues::of($pen->fresh())['entry.category']);
        $this->assertArrayNotHasKey((string) $pens->getKey(), $this->categoryChoices());
    }

    public function test_a_new_category_can_be_picked_at_once(): void
    {
        $this->storeTypes();
        $this->assertSame([], $this->categoryChoices());

        $pens = $this->entry('item-category', 'Pens');

        $this->assertSame([(string) $pens->getKey() => 'Pens'], $this->categoryChoices());
    }

    public function test_the_content_types_screen_saves_a_link_field_with_the_type_it_links_to(): void
    {
        $this->actingAsDeveloper();

        Livewire::test(EditContentTypes::class)
            ->fillForm(['types' => self::types()])
            ->call('save')
            ->assertNotified('Saved. The new types are in the menu now.');

        $field = StoredEntryTypes::definitions()[0]['fields'][0];
        $this->assertSame(['category', 'entry', 'item-category'], [$field['name'], $field['type'], $field['target']]);
    }

    public function test_a_link_field_must_link_to_a_type_that_exists(): void
    {
        [, $problem] = StoredEntryTypes::clean(self::types('ghost'), []);
        $this->assertSame('The Category field of Item must link to one of the content types.', $problem);

        // The screen offers only the types it shows, so it refuses one too.
        $this->actingAsDeveloper();
        Livewire::test(EditContentTypes::class)
            ->fillForm(['types' => self::types('ghost')])
            ->call('save')
            ->assertHasFormErrors();

        $this->assertNull(app(EntryTypes::class)->get('item'));
    }

    public function test_the_items_screen_shows_and_filters_by_category(): void
    {
        $this->storeTypes();
        $pens = $this->entry('item-category', 'Pens');
        $paper = $this->entry('item-category', 'Paper');
        $pen = $this->entry('item', 'Blue pen', ['category' => $pens->getKey(), 'price' => 2.5]);
        $a4 = $this->entry('item', 'A4 paper', ['category' => $paper->getKey()]);
        $this->actingAsClient();

        Livewire::test(ManageEntries::class, ['type' => 'item'])
            ->assertCanSeeTableRecords([$pen, $a4])
            ->assertTableColumnStateSet('field_category', 'Pens', $pen)
            ->assertTableColumnStateSet('field_price', '2.5', $pen)
            ->filterTable('field_category', (string) $paper->getKey())
            ->assertCanSeeTableRecords([$a4])
            ->assertCanNotSeeTableRecords([$pen]);

        Livewire::test(ManageEntries::class, ['type' => 'item'])
            ->callAction(TestAction::make('create'), ['title' => 'Red pen', 'fields' => ['category' => (string) $pens->getKey()]])
            ->assertHasNoFormErrors();

        $red = Entry::query()->where('title', 'Red pen')->firstOrFail();
        $this->assertSame((string) $pens->getKey(), $red->fieldValues()['category']);

        // Clearing the select saves no category.
        Livewire::test(ManageEntries::class, ['type' => 'item'])
            ->callAction(TestAction::make('edit')->table($red), ['fields' => ['category' => null]])
            ->assertHasNoFormErrors();

        $this->assertSame('', $red->fresh()->fieldValues()['category']);
    }

    /**
     * The category field's choices, without the empty one.
     *
     * @return array<string, string>
     */
    private function categoryChoices(): array
    {
        $field = app(EntryTypes::class)->get('item')?->getFields()[0]->toArray();

        return array_filter(array_column($field['options'] ?? [], 'label', 'value'), fn (string $label, string $value): bool => $value !== '', ARRAY_FILTER_USE_BOTH);
    }
}
