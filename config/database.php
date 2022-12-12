<?php

return [

   'default' => env('DB_CONNECTION', 'mysql'),
   'redis' => [
        'client' => 'predis',
        'cluster' => false,
        'default' => [
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port'     => env('REDIS_PORT', 6379),
            'database' => 0,
        ],
    ],
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
            //     // PDO::ATTR_EMULATE_PREPARES => true
            //     \PDO::ATTR_PERSISTENT => true
            // ],
         ],
         'default-read' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST_READ', env('DB_HOST')),
            'port'      => env('DB_PORT_READ', env('DB_PORT')),
            'database'  => env('DB_DATABASE_READ', env('DB_DATABASE')),
            'username'  => env('DB_USERNAME_READ', env('DB_USERNAME')),
            'password'  => env('DB_PASSWORD_READ', env('DB_PASSWORD')),
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
            // 'options'   => [
            //     // PDO::ATTR_EMULATE_PREPARES => true
            //     \PDO::ATTR_PERSISTENT => true
            // ],
         ],
         'server2' => [
            'driver'    => 'mysql',
            'host'      => env('DB_SERVER2_HOST', env('DB_HOST')),
            'port'      => env('DB_SERVER2_PORT', env('DB_PORT')),
            'database'  => env('DB_SERVER2_DATABASE', env('DB_DATABASE')),
            'username'  => env('DB_SERVER2_USERNAME', env('DB_USERNAME')),
            'password'  => env('DB_SERVER2_PASSWORD', env('DB_PASSWORD')),
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
            // 'options'   => [
            //     // PDO::ATTR_EMULATE_PREPARES => true
            //     \PDO::ATTR_PERSISTENT => true
            // ],
        ],
        'server2-read' => [
            'driver'    => 'mysql',
            'host'      => env('DB_SERVER2_HOST_READ', env('DB_HOST')),
            'port'      => env('DB_SERVER2_PORT_READ', env('DB_PORT')),
            'database'  => env('DB_SERVER2_DATABASE_READ', env('DB_DATABASE')),
            'username'  => env('DB_SERVER2_USERNAME_READ', env('DB_USERNAME')),
            'password'  => env('DB_SERVER2_PASSWORD_READ', env('DB_PASSWORD')),
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
            // 'options'   => [
            //     // PDO::ATTR_EMULATE_PREPARES => true
            //     \PDO::ATTR_PERSISTENT => true
            // ],
        ],

        'server3' => [
            'driver'    => 'mysql',
            'host'      => env('DB_SERVER3_HOST', env('DB_HOST')),
            'port'      => env('DB_SERVER3_PORT', env('DB_PORT')),
            'database'  => env('DB_SERVER3_DATABASE', env('DB_DATABASE')),
            'username'  => env('DB_SERVER3_USERNAME', env('DB_USERNAME')),
            'password'  => env('DB_SERVER3_PASSWORD', env('DB_PASSWORD')),
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
            // 'options'   => [
            //     // PDO::ATTR_EMULATE_PREPARES => true
            //     \PDO::ATTR_PERSISTENT => true
            // ],
        ],
        'server3-read' => [
            'driver'    => 'mysql',
            'host'      => env('DB_SERVER3_HOST_READ', env('DB_HOST')),
            'port'      => env('DB_SERVER3_PORT_READ', env('DB_PORT')),
            'database'  => env('DB_SERVER3_DATABASE_READ', env('DB_DATABASE')),
            'username'  => env('DB_SERVER3_USERNAME_READ', env('DB_USERNAME')),
            'password'  => env('DB_SERVER3_PASSWORD_READ', env('DB_PASSWORD')),
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
            // 'options'   => [
            //     // PDO::ATTR_EMULATE_PREPARES => true
            //     \PDO::ATTR_PERSISTENT => true
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
            //     // PDO::ATTR_EMULATE_PREPARES => true
            //     \PDO::ATTR_PERSISTENT => true
            // ],
        ],
    ],
];
