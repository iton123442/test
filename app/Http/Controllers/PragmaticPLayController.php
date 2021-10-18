<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ProviderHelper;
use DB;
use App\Helpers\Helper;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use App\Helpers\AWSHelper;
use App\Models\GameTransactionMDB;

class PragmaticPLayController extends Controller
{
    public $key;
    public $provider_id = 26; //26 


    public function __construct(){
    	$this->key = config('providerlinks.tpp.secret_key');
    }

    public function authenticate(Request $request)
    {
        $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);
        
        Helper::saveLog('PP authenticate', $this->provider_id, json_encode($data) , "");

        // $hash = md5('providerId='.$data->providerId.'&token='.$data->token.$this->key);
        $dataSort = json_decode($json_encode, true);
        $hash = $this->hashParam($dataSort);

        if($hash != $data->hash){
            $response = [
                "error" => 5,
                "decription" => "Hash don't match!"
            ];
            return $response;
            Helper::saveLog("PP hash error", $this->provider_id, json_encode($data), $response);
        }
               
        $providerId = $data->providerId;
        $hash = $data->hash;
        $token = $data->token;
        $client_details = ProviderHelper::getClientDetails('token',$token);
       
        if($client_details != null)
        {
            $currency = $client_details->default_currency;
            $country = $client_details->country_code; 
            $balance = $client_details->balance; 
            $userid = "TGaming_".$client_details->player_id;
            $save_bal = DB::table("player_session_tokens")->where("token_id","=",$client_details->token_id)->update(["balance" => floatval(number_format($balance, 2, '.', ''))]); #val
            $response = array(
                "userId" => $userid,
                "currency" => $currency,
                "cash" => floatval(number_format($balance, 2, '.', '')),
                "bonus" => 0.00,
                "country" => $country,
                "jurisdiction" => "99",
                "error" => 0,
                "decription" => "Success"
            );

            Helper::saveLog('PP authenticate', $this->provider_id, json_encode($data) , $response);

            
        }else{
            $response = [
                "error" => 4,
                "decription" => "Success"
            ];
        }

