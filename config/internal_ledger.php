<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Who pays transaction_cost on internal ledger postings
    |--------------------------------------------------------------------------
    |
    | customer: fees are debited from the client account (withdrawal: together
    | with principal; deposit: after crediting principal). Agent float is only
    | debited/credited for the principal amount.
    |
    | agent: reserved for future use (agent float would absorb fees).
    |
    */
    'fee_payer' => env('INTERNAL_LEDGER_FEE_PAYER', 'customer'),

];
