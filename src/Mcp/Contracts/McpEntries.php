<?php

namespace Vortechron\FilamentCms\Mcp\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Entries (documents of kind "entry", one entry type each) as the MCP
 * server reads and writes them. The CMS binds Entries\RegisteredEntries
 * while entry types are registered (it saves through EntryStore: fields
 * cleaned by the type's fields, sections through DocumentStore as a
 * developer, a version kept), and Entries\NoEntries when there are none.
 */
interface McpEntries
{
    /**
     * Whether this site has entry types. When false, the entry tools say
     * that entries are not set up.
     */
    public function available(): bool;

    /**
     * The registered entry types:
     * `[['key' => 'product', 'label' => 'Produk', 'path' => '/produk', 'body' => true, 'fields' => [Field::toArray(), ...]], ...]`.
     *
     * @return list<array<string, mixed>>
     */
    public function types(): array;

    /**
     * Entries, newest or in the type's order: each
     * `['id', 'type', 'title', 'path', 'url', 'published', 'sort', 'fields' => [...], 'sections' => [...]]`.
     *
     * @return list<array<string, mixed>>
     */
    public function list(?string $type, int $limit): array;

    /**
     * Makes (no `id`) or changes an entry. `$entry` has been checked for its
     * shape: `id` ?int, `type` string (needed without an id), `title`
     * string, `slug` ?string, `published` ?bool, `sort` ?int, `fields`
     * array by field name, `sections` a list of page sections. Keys left
     * out keep their stored value.
     *
     * @param  array<string, mixed>  $entry
     * @return array{ok: bool, errors?: array<string, string>, id?: int, url?: string|null}
     */
    public function save(array $entry, ?Authenticatable $user): array;

    /**
     * @return array{ok: bool, errors?: array<string, string>}
     */
    public function delete(int $id): array;
}
