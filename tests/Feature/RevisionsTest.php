<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Vortechron\FilamentCms\ContentStore;
use Vortechron\FilamentCms\Filament\Pages\EditContent;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Tests\TestCase;

class RevisionsTest extends TestCase
{
    private function saveTitle(string $title): void
    {
        app(ContentStore::class)->save('home', ['hero' => ['title' => $title]], auth()->user());
    }

    public function test_only_the_newest_twenty_versions_are_kept(): void
    {
        $this->actingAsClient();

        foreach (range(1, 22) as $number) {
            $this->saveTitle("Tajuk {$number}");
        }

        $this->assertSame(20, CmsRevision::query()->where('key', 'home')->count());
        $this->assertSame('Tajuk 3', CmsRevision::query()->where('key', 'home')->oldest('id')->first()->content['hero']['title']);
    }

    public function test_a_developer_restores_an_older_version(): void
    {
        $this->actingAsDeveloper();
        $this->saveTitle('Tajuk lama');
        $old = CmsRevision::query()->latest('id')->first();
        $this->saveTitle('Tajuk dipadam silap');

        Livewire::withQueryParams(['key' => 'home'])->test(EditContent::class)
            ->callAction(TestAction::make('restore'), ['revision' => $old->getKey()])
            ->assertHasNoActionErrors()
            ->assertSchemaStateSet(['hero.title' => 'Tajuk lama']);

        $this->assertSame('Tajuk lama', cms('home')->section('hero')->title);
    }

    public function test_a_developer_cannot_restore_another_pages_version(): void
    {
        $this->actingAsDeveloper();
        app(ContentStore::class)->save('settings', ['business' => ['name' => 'Kedai lain']], auth()->user());
        $other = CmsRevision::query()->where('key', 'settings')->first();
        $this->saveTitle('Tajuk kekal');

        Livewire::withQueryParams(['key' => 'home'])->test(EditContent::class)
            ->callAction(TestAction::make('restore'), ['revision' => $other->getKey()])
            ->assertHasActionErrors(['revision']);

        $this->assertSame('Tajuk kekal', cms('home')->section('hero')->title);
    }

    public function test_a_client_cannot_restore_versions(): void
    {
        $this->actingAsClient();

        Livewire::withQueryParams(['key' => 'home'])->test(EditContent::class)
            ->assertActionHidden(TestAction::make('restore'));
    }
}
