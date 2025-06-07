<?php

namespace App\Console\Commands;

use App\Services\MetadataCacheService;
use Illuminate\Console\Command;

class ClearMetadataCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-metadata';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all metadata cache entries (makes, models, years)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Clearing metadata cache...');

        $metadataCacheService = app(MetadataCacheService::class);
        $metadataCacheService->clearAllCaches();

        $this->info('Metadata cache cleared successfully!');

        return 0;
    }
}
