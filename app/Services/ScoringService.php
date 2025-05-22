<?php

namespace App\Services;

use App\Data\CategoryRankings;
use App\Models\Stat;
use Illuminate\Support\Facades\Log;

class ScoringService {

    const BASE_SCORE = 100; // Base score for the vehicle
    const MIN_SCORE = 40; // Least possible score

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
        $averageComplaintsPerMake = Stat::getValue(sprintf('avg_complaints_make_%s', strtolower($make))) ?? 1;
        $averageComplaintsPerModel = Stat::getValue(sprintf('avg_complaints_%s_%s', strtolower($make), strtolower($model))) ?? 1;
        $averageComplaintsTotal = Stat::getValue('avg_complaints_per_document') ?? 1;
        $totalVehicles = Stat::getValue('total_documents') ?? 1;

        $score = self::BASE_SCORE;

        // 1. Use a stronger overall penalty, or make it scale beyond 0.4 (try 0.7 as a cap for bad cases)
        $overallRatio = $complaintCount / max($averageComplaintsPerModel, 1);
        $overallPenalty = min(0.7, max(0, ($overallRatio - 1) / 2)); // No penalty if at or below avg, up to 0.7 if much higher
        $score -= round($overallPenalty * (self::BASE_SCORE - self::MIN_SCORE));

        // 2. Fix: Sort by highest priority first (high > medium > low). If using string priorities, use usort:
        $rankedCategories = [];
        foreach ($countsPerCategory as $category => $count) {
            $priority = $countsPerPriority[$category] ?? 'medium'; // default to medium if not set
            $rankedCategories[] = ['category' => $category, 'priority' => $priority, 'count' => $count];
        }

        // Custom sort: high first, medium second, low third
        $priorityOrder = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($rankedCategories, function($a, $b) use ($priorityOrder) {
            return $priorityOrder[$a['priority']] <=> $priorityOrder[$b['priority']];
        });

        // Get the top 10 highest priority categories
        $topCategories = array_slice($rankedCategories, 0, 10);

        // 3. Use more impactful per-category penalties for big outliers
        $priorityWeights = [
            'high'   => 2.2,    // up from 1.5
            'medium' => 1.3,
            'low'    => 0.9,
        ];

        foreach ($topCategories as $item) {
            $category = $item['category'];
            $priority = $item['priority'];
            $count = $item['count'];

            $priorityWeight = $priorityWeights[$priority] ?? 1.0;

            $totalCount = Stat::getValue(sprintf('total_complaints_category_%s', str_replace(" ", "_", $category))) ?? 1;
            $averageCategory = $totalCount / max($totalVehicles, 1);

            if ($count > $averageCategory) {
                $overAvg = ($count - $averageCategory) / max($averageCategory, 1);
                $deduction = $overAvg * $priorityWeight * 6; // Increase impact per category!
                $score -= round($deduction);
            }
        }

        // Clamp to min/max
        $score = max(self::MIN_SCORE, min(self::BASE_SCORE, $score));

        return (int) $score;
    }
}