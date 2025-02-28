<?php

declare(strict_types=1);

return [
    'store' => env('LOCK_STORE', 'semaphore'),
    'pgsql_store' => [
        'connection' => env('LOCK_PGSQL_STORE_CONNECTION'),
    ],
    'prefix' => env('LOCK_PREFIX'),

    'old_store' => env('OLD_LOCK_STORE'),
    'old_pgsql_store' => [
        'connection' => env('OLD_LOCK_PGSQL_STORE_CONNECTION'),
    ],
    'old_prefix' => env('OLD_LOCK_PREFIX'),
];
