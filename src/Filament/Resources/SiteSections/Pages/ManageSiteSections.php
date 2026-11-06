<?php

namespace Vortechron\FilamentCms\Filament\Resources\SiteSections\Pages;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Exceptions\Halt;
use Livewire\Attributes\Locked;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\BuildSitePage;
use Vortechron\FilamentCms\Filament\Resources\SiteSections\SiteSectionResource;
use Vortechron\FilamentCms\Models\Document;

/**
 * Saved sections are made in the page builder, so this screen has no "new"
 * button.
 */
class ManageSiteSections extends ManageRecords
{
    protected static string $resource = SiteSectionResource::class;

    #[Locked]
    public array $editVersions = [];

    public function rememberVersion(Document $document): array
    {
        $this->editVersions[$document->getKey()] = (int) $document->edit_version;

        return ['title' => $document->title];
    }

    public function rename(Document $document, array $data): Document
    {
        abort_unless(SiteSectionResource::canEdit($document) && BuildSitePage::mayBuild($document), 403);
        $result = app(DocumentStore::class)->saveMetadata($document, ['title' => $data['title']], Filament::auth()->user(), expectedVersion: $this->editVersions[$document->getKey()] ?? null);
        if (! $result['ok']) {
            Notification::make()->danger()->title(implode(' ', $result['errors'] ?? []))->send();
            throw new Halt;
        }

        return $document;
    }

    public function getTitle(): string
    {
        return __('filament-cms::sections.sections');
    }
}
