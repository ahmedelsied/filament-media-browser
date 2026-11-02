<?php

namespace AhmedElsied\FilamentMediaBrowser\Resources;

use AhmedElsied\FilamentMediaBrowser\Enums\LibraryKind;
use AhmedElsied\FilamentMediaBrowser\Resources\DocumentBrowserResource\Pages\ListDocumentBrowser;

class DocumentBrowserResource extends MediaBrowserResource
{
    protected static ?string $slug = 'document-browser';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 101;

    protected static function libraryKind(): LibraryKind
    {
        return LibraryKind::DOCUMENT;
    }

    public static function getModelLabel(): string
    {
        return __('filament-media-browser::media-browser.resources.document_browser');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-media-browser::media-browser.resources.document_browser');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-media-browser::media-browser.resources.document_browser');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentBrowser::route('/'),
        ];
    }
}
