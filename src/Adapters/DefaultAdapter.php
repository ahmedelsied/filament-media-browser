<?php

namespace A0x01\FilamentMediaBrowser\Adapters;

use A0x01\FilamentMediaBrowser\Enums\LibraryKind;
use A0x01\FilamentMediaBrowser\Models\MediaBrowserFile;
use A0x01\FilamentMediaBrowser\Ports\DefaultPort;
use A0x01\FilamentMediaBrowser\Support\LibraryFiles;
use A0x01\FilamentMediaBrowser\Support\UsageLinks;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class DefaultAdapter implements DefaultPort
{
    public function libraryQuery(LibraryKind $kind): Builder
    {
        $query = MediaBrowserFile::query()->select([
            'id',
            'name',
            'file_name',
            'mime_type',
            'disk',
            'path',
            'size',
            'custom_properties',
            'created_at',
        ]);

        return match ($kind) {
            LibraryKind::DOCUMENT => $query->whereIn('mime_type', LibraryFiles::DOCUMENT_MIME_TYPES),
            LibraryKind::IMAGE => $query->where('mime_type', 'like', 'image/%'),
        };
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

            if ($this->writeUpload($upload, $name !== '' ? $name : null) instanceof MediaBrowserFile) {
                $count++;
            }
        }

        return $count;
    }

    public function storeUpload(UploadedFile $upload, ?string $name = null, ?string $disk = null): Model
    {
        $stored = $this->writeUpload($upload, $name, $disk);

        if (! $stored instanceof MediaBrowserFile) {
            throw new RuntimeException('Could not store the uploaded file.');
        }

        return $stored;
    }

    public function findFiles(array $keys): Collection
    {
        return $this->filesInKeyOrder(
            MediaBrowserFile::query()->whereIn('id', $this->filledKeys($keys))->get(),
            $keys,
        );
    }

    public function findLibraryFiles(LibraryKind $kind, array $keys): Collection
    {
        return $this->findFiles($keys)
            ->filter(function (Model $file) use ($kind): bool {
                if (! $file instanceof MediaBrowserFile) {
                    return false;
                }

                return match ($kind) {
                    LibraryKind::DOCUMENT => in_array($file->mime_type, LibraryFiles::DOCUMENT_MIME_TYPES, true),
                    LibraryKind::IMAGE => str_starts_with((string) $file->mime_type, 'image/'),
                };
            })
            ->values();
    }

    public function attachments(Model $record, string $collection): Collection
    {
        return $this->files($record)
            ->wherePivot('collection_name', $collection)
            ->get();
    }

    public function syncAttachments(Model $record, string $collection, array $ids): void
    {
        $this->files($record)
            ->wherePivot('collection_name', $collection)
            ->detach();

        $ids = array_values(array_unique(array_filter(
            $ids,
            fn (mixed $id): bool => filled($id),
        )));

        foreach ($ids as $order => $id) {
            $this->files($record)->attach($id, [
                'collection_name' => $collection,
                'order_column' => $order,
            ]);
        }
    }

    public function delete(object $record): void
    {
        if ($record instanceof Model) {
            $record->delete();
        }
    }

    public function usages(object $record): array
    {
        if (! $record instanceof MediaBrowserFile) {
            return [];
        }

        return UsageLinks::forForeignKey('media_browser_attachments', 'media_browser_file_id', $record->getKey());
    }

    public function usageCount(object $record): int
    {
        if (! $record instanceof Model) {
            return 0;
        }

        return UsageLinks::count('media_browser_attachments', 'media_browser_file_id', $record->getKey());
    }

    public function isLibraryOwned(object $record): bool
    {
        return $record instanceof MediaBrowserFile;
    }

    private function writeUpload(UploadedFile $upload, ?string $name, ?string $disk = null): ?MediaBrowserFile
    {
        $disk = filled($disk) ? $disk : (string) config('media-browser.disk', 'public');
        $id = (string) Str::ulid();
        $fileName = $upload->getClientOriginalName();
        $path = 'media-browser/'.$id.'/'.$fileName;
        $name = trim((string) $name);

        if ($upload instanceof TemporaryUploadedFile && ! $this->isTmpDiskLocal()) {
            $stream = $upload->readStream();

            if (! is_resource($stream)) {
                return null;
            }

            Storage::disk($disk)->writeStream($path, $stream);
        } else {
            $stored = $upload->storeAs('media-browser/'.$id, $fileName, $disk);
            $path = is_string($stored) ? $stored : $path;
        }

        return MediaBrowserFile::query()->create([
            'id' => $id,
            'name' => $name !== '' ? $name : pathinfo($fileName, PATHINFO_FILENAME),
            'file_name' => $fileName,
            'mime_type' => $upload->getMimeType(),
            'disk' => $disk,
            'path' => $path,
            'size' => $upload->getSize() ?: 0,
            'custom_properties' => LibraryFiles::uploaderProperties(),
        ]);
    }

    /**
     * @return MorphToMany<MediaBrowserFile, Model>
     */
    private function files(Model $record): MorphToMany
    {
        return $record
            ->morphToMany(
                MediaBrowserFile::class,
                'attachable',
                'media_browser_attachments',
                'attachable_id',
                'media_browser_file_id',
            )
            ->withPivot(['collection_name', 'order_column'])
            ->orderByPivot('order_column')
            ->withTimestamps();
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

    /**
     * @param  Collection<int, Model>  $files
     * @param  list<string>  $keys
     * @return Collection<string, Model>
     */
    private function filesInKeyOrder(Collection $files, array $keys): Collection
    {
        $byKey = $files->keyBy(fn (Model $file): string => (string) $file->getKey());
        $ordered = new Collection;

        foreach ($this->filledKeys($keys) as $key) {
            $file = $byKey->get($key);

            if ($file instanceof Model) {
                $ordered->put($key, $file);
            }
        }

        return $ordered;
    }

    private function isTmpDiskLocal(): bool
    {
        return config('filesystems.disks.'.FileUploadConfiguration::disk().'.driver') === 'local';
    }
}
