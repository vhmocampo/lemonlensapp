<?php

namespace App\Http\Controllers;

use App\Models\Stat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * @OA\Tag(
 *     name="Vehicle Metadata",
 *     description="API Endpoints for vehicle metadata like makes, models, and years"
 * )
 */
class MetadataController extends Controller
{
    /**
     * Get list of all vehicle makes
     *
     * @return \Illuminate\Http\JsonResponse
     * 
     * @OA\Get(
     *     path="/vehicle/makes",
     *     summary="Get all vehicle makes",
     *     tags={"Vehicle Metadata"},
     *     @OA\Response(
     *         response=200,
     *         description="List of vehicle makes",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="makes",
     *                 type="array",
     *                 @OA\Items(type="string", example="Honda")
     *             )
     *         )
     *     )
     * )
     */
    public function makes()
    {
        // Cache makes for 1 hour since they come from stats that may update
        $makes = Cache::remember('vehicle_makes', 60*60*24*5, function() {
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
     * 
     * @OA\Get(
     *     path="/vehicle/models",
     *     summary="Get all models for a specific make",
     *     tags={"Vehicle Metadata"},
     *     @OA\Parameter(
     *         name="make",
     *         in="query",
     *         required=true,
     *         description="Vehicle make (e.g. Honda)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of vehicle models for the specified make",
     *         @OA\JsonContent(
     *             @OA\Property(property="make", type="string", example="Honda"),
     *             @OA\Property(
     *                 property="models",
     *                 type="array",
     *                 @OA\Items(type="string", example="Accord")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Make parameter is required")
     * )
     */
    public function models(Request $request)
    {
        $make = $request->query('make');

        if (!$make) {
            return response()->json([
                'error' => 'Make parameter is required'
            ], 400);
        }

        // Normalize make name for searching
        $normalizedMake = strtolower($make);

        // Cache models for each make for 1 hour
        $cacheKey = 'vehicle_models_' . $normalizedMake;

        $models = Cache::remember($cacheKey, 60*60*24*5, function() use ($normalizedMake) {
            // Get vehicles from the vehicles collection
            $vehicles = \App\Models\Vehicle::where('make', 'like', $normalizedMake)
                ->get();

            // Filter and format models by total_complaint_count > 10
            $models = $vehicles->filter(function($vehicle) {
                return isset($vehicle->total_complaint_count) && $vehicle->total_complaint_count > 10;
            })->map(function($vehicle) {
                return $this->formatModelName($vehicle->model);
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
     * Get list of all available years for a specific make and model
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @OA\Get(
     *     path="/vehicle/years",
     *     summary="Get all available vehicle years for a specific make and model",
     *     tags={"Vehicle Metadata"},
     *     @OA\Parameter(
     *         name="make",
     *         in="query",
     *         required=true,
     *         description="Vehicle make (e.g. Toyota)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="model",
     *         in="query",
     *         required=true,
     *         description="Vehicle model (e.g. 4Runner)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of available vehicle years for the specified make and model",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="years",
     *                 type="array",
     *                 @OA\Items(type="integer", example=2018)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Make and model parameters are required")
     * )
     */
    public function years(Request $request)
    {
        $make = $request->query('make');
        $model = $request->query('model');
        if (!$make || !$model) {
            return response()->json([
                'error' => 'Make and model parameters are required'
            ], 400);
        }

        // Normalize make and model names for searching
        $normalizedMake = strtolower($make);
        $normalizedModel = strtolower(str_replace(' ', '_', $model));

        // Cache years for each make/model for 1 day
        $cacheKey = 'vehicle_years_' . $normalizedMake . '_' . $normalizedModel;

        $years = Cache::remember($cacheKey, 60*60*24*5, function() use ($normalizedMake, $normalizedModel) {
            // Get vehicles from the vehicles collection for the given make and model
            $vehicles = \App\Models\Vehicle::where('make', 'like', $normalizedMake)
                ->where('model', 'like', $normalizedModel)
                ->get();

            // Extract years from vehicles
            $years = $vehicles->pluck('year')->unique()->sortDesc()->values()->all();
            return $years;
        });

        return response()->json([
            'years' => $years
        ]);
    }
}