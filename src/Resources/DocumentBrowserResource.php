<?php

namespace A0x01\FilamentMediaBrowser\Resources;

use A0x01\FilamentMediaBrowser\Enums\LibraryKind;
use A0x01\FilamentMediaBrowser\Resources\DocumentBrowserResource\Pages\ListDocumentBrowser;

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
