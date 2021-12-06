<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransactionMDB;
use App\Helpers\AmuseGamingHelper;
use DB;
use SimpleXMLElement;
class AmuseGamingController extends Controller
{   
    public $provider_db_id;
    public function __construct(){
       $this->provider_db_id = config('providerlinks.amusegaming.provider_db_id');
       $this->API_URL = config('providerlinks.amusegaming.api_url');
       $this->publicKey = config('providerlinks.amusegaming.public_key');
    //    $this->prefix = "UUID";
       $this->secretKey = config('providerlinks.amusegaming.secret_key');
    }
    // AUTHENTICATION_FAILED – Can't reach the player's account at the moment.
    // SESSION_TIMEOUT – Session time limit exceeded.
    // INSUFFICIENT_BALANCE – Player's balance is insufficient.
    // PLAYER_LIMIT_EXCEEDED – Player's bet limit has been reached.

    public function GetPlayerBalance(Request $request){
        
        $data = $request->getContent();
        $xmlparser = new SimpleXMLElement($data);
        // Helper::saveLog("AmuseGaming GetPlayerBalance", 555, json_encode($xmlparser), "");
        $client_details = ProviderHelper::getClientDetails('player_id', $xmlparser->UserId);
        $providers_operator_ID = config('providerlinks.amusegaming.operator.'.$client_details->default_currency.'.operator_id');
        if($providers_operator_ID != $xmlparser->OperatorId){
            $array_data = array(
                "status" => "Error: Invalid OperatorId.",
            );
            $response =  AmuseGamingHelper::arrayToXml($array_data,"<Response/>");
            // Helper::saveLog("AmuseGaming GetPlayerBalance", 555, json_encode($response), "");
            return response($response,200)
                    ->header('Content-Type', 'application/xml');    
        }
        $array_data = array(
            "status" => "ok",
            "balance" => $client_details->balance
        );
        $response =  AmuseGamingHelper::arrayToXml($array_data,"<Response/>");
        // Helper::saveLog("AmuseGaming GetPlayerBalance", 555, json_encode($response), "");
        return response($response,200)
				->header('Content-Type', 'application/xml');
    }

    public function WithdrawAndDeposit(Request $request){
        $data = $request->getContent();
        $xmlparser = new SimpleXMLElement($data);
        Helper::saveLog("AmuseGaming WithdrawAndDeposit", $this->provider_db_id, json_encode($xmlparser), "");
        $client_details = ProviderHelper::getClientDetails('player_id', $xmlparser->UserId);
        $providers_operator_ID = config('providerlinks.amusegaming.operator.'.$client_details->default_currency.'.operator_id');
        if($providers_operator_ID != $xmlparser->OperatorId){
            $array_data = array(
                "status" => "Error: Invalid OperatorId.",
            );
            $response =  AmuseGamingHelper::arrayToXml($array_data,"<Response/>");
            // Helper::saveLog("AmuseGaming GetPlayerBalance", 555, json_encode($response), "");
            return response($response,200)
                    ->header('Content-Type', 'application/xml');    
        }
        if($client_details == null){ 
            $array_data = array(
                "status" => "INSUFFICIENT_BALANCE"
            );
            $response =  AmuseGamingHelper::arrayToXml($array_data,"<Response/>");
            Helper::saveLog("AmuseGaming Session Expire", $this->provider_db_id, json_encode($response), "RESPONSE");
            return response($response,200)
                    ->header('Content-Type', 'application/xml');
        }
        $provider_trans_id = $xmlparser->TransactionId;
        if($xmlparser->WithdrawAmount != 0){
            try{    
                ProviderHelper::idenpotencyTable($provider_trans_id);
            }catch(\Exception $e){
                $array_data = array(
                    "status" => "ok",
                    "balance" => $client_details->balance
                );
                $response =  AmuseGamingHelper::arrayToXml($array_data,"<Response/>");
                $msg = array(
                    'err_message' => $e->getMessage(),
                    'err_line' => $e->getLine(),
                    'err_file' => $e->getFile()
                );
                Helper::saveLog("AmuseGaming Withdraw Transactions Alraedy Exist", $this->provider_db_id, json_encode($response), $msg);
                return response($response,200)
                    ->header('Content-Type', 'application/xml');
            } 
            $debit_responst = $this->debit($xmlparser);
            $debit_responst = new SimpleXMLElement($debit_responst);
            if($debit_responst->status != 'ok'){
                Helper::saveLog("AmuseGaming Withdraw Request", $this->provider_db_id, json_encode($debit_responst), "RESPONSE");
                $response = AmuseGamingHelper::arrayToXml($debit_responst,"<Response/>");
                return response($response,200)
                        ->header('Content-Type', 'application/xml');
            }
            $credit_response = $this->credit($xmlparser);
            // $credit_response = new SimpleXMLElement($credit_response);
            Helper::saveLog("AmuseGaming Deposit Request", $this->provider_db_id, json_encode($credit_response), "RESPONSE");
            $response = $credit_response;
            return response($response,200)
                    ->header('Content-Type', 'application/xml');
        }
        if($xmlparser->WithdrawAmount == 0){
            // if($xmlparser->GameBrand == 'netent'){
            // // if($xmlparser->GameBrand == 'novomatic' || $xmlparser->GameBrand == 'amatic' || $xmlparser->GameBrand == 'quickspin'){
            //     $freespin = true;
            // }else{
                $freespin = false;
            // }
            $credit_response = $this->credit($xmlparser,$freespin);
            // $credit_response = new SimpleXMLElement($credit_response);
            Helper::saveLog("AmuseGaming Deposit Request", $this->provider_db_id, json_encode($credit_response), "RESPONSE");
            $response = $credit_response;
            return response($response,200)
                ->header('Content-Type', 'application/xml');
        }
    }

