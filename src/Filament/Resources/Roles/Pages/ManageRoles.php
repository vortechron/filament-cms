<?php

namespace Vortechron\FilamentCms\Filament\Resources\Roles\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Vortechron\FilamentCms\Access\CmsRoles;
use Vortechron\FilamentCms\Filament\Resources\Roles\RoleResource;

class ManageRoles extends ManageRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->after(fn () => CmsRoles::forget())];
    }
}
