<?php

namespace A0x01\FilamentMediaBrowser\Ports;

use A0x01\FilamentMediaBrowser\Enums\LibraryKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

interface SpatieMediaPort
{
    public function libraryQuery(LibraryKind $kind): Builder;

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeUploads(LibraryKind $kind, array $data): int;

    public function storeUpload(UploadedFile $upload, ?string $name = null, ?string $disk = null): Model;

    /**
     * @param  list<string>  $keys
     * @return Collection<string, Model>
     */
    public function findFiles(array $keys): Collection;

    /**
     * @param  list<string>  $keys
     * @return Collection<int, Model>
     */
    public function findLibraryFiles(LibraryKind $kind, array $keys): Collection;

    /**
     * @return Collection<int, Model>
     */
    public function attachments(Model $record, string $collection): Collection;

    /**
     * @param  list<int|string>  $ids
     */
    public function syncAttachments(Model $record, string $collection, array $ids): void;

    public function delete(object $record): void;

    /**
     * @return list<array{label: string, url: ?string}>
     */
    public function usages(object $record): array;

    public function usageCount(object $record): int;

    public function isLibraryOwned(object $record): bool;

    public function ownedMedia(object $model, string $collection): mixed;

    public function clearOwnedCollection(object $model, string $collection): void;
}
