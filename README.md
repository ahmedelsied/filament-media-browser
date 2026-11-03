# Filament Media Browser

A shared media library for Filament 3, 4, and 5. Editors upload and manage images and documents in the panel, then attach those files to records from a form field. One file can be used by many records. Detaching it removes the usage and leaves the file in the library.

The package ships with two drivers:

| Driver | `config('media-browser.driver')` | Storage |
| --- | --- | --- |
| Default | `default` | `media_browser_files`, `media_browser_attachments` |
| Spatie | `spatie` | Spatie media owned by one `media_pool` row, collection `library`. Usages are stored in `media_attachments`. |

Use the default driver for a new library. Use the Spatie driver when the application already stores files with [spatie/laravel-medialibrary](https://github.com/spatie/laravel-medialibrary).

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Form field](#form-field)
- [Reading and updating attachments](#reading-and-updating-attachments)
  - [Default driver](#default-driver)
  - [Spatie driver](#spatie-driver)
- [File URLs](#file-urls)
- [Configuration](#configuration)
- [License](#license)
- [Contributing](#contributing)

## Requirements

- PHP 8.4+
- Laravel 11, 12, or 13
- Filament 3.2, 4, or 5

The Spatie driver also requires `spatie/laravel-medialibrary` 11+.

## Installation

Install the latest stable release from [Packagist](https://packagist.org/packages/ahmedelsied/filament-media-browser):

```bash
composer require ahmedelsied/filament-media-browser
php artisan media-browser:install
php artisan migrate
```

Composer installs the highest stable version, currently `v1.0.0`. Laravel discovers `AhmedElsied\FilamentMediaBrowser\MediaBrowserServiceProvider` automatically.

`media-browser:install` asks whether the project already uses Filament Spatie Media.

| Answer | Driver | Migrations |
| --- | --- | --- |
| No | `default` | `media_browser_files`, `media_browser_attachments` |
| Yes | `spatie` | `media_pool`, `media_attachments` |

Answer **yes** only when `spatie/laravel-medialibrary` 11+ is installed. The command publishes `config/media-browser.php` and that driver's migrations. An existing config file keeps its other values, and the `driver` key is updated. A migration that is already published is left unchanged. The command does not edit `.env`.

Register the plugin on the panel:

```php
use AhmedElsied\FilamentMediaBrowser\MediaBrowserPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            MediaBrowserPlugin::make(),
        ]);
}
```

This adds two resources:

| Resource | URL |
| --- | --- |
| Image Browser | `/media-browser` |
| Document Browser | `/document-browser` |

A signed-in user who can access the panel can upload, inspect, and permanently delete library files. The view modal lists every record that uses the file and links to that record's edit page, or its view page when the resource has no edit page.

To send those links to a parent record, or to set the type label:

```php
MediaBrowserPlugin::make()
    ->linkTargetUsing(fn (Model $record): Model => $record)
    ->recordUrlUsing(fn (Model $record): ?string => null)
    ->typeLabelUsing(fn (Model $record): ?string => null);
```

`linkTargetUsing` selects the model that owns the page. `recordUrlUsing` returns its URL. An empty result falls back to the model's Filament resource. `typeLabelUsing` replaces the class basename shown before the record title.

Changing `driver` later requires the other driver's migration. Files are not copied from one driver to the other.

## Form field

`MediaBrowserField` opens the library. The editor selects an existing file or uploads a new one. Saving the form writes the attachment for that collection. The field does not add a column to the record.

```php
use AhmedElsied\FilamentMediaBrowser\Forms\Components\MediaBrowserField;

MediaBrowserField::make('cover')
    ->image()
    ->collection('cover');

MediaBrowserField::make('attachments')
    ->document()
    ->multiple()
    ->collection('attachments');
```

| Method | Behavior |
| --- | --- |
| `image()` | Default. One JPEG, PNG, WebP, or SVG, up to 2048 KB. |
| `document()` | PDF, DOC, DOCX, PPT, PPTX, TXT, or CSV, up to 5120 KB. |
| `multiple()` | More than one file, stored in the chosen order. |
| `collection()` | Collection name used when reading the files back. Defaults to the field name. |
| `disk()` | Disk for a new upload. |

Any Eloquent model can use the field on the default driver. On the Spatie driver the model must use `InteractsWithPoolMedia`, as shown below. View pages keep the preview and hide the choose and remove actions.

## Reading and updating attachments

A record stores a link to a library file, grouped by collection name (`cover`, `gallery`, and so on). Use the code below from an API resource, a job, or anywhere outside the form.

`MediaLibrary::resolve()` returns the configured driver.

### Default driver

No trait is required.

```php
use AhmedElsied\FilamentMediaBrowser\MediaLibrary;

$library = MediaLibrary::resolve();

$cover = $library->attachments($post, 'cover');
```

`attachments()` returns the `MediaBrowserFile` models for that collection, in saved order.

To add a file to the library and attach it, pass `getKey()` from each file model. That value is `media_browser_files.id`. `syncAttachments()` replaces one collection. Other collections on the same record are left unchanged, and the library files are not deleted.

```php
$uploaded = $library->storeUpload($upload, 'Homepage hero');
$currentCover = $library->attachments($post, 'cover')->first();

$library->syncAttachments($post, 'gallery', [
    $uploaded->getKey(),
    $currentCover->getKey(),
]);
```

`$upload` is an `Illuminate\Http\UploadedFile`. Clear a collection by passing an empty list:

```php
$library->syncAttachments($post, 'gallery', []);
```

### Spatie driver

Implement Spatie's `HasMedia` and use the pool trait:

```php
use AhmedElsied\FilamentMediaBrowser\Contracts\AttachesMedia;
use AhmedElsied\FilamentMediaBrowser\Traits\InteractsWithPoolMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

class Post extends Model implements AttachesMedia, HasMedia
{
    use InteractsWithPoolMedia;
}
```

`getFirstMedia()` returns the first file in a collection. `getMedia()` returns every file in that collection, in order. When a collection has no library usage, both methods fall back to media the model owns directly, including files uploaded with the Filament Spatie field. `clearMediaCollection()` deletes only those owned files.

```php
$cover = $post->getFirstMedia('cover');
$gallery = $post->getMedia('gallery');
```

`syncAttachedMedia()` takes Spatie `media.id` values from `getKey()`. The uuid shown in the picker is a different value. When you have a uuid, load the media model first.

```php
use AhmedElsied\FilamentMediaBrowser\MediaLibrary;

$library = MediaLibrary::resolve();

$uploaded = $library->storeUpload($upload, 'Homepage hero');
$picked = $library->findFiles([$uuid])->first();

$post->syncAttachedMedia('gallery', [
    $uploaded->getKey(),
    $picked->getKey(),
]);
```

`attachMedia($file, 'cover')` attaches a single file. `detachMediaCollection('cover')` removes that collection's links and leaves the files in the library.

`MediaLibrary::resolve()->syncAttachments()` performs the same replacement on either driver. On the Spatie driver the model must use `InteractsWithPoolMedia`.

## File URLs

`PreviewUrl::for()` returns the public URL, or a temporary URL when the disk is a private cloud disk.

Default driver:

```php
use AhmedElsied\FilamentMediaBrowser\MediaLibrary;
use AhmedElsied\FilamentMediaBrowser\Support\PreviewUrl;

$library = MediaLibrary::resolve();
$cover = $library->attachments($post, 'cover')->first();
$gallery = $library->attachments($post, 'gallery');

return [
    'cover' => $cover ? PreviewUrl::for($cover) : null,
    'gallery' => $gallery->map(fn ($file) => PreviewUrl::for($file))->values(),
];
```

`MediaBrowserFile::getUrl()` returns the plain disk URL for a public disk.

Spatie driver:

```php
use AhmedElsied\FilamentMediaBrowser\Support\PreviewUrl;

$cover = $post->getFirstMedia('cover');

return [
    'cover' => $cover ? PreviewUrl::for($cover) : null,
    'gallery' => $post->getMedia('gallery')
        ->map(fn ($media) => PreviewUrl::for($media))
        ->values(),
];
```

`getUrl()` on the Spatie media model returns Spatie's plain URL.

## Configuration

Published to `config/media-browser.php`.

| Key | Default | Purpose |
| --- | --- | --- |
| `driver` | `default` | `default` or `spatie`. Read in code with `Driver::current()`. |
| `disk` | `public` | Filesystem disk for the default driver. The Spatie driver uses `config('media-library.disk_name')`. |

Interface strings are included in English and Arabic and follow the application locale.

## License

MIT.

## Contributing

Open an issue or a pull request on the repository. Bug reports should include the Filament version, the driver (`default` or `spatie`), and the steps that reproduce the problem.

Before you send a change:

1. Fork the repository and create a branch from the default branch.
2. Keep the public API stable. `MediaBrowserField`, `MediaLibrary`, and `MediaBrowserPlugin` are what applications call.
3. Test the change on the driver it touches. A change that reads or writes attachments needs to work for both drivers when both drivers share that code.
4. A Filament class that moved between versions must go through `FilamentVersion`, so the package still loads on Filament 3.2, 4, and 5.
5. Add or update the English and Arabic strings together when the interface copy changes.
6. Update this README when the install steps or the public usage change.

Match the style of the surrounding code. Do not commit published config, `.env` files, or local vendor directories.
