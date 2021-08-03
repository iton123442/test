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
            // 'options'   => [
            //     PDO::ATTR_EMULATE_PREPARES => true
            // ],
         ],
         'server2' => [
            'driver'    => 'mysql',
            'host'      => env('DB_SERVER2_HOST', env('DB_SERVER3_HOST')),
            'port'      => env('DB_SERVER2_PORT', env('DB_SERVER3_PORT')),
            'database'  => env('DB_SERVER2_DATABASE', env('DB_SERVER3_DATABASE')),
            'username'  => env('DB_SERVER2_USERNAME', env('DB_SERVER3_USERNAME')),
            'password'  => env('DB_SERVER2_PASSWORD', env('DB_SERVER3_PASSWORD')),
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
            // 'options'   => [
            //     PDO::ATTR_EMULATE_PREPARES => true
            // ],
        ],

        'server3' => [
            'driver'    => 'mysql',
            'host'      => env('DB_SERVER3_HOST', env('DB_SERVER2_HOST')),
            'port'      => env('DB_SERVER3_PORT', env('DB_SERVER2_PORT')),
            'database'  => env('DB_SERVER3_DATABASE', env('DB_SERVER2_DATABASE')),
            'username'  => env('DB_SERVER3_USERNAME', env('DB_SERVER2_USERNAME')),
            'password'  => env('DB_SERVER3_PASSWORD', env('DB_SERVER2_PASSWORD')),
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
            // 'options'   => [
            //     PDO::ATTR_EMULATE_PREPARES => true
            // ],
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
            // 'options'   => [
            //     PDO::ATTR_EMULATE_PREPARES => true
            // ],
        ],
    ],
];
