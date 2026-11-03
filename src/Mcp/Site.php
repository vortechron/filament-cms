<?php

namespace Vortechron\FilamentCms\Mcp;

use Throwable;
use Vortechron\FilamentCms\Cms;

/**
 * The site the MCP server serves: its name and address, and the words the
 * agent reads first.
 */
final class Site
{
    public static function name(): string
    {
        try {
            $name = app(Cms::class)->has(Cms::SETTINGS) ? cms_settings()->section('business')->name : null;
        } catch (Throwable) {
            $name = null;
        }

        return is_string($name) && trim($name) !== '' ? trim($name) : (string) config('app.name');
    }

    public static function url(): string
    {
        return url('/');
    }

    public static function instructions(): string
    {
        return implode("\n\n", [
            'This server holds the content of the website "'.self::name().'" ('.self::url().'), built with Filament CMS. '
                .'You act as a developer: you may read and change its pages, header and footer, synced and saved sections, '
                .'design kit, shop settings, entries and form submissions, and bring back older versions.',
            'Every write goes through the same checks, cleaning, locks and version history as the admin panel. '
                .'A write that is refused changes nothing and answers {"ok": false, "errors": {...}} in plain words; fix the value and send it again.',
            'Start with site_overview. Read page_format before you write a page value. For small changes use the section tools '
                .'(update_section, insert_section, move_section, remove_section) instead of sending the whole page. '
                .'Check the result with render_document.',
            'Own block text and textarea fields may hold {key} short codes (for example "Hubungi {site.name}"); site_overview\'s dynamic_values lists the keys.',
        ]);
    }
}
