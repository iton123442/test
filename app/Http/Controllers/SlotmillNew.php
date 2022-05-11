<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ProviderHelper;
use App\Helpers\Helper;
use App\Helpers\FreeSpinHelper;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransactionMDB;
use Carbon\Carbon;
use DB;

class SlotmillNew extends Controller
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
        Helper::saveLog('SLOTMILL wager', $this->provider_db_id, json_encode($request->all()), "ENDPOINT HIT");
        $playersid = explode('_', $request['playerid']);
        $client_details = ProviderHelper::getClientDetails('player_id',$playersid[1]);
        $game_trans_id = ProviderHelper::idGenerate($client_details->connection_name, 1);
		$game_trans_ext_id = ProviderHelper::idGenerate($client_details->connection_name, 2);   
        $game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $game_code);
        $fund_extra_data = [];
        $fund_extra_data = [
            'provider_name' => $game_details->provider_name
        ]; 
        if ($client_details == null) {
            $response = [
                "code" => 1008,
                "msg" => "The player is not authorized",
            ];
            Helper::saveLog('SLOTMILL wager', $this->provider_db_id, json_encode($request->all()), $response);
            return response($response,200)->header('Content-Type', 'application/json');
        }

        $game_code = $request["cat5"];
        try{
            ProviderHelper::idenpotencyTable($this->prefix.'_'.$request["reference"]);
        }catch(\Exception $e){
            $bet_transaction = GameTransactionMDB::findGameExt($request["reference"], 1,'round_id', $client_details);
            if ($bet_transaction != 'false') {
                if ($bet_transaction->mw_response == 'null') {
                   $response = [
                        "code" => 1,
                        "msg" => "Technical problem",
                    ];
                }else {
                    $response = $bet_transaction->mw_response;
                }

                Helper::saveLog('SLOTMILL wager duplicate_transaction', $this->provider_db_id, json_encode($request->all()), $response);
                return response($response,200)
                ->header('Content-Type', 'application/json');
                
            } else {
                $response = [
                    "code" => 1,
                    "msg" => "Technical problem",
                ];
                Helper::saveLog('SLOTMILL wager duplicate_transaction', $this->provider_db_id, json_encode($request->all()), $response);
                return response($response,200)
                ->header('Content-Type', 'application/json');
            } 
        }
        try {
           
            $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount,$game_details->game_code,$game_details->game_name,$game_trans_ext_id,$game_trans_id,"debit",false, $fund_extra_data);
            
        } catch (\Exception $e) {
            $response = [
                "code" => 1,
                "msg" => "Technical problem",
            ];
            $createGameTransactionLog = [
                "connection_name" => $client_details->connection_name,
                "column" =>[
                    "game_trans_ext_id" => $game_trans_ext_id,
                    "request" => json_encode("FAILED"),
                    "response" => json_encode($response),
                    "log_type" => "provider_details",
                    "transaction_detail" => "Failed",
                ]
            ];
            ProviderHelper::queTransactionLogs($createGameTransactionLog);
            $updateGameTransaction = [
                "win" => 2,
                'trans_status' => 5
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
            Helper::saveLog('SLOTMILL wager Retry exception fund', $this->provider_db_id, json_encode($request->all()), $response);
            return response($response,200)
            ->header('Content-Type', 'application/json');
        }
        $bet_amount = $request["amount"];
        $provider_trans_id = $request["reference"];
        $bet_id = $request["subreference"];
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
                    Helper::saveLog('SLOTMILL wager sucess ', $this->provider_db_id, json_encode($request->all()), $response);
                    break;
                
                default:
                    $response = [
                        "code" => 1006,
                        "msg" => "Overdraft", // not enough money
                    ];
                    $createGameTransactionLog = [
                        "connection_name" => $client_details->connection_name,
                        "column" =>[
                            "game_trans_ext_id" => $game_transid_ext,
                            "request" => json_encode($client_response->requestoclient),
                            "response" => json_encode($client_response->fundtransferresponse),
                            "log_type" => "client_details",
                            "transaction_detail" => "FAILED",
                        ]
                    ];
                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
                    $updateGameTransaction = [
                        "win" => 2,
                        'trans_status' => 5
                    ];
                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
                    Helper::saveLog('SLOTMILL wager not enoughmoney ', $this->provider_db_id, json_encode($request->all()), $response);
            }
        }   
        //Create GameTransaction, GameExtension
        $gameTransactionData = array(
            "provider_trans_id" => $bet_id,
            "token_id" => $client_details->token_id,
            "game_id" => $game_details->game_id,
            "round_id" => $provider_trans_id,
            "bet_amount" => $bet_amount,
            "win" => 5,
            "pay_amount" => 0,
            "income" => 0,
            "entry_id" =>1,
            "trans_status" =>1,
            "operator_id" => $client_details->operator_id,
            "client_id" => $client_details->client_id,
            "player_id" => $client_details->player_id,
        );
       GameTransactionMDB::createGametransactionV2($gameTransactionData,$game_trans_id,$client_details);

        $round_id = $provider_trans_id;
        $game_type = 1;

        $gameTransactionEXTData = array(
            "game_trans_id" => $game_trans_id,
            "provider_trans_id" => $bet_id,
            "round_id" => $round_id,
            "amount" => $bet_amount,
            "game_transaction_type"=> 1,
            // "provider_request" =>json_encode($request->all()),
        );
      GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$game_trans_ext_id,$client_details);
      $createGameTransactionLog = [
        "connection_name" => $client_details->connection_name,
        "column" =>[
            "game_trans_ext_id" => $game_transid_ext,
            "request" => json_encode($request->all()),
            "response" => json_encode($response),
            "log_type" => "provider_details",
            "transaction_detail" => "success",
        ]
    ];
    ProviderHelper::queTransactionLogs($createGameTransactionLog); 
        return response($response,200)
                ->header('Content-Type', 'application/json');
        
    }

    public function cancelwager(Request $request)
    {
        $playersid = explode('_', $request['playerid']);
        $client_details = ProviderHelper::getClientDetails('player_id',$playersid[1]);
        if ($client_details == null) {
            $response = [
                "code" => 1008,
                "msg" => "The player is not authorized",
            ];
            Helper::saveLog('SLOTMILL wager', $this->provider_db_id, json_encode($request->all()), $response);
            return response($response,200)->header('Content-Type', 'application/json');
        }


        try{
            ProviderHelper::idenpotencyTable($this->prefix.'_'.$request["subreference"]);
        }catch(\Exception $e){
            if ($request["subreference"] != $request["reference"]) {
                // $bet_transaction = ProviderHelper::findGameExt($request["reference"], 2,'transaction_id');
                $bet_transaction = GameTransactionMDB::findGameExt($request["reference"], 3,'round_id', $client_details);
                if ($bet_transaction != 'false') {
                    if ($bet_transaction->mw_response == 'null') {
                       $response = [
                            "code" => 1,
                            "msg" => "Technical problem",
                        ];
                    }else {
                        $response = $bet_transaction->mw_response;
                    }

                    Helper::saveLog('SLOTMILL wager duplicate_transaction', $this->provider_db_id, json_encode($request->all()), $response);
                    return response($response,200)
                    ->header('Content-Type', 'application/json');
                    
                } else {
                    $response = [
                        "code" => 1,
                        "msg" => "Technical problem",
                    ];
                    Helper::saveLog('SLOTMILL wager duplicate_transaction', $this->provider_db_id, json_encode($request->all()), $response);
                    return response($response,200)
                    ->header('Content-Type', 'application/json');
                } 
            }
        }

        $bet_transaction = GameTransactionMDB::findGameTransactionDetails($request["reference"], 'round_id',1, $client_details);
        $client_details->connection_name = $bet_transaction->connection_name;

        $game_details = ProviderHelper::findGameID($bet_transaction->game_id);

        $transaction_uuid = $request["reference"];
        $reference_transaction_uuid = $request["subreference"];
        $amount = $request["amount"];

        $balance = $client_details->balance + $amount;
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
        
        $gameTransactionEXTData = array(
            "game_trans_id" => $bet_transaction->game_trans_id,
            "provider_trans_id" => $reference_transaction_uuid,
            "round_id" => $transaction_uuid,
            "amount" => $amount,
            "game_transaction_type"=> 3,
            "provider_request" =>json_encode($request->all()),
            "mw_response" => json_encode($response),
        );
        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

        ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
        //Initialize data to pass
        $win = 4;  /// 1win 0lost
        $entry_id = 2;

        $updateGameTransaction = [
            'win' => 5,
            'pay_amount' => $amount,
            'income' => $bet_transaction->bet_amount - $amount,
            'entry_id' => $entry_id,
            'trans_status' => 3
        ];
        GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);

        $body_details = [
            "type" => "credit",
            "win" => $win,
            "token" => $client_details->player_token,
            "rollback" => true,
            "game_details" => [
                "game_id" => $game_details->game_id
            ],
            "game_transaction" => [
                "amount" => $amount
            ],
            "connection_name" => $bet_transaction->connection_name,
            "game_trans_ext_id" => $game_trans_ext_id,
            "game_transaction_id" => $bet_transaction->game_trans_id

        ];

        try {
            $client = new Client();
            $guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-bgFundTransferV2MultiDB',
                [ 'body' => json_encode($body_details), 'timeout' => '2.00']
            );
            //THIS RESPONSE IF THE TIMEOUT NOT FAILED
            Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($request->all()), $response);
            return response($response,200)
                    ->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($request->all()), $response);
            return response($response,200)
                    ->header('Content-Type', 'application/json');
        }

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
        Helper::saveLog('SLOTMILL endwager', $this->provider_db_id, json_encode($request->all()), "ENDPOINT HIT");
        $playersid = explode('_', $request['playerid']);
        $client_details = ProviderHelper::getClientDetails('player_id',$playersid[1]);
        $game_trans_ext_id = ProviderHelper::idGenerate($client_details->connection_name, 2);   
        if ($client_details == null) {
            $response = [
                "code" => 1008,
                "msg" => "The player is not authorized",
            ];
            Helper::saveLog('SLOTMILL endwager', $this->provider_db_id, json_encode($request->all()), $response);
            return response($response,200)->header('Content-Type', 'application/json');
        }

        try{
            ProviderHelper::idenpotencyTable($this->prefix.'_'.$request["subreference"]);
        }catch(\Exception $e){
            if ($request["subreference"] != $request["reference"]) {
                // $bet_transaction = ProviderHelper::findGameExt($request["reference"], 2,'transaction_id');
                $bet_transaction = GameTransactionMDB::findGameExt($request["reference"], 2,'round_id', $client_details);
                if ($bet_transaction != 'false') {
                    if ($bet_transaction->mw_response == 'null') {
                       $response = [
                            "code" => 1,
                            "msg" => "Technical problem",
                        ];
                    }else {
                        $response = $bet_transaction->mw_response;
                    }

                    Helper::saveLog('SLOTMILL wager duplicate_transaction', $this->provider_db_id, json_encode($request->all()), $response);
                    return response($response,200)
                    ->header('Content-Type', 'application/json');
                    
                } else {
                    $response = [
                        "code" => 1,
                        "msg" => "Technical problem",
                    ];
                    Helper::saveLog('SLOTMILL wager duplicate_transaction', $this->provider_db_id, json_encode($request->all()), $response);
                    return response($response,200)
                    ->header('Content-Type', 'application/json');
                } 
            }
        }
        $game_code = $request["cat5"];
        //gettransaction add connection 
        $bet_transaction = GameTransactionMDB::findGameTransactionDetails($request["reference"], 'round_id',1, $client_details);
        $client_details->connection_name = $bet_transaction->connection_name;   
        $game_details = ProviderHelper::findGameID($bet_transaction->game_id);
        $transaction_uuid = $request["reference"];
        $reference_transaction_uuid = $request["subreference"];
        $amount = $request["amount"] + $request["bonusprize"];

        $balance = $client_details->balance + $amount;
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
        
        $gameTransactionEXTData = array(
            "game_trans_id" => $bet_transaction->game_trans_id,
            "provider_trans_id" => $reference_transaction_uuid,
            "round_id" => $transaction_uuid,
            "amount" => $amount,
            "game_transaction_type"=> 2,
            // "provider_request" =>json_encode($request->all()),
            // "mw_response" => json_encode($response),
        );
        GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$game_trans_ext_id,$client_details);
        ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
        //Initialize data to pass
        $win = $amount > 0  ?  1 : 0;  /// 1win 0lost
        $entry_id = $amount > 0  ?  2 : 1; 

        $updateGameTransaction = [
            'win' => 5,
            'pay_amount' => $amount,
            'income' => $bet_transaction->bet_amount - $amount,
            'entry_id' => $entry_id,
            'trans_status' => 2
        ];
        GameTransactionMDB::updateGametransactionV2($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
        $createGameTransactionLog = [
            "connection_name" => $client_details->connection_name,
            "column" =>[
                "game_trans_ext_id" => $game_transid_ext,
                "request" => json_encode($request->all()),
                "response" => json_encode($response),
                "log_type" => "provider_details",
                "transaction_detail" => "success",
             ]
        ];
        ProviderHelper::queTransactionLogs($createGameTransactionLog);
        $body_details = [
            "type" => "credit",
            "win" => $win,
            "token" => $client_details->player_token,
            "rollback" => false,
            "game_details" => [
                "game_id" => $game_details->game_id
            ],
            "game_transaction" => [
                "amount" => $amount
            ],
            "connection_name" => $bet_transaction->connection_name,
            "game_trans_ext_id" => $game_trans_ext_id,
            "game_transaction_id" => $bet_transaction->game_trans_id

        ];

        if(isset($request["prepaidref"])) {
            $getFreespin = FreeSpinHelper::getFreeSpinDetails($request["prepaidref"], "provider_trans_id" );
            if($getFreespin){
                $getOrignalfreeroundID = explode("_",$request["prepaidref"]);
                $body_details["fundtransferrequest"]["fundinfo"]["freeroundId"] = $getOrignalfreeroundID[1]; //explod the provider trans use the original
                $status = ($getFreespin->spin_remaining - 1) == 0 ? 2 : 1;
                $updateFreespinData = [
                    "status" => $status,
                    "win" => $getFreespin->win + $amount,
                    "spin_remaining" => $getFreespin->spin_remaining - 1
                ];
                $updateFreespin = FreeSpinHelper::updateFreeSpinDetails($updateFreespinData, $getFreespin->freespin_id);
                if($status == 2 ){
                    $body_details["fundtransferrequest"]["fundinfo"]["freeroundend"] = true; //explod the provider trans use the original
                } else {
                    $body_details["fundtransferrequest"]["fundinfo"]["freeroundend"] = false; //explod the provider trans use the original
                }
                //create transction 
                $createFreeRoundTransaction = array(
                    "game_trans_id" => $bet_transaction->game_trans_id,
                    'freespin_id' => $getFreespin->freespin_id
                );
                FreeSpinHelper::createFreeRoundTransaction($createFreeRoundTransaction);
            }
        }
        try {
            $client = new Client();
            $guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-bgFundTransferV2MultiDB',
                [ 'body' => json_encode($body_details), 'timeout' => '2.00']
            );
            //THIS RESPONSE IF THE TIMEOUT NOT FAILED
            Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($request->all()), $response);
            return response($response,200)
                    ->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($request->all()), $response);
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
