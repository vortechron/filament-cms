<?php

namespace Workbench\App\Cms;

use Filament\Forms\Components\TextInput;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\Settings\SettingsSection;
use Vortechron\FilamentCms\Settings\ShopValue;

/**
 * A project's own settings section: one field, one shop info field and one
 * short code, all declared in this class.
 */
class ClinicSection extends SettingsSection
{
    public function label(): string
    {
        return 'Klinik';
    }

    public function fields(): array
    {
        return [TextInput::make('doctor')->maxLength(120)];
    }

    public function builderFields(): array
    {
        return [Field::text('doctor')->label('Doktor bertugas')];
    }

    public function shopValues(): array
    {
        return [ShopValue::text('site.doctor_on_duty', 'Doktor bertugas', fn (): mixed => $this->setting('doctor'))];
    }
}
