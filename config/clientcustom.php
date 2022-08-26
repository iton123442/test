<?php

return [
    'data_type' => [
        'transaction' => [
        	'string' => [1,13,6,32]  // Client Operator Id
        ]
    ],
    'auto_refund' => [
    	'exclude' => [
    		'operator_id' => [17], // Client Operator Id
            'provider_names' => ["Wazdan","JustPlay", "IDNPoker", "OnlyPlay", "QuickSpin Direct","BGaming", "SimplePlay", "digitain", "bolegaming", "Iconic Gaming", "FunTa Gaming"]
    	]
    ]
];