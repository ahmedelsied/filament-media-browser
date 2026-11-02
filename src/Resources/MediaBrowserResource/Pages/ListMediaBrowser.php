<?php

namespace A0x01\FilamentMediaBrowser\Resources\MediaBrowserResource\Pages;

use A0x01\FilamentMediaBrowser\Resources\MediaBrowserResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListMediaBrowser extends ListRecords
{
    protected static string $resource = MediaBrowserResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label(MediaBrowserResource::headerUploadLabel())
                ->icon('heroicon-o-plus')
                ->modalHeading(MediaBrowserResource::headerUploadLabel())
                ->modalSubmitActionLabel(__('filament-media-browser::media-browser.image_browser.upload'))
                ->form(MediaBrowserResource::libraryUploadForm())
                ->action(function (array $data): void {
                    MediaBrowserResource::notifyLibraryUploads(
                        MediaBrowserResource::storeLibraryUploads($data),
                    );
                }),
        ];
    }
}
