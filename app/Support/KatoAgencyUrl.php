<?php

namespace App\Support;

use Illuminate\Support\Str;

class KatoAgencyUrl
{
    private const LEGACY_SUFFIXES = [
        '/company/register',
        '/onboarding/register',
        '/agency/onboarding/register',
    ];

    /**
     * Normalize a KATO base URL to the agency API root.
     *
     * Examples:
     * - .../api/v2/company/register -> .../api/v2/agency
     * - .../api/v2 -> .../api/v2/agency
     * - .../api/v2/agency -> unchanged
     */
    public static function resolveAgencyBase(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $base = rtrim(trim($url), '/');

        foreach (self::LEGACY_SUFFIXES as $suffix) {
            if (Str::endsWith($base, $suffix)) {
                $base = rtrim(substr($base, 0, -strlen($suffix)), '/');
                break;
            }
        }

        if (preg_match('#/api/v2$#', $base) === 1) {
            $base .= '/agency';
        }

        return $base;
    }
}
