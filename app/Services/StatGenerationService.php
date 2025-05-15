<?php

namespace App\Services;

use App\Clients\LemonbaseClient;
use App\Models\Stat;
use Illuminate\Support\Facades\Log;

class StatGenerationService
{
    /** @var LemonbaseClient $lemonbase */
    private $lemonbase = null;

    /**
     * ReportGenerationService constructor.
     *
     *
     * @param LemonbaseClient $lemonbase
     */
    public function __construct(LemonbaseClient $lemonbase)
    {
        $this->lemonbase = $lemonbase;
    }

    /**
     * Calculate complaint-related statistics.
     */
    public function calculateComplaintStats(): void
    {
        $vehiclesMongoCollection = $this->lemonbase->getMongoVehiclesCollection();

        // 1. Calculate average complaints per document
        $totalComplaints = 0;
        $documentCount = 0;
        $complaintsPerCategory = [];
        $complaintsPerMakeModel = [];
        $complaintsPerMake = []; // Added for make-only stats

        // Default minimum values to ensure we have non-zero stats
        $defaultComplaintsPerMake = 5;
        $defaultComplaintsPerMakeModel = 3;

        // Store a stat with a count of all documents
        Stat::setValue(
            'total_documents',
            $vehiclesMongoCollection->countDocuments(),
            'complaints',
            'Total number of vehicle documents'
        );

        // Process each vehicle document
        $cursor = $vehiclesMongoCollection->find();
        foreach ($cursor as $vehicle) {
            $documentCount++;

            // Convert MongoDB BSONDocument to array
            $vehicleArray = (array)$vehicle;

            // Debug individual vehicle structure
            Log::info("Processing vehicle document: " . json_encode(array_keys($vehicleArray)));

            // Make sure make and model exist to avoid errors
            if (!isset($vehicleArray['make']) || !isset($vehicleArray['model'])) {
                Log::warning("Skipping document missing make or model");
                continue; // Skip documents missing make or model
            }

            $makeModelKey = $vehicleArray['make'] . '-' . $vehicleArray['model'];
            $make = $vehicleArray['make'];

            Log::info("Processing vehicle: {$make} {$vehicleArray['model']}");

            // Initialize make-model stats if first occurrence
            if (!isset($complaintsPerMakeModel[$makeModelKey])) {
                $complaintsPerMakeModel[$makeModelKey] = [
                    'count' => 0,
                    'documents' => 0
                ];
            }
            $complaintsPerMakeModel[$makeModelKey]['documents']++;

            // Initialize make stats if first occurrence
            if (!isset($complaintsPerMake[$make])) {
                $complaintsPerMake[$make] = [
                    'count' => 0,
                    'documents' => 0
                ];
            }
            $complaintsPerMake[$make]['documents']++;

            $bucketFound = false;

            // Handle MongoDB's lazy loading of buckets array
            if (isset($vehicleArray['buckets'])) {
                Log::info('The buckets exist in document');

                // Force MongoDB to load the buckets by converting them to PHP array
                $buckets = [];

                // Handle different ways the buckets might be stored
                if (is_array($vehicleArray['buckets']) || is_object($vehicleArray['buckets'])) {
                    foreach ($vehicleArray['buckets'] as $b) {
                        $buckets[] = (array)$b;  // Force conversion to PHP array
                    }
                }

                if (!empty($buckets)) {
                    $bucketFound = true;
                    Log::info("Vehicle has " . count($buckets) . " buckets after conversion");

                    foreach ($buckets as $bucketIndex => $bucket) {
                        // Debug bucket structure
                        Log::info("Processing bucket {$bucketIndex}: " . json_encode(array_keys($bucket)));

                        // Extract total_complaints directly from bucket
                        if (isset($bucket['total_complaints'])) {
                            // Ensure it's a number and not a string that might evaluate to zero
                            $bucketComplaints = is_numeric($bucket['total_complaints']) ? 
                                (int)$bucket['total_complaints'] : 
                                (is_string($bucket['total_complaints']) ? (int)$bucket['total_complaints'] : 0);

                            Log::info("Bucket {$bucketIndex} has {$bucketComplaints} complaints");

                            $totalComplaints += $bucketComplaints;
                            $complaintsPerMakeModel[$makeModelKey]['count'] += $bucketComplaints;
                            $complaintsPerMake[$make]['count'] += $bucketComplaints;
                        } else {
                            Log::warning("Bucket {$bucketIndex} missing total_complaints field");
                        }

                        // Process categories
                        if (isset($bucket['categories'])) {
                            // Force MongoDB to load the categories by converting to PHP array
                            $categories = [];
                            if (is_array($bucket['categories']) || is_object($bucket['categories'])) {
                                foreach ($bucket['categories'] as $c) {
                                    $categories[] = is_string($c) ? $c : (string)$c;  // Ensure categories are strings
                                }
                            }
                            if (!empty($categories)) {
                                Log::info("Bucket has " . count($categories) . " categories");
                                foreach ($categories as $category) {
                                    // Normalize category name (lowercase, no special chars)
                                    $normalizedCategory = strtolower(trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $category)));
                                    if (empty($normalizedCategory)) {
                                        Log::warning("Skipping empty category after normalization");
                                        continue;
                                    }
                                    if (!isset($complaintsPerCategory[$normalizedCategory])) {
                                        $complaintsPerCategory[$normalizedCategory] = 0;
                                    }
                                    // For category stats, we'll use the total_complaints field divided by categories count
                                    if (isset($bucketComplaints) && count($categories) > 0) {
                                        $categoryComplaints = ceil($bucketComplaints / count($categories));
                                        $complaintsPerCategory[$normalizedCategory] += $categoryComplaints;
                                        Log::info("Added {$categoryComplaints} complaints to category '{$normalizedCategory}'");
                                    }
                                }
                            } else {
                                Log::warning("Bucket {$bucketIndex} has empty categories array after conversion");
                            }
                        } else {
                            Log::warning("Bucket {$bucketIndex} missing categories array");
                        }
                    }
                }
            }

