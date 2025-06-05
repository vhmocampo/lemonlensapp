<?php

namespace App\Factories;

use App\Data\CategoryRankings;
use App\Data\FreeReport;
use App\Data\Repair;
use App\Facades\Lemonbase;
use App\Data\VehicleComplaintCollection;
use App\Data\VehicleComplaint;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\Vehicle;
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
        $vehicle = Lemonbase::getVehicle($report->year, $report->make, $report->model);

        if (!$vehicle) {
            throw new \Exception('Vehicle not found');
        }

        // Get the complaints for the vehicle, filter and sort them
        $filteredComplaints = self::filterComplaints(Lemonbase::getComplaintsForYearMakeModelMileage(
            $report->year,
            $report->make,
            $report->model,
            $report->mileage
        ));
        
        // Sort the filtered complaints by (count, high_estimate, match_score) descending
        self::sortComplaints($filteredComplaints);

        // Strip out sensitive fields and generalize some for free report
        $complaints = array_slice(self::generalizeComplaints($filteredComplaints), 0, 5);

        // get lowest cost from complaints, then get highest cost and present a range
        $costFrom = 0;
        $costTo = 0;
        foreach ($complaints as $complaint) {
            if ($complaint['average_cost'] < $costFrom || $costFrom === 0) {
                $costFrom = $complaint['average_cost'];
            }
            if ($complaint['average_cost'] > $costTo || $costTo === 0) {
                $costTo = $complaint['average_cost'];
            }   
        }

        // Get score for this vehicle
        $score = app(ScoringService::class)->getVehicleScore($vehicle);
        $recommendation = app(ScoringService::class)->getBuyerReccomendation($score);
        $result = [
            'score' => $score,
            'summary' => $vehicle->content['summary'] ?? '',
            'recommendation' => $recommendation,
            'complaints' => $complaints,
            'cost_from' => $costFrom,
            'cost_to' => $costTo,
            'recalls' => $vehicle->getRecalls() ?? [
                [
                    'description' => 'No recalls/critical issues found, consider a premium report for this vehicle for more information',
                    'priority' => 2,
                ]
            ],
            'known_issues' => $vehicle->getKnownIssues() ?? [
                [
                    'description' => 'No widley known issues found, consider a premium report for this vehicle for more information',
                    'priority' => 2,
                ]
            ],
            'suggestions' => $vehicle->getSuggestions() ?? [
                [
                    'description' => 'Regular maintenance extends the lifespan of this vehicle significantly',
                    'priority' => 2,
                ],
                [
                    'description' => 'Change the oil and filter regularly, and check the air filter',
                    'priority' => 2,
                ],
                [
                    'description' => 'Check the maintenance schedule and follow it',
                    'priority' => 2,
                ]
            ],
        ];
        
        $freeReport = new FreeReport();
        $freeReport->fromArray($result);
        $report->result = $freeReport;
        $report->status = ReportStatus::COMPLETED;
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
     * Filter the complaints to only include the most common repairs
     *
     * @param VehicleComplaintCollection $complaints
     * @return array
     */
    private static function filterComplaints(VehicleComplaintCollection $complaints)
    {
        $filteredComplaints = [];
        foreach ($complaints->items() as $complaint) {

            $repairDtos = $complaint->getEstimatedRepairDTOs(0.60);
            foreach ($repairDtos as $repair) {

                // If the repair title is not in the filtered complaints, add it
                if (!isset($filteredComplaints[$repair->getTitle()])) {
                    $filteredComplaints[$repair->getTitle()] = [
                        'count' => 0,
                        'match_scores' => [],
                        'primary_complaint' => '',
                    ];
                }

                // Calculate the average match score for the repair title
                $matchScores = array_merge($filteredComplaints[$repair->getTitle()]['match_scores'] ?? [], [$repair->getConfidenceScore()]);
                $matchScoreAverage = count($matchScores) > 0 ? array_sum($matchScores) / count($matchScores) : 0;

                $primaryComplaint = $complaint->getTitle();
                // if primary complaint contains any words like 'summarize', 'summary', 'complaint', 'language', 'apologize', 'unable', then set to null
                if (strpos($primaryComplaint, 'summarize') !== false || strpos($primaryComplaint, 'summary') !== false || strpos($primaryComplaint, 'complaint') !== false || strpos($primaryComplaint, 'language') !== false || strpos($primaryComplaint, 'apologize') !== false || strpos($primaryComplaint, 'unable') !== false) {
                    $primaryComplaint = null;
                }

                $filteredComplaints[$repair->getTitle()] = [
                    'count' => $filteredComplaints[$repair->getTitle()]['count'] + 1,
                    'title' => $repair->getTitle(),
                    'normalized_title' => Deslugify::deslugify($repair->getTitle()),
                    'match_score' => $matchScoreAverage,
                    'low_estimate' => $repair->getEstimatedCostLow(),
                    'high_estimate' => $repair->getEstimatedCostHigh(),
                    'category' => $complaint->getCategory(),
                    'category_ranking' => CategoryRankings::getPriority($complaint->getCategory()),
                    'bucket_from' => $complaint->getBucketFrom(),
                    'bucket_to' => $complaint->getBucketTo(),
                    'primary_complaint' => 
                        (strlen($primaryComplaint) > strlen($filteredComplaints[$repair->getTitle()]['primary_complaint'])) 
                        ? $primaryComplaint
                        : $filteredComplaints[$repair->getTitle()]['primary_complaint']
                ];
            }
        }

        return $filteredComplaints;
    }

    /**
     * Sort the complaints by count, high estimate, and match score
     *
     * @param array $complaints
     */
    private static function sortComplaints(array &$complaints)
    {
        uasort($complaints, function ($a, $b) {
            // First compare by count
            if ($a['count'] !== $b['count']) {
                return $b['count'] - $a['count'];
            }

            if ($a['high_estimate'] !== $b['high_estimate']) {
                return $b['high_estimate'] - $a['high_estimate'];
            }

            // Finally by match score
            return $b['match_score'] <=> $a['match_score'];
        });
    }

    /**
     * Generalize the complaints to only include the information we want to show in the free report
     *
     * @param array $complaints
     * @return array
     */
    private static function generalizeComplaints(array $complaints, $reportType = 'free')
    {
        $descriptionService = app(RepairDescriptionService::class);
        return array_map(function($complaint) use ($reportType, $descriptionService) {

            $timesReported = (intval($complaint['count']) > 100) 
                            ? 'well documented' 
                            : ((intval($complaint['count']) > 10)
                                ? 'reported several times'
                                : (intval($complaint['count']) >= 1 
                                    ? 'reported at least once'
                                    : 'not reported often'));

            $description = $descriptionService($complaint['title']);

            return [
                'normalized_title' => $complaint['normalized_title'],
                'description' => $description,
                'times_reported' => $timesReported,
                'bucket_from' => $complaint['bucket_from'],
                'bucket_to' => $complaint['bucket_to'],
                'likelyhood' => null, // users will have to pay for premium to see this
                'complaint' => (strlen($complaint['primary_complaint']) > 10 && strpos($complaint['primary_complaint'], 'NHTSA') === false) ? $complaint['primary_complaint'] : null, // only show if its a longer complaint with details
                'average_cost' => floor(($complaint['low_estimate'] + $complaint['high_estimate']) / 2),
            ];
        }, $complaints);
    }
}