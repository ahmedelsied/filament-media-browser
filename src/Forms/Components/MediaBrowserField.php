<?php

namespace A0x01\FilamentMediaBrowser\Forms\Components;

use A0x01\FilamentMediaBrowser\Enums\Driver;
use A0x01\FilamentMediaBrowser\Enums\LibraryKind;
use A0x01\FilamentMediaBrowser\MediaLibrary;
use A0x01\FilamentMediaBrowser\Support\FileKey;
use A0x01\FilamentMediaBrowser\Support\FilamentVersion;
use A0x01\FilamentMediaBrowser\Support\LibraryFiles;
use A0x01\FilamentMediaBrowser\Support\PreviewUrl;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use LogicException;
use Throwable;

/**
 * Pick a file already in the library, or upload a new one into it.
 * The field does not store a column. Saving writes attachment rows, and
 * removing a file detaches that usage.
 *
 * Single state: ['key' => ?string, 'upload' => ?TemporaryUploadedFile].
 * Multiple state: ['keys' => list<string>, 'uploads' => list<TemporaryUploadedFile>].
 */
class MediaBrowserField extends Field
{
    protected string $view = 'filament-media-browser::forms.media-browser-field';

    protected string|Closure|null $collection = null;

    protected string|Closure|null $disk = null;

    protected bool|Closure $isMultiple = false;

    protected int|Closure|null $maxFiles = null;

    protected LibraryKind|Closure $libraryKind = LibraryKind::IMAGE;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dehydrated(false);

        $this->key(fn (MediaBrowserField $component): string => $component->getStatePath());

        $this->default(fn (MediaBrowserField $component): array => $component->isMultiple()
            ? ['keys' => [], 'uploads' => []]
            : ['key' => null, 'upload' => null]);

        $this->registerActions([
            $this->makePickAction(),
            $this->makeRemoveAction(),
            $this->makeRemoveItemAction(),
        ]);

        $this->loadStateFromRelationshipsUsing(
            fn (MediaBrowserField $component) => $component->hydrateFromRecord(),
        );

        $this->saveRelationshipsUsing(
            fn (MediaBrowserField $component, Model $record) => $component->persistTo($record),
        );

