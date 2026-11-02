<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Livewire\Livewire;
use Vortechron\FilamentCms\Filament\Pages\EditContent;
use Vortechron\FilamentCms\Filament\Pages\EditSettings;
use Vortechron\FilamentCms\Tests\TestCase;
use Workbench\App\Cms\ShopSettings;

class SettingsTest extends TestCase
{
    public function test_the_client_saves_the_shop_phone_and_hours(): void
    {
        $this->actingAsClient();

        Livewire::test(EditSettings::class)
            ->assertOk()
            ->assertSee('Tetapan')
            ->fillForm([
                'business.name' => 'ROC - Adam Shop',
                'contact.phones' => [['number' => '011-2705 1612']],
                'hours.days' => [['days' => 'Isnin - Sabtu', 'time' => '9 pagi - 6 petang']],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = cms_settings();
        $this->assertSame('ROC - Adam Shop', $settings->section('business')->name);
        $this->assertSame(['011-2705 1612'], $settings->section('contact')->phones);
        $this->assertSame('Isnin - Sabtu', $settings->section('hours')->days[0]['days']);
    }

    public function test_search_engines_are_off_until_someone_turns_them_on(): void
    {
        $this->assertFalse(cms_settings()->section('seo')->allow_indexing);
    }

    public function test_settings_sections_cannot_be_hidden(): void
    {
        $this->actingAsClient();

        Livewire::test(EditSettings::class)->assertSchemaComponentDoesNotExist('contact._visible', 'form');
    }

    public function test_a_malaysian_number_becomes_a_whatsapp_link(): void
    {
        $this->assertSame('https://wa.me/601127051612', cms_whatsapp_url('011-2705 1612'));
        $this->assertSame('https://wa.me/601164046204?text=Salam%20kedai', cms_whatsapp_url('+60 11-6404 6204', 'Salam kedai'));
        $this->assertNull(cms_whatsapp_url(''));
    }

    public function test_links_must_be_web_addresses(): void
    {
        $this->actingAsClient();

        Livewire::test(EditSettings::class)
            ->fillForm([
                'business.name' => 'ROC - Adam Shop',
                'contact.map_url' => 'data://text/html,hello',
                'social.links' => [['platform' => 'facebook', 'url' => 'ftp://example.com/page']],
            ])
            ->call('save')
            ->assertHasFormErrors(['contact.map_url', 'social.links.0.url']);
    }

    public function test_a_project_sets_its_own_starting_settings(): void
    {
        config(['filament-cms.settings' => ShopSettings::class]);
        $this->actingAsClient();

        $this->assertSame('Kedai Contoh', cms_settings()->section('business')->name);
        $this->assertSame(['011-1111 2222'], cms_settings()->section('contact')->phones);
        $this->assertFalse(cms_settings()->section('seo')->allow_indexing);

        Livewire::test(EditSettings::class)->assertSchemaStateSet(['business.name' => 'Kedai Contoh']);
    }

    public function test_settings_sit_after_the_pages_in_the_content_menu(): void
    {
        $this->actingAsClient();

        [$settings] = EditSettings::getNavigationItems();
        [$home] = EditContent::getNavigationItems();

        $this->assertSame('Kandungan', $settings->getGroup());
        $this->assertSame($home->getGroup(), $settings->getGroup());
        $this->assertGreaterThan($home->getSort(), $settings->getSort());
    }
}
