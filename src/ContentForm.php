<?php

namespace Vortechron\FilamentCms;

use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

/**
 * Turns a CmsPage into the form the client fills in: one panel section per
 * page section, each with its own fields under the section key.
 */
final class ContentForm
{
    /**
     * @return list<Section>
     */
    public static function components(CmsPage $page): array
    {
        return array_map(
            fn (CmsSection $section): Section => Section::make($section->label())
                ->description($section->description())
                ->statePath($section->key)
                ->collapsible()
                ->schema([
                    ...($section->hideable() ? [self::visibleToggle()] : []),
                    ...$section->fields(),
                ]),
            $page->sections(),
        );
    }

    /**
     * What the form starts with: the saved values on top of the defaults.
     *
     * @param  array<string, array<string, mixed>>  $saved
     * @return array<string, array<string, mixed>>
     */
    public static function fill(CmsPage $page, array $saved): array
    {
        $state = [];

        foreach ($page->sections() as $section) {
            $values = PageContent::merge($section, $saved[$section->key] ?? []);

            if ($section->hideable()) {
                $values[PageContent::VISIBLE] = (bool) ($values[PageContent::VISIBLE] ?? true);
            }

            $state[$section->key] = $values;
        }

        return $state;
    }

    private static function visibleToggle(): Toggle
    {
        return Toggle::make(PageContent::VISIBLE)
            ->label(__('filament-cms::cms.show_section'))
            ->default(true);
    }
}
