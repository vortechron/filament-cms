<?php

namespace Vortechron\FilamentCms\Settings;

use Closure;

/**
 * One short code a settings section gives the page builder, such as
 * `site.phone`. The resolver runs when a page is drawn, never when the
 * value is registered, so it must read the settings itself (see
 * SettingsSection::setting()). `$group` is the label the builder lists it
 * under; null uses the section's label.
 */
final readonly class ShopValue
{
    /**
     * @param  Closure(): mixed  $resolve
     * @param  'text'|'link'|'image'  $type
     */
    public function __construct(
        public string $key,
        public string $label,
        public Closure $resolve,
        public string $type = 'text',
        public ?string $group = null,
    ) {}

    public static function text(string $key, string $label, Closure $resolve, ?string $group = null): self
    {
        return new self($key, $label, $resolve, 'text', $group);
    }

    public static function link(string $key, string $label, Closure $resolve, ?string $group = null): self
    {
        return new self($key, $label, $resolve, 'link', $group);
    }

    public static function image(string $key, string $label, Closure $resolve, ?string $group = null): self
    {
        return new self($key, $label, $resolve, 'image', $group);
    }
}
