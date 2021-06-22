<?php

return array(
    'dsn' => env('SENTRY_LARAVEL_DSN'),

    // capture release as git sha
    // 'release' => trim(exec('git log --pretty="%h" -n1 HEAD')),
    'breadcrumbs' => [
        // Capture Laravel logs. Defaults to `true`.
        'logs' => true,
        // Capture queue job information. Defaults to `true`.
        'queue_info' => true,
        // Capture SQL queries. Defaults to `true`.
        'sql_queries' => true,
        // Capture bindings (parameters) on SQL queries. Defaults to `false`.
        'sql_bindings' => false,
    ]
);
