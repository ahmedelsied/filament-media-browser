<?php

namespace A0x01\FilamentMediaBrowser;

use A0x01\FilamentMediaBrowser\Adapters\DefaultAdapter;
use A0x01\FilamentMediaBrowser\Adapters\SpatieMediaAdapter;
use A0x01\FilamentMediaBrowser\Console\InstallCommand;
use A0x01\FilamentMediaBrowser\Enums\Driver;
use A0x01\FilamentMediaBrowser\Models\MediaPool;
use A0x01\FilamentMediaBrowser\Ports\DefaultPort;
use A0x01\FilamentMediaBrowser\Ports\SpatieMediaPort;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class MediaBrowserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/media-browser.php',
            'media-browser',
        );

        $this->bindDriver();
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-media-browser');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-media-browser');

        if (Driver::current() === Driver::Spatie) {
            Relation::morphMap([
                'App\\Models\\MediaPool' => MediaPool::class,
            ]);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/media-browser.php' => config_path('media-browser.php'),
            ], 'filament-media-browser-config');
        }
    }

    public function bindDriver(): void
    {
        unset($this->app[DefaultPort::class], $this->app[SpatieMediaPort::class]);

        if (Driver::current() === Driver::Spatie) {
            $this->app->singleton(SpatieMediaPort::class, SpatieMediaAdapter::class);

            return;
        }

        $this->app->singleton(DefaultPort::class, DefaultAdapter::class);
    }
}
