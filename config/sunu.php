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

    /*
    | SUNU-only mode: one business (SUNU). A super admin lands inside SUNU (no separate
    | super-admin area, no "Switch to Superadmin"), creating or switching business units is
    | refused, and super-admin tools sit in an "Administration" sidebar group. Set
    | SUNU_SINGLE_BUSINESS=false for a multi-business deployment.
    | business_unit_id: SUNU's id; empty = the first active business unit.
    */
    'single_business' => (bool) env('SUNU_SINGLE_BUSINESS', true),
    'business_unit_id' => env('SUNU_BUSINESS_UNIT_ID'),

    /** Wrong collection PINs before the PIN is locked. */
    'pin_max_attempts' => 5,
];
