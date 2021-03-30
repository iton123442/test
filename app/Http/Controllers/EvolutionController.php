<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ClientRequestHelper;
use App\Helpers\EVGHelper;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Models\GameTransaction;
use DB;
class EvolutionController extends Controller
{
    //
    // # Rollback 01/06/20
    public function authentication(Request $request){
        if($request->has("authToken")&& $request->authToken == config("providerlinks.evolution.owAuthToken")){
            $data = json_decode($request->getContent(),TRUE);
            $client_details = ProviderHelper::getClientDetails("player_id",$data["userId"]);
            if($client_details){
                $client_response=ClientRequestHelper::playerDetailsCall($client_details->player_token);
                $msg = array(
                    "status"=>"OK",
                    "sid" => $data["sid"],
                    "uuid"=>$data["uuid"],
                );
                return response($msg,200)->header('Content-Type', 'application/json');
            }
            else{
                $msg = array(
                    "status"=>"INVALID_PARAMETER",
                    "uuid"=>$data["uuid"],
                );
                return response($msg,200)->header('Content-Type', 'application/json');
            }
        }
        else{
            $data = json_decode($request->getContent(),TRUE);
            $msg = array(
                "status"=>"INVALID_TOKEN_ID",
                "uuid"=>$data["uuid"],
            );
            return response($msg,200)->header('Content-Type', 'application/json');
        }
    }
    public function sid(Request $request){
        if($request->has("authToken")&& $request->authToken == config("providerlinks.evolution.owAuthToken")){
            $data = json_decode($request->getContent(),TRUE);
            $client_details = ProviderHelper::getClientDetails("player_id",$data["userId"]);
            if($client_details){
                $client_response=ClientRequestHelper::playerDetailsCall($client_details->player_token);
                $msg = array(
                    "status"=>"OK",
                    "sid" => substr("abcdefghijklmnopqrstuvwxyz1234567890", mt_rand(0, 25), 1).substr(md5(time()), 1),
                    "uuid"=>$data["uuid"],
                );
                return response($msg,200)->header('Content-Type', 'application/json');
            }
            else{
                $msg = array(
                    "status"=>"INVALID_PARAMETER",
                    "uuid"=>$data["uuid"],
                );
                return response($msg,200)->header('Content-Type', 'application/json');
            }
        }
        else{
            $data = json_decode($request->getContent(),TRUE);
            $msg = array(
                "status"=>"INVALID_TOKEN_ID",
                "uuid"=>$data["uuid"],
            );
            return response($msg,200)->header('Content-Type', 'application/json');
        }
    }
    public function balance(Request $request){
        if($request->has("authToken")&& $request->authToken == config("providerlinks.evolution.owAuthToken")){
            $data = json_decode($request->getContent(),TRUE);
            $client_details = ProviderHelper::getClientDetails("player_id",$data["userId"]);
            if($client_details){
                $client_response=ClientRequestHelper::playerDetailsCall($client_details->player_token);
                Helper::saveLog('BALANCE(EVG)', 12, json_encode(["balancebefore"=>$client_details->balance]), ["ONBALANCE"]);
                $msg = array(
                    "status"=>"OK",
                    "balance" => (float)number_format($client_details->balance,2,'.', ''),
                    "uuid"=>$data["uuid"],
                );
                return response($msg,200)->header('Content-Type', 'application/json');
            }
            else{
                $msg = array(
                    "status"=>"INVALID_PARAMETER",
                    "uuid"=>$data["uuid"],
                );
                return response($msg,200)->header('Content-Type', 'application/json');
            }
        }
        else{
            $data = json_decode($request->getContent(),TRUE);
            $msg = array(
                "status"=>"INVALID_TOKEN_ID",
                "uuid"=>$data["uuid"],
            );
            return response($msg,200)->header('Content-Type', 'application/json');
        }
    }

