<?php

namespace Vortechron\FilamentCms;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Vortechron\FilamentBlockEditor\Content\DynamicValues;
use Vortechron\FilamentBlockEditor\Content\SectionResolver;
use Vortechron\FilamentBlockEditor\Design\KitSource;
use Vortechron\FilamentCms\Mcp\Contracts\McpEntries;
use Vortechron\FilamentCms\Mcp\Contracts\McpFormSubmissions;
use Vortechron\FilamentCms\Mcp\Entries\NoEntries;
use Vortechron\FilamentCms\Mcp\Forms\EloquentFormSubmissions;
use Vortechron\FilamentCms\Mcp\Forms\NoFormSubmissions;

class FilamentCmsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-cms';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasTranslations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Cms::class);

        // The stored kit; the builder's preview overrides it for one draw.
        $this->app->scoped(KitSource::class, CmsKitSource::class);

        // Synced sections are documents of kind "section".
        $this->app->bind(SectionResolver::class, CmsSectionResolver::class);

        // What the MCP server reads and writes entries with; none until entry types exist.
        $this->app->bind(McpEntries::class, NoEntries::class);

        // Form submissions, once the forms' model exists.
        $this->app->bind(McpFormSubmissions::class, fn (): McpFormSubmissions => class_exists(Models\FormSubmission::class)
            ? new EloquentFormSubmissions(Models\FormSubmission::class)
            : new NoFormSubmissions);
    }

    public function packageBooted(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // site.* and page.* values; each one reads the settings when drawn.
        $this->callAfterResolving(DynamicValues::class, SiteValues::register(...));
    }
}
