<?php

namespace Vortechron\FilamentCms\Settings;

use Vortechron\FilamentCms\Cms;
use Vortechron\FilamentCms\CmsPage;
use Vortechron\FilamentCms\Settings\Sections\BusinessSettings;
use Vortechron\FilamentCms\Settings\Sections\ContactSettings;
use Vortechron\FilamentCms\Settings\Sections\HoursSettings;
use Vortechron\FilamentCms\Settings\Sections\SeoSettings;
use Vortechron\FilamentCms\Settings\Sections\SocialSettings;

/**
 * The site-wide settings most client sites need. To give a project its own
 * defaults or extra sections, extend this class and point
 * filament-cms.settings at it.
 */
class StandardSettings extends CmsPage
{
    public static function key(): string
    {
        return Cms::SETTINGS;
    }

    public static function label(): string
    {
        return __('filament-cms::settings.label');
    }

    public function sections(): array
    {
        return [
            BusinessSettings::make('business'),
            ContactSettings::make('contact'),
            HoursSettings::make('hours'),
            SocialSettings::make('social'),
            SeoSettings::make('seo'),
        ];
    }
}
