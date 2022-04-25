<?php

//NOTE: SAME FOR THE CONNECTION NAME FOR THE CLIENT AND CONFIGURE
return [
    'generate_id'=>[
        "default" => [
            'game_trans_id'=> 100000000000000000,
            'game_transaction_ext' => 100000000000000000,
        ],
        "server1-askmebet" => [
            'game_trans_id'=> 200000000000000000,
            'game_transaction_ext' => 200000000000000000,
        ],
        "server2-askmebet" =>[
            'game_trans_id'=> 100000000000000000,
            'game_transaction_ext' => 100000000000000000,
        ],
        "server3" =>[
            'game_trans_id'=> 100000000000000000,
            'game_transaction_ext' => 100000000000000000,
        ],
        "mysql" => [
            'game_trans_id'=> 100,
            'game_transaction_ext' => 100,
        ],
        "server_TW" => [
            'game_trans_id'=> 100000000000000000,
            'game_transaction_ext' => 100000000000000000,
        ],
    ]
];

?>
