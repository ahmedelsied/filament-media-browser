<?php

namespace A0x01\FilamentMediaBrowser\Models;

use BackedEnum;
use A0x01\FilamentMediaBrowser\Enums\LibraryKind;
use A0x01\FilamentMediaBrowser\Support\LibraryFiles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Singleton owner of every shared (picker-managed) media file.
 *
 * Files uploaded through the ImagePicker / DocumentPicker are always attached
 * to the pool; models reference them through the `media_attachments` usage
 * pivot, so one physical file can be used in many places and is never copied.
 */
class MediaPool extends Model implements HasMedia
{
    use HasUlids;
    use InteractsWithMedia;

    public const LIBRARY_COLLECTION = 'library';

    /**
     * @var list<string>
     */
    public const DOCUMENT_MIME_TYPES = LibraryFiles::DOCUMENT_MIME_TYPES;

    protected $table = 'media_pool';

    protected $guarded = [];

    public static function instance(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /**
     * @return array{uploaded_by_id: string, uploaded_by_name: string}|array{}
     */
    public static function currentUploaderProperties(): array
    {
        return LibraryFiles::uploaderProperties();
    }

    /**
     * @param  Builder<Media>  $query
     * @return Builder<Media>
     */
    public static function constrainToLibraryImages(Builder $query): Builder
    {
        return self::constrainToLibrary($query, LibraryKind::IMAGE);
    }

    /**
     * @param  Builder<Media>  $query
     * @return Builder<Media>
     */
    public static function constrainToLibraryDocuments(Builder $query): Builder
    {
        return self::constrainToLibrary($query, LibraryKind::DOCUMENT);
    }

    /**
     * @param  Builder<Media>  $query
     * @return Builder<Media>
     */
    public static function constrainToLibrary(Builder $query, LibraryKind|BackedEnum|string $kind): Builder
    {
        $query
            ->where('model_type', (new self)->getMorphClass())
            ->where('collection_name', self::LIBRARY_COLLECTION);

        return match (self::normalizeKind($kind)) {
            LibraryKind::DOCUMENT => $query->whereIn('mime_type', self::DOCUMENT_MIME_TYPES),
            LibraryKind::IMAGE => $query->where('mime_type', 'like', 'image/%'),
        };
    }

    /**
     * @return Builder<Media>
     */
    public static function libraryImagesQuery(): Builder
    {
        return self::constrainToLibraryImages(Media::query());
    }

    /**
     * @return Builder<Media>
     */
    public static function libraryDocumentsQuery(): Builder
    {
        return self::constrainToLibraryDocuments(Media::query());
    }

    /**
     * @return Builder<Media>
     */
    public static function libraryQuery(LibraryKind|BackedEnum|string $kind): Builder
    {
        return self::constrainToLibrary(Media::query(), $kind);
    }

    public static function isLibraryFile(Media $media, LibraryKind|BackedEnum|string|null $kind = null): bool
    {
        if ($media->model_type !== (new self)->getMorphClass()) {
            return false;
        }

        if ($media->collection_name !== self::LIBRARY_COLLECTION) {
            return false;
        }

        return match ($kind === null ? null : self::normalizeKind($kind)) {
            LibraryKind::IMAGE => str_starts_with((string) $media->mime_type, 'image/'),
            LibraryKind::DOCUMENT => in_array($media->mime_type, self::DOCUMENT_MIME_TYPES, true),
            null => true,
        };
    }

    public static function mimeTypeName(?string $mimeType): string
    {
        return LibraryFiles::name($mimeType);
    }

    private static function normalizeKind(LibraryKind|BackedEnum|string $kind): LibraryKind
    {
        if ($kind instanceof LibraryKind) {
            return $kind;
        }

        $value = $kind instanceof BackedEnum ? (string) $kind->value : $kind;

        return LibraryKind::from($value);
    }
}
