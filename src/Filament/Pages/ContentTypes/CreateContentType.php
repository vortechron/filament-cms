<?php

namespace Vortechron\FilamentCms\Filament\Pages\ContentTypes;

use Filament\Pages\Page;
use Filament\Panel;

/**
 * A new content type, /admin/content-types/create.
 */
class CreateContentType extends Page
{
    use EditsContentType;

    protected static ?string $slug = 'content-types/create';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function getTitle(): string
    {
        return __('filament-cms::types.add');
    }

    protected function savedKey(): ?string
    {
        return null;
    }

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'content-types.create';
    }
}
