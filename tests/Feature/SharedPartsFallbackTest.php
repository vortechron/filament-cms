<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\TestCase;

class SharedPartsFallbackTest extends TestCase
{
    public function test_missing_english_text_keeps_the_shared_header_and_footer(): void
    {
        config(['filament-cms.locales' => ['ms' => 'Malay', 'en' => 'English'], 'filament-cms.default_locale' => 'ms', 'filament-block-editor.content_locale' => 'ms']);
        $store = app(DocumentStore::class);
        $value = fn (string $id, string $text): array => ['version' => 2, 'sections' => [
            ['id' => $id, 'type' => 'rich', 'data' => ['document' => [
                ['id' => $id.'-text', ...Blocks::paragraph($text)],
            ]]],
        ]];
        $page = new SitePage(['title' => 'Home', 'path' => '/', 'is_published' => true]);
        $this->assertTrue($store->save($page, $value('page-test', 'Page body'), null, true)['ok']);
        $this->assertTrue($store->saveMetadata($page, ['translations' => ['en' => ['is_published' => true]]], null, $page->edit_version)['ok']);
        foreach ([Document::HEADER => 'Shared header words', Document::FOOTER => 'Shared footer words'] as $kind => $text) {
            $part = new Document(['kind' => $kind, 'title' => $kind]);
            $this->assertTrue($store->save($part, $value($kind.'-test', $text), null, true)['ok']);
            $this->assertNull($part->translation('en'));
        }

        $this->get('/en')->assertOk()->assertSeeInOrder(['Shared header words', 'Page body', 'Shared footer words']);
        $this->get('/')->assertOk()->assertSeeInOrder(['Shared header words', 'Page body', 'Shared footer words']);
    }
}
