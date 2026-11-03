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
 * While at least one type is registered, the page builder offers the
 * "cms-entries" list block (EntryList).
 */
final class EntryTypes
{
    /**
     * @var array<string, EntryType>
     */
    private array $types = [];

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
        return $key !== null && isset($this->types[$key]);
    }

    public function get(?string $key): ?EntryType
    {
        return $key === null ? null : ($this->types[$key] ?? null);
    }

    /**
     * @return array<string, EntryType>
     */
    public function all(): array
    {
        return $this->types;
    }
}
