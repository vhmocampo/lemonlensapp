<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\MetadataCacheService;

class PopulateMetadataCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of attempts for the job.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 3600; // 1 hour

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     */
    public function handle(MetadataCacheService $metadataCacheService): void
    {
        Log::info('Starting PopulateMetadataCacheJob...');

        try {
            $metadataCacheService->populateAllCaches();
            Log::info('PopulateMetadataCacheJob completed successfully');
        } catch (\Exception $e) {
            Log::error('PopulateMetadataCacheJob failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
