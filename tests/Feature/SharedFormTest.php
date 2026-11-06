<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Vortechron\FilamentCms\DocumentTranslations;
use Vortechron\FilamentCms\Forms\FormLocator;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\Concerns\MakesForms;
use Vortechron\FilamentCms\Tests\TestCase;

class SharedFormTest extends TestCase
{
    use MakesForms;

    public function test_old_language_endpoint_uses_the_resolved_public_labels_options_and_field_names(): void
    {
        config(['filament-cms.locales' => ['ms' => 'Malay', 'en' => 'English'], 'filament-cms.default_locale' => 'ms']);
        $root = $this->pageWithForm();
        $form = self::exampleForm();
        $form['fields'][0]['label'] = 'Your name';
        $form['fields'][2]['options'] = ['Copying', 'Parcel'];
        $form['fields'][4]['options'] = ['Monday', 'Tuesday'];
        $english = SitePage::create(['title' => 'Contact', 'path' => '/en/contact', 'locale' => 'en', 'translation_of' => $root->id, 'is_published' => true, 'sections' => [self::formSection(form: $form)]]);
        $result = app(DocumentTranslations::class)->convert($root);
        $this->assertTrue($result['ok'], json_encode($result));
        $this->get('/en/contact')->assertOk()->assertSee('Your name')->assertSee('Monday')->assertSee('name="nama"', false);
        $this->send($english, self::goodInput(['nama' => '', 'hari' => ['Monday']]))->assertRedirect();
        $this->assertSame('Please fill in Your name.', session('errors')->first('form.frm00001.nama'));
        $this->send($english, self::goodInput(['hari' => ['Monday']]))->assertRedirect()->assertSessionHasNoErrors();
        $this->send($root)->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_form_lookup_uses_the_selected_documents_language_for_shared_parts_even_outside_http(): void
    {
        config(['filament-cms.locales' => ['ms' => 'Malay', 'en' => 'English'], 'filament-cms.default_locale' => 'ms', 'filament-block-editor.content_locale' => 'ms']);
        $root = SitePage::create(['title' => 'Home', 'path' => '/', 'content' => []]);
        $translations = app(DocumentTranslations::class);
        $english = $translations->create($root, 'en');
        $header = Document::create(['kind' => 'header', 'title' => 'Header', 'content' => ['version' => 2, 'sections' => [self::formSection()]]]);
        $form = self::exampleForm();
        $form['fields'][0]['label'] = 'English name';
        $englishHeader = Document::create(['kind' => 'header', 'title' => 'English header', 'locale' => 'en', 'translation_of' => $header->id, 'is_published' => true, 'content' => ['version' => 2, 'sections' => [self::formSection(form: $form)]]]);
        $this->assertTrue($translations->convert($header)['ok']);
        $located = app(FormLocator::class)->find($english, 'sec00001.frm00001');
        $this->assertNotNull($located);
        $this->assertSame('English name', $located->form->toArray()['fields'][0]['label']);
    }
}
