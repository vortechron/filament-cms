<?php

namespace Vortechron\FilamentCms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A page the client builds in the page builder, shown at its own address.
 * The home page has the path "/".
 *
 * @property string $title
 * @property string $path
 * @property list<array<string, mixed>> $sections
 * @property bool $is_published
 */
class SitePage extends Model
{
    protected $table = 'cms_pages';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sections' => 'array', 'is_published' => 'boolean'];
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
     * @return Attribute<string, string>
     */
    protected function path(): Attribute
    {
        return Attribute::make(set: fn (?string $value): string => self::cleanPath($value));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * The key its saved versions are kept under.
     */
    public function revisionKey(): string
    {
        return 'page:'.$this->getKey();
    }

    public function url(): string
    {
        return url($this->path);
    }
}
