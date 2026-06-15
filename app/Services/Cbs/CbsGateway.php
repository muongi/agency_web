<?php

namespace App\Services\Cbs;

use App\Models\BusinessUnit;
use App\Models\BusinessUnitCbsTransactionMapping;
use App\Models\TransactionType;

class CbsGateway
{
    public function __construct(
        private ConfigurableCbsService $configurableCbsService
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array{success: bool, data?: array<string, mixed>, error?: string, full_response?: mixed, http_status?: int}|null
     */
    public function executeIfMapped(
        BusinessUnit $businessUnit,
        TransactionType $transactionType,
        array $context
    ): ?array {
        if (! $businessUnit->hasConfigurableCbs()) {
            return null;
        }

        $mapping = BusinessUnitCbsTransactionMapping::query()
            ->where('business_unit_id', $businessUnit->id)
            ->where('transaction_type_id', $transactionType->id)
            ->where('is_active', true)
            ->first();

        if (! $mapping) {
            return null;
        }

        return $this->configurableCbsService->execute(
            $businessUnit,
            $transactionType,
            $mapping,
            $context
        );
    }
}
