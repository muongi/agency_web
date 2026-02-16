<?php

namespace App\Jobs;

use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class GenerateCommissionReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $reportId;

    /**
     * Create a new job instance.
     */
    public function __construct($reportId)
    {
        $this->reportId = $reportId;
    }

    /**
     * Execute the job.
     */
    public function handle(ReportService $reportService)
    {
        $report = Report::findOrFail($this->reportId);
        
        try {
            $report->markAsProcessing();

            $filters = $report->filters ?? [];
            $data = $reportService->getCommissionSummary($filters);

            if ($data->isEmpty()) {
                $report->markAsFailed('No data found for the selected filters.');
                return;
            }

            // Prepare CSV data
            $headers = [
                'agent_name',
                'transaction_type_name',
                'month',
                'year',
                'total_commission',
                'total_cost',
                'total_business_share',
                'transaction_count',
            ];

            $csvData = $data->map(function ($item) {
                return [
                    'agent_name' => $item['agent_name'],
                    'transaction_type_name' => $item['transaction_type_name'],
                    'month' => $item['month'],
                    'year' => $item['year'],
                    'total_commission' => number_format($item['total_commission'], 2),
                    'total_cost' => number_format($item['total_cost'], 2),
                    'total_business_share' => number_format($item['total_business_share'], 2),
                    'transaction_count' => $item['transaction_count'],
                ];
            })->toArray();

            $filename = 'commission_report_' . $report->id . '_' . Carbon::now()->format('Y-m-d_His') . '.csv';
            $filePath = $reportService->generateCsvFile($csvData, $headers, $filename);

            $report->markAsCompleted($filePath, $filename, 'csv', count($csvData));
        } catch (\Exception $e) {
            $report->markAsFailed('Error generating report: ' . $e->getMessage());
            throw $e;
        }
    }
}
