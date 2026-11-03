<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\BuildSitePage;
use Vortechron\FilamentCms\Filament\Resources\SitePages\SitePageResource;
use Vortechron\FilamentCms\Filament\Resources\SiteSections\SiteSectionResource;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\TestCase;

class BuilderKindsTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function section(string $id, string $text): array
    {
        return ['id' => $id, 'type' => 'rich', 'data' => ['document' => [Blocks::paragraph($text)]]];
    }

    private function document(string $kind, string $title = 'Dokumen'): Document
    {
        return Document::query()->create(['kind' => $kind, 'title' => $title, 'content' => ['version' => 2, 'sections' => []]]);
    }

    private function home(): SitePage
    {
        return SitePage::query()->create(['title' => 'Utama', 'path' => '/', 'is_published' => true, 'sections' => [$this->section('home0001', 'Isi utama')]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function builderOptions(Document $document): array
    {
        return Livewire::test(BuildSitePage::class, ['record' => $document->getKey()])->instance()->getPageBuilderOptions();
    }

    /**
     * Each stored kind the builder opens, and the builder kind it opens it as.
     * An entry's body has its own tests (EntryBuildersTest): it needs a registered type.
     *
     * @return array<string, array{string, string}>
     */
    public static function buildableKinds(): array
    {
        return [
            'page' => ['page', 'page'],
            'header' => ['header', 'header'],
            'footer' => ['footer', 'footer'],
            'synced section' => ['section', 'section'],
            'saved section' => ['template', 'section'],
            'card' => ['card', 'card'],
            'single' => ['single', 'single'],
        ];
    }

    #[DataProvider('buildableKinds')]
    public function test_the_builder_opens_for_every_kind_it_builds(string $kind, string $builderKind): void
    {
        $this->actingAsClient();
        $document = $kind === Document::PAGE ? $this->home() : $this->document($kind);

        $this->get(BuildSitePage::getUrl(['record' => $document]))->assertOk()->assertSee('mountPageBuilder', false);
        $this->assertSame($builderKind, $this->builderOptions($document)['kind']);
    }

    public function test_kinds_the_builder_does_not_build_are_not_found(): void
    {
        $this->actingAsClient();

        $this->get(BuildSitePage::getUrl(['record' => $this->document(Document::KIT)]))->assertNotFound();
    }

    public function test_the_header_and_footer_are_made_when_first_built(): void
    {
        $this->actingAsClient();

        $this->get(BuildSitePage::getUrl(['record' => 'header']))->assertOk();
        $this->get(BuildSitePage::getUrl(['record' => 'header']))->assertOk();
        $this->get(BuildSitePage::getUrl(['record' => 'footer']))->assertOk();

        $this->assertSame(1, Document::query()->ofKind(Document::HEADER)->count());
        $this->assertSame(1, Document::query()->ofKind(Document::FOOTER)->count());
    }

    public function test_title_back_and_view_links_follow_the_kind(): void
    {
        $this->actingAsClient();
        $home = $this->home();
        $draft = SitePage::query()->create(['title' => 'Draf', 'path' => '/draf', 'sections' => []]);
        $header = Document::findOrCreatePart(Document::HEADER);

        $page = $this->builderOptions($home);
        $this->assertSame('Utama', $page['title']);
        $this->assertSame($home->url(), $page['viewUrl']);
        $this->assertSame(SitePageResource::getUrl(), $page['backUrl']);

        $this->assertArrayNotHasKey('viewUrl', $this->builderOptions($draft));

        $synced = $this->builderOptions($this->document(Document::SECTION, 'Promosi'));
        $this->assertSame('Promosi', $synced['title']);
        $this->assertSame(SiteSectionResource::getUrl(), $synced['backUrl']);

        $part = $this->builderOptions($header);
        $this->assertSame('Header', $part['title']);
        $this->assertArrayNotHasKey('viewUrl', $part);
        $this->assertSame(SitePageResource::getUrl(), $part['backUrl']);
    }

    public function test_someone_who_may_not_edit_pages_cannot_build_any_kind(): void
    {
        $header = Document::findOrCreatePart(Document::HEADER);
        $synced = $this->document(Document::SECTION);
        Gate::policy(SitePage::class, DenyEditingPolicy::class);
        $this->actingAsClient();

        $this->get(BuildSitePage::getUrl(['record' => $header]))->assertForbidden();
        $this->get(BuildSitePage::getUrl(['record' => $synced]))->assertForbidden();
        $this->get(BuildSitePage::getUrl(['record' => 'footer']))->assertForbidden();
        Livewire::test(BuildSitePage::class, ['record' => $header->getKey()])->assertForbidden();
        $this->assertSame(0, Document::query()->ofKind(Document::FOOTER)->count());
    }

    public function test_saving_the_header_keeps_a_version_and_shows_on_every_page(): void
    {
        $this->actingAsClient();
        $this->home();
        $header = Document::findOrCreatePart(Document::HEADER);

        Livewire::test(BuildSitePage::class, ['record' => $header->getKey()])
            ->call('savePageBuilder', ['sections' => [$this->section('hdr00001', 'Logo kedai')]])
            ->assertReturned(['ok' => true]);

        $this->get('/')->assertSee('Logo kedai');
        $this->assertSame(1, CmsRevision::query()->where('key', 'doc:'.$header->getKey())->count());
    }

    public function test_a_refused_save_returns_the_errors(): void
    {
        $this->actingAsClient();
        $synced = $this->document(Document::SECTION);

        $result = Livewire::test(BuildSitePage::class, ['record' => $synced->getKey()])
            ->call('savePageBuilder', ['sections' => [$this->section('sec00001', 'Satu'), $this->section('sec00002', 'Dua')]])
            ->effects['returns'][0];

        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('sections', $result['errors']);
    }

    public function test_the_header_preview_draws_it_in_the_page_view_with_the_home_page(): void
    {
        $this->actingAsClient();
        $this->home();
        $footer = Document::findOrCreatePart(Document::FOOTER);
        app(DocumentStore::class)->save($footer, [$this->section('ftr00001', 'Hak cipta')], null);
        $header = Document::findOrCreatePart(Document::HEADER);

        $html = Livewire::test(BuildSitePage::class, ['record' => $header->getKey()])
            ->call('previewPageBuilder', ['sections' => [$this->section('hdr00001', 'Header baru')]])
            ->effects['returns'][0];

        $this->assertStringContainsString('data-test-page="/"', $html);
        $this->assertStringContainsString('data-block-id="hdr00001"', str($html)->before('<h1>')->toString());
        $this->assertStringContainsString('data-item', str($html)->before('<h1>')->toString());
        $this->assertStringContainsString('<div data-part="page" class="sf-page">', $html);
        $this->assertStringContainsString('Isi utama', $html);
        $this->assertStringContainsString('<div data-part="footer" class="sf-page">', $html);
        $this->assertStringNotContainsString('data-part="header"', $html);
    }

    public function test_a_synced_section_preview_draws_it_as_the_content(): void
    {
        $this->actingAsClient();
        $this->home();
        $synced = $this->document(Document::SECTION, 'Promosi');

        $html = Livewire::test(BuildSitePage::class, ['record' => $synced->getKey()])
            ->call('previewPageBuilder', ['sections' => [$this->section('syn00001', 'Promosi raya')]])
            ->effects['returns'][0];

        $this->assertStringContainsString('Promosi raya', str($html)->after('<main>')->toString());
        $this->assertStringNotContainsString('Isi utama', $html);
    }

    public function test_the_builder_knows_who_is_a_developer(): void
    {
        $page = $this->home();

        $this->actingAsClient();
        $this->assertFalse($this->builderOptions($page)['developer']);

        $this->actingAsDeveloper();
        $this->assertTrue($this->builderOptions($page)['developer']);
    }

    public function test_the_builder_lists_the_header_and_footer_parts(): void
    {
        $this->actingAsClient();

        $parts = $this->builderOptions($this->home())['parts'];

        $this->assertSame(['header', 'footer'], array_column($parts, 'key'));
        $this->assertSame(BuildSitePage::getUrl(['record' => 'header']), $parts[0]['editUrl']);
    }

    public function test_the_design_kit_is_shown_and_saved_through_the_store(): void
    {
        $this->actingAsClient();
        $page = $this->home();

        $this->assertSame('#2563eb', $this->builderOptions($page)['kit']['colors']['primary']);
        $this->assertTrue(Livewire::test(BuildSitePage::class, ['record' => $page->getKey()])->instance()->getPageBuilderSupports()['saveKit']);

        Livewire::test(BuildSitePage::class, ['record' => $page->getKey()])
            ->call('saveKitFromPageBuilder', ['colors' => ['primary' => '#c59d6e']])
            ->assertReturned(['ok' => true]);

        $this->assertSame('#c59d6e', cms_kit()['colors']['primary']);
        $kit = Document::firstOfKind(Document::KIT);
        $this->assertSame(1, CmsRevision::query()->where('key', $kit->revisionKey())->count());
    }

    public function test_the_kit_preview_does_not_change_the_stored_kit(): void
    {
        $this->actingAsClient();
        $page = $this->home();

        $html = Livewire::test(BuildSitePage::class, ['record' => $page->getKey()])
            ->call('previewPageBuilder', ['sections' => []], ['colors' => ['primary' => '#123456']])
            ->effects['returns'][0];

        $this->assertStringContainsString('--kit-color-primary:#123456', $html);
        $this->assertSame('#2563eb', cms_kit()['colors']['primary']);
    }
}
