<?php

namespace Vortechron\FilamentCms\Tests\Feature\Mcp;

use PHPUnit\Framework\Attributes\DataProvider;
use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentBlockEditor\Blocks\BlockRegistry;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentBlockEditor\Blocks\OwnBlock;
use Vortechron\FilamentBlockEditor\Rendering\ContentLocale;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\DocumentTranslations;
use Vortechron\FilamentCms\Mcp\Tools\GetDocument;
use Vortechron\FilamentCms\Mcp\Tools\RestoreVersion;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\SitePage;

class TranslatedRestoreLocaleTest extends McpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['filament-cms.locales' => ['ms' => 'Malay', 'en' => 'English'], 'filament-cms.default_locale' => 'ms', 'filament-block-editor.content_locale' => 'ms']);
        app('view')->addNamespace('restore-locale', __DIR__.'/../../Fixtures/views');
        $this->registerService(false);
    }

    private function registerService(bool $withProtectedField): void
    {
        $fields = [
            Field::text('title')->default(fn (): string => ContentLocale::site() === 'en' ? 'Our services' : 'Servis kami'),
            Field::text('details'),
        ];
        if ($withProtectedField) {
            $fields[] = Field::text('owner_text')->developerOnly()->default(fn (): string => ContentLocale::site() === 'en' ? 'English protected default' : 'Malay protected default');
        }
        app(BlockRegistry::class)->register(OwnBlock::make('restore-service-card')->fields($fields)->view('restore-locale::restore-service'));
    }

    private function value(array $values): array
    {
        return ['version' => 2, 'sections' => [['id' => 'sec00001', 'type' => 'rich', 'data' => ['document' => [
            ['id' => 'service1', ...Blocks::ownBlock('restore-service-card', $values)],
        ]]]]];
    }

    public static function writers(): array
    {
        return [
            'Malay panel client' => ['ms', 'client'], 'English panel client' => ['en', 'client'],
            'Malay panel developer' => ['ms', 'developer'], 'English panel developer' => ['en', 'developer'],
            'Malay panel MCP' => ['ms', 'mcp'], 'English panel MCP' => ['en', 'mcp'],
        ];
    }

    #[DataProvider('writers')]
    public function test_a_legacy_english_restore_uses_english_defaults_and_restores_the_callers_locale(string $panel, string $writer): void
    {
        app()->setLocale($panel);
        $page = SitePage::create(['title' => 'Home', 'path' => '/', 'is_published' => true, 'content' => $this->value(['title' => 'Servis kami', 'details' => 'Maklumat asal'])]);
        $en = SitePage::create(['title' => 'English', 'path' => '/en', 'locale' => 'en', 'translation_of' => $page->id, 'is_published' => true, 'content' => $this->value(['title' => 'Current English services', 'details' => 'Current English details'])]);
        $this->assertTrue(app(DocumentTranslations::class)->convert($page)['ok']);
        $old = $this->value(['details' => 'Archived English details']);
        $revision = CmsRevision::create(['key' => $en->revisionKey(), 'content' => $old]);
        // A newly declared protected field exercises locale-dependent guard defaults too.
        $this->registerService(true);

        $result = $writer === 'mcp'
            ? $this->runTool(RestoreVersion::class, ['document_id' => $en->id, 'version_id' => $revision->id, 'expected_version' => 1])
            : app(DocumentStore::class)->restore($en, $revision, null, $writer === 'developer', 1);

        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertSame('ms', ContentLocale::site());
        $this->assertSame($panel, ContentLocale::panel());
        $this->assertSame($panel, app()->getLocale());
        $this->assertSame(2, $result['edit_version']);
        $this->get('/en')->assertOk()->assertSee('Our services')->assertSee('Archived English details')->assertSee('English protected default')->assertDontSee('Servis kami')->assertDontSee('Malay protected default');
        $this->get('/')->assertOk()->assertSee('Servis kami')->assertSee('Maklumat asal');
        $this->assertSame($old, $revision->fresh()->content);
    }

    #[DataProvider('writers')]
    public function test_refused_legacy_restore_resets_the_locale_and_keeps_owner_errors_in_the_panel_language(string $panel, string $writer): void
    {
        app()->setLocale($panel);
        $page = SitePage::create(['title' => 'Home', 'path' => '/', 'is_published' => true, 'content' => $this->value(['title' => 'Servis kami', 'details' => 'Maklumat asal'])]);
        $en = SitePage::create(['title' => 'English', 'path' => '/en', 'locale' => 'en', 'translation_of' => $page->id, 'is_published' => true, 'content' => $this->value(['title' => 'Current English services', 'details' => 'Current English details'])]);
        $this->assertTrue(app(DocumentTranslations::class)->convert($page)['ok']);
        $old = $this->value(['details' => 'Archived English details']);
        $old['sections'][0]['data']['padding'] = 'xl';
        $revision = CmsRevision::create(['key' => $en->revisionKey(), 'content' => $old]);
        $before = $this->runTool(GetDocument::class, ['id' => $page->id]);

        $result = $writer === 'mcp'
            ? $this->runTool(RestoreVersion::class, ['document_id' => $en->id, 'version_id' => $revision->id, 'expected_version' => 1])
            : app(DocumentStore::class)->restore($en, $revision, null, $writer === 'developer', 1);

        $this->assertFalse($result['ok']);
        $this->assertSame(__('filament-cms::languages.restore_translation', [], $panel), $result['errors']['revision']);
        $this->assertSame('ms', ContentLocale::site());
        $this->assertSame($panel, ContentLocale::panel());
        $this->assertSame($panel, app()->getLocale());
        $this->assertSame($before, $this->runTool(GetDocument::class, ['id' => $page->id]));
        $this->get('/en')->assertSee('Current English services')->assertSee('Current English details');
        $this->assertSame($old, $revision->fresh()->content);
    }
}
