<?php

namespace Vortechron\FilamentCms\Settings\Sections;

use Filament\Forms\Components\TextInput;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\CmsImage;
use Vortechron\FilamentCms\Settings\SettingsSection;
use Vortechron\FilamentCms\Settings\ShopValue;

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
            TextInput::make('legal_name')->label(__('filament-cms::settings.legal_name'))->maxLength(160),
            TextInput::make('registration_number')->label(__('filament-cms::settings.registration_number'))->maxLength(40),
            CmsImage::make('logo')->label(__('filament-cms::settings.logo'))->helperText(__('filament-cms::settings.logo_help')),
        ];
    }

    public function builderFields(): array
    {
        return [
            Field::text('name')->label(__('filament-cms::settings.name')),
            Field::text('tagline')->label(__('filament-cms::settings.tagline')),
            Field::text('legal_name')->label(__('filament-cms::settings.legal_name')),
            Field::text('registration_number')->label(__('filament-cms::settings.registration_number')),
            Field::image('logo')->label(__('filament-cms::settings.logo')),
        ];
    }

    public function builderRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'legal_name' => ['nullable', 'string', 'max:160'],
            'registration_number' => ['nullable', 'string', 'max:40'],
            'logo' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function shopValues(): array
    {
        return [
            ShopValue::text('site.name', __('filament-cms::settings.name'), fn (): mixed => $this->setting('name')),
            ShopValue::text('site.legal_name', __('filament-cms::settings.legal_name'), fn (): mixed => $this->setting('legal_name')),
            ShopValue::text('site.registration', __('filament-cms::settings.registration_number'), fn (): mixed => $this->setting('registration_number')),
            ShopValue::image('site.logo', __('filament-cms::settings.logo'), fn (): ?string => cms_image($this->setting('logo'))),
        ];
    }
}
