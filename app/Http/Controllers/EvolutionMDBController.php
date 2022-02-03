<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ClientRequestHelper;
use App\Helpers\EVGHelper;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Models\GameTransaction;
use App\Models\GameTransactionMDB;
use DB;
class EvolutionMDBController extends Controller
{
    //
    // # UPDATE CONTROLLER 2022 02 04
    private $prefix = '42';
    public function authentication(Request $request){
        Helper::saveLog('evolution', 42, json_encode($request->all()), "ENDPOINT HIT authentication MDB");
        if($request->has("authToken")&& $request->authToken == config("providerlinks.evolution.owAuthToken")){
            $data = json_decode($request->getContent(),TRUE);
            $client_details = ProviderHelper::getClientDetails("player_id",$data["userId"]);
            if($client_details){
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
        Helper::saveLog('evolution', 42, json_encode($request->all()), "ENDPOINT HIT sid MDB");
        if($request->has("authToken")&& $request->authToken == config("providerlinks.evolution.owAuthToken")){
            $data = json_decode($request->getContent(),TRUE);
            $client_details = ProviderHelper::getClientDetails("player_id",$data["userId"]);
            if($client_details){
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
        Helper::saveLog('evolution', 42, json_encode($request->all()), "ENDPOINT HIT balance MDB");
        if($request->has("authToken")&& $request->authToken == config("providerlinks.evolution.owAuthToken")){
            $data = json_decode($request->getContent(),TRUE);
            $client_details = ProviderHelper::getClientDetails("player_id",$data["userId"]);
            if($client_details){
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
        Helper::saveLog('evolution', 42, json_encode($request->all()), "ENDPOINT HIT debit MDB");
        if($request->has("authToken")&& $request->authToken == config("providerlinks.evolution.owAuthToken")){
            $data = json_decode($request->getContent(),TRUE);
            $client_details = ProviderHelper::getClientDetails("player_id",$data["userId"]);
            if($client_details){
                    try{
                        ProviderHelper::idenpotencyTable($this->prefix.$data["transaction"]["id"].'_'.$data["transaction"]["refId"].'_1');
                    }catch(\Exception $e){
                        $bet_transaction = GameTransactionMDB::findGameExt($data["transaction"]["id"], 1,'transaction_id', $client_details);
                        if($bet_transaction != "false"){
                            if($bet_transaction->general_details == "FAILED" ) {
                                $msg = array(
                                    "status"=>"INSUFFICIENT_FUNDS",
                                    "uuid"=>$data["uuid"],
                                );
                                return response($msg,200)->header('Content-Type', 'application/json');
                            } else if ($bet_transaction->general_details == "success") {
                                $msg = array(
                                    "status"=>"OK",
                                    "balance"=>(float)$client_details->balance,
                                    "uuid"=>$data["uuid"],
                                );
                                return response($msg,200)->header('Content-Type', 'application/json');
                            }

                        }
                        $msg = array(
                            "status"=>"BET_ALREADY_EXIST",
                            "uuid"=>$data["uuid"],
                        );
                        return response($msg,200)->header('Content-Type', 'application/json');
                    }
                   
                    $game_details = ProviderHelper::findGameDetails('game_code', $this->prefix, $data["game"]["details"]["table"]["id"]);
                    $TransactionData = array(
                        "provider_trans_id" => $data["transaction"]["id"],
                        "token_id" => $client_details->token_id,
                        "game_id" => $game_details->game_id,
                        "round_id" => $data["transaction"]["refId"],
                        "bet_amount" => $data["transaction"]["amount"],
                        "win" => 5,
                        "pay_amount" =>0,
                        "income" =>$data["transaction"]["amount"],
                        "entry_id" =>1,
                    );
                    $game_transactionid = GameTransactionMDB::createGametransaction($TransactionData,$client_details);
                    $betgametransactionext = array(
                        "game_trans_id" => $game_transactionid,
                        "round_id" =>$data["transaction"]["refId"],
                        "provider_trans_id" => $data["transaction"]["id"],
                        "amount" =>$data["transaction"]["amount"],
                        "game_transaction_type"=>1,
                        "provider_request" =>json_encode($data),
                    );
                    $betGametransactionExtId = GameTransactionMDB::createGameTransactionExt($betgametransactionext,$client_details);
                    $fund_extra_data = [
                        'provider_name' => $game_details->provider_name
                    ];
                    $client_response = ClientRequestHelper::fundTransfer($client_details,$data["transaction"]["amount"],$game_details->game_code,$game_details->game_name,$betGametransactionExtId,$game_transactionid,"debit",false,$fund_extra_data);
                    if(isset($client_response->fundtransferresponse->status->code) 
                    && $client_response->fundtransferresponse->status->code == "200"){
                        ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                        $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
                        $msg = array(
                            "status"=>"OK",
                            "balance"=>(float)$balance,
                            "uuid"=>$data["uuid"],
                        );
                        $dataToUpdate = array(
                            "mw_response" => json_encode($msg),
                            "transaction_detail" =>"success",
							"general_details" => "success",
                        );
                        GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$betGametransactionExtId,$client_details);
                        return response($msg,200)
                            ->header('Content-Type', 'application/json');
                    }
                    elseif(isset($client_response->fundtransferresponse->status->code) 
                    && $client_response->fundtransferresponse->status->code == "402"){
                        $datatoupdate = array(
                            "win"=>2
                        );
                        // GameTransaction::updateGametransaction($datatoupdate,$game_transactionid);
                        GameTransactionMDB::updateGametransaction($datatoupdate, $game_transactionid, $client_details);
                        $msg = array(
                            "status"=>"INSUFFICIENT_FUNDS",
                            "uuid"=>$data["uuid"],
                        );
                        $dataToUpdate = array(
                            "mw_response" => json_encode($msg),
                            "transaction_detail" =>"FAILED",
							"general_details" => "FAILED",
                        );
                        GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$betGametransactionExtId,$client_details);
                        return response($msg,200)
                        ->header('Content-Type', 'application/json');
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
        Helper::saveLog('evolution', 42, json_encode($request->all()), "ENDPOINT HIT credit MDB");
        if($request->has("authToken")&& $request->authToken == config("providerlinks.evolution.owAuthToken")){
            $data = json_decode($request->getContent(),TRUE);
            $client_details = ProviderHelper::getClientDetails("player_id",$data["userId"]);
            if($client_details){
                try{
                    ProviderHelper::idenpotencyTable($this->prefix.'_'.$data["transaction"]["id"].'_'.$data["transaction"]["refId"].'_2');
                }catch(\Exception $e){
                    $msg = array(
                        "status"=>"OK",
                        "balance"=>(float)$client_details->balance,
                        "uuid"=>$data["uuid"],
                    );
                    return response($msg,200)->header('Content-Type', 'application/json');
                } 
                $game_details = ProviderHelper::findGameDetails('game_code', $this->prefix, $data["game"]["details"]["table"]["id"]);
		        $game = GameTransactionMDB::findGameTransactionDetails($data["transaction"]["refId"], 'round_id',false, $client_details);
                if($game == 'false'){
                    $msg = array(
                        "status"=>"BET_DOES_NOT_EXIST",
                        "uuid"=>$data["uuid"],
                    );
                    return response($msg,200)->header('Content-Type', 'application/json');
                }
                else{
                    $createGametransaction = array(
                        "win" => 5,
                        "pay_amount" => $game->pay_amount+$data["transaction"]["amount"],
                        "income" =>$game->income - $data["transaction"]["amount"],
                        "entry_id" => $data["transaction"]["amount"] == 0 && $game->pay_amount == 0 ? 1 : 2,
                    );
                    GameTransactionMDB::updateGametransaction($createGametransaction,$game->game_trans_id,$client_details);
                }
                $win_or_lost = ($game->pay_amount+$data["transaction"]["amount"]) == 0 ? 0 : 1;
                $msg = array(
                    "status"=>"OK",
                    "balance"=>$client_details->balance+$data["transaction"]["amount"],
                    "uuid"=>$data["uuid"],
                );
                $wingametransactionext = array(
                    "game_trans_id" => $game->game_trans_id,
                    "provider_trans_id" =>  $data["transaction"]["id"],
                    "round_id" => $data["transaction"]["refId"],
                    "amount" => $data["transaction"]["amount"],
                    "game_transaction_type"=>2,
                    "provider_request" =>json_encode($data),
                    "mw_response" => json_encode($msg)
                );
                $winGametransactionExtId = GameTransactionMDB::createGameTransactionExt($wingametransactionext,$client_details);
                $action_payload = [
                    "type" => "custom", #genreral,custom :D # REQUIRED!
                    "custom" => [
                        "provider" => 'evolution',
                        "game_transaction_ext_id" => $winGametransactionExtId,
                        "client_connection_name" => $client_details->connection_name,
                        "win_or_lost" => $win_or_lost,
                        "provider_name" => $game_details->provider_name
                    ],
                    "provider" => [
                        "provider_request" => $data, #R
                        "provider_trans_id"=>$data["transaction"]["id"], #R
                        "provider_round_id"=>$data["transaction"]["refId"], #R
                    ],
                    "mwapi" => [
                        "roundId"=>$game->game_trans_id, #R
                        "type"=>2, #R
                        "game_id" => $game_details->game_id, #R
                        "player_id" => $client_details->player_id, #R
                        "mw_response" => $msg, #R
                    ]
                ];
                //$transactionId= EVGHelper::createEVGGameTransactionExt($gametransactionid,$data,null,$msg,null,2);
                // $sendtoclient =  microtime(true);  
                // $client_response = ClientRequestHelper::fundTransfer_TG($client_details,round($data["transaction"]["amount"],2),$game_details->game_code,$game_details->game_name,$transactionId,$gametransactionid,"credit");
                //$client_response = ClientRequestHelper::fundTransfer_ToGo($client_details,round($data["transaction"]["amount"],2),$game_details->game_code,$game_details->game_name,$transactionId,$gametransactionid,'credit',false,$action_payload);
                $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$data["transaction"]["amount"],$game_details->game_code,$game_details->game_name,$game->game_trans_id,'credit',false,$action_payload);
                if(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "200"){
                    $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
                    $msg = array(
                        "status"=>"OK",
                        "balance"=>(float)$balance,
                        "uuid"=>$data["uuid"],
                    );
                    return response($msg,200)
                        ->header('Content-Type', 'application/json');
                }
                // }
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
        Helper::saveLog('evolution', 42, json_encode($request->all()), "ENDPOINT HIT cancel MDB");
        if($request->has("authToken")&& $request->authToken == config("providerlinks.evolution.owAuthToken")){
            $data = json_decode($request->getContent(),TRUE);
            $client_details = ProviderHelper::getClientDetails("player_id",$data["userId"]);
            if($client_details){
                    try{
                        ProviderHelper::idenpotencyTable($this->prefix.'_'.$data["transaction"]["id"].'_'.$data["transaction"]["refId"].'_3');
                    }catch(\Exception $e){
                        $msg = array(
                            "status"=>"BET_ALREADY_SETTLED",
                            "uuid"=>$data["uuid"],
                        );
                        return response($msg,200)->header('Content-Type', 'application/json');
                    }
                    try{
                        ProviderHelper::idenpotencyTable($this->prefix.'_'.$data["transaction"]["id"].'_'.$data["transaction"]["refId"].'_2');
                    }catch(\Exception $e){
                        $msg = array(
                            "status"=>"WIN_ALREADY_RECEIVE",
                            "uuid"=>$data["uuid"],
                        );
                        return response($msg,200)->header('Content-Type', 'application/json');
                    }
                    // $check_bet_exist = GameTransactionMDB::getGameTransactionDataByProviderTransactionIdAndEntryType($data["transaction"]["id"],1,$client_details);
                    $check_bet_exist = GameTransactionMDB::findGameTransactionDetails($data["transaction"]["refId"], 'round_id',false, $client_details);
                    if($check_bet_exist == 'false'){
                        $msg = array(
                            "status"=>"BET_DOES_NOT_EXIST",
                            "uuid"=>$data["uuid"],
                        );
                        return response($msg,200)->header('Content-Type', 'application/json');
                    }
                    $game_details = ProviderHelper::findGameDetails('game_code', $this->prefix, $data["game"]["details"]["table"]["id"]);
                    $updateGametransaction = array(
                        "win" => 5,
                        "pay_amount" =>$data["transaction"]["amount"],
                        "income" => $check_bet_exist->bet_amount-$data["transaction"]["amount"],
                        "entry_id" =>2,
                    );
                    GameTransactionMDB::updateGametransaction($updateGametransaction,$check_bet_exist->game_trans_id,$client_details);
                    $refundgametransactionext = array(
                        "game_trans_id" => $check_bet_exist->game_trans_id,
                        "provider_trans_id" =>  $data["transaction"]["id"],
                        "round_id" =>$data["transaction"]["refId"],
                        "amount" =>$data["transaction"]["amount"],
                        "game_transaction_type"=>3,
                        "provider_request" =>json_encode($data),
                    );
                    $fund_extra_data = [
                        'provider_name' => $game_details->provider_name
                    ];
                    $refundgametransactionextID = GameTransactionMDB::createGameTransactionExt($refundgametransactionext,$client_details);
                    $client_response = ClientRequestHelper::fundTransfer($client_details,$data["transaction"]["amount"],$game_details->game_code,$game_details->game_name,$refundgametransactionextID,$check_bet_exist->game_trans_id,"credit",true,$fund_extra_data);
                    $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
                    if(isset($client_response->fundtransferresponse->status->code) 
                    && $client_response->fundtransferresponse->status->code == "200"){
                        $msg = array(
                            "status"=>"OK",
                            "balance"=>(float)$balance,
                            "uuid"=>$data["uuid"],
                        );
                        $dataToUpdate = array(
                            "mw_response" => json_encode($msg),
                            "transaction_detail" =>"success",
							"general_details" => "success",
                        );
                        GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$refundgametransactionextID,$client_details);
                        $datatoupdate = array(
                            "win"=>4
                        );
                        // GameTransaction::updateGametransaction($datatoupdate,$game_transactionid);
                        GameTransactionMDB::updateGametransaction($datatoupdate, $check_bet_exist->game_trans_id, $client_details);
                      
                        return response($msg,200)
                            ->header('Content-Type', 'application/json');
                    }
                    $msg = array(
                        "status"=>"OK",
                        "balance"=>(float)$balance,
                        "uuid"=>$data["uuid"],
                    );
                    $dataToUpdate = array(
                        "mw_response" =>json_encode($msg),
                        "mw_request"=>json_encode($client_response->requestoclient),
                        "client_response" =>json_encode($client_response->fundtransferresponse),
                        "transaction_detail" => "PENDING",
                        "general_details" => "PENDING",
                    );
                    GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$refundgametransactionext,$client_details);
                    return response($msg,200)
                        ->header('Content-Type', 'application/json');
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
    // public function internalrefund(Request $request){
    //     if($request->has("authToken")&& $request->authToken == config("providerlinks.evolution.owAuthToken")){
    //         $data = json_decode($request->getContent(),TRUE);
    //         //Helper::saveLog('cancelrequest(EVG)', 50, json_encode($data), "cancel");
    //         $client_details = ProviderHelper::getClientDetails("player_id",$data["userId"]);
    //         if($client_details){
    //             $game_transaction = Helper::checkGameTransaction($data["transaction"]["id"],$data["transaction"]["refId"],3);
    //             if($game_transaction){
    //                 $msg = array(
    //                     "status"=>"BET_ALREADY_SETTLED",
    //                     "uuid"=>$data["uuid"],
    //                 );
    //                 return response($msg,200)->header('Content-Type', 'application/json');
    //             }
    //             else{
    //                 $check_bet_exist = Helper::checkGameTransaction($data["transaction"]["id"],$data["transaction"]["refId"],1);
    //                     $win = 0;
    //                     if(config("providerlinks.evolution.env") == 'test'){
    //                         $game_details = EVGHelper::getGameDetails($data["game"]["details"]["table"]["id"],$data["game"]["type"],config("providerlinks.evolution.env"));
    //                     }
    //                     if(config("providerlinks.evolution.env") == 'production'){
    //                         $game_details = EVGHelper::getGameDetails($data["game"]["details"]["table"]["id"],null,config("providerlinks.evolution.env"));
    //                     }
    //                     $json_data = array(
    //                         "transid" => $data["transaction"]["id"],
    //                         "amount" => round($data["transaction"]["amount"],2),
    //                         "roundid" => $data["transaction"]["refId"],
    //                     );
    //                     if($data["transaction"]["refId"]){
    //                         $gametransactionid=$data["transaction"]["refId"];
    //                     }
    //                     else{
    //                         $msg = array(
    //                             "status"=>"INVALID_PARAMETER",
    //                             "uuid"=>$data["uuid"],
    //                         );
    //                         return response($msg,200)->header('Content-Type', 'application/json');
    //                     }
    //                     $transactionId= EVGHelper::createEVGGameTransactionExt($gametransactionid,$data,null,null,null,3); 
    //                     $client_response = ClientRequestHelper::fundTransfer($client_details,round($data["transaction"]["amount"],2),$game_details->game_code,$game_details->game_name,$transactionId,$gametransactionid,"credit",true);
    //                     $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
    //                     if(isset($client_response->fundtransferresponse->status->code) 
    //                     && $client_response->fundtransferresponse->status->code == "200"){
    //                         $msg = array(
    //                             "status"=>"OK",
    //                             "balance"=>(float)$balance,
    //                             "uuid"=>$data["uuid"],
    //                         );
    //                         Helper::updateGameTransactionExt($transactionId,$client_response->requestoclient,$msg,$client_response);
    //                         return response($msg,200)
    //                             ->header('Content-Type', 'application/json');
    //                     }
    //             }
    //         }
    //         else{
    //             $msg = array(
    //                 "status"=>"INVALID_PARAMETER",
    //                 "uuid"=>$data["uuid"],
    //             );
    //             return response($msg,200)->header('Content-Type', 'application/json');
    //         }
    //     }
    //     else{
    //         $data = json_decode($request->getContent(),TRUE);
    //         $msg = array(
    //             "status"=>"INVALID_TOKEN_ID",
    //             "uuid"=>$data["uuid"],
    //         );
    //         return response($msg,200)->header('Content-Type', 'application/json');
    //     }
    // }

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

