<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Vortechron\FilamentCms\Filament\Pages\EditContent;
use Vortechron\FilamentCms\Tests\TestCase;

class EditContentTest extends TestCase
{
    private function editHomePage(): Testable
    {
        return Livewire::withQueryParams(['key' => 'home'])->test(EditContent::class);
    }

    public function test_the_client_sees_todays_text_in_the_form(): void
    {
        $this->actingAsClient();

        $this->editHomePage()
            ->assertOk()
            ->assertSee('Halaman utama')
            ->assertSchemaStateSet([
                'hero.title' => 'Sambal Dapur Maya',
                'pakej.heading' => 'Pakej sambal',
            ]);
    }

    public function test_the_client_adds_a_package_and_the_page_shows_it(): void
    {
        $this->actingAsClient();

        $this->editHomePage()
            ->fillForm([
                'hero.title' => 'Sambal Maya',
                'pakej.packages' => [
                    ['name' => 'Kecil', 'price' => '15'],
                    ['name' => 'Besar', 'price' => '25'],
                    ['name' => 'Combo', 'price' => '39'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Sambal Maya', cms('home')->section('hero')->title);
        $this->assertSame(['Kecil', 'Besar', 'Combo'], array_column(cms('home')->section('pakej')->packages, 'name'));
    }

    public function test_the_client_cannot_add_more_packages_than_the_design_allows(): void
    {
        $this->actingAsClient();

        $this->editHomePage()
            ->fillForm(['pakej.packages' => array_fill(0, 7, ['name' => 'Pakej', 'price' => '10'])])
            ->call('save')
            ->assertHasFormErrors(['pakej.packages']);

        $this->assertCount(2, cms('home')->section('pakej')->packages);
    }

    public function test_the_client_can_hide_a_section(): void
    {
        $this->actingAsClient();

        $this->editHomePage()
            ->fillForm(['pakej._visible' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse(cms('home')->visible('pakej'));
        $this->assertTrue(cms('home')->visible('hero'));
        $this->assertNull(cms('home')->section('pakej')->get('_visible'));
    }

    public function test_a_section_that_cannot_be_hidden_has_no_switch(): void
    {
        $this->actingAsClient();

        $this->editHomePage()
            ->assertSchemaComponentExists('pakej._visible', 'form')
            ->assertSchemaComponentDoesNotExist('hero._visible', 'form');
    }

    public function test_an_unknown_page_is_not_found(): void
    {
        $this->actingAsClient();

        Livewire::withQueryParams(['key' => 'nope'])->test(EditContent::class)->assertNotFound();
    }

    public function test_settings_are_not_edited_on_the_content_screen(): void
    {
        $this->actingAsClient();

        Livewire::withQueryParams(['key' => 'settings'])->test(EditContent::class)->assertNotFound();
    }

    public function test_the_menu_has_one_item_per_page(): void
    {
        $this->actingAsClient();

        $this->get(EditContent::getUrl(['key' => 'home']))
            ->assertOk()
            ->assertSee('Halaman utama');
    }
}
