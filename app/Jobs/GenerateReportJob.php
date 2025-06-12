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
use App\Services\CreditService;
use Illuminate\Support\Facades\Mail;

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

            Mail::raw(sprintf('FAILED REPORT GENERATION - %s', $report->uuid,), function ($message) {
                $message->to('vmocampo357@gmail.com')
                        ->subject('FAILED ERROR REPORT');
            });

            $user = $report->user;
            $service = app(CreditService::class);
            $service->addCredits($user, 1, 'Re-imburse failed premium report for ' . $report->make . ' ' . $report->model . ' ' . $report->year, [
                'report_uuid' => $report->uuid,
                'vehicle' => [
                    'make' => $report->make,
                    'model' => $report->model,
                    'year' => $report->year,
                    'mileage' => $report->mileage,
                ],
            ]);

            $report->status = ReportStatus::FAILED;
            $report->save();
        }
    }
}
