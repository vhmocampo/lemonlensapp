<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Stat;
use App\Models\Vehicle;

class MetadataCacheService
{
    /**
     * Cache TTL in seconds (5 days)
     */
    const CACHE_TTL = 60 * 60 * 24 * 5;

    /**
     * Populate all metadata caches
     */
    public function populateAllCaches(): void
    {
        Log::info('Starting metadata cache population...');

        // Step 1: Cache vehicle makes
        $makes = $this->cacheMakes();

        // Step 2: Cache models for each make
        foreach ($makes as $make) {
            $this->cacheModelsForMake($make);
        }

        // Step 3: Cache years for each make/model combination
        foreach ($makes as $make) {
            $models = $this->getModelsForMake($make);
            foreach ($models as $model) {
                $this->cacheYearsForMakeModel($make, $model);
            }
        }

        Log::info('Metadata cache population completed');
    }

    /**
     * Cache vehicle makes and return them
     */
    public function cacheMakes(): array
    {
        Log::info('Caching vehicle makes...');

        $cacheKey = 'vehicle_makes';
        $makes = Cache::remember($cacheKey, self::CACHE_TTL, function() {
            // Get all stats that start with 'avg_complaints_make_'
            $makeStats = Stat::where('key', 'like', 'avg_complaints_make_%')
                ->get();

            // Extract and clean make names from stat keys
            $makes = $makeStats->map(function($stat) {
                // Extract the make from the key: 'avg_complaints_make_toyota' -> 'toyota'
                $make = str_replace('avg_complaints_make_', '', $stat->key);
                // Convert to title case: 'toyota' -> 'Toyota'
                return ucfirst($make);
            })->unique()->sort()->values()->all();

            return $makes;
        });

        Log::info('Cached ' . count($makes) . ' vehicle makes');
        return $makes;
    }

    /**
     * Cache models for a specific make and return them
     */
    public function cacheModelsForMake(string $make): array
    {
        Log::info("Caching models for make: {$make}");

        // Normalize make name for searching
        $normalizedMake = strtolower($make);
        $cacheKey = 'vehicle_models_' . $normalizedMake;

        $models = Cache::remember($cacheKey, self::CACHE_TTL, function() use ($normalizedMake) {
            // Get vehicles from the vehicles collection
            $vehicles = Vehicle::where('make', 'like', $normalizedMake)
                ->get();

            // Filter and format models by total_complaint_count > 10
            $models = $vehicles->filter(function($vehicle) {
                return isset($vehicle->total_complaint_count) && $vehicle->total_complaint_count > 10;
            })->map(function($vehicle) {
                return $this->formatModelName($vehicle->model);
            })->unique()->sort()->values()->all();

            return $models;
        });

        Log::info("Cached " . count($models) . " models for make: {$make}");
        return $models;
    }

    /**
     * Cache years for a specific make/model combination and return them
     */
    public function cacheYearsForMakeModel(string $make, string $model): array
    {
        Log::info("Caching years for {$make} {$model}");

        // Normalize make and model names for searching
        $normalizedMake = strtolower($make);
        $normalizedModel = strtolower(str_replace(' ', '_', $model));
        $cacheKey = 'vehicle_years_' . $normalizedMake . '_' . $normalizedModel;

        $years = Cache::remember($cacheKey, self::CACHE_TTL, function() use ($normalizedMake, $normalizedModel) {
            // Get vehicles from the vehicles collection for the given make and model
            $vehicles = Vehicle::where('make', 'like', $normalizedMake)
                ->where('model', 'like', $normalizedModel)
                ->get();

            // Extract years from vehicles
            $years = $vehicles->pluck('year')->unique()->sortDesc()->values()->all();
            return $years;
        });

        Log::info("Cached " . count($years) . " years for {$make} {$model}");
        return $years;
    }

    /**
     * Get makes (from cache or database)
     */
    public function getMakes(): array
    {
        $cacheKey = 'vehicle_makes';
        return Cache::get($cacheKey, function() {
            return $this->cacheMakes();
        });
    }

    /**
     * Get models for a specific make (from cache or database)
     */
    public function getModelsForMake(string $make): array
    {
        $normalizedMake = strtolower($make);
        $cacheKey = 'vehicle_models_' . $normalizedMake;
        
        return Cache::get($cacheKey, function() use ($make) {
            return $this->cacheModelsForMake($make);
        });
    }

    /**
     * Get years for a specific make/model combination (from cache or database)
     */
    public function getYearsForMakeModel(string $make, string $model): array
    {
        $normalizedMake = strtolower($make);
        $normalizedModel = strtolower(str_replace(' ', '_', $model));
        $cacheKey = 'vehicle_years_' . $normalizedMake . '_' . $normalizedModel;
        
        return Cache::get($cacheKey, function() use ($make, $model) {
            return $this->cacheYearsForMakeModel($make, $model);
        });
    }

    /**
     * Clear all metadata caches
     */
    public function clearAllCaches(): void
    {
        Log::info('Clearing all metadata caches...');

        // Clear makes cache
        Cache::forget('vehicle_makes');

        // Clear models and years caches
        $makes = $this->getMakes();
        foreach ($makes as $make) {
            $normalizedMake = strtolower($make);
            Cache::forget('vehicle_models_' . $normalizedMake);

            $models = $this->getModelsForMake($make);
            foreach ($models as $model) {
                $normalizedModel = strtolower(str_replace(' ', '_', $model));
                Cache::forget('vehicle_years_' . $normalizedMake . '_' . $normalizedModel);
            }
        }

        Log::info('All metadata caches cleared');
    }

    /**
     * Format model names properly
     * - Replace underscores with spaces
     * - Capitalize segments with 3 or fewer letters
     * - Ensure proper capitalization for other segments
     *
     * @param string $modelName
     * @return string
     */
    private function formatModelName(string $modelName): string
    {
        // Split by underscores
        $segments = explode('_', $modelName);
        
        // Format each segment
        $formattedSegments = array_map(function($segment) {
            // If segment is 3 characters or less, uppercase it
            if (strlen($segment) <= 3) {
                return strtoupper($segment);
            }
            
            // Otherwise, capitalize first letter
            return ucfirst($segment);
        }, $segments);
        
        // Join with spaces
        return implode(' ', $formattedSegments);
    }
}
