<?php

namespace A0x01\FilamentMediaBrowser\Support;

use Illuminate\Support\Str;

class LibraryFiles
{
    /**
     * PDF, DOC/DOCX, PPT/PPTX, TXT, CSV.
     *
     * @var list<string>
     */
    public const DOCUMENT_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.ms-word',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
        'application/csv',
    ];

    /**
     * @var list<string>
     */
    public const IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/svg+xml',
    ];

    public static function name(?string $mimeType): string
    {
        if (blank($mimeType)) {
            return '—';
        }

        return match ($mimeType) {
            'application/pdf' => 'pdf',
            'application/msword', 'application/vnd.ms-word' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt',
            'text/csv', 'application/csv' => 'csv',
            default => self::subtype($mimeType),
        };
    }

    public static function color(?string $mimeType): string
    {
        return match (true) {
            str_starts_with((string) $mimeType, 'image/') => 'success',
            str_starts_with((string) $mimeType, 'video/') => 'warning',
            in_array($mimeType, self::DOCUMENT_MIME_TYPES, true) => 'info',
            default => 'gray',
        };
    }

    /**
     * @return array{uploaded_by_id: string, uploaded_by_name: string}|array{}
     */
    public static function uploaderProperties(): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        $name = trim((string) $user->name);

        return [
            'uploaded_by_id' => (string) $user->getAuthIdentifier(),
            'uploaded_by_name' => $name !== '' ? $name : (string) $user->getAuthIdentifier(),
        ];
    }

    private static function subtype(string $mimeType): string
    {
        $subtype = Str::after($mimeType, '/');

        if ($subtype === $mimeType) {
            return strtolower($mimeType);
        }

        if (str_contains($subtype, '+')) {
            $subtype = Str::before($subtype, '+');
        }

        return strtolower($subtype);
    }
}
