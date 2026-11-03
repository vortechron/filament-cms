<?php

namespace Vortechron\FilamentCms\Filament\Resources\SitePages\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Vortechron\FilamentCms\Filament\Resources\SitePages\SitePageResource;
use Vortechron\FilamentCms\FilamentCmsPlugin;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;

/**
 * The Halaman screen: the client's pages, then the header and footer every
 * page shares, each with "Bina".
 */
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

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedSchema::make('pagesTable'), $this->partsArea()]);
    }

    /**
     * The list screen's own content: the table of pages.
     */
    public function pagesTable(Schema $schema): Schema
    {
        return parent::content($schema);
    }

    /**
     * One row each for the header and footer. Opening one makes it the first
     * time.
     */
    protected function partsArea(): Section
    {
        return Section::make(__('filament-cms::documents.parts'))
            ->description(__('filament-cms::documents.parts_help'))
            ->visible(fn (): bool => FilamentCmsPlugin::get()->hasParts() && BuildSitePage::mayBuild(null))
            ->schema(array_map(fn (string $kind): Flex => Flex::make([
                Text::make(__("filament-cms::documents.{$kind}"))->weight(FontWeight::SemiBold)->grow(),
                Actions::make([
                    Action::make("build_{$kind}")
                        ->label(__('filament-cms::pages.build'))
                        ->icon(Heroicon::OutlinedPaintBrush)
                        ->button()
                        ->url(BuildSitePage::getUrl(['record' => $kind])),
                ])->grow(false),
            ])->verticallyAlignCenter(), Document::PARTS));
    }
}
