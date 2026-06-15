<?php

namespace App\Services;

use App\Models\CnssSequence;
use Illuminate\Support\Facades\DB;

class CnssIdentifierService
{
    /**
     * @return array{voluntary_insured_number: string, cnss_registration_affiliation_number: string, worker_number: string|null}
     */
    public function allocateNumbers(int $businessUnitId): array
    {
        $voluntaryPrefix = (string) config('cnss.voluntary_number_prefix', 'VINS');
        $affiliationPrefix = (string) config('cnss.affiliation_number_prefix', 'AFF');
        $width = max(4, (int) config('cnss.number_sequence_width', 8));
        $mirror = (bool) config('cnss.worker_number_mirrors_voluntary', true);

        $allocate = function () use ($businessUnitId, $voluntaryPrefix, $affiliationPrefix, $width, $mirror) {
            CnssSequence::query()->firstOrCreate(
                ['business_unit_id' => $businessUnitId],
                ['next_voluntary_seq' => 0, 'next_affiliation_seq' => 0]
            );

            $seq = CnssSequence::query()
                ->where('business_unit_id', $businessUnitId)
                ->lockForUpdate()
                ->firstOrFail();

            $seq->next_voluntary_seq = ((int) $seq->next_voluntary_seq) + 1;
            $seq->next_affiliation_seq = ((int) $seq->next_affiliation_seq) + 1;
            $seq->save();

            $voluntary = $voluntaryPrefix.str_pad((string) $seq->next_voluntary_seq, $width, '0', STR_PAD_LEFT);
            $affiliation = $affiliationPrefix.str_pad((string) $seq->next_affiliation_seq, $width, '0', STR_PAD_LEFT);
            $worker = $mirror ? $voluntary : null;

            return [
                'voluntary_insured_number' => $voluntary,
                'cnss_registration_affiliation_number' => $affiliation,
                'worker_number' => $worker,
            ];
        };

        return DB::transactionLevel() > 0 ? $allocate() : DB::transaction($allocate);
    }
}
