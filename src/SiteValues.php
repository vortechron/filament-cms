<?php

namespace Vortechron\FilamentCms;

use Closure;
use InvalidArgumentException;
use Vortechron\FilamentBlockEditor\Content\DynamicValues;
use Vortechron\FilamentBlockEditor\Rendering\RenderContext;
use Vortechron\FilamentCms\Settings\Sections\SocialSettings;

/**
 * The dynamic values the CMS gives the page builder: the shop's details from
 * the settings (`site.*`) and the page being drawn (`page.*`). Registered
 * once at boot; each value reads the settings when it is drawn, so a change
 * saved a moment ago shows at once.
 *
 * `page.title` and `page.url` come from the RenderContext values the
 * SitePageRenderer passes.
 */
final class SiteValues
{
    public static function register(DynamicValues $values): void
    {
        $setting = fn (string $section, string $field): Closure => fn (): mixed => self::setting($section, $field);
        $business = __('filament-cms::settings.business');
        $contact = __('filament-cms::settings.contact');
        $hours = __('filament-cms::settings.hours');
        $social = __('filament-cms::settings.social');

        $values
            ->register('site.name', __('filament-cms::settings.name'), $setting('business', 'name'), group: $business)
            ->register('site.legal_name', __('filament-cms::settings.legal_name'), $setting('business', 'legal_name'), group: $business)
            ->register('site.registration', __('filament-cms::settings.registration_number'), $setting('business', 'registration_number'), group: $business)
            ->register('site.logo', __('filament-cms::settings.logo'), fn (): ?string => cms_image(self::setting('business', 'logo')), 'image', $business)
            ->register('site.phone', __('filament-cms::values.phone'), fn (): ?string => self::phones()[0] ?? null, group: $contact)
            ->register('site.phones', __('filament-cms::values.phones'), fn (): string => implode(' / ', self::phones()), group: $contact)
            ->register('site.email', __('filament-cms::settings.email'), $setting('contact', 'email'), group: $contact)
            ->register('site.address', __('filament-cms::settings.address'), $setting('contact', 'address'), group: $contact)
            ->register('site.whatsapp_number', __('filament-cms::values.whatsapp_number'), fn (): ?string => cms_whatsapp_number(self::phones()[0] ?? null), group: $contact)
            ->register('site.whatsapp', __('filament-cms::values.whatsapp'), fn (): ?string => cms_whatsapp_url(self::phones()[0] ?? null), 'link', $contact)
            ->register('site.phone_link', __('filament-cms::values.phone_link'), fn (): ?string => self::prefixed('tel:+', cms_whatsapp_number(self::phones()[0] ?? null)), 'link', $contact)
            ->register('site.email_link', __('filament-cms::values.email_link'), fn (): ?string => self::prefixed('mailto:', self::email()), 'link', $contact)
            ->register('site.map', __('filament-cms::settings.map_url'), fn (): ?string => cms_url(self::setting('contact', 'map_url')), 'link', $contact)
            ->register('site.hours', __('filament-cms::settings.hours'), fn (): string => implode("\n", self::hours()), group: $hours)
            ->register('site.hours_note', __('filament-cms::values.hours_note'), $setting('hours', 'note'), group: $hours);

        foreach (SocialSettings::PLATFORMS as $platform => $label) {
            $values->register("site.{$platform}", $label, fn (): ?string => self::social($platform), 'link', $social);
        }

        $page = __('filament-cms::values.page');

        $values
            ->register('page.title', __('filament-cms::values.page_title'), fn (RenderContext $context): mixed => $context->values['page.title'] ?? null, group: $page)
            ->register('page.url', __('filament-cms::values.page_url'), fn (RenderContext $context): mixed => $context->values['page.url'] ?? null, 'link', $page);
    }

    /**
     * One saved setting, or null when the project's settings have no such
     * section.
     */
    private static function setting(string $section, string $field): mixed
    {
        try {
            return cms_settings()->section($section)->get($field);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private static function phones(): array
    {
        $phones = self::setting('contact', 'phones');
        $numbers = array_map(fn (mixed $phone): mixed => is_array($phone) ? ($phone['number'] ?? null) : $phone, is_array($phones) ? $phones : []);

        return array_values(array_filter(array_map(fn (mixed $number): string => is_string($number) ? trim($number) : '', $numbers)));
    }

    /**
     * Each opening hours row as "days: time".
     *
     * @return list<string>
     */
    private static function hours(): array
    {
        $rows = self::setting('hours', 'days');
        $lines = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $days = is_array($row) && is_string($row['days'] ?? null) ? trim($row['days']) : '';
            $time = is_array($row) && is_string($row['time'] ?? null) ? trim($row['time']) : '';

            if ($days !== '' || $time !== '') {
                $lines[] = $days !== '' && $time !== '' ? "{$days}: {$time}" : $days.$time;
            }
        }

        return $lines;
    }

    private static function email(): ?string
    {
        $email = self::setting('contact', 'email');

        return is_string($email) && filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false ? trim($email) : null;
    }

    private static function social(string $platform): ?string
    {
        $links = self::setting('social', 'links');

        foreach (is_array($links) ? $links : [] as $link) {
            if (is_array($link) && ($link['platform'] ?? null) === $platform && cms_url($link['url'] ?? null) !== null) {
                return cms_url($link['url']);
            }
        }

        return null;
    }

    private static function prefixed(string $prefix, ?string $value): ?string
    {
        return $value === null ? null : $prefix.$value;
    }
}
