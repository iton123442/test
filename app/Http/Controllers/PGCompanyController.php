<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ProviderHelper;
use App\Helpers\Helper;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use Carbon\Carbon;
use DB;
use Webpatser\Uuid\Uuid;


class PGCompanyController extends Controller
{
    //
    public $provider_db_id, $middleware_api, $prefix;
    
    public function __construct()
    {
        $this->provider_db_id = config('providerlinks.pgvirtual.provider_db_id');
        $this->auth = config('providerlinks.pgvirtual.auth');
        $this->middleware_api = config('providerlinks.oauth_mw_api.mwurl');
        $this->prefix = "PGID_"; // for idom name
    }
    private static function isValidUUID($uuid)
    {
        if (!is_string($uuid) || (preg_match('/^[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}$/i', $uuid) !== 1)) {
            return false;
        }

        return true;
    }

    public function auth_player(Request $request, $auth_key,$game_session_token)
    {   
        Helper::saveLog('PGVirtual validate req', $this->provider_db_id, json_encode($request->all()), $auth_key."====".$game_session_token);
        if ($auth_key == $this->auth) {
            try {
                $token = Helper::getPGVirtualPlayerGameRound($game_session_token);
                // $lang = Helper::getInfoPlayerGameRound($game_session_token);
                $client_details = ProviderHelper::getClientDetails('token',$token->player_token);
                $response = [
                    "status" => "1024",
                    "description" => "Success",
                    "playerId" => "pgvirtual".$client_details->player_id,
                    "currency" => $client_details->default_currency,
                    // "lang" => $lang->uuid_token
                    "lang" => "en-US"
                ];
                Helper::saveLog('PGVirtual validate response', $this->provider_db_id, json_encode($request->all()), $response);
                return response($response,200)
                        ->header('Content-Type', 'application/json');
            }catch (\Exception $e){
                Helper::saveLog('PGVirtual validate err', $this->provider_db_id,  $e->getMessage(),  $e->getMessage());
                return response($e->getMessage(),404)
                        ->header('Content-Type', 'application/json');
            }
        }else{
            Helper::saveLog('PGVirtual validate auth dont match ', $this->provider_db_id,  $this->auth,  $auth_key);
        }
        
    }

    public function keepalive(Request $request, $auth_key,$game_session_token)
    {
        Helper::saveLog('PGVirtual keepalive req', $this->provider_db_id, json_encode($request->all()), $auth_key."====".$game_session_token);
        if ($auth_key == $this->auth) {
            try {
                $token = Helper::getPGVirtualPlayerGameRound($game_session_token);
                $client_details = ProviderHelper::getClientDetails('token',$token->player_token);
                $response = [
                    "status" => "1024",
                    "description" => "Success",
                ];
                Helper::saveLog('PGVirtual keepalive response', $this->provider_db_id, json_encode($request->all()), $response);
                return response($response,200)
                        ->header('Content-Type', 'application/json');
            }catch (\Exception $e){
                Helper::saveLog('PGVirtual keepalive err', $this->provider_db_id,  $e->getMessage(),  $e->getMessage());
                return response($e->getMessage(),404)
                        ->header('Content-Type', 'application/json');
            }
        }
        
        
    }

