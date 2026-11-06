<?php

namespace Vortechron\FilamentCms\Tests\Feature;

class UpdateOnlyLanguagePolicy extends ReadOnlyPolicy
{
    public function update(): bool
    {
        return true;
    }
}
