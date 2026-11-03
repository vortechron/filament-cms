<?php

namespace Vortechron\FilamentCms\Models;

use Illuminate\Database\Eloquent\Builder;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;

/**
 * One entry of an entry type (a product, an event...): a document of kind
 * "entry". Its `content` is `['version' => 2, 'fields' => [...], 'sections' => [...]]`.
 * Save it through EntryStore, never directly.
 *
 * Write an EntryPolicy for this model to decide who may manage entries.
 */
class Entry extends Document
{
    protected $attributes = ['kind' => self::ENTRY];

    protected static function booted(): void
    {
        static::addGlobalScope('kind', fn (Builder $query) => $query->where($query->qualifyColumn('kind'), self::ENTRY));
        static::creating(fn (Entry $entry) => $entry->kind = self::ENTRY);
    }

    /**
     * Its registered type, or null when the type is not registered (any more).
     */
    public function definition(): ?EntryType
    {
        return app(EntryTypes::class)->get($this->entry_type);
    }

    /**
     * The stored, cleaned field values.
     *
     * @return array<string, mixed>
     */
    public function fieldValues(): array
    {
        $fields = is_array($this->content) ? ($this->content['fields'] ?? null) : null;

        return is_array($fields) ? $fields : [];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where($query->qualifyColumn('entry_type'), $type);
    }

    /**
     * Newest first, by title, or in the client's own order (manual).
     *
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query, string $sort): void
    {
        match ($sort) {
            'title' => $query->orderBy('title')->orderBy('id'),
            'manual' => $query->orderBy('sort')->orderBy('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }
}
