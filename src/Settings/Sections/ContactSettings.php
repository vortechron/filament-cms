<?php

namespace Vortechron\FilamentCms\Settings\Sections;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Vortechron\FilamentCms\Settings\SettingsSection;

class ContactSettings extends SettingsSection
{
    public function label(): string
    {
        return __('filament-cms::settings.contact');
    }

    public function fields(): array
    {
        return [
            Repeater::make('phones')
                ->label(__('filament-cms::settings.phones'))
                ->helperText(__('filament-cms::settings.phones_help'))
                ->simple(TextInput::make('number')->label(__('filament-cms::settings.number'))->tel()->required()->maxLength(30))
                ->maxItems(4)
                ->addActionLabel(__('filament-cms::settings.add_phone')),
            TextInput::make('email')->label(__('filament-cms::settings.email'))->email()->maxLength(120),
            Textarea::make('address')->label(__('filament-cms::settings.address'))->rows(3)->maxLength(300),
            TextInput::make('map_url')->label(__('filament-cms::settings.map_url'))->url()->rule('url:http,https')->maxLength(500),
        ];
    }
}
