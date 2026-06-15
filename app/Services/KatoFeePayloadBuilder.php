<?php

namespace App\Services;

class KatoFeePayloadBuilder
{
    public const OPERATION_DEPOSIT = 'deposit';

    public const OPERATION_WITHDRAW = 'withdraw';

    public const OPERATION_ENQUIRY = 'enquiry';

    /**
     * @param  array{transaction_cost: float, commission: float, business_share: float}  $split
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    public function build(string $operation, array $split, array $base): array
    {
        $payload = $base;

        $transactionCost = (float) ($split['transaction_cost'] ?? 0);
        $commission = (float) ($split['commission'] ?? 0);
        $businessShare = (float) ($split['business_share'] ?? 0);

        if ($operation === self::OPERATION_DEPOSIT) {
            if ($commission > 0) {
                $payload['agentCommission'] = round($commission, 2);
            }

            if ($transactionCost > 0) {
                $payload['totalFee'] = round($transactionCost, 2);
                $payload['bankRevenue'] = round($businessShare, 2);
            }

            return $payload;
        }

        if ($operation === self::OPERATION_WITHDRAW) {
            if ($transactionCost > 0) {
                $payload['totalFee'] = round($transactionCost, 2);
            }

            if ($commission > 0) {
                $payload['agentCommission'] = round($commission, 2);
            }

            if ($transactionCost > 0) {
                $payload['saccoIncome'] = round($businessShare, 2);
            }

            return $payload;
        }

        if ($operation === self::OPERATION_ENQUIRY) {
            if ($transactionCost > 0) {
                $payload['totalFee'] = round($transactionCost, 2);
            }

            if ($commission > 0) {
                $payload['agentCommission'] = round($commission, 2);
            }

            if ($transactionCost > 0) {
                $payload['bankRevenue'] = round($businessShare, 2);
            } elseif ($commission > 0) {
                $payload['bankRevenue'] = round(-$commission, 2);
            }

            return $payload;
        }

        return $payload;
    }
}
