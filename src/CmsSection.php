<?php

namespace Vortechron\FilamentCms;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;

/**
 * One part of a page the client edits, like "Pakej" or "Bahagian atas".
 *
 * The section decides the shape: which fields and lists it has, and the
 * text it shows before the client saves anything. The client project's own
 * Blade view decides the look.
 */
abstract class CmsSection
{
    final public function __construct(public readonly string $key) {}

    public static function make(string $key): static
    {
        return new static($key);
    }

    /**
     * The heading the client sees in the panel.
     */
    abstract public function label(): string;

    /**
     * Filament form fields. Their state paths are relative to the section.
     *
     * @return array<Component|Field>
     */
    abstract public function fields(): array;

    /**
     * What the page shows until the client saves. Usually today's text.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [];
    }

    /**
     * Whether the client may switch this section off.
     */
    public function hideable(): bool
    {
        return true;
    }

    /**
     * A short line under the heading in the panel.
     */
    public function description(): ?string
    {
        return null;
    }
}
