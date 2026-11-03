<?php

namespace Vortechron\FilamentCms\Forms;

use Illuminate\Support\Facades\Route;
use Vortechron\FilamentBlockEditor\Forms\FormEndpoint;
use Vortechron\FilamentBlockEditor\Rendering\RenderContext;

/**
 * Where a form block sends to: the submit route of the document being
 * drawn (`document.id` in the RenderContext values, which SitePageRenderer
 * sets). A form in the header, the footer or a synced section posts to the
 * page it is shown on. Without a document, or when the host did not call
 * FilamentCms::routes(), there is no endpoint and the button is disabled.
 */
final class CmsFormEndpoint implements FormEndpoint
{
    public const VALUE = 'document.id';

    public const ROUTE = 'filament-cms.forms.submit';

    public function action(RenderContext $context): ?string
    {
        $id = $context->values[self::VALUE] ?? null;

        if (! is_scalar($id) || ! ctype_digit((string) $id) || ! Route::has(self::ROUTE)) {
            return null;
        }

        return route(self::ROUTE, ['document' => (int) $id]);
    }

    public function hiddenFields(RenderContext $context): array
    {
        return [];
    }
}
