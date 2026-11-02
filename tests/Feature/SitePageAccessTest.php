<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Vortechron\FilamentCms\Filament\Resources\SitePages\Pages\BuildSitePage;
use Vortechron\FilamentCms\Models\SitePage;
use Vortechron\FilamentCms\Tests\TestCase;

class SitePageAccessTest extends TestCase
{
    public function test_a_user_who_may_not_edit_the_page_cannot_open_or_use_its_builder(): void
    {
        $page = SitePage::query()->create(['title' => 'Tentang', 'path' => '/tentang', 'is_published' => true, 'sections' => []]);
        Gate::policy(SitePage::class, DenyEditingPolicy::class);
        $this->actingAsClient();

        $this->get(BuildSitePage::getUrl(['record' => $page]))->assertForbidden();
        Livewire::test(BuildSitePage::class, ['record' => $page->getKey()])->assertForbidden();
    }
}

class DenyEditingPolicy
{
    public function viewAny(): bool
    {
        return true;
    }

    public function update(): bool
    {
        return false;
    }
}
