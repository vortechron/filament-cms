<?php

namespace Vortechron\FilamentCms;

use Closure;
use Illuminate\Validation\ValidationException;
use Vortechron\FilamentBlockEditor\Rendering\ContentLocale;
use Vortechron\FilamentCms\Models\Document;

/** Language configuration and temporary rendering context, separate from the panel. */
final class SiteLanguages
{
    public static function labels(): array
    {
        $labels = config('filament-cms.locales', []);

        return is_array($labels) ? array_filter($labels, fn ($label, $locale): bool => is_string($locale) && preg_match('/^[a-z]{2}$/D', $locale) === 1 && is_string($label), ARRAY_FILTER_USE_BOTH) : [];
    }

    public static function enabled(): bool
    {
        return count(self::labels()) > 1;
    }

    public static function default(): string
    {
        $default = config('filament-cms.default_locale');

        return is_string($default) && isset(self::labels()[$default]) ? $default : (array_key_first(self::labels()) ?? (config('filament-block-editor.content_locale') ?: app()->getLocale()));
    }

    public static function current(): string
    {
        return ContentLocale::site();
    }

    public static function check(string $locale): void
    {
        if (! isset(self::labels()[$locale])) {
            throw ValidationException::withMessages(['locale' => 'Choose a configured website language.']);
        }
    }

    public static function run(string $locale, Closure $draw): mixed
    {
        event(new Events\SettingsOverridden(null));
        try {
            return ContentLocale::with($locale, fn () => ContentLocale::run($draw));
        } finally {
            event(new Events\SettingsOverridden(null));
        }
    }

    public static function forPath(string $path): string
    {
        $first = explode('/', trim($path, '/'))[0];

        return self::enabled() && isset(self::labels()[$first]) && $first !== self::default() ? $first : self::default();
    }

    public static function path(string $basePath, string $locale): string
    {
        $path = Document::cleanPath($basePath);
        $first = explode('/', trim($path, '/'))[0];
        if (isset(self::labels()[$first]) && $first !== self::default()) {
            $path = '/'.ltrim(substr($path, strlen($first) + 1), '/');
        }

        return $locale === self::default() ? $path : '/'.$locale.($path === '/' ? '' : $path);
    }

    /** Only published counterparts are advertised to visitors. */
    public static function links(Document $document): array
    {
        if (! self::enabled()) {
            return [];
        }

        $links = [];
        foreach (self::labels() as $locale => $label) {
            $translated = $document->translation($locale);
            if ($translated?->is_published && $translated->path !== null) {
                $links[] = ['locale' => $locale, 'label' => $label, 'url' => $translated->url(), 'current' => $document->language() === $locale];
            }
        }

        return $links;
    }
}
