<?php

namespace Vortechron\FilamentCms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Everything the client builds in the page builder, one row each: pages,
 * the header and footer, synced sections, saved sections (templates), the
 * design kit, and entries with their card and page templates.
 *
 * `content` is a page value (`['version' => 2, 'sections' => [...]]`), or
 * the kit for kind "kit". Save it through DocumentStore, never directly.
 *
 * @property int $id
 * @property string $kind
 * @property string|null $entry_type
 * @property string $title
 * @property string|null $path
 * @property array<string, mixed> $content
 * @property array<string, mixed>|null $settings
 * @property bool $is_published
 * @property int $sort
 */
class Document extends Model
{
    public const PAGE = 'page';

    public const HEADER = 'header';

    public const FOOTER = 'footer';

    /**
     * A synced section: one rich section many pages show (`type: ref`).
     */
    public const SECTION = 'section';

    /**
     * A saved section: a copy the add panel offers as a template.
     */
    public const TEMPLATE = 'template';

    public const KIT = 'kit';

    /**
     * How an entry type draws one entry in a list, and its own page.
     */
    public const CARD = 'card';

    public const SINGLE = 'single';

    public const ENTRY = 'entry';

    public const KINDS = [self::PAGE, self::HEADER, self::FOOTER, self::SECTION, self::TEMPLATE, self::KIT, self::CARD, self::SINGLE, self::ENTRY];

    /**
     * The kinds the header and footer area builds.
     */
    public const PARTS = [self::HEADER, self::FOOTER];

    protected $table = 'cms_documents';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['content' => 'array', 'settings' => 'array', 'is_published' => 'boolean', 'sort' => 'integer'];
    }

    /**
     * Every path is stored as "/" or "/lower-case-words-with-dashes", so a
     * page is found the same way however it was typed.
     */
    public static function cleanPath(?string $path): string
    {
        $parts = array_filter(array_map(
            fn (string $part): string => Str::slug($part),
            explode('/', (string) $path),
        ));

        return '/'.implode('/', $parts);
    }

    /**
     * A document without an address (a header, a synced section) keeps null.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function path(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => $value === null ? null : self::cleanPath($value));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOfKind(Builder $query, string $kind): void
    {
        $query->where($query->qualifyColumn('kind'), $kind);
    }

    /**
     * The header or the footer, or null when it was never made.
     */
    public static function part(string $kind): ?self
    {
        return static::query()->ofKind($kind)->oldest('id')->first();
    }

    /**
     * The header or the footer, made empty on first use.
     */
    public static function findOrCreatePart(string $kind): self
    {
        return self::part($kind) ?? static::query()->create([
            'kind' => $kind,
            'title' => __("filament-cms::documents.{$kind}"),
            'content' => ['version' => 2, 'sections' => []],
        ]);
    }

    /**
     * The key its saved versions are kept under. Pages keep "page:{id}", the
     * key they had before documents existed.
     */
    public function revisionKey(): string
    {
        return (($this->kind ?? self::PAGE) === self::PAGE ? 'page:' : 'doc:').$this->getKey();
    }

    public function url(): ?string
    {
        return $this->path === null ? null : url($this->path);
    }
}
