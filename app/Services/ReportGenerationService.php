<?php

namespace App\Services;

use App\Models\Report;
use App\Factories\ReportFactory;

class ReportGenerationService
{
    protected $reportFactory;

    public function __construct(ReportFactory $reportFactory)
    {
        $this->reportFactory = $reportFactory;
    }

    /**
     * Generate a report for a vehicle based on its mileage and other data.
     * If data is passed, it will generally be user submitted strings.
     *
     * @param object $vehicle The vehicle to generate a report for
     * @param int $mileage The current mileage of the vehicle
     * @param array $data Additional data for premium reports
     * @return void
     */
    public function generateReport($vehicle, $mileage, $data = []) {
        // Determine the report type based on input
        $isPremium = !empty($data);

        // Use the factory to create the appropriate report DTO
        $reportDTO = $isPremium 
            ? $this->reportFactory->createPremiumReport($vehicle, $mileage, $data)
            : $this->reportFactory->createFreeReport($vehicle, $mileage);

        return;
    }
}