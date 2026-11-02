<?php

namespace A0x01\FilamentMediaBrowser\Support;

use Illuminate\Database\Eloquent\Model;

class FileKey
{
    public static function of(Model $file): string
    {
        $uuid = $file->getAttribute('uuid');

        return filled($uuid) ? (string) $uuid : (string) $file->getKey();
    }
}
