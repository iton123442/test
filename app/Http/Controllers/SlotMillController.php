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
        Helper::saveLog('SLOTMILL wager', $this->provider_db_id, json_encode($request->all()), "ENDPOINT HIT");
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

        $game_code = $request["cat5"];
        try{
            ProviderHelper::idenpotencyTable($this->prefix.'_'.$request["reference"]);
        }catch(\Exception $e){
            $bet_transaction = ProviderHelper::findGameExt($request["reference"], 1,'transaction_id');
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
        
        $general_details = ["aggregator" => [], "provider" => [], "client" => []];
        try {
            $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount,$game_details->game_code,$game_details->game_name,$game_trans_ext_id,$game_trans_id,"debit","false");
            ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
        } catch (\Exception $e) {
            $response = [
                "code" => 1,
                "msg" => "Technical problem",
            ];
            ProviderHelper::updatecreateGameTransExt($game_trans_ext_id,  $request->all(), $response, 'FAILED', $e->getMessage(),  $response, $general_details);
            ProviderHelper::updateGameTransactionStatus($game_trans_id, 2, 99);
            Helper::saveLog('SLOTMILL wager Retry exception fund', $this->provider_db_id, json_encode($request->all()), $response);
            return response($response,200)
            ->header('Content-Type', 'application/json');
        }

        if (isset($client_response->fundtransferresponse->status->code)) {

            switch ($client_response->fundtransferresponse->status->code) {
                case "200":
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
                    Helper::updateGameTransactionExt($game_trans_ext_id,$client_response->requestoclient,$response,$client_response->fundtransferresponse);  
                    ProviderHelper::updateGameTransactionStatus($game_trans_id, 2, 99);
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
        $game_details = Helper::getInfoPlayerGameRound($client_details->player_token);
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
        Helper::saveLog('SLOTMILL endwager', $this->provider_db_id, json_encode($request->all()), "ENDPOINT HIT");
        $playersid = explode('_', $request['playerid']);
        $client_details = ProviderHelper::getClientDetails('player_id',$playersid[1]);
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
                $bet_transaction = ProviderHelper::findGameExt($request["reference"], 2,'transaction_id');
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

        $bet_transaction = ProviderHelper::findGameTransaction($request["reference"], 'transaction_id',1);

        $game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $game_code);
        
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
        
        $game_trans_ext_id = $this->createGameTransExt($bet_transaction->game_trans_id,$transaction_uuid, $reference_transaction_uuid, $amount, 2, $request->all() , $response, $requesttosend = null, $client_response = null, $data_response = null);

        ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
        //Initialize data to pass
        $win = $amount > 0  ?  1 : 0;  /// 1win 0lost
        $type = $amount > 0  ? "credit" : "debit";
        $request_data = [
            'win' => 5,
            'amount' => $amount,
            'payout_reason' => ProviderHelper::updateReason(5),
        ];
        //update transaction
        Helper::updateGameTransaction($bet_transaction,$request_data,$type);

        $body_details = [
            "type" => "credit",
            "win" => $win,
            "token" => $client_details->player_token,
            "rollback" => false,
            "game_details" => [
                "game_id" => $game_details->game_id
            ],
            "game_transaction" => [
                "provider_trans_id" => $transaction_uuid,
                "round_id" => $reference_transaction_uuid,
                "amount" => $amount
            ],
            "provider_request" => $request->all(),
            "provider_response" => $response,
            "game_trans_ext_id" => $game_trans_ext_id,
            "game_transaction_id" => $bet_transaction->game_trans_id

        ];


        try {
            $client = new Client();
            $guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-fundtransferV2',
                [ 'body' => json_encode($body_details), 'timeout' => '0.20']
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


    public static function createGameTransExt($game_trans_id, $provider_trans_id, $round_id, $amount, $game_type, $provider_request, $mw_response, $mw_request, $client_response, $transaction_detail){
        $gametransactionext = array(
            "game_trans_id" => $game_trans_id,
            "provider_trans_id" => $provider_trans_id,
            "round_id" => $round_id,
            "amount" => $amount,
            "game_transaction_type"=>$game_type,
            "provider_request" => json_encode($provider_request),
            "mw_response" =>json_encode($mw_response),
            "mw_request"=>json_encode($mw_request),
            "client_response" =>json_encode($client_response),
            "transaction_detail" =>json_encode($transaction_detail)
        );
        $gamestransaction_ext_ID = DB::table("game_transaction_ext")->insertGetId($gametransactionext);
        return $gamestransaction_ext_ID;
    }

}
