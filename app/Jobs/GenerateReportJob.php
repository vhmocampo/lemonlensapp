<?php

namespace App\Jobs;

use App\Enums\ReportStatus;
use App\Factories\ReportFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;
use App\Models\Report;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $reportId;

    public function __construct($reportId)
    {
        $this->reportId = $reportId;
    }

    public function handle()
    {
        $report = Report::find($this->reportId);
        switch ($report->type) {
            case 'standard':
                ReportFactory::createFreeReport($report);
                break;
            case 'premium':
                ReportFactory::createPremiumReport($report);
            break;
        }
    }

    public function failed(\Throwable $exception)
    {
        // Handle the failure, e.g., log the error or notify someone
        \Log::error('Failed to generate report', [
            'report_id' => $this->reportId,
            'error' => $exception->getMessage()
        ]);
        $report = Report::find($this->reportId);
        if ($report) {
            $report->status = ReportStatus::FAILED;
            $report->save();
        }
    }
}
