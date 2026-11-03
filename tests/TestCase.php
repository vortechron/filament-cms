<?php

namespace Vortechron\FilamentCms\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\QueryBuilder\QueryBuilderServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kirschbaum\PowerJoins\PowerJoinsServiceProvider;
use Laravel\Mcp\Server\McpServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use Vortechron\FilamentBlockEditor\FilamentBlockEditorServiceProvider;
use Vortechron\FilamentCms\FilamentCmsServiceProvider;
use Workbench\App\Cms\HomePage;
use Workbench\App\Models\User;
use Workbench\App\Providers\Filament\AdminPanelProvider;

/**
 * Boots the workbench app: one "admin" panel with the CMS plugin and a
 * sample home page (a hero and a pricing list).
 */
abstract class TestCase extends Orchestra
{
    use RefreshDatabase;
    use WithWorkbench;

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            McpServiceProvider::class,
            NotificationsServiceProvider::class,
            PowerJoinsServiceProvider::class,
            QueryBuilderServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentBlockEditorServiceProvider::class,
            FilamentCmsServiceProvider::class,
            AdminPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.locale', 'ms');
        $app['config']->set('filesystems.disks.public.url', '/storage');
        $app['config']->set('filament-cms.pages', [HomePage::class]);
        $app['view']->addLocation(__DIR__.'/../workbench/resources/views');
    }

    protected function actingAsClient(): User
    {
        return $this->signIn(User::factory()->create());
    }

    protected function actingAsDeveloper(): User
    {
        return $this->signIn(User::factory()->developer()->create());
    }

    private function signIn(User $user): User
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::getCurrentPanel()->boot();

        return $user;
    }
}
