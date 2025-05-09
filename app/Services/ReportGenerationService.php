<?php

namespace App\Services;

use App\Models\Report;

class ReportGenerationService
{
    public function generate(Report $report): array
    {
        // Heavy logic here (e.g. call LLMs, aggregate data)
        return [
            'summary' => "Generated report for type: {$report->type}",
            'timestamp' => now(),
        ];
    }
}