        return $response;
    }

    public function balance(Request $request)
    {
        
        $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);
        $dataSort = json_decode($json_encode, true);
        $hash = $this->hashParam($dataSort);
        if($hash != $data->hash){
            $response = [
                "error" => 5,
                "decription" => "Hash don't match!"
            ];
            return $response;
            Helper::saveLog("PP hash error", $this->provider_id, json_encode($data), $response);
        }
        $playerId = ProviderHelper::explodeUsername('_',$data->userId);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        $response = array(
            "currency" => $client_details->default_currency,
            "cash" => floatval(number_format($client_details->balance, 2, '.', '')),
            "bonus" => 0.00,
            "error" => 0,
            "description" => "Success"
        );
        return $response;
    }

    public function bet(Request $request)
    {
        $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);

        $dataSort = json_decode($json_encode, true);

        $hash = $this->hashParam($dataSort);

        if($hash != $data->hash){
            $response = [
                "error" => 5,
                "decription" => "Invalid Hash"
            ];
            return $response;
            Helper::saveLog("PP hash error", $this->provider_id, json_encode($data), $response);
        }

        
        $game_details = Helper::findGameDetails('game_code', $this->provider_id, $data->gameId);
        $playerId = ProviderHelper::explodeUsername('_',$data->userId);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        
        AWSHelper::saveLog('TPP bet recieved', $this->provider_id, json_encode($data), "bet received");
      
        $tokenId = $client_details->token_id;
        $game_code = $data->gameId;
        $bet_amount = $data->amount;
        $roundId = $data->roundId;
        $provider_trans_id = $data->reference;
        $bet_payout = 0; // Bet always 0 payout!
        $method = 1; // 1 bet, 2 win
        $win_or_lost = 4; // 0 Lost, 1 win, 3 draw, 4 refund, 5 processing
        $payout_reason = 'Bet';
        $income = $data->amount;

        if($bet_amount > $client_details->balance){
            $response = array(
                "cash" => floatval(number_format($client_details->balance, 2, '.', '')),
                "error" => 1,
                "description" => "Not Enough Balance"
            );
            Helper::saveLog('PP bet not enough balance', $this->provider_id,json_encode($data) , $response);
            return $response;
        }
       
        // $checkGameTrans = DB::select("SELECT game_trans_id FROM game_transactions WHERE provider_trans_id = '".$data->reference."' AND round_id = '".$data->roundId."' ");
        $checkGameTrans = GameTransactionMDB::findGameTransactionDetails($data->reference,'transaction_id',false,$client_details);
        if($checkGameTrans != 'false'){
            $response = array(
                "transactionId" => $checkGameTrans->game_trans_id,
                "currency" => $client_details->default_currency,
                "cash" => floatval(number_format($client_details->balance, 2, '.', '')),
                "bonus" => 0.00,
                "usedPromo" => 0,
                "error" => 0,
                "description" => "Success"
            );
            Helper::saveLog('PP bet duplicate', $this->provider_id,json_encode($data) , $response);
            return $response;
        }
        
        // $checkDoubleBet = DB::select("SELECT game_trans_id,bet_amount FROM game_transactions WHERE round_id = '".$data->roundId."' ");
        $checkDoubleBet = GameTransactionMDB::findGameTransactionDetails($data->roundId,'round_id',false,$client_details);
        if($checkDoubleBet != 'false'){
            // $checkDuplicate = DB::select("SELECT game_transaction_type FROM game_transaction_ext WHERE provider_trans_id = '".$data->reference."' ");
            $checkDuplicate = GameTransactionMDB::findGameExt($data->reference,2,'transaction_id',$client_details);
            if($checkDuplicate != 'false'){
                $response = array(
                    "transactionId" => $checkDoubleBet[0]->game_trans_id,
                    "currency" => $client_details->default_currency,
                    "cash" => floatval(number_format($client_details->balance, 2, '.', '')),
                    "bonus" => 0.00,
                    "usedPromo" => 0,
                    "error" => 0,
                    "description" => "Success"
                );
                Helper::saveLog('PP bet duplicate in double', $this->provider_id,json_encode($data) , $response);
                return $response;
            }
            $amount = $checkDoubleBet->bet_amount + $data->amount;
            // $game_transextension = ProviderHelper::createGameTransExtV2($checkDoubleBet->game_trans_id,$provider_trans_id, $roundId, $data->amount, 1);
            $gameTransactionEXTData = array(
                "game_trans_id" => $checkDoubleBet->game_trans_id,
                "provider_trans_id" => $provider_trans_id,
                "round_id" => $roundId,
                "amount" => $data->amount,
                "game_transaction_type"=> 1,
                "provider_request" =>json_encode($data),
            );
            $game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
             try {
                $client_response = ClientRequestHelper::fundTransfer($client_details, $data->amount,$game_details->game_code,$game_details->game_name,$game_transextension,$checkDoubleBet->game_trans_id,'debit');
                $updateDoubleBet = DB::table('game_transactions')->where('game_trans_id','=',$checkDoubleBet->game_trans_id)->update(["bet_amount" => $amount, "transaction_reason" => "Double Bet"]);
                $response_log = array(
                    "transactionId" => $checkDoubleBet->game_trans_id,
                    "currency" => $client_details->default_currency,
                    "cash" => floatval(number_format($client_response->fundtransferresponse->balance, 2, '.', '')),
                    "bonus" => 0.00,
                    "usedPromo" => 0,
                    "error" => 0,
                    "description" => "Success"
                );
                $trans_details = array(
                    "game_trans_id" => $checkDoubleBet->game_trans_id,
                    "bet_amount" => $amount,
                    "win" => false,
                    "response" => $response_log
                );
                
                if(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "200"){
                    $response = array(
                        "transactionId" => $game_transextension,
                        "currency" => $client_details->default_currency,
                        "cash" => floatval(number_format($client_response->fundtransferresponse->balance, 2, '.', '')),
                        "bonus" => 0.00,
                        "usedPromo" => 0,
                        "error" => 0,
                        "description" => "Success"
                    );
                    $update_gametransactionext = array(
                        "mw_response" =>json_encode($response),
                        "mw_request"=>json_encode($client_response->requestoclient),
                        "client_response" =>json_encode($client_response->fundtransferresponse),
                        "transaction_detail" =>json_encode("success"),
                        "general_details" =>json_encode("success"),
                    );
                    GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_transextension,$client_details);
                    Helper::saveLog('PP bet additional', $this->provider_id,json_encode($data) , $response);
                    return $response;
                }elseif(isset($client_response->fundtransferresponse->status->code) 
                    && $client_response->fundtransferresponse->status->code == "402"){
                        $response = array(
                            "cash" => floatval(number_format($client_details->balance, 2, '.', '')),
                            "error" => 1,
                            "description" => "Not Enough Balance"
                        );
                        Helper::saveLog('PP bet not enough balance', $this->provider_id,json_encode($data) , $response);
                        $update_gametransactionext = array(
                            "mw_response" =>json_encode($response),
                            "mw_request"=>json_encode($client_response->requestoclient),
                            "client_response" =>json_encode($client_response->fundtransferresponse),
                            "transaction_detail" =>json_encode("FAILED"),
                            "general_details" =>json_encode("FAILED")
                        );
                        GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_transextension,$client_details);
                        $updateGameTransaction = [
                            "win" => 2,
                            'trans_status' => 5
                        ];
                        GameTransactionMDB::updateGametransaction($updateGameTransaction, $checkDoubleBet->game_trans_id, $client_details);
                        return $response;
                }else{
                    $response = array(
                        "cash" => floatval(number_format($client_details->balance, 2, '.', '')),
                        "error" => 1,
                        "description" => "Not Enough Balance"
                    );
                    Helper::saveLog('PP bet not enough balance', $this->provider_id,json_encode($data) , $response);
                    $update_gametransactionext = array(
                        "mw_response" =>json_encode($response),
                        "mw_request"=>json_encode($client_response->requestoclient),
                        "client_response" =>json_encode($client_response->fundtransferresponse),
                        "transaction_detail" =>json_encode("FAILED"),
                        "general_details" =>json_encode("FAILED")
                    );
                    GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_transextension,$client_details);
                    $updateGameTransaction = [
                        "win" => 2,
                        'trans_status' => 5
                    ];
                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $checkDoubleBet->game_trans_id, $client_details);
                    return $response;
                }
            } catch (\Exception $e) {
                $response = array(
                    "cash" => floatval(number_format($client_details->balance, 2, '.', '')),
                    "error" => 100,
                    "description" => "Internal server error."
                );
                $msg = array("status" => 'error',"message" => $e->getMessage());
                $update_gametransactionext = array(
                    "mw_response" =>json_encode($response),
                    "mw_request"=> $msg,
                    "client_response" => $msg,
                    "transaction_detail" =>json_encode("FAILED"),
                    "general_details" =>json_encode("FATAL FAILED")
                );
                GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_transextension,$client_details);
                $updateGameTransaction = [
                    "win" => 2,
                    'trans_status' => 5
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $checkDoubleBet->game_trans_id, $client_details);
                Helper::saveLog('PP bet - FATAL ERROR', $this->provider_id, json_encode($data), Helper::datesent());
                return $response;
            }
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
        $gamerecord = GameTransactionMDB::createGametransaction($gameTransactionData,$client_details);
        $gameTransactionEXTData = array(
            "game_trans_id" => $gamerecord,
            "provider_trans_id" => $provider_trans_id,
            "round_id" => $roundId,
            "amount" => $data->amount,
            "game_transaction_type"=> 1,
            "provider_request" =>json_encode($data),
        );
        $game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

        try {
            $client_response = ClientRequestHelper::fundTransfer($client_details, $bet_amount,$game_details->game_code,$game_details->game_name,$game_transextension,$gamerecord,'debit');
            if(isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "200"){
                $response = array(
                    "transactionId" => $gamerecord,
                    "currency" => $client_details->default_currency,
                    "cash" => floatval(number_format($client_response->fundtransferresponse->balance, 2, '.', '')),
                    "bonus" => 0.00,
                    "usedPromo" => 0,
                    "error" => 0,
                    "description" => "Success"
                );
                $update_gametransactionext = array(
                    "mw_response" =>json_encode($response),
                    "mw_request"=>json_encode($client_response->requestoclient),
                    "client_response" =>json_encode($client_response->fundtransferresponse),
                    "transaction_detail" =>json_encode("success"),
                    "general_details" =>json_encode("success"),
                );
                GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_transextension,$client_details);
                $save_bal = DB::table("player_session_tokens")->where("token_id","=",$tokenId)->update(["balance" => $client_response->fundtransferresponse->balance]);
                AWSHelper::saveLog('TPP bet response', $this->provider_id, json_encode($data), "response");
                return $response;
            }elseif(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "402"){
                    $response = array(
                        "cash" => floatval(number_format($client_details->balance, 2, '.', '')),
                        "error" => 1,
                        "description" => "Not Enough Balance"
                    );
                    $update_gametransactionext = array(
                        "mw_response" =>json_encode($response),
                        "mw_request"=>json_encode($client_response->requestoclient),
                        "client_response" =>json_encode($client_response->fundtransferresponse),
                        "transaction_detail" =>json_encode("FAILED"),
                        "general_details" =>json_encode("FAILED")
                    );
                    GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_transextension,$client_details);
                    $updateGameTransaction = [
                        "win" => 2,
                        'trans_status' => 5
                    ];
                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
                    Helper::saveLog('PP bet not enough balance', $this->provider_id,json_encode($data) , $response);
                    return $response;
            }else{
                $response = array(
                    "cash" => floatval(number_format($client_details->balance, 2, '.', '')),
                    "error" => 1,
                    "description" => "Not Enough Balance"
                );
                $update_gametransactionext = array(
                    "mw_response" =>json_encode($response),
                    "mw_request"=>json_encode($client_response->requestoclient),
                    "client_response" =>json_encode($client_response->fundtransferresponse),
                    "transaction_detail" =>json_encode("FAILED"),
                    "general_details" =>json_encode("FAILED")
                );
                GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_transextension,$client_details);
                $updateGameTransaction = [
                    "win" => 2,
                    'trans_status' => 5
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
                Helper::saveLog('PP bet not enough balance', $this->provider_id,json_encode($data) , $response);
                return $response;
            }
          
           
        } catch (\Exception $e) {
            $response = array(
                "cash" => floatval(number_format($client_details->balance, 2, '.', '')),
                "error" => 100,
                "description" => "Internal server error."
            );
            $msg = array("status" => 'error',"message" => $e->getMessage());
            $update_gametransactionext = array(
                "mw_response" =>json_encode($response),
                "mw_request"=>$msg,
                "client_response" =>$msg,
                "transaction_detail" =>json_encode("FAILED - FATAL"),
                "general_details" =>json_encode("FAILED")
            );
            GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_transextension,$client_details);
            $updateGameTransaction = [
                "win" => 2,
                'trans_status' => 5
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
            Helper::saveLog('PP bet - FATAL ERROR', $this->provider_id, json_encode($data), Helper::datesent());
            return $response;
        }

        
    }

    public function result(Request $request)
    {
        $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true); 
        $data = json_decode($json_encode);
        
        AWSHelper::saveLog('TPP result recieved', $this->provider_id, json_encode($data), "recieved");
        $dataSort = json_decode($json_encode, true);
        $hash = $this->hashParam($dataSort);
        if($hash != $data->hash){
            $response = [
                "error" => 5,
                "decription" => "Hash don't match!"
            ];
            AWSHelper::saveLog('TPP result Hash', $this->provider_id, json_encode($data), $response);
            return $response;
        }
        $playerId = ProviderHelper::explodeUsername('_',$data->userId);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        $game_details = Helper::findGameDetails('game_code', $this->provider_id, $data->gameId);

        $token_id = $client_details->token_id;
        $game_id = $game_details->game_id;
        $game_name = $game_details->game_name;
        $game_code = $game_details->game_code;
        $bet_amount = $data->amount;
        $win = $bet_amount > 0 ? 1 : 0;
        $entry_id = $win == 0 ? '1' : '2';
        $provider_trans_id = $data->reference;
        $round_id = $data->roundId;

        $game_trans = GameTransactionMDB::findGameTransactionDetails($round_id,'round_id',false,$client_details);
        $checkExt = GameTransactionMDB::findGameExt($provider_trans_id,2,'transaction_id',$client_details);
        
        if($checkExt  != 'false'){
            $response_log = array(
                "transactionId" => $game_trans->game_trans_id,
                "currency" => $client_details->default_currency,
                "cash" => floatval(number_format($client_details->balance, 2, '.', '')),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success",
            );
            AWSHelper::saveLog('TPP result ext not found', $this->provider_id, json_encode($data), $response);
            return $response_log;
        }
        
        try {

            $income = $game_trans->bet_amount - $data->amount;
            $balance = $client_details->balance + $data->amount;
           
            $create_gametransactionext = array(
                "game_trans_id" =>$game_trans->game_trans_id,
                "provider_trans_id" => $provider_trans_id,
                "round_id" => $round_id,
                "amount" => $data->amount,
                "game_transaction_type"=> 2,
                "provider_request" => json_encode($data)
            );
            $game_trans_ext_v2 = GameTransactionMDB::createGameTransactionExt($create_gametransactionext,$client_details);
            $response = array(
                "transactionId" => $game_trans_ext_v2,
                "currency" => $client_details->default_currency,
                "cash" => floatval(number_format($balance, 2, '.', '')),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success",
            );
            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "provider" => 'tpp',
                    "game_trans_ext_id" => $game_trans_ext_v2,
                    "client_connection_name" => $client_details->connection_name,
                ],
                "provider" => [
                    "provider_request" => $data,
                    "provider_trans_id"=>$provider_trans_id,
                    "provider_round_id"=>$round_id,
                ],
                "mwapi" => [
                    "roundId"=> $game_trans->game_trans_id,
                    "type"=>2,
                    "game_id" => $game_details->game_id,
                    "player_id" => $client_details->player_id,
                    "mw_response" => $response,
                ]
            ];
            $client_response2 = ClientRequestHelper::fundTransfer_TG($client_details, $bet_amount, $game_code, $game_name, $game_trans->game_trans_id, 'credit', false, $action_payload);
            $updateGameTransaction = [
                'win' => $win,
                'pay_amount' => abs($data->amount),
                'income' => $income,
                'entry_id' => $entry_id,
                'trans_status' => 2
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans->game_trans_id, $client_details);
            $update_gametransactionext = array(
                "mw_response" =>json_encode($response),
            );
            GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_v2,$client_details);
            $save_bal = DB::table("player_session_tokens")->where("token_id","=",$token_id)->update(["balance" => $balance]);
            AWSHelper::saveLog('TPP result response', $this->provider_id, json_encode($data), "response");
            return $response;

        } catch (\Exception $e) {
            $msg = array("status" => 'error',"message" => $e->getMessage());
            $response = array(
                "cash" => floatval(number_format($client_details->balance, 2, '.', '')),
                "error" => 100,
                "description" => "Internal server error."
            );
            Helper::saveLog('PP gameWin - FATAL ERROR', $this->provider_id, json_encode($data), Helper::datesent());
            $updateGameTransaction = [
                "win" => 2,
                'trans_status' => 5
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans->game_trans_id, $client_details);
            return $msg;
        }
    }

    public function endRound(Request $request)
    {
        $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);
        AWSHelper::saveLog('PP endRound requestssssss', $this->provider_id, json_encode($data) ,"endRound");
        $dataSort = json_decode($json_encode, true);
        $hash = $this->hashParam($dataSort);
        if($hash != $data->hash){
            $response = [
                "error" => 5,
                "decription" => "Hash dont match!"
            ];
            AWSHelper::saveLog('PP endRound hash', $this->provider_id, json_encode($data) ,$response);
            return $response;
        }
        $playerId = ProviderHelper::explodeUsername('_',$data->userId);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        $game_details = Helper::findGameDetails('game_code', $this->provider_id, $data->gameId);
        $game_trans = GameTransactionMDB::findGameTransactionDetails($data->roundId,'round_id',false,$client_details);
        $checkExt = GameTransactionMDB::findGameExt($data->roundId,2,'round_id',$client_details);
        $provider_trans_id = $game_trans->provider_trans_id;
        $round_id = $data->roundId;
        $bet_amount = 0;
        $entry_id = 2;
        $game_name = $game_details->game_name;
        $game_code = $game_details->game_code;
        $token_id = $client_details->token_id;
        $win = $bet_amount > 0 ? 1 : 0;
        if($checkExt != 'false'){
            $response = array(
                "cash" => floatval(number_format($client_details->balance, 2, '.', '')),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success",
            );
            AWSHelper::saveLog('PP endRound/result already exist', $this->provider_id, json_encode($data) ,$response);
            return $response;
        }
        try {
            $balance = $client_details->balance;
            $create_gametransactionext = array(
                "game_trans_id" =>$game_trans->game_trans_id,
                "provider_trans_id" => $provider_trans_id,
                "round_id" => $round_id,
                "amount" => $bet_amount,
                "game_transaction_type"=> 2,
                "provider_request" => json_encode($data)
            );
            $game_trans_ext_v2 = GameTransactionMDB::createGameTransactionExt($create_gametransactionext,$client_details);
            $response = array(
                "cash" => floatval(number_format($balance, 2, '.', '')),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success",
            );
            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "provider" => 'tpp',
                    "game_trans_ext_id" => $game_trans_ext_v2,
                    "client_connection_name" => $client_details->connection_name,
                ],
                "provider" => [
                    "provider_request" => $data,
                    "provider_trans_id"=>$provider_trans_id,
                    "provider_round_id"=>$round_id,
                ],
                "mwapi" => [
                    "roundId"=> $game_trans->game_trans_id,
                    "type"=> 2,
                    "game_id" => $game_details->game_id,
                    "player_id" => $client_details->player_id,
                    "mw_response" => $response,
                ]
            ];
            $updateGameTransaction = [
                'win' => 0,
                'income' => $game_trans->bet_amount,
                'entry_id' => 1,
                'trans_status' => 2
            ];
            ClientRequestHelper::fundTransfer_TG($client_details, $bet_amount, $game_code, $game_name, $game_trans->game_trans_id, 'credit', false, $action_payload);
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans->game_trans_id, $client_details);
            $update_gametransactionext = array(
                "mw_response" =>json_encode($response),
            );
            GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_v2,$client_details);
            $save_bal = DB::table("player_session_tokens")->where("token_id","=",$token_id)->update(["balance" => $balance]);
            AWSHelper::saveLog('TPP endRound response', $this->provider_id, json_encode($data), $response);
            return $response;

        } catch (\Exception $e) {
            $msg = array("status" => 'error',"message" => $e->getMessage());
            ProviderHelper::updatecreateGameTransExt($game_trans->game_trans_id, 'FAILED', $msg, 'FAILED', $e->getMessage(), 'FAILED', 'FAILED');
            Helper::saveLog('PP endRound - FATAL ERROR', $this->provider_id, json_encode($data), $msg);
            return $msg;
        }
    }
    
    public function getBalancePerGame(Request $request)
    {
        $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);
        Helper::saveLog('PP getBalancePerGame request', $this->provider_id, json_encode($data) ,"getBalancePerGame");
        $playerId = ProviderHelper::explodeUsername('_',$data->userId);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        $gameIdList = explode(",", $data->gameIdList);
        $response = array();
        foreach($gameIdList as $item):
            $data = array(
                "gameID" => $item,
                "cash" => floatval(number_format($client_details->balance, 2, '.', '')),
                "bonus" => 0.00
            );
            array_push($response,$data);
        endforeach; 
        $response = array(
             "gamesBalances" => $response
        );
        return $response;
    }

    public function sessionExpired(Request $request)
    {
        $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);
        
        Helper::saveLog('PP sessionExpired request', $this->provider_id, json_encode($data) ,"sessionExpired");

        $dataSort = json_decode($json_encode, true);
        $hash = $this->hashParam($dataSort);

        if($hash != $data->hash){
            $response = [
                "error" => 5,
                "decription" => "Hash don't match!"
            ];
            return $response;
            Helper::saveLog("PP hash error", $this->provider_id, json_encode($data), $response);
        }

        $response = array(
            "error" => 0,
            "description" => "Success"
        );

        Helper::saveLog('PP sessionExpired request', $this->provider_id, json_encode($data) , $response);
        return $response;

    }

    public function refund(Request $request)
    {
        $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);

        Helper::saveLog('PP refund request', $this->provider_id, json_encode($data) , "");

        // $hash = md5('amount='.$data->amount.'&gameId='.$data->gameId.'&providerId='.$data->providerId.'&reference='.$data->reference.'&roundDetails='.$data->roundDetails.'&roundId='.$data->roundId.'&timestamp='.$data->timestamp.'&userId='.$data->userId.$this->key);
        $dataSort = json_decode($json_encode, true);
        $hash = $this->hashParam($dataSort);

        if($hash != $data->hash){
            $response = [
                "error" => 5,
                "decription" => "Hash don't match!"
            ];
            return $response;
            Helper::saveLog("PP hash error", $this->provider_id, json_encode($data), $response);
        }
        $playerId = ProviderHelper::explodeUsername('_',$data->userId);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        $game_trans = GameTransactionMDB::findGameTransactionDetails($data->roundId,'round_id',false,$client_details);
        $checkExt = GameTransactionMDB::findGameExt($data->reference,3,'transaction_id',$client_details);
        $checkExt2 = GameTransactionMDB::findGameExt($data->reference,1,'transaction_id',$client_details);
        Helper::saveLog("PP hash error", $this->provider_id, json_encode($data), $checkExt2);
        if($checkExt2 == 'false'){
            $response = array(
                "error" => 0,
                "description" => "Success"
            );
            return $response;
        }
        // return count($game_trans);
        if($checkExt == 'false'){
            $game_details = Helper::findGameDetails('game_code', $this->provider_id, $data->gameId);
            $roundId = $data->roundId;
            $provider_trans_id = $data->reference;
            $bet_amount = $data->amount;
            $create_gametransactionext = array(
                "game_trans_id" =>$game_trans->game_trans_id,
                "provider_trans_id" => $provider_trans_id,
                "round_id" => $roundId,
                "amount" => $bet_amount,
                "game_transaction_type"=> 3,
                "provider_request" => json_encode($data)
            );
            $game_trans_ext_v2 = GameTransactionMDB::createGameTransactionExt($create_gametransactionext,$client_details);
            $response = array(
                "transactionId" => $game_trans_ext_v2,
                "error" => 0,
                "description" => "Success"
            );
            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "provider" => 'tpp',
                    "game_trans_ext_id" => $game_trans_ext_v2,
                    "client_connection_name" => $client_details->connection_name,
                ],
                "provider" => [
                    "provider_request" => $data,
                    "provider_trans_id"=>$provider_trans_id,
                    "provider_round_id"=>$roundId,
                ],
                "mwapi" => [
                    "roundId"=> $game_trans->game_trans_id,
                    "type" => 3,
                    "game_id" => $game_details->game_id,
                    "player_id" => $client_details->player_id,
                    "mw_response" => $response,
                ]
            ];
            ClientRequestHelper::fundTransfer_TG($client_details, $bet_amount, $game_details->game_code, $game_details->game_name, $game_trans->game_trans_id, 'credit', true, $action_payload);
            $updateGameTransaction = [
                'win' => 4,
                'pay_amount' => $bet_amount,
                'income' => 0,
                'entry_id' => 2,
                'trans_status' => 2
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans->game_trans_id, $client_details);
            $update_gametransactionext = array(
                "mw_response" =>json_encode($response),
            );
            GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_v2,$client_details);
            Helper::saveLog('PP refund request', $this->provider_id, json_encode($data) , $response);
            return $response;
        }else{
            $response = array(
                "error" => 0,
                "description" => "Success"
            );
            return $response;
        }

    }

    public function bonusWin(Request $request)
    {
        $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);

        Helper::saveLog('PP bonus', $this->provider_id, json_encode($data) , "");

        $game_trans = DB::table("game_transactions")->where("round_id","=",$data->roundId)->first();
        $game_details = DB::table("games")->where("game_id","=",$game_trans->game_id)->first();
        
        $playerId = ProviderHelper::explodeUsername('_',$data->userId);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);

        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$client_details->client_access_token
            ]
        ]);
        
    }

    public function promoWin(Request $request){

        $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);

        Helper::saveLog('PP promoWin request', $this->provider_id, json_encode($data) , "");

        $dataSort = json_decode($json_encode, true);
        $hash = "amount=$data->amount&campaignId=$data->campaignId&campaignType=$data->campaignType&currency=$data->currency&providerId=$data->providerId&reference=$data->reference&timestamp=$data->timestamp&userId=$data->userId$this->key";
        $hash = md5($hash);
        if($hash != $data->hash){
            $response = [
                "error" => 5,
                "decription" => "Hash don't match!"
            ];
            return $response;
            Helper::saveLog("PP hash error", $this->provider_id, json_encode($data), $response);
        }

        $playerId = ProviderHelper::explodeUsername('_',$data->userId);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        
        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$client_details->client_access_token
            ]
        ]);


        try {
        $game_details = Helper::findGameDetails('game_code', $this->provider_id, 'vs25pyramid');
        $tokenId = $client_details->token_id;
        $roundId = $data->campaignId;
        $checkGameTrans = DB::table('game_transactions')->where("round_id","=",$roundId)->where("provider_trans_id","=", $data->reference)->get();
        // return count($checkGameTrans);
        // $checkExt = ProviderHelper::findGameExt($roundId, '2', 'round_id');

        if(count($checkGameTrans) > 0){
            $response_log = array(
                "transactionId" => $checkGameTrans[0]->game_trans_id,
                "currency" => $client_details->default_currency,
                "cash" => floatval(number_format($client_details->balance, 2, '.', '')),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success",
            );

            return $response_log;
        }

        // vs25pyramid
        // Pyramid King
        $responseDetails = $this->responsetosend($client_details->client_access_token, $client_details->client_api_key, "vs25pyramid", "Pyramid King", $client_details->client_player_id, $client_details->player_token, $data->amount, $client, $client_details->fund_transfer_url, "credit",$client_details->default_currency);

        $gametrans = ProviderHelper::createGameTransaction($tokenId, $game_details->game_id, 0.00, $data->amount, 2, 1, "Tournament", "Promo Win ", 0- $data->amount, $data->reference, $roundId);
        
        $response_log = array(
            "transactionId" => $gametrans,
            "currency" => $client_details->default_currency,
            "cash" => floatval(number_format($responseDetails['client_response']->fundtransferresponse->balance, 2, '.', '')),
            "bonus" => 0,
            "error" => 0,
            "description" => "Success",
        );

        $game_trans_ext = ProviderHelper::createGameTransExt( $gametrans, $data->reference, $roundId, $data->amount, 2, $data, $response_log, $responseDetails['requesttosend'], $responseDetails['client_response'], "Promo Win Tournament");
        
        $response = array(
            "transactionId" => $game_trans_ext,
            "currency" => $client_details->default_currency,
            "cash" => floatval(number_format($responseDetails['client_response']->fundtransferresponse->balance, 2, '.', '')),
            "bonus" => 0,
            "error" => 0,
            "description" => "Success",
        );
        Helper::saveLog('PP promoWin response', $this->provider_id, json_encode($data) , $response);
        return $response;
        }catch(\Exception $e){
            $error = [
                'error' => $e->getMessage()
            ];
            Helper::saveLog('PP ERROR', $this->provider_id, json_encode($data), $e->getMessage());
            return $error;
        }
    }

    public function jackpotWin(Request $request){
        $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);

        Helper::saveLog('PP jackpotWin request', $this->provider_id, json_encode($data) , "");

        $dataSort = json_decode($json_encode, true);
        $hash = $this->hashParam($dataSort);
        if($hash != $data->hash){
            $response = [
                "error" => 5,
                "decription" => "Hash don't match!"
            ];
            return $response;
            Helper::saveLog("PP hash error", $this->provider_id, json_encode($data), $response);
        }

        $game_trans = DB::table("game_transactions")->where("round_id","=",$data->roundId)->first();
        $game_details = DB::table("games")->where("game_id","=",$game_trans->game_id)->first();
        
        $playerId = ProviderHelper::explodeUsername('_',$data->userId);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);

        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$client_details->client_access_token
            ]
        ]);

        $responseDetails = $this->responsetosend($client_details->client_access_token,$client_details->client_api_key, $game_details->game_code, $game_details->game_name, $client_details->client_player_id, $client_details->player_token, $data->amount, $client, $client_details->fund_transfer_url, "credit", $client_details->default_currency );

        $game_trans = DB::table('game_transactions')->where("round_id","=",$data->roundId)->get();

        $income = $game_trans[0]->bet_amount - $data->amount;
        $win = 1;
        
        $updateGameTrans = DB::table('game_transactions')
            ->where("round_id","=",$data->roundId)
            ->update([
                "win" => $win,
                "pay_amount" => $data->amount,
                "income" => $income,
                "entry_id" => 2,
                "payout_reason" => "Jackpot Win"
            ]);
    
        $response = array(
            "transactionId" => $game_trans[0]->game_trans_id,
            "currency" => $client_details->default_currency,
            "cash" => floatval(number_format($responseDetails['client_response']->fundtransferresponse->balance, 2, '.', '')),
            "bonus" => 0,
            "error" => 0,
            "description" => "Success",
        );

        $trans_details = array(
            "game_trans_id" => $game_trans[0]->game_trans_id,
            "bet_amount" => $game_trans[0]->bet_amount,
            "pay_amount" => $data->amount,
            "win" => true,
            "response" => $response
            );

        $game_trans_ext = ProviderHelper::createGameTransExt($game_trans[0]->game_trans_id, $game_trans[0]->provider_trans_id, $game_trans[0]->round_id, $data->amount, 2, $data, $response, $responseDetails['requesttosend'], $responseDetails['client_response'], $trans_details);

        return $response;

    }

    public function hashParam($sortData){
        ksort($sortData);
        $param = "";
        $i = 0;
        foreach($sortData as $key => $item){
            if($key != 'hash'){
                if($i == 0){
                    $param .= $key ."=". $item;
                }else{
                    $param .= "&".$key ."=". $item;
                }
                $i++;
            }
        }
        $str = str_replace("\n","",$param.$this->key);
        $clean = str_replace("\r","",$str);
        return $hash = md5($clean);
    }

    public function checkGameTrans($round_id, $game_code){
        $check = DB::table('game_transactions as gs')
                ->select('*')
                ->leftJoin('games as g','gs.game_id','=','g.game_id')
                ->where('g.game_code','=',$game_code)
                ->where('gs.round_id','=',$round_id)->get();
        if(count($check) > 0){
            return "true";
        }else{
            return "false";
        }

    }
}
