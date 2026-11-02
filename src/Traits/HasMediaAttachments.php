<?php

namespace A0x01\FilamentMediaBrowser\Traits;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Events\CollectionHasBeenClearedEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Lets a model reference pool-owned media through the `media_attachments`
 * usage pivot: one physical file, many usages, detach-only removal.
 *
 * Requires the model to implement Spatie's HasMedia (InteractsWithMedia).
 * getFirstMedia() / getMedia() check pivot usages first and fall back to the
 * model's own Spatie collections, so every existing read site keeps working.
 *
 * @method \Illuminate\Database\Eloquent\Relations\MorphMany<Media, $this> media()
 * @method Media|null getFirstMediaFromSpatieCollection(string $collectionName = 'default', mixed $filters = [])
 * @method MediaCollection<int, Media> getMediaFromSpatieCollection(string $collectionName = 'default', array|callable $filters = [])
 */
trait HasMediaAttachments
{
    public static function bootHasMediaAttachments(): void
    {
        // Soft delete leaves `$model->exists` true, so usages stay until a
        // real / force delete. Models without SoftDeletes always hard-delete.
        static::deleted(function (self $model): void {
            if ($model->exists) {
                return;
            }

            $model->attachedMedia()->detach();
        });
    }

    /**
     * @return MorphToMany<Media, $this>
     */
    public function attachedMedia(): MorphToMany
    {
        return $this
            ->morphToMany(Media::class, 'attachable', 'media_attachments', 'attachable_id', 'media_id')
            ->withPivot(['collection_name', 'order_column'])
            ->orderByPivot('order_column')
            ->withTimestamps();
    }

    /**
     * Replace the model's usage for the given collection with the given media.
     * The media row and file stay owned by the pool; only a pivot row is written.
     */
    public function attachMedia(Media $media, string $collectionName, ?int $orderColumn = null): void
    {
        $this->syncAttachedMedia($collectionName, [$media->getKey()]);

        if ($orderColumn !== null) {
            $this->attachedMedia()->updateExistingPivot($media->getKey(), [
                'order_column' => $orderColumn,
            ]);
            $this->unsetRelation('attachedMedia');
        }
    }

    /**
     * Replace every usage for the collection with `$mediaIds`, in order.
     *
     * @param  list<int|string>  $mediaIds
     */
    public function syncAttachedMedia(string $collectionName, array $mediaIds): void
    {
        $this->detachMediaCollection($collectionName);

        $ids = array_values(array_unique(array_filter(
            $mediaIds,
            fn (mixed $id): bool => filled($id),
        )));

        foreach ($ids as $order => $mediaId) {
            $this->attachedMedia()->attach($mediaId, [
                'collection_name' => $collectionName,
                'order_column' => $order,
            ]);
        }

        $this->unsetRelation('attachedMedia');
    }

    /**
     * Detach-only removal: deletes the usage pivot row, never the file.
     */
    public function detachMediaCollection(string $collectionName): void
    {
        $this->attachedMedia()->wherePivot('collection_name', $collectionName)->detach();

        $this->unsetRelation('attachedMedia');
    }

    public function getAttachedMedia(string $collectionName): ?Media
    {
        $media = $this->getAttachedMediaCollection($collectionName)->first();

        return $media instanceof Media ? $media : null;
    }

    /**
     * Pivot usages for this collection, in `order_column` order.
     *
     * @return EloquentCollection<int, Media>
     */
    public function getAttachedMediaCollection(string $collectionName): EloquentCollection
    {
        if ($this->relationLoaded('attachedMedia')) {
            return $this->getRelation('attachedMedia')
                ->filter(function (Media $media) use ($collectionName): bool {
                    $pivot = $media->getRelation('pivot');

                    return is_object($pivot)
                        && data_get($pivot, 'collection_name') === $collectionName;
                })
                ->values();
        }

        return $this->attachedMedia()->wherePivot('collection_name', $collectionName)->get();
    }

    public function getFirstMedia(string $collectionName = 'default', mixed $filters = []): ?Media
    {
        return $this->getAttachedMedia($collectionName)
            ?? $this->getFirstMediaFromSpatieCollection($collectionName, $filters);
    }

    /**
     * @param  array<string, mixed>|callable  $filters
     * @return MediaCollection<int, Media>
     */
    public function getMedia(string $collectionName = 'default', array|callable $filters = []): MediaCollection
    {
        $attached = $this->getAttachedMediaCollection($collectionName);

        if ($attached->isNotEmpty()) {
            $collection = new MediaCollection($attached->all());
            $collection->collectionName($collectionName);

            return $collection;
        }

        return $this->getMediaFromSpatieCollection($collectionName, $filters);
    }

    /**
     * Delete Spatie-owned files on this record only. Pool files referenced
     * through media_attachments must never be deleted here — getMedia()
     * returns those usages, and Spatie's clearMediaCollection() would
     * otherwise destroy the shared library file.
     */
    public function clearMediaCollection(string $collectionName = 'default'): HasMedia
    {
        $this->media()
            ->where('collection_name', $collectionName)
            ->get()
            ->each(fn (Media $media) => $media->delete());

        event(new CollectionHasBeenClearedEvent($this, $collectionName));

        $this->unsetRelation('media');

        return $this;
    }
}
