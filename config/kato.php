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

    /*
    |--------------------------------------------------------------------------
    | KATO member registration field limits
    |--------------------------------------------------------------------------
    |
    | KATO CBS SQL columns are shorter than our local fields; exceeding them
    | causes "String or binary data would be truncated" (HTTP 500).
    |
    */
    'member_town_max_length' => (int) env('KATO_MEMBER_TOWN_MAX_LENGTH', 50),

    /*
    |--------------------------------------------------------------------------
    | Default KATO JWT lifetime when API omits expiresIn (seconds)
    |--------------------------------------------------------------------------
    */
    'default_token_ttl' => (int) env('KATO_DEFAULT_TOKEN_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Proactive scheduled refresh window (minutes before expiry)
    |--------------------------------------------------------------------------
    */
    'token_refresh_lead_minutes' => (int) env('KATO_TOKEN_REFRESH_LEAD_MINUTES', 15),
];

