<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Vortechron\FilamentBlockEditor\Forms\FormDefinition;
use Vortechron\FilamentCms\DocumentTranslations;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\TestCase;

class FormLinkResolutionTest extends TestCase
{
    public function test_logical_form_redirects_keep_choice_ids_blank_markers_and_other_targets(): void
    {
        config(['filament-cms.locales' => ['ms' => 'Malay', 'en' => 'English'], 'filament-cms.default_locale' => 'ms']);
        $root = SitePage::create(['title' => 'Contact', 'path' => '/hubungi', 'locale' => 'ms', 'is_published' => true, 'content' => []]);
        SitePage::create(['title' => 'Contact', 'path' => '/en/contact', 'locale' => 'en', 'translation_of' => $root->id, 'is_published' => true, 'content' => []]);
        $form = [
            'fields' => [['id' => 'field1', 'type' => 'select', 'name' => 'perkara', 'label' => 'Perkara', 'options' => ['', 'B'], 'option_ids' => ['choice1', 'choice2']]],
            'submit' => ['text' => '', 'align' => 'left'], 'success' => '', '_translated_empty' => ['submit', 'success'],
            'actions' => [
                ['type' => 'redirect', 'url' => '/en/contact?ref=form#visit'],
                ['type' => 'webhook', 'url' => 'https://hooks.example.test/en/contact?ref=webhook'],
                ['type' => 'whatsapp', 'to' => '@site.phone', 'message' => '/en/contact {perkara}'],
            ],
            // Raw conversion input must retain unsupported metadata for schema validation to reject it.
            'opaque' => ['url' => '/en/contact', 'value' => 1],
        ];
        $json = FormDefinition::encode($form);
        $value = ['version' => 2, 'sections' => [['id' => 'form-section', 'type' => 'rich', 'data' => ['document' => [
            ['id' => 'form-block', 'type' => 'form', 'props' => ['form' => $json]],
        ]]]]];

        $logical = app(DocumentTranslations::class)->logicalLinks($value);

        $expected = $form;
        $expected['actions'][0]['url'] = '/hubungi?ref=form#visit';
        $this->assertSame($expected, FormDefinition::decode($logical['sections'][0]['data']['document'][0]['props']['form']));
        $this->assertSame($json, $value['sections'][0]['data']['document'][0]['props']['form']);
    }
}
