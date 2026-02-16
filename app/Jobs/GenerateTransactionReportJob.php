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

class GenerateTransactionReportJob implements ShouldQueue
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
            $data = $reportService->getTransactionSummary($filters);

            if ($data->isEmpty()) {
                $report->markAsFailed('No data found for the selected filters.');
                return;
            }

            // Prepare CSV data
            $headers = [
                'transaction_date',
                'transaction_type',
                'transaction_amount',
                'transaction_cost',
                'commission',
                'business_share',
                'agent_name',
                'client_name',
                'terminal_serial',
                'status',
                'reference',
            ];

            $csvData = $data->map(function ($transaction) {
                return [
                    'transaction_date' => $transaction->transaction_date->format('Y-m-d H:i:s'),
                    'transaction_type' => $transaction->transactionType ? $transaction->transactionType->name : 'N/A',
                    'transaction_amount' => number_format($transaction->transaction_amount, 2),
                    'transaction_cost' => number_format($transaction->transaction_cost, 2),
                    'commission' => number_format($transaction->commission, 2),
                    'business_share' => number_format($transaction->business_share, 2),
                    'agent_name' => $transaction->agent ? $transaction->agent->full_name : 'N/A',
                    'client_name' => $transaction->client ? $transaction->client->full_name : 'N/A',
                    'terminal_serial' => $transaction->terminal ? $transaction->terminal->serial_number : 'N/A',
                    'status' => $transaction->status,
                    'reference' => $transaction->reference ?? 'N/A',
                ];
            })->toArray();

            $filename = 'transaction_report_' . $report->id . '_' . Carbon::now()->format('Y-m-d_His') . '.csv';
            $filePath = $reportService->generateCsvFile($csvData, $headers, $filename);

            $report->markAsCompleted($filePath, $filename, 'csv', count($csvData));
        } catch (\Exception $e) {
            $report->markAsFailed('Error generating report: ' . $e->getMessage());
            throw $e;
        }
    }
}
