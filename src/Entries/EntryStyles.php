<?php

namespace Vortechron\FilamentCms\Entries;

/**
 * The CSS of the built-in entry cards and entry page, for
 * <x-filament-cms::entries-styles />. It reads the design kit's variables.
 */
final class EntryStyles
{
    public static function css(): string
    {
        $css = file_get_contents(__DIR__.'/../../resources/css/entries.css');

        // Printed in a <style> tag.
        return str_replace('<', '', is_string($css) ? $css : '');
    }
}
