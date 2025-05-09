<?php

namespace App\Data;

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
}
