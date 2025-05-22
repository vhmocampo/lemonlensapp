<?php

namespace App\Services;

use App\Data\CategoryRankings;
use App\Models\Stat;
use Illuminate\Support\Facades\Log;

class ScoringService {

    const BASE_SCORE = 50; // Base score for the vehicle
    const MAX_SCORE = 100; // Maximum possible score

    /**
     * Calculate a vehicle score based on complaints and statistics
     * 
     * @param int $year The vehicle year
     * @param string $make The vehicle make
     * @param string $model The vehicle model
     * @param int $mileage The vehicle mileage
     * @param array $filteredComplaints Array of filtered complaints with priority counts
     * @return int Score from BASE_SCORE to 100
     */
    public function getVehicleScore($year, $make, $model, $mileage, $countsPerCategory, $countsPerPriority, $complaintCount) {
        $averageComplaintsPerMake = Stat::getValue(sprintf('avg_complaints_make_%s', strtolower($make)));
        $averageComplaintsPerModel = Stat::getValue(sprintf('avg_complaints_%s_%s', strtolower($make), strtolower($model)));
        $averageComplaintsTotal = Stat::getValue('avg_complaints_per_document');
        $totalVehicles = Stat::getValue('total_documents');

        // Compare category counts to average counts
        $rankedCategories = [];
        foreach ($countsPerCategory as $category => $count) {
            $rankedCategories[$category] = CategoryRankings::getPriority($category);
        }
        asort($rankedCategories);
        $topFiveCategories = array_slice($rankedCategories, 0, 5, true);

        foreach ($topFiveCategories as $category => $priority) {
            $totalCount = Stat::getValue(sprintf('total_complaints_category_%s', str_replace(" ", "_", $category)));
            $averageCount = $totalCount / $totalVehicles;
        }

        $score = self::BASE_SCORE;

    }
}