<?php

namespace Vortechron\FilamentCms;

use ArrayAccess;
use LogicException;

/**
 * What one section holds for the public page: the client's saved values on
 * top of the section's defaults. Read it like an array or an object:
 * $section->title, $section['packages'], $section->get('cta.label').
 *
 * @implements ArrayAccess<string, mixed>
 */
final class SectionContent implements ArrayAccess
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(private readonly array $values, private readonly bool $visible = true) {}

    public function visible(): bool
    {
        return $this->visible;
    }

    public function get(string $path, mixed $default = null): mixed
    {
        return data_get($this->values, $path, $default);
    }

    /**
     * The public address of a photo field, or null when it is empty.
     */
    public function image(string $path): ?string
    {
        return cms_image($this->get($path));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    public function __isset(string $name): bool
    {
        return isset($this->values[$name]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->values[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Section content is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Section content is read-only.');
    }
}
