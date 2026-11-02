<?php

namespace Workbench\App\Cms;

use Vortechron\FilamentCms\CmsPage;

class HomePage extends CmsPage
{
    public static function key(): string
    {
        return 'home';
    }

    public static function label(): string
    {
        return 'Halaman utama';
    }

    public function sections(): array
    {
        return [
            HeroSection::make('hero'),
            PricingSection::make('pakej'),
        ];
    }
}
