<?php

namespace A0x01\FilamentMediaBrowser;

use A0x01\FilamentMediaBrowser\Enums\Driver;
use A0x01\FilamentMediaBrowser\Ports\DefaultPort;
use A0x01\FilamentMediaBrowser\Ports\SpatieMediaPort;

class MediaLibrary
{
    public static function resolve(): DefaultPort|SpatieMediaPort
    {
        return Driver::current() === Driver::Spatie
            ? app(SpatieMediaPort::class)
            : app(DefaultPort::class);
    }
}
