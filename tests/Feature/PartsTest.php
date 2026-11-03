<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Livewire\Livewire;
use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\BuildSitePage;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\TestCase;

class PartsTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function section(string $id, string $text, bool $hidden = false): array
    {
        return ['id' => $id, 'type' => 'rich', 'data' => ['document' => [Blocks::paragraph($text)]], ...($hidden ? ['hidden' => true] : [])];
    }

    private function part(string $kind, array $sections): Document
    {
        $part = Document::findOrCreatePart($kind);
        app(DocumentStore::class)->save($part, $sections, null, developer: true);

        return $part;
    }

    private function page(): SitePage
    {
        return SitePage::query()->create(['title' => 'Tentang', 'path' => '/tentang', 'is_published' => true, 'sections' => [$this->section('sec00009', 'Isi halaman')]]);
    }

    public function test_a_page_without_a_header_or_footer_gets_empty_parts_and_makes_none(): void
    {
        $this->page();

        $this->get('/tentang')->assertOk()->assertDontSee('data-part', false);
        $this->assertSame(0, Document::query()->whereIn('kind', [Document::HEADER, Document::FOOTER])->count());
    }

    public function test_the_header_and_footer_are_drawn_around_every_page(): void
    {
        $this->page();
        $this->part(Document::HEADER, [$this->section('hdr00001', 'Logo kedai')]);
        $this->part(Document::FOOTER, [$this->section('ftr00001', 'Hak cipta kedai')]);

        $this->get('/tentang')
            ->assertOk()
            ->assertSeeInOrder(['<div data-part="header" class="sf-page">', 'Logo kedai', 'Isi halaman', '<div data-part="footer" class="sf-page">', 'Hak cipta kedai'], false);
    }

    public function test_a_part_with_only_hidden_sections_draws_nothing(): void
    {
        $this->page();
        $this->part(Document::HEADER, [$this->section('hdr00001', 'Tersorok', hidden: true)]);

        $this->get('/tentang')->assertOk()->assertDontSee('data-part', false)->assertDontSee('Tersorok');
    }

    public function test_the_builder_preview_of_a_page_draws_the_parts_as_visitors_see_them(): void
    {
        $page = $this->page();
        $this->part(Document::HEADER, [$this->section('hdr00001', 'Logo kedai'), $this->section('hdr00002', 'Tersorok', hidden: true)]);
        $this->actingAsClient();

        $html = Livewire::test(BuildSitePage::class, ['record' => $page->getKey()])
            ->call('previewPageBuilder', ['sections' => [$this->section('sec00002', 'Belum disimpan')]])
            ->effects['returns'][0];

        $header = str($html)->after('<div data-part="header" class="sf-page">')->before('<h1>')->toString();
        $this->assertStringContainsString('Logo kedai', $header);
        $this->assertStringNotContainsString('Tersorok', $header);
        $this->assertStringNotContainsString('data-item', $header);
        $this->assertStringNotContainsString('data-empty-label', $header);
        $this->assertStringContainsString('data-item', str($html)->after('<main>')->toString());
    }
}
