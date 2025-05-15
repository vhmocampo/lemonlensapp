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
        return Vehicle::where('year', $year)
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
     * @return VehicleComplaintCollection|VehicleComplaint[] An array of VehicleComplaint objects
     */
    public function extractComplaintsInMileageRange(?Vehicle $vehicle, int $mileage): VehicleComplaintCollection|array
    {
        if (!$vehicle || empty($vehicle->buckets)) {
            return [];
        }

        $relevantComplaints = [];

        foreach ($vehicle->buckets as $bucket) {
            // Check if the bucket's mileage range has any part above or equal to our minimum mileage
            if ($bucket['to_mileage'] >= $mileage) {
                // Include all complaints from this bucket
                if (!empty($bucket['complaints'])) {
                    foreach ($bucket['complaints'] as $complaint) {
                        $relevantComplaints[] = $complaint;
                    }
                }
            }
        }

        return new VehicleComplaintCollection($relevantComplaints);
    }

    /**
     * Get complaints for a specific make, model, year, and mileage.
     *
     * @param string $make
     * @param string $model
     * @param integer $year
     * @param integer $mileage
     * @return VehicleComplaintCollection|VehicleComplaint[] An array of VehicleComplaint objects
     */
    public function getComplaintsForYearMakeModelMileage(
        int $year,
        string $make,
        string $model,
        int $mileage
    ): VehicleComplaintCollection|array {
        $vehicle = $this->getVehicle($year, $make, $model);

        if (!$vehicle) {
            return [];
        }

        return $this->extractComplaintsInMileageRange($vehicle, $mileage);
    }
}
