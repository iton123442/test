<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ProviderHelper;
use App\Helpers\Helper;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use Carbon\Carbon;
use DB;

class SlotMillController extends Controller
{
    public $provider_db_id, $middleware_api, $prefix;
    
    public function __construct()
    {
    	$this->provider_db_id = config('providerlinks.slotmill.provider_db_id');
    	$this->middleware_api = config('providerlinks.oauth_mw_api.mwurl');
    	$this->prefix = "SLOTMILL_"; // for idom name
    }

    public function playerinfo(Request $request)
    {
    	$client_details = ProviderHelper::getClientDetails('token', $request['sessiontoken']);

    	if($client_details != null){
    		$response = [
	    		"code" => 0,
	    		"data" => [
	    			"playerId" => "TG_".$client_details->player_id,
	    			"nickName" => $client_details->display_name,
	    			"organization" => config('providerlinks.slotmill.brand'), // client belong
	    			"balance" => (string)$client_details->balance,
	    			"applicableBonus" => "0.0",
	    			"currency" => $client_details->default_currency,
	    			"homeCurrency" => $client_details->default_currency,
	    		]
	    	];
	    	Helper::saveLog('SLOTMILL playerinfo', $this->provider_db_id, json_encode($request->all()), $response);
    		return response($response,200)
				->header('Content-Type', 'application/json');
    	}else {
    		$response = [
    			"code" => 1000,
    			"msg" => "Session expired. Please log in again."
    		];

    		Helper::saveLog('SLOTMILL playerinfo error', $this->provider_db_id, json_encode($request->all()), $response);
    		return response($response,200)
				->header('Content-Type', 'application/json');
    	}
    	
    }

    public function wager(Request $request)
    {

    	$playersid = explode('_', $request['playerid']);
        $client_details = ProviderHelper::getClientDetails('player_id',$playersid[1]);

        // $balance = $client_details->balance - $request["amount"];
        // $response = [
        //     "code" => 0,
        //     "data" => [
        //         "playerId" => "TG_".$client_details->player_id,
        //         "nickName" => $client_details->display_name,
        //         "organization" => config('providerlinks.slotmill.brand'), // client belong
        //         "balance" => (string)$balance,
        //         "applicableBonus" => "0.0",
        //         "currency" => $client_details->default_currency,
        //         "homeCurrency" => $client_details->default_currency,
        //     ]
        // ];

        // return response($response,200)
        //         ->header('Content-Type', 'application/json');


        $game_code = $request["cat5"];
    	try{
			ProviderHelper::idenpotencyTable($this->prefix.'_'.$request["reference"]);
		}catch(\Exception $e){
			$response = [
	    		"code" => 0,
	    		"data" => [
	    			"playerId" => "TG_".$client_details->player_id,
	    			"nickName" => $client_details->display_name,
	    			"organization" => "$client_details->client_id", // client belong
	    			"balance" => (string)$client_details->balance,
	    			"applicableBonus" => "0.0",
	    			"currency" => $client_details->default_currency,
	    			"homeCurrency" => $client_details->default_currency,
	    		]
	    	];
			Helper::saveLog('SLOTMILL wager duplicate_transaction', $this->provider_db_id, json_encode($request->all()), $response);
			return response($response,200)
                ->header('Content-Type', 'application/json');
		}
        $game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $game_code);
        
        $pay_amount = 0;
        $bet_amount = $request["amount"];
        $income = 0;
        $win_type = 1;
        $method = 1;
        $win_or_lost = 5; // 0 lost,  5 processing
        $payout_reason = "Bet";
        $provider_trans_id = $request["reference"];
        $bet_id = $request["subreference"];
        //Create GameTransaction, GameExtension
       
        $game_trans_id  = ProviderHelper::createGameTransaction($client_details->token_id, $game_details->game_id, $bet_amount,  $pay_amount, $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id, $bet_id);
        
        $game_trans_ext_id = ProviderHelper::createGameTransExt($game_trans_id,$provider_trans_id, $bet_id, $bet_amount, $win_type, $request->all(), $data_response = null, $requesttosend = null, $client_response = null, $data_response = null);
        
         //requesttosend, and responsetoclient client side
        $type = "debit";
        $rollback = false;
        
