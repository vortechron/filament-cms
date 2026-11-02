<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Vortechron\FilamentCms\ContentStore;
use Vortechron\FilamentCms\Filament\Pages\EditContent;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Settings\Sections\BusinessSettings;
use Vortechron\FilamentCms\Tests\TestCase;

class HardeningTest extends TestCase
{
    public function test_only_web_addresses_are_printed_as_links(): void
    {
        $this->assertSame('https://maps.google.com/?q=kedai', cms_url('https://maps.google.com/?q=kedai'));
        $this->assertSame('http://example.com', cms_url(' http://example.com '));
        $this->assertNull(cms_url('javascript:alert(1)'));
        $this->assertNull(cms_url('data://text/html,hello'));
        $this->assertNull(cms_url('//evil.example'));
        $this->assertNull(cms_url(null));
    }

    public function test_an_svg_cannot_be_uploaded_as_a_photo(): void
    {
        Storage::fake('public');
        $this->actingAsClient();

        Livewire::withQueryParams(['key' => 'home'])->test(EditContent::class)
            ->fillForm(['hero.photo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])
            ->call('save')
            ->assertHasFormErrors(['hero.photo']);
    }

    public function test_saving_the_same_content_again_does_not_use_up_a_version(): void
    {
        $this->actingAsClient();
        $store = app(ContentStore::class);

        $store->save('home', ['hero' => ['title' => 'Sama']], auth()->user());
        $store->save('home', ['hero' => ['title' => 'Sama']], auth()->user());
        $store->save('home', ['hero' => ['title' => 'Lain']], auth()->user());

        $this->assertSame(2, CmsRevision::query()->where('key', 'home')->count());
    }

    public function test_the_registered_company_name_is_a_setting(): void
    {
        $this->assertContains('legal_name', array_map(fn ($field) => $field->getName(), (new BusinessSettings('business'))->fields()));
    }
}
