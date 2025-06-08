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
     * @param Vehicle $vehicle The vehicle
     * @return int Score from BASE_SCORE to 100
     */
    public function getVehicleScore($vehicle) {

        $score = self::BASE_SCORE;

        // Get the vehicle's reliability score
        $reliabilityScore = $vehicle->content['reliability'];

        // Get the vehicle's total complaint count
        $totalComplaintCount = $vehicle->total_complaint_count ?? 100;

        // Get the vehicle's units sold
        $unitsSold = $vehicle->content['units_sold'] ?? 30000;

        // Get the average complaint per 1000 units sold from the Stats table
        $averageComplaintPer1000UnitsSold = Stat::getValue('avg_complaints_per_1000');

        // Calculate the score
        $score = $reliabilityScore - ($totalComplaintCount / $unitsSold) * $averageComplaintPer1000UnitsSold;

        // Calculate the score
        return (int) $score;
    }

    public function getBuyerReccomendation($score) {
        $min = Stat::getValue('min_reliability_score');
        $max = Stat::getValue('max_reliability_score');
        $avg = Stat::getValue('avg_reliability_score');
        
        if ($max == $min) {
            // If all scores are the same, just compare to avg
            if ($score > $avg) return "Good Choice";
            if ($score == $avg) return "Consider Other Options";
            return "Avoid if possible";
        }
        
        $percentile = ($score - $min) / ($max - $min); // 0 to 1
    
        if ($percentile > 0.86) return "Rare Find, Great Buy!";
        if ($percentile >= 0.85) return "Strong Choice";
        if ($percentile >= 0.65) return "Good Choice";
        if ($percentile >= 0.35) return "Consider Other Options";
        if ($percentile >= 0.15) return "Avoid if possible";
        return "Avoid at all costs";
    }
    
}