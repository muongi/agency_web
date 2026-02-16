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

class GenerateClientReportJob implements ShouldQueue
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
            $data = $reportService->getClientMonthlyTotals($filters);

            if ($data->isEmpty()) {
                $report->markAsFailed('No data found for the selected filters.');
                return;
            }

            // Prepare CSV data
            $headers = [
                'client_name',
                'month',
                'year',
                'total_amount',
                'total_cost',
                'transaction_count',
            ];

            $csvData = $data->map(function ($item) {
                return [
                    'client_name' => $item['client_name'],
                    'month' => $item['month'],
                    'year' => $item['year'],
                    'total_amount' => number_format($item['total_amount'], 2),
                    'total_cost' => number_format($item['total_cost'], 2),
                    'transaction_count' => $item['transaction_count'],
                ];
            })->toArray();

            $filename = 'client_report_' . $report->id . '_' . Carbon::now()->format('Y-m-d_His') . '.csv';
            $filePath = $reportService->generateCsvFile($csvData, $headers, $filename);

            $report->markAsCompleted($filePath, $filename, 'csv', count($csvData));
        } catch (\Exception $e) {
            $report->markAsFailed('Error generating report: ' . $e->getMessage());
            throw $e;
        }
    }
}
