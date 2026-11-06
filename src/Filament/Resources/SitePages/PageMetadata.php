<?php

namespace Vortechron\FilamentCms\Filament\Resources\SitePages;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Vortechron\FilamentCms\Filament\Schemas\TranslatableText;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\SiteLanguages;

final class PageMetadata
{
    public const FIELDS = ['title', 'path', 'is_published', 'seo_title', 'seo_description'];

    public static function text(string $name, string $label, int $limit, bool $required = false, bool $multiline = false): mixed
    {
        $field = fn (string $path) => ($multiline ? Textarea::make($path)->rows(2) : TextInput::make($path))->label($label)->maxLength($limit);

        return Group::make(fn (?SitePage $record): array => [
            $record !== null && $record->shared_format !== 1
                ? $field($name)->required($required)
                : TranslatableText::field($field($name)->required($required), fn (string $locale) => $field("translations.{$locale}.{$name}")),
        ]);
    }

    public static function languageAddresses(): array
    {
        $sections = [];
        if (! SiteLanguages::enabled()) {
            return [];
        }
        foreach (SiteLanguages::labels() as $locale => $label) {
            if ($locale === SiteLanguages::default()) {
                continue;
            }
            $sections[] = Section::make($label)->collapsed()->visible(fn (?SitePage $record): bool => $record === null || $record->shared_format === 1)->schema([
                TextInput::make("translations.{$locale}.path")->label(__('filament-cms::pages.path'))->maxLength(120),
                Toggle::make("translations.{$locale}.is_published")->label(__('filament-cms::pages.published'))->default(false),
            ]);
        }

        return $sections;
    }

    public static function fill(SitePage $page): array
    {
        $data = $page->only(self::FIELDS);
        if (SiteLanguages::enabled() && $page->shared_format === 1) {
            foreach (SiteLanguages::labels() as $locale => $label) {
                if ($locale !== SiteLanguages::default()) {
                    $data['translations'][$locale] = $page->translation($locale)?->only(self::FIELDS) ?? ['title' => null, 'path' => null, 'is_published' => false, 'seo_title' => null, 'seo_description' => null];
                }
            }
        }

        return $data;
    }

    public static function changes(array $data, array $baseline): array
    {
        $changes = [];
        foreach (self::FIELDS as $name) {
            if (array_key_exists($name, $data) && $data[$name] !== ($baseline[$name] ?? null)) {
                $changes[$name] = $data[$name];
            }
        }
        foreach ($data['translations'] ?? [] as $locale => $fields) {
            $diff = self::changes($fields, $baseline['translations'][$locale] ?? []);
            if ($diff !== []) {
                $changes['translations'][$locale] = $diff;
            }
        }

        return $changes;
    }
}
