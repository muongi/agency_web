<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\BusinessUnit;

class BusinessDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'business.access']);
    }

    public function dashboard()
    {
        $businessUnit = auth()->user()->currentBusinessUnit;
        
        if (!$businessUnit) {
            return view('business.dashboard', [
                'businessUnit' => null,
                'transactionStats' => null,
                'topAgents' => collect(),
            ]);
        }

        // Eager load relationships
        $businessUnit->load(['users', 'roles']);

        // Get current month date range
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // Get completed transactions for current month
        $transactions = Transaction::where('business_unit_id', $businessUnit->id)
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->get();

        // Calculate statistics
        $transactionStats = [
            'total_transactions' => $transactions->count(),
            'total_amount' => $transactions->sum('transaction_amount'),
            'total_commissions' => $transactions->sum('commission'),
            'total_business_share' => $transactions->sum('business_share'),
        ];

        // Top 10 agents by commission
        $topAgentsQuery = Transaction::where('business_unit_id', $businessUnit->id)
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->whereNotNull('agent_id')
            ->selectRaw('agent_id, SUM(commission) as total_commission, COUNT(*) as transaction_count')
            ->groupBy('agent_id')
            ->orderBy('total_commission', 'desc')
            ->limit(10);

        $topAgents = collect();
        $topAgentsData = $topAgentsQuery->get();
        
        foreach ($topAgentsData as $item) {
            $agent = \App\Models\Agent::find($item->agent_id);
            $topAgents->push([
                'agent' => $agent,
                'total_commission' => (float) $item->total_commission,
                'transaction_count' => (int) $item->transaction_count,
            ]);
        }

        // Get monthly transaction totals for current year
        $currentYear = now()->year;
        $startOfYear = now()->startOfYear();
        $endOfYear = now()->endOfYear();

        $monthlyTransactions = Transaction::where('business_unit_id', $businessUnit->id)
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$startOfYear, $endOfYear])
            ->selectRaw('
                MONTH(transaction_date) as month,
                YEAR(transaction_date) as year,
                SUM(transaction_amount) as total_amount,
                COUNT(*) as transaction_count
            ')
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get();

        // Create array with all 12 months, filling in zeros for months with no transactions
        $monthlyData = [];
        $monthNames = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
        ];

        for ($month = 1; $month <= 12; $month++) {
            $monthTransaction = $monthlyTransactions->firstWhere('month', $month);
            $monthlyData[] = [
                'month' => $month,
                'month_name' => $monthNames[$month] . '-' . $currentYear,
                'total_amount' => $monthTransaction ? (float) $monthTransaction->total_amount : 0,
                'transaction_count' => $monthTransaction ? (int) $monthTransaction->transaction_count : 0,
            ];
        }

        return view('business.dashboard', compact('businessUnit', 'transactionStats', 'topAgents', 'monthlyData', 'currentYear'));
    }
}

