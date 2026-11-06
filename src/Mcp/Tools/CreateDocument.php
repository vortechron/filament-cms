<?php

namespace Vortechron\FilamentCms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Vortechron\FilamentBlockEditor\Content\PageValue;
use Vortechron\FilamentCms\Documents\CmsKind;
use Vortechron\FilamentCms\DocumentTranslations;
use Vortechron\FilamentCms\Mcp\DocumentWriter;
use Vortechron\FilamentCms\Mcp\McpActor;
use Vortechron\FilamentCms\Mcp\Reply;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\PageLimit;

#[Name('create_document')]
#[Description('Makes a new document: a page (needs a title and a path; unpublished unless published is true), the header or footer (when the site has none yet), a synced section (kind section) or a saved section (kind template), each of those two holding exactly one rich section. A page is refused once the site has its page limit (site_overview pages.max). The value is checked and cleaned like a save in the panel and kept as the first version. Answers the new id.')]
class CreateDocument extends Tool
{
    public function handle(Request $request, DocumentWriter $writer): ResponseFactory
    {
        $kind = is_string($request->get('kind')) ? CmsKind::tryFrom($request->get('kind')) : null;

        if ($kind?->mcpCreatable() !== true) {
            return Reply::refuse('kind', 'The kind must be one of: '.implode(', ', self::kinds()).'.');
        }

        if ($request->get('translation_of') !== null) {
            $id = $request->get('translation_of');
            $source = (is_int($id) || (is_string($id) && ctype_digit($id))) ? Document::find((int) $id) : null;
            if ($source === null || $source->kind !== $kind->value || ! is_string($request->get('locale'))) {
                return Reply::refuse('translation_of', 'Give a document of the same kind and a configured locale.');
            }
            try {
                $copy = app(DocumentTranslations::class)->create($source, $request->get('locale'), McpActor::user());
            } catch (ValidationException $exception) {
                return Reply::refused(array_map(fn (array $messages): string => $messages[0], $exception->errors()));
            }

            return Reply::fromResult(['ok' => true], ['id' => $copy->getKey(), 'url' => $copy->url()]);
        }

        $existing = $kind->isPart() ? Document::firstOfKind($kind->value) : null;

        if ($existing !== null) {
            return Reply::refuse('kind', "This site already has a {$kind->value} (document {$existing->getKey()}). Change it with save_document.");
        }

        if ($kind === CmsKind::Page && PageLimit::reached()) {
            return Reply::refuse('kind', 'This site may have at most '.PageLimit::max().' pages (its package), and it has '.PageLimit::used().'. '
                .'Reuse or delete a page instead; a developer raises the limit with CMS_MAX_PAGES (filament-cms.max_pages).');
        }

        $value = DocumentWriter::value($request->all());

        if (is_string($value)) {
            return Reply::refuse('value', $value);
        }

        $document = $kind === CmsKind::Page ? new SitePage : new Document(['kind' => $kind->value]);
        $meta = $request->all(['title', 'path', 'published', 'seo']);

        if ($kind->isPart()) {
            $meta['title'] ??= $kind->label();
        }

        $result = $writer->save($document, $value ?? ['version' => PageValue::VERSION, 'sections' => []], $meta);

        return Reply::fromResult($result, fn (): array => ['id' => $document->getKey()]);
    }

    /**
     * @return list<string>
     */
    private static function kinds(): array
    {
        return CmsKind::values(fn (CmsKind $kind): bool => $kind->mcpCreatable());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->enum(self::kinds())->required()->description('page, header, footer, section (synced) or template (saved).'),
            'translation_of' => $schema->integer()->description('Copy this existing document into a linked draft. Uses the source content and does not consume another page allowance. Read/save the returned id to edit it.'),
            'locale' => $schema->string()->description('Configured target language, required with translation_of.'),
            'title' => $schema->string()->description('The title, at most 120 characters. Needed except for the header and footer.'),
            'path' => $schema->string()->description('Pages only, and needed for them: the address, such as "/tentang".'),
            'published' => $schema->boolean()->description('Pages only. Default false.'),
            'value' => $schema->object()->description('The page value: {"version": 2, "sections": [...]} (see page_format). Default: no sections.'),
            'seo' => $schema->object([
                'title' => $schema->string(),
                'description' => $schema->string(),
            ])->description('Pages only: the search engine title (70) and description (170).'),
        ];
    }
}