    public function placebet(Request $request, $auth_key,$game_session_token)
    {
        try {
            Helper::saveLog('PGVirtual placebet req', $this->provider_db_id, json_encode($request->all()), $auth_key."====".$game_session_token);
            if ($auth_key == $this->auth) {
                $data = $request->all();
                $game_round = Helper::getPGVirtualPlayerGameRound($game_session_token);
                $client_details = ProviderHelper::getClientDetails('token',$game_round->player_token);
                //checking balance
                if ($client_details->balance < $data["placeBet"]["amount"] ) {
                    $response = [
                        "status" => "404",
                        "description" => "error",
                    ];
                    Helper::saveLog('PGVirtual not enought balance', $this->provider_db_id, json_encode($request->all()), $response);
                    return response($response,404)
                        ->header('Content-Type', 'application/json');

                }
                try{
                    ProviderHelper::idenpotencyTable($this->prefix.'_'.$game_session_token.$data["placeBet"]["id"].'-B');
                }catch(\Exception $e){
                    $response = [
                        "status" => "404",
                        "description" => "error",
                    ];
                    Helper::saveLog('PGVirtual BET duplicate_transaction', $this->provider_db_id, json_encode($request->all()),$response);
                    return response($response,404)
                        ->header('Content-Type', 'application/json');
                }

                $game_details = ProviderHelper::findGameID($game_round->game_id);
                $game_code = $game_details->game_code;
                $game_transaction_type = 1; // 1 Bet, 2 Win
                $token_id = $client_details->token_id;
                $bet_amount = ($data["placeBet"]["amount"] / 100);
                $pay_amount = 0;
                $income = 0;
                $win_type = 0;
                $method = 1;
                $win_or_lost = 5; // 0 lost,  5 processing
                $payout_reason = 'Bet';
                $provider_trans_id = $this->prefix.$data["placeBet"]["id"];

                $game_trans_id  = ProviderHelper::createGameTransaction($token_id, $game_details->game_id, $bet_amount,  $pay_amount, $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id, $provider_trans_id);

                $game_trans_ext_id = $this->createGameTransExt($game_trans_id,$provider_trans_id, $provider_trans_id, $bet_amount, $game_transaction_type, $data, $data_response = null, $requesttosend = null, $client_response = null, $data_response = null);


                $general_details = ["aggregator" => [], "provider" => [], "client" => []];
                try {
                    $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount,$game_code,$game_details->game_name,$game_trans_ext_id,$game_trans_id,"debit",false);
                } catch (\Exception $e) {
                    $response = array(
                        "status" => "404",
                        "description" => "error",
                    );
                    ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, 'FAILED', $response, 'FAILED', $e->getMessage(), 'FAILED', $general_details);
                    ProviderHelper::updateGameTransactionStatus($game_trans_id, 2, 99);
                    Helper::saveLog('PGVirtual BET FATAL ERROR' . $e->getMessage(), $this->provider_db_id, json_encode($request->all()), $response);
                    return response($response,404)
                        ->header('Content-Type', 'application/json');
                }
                
                if (isset($client_response->fundtransferresponse->status->code)) {

                    switch ($client_response->fundtransferresponse->status->code) {
                        case "200":
                            $num = $client_response->fundtransferresponse->balance;
                            $response = [
                                "status" => "1024",
                                "description" => "Success",
                            ];
                            ProviderHelper::_insertOrUpdate($client_details->token_id, $num); 
                            $this->updateGameTransactionExt(
                                $game_trans_ext_id,
                                $client_response->requestoclient,
                                $response,
                                $client_response->fundtransferresponse);
                            Helper::saveLog('PGVirtual BET success', $this->provider_db_id, json_encode($request->all()), $response);
                            return response($response,200)->header('Content-Type', 'application/json');
                            break;
                        
                        case "402":
                            $response = [
                                "status" => "404",
                                "description" => "error",
                            ];
                            ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, 'FAILED', $response, 'FAILED', $client_response, 'FAILED', $general_details);
                            ProviderHelper::updateGameTransactionStatus($game_trans_id, 2, 99);
                            Helper::saveLog('PGVirtual BET not_enough_balance', $this->provider_db_id, json_encode($request->all()), $response);
                            // ProviderHelper::createRestrictGame($game_details->game_id,$client_details->player_id,$game_trans_ext_id,json_encode(json_encode($response)));
                            return response($response,404)->header('Content-Type', 'application/json');
                            break;
                    }
                }
               

            }
        } catch (\Exception $e) {
            $response = [
                "status" => "404",
                "description" => "error",
            ];
            Helper::saveLog('PGVirtual BET internal Error The exception was created on line: ' . $e->getLine() . " ". $e->getMessage(), $this->provider_db_id, json_encode($request->all()), $response);
            return response($response,404)
                ->header('Content-Type', 'application/json');
        }

    }
    //refund bet
    public function cancelbet(Request $request, $auth_key,$game_session_token)
    {
        Helper::saveLog('PGVirtual cancelbet req', $this->provider_db_id, json_encode($request->all()), $auth_key."====".$game_session_token);
        if ($auth_key == $this->auth) {
            $data = $request->all();

            $game_round = Helper::getPGVirtualPlayerGameRound($game_session_token);
            $client_details = ProviderHelper::getClientDetails('token',$game_round->player_token);
            $game_details = ProviderHelper::findGameID($game_round->game_id);
            foreach ($data["cancelBet"]["ids"] as $ticketIds) {
                try{
                    ProviderHelper::idenpotencyTable($this->prefix.'_'.$game_session_token.$data["cancelBet"]["id"]."-C");
                }catch(\Exception $e){
                    $response = [
                        "status" => "1024",
                        "description" => "Success",
                    ];
                    Helper::saveLog('PGVirtual Sync Bet already process', $this->provider_db_id, json_encode($request->all()), $response);
                    return response($response,200)
                        ->header('Content-Type', 'application/json');
                }

                $details = ProviderHelper::findGameExt($this->prefix.$ticketIds, 1, 'transaction_id');
                if ($details != 'false') {

                    $bet_transaction = ProviderHelper::findGameTransaction($details->game_trans_id, 'game_transaction');
                    $game_transextension = $this->createGameTransExt($bet_transaction->game_trans_id,$this->prefix.$ticketIds, $this->prefix.$ticketIds, $bet_transaction->bet_amount, 3, $data, $data_response = null, $requesttosend = null, $client_response = null, $data_response = null);

                    
                    $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_transaction->bet_amount,$game_details->game_code,$game_details->game_name,$details->game_trans_ext_id,$details->game_trans_id,"credit","true");
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    $request_data = [
                        'amount' => $bet_transaction->bet_amount,
                        'transid' => $this->prefix.$ticketIds,
                        'roundid' => $this->prefix.$ticketIds
                    ];
                    //update transaction
                    Helper::updateGameTransaction($bet_transaction,$request_data,"refund");
                    $response = [
                        "status" => "1024",
                        "description" => "Success",
                    ];
                    $this->updateGameTransactionExt(
                            $game_transextension,
                            $client_response->requestoclient,
                            $response,
                            $client_response->fundtransferresponse);
                    Helper::saveLog('PGVirtual cancelbet success', $this->provider_db_id, json_encode($request->all()), $response);
                } else {
                    Helper::saveLog('PGVirtual cancelbet not found ', $this->provider_db_id, json_encode($request->all()), $this->prefix.$ticketIds);
                }
            }

            $response = [
                "status" => "1024",
                "description" => "Success",
            ];
            return response($response,200)->header('Content-Type', 'application/json');
        }
        
    }

    public function syncbet(Request $request, $auth_key)
    {
        Helper::saveLog('PGVirtual syncbet req', $this->provider_db_id, json_encode($request->all()), $auth_key);

        if ($auth_key == $this->auth) {
            $data = $request->all();
            $bet_transation_id = $this->prefix.$data["syncBet"]["id"];

            $status = $data["syncBet"]["status"];
            $amount = round(($data["syncBet"]["amount_won"] / 100), 2);
            
            // $transaction_ext = ProviderHelper::findGameExt($bet_transation_id, 2, 'transaction_id');
            // if ($transaction_ext != 'false') {
            //     $response = [
            //         "status" => "400",
            //         "description" => "Sync Bet already process",
            //     ];
            //     Helper::saveLog('PGVirtual Sync Bet already process', $this->provider_db_id, json_encode($request->all()), $response);
            //     return response($response,200)
            //         ->header('Content-Type', 'application/json');
            // }

            try{
                ProviderHelper::idenpotencyTable($this->prefix.'_'.$data["syncBet"]["id"]."-W");
            }catch(\Exception $e){
                $response = [
                    "status" => "1024",
                    "description" => "Success",
                ];
                Helper::saveLog('PGVirtual Sync Bet already process', $this->provider_db_id, json_encode($request->all()), $response);
                return response($response,200)
                    ->header('Content-Type', 'application/json');
            }

            // $transaction_ext = ProviderHelper::findGameExt($bet_transation_id, 1, 'transaction_id'); 

            $bet_transaction = ProviderHelper::findGameTransaction($bet_transation_id, 'transaction_id',1); 
           
            if ($bet_transaction != 'false') {
                //GET BET TRANSACTION
                try {
                    //DEATILS FOR THE CLIENT REQUEST
                    $client_details = ProviderHelper::getClientDetails('token_id',$bet_transaction->token_id);
                    
                    $game_details = ProviderHelper::findGameID($bet_transaction->game_id);
                    //CREATE GAME EXTENSION
                    $num = $client_details->balance + $amount;
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $num); 
                    $response = [
                        "status" => "1024",
                        "description" => "Success",
                    ];

                    $game_trans_ext_id = $this->createGameTransExt($bet_transaction->game_trans_id,$bet_transation_id, $bet_transation_id, $amount, 2, $data, $response, $requesttosend = null, $client_response = null, $data_response = null);
                    
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
                            "provider_trans_id" => $bet_transation_id,
                            "round_id" => $bet_transation_id,
                            "amount" => $amount
                        ],
                        "provider_request" => $data,
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

                } catch (\Exception $e) {
                    $response = [
                        "status" => "1024",
                        "description" => "Success",
                    ];
                    Helper::saveLog('PGVirtual Sync Bet already fatal', $this->provider_db_id, json_encode($request->all()), $response);
                    return response($response,200)
                        ->header('Content-Type', 'application/json');
                }
                

                // $type = "credit";
                // $rollback = false;
                
                // $client_response = ClientRequestHelper::fundTransfer($client_details,$amount,$bet_transaction->game_id,$game_details->game_name,$game_trans_ext_id,$bet_transaction->game_trans_id,$type,$rollback);
                // ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                // $win  = $status == "W" ? 1 : 0;  /// 1win 0lost
                // $type = $status == "W" ? "credit" : "debit";
                // $amount = $status == "L" ? 0 : $amount; 
                // $request_data = [
                //     'win' => $win,
                //     'amount' => $amount,
                //     'payout_reason' => ProviderHelper::updateReason($win),
                // ];
                // Helper::updateGameTransaction($bet_transaction,$request_data,$type);
                // $response = [
                //     "status" => "1024",
                //     "description" => "Success",
                // ];
                // $this->updateGameTransactionExt(
                //     $game_trans_ext_id,
                //     $client_response->requestoclient,
                //     $response,
                //     $client_response->fundtransferresponse);

                // Helper::saveLog('PGVirtual syncbet success', $this->provider_db_id, json_encode($request->all()), $response);
                // return response($response,200)
                //     ->header('Content-Type', 'application/json');
            } else {
                $response = [
                    "status" => "404",
                    "description" => "BET FAILED",
                ];
                Helper::saveLog('PGVirtual FAILED', $this->provider_db_id, json_encode($request->all()), $response);
                return response($response,200)
                    ->header('Content-Type', 'application/json');
            }
            
        }
        
    }

    public function paybet(Request $request, $auth_key)
    {
        Helper::saveLog('PGVirtual paybet req', $this->provider_db_id, json_encode($request->all()), $auth_key);
        if ($auth_key == $this->auth) {
            $data = $request->all();
            $array = [];
            $bool = false;
            foreach ($data["payBet"]["ids"] as $ticketIds) {
                $bet_transation_id = $this->prefix.$ticketIds;
                $transaction_ext = ProviderHelper::findGameExt($bet_transation_id, 2, 'transaction_id');
                if ($transaction_ext == 'false') {
                    array_push($array, $ticketIds);
                    $bool = true;
                }
            }
            if ($bool) {
               $response = [
                    "status" => "402",
                    "description" => $array,
                ];
                Helper::saveLog('PGVirtual paybet FAILED', $this->provider_db_id, json_encode($request->all()), $response);
                return response($response,200)
                        ->header('Content-Type', 'application/json');
            } else {
                $response = [
                    "status" => "1024",
                    "description" => "Success",
                ];
                Helper::saveLog('PGVirtual paybet success', $this->provider_db_id, json_encode($request->all()), $response);
                return response($response,200)
                        ->header('Content-Type', 'application/json');
            }
            
        }
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

    public static function updateGameTransactionExt($gametransextid,$mw_request,$mw_response,$client_response){
        $gametransactionext = array(
            "mw_request"=>json_encode($mw_request),
            "mw_response" =>json_encode($mw_response),
            "client_response" =>json_encode($client_response),
            "transaction_detail" => "success",
        );
        DB::table('game_transaction_ext')->where("game_trans_ext_id",$gametransextid)->update($gametransactionext);
    }

}
