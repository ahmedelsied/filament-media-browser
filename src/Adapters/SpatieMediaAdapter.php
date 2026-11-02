<?php

namespace AhmedElsied\FilamentMediaBrowser\Adapters;

use AhmedElsied\FilamentMediaBrowser\Enums\LibraryKind;
use AhmedElsied\FilamentMediaBrowser\Models\MediaPool;
use AhmedElsied\FilamentMediaBrowser\Ports\SpatieMediaPort;
use AhmedElsied\FilamentMediaBrowser\Support\LibraryFiles;
use AhmedElsied\FilamentMediaBrowser\Support\UsageLinks;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SpatieMediaAdapter implements SpatieMediaPort
{
    public function libraryQuery(LibraryKind $kind): Builder
    {
        return MediaPool::constrainToLibrary(Media::query(), $kind)->select($this->listingColumns());
    }

    public function storeUploads(LibraryKind $kind, array $data): int
    {
        $uploads = $data['uploads'] ?? [];

        if (! is_array($uploads)) {
            $uploads = [$uploads];
        }

        $name = trim((string) ($data['name'] ?? ''));
        $count = 0;

        foreach ($uploads as $upload) {
            if (! $upload instanceof UploadedFile) {
                continue;
            }

            $this->storeUpload($upload, $name !== '' ? $name : null);
            $count++;
        }

        return $count;
    }

    public function storeUpload(UploadedFile $upload, ?string $name = null, ?string $disk = null): Model
    {
        $disk = filled($disk) ? $disk : (string) config('media-library.disk_name', 'public');
        $name = trim((string) $name);

        $adder = $upload instanceof TemporaryUploadedFile && ! $this->isTmpDiskLocal()
            ? MediaPool::instance()->addMediaFromStream($upload->readStream())
            : MediaPool::instance()->addMedia($upload);

        $adder = $adder
            ->usingFileName($upload->getClientOriginalName())
            ->withCustomProperties(LibraryFiles::uploaderProperties());

        if ($name !== '') {
            $adder->usingName($name);
        }

        return $adder->toMediaCollection(MediaPool::LIBRARY_COLLECTION, $disk);
    }

    public function findFiles(array $keys): Collection
    {
        $keys = $this->filledKeys($keys);

        if ($keys === []) {
            return new Collection;
        }

        $files = Media::query()->whereIn('uuid', $keys)->get();
        $byKey = $files->keyBy(fn (Media $file): string => (string) $file->uuid);
        $ordered = new Collection;

        foreach ($keys as $key) {
            $file = $byKey->get($key);

            if ($file instanceof Media) {
                $ordered->put($key, $file);
            }
        }

        return $ordered;
    }

    public function findLibraryFiles(LibraryKind $kind, array $keys): Collection
    {
        return $this->findFiles($keys)
            ->filter(fn (Model $file): bool => $file instanceof Media && MediaPool::isLibraryFile($file, $kind))
            ->values();
    }

    public function attachments(Model $record, string $collection): Collection
    {
        if (! method_exists($record, 'getAttachedMediaCollection')) {
            return new Collection;
        }

        $attached = $record->getAttachedMediaCollection($collection);

        return $attached instanceof Collection
            ? $attached
            : new Collection($attached->all());
    }

    public function syncAttachments(Model $record, string $collection, array $ids): void
    {
        if (! method_exists($record, 'syncAttachedMedia')) {
            throw new RuntimeException('The record must use InteractsWithPoolMedia to attach library files.');
        }

        $record->syncAttachedMedia($collection, $ids);
    }

    public function delete(object $record): void
    {
        if ($record instanceof Model) {
            $record->delete();
        }
    }

    public function usages(object $record): array
    {
        if (! $record instanceof Media) {
            return [];
        }

        $items = UsageLinks::forForeignKey('media_attachments', 'media_id', $record->getKey());

        if ($items !== []) {
            return $items;
        }

        if (! $this->isLibraryOwned($record)) {
            return UsageLinks::forOwner((string) $record->model_type, (string) $record->model_id);
        }

        return [];
    }

    public function usageCount(object $record): int
    {
        if (! $record instanceof Model) {
            return 0;
        }

        return UsageLinks::count('media_attachments', 'media_id', $record->getKey());
    }

    public function isLibraryOwned(object $record): bool
    {
        return $record instanceof Media
            && $record->model_type === (new MediaPool)->getMorphClass();
    }

    public function ownedMedia(object $model, string $collection): mixed
    {
        if (! $model instanceof Model || ! method_exists($model, 'media')) {
            return null;
        }

        return $model->media()->where('collection_name', $collection)->get();
    }

    public function clearOwnedCollection(object $model, string $collection): void
    {
        if ($model instanceof Model && method_exists($model, 'clearMediaCollection')) {
            $model->clearMediaCollection($collection);
        }
    }

    /**
     * Spatie's file remover reads manipulations and responsive_images on delete.
     *
     * @return list<string>
     */
    private function listingColumns(): array
    {
        return [
            'id',
            'uuid',
            'model_type',
            'model_id',
            'collection_name',
            'name',
            'file_name',
            'mime_type',
            'disk',
            'conversions_disk',
            'size',
            'manipulations',
            'custom_properties',
            'generated_conversions',
            'responsive_images',
            'created_at',
        ];
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function filledKeys(array $keys): array
    {
        return array_values(array_filter(
            array_map(fn (mixed $key): string => is_scalar($key) ? (string) $key : '', $keys),
            fn (string $key): bool => $key !== '',
        ));
    }

    private function isTmpDiskLocal(): bool
    {
        return config('filesystems.disks.'.FileUploadConfiguration::disk().'.driver') === 'local';
    }
}
