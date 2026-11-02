<?php

namespace Vortechron\FilamentCms\Filament\Resources\SitePages\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Vortechron\FilamentCms\Filament\Resources\SitePages\SitePageResource;
use Vortechron\FilamentCms\Models\SitePage;

class ManageSitePages extends ManageRecords
{
    protected static string $resource = SitePageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(fn (array $data): array => [...$data, 'sections' => []])
                ->successRedirectUrl(fn (SitePage $record): string => BuildSitePage::getUrl(['record' => $record])),
        ];
    }
}
