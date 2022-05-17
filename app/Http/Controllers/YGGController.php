<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ProviderHelper;
use DB;
use App\Helpers\Helper;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransactionMDB;

class YGGController extends Controller
{
    public $provider_id;
    public $org;

    public function __construct(){
        $this->provider_id = config("providerlinks.ygg002.provider_id");
        $this->org = config("providerlinks.ygg002.Org");
        $this->topOrg = config("providerlinks.ygg002.topOrg");
    }

    public function playerinfo(Request $request){
        Helper::saveLog("YGG 002 playerinfo req", $this->provider_id, json_encode($request->all()), "RECEIVED");
        $client_details = ProviderHelper::getClientDetails('token',$request->sessiontoken);
        if($client_details == null){ 
            $response = array(
                "code" => 1000,
                "msg" => "Session expired. Please log in again."
            );
            return $response;
            Helper::saveLog("YGG 002 playerinfo response", $this->provider_id,json_encode($request->all(),JSON_FORCE_OBJECT), $response);
        }
        $player_id = "TG_".$client_details->player_id;
        $balance = floatval(number_format($client_details->balance, 2, '.', ''));
        $save_bal = DB::table("player_session_tokens")->where("token_id","=",$client_details->token_id)->update(["balance" => $balance]); #new method
        $response = array(
            "code" => 0,
            "data" => array(
                "gender" => "",
                "playerId" => $player_id,
                "organization" => $this->org,
                "balance" => $balance,
                "applicableBonus" => "",
                "currency" => $client_details->default_currency,
                "homeCurrency" => $client_details->default_currency,
                "nickName" => $client_details->display_name,
                "country" => $client_details->country_code
            ),
            "msg" => "Success"
        );
        Helper::saveLog("YGG 002 playerinfo response", $this->provider_id, json_encode($request->all()), $response);
        return $response;   
    }

