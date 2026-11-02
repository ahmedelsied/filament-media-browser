<?php

namespace AhmedElsied\FilamentMediaBrowser;

use AhmedElsied\FilamentMediaBrowser\Enums\Driver;
use AhmedElsied\FilamentMediaBrowser\Ports\DefaultPort;
use AhmedElsied\FilamentMediaBrowser\Ports\SpatieMediaPort;

class MediaLibrary
{
    public static function resolve(): DefaultPort|SpatieMediaPort
    {
        return Driver::current() === Driver::Spatie
            ? app(SpatieMediaPort::class)
            : app(DefaultPort::class);
    }
}
