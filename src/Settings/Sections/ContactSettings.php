<?php

namespace Vortechron\FilamentCms\Settings\Sections;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\CmsUrl;
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
            CmsUrl::make('map_url')->label(__('filament-cms::settings.map_url')),
        ];
    }

    public function builderFields(): array
    {
        return [
            Field::list('phones')
                ->label(__('filament-cms::settings.phones'))
                ->maxItems(4)
                ->fields([Field::text('number')->label(__('filament-cms::settings.number'))]),
            Field::text('email')->label(__('filament-cms::settings.email')),
            Field::textarea('address')->label(__('filament-cms::settings.address')),
            Field::link('map_url')->label(__('filament-cms::settings.map_url')),
        ];
    }

    public function builderRules(): array
    {
        return [
            'phones' => ['nullable', 'array', 'max:4'],
            'phones.*' => ['array'],
            'phones.*.number' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:300'],
            'map_url' => ['nullable', 'string', 'url:http,https', 'max:500'],
        ];
    }

    /**
     * The Tetapan screen saves phones as a plain list of numbers; the
     * builder's list holds rows of `['number' => ...]`.
     */
    public function toBuilderValues(array $values): array
    {
        $phones = is_array($values['phones'] ?? null) ? $values['phones'] : [];

        return [...$values, 'phones' => array_values(array_map(fn (mixed $phone): array => ['number' => is_array($phone) ? ($phone['number'] ?? '') : $phone], $phones))];
    }

    public function fromBuilderValues(array $values): array
    {
        $phones = is_array($values['phones'] ?? null) ? $values['phones'] : [];

        return [...$values, 'phones' => array_values(array_map(fn (array $phone): string => trim((string) ($phone['number'] ?? '')), $phones))];
    }
}
