<?php

namespace Vortechron\FilamentCms;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Vortechron\FilamentCms\Events\SettingsOverridden;
use Vortechron\FilamentCms\Models\CmsContent;

/** Optional translations of explicitly chosen settings text. Shop facts stay shared. */
final class CmsSettingsTranslations
{
    public static function fields(): array
    {
        $fields = config('filament-cms.translatable_settings', []);

        return is_array($fields) ? $fields : [];
    }

    public static function saved(string $locale): array
    {
        return CmsContent::where('key', 'settings:'.$locale)->value('content') ?? [];
    }

    public static function overlay(array $base, string $locale): array
    {
        if (! SiteLanguages::enabled() || $locale === SiteLanguages::default()) {
            return $base;
        }
        $saved = self::saved($locale);
        foreach (self::fields() as $section => $fields) {
            foreach ($fields as $field) {
                $value = $saved[$section][$field] ?? null;
                if (is_string($value)) {
                    $base[$section][$field] = $value;
                }
            }
        }

        return $base;
    }

    public function save(string $locale, array $values, ?Authenticatable $user = null, ?array $expectedValues = null): void
    {
        SiteLanguages::check($locale);
        if ($locale === SiteLanguages::default()) {
            throw ValidationException::withMessages(['locale' => 'Edit Settings to change the original language.']);
        }
        $rules = [];
        $allowed = self::fields();
        foreach ($values as $section => $fields) {
            if (! isset($allowed[$section]) || ! is_array($fields) || array_diff(array_keys($fields), $allowed[$section])) {
                throw ValidationException::withMessages(['settings' => 'Only the configured text settings have translations.']);
            }
            foreach ($fields as $field => $value) {
                $rules[$section.'.'.$field] = ['nullable', 'string', 'max:10000'];
            }
        }
        Validator::make($values, $rules)->validate();
        DB::transaction(function () use ($locale, $values, $user, $expectedValues): void {
            // A stable existing row also serializes the first translated-text write.
            CmsContent::query()->insertOrIgnore(['key' => 'settings:'.$locale, 'content' => '{}', 'created_at' => now(), 'updated_at' => now()]);
            $row = CmsContent::where('key', 'settings:'.$locale)->lockForUpdate()->first();
            $saved = $row?->content ?? [];
            if ($expectedValues !== null) {
                foreach ($values as $section => $fields) {
                    foreach ($fields as $field => $value) {
                        if (($saved[$section][$field] ?? null) !== ($expectedValues[$section][$field] ?? null)) {
                            throw ValidationException::withMessages(['translations' => __('filament-cms::languages.changed')]);
                        }
                    }
                }
            }
            foreach ($values as $section => $fields) {
                $saved[$section] = array_replace($saved[$section] ?? [], $fields);
            }
            app(ContentStore::class)->save('settings:'.$locale, $saved, $user);
        });
        event(new SettingsOverridden(null));
    }
}
