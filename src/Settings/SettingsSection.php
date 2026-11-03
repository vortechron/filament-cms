<?php

namespace Vortechron\FilamentCms\Settings;

use InvalidArgumentException;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\CmsSection;

/**
 * Settings are site-wide, so the client cannot switch them off.
 *
 * A settings section may also show in the page builder's shop info form
 * (the header and footer panel): builderFields() lists its block editor
 * fields, builderRules() checks a save from there with the same rules as
 * its Filament fields, and the two value hooks turn the saved shape into
 * the builder's shape and back. Keys the builder does not show are kept.
 * Picture fields are saved paths; the builder gets their address.
 */
abstract class SettingsSection extends CmsSection
{
    public function hideable(): bool
    {
        return false;
    }

    /**
     * The fields of the builder's shop info form. None keeps this section
     * out of the builder.
     *
     * @return list<Field>
     */
    public function builderFields(): array
    {
        return [];
    }

    /**
     * Laravel rules for a save from the builder, by field (dot paths inside
     * this section), the same as its Filament fields check.
     *
     * @return array<string, mixed>
     */
    public function builderRules(): array
    {
        return [];
    }

    /**
     * The short codes (dynamic values) this section gives the page builder,
     * for example `site.phone`. They are registered with this section's key
     * as the shop info group. Resolvers run when a page is drawn, so read
     * the settings with setting(), not before.
     *
     * @return list<ShopValue>
     */
    public function shopValues(): array
    {
        return [];
    }

    /**
     * One saved value of this section as the page being drawn sees it (the
     * builder preview swaps the settings), or null when the project's
     * settings have no such section.
     */
    protected function setting(string $field): mixed
    {
        try {
            return cms_settings()->section($this->key)->get($field);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Saved values as the builder's fields hold them.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function toBuilderValues(array $values): array
    {
        return $values;
    }

    /**
     * Cleaned builder values as they are saved.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function fromBuilderValues(array $values): array
    {
        return $values;
    }
}
