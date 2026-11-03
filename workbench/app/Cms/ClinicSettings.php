<?php

namespace Workbench\App\Cms;

use Vortechron\FilamentCms\Settings\StandardSettings;

class ClinicSettings extends StandardSettings
{
    public function sections(): array
    {
        return [...parent::sections(), ClinicSection::make('clinic')];
    }
}
