<?php

namespace Vortechron\FilamentCms\Filament\Resources\SiteSections\Pages;

use Filament\Resources\Pages\ManageRecords;
use Vortechron\FilamentCms\Filament\Resources\SiteSections\SiteSectionResource;

/**
 * Saved sections are made in the page builder, so this screen has no "new"
 * button.
 */
class ManageSiteSections extends ManageRecords
{
    protected static string $resource = SiteSectionResource::class;

    public function getTitle(): string
    {
        return __('filament-cms::sections.sections');
    }
}
