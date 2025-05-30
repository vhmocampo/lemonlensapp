<?php

namespace App\Clients;

use App\Data\VehicleComplaint;
use App\Data\VehicleComplaintCollection;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

/**
 * Class LemonbaseClient
 * @package App\Clients
 *
 * This class is responsible for interacting with the Lemonbase API.
 * It handles the base URL, API key, and retries for requests.
 *
 * Currently, we're going to call everything from MongoDB directly, but in the future
 * this should be linked to the Lemonbase API (FastAPI).
 */
class LemonbaseClient
{
    protected ?string $baseUrl;
    protected ?string $apiKey;
    protected ?int $retries;

    public function __construct()
    {
        $this->baseUrl = config('lemonbase.base_url');
        $this->apiKey = config('lemonbase.api_key');
        $this->retries = config('lemonbase.retries', 3);
    }

    public function getMongoClient()
    {
        return DB::connection('mongodb')->getClient();
    }

    /**
     * Get the vehicles collection from the MongoDB database.
     *
     * @return \Illuminate\Support\Collection
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public function getMongoVehiclesCollection()
    {
        return $this->getMongoClient()->selectCollection('lemonbase', 'vehicles');
    }

    /**
     * Get vehicle information from the database.
     *
     * @param [type] $year
     * @param [type] $make
     * @param [type] $model
     * @return Vehicle|null
     */
    public function getVehicle($year, $make, $model)
    {
        return Vehicle::where('year', intval($year))
            ->where('make', $make)
            ->where('model', $model)
            ->first();
    }

    /**
     * Extract complaints that fall at or above a specific mileage.
     * The range starts at the given mileage with no upper limit.
     *
     * @param Vehicle|null $vehicle The vehicle object containing complaint data
     * @param int $mileage The minimum mileage threshold
     * @return array Array of complaint data
     */
    public function extractComplaintsInMileageRange(?Vehicle $vehicle, int $mileage): array
    {
        if (!$vehicle || empty($vehicle->buckets)) {
            return [];
        }

        $relevantComplaints = [];

        foreach ($vehicle->buckets as $bucket) {
            // Calculate the mileage range we want to include
            $minMileage = max(0, $mileage - 15000);
            $maxMileage = $mileage + 45000;

            // Check if bucket overlaps with our desired mileage range
            if (($bucket['from_mileage'] <= $maxMileage && $bucket['to_mileage'] >= $minMileage)) {
                // Include all complaints from this bucket
                if (!empty($bucket['complaints'])) {
                    foreach ($bucket['complaints'] as $complaint) {
                        $relevantComplaints[] = array_merge($complaint, [
                            'bucket_from' => $bucket['from_mileage'], 
                            'bucket_to' => $bucket['to_mileage']
                        ]);
                    }
                }
            }
        }

        return $relevantComplaints;
    }

    /**
     * Get complaints for a specific make, model, year, and mileage.
     *
     * @param string $make
     * @param string $model
     * @param integer $year
     * @param integer $mileage
     * @return VehicleComplaintCollection A collection of VehicleComplaint objects
     */
    public function getComplaintsForYearMakeModelMileage(
        int $year,
        string $make,
        string $model,
        int $mileage
    ): VehicleComplaintCollection {
        $years = range($year - 1, $year + 1);
        $vehicles = Vehicle::whereIn('year', $years)
            ->where('make', $make)
            ->where('model', $model)
            ->get();
        
        $complaints = [];
        
        if (!$vehicles->isEmpty()) {
            // Ensure that we capture some complaints prior to the mileage as well (1 year)
            $mileage = (int) $mileage - 10000; // Adjust mileage to account for the range
            $mileage = max($mileage, 0); // Ensure mileage is not negative
            foreach ($vehicles as $vehicle) {
                $complaints = array_merge($complaints, $this->extractComplaintsInMileageRange($vehicle, $mileage));
            }
        }

        return new VehicleComplaintCollection($complaints);
    }
}
