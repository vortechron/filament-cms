<?php

namespace Vortechron\FilamentCms\Settings\Sections;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Vortechron\FilamentCms\CmsImage;
use Vortechron\FilamentCms\Settings\SettingsSection;

class SeoSettings extends SettingsSection
{
    public function label(): string
    {
        return __('filament-cms::settings.seo');
    }

    public function description(): ?string
    {
        return __('filament-cms::settings.seo_help');
    }

    public function fields(): array
    {
        return [
            TextInput::make('title')->label(__('filament-cms::settings.seo_title'))->maxLength(70),
            Textarea::make('description')->label(__('filament-cms::settings.seo_description'))->rows(2)->maxLength(170),
            CmsImage::make('share_image')->label(__('filament-cms::settings.share_image')),
            Toggle::make('allow_indexing')->label(__('filament-cms::settings.allow_indexing')),
        ];
    }

    public function defaults(): array
    {
        return ['allow_indexing' => false];
    }
}
