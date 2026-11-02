<?php

namespace Vortechron\FilamentCms;

/**
 * What one page holds for the public page, section by section.
 */
final class PageContent
{
    public const VISIBLE = '_visible';

    /**
     * @param  array<string, array<string, mixed>>  $saved
     */
    public function __construct(private readonly CmsPage $page, private readonly array $saved) {}

    public function section(string $key): SectionContent
    {
        $section = $this->page->section($key);
        $values = self::merge($this->page, $section, $this->saved[$key] ?? []);
        $visible = ! $section->hideable() || (bool) ($values[self::VISIBLE] ?? true);
        unset($values[self::VISIBLE]);

        return new SectionContent($values, $visible);
    }

    public function visible(string $key): bool
    {
        return $this->section($key)->visible();
    }

    /**
     * The client's saved values on top of the page's and the section's
     * defaults, key by key. A key the client saved empty stays empty.
     *
     * @param  array<string, mixed>  $saved
     * @return array<string, mixed>
     */
    public static function merge(CmsPage $page, CmsSection $section, array $saved): array
    {
        return array_replace($section->defaults(), $page->defaults()[$section->key] ?? [], $saved);
    }
}
