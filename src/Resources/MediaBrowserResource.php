<?php

namespace AhmedElsied\FilamentMediaBrowser\Resources;

use AhmedElsied\FilamentMediaBrowser\Enums\Driver;
use AhmedElsied\FilamentMediaBrowser\Enums\LibraryKind;
use AhmedElsied\FilamentMediaBrowser\Models\MediaBrowserFile;
use AhmedElsied\FilamentMediaBrowser\Ports\DefaultPort;
use AhmedElsied\FilamentMediaBrowser\Ports\SpatieMediaPort;
use AhmedElsied\FilamentMediaBrowser\Resources\MediaBrowserResource\Pages\ListMediaBrowser;
use AhmedElsied\FilamentMediaBrowser\Support\FilamentVersion;
use AhmedElsied\FilamentMediaBrowser\Support\LibraryFiles;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Resource;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaBrowserResource extends Resource
{
    protected static ?string $slug = 'media-browser';

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 100;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = false;

    public static function getModel(): string
    {
        return Driver::current() === Driver::Spatie
            ? Media::class
            : MediaBrowserFile::class;
    }

    public static function getModelLabel(): string
    {
        return __('filament-media-browser::media-browser.resources.image_browser');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-media-browser::media-browser.resources.image_browser');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-media-browser::media-browser.resources.image_browser');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-media-browser::media-browser.groups.media');
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        $panel = Filament::getCurrentPanel();

        return $user !== null
            && $panel !== null
            && method_exists($user, 'canAccessPanel')
            && $user->canAccessPanel($panel);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return static::port()->libraryQuery(static::libraryKind());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50, 100])
            ->columns(self::tableColumns())
            ->filters(self::tableFilters(), FiltersLayout::AboveContent)
            ->actions(self::recordActions())
            ->emptyStateHeading(fn (ListRecords $livewire): string => self::hasActiveTableState($livewire)
                ? static::trans('empty_filtered_heading')
                : static::trans('empty_heading'))
            ->emptyStateDescription(fn (ListRecords $livewire): string => self::hasActiveTableState($livewire)
                ? static::trans('empty_filtered_description')
                : static::trans('empty_description'))
            ->emptyStateIcon(fn (): string => static::libraryKind() === LibraryKind::DOCUMENT
                ? 'heroicon-o-document-text'
                : 'heroicon-o-photo')
            ->emptyStateActions(self::emptyStateActions());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaBrowser::route('/'),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function libraryUploadForm(): array
    {
        $isDocument = static::libraryKind() === LibraryKind::DOCUMENT;

        $upload = FileUpload::make('uploads')
            ->label($isDocument
                ? __('filament-media-browser::media-browser.fields.document')
                : __('filament-media-browser::media-browser.fields.image'))
            ->multiple()
            ->required()
            ->storeFiles(false);

        if ($isDocument) {
            $upload
                ->acceptedFileTypes(LibraryFiles::DOCUMENT_MIME_TYPES)
                ->maxSize(5120)
                ->helperText(__('filament-media-browser::media-browser.fields.document_upload_helper'));
        } else {
            $upload
                ->image()
                ->acceptedFileTypes(LibraryFiles::IMAGE_MIME_TYPES)
                ->maxSize(2048)
                ->helperText(__('filament-media-browser::media-browser.fields.image_upload_helper'));
        }

        return [
            TextInput::make('name')
                ->label(__('filament-media-browser::media-browser.fields.name'))
                ->required()
                ->maxLength(255),
            $upload,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function storeLibraryUploads(array $data): int
    {
        return static::port()->storeUploads(static::libraryKind(), $data);
    }

    public static function notifyLibraryUploads(int $count): void
    {
        if ($count < 1) {
            return;
        }

        Notification::make()
            ->title(static::transChoice('uploaded', $count, ['count' => $count]))
            ->success()
            ->send();
    }

    public static function headerUploadLabel(): string
    {
        return static::libraryKind() === LibraryKind::DOCUMENT
            ? static::trans('upload_document')
            : static::trans('upload_image');
    }

    protected static function libraryKind(): LibraryKind
    {
        return LibraryKind::IMAGE;
    }

    protected static function port(): DefaultPort|SpatieMediaPort
    {
        return Driver::current() === Driver::Spatie
            ? app(SpatieMediaPort::class)
            : app(DefaultPort::class);
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    protected static function trans(string $key, array $replace = []): string
    {
        $group = static::libraryKind() === LibraryKind::DOCUMENT ? 'document_browser' : 'image_browser';

        return __("filament-media-browser::media-browser.{$group}.{$key}", $replace);
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    protected static function transChoice(string $key, int $number, array $replace = []): string
    {
        $group = static::libraryKind() === LibraryKind::DOCUMENT ? 'document_browser' : 'image_browser';

        return trans_choice("filament-media-browser::media-browser.{$group}.{$key}", $number, $replace);
    }

    /**
     * @return array<\Filament\Tables\Columns\Column>
     */
    private static function tableColumns(): array
    {
        $isDocument = static::libraryKind() === LibraryKind::DOCUMENT;
        $columns = [];

        if (! $isDocument) {
            $columns[] = ImageColumn::make('preview')
                ->label(static::trans('preview'))
                ->getStateUsing(fn (Model $record): string => self::previewUrl($record))
                ->square()
                ->height(48);
        }

        $columns[] = TextColumn::make('name')
            ->label(__('filament-media-browser::media-browser.fields.name'))
            ->searchable()
            ->sortable();
        $columns[] = TextColumn::make('file_name')
            ->label(static::trans('file_name'))
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: ! $isDocument);
        $columns[] = TextColumn::make('mime_type')
            ->label(static::trans('mime_type'))
            ->badge()
            ->formatStateUsing(fn (?string $state): string => LibraryFiles::name($state))
            ->color(fn (Model $record): string => LibraryFiles::color($record->getAttribute('mime_type')));
        $columns[] = TextColumn::make('size')
            ->label(static::trans('size'))
            ->formatStateUsing(fn (Model $record): string => self::humanSize($record))
            ->sortable();
        $columns[] = TextColumn::make('uploaded_by')
            ->label(static::trans('uploaded_by'))
            ->getStateUsing(fn (Model $record): ?string => self::uploaderName($record))
            ->placeholder('—');
        $columns[] = TextColumn::make('created_at')
            ->label(static::trans('uploaded_at'))
            ->dateTime()
            ->sortable();

        return $columns;
    }

    /**
     * @return array<\Filament\Tables\Filters\BaseFilter>
     */
    private static function tableFilters(): array
    {
        return [
            SelectFilter::make('mime_type')
                ->label(static::trans('mime_type'))
                ->options(fn (): array => array_map(
                    LibraryFiles::name(...),
                    self::distinctColumnOptions('mime_type'),
                )),
            Filter::make('created_at')
                ->label(__('filament-media-browser::media-browser.fields.created_at'))
                ->columnSpan(2)
                ->columns(2)
                ->form([
                    DatePicker::make('from')->label(static::trans('from'))->columnSpan(1),
                    DatePicker::make('until')->label(static::trans('until'))->columnSpan(1),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when(
                        $data['from'] ?? null,
                        fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date),
                    )
                    ->when(
                        $data['until'] ?? null,
                        fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date),
                    ))
                ->indicateUsing(fn (array $data): array => array_values(array_filter([
                    filled($data['from'] ?? null)
                        ? Indicator::make(static::trans('from_date', [
                            'date' => Carbon::parse($data['from'])->toFormattedDateString(),
                        ]))->removeField('from')
                        : null,
                    filled($data['until'] ?? null)
                        ? Indicator::make(static::trans('until_date', [
                            'date' => Carbon::parse($data['until'])->toFormattedDateString(),
                        ]))->removeField('until')
                        : null,
                ]))),
        ];
    }

    /**
     * @return array<int, object>
     */
    private static function recordActions(): array
    {
        $viewAction = FilamentVersion::viewAction();
        $deleteAction = FilamentVersion::deleteAction();

        return [
            $viewAction::make()->infolist(self::infolistSchema()),
            $deleteAction::make()
                ->modalHeading(static::trans('delete_heading'))
                ->modalDescription(fn (Model $record): string => self::deleteModalDescription($record)),
        ];
    }

    /**
     * @return array<\Filament\Infolists\Components\Component>
     */
    private static function infolistSchema(): array
    {
        $schema = [];

        if (static::libraryKind() !== LibraryKind::DOCUMENT) {
            $schema[] = ImageEntry::make('preview')
                ->label(static::trans('preview'))
                ->getStateUsing(fn (Model $record): string => self::previewUrl($record))
                ->height(320)
                ->extraImgAttributes(['class' => 'rounded-lg object-contain'])
                ->columnSpanFull();
        }

        $grid = FilamentVersion::grid();

        $schema[] = $grid::make(2)->schema([
            TextEntry::make('name')->label(__('filament-media-browser::media-browser.fields.name')),
            TextEntry::make('file_name')->label(static::trans('file_name')),
            TextEntry::make('mime_type')
                ->label(static::trans('mime_type'))
                ->badge()
                ->formatStateUsing(fn (?string $state): string => LibraryFiles::name($state))
                ->color(fn (Model $record): string => LibraryFiles::color($record->getAttribute('mime_type'))),
            TextEntry::make('size')
                ->label(static::trans('size'))
                ->formatStateUsing(fn (Model $record): string => self::humanSize($record)),
            TextEntry::make('attached_to')
                ->label(static::trans('attached_to'))
                ->html()
                ->getStateUsing(fn (Model $record): ?string => self::attachedToHtml($record))
                ->placeholder('—'),
            TextEntry::make('usage')
                ->label(static::trans('usage'))
                ->getStateUsing(fn (Model $record): string => self::usageLabel($record)),
            TextEntry::make('uploaded_by')
                ->label(static::trans('uploaded_by'))
                ->getStateUsing(fn (Model $record): ?string => self::uploaderName($record))
                ->placeholder('—'),
            TextEntry::make('created_at')
                ->label(static::trans('uploaded_at'))
                ->dateTime(),
        ]);
        $actions = FilamentVersion::infolistActions();
        $action = FilamentVersion::infolistAction();

        $schema[] = $actions::make([
            $action::make('openOriginal')
                ->label(static::trans('open_original'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (Model $record): string => self::openUrl($record), shouldOpenInNewTab: true),
        ])->columnSpanFull();

        return $schema;
    }

    /**
     * @return array<int, object>
     */
    private static function emptyStateActions(): array
    {
        $action = FilamentVersion::tableAction();

        return [
            $action::make('create')
                ->label(static::trans('upload_image'))
                ->icon('heroicon-o-plus')
                ->modalHeading(static::trans('upload_image'))
                ->modalSubmitActionLabel(static::trans('upload'))
                ->visible(fn (ListRecords $livewire): bool => ! self::hasActiveTableState($livewire))
                ->form(self::libraryUploadForm())
                ->action(function (array $data): void {
                    self::notifyLibraryUploads(self::storeLibraryUploads($data));
                }),
            $action::make('resetTableFilters')
                ->label(static::trans('reset_filters'))
                ->icon('heroicon-m-arrow-uturn-left')
                ->visible(fn (ListRecords $livewire): bool => self::hasActiveTableState($livewire))
                ->action(fn (ListRecords $livewire) => self::resetTableState($livewire)),
        ];
    }

    private static function previewUrl(Model $record): string
    {
        if (method_exists($record, 'hasGeneratedConversion') && $record->hasGeneratedConversion('thumb') && method_exists($record, 'getUrl')) {
            return $record->getUrl('thumb');
        }

        return self::openUrl($record);
    }

    private static function openUrl(Model $record): string
    {
        if (! method_exists($record, 'getUrl')) {
            return '';
        }

        return (string) $record->getUrl();
    }

    private static function humanSize(Model $record): string
    {
        $size = $record->getAttribute('human_readable_size');

        return is_string($size) ? $size : '';
    }

    private static function uploaderName(Model $record): ?string
    {
        if (! method_exists($record, 'getCustomProperty')) {
            return null;
        }

        $name = $record->getCustomProperty('uploaded_by_name');

        return filled($name) ? (string) $name : null;
    }

    private static function attachedToHtml(Model $record): ?string
    {
        $items = static::port()->usages($record);

        if ($items === []) {
            return null;
        }

        return collect($items)
            ->unique(fn (array $item): string => $item['label'].'|'.(string) $item['url'])
            ->map(function (array $item): string {
                $label = e($item['label']);

                if ($item['url'] === null) {
                    return $label;
                }

                return '<a href="'.e($item['url']).'" target="_blank" rel="noopener noreferrer" class="text-primary-600 underline decoration-primary-600/40 hover:decoration-primary-600 dark:text-primary-400">'.$label.'</a>';
            })
            ->implode('<br>');
    }

    private static function usageLabel(Model $record): string
    {
        $usages = static::port()->usageCount($record);

        $parts = [
            $usages > 0
                ? static::transChoice('used_in', $usages, ['count' => $usages])
                : static::trans('not_used'),
        ];

        if (! static::port()->isLibraryOwned($record)) {
            $parts[] = static::trans('owned_by', [
                'model' => class_basename((string) $record->getAttribute('model_type')),
            ]);
        }

        return implode(' · ', $parts);
    }

    private static function deleteModalDescription(Model $record): string
    {
        $warnings = [];
        $usages = static::port()->usageCount($record);

        if ($usages > 0) {
            $warnings[] = static::transChoice('delete_used', $usages, ['count' => $usages]);
        }

        if (! static::port()->isLibraryOwned($record)) {
            $warnings[] = static::trans('delete_owned', [
                'model' => class_basename((string) $record->getAttribute('model_type')),
            ]);
        }

        $warnings[] = static::trans('delete_permanent');

        return implode(' ', $warnings);
    }

    /**
     * @return array<string, string>
     */
    private static function distinctColumnOptions(string $column): array
    {
        return static::port()->libraryQuery(static::libraryKind())
            ->select($column)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column, $column)
            ->all();
    }

    private static function hasActiveTableState(ListRecords $livewire): bool
    {
        $hasFilters = collect($livewire->tableFilters ?? [])
            ->flatMap(fn ($state): array => Arr::wrap($state))
            ->contains(fn ($value): bool => filled($value));

        return $hasFilters || filled($livewire->tableSearch ?? '');
    }

    private static function resetTableState(ListRecords $livewire): void
    {
        $livewire->resetTableFiltersForm();
        $livewire->resetTableSearch();
    }
}
