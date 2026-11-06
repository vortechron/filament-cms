<?php

namespace Vortechron\FilamentCms;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\Entry;

/** An entry keeps one identity, shared stock facts, and optional translated text. */
final class EntryTranslations
{
    public static function fields(Entry $entry): array
    {
        return array_values(array_filter($entry->definition()?->getFields() ?? [], fn ($field): bool => $field->isTranslatable()));
    }

    public function validate(Entry $entry, string $locale, array $values): void
    {
        SiteLanguages::check($locale);
        if ($locale === SiteLanguages::default()) {
            throw ValidationException::withMessages(['locale' => 'Edit the original entry to change its default language.']);
        }
        $rules = ['title' => ['sometimes', 'nullable', 'string', 'max:120'], 'fields' => ['sometimes', 'array']];
        $allowed = [];
        foreach (self::fields($entry) as $field) {
            $allowed[] = $field->name;
            $rules['fields.'.$field->name] = ['sometimes', 'nullable', 'string', 'max:10000'];
        }
        Validator::make($values, $rules)->validate();
        if (array_diff(array_keys($values), ['title', 'fields']) || array_diff(array_keys($values['fields'] ?? []), $allowed)) {
            throw ValidationException::withMessages(['fields' => 'Only the title and text fields have translations. Prices, photos and relationships are shared.']);
        }

    }

    public function save(Entry $entry, string $locale, array $values, ?Authenticatable $user = null, ?array $expected = null): void
    {
        $this->validate($entry, $locale, $values);

        DB::transaction(function () use ($entry, $locale, $values, $user, $expected): void {
            $stored = Entry::query()->lockForUpdate()->findOrFail($entry->getKey());
            $settings = $stored->settings ?? [];
            $previous = $settings['translations'][$locale] ?? [];
            if ($expected !== null) {
                if (array_key_exists('title', $values) && ($previous['title'] ?? null) !== ($expected['title'] ?? null)) {
                    throw ValidationException::withMessages(['translations' => __('filament-cms::languages.changed')]);
                }
                foreach ($values['fields'] ?? [] as $field => $value) {
                    if (($previous['fields'][$field] ?? null) !== ($expected['fields'][$field] ?? null)) {
                        throw ValidationException::withMessages(['translations' => __('filament-cms::languages.changed')]);
                    }
                }
            }
            $next = array_replace($previous, array_intersect_key($values, ['title' => true]));
            $next['fields'] = array_replace($previous['fields'] ?? [], $values['fields'] ?? []);
            $settings['translations'][$locale] = $next;
            $stored->settings = $settings;
            $stored->save();
            $entry->settings = $settings;
            if ($next !== $previous) {
                $key = 'entry-text:'.$entry->getKey().':'.$locale;
                CmsRevision::create(['key' => $key, 'content' => $next, 'user_id' => $user?->getAuthIdentifier()]);
                $newest = CmsRevision::where('key', $key)->latest('id')->limit(max(1, (int) config('filament-cms.revisions', 20)))->pluck('id');
                CmsRevision::where('key', $key)->whereNotIn('id', $newest)->delete();
            }
        });
    }
}
