<?php

namespace Vortechron\FilamentCms\Mcp\Contracts;

/**
 * Entries (documents of kind "entry", one entry type each) as the MCP
 * server reads and writes them. The CMS binds NoEntries until entry types
 * exist; the entries track binds a real one.
 */
interface McpEntries
{
    /**
     * Whether this site has entry types. When false, the entry tools say
     * that entries are not set up.
     */
    public function available(): bool;

    /**
     * The registered entry types, for site_overview:
     * `[['key' => 'product', 'label' => 'Produk', 'path' => '/produk', 'body' => true, 'fields' => [Field::toArray(), ...]], ...]`.
     *
     * @return list<array<string, mixed>>
     */
    public function types(): array;
}
