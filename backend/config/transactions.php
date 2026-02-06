<?php

return [
    'default_currency' => env('TRANSACTIONS_CURRENCY', 'EUR'),
    'contract' => [
        'template' => env('TRANSACTIONS_CONTRACT_TEMPLATE', 'standard_v1'),
        'terms' => env(
            'TRANSACTIONS_CONTRACT_TERMS',
            'The tenant agrees to keep the property in good condition and comply with house rules. The landlord agrees to provide access on the move-in date. This agreement is governed by local law.'
        ),
    ],
];