        $this->rule(fn (MediaBrowserField $component): Closure => $component->makeRequiredRule());
    }

    public function collection(string|Closure|null $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    public function disk(string|Closure|null $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function multiple(bool|Closure $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    public function maxFiles(int|Closure|null $count): static
    {
        $this->maxFiles = $count;

        return $this;
    }

    public function image(): static
    {
        $this->libraryKind = LibraryKind::IMAGE;

        return $this;
    }

    public function document(): static
    {
        $this->libraryKind = LibraryKind::DOCUMENT;

        return $this;
    }

    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->isMultiple);
    }

    public function isDocument(): bool
    {
        return $this->getLibraryKind() === LibraryKind::DOCUMENT;
    }

    public function getMaxFiles(): ?int
    {
        $max = $this->evaluate($this->maxFiles);

        return is_int($max) && $max > 0 ? $max : null;
    }

    public function getLibraryKind(): LibraryKind
    {
        $kind = $this->evaluate($this->libraryKind);

        return $kind instanceof LibraryKind ? $kind : LibraryKind::IMAGE;
    }

    public function getCollectionName(): string
    {
        $collection = $this->evaluate($this->collection);

        return filled($collection) ? (string) $collection : $this->getName();
    }

    public function getDiskName(): ?string
    {
        $disk = $this->evaluate($this->disk);

        return filled($disk) ? (string) $disk : null;
    }

    public function getCurrentMedia(): ?Model
    {
        return $this->getCurrentMediaItems()[0] ?? null;
    }

    /**
     * @return list<Model>
     */
    public function getCurrentMediaItems(): array
    {
        $keys = $this->getSelectedKeys();

        if ($keys === []) {
            return [];
        }

        $files = MediaLibrary::resolve()->findFiles($keys);

        $ordered = [];

        foreach ($keys as $key) {
            $file = $files->get($key);

            if ($file instanceof Model) {
                $ordered[] = $file;
            }
        }

        return $ordered;
    }

    /**
     * @return list<array{source: 'key'|'upload', index: int, name: string, preview_url: ?string}>
     */
    public function getDisplayItems(): array
    {
        $items = [];

        foreach ($this->getCurrentMediaItems() as $index => $file) {
            $name = (string) ($file->getAttribute('file_name') ?: '');

            $items[] = [
                'source' => 'key',
                'index' => $index,
                'name' => $name,
                'preview_url' => PreviewUrl::for($file) ?: null,
            ];
        }

        foreach ($this->getPendingUploads() as $index => $upload) {
            $previewUrl = null;

            if ($upload instanceof TemporaryUploadedFile) {
                try {
                    $previewUrl = $upload->temporaryUrl();
                } catch (Throwable) {
                    $previewUrl = null;
                }
            }

            $items[] = [
                'source' => 'upload',
                'index' => $index,
                'name' => basename($upload->getClientOriginalName()),
                'preview_url' => filled($previewUrl) ? $previewUrl : null,
            ];
        }

        return $items;
    }

    public function getDisplayPreviewUrl(): ?string
    {
        $uploadPreviewUrl = $this->getUploadPreviewUrl();

        if (filled($uploadPreviewUrl)) {
            return $uploadPreviewUrl;
        }

        $file = $this->getCurrentMedia();

        return $file instanceof Model ? (PreviewUrl::for($file) ?: null) : null;
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    public function pickerTranslation(string $key, array $replace = []): string
    {
        $group = $this->isDocument() ? 'document' : 'image';

        return __('filament-media-browser::media-browser.picker.'.$group.'.'.$key, $replace);
    }

    /**
     * @return list<string>
     */
    public function acceptedFileTypes(): array
    {
        return $this->isDocument()
            ? LibraryFiles::DOCUMENT_MIME_TYPES
            : LibraryFiles::IMAGE_MIME_TYPES;
    }

    public function maxUploadKilobytes(): int
    {
        return $this->isDocument() ? 5120 : 2048;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function applySelection(array $data): void
    {
        if ($this->isMultiple()) {
            $this->applyMultipleSelection($data);

            return;
        }

        $this->applySingleSelection($data);
    }

    public function clear(): void
    {
        $this->state($this->isMultiple()
            ? ['keys' => [], 'uploads' => []]
            : ['key' => null, 'upload' => null]);
    }

    public function removeItem(string $source, int $index): void
    {
        if (! $this->isMultiple()) {
            $this->clear();

            return;
        }

        $keys = $this->getSelectedKeys();
        $uploads = $this->getPendingUploads();

        if ($source === 'key' && array_key_exists($index, $keys)) {
            unset($keys[$index]);
            $keys = array_values($keys);
        }

        if ($source === 'upload' && array_key_exists($index, $uploads)) {
            unset($uploads[$index]);
            $uploads = array_values($uploads);
        }

        $this->state([
            'keys' => $keys,
            'uploads' => $uploads,
        ]);
    }

    /**
     * @return list<string>
     */
    public function getSelectedKeys(): array
    {
        $state = is_array($this->getState()) ? $this->getState() : [];

        $fromList = $state['keys'] ?? null;

        if (is_array($fromList) && $fromList !== []) {
            return $this->wrapKeys($fromList);
        }

        return $this->wrapKeys($state['key'] ?? null);
    }

    /**
     * @return list<UploadedFile>
     */
    public function getPendingUploads(): array
    {
        $state = is_array($this->getState()) ? $this->getState() : [];

        $fromList = $state['uploads'] ?? null;

        if (is_array($fromList) && $fromList !== []) {
            return $this->wrapUploads($fromList);
        }

        return $this->wrapUploads($state['upload'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function applySingleSelection(array $data): void
    {
        $upload = $data['upload'] ?? null;
        $key = $data['selection'] ?? null;

        if (is_array($key)) {
            $key = $key[0] ?? null;
        }

        if (is_array($upload)) {
            $upload = $upload[0] ?? null;
        }

        $pending = $this->getPendingUploads()[0] ?? null;
        $uploadIsNew = $upload instanceof UploadedFile
            && ($pending === null || ! $this->isSameUpload($upload, $pending));

        if ($uploadIsNew) {
            $this->state([
                'key' => null,
                'upload' => $upload,
            ]);

            return;
        }

        if (filled($key)) {
            $this->state([
                'key' => (string) $key,
                'upload' => null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function applyMultipleSelection(array $data): void
    {
        $keys = $this->wrapKeys($data['selection'] ?? null);
        $modalUploads = $this->wrapUploads($data['upload'] ?? null);
        $pending = $this->getPendingUploads();
        $newUploads = $this->uploadsNotIn($modalUploads, $pending);

        if ($newUploads !== []) {
            $uploads = array_merge($pending, $newUploads);
        } elseif ($keys !== []) {
            $uploads = [];
        } else {
            $uploads = $pending;
        }

        if ($uploads === [] && $keys === []) {
            return;
        }

        if (! $this->selectionFitsMaxFiles(count($keys) + count($uploads))) {
            Notification::make()
                ->danger()
                ->title($this->pickerTranslation('max_files', ['count' => $this->getMaxFiles() ?? 0]))
                ->send();

            return;
        }

        $this->state([
            'keys' => $keys,
            'uploads' => $uploads,
        ]);
    }

    protected function makePickAction(): object
    {
        $action = FilamentVersion::formAction();
        $tabs = FilamentVersion::tabs();
        $tab = FilamentVersion::tab();

        return $action::make('pick')
            ->label(fn (MediaBrowserField $component): string => $component->pickerTranslation('choose'))
            ->icon(fn (MediaBrowserField $component): string => $component->isDocument()
                ? 'heroicon-o-document-text'
                : 'heroicon-o-photo')
            ->modalHeading(fn (MediaBrowserField $component): string => $component->pickerTranslation('choose'))
            ->modalWidth('3xl')
            ->modalSubmitActionLabel(fn (MediaBrowserField $component): string => $component->pickerTranslation('use'))
            ->fillForm(fn (MediaBrowserField $component): array => [
                'search' => null,
                'page' => 1,
                'selection' => $component->isMultiple()
                    ? $component->getSelectedKeys()
                    : ($component->getSelectedKeys()[0] ?? null),
                'upload' => null,
            ])
            ->form(fn (MediaBrowserField $component): array => [
                $tabs::make('source')->tabs([
                    $tab::make('library')
                        ->label($component->pickerTranslation('library'))
                        ->schema([
                            TextInput::make('search')
                                ->hiddenLabel()
                                ->placeholder($component->pickerTranslation('search_by_name'))
                                ->live(debounce: 500)
                                ->afterStateUpdated(fn (mixed $set) => $set('page', 1)),
                            Hidden::make('page')
                                ->default(1)
                                ->live(),
                            LibraryGrid::make('selection')
                                ->hiddenLabel()
                                ->libraryKind($component->getLibraryKind())
                                ->multiple($component->isMultiple())
                                ->searchUsing(fn (mixed $get): ?string => $get('search'))
                                ->pageUsing(fn (mixed $get): int => (int) ($get('page') ?: 1)),
                        ]),
                    $tab::make('upload')
                        ->label($component->pickerTranslation('upload_new'))
                        ->schema([
                            $component->makeUploadField(),
                        ]),
                ]),
            ])
            ->action(function (array $data, MediaBrowserField $component): void {
                $component->applySelection($data);
            });
    }

    protected function makeUploadField(): FileUpload
    {
        $field = FileUpload::make('upload')
            ->hiddenLabel()
            ->storeFiles(false)
            ->acceptedFileTypes($this->acceptedFileTypes())
            ->maxSize($this->maxUploadKilobytes());

        if (! $this->isDocument()) {
            $field->image();
        }

        if ($this->isMultiple()) {
            $field->multiple();

            $max = $this->getMaxFiles();

            if ($max !== null) {
                $field->maxFiles($max);
            }
        }

        return $field;
    }

    protected function makeRemoveAction(): object
    {
        $action = FilamentVersion::formAction();

        return $action::make('remove')
            ->label(fn (MediaBrowserField $component): string => $component->pickerTranslation('remove'))
            ->icon('heroicon-o-x-mark')
            ->color('danger')
            ->visible(fn (MediaBrowserField $component): bool => ! $component->isMultiple())
            ->action(function (MediaBrowserField $component): void {
                $component->clear();
            });
    }

    protected function makeRemoveItemAction(): object
    {
        $action = FilamentVersion::formAction();

        return $action::make('removeItem')
            ->label(fn (MediaBrowserField $component): string => $component->pickerTranslation('remove'))
            ->icon('heroicon-m-x-mark')
            ->iconButton()
            ->size('xs')
            ->color('danger')
            ->tooltip(fn (MediaBrowserField $component): string => $component->pickerTranslation('remove'))
            ->action(function (array $arguments, MediaBrowserField $component): void {
                $component->removeItem(
                    is_string($arguments['source'] ?? null) ? $arguments['source'] : '',
                    is_numeric($arguments['index'] ?? null) ? (int) $arguments['index'] : -1,
                );
            });
    }

    protected function hydrateFromRecord(): void
    {
        $record = $this->resolveRecord($this->getRecord());

        if (! $record instanceof Model) {
            return;
        }

        $keys = array_map(
            fn (Model $file): string => FileKey::of($file),
            $this->filesOnRecord($record),
        );

        if ($this->isMultiple()) {
            $this->state([
                'keys' => $keys,
                'uploads' => [],
            ]);

            return;
        }

        $this->state([
            'key' => $keys[0] ?? null,
            'upload' => null,
        ]);
    }

    protected function persistTo(Model $record): void
    {
        $record = $this->resolveRecord($record);

        if (! $record instanceof Model) {
            return;
        }

        $this->assertRecordCanAttach($record);

        $uploads = $this->getPendingUploads();
        $keys = $this->getSelectedKeys();

        if ($uploads === [] && $keys === []) {
            $this->clearCollection($record);

            return;
        }

        $port = MediaLibrary::resolve();
        $library = $port->findLibraryFiles($this->getLibraryKind(), $keys);
        $files = $library->all();

        foreach ($uploads as $upload) {
            $name = pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME);
            $files[] = $port->storeUpload($upload, $name !== '' ? $name : null, $this->getDiskName());
        }

        if ($files !== []) {
            $port->syncAttachments($record, $this->getCollectionName(), array_map(
                fn (Model $file): mixed => $file->getKey(),
                $files,
            ));
            $this->clearLegacyOwned($record);
            $this->stateFromFiles($files);

            return;
        }

        if ($this->selectedKeysAreLegacy($record, $keys)) {
            return;
        }

        abort(404);
    }

    /**
     * @return list<Model>
     */
    protected function filesOnRecord(Model $record): array
    {
        if (Driver::current() === Driver::Spatie && method_exists($record, 'getMedia')) {
            if ($this->isMultiple()) {
                return $record->getMedia($this->getCollectionName())->all();
            }

            $first = $record->getFirstMedia($this->getCollectionName());

            return $first instanceof Model ? [$first] : [];
        }

        $attached = MediaLibrary::resolve()->attachments($record, $this->getCollectionName());

        if (! $this->isMultiple()) {
            $first = $attached->first();

            return $first instanceof Model ? [$first] : [];
        }

        return $attached->all();
    }

    protected function clearCollection(Model $record): void
    {
        MediaLibrary::resolve()->syncAttachments($record, $this->getCollectionName(), []);
        $this->clearLegacyOwned($record);
        $this->clear();
    }

    protected function clearLegacyOwned(Model $record): void
    {
        if (Driver::current() !== Driver::Spatie || ! method_exists($record, 'clearMediaCollection')) {
            return;
        }

        $record->clearMediaCollection($this->getCollectionName());
    }

    /**
     * @param  list<string>  $keys
     */
    protected function selectedKeysAreLegacy(Model $record, array $keys): bool
    {
        if ($keys === [] || ! method_exists($record, 'getMedia')) {
            return false;
        }

        $owned = $record->getMedia($this->getCollectionName())->keyBy(
            fn (Model $file): string => FileKey::of($file),
        );
        $port = MediaLibrary::resolve();

        foreach ($keys as $key) {
            $file = $owned->get($key);

            if (! $file instanceof Model || $port->isLibraryOwned($file)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<Model>  $files
     */
    protected function stateFromFiles(array $files): void
    {
        $keys = array_values(array_filter(array_map(
            fn (Model $file): string => FileKey::of($file),
            $files,
        )));

        if ($this->isMultiple()) {
            $this->state(['keys' => $keys, 'uploads' => []]);

            return;
        }

        $this->state(['key' => $keys[0] ?? null, 'upload' => null]);
    }

    protected function resolveRecord(?Model $record): ?Model
    {
        if (! $record instanceof Model) {
            return null;
        }

        if (Driver::current() !== Driver::Spatie || method_exists($record, 'syncAttachedMedia')) {
            return $record;
        }

        $modelClass = $this->getModel();

        if (
            is_string($modelClass)
            && class_exists($modelClass)
            && is_subclass_of($modelClass, Model::class)
            && $record->getKey() !== null
        ) {
            $found = $modelClass::query()->find($record->getKey());

            if ($found instanceof Model && method_exists($found, 'syncAttachedMedia')) {
                return $found;
            }
        }

        return $record;
    }

    protected function assertRecordCanAttach(Model $record): void
    {
        if (Driver::current() === Driver::Spatie && ! method_exists($record, 'syncAttachedMedia')) {
            throw new LogicException(sprintf(
                'MediaBrowserField requires the record model [%s] to use InteractsWithPoolMedia.',
                $record::class,
            ));
        }
    }

    protected function makeRequiredRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $this->isRequired()) {
                return;
            }

            if ($this->getSelectedKeys() !== [] || $this->getPendingUploads() !== []) {
                return;
            }

            $fail('validation.required')->translate();
        };
    }

    protected function getUploadPreviewUrl(): ?string
    {
        $upload = $this->getPendingUploads()[0] ?? null;

        if (! $upload instanceof TemporaryUploadedFile) {
            return null;
        }

        try {
            return $upload->temporaryUrl();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<UploadedFile>
     */
    protected function wrapUploads(mixed $upload): array
    {
        if ($upload instanceof UploadedFile) {
            return [$upload];
        }

        if (! is_array($upload)) {
            return [];
        }

        return array_values(array_filter(
            $upload,
            fn (mixed $file): bool => $file instanceof UploadedFile,
        ));
    }

    /**
     * @param  list<UploadedFile>  $uploads
     * @param  list<UploadedFile>  $existing
     * @return list<UploadedFile>
     */
    protected function uploadsNotIn(array $uploads, array $existing): array
    {
        return array_values(array_filter(
            $uploads,
            fn (UploadedFile $upload): bool => ! $this->uploadListContains($existing, $upload),
        ));
    }

    /**
     * @param  list<UploadedFile>  $haystack
     */
    protected function uploadListContains(array $haystack, UploadedFile $needle): bool
    {
        foreach ($haystack as $candidate) {
            if ($this->isSameUpload($candidate, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function isSameUpload(UploadedFile $left, UploadedFile $right): bool
    {
        if ($left === $right) {
            return true;
        }

        $leftName = $left instanceof TemporaryUploadedFile
            ? $left->getFilename()
            : $left->getClientOriginalName();
        $rightName = $right instanceof TemporaryUploadedFile
            ? $right->getFilename()
            : $right->getClientOriginalName();

        return $leftName !== '' && $leftName === $rightName;
    }

    /**
     * @return list<string>
     */
    protected function wrapKeys(mixed $key): array
    {
        if (is_scalar($key) && (string) $key !== '') {
            return [(string) $key];
        }

        if (! is_array($key)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
                $key,
            ),
            fn (string $value): bool => $value !== '',
        ));
    }

    protected function selectionFitsMaxFiles(int $count): bool
    {
        $max = $this->getMaxFiles();

        return $max === null || $count <= $max;
    }
}
