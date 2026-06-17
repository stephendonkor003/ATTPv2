<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class GalleryImageService
{
    private const SOURCE_DIR = 'gallary';
    private const OPTIMIZED_DIR = 'gallary/optimized';
    private const THUMB_DIR = 'thumbs';
    private const LARGE_DIR = 'large';
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function items(): array
    {
        $sourcePath = public_path(self::SOURCE_DIR);

        if (!File::isDirectory($sourcePath)) {
            return [];
        }

        return collect(File::files($sourcePath))
            ->filter(fn ($file) => in_array(strtolower($file->getExtension()), self::IMAGE_EXTENSIONS, true))
            ->sortBy(fn ($file) => $file->getFilename())
            ->map(fn ($file) => $this->itemFor($file->getFilename()))
            ->values()
            ->all();
    }

    public function fallbackUrl(string $size = 'large'): ?string
    {
        $item = $this->items()[0] ?? null;

        if (!$item) {
            return null;
        }

        return $item[$size] ?? $item['large'] ?? $item['thumb'] ?? $item['original'] ?? null;
    }

    public function optimizeAll(bool $force = false): array
    {
        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->sourceFiles() as $file) {
            $result = $this->optimize($file->getFilename(), $force);

            if ($result === 'created') {
                $created++;
            } elseif ($result === 'skipped') {
                $skipped++;
            } else {
                $failed++;
            }
        }

        return compact('created', 'skipped', 'failed');
    }

    public function optimize(string $filename, bool $force = false): string
    {
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            return 'failed';
        }

        $filename = basename($filename);
        $source = public_path(self::SOURCE_DIR . '/' . $filename);

        if (!File::isFile($source) || !$this->isImageFilename($filename)) {
            return 'failed';
        }

        $thumb = $this->optimizedPath($filename, self::THUMB_DIR);
        $large = $this->optimizedPath($filename, self::LARGE_DIR);

        if (
            !$force
            && File::isFile($thumb)
            && File::isFile($large)
            && File::lastModified($thumb) >= File::lastModified($source)
            && File::lastModified($large) >= File::lastModified($source)
        ) {
            return 'skipped';
        }

        File::ensureDirectoryExists(dirname($thumb));
        File::ensureDirectoryExists(dirname($large));

        $image = $this->loadImage($source);

        if (!$image) {
            return 'failed';
        }

        $ok = $this->saveResizedWebp($image, $thumb, 720, 78)
            && $this->saveResizedWebp($image, $large, 1600, 82);

        imagedestroy($image);

        return $ok ? 'created' : 'failed';
    }

    private function itemFor(string $filename): array
    {
        $thumb = self::OPTIMIZED_DIR . '/' . self::THUMB_DIR . '/' . $this->webpName($filename);
        $large = self::OPTIMIZED_DIR . '/' . self::LARGE_DIR . '/' . $this->webpName($filename);
        $original = self::SOURCE_DIR . '/' . $filename;

        return [
            'name' => $filename,
            'thumb' => File::isFile(public_path($thumb)) ? asset($thumb) : asset($original),
            'large' => File::isFile(public_path($large)) ? asset($large) : asset($original),
            'original' => asset($original),
        ];
    }

    private function sourceFiles()
    {
        $sourcePath = public_path(self::SOURCE_DIR);

        if (!File::isDirectory($sourcePath)) {
            return collect();
        }

        return collect(File::files($sourcePath))
            ->filter(fn ($file) => in_array(strtolower($file->getExtension()), self::IMAGE_EXTENSIONS, true));
    }

    private function isImageFilename(string $filename): bool
    {
        return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true);
    }

    private function optimizedPath(string $filename, string $size): string
    {
        return public_path(self::OPTIMIZED_DIR . '/' . $size . '/' . $this->webpName($filename));
    }

    private function webpName(string $filename): string
    {
        return pathinfo($filename, PATHINFO_FILENAME) . '.webp';
    }

    private function loadImage(string $path)
    {
        $info = @getimagesize($path);
        $type = $info[2] ?? null;

        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => false,
        };
    }

    private function saveResizedWebp($source, string $target, int $maxWidth, int $quality): bool
    {
        $width = imagesx($source);
        $height = imagesy($source);

        if ($width < 1 || $height < 1) {
            return false;
        }

        $ratio = min(1, $maxWidth / $width);
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $width,
            $height
        );

        $saved = imagewebp($canvas, $target, $quality);
        imagedestroy($canvas);

        return $saved;
    }
}
