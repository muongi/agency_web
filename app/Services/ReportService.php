<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Agent;
use App\Models\Client;
use App\Models\TransactionType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Get agent monthly transaction totals.
     */
    public function getAgentMonthlyTotals($filters)
    {
        $query = Transaction::where('business_unit_id', $filters['business_unit_id'])
            ->whereNotNull('agent_id')
            ->selectRaw('
                agent_id,
                MONTH(transaction_date) as month,
                YEAR(transaction_date) as year,
                SUM(transaction_amount) as total_amount,
                SUM(transaction_cost) as total_cost,
                SUM(commission) as total_commission,
                SUM(business_share) as total_business_share,
                COUNT(*) as transaction_count
            ')
            ->groupBy('agent_id', 'month', 'year');

        if (!empty($filters['start_date'])) {
            $query->where('transaction_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('transaction_date', '<=', $filters['end_date'] . ' 23:59:59');
        }
        if (!empty($filters['agent_id'])) {
            $query->where('agent_id', $filters['agent_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $results = $query->get();

        // Load agent relationships
        $agentIds = $results->pluck('agent_id')->unique();
        $agents = Agent::whereIn('id', $agentIds)->get()->keyBy('id');

        return $results->map(function ($item) use ($agents) {
            $agent = $agents->get($item->agent_id);
            return [
                'agent_id' => $item->agent_id,
                'agent_name' => $agent ? $agent->full_name : 'Unknown',
                'agent_username' => $agent ? $agent->username : 'N/A',
                'month' => $item->month,
                'year' => $item->year,
                'total_amount' => (float) $item->total_amount,
                'total_cost' => (float) $item->total_cost,
                'total_commission' => (float) $item->total_commission,
                'total_business_share' => (float) $item->total_business_share,
                'transaction_count' => (int) $item->transaction_count,
            ];
        });
    }

    /**
     * Get client monthly transaction totals.
     */
    public function getClientMonthlyTotals($filters)
    {
        $query = Transaction::where('business_unit_id', $filters['business_unit_id'])
            ->whereNotNull('client_id')
            ->selectRaw('
                client_id,
                MONTH(transaction_date) as month,
                YEAR(transaction_date) as year,
                SUM(transaction_amount) as total_amount,
                SUM(transaction_cost) as total_cost,
                COUNT(*) as transaction_count
            ')
            ->groupBy('client_id', 'month', 'year');

        if (!empty($filters['start_date'])) {
            $query->where('transaction_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('transaction_date', '<=', $filters['end_date'] . ' 23:59:59');
        }
        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $results = $query->get();

        // Load client relationships
        $clientIds = $results->pluck('client_id')->unique();
        $clients = Client::whereIn('id', $clientIds)->get()->keyBy('id');

        return $results->map(function ($item) use ($clients) {
            $client = $clients->get($item->client_id);
            return [
                'client_id' => $item->client_id,
                'client_name' => $client ? $client->full_name : 'Unknown',
                'month' => $item->month,
                'year' => $item->year,
                'total_amount' => (float) $item->total_amount,
                'total_cost' => (float) $item->total_cost,
                'transaction_count' => (int) $item->transaction_count,
            ];
        });
    }

    /**
     * Get transaction summary.
     */
    public function getTransactionSummary($filters)
    {
        $query = Transaction::where('business_unit_id', $filters['business_unit_id'])
            ->with(['transactionType', 'agent', 'client', 'terminal']);

        if (!empty($filters['start_date'])) {
            $query->where('transaction_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('transaction_date', '<=', $filters['end_date'] . ' 23:59:59');
        }
        if (!empty($filters['transaction_type_id'])) {
            $query->where('transaction_type_id', $filters['transaction_type_id']);
        }
        if (!empty($filters['agent_id'])) {
            $query->where('agent_id', $filters['agent_id']);
        }
        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }
        if (!empty($filters['terminal_id'])) {
            $query->where('terminal_id', $filters['terminal_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('transaction_date', 'desc')->get();
    }

    /**
     * Get commission summary.
     */
    public function getCommissionSummary($filters)
    {
        $query = Transaction::where('business_unit_id', $filters['business_unit_id'])
            ->whereNotNull('agent_id')
            ->selectRaw('
                agent_id,
                transaction_type_id,
                MONTH(transaction_date) as month,
                YEAR(transaction_date) as year,
                SUM(commission) as total_commission,
                SUM(transaction_cost) as total_cost,
                SUM(business_share) as total_business_share,
                COUNT(*) as transaction_count
            ')
            ->groupBy('agent_id', 'transaction_type_id', 'month', 'year');

        if (!empty($filters['start_date'])) {
            $query->where('transaction_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('transaction_date', '<=', $filters['end_date'] . ' 23:59:59');
        }
        if (!empty($filters['agent_id'])) {
            $query->where('agent_id', $filters['agent_id']);
        }
        if (!empty($filters['transaction_type_id'])) {
            $query->where('transaction_type_id', $filters['transaction_type_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $results = $query->get();

        // Load relationships
        $agentIds = $results->pluck('agent_id')->unique();
        $agents = Agent::whereIn('id', $agentIds)->get()->keyBy('id');
        $typeIds = $results->pluck('transaction_type_id')->unique();
        $types = TransactionType::whereIn('id', $typeIds)->get()->keyBy('id');

        return $results->map(function ($item) use ($agents, $types) {
            $agent = $agents->get($item->agent_id);
            $type = $types->get($item->transaction_type_id);
            return [
                'agent_id' => $item->agent_id,
                'agent_name' => $agent ? $agent->full_name : 'Unknown',
                'transaction_type_id' => $item->transaction_type_id,
                'transaction_type_name' => $type ? $type->name : 'Unknown',
                'month' => $item->month,
                'year' => $item->year,
                'total_commission' => (float) $item->total_commission,
                'total_cost' => (float) $item->total_cost,
                'total_business_share' => (float) $item->total_business_share,
                'transaction_count' => (int) $item->transaction_count,
            ];
        });
    }

    /**
     * Get transaction cost summary.
     */
    public function getCostSummary($filters)
    {
        $query = Transaction::where('business_unit_id', $filters['business_unit_id'])
            ->selectRaw('
                transaction_type_id,
                MONTH(transaction_date) as month,
                YEAR(transaction_date) as year,
                SUM(transaction_cost) as total_cost,
                SUM(commission) as total_commission,
                SUM(business_share) as total_business_share,
                SUM(transaction_amount) as total_amount,
                COUNT(*) as transaction_count
            ')
            ->groupBy('transaction_type_id', 'month', 'year');

        if (!empty($filters['start_date'])) {
            $query->where('transaction_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('transaction_date', '<=', $filters['end_date'] . ' 23:59:59');
        }
        if (!empty($filters['transaction_type_id'])) {
            $query->where('transaction_type_id', $filters['transaction_type_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $results = $query->get();

        // Load transaction type relationships
        $typeIds = $results->pluck('transaction_type_id')->unique();
        $types = TransactionType::whereIn('id', $typeIds)->get()->keyBy('id');

        return $results->map(function ($item) use ($types) {
            $type = $types->get($item->transaction_type_id);
            return [
                'transaction_type_id' => $item->transaction_type_id,
                'transaction_type_name' => $type ? $type->name : 'Unknown',
                'transaction_type_code' => $type ? $type->code : 'N/A',
                'month' => $item->month,
                'year' => $item->year,
                'total_cost' => (float) $item->total_cost,
                'total_commission' => (float) $item->total_commission,
                'total_business_share' => (float) $item->total_business_share,
                'total_amount' => (float) $item->total_amount,
                'transaction_count' => (int) $item->transaction_count,
            ];
        });
    }

    /**
     * Generate CSV file from data.
     */
    public function generateCsvFile($data, $headers, $filename)
    {
        $directory = 'reports';
        Storage::makeDirectory($directory);

        $filePath = $directory . '/' . $filename;
        $fullPath = storage_path('app/' . $filePath);

        $file = fopen($fullPath, 'w');

        // Write BOM for UTF-8 (helps with Excel)
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

        // Write headers
        fputcsv($file, $headers);

        // Write data rows
        foreach ($data as $row) {
            // If row is already an array with matching keys, use it directly
            if (is_array($row)) {
                $csvRow = [];
                foreach ($headers as $header) {
                    $csvRow[] = $row[$header] ?? '';
                }
                fputcsv($file, $csvRow);
            } else {
                // If row is an object, convert to array
                $rowArray = is_object($row) ? (array) $row : $row;
                $csvRow = [];
                foreach ($headers as $header) {
                    $csvRow[] = $rowArray[$header] ?? '';
                }
                fputcsv($file, $csvRow);
            }
        }

        fclose($file);

        return $filePath;
    }

    /**
     * Store report file.
     */
    public function storeReportFile($filePath, $report)
    {
        // File is already stored, just return the path
        return $filePath;
    }
}

