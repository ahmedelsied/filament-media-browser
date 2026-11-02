<?php

namespace A0x01\FilamentMediaBrowser\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

class MediaBrowserFile extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'custom_properties' => 'array',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $file): void {
            if (filled($file->disk) && filled($file->path)) {
                Storage::disk($file->disk)->delete($file->path);
            }
        });
    }

    public function getCustomProperty(string $propertyName, mixed $default = null): mixed
    {
        return data_get($this->custom_properties, $propertyName, $default);
    }

    public function getUrl(?string $conversionName = null): string
    {
        if (blank($this->disk) || blank($this->path)) {
            return '';
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    public function hasGeneratedConversion(string $conversionName): bool
    {
        return false;
    }

    protected function humanReadableSize(): Attribute
    {
        return Attribute::get(fn (): string => Number::fileSize((int) $this->size, precision: 2));
    }
}
