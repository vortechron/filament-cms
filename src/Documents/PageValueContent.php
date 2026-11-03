<?php

namespace Vortechron\FilamentCms\Documents;

use Closure;
use Vortechron\FilamentBlockEditor\Content\PageContent;
use Vortechron\FilamentBlockEditor\Content\PageValue;
use Vortechron\FilamentCms\Models\Document;

/**
 * A page value (`['version' => 2, 'sections' => [...]]`) of any format:
 * checked with PageValue::rules(), upgraded and cleaned. A synced or saved
 * section holds one rich section or none.
 */
final class PageValueContent implements ContentHandler
{
    public function merge(Document $document, array $value): array
    {
        return $value;
    }

    public function errors(Document $document, array $value): array
    {
        $upgraded = PageValue::upgrade($value);
        $errors = ValidationErrors::of('value', $upgraded, PageValue::rules('value'));

        if ($errors === [] && $document->cmsKind()?->holdsOneSection() === true) {
            $sections = $upgraded['sections'];

            if (count($sections) > 1 || (isset($sections[0]) && ($sections[0]['type'] ?? null) !== PageContent::TYPE)) {
                $errors['sections'] = __('filament-cms::documents.one_section');
            }
        }

        return $errors;
    }

    public function clean(Document $document, array $value, Closure $stored, bool $developer): array
    {
        $clean = PageValue::normalize($value);

        return $developer ? $clean : PageValue::guard($clean, PageValue::normalize($stored()));
    }

    public function restored(array $content): array
    {
        return $content;
    }
}
