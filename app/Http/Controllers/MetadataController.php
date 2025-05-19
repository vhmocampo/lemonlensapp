<?php

namespace App\Http\Controllers;

use App\Models\Stat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MetadataController extends Controller
{
    /**
     * Get list of all vehicle makes
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function makes()
    {
        // Cache makes for 1 hour since they come from stats that may update
        $makes = Cache::remember('vehicle_makes', 60*60, function() {
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

        return response()->json([
            'makes' => $makes
        ]);
    }

    /**
     * Get list of models for a specific make
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function models(Request $request)
    {
        $make = $request->query('make');

        if (!$make) {
            return response()->json([
                'error' => 'Make parameter is required'
            ], 400);
        }

        // Normalize make name for searching in keys
        $normalizedMake = strtolower($make);

        // Cache models for each make for 1 hour
        $cacheKey = 'vehicle_models_' . $normalizedMake;

        $models = Cache::remember($cacheKey, 60*60, function() use ($normalizedMake) {
            // Get all stats that match the make-model pattern
            $modelStats = Stat::where('key', 'like', 'avg_complaints_' . $normalizedMake . '_%')
                ->where('key', 'not like', 'avg_complaints_make_%')
                ->get();

            // Extract and clean model names from stat keys
            $models = $modelStats->map(function($stat) use ($normalizedMake) {
                // Extract the model from the key: 'avg_complaints_toyota_camry' -> 'camry'
                $model = str_replace('avg_complaints_' . $normalizedMake . '_', '', $stat->key);

                // Format the model name properly
                $model = $this->formatModelName($model);

                return $model;
            })->unique()->sort()->values()->all();

            return $models;
        });

        return response()->json([
            'make' => $make,
            'models' => $models
        ]);
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

    /**
     * Get list of all available years
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function years(Request $request)
    {
        // For years, we'll provide a static list since we don't have year-specific stats
        // This could be enhanced in the future to use actual data
        $years = Cache::remember('vehicle_years', 60*60*24, function() {
            // Generate years from 2006 to current year
            $currentYear = 2016;
            $years = range(2006, $currentYear);
            rsort($years); // Sort in descending order
            return $years;
        });

        return response()->json([
            'years' => $years
        ]);
    }
}