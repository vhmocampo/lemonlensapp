<?php

namespace App\Factories;

use App\Data\CategoryRankings;
use App\Data\Repair;
use App\Facades\Lemonbase;
use App\Data\VehicleComplaintCollection;
use App\Data\VehicleComplaint;
use App\Models\Report;
use App\Services\RepairDescriptionService;
use App\Services\ScoringService;
use App\Util\Deslugify;

class ReportFactory
{
    /**
     * Create a free report DTO.
     *
     * @param object $report
     */
    public static function createFreeReport(Report $report): void
    {
        /** @var VehicleComplaintCollection $complaints */
        $complaints = Lemonbase::getComplaintsForYearMakeModelMileage(
            $report->year,
            $report->make,
            $report->model,
            $report->mileage
        );

        $filteredComplaints = self::filterComplaints($complaints);

        // Per priority, flatten the repairs and deduplicate for final output
        $repairBuckets = [
            'high' => [],
            'medium' => [],
            'low' => [],
            'title_counts' => [],
        ];

        // Get the repair buckets and title counts
        foreach ($filteredComplaints as $priority => $complaints) {
            $finalRepairs = [];
            foreach ($complaints as $complaint) {
                if (empty($complaint['estimated_repairs'])) {
                    continue;
                }
                foreach ($complaint['estimated_repairs'] as $repair) {
                    $repairBuckets['title_counts'][$repair['title']] = ($repairBuckets['title_counts'][$repair['title']] ?? 0) + 1;
                    $finalRepairs[$repair['title']] = [
                        'complaint_title' => $complaint['title'],
                        'category' => $complaint['category'],
                        'average_mileage' => $complaint['average_mileage'],
                        'average_score' => $complaint['average_score'],
                        'median_cost' => $complaint['median_cost'],
                        'title' => $repair['title'],
                        'description' => $repair['description'],
                        'confidence_score' => $repair['confidence_score'],
                        'estimated_cost_low' => $repair['estimated_cost_low'],
                        'estimated_cost_high' => $repair['estimated_cost_high'],
                        'estimated_cost' => $repair['estimated_cost'],
                    ];
                }
            }
            $repairBuckets[$priority] = $finalRepairs;
        }

        // Here we can calculate the vehicle score, before filtering for free reports
        $vehicleScore = app(ScoringService::class)->getVehicleScore(
            $report->year,
            $report->make,
            $report->model,
            $report->mileage,
            $filteredComplaints['category_counts'],
            $filteredComplaints['priority_counts'],
            count($filteredComplaints['high']) + count($filteredComplaints['medium']) + count($filteredComplaints['low'])
        );

        // For free reports, sort by score and just get the top 3 for each priority
        foreach ($repairBuckets as $priority => $repairs) {
            if ($priority === 'title_counts') {
                continue;
            }
            usort($repairs, function ($a, $b) {
                return $b['average_score'] <=> $a['average_score'];
            });
            $repairBuckets[$priority] = array_slice($repairs, 0, 3);
        }

        // For free reports, show top 3 titles, ordered by count
        arsort($repairBuckets['title_counts']);
        $repairBuckets['title_counts'] = array_slice($repairBuckets['title_counts'], 0, 3);

        // For free reports, show top 3 categories, ordered by count
        arsort($filteredComplaints['category_counts']);
        $filteredComplaints['category_counts'] = array_slice($filteredComplaints['category_counts'], 0, 3);

        $report->result = [
            'score' => $vehicleScore,
            'repair_buckets' => $repairBuckets,
            'category_counts' => $filteredComplaints['category_counts'],
            'priority_counts' => $filteredComplaints['priority_counts']
        ];

        \Log::debug('Report result:', $report->result);
        $report->status = 'completed';
        $report->save();
    }

    /**
     * Create a premium report DTO.
     *
     * @param object $vehicle
     * @param int $mileage
     * @param array $data
     */
    public static function createPremiumReport($vehicle, $mileage, array $data): void
    {

    }

    /**
     * Filter complaints by category and priority.
     *
     * @param VehicleComplaintCollection $complaints
     * @return array
     */
    public static function filterComplaints(
        VehicleComplaintCollection $complaints,
    ): array {

        $repairDescriptionService = app(RepairDescriptionService::class);
        $categoryCounts = [];
        $priorityCounts = [];
        $filteredComplaints = [
            'high' => [],
            'medium' => [],
            'low' => [],
        ];

        /** @var VehicleComplaint $complaint */
        $complaints->items()->each(function (VehicleComplaint $complaint) use (
            &$categoryCounts,
            &$priorityCounts,
            &$filteredComplaints,
            $repairDescriptionService
        ) {
            $category = $complaint->getCategory();
            $priority = CategoryRankings::getPriority($category);

            $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
            $priorityCounts[$priority] = ($priorityCounts[$priority] ?? 0) + 1;


            $repairDtos = $complaint->getEstimatedRepairDTOs(0.65);

            if (empty($repairDtos)) {
                return;
            }

            // Deduplicate repairs by title
            $repairDtos = array_values(array_unique($repairDtos, SORT_REGULAR));

            $averageScore = array_reduce($repairDtos, function ($carry, Repair $repair) {
                return $carry + $repair->getConfidenceScore();
            }, 0) / count($repairDtos);

            // Replace the average cost calculation with median
            $costs = array_map(function (Repair $repair) {
                return ($repair->getEstimatedCostLow() + $repair->getEstimatedCostHigh()) / 2;
            }, $repairDtos);
            sort($costs);
            $medianCost = 0;
            $count = count($costs);
            if ($count > 0) {
                $middle = floor(($count - 1) / 2);
                if ($count % 2) {
                    // Odd number of elements
                    $medianCost = $costs[$middle];
                } else {
                    // Even number of elements - average the middle two
                    $medianCost = ($costs[$middle] + $costs[$middle + 1]) / 2;
                }
            }

            $filteredComplaints[$priority][] = [
                'title' => $complaint->getTitle(),
                'category' => $category,
                'average_mileage' => $complaint->getAverageMileage(),
                'average_score' => $averageScore,
                'median_cost' => $medianCost,
                'estimated_repairs' => array_map(function (Repair $repair) use ($repairDescriptionService) {
                    return [
                        'title' => Deslugify::delugify($repair->getTitle()),
                        'description' => $repairDescriptionService($repair->getTitle()),
                        'confidence_score' => $repair->getConfidenceScore(),
                        'estimated_cost_low' => $repair->getEstimatedCostLow(),
                        'estimated_cost_high' => $repair->getEstimatedCostHigh(),
                        'estimated_cost' => $repair->getEstimatedCostLow() + ($repair->getEstimatedCostHigh() - $repair->getEstimatedCostLow()) / 2,
                    ];
                }, $repairDtos),
            ];
        });

        // set the category counts and priority counts in the filtered complaints
        $filteredComplaints['category_counts'] = $categoryCounts;
        $filteredComplaints['priority_counts'] = $priorityCounts;

        return $filteredComplaints;
    }
}