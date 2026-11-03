<?php

namespace Vortechron\FilamentCms\Settings\Sections;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\Settings\SettingsSection;
use Vortechron\FilamentCms\Settings\ShopValue;

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

    public function builderFields(): array
    {
        return [
            Field::list('days')
                ->label(__('filament-cms::settings.hours'))
                ->maxItems(7)
                ->fields([
                    Field::text('days')->label(__('filament-cms::settings.days')),
                    Field::text('time')->label(__('filament-cms::settings.time')),
                ]),
            Field::text('note')->label(__('filament-cms::settings.hours_note')),
        ];
    }

    public function builderRules(): array
    {
        return [
            'days' => ['nullable', 'array', 'max:7'],
            'days.*' => ['array'],
            'days.*.days' => ['required', 'string', 'max:60'],
            'days.*.time' => ['required', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:160'],
        ];
    }

    public function shopValues(): array
    {
        return [
            ShopValue::text('site.hours', __('filament-cms::settings.hours'), fn (): string => implode("\n", $this->lines())),
            ShopValue::text('site.hours_note', __('filament-cms::values.hours_note'), fn (): mixed => $this->setting('note')),
        ];
    }

    /**
     * Each opening hours row as "days: time".
     *
     * @return list<string>
     */
    private function lines(): array
    {
        $rows = $this->setting('days');
        $lines = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $days = is_array($row) && is_string($row['days'] ?? null) ? trim($row['days']) : '';
            $time = is_array($row) && is_string($row['time'] ?? null) ? trim($row['time']) : '';

            if ($days !== '' || $time !== '') {
                $lines[] = $days !== '' && $time !== '' ? "{$days}: {$time}" : $days.$time;
            }
        }

        return $lines;
    }
}
