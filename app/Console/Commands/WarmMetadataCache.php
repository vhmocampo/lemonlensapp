<?php

namespace App\Console\Commands;

use App\Jobs\PopulateMetadataCacheJob;
use Illuminate\Console\Command;

class WarmMetadataCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:warm-metadata 
                            {--sync : Run the job synchronously instead of queuing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm up the metadata cache by pre-loading makes, models, and years';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Warming metadata cache...');

        if ($this->option('sync')) {
            $this->info('Running job synchronously...');
            $metadataCacheService = app(\App\Services\MetadataCacheService::class);
            $metadataCacheService->populateAllCaches();
            $this->info('Metadata cache warmed successfully!');
        } else {
            $this->info('Queuing metadata cache warming job...');
            PopulateMetadataCacheJob::dispatch();
            $this->info('Metadata cache warming job has been queued.');
        }

        return 0;
    }
}
