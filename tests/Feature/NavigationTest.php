<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Filament\Resources\SitePages\SitePageResource;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Tests\TestCase;

class NavigationTest extends TestCase
{
    public function test_the_owner_lands_on_halaman_and_borang_comes_after_the_content_screens(): void
    {
        app(EntryTypes::class)->register(EntryType::make('product')->label('Produk')->fields([Field::text('price')]));
        // Seksyen is in the menu once a synced or saved section exists.
        Document::query()->create(['kind' => Document::TEMPLATE, 'title' => 'Kotak harga', 'content' => ['version' => 2, 'sections' => []]]);
        $this->actingAsClient();

        $groups = array_map(fn (NavigationGroup $group): array => [
            'group' => $group->getLabel(),
            'items' => array_map(fn (NavigationItem $item): string => $item->getLabel(), array_values($group->getItems()->all() ?? [])),
        ], array_values(Filament::getNavigation()));

        $this->assertSame([['group' => 'Kandungan', 'items' => ['Halaman', 'Seksyen', 'Produk', 'Halaman utama', 'Borang', 'Tetapan']]], $groups);
        $this->get('/admin')->assertRedirect(SitePageResource::getUrl());
    }
}
