<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Number prefixes (per-environment; no regulator format implied)
    |--------------------------------------------------------------------------
    */
    'voluntary_number_prefix' => env('CNSS_VOLUNTARY_PREFIX', 'VINS'),
    'affiliation_number_prefix' => env('CNSS_AFF_PREFIX', 'AFF'),
    'number_sequence_width' => (int) env('CNSS_NUMBER_WIDTH', 8),

    /*
    |--------------------------------------------------------------------------
    | When true, worker_number is set equal to voluntary_insured_number on enroll
    |--------------------------------------------------------------------------
    */
    'worker_number_mirrors_voluntary' => env('CNSS_WORKER_MIRROR_VOLUNTARY', true),

    /*
    |--------------------------------------------------------------------------
    | New enrollments default to this status (approved = immediate numbers for app)
    |--------------------------------------------------------------------------
    */
    'default_enrollment_status' => env('CNSS_DEFAULT_ENROLLMENT_STATUS', 'approved'),

    /*
    |--------------------------------------------------------------------------
    | Transaction type code for ledger-mode CNSS contributions (assign per BU in DB)
    |--------------------------------------------------------------------------
    */
    'ledger_transaction_type_code' => env('CNSS_LEDGER_TRANSACTION_TYPE_CODE', 'CNSS001'),

    /*
    |--------------------------------------------------------------------------
    | Optional default account number for ledger postings when request omits it
    |--------------------------------------------------------------------------
    */
    'default_ledger_account_number' => env('CNSS_DEFAULT_LEDGER_ACCOUNT_NUMBER'),
];
