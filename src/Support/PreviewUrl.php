<?php

namespace AhmedElsied\FilamentMediaBrowser\Support;

use Illuminate\Database\Eloquent\Model;
use Throwable;

class PreviewUrl
{
    public static function for(Model $file, int $ttlMinutes = 30): string
    {
        $disk = $file->getAttribute('disk');

        if (self::isPrivateCloudDisk(is_string($disk) ? $disk : null) && method_exists($file, 'getTemporaryUrl')) {
            try {
                return (string) $file->getTemporaryUrl(now()->addMinutes($ttlMinutes));
            } catch (Throwable) {
                // The driver cannot sign URLs. Fall back to the plain URL.
            }
        }

        return method_exists($file, 'getUrl') ? (string) $file->getUrl() : '';
    }

    private static function isPrivateCloudDisk(?string $disk): bool
    {
        $config = config('filesystems.disks.'.$disk, []);

        if (($config['driver'] ?? 'local') === 'local') {
            return false;
        }

        return ($config['visibility'] ?? 'private') !== 'public';
    }
}
