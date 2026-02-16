<?php

namespace App\Services;

use App\Models\TransactionType;
use App\Models\TransactionCostConfiguration;
use App\Models\CommissionConfiguration;

class TransactionCalculationService
{
    /**
     * Calculate transaction cost from transaction amount.
     *
     * @param int $transactionTypeId
     * @param float $transactionAmount
     * @param int|null $businessUnitId
     * @return float
     */
    public function calculateTransactionCost($transactionTypeId, $transactionAmount, $businessUnitId = null)
    {
        $costConfig = TransactionCostConfiguration::where('transaction_type_id', $transactionTypeId)
            ->where('is_active', true);
        
        if ($businessUnitId) {
            $costConfig->where('business_unit_id', $businessUnitId);
        }
        
        $costConfig = $costConfig->first();
        
        if (!$costConfig) {
            return 0;
        }
        
        return $costConfig->calculateCost($transactionAmount);
    }

    /**
     * Calculate commission from transaction cost.
     *
     * @param int $transactionTypeId
     * @param float $transactionCost
     * @param int|null $businessUnitId
     * @return float
     */
    public function calculateCommission($transactionTypeId, $transactionCost, $businessUnitId = null)
    {
        $commissionConfig = CommissionConfiguration::where('transaction_type_id', $transactionTypeId)
            ->where('is_active', true);
        
        if ($businessUnitId) {
            $commissionConfig->where('business_unit_id', $businessUnitId);
        }
        
        $commissionConfig = $commissionConfig->first();
        
        if (!$commissionConfig) {
            return 0;
        }
        
        return $commissionConfig->calculateCommission($transactionCost);
    }

    /**
     * Calculate full flow: transaction amount → cost → commission → split.
     *
     * @param int $transactionTypeId
     * @param float $transactionAmount
     * @param int|null $businessUnitId
     * @return array Returns ['transaction_amount', 'transaction_cost', 'commission', 'business_share']
     */
    public function calculateSplit($transactionTypeId, $transactionAmount, $businessUnitId = null)
    {
        // Step 1: Calculate transaction cost
        $transactionCost = $this->calculateTransactionCost($transactionTypeId, $transactionAmount, $businessUnitId);
        
        // Step 2: Calculate commission from cost
        $commission = $this->calculateCommission($transactionTypeId, $transactionCost, $businessUnitId);
        
        // Step 3: Calculate business share (cost - commission)
        $businessShare = $transactionCost - $commission;
        
        return [
            'transaction_amount' => $transactionAmount,
            'transaction_cost' => $transactionCost,
            'commission' => $commission,
            'business_share' => $businessShare,
        ];
    }

    /**
     * Get transaction cost configuration for a transaction type.
     *
     * @param int $transactionTypeId
     * @param int|null $businessUnitId
     * @return TransactionCostConfiguration|null
     */
    public function getTransactionCostConfiguration($transactionTypeId, $businessUnitId = null)
    {
        $query = TransactionCostConfiguration::where('transaction_type_id', $transactionTypeId)
            ->where('is_active', true);
        
        if ($businessUnitId) {
            $query->where('business_unit_id', $businessUnitId);
        }
        
        return $query->first();
    }

    /**
     * Get commission configuration for a transaction type.
     *
     * @param int $transactionTypeId
     * @param int|null $businessUnitId
     * @return CommissionConfiguration|null
     */
    public function getCommissionConfiguration($transactionTypeId, $businessUnitId = null)
    {
        $query = CommissionConfiguration::where('transaction_type_id', $transactionTypeId)
            ->where('is_active', true);
        
        if ($businessUnitId) {
            $query->where('business_unit_id', $businessUnitId);
        }
        
        return $query->first();
    }
}

