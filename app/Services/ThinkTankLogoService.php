<?php

namespace App\Services;

use App\Models\ConsortiumThinkTank;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ThinkTankLogoService
{
    private const DIRECTORY = 'think-tank-logos';

    /**
     * @return array{previous_path: ?string, current_path: ?string, removed: bool}
     */
    public function replace(
        ConsortiumThinkTank $thinkTank,
        ?UploadedFile $logo,
        bool $remove = false
    ): array {
        if (! $logo && ! $remove) {
            throw new RuntimeException('A logo file or removal request is required.');
        }

        $previousPath = $thinkTank->logo_path;
        $currentPath = null;

        try {
            if ($logo) {
                $storedPath = $logo->store(self::DIRECTORY, 'public');

                if (! is_string($storedPath) || $storedPath === '') {
                    throw new RuntimeException('The think tank logo could not be stored.');
                }

                $currentPath = $storedPath;
            }

            $thinkTank->forceFill(['logo_path' => $currentPath])->saveOrFail();
        } catch (Throwable $exception) {
            $this->deleteSafely($currentPath);

            throw $exception;
        }

        if ($previousPath !== $currentPath) {
            $this->deleteSafely($previousPath);
        }

        return [
            'previous_path' => $previousPath,
            'current_path' => $currentPath,
            'removed' => $currentPath === null,
        ];
    }

    private function deleteSafely(?string $path): void
    {
        $path = str_replace('\\', '/', trim((string) $path));

        if ($path === '' || ! Str::startsWith($path, self::DIRECTORY.'/') || str_contains($path, '../')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
