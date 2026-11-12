<?php

namespace App\Support;

use App\Models\BusinessUnit;

/**
 * SUNU-only mode (config sunu.single_business): the deployment has one business, SUNU.
 * main() = SUNU_BUSINESS_UNIT_ID when set, else the first active business unit.
 */
class SunuBusiness
{
    protected static ?BusinessUnit $main = null;

    protected static bool $resolved = false;

    public static function enabled(): bool
    {
        return (bool) config('sunu.single_business');
    }

    public static function main(): ?BusinessUnit
    {
        if (! static::$resolved) {
            $id = config('sunu.business_unit_id');
            static::$main = $id
                ? BusinessUnit::find($id)
                : BusinessUnit::where('is_active', true)->orderBy('id')->first();
            static::$resolved = true;
        }

        return static::$main;
    }

    /** Single mode is on and SUNU exists (a fresh install may still create its first business). */
    public static function locked(): bool
    {
        return static::enabled() && static::main() !== null;
    }

    public static function forget(): void
    {
        static::$main = null;
        static::$resolved = false;
    }
}
