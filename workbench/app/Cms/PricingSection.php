<?php

namespace Workbench\App\Cms;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Vortechron\FilamentCms\CmsSection;

class PricingSection extends CmsSection
{
    public function label(): string
    {
        return 'Pakej';
    }

    public function fields(): array
    {
        return [
            TextInput::make('heading'),
            Repeater::make('packages')
                ->schema([
                    TextInput::make('name')->required(),
                    TextInput::make('price')->prefix('RM'),
                ])
                ->maxItems(6)
                ->addActionLabel('Tambah pakej'),
        ];
    }

    public function defaults(): array
    {
        return [
            'heading' => 'Pakej sambal',
            'packages' => [
                ['name' => 'Kecil', 'price' => '15'],
                ['name' => 'Besar', 'price' => '25'],
            ],
        ];
    }
}
