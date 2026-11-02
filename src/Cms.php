<?php

namespace Vortechron\FilamentCms;

use InvalidArgumentException;
use Vortechron\FilamentCms\Models\CmsContent;

/**
 * Finds the pages a project registered in config/filament-cms.php and reads
 * their content for the public site.
 */
final class Cms
{
    public const SETTINGS = 'settings';

    /**
     * Every editable page, settings included, by key.
     *
     * @return array<string, class-string<CmsPage>>
     */
    public function pages(): array
    {
        $pages = [];

        foreach ([...config('filament-cms.pages', []), config('filament-cms.settings')] as $class) {
            if (filled($class)) {
                $pages[$class::key()] = $class;
            }
        }

        return $pages;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->pages());
    }

    public function definition(string $key): CmsPage
    {
        $class = $this->pages()[$key] ?? throw new InvalidArgumentException("There is no CMS page [{$key}].");

        return app($class);
    }

    public function page(string $key): PageContent
    {
        return new PageContent($this->definition($key), $this->saved($key));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function saved(string $key): array
    {
        return CmsContent::query()->where('key', $key)->value('content') ?? [];
    }
}
