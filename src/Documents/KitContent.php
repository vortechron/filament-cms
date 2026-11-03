<?php

namespace Vortechron\FilamentCms\Documents;

use Closure;
use Vortechron\FilamentBlockEditor\Design\Kit;
use Vortechron\FilamentCms\Models\Document;

/**
 * The design kit: checked with Kit::rules() and cleaned with
 * Kit::normalize(). It has no locks, so a developer and a client save it
 * the same way.
 */
final class KitContent implements ContentHandler
{
    public function merge(Document $document, array $value): array
    {
        return $value;
    }

    public function errors(Document $document, array $value): array
    {
        return ValidationErrors::of('kit', $value, Kit::rules('kit'));
    }

    public function clean(Document $document, array $value, Closure $stored, bool $developer): array
    {
        return Kit::normalize($value);
    }

    public function restored(array $content): array
    {
        return $content;
    }
}