    public function debit(Request $request){
        $startTime =  microtime(true);
        if($request->has("authToken")&& $request->authToken == config("providerlinks.evolution.owAuthToken")){
            $data = json_decode($request->getContent(),TRUE);
            $client_details = ProviderHelper::getClientDetails("player_id",$data["userId"]);
            if($client_details){
                $game_transaction = Helper::checkGameTransaction($data["transaction"]["id"]);
                if($game_transaction){
                    $msg = array(
                        "status"=>"BET_ALREADY_EXIST",
                        "uuid"=>$data["uuid"],
                    );
                    return response($msg,200)->header('Content-Type', 'application/json');
                }
                else{
                    $game_details = EVGHelper::getGameDetails($data["game"]["details"]["table"]["id"],null,config("providerlinks.evolution.env"));
                    $TransactionData = array(
                        "provider_trans_id" => $data["transaction"]["id"],
                        "token_id" => $client_details->token_id,
                        "game_id" => $game_details->game_id,
                        "round_id" => $data["transaction"]["refId"],
                        "bet_amount" => round($data["transaction"]["amount"],2),
                        "win" =>5,
                        "pay_amount" =>0,
                        "income" =>round($data["transaction"]["amount"],2),
                        "entry_id" =>1,
                    );
                    $game_transactionid = GameTransaction::createGametransaction($TransactionData);
                    $betgametransactionext = array(
                        "game_trans_id" => $game_transactionid,
                        "round_id" =>$data["transaction"]["refId"],
                        "provider_trans_id" => $data["transaction"]["id"],
                        "amount" =>round($data["transaction"]["amount"],2),
                        "game_transaction_type"=>1,
                        "provider_request" =>json_encode($data),
                    );
                    $betGametransactionExtId = GameTransaction::createGameTransactionExt($betgametransactionext);
                    $client_response = ClientRequestHelper::fundTransfer($client_details,round($data["transaction"]["amount"],2),$game_details->game_code,$game_details->game_name,$betGametransactionExtId,$game_transactionid,"debit");
                    $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
                    if(isset($client_response->fundtransferresponse->status->code) 
                    && $client_response->fundtransferresponse->status->code == "200"){
                        ProviderHelper::_insertOrUpdate($client_details->token_id,$balance);
                        $msg = array(
                            "status"=>"OK",
                            "balance"=>(float)$balance,
                            "uuid"=>$data["uuid"],
                        );
                        Helper::updateGameTransactionExt($betGametransactionExtId,$client_response->requestoclient,$msg,$client_response);
                        
                       // Helper::saveLog('responseTime(EVG)', 12, json_encode(["type"=>"debitproccess","stating"=>$startTime,"response"=>microtime(true)]), ["response"=>microtime(true) - $startTime,"mw_response"=> microtime(true) - $startTime - $client_response_time,"clientresponse"=>$client_response_time]);
                        return response($msg,200)
                            ->header('Content-Type', 'application/json');
                    }
                    elseif(isset($client_response->fundtransferresponse->status->code) 
                    && $client_response->fundtransferresponse->status->code == "402"){
                        $data = array(
                            "win"=>2,
                            "transaction_reason" => "FAILED Due to low balance or Client Server Timeout"
                        );
                        GameTransaction::updateGametransaction($data,$game_transactionid);
                        $msg = array(
                            "status"=>"INSUFFICIENT_FUNDS",
                            "uuid"=>$data["uuid"],
                        );
                        Helper::updateGameTransactionExt($betGametransactionExtId,$client_response->requestoclient,$msg,$client_response);
                        return response($msg,200)
                        ->header('Content-Type', 'application/json');
                    }
                }
            }
            else{
                $msg = array(
                    "status"=>"INVALID_PARAMETER",
                    "uuid"=>$data["uuid"],
                );
                return response($msg,200)->header('Content-Type', 'application/json');
            }
        }
        else{
            $data = json_decode($request->getContent(),TRUE);
            $msg = array(
                "status"=>"INVALID_TOKEN_ID",
                "uuid"=>$data["uuid"],
            );
            return response($msg,200)->header('Content-Type', 'application/json');
        }
    }
    public function credit(Request $request){
        $startTime =  microtime(true);
        if($request->has("authToken")&& $request->authToken == config("providerlinks.evolution.owAuthToken")){
            $data = json_decode($request->getContent(),TRUE);
            $client_details = ProviderHelper::getClientDetails("player_id",$data["userId"]);
            if($client_details){
                $game_transaction = Helper::checkGameTransaction($data["transaction"]["id"]);
                if($game_transaction){
                    $msg = array(
                        "status"=>"BET_ALREADY_SETTLED",
                        "uuid"=>$data["uuid"],
                    );
                    return response($msg,200)->header('Content-Type', 'application/json');
                }
                else{
                    $win = $data["transaction"]["amount"] == 0 ? 0 : 1;
                    if(config("providerlinks.evolution.env") == 'test'){
                        $game_details = EVGHelper::getGameDetails($data["game"]["details"]["table"]["id"],$data["game"]["type"],config("providerlinks.evolution.env"));
                    }
                    if(config("providerlinks.evolution.env") == 'production'){
                        $game_details = EVGHelper::getGameDetails($data["game"]["details"]["table"]["id"],null,config("providerlinks.evolution.env"));
                    }
                    $json_data = array(
                        "transid" => $data["transaction"]["id"],
                        "amount" => round($data["transaction"]["amount"],2),
                        "roundid" => $data["transaction"]["refId"],
                        "payout_reason" => null,
                        "win" => $win,
                    );
                    $game = $this->getGameTransactionbyround($data["transaction"]["refId"]);
                    if(!$game){
                        //$gametransactionid=Helper::createGameTransaction('credit', $json_data, $game_details, $client_details); 
                        $msg = array(
                            "status"=>"BET_DOES_NOT_EXIST",
                            "uuid"=>$data["uuid"],
                        );
                        return response($msg,200)->header('Content-Type', 'application/json');
                    }
                    else{
                        if($win == 0){
                            $gameupdate = Helper::updateGameTransaction($game,$json_data,"debit");
                        }else{
                            $gameupdate = Helper::updateGameTransaction($game,$json_data,"credit");
                        }
                        $gametransactionid = $game->game_trans_id;
                    }
                    // $transactionId= EVGHelper::createEVGGameTransactionExt($gametransactionid,$data,null,null,null,2);
                    // $sendtoclient =  microtime(true);  
                    // $client_response = ClientRequestHelper::fundTransfer($client_details,round($data["transaction"]["amount"],2),$game_details->game_code,$game_details->game_name,$transactionId,$gametransactionid,"credit");
                    // $client_response_time = microtime(true) - $sendtoclient;
                    // $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
                    $msg = array(
                        "status"=>"OK",
                        "balance"=>$client_details->balance+round($data["transaction"]["amount"],2),
                        "uuid"=>$data["uuid"],
                    );

					$action_payload = [
						"type" => "custom", #genreral,custom :D # REQUIRED!
						"custom" => [
							"provider" => 'evolution',
						],
						"provider" => [
							"provider_request" => $data, #R
							"provider_trans_id"=>$data["transaction"]["id"], #R
							"provider_round_id"=>$data["transaction"]["refId"], #R
						],
						"mwapi" => [
							"roundId"=>$gametransactionid, #R
							"type"=>2, #R
							"game_id" => $game_details->game_id, #R
							"player_id" => $client_details->player_id, #R
							"mw_response" => $msg, #R
						]
					];

                    $sendtoclient =  microtime(true);  
                    // $client_response = ClientRequestHelper::fundTransfer_TG($client_details,round($data["transaction"]["amount"],2),$game_details->game_code,$game_details->game_name,$transactionId,$gametransactionid,"credit");
            		$client_response = ClientRequestHelper::fundTransfer_TG($client_details,round($data["transaction"]["amount"],2),$game_details->game_code,$game_details->game_name,$gametransactionid,'credit',false,$action_payload);
                    $client_response_time = microtime(true) - $sendtoclient;
                    $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
                    
                    if(isset($client_response->fundtransferresponse->status->code) 
                    && $client_response->fundtransferresponse->status->code == "200"){
                        ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                        $msg = array(
                            "status"=>"OK",
                            "balance"=>(float)$balance,
                            "uuid"=>$data["uuid"],
                        );
                        //Helper::updateGameTransactionExt($transactionId,$client_response->requestoclient,$msg,$client_response);

                        //Helper::saveLog('responseTime(EVG)', 12, json_encode(["type"=>"creditproccess","stating"=>$startTime,"response"=>microtime(true)]), ["response"=>microtime(true) - $startTime,"mw_response"=> microtime(true) - $startTime - $client_response_time,"clientresponse"=>$client_response_time]);
                        return response($msg,200)
                            ->header('Content-Type', 'application/json');
                    }
                }
            }
            else{
                $msg = array(
                    "status"=>"INVALID_PARAMETER",
                    "uuid"=>$data["uuid"],
                );
                return response($msg,200)->header('Content-Type', 'application/json');
            }
        }
        else{
            $data = json_decode($request->getContent(),TRUE);
            $msg = array(
                "status"=>"INVALID_TOKEN_ID",
                "uuid"=>$data["uuid"],
            );
            return response($msg,200)->header('Content-Type', 'application/json');
        }
    }
    public function cancel(Request $request){
        $startTime =  microtime(true);
        if($request->has("authToken")&& $request->authToken == config("providerlinks.evolution.owAuthToken")){
            $data = json_decode($request->getContent(),TRUE);
            $client_details = ProviderHelper::getClientDetails("player_id",$data["userId"]);
            if($client_details){
                // $game_transaction = Helper::checkGameTransaction($data["transaction"]["id"],$data["transaction"]["refId"],3);
                // if($game_transaction){
                //     $msg = array(
                //         "status"=>"BET_ALREADY_SETTLED",
                //         "uuid"=>$data["uuid"],
                //     );
                //     return response($msg,200)->header('Content-Type', 'application/json');
                // }
                // else{
                    $check_bet_exist = Helper::checkGameTransaction($data["transaction"]["id"],$data["transaction"]["refId"],1);
                    if(!$check_bet_exist){
                        $msg = array(
                            "status"=>"BET_DOES_NOT_EXIST",
                            "uuid"=>$data["uuid"],
                        );
                        return response($msg,200)->header('Content-Type', 'application/json');
                    }
                    else{
                        try{
                        ProviderHelper::idenpotencyTable($this->prefix.'_'.$data["transaction"]["id"].'_'.$data["transaction"]["refId"].'_3');
                        }catch(\Exception $e){
                            $msg = array(
                                "status"=>"BET_ALREADY_SETTLED",
                                "uuid"=>$data["uuid"],
                            );
                            return response($msg,200)->header('Content-Type', 'application/json');
                        }
                        $win = 0;
                        if(config("providerlinks.evolution.env") == 'test'){
                            $game_details = EVGHelper::getGameDetails($data["game"]["details"]["table"]["id"],$data["game"]["type"],config("providerlinks.evolution.env"));
                        }
                        if(config("providerlinks.evolution.env") == 'production'){
                            $game_details = EVGHelper::getGameDetails($data["game"]["details"]["table"]["id"],null,config("providerlinks.evolution.env"));
                        }
                        $json_data = array(
                            "transid" => $data["transaction"]["id"],
                            "amount" => round($data["transaction"]["amount"],2),
                            "roundid" => $data["transaction"]["refId"],
                        );
                        $game = Helper::getGameTransaction($client_details->player_token,$data["transaction"]["refId"]);
                        if(!$game){
                            $gametransactionid=Helper::createGameTransaction('refund', $json_data, $game_details, $client_details); 
                        }
                        else{
                            $gameupdate = Helper::updateGameTransaction($game,$json_data,"refund");
                            $gametransactionid = $game->game_trans_id;
                        }
                        $transactionId= EVGHelper::createEVGGameTransactionExt($gametransactionid,$data,null,null,null,3); 
                        $sendtoclient =  microtime(true);
                        $client_response = ClientRequestHelper::fundTransfer($client_details,round($data["transaction"]["amount"],2),$game_details->game_code,$game_details->game_name,$transactionId,$gametransactionid,"credit",true);
                        $client_response_time = microtime(true) - $sendtoclient;
                        $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
                        if(isset($client_response->fundtransferresponse->status->code) 
                        && $client_response->fundtransferresponse->status->code == "200"){
                            $msg = array(
                                "status"=>"OK",
                                "balance"=>(float)$balance,
                                "uuid"=>$data["uuid"],
                            );
                            Helper::updateGameTransactionExt($transactionId,$client_response->requestoclient,$msg,$client_response);
                            //Helper::saveLog('responseTime(EVG)', 12, json_encode(["type"=>"creditproccess","stating"=>$startTime,"response"=>microtime(true)]), ["response"=>microtime(true) - $startTime,"mw_response"=> microtime(true) - $startTime - $client_response_time,"clientresponse"=>$client_response_time]);
                            return response($msg,200)
                                ->header('Content-Type', 'application/json');
                        }
                    }
                }
            }
            else{
                $msg = array(
                    "status"=>"INVALID_PARAMETER",
                    "uuid"=>$data["uuid"],
                );
                return response($msg,200)->header('Content-Type', 'application/json');
            }
        }
        else{
            $data = json_decode($request->getContent(),TRUE);
            $msg = array(
                "status"=>"INVALID_TOKEN_ID",
                "uuid"=>$data["uuid"],
            );
            return response($msg,200)->header('Content-Type', 'application/json');
        }
    }
    public function gameLaunch(Request $request){
        return EVGHelper::gameLaunch($request->token,"139.180.159.34",$request->game_code);
    }
    public function internalrefund(Request $request){
        if($request->has("authToken")&& $request->authToken == config("providerlinks.evolution.owAuthToken")){
            $data = json_decode($request->getContent(),TRUE);
            //Helper::saveLog('cancelrequest(EVG)', 50, json_encode($data), "cancel");
            $client_details = ProviderHelper::getClientDetails("player_id",$data["userId"]);
            if($client_details){
                $game_transaction = Helper::checkGameTransaction($data["transaction"]["id"],$data["transaction"]["refId"],3);
                if($game_transaction){
                    $msg = array(
                        "status"=>"BET_ALREADY_SETTLED",
                        "uuid"=>$data["uuid"],
                    );
                    return response($msg,200)->header('Content-Type', 'application/json');
                }
                else{
                    $check_bet_exist = Helper::checkGameTransaction($data["transaction"]["id"],$data["transaction"]["refId"],1);
                        $win = 0;
                        if(config("providerlinks.evolution.env") == 'test'){
                            $game_details = EVGHelper::getGameDetails($data["game"]["details"]["table"]["id"],$data["game"]["type"],config("providerlinks.evolution.env"));
                        }
                        if(config("providerlinks.evolution.env") == 'production'){
                            $game_details = EVGHelper::getGameDetails($data["game"]["details"]["table"]["id"],null,config("providerlinks.evolution.env"));
                        }
                        $json_data = array(
                            "transid" => $data["transaction"]["id"],
                            "amount" => round($data["transaction"]["amount"],2),
                            "roundid" => $data["transaction"]["refId"],
                        );
                        if($data["transaction"]["refId"]){
                            $gametransactionid=$data["transaction"]["refId"];
                        }
                        else{
                            $msg = array(
                                "status"=>"INVALID_PARAMETER",
                                "uuid"=>$data["uuid"],
                            );
                            return response($msg,200)->header('Content-Type', 'application/json');
                        }
                        $transactionId= EVGHelper::createEVGGameTransactionExt($gametransactionid,$data,null,null,null,3); 
                        $client_response = ClientRequestHelper::fundTransfer($client_details,round($data["transaction"]["amount"],2),$game_details->game_code,$game_details->game_name,$transactionId,$gametransactionid,"credit",true);
                        $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
                        if(isset($client_response->fundtransferresponse->status->code) 
                        && $client_response->fundtransferresponse->status->code == "200"){
                            $msg = array(
                                "status"=>"OK",
                                "balance"=>(float)$balance,
                                "uuid"=>$data["uuid"],
                            );
                            Helper::updateGameTransactionExt($transactionId,$client_response->requestoclient,$msg,$client_response);
                            return response($msg,200)
                                ->header('Content-Type', 'application/json');
                        }
                }
            }
            else{
                $msg = array(
                    "status"=>"INVALID_PARAMETER",
                    "uuid"=>$data["uuid"],
                );
                return response($msg,200)->header('Content-Type', 'application/json');
            }
        }
        else{
            $data = json_decode($request->getContent(),TRUE);
            $msg = array(
                "status"=>"INVALID_TOKEN_ID",
                "uuid"=>$data["uuid"],
            );
            return response($msg,200)->header('Content-Type', 'application/json');
        }
    }
    private function _getClientDetails($type = "", $value = "") {

		$query = DB::table("clients AS c")
				 ->select('p.client_id', 'p.player_id', 'p.client_player_id','p.username', 'p.email', 'p.language', 'p.currency', 'pst.token_id', 'pst.player_token' , 'pst.status_id', 'p.display_name','c.default_currency', 'c.client_api_key', 'cat.client_token AS client_access_token', 'ce.player_details_url', 'ce.fund_transfer_url')
				 ->leftJoin("players AS p", "c.client_id", "=", "p.client_id")
				 ->leftJoin("player_session_tokens AS pst", "p.player_id", "=", "pst.player_id")
				 ->leftJoin("client_endpoints AS ce", "c.client_id", "=", "ce.client_id")
				 ->leftJoin("client_access_tokens AS cat", "c.client_id", "=", "cat.client_id");
				 
				if ($type == 'token') {
					$query->where([
				 		["pst.player_token", "=", $value],
				 		["pst.status_id", "=", 1]
				 	]);
				}

				if ($type == 'player_id') {
					$query->where([
				 		["p.player_id", "=", $value],
				 		["pst.status_id", "=", 1]
				 	])->orderBy('pst.token_id','desc')->limit(1);
				}

				 $result= $query->first();

		return $result;
    }

    public  function getGameTransaction($player_token,$game_round){
        DB::enableQueryLog();
		$game = DB::select("SELECT
						entry_id,bet_amount,game_trans_id,pay_amount
						FROM game_transactions g
						INNER JOIN player_session_tokens USING (token_id)
						WHERE player_token = '".$player_token."' and round_id = '".$game_round."'");
        $result = count($game);
		return $result > 0 ? $game[0] : null;
    }
    public function getGameTransactionbyround($game_round){
		$game = DB::select("SELECT * FROM game_transactions WHERE round_id = '".$game_round."'");
        $result = count($game);
		return $result > 0 ? $game[0] : null;
	}
}

