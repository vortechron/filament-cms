<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Illuminate\Validation\ValidationException;
use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentBlockEditor\Content\TextTranslations;
use Vortechron\FilamentCms\CmsSettingsTranslations;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Entries\StoredEntryTypes;
use Vortechron\FilamentCms\EntryTranslations;
use Vortechron\FilamentCms\Models\Entry;
use Vortechron\FilamentCms\SiteLanguages;
use Vortechron\FilamentCms\Tests\TestCase;

class SharedTextValuesTest extends TestCase
{
    public function test_stale_settings_text_is_refused_and_intentional_blank_is_retained(): void
    {
        config(['filament-cms.locales' => ['ms' => 'Malay', 'en' => 'English'], 'filament-cms.default_locale' => 'ms', 'filament-cms.translatable_settings' => ['hours' => ['note']]]);
        $store = app(CmsSettingsTranslations::class);
        $store->save('en', ['hours' => ['note' => 'Closed Sunday']]);
        $baseline = CmsSettingsTranslations::saved('en');
        $store->save('en', ['hours' => ['note' => '']], expectedValues: $baseline);
        $this->assertSame('', CmsSettingsTranslations::overlay(['hours' => ['note' => 'Tutup Ahad']], 'en')['hours']['note']);
        $this->expectException(ValidationException::class);
        $store->save('en', ['hours' => ['note' => 'Stale']], expectedValues: $baseline);
    }

    public function test_entry_text_can_be_intentionally_blank_and_nonword_fields_cannot_be_translated(): void
    {
        config(['filament-cms.locales' => ['ms' => 'Malay', 'en' => 'English'], 'filament-cms.default_locale' => 'ms']);
        app(EntryTypes::class)->register(
            StoredEntryTypes::build(['key' => 'item', 'fields' => [
                ['name' => 'description', 'type' => 'text'],
                ['name' => 'sku', 'type' => 'text', 'translatable' => false],
            ]])
        );
        $item = Entry::create(['entry_type' => 'item', 'title' => 'Pensel', 'content' => ['version' => 2, 'fields' => ['description' => 'Kayu', 'sku' => 'P001'], 'sections' => []]]);
        $translations = app(EntryTranslations::class);
        $translations->save($item, 'en', ['title' => '', 'fields' => ['description' => '']]);
        $this->assertSame('', $item->fresh()->translatedTitle('en'));
        $this->assertSame('', $item->fresh()->translatedFieldValues('en')['description']);
        $this->expectException(ValidationException::class);
        $translations->save($item, 'en', ['fields' => ['sku' => 'Different']]);
    }

    public function test_entry_body_uses_the_same_text_resolution_as_pages_and_form_lookup(): void
    {
        config(['filament-cms.locales' => ['ms' => 'Malay', 'en' => 'English'], 'filament-cms.default_locale' => 'ms']);
        app(EntryTypes::class)->register(EntryType::make('item')->body(true));
        $item = Entry::create(['entry_type' => 'item', 'title' => 'Pensel', 'content' => ['version' => 2, 'fields' => [], 'sections' => []]]);
        $value = ['version' => 2, 'sections' => [['id' => 'sec00001', 'type' => 'rich', 'data' => ['document' => [['id' => 'body0001', ...Blocks::paragraph('Badan Melayu')]]]]]];
        $translated = $value;
        $translated['sections'][0]['data']['document'][0]['content'][0]['text'] = 'English body';
        $value['translations']['en'] = TextTranslations::extract($value, $translated);
        $this->assertTrue(app(DocumentStore::class)->save($item, $value, null, true)['ok']);
        $sections = SiteLanguages::run('en', fn () => $item->fresh()->contentSections());
        $this->assertStringContainsString('English body', json_encode($sections));
        $this->assertStringNotContainsString('Badan Melayu', json_encode($sections));
    }
}
