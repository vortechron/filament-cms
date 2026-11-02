<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Vortechron\FilamentCms\Filament\Pages\EditContent;
use Vortechron\FilamentCms\Tests\TestCase;

class PhotoTest extends TestCase
{
    public function test_an_uploaded_photo_shows_on_the_page(): void
    {
        Storage::fake('public');
        $this->actingAsClient();

        Livewire::withQueryParams(['key' => 'home'])->test(EditContent::class)
            ->fillForm(['hero.photo' => UploadedFile::fake()->image('kedai.jpg', 800, 600)])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = cms('home')->section('hero')->photo;
        $this->assertIsString($path);
        $this->assertStringStartsWith('cms/', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame('/storage/'.$path, cms('home')->section('hero')->image('photo'));
    }

    public function test_an_empty_photo_has_no_address(): void
    {
        $this->assertNull(cms('home')->section('hero')->image('photo'));
    }
}
