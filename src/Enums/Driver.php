<?php

namespace AhmedElsied\FilamentMediaBrowser\Enums;

enum Driver: string
{
    case Default = 'default';
    case Spatie = 'spatie';

    public static function current(): self
    {
        $driver = config('media-browser.driver');

        if ($driver instanceof self) {
            return $driver;
        }

        return self::tryFrom((string) $driver) ?? self::Default;
    }
}
