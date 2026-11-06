<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentBlockEditor\Content\TextTranslations;
use Vortechron\FilamentBlockEditor\Forms\FormDefinition;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\DocumentTranslations;
use Vortechron\FilamentCms\Forms\FormLocator;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\SiteLanguages;
use Vortechron\FilamentCms\Tests\Concerns\MakesForms;
use Vortechron\FilamentCms\Tests\TestCase;

class SharedPartResolutionTest extends TestCase
{
    use MakesForms;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filament-cms.locales' => ['ms' => 'Malay', 'en' => 'English'], 'filament-cms.default_locale' => 'ms', 'filament-block-editor.content_locale' => 'ms']);
    }

    public static function parts(): array
    {
        return ['header' => [Document::HEADER], 'footer' => [Document::FOOTER]];
    }

    private function publishedPages(): array
    {
        $page = new SitePage(['title' => 'Home', 'path' => '/', 'is_published' => true]);
        $store = app(DocumentStore::class);
        $this->assertTrue($store->save($page, ['version' => 2, 'sections' => []], null, true)['ok']);
        $this->assertTrue($store->saveMetadata($page, ['translations' => ['en' => ['is_published' => true]]], null, 1)['ok']);

        return [$page, $page->translation('en')];
    }

    private function value(string $text): array
    {
        return ['version' => 2, 'sections' => [['id' => 'part0001', 'type' => 'rich', 'data' => [
            'layout' => 'poster', 'advanced' => ['class' => 'shared-part-design'],
            'document' => [
                ['id' => 'partimage', ...Blocks::image('/storage/part-photo.webp')],
                ['id' => 'partwords', ...Blocks::paragraph($text)],
            ],
        ]]]];
    }

    #[DataProvider('parts')]
    public function test_missing_part_text_keeps_shared_layout_media_and_current_language_links_without_creating_a_peer(string $kind): void
    {
        $this->publishedPages();
        $store = app(DocumentStore::class);
        $contact = new SitePage(['title' => 'Contact', 'path' => '/contact', 'is_published' => true]);
        $this->assertTrue($store->save($contact, ['version' => 2, 'sections' => []], null, true)['ok']);
        $this->assertTrue($store->saveMetadata($contact, ['translations' => ['en' => ['path' => '/get-in-touch', 'is_published' => true]]], null, 1)['ok']);
        $part = new Document(['kind' => $kind, 'title' => $kind]);
        $value = $this->value('Shared fallback words');
        $value['sections'][0]['data']['document'][1]['content'] = [['type' => 'link', 'href' => '/contact?ref=part#visit', 'content' => [['type' => 'text', 'text' => 'Shared fallback words', 'styles' => []]]]];
        $this->assertTrue($store->save($part, $value, null, true)['ok']);

        $this->get('/en')->assertOk()->assertSee('Shared fallback words')->assertSee('shared-part-design')->assertSee('/storage/part-photo.webp')->assertSee('href="/en/get-in-touch?ref=part#visit"', false);
        $this->get('/')->assertOk()->assertSee('href="/contact?ref=part#visit"', false);
        $this->assertSame($part->id, SiteLanguages::run('en', fn () => Document::findOrCreatePart($kind))->id);
        $this->assertNull($part->translation('en'));
        $this->assertSame(1, $part->fresh()->edit_version);
    }

    #[DataProvider('parts')]
    public function test_an_english_visitor_can_submit_the_shared_part_form_without_a_language_row(string $kind): void
    {
        [, $english] = $this->publishedPages();
        $part = new Document(['kind' => $kind, 'title' => $kind]);
        $this->assertTrue(app(DocumentStore::class)->save($part, ['version' => 2, 'sections' => [self::formSection()]], null, true)['ok']);

        $this->get('/en')->assertOk()->assertSee('data-form="sec00001.frm00001"', false)->assertSee('name="nama"', false)->assertSee('Fotostat');
        $this->send($english, self::goodInput(['nama' => '']))->assertSessionHasErrors('form.frm00001.nama');
        $this->assertSame('Please fill in Nama.', session('errors')->first('form.frm00001.nama'));
        $this->send($english)->assertRedirect(url('/en').'#form-frm00001')->assertSessionHasNoErrors();
        $located = app(FormLocator::class)->find($english, 'sec00001.frm00001');
        $this->assertSame('nama', $located->form->fields()[0]['name']);
        $this->assertSame('Nama', $located->form->fields()[0]['label']);
        $this->assertNull($part->translation('en'));
        $this->assertSame(1, $part->fresh()->edit_version);
    }

    #[DataProvider('parts')]
    public function test_a_fallback_part_form_redirects_to_the_published_counterpart_in_the_visitors_language(string $kind): void
    {
        [, $english] = $this->publishedPages();
        $store = app(DocumentStore::class);
        $contact = new SitePage(['title' => 'Contact', 'path' => '/hubungi', 'is_published' => true]);
        $this->assertTrue($store->save($contact, ['version' => 2, 'sections' => []], null, true)['ok']);
        $this->assertTrue($store->saveMetadata($contact, ['translations' => ['en' => ['path' => '/contact', 'is_published' => true]]], null, 1)['ok']);
        $part = new Document(['kind' => $kind, 'title' => $kind]);
        $form = self::exampleForm([['type' => 'redirect', 'url' => '/hubungi?ref=part#visit']]);
        $this->assertTrue($store->save($part, ['version' => 2, 'sections' => [self::formSection(form: $form)]], null, true)['ok']);

        $this->get('/en')->assertOk()->assertSee('data-form="sec00001.frm00001"', false);
        $this->send($english)->assertRedirect('/en/contact?ref=part#visit');
        $resolved = app(DocumentTranslations::class)->resolvedValue($part, 'en');
        $renderedForm = FormDefinition::fromProps($resolved['sections'][0]['data']['document'][1]['props']);
        $locatedForm = app(FormLocator::class)->find($english, 'sec00001.frm00001')->form;
        $this->assertSame(['type' => 'redirect', 'url' => '/en/contact?ref=part#visit'], $renderedForm->action('redirect'));
        $this->assertSame($renderedForm->toArray(), $locatedForm->toArray());
        $this->assertNull($part->translation('en'));
        $this->assertSame(1, $part->fresh()->edit_version);
    }

    #[DataProvider('parts')]
    public function test_an_existing_draft_part_hides_the_shared_fallback_and_its_form_until_published(string $kind): void
    {
        [, $english] = $this->publishedPages();
        $store = app(DocumentStore::class);
        $part = new Document(['kind' => $kind, 'title' => $kind]);
        $value = ['version' => 2, 'sections' => [self::formSection()]];
        $this->assertTrue($store->save($part, $value, null, true)['ok']);
        $this->assertTrue($store->saveMetadata($part, ['translations' => ['en' => ['title' => 'English part']]], null, 1)['ok']);
        $peer = $part->translation('en');

        $this->get('/en')->assertOk()->assertDontSee('data-form="sec00001.frm00001"', false)->assertDontSee('Hubungi kami');
        $this->send($english)->assertNotFound();
        $this->assertNull(app(FormLocator::class)->find($english, 'sec00001.frm00001'));
        $this->assertSame($peer->id, SiteLanguages::run('en', fn () => Document::findOrCreatePart($kind))->id);
        $this->get('/')->assertOk()->assertSee('data-form="sec00001.frm00001"', false);

        $this->assertTrue($store->saveMetadata($peer, ['is_published' => true], null, 2)['ok']);
        $this->get('/en')->assertOk()->assertSee('data-form="sec00001.frm00001"', false);
        $this->send($english)->assertRedirect()->assertSessionHasNoErrors();
    }

    public static function translatedParts(): array
    {
        return [
            'header English' => [Document::HEADER, 'English part words'],
            'footer English' => [Document::FOOTER, 'English part words'],
            'header blank' => [Document::HEADER, ''],
            'footer blank' => [Document::FOOTER, ''],
        ];
    }

    #[DataProvider('translatedParts')]
    public function test_a_published_language_row_uses_its_words_or_explicit_blank_in_the_shared_design(string $kind, string $text): void
    {
        $this->publishedPages();
        $store = app(DocumentStore::class);
        $part = new Document(['kind' => $kind, 'title' => $kind]);
        $value = $this->value('Default part words');
        $value['translations']['en'] = TextTranslations::extract($value, $this->value($text));
        $this->assertTrue($store->save($part, $value, null, true)['ok']);
        $this->assertTrue($store->saveMetadata($part, ['translations' => ['en' => ['is_published' => true]]], null, 1)['ok']);

        $response = $this->get('/en')->assertOk()->assertSee('shared-part-design')->assertSee('/storage/part-photo.webp')->assertDontSee('Default part words');
        if ($text !== '') {
            $response->assertSee('English part words');
        }
        $this->get('/')->assertOk()->assertSee('Default part words')->assertDontSee('English part words');
        $this->assertSame($part->translation('en')->id, SiteLanguages::run('en', fn () => Document::findOrCreatePart($kind))->id);
    }

    #[DataProvider('parts')]
    public function test_a_legacy_part_without_a_language_row_stays_absent_and_cannot_accept_a_form_in_that_language(string $kind): void
    {
        [, $english] = $this->publishedPages();
        $part = Document::create(['kind' => $kind, 'title' => $kind, 'content' => ['version' => 2, 'sections' => [self::formSection()]]]);

        $this->get('/en')->assertOk()->assertDontSee('data-part="'.$kind.'"', false)->assertDontSee('Hubungi kami');
        $this->send($english)->assertNotFound();
        $this->get('/')->assertOk()->assertSee('Hubungi kami');
        $this->assertNull($part->translation('en'));
        $this->assertNull($part->fresh()->shared_format);
    }
}
