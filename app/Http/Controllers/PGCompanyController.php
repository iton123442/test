<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ProviderHelper;
use App\Helpers\Helper;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransactionMDB;
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
        $this->prefix = "PGVUUID"; // for idom name
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
                $token = $this->getPlayerGameRoundSession($game_session_token);
                $client_details = ProviderHelper::getClientDetails('token_id',$token->token_id);
                if ($client_details != null) {
                    $getSession =  $this->getPlayerGameRoundSessionValidate($client_details->player_id);
                    if($getSession->game_session_token == $game_session_token) {
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
                    }
                    $response = [
                        "status" => "404",
                        "description" => "SESSION EXPIRED",
                    ];
                    Helper::saveLog('PGVirtual validate req', $this->provider_db_id, json_encode($request->all()), $response);
                    return response($response,200)
                        ->header('Content-Type', 'application/json');
                }
                $response = [
                    "status" => "404",
                    "description" => "PLAYER NOT FOUND",
                ];
                Helper::saveLog('PGVirtual validate req', $this->provider_db_id, json_encode($request->all()), $response);
                return response($response,200)
                    ->header('Content-Type', 'application/json');

                
            }catch (\Exception $e){
                Helper::saveLog('PGVirtual validate err', $this->provider_db_id,  $e->getMessage(),  $e->getMessage());
                $response = [
                    "status" => "404",
                    "description" => "PLAYER NOT FOUND",
                ];
                Helper::saveLog('PGVirtual validate req', $this->provider_db_id, json_encode($request->all()), $response);
                return response($response,200)
                    ->header('Content-Type', 'application/json');
            }
        }else{
            $response = [
                "status" => "404",
                "description" => "AUTH NOT FOUND",
            ];
            Helper::saveLog('PGVirtual validate req', $this->provider_db_id, json_encode($request->all()), $response);
            return response($response,200)
                ->header('Content-Type', 'application/json');
        }
        
    }

    public function keepalive(Request $request, $auth_key,$game_session_token)
    {
        Helper::saveLog('PGVirtual keepalive req', $this->provider_db_id, json_encode($request->all()), $auth_key."====".$game_session_token);
        if ($auth_key == $this->auth) {
            try {
                $token = $this->getPlayerGameRoundSession($game_session_token);
                $client_details = ProviderHelper::getClientDetails('token_id',$token->token_id);
                if($client_details != null) {
                    $getSession =  $this->getPlayerGameRoundSessionValidate($client_details->player_id);
                    if($getSession->game_session_token == $game_session_token) {
                        $response = [
                            "status" => "1024",
                            "description" => "Success",
                        ];
                        Helper::saveLog('PGVirtual keepalive response', $this->provider_db_id, json_encode($request->all()), $response);
                        return response($response,200)
                                ->header('Content-Type', 'application/json');
                    }
                    $response = [
                        "status" => "404",
                        "description" => "SESSION EXPIRED",
                    ];
                    Helper::saveLog('PGVirtual validate req', $this->provider_db_id, json_encode($request->all()), $response);
                    return response($response,200)
                        ->header('Content-Type', 'application/json');
                }
            }catch (\Exception $e){
                $response = [
                    "status" => "404",
                    "description" => "Error",
                ];
                Helper::saveLog('PGVirtual keepalive err', $this->provider_db_id,  $e->getMessage(), $response);
                return response($e->getMessage(),200)
                        ->header('Content-Type', 'application/json');
            }
        }
        $response = [
            "status" => "404",
            "description" => "AUTH NOT FOUND",
        ];
        Helper::saveLog('PGVirtual keepalive req', $this->provider_db_id, json_encode($request->all()), $response);
        return response($response,200)
            ->header('Content-Type', 'application/json');
        
        
    }

    public function placebet(Request $request, $auth_key,$game_session_token)
    {
        Helper::saveLog('PGVirtual placebet req', $this->provider_db_id, json_encode($request->all()), $auth_key."====".$game_session_token);
        if ($auth_key == $this->auth) {
            $data = $request->all();
            $token = $this->getPlayerGameRoundSession($game_session_token);
            $client_details = ProviderHelper::getClientDetails('token_id',$token->token_id);
            if ($client_details == null) {
                $response = [
                    "status" => "404",
                    "description" => "PLAYER NOT FOUND",
                ];
                Helper::saveLog('PGVirtual validate req', $this->provider_db_id, json_encode($request->all()), $response);
                return response($response,200)
                    ->header('Content-Type', 'application/json');
            }
            $getSession =  $this->getPlayerGameRoundSessionValidate($client_details->player_id);
            if($getSession->game_session_token != $game_session_token) {
                $response = [
                    "status" => "404",
                    "description" => "SESSION EXPIRED",
                ];
                Helper::saveLog('PGVirtual validate req', $this->provider_db_id, json_encode($request->all()), $response);
                return response($response,200)
                    ->header('Content-Type', 'application/json');
            }
            //checking balance
            $bet_amount = $data["placeBet"]["amount"];
            if ($bet_amount < 0.00) {
                $response = [
                    "status" => "404",
                    "description" => "INVALID AMOUNT",
                ];
                Helper::saveLog('PGVirtual invalide placebet', $this->provider_db_id, json_encode($request->all()), $response);
                return response($response,200)
                    ->header('Content-Type', 'application/json');
            }
            $transaction_id = $this->prefix.$data["placeBet"]["id"].'B';
            $round_id = $this->prefix.$data["placeBet"]["id"];
            try{
                ProviderHelper::idenpotencyTable($transaction_id);
            }catch(\Exception $e){
                $bet_transaction = GameTransactionMDB::findGameExt($transaction_id, 1,'transaction_id', $client_details);
                if ($bet_transaction != 'false') {
                    if ($bet_transaction->transaction_detail == 'FAILED') {
                        $response = [
                            "status" => "404",
                            "description" => "error",
                        ];
                        Helper::saveLog('PGVirtual placebet req dubplicate', $this->provider_db_id, json_encode($request->all()), $response);
                    }else {
                        $response = [
                            "status" => "1024",
                            "description" => "Success",
                        ];
                    }

                    Helper::saveLog('PGVirtual placebet req  duplicate_transaction', $this->provider_db_id, json_encode($request->all()), $response);
                    return response($response,200)
                    ->header('Content-Type', 'application/json');
                    
                } else {
                    $response = [
                        "status" => "404",
                        "description" => "NOT FOUND TRANSACTION",
                    ];
                    Helper::saveLog('PGVirtual placebet req not found dubplicate', $this->provider_db_id, json_encode($request->all()), $response);
                    return response($response,200)
                    ->header('Content-Type', 'application/json');
                } 
            }
            $game_details = ProviderHelper::findGameID($token->game_id);
            $game_session_data = array(
                "game_session_token" => $game_session_token,
                "provider_transaction_id"  => $round_id,
                "game_id" => $game_details->game_id,
                "token_id" => $client_details->token_id,
                "status" => 1,
            );
            $pg_game_roundID = $this->createPlayerGameRoundSession($game_session_data);
            $gameTransactionData = array(
                "provider_trans_id" => $transaction_id,
                "token_id" => $client_details->token_id,
                "game_id" => $game_details->game_id,
                "round_id" => $round_id,
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
            $game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
            $gameTransactionEXTData = array(
                "game_trans_id" => $game_trans_id,
                "provider_trans_id" => $transaction_id,
                "round_id" => $round_id,
                "amount" => $bet_amount,
                "game_transaction_type"=> 1,
                "provider_request" =>json_encode($request->all()),
            );
            $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
            try {
                $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount,$game_details->game_code,$game_details->game_name,$game_trans_ext_id,$game_trans_id,"debit",false);
            } catch (\Exception $e) {
                $response = array(
                    "status" => "404",
                    "description" => "error",
                );
                $updateTransactionEXt = array(
                    "mw_response" => json_encode($response),
                    'mw_request' => json_encode("FAILED"),
                    'client_response' => json_encode("FAILED"),
                    "transaction_detail" => "FAILED",
                    "general_details" =>"FAILED"
                );
                GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                $updateGameTransaction = [
                    "win" => 2,
                    'trans_status' => 5
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
                $updatePlayerGameRoundSession = array(
                    "status" => 4
                );
                $this->updatePlayerGameRoundSession($updatePlayerGameRoundSession,$pg_game_roundID);
                Helper::saveLog('PGVirtual BET FATAL ERROR', $this->provider_db_id, json_encode($request->all()), $response);
                return response($response,200)
                    ->header('Content-Type', 'application/json');
            }
            if (isset($client_response->fundtransferresponse->status->code)) {
                ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                switch ($client_response->fundtransferresponse->status->code) {
                    case "200":
                        $response = [
                            "status" => "1024",
                            "description" => "Success",
                        ];
                        $update_gametransactionext = array(
                            "mw_response" =>json_encode($response),
                            "mw_request"=>json_encode($client_response->requestoclient),
                            "client_response" =>json_encode($client_response->fundtransferresponse),
                            "transaction_detail" =>"success",
                            "general_details" =>"success",
                        );
                        GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_id,$client_details);
                        Helper::saveLog('PGVirtual BET success', $this->provider_db_id, json_encode($request->all()), $response);
                        return response($response,200)->header('Content-Type', 'application/json');
                        break;
                    default:
                        $response = [
                            "status" => "404",
                            "description" => "PLAYER NOT FOUND",
                        ];
                        $update_gametransactionext = array(
                            "mw_response" =>json_encode($response),
                            "mw_request"=>json_encode($client_response->requestoclient),
                            "client_response" =>json_encode($client_response->fundtransferresponse),
                            "transaction_detail" =>"FAILED",
                            "general_details" =>"FAILED",
                        );
                        GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_id,$client_details);
                        $updateGameTransaction = [
                            "win" => 2,
                            'trans_status' => 5
                        ];
                        GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
                        $updatePlayerGameRoundSession = array(
                            "status" => 4
                        );
                        $this->updatePlayerGameRoundSession($updatePlayerGameRoundSession,$pg_game_roundID);
                        Helper::saveLog('PGVirtual BET not_enough_balance', $this->provider_db_id, json_encode($request->all()), $response);
                        return response($response,200)->header('Content-Type', 'application/json');
                }
            }
        } else {
            $response = [
                "status" => "404",
                "description" => "error",
            ];
            Helper::saveLog('PGVirtual BET AUTH', $this->provider_db_id, json_encode($request->all()), "NOT PARTNER");
            return response($response,200)->header('Content-Type', 'application/json');
        }
    }
    //refund bet
    // public function cancelbet(Request $request, $auth_key,$game_session_token)
    public function cancelbet(Request $request, $auth_key)
    {
        Helper::saveLog('PGVirtual cancelbet req', $this->provider_db_id, json_encode($request->all()), $auth_key);
        if ($auth_key == $this->auth) {
            $data = $request->all();
            // $transaction_id = $this->prefix.$data["cancelBet"]["id"].'C';
            $bet_transation_id = $this->prefix.$data["cancelBet"]["id"];
            $getPlayerGameRoundSession = $this->getPlayerGameRoundSessionID($bet_transation_id);
            // $client_details = ProviderHelper::getClientDetails('token_id',$getPlayerGameRoundSession->token_id);
            if($getPlayerGameRoundSession != "false") {
                $response = [
                    "status" => "1024",
                    "description" => "Success",
                ];
                Helper::saveLog("PGVirtual cancelbet req", $this->provider_db_id, json_encode($request->all()), $response);
                return response($response,200)->header('Content-Type', 'application/json');
                // if ($client_details != null) {
                    
                    
                    // if ($getPlayerGameRoundSession->status == 3) {
                    //     $response = [
                    //         "status" => "1024",
                    //         "description" => "Success",
                    //     ];
                    //     Helper::saveLog("PGVirtual cancelbet req", $this->provider_db_id, json_encode($request->all()), $response);
                    //     return response($response,200)->header('Content-Type', 'application/json');
                    // }

                    // if ($getPlayerGameRoundSession->status == 4) {
                    //     $response = array(
                    //         "status" => "404",
                    //         "description" => "TRANSACTION FAILED",
                    //     );
                    //     Helper::saveLog("PGVirtual cancelbet failed", $this->provider_db_id, json_encode($request->all()), $response);
                    //     return response($response,200)->header('Content-Type', 'application/json');
                    // }
                    // try{
                    //     ProviderHelper::idenpotencyTable($transaction_id);
                    // }catch(\Exception $e){
                    //     if ($getPlayerGameRoundSession->status == 3) {
                    //         $bet_transaction = GameTransactionMDB::findGameExt($transaction_id, 3,'transaction_id', $client_details);
                    //         if ($bet_transaction != 'false') {
                    //             $response = [
                    //                 "status" => "404",
                    //                 "description" => "TRANSACTION ALREADY REFUNDED",
                    //             ];
                    //             return response($response,200)
                    //             ->header('Content-Type', 'application/json');
                    //         }
                    //     }
                    // }

                    // $bet_transaction = GameTransactionMDB::findGameTransactionDetails($bet_transation_id, 'round_id',false, $client_details);
                    // $client_details->connection_name = $bet_transaction->connection_name;
                    // $game_details = ProviderHelper::findGameID($bet_transaction->game_id);
                    // $balance = $client_details->balance + $bet_transaction->bet_amount;
                    // ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
                    // $response = [
                    //     "status" => "1024",
                    //     "description" => "Success",
                    // ];
                    // $gameTransactionEXTData = array(
                    //     "game_trans_id" => $bet_transaction->game_trans_id,
                    //     "provider_trans_id" => $transaction_id,
                    //     "round_id" => $bet_transation_id,
                    //     "amount" => $bet_transaction->bet_amount,
                    //     "game_transaction_type"=> 3,
                    //     "provider_request" =>json_encode($request->all()),
                    //     "mw_response" => json_encode($response),
                    // );
                    // $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
                    // $win =4;
                    // $updateGameTransaction = [
                    //     'win' => 5,
                    //     'pay_amount' => $bet_transaction->bet_amount,
                    //     'income' => 0,
                    //     'entry_id' => 2,
                    //     'trans_status' => 2
                    // ];
                    // GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
                    // $updatePlayerGameRoundSession = array(
                    //     "status" => 3
                    // );
                    // $this->updatePlayerGameRoundSession($updatePlayerGameRoundSession,$getPlayerGameRoundSession->pg_id);
                    // $body_details = [
                    //     "type" => "credit",
                    //     "win" => $win,
                    //     "token" => $client_details->player_token,
                    //     "rollback" => true,
                    //     "game_details" => [
                    //         "game_id" => $game_details->game_id
                    //     ],
                    //     "game_transaction" => [
                    //         "amount" =>$bet_transaction->bet_amount
                    //     ],
                    //     "connection_name" => $bet_transaction->connection_name,
                    //     "game_trans_ext_id" => $game_trans_ext_id,
                    //     "game_transaction_id" => $bet_transaction->game_trans_id

                    // ];
                    // try {
                    //     $client = new Client();
                    //     $guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-bgFundTransferV2MultiDB',
                    //         [ 'body' => json_encode($body_details), 'timeout' => '1.50']
                    //     );
                    //     Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($request->all()), $response);
                    //     return response($response,200)
                    //             ->header('Content-Type', 'application/json');
                    // } catch (\Exception $e) {
                    //     Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($request->all()), $response);
                    //     return response($response,200)
                    //             ->header('Content-Type', 'application/json');
                    // }

                // } else {
                //     $response = [
                //         "status" => "404",
                //         "description" => "error",
                //     ];
                //     Helper::saveLog("PGVirtual cancelbet req", $this->provider_db_id, json_encode($request->all()), $response);
                //     return response($response,200)->header('Content-Type', 'application/json');
                // }
            } else {
                $response = [
                    "status" => "404",
                    "description" => "BET NOT FOUND",
                ];
                Helper::saveLog("PGVirtual cancelbet req", $this->provider_db_id, json_encode($request->all()), $response);
                return response($response,200)->header('Content-Type', 'application/json');
            }
        } else {
            $response = [
                "status" => "404",
                "description" => "AUTH NOT FOUND",
            ];
            Helper::saveLog("PGVirtual cancelbet req", $this->provider_db_id, json_encode($request->all()), $response);
            return response($response,200)->header('Content-Type', 'application/json');
        }
        
    }

    public function syncbet(Request $request, $auth_key)
    {
        Helper::saveLog('PGVirtual syncbet req', $this->provider_db_id, json_encode($request->all()), $auth_key);
        if ($auth_key == $this->auth) {
            $data = $request->all();
            $transaction_id = $this->prefix.$data["syncBet"]["id"].$data["syncBet"]["status"]; // STATUS [W/L/C/V]
            $bet_transation_id = $this->prefix.$data["syncBet"]["id"];
            $getPlayerGameRoundSession = $this->getPlayerGameRoundSessionID($bet_transation_id);
            if ($getPlayerGameRoundSession == 'false') {
                $response = [
                    "status" => "404",
                    "description" => "BET NOT FOUND",
                ];
                return response($response,200)->header('Content-Type', 'application/json');
            }
            if($data["syncBet"]["status"] == "W" ||$data["syncBet"]["status"] == "L" ) {
                $amount = $data["syncBet"]["amount_won"];
                $game_transaction_type = 2;
                $win = $amount > 0 ? 1 : 0;
                $rollback_type = false;
            } else {
                //CANCEL BY THE PLAYER OR CANCEL BY THE PGCOMPANY
                $amount = $data["syncBet"]["amount_refund"];
                $game_transaction_type = 3;
                $win = 4;
                $rollback_type = true;
            }
            $client_details = ProviderHelper::getClientDetails('token_id',$getPlayerGameRoundSession->token_id);
            try{
                ProviderHelper::idenpotencyTable($transaction_id);
            }catch(\Exception $e){
                $bet_transaction = GameTransactionMDB::findGameExt($transaction_id, $game_transaction_type,'transaction_id', $client_details);
                if ($bet_transaction != 'false') {
                    $response = [
                        "status" => "1024",
                        "description" => "Success",
                    ];
                    Helper::saveLog('PGVirtual placebet req  duplicate_transaction', $this->provider_db_id, json_encode($request->all()), $response);
                    return response($response,200)
                    ->header('Content-Type', 'application/json');
                } 
                // else {
                //     $response = [
                //         "status" => "404",
                //         "description" => "TRANSACTION ALREADY PROCESS",
                //     ];
                //     Helper::saveLog('PGVirtual placebet req not found dubplicate', $this->provider_db_id, json_encode($request->all()), $response);
                //     return response($response,200)
                //     ->header('Content-Type', 'application/json');
                // } 
                
            }
            $bet_transaction = GameTransactionMDB::findGameTransactionDetails($bet_transation_id, 'round_id',false, $client_details);
            if ($bet_transaction != "false") {
                $updatePlayerGameRoundSession = array(
                    "status" => $game_transaction_type
                );
                $this->updatePlayerGameRoundSession($updatePlayerGameRoundSession,$getPlayerGameRoundSession->pg_id);
                $client_details->connection_name = $bet_transaction->connection_name;
                $game_details = ProviderHelper::findGameID($bet_transaction->game_id);
                $balance = $client_details->balance + $amount;
                ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
                $response = [
                    "status" => "1024",
                    "description" => "Success",
                ];
                $gameTransactionEXTData = array(
                    "game_trans_id" => $bet_transaction->game_trans_id,
                    "provider_trans_id" => $transaction_id,
                    "round_id" => $bet_transation_id,
                    "amount" => $amount,
                    "game_transaction_type"=> $game_transaction_type,
                    "provider_request" =>json_encode($request->all()),
                    "mw_response" => json_encode($response),
                );
                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
                $updateGameTransaction = [
                    'win' => 5,
                    'pay_amount' => $amount,
                    'income' => $bet_transaction->bet_amount - $amount,
                    'entry_id' => $amount > 0 ? 2 : 1,
                    'trans_status' => 2
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
                $body_details = [
                    "type" => "credit",
                    "win" => $win,
                    "token" => $client_details->player_token,
                    "rollback" => $rollback_type,
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
                        [ 'body' => json_encode($body_details), 'timeout' => '1.50']
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
            } else {
                $response = [
                    "status" => "404",
                    "description" => "NOT FOUND TRANSACTION",
                ];
                Helper::saveLog('PGVirtual placebet req not found dubplicate', $this->provider_db_id, json_encode($request->all()), $response);
                return response($response,200)
                ->header('Content-Type', 'application/json');
            }
        } else {
            $response = [
                "status" => "404",
                "description" => "AUTH NOT FOUND",
            ];
            Helper::saveLog('PGVirtual Sync AUTH NOT FOUND', $this->provider_db_id, json_encode($request->all()), $response);
            return response($response,200)
                ->header('Content-Type', 'application/json');
        } 
        
    }

    public function paybet(Request $request, $auth_key)
    {
        Helper::saveLog('PGVirtual paybet req', $this->provider_db_id, json_encode($request->all()), $auth_key);
        if ($auth_key == $this->auth) {
            $data = $request->all();
            $bet_transation_id = $this->prefix.$data["payBet"]["id"];
            $getPlayerGameRoundSession = $this->getPlayerGameRoundSessionID($bet_transation_id);
            if ($getPlayerGameRoundSession == 'false') {
                $response = [
                    "status" => "404",
                    "description" => "BET NOT FOUND",
                ];
                return response($response,200)->header('Content-Type', 'application/json');
            }
            $response = [
                "status" => "1024",
                "description" => "Success",
            ];
            Helper::saveLog('PGVirtual paybet', $this->provider_db_id, json_encode($request->all()), $response);
            return response($response,200)
                ->header('Content-Type', 'application/json');
        }   

        $response = [
            "status" => "404",
            "description" => "AUTH NOT FOUND",
        ];
        Helper::saveLog('PGVirtual Sync AUTH NOT FOUND', $this->provider_db_id, json_encode($request->all()), $response);
        return response($response,200)
            ->header('Content-Type', 'application/json');
    }

    // public static function getPGVirtualPlayerGameRound($game_session_token){
    //     $token = DB::select("SELECT player_token,game_id,uuid_token FROM player_game_rounds WHERE uuid_token = '".$game_session_token."' ");
    //     $data_rows = count($token);
    //     return $data_rows > 0? $token[0] : 'false';
    // }

    public static function getPlayerGameRoundSessionID($ticketIds){
        $token = DB::select("SELECT game_session_token,provider_transaction_id,game_id,token_id,status,pg_id FROM pgvirtual_game_round WHERE provider_transaction_id = '".$ticketIds."' ");
        $data_rows = count($token);
        return $data_rows > 0? $token[0] : 'false';
    }
    public static function getPlayerGameRoundSession($game_session){
        $token = DB::select("SELECT game_session_token,provider_transaction_id,game_id,token_id,status,pg_id FROM pgvirtual_game_round WHERE game_session_token = '".$game_session."' ");
        $data_rows = count($token);
        return $data_rows > 0? $token[0] : 'false';
    }

    public static function getPlayerGameRoundSessionValidate($player_id){
        $token = DB::select("SELECT game_session_token FROM pgvirtual_game_session_token where player_id = ".$player_id." order by pg_game_session_id desc limit 1 ");
        $data_rows = count($token);
        return $data_rows > 0? $token[0] : 'false';
    }
    public static function updatePlayerGameRoundSession($data,$pg_id){
        DB::table('pgvirtual_game_round')->where('pg_id',$pg_id)->update($data);
    }
    public static function createPlayerGameRoundSession($data){
        return DB::table('pgvirtual_game_round')->insertGetId($data);
    }   


    // update GAMELOBBY
    // add function helper createPGVitualGameRoundSession

    // BET PROCESS


    // GAME_TRANSACTION PROCESS INSERT
    // prefix + pg_id + [B,CANCEL,W/L] = IDOMPOTENT
    // prefix + pg_id + [B,CANCEL,W/L] = PROVIDER_TRANS_ID
    // prefix + pg_id = ROUND_ID

    // pgvirtual_game_round PROCESS INSERT

    // prefix + pg_id = provider_transaction_id

    // GET CLIENT USING TOKEN _ID


    // pg_game_round
    // status 1 = bet , 2 = credit , 3 = cancel , 4 = failed
    // 1 = bet => progressing receiving bet
    // 2 = win => already settled receiving 
    // 3 = cancel = > need to refund
    // 4 = failed if client response failed for the bet only

}
