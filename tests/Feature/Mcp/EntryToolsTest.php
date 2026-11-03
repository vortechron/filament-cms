<?php

namespace Vortechron\FilamentCms\Tests\Feature\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\Entries\EntryStore;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Mcp\Contracts\McpEntries;
use Vortechron\FilamentCms\Mcp\Entries\NoEntries;
use Vortechron\FilamentCms\Mcp\Tools\DeleteEntry;
use Vortechron\FilamentCms\Mcp\Tools\ListEntries;
use Vortechron\FilamentCms\Mcp\Tools\SaveEntry;
use Vortechron\FilamentCms\Mcp\Tools\SiteOverview;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\Entry;
use Workbench\App\Models\User;

class EntryToolsTest extends McpTestCase
{
    /**
     * Entries kept in memory, the way the entries track's adapter answers.
     */
    private function fakeEntries(): object
    {
        $entries = new class implements McpEntries
        {
            /** @var array<int, array<string, mixed>> */
            public array $saved = [1 => ['id' => 1, 'type' => 'product', 'title' => 'Kopi', 'path' => '/produk/kopi', 'published' => true, 'fields' => ['price' => '12']]];

            public ?Authenticatable $savedBy = null;

            public function available(): bool
            {
                return true;
            }

            public function types(): array
            {
                return [['key' => 'product', 'label' => 'Produk', 'path' => '/produk', 'body' => true, 'fields' => [['name' => 'price', 'label' => 'Harga', 'type' => 'text']]]];
            }

            public function list(?string $type, int $limit): array
            {
                return array_slice(array_values(array_filter($this->saved, fn (array $entry): bool => $type === null || $entry['type'] === $type)), 0, $limit);
            }

            public function save(array $entry, ?Authenticatable $user): array
            {
                if (($entry['type'] ?? null) !== 'product') {
                    return ['ok' => false, 'errors' => ['type' => 'There is no entry type '.json_encode($entry['type'] ?? null).'.']];
                }

                $id = $entry['id'] ?? max(array_keys($this->saved)) + 1;
                $this->saved[$id] = [...($this->saved[$id] ?? []), ...$entry, 'id' => $id];
                $this->savedBy = $user;

                return ['ok' => true, 'id' => $id, 'url' => url('/produk/'.$id)];
            }

            public function delete(int $id): array
            {
                if (! isset($this->saved[$id])) {
                    return ['ok' => false, 'errors' => ['id' => "There is no entry {$id}."]];
                }

                unset($this->saved[$id]);

                return ['ok' => true];
            }
        };

        $this->app->instance(McpEntries::class, $entries);

        return $entries;
    }

