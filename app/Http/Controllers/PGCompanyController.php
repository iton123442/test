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
                $token = $this->getPGVirtualPlayerGameRound($game_session_token);
                // $lang = Helper::getInfoPlayerGameRound($game_session_token);
                $client_details = ProviderHelper::getClientDetails('token',$token->uuid_token);
                if ($client_details != null) {
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
                $token = $this->getPGVirtualPlayerGameRound($game_session_token);
                $client_details = ProviderHelper::getClientDetails('token',$token->uuid_token);
                if($client_details != null) {
                    $response = [
                        "status" => "1024",
                        "description" => "Success",
                    ];
                    Helper::saveLog('PGVirtual keepalive response', $this->provider_db_id, json_encode($request->all()), $response);
                    return response($response,200)
                            ->header('Content-Type', 'application/json');
                }
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
                $game_round = $this->getPGVirtualPlayerGameRound($game_session_token);
                $client_details = ProviderHelper::getClientDetails('token',$game_round->uuid_token);
                //checking balance
                $bet_amount = $data["placeBet"]["amount"];
                if ($bet_amount < 0.00) {
                    $response = [
                        "status" => "404",
                        "description" => "error",
                    ];
                    Helper::saveLog('PGVirtual not enought balance', $this->provider_db_id, json_encode($request->all()), $response);
                    return response($response,404)
                        ->header('Content-Type', 'application/json');
                }
                try{
                    ProviderHelper::idenpotencyTable($this->prefix.$data["placeBet"]["id"].'-B');
                }catch(\Exception $e){
                    $bet_transaction = GameTransactionMDB::findGameExt($this->prefix.$data["placeBet"]["id"], 1,'transaction_id', $client_details);
                    if ($bet_transaction != 'false') {
                        if ($bet_transaction->mw_response == 'null') {
                            $response = [
                                "status" => "404",
                                "description" => "error",
                            ];
                            Helper::saveLog('SLOTMILL wager no response dubplicate', $this->provider_db_id, json_encode($request->all()), $response);
                        }else {
                            $response = $bet_transaction->mw_response;
                        }

                        Helper::saveLog('SLOTMILL wager duplicate_transaction', $this->provider_db_id, json_encode($request->all()), $response);
                        return response($response,200)
                        ->header('Content-Type', 'application/json');
                        
                    } else {
                        $response = [
                            "status" => "404",
                            "description" => "error",
                        ];
                        Helper::saveLog('SLOTMILL wager not found dubplicate', $this->provider_db_id, json_encode($request->all()), $response);
                        return response($response,404)
                        ->header('Content-Type', 'application/json');
                    } 
                }
                $game_details = ProviderHelper::findGameID($game_round->game_id);
                $provider_trans_id = $this->prefix.$data["placeBet"]["id"];
                $game_session_data = array(
                    "game_session_token" => $game_session_token,
                    "provider_transaction_id"  => $provider_trans_id,
                    "game_id" => $game_details->game_id,
                    "token_id" => $client_details->token_id,
                    "status" => 1,
                );
                $this->createPlayerGameRoundSession($game_session_data);
                    $gameTransactionData = array(
                    "provider_trans_id" => $provider_trans_id,
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
                $game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
                $gameTransactionEXTData = array(
                    "game_trans_id" => $game_trans_id,
                    "provider_trans_id" => $provider_trans_id,
                    "round_id" => $provider_trans_id,
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
                    Helper::saveLog('PGVirtual BET FATAL ERROR', $this->provider_db_id, json_encode($request->all()), $response);
                    return response($response,404)
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
                                "description" => "error",
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
                            Helper::saveLog('PGVirtual BET not_enough_balance', $this->provider_db_id, json_encode($request->all()), $response);
                            return response($response,404)->header('Content-Type', 'application/json');
                    }
                }
            } else {
                $response = [
                    "status" => "404",
                    "description" => "error",
                ];
                Helper::saveLog('PGVirtual BET AUTH', $this->provider_db_id, json_encode($request->all()), "NOT PARTNER");
                return response($response,404)->header('Content-Type', 'application/json');
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
            $token = $this->getPGVirtualPlayerGameRound($game_session_token);
            $client_details = ProviderHelper::getClientDetails('token',$token->uuid_token);
            $ids = isset($data["cancelBet"]["id"]) ? $data["cancelBet"]["id"] : $data["cancelBet"]["ticketIds"];
            foreach ($ids as $ticketIds) {
                $bet_transation_id = $this->prefix.$ticketIds;
                $getPlayerGameRoundSession = $this->getPlayerGameRoundSession($bet_transation_id);
                if ($getPlayerGameRoundSession == 'false') {
                    $response = [
                        "status" => "404",
                        "description" => "BET NOT FOUND",
                    ];
                    return response($response,200)->header('Content-Type', 'application/json');
                }
                if ($getPlayerGameRoundSession->status == 2) {
                    $response = array(
                        "status" => "404",
                        "description" => "error",
                    );
                    $bet_transaction = GameTransactionMDB::findGameTransactionDetails($bet_transation_id, 'round_id',false, $client_details);
                    if ($bet_transaction != 'false') {
                        $client_details->connection_name = $bet_transaction->connection_name;
                        $gameTransactionEXTData = array(
                            "game_trans_id" => $bet_transaction->game_trans_id,
                            "provider_trans_id" => $getPlayerGameRoundSession->game_session_token,
                            "round_id" => $bet_transation_id,
                            "amount" => $bet_transaction->bet_amount,
                            "game_transaction_type"=> 3,
                            "provider_request" =>json_encode($request->all()),
                            "mw_response" => json_encode($response),
                            "mw_request"=> "DONT SENT TO CLIENT",
                            "client_response" => "DONT SENT TO CLIENT",
                            "transaction_detail" =>"DONT SENT TO CLIENT",
                            "general_details" =>"DONT SENT TO CLIENT",
                        );
                        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
                    }
                    return response($response,200)->header('Content-Type', 'application/json');
                }
                try{
                    ProviderHelper::idenpotencyTable($bet_transation_id.'-C');
                }catch(\Exception $e){
                    if ($getPlayerGameRoundSession->status == 3) {
                        $bet_transaction = GameTransactionMDB::findGameExt($bet_transation_id, 3,'round_id', $client_details);
                        if ($bet_transaction != 'false') {
                            $response = [
                                "status" => "1024",
                                "description" => "Success",
                            ];
                            return response($response,200)
                            ->header('Content-Type', 'application/json');
                        }
                    }
                    
                }
                $bet_transaction = GameTransactionMDB::findGameTransactionDetails($bet_transation_id, 'round_id',false, $client_details);
                $client_details->connection_name = $bet_transaction->connection_name;
                $game_details = ProviderHelper::findGameID($bet_transaction->game_id);
                $balance = $client_details->balance + $bet_transaction->bet_amount;
                ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
                $response = [
                    "status" => "1024",
                    "description" => "Success",
                ];
                $gameTransactionEXTData = array(
                    "game_trans_id" => $bet_transaction->game_trans_id,
                    "provider_trans_id" => $getPlayerGameRoundSession->game_session_token,
                    "round_id" => $bet_transation_id,
                    "amount" => $bet_transaction->bet_amount,
                    "game_transaction_type"=> 3,
                    "provider_request" =>json_encode($request->all()),
                    "mw_response" => json_encode($response),
                );
                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
                $win =4;
                $updateGameTransaction = [
                    'win' => 5,
                    'pay_amount' => $bet_transaction->bet_amount,
                    'income' => 0,
                    'entry_id' => 2,
                    'trans_status' => 2
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
                $updatePlayerGameRoundSession = array(
                    "status" => 3
                );
                $this->updatePlayerGameRoundSession($updatePlayerGameRoundSession,$getPlayerGameRoundSession->pg_id);
                $body_details = [
                    "type" => "credit",
                    "win" => $win,
                    "token" => $client_details->player_token,
                    "rollback" => true,
                    "game_details" => [
                        "game_id" => $game_details->game_id
                    ],
                    "game_transaction" => [
                        "amount" =>$bet_transaction->bet_amount
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
                    Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($request->all()), $response);
                    return response($response,200)
                            ->header('Content-Type', 'application/json');
                } catch (\Exception $e) {
                    Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($request->all()), $response);
                    return response($response,200)
                            ->header('Content-Type', 'application/json');
                }
            }
        }
        
    }

    public function syncbet(Request $request, $auth_key)
    {
        Helper::saveLog('PGVirtual syncbet req', $this->provider_db_id, json_encode($request->all()), $auth_key);
        if ($auth_key == $this->auth) {
            $data = $request->all();
            $bet_transation_id = $this->prefix.$data["syncBet"]["id"];
            $getPlayerGameRoundSession = $this->getPlayerGameRoundSession($bet_transation_id);
            if ($getPlayerGameRoundSession == 'false') {
                $response = [
                    "status" => "404",
                    "description" => "BET NOT FOUND",
                ];
                return response($response,200)->header('Content-Type', 'application/json');
            }
            $amount = $data["syncBet"]["amount_won"];
            $client_details = ProviderHelper::getClientDetails('token_id',$getPlayerGameRoundSession->token_id);
            try{
                ProviderHelper::idenpotencyTable($this->prefix.$data["syncBet"]["id"].'-W');
            }catch(\Exception $e){
                if ($getPlayerGameRoundSession->status == 2) {
                    $bet_transaction = GameTransactionMDB::findGameExt($this->prefix.$data["syncBet"]["id"], 2,'round_id', $client_details);
                    if ($bet_transaction != 'false') {
                        $response = [
                            "status" => "1024",
                            "description" => "Success",
                        ];
                        return response($response,200)
                        ->header('Content-Type', 'application/json');
                    }
                }
                
            }
            $bet_transaction = GameTransactionMDB::findGameTransactionDetails($bet_transation_id, 'round_id',false, $client_details);
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
                "provider_trans_id" => $getPlayerGameRoundSession->game_session_token,
                "round_id" => $bet_transation_id,
                "amount" => $amount,
                "game_transaction_type"=> 2,
                "provider_request" =>json_encode($request->all()),
                "mw_response" => json_encode($response),
            );
            $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
            $win = $amount > 0 ? 1 : 0;
            $updateGameTransaction = [
                'win' => 5,
                'pay_amount' => $amount,
                'income' => $bet_transaction->bet_amount - $amount,
                'entry_id' => $amount > 0 ? 2 : 1,
                'trans_status' => 2
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
            $updatePlayerGameRoundSession = array(
                "status" => 2
            );
            $this->updatePlayerGameRoundSession($updatePlayerGameRoundSession,$getPlayerGameRoundSession->pg_id);
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
            $array = [];
            $bool = false;
            $ids = isset($data["payBet"]["id"]) ? $data["payBet"]["id"] : $data["payBet"]["ticketIds"];
            foreach ($ids as $ticketIds) {
                $bet_transation_id = $this->prefix.$ticketIds;
                $getPlayerGameRoundSession = $this->getPlayerGameRoundSession($bet_transation_id);
                if ($getPlayerGameRoundSession != 'false') {
                    if ($getPlayerGameRoundSession->status == 1) {
                        array_push($array, $ticketIds);
                        $bool = true;
                    }
                }
            }
            if ($bool) {
                $response = [
                    "status" => "402",
                    "description" => $array,
                ];
                Helper::saveLog('PGVirtual pay_error', $this->provider_db_id, json_encode($request->all()), $response);
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

    public static function getPGVirtualPlayerGameRound($game_session_token){
        $token = DB::select("SELECT player_token,game_id,uuid_token FROM player_game_rounds WHERE player_token = '".$game_session_token."' ");
        $data_rows = count($token);
        return $data_rows > 0? $token[0] : 'false';
    }

    public static function getPlayerGameRoundSession($ticketIds){
        $token = DB::select("SELECT game_session_token,provider_transaction_id,game_id,token_id,status,pg_id FROM pgvirtual_game_round WHERE provider_transaction_id = '".$ticketIds."' ");
        $data_rows = count($token);
        return $data_rows > 0? $token[0] : 'false';
    }
    public static function updatePlayerGameRoundSession($data,$pg_id){
        DB::table('pgvirtual_game_round')->where('pg_id',$pg_id)->update($data);
    }
    public static function createPlayerGameRoundSession($data){
        return DB::table('pgvirtual_game_round')->insertGetId($data);
    }   

}
