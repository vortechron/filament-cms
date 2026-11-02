<?php

namespace Vortechron\FilamentCms\Settings\Sections;

use Filament\Forms\Components\TextInput;
use Vortechron\FilamentCms\CmsImage;
use Vortechron\FilamentCms\Settings\SettingsSection;

class BusinessSettings extends SettingsSection
{
    public function label(): string
    {
        return __('filament-cms::settings.business');
    }

    public function fields(): array
    {
        return [
            TextInput::make('name')->label(__('filament-cms::settings.name'))->required()->maxLength(120),
            TextInput::make('tagline')->label(__('filament-cms::settings.tagline'))->maxLength(160),
            TextInput::make('registration_number')->label(__('filament-cms::settings.registration_number'))->maxLength(40),
            CmsImage::make('logo')->label(__('filament-cms::settings.logo')),
        ];
    }
}
