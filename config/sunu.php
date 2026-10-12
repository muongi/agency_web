<?php

return [
    /*
    | Core banking (accounts, transactions, transaction types/costs/commissions,
    | core banking systems, banking reports). Off for the SUNU deployment: menus hidden
    | and web routes return 404. API routes are not affected.
    */
    'core_banking' => (bool) env('CORE_BANKING_ENABLED', false),

    /*
    | Permission groups hidden on the role pages while core_banking is off
    | (their slugs stay in the DB and on roles, so turning it back on restores them).
    */
    'banking_permission_groups' => ['Accounts', 'Transactions', 'Banking Configuration', 'Banking Reports'],

    /** CNSS permission group on the role pages (CNSS is not used by SUNU). */
    'show_cnss' => (bool) env('SUNU_SHOW_CNSS', false),

    /*
    | Test only: default 4-digit collection PIN given to agents without one
    | (php artisan sunu:agents-default-pin) and offered on the web PIN reset.
    | Leave empty in production.
    */
    'default_agent_pin' => env('SUNU_DEFAULT_AGENT_PIN'),

    /** Wrong collection PINs before the PIN is locked. */
    'pin_max_attempts' => 5,
];
