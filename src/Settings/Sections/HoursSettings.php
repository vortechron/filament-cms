<?php

namespace Vortechron\FilamentCms\Settings\Sections;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Vortechron\FilamentCms\Settings\SettingsSection;

class HoursSettings extends SettingsSection
{
    public function label(): string
    {
        return __('filament-cms::settings.hours');
    }

    public function fields(): array
    {
        return [
            Repeater::make('days')
                ->hiddenLabel()
                ->schema([
                    TextInput::make('days')->label(__('filament-cms::settings.days'))->placeholder('Isnin - Jumaat')->required()->maxLength(60),
                    TextInput::make('time')->label(__('filament-cms::settings.time'))->placeholder('9 pagi - 6 petang')->required()->maxLength(60),
                ])
                ->columns(2)
                ->maxItems(7)
                ->addActionLabel(__('filament-cms::settings.add_hours')),
            TextInput::make('note')->label(__('filament-cms::settings.hours_note'))->maxLength(160),
        ];
    }
}
