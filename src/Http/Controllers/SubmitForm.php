<?php

namespace Vortechron\FilamentCms\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Vortechron\FilamentCms\Documents\CmsKind;
use Vortechron\FilamentCms\Forms\FormSubmissionHandler;
use Vortechron\FilamentCms\Models\Document;

/**
 * POST /_cms/forms/{document}: a visitor sends a form block on a published
 * page or entry. Anything else is a 404.
 */
class SubmitForm
{
    public function __invoke(Request $request, string $document, FormSubmissionHandler $handler): Response
    {
        abort_unless(ctype_digit($document), 404);

        $model = Document::query()
            ->whereIn('kind', CmsKind::values(fn (CmsKind $kind): bool => $kind->isRoutable()))
            ->published()
            ->whereKey((int) $document)
            ->firstOrFail();

        return $handler->handle($model, $request);
    }
}
