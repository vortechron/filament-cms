<?php

namespace Vortechron\FilamentCms\Settings\Sections;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Vortechron\FilamentCms\Settings\SettingsSection;

class SocialSettings extends SettingsSection
{
    public const PLATFORMS = [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
        'threads' => 'Threads',
        'x' => 'X',
        'youtube' => 'YouTube',
        'shopee' => 'Shopee',
        'lazada' => 'Lazada',
    ];

    public function label(): string
    {
        return __('filament-cms::settings.social');
    }

    public function fields(): array
    {
        return [
            Repeater::make('links')
                ->hiddenLabel()
                ->schema([
                    Select::make('platform')->label(__('filament-cms::settings.platform'))->options(self::PLATFORMS)->required(),
                    TextInput::make('url')->label(__('filament-cms::settings.url'))->url()->required()->maxLength(500),
                ])
                ->columns(2)
                ->maxItems(8)
                ->addActionLabel(__('filament-cms::settings.add_link')),
        ];
    }
}
