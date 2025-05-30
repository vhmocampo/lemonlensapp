<?php

namespace App\Data;

use Illuminate\Support\Arr;

class VehicleComplaint
{
    /**
     * Store the complaint data.
     *
     * @var array
     */
    protected array $data;

    /**
     * Create a new VehicleComplaint instance.
     *
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getTitle(): ?string
    {
        return $this->data['title'] ?? null;
    }

    public function getCategory(): ?string
    {
        return $this->data['category'] ?? null;
    }

    public function getSeverity(): ?float
    {
        return $this->data['severity_rating'] ?? null;
    }

    public function getAverageMileage(): ?int
    {
        return $this->data['average_mileage'] ?? null;
    }

    public function getMatchedRepairs(): array
    {
        return $this->data['matched_repairs'] ?? [];
    }

    public function getEstimatedRepairs(): array
    {
        return $this->data['estimated_repairs'] ?? [];
    }

    public function getBucketFrom(): ?int
    {
        return $this->data['bucket_from'] ?? null;
    }
    
    public function getBucketTo(): ?int
    {
        return $this->data['bucket_to'] ?? null;
    }

    /**
     * Get estimated repairs as Repair DTOs, optionally filtered by confidence score.
     * Only repairs that have an estimated cost are returned.
     *
     * @param float|null $minimumScore The minimum confidence score to include (null means no filtering)
     * @return Repair[] Array of Repair DTOs
     */
    public function getEstimatedRepairDTOs(?float $minimumScore = null): array
    {
        $estimatedRepairs = $this->getEstimatedRepairs();
        $matchedRepairs = $this->getMatchedRepairs();
        $repairs = [];

        // Only process if we have both matched and estimated repairs
        if (!empty($estimatedRepairs) && !empty($matchedRepairs)) {
            foreach ($matchedRepairs as $matchedRepair) {
                // Skip if there's a minimum score and this repair doesn't meet it
                if ($minimumScore !== null && 
                    (isset($matchedRepair['score']) && $matchedRepair['score'] < $minimumScore)) {
                    continue;
                }

                // Find the corresponding estimated repair data
                foreach ($estimatedRepairs as $estimatedRepair) {
                    // Match the repair_slug with the matched_repair from matchedRepairs
                    if (isset($estimatedRepair['repair_slug']) && 
                        isset($matchedRepair['matched_repair']) &&
                        $this->normalizeRepairSlug($estimatedRepair['repair_slug']) === 
                        $this->normalizeRepairSlug($matchedRepair['matched_repair'])) {

                        // Combine the data from both sources
                        $repairData = array_merge(Arr::only($estimatedRepair, [
                            'repair_slug',
                            'estimated_cost_low',
                            'estimated_cost_high',
                        ]), [
                            'repair' => $matchedRepair['inferred_repair'] ?? null,
                            'score' => $matchedRepair['score'] ?? null
                        ]);

                        // Only include repairs that have cost estimates
                        if (isset($repairData['estimated_cost_low']) && isset($repairData['estimated_cost_high'])) {
                            $repairs[] = new Repair($repairData);
                        }
                    }
                }
            }
        }

        return $repairs;
    }

    /**
     * Normalize a repair slug or name for comparison.
     * 
     * @param string $repairName The repair name or slug to normalize
     * @return string Normalized repair name
     */
    private function normalizeRepairSlug(string $repairName): string
    {
        // Convert to lowercase and replace hyphens with spaces
        return str_replace('-', ' ', strtolower($repairName));
    }
}