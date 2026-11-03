<?php

namespace Vortechron\FilamentCms;

use Filament\Forms\Components\FileUpload;

/**
 * A photo field. The browser shrinks a phone photo to at most 1920 pixels
 * before it uploads, so large photos stay under the server's upload limit.
 */
class CmsImage
{
    /**
     * The folder on the CMS disk the photos go to.
     */
    public const DIRECTORY = 'cms';

    /**
     * The file types a photo may be. No SVG: it can carry script.
     */
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public static function make(string $name): FileUpload
    {
        return FileUpload::make($name)
            ->image()
            // SVG files can carry script, and the photos are served from the site's own domain.
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->disk(config('filament-cms.disk', 'public'))
            ->directory(self::DIRECTORY)
            ->visibility('public')
            ->automaticallyResizeImagesMode('contain')
            ->automaticallyResizeImagesToWidth('1920')
            ->automaticallyResizeImagesToHeight('1920')
            ->automaticallyUpscaleImagesWhenResizing(false)
            ->maxSize(10240);
    }
}
