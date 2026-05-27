<?php

namespace App\Console\Commands;

use App\Services\GalleryImageService;
use Illuminate\Console\Command;

class OptimizeGalleryCommand extends Command
{
    protected $signature = 'gallery:optimize {--force : Recreate optimized images even when they already exist}';

    protected $description = 'Create lightweight WebP thumbnails and large gallery images from public/gallary originals';

    public function handle(GalleryImageService $gallery): int
    {
        ini_set('memory_limit', '768M');
        set_time_limit(0);

        $this->info('Optimizing gallery images...');

        $result = $gallery->optimizeAll((bool) $this->option('force'));

        $this->line("Created: {$result['created']}");
        $this->line("Skipped: {$result['skipped']}");
        $this->line("Failed: {$result['failed']}");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
