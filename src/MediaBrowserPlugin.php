<?php

namespace A0x01\FilamentMediaBrowser;

use Closure;
use A0x01\FilamentMediaBrowser\Resources\DocumentBrowserResource;
use A0x01\FilamentMediaBrowser\Resources\MediaBrowserResource;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class MediaBrowserPlugin implements Plugin
{
    protected static ?Closure $recordUrlUsing = null;

    protected static ?Closure $linkTargetUsing = null;

    protected static ?Closure $typeLabelUsing = null;

    public function getId(): string
    {
        return 'filament-media-browser';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            MediaBrowserResource::class,
            DocumentBrowserResource::class,
        ]);
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        return app(static::class);
    }

    public function recordUrlUsing(?Closure $callback): static
    {
        static::$recordUrlUsing = $callback;

        return $this;
    }

    public function linkTargetUsing(?Closure $callback): static
    {
        static::$linkTargetUsing = $callback;

        return $this;
    }

    public function typeLabelUsing(?Closure $callback): static
    {
        static::$typeLabelUsing = $callback;

        return $this;
    }

    public static function urlForRecord(Model $record): ?string
    {
        $target = self::linkTarget($record);

        if (static::$recordUrlUsing instanceof Closure) {
            $url = (static::$recordUrlUsing)($target);

            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return self::genericRecordUrl($target);
    }

    public static function linkTarget(Model $record): Model
    {
        if (! static::$linkTargetUsing instanceof Closure) {
            return $record;
        }

        $target = (static::$linkTargetUsing)($record);

        return $target instanceof Model ? $target : $record;
    }

    public static function typeLabel(Model $record): ?string
    {
        if (! static::$typeLabelUsing instanceof Closure) {
            return null;
        }

        $label = (static::$typeLabelUsing)($record);

        return is_string($label) && $label !== '' ? $label : null;
    }

    private static function genericRecordUrl(Model $record): ?string
    {
        $resource = Filament::getModelResource($record);

        if (! is_string($resource) || ! is_subclass_of($resource, Resource::class)) {
            return null;
        }

        try {
            if ($resource::hasPage('edit')) {
                return $resource::getUrl('edit', ['record' => $record]);
            }

            if ($resource::hasPage('view')) {
                return $resource::getUrl('view', ['record' => $record]);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
