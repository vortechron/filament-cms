<?php

namespace Vortechron\FilamentCms;

use Vortechron\FilamentBlockEditor\Content\DynamicValues;
use Vortechron\FilamentBlockEditor\Rendering\RenderContext;
use Vortechron\FilamentCms\Settings\SettingsSection;
use Vortechron\FilamentCms\Settings\StandardSettings;

/**
 * The dynamic values the CMS gives the page builder: the values each
 * settings section declares in shopValues() (the shop's `site.*` details) and
 * the page being drawn (`page.*`). Registered once at boot; each value reads
 * the settings when it is drawn, so a change saved a moment ago shows at
 * once. Each section value names its section's key as the shop info group
 * it comes from (`settings`), so the builder can show that group.
 *
 * Sections register in order; if two register the same key, the last one
 * wins (a project section can replace a standard value).
 *
 * `page.title` and `page.url` come from the RenderContext values the
 * SitePageRenderer passes.
 */
final class SiteValues
{
    public static function register(DynamicValues $values): void
    {
        foreach (self::sections() as $section) {
            foreach ($section->shopValues() as $value) {
                $values->register($value->key, $value->label, $value->resolve, $value->type, $value->group ?? $section->label(), settings: $section->key);
            }
        }

        $page = __('filament-cms::values.page');

        $values
            ->register('page.title', __('filament-cms::values.page_title'), fn (RenderContext $context): mixed => $context->values['page.title'] ?? null, group: $page)
            ->register('page.url', __('filament-cms::values.page_url'), fn (RenderContext $context): mixed => $context->values['page.url'] ?? null, 'link', $page);
    }

    /**
     * The sections of the project's settings page, or of StandardSettings
     * when none is set, so the standard `site.*` keys always register.
     *
     * @return list<SettingsSection>
     */
    private static function sections(): array
    {
        $cms = app(Cms::class);
        $page = $cms->has(Cms::SETTINGS) ? $cms->definition(Cms::SETTINGS) : app(StandardSettings::class);

        return array_values(array_filter($page->sections(), fn (mixed $section): bool => $section instanceof SettingsSection));
    }
}
