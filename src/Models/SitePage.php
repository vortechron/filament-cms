<?php

namespace Vortechron\FilamentCms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Vortechron\FilamentBlockEditor\Content\PageValue;
use Vortechron\FilamentCms\SiteLanguages;

/**
 * A page the client builds in the page builder, shown at its own address:
 * a document of kind "page". The home page has the path "/".
 *
 * `sections` reads and writes `content.sections`.
 *
 * @property string $path
 * @property list<array<string, mixed>> $sections
 */
class SitePage extends Document
{
    protected $attributes = ['kind' => self::PAGE];

    protected static function booted(): void
    {
        static::addGlobalScope('kind', fn (Builder $query) => $query->where($query->qualifyColumn('kind'), self::PAGE));
        static::creating(fn (SitePage $page) => $page->kind = self::PAGE);
    }

    /**
     * @return Attribute<list<array<string, mixed>>, list<array<string, mixed>>|null>
     */
    protected function sections(): Attribute
    {
        return Attribute::make(
            get: fn (): array => PageValue::upgrade($this->content ?? [])['sections'] ?? [],
            set: fn (?array $value): array => ['content' => json_encode(['version' => PageValue::VERSION, 'sections' => array_values($value ?? [])])],
        );
    }

    /**
     * A page always has an address: "/" when none is given.
     *
     * @return Attribute<string, string|null>
     */
    protected function path(): Attribute
    {
        return Attribute::make(set: fn (?string $value): string => self::cleanPath($value));
    }

    /**
     * The home page (path "/"), or null before it is made.
     */
    public static function home(): ?self
    {
        return static::query()->inLanguage()->where('path', SiteLanguages::path('/', SiteLanguages::current()))->first();
    }

    public function url(): string
    {
        return url($this->path);
    }
}
