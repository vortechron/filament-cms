<?php

namespace Vortechron\FilamentCms;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Vortechron\FilamentBlockEditor\Content\DynamicValues;
use Vortechron\FilamentBlockEditor\Content\SectionResolver;
use Vortechron\FilamentBlockEditor\Design\KitSource;
use Vortechron\FilamentBlockEditor\Forms\FormEndpoint;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Forms\CmsFormEndpoint;
use Vortechron\FilamentCms\Forms\DnsHostResolver;
use Vortechron\FilamentCms\Forms\HostResolver;

class FilamentCmsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-cms';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Cms::class);
        $this->app->singleton(EntryTypes::class);

        // The stored kit; the builder's preview overrides it for one draw.
        $this->app->scoped(KitSource::class, CmsKitSource::class);

        // Synced sections are documents of kind "section".
        $this->app->bind(SectionResolver::class, CmsSectionResolver::class);

        // Form blocks post to the page they are on (Forms\FormSubmissionHandler).
        $this->app->bind(FormEndpoint::class, CmsFormEndpoint::class);
        $this->app->bindIf(HostResolver::class, DnsHostResolver::class);
    }

    public function packageBooted(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // site.* and page.* values; each one reads the settings when drawn.
        $this->callAfterResolving(DynamicValues::class, SiteValues::register(...));
    }
}
