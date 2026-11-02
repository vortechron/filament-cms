<?php

namespace Workbench\App\Cms;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Vortechron\FilamentCms\CmsImage;
use Vortechron\FilamentCms\CmsSection;

class HeroSection extends CmsSection
{
    public function label(): string
    {
        return 'Bahagian atas';
    }

    public function hideable(): bool
    {
        return false;
    }

    public function fields(): array
    {
        return [
            TextInput::make('title')->required(),
            Textarea::make('lead'),
            CmsImage::make('photo'),
        ];
    }

    public function defaults(): array
    {
        return [
            'title' => 'Sambal Dapur Maya',
            'lead' => 'Sambal rumah, dimasak setiap minggu.',
        ];
    }
}
