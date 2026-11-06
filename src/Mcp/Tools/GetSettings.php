<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Vortechron\FilamentCms\CmsSettingsTranslations;
use Vortechron\FilamentCms\Mcp\Reply;
use Vortechron\FilamentCms\Mcp\SettingsInfo;
use Vortechron\FilamentCms\SiteLanguages;

#[Name('get_settings')]
#[Description('The site settings (business, contact, hours, social, seo, and any the site adds), each section with its fields and its values (saved values over the defaults), in the shape save_settings takes. Pages show them through dynamic values such as site.phone; own block text fields may hold {key} short codes, and the dynamic_values of site_overview list the keys. A picture (the logo) is a path on the site\'s disk. "editable": false means only the panel can change that section.')]
#[IsReadOnly]
class GetSettings extends Tool
{
    public function handle(Request $request, SettingsInfo $settings): ResponseFactory
    {
        $locale = $request->get('locale');
        if ($locale !== null) {
            if (! is_string($locale) || ! isset(SiteLanguages::labels()[$locale])) {
                return Reply::refuse('locale', 'Choose a configured website language.');
            }

            return SiteLanguages::run($locale, fn (): ResponseFactory => Reply::read(['locale' => $locale, 'sections' => $settings->full(), 'translations' => CmsSettingsTranslations::saved($locale)]));
        }

        return Reply::read(['sections' => $settings->full()]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['locale' => $schema->string()->description('Optional configured content language. Omit for the original settings.')];
    }
}
