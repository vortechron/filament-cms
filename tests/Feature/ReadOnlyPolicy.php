<?php

namespace Vortechron\FilamentCms\Tests\Feature;

/**
 * A policy that lets someone see a screen but change nothing.
 */
class ReadOnlyPolicy
{
    public function viewAny(): bool
    {
        return true;
    }

    public function create(): bool
    {
        return false;
    }

    public function update(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }
}
