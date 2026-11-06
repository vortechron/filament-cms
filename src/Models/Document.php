<?php

namespace Vortechron\FilamentCms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Vortechron\FilamentBlockEditor\Content\PageValue;
use Vortechron\FilamentCms\Documents\CmsKind;
use Vortechron\FilamentCms\DocumentTranslations;
use Vortechron\FilamentCms\EntryTranslations;
use Vortechron\FilamentCms\SiteLanguages;

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
    /*
     * The stored kinds, as strings for queries. What each one is lives in
     * Documents\CmsKind.
     */
    public const PAGE = CmsKind::Page->value;

    public const HEADER = CmsKind::Header->value;

    public const FOOTER = CmsKind::Footer->value;

    public const SECTION = CmsKind::Section->value;

    public const TEMPLATE = CmsKind::Template->value;

    public const KIT = CmsKind::Kit->value;

    public const CARD = CmsKind::Card->value;

    public const SINGLE = CmsKind::Single->value;

    public const ENTRY = CmsKind::Entry->value;

    protected $table = 'cms_documents';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['content' => 'array', 'settings' => 'array', 'is_published' => 'boolean', 'sort' => 'integer'];
    }

    public function language(): string
    {
        return $this->locale ?: SiteLanguages::default();
    }

    public function translationRootId(): int
    {
        return (int) ($this->translation_of ?: $this->getKey());
    }

    /** Default-language parts predate publishing; translated parts start as drafts. */
    public function isPublicPart(): bool
    {
        return $this->cmsKind()?->isPart() === true && ($this->language() === SiteLanguages::default() || $this->is_published);
    }

    public function translations(): Collection
    {
        $root = $this->translationRootId();

        return self::query()->where(fn (Builder $q) => $q->whereKey($root)->orWhere('translation_of', $root))->get();
    }

    public function translation(string $locale): ?self
    {
        return $this->translations()->first(fn (self $document): bool => $document->language() === $locale);
    }

    public function translatedTitle(?string $locale = null): string
    {
        $locale ??= SiteLanguages::current();
        $title = $this->settings['translations'][$locale]['title'] ?? null;

        return $locale !== SiteLanguages::default() && is_string($title) && trim($title) !== '' ? $title : (string) $this->title;
    }

    public function translatedFieldValues(?string $locale = null): array
    {
        if (! $this instanceof Entry) {
            return [];
        }
        $locale ??= SiteLanguages::current();
        $fields = $this->fieldValues();
        if ($locale === SiteLanguages::default()) {
            return $fields;
        }
        $allowed = array_map(fn ($field): string => $field->name, EntryTranslations::fields($this));
        $text = $this->settings['translations'][$locale]['fields'] ?? [];

        return array_replace($fields, array_filter(array_intersect_key(is_array($text) ? $text : [], array_flip($allowed)), fn ($value): bool => is_string($value) && $value !== ''));
    }

    public function scopeInLanguage(Builder $query, ?string $locale = null): void
    {
        if (! SiteLanguages::enabled()) {
            $query->whereNull('translation_of');

            return;
        }

        $locale ??= SiteLanguages::current();
        $query->where(function (Builder $query) use ($locale): void {
            $query->where('locale', $locale);
            if ($locale === SiteLanguages::default()) {
                $query->orWhereNull('locale');
            }
        });
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
     * Whether another document already has this address. Pages and entries
     * share the one unique `path` column, so any kind counts.
     */
    public static function pathTaken(string $path, ?self $except = null): bool
    {
        return static::query()
            ->withoutGlobalScopes()
            ->where('path', self::cleanPath($path))
            ->when($except?->exists, fn (Builder $query) => $query->whereKeyNot($except->getKey()))
            ->exists();
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
     * The one document of a kind the site has one of (the header, the
     * footer, the kit), or null when it was never made.
     */
    public static function firstOfKind(string $kind): ?self
    {
        return static::query()->ofKind($kind)->when($kind !== self::KIT, fn (Builder $q) => $q->inLanguage())->oldest('id')->first();
    }

    /**
     * The header or the footer, made empty on first use.
     */
    public static function findOrCreatePart(string $kind): self
    {
        if (SiteLanguages::enabled() && SiteLanguages::current() !== SiteLanguages::default() && self::firstOfKind($kind) === null) {
            $root = SiteLanguages::run(SiteLanguages::default(), fn (): self => self::findOrCreatePart($kind));

            return app(DocumentTranslations::class)->create($root, SiteLanguages::current());
        }

        return self::firstOfKind($kind) ?? static::query()->create([
            'kind' => $kind,
            'title' => CmsKind::from($kind)->label(),
            'content' => ['version' => PageValue::VERSION, 'sections' => []],
        ]);
    }

    /**
     * What kind of document it is, or null for a kind this version does not know.
     */
    public function cmsKind(): ?CmsKind
    {
        return CmsKind::of($this->kind);
    }

    /**
     * The key its saved versions are kept under. Pages keep "page:{id}", the
     * key they had before documents existed.
     */
    public function revisionKey(): string
    {
        return (($this->kind ?? self::PAGE) === self::PAGE ? 'page:' : 'doc:').$this->getKey();
    }

    /**
     * Its sections, read the one way: the stored content upgraded to
     * format 2 and cleaned. A kit has none.
     *
     * @return list<array<string, mixed>>
     */
    public function contentSections(): array
    {
        return self::sectionsOf($this->cmsKind()?->holdsSections() === false ? null : $this->content);
    }

    /**
     * The sections of any stored content (a document's, a version's).
     *
     * @return list<array<string, mixed>>
     */
    public static function sectionsOf(mixed $content): array
    {
        return PageValue::normalize(is_array($content) ? $content : [])['sections'];
    }

    public function url(): ?string
    {
        return $this->path === null ? null : url($this->path);
    }
}
