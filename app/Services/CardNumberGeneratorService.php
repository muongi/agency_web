<?php

namespace App\Services;

use App\Models\CardSettings;
use Illuminate\Support\Facades\DB;

class CardNumberGeneratorService
{
    /**
     * Generate a unique card number for the given business unit.
     * Uses atomic counter table to ensure uniqueness.
     *
     * @param int $businessUnitId
     * @return string
     * @throws \Exception
     */
    public static function generate($businessUnitId)
    {
        // Get or create card settings for the business unit
        $settings = CardSettings::getOrCreateForBusinessUnit($businessUnitId);

        if (empty($settings->card_prefix)) {
            throw new \Exception('Card prefix is not configured for this business unit.');
        }

        $prefix = $settings->card_prefix;
        $incrementLength = $settings->auto_increment_length ?? 6;

        // Use database transaction with row-level locking for atomic increment
        return DB::transaction(function () use ($businessUnitId, $prefix, $incrementLength) {
            // Lock the sequence row for this business unit
            $sequence = DB::table('card_sequences')
                ->where('business_unit_id', $businessUnitId)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                // Create sequence if it doesn't exist
                DB::table('card_sequences')->insert([
                    'business_unit_id' => $businessUnitId,
                    'last_number' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $nextNumber = 1;
            } else {
                $nextNumber = $sequence->last_number + 1;
            }

            // Check if we've reached the maximum for this increment length
            $maxNumber = pow(10, $incrementLength) - 1;
            if ($nextNumber > $maxNumber) {
                throw new \Exception("Card number sequence exhausted. Maximum number ({$maxNumber}) reached for business unit.");
            }

            // Update the sequence
            DB::table('card_sequences')
                ->where('business_unit_id', $businessUnitId)
                ->update([
                    'last_number' => $nextNumber,
                    'updated_at' => now(),
                ]);

            // Generate card number: prefix + padded increment
            $paddedNumber = str_pad($nextNumber, $incrementLength, '0', STR_PAD_LEFT);
            $cardNumber = $prefix . $paddedNumber;

            // Double-check uniqueness (should never happen with proper locking, but safety check)
            $exists = DB::table('cards')
                ->where('card_number', $cardNumber)
                ->exists();

            if ($exists) {
                // If somehow a duplicate exists, try next number
                return self::generate($businessUnitId);
            }

            return $cardNumber;
        });
    }

    /**
     * Get the next card number without incrementing (for preview).
     *
     * @param int $businessUnitId
     * @return string
     */
    public static function previewNext($businessUnitId)
    {
        $settings = CardSettings::getOrCreateForBusinessUnit($businessUnitId);
        $prefix = $settings->card_prefix ?? '0000';
        $incrementLength = $settings->auto_increment_length ?? 6;

        $sequence = DB::table('card_sequences')
            ->where('business_unit_id', $businessUnitId)
            ->first();

        $nextNumber = $sequence ? ($sequence->last_number + 1) : 1;
        $paddedNumber = str_pad($nextNumber, $incrementLength, '0', STR_PAD_LEFT);

        return $prefix . $paddedNumber;
    }
}

