<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentBlockEditor\Forms\FormDefinition;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\FormSubmission;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\Concerns\MakesForms;
use Vortechron\FilamentCms\Tests\TestCase;

class FormLookupTest extends TestCase
{
    use MakesForms;

    public function test_a_page_that_is_not_published_gives_a_404(): void
    {
        $page = $this->pageWithForm(published: false);

        $this->send($page)->assertNotFound();
        $this->assertSame(0, FormSubmission::query()->count());
    }

    public function test_a_document_that_is_not_a_page_or_an_entry_gives_a_404(): void
    {
        $synced = Document::query()->create(['kind' => Document::SECTION, 'title' => 'Promosi', 'is_published' => true, 'content' => ['version' => 2, 'sections' => [self::formSection()]]]);

        $this->send($synced)->assertNotFound();
        $this->post('/_cms/forms/abc', ['_form' => 'sec00001.frm00001'])->assertNotFound();
        $this->assertSame(0, FormSubmission::query()->count());
    }

    public function test_a_form_the_page_does_not_show_gives_a_404(): void
    {
        $page = $this->pageWithForm();
        SitePage::query()->create(['title' => 'Lain', 'path' => '/lain', 'is_published' => true, 'sections' => [self::formSection('sec00002', 'frm00002')]]);
        $hidden = SitePage::query()->create(['title' => 'Sorok', 'path' => '/sorok', 'is_published' => true, 'sections' => [[...self::formSection(), 'hidden' => true]]]);

        $this->send($page, key: 'sec00002.frm00002')->assertNotFound();
        $this->send($page, key: 'sec00001.missing')->assertNotFound();
        $this->send($page, key: 'other.frm00001')->assertNotFound();
        $this->send($page, key: '../../frm00001')->assertNotFound();
        $this->send($hidden)->assertNotFound();
        $this->assertSame(0, FormSubmission::query()->count());
    }

    public function test_the_stored_form_is_used_even_when_the_request_lies_about_it(): void
    {
        $page = $this->pageWithForm();
        $lie = FormDefinition::encode(FormDefinition::normalize(['name' => 'Palsu', 'fields' => [['type' => 'text', 'name' => 'rahsia']], 'actions' => [['type' => 'webhook', 'url' => 'https://evil.example']]]));

        $this->send($page, [...self::goodInput(['nama' => '']), 'form' => $lie, 'props' => ['form' => $lie], 'rahsia' => 'x'])
            ->assertSessionHasErrors('form.frm00001.nama');
        $this->send($page, [...self::goodInput(), 'form' => $lie, 'rahsia' => 'x', 'form_name' => 'Palsu'])
            ->assertSessionHasNoErrors();

        $submission = FormSubmission::query()->sole();
        $this->assertSame('Borang pertanyaan', $submission->form_name);
        $this->assertArrayNotHasKey('rahsia', $submission->data);
    }

    public function test_a_form_in_the_header_or_footer_is_saved_for_the_page_it_was_sent_from(): void
    {
        $this->part(Document::HEADER, [self::formSection('hdr00001', 'frmhdr01', [...self::exampleForm(), 'name' => 'Borang header'])]);
        $this->part(Document::FOOTER, [self::formSection('ftr00001', 'frmftr01', [...self::exampleForm(), 'name' => 'Borang footer'])]);
        $page = $this->pageWithForm();

        $this->send($page, key: 'hdr00001.frmhdr01')->assertRedirect(url('/hubungi').'#form-frmhdr01');
        $this->send($page, key: 'ftr00001.frmftr01')->assertRedirect(url('/hubungi').'#form-frmftr01');

        $this->assertSame([[$page->getKey(), 'Borang header', '/hubungi'], [$page->getKey(), 'Borang footer', '/hubungi']], FormSubmission::query()->oldest('id')->get()
            ->map(fn (FormSubmission $submission): array => [$submission->document_id, $submission->form_name, $submission->meta['page']])->all());
    }

    public function test_a_form_in_a_synced_section_is_found_through_the_page_that_shows_it(): void
    {
        $synced = Document::query()->create(['kind' => Document::SECTION, 'title' => 'Promosi', 'content' => ['version' => 2, 'sections' => [self::formSection('syn00001', 'frmsyn01', [...self::exampleForm(), 'name' => 'Borang segerak'])]]]);
        $page = SitePage::query()->create(['title' => 'Utama', 'path' => '/', 'is_published' => true, 'sections' => [['id' => 'ref00001', 'type' => 'ref', 'ref' => $synced->getKey()]]]);
        $other = $this->pageWithForm();

        $this->assertStringContainsString('data-form="ref00001.frmsyn01"', $this->get('/')->getContent());
        $this->send($page, key: 'ref00001.frmsyn01')->assertSessionHas('filament-cms.form_sent', 'frmsyn01');
        $this->send($other, key: 'ref00001.frmsyn01')->assertNotFound();
        $this->send($page, key: 'syn00001.frmsyn01')->assertNotFound();

        $this->assertSame('Borang segerak', FormSubmission::query()->sole()->form_name);
    }

    public function test_the_page_wins_over_the_header_and_a_bare_block_id_is_found(): void
    {
        $this->part(Document::HEADER, [self::formSection('sec00001', 'frm00001', [...self::exampleForm(), 'name' => 'Borang header'])]);
        $page = $this->pageWithForm();

        $this->send($page);
        $this->send($page, key: 'frm00001');

        $this->assertSame(['Borang pertanyaan', 'Borang pertanyaan'], FormSubmission::query()->oldest('id')->pluck('form_name')->all());
        $this->assertSame(['sec00001.frm00001', 'frm00001'], FormSubmission::query()->oldest('id')->pluck('form_key')->all());
    }

    public function test_a_form_inside_a_column_is_found(): void
    {
        $form = ['id' => 'frmcol01', ...Blocks::form(self::exampleForm())];
        $page = SitePage::query()->create(['title' => 'Kolum', 'path' => '/kolum', 'is_published' => true, 'sections' => [['id' => 'sec00009', 'type' => 'rich', 'data' => ['document' => [
            ['id' => 'cols0001', 'type' => 'columnList', 'props' => [], 'content' => [], 'children' => [
                ['id' => 'col00001', 'type' => 'column', 'props' => ['width' => 1], 'content' => [], 'children' => [Blocks::paragraph('Kiri')]],
                ['id' => 'col00002', 'type' => 'column', 'props' => ['width' => 1], 'content' => [], 'children' => [$form]],
            ]],
        ]]]]]);

        $this->assertStringContainsString('data-form="sec00009.frmcol01"', $this->get('/kolum')->getContent());
        $this->send($page, key: 'sec00009.frmcol01')->assertSessionHas('filament-cms.form_sent', 'frmcol01');
    }
}
