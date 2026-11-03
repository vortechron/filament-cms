<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Filament\Actions\Testing\TestAction;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\Entries\EntryStore;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Filament\Resources\Entries\EntryResource;
use Vortechron\FilamentCms\Filament\Resources\Entries\Pages\ManageEntries;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\ManageSitePages;
use Vortechron\FilamentCms\FilamentCmsPlugin;
use Vortechron\FilamentCms\Models\Entry;
use Vortechron\FilamentCms\Tests\TestCase;

class EntriesScreenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        app(EntryTypes::class)
            ->register(EntryType::make('product')->label('Produk')->pluralLabel('Senarai produk')->path('/produk')->body(true)->fields([
                Field::text('price')->label('Harga'),
                Field::textarea('summary')->label('Ringkasan'),
                Field::select('size')->label('Saiz')->options(['s' => 'Kecil', 'l' => 'Besar']),
                Field::toggle('featured')->label('Pilihan'),
                Field::number('stock')->label('Stok')->min(0)->max(500),
                Field::link('shop')->label('Pautan kedai'),
                Field::image('photo')->label('Gambar'),
                Field::list('notes')->label('Nota')->fields([Field::text('line')->label('Baris')])->maxItems(3),
            ]))
            ->register(EntryType::make('faq')->label('Soalan')->sort('manual')->fields([Field::textarea('answer')->label('Jawapan')]));
    }

    private function entry(string $type, string $title, array $data = []): Entry
    {
        $entry = new Entry(['entry_type' => $type]);
        app(EntryStore::class)->save($entry, ['title' => $title, ...$data], null);

        return $entry->fresh();
    }

    public function test_each_type_has_its_own_item_under_kandungan(): void
    {
        $this->actingAsClient();

        $items = collect(EntryResource::getNavigationItems());

        $this->assertSame(['Senarai produk', 'Soalan'], $items->map(fn (NavigationItem $item): string => $item->getLabel())->all());
        $this->assertSame(['Kandungan'], $items->map(fn (NavigationItem $item): ?string => $item->getGroup())->unique()->values()->all());
        $this->assertSame(EntryResource::getUrl('index', ['type' => 'faq']), $items[1]->getUrl());
        $this->get(EntryResource::getUrl('index', ['type' => 'product']))->assertOk()->assertSee(['Senarai produk', 'Soalan']);
    }

    public function test_the_screen_of_an_unknown_type_is_not_found(): void
    {
        $this->actingAsClient();

        $this->get(EntryResource::getUrl('index', ['type' => 'ghost']))->assertNotFound();
    }

    public function test_the_list_shows_the_entries_of_its_type_only(): void
    {
        $kopi = $this->entry('product', 'Kopi', ['is_published' => true]);
        $teh = $this->entry('product', 'Teh');
        $faq = $this->entry('faq', 'Bila buka?');
        $this->actingAsClient();

        Livewire::test(ManageEntries::class, ['type' => 'product'])
            ->assertCanSeeTableRecords([$kopi, $teh])
            ->assertCanNotSeeTableRecords([$faq])
            ->assertSee(['Tajuk', 'Diterbitkan', 'Dikemas kini']);
    }

    public function test_an_entry_is_created_with_every_kind_of_field(): void
    {
        $this->actingAsClient();

        Livewire::test(ManageEntries::class, ['type' => 'product'])
            ->callAction(TestAction::make('create'), [
                'title' => 'Kopi Kampung',
                'is_published' => true,
                'fields' => [
                    'price' => 'RM 12',
                    'summary' => "Kopi pekat.\nDari Johor.",
                    'size' => 'l',
                    'featured' => true,
                    'stock' => 20,
                    'shop' => 'https://kedai.test/kopi',
                    'photo' => UploadedFile::fake()->image('kopi.jpg'),
                    'notes' => [['line' => 'Satu'], ['line' => 'Dua']],
                ],
            ])
            ->assertHasNoActionErrors();

        $entry = Entry::query()->sole();
        $fields = $entry->fieldValues();

        $this->assertSame('product', $entry->entry_type);
        $this->assertSame('/produk/kopi-kampung', $entry->path);
        $this->assertTrue($entry->is_published);
        $this->assertSame('RM 12', $fields['price']);
        $this->assertSame("Kopi pekat.\nDari Johor.", $fields['summary']);
        $this->assertSame('l', $fields['size']);
        $this->assertTrue($fields['featured']);
        $this->assertSame(20, $fields['stock']);
        $this->assertSame('https://kedai.test/kopi', $fields['shop']);
        $this->assertSame([['line' => 'Satu'], ['line' => 'Dua']], $fields['notes']);
        $this->assertMatchesRegularExpression('#^/storage/cms/[A-Za-z0-9]+\.jpg$#', $fields['photo']);
        Storage::disk('public')->assertExists(substr($fields['photo'], strlen('/storage/')));
    }

    public function test_a_link_that_is_not_http_is_refused(): void
    {
        $this->actingAsClient();

        Livewire::test(ManageEntries::class, ['type' => 'product'])
            ->callAction(TestAction::make('create'), ['title' => 'Kopi', 'fields' => ['shop' => 'javascript:alert(1)']])
            ->assertHasActionErrors(['fields.shop']);

        $this->assertSame(0, Entry::query()->count());
    }

    public function test_an_entry_is_edited_with_its_stored_values(): void
    {
        Storage::disk('public')->put('cms/kopi.jpg', 'jpg');
        $entry = $this->entry('product', 'Kopi', ['fields' => ['price' => 'RM 12', 'photo' => '/storage/cms/kopi.jpg']]);
        $this->actingAsClient();

        Livewire::test(ManageEntries::class, ['type' => 'product'])
            ->mountAction(TestAction::make('edit')->table($entry))
            ->assertActionDataSet(['title' => 'Kopi', 'slug' => 'kopi', 'fields.price' => 'RM 12'])
            ->fillForm(['title' => 'Kopi Pekat', 'fields' => ['price' => 'RM 15']])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $entry = $entry->fresh();
        $this->assertSame('Kopi Pekat', $entry->title);
        $this->assertSame('/produk/kopi', $entry->path);
        $this->assertSame('RM 15', $entry->fieldValues()['price']);
        $this->assertSame('/storage/cms/kopi.jpg', $entry->fieldValues()['photo']);
    }

    public function test_a_picture_path_that_was_not_uploaded_here_is_not_saved(): void
    {
        Storage::disk('public')->put('private/secret.jpg', 'jpg');
        $entry = $this->entry('product', 'Kopi');
        $this->actingAsClient();

        Livewire::test(ManageEntries::class, ['type' => 'product'])
            ->mountAction(TestAction::make('edit')->table($entry))
            ->set('mountedActions.0.data.fields.photo', ['x' => 'private/secret.jpg'])
            ->callMountedAction();

        $this->assertNull($entry->fresh()->fieldValues()['photo']);
    }

    public function test_entries_are_published_unpublished_and_deleted(): void
    {
        $entry = $this->entry('product', 'Kopi');
        $this->actingAsClient();

        $screen = Livewire::test(ManageEntries::class, ['type' => 'product'])
            ->assertActionHidden(TestAction::make('unpublish')->table($entry))
            ->callAction(TestAction::make('publish')->table($entry));
        $this->assertTrue($entry->fresh()->is_published);

        $screen->callAction(TestAction::make('unpublish')->table($entry));
        $this->assertFalse($entry->fresh()->is_published);

        $screen->callAction(TestAction::make('delete')->table($entry));
        $this->assertSame(0, Entry::query()->count());
    }

    public function test_a_manual_type_can_be_reordered(): void
    {
        $one = $this->entry('faq', 'Satu');
        $two = $this->entry('faq', 'Dua');
        $this->actingAsClient();

        Livewire::test(ManageEntries::class, ['type' => 'faq'])
            ->call('reorderTable', [(string) $two->getKey(), (string) $one->getKey()]);

        $this->assertLessThan($one->fresh()->sort, $two->fresh()->sort);
        $this->assertFalse(Livewire::test(ManageEntries::class, ['type' => 'product'])->instance()->getTable()->isReorderable());
    }

    public function test_bina_opens_the_body_only_for_a_type_with_a_body(): void
    {
        $product = $this->entry('product', 'Kopi');
        $faq = $this->entry('faq', 'Bila buka?');
        $this->actingAsClient();

        Livewire::test(ManageEntries::class, ['type' => 'product'])
            ->assertActionHasUrl(TestAction::make('build')->table($product), url('/admin/site-pages/'.$product->getKey().'/build'));
        Livewire::test(ManageEntries::class, ['type' => 'faq'])
            ->assertActionHidden(TestAction::make('build')->table($faq));
    }

    public function test_someone_the_policy_refuses_cannot_open_or_change_entries(): void
    {
        $entry = $this->entry('product', 'Kopi');
        Gate::policy(Entry::class, DenyEntriesPolicy::class);
        $this->actingAsClient();

        $this->get(EntryResource::getUrl('index', ['type' => 'product']))->assertForbidden();
        $this->assertFalse(EntryResource::canAccess());
        Livewire::test(ManageEntries::class, ['type' => 'product'])->assertForbidden();
        $this->assertSame('Kopi', $entry->fresh()->title);
    }

    public function test_someone_who_may_not_edit_does_not_get_the_edit_actions(): void
    {
        $entry = $this->entry('product', 'Kopi');
        Gate::policy(Entry::class, ReadOnlyEntriesPolicy::class);
        $this->actingAsClient();

        Livewire::test(ManageEntries::class, ['type' => 'product'])
            ->assertActionHidden(TestAction::make('edit')->table($entry))
            ->assertActionHidden(TestAction::make('publish')->table($entry))
            ->assertActionHidden(TestAction::make('delete')->table($entry))
            ->assertActionHidden(TestAction::make('create'));
    }

    public function test_a_page_cannot_take_the_address_of_an_entry(): void
    {
        $this->entry('product', 'Kopi');
        $this->actingAsClient();

        Livewire::test(ManageSitePages::class)
            ->callAction(TestAction::make('create'), ['title' => 'Kopi', 'path' => '/produk/kopi'])
            ->assertHasActionErrors(['path']);
    }

    public function test_the_screens_can_be_switched_off(): void
    {
        $on = Panel::make()->id('on');
        FilamentCmsPlugin::make()->register($on);
        $off = Panel::make()->id('off');
        FilamentCmsPlugin::make()->entries(false)->register($off);

        $this->assertContains(EntryResource::class, $on->getResources());
        $this->assertNotContains(EntryResource::class, $off->getResources());
    }
}

class DenyEntriesPolicy
{
    public function viewAny(): bool
    {
        return false;
    }
}

class ReadOnlyEntriesPolicy
{
    public function viewAny(): bool
    {
        return true;
    }

    public function create(): bool
    {
        return false;
    }

    public function update(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }
}
