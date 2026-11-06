<?php

namespace Vortechron\FilamentCms\Entries;

use Illuminate\Validation\ValidationException;
use Vortechron\FilamentBlockEditor\Content\PageValue;
use Vortechron\FilamentCms\Documents\CmsKind;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\SiteLanguages;

/**
 * The two designs of an entry type, built in the page builder: its card
 * (how the list block draws one entry) and its single (the top of each
 * entry's own page). Documents of kind "card" and "single" with the type's
 * key as `entry_type`.
 */
final class EntryTemplates
{
    public static function find(EntryType|string $type, string $kind): ?Document
    {
        $designs = Document::query()
            ->ofKind($kind)
            ->where('entry_type', $type instanceof EntryType ? $type->key : $type)
            ->oldest('id');

        return (clone $designs)->inLanguage()->first()
            ?? (SiteLanguages::enabled() ? $designs->whereNull('translation_of')->where('shared_format', 1)->first() : null);
    }

    /**
     * The design, made empty the first time.
     */
    public static function findOrCreate(EntryType $type, string $kind): Document
    {
        abort_unless(CmsKind::tryFrom($kind)?->isEntryDesign() === true, 404);

        if ($existing = self::find($type, $kind)) {
            return $existing;
        }
        $template = new Document([
            'kind' => $kind,
            'entry_type' => $type->key,
            'title' => __("filament-cms::entries.{$kind}_title", ['type' => $type->getLabel()]),
        ]);
        if (! SiteLanguages::enabled()) {
            $template->content = ['version' => PageValue::VERSION, 'sections' => []];
            $template->save();

            return $template;
        }
        $result = app(DocumentStore::class)->save($template, ['version' => PageValue::VERSION, 'sections' => []], null, true);
        if (! $result['ok']) {
            throw ValidationException::withMessages($result['errors']);
        }

        return $template;
    }
}
