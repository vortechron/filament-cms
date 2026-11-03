<?php

namespace Vortechron\FilamentCms\Tests\Feature\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Vortechron\FilamentCms\Mcp\Contracts\McpEntries;
use Vortechron\FilamentCms\Mcp\Tools\DeleteEntry;
use Vortechron\FilamentCms\Mcp\Tools\ListEntries;
use Vortechron\FilamentCms\Mcp\Tools\SaveEntry;
use Vortechron\FilamentCms\Mcp\Tools\SiteOverview;
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

    public function test_without_entry_types_the_entry_tools_say_entries_are_not_set_up(): void
    {
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
