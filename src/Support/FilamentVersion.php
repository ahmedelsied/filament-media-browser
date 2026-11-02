<?php

namespace A0x01\FilamentMediaBrowser\Support;

class FilamentVersion
{
    public static function usesSchemas(): bool
    {
        return class_exists(\Filament\Schemas\Schema::class);
    }

    /**
     * @return class-string
     */
    public static function tableAction(): string
    {
        return self::usesSchemas()
            ? \Filament\Actions\Action::class
            : \Filament\Tables\Actions\Action::class;
    }

    /**
     * @return class-string
     */
    public static function formAction(): string
    {
        return self::usesSchemas()
            ? \Filament\Actions\Action::class
            : \Filament\Forms\Components\Actions\Action::class;
    }

    /**
     * @return class-string
     */
    public static function viewAction(): string
    {
        return self::usesSchemas()
            ? \Filament\Actions\ViewAction::class
            : \Filament\Tables\Actions\ViewAction::class;
    }

    /**
     * @return class-string
     */
    public static function deleteAction(): string
    {
        return self::usesSchemas()
            ? \Filament\Actions\DeleteAction::class
            : \Filament\Tables\Actions\DeleteAction::class;
    }

    /**
     * @return class-string
     */
    public static function infolistAction(): string
    {
        return self::usesSchemas()
            ? \Filament\Actions\Action::class
            : \Filament\Infolists\Components\Actions\Action::class;
    }

    /**
     * @return class-string
     */
    public static function infolistActions(): string
    {
        return self::usesSchemas()
            ? \Filament\Schemas\Components\Actions::class
            : \Filament\Infolists\Components\Actions::class;
    }

    /**
     * @return class-string
     */
    public static function grid(): string
    {
        return self::usesSchemas()
            ? \Filament\Schemas\Components\Grid::class
            : \Filament\Infolists\Components\Grid::class;
    }

    /**
     * @return class-string
     */
    public static function tabs(): string
    {
        return self::usesSchemas()
            ? \Filament\Schemas\Components\Tabs::class
            : \Filament\Forms\Components\Tabs::class;
    }

    /**
     * @return class-string
     */
    public static function tab(): string
    {
        return self::usesSchemas()
            ? \Filament\Schemas\Components\Tabs\Tab::class
            : \Filament\Forms\Components\Tabs\Tab::class;
    }
}
