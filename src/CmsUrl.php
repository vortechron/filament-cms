<?php

namespace Vortechron\FilamentCms;

use Filament\Forms\Components\TextInput;

/**
 * A link field that accepts only http and https addresses. Laravel's plain
 * url rule also accepts schemes such as data:// and ftp://. Print the value
 * with cms_url() as well.
 */
class CmsUrl
{
    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->url()
            ->rule('url:http,https')
            ->maxLength(500);
    }
}
