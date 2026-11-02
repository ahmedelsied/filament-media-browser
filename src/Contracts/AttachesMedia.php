<?php

namespace AhmedElsied\FilamentMediaBrowser\Contracts;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Models that can reference pool-owned Spatie media through media_attachments.
 *
 * @mixin Model
 */
interface AttachesMedia extends HasMedia
{
    public function attachMedia(Media $media, string $collectionName, ?int $orderColumn = null): void;

    /**
     * @param  list<int|string>  $mediaIds
     */
    public function syncAttachedMedia(string $collectionName, array $mediaIds): void;

    public function detachMediaCollection(string $collectionName): void;

    public function getAttachedMedia(string $collectionName): ?Media;

    public function getFirstMedia(string $collectionName = 'default', mixed $filters = []): ?Media;

    /**
     * @param  array<string, mixed>|callable  $filters
     * @return \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media>
     */
    public function getMedia(string $collectionName = 'default', array|callable $filters = []): \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;

    public function clearMediaCollection(string $collectionName = 'default'): HasMedia;
}
