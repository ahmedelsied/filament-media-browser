<?php

namespace AhmedElsied\FilamentMediaBrowser\Resources\DocumentBrowserResource\Pages;

use AhmedElsied\FilamentMediaBrowser\Resources\DocumentBrowserResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListDocumentBrowser extends ListRecords
{
    protected static string $resource = DocumentBrowserResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label(DocumentBrowserResource::headerUploadLabel())
                ->icon('heroicon-o-plus')
                ->modalHeading(DocumentBrowserResource::headerUploadLabel())
                ->modalSubmitActionLabel(__('filament-media-browser::media-browser.document_browser.upload'))
                ->form(DocumentBrowserResource::libraryUploadForm())
                ->action(function (array $data): void {
                    DocumentBrowserResource::notifyLibraryUploads(
                        DocumentBrowserResource::storeLibraryUploads($data),
                    );
                }),
        ];
    }
}
