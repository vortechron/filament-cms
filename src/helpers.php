<?php

use Illuminate\Support\Facades\Storage;
use Vortechron\FilamentBlockEditor\Design\KitSource;
use Vortechron\FilamentCms\Cms;
use Vortechron\FilamentCms\PageContent;

if (! function_exists('cms')) {
    /**
     * The content of one CMS page: cms('home')->section('pakej')->packages.
     */
    function cms(string $page): PageContent
    {
        return app(Cms::class)->page($page);
    }
}

if (! function_exists('cms_settings')) {
    /**
     * The site-wide settings: cms_settings()->section('contact')->email.
     */
    function cms_settings(): PageContent
    {
        return app(Cms::class)->page(Cms::SETTINGS);
    }
}

if (! function_exists('cms_kit')) {
    /**
     * The site's design kit, cleaned: cms_kit()['colors']['primary'].
     *
     * @return array<string, mixed>
     */
    function cms_kit(): array
    {
        return app(KitSource::class)->current();
    }
}

if (! function_exists('cms_image')) {
    /**
     * The public address of a stored photo, or null when there is none.
     */
    function cms_image(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = reset($path) ?: null;
        }

        return is_string($path) && $path !== ''
            ? Storage::disk(config('filament-cms.disk', 'public'))->url($path)
            : null;
    }
}

if (! function_exists('cms_whatsapp_number')) {
    /**
     * A phone number as people write it in Malaysia, in the international
     * digits WhatsApp uses: "012-345 6789" becomes 60123456789.
     */
    function cms_whatsapp_number(?string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        if ($digits === '') {
            return null;
        }

        return str_starts_with($digits, '0') ? '6'.$digits : $digits;
    }
}

if (! function_exists('cms_whatsapp_url')) {
    /**
     * A wa.me link from a phone number as people write it in Malaysia:
     * "012-345 6789" becomes https://wa.me/60123456789.
     */
    function cms_whatsapp_url(?string $number, ?string $text = null): ?string
    {
        $digits = cms_whatsapp_number($number);

        return $digits === null ? null : 'https://wa.me/'.$digits.(filled($text) ? '?text='.rawurlencode($text) : '');
    }
}

if (! function_exists('cms_url')) {
    /**
     * A link the client typed, or null unless it is an http or https
     * address. Use it for every client link printed in an href.
     */
    function cms_url(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);

        return preg_match('#^https?://[^/\s]#i', $url) === 1 ? $url : null;
    }
}
