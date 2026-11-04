<?php

namespace Vortechron\FilamentCms\Entries;

use InvalidArgumentException;
use Vortechron\FilamentBlockEditor\Blocks\BlockRegistry;

/**
 * The entry types of the site, by key. One instance serves the whole app
 * (a singleton). Register types in a service provider's boot():
 *
 *     app(EntryTypes::class)->register(EntryType::make('product')->path('/produk'));
 *
 * Types made in the panel (StoredEntryTypes) are served next to them; a
 * code type wins on the same key or path.
 *
 * While at least one type exists, the page builder offers the
 * "cms-entries" list block (EntryList).
 */
final class EntryTypes
{
    /**
     * @var array<string, EntryType>
     */
    private array $types = [];

    /**
     * The panel's types, read once a request; null until first asked.
     *
     * @var array<string, EntryType>|null
     */
    private ?array $stored = null;

    /**
     * Adds a type, or replaces the one with the same key.
     *
     * @throws InvalidArgumentException when another type already uses its path
     */
    public function register(EntryType $type): self
    {
        foreach ($this->types as $key => $other) {
            if ($key !== $type->key && $type->getPath() !== null && $other->getPath() === $type->getPath()) {
                throw new InvalidArgumentException("The [{$type->key}] and [{$key}] entry types both use the path [{$type->getPath()}].");
            }
        }

        $this->types[$type->key] = $type;
        EntryList::register(app(BlockRegistry::class), $this);

        return $this;
    }

    public function forget(string $key): self
    {
        unset($this->types[$key]);
        EntryList::register(app(BlockRegistry::class), $this);

        return $this;
    }

    public function has(?string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function get(?string $key): ?EntryType
    {
        return $key === null ? null : ($this->types[$key] ?? $this->stored()[$key] ?? null);
    }

    /**
     * The panel's types first, then the code's.
     *
     * @return array<string, EntryType>
     */
    public function all(): array
    {
        return [...$this->stored(), ...$this->types];
    }

    /**
     * Only the types registered in code.
     *
     * @return array<string, EntryType>
     */
    public function inCode(): array
    {
        return $this->types;
    }

    /**
     * Reads the panel's types again, after the Content types screen saves.
     */
    public function flushStored(): void
    {
        $this->stored = null;
        $this->stored();
    }

    /**
     * @return array<string, EntryType>
     */
    private function stored(): array
    {
        if ($this->stored === null) {
            // Set first: registering the list block below asks for all().
            $this->stored = [];
            $paths = array_filter(array_map(fn (EntryType $type): ?string => $type->getPath(), $this->types));

            $this->stored = array_filter(
                StoredEntryTypes::load(),
                fn (EntryType $type): bool => ! isset($this->types[$type->key]) && ! in_array($type->getPath(), $paths, true),
            );

            EntryList::register(app(BlockRegistry::class), $this);
        }

        return $this->stored;
    }
}
