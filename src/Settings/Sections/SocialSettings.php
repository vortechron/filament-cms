<?php

namespace Vortechron\FilamentCms\Settings\Sections;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Illuminate\Validation\Rule;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\CmsUrl;
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
                    CmsUrl::make('url')->label(__('filament-cms::settings.url'))->required(),
                ])
                ->columns(2)
                ->maxItems(8)
                ->addActionLabel(__('filament-cms::settings.add_link')),
        ];
    }

    public function builderFields(): array
    {
        return [
            Field::list('links')
                ->label(__('filament-cms::settings.social'))
                ->maxItems(8)
                ->fields([
                    Field::select('platform')->label(__('filament-cms::settings.platform'))->options(self::PLATFORMS),
                    Field::link('url')->label(__('filament-cms::settings.url')),
                ]),
        ];
    }

    public function builderRules(): array
    {
        return [
            'links' => ['nullable', 'array', 'max:8'],
            'links.*' => ['array'],
            'links.*.platform' => ['required', 'string', Rule::in(array_keys(self::PLATFORMS))],
            'links.*.url' => ['required', 'string', 'url:http,https', 'max:500'],
        ];
    }
}
