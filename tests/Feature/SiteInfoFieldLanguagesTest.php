<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Illuminate\Validation\ValidationException;
use Vortechron\FilamentBlockEditor\Rendering\ContentLocale;
use Vortechron\FilamentCms\CmsSettingsTranslations;
use Vortechron\FilamentCms\ContentStore;
use Vortechron\FilamentCms\SiteInfo;
use Vortechron\FilamentCms\Tests\TestCase;

class SiteInfoFieldLanguagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'filament-cms.locales' => ['ms' => 'Bahasa Melayu', 'en' => 'English'],
            'filament-cms.default_locale' => 'ms',
            'filament-cms.translatable_settings' => ['hours' => ['note']],
        ]);
        app(ContentStore::class)->save('settings', [
            'hours' => ['note' => 'Tutup Ahad'],
            'contact' => ['phones' => ['012-345 6789']],
        ], null);
        app(CmsSettingsTranslations::class)->save('en', ['hours' => ['note' => 'Closed Sunday']]);
    }

    public function test_the_editor_gets_default_words_and_translation_values_without_translating_shop_facts(): void
    {
        $snapshot = ContentLocale::with('en', fn (): array => app(SiteInfo::class)->forBuilder(fieldLanguages: true));

        $this->assertSame('Tutup Ahad', $snapshot['values']['hours']['note']);
        $this->assertSame('Closed Sunday', $snapshot['translations']['en']['hours']['note']);
        $fields = collect($snapshot['groups'])->keyBy('key');
        $hours = collect($fields['hours']['fields'])->keyBy('name');
        $contact = collect($fields['contact']['fields'])->keyBy('name');
        $this->assertTrue($hours['note']->isTranslatable());
        $this->assertFalse($contact['email']->isTranslatable());
        $this->assertFalse($contact['phones']->isTranslatable());
    }

    public function test_translated_builder_settings_follow_field_limits_and_keep_a_deliberate_blank(): void
    {
        $info = app(SiteInfo::class);
        $baseline = CmsSettingsTranslations::saved('en');
        $info->saveTranslations('en', ['hours' => ['note' => '']], null, $baseline);
        $this->assertSame('', CmsSettingsTranslations::saved('en')['hours']['note']);
        $this->assertSame('Tutup Ahad', cms_settings()->section('hours')->note);

        $this->expectException(ValidationException::class);
        $info->saveTranslations('en', ['hours' => ['note' => str_repeat('x', 161)]], null, ['hours' => ['note' => '']]);
    }

    public function test_preview_uses_only_the_selected_draft_language_and_never_saves_it(): void
    {
        $values = ['hours' => ['note' => 'Draf Melayu']];
        $translations = ['en' => ['hours' => ['note' => 'English draft']]];
        $info = app(SiteInfo::class);
        $english = ContentLocale::with('en', fn (): array => $info->previewSettings($values, $translations));
        $malay = ContentLocale::with('ms', fn (): array => $info->previewSettings($values, $translations));

        $this->assertSame('English draft', $english['hours']['note']);
        $this->assertSame('Draf Melayu', $malay['hours']['note']);
        $this->assertSame('Closed Sunday', CmsSettingsTranslations::saved('en')['hours']['note']);
        $this->assertSame('Tutup Ahad', cms_settings()->section('hours')->note);
        $blank = ContentLocale::with('en', fn (): array => $info->previewSettings($values, ['en' => ['hours' => ['note' => '']]]));
        $this->assertSame('', $blank['hours']['note']);
    }
}
