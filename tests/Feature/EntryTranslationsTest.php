<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\Entries\EntryStore;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Entries\EntryValues;
use Vortechron\FilamentCms\EntryTranslations;
use Vortechron\FilamentCms\Filament\Resources\Entries\EntryForm;
use Vortechron\FilamentCms\Models\Entry;
use Vortechron\FilamentCms\SiteLanguages;
use Vortechron\FilamentCms\Tests\TestCase;

class EntryTranslationsTest extends TestCase
{
    public function test_translated_item_text_keeps_its_price_and_relationship_shared(): void
    {
        config(['filament-cms.locales' => ['ms' => 'Bahasa Melayu', 'en' => 'English'], 'filament-cms.default_locale' => 'ms']);
        app(EntryTypes::class)->register(EntryType::make('item')->fields([Field::number('price'), Field::text('description'), Field::select('category')->options(['8' => 'Alat tulis'])]));
        $item = Entry::create(['entry_type' => 'item', 'title' => 'Pensel', 'content' => ['version' => 2, 'fields' => ['price' => 2, 'description' => 'Pensel kayu', 'category' => '8'], 'sections' => []]]);

        app(EntryTranslations::class)->save($item, 'en', ['title' => 'Pencil', 'fields' => ['description' => 'Wooden pencil']]);

        $values = SiteLanguages::run('en', fn () => EntryValues::of($item->fresh()));
        $this->assertSame('Pencil', $values['entry.title']);
        $this->assertSame('Wooden pencil', $values['entry.description']);
        $this->assertSame('2', $values['entry.price']);
        $this->assertSame('8', $item->fresh()->fieldValues()['category']);
        $this->assertSame('Pensel', $item->fresh()->title);
        $this->assertSame('Pensel', $item->fresh()->translatedTitle('ms'));
        $item->content = ['version' => 2, 'fields' => ['price' => 3, 'description' => 'Pensel kayu', 'category' => '8'], 'sections' => []];
        $item->save();
        $this->assertSame(3, $item->translatedFieldValues('en')['price']);
    }

    public function test_a_price_save_from_an_old_form_keeps_newer_english_text(): void
    {
        config(['filament-cms.locales' => ['ms' => 'Malay', 'en' => 'English'], 'filament-cms.default_locale' => 'ms']);
        $type = EntryType::make('item')->fields([Field::number('price')]);
        app(EntryTypes::class)->register($type);
        $item = Entry::create(['entry_type' => 'item', 'title' => 'Pensel', 'content' => ['version' => 2, 'fields' => ['price' => 2], 'sections' => []]]);
        app(EntryTranslations::class)->save($item, 'en', ['title' => 'Pencil']);
        $oldForm = EntryForm::fill($type, $item->fresh());
        app(EntryTranslations::class)->save($item, 'en', ['title' => 'Wooden pencil']);
        $oldForm['fields']['price'] = 3;

        $result = app(EntryStore::class)->save($item->fresh(), EntryForm::data($type, $oldForm, $item), null);

        $this->assertTrue($result['ok']);
        $this->assertSame('Wooden pencil', $item->fresh()->translatedTitle('en'));
        $this->assertEquals(3, $item->fresh()->fieldValues()['price']);
    }

    public function test_editing_stale_english_text_is_refused_without_saving_other_fields(): void
    {
        config(['filament-cms.locales' => ['ms' => 'Malay', 'en' => 'English'], 'filament-cms.default_locale' => 'ms']);
        $type = EntryType::make('item')->fields([Field::number('price')]);
        app(EntryTypes::class)->register($type);
        $item = Entry::create(['entry_type' => 'item', 'title' => 'Pensel', 'content' => ['version' => 2, 'fields' => ['price' => 2], 'sections' => []]]);
        app(EntryTranslations::class)->save($item, 'en', ['title' => 'Pencil']);
        $oldForm = EntryForm::fill($type, $item->fresh());
        app(EntryTranslations::class)->save($item, 'en', ['title' => 'Wooden pencil']);
        $oldForm['fields']['price'] = 3;
        $oldForm['translations']['en']['title'] = 'Old edit';

        $result = app(EntryStore::class)->save($item->fresh(), EntryForm::data($type, $oldForm, $item), null);

        $this->assertFalse($result['ok']);
        $this->assertSame('Wooden pencil', $item->fresh()->translatedTitle('en'));
        $this->assertEquals(2, $item->fresh()->fieldValues()['price']);
    }
}
