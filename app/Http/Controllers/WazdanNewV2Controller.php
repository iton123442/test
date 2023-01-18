<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\WazdanHelper;
use GuzzleHttp\Client;
use App\Helpers\ProviderHelper;
use App\Helpers\FreeSpinHelper;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransaction as GMT;
use App\Models\GameTransactionMDB;
use DB;
class WazdanNewV2Controller extends Controller
{
    private $prefix = 33;
    public function __construct() {
        $this->startTime = microtime(true);
    }
    public function hashCode(Request $request){
        Helper::saveLog('hashCode (Wazdan)', 33, $request->getContent(), "Hash Hit");
        // $operator = "tigergames";
        // $license = "curacao";
        $operator = config('providerlinks.wazdan.operator_data');
        $license = config('providerlinks.wazdan.license');
        $key = "uTDVNr4wu6Y78SNbr36bqsSCH904Rcn1";
        $data = array(
            "how" => 'hash_hmac("sha256","'.$request->getContent().'",'.$key.')',
            "hmac"=>hash_hmac("sha256",$request->getContent(),$key)
        );
        Helper::saveLog('hashCode (Wazdan)2', 33, $request->getContent(), $data);
        return $data;
    }
    public function authenticate(Request $request){
        $data = $request->getContent();
        $datadecoded = json_decode($data,TRUE);
        if($datadecoded["token"]){
            $client_details = ProviderHelper::getClientDetails('token', $request->token);
            Helper::saveLog('AuthPlayer (Wazdan)', 33, $data, $client_details);
            if($client_details){
                $balance = round($client_details->balance,2);
                $msg = array(
                    "status" => 0,
                    "user"=> array(
                        "id" => $client_details->player_id,
                        "currency" => $client_details->default_currency,
                    ),
                    "funds" => array(
                        "balance" => $balance
                    ),
                );
                return response($msg,200)->header('Content-Type', 'application/json');
            }
            else{
                $msg = array(
                    "status" =>1,
                    "message" => array(
                        "text"=>"This Session Already expired!",
                        "choices"=>array(
                            array(
                                "label" => "Go Back to Game List",
                                "action" => "close_game",
                                "response" => "quit"
                            )
                        )
                    )
                );
                return response($msg,200)->header('Content-Type', 'application/json');
            }
        }
        else{
            $msg = array(
                "status" =>1,
                "message" => array(
                    "text"=>"This Session Already expired please relaunch the game again",
                    "choices"=>array(
                        array(
                            "label" => "Go Back to Game List",
                            "action" => "close_game",
                            "response" => "quit"
                        )
                    )
                )
            );
            return response($msg,200)->header('Content-Type', 'application/json');
        }
    }
    public function getStake(Request $request){
        $data = $request->getContent();
        $timeStart = microtime(TRUE);
        $datadecoded = json_decode($data,TRUE);
        Helper::saveLog('getStake process (Wazdan)', 57, $data, $timeStart);
        // dd($datadecoded["user"]["token"]);
        if($datadecoded["user"]["token"]){    
            try{
                ProviderHelper::idenpotencyTable($this->prefix.'_'.$datadecoded["transactionId"].'_1');
            }catch(\Exception $e){
                $client_details = ProviderHelper::getClientDetails('token', $datadecoded["user"]["token"]);
                $bet_transaction = GameTransactionMDB::findGameExt($datadecoded["transactionId"], 1,'transaction_id', $client_details);
                if ($bet_transaction != 'false') {
                    // if( $bet_transaction->transaction_detail == "SUCCESS" ){
                    if($bet_transaction->transaction_detail == '"FAILED"' || $bet_transaction->transaction_detail == "FAILED" ){
                        $msg = array(
                            "status" =>8,
                            "message" => array(
                                "text"=>"Insufficient funds",
                            )
                        );
                        return response($msg,200)
                            ->header('Content-Type', 'application/json');
                    }
                    $msg = array(
                        "status" => 0,
                        "funds" => array(
                            "balance" => round($client_details->balance,2)
                        ),
                    );
                    return response($msg,200)
                        ->header('Content-Type', 'application/json');
                } 
                $msg = array(
                    "status" =>8,
                    "message" => array(
                        "text"=>"Insufficient funds",
                    )
                );
                return response($msg,200)
                    ->header('Content-Type', 'application/json');
            }
            $client_details = ProviderHelper::getClientDetails('token', $datadecoded["user"]["token"]);
            if($client_details){
                $game_details = ProviderHelper::findGameDetails('game_code', $this->prefix, $datadecoded["gameId"]);
                $bet_transaction = GameTransactionMDB::findGameTransactionDetails($datadecoded["roundId"], 'round_id',false, $client_details);
                if($bet_transaction != "false") {
                    $client_details->connection_name = $bet_transaction->connection_name;
                    $game_transactionid = $bet_transaction->game_trans_id;
                    $updateGameTransaction = [
                        'win' => 5,
                        'bet_amount' => $bet_transaction->bet_amount + round($datadecoded["amount"],2),
                        'entry_id' => 1,
                        'trans_status' => 1
                    ];
                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
                } else {
                    // $game_transactionid = ProviderHelper::idGenerate($client_details->connection_name,1);
                    $gameTransactionData = array(
                        "provider_trans_id" => $datadecoded["transactionId"],
                        "token_id" => $client_details->token_id,
                        "game_id" => $game_details->game_id,
                        "round_id" => $datadecoded["roundId"],
                        "bet_amount" => round($datadecoded["amount"],2),
                        "pay_amount" =>0,
                        "win" => 5,
                        "income" =>0,
                        "entry_id" =>1,
                    );
                    $game_transactionid = GameTransactionMDB::createGametransaction($gameTransactionData,$client_details);
                }
                $betGametransactionExtId = ProviderHelper::idGenerate($client_details->connection_name,2);
                try{
                    $fund_extra_data = [
                        'provider_name' => $game_details->provider_name,
                        'connection_timeout' => 3,
                    ];
                    $client_response = ClientRequestHelper::fundTransfer($client_details,round($datadecoded["amount"],2),$game_details->game_code,$game_details->game_name,$betGametransactionExtId,$game_transactionid,"debit",false,$fund_extra_data);
                }catch(\Exception $e){
                    Helper::saveLog('responseTime(WazdanFailedBet)', 57, json_encode(["starting"=>$this->startTime,"response"=>microtime(true)]), microtime(true) - $this->startTime);
                    $msg = array(
                        "status" =>8,
                        "message" => array(
                            "text"=>"Insufficient funds",
                        )
                    );
                    $gameTransactionData = array(
                        'win' => 2
                    );
                    GameTransactionMDB::updateGametransaction($gameTransactionData,$game_transactionid,$client_details);
                    $betgametransactionext = array(
                        "game_trans_id" => $game_transactionid,
                        "provider_trans_id" => $datadecoded["transactionId"],
                        "round_id" => $datadecoded["roundId"],
                        "amount" => round($datadecoded["amount"],2),
                        "game_transaction_type"=>1,
                        "provider_request" =>json_encode($datadecoded),
                        "mw_response" => json_encode($msg),
                        "mw_request" => "FAILED",
                        "general_details" => "FAILED",
                        "client_response" => "FAILED",
                        "transaction_detail" => "FAILED",
                    );
                    GameTransactionMDB::createGameTransactionExtV2($betgametransactionext,$betGametransactionExtId,$client_details); 
                    Helper::saveLog('Success bet!', 57, json_encode($client_response), microtime(true) - $this->startTime);
                    return response($msg,200)
                        ->header('Content-Type', 'application/json');
                }
                if(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "200"){
                    $balance = round($client_response->fundtransferresponse->balance,2);
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    $msg = array(
                        "status" => 0,
                        "funds" => array(
                            "balance" => $balance
                        ),
                    );
                    $betgametransactionext = array(
                        "game_trans_id" => $game_transactionid,
                        "provider_trans_id" => $datadecoded["transactionId"],
                        "round_id" => $datadecoded["roundId"],
                        "amount" => round($datadecoded["amount"],2),
                        "game_transaction_type"=>1,
                        "provider_request" =>json_encode($datadecoded),
                        "mw_response" => json_encode($msg),
                        "mw_request" => json_encode($client_response->requestoclient),
                        "client_response" => json_encode($client_response),
                        "general_details" => "SUCCESS",
                        "transaction_detail" => "SUCCESS"
                    );
                    GameTransactionMDB::createGameTransactionExtV2($betgametransactionext,$betGametransactionExtId,$client_details);
                    // sleep(10);
                    return response($msg,200)
                        ->header('Content-Type', 'application/json');
                }
                elseif(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "402"){
                    $msg = array(
                        "status" =>8,
                        "message" => array(
                            "text"=>"Insufficient funds",
                        )
                    );
                    try{
                        $data = array(
                            "win"=>2
                        );
                        GameTransactionMDB::updateGametransaction($data,$game_transactionid,$client_details);
                        $betgametransactionext = array(
                            "game_trans_id" => $game_transactionid,
                            "provider_trans_id" => $datadecoded["transactionId"],
                            "round_id" => $datadecoded["roundId"],
                            "amount" => round($datadecoded["amount"],2),
                            "game_transaction_type"=>1,
                            "provider_request" =>json_encode($datadecoded),
                            "mw_response" => json_encode($msg),
                            "mw_request" => json_encode($client_response->requestoclient),
                            "general_details" => "FAILED",
                            "client_response" => json_encode($client_response),
                            "transaction_detail" => "FAILED",
                        );
                        GameTransactionMDB::createGameTransactionExtV2($betgametransactionext,$betGametransactionExtId,$client_details);
                    }catch(\Exception $e){
                        Helper::saveLog('betGameInsuficient(Wazdan)', 12, json_encode($e->getMessage().' '.$e->getLine()), $client_response->fundtransferresponse->status->message);
                    } 
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                

            }
        }
        else{
            $msg = array(
                "status" =>1,
                "message" => array(
                    "text"=>"This Session not Found",
                )
            ); 
            Helper::saveLog('betGameInsuficient(Wazdan)', 33, $data, $msg);
            return response($msg,200)
            ->header('Content-Type', 'application/json');
        } 
    }
    public function rollbackState(Request $request){
        $data = $request->getContent();
        $datadecoded = json_decode($data,TRUE);
        Helper::saveLog('rollbackStake(Wazdan)', 33, $data, "Initialize");
        if($datadecoded["user"]["token"]){
        $client_details = ProviderHelper::getClientDetails('token', $datadecoded["user"]["token"]);
        if($client_details){
                try{
                    ProviderHelper::idenpotencyTable($this->prefix.'_'.$datadecoded["transactionId"].'_3');
                }catch(\Exception $e){
                    $msg = array(
                        "status" => 0,
                        "funds" => array(
                            "balance" => round($client_details->balance,2)
                        ),
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                $gameExtension =  GameTransactionMDB::getGameTransactionDataByProviderTransactionIdAndEntryType($datadecoded["originalTransactionId"],1,$client_details);
                //$gameExtension = WazdanHelper::getTransactionExt($datadecoded["originalTransactionId"]);
                $game_details = ProviderHelper::findGameDetails('game_code', $this->prefix, $datadecoded["gameId"]);
                if($gameExtension==null){
                    $msg = array(
                        "status" =>1,
                        "message" => array(
                            "text"=>"The Transaction Doesn't Exist!",
                            "choices"=>array(
                                array(
                                    "label" => "Go Back to Game List",
                                    "action" => "close_game",
                                    "response" => "quit"
                                )
                            )
                        )
                    );
                    return response($msg,200)->header('Content-Type', 'application/json');
                }
                $refundgametransactionextId = ProviderHelper::idGenerate($client_details->connection_name,2);
                try{
                    $fund_extra_data = [
                        'provider_name' => $game_details->provider_name
                    ];  
                    $client_response = ClientRequestHelper::fundTransfer($client_details,round($datadecoded["amount"],2),$game_details->game_code,$game_details->game_name,$refundgametransactionextId,$gameExtension->game_trans_id,"credit",true,$fund_extra_data);
                }catch(\Exception $e){
                    $createGametransaction = array(
                        "win" => 2,
                        "pay_amount" =>0,
                        "income" =>0,
                        "entry_id" =>round($datadecoded["amount"],2) == 0 && $game->pay_amount == 0 ? 1 : 2,
                    );
                    GameTransactionMDB::updateGametransaction($createGametransaction,$game->game_trans_id,$client_details);
                    $response = array(
                        "status" =>1,
                        "message" => array(
                            "text"=>"The Transaction Doesn't Exist!",
                            "choices"=>array(
                                array(
                                    "label" => "Go Back to Game List",
                                    "action" => "close_game",
                                    "response" => "quit"
                                )
                            )
                        )
                    );
                    $wingametransactionext = array(
                        "game_trans_id" => $game->game_trans_id,
                        "provider_trans_id" => $datadecoded["transactionId"],
                        "round_id" => $datadecoded["roundId"],
                        "amount" => round($datadecoded["amount"],2),
                        "game_transaction_type"=>2,
                        "provider_request" =>json_encode($datadecoded),
                        "mw_response" => json_encode($response),
                        "mw_request" => "FAILED",
                        "general_details" => "FAILED",
                        "client_response" => "FAILED",
                        "transaction_detail" => "FAILED"
                    );
                    GameTransactionMDB::createGameTransactionExtV2($wingametransactionext,$refundgametransactionextId,$client_details);
                    //Helper::updateGameTransactionExt($transactionId,$client_response->requestoclient,$msg,$client_response);
                    Helper::saveLog('responseTime(WazdanFailed)', 57, json_encode(["starting"=>$this->startTime,"response"=>microtime(true)]), microtime(true) - $this->startTime);
                    return response($response,200)
                        ->header('Content-Type', 'application/json');
                }
                if(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "200"){
                    $balance = round($client_response->fundtransferresponse->balance,2);
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    $datadecoded["roundId"] = $gameExtension->round_id;
                    $updateGametransaction = array(
                        "win" =>4,
                        "pay_amount" =>round($datadecoded["amount"],2),
                        "income" =>$gameExtension->amount-round($datadecoded["amount"],2),
                        "entry_id" =>2,
                    );
                    GameTransactionMDB::updateGametransaction($updateGametransaction,$gameExtension->game_trans_id,$client_details);
                    $msg = array(
                        "status" => 0,
                        "funds" => array(
                            "balance" => $balance
                        ),
                    );
                    $refundgametransactionext = array(
                        "game_trans_id" => $gameExtension->game_trans_id,
                        "provider_trans_id" =>  $datadecoded["transactionId"],
                        "round_id" =>$datadecoded["roundId"],
                        "amount" =>round($datadecoded["amount"],2),
                        "game_transaction_type"=>3,
                        "provider_request" =>json_encode($datadecoded),
                        "mw_response" => json_encode($msg),
                        "mw_request" => json_encode($client_response->requestoclient),
                        "client_response" => json_encode($client_response),
                        "general_details" => "SUCCESS",
                        "transaction_detail" => "SUCCESS"
                    );
                    GameTransactionMDB::createGameTransactionExtV2($refundgametransactionext,$refundgametransactionextId,$client_details);
                    return response($msg,200)
                        ->header('Content-Type', 'application/json');
                }
            }
            else{
                $msg = array(
                    "status" =>1,
                    "message" => array(
                        "text"=>"session not found",
                        "choices"=>array(
                            array(
                                "label" => "Go Back to Game List",
                                "action" => "close_game",
                                "response" => "quit"
                            )
                        )
                    )
                );
                return response($msg,200)->header('Content-Type', 'application/json');
            }
        } 
    }
    public function returnWin(Request $request){
        $data = $request->getContent();
        $datadecoded = json_decode($data,TRUE);
        Helper::saveLog('returnWin(Wazdan)', 33, $data, "Initialize");
        if($datadecoded["user"]["token"]){
            $client_details = ProviderHelper::getClientDetails('token', $datadecoded["user"]["token"]);
            if($client_details){
                $isGameExtFailed = GameTransactionMDB::findGameExt($datadecoded["roundId"], 1,'round_id', $client_details);
                if($isGameExtFailed != 'false'){ 
                    if($isGameExtFailed->transaction_detail == '"FAILED"' || $isGameExtFailed->transaction_detail == "FAILED" ){
                        $response = array(
                            "status" =>1,
                            "message" => array(
                                "text"=>"The Transaction Doesn't Exist!",
                                "choices"=>array(
                                    array(
                                        "label" => "Go Back to Game List",
                                        "action" => "close_game",
                                        "response" => "quit"
                                    )
                                )
                            )
                        );
                        $wingametransactionext = array(
                            "game_trans_id" => $isGameExtFailed->game_trans_id,
                            "provider_trans_id" => $datadecoded["transactionId"],
                            "round_id" => $datadecoded["roundId"],
                            "amount" => round($datadecoded["amount"],2),
                            "game_transaction_type"=>2,
                            "provider_request" =>json_encode($datadecoded),
                            "mw_response" => json_encode($response)
                        );
                        $winGametransactionExtId = GameTransactionMDB::createGameTransactionExt($wingametransactionext,$client_details);
                        return response($response,200)->header('Content-Type', 'application/json');
                    }
                }
                try{
                    ProviderHelper::idenpotencyTable($this->prefix.'_'.$datadecoded["transactionId"].'_2');
                }catch(\Exception $e){
                    $msg = array(
                        "status" => 0,
                        "funds" => array(
                            "balance" => round($client_details->balance,2)
                        ),
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                //ryy
                $game_details = ProviderHelper::findGameDetails('game_code', $this->prefix, $datadecoded["gameId"]);
                // $client_details->connection_name = $bet_transaction->connection_name;
                $game = GameTransactionMDB::getGameTransactionByRoundId($datadecoded["roundId"],$client_details);
                if($game==null){
                    if(isset( $datadecoded['freeRoundInfo']['txId'] )) {
                        $getOrignalfreeroundID = explode("_",$datadecoded['freeRoundInfo']['txId']);
                        $action_payload["fundtransferrequest"]["fundinfo"]["freeroundId"] = $getOrignalfreeroundID[1]; //explod the provider trans use the original
                        $game_transactionid = ProviderHelper::idGenerate($client_details->connection_name,1);
                        $betGametransactionExtId = ProviderHelper::idGenerate($client_details->connection_name,2);
                        try{
                            $fund_extra_data = [
                                'provider_name' => $game_details->provider_name
                            ];  
                            $client_response = ClientRequestHelper::fundTransfer($client_details, 0,$game_details->game_code,$game_details->game_name,$betGametransactionExtId,$game_transactionid,"debit",false,$fund_extra_data);
                        }catch(\Exception $e){
                            $msg = array(
                                "status" =>8,
                                "message" => array(
                                    "text"=>"Insufficient funds",
                                )
                            );
                            $gameTransactionData = array(
                                "provider_trans_id" => $datadecoded["transactionId"],
                                "token_id" => $client_details->token_id,
                                "game_id" => $game_details->game_id,
                                "round_id" => $datadecoded["roundId"],
                                "bet_amount" => 0,
                                "pay_amount" =>0,
                                "win" => 2,
                                "income" =>0,
                                "entry_id" =>1,
                            );
                            GameTransactionMDB::createGametransactionV2($gameTransactionData,$game_transactionid,$client_details);
                            $betgametransactionext = array(
                                "game_trans_id" => $game_transactionid,
                                "provider_trans_id" => $datadecoded["transactionId"],
                                "round_id" => $datadecoded["roundId"],
                                "amount" => 0,
                                "game_transaction_type"=>1,
                                "provider_request" =>json_encode($datadecoded),
                                "mw_response" => json_encode($msg),
                                "mw_request" => "FAILED",
                                "general_details" => "FAILED",
                                "client_response" => "FAILED",
                                "transaction_detail" => "FAILED",
                            );
                            GameTransactionMDB::createGameTransactionExtV2($betgametransactionext,$betGametransactionExtId,$client_details); 
                            return response($msg,200)
                                ->header('Content-Type', 'application/json');
                        }
                        if(isset($client_response->fundtransferresponse->status->code) 
                        && $client_response->fundtransferresponse->status->code == "200"){
                            $balance = round($client_response->fundtransferresponse->balance,2);
                            ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                            Helper::saveLog('wazdan FreeRound', 57, $data, "Hit!");
                            $gameTransactionData = array(
                                "provider_trans_id" => $datadecoded["transactionId"],
                                "token_id" => $client_details->token_id,
                                "game_id" => $game_details->game_id,
                                "round_id" => $datadecoded["roundId"],
                                "bet_amount" => 0,
                                "pay_amount" =>0,
                                "win" => 5,
                                "income" =>0,
                                "entry_id" =>1,
                            );
                            GameTransactionMDB::createGametransactionV2($gameTransactionData,$game_transactionid,$client_details);
                            $msg = array(
                                "status" => 0,
                                "funds" => array(
                                    "balance" => $balance
                                ),
                            );
                            $betgametransactionext = array(
                                "game_trans_id" => $game_transactionid,
                                "provider_trans_id" => $datadecoded["transactionId"],
                                "round_id" => $datadecoded["roundId"],
                                "amount" => 0,
                                "game_transaction_type"=>1,
                                "provider_request" =>json_encode($datadecoded),
                                "mw_response" => json_encode($msg),
                                "mw_request" => json_encode($client_response->requestoclient),
                                "client_response" => json_encode($client_response),
                                "general_details" => "SUCCESS",
                                "transaction_detail" => "SUCCESS"
                            );
                            GameTransactionMDB::createGameTransactionExtV2($betgametransactionext,$betGametransactionExtId,$client_details);
                        }
                        $getFreespin = FreeSpinHelper::getFreeSpinDetails($datadecoded['freeRoundInfo']['txId'], "provider_trans_id" );
                        $bet_transaction = GameTransactionMDB::findGameTransactionDetails($datadecoded["roundId"], 'round_id',false, $client_details);
                        if($getFreespin){
                            //update transaction
                            $status = ($getFreespin->spin_remaining - 1) == 0 ? 2 : 1;
                            $updateFreespinData = [
                                "status" => 2,
                                "spin_remaining" => 0
                            ];
                            FreeSpinHelper::updateFreeSpinDetails($updateFreespinData, $getFreespin->freespin_id);
                                //create transction 
                            if($status == 2) {
                                $action_payload["fundtransferrequest"]["fundinfo"]["freeroundend"] = true;
                            }  else {
                                $action_payload["fundtransferrequest"]["fundinfo"]["freeroundend"] = false; //explod the provider trans use the original
                            }
                            $createFreeRoundTransaction = array(
                                "game_trans_id" => $bet_transaction->game_trans_id,
                                'freespin_id' => $getFreespin->freespin_id
                            );
                            FreeSpinHelper::createFreeRoundTransaction($createFreeRoundTransaction);
                        }
                        $game = GameTransactionMDB::getGameTransactionByRoundId($datadecoded["roundId"],$client_details);
                    } else {
                        $msg = array(
                            "status" => 0,
                            "funds" => array(
                                "balance" => round($client_details->balance,2)
                            )
                        );
                        Helper::saveLog('refundAlreadyexist(Wazdan)', 33, $data, $msg);
                        return response($msg,200)
                        ->header('Content-Type', 'application/json');
                    }
                }
                $win_or_lost = round($datadecoded["amount"],2) == 0 && $game->pay_amount == 0 ? 0 : 1;
                $winGametransactionExtId = ProviderHelper::idGenerate($client_details->connection_name,2);
                try{
                    $response = array(
                        "status" => 0,
                        "funds" => array(
                            "balance" => $client_details->balance + round($datadecoded["amount"],2)
                        )
                    );
                    $action_payload = [
                        "type" => "custom", #genreral,custom :D # REQUIRED!
                        "custom" => [
                            "provider" => 'wazdan',
                            "game_transaction_ext_id" => $winGametransactionExtId,
                            "client_connection_name" => $client_details->connection_name,
                            "win_or_lost" => $win_or_lost,
                        ],
                        "provider" => [
                            "provider_request" => $datadecoded, #R
                            "provider_trans_id"=>$datadecoded["transactionId"], #R
                            "provider_round_id"=>$datadecoded["roundId"], #R
                            'provider_name' => $game_details->provider_name
                        ],
                        "mwapi" => [
                            "roundId"=>$game->game_trans_id, #R
                            "type"=>2, #R
                            "game_id" => $game_details->game_id, #R
                            "player_id" => $client_details->player_id, #R
                            "mw_response" => $response, #R
                        ]
                    ];
                    $client_response = ClientRequestHelper::fundTransfer_TG($client_details,round($datadecoded["amount"],2),$game_details->game_code,$game_details->game_name,$game->game_trans_id,'credit',false,$action_payload);
                }catch(\Exception $e){
                    $createGametransaction = array(
                        "win" => 2,
                        "pay_amount" =>0,
                        "income" =>0,
                        "entry_id" =>round($datadecoded["amount"],2) == 0 && $game->pay_amount == 0 ? 1 : 2,
                    );
                    GameTransactionMDB::updateGametransaction($createGametransaction,$game->game_trans_id,$client_details);
                    $response = array(
                        "status" =>1,
                        "message" => array(
                            "text"=>"The Transaction Doesn't Exist!",
                            "choices"=>array(
                                array(
                                    "label" => "Go Back to Game List",
                                    "action" => "close_game",
                                    "response" => "quit"
                                )
                            )
                        )
                    );
                    $wingametransactionext = array(
                        "game_trans_id" => $game->game_trans_id,
                        "provider_trans_id" => $datadecoded["transactionId"],
                        "round_id" => $datadecoded["roundId"],
                        "amount" => round($datadecoded["amount"],2),
                        "game_transaction_type"=>2,
                        "provider_request" =>json_encode($datadecoded),
                        "mw_response" => json_encode($response),
                        "mw_request" => "FAILED",
                        "general_details" => "FAILED",
                        "client_response" => "FAILED",
                        "transaction_detail" => "FAILED"
                    );
                    GameTransactionMDB::createGameTransactionExtV2($wingametransactionext,$winGametransactionExtId,$client_details);
                    //Helper::updateGameTransactionExt($transactionId,$client_response->requestoclient,$msg,$client_response);
                    Helper::saveLog('responseTime(WazdanFailed)', 57, json_encode(["starting"=>$this->startTime,"response"=>microtime(true)]), microtime(true) - $this->startTime);
                    return response($response,200)
                        ->header('Content-Type', 'application/json');
                }
                if(isset($client_response->fundtransferresponse->status->code) && $client_response->fundtransferresponse->status->code == "200"){
                    $balance = round($client_response->fundtransferresponse->balance,2);
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    $createGametransaction = array(
                        "win" => $win_or_lost,
                        "pay_amount" => $game->pay_amount + round($datadecoded["amount"],2),
                        "income" =>$game->income - round($datadecoded["amount"],2),
                        "entry_id" =>round($datadecoded["amount"],2) == 0 && $game->pay_amount == 0 ? 1 : 2,
                    );
                    GameTransactionMDB::updateGametransaction($createGametransaction,$game->game_trans_id,$client_details);
                    $msg = array(
                        "status" => 0,
                        "funds" => array(
                            "balance" => $balance
                        )
                    );
                    Helper::saveLog('Win Wazdan HIT', 57, json_encode($datadecoded), $msg);
                    $wingametransactionext = array(
                        "game_trans_id" => $game->game_trans_id,
                        "provider_trans_id" => $datadecoded["transactionId"],
                        "round_id" => $datadecoded["roundId"],
                        "amount" => round($datadecoded["amount"],2),
                        "game_transaction_type"=>2,
                        "provider_request" =>json_encode($datadecoded),
                        "mw_response" => json_encode($msg),
                        "mw_request" => json_encode($client_response->requestoclient),
                        "client_response" => json_encode($client_response),
                        "general_details" => "SUCCESS",
                        "transaction_detail" => "SUCCESS"
                    );
                    GameTransactionMDB::createGameTransactionExtV2($wingametransactionext,$winGametransactionExtId,$client_details);
                    //Helper::updateGameTransactionExt($transactionId,$client_response->requestoclient,$msg,$client_response);
                    Helper::saveLog('responseTime(WAZDANWIN)', 57, json_encode(["starting"=>$this->startTime,"response"=>microtime(true)]), microtime(true) - $this->startTime);
                    // sleep(10);
                    return response($msg,200)
                        ->header('Content-Type', 'application/json');
                }
                else{
                    return "something error with the client";
                }
            }
        } 
    }
    public function gameClose(Request $request){
        $data = $request->getContent();
        $datadecoded = json_decode($data,TRUE);
        $msg = array(
            "status" => 0
        );
        return response($msg,200)
            ->header('Content-Type', 'application/json');
    }
    public function getFunds(Request $request){
        $data = $request->getContent();
        $datadecoded = json_decode($data,TRUE);
        Helper::saveLog('getFund(Wazdan)', 33, $data, "Initialize");
        if($datadecoded["user"]["token"]){
            $client_details = ProviderHelper::getClientDetails('token', $datadecoded["user"]["token"]);
            Helper::saveLog('GetFund (Wazdan)', 33, $data, $client_details);
            if($client_details){
                $balance = round($client_details->balance,2);
                $msg = array(
                    "status" => 0,
                    "funds" => array(
                        "balance" => $balance
                    ),
                );
                return response($msg,200)->header('Content-Type', 'application/json');
            }
            else{
                $msg = array(
                    "status" =>1,
                    "message" => array(
                        "text"=>"This Session Already expired!",
                        "choices"=>array(
                            array(
                                "label" => "Go Back to Game List",
                                "action" => "close_game",
                                "response" => "quit"
                            )
                        )
                    )
                );
                return response($msg,200)->header('Content-Type', 'application/json');
            }
        }
        else{
            $msg = array(
                "status" =>1,
                "message" => array(
                    "text"=>"This Session Already expired please relaunch the game again",
                    "choices"=>array(
                        array(
                            "label" => "Go Back to Game List",
                            "action" => "close_game",
                            "response" => "quit"
                        )
                    )
                )
            );
            return response($msg,200)->header('Content-Type', 'application/json');
        }
    }
    public function getTransactionHistory(Request $request){
        $data = $request->getContent();
        $datadecoded = json_decode($data,TRUE);
        Helper::saveLog('RoundHistory(Wazdan)', 33, $data, "Initialize");
        $key = "uTDVNr4wu6Y78SNbr36bqsSCH904Rcn1";
        $operator_data = config('providerlinks.wazdan.operator_data');
        $license = config('providerlinks.wazdan.license');
        $paramsToSend = [
            "operator" => $operator_data,
            "license" => $license,
            "roundId" => $datadecoded->round_id
        ];
        $signature = hash_hmac("sha256",$paramsToSend,$key);
        dd($signature);
    }
}
