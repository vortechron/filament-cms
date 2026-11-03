<?php

namespace Vortechron\FilamentCms;

use Closure;
use InvalidArgumentException;
use Vortechron\FilamentCms\Events\SettingsOverridden;
use Vortechron\FilamentCms\Models\CmsContent;

/**
 * Finds the pages a project registered in config/filament-cms.php and reads
 * their content for the public site.
 *
 * withSettings() draws with other settings for a moment (the builder's
 * preview of unsaved shop info): every reader of saved(Cms::SETTINGS),
 * cms_settings() and the site.* values included, sees them until it ends.
 */
final class Cms
{
    public const SETTINGS = 'settings';

    /**
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $settingsOverride = null;

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
        if ($key === self::SETTINGS && $this->settingsOverride !== null) {
            return $this->settingsOverride;
        }

        return CmsContent::query()->where('key', $key)->value('content') ?? [];
    }

    /**
     * Runs $draw with these settings in place of the stored ones, then puts
     * the stored ones back, also when $draw throws. Nothing is saved.
     * SettingsOverridden fires when they change and when they come back.
     *
     * @template T
     *
     * @param  array<string, array<string, mixed>>  $settings  the whole settings content, by section
     * @param  Closure(): T  $draw
     * @return T
     */
    public function withSettings(array $settings, Closure $draw): mixed
    {
        $previous = $this->settingsOverride;

        try {
            $this->settingsOverride = $settings;
            SettingsOverridden::dispatch($settings);

            return $draw();
        } finally {
            $this->settingsOverride = $previous;
            SettingsOverridden::dispatch($previous);
        }
    }
}
