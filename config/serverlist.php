<?php

//NOTE: SAME FOR THE CONNECTION NAME FOR THE CLIENT AND CONFIGURE
return [
    'server_list'=>[
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
        "server2-api_test2" =>[
            'connection_name'=>'server2',
            'db_list'=> [
                'api_test2_Extension',  //game_trasnaction_ext
                'api_test2' // game_transaction
            ]
        ],
        "server3-api_test2" =>[
            'connection_name'=>'server3',
            'db_list'=> [
                'api_test2_Extension', //game_trasnaction_ext
                'api_test2' // game_transaction
            ]
        ],
        "default" => [
            'connection_name'=>'mysql',
            'db_list'=> [
                'wt_mw_db_production',//game_trasnaction_ext
                'wt_mw_db_production' // game_transaction
            ]
        ],
    ]
];

// return [
//     'server_list'=>[
//         "server1" => [
//             'connection_name'=>'server1',
//             'db_list'=> [
//                 'marv_api-test', //game_trasnaction_ext
//                 'marv_api-test' // game_transaction
//             ]
//         ],
//         "server2" =>[
//             'connection_name'=>'server2',
//             'db_list'=> [
//                 'wt_game_transaction_ext',  //game_trasnaction_ext
//                 'wt_game_transaction' // game_transaction
//             ]
//         ],
//         "server3" =>[
//             'connection_name'=>'server3',
//             'db_list'=> [
//                 'svr3_extension', //game_trasnaction_ext
//                 'marv_api-test' // game_transaction
//             ]
//         ],
//         "default" => [
//             'connection_name'=>'mysql',
//             'db_list'=> [
//                 'marv_api-test',
//                 'marv_api-test'
//             ]
//         ],
//     ]
// ];

?>