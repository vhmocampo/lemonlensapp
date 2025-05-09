<?php

namespace App\Data;

use Illuminate\Support\Collection;

class VehicleComplaintCollection
{
    /** @var Collection|VehicleComplaint[] */
    protected Collection $items;

    public function __construct(array $rawItems)
    {
        // Convert raw arrays into VehicleComplaint instances
        $this->items = collect($rawItems)->map(fn ($data) => ($data instanceof VehicleComplaint) ? $data : new VehicleComplaint($data));
    }

    public static function fromMongoCursor(iterable $cursor): static
    {
        return new static(iterator_to_array($cursor));
    }

    public function filterByCategory(string $category): static
    {
        return new static(
            $this->items->filter(fn ($c) => $c->getCategory() === $category)->values()->all()
        );
    }

    public function getAverageMileage(): ?float
    {
        $mileages = $this->items->pluck('average_mileage')->filter()->all();

        if (empty($mileages)) return null;

        return round(array_sum($mileages) / count($mileages), 1);
    }

    public function count(): int
    {
        return $this->items->count();
    }

    public function all(): Collection
    {
        return $this->items;
    }

    public function items(): Collection
    {
        return $this->items;
    }
}
