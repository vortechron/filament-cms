<?php

namespace Workbench\App\Cms;

use Vortechron\FilamentCms\Settings\StandardSettings;

class ShopSettings extends StandardSettings
{
    public function defaults(): array
    {
        return [
            'business' => ['name' => 'Kedai Contoh'],
            'contact' => ['phones' => ['011-1111 2222']],
        ];
    }
}
