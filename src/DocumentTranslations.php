<?php

namespace Vortechron\FilamentCms;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Vortechron\FilamentCms\Models\Document;

/** Copies a document once into another language. Subsequent calls never overwrite it. */
final class DocumentTranslations
{
    public function create(Document $source, string $locale, ?Authenticatable $user = null): Document
    {
        SiteLanguages::check($locale);
        if (! SiteLanguages::enabled() || in_array($source->kind, [Document::KIT, Document::ENTRY], true)) {
            throw ValidationException::withMessages(['locale' => 'This document does not have separate language versions.']);
        }

        return DB::transaction(function () use ($source, $locale, $user): Document {
            $root = Document::query()->lockForUpdate()->findOrFail($source->translationRootId());
            if ($existing = $root->translation($locale)) {
                return $existing;
            }

            $source = $source->fresh();
            $copy = $source->replicate(['path', 'is_published', 'locale', 'translation_of']);
            $copy->locale = $locale;
            $copy->translation_of = $root->getKey();
            $copy->is_published = false;
            $copy->path = $source->path === null ? null : SiteLanguages::path($source->path, $locale);
            if ($copy->path !== null && Document::pathTaken($copy->path)) {
                throw ValidationException::withMessages(['path' => 'Another page already has the translated address.']);
            }

            $result = app(DocumentStore::class)->save($copy, $source->content ?? [], $user, developer: true);
            if (! $result['ok']) {
                throw ValidationException::withMessages($result['errors']);
            }

            return $copy;
        });
    }
}