    /**
     * A real entry type, registered the way a host's service provider does.
     */
    private function registerProduct(): void
    {
        app(EntryTypes::class)
            ->register(EntryType::make('product')->label('Produk')->path('/produk')->body(true)->sort('title')->fields([
                Field::text('price')->label('Harga'),
                Field::number('stock')->min(0)->max(99),
            ]))
            ->register(EntryType::make('faq')->label('Soalan')->fields([Field::textarea('answer')]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storedEntry(string $type, string $title, array $data = []): Entry
    {
        $entry = new Entry(['entry_type' => $type]);
        $this->assertTrue(app(EntryStore::class)->save($entry, ['title' => $title, ...$data], null)['ok']);

        return $entry->fresh();
    }

    public function test_list_entries_answers_the_registered_types_and_their_stored_entries(): void
    {
        $this->registerProduct();
        $teh = $this->storedEntry('product', 'Teh', ['is_published' => true, 'fields' => ['price' => 'RM 5']]);
        $this->storedEntry('product', 'Air', ['is_published' => false]);
        $this->storedEntry('faq', 'Bila buka?', ['fields' => ['answer' => 'Setiap hari']]);

        $result = $this->runTool(ListEntries::class, ['type' => 'product']);

        $this->assertFalse($this->lastToolFailed);
        $this->assertSame(['product', 'faq'], array_column($result['types'], 'key'));
        $this->assertSame(['price', 'stock'], array_column($result['types'][0]['fields'], 'name'));
        $this->assertSame(['Air', 'Teh'], array_column($result['entries'], 'title'));
        $this->assertSame(['id' => $teh->getKey(), 'type' => 'product', 'title' => 'Teh', 'path' => '/produk/teh', 'url' => url('/produk/teh'), 'published' => true], array_intersect_key($result['entries'][1], array_flip(['id', 'type', 'title', 'path', 'url', 'published'])));
        $this->assertSame(['price' => 'RM 5', 'stock' => null], $result['entries'][1]['fields']);
        $this->assertSame(3, count($this->runTool(ListEntries::class)['entries']));
        $this->assertTrue($this->runTool(SiteOverview::class)['entries']['available']);
        $this->assertRefused($this->runTool(ListEntries::class, ['type' => 'blog']), 'type');
    }

    public function test_save_entry_makes_a_real_entry_with_its_fields_cleaned_by_the_type(): void
    {
        $this->registerProduct();
        $agent = User::factory()->create(['email' => 'ai@vortechron.com']);
        config(['filament-cms.mcp.user' => 'ai@vortechron.com']);
        $body = [$this->richSection('body0001', 'Dari ladang kami')];

        $result = $this->runTool(SaveEntry::class, ['type' => 'product', 'title' => 'Kopi Kampung', 'published' => true, 'fields' => ['price' => 'RM 12', 'stock' => 'banyak'], 'sections' => $body]);

        $this->assertSaved($result);
        $entry = Entry::query()->findOrFail($result['id']);
        $this->assertSame(url('/produk/kopi-kampung'), $result['url']);
        $this->assertSame(['product', 'Kopi Kampung', '/produk/kopi-kampung', true], [$entry->entry_type, $entry->title, $entry->path, $entry->is_published]);
        $this->assertSame(['price' => 'RM 12', 'stock' => null], $entry->fieldValues());
        $this->assertStringContainsString('Dari ladang kami', json_encode($entry->content['sections']));
        $this->assertTrue($agent->is(CmsRevision::query()->where('key', $entry->revisionKey())->sole()->user));
    }

    public function test_save_entry_changes_only_the_keys_sent_and_refuses_what_the_type_would_drop(): void
    {
        $this->registerProduct();
        $kopi = $this->storedEntry('product', 'Kopi', ['is_published' => true, 'fields' => ['price' => 'RM 12', 'stock' => 4]]);
        $faq = $this->storedEntry('faq', 'Bila buka?');

        $this->assertSaved($this->runTool(SaveEntry::class, ['id' => $kopi->getKey(), 'published' => false, 'sort' => 7]));
        $this->assertSame(['Kopi', '/produk/kopi', false, 7, ['price' => 'RM 12', 'stock' => 4]], [$kopi->fresh()->title, $kopi->fresh()->path, $kopi->fresh()->is_published, $kopi->fresh()->sort, $kopi->fresh()->fieldValues()]);

        $this->assertRefused($this->runTool(SaveEntry::class, ['id' => 999, 'title' => 'Teh']), 'id');
        $this->assertRefused($this->runTool(SaveEntry::class, ['type' => 'blog', 'title' => 'Teh']), 'type');
        $this->assertRefused($this->runTool(SaveEntry::class, ['id' => $kopi->getKey(), 'type' => 'faq']), 'type');
        $this->assertRefused($this->runTool(SaveEntry::class, ['id' => $kopi->getKey(), 'fields' => ['harga' => 'RM 9']]), 'fields');
        $this->assertRefused($this->runTool(SaveEntry::class, ['id' => $faq->getKey(), 'sections' => [$this->richSection('s1', 'Isi')]]), 'sections');
        $this->assertRefused($this->runTool(SaveEntry::class, ['id' => $kopi->getKey(), 'title' => '   ']), 'title');
        $this->assertSame(['price' => 'RM 12', 'stock' => 4], $kopi->fresh()->fieldValues());
    }

    public function test_delete_entry_deletes_a_real_entry_and_keeps_its_versions(): void
    {
        $this->registerProduct();
        $kopi = $this->storedEntry('product', 'Kopi');
        $page = $this->makePage('/');

        $this->assertSaved($this->runTool(DeleteEntry::class, ['id' => $kopi->getKey(), 'confirm' => true]));

        $this->assertNull(Entry::query()->find($kopi->getKey()));
        $this->assertSame(1, CmsRevision::query()->where('key', $kopi->revisionKey())->count());
        $this->assertRefused($this->runTool(DeleteEntry::class, ['id' => $kopi->getKey(), 'confirm' => true]), 'id');
        $this->assertRefused($this->runTool(DeleteEntry::class, ['id' => $page->getKey(), 'confirm' => true]), 'id');
        $this->assertNotNull($page->fresh());
    }

    public function test_with_no_entry_types_registered_the_tools_say_entries_are_not_set_up(): void
    {
        $this->assertRefused($this->runTool(ListEntries::class), 'entries');
        $this->assertFalse($this->runTool(SiteOverview::class)['entries']['available']);
    }

    public function test_without_entry_types_the_entry_tools_say_entries_are_not_set_up(): void
    {
        $this->app->instance(McpEntries::class, new NoEntries);

        $this->assertRefused($this->runTool(ListEntries::class), 'entries');
        $this->assertRefused($this->runTool(SaveEntry::class, ['type' => 'product', 'title' => 'Kopi']), 'entries');
        $this->assertRefused($this->runTool(DeleteEntry::class, ['id' => 1, 'confirm' => true]), 'entries');
    }

    public function test_list_entries_answers_the_entries_of_a_type(): void
    {
        $this->fakeEntries();

        $result = $this->runTool(ListEntries::class, ['type' => 'product']);

        $this->assertSame(['Kopi'], array_column($result['entries'], 'title'));
        $this->assertSame('product', $result['types'][0]['key']);
        $this->assertTrue($this->runTool(SiteOverview::class)['entries']['available']);
    }

    public function test_save_entry_sends_a_checked_entry_to_the_entries_store_as_the_mcp_user(): void
    {
        $entries = $this->fakeEntries();
        $agent = User::factory()->create(['email' => 'ai@vortechron.com']);
        config(['filament-cms.mcp.user' => 'ai@vortechron.com']);

        $result = $this->runTool(SaveEntry::class, ['type' => 'product', 'title' => 'Teh', 'published' => true, 'fields' => ['price' => '8']]);

        $this->assertSaved($result);
        $this->assertSame(2, $result['id']);
        $this->assertSame(url('/produk/2'), $result['url']);
        $this->assertSame(['price' => '8'], $entries->saved[2]['fields']);
        $this->assertTrue($agent->is($entries->savedBy));
    }

    public function test_save_entry_refuses_a_bad_shape_and_passes_on_the_store_errors(): void
    {
        $this->fakeEntries();

        $this->assertRefused($this->runTool(SaveEntry::class, ['type' => 'product', 'title' => str_repeat('x', 200)]), 'title');
        $this->assertRefused($this->runTool(SaveEntry::class, ['type' => 'product', 'title' => 'Teh', 'fields' => 'harga 8']), 'fields');
        $this->assertRefused($this->runTool(SaveEntry::class, ['title' => 'Teh']), 'type');
        $this->assertRefused($this->runTool(SaveEntry::class, ['type' => 'blog', 'title' => 'Teh']), 'type');
    }

    public function test_delete_entry_needs_confirm_true(): void
    {
        $entries = $this->fakeEntries();

        $this->assertRefused($this->runTool(DeleteEntry::class, ['id' => 1]), 'confirm');
        $this->assertArrayHasKey(1, $entries->saved);

        $this->assertSaved($this->runTool(DeleteEntry::class, ['id' => 1, 'confirm' => true]));
        $this->assertArrayNotHasKey(1, $entries->saved);
        $this->assertRefused($this->runTool(DeleteEntry::class, ['id' => 1, 'confirm' => true]), 'id');
    }
}
