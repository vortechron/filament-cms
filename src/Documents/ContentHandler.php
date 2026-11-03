<?php

namespace Vortechron\FilamentCms\Documents;

use Closure;
use Vortechron\FilamentCms\Models\Document;

/**
 * How DocumentStore checks and cleans the content of one kind of document
 * (CmsKind::content()): a page value, the design kit, or an entry's fields
 * and sections. The store runs merge(), then errors(), then, inside its
 * transaction, clean(), and saves what clean() returns as a version.
 */
interface ContentHandler
{
    /**
     * The value to check: what was sent, with the stored parts it keeps.
     *
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    public function merge(Document $document, array $value): array;

    /**
     * What is wrong with the merged value: the first message by key, or [].
     *
     * @param  array<mixed>  $value
     * @return array<string, string>
     */
    public function errors(Document $document, array $value): array;

    /**
     * The content to store: the merged value cleaned, and when the person
     * saving is not a developer, with the stored locks and developer-only
     * data put back (PageValue::guard()).
     *
     * @param  array<mixed>  $value  a merged value without errors
     * @param  Closure(): array<mixed>  $stored  what is stored now, read (and locked) when needed
     * @return array<mixed>
     */
    public function clean(Document $document, array $value, Closure $stored, bool $developer): array;

    /**
     * What a saved version brings back when it is restored.
     *
     * @param  array<mixed>  $content  the version's content
     * @return array<mixed>
     */
    public function restored(array $content): array;
}
