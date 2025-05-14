<?php

namespace App\Data;

class Repair
{
    /**
     * Store the repair data.
     *
     * @var array
     */
    protected array $data;

    /**
     * Create a new Repair instance.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the repair title/name.
     *
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->data['repair_slug'] ?? $this->data['repair_item'] ?? null;
    }

    /**
     * Get the estimated low cost for the repair.
     *
     * @return float|null
     */
    public function getEstimatedCostLow(): ?float
    {
        return $this->data['estimated_cost_low'] ?? null;
    }

    /**
     * Get the estimated high cost for the repair.
     *
     * @return float|null
     */
    public function getEstimatedCostHigh(): ?float
    {
        return $this->data['estimated_cost_high'] ?? null;
    }

    /**
     * Get the confidence score for this repair.
     *
     * @return float|null
     */
    public function getConfidenceScore(): ?float
    {
        return $this->data['score'] ?? null;
    }

    /**
     * Get the source of the repair information.
     *
     * @return string|null
     */
    public function getSource(): ?string
    {
        return $this->data['source'] ?? null;
    }

    /**
     * Get the formatted cost range as a string.
     *
     * @return string|null
     */
    public function getFormattedCostRange(): ?string
    {
        if ($this->getEstimatedCostLow() === null || $this->getEstimatedCostHigh() === null) {
            return null;
        }

        return '$' . $this->getEstimatedCostLow() . ' - $' . $this->getEstimatedCostHigh();
    }
}