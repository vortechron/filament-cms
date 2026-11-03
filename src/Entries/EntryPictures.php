<?php

namespace Vortechron\FilamentCms\Entries;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Vortechron\FilamentCms\CmsImage;

/**
 * Entry pictures are stored as public addresses, while the entry form's
 * upload field works with paths on the CMS disk. These turn one into the
 * other, and refuse a path the form did not upload: a submitted path is
 * client data (see Filament's "Authorizing existing file paths").
 */
final class EntryPictures
{
    /**
     * The path on the CMS disk of a stored address, or the address itself
     * when it points elsewhere (the upload field then shows nothing).
     */
    public static function path(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $root = self::root();

        return str_starts_with($url, $root) ? substr($url, strlen($root)) : $url;
    }

    /**
     * The address to store for the form's state: the stored one when it did
     * not change, a new upload's address, or null. A stored picture from
     * elsewhere (not on the CMS disk, so the upload field cannot show it) is
     * kept when the form sends none.
     */
    public static function url(mixed $state, mixed $stored): ?string
    {
        $path = Arr::first(array_filter(Arr::wrap($state), is_string(...)));
        $stored = is_string($stored) && $stored !== '' ? $stored : null;

        if ($path === null || $path === '') {
            return $stored !== null && ! str_starts_with($stored, self::root()) ? $stored : null;
        }

        if ($path === $stored || self::addressOf($path) === $stored) {
            return $stored;
        }

        return self::isUpload($path) ? self::addressOf($path) : null;
    }

    /**
     * A file of CmsImage's types directly inside CmsImage's folder, with a
     * plain name, that exists. Nothing is decoded, so "..", "%2e" and "//"
     * never pass.
     */
    public static function isUpload(string $path): bool
    {
        $types = implode('|', CmsImage::EXTENSIONS);

        return preg_match('#^'.preg_quote(CmsImage::DIRECTORY, '#').'/[A-Za-z0-9][A-Za-z0-9_-]*(?:\.[A-Za-z0-9_-]+)*\.(?:'.$types.')$#i', $path) === 1
            && Storage::disk(self::disk())->exists($path);
    }

    private static function addressOf(string $path): string
    {
        return Storage::disk(self::disk())->url($path);
    }

    private static function root(): string
    {
        return rtrim(Storage::disk(self::disk())->url(''), '/').'/';
    }

    private static function disk(): string
    {
        return (string) config('filament-cms.disk', 'public');
    }
}
