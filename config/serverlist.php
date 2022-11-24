<?php

//NOTE: SAME FOR THE CONNECTION NAME FOR THE CLIENT AND CONFIGURE
return [
    'server_list'=>[
        "default" => [
            'connection_name'=>'mysql',
            'db_list'=> [
                'api_test',//game_trasnaction_ext
                'api_test', // game_transaction
                'api_test' // game_transaction_logs
            ]
        ],
        "server1" => [
            'connection_name'=>'server1',
            'db_list'=> [
                'api_test', //game_trasnaction_ext
                'api_test', // game_transaction
                'api_test' // game_transaction_logs
            ]
        ],
        "server2" =>[
            'connection_name'=>'server2',
            'db_list'=> [
                'api_test_Extension',  //game_trasnaction_ext
                'api_test', // game_transaction
                'api_test' // game_transaction_logs
            ]
        ],
        "server3" =>[
            'connection_name'=>'server3',
            'db_list'=> [
                'api_test_Extension', //game_trasnaction_ext
                'api_test', // game_transaction
                'api_test' // game_transaction_logs
            ]
        ],
        "mysql" => [
            'connection_name'=>'mysql',
            'db_list'=> [
                'api_test',//game_trasnaction_ext
                'api_test', // game_transaction
                'api_test' // game_transaction_logs
            ]
        ],

        "server_TW" => [
            'connection_name'=>'mysql',
            'db_list'=> [
                'wt_mw_db_staging',//game_trasnaction_ext
                'wt_mw_db_staging' // game_transaction
            ]
        ],
    ]
];

?>
