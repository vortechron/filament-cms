<?php

use Illuminate\Support\Facades\Storage;
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

if (! function_exists('cms_whatsapp_url')) {
    /**
     * A wa.me link from a phone number as people write it in Malaysia:
     * "011-2705 1612" becomes https://wa.me/601127051612.
     */
    function cms_whatsapp_url(?string $number, ?string $text = null): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '6'.$digits;
        }

        return 'https://wa.me/'.$digits.(filled($text) ? '?text='.rawurlencode($text) : '');
    }
}