    public function debit($request){
        Helper::saveLog("AmuseGaming Withdraw Transactions Recieved", $this->provider_db_id, json_encode($request), "Recieved");
        $client_details = ProviderHelper::getClientDetails('player_id', $request->UserId);
        $providers_operator_ID = config('providerlinks.amusegaming.operator.'.$client_details->default_currency.'.operator_id');
        if($providers_operator_ID != $request->OperatorId){
            $array_data = array(
                "status" => "Error: Invalid OperatorId.",
            );
            $response =  AmuseGamingHelper::arrayToXml($array_data,"<Response/>");
            // Helper::saveLog("AmuseGaming GetPlayerBalance", 555, json_encode($response), "");
            return response($response,200)
                    ->header('Content-Type', 'application/xml');    
        }
        $game_details = Helper::findGameDetails('game_code', $this->provider_db_id,$request->GameId);
        $player_balance = $client_details->balance;
        $player_tokenID = $client_details->token_id;
        $provider_round_id = json_decode($request->BetId);
        $provider_trans_id = json_decode($request->TransactionId);
        $provider_bet_amount = json_decode($request->WithdrawAmount);
        $provider_game_code = $request->GameId;
        $provider_game_brand = $request->GameBrand;

        if($player_balance < $provider_bet_amount){
            $array_data = array(
                "status" => "INSUFFICIENT_BALANCE"
            );
            $response =  AmuseGamingHelper::arrayToXml($array_data,"<Response/>");
            Helper::saveLog("AmuseGaming Not Enough Balance", $this->provider_db_id, json_encode($response), "RESPONSE");
            return $response;
            // return response($response,200)
            //         ->header('Content-Type', 'application/xml');
        }
        $gameTransactionData = array(
            "provider_trans_id" => $provider_trans_id,
            "token_id" => $player_tokenID,
            "game_id" => $game_details->game_id,
            "round_id" => $provider_round_id,
            "bet_amount" => $provider_bet_amount,
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
            "round_id" => $provider_round_id,
            "amount" => $provider_bet_amount,
            "game_transaction_type"=> 1,
            "provider_request" =>json_encode($request),
        );
        $game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
        try{
            // Helper::saveLog("AmuseGaming Withdraw Transactions Recieved", $this->provider_db_id, json_encode($provider_bet_amount), json_decode($provider_bet_amount));
            $client_response = ClientRequestHelper::fundTransfer($client_details, $provider_bet_amount,$game_details->game_code,$game_details->game_name,$game_transextension,$game_trans,'debit');
            if(isset($client_response->fundtransferresponse->status->code) 
             && $client_response->fundtransferresponse->status->code == "200"){
                $array_data = array(
                    "status" => "ok",
                    "balance" => $client_details->balance
                );
                $response =  AmuseGamingHelper::arrayToXml($array_data,"<Response/>");
                $update_gametransactionext = array(
                    "mw_response" =>json_encode($response),
                    "mw_request"=>json_encode($client_response->requestoclient),
                    "client_response" =>json_encode($client_response->fundtransferresponse),
                    "transaction_detail" =>json_encode("success"),
                    "general_details" =>json_encode("success"),
                );
                GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_transextension,$client_details);
                $save_bal = DB::table("player_session_tokens")->where("token_id","=",$player_tokenID)->update(["balance" => $client_response->fundtransferresponse->balance]);
                Helper::saveLog("AmuseGaming Withdraw Transactions Success", $this->provider_db_id, json_encode($response), "RESPONSE");
                return $response;
            }elseif(isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "402"){
                $array_data = array(
                    "status" => "INSUFFICIENT_BALANCE"
                );
                $response =  AmuseGamingHelper::arrayToXml($array_data,"<Response/>");
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
                Helper::saveLog("AmuseGaming Withdraw Transactions Failed 402", $this->provider_db_id, json_encode($response), json_encode($request));
                return $response;
                // return response($response,200)
                //     ->header('Content-Type', 'application/xml');
            }else{
                $array_data = array(
                    "status" => "INSUFFICIENT_BALANCE"
                );
                $response =  AmuseGamingHelper::arrayToXml($array_data,"<Response/>");
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
                Helper::saveLog("AmuseGaming Withdraw Transactions Failed Not 402", $this->provider_db_id, json_encode($response), json_encode($request));
                return $response;
                // return response($response,200)
                //     ->header('Content-Type', 'application/xml');
            }
        }catch(\Exception $e){
            $msg = array(
                'err_message' => $e->getMessage(),
                'err_line' => $e->getLine(),
                'err_file' => $e->getFile()
            );
            $array_data = array(
                "status" => "INSUFFICIENT_BALANCE"
            );
            $response =  AmuseGamingHelper::arrayToXml($array_data,"<Response/>");
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
            Helper::saveLog('AmuseGaming Withdraw Transactions Failed Catch Error', $this->provider_db_id, json_encode($response,JSON_FORCE_OBJECT), $msg);
            return $response;
            // return response($response,200)
            //         ->header('Content-Type', 'application/xml');
        }
    }

    public function credit($request,$freespin=false){
        $client_details = ProviderHelper::getClientDetails('player_id', $request->UserId);
        $providers_operator_ID = config('providerlinks.amusegaming.operator.'.$client_details->default_currency.'.operator_id');
        if($providers_operator_ID != $request->OperatorId){
            $array_data = array(
                "status" => "Error: Invalid OperatorId.",
            );
            $response =  AmuseGamingHelper::arrayToXml($array_data,"<Response/>");
            // Helper::saveLog("AmuseGaming GetPlayerBalance", 555, json_encode($response), "");
            return response($response,200)
                    ->header('Content-Type', 'application/xml');    
        }
        $game_details = Helper::findGameDetails('game_code', $this->provider_db_id,$request->GameId);
        $player_balance = $client_details->balance;
        $player_tokenID = $client_details->token_id;
        $provider_round_id = json_decode($request->BetId);
        $provider_trans_id = json_decode($request->TransactionId);
        $provider_game_code = json_decode($request->GameId);
        $provider_game_brand = $request->GameBrand;
        $checkTrans = GameTransactionMDB::findGameTransactionDetails($provider_round_id,'round_id',false,$client_details);
        Helper::saveLog("AmuseGaming Deposit Transactions findGameTransactionDetails", $this->provider_db_id, json_encode($checkTrans), "bet details");
        if($freespin == false){
            $provider_win_amount = json_decode($request->DepositAmount);
            $win_amount = $checkTrans->pay_amount + $provider_win_amount;
            Helper::saveLog("AmuseGaming Deposit Transactions freespin=false", $this->provider_db_id, $provider_win_amount, $win_amount);
        }else{
            $provider_win_amount = json_decode($request->DepositAmount) - $checkTrans->pay_amount;
            $win_amount = $checkTrans->pay_amount + $provider_win_amount;
            Helper::saveLog("AmuseGaming Deposit Transactions freespin=false", $this->provider_db_id, $provider_win_amount, $win_amount);
        }
        $checkTransExt = GameTransactionMDB::findGameExt($provider_trans_id,2,'transaction_id',$client_details);
        $income = $checkTrans->bet_amount - $win_amount;
        $entry_id = $win_amount > 0 ? 2 : 1;
        $win = $win_amount > 0 ? 1 : 0;
        if($checkTransExt != 'false'){
            $array_data = array(
                "status" => "ok",
                "balance" => $client_details->balance
            );
            $response =  AmuseGamingHelper::arrayToXml($array_data,"<Response/>");
            Helper::saveLog("AmuseGaming Deposit Transactions Duplicate", $this->provider_db_id, json_encode($response), json_encode($request));
            return $response;
        }
        $balance = $client_details->balance + $win_amount;
        $array_data = array(
            "status" => "ok",
            "balance" => $balance
        );
        $response =  AmuseGamingHelper::arrayToXml($array_data,"<Response/>");
        $create_gametransactionext = array(
            "game_trans_id" => $checkTrans->game_trans_id,
            "provider_trans_id" => $provider_trans_id,
            "round_id" => $provider_round_id,
            "amount" => $provider_win_amount,
            "game_transaction_type"=> 2,
            "provider_request" => json_encode($request),
            "mw_response" => json_encode($response)
        );
        $game_trans_ext_v2 = GameTransactionMDB::createGameTransactionExt($create_gametransactionext,$client_details);
        try{
            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "provider" => 'AmuseGaming',
                    "game_trans_ext_id" => $game_trans_ext_v2,
                    "client_connection_name" => $client_details->connection_name,
                ],
                "provider" => [
                    "provider_request" => $request,
                    "provider_trans_id"=>$provider_trans_id,
                    "provider_round_id"=>$provider_round_id,
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
            ClientRequestHelper::fundTransfer_TG($client_details, $provider_win_amount, $game_details->game_code, $game_details->game_name, $checkTrans->game_trans_id, 'credit', false, $action_payload);
            $save_bal = DB::table("player_session_tokens")->where("token_id","=",$player_tokenID)->update(["balance" => $balance]);
            Helper::saveLog("AmuseGaming Deposit Transactions Success", $this->provider_db_id, json_encode($response), json_encode($request));
            return $response;
        }catch(\Exception $e){
            $msg = array(
                'error' => '1',
                'message' => $e->getMessage(),
            );
            $array_data = array(
                "status" => "AUTHENTICATION_FAILED"
            );
            $response =  AmuseGamingHelper::arrayToXml($array_data,"<Response/>");
            $update_gametransactionext = array(
                "mw_response" =>json_encode($response),
                "mw_request"=>"Failed send",
                "client_response" =>json_encode($msg),
                "transaction_detail" =>json_encode("FAILED"),
                "general_details" =>json_encode("FAILED")
            );
            GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_v2,$client_details);
            $updateGameTransaction = [
                "win" => 2,
                'trans_status' => 5
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $checkTrans->game_trans_id, $client_details);
            Helper::saveLog('AmuseGaming Withdraw Transactions Failed Catch Error', $this->provider_db_id, json_encode($response), $msg);
            return $response;
        }
    }

    public function Cancel(Request $request){
        $data = $request->getContent();
        $xmlparser = new SimpleXMLElement($data);
        Helper::saveLog("AmuseGaming Cancel", $this->provider_db_id, json_encode($xmlparser), "REQUEST");
        $client_details = ProviderHelper::getClientDetails('player_id', $xmlparser->UserId);
        $providers_operator_ID = config('providerlinks.amusegaming.operator.'.$client_details->default_currency.'.operator_id');
        if($providers_operator_ID != $request->OperatorId){
            $array_data = array(
                "status" => "Error: Invalid OperatorId.",
            );
            $response =  AmuseGamingHelper::arrayToXml($array_data,"<Response/>");
            // Helper::saveLog("AmuseGaming GetPlayerBalance", 555, json_encode($response), "");
            return response($response,200)
                    ->header('Content-Type', 'application/xml');    
        }
        $provider_trans_id = json_decode($xmlparser->TransactionId);
        $game_trans = GameTransactionMDB::findGameTransactionDetails($provider_trans_id,'transaction_id',false,$client_details);
        if($game_trans != 'false'){
            $checkExt = GameTransactionMDB::findGameExt($provider_trans_id,3,'transaction_id',$client_details);
            $game_details = Helper::findGameDetails('game_id', $this->provider_db_id, $game_trans->game_id);
            if($checkExt != 'false'){
                $array_data = array(
                    "status" => "ok",
                    "balance" => $client_details->balance
                );
                $response =  AmuseGamingHelper::arrayToXml($array_data,"<Response/>");
                Helper::saveLog("AmuseGaming Cancel Transactions Duplicate", $this->provider_db_id, json_encode($response), json_encode($request));
                return response($response,200)
                    ->header('Content-Type', 'application/xml');
            }
            $array_data = array(
                "status" => "ok",
                "balance" => $client_details->balance
            );
            $response =  AmuseGamingHelper::arrayToXml($array_data,"<Response/>");
            $create_gametransactionext = array(
                "game_trans_id" => $game_trans->game_trans_id,
                "provider_trans_id" => $provider_trans_id,
                "round_id" => $game_trans->round_id,
                "amount" => $game_trans->bet_amount,
                "game_transaction_type"=> 3,
                "provider_request" => json_encode($xmlparser),
                "mw_response" => json_encode($response)
            );
            $game_trans_ext_v2 = GameTransactionMDB::createGameTransactionExt($create_gametransactionext,$client_details);
            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "provider" => 'AmuseGaming',
                    "game_trans_ext_id" => $game_trans_ext_v2,
                    "client_connection_name" => $client_details->connection_name,
                ],
                "provider" => [
                    "provider_request" => $xmlparser,
                    "provider_trans_id"=>$provider_trans_id,
                    "provider_round_id"=>$game_trans->round_id,
                ],
                "mwapi" => [
                    "roundId"=> $game_trans->game_trans_id,
                    "type" => 3,
                    "game_id" => $game_details->game_id,
                    "player_id" => $client_details->player_id,
                    "mw_response" => $response,
                ]
            ];
            ClientRequestHelper::fundTransfer_TG($client_details, $game_trans->bet_amount, $game_details->game_code, $game_details->game_name, $game_trans->game_trans_id, 'credit', true, $action_payload);
            $updateGameTransaction = [
                'win' => 4,
                'pay_amount' => $game_trans->bet_amount,
                'income' => 0,
                'entry_id' => 2,
                'trans_status' => 2
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans->game_trans_id, $client_details);
            Helper::saveLog("AmuseGaming Cancel Transactions Success", $this->provider_db_id, json_encode($response), json_encode($request));
                return response($response,200)
                    ->header('Content-Type', 'application/xml');
        }else{
            $array_data = array(
                "status" => "ok",
                "balance" => $client_details->balance
            );
            $response =  AmuseGamingHelper::arrayToXml($array_data,"<Response/>");
            Helper::saveLog("AmuseGaming Cancel Bet Not Found", $this->provider_db_id, json_encode($response), "RESPONSE");
            return response($response,200)
                ->header('Content-Type', 'application/xml');
        }

    }


    public function getGamelist(Request $request){
        $result = AmuseGamingHelper::AmuseGamingGameList($request->brand,$request->channel,$request->currency);
        return response()->json($result);
    }
}
