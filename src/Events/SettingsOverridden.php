<?php

namespace Vortechron\FilamentCms\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Cms::withSettings() changed the settings every reader sees: once when a
 * draw with other settings starts ($settings holds them), and once when it
 * ends ($settings is null, or the outer draw's settings when draws nest).
 * A host that keeps the settings for the request forgets its copy here.
 */
final class SettingsOverridden
{
    use Dispatchable;

    /**
     * @param  array<string, array<string, mixed>>|null  $settings  what is read now; null means the stored settings
     */
    public function __construct(public readonly ?array $settings) {}
}
