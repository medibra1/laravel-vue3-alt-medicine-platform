<?php

return [
    /**
     * Account numbers written in the accounting export. Defaults follow
     * the French/OHADA-style chart; override per deployment with the
     * BILLING_ACCOUNT_* variables to match the accountant's own chart.
     */
    'accounts' => [
        'customer' => env('BILLING_ACCOUNT_CUSTOMER', '411000'),
        'revenue' => env('BILLING_ACCOUNT_REVENUE', '706000'),
        'bank' => env('BILLING_ACCOUNT_BANK', '512000'),
        'cash' => env('BILLING_ACCOUNT_CASH', '530000'),
        'expense' => env('BILLING_ACCOUNT_EXPENSE', '606000'),
    ],

    /** Journal codes of the accounting export. */
    'journals' => [
        'sales' => 'VT',
        'bank' => 'BQ',
        'cash' => 'CA',
        'purchases' => 'AC',
    ],
];
