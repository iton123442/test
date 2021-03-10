<?php

return [

   'default' => env('DB_CONNECTION', 'mysql'),

   'connections' => [
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST'),
            'port'      => env('DB_PORT'),
            'database'  => env('DB_DATABASE'),
            'username'  => env('DB_USERNAME'),
            'password'  => env('DB_PASSWORD'),
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
         ],

        'savelog' => [
            'driver'    => 'mysql',
            'host'      => env('DB_LOG_HOST', env('DB_HOST')),
            'port'      => env('DB_LOG_PORT', env('DB_PORT')),
            'database'  => env('DB_LOG_DATABASE', env('DB_DATABASE')),
            'username'  => env('DB_LOG_USERNAME', env('DB_USERNAME')),
            'password'  => env('DB_LOG_PASSWORD', env('DB_PASSWORD')),
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ],
    ],
];