    public function wager(Request $request){
        Helper::saveLog("YGG 002 wager req", $this->provider_id, json_encode($request->all()), "");
        $playerId = ProviderHelper::explodeUsername('_',$request->playerid);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        $game_details = Helper::findGameDetails('game_code', $this->provider_id, $request->cat5);
        # Check Game Restricted
		
        if($client_details == null){ 
            $response = array(
                "code" => 1000,
                "msg" => "Session expired. Please log in again."
            );
            Helper::saveLog("YGG 002 wager response", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return $response;
        }
  
        $balance = $client_details->balance;
        
        $tokenId = $client_details->token_id;
        $game_code = $game_details->game_code;
        $game_id = $game_details->game_id;
        $bet_amount = $request->amount;
        $roundId = $request->reference;
        $provider_trans_id = $request->subreference;
        $bet_payout = 0; // Bet always 0 payout!
        $method = 1; // 1 bet, 2 win
        $win_or_lost = 0; // 0 Lost, 1 win, 3 draw, 4 refund, 5 processing
        $payout_reason = 'Bet';
        $income = $request->amount;
        $checkTrans = GameTransactionMDB::findGameTransactionDetails($provider_trans_id,'transaction_id',false,$client_details);
        if($checkTrans != 'false'){
            $response = array(
                "code" => 0,
                "data" => array(
                    "currency" => $client_details->default_currency,
                    "applicableBonus" => 0.00,
                    "homeCurrency" => $client_details->default_currency,
                    "organization" => $this->org,
                    "balance" => floatval(number_format($client_details->balance, 2, '.', '')),
                    "nickName" => $client_details->display_name,
                    "playerId" => "TG001_".$client_details->player_id
                ),
            );
            Helper::saveLog("YGG 002 wager dubplicate", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return $response;
        }
        if($balance < $request->amount){
            $response = array(
                "code" => 1006,
                "msg" => "You do not have sufficient fundsfor the bet."
            );
            Helper::saveLog("YGG 002 wager response", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return $response;
        }
        $gameTransactionData = array(
            "provider_trans_id" => $provider_trans_id,
            "token_id" => $tokenId,
            "game_id" => $game_details->game_id,
            "round_id" => $roundId,
            "bet_amount" => $bet_amount,
            "win" => 5,
            "pay_amount" => 0,
            "income" => 0,
            "entry_id" =>1,
            "trans_status" =>1,
        );
        $game_trans = GameTransactionMDB::createGametransaction($gameTransactionData,$client_details);
        $gameTransactionEXTData = array(
            "game_trans_id" => $game_trans,
            "provider_trans_id" => $provider_trans_id,
            "round_id" => $roundId,
            "amount" => $bet_amount,
            "game_transaction_type"=> 1,
        );
        $game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
        
        try{
            $client_response = ClientRequestHelper::fundTransfer($client_details, $bet_amount,$game_details->game_code,$game_details->game_name,$game_transextension,$game_trans,'debit');
            if(isset($client_response->fundtransferresponse->status->code) 
             && $client_response->fundtransferresponse->status->code == "200"){
                $response = array(
                    "code" => 0,
                    "data" => array(
                        "currency" => $client_details->default_currency,
                        "applicableBonus" => 0.00,
                        "homeCurrency" => $client_details->default_currency,
                        "organization" => $this->org,
                        "balance" => floatval(number_format($client_response->fundtransferresponse->balance, 2, '.', '')),
                        "nickName" => $client_details->display_name,
                        "playerId" => "TG001_".$client_details->player_id
                    ),
                );
                // $update_gametransactionext = array(
                //     "mw_response" =>json_encode($response),
                //     "mw_request"=>json_encode($client_response->requestoclient),
                //     "client_response" =>json_encode($client_response->fundtransferresponse),
                //     "transaction_detail" =>json_encode("success"),
                //     "general_details" =>json_encode("success"),
                // );
                // GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_transextension,$client_details);
                $createGameTransactionLog = [
                    "connection_name" => $client_details->connection_name,
                    "column" =>[
                        "game_trans_ext_id" => $game_transextension,
                        "request" => json_encode($request->all()),
                        "response" => json_encode($response),
                        "log_type" => "provider_details",
                        "transaction_detail" => "success",
                    ]
                ];
                ProviderHelper::queTransactionLogs($createGameTransactionLog);
                ProviderHelper::_insertOrUpdate($tokenId, $client_response->fundtransferresponse->balance);
                Helper::saveLog('YGG 002 wager', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
                return $response;
            }elseif(isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "402"){
                $response = array(
                    "code" => 1006,
                    "msg" => "You do not have sufficient fundsfor the bet."
                );
                $update_gametransactionext = array(
                    "mw_response" =>json_encode($response),
                    "mw_request"=>json_encode($client_response->requestoclient),
                    "client_response" =>json_encode($client_response->fundtransferresponse),
                    "transaction_detail" =>json_encode("402"),
                );
                GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_transextension,$client_details);
                $updateGameTransaction = [
                    "win" => 2,
                    'trans_status' => 5
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);
                Helper::saveLog("YGG 002 wager response", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
                return $response;
            }else{
                $response = array(
                    "code" => 1,
                    "msg" => "Something went wrong!"
                );
                $update_gametransactionext = array(
                    "mw_response" =>json_encode($response),
                    "mw_request"=>json_encode($client_response->requestoclient),
                    "client_response" =>json_encode($client_response->fundtransferresponse),
                    "transaction_detail" =>json_encode("402"),
                );
                GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_transextension,$client_details);
                $updateGameTransaction = [
                    "win" => 2,
                    'trans_status' => 5
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);
                Helper::saveLog("YGG 002 wager response", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
                return $response;
            }
        }catch(\Exception $e){
            $msg = array(
                'err_message' => $e->getMessage(),
                'err_line' => $e->getLine(),
                'err_file' => $e->getFile()
            );
            $response = array(
                "code" => 1006,
                "msg" => "You do not have sufficient fundsfor the bet."
            );
            $update_gametransactionext = array(
                "mw_response" =>json_encode($response),
                "mw_request"=>"Failed send",
                "client_response" =>json_encode($msg),
                "transaction_detail" =>json_encode("FAILED"),
                "general_details" =>json_encode("FAILED")
            );
            GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_transextension,$client_details);
            $updateGameTransaction = [
                "win" => 2,
                'trans_status' => 5
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);
            Helper::saveLog('YGG 002 wager error', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $msg);
            return json_encode($response, JSON_FORCE_OBJECT); 
        }
    }

    public function cancelwager(Request $request){
        Helper::saveLog("YGG 002 cancelwager req", $this->provider_id, json_encode($request->all()), "");
        $playerId = ProviderHelper::explodeUsername('_',$request->playerid);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        if($client_details == null){ 
            $response = array(
                "code" => 1000,
                "msg" => "Session expired. Please log in again."
            );
            Helper::saveLog("YGG 002 cancelwager login", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return $response;
        }
        $provider_trans_id = $request->subreference;
        $round_id = $request->reference;
        $checkTrans = GameTransactionMDB::findGameTransactionDetails($request->subreference,'transaction_id',false,$client_details);

        if($checkTrans != 'false'){
            $game_details = Helper::findGameDetails('game_id', $this->provider_id, $checkTrans->game_id);
            $checktTran = GameTransactionMDB::findGameExt($request->subreference,3,'transaction_id',$client_details);
            if($checktTran != 'false'){
                $response = array(
                    "code" => 0,
                    "data" => array(
                        "playerId" => "TG001_".$client_details->player_id,
                        "organization" => $this->org,
                        "balance" => floatval(number_format($client_details->balance, 2, '.', '')),
                        "currency" => $client_details->default_currency,
                    )
                );
                Helper::saveLog('YGG 002 cancelwager duplicate call', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
                return $response;
            }
            $checktTransExt = GameTransactionMDB::findGameExt($round_id,3,'round_id',$client_details);
            if($checktTransExt != 'false'){
                $response = array(
                    "code" => 0,
                    "data" => array(
                        "playerId" => "TG001_".$client_details->player_id,
                        "organization" => $this->org,
                        "balance" => floatval(number_format($client_details->balance, 2, '.', '')),
                        "currency" => $client_details->default_currency,
                    )
                );
                Helper::saveLog('YGG 002 cancelwager duplicate call', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
                return $response;
            }
            $balance = $client_details->balance + $checkTrans->bet_amount;
            $response = array(
                "code" => 0,
                "data" => array(
                    "playerId" => "TG001_".$client_details->player_id,
                    "organization" => $this->org,
                    "balance" => floatval(number_format($balance, 2, '.', '')),
                    "currency" => $client_details->default_currency
                )
            );
            $create_gametransactionext = array(
                "game_trans_id" => $checkTrans->game_trans_id,
                "provider_trans_id" => $provider_trans_id,
                "round_id" => $round_id,
                "amount" => $checkTrans->bet_amount,
                "game_transaction_type"=> 3
            );
            $game_trans_ext_v2 = GameTransactionMDB::createGameTransactionExt($create_gametransactionext,$client_details);
            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "provider" => 'ygg',
                    "game_trans_ext_id" => $game_trans_ext_v2,
                    "client_connection_name" => $client_details->connection_name,
                    "win_or_lost" => 4;
                ],
                "provider" => [
                    "provider_request" => $request->all(),
                    "provider_trans_id"=>$provider_trans_id,
                    "provider_round_id"=>$round_id,
                ],
                "mwapi" => [
                    "roundId"=> $checkTrans->game_trans_id,
                    "type" => 3,
                    "game_id" => $game_details->game_id,
                    "player_id" => $client_details->player_id,
                    "mw_response" => $response,
                ]
            ];
            ClientRequestHelper::fundTransfer_TG($client_details, $checkTrans->bet_amount, $game_details->game_code, $game_details->game_name, $checkTrans->game_trans_id, 'credit', true, $action_payload);
            $updateGameTransaction = [
                'win' => 4,
                'pay_amount' => $checkTrans->bet_amount,
                'income' => 0,
                'entry_id' => 2,
                'trans_status' => 2
            ];
            $createGameTransactionLog = [
                "connection_name" => $client_details->connection_name,
                "column" =>[
                    "game_trans_ext_id" => $game_trans_ext_v2,
                    "request" => json_encode($request->all()),
                    "response" => json_encode($response),
                    "log_type" => "provider_details",
                    "transaction_detail" => "success",
                ]
            ];
            ProviderHelper::queTransactionLogs($createGameTransactionLog);
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $checkTrans->game_trans_id, $client_details);
            ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
            Helper::saveLog('YGG 002 cancelwager response', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return $response;

        }else{
            $response = array(
                "code" => 0,
                "data" => array(
                    "playerId" => "TG001_".$client_details->player_id,
                    "organization" => $this->org,
                    "balance" => floatval(number_format($client_details->balance, 2, '.', '')),
                    "currency" => $client_details->default_currency
                )
            );
            Helper::saveLog('YGG 002 cancelwager not exist', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return $response;
        }
    }

    public function appendwagerresult(Request $request){
        Helper::saveLog('YGG 002 appendwagerresult request', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), "" );
        $playerId = ProviderHelper::explodeUsername('_',$request->playerid);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        if($client_details == null){ 
            $response = array(
                "code" => 1000,
                "msg" => "Session expired. Please log in again."
            );
            Helper::saveLog("YGG 002 appendwagerresult login", $this->provider_id,json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return $response;
        }
        $game_details = Helper::findGameDetails('game_code', $this->provider_id, $request->cat5);
        $balance = $client_details->balance;
        $tokenId = $client_details->token_id;
        $bonusamount = $request->amount;
        $provider_trans_id = $request->subreference;
        $round_id = $request->reference;
        
        $checkTrans = GameTransactionMDB::findGameTransactionDetails($round_id,'round_id',false,$client_details);
        if($checkTrans != 'false'){
            $checkTransExt = GameTransactionMDB::findGameExt($provider_trans_id,false,'transaction_id',$client_details);
            if($checkTransExt != 'false'){
                $response = array(
                    "code" => 0,
                    "data" => array(
                        "currency" => $client_details->default_currency,
                        "applicableBonus" => floatval(number_format($balance, 2, '.', '')),
                        "homeCurrency" => $client_details->default_currency,
                        "organization" => $this->org,
                        "balance" => floatval(number_format($balance, 2, '.', '')),
                        "nickName" => $client_details->display_name,
                        "playerId" => "TG001_".$client_details->player_id,
                        "bonus" => 0
                    ),
                );
                Helper::saveLog("YGG 002 appendwagerresult dubplicate", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
                return $response;
            }

            $gameTransactionData = array(
                "provider_trans_id" => $provider_trans_id,
                "token_id" => $tokenId,
                "game_id" => $game_details->game_id,
                "round_id" => $round_id,
                "bet_amount" => 0,
                "win" => 1,
                "pay_amount" => $bonusamount,
                "income" => 0,
                "entry_id" =>1,
                "trans_status" =>1,
            );
            $gametrans = GameTransactionMDB::createGametransaction($gameTransactionData,$client_details);
            $gameTransactionEXTData = array(
                "game_trans_id" => $gametrans,
                "provider_trans_id" => $provider_trans_id,
                "round_id" => $round_id,
                "amount" => $bonusamount,
                "game_transaction_type"=> 2,
            );
            $game_trans_ext_v2 = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
            try{   
                $client_response = ClientRequestHelper::fundTransfer($client_details, $bonusamount, $game_details->game_code, $game_details->game_name, $game_trans_ext_v2, $gametrans, 'credit');
                $bonus = 'getbonusprize';
                if(isset($client_response->fundtransferresponse->status->code) 
                 && $client_response->fundtransferresponse->status->code == "200"){
                    $response = array(
                        "code" => 0,
                        "data" => array(
                            "currency" => $client_details->default_currency,
                            "applicableBonus" =>  floatval(number_format($client_response->fundtransferresponse->balance, 2, '.', '')),
                            "homeCurrency" => $client_details->default_currency,
                            "organization" => $this->org,
                            "balance" =>  floatval(number_format($client_response->fundtransferresponse->balance, 2, '.', '')),
                            "nickName" => $client_details->display_name,
                            "playerId" => "TG001_".$client_details->player_id,
                            "bonus" => 0
                        ),
                    );
                    // $update_gametransactionext = array(
                    //     "mw_response" =>json_encode($response),
                    //     "mw_request"=>json_encode($client_response->requestoclient),
                    //     "client_response" =>json_encode($client_response->fundtransferresponse),
                    //     "transaction_detail" =>json_encode("success"),
                    //     "general_details" =>json_encode("success"),
                    // );
                    // GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_v2,$client_details);
                    $createGameTransactionLog = [
                        "connection_name" => $client_details->connection_name,
                        "column" =>[
                            "game_trans_ext_id" => $game_trans_ext_v2,
                            "request" => json_encode($request->all()),
                            "response" => json_encode($response),
                            "log_type" => "provider_details",
                            "transaction_detail" => "success",
                        ]
                    ];
                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
                    $save_bal = DB::table("player_session_tokens")->where("token_id","=",$tokenId)->update(["balance" => $client_response->fundtransferresponse->balance]);
                    Helper::saveLog('YGG 002 wager response', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
                    return $response;
                }elseif(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "402"){
                    $response = array(
                        "code" => 1006,
                        "msg" => "You do not have sufficient fundsfor the bet."
                    );
                    $update_gametransactionext = array(
                        "mw_response" =>json_encode($response),
                        "mw_request"=>json_encode($client_response->requestoclient),
                        "client_response" =>json_encode($client_response->fundtransferresponse),
                        "transaction_detail" =>json_encode("402"),
                    );
                    GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_v2,$client_details);
                    $updateGameTransaction = [
                        "win" => 2,
                        'trans_status' => 5
                    ];
                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $gametrans, $client_details);
                    Helper::saveLog("YGG wager response", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
                    return $response;
                }else{
                    $response = array(
                        "code" => 1,
                        "msg" => "Something went wrong!"
                    );
                    $update_gametransactionext = array(
                        "mw_response" =>json_encode($response),
                        "mw_request"=>json_encode($client_response->requestoclient),
                        "client_response" =>json_encode($client_response->fundtransferresponse),
                        "transaction_detail" =>json_encode("402"),
                    );
                    GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_v2,$client_details);
                    $updateGameTransaction = [
                        "win" => 2,
                        'trans_status' => 5
                    ];
                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $gametrans, $client_details);
                    Helper::saveLog("YGG wager response", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
                    return $response;
                }
                
                Helper::saveLog('Yggdrasil appendwagerresult', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
                return $response;
    
            }catch(\Exception $e){
                $msg = array(
                    'err_message' => $e->getMessage(),
                    'err_line' => $e->getLine(),
                    'err_file' => $e->getFile()
                );
                $response = array(
                    "code" => 1,
                    "msg" => "Something went wrong!"
                );
                $update_gametransactionext = array(
                    "mw_response" =>json_encode($response),
                    "mw_request"=>json_encode($msg),
                    "client_response" =>json_encode($msg),
                    "transaction_detail" =>json_encode("FAILED"),
                    "general_details" =>json_encode("FAILED")
                );
                GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_v2,$client_details);
                $updateGameTransaction = [
                    "win" => 2,
                    'trans_status' => 5
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $gametrans, $client_details);
                Helper::saveLog('Yggdrasil wager error', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $msg);
                return json_encode($response, JSON_FORCE_OBJECT); 
            }


        }else{
            $response = array(
                "code" => 0,
                "data" => array(
                    "currency" => $client_details->default_currency,
                    "applicableBonus" => floatval(number_format($balance, 2, '.', '')),
                    "homeCurrency" => $client_details->default_currency,
                    "organization" => $this->org,
                    "balance" => floatval(number_format($balance, 2, '.', '')),
                    "nickName" => $client_details->display_name,
                    "playerId" => "TG001_".$client_details->player_id,
                    "bonus" => 0
                ),
            );
            Helper::saveLog("YGG 002 appendwagerresult not exist", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return $response;
        }
        
       

    }

    public function endwager(Request $request){
        Helper::saveLog("YGG 002 endwager", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), "recieved");
        $playerId = ProviderHelper::explodeUsername('_',$request->playerid);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        if($client_details == null){ 
            $response = array(
                "code" => 1000,
                "msg" => "Session expired. Please log in again."
            );
            Helper::saveLog("YGG 002 endwager login", $this->provider_id,json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return $response;
        }
        $balance = $client_details->balance;
        $tokenId = $client_details->token_id;
        $win_amount = $request->amount;
        $provider_trans_id = $request->subreference;
        $round_id = $request->reference;
        $checkTrans = GameTransactionMDB::findGameTransactionDetails($round_id,'round_id',false,$client_details);
        $checkTransExt = GameTransactionMDB::findGameExt($provider_trans_id,false,'transaction_id',$client_details);
        $income = $checkTrans->bet_amount - $win_amount;
        $entry_id = $win_amount > 0 ? 2 : 1;
        $win = $win_amount > 0 ? 1 : 0;
        $game_details = Helper::findGameDetails('game_code', $this->provider_id, $request->cat5);
        if($checkTrans != 'false'){
            if($checkTransExt != 'false'){
                $response = array(
                    "code" => 0,
                    "data" => array(
                        "currency" => $client_details->default_currency,
                        "applicableBonus" => 0.00,
                        "homeCurrency" => $client_details->default_currency,
                        "organization" => $this->org,
                        "balance" => floatval(number_format($client_details->balance, 2, '.', '')),
                        "nickName" => $client_details->display_name,
                        "playerId" => "TG001_".$client_details->player_id,
                    ),
                );
                Helper::saveLog("YGG 002 endwager(win) dubplicate", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
                return $response;
            }
            try{
                $balance = $client_details->balance + $win_amount;
                $response = array(
                    "code" => 0,
                    "data" => array(
                        "currency" => $client_details->default_currency,
                        "applicableBonus" => 0.00,
                        "homeCurrency" => $client_details->default_currency,
                        "organization" => $this->org,
                        "balance" => floatval(number_format($balance, 2, '.', '')),
                        "nickName" => $client_details->display_name,
                        "playerId" => "TG001_".$client_details->player_id
                    ),
                );
                $create_gametransactionext = array(
                    "game_trans_id" => $checkTrans->game_trans_id,
                    "provider_trans_id" => $provider_trans_id,
                    "round_id" => $round_id,
                    "amount" => $win_amount,
                    "game_transaction_type"=> 2
                );
                $game_trans_ext_v2 = GameTransactionMDB::createGameTransactionExt($create_gametransactionext,$client_details);
                $action_payload = [
                    "type" => "custom", #genreral,custom :D # REQUIRED!
                    "custom" => [
                        "provider" => 'ygg',
                        "game_trans_ext_id" => $game_trans_ext_v2,
                        "client_connection_name" => $client_details->connection_name,
                        "win_or_lost" => $win,
                    ],
                    "provider" => [
                        "provider_request" => $request->all(),
                        "provider_trans_id"=>$provider_trans_id,
                        "provider_round_id"=>$round_id,
                    ],
                    "mwapi" => [
                        "roundId"=> $checkTrans->game_trans_id,
                        "type" => 2,
                        "game_id" => $game_details->game_id,
                        "player_id" => $client_details->player_id,
                        "mw_response" => $response,
                    ]
                ];
                $updateGameTransaction = [
                    'win' => $win,
                    'pay_amount' => $win_amount,
                    'income' => $income,
                    'entry_id' => $entry_id,
                    'trans_status' => 2
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $checkTrans->game_trans_id, $client_details);
                ClientRequestHelper::fundTransfer_TG($client_details, $win_amount, $game_details->game_code, $game_details->game_name, $checkTrans->game_trans_id, 'credit', false, $action_payload);
                $createGameTransactionLog = [
                    "connection_name" => $client_details->connection_name,
                    "column" =>[
                        "game_trans_ext_id" => $game_trans_ext_v2,
                        "request" => json_encode($request->all()),
                        "response" => json_encode($response),
                        "log_type" => "provider_details",
                        "transaction_detail" => "success",
                    ]
                ];
                ProviderHelper::queTransactionLogs($createGameTransactionLog);
                $save_bal = DB::table("player_session_tokens")->where("token_id","=",$tokenId)->update(["balance" => $balance]);
                Helper::saveLog("YGG 002 endwager (win)", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
                return $response;
    
            }catch(\Exception $e){
                $msg = array(
                    'error' => '1',
                    'message' => $e->getMessage(),
                );
                Helper::saveLog('YGG 002 endwager error', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $msg);
                return json_encode($msg, JSON_FORCE_OBJECT); 
            }
        }else{
            $response = array(
                "code" => 0,
                "data" => array(
                    "currency" => $client_details->default_currency,
                    "applicableBonus" => 0.00,
                    "homeCurrency" => $client_details->default_currency,
                    "organization" => $this->org,
                    "balance" => floatval(number_format($client_details->balance, 2, '.', '')),
                    "nickName" => $client_details->display_name,
                    "playerId" => "TG001_".$client_details->player_id,
                ),
            );
            Helper::saveLog("YGG 002 endwager not exist", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return $response;
        }
        
    }

    public function campaignpayout(Request $request){
        Helper::saveLog('YGG 002 campaignpayout request', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), "");
        $playerId = ProviderHelper::explodeUsername('_',$request->playerid);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        $response = array(
            "code" => 0,
            "data" => array(
                "currency" => $client_details->default_currency,
                "applicableBonus" => floatval(number_format($client_details->balance, 2, '.', '')),
                "homeCurrency" => $client_details->default_currency,
                "organization" => $this->org,
                "balance" => floatval(number_format($client_details->balance, 2, '.', '')),
                "nickName" => $client_details->display_name,
                "playerId" => "TG001_".$client_details->player_id
            ),
        );
        Helper::saveLog("YGG 002 campaignpayout response", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
        return $response;
    }

    public function getbalance(Request $request){
        Helper::saveLog('YGG 002 getbalance request', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), "");
        $client_details = ProviderHelper::getClientDetails('token',$request->sessiontoken);
        if($client_details == null){ 
            $response = array(
                "code" => 1000,
                "msg" => "Session expired. Please log in again."
            );
            return $response;
            Helper::saveLog("YGG getbalance response", $this->provider_id,json_encode($request->all(),JSON_FORCE_OBJECT), $response);
        }
        $player_id = "TG001_".$client_details->player_id;
        $balance = floatval(number_format($client_details->balance, 2, '.', ''));

        $response = array(
            "code" => 0,
            "data" => array(
                "currency" => $client_details->default_currency,
                "applicableBonus" => 0,
                "homeCurrency" => $client_details->default_currency,
                "organization" => $this->org,
                "balance" => $balance,
                "nickName" => $client_details->display_name,
                "playerId" => $player_id,
            )
        );
        Helper::saveLog("YGG 002 getbalance response", $this->provider_id, json_encode($request->all()), $response);
        return $response; 
    }
    
   

}
