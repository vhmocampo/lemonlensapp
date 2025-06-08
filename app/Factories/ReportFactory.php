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
use App\Services\PremiumChatService;
use App\Services\RepairDescriptionService;
use App\Services\ScoringService;
use App\Util\Deslugify;
use Illuminate\Support\Str;
use App\Services\CreditService;
use Illuminate\Support\Facades\Http;

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
     * @param object $report
     */
    public static function createPremiumReport(Report $report): void
    {
        $vehicle = Lemonbase::getVehicle($report->year, $report->make, $report->model);

        if (!$vehicle) {
            throw new \Exception('Vehicle not found');
        }

        // Send REPORT html if possible
        $listingHtml = null;
        if (isset($report->params['listingLink']) && !empty($report->params['listingLink'])) {
            try {
                $listingHtml = self::getListingInformation($report);
            } catch (\Exception $e) {
                // Log the error but continue
                \Log::error('Failed to fetch listing information: ' . $e->getMessage());
            }
        }

        $chatService = app(PremiumChatService::class);
        $response = $chatService(
            $report->make,
            $report->model,
            $report->year,
            $report->mileage,
            $report->params['zipCode'] ?? 90210,
            $report->params['additionalInfo'] ?? '',
            $listingHtml
        );

        if (!is_array($response) || empty($response) || !isset($response['score']) || !isset($response['summary'])) {
            // retry 2 more times
            for ($i = 0; $i < 2; $i++) {
                $response = $chatService(
                    $report->make,
                    $report->model,
                    $report->year,
                    $report->mileage,
                    $report->params['zipCode'] ?? 90210,
                    $report->params['additionalInfo'] ?? '',
                    $listingHtml
                );
                if (is_array($response) && !empty($response) && isset($response['score']) && isset($response['summary'])) {
                    break;
                }
            }
        }

        if (!is_array($response) || empty($response) || !isset($response['score']) || !isset($response['summary'])) {
            throw new \Exception('Failed to generate premium report, please try again later');
        }

        // Recalls mapping to expected format
        $recalls = [];
        if (isset($response['recalls']) && is_array($response['recalls'])) {
            foreach ($response['recalls'] as $recall) {
                $recalls[] = [
                    'description' => $recall['description'] . "; Date: " . $recall['recall_date'] ?? 'No description provided',
                    'priority' => $recall['critical'] ? 1 : 2,
                ];
            }
        }

        // Known issues mapping to expected format
        $knownIssues = [];
        if (isset($response['known_issues']) && is_array($response['known_issues'])) {
            foreach ($response['known_issues'] as $issue) {
                $knownIssues[] = [
                    'description' => $issue['description'] ?? 'No description provided',
                    'priority' => $issue['critical'] ? 1 : 2,
                ];
            }
        }

        // Format complaints to the expected structure
        $complaints = [];
        if (isset($response['repairs']) && is_array($response['repairs'])) {
            usort($response['repairs'], function ($a, $b) {
                return intval($b['likelihood']) <=> intval($a['likelihood']); // Sort by likelihood descending
            });
            foreach ($response['repairs'] as $repair) {
                $complaints[Str::slug($repair['name'])] = [
                    'normalized_title' => $repair['name'] ?? 'Unknown Repair',
                    'description' => $repair['description'] ?? 'No description provided',
                    'times_reported' => $repair['times_reported'] ?? 'not reported often',
                    'bucket_from' => $repair['mileage_range_from'] ?? 0,
                    'bucket_to' => $repair['mileage_range_to'] ?? 0,
                    'cost_range_from' => $repair['cost_range_from'] ?? 0,
                    'cost_range_to' => $repair['cost_range_to'] ?? 0,
                    'likelyhood' => $repair['likelihood'] ?? null, // users will have to pay for premium to see this
                    'complaint' => $repair['example_complaint'] ?? null, // only show if its a longer complaint with details
                    'average_cost' => $repair['average_cost'] ?? 0,
                ];
            }
        }

        $score = $response['score'] ?? app(ScoringService::class)->getVehicleScore($vehicle);
        $recommendation = app(ScoringService::class)->getBuyerReccomendation($score);
        $result = [
            'score' => $score,
            'summary' => $response['summary'] ?? $vehicle->content['summary'],
            'recommendation' => $recommendation,
            'complaints' => $complaints,
            'cost_from' => $response['cost_from'] ?? 0,
            'cost_to' => $response['cost_to'] ?? 0,
            'checklist' => $response['checklist'] ?? [],
            'questions' => $response['questions'] ?? [],
            'recalls' => $recalls,
            'known_issues' => $knownIssues,
            'sources' => $response['sources'] ?? 'NHTSA, Edmunds, Consumer Reports',
            'suggestions' => $response['suggestions'] ?? $vehicle->getSuggestions() ?? [
                'Regular maintenance extends the lifespan of this vehicle significantly',
                'Change the oil and filter regularly, and check the air filter',
                'Check the maintenance schedule and follow it',
            ],
        ];

        // Deduct a credit
        $user = $report->user;
        $service = app(CreditService::class);
        $service->deductCredits($user, 1, 'Premium report for ' . $report->make . ' ' . $report->model . ' ' . $report->year, [
            'report_uuid' => $report->uuid,
            'vehicle' => [
                'make' => $report->make,
                'model' => $report->model,
                'year' => $report->year,
                'mileage' => $report->mileage,
            ],
        ]);

        $report->result = $result;
        $report->status = ReportStatus::COMPLETED;
        $report->save();
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
                'complaint' => null, // only show if its a longer complaint with details
                'average_cost' => floor(($complaint['low_estimate'] + $complaint['high_estimate']) / 2),
            ];
        }, $complaints);
    }

    public static function getListingInformation(Report $report)
    {
        $link = $report->params['listingLink'] ?? null;
        if (!$link) {
            throw new \Exception('Listing URL not provided');
        }

        $url = sprintf("https://api.scraperapi.com/?api_key=%s&url=%s&country_code=us", config('services.scraperapi.key'), urlencode($link));

        $response = Http::withoutVerifying()
            ->get($url)
            ->body();

        // Remove HTML tags and decode HTML entities
        $clean_response = strip_tags($response);
        $clean_response = html_entity_decode($clean_response, ENT_QUOTES | ENT_HTML5);

        return $clean_response;
    }
}