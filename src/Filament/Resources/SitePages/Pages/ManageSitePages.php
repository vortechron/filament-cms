<?php

namespace Vortechron\FilamentCms\Filament\Resources\SitePages\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Vortechron\FilamentCms\Documents\CmsKind;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Filament\Resources\SitePages\PageMetadata;
use Vortechron\FilamentCms\Filament\Resources\SitePages\SitePageResource;
use Vortechron\FilamentCms\FilamentCmsPlugin;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\PageLimit;

/**
 * The Halaman screen: the client's pages, then the header and footer every
 * page shares, each with "Bina". With a page limit (PageLimit), it says how
 * many pages are used and the new page button stops at the limit.
 */
class ManageSitePages extends ManageRecords
{
    protected static string $resource = SitePageResource::class;

    #[Locked]
    public array $metadataBaselines = [];

    #[Locked]
    public array $tableVersions = [];

    public function publicationState(SitePage $page): bool
    {
        $this->tableVersions[$page->getKey()] ??= (int) $page->edit_version;

        return (bool) $page->is_published;
    }

    public function savePublication(SitePage $page, bool $published): bool
    {
        abort_unless(SitePageResource::canEdit($page), 403);
        $result = app(DocumentStore::class)->saveMetadata($page, ['is_published' => $published], Filament::auth()->user(), expectedVersion: $this->tableVersions[$page->getKey()] ?? null);
        if (! $result['ok']) {
            $this->refuseSave($result['errors'] ?? []);
        }
        $this->tableVersions[$page->getKey()] = $result['edit_version'] ?? (int) $page->edit_version;

        return $published;
    }

    public function metadataForEdit(SitePage $page): array
    {
        $data = PageMetadata::fill($page);
        $this->metadataBaselines[$page->getKey()] = ['data' => $data, 'version' => (int) $page->edit_version];

        return $data;
    }

    public function saveMetadata(SitePage $page, array $data): SitePage
    {
        abort_unless(SitePageResource::canEdit($page), 403);
        $baseline = $this->metadataBaselines[$page->getKey()] ?? null;
        abort_if($baseline === null, 409);
        $changes = PageMetadata::changes($data, $baseline['data']);
        $result = app(DocumentStore::class)->saveMetadata($page, $changes, Filament::auth()->user(), expectedVersion: $baseline['version']);
        if (! $result['ok']) {
            $this->refuseSave($result['errors'] ?? []);
        }
        $this->metadataBaselines[$page->getKey()] = ['data' => $data, 'version' => $result['edit_version'] ?? (int) $page->edit_version];
        $this->tableVersions[$page->getKey()] = $result['edit_version'] ?? (int) $page->edit_version;

        return $page;
    }

    public function refuseSave(array $errors): never
    {
        Notification::make()->danger()->title(implode(' ', $errors))->send();
        $schemaName = $this->getMountedActionSchemaName();
        $path = $schemaName === null ? null : $this->getSchema($schemaName)?->getStatePath();
        $messages = [];
        foreach ($errors as $field => $message) {
            $messages[$path ? $path.'.'.$field : $field] = $message;
        }
        throw ValidationException::withMessages($messages);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->disabled(fn (): bool => PageLimit::reached())
                ->tooltip(fn (): ?string => PageLimit::reached() ? self::limitWords() : null)
                ->using(function (array $data): SitePage {
                    abort_unless(SitePageResource::canCreate() && ! PageLimit::reached(), 403);

                    return DB::transaction(function () use ($data): SitePage {
                        $translations = $data['translations'] ?? [];
                        unset($data['translations']);
                        $page = new SitePage($data);
                        $store = app(DocumentStore::class);
                        $result = $store->save($page, ['version' => 2, 'sections' => []], Filament::auth()->user());
                        if (! $result['ok']) {
                            throw ValidationException::withMessages($result['errors'] ?? []);
                        }
                        $changes = PageMetadata::changes(['translations' => $translations], ['translations' => array_map(fn () => ['title' => null, 'path' => null, 'is_published' => false, 'seo_title' => null, 'seo_description' => null], $translations)]);
                        if ($changes !== []) {
                            $result = $store->saveMetadata($page, $changes, Filament::auth()->user(), expectedVersion: $result['edit_version'] ?? (int) $page->edit_version);
                            if (! $result['ok']) {
                                throw ValidationException::withMessages($result['errors'] ?? []);
                            }
                        }

                        return $page;
                    });
                })
                ->successRedirectUrl(fn (SitePage $record): string => BuildSitePage::getUrl(['record' => $record])),
        ];
    }

    public function getSubheading(): ?string
    {
        return PageLimit::max() === null ? null : self::limitWords();
    }

    private static function limitWords(): string
    {
        $max = PageLimit::max();

        return PageLimit::reached()
            ? __('filament-cms::pages.limit_full', ['max' => $max])
            : __('filament-cms::pages.limit', ['used' => PageLimit::used(), 'max' => $max]);
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
            ->schema(array_map(fn (CmsKind $kind): Flex => Flex::make([
                Text::make($kind->label())->weight(FontWeight::SemiBold)->grow(),
                Actions::make([
                    Action::make("build_{$kind->value}")
                        ->label(__('filament-cms::pages.build'))
                        ->icon(Heroicon::OutlinedPaintBrush)
                        ->button()
                        ->url(BuildSitePage::getUrl(['record' => $kind->value])),
                ])->grow(false),
            ])->verticallyAlignCenter(), CmsKind::parts()));
    }
}
