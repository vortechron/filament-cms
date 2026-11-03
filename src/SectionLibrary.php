<?php

namespace Vortechron\FilamentCms;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Vortechron\FilamentBlockEditor\Content\PageContent;
use Vortechron\FilamentBlockEditor\Content\PageValue;
use Vortechron\FilamentCms\Documents\CmsKind;
use Vortechron\FilamentCms\Models\Document;

/**
 * Sections kept on their own: synced sections (kind "section", one section
 * many pages show through a `ref`) and saved sections (kind "template", a
 * copy the builder's add panel offers under the category "saved").
 */
final class SectionLibrary
{
    /**
     * The builder's category of saved sections.
     */
    public const CATEGORY = 'saved';

    public function __construct(private readonly DocumentStore $store) {}

    /**
     * Saves one cleaned section as a new synced section or template, through
     * the store. The result is what the builder's saveSection() expects.
     *
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    public function save(array $section, string $title, bool $synced, ?Authenticatable $user, bool $developer): array
    {
        $document = new Document(['kind' => $synced ? Document::SECTION : Document::TEMPLATE, 'title' => $title]);
        $result = $this->store->save($document, ['version' => PageValue::VERSION, 'sections' => [$section]], $user, $developer);

        if (! $result['ok']) {
            return $result;
        }

        return ['ok' => true, 'id' => $document->getKey(), 'template' => $synced ? null : self::template($document)];
    }

    /**
     * @return Collection<int, Document>
     */
    public function synced(): Collection
    {
        return Document::query()->ofKind(Document::SECTION)->orderBy('title')->get()
            ->filter(fn (Document $document): bool => self::firstSection($document) !== null)
            ->values();
    }

    /**
     * The saved sections, as entries of the builder's templates.
     *
     * @return list<array<string, mixed>>
     */
    public function templates(): array
    {
        return Document::query()->ofKind(Document::TEMPLATE)->orderBy('title')->get()
            ->map(fn (Document $document): ?array => self::template($document))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The documents that show this synced section.
     *
     * @return Collection<int, Document>
     */
    public function usedBy(Document $synced): Collection
    {
        return Document::query()->whereIn('kind', CmsKind::values(fn (CmsKind $kind): bool => $kind->mayShowSynced()))->orderBy('title')->lazy()
            ->filter(fn (Document $document): bool => collect($document->contentSections())
                ->contains(fn (array $section): bool => $section['type'] === PageContent::REF && (string) $section['ref'] === (string) $synced->getKey()))
            ->values()
            ->collect();
    }

    /**
     * The one rich section a synced or saved section holds, or null.
     *
     * @return array<string, mixed>|null
     */
    public static function firstSection(Document $document): ?array
    {
        $section = $document->contentSections()[0] ?? null;

        return ($section['type'] ?? null) === PageContent::TYPE ? $section : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function template(Document $document): ?array
    {
        $section = self::firstSection($document);

        return $section === null ? null : [
            'key' => 'saved-'.$document->getKey(),
            'label' => (string) $document->title,
            'category' => self::CATEGORY,
            'description' => '',
            'section' => $section,
        ];
    }
}