        $general_details = ["aggregator" => [], "provider" => [], "client" => []];
        try {
            $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount,$game_details->game_code,$game_details->game_name,$game_trans_ext_id,$game_trans_id,$type,$rollback);
        } catch (\Exception $e) {
            $response = [
                "code" => 1,
                "msg" => "Technical problem",
            ];
            ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, 'FAILED', $response, 'FAILED', $e->getMessage(), 'FAILED', $general_details);
            ProviderHelper::updateGameTransactionStatus($game_trans_id, 2, 99);
            Helper::saveLog('SLOTMILL wager Retry exception fund', $this->provider_db_id, json_encode($request->all()), $response);
            return response($response,200)
            ->header('Content-Type', 'application/json');
        }

        if (isset($client_response->fundtransferresponse->status->code)) {

            switch ($client_response->fundtransferresponse->status->code) {
                case "200":
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    $response = [
                        "code" => 0,
                        "data" => [
                            "playerId" => "TG_".$client_details->player_id,
                            "nickName" => $client_details->display_name,
                            "organization" => config('providerlinks.slotmill.brand'), // client belong
                            "balance" => (string)$client_response->fundtransferresponse->balance,
                            "applicableBonus" => "0.0",
                            "currency" => $client_details->default_currency,
                            "homeCurrency" => $client_details->default_currency,
                        ]
                    ];

                    Helper::updateGameTransactionExt($game_trans_ext_id,$client_response->requestoclient,$response,$client_response->fundtransferresponse);   
                    Helper::saveLog('SLOTMILL wager sucess ', $this->provider_db_id, json_encode($request->all()), $response);
                    break;
                
                case "402":
                    $response = [
                        "code" => 1006,
                        "msg" => "Overdraft", // not enough money
                    ];
                    ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, 'FAILED', $response, 'FAILED', $client_response, 'FAILED', $general_details);
                    ProviderHelper::updateGameTransactionStatus($game_trans_id, 2, 99);
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    Helper::saveLog('SLOTMILL wager not enoughmoney ', $this->provider_db_id, json_encode($request->all()), $response);
                    break;
            }
        }       

        return response($response,200)
                ->header('Content-Type', 'application/json');
		
    }

    public function cancelwager(Request $request)
    {
        $playersid = explode('_', $request['playerid']);
        $client_details = ProviderHelper::getClientDetails('player_id',$playersid[1]);
        $response = [
            "code" => 0,
            "data" => [
                "playerId" => "TG_".$client_details->player_id,
                "nickName" => $client_details->display_name,
                "organization" => "$client_details->client_id", // client belong
                "balance" => (string)$client_details->balance,
                "applicableBonus" => "0.0",
                "currency" => $client_details->default_currency,
                "homeCurrency" => $client_details->default_currency,
            ]
        ];
        Helper::saveLog('SLOTMILL cancelwager ', $this->provider_db_id, json_encode($request->all()), $response);
        return response($response,200)
                ->header('Content-Type', 'application/json');
    }

    public function appendwagerresult(Request $request)
    {
        $playersid = explode('_', $request['playerid']);
        $client_details = ProviderHelper::getClientDetails('player_id',$playersid[1]);
        $response = [
            "code" => 0,
            "data" => [
                "playerId" => "TG_".$client_details->player_id,
                "nickName" => $client_details->display_name,
                "organization" => "$client_details->client_id", // client belong
                "balance" => (string)$client_details->balance,
                "applicableBonus" => "0.0",
                "currency" => $client_details->default_currency,
                "homeCurrency" => $client_details->default_currency,
            ]
        ];
        Helper::saveLog('SLOTMILL appendwagerresult ', $this->provider_db_id, json_encode($request->all()), $response);
    }

    public function appendwagergoods(Request $request)
    {
        $playersid = explode('_', $request['playerid']);
        $client_details = ProviderHelper::getClientDetails('player_id',$playersid[1]);
        $response = [
            "code" => 0,
            "data" => [
                "playerId" => "TG_".$client_details->player_id,
                "nickName" => $client_details->display_name,
                "organization" => "$client_details->client_id", // client belong
                "balance" => (string)$client_details->balance,
                "applicableBonus" => "0.0",
                "currency" => $client_details->default_currency,
                "homeCurrency" => $client_details->default_currency,
            ]
        ];
        Helper::saveLog('SLOTMILL appendwagergoods ', $this->provider_db_id, json_encode($request->all()), $response);
        return response($response,200)
                ->header('Content-Type', 'application/json');
    }

    public function endwager(Request $request)
    {
        $playersid = explode('_', $request['playerid']);
        $client_details = ProviderHelper::getClientDetails('player_id',$playersid[1]);
        
        // $balance = $client_details->balance + $request["amount"];
        // $response = [
        //     "code" => 0,
        //     "data" => [
        //         "playerId" => "TG_".$client_details->player_id,
        //         "nickName" => $client_details->display_name,
        //         "organization" => config('providerlinks.slotmill.brand'), // client belong
        //         "balance" => (string)$balance,
        //         "applicableBonus" => "0.0",
        //         "currency" => $client_details->default_currency,
        //         "homeCurrency" => $client_details->default_currency,
        //     ]
        // ];
        // return response($response,200)->header('Content-Type', 'application/json');
        
        $game_code = $request["cat5"];
        $transaction_check = ProviderHelper::findGameExt($request["reference"], 2,'transaction_id');
       
        if($transaction_check != 'false') {
            $response = [
                "code" => 0,
                "data" => [
                    "playerId" => "TG_".$client_details->player_id,
                    "nickName" => $client_details->display_name,
                    "organization" => config('providerlinks.slotmill.brand'), // client belong
                    "balance" => (string)$client_details->balance,
                    "applicableBonus" => "0.0",
                    "currency" => $client_details->default_currency,
                    "homeCurrency" => $client_details->default_currency,
                ]
            ];
            Helper::saveLog('SLOTMILL endwager already process ', $this->provider_db_id, json_encode($request->all()), $response);
            return response($response,200)
                    ->header('Content-Type', 'application/json');
        }

        $bet_transaction = ProviderHelper::findGameTransaction($request["reference"], 'transaction_id',1);
        $game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $game_code);

        $body_details = [
            "token" => $client_details->player_token,
            "rollback" => false,
            "game_details" => [
                "game_code" => $game_code,
                "provider_id" => $this->provider_db_id
            ],
            "game_transaction" => [
                "provider_trans_id" => $request["reference"],
                "round_id" => $request["subreference"],
                "amount" => $request["amount"],
            ],
            "provider_request" => $request->all(),
            "existing_bet" => [
                "game_trans_id" => $bet_transaction->game_trans_id
            ]
        ];
        try {
            $client = new Client();
            $guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/credit/bg-fundtransfer',
                [ 'body' => json_encode($body_details), 'timeout' => '0.05']
            );
            
        } catch (\Exception $e) {
            $balance = $client_details->balance + $request["amount"];
            $response = [
                "code" => 0,
                "data" => [
                    "playerId" => "TG_".$client_details->player_id,
                    "nickName" => $client_details->display_name,
                    "organization" => config('providerlinks.slotmill.brand'), // client belong
                    "balance" => (string)$balance,
                    "applicableBonus" => "0.0",
                    "currency" => $client_details->default_currency,
                    "homeCurrency" => $client_details->default_currency,
                ]
            ];
            ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
            Helper::saveLog('SLOTMILL endwager sucess', $this->provider_db_id, json_encode($request->all()), $response);
            return response($response,200)
                    ->header('Content-Type', 'application/json');
        }
       
    }

    public function reverse(Request $request)
    {
        $playersid = explode('_', $request['playerid']);
        $client_details = ProviderHelper::getClientDetails('player_id',$playersid[1]);
        $balance = $client_details->balance + $request["amount"];
        $response = [
            "code" => 0,
            "data" => [
                "playerId" => "TG_".$client_details->player_id,
                "nickName" => $client_details->display_name,
                "organization" => "$client_details->client_id", // client belong
                "balance" => (string)$balance,
                "applicableBonus" => "0.0",
                "currency" => $client_details->default_currency,
                "homeCurrency" => $client_details->default_currency,
            ]
        ];
        ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
        Helper::saveLog('SLOTMILL reverse ', $this->provider_db_id, json_encode($request->all()), $response);
        return response($response,200)
                ->header('Content-Type', 'application/json');
    }

}
