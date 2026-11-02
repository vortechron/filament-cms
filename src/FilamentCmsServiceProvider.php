<?php

namespace Vortechron\FilamentCms;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

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
    }

    public function packageBooted(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
