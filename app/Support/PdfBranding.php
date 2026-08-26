<?php

namespace App\Support;

final class PdfBranding
{
    public const PLATFORM_NAME = 'Africa Think Tank Platform';

    /**
     * Shared branding values for PDF views and mail attachments.
     *
     * @return array{platformName: string, platformUrl: string, logoDataUri: ?string}
     */
    public static function viewData(): array
    {
        return [
            'platformName' => self::PLATFORM_NAME,
            'platformUrl' => rtrim(config('app.url') ?: url('/'), '/'),
            'logoDataUri' => self::logoDataUri(),
        ];
    }

    public static function logoDataUri(?string $path = null): ?string
    {
        $path ??= public_path('assets/images/attp-logo.jpeg');

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if (! is_string($contents) || $contents === '') {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode($contents);
    }
}
