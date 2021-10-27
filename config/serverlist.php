<?php

//NOTE: SAME FOR THE CONNECTION NAME FOR THE CLIENT AND CONFIGURE
return [
    'server_list'=>[
        "default" => [
            'connection_name'=>'mysql',
            'db_list'=> [
                'api_test',//game_trasnaction_ext
                'api_test' // game_transaction
            ]
        ],
        "server1" => [
            'connection_name'=>'server1',
            'db_list'=> [
                'api_test', //game_trasnaction_ext
                'api_test' // game_transaction
            ]
        ],
        "server2" =>[
            'connection_name'=>'server2',
            'db_list'=> [
                'api_test_Extension',  //game_trasnaction_ext
                'api_test' // game_transaction
            ]
        ],
        "server3" =>[
            'connection_name'=>'server3',
            'db_list'=> [
                'api_test_Extension', //game_trasnaction_ext
                'api_test' // game_transaction
            ]
        ],
        "mysql" => [
            'connection_name'=>'mysql',
            'db_list'=> [
                'api_test',//game_trasnaction_ext
                'api_test' // game_transaction
            ]
        ],

        "server_TW" => [
            'connection_name'=>'mysql',
            'db_list'=> [
                'api_test',//game_trasnaction_ext
                'api_test' // game_transaction
            ]
        ],
    ]
];

?>