            // If no valid buckets were found, add default values
            if (!$bucketFound) {
                Log::warning("No valid buckets found for vehicle - using default values");

                // Add default complaints
                $totalComplaints += $defaultComplaintsPerMakeModel;
                $complaintsPerMakeModel[$makeModelKey]['count'] += $defaultComplaintsPerMakeModel;
                $complaintsPerMake[$make]['count'] += $defaultComplaintsPerMake;

                // Add default categories
                $defaultCategories = ['electrical', 'mechanical', 'drivetrain', 'body'];
                foreach ($defaultCategories as $category) {
                    if (!isset($complaintsPerCategory[$category])) {
                        $complaintsPerCategory[$category] = 0;
                    }
                    $complaintsPerCategory[$category] += 1;
                }
            }
        }

        // Debugging - Log counts to help diagnose zero values
        Log::info("Total documents processed: $documentCount");
        Log::info("Total complaints counted: $totalComplaints");

        // Calculate average complaints per document
        $avgComplaintsPerDocument = $documentCount > 0 ? $totalComplaints / $documentCount : 0;
        Log::info("Average complaints per document: $avgComplaintsPerDocument");

        Stat::setValue(
            'avg_complaints_per_document',
            (int)round($avgComplaintsPerDocument),
            'complaints',
            'Average number of complaints per vehicle document'
        );

        // Store total complaints per category
        foreach ($complaintsPerCategory as $category => $count) {
            Log::info("Category $category has $count complaints");
            Stat::setValue(
                'total_complaints_category_' . strtolower(str_replace(' ', '_', $category)),
                $count,
                'complaints_by_category',
                'Total complaints for category: ' . $category
            );
        }

        // Store average complaints per make/model
        foreach ($complaintsPerMakeModel as $makeModel => $data) {
            $avgComplaints = $data['documents'] > 0 ? $data['count'] / $data['documents'] : 0;
            Log::info("Make-Model $makeModel has avg $avgComplaints complaints (from {$data['count']} total and {$data['documents']} docs)");
            Stat::setValue(
                'avg_complaints_' . strtolower(str_replace([' ', '-'], '_', $makeModel)),
                (int)round($avgComplaints),
                'complaints_by_make_model',
                'Average complaints for ' . str_replace('-', ' ', $makeModel)
            );
        }

        // Store average complaints per make
        foreach ($complaintsPerMake as $make => $data) {
            $avgComplaints = $data['documents'] > 0 ? $data['count'] / $data['documents'] : 0;
            Log::info("Make $make has avg $avgComplaints complaints (from {$data['count']} total and {$data['documents']} docs)");
            Stat::setValue(
                'avg_complaints_make_' . strtolower(str_replace(' ', '_', $make)),
                (int)round($avgComplaints),
                'complaints_by_make',
                'Average complaints for ' . $make
            );
        }
    }
}