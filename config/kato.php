<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default KATO (agency API) base URL
    |--------------------------------------------------------------------------
    |
    | This should be the agency API root only (no trailing /company/register
    | or /onboarding/register). Example:
    | https://api.saccoworks.co.ke/api/v2/agency
    |
    */
    'default_base_url' => env('KATO_DEFAULT_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Default currency and country for KATO company onboarding
    |--------------------------------------------------------------------------
    */
    'default_currency' => env('KATO_DEFAULT_CURRENCY', env('DEFAULT_CURRENCY', 'CFA')),
    'default_country_code' => env('KATO_DEFAULT_COUNTRY', 'BF'),
];

