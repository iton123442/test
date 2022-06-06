<?php

return [
    'data_type' => [
        'transaction' => [
        	'string' => [1,6,32]  // Client Operator Id
        ]
    ],
    'auto_refund' => [
    	'exclude' => [
    		'operator_id' => [17] // Client Operator Id
    	]
    ]
];