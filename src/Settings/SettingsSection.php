<?php

namespace Vortechron\FilamentCms\Settings;

use Vortechron\FilamentCms\CmsSection;

/**
 * Settings are site-wide, so the client cannot switch them off.
 */
abstract class SettingsSection extends CmsSection
{
    public function hideable(): bool
    {
        return false;
    }
}
