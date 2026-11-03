<?php

namespace Vortechron\FilamentCms\Tests\Feature;

/**
 * A policy that refuses even opening the screen.
 */
class NoAccessPolicy
{
    public function viewAny(): bool
    {
        return false;
    }
}
