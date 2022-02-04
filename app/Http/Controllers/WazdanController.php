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
class WazdanController extends Controller
{
    //
    private $prefix = 33;
    public function __construct() {
        $this->startTime = microtime(true);
    }
    public function hashCode(Request $request){
        Helper::saveLog('hashCode (Wazdan)', 50, $request->getContent(), "Hash Hit");
        $operator = "tigergames";
        $license = "curacao";
        $key = "uTDVNr4wu6Y78SNbr36bqsSCH904Rcn1";
        $data = array(
            "how" => 'hash_hmac("sha256","'.$request->getContent().'",'.$key.')',
            "hmac"=>hash_hmac("sha256",$request->getContent(),$key)
        );
        Helper::saveLog('hashCode (Wazdan)2', 50, $request->getContent(), $data);
        return $data;
    }
    public function authenticate(Request $request){
        $data = $request->getContent();
        $datadecoded = json_decode($data,TRUE);
        if($datadecoded["token"]){
            $client_details = ProviderHelper::getClientDetails('token', $request->token);
            Helper::saveLog('AuthPlayer (Wazdan)', 50, $data, $client_details);
            if($client_details){
                // $client = new Client([
                //     'headers' => [ 
                //         'Content-Type' => 'application/json',
                //         'Authorization' => 'Bearer '.$client_details->client_access_token
                //     ]
                // ]);
                
                // $guzzle_response = $client->post($client_details->player_details_url,
                //     ['body' => json_encode(
                //             [
                //                 "access_token" => $client_details->client_access_token,
                //                 "hashkey" => md5($client_details->client_api_key.$client_details->client_access_token),
                //                 "type" => "playerdetailsrequest",
                //                 "datesent" => "",
                //                 "gameid" => "",
                //                 "clientid" => $client_details->client_id,
                //                 "playerdetailsrequest" => [
                //                     "player_username"=>$client_details->username,
                //                     "client_player_id"=>$client_details->client_player_id,
                //                     "token" => $client_details->player_token,
                //                     "gamelaunch" => "true"
                //                 ]]
                //     )]
                // );
                // $client_response = json_decode($guzzle_response->getBody()->getContents());
                // Helper::saveLog('AuthPlayer(Wazdan)', 12, $data, $client_response);
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
        $datadecoded = json_decode($data,TRUE);
        Helper::saveLog('getStake(Wazdan)', 50, $data, "Initialize");
        if($datadecoded["user"]["token"]){
            // $client_details = ProviderHelper::getClientDetails('token', $request->token);
            try{
                ProviderHelper::idenpotencyTable($this->prefix.'_'.$datadecoded["transactionId"].'_1');
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
                
                $betgametransactionext = array(
                    "game_trans_id" => $game_transactionid,
                    "provider_trans_id" => $datadecoded["transactionId"],
                    "round_id" => $datadecoded["roundId"],
                    "amount" => round($datadecoded["amount"],2),
                    "game_transaction_type"=>1,
                    "provider_request" =>json_encode($datadecoded),
                );
                $betGametransactionExtId = GameTransactionMDB::createGameTransactionExt($betgametransactionext,$client_details);  
                $fund_extra_data = [
                    'provider_name' => $game_details->provider_name
                ];  
                //insert freespin here
                // if(isset($datadecoded["transaction_id"])){
                //     $fund_extra_data["fundtransferrequest"]["fundinfo"]["freespin"] = true;
                //     $getFreespin = FreeSpinHelper::getFreeSpinDetails($datadecoded["transaction_id"], "provider_trans_id" );

                //     if($getFreespin){
                //       //update transaction
                //          $status = ($getFreespin->spin_remaining - 1) == 0 ? 2 : 1;
                //          $updateFreespinData = [
                //              "status" => $status,
                //              "spin_remaining" => $getFreespin->spin_remaining - 1
                //          ];
                //          $updateFreespin = FreeSpinHelper::updateFreeSpinDetails($updateFreespinData, $getFreespin->freespin_id);
                //          //create transction 
                //          $createFreeRoundTransaction = array(
                //              "game_trans_id" => $game_transactionid,
                //              'freespin_id' => $getFreespin->freespin_id
                //          );
                //          FreeSpinHelper::createFreeRoundTransaction($createFreeRoundTransaction);
                //     }
                // }
                $client_response = ClientRequestHelper::fundTransfer($client_details,round($datadecoded["amount"],2),$game_details->game_code,$game_details->game_name,$betGametransactionExtId,$game_transactionid,"debit",false,$fund_extra_data);
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
                    $dataToUpdate = array(
                        "mw_response" => json_encode($msg)
                    );
                    GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$betGametransactionExtId,$client_details);
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
                        if ($bet_transaction == "false") {
                            // $data = array(
                            //     "win"=>2,
                            //     "transaction_reason" => "FAILED Due to low balance or Client Server Timeout"
                            // );
                            $data = array(
                                "win"=>2
                            );
                            GameTransactionMDB::updateGametransaction($data,$game_transactionid,$client_details);
                            $dataToUpdate = array(
                                "mw_response" => json_encode($msg)
                            );
                            GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$betGametransactionExtId,$client_details);
                        }
                        
                    }catch(\Exception $e){
                        Helper::saveLog('betGameInsuficient(ICG)', 12, json_encode($e->getMessage().' '.$e->getLine()), $client_response->fundtransferresponse->status->message);
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
            Helper::saveLog('betGameInsuficient(Wazdan)', 50, $data, $msg);
            return response($msg,200)
            ->header('Content-Type', 'application/json');
        } 
    }
    public function rollbackState(Request $request){
        
        $data = $request->getContent();
        $datadecoded = json_decode($data,TRUE);
        Helper::saveLog('rollbackStake(Wazdan)', 50, $data, "Initialize");
        if($datadecoded["user"]["token"]){
        $client_details = ProviderHelper::getClientDetails('token', $datadecoded["user"]["token"]);
        if($client_details){
                // $win = 0;
                // $game_details = Helper::getInfoPlayerGameRound($client_details->player_token);
                // $game_details = ProviderHelper::findGameDetails('game_code', $this->prefix, $datadecoded["gameId"]);
                // $json_data = array(
                //     "transid" => $datadecoded["transactionId"],
                //     "amount" => round($datadecoded["amount"],2),
                //     "roundid" => 0,
                // );
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
                        "status" => 0,
                        "funds" => array(
                            "balance" => round($client_details->balance,2)
                        )
                    );
                    Helper::saveLog('refundAlreadyexist(Wazdan)', 50, $data, $msg);
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                $datadecoded["roundId"] = $gameExtension->round_id;
                $updateGametransaction = array(
                    "win" =>4,
                    "pay_amount" =>round($datadecoded["amount"],2),
                    "income" =>$gameExtension->amount-round($datadecoded["amount"],2),
                    "entry_id" =>2,
                );
                GameTransactionMDB::updateGametransaction($updateGametransaction,$gameExtension->game_trans_id,$client_details);
                $refundgametransactionext = array(
                    "game_trans_id" => $gameExtension->game_trans_id,
                    "provider_trans_id" =>  $datadecoded["transactionId"],
                    "round_id" =>$datadecoded["roundId"],
                    "amount" =>round($datadecoded["amount"],2),
                    "game_transaction_type"=>3,
                    "provider_request" =>json_encode($datadecoded),
                );
                $refundgametransactionextId = GameTransactionMDB::createGameTransactionExt($refundgametransactionext,$client_details);
                $fund_extra_data = [
                    'provider_name' => $game_details->provider_name
                ];  
                // $transactionId = WazdanHelper::createWazdanGameTransactionExt($gametransactionid,$datadecoded,null,null,null,3);
                $client_response = ClientRequestHelper::fundTransfer($client_details,round($datadecoded["amount"],2),$game_details->game_code,$game_details->game_name,$refundgametransactionextId,$gameExtension->game_trans_id,"credit",true,$fund_extra_data);
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
                    $dataToUpdate = array(
                        "mw_response" => json_encode($msg)
                    );
                    GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$refundgametransactionext,$client_details);
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
        if($datadecoded["user"]["token"]){
            $client_details = ProviderHelper::getClientDetails('token', $datadecoded["user"]["token"]);
            if($client_details){
                // $returnWinTransaction = WazdanHelper::gameTransactionExtChecker($datadecoded["transactionId"]);
                // if($returnWinTransaction){
                //     $msg = array(
                //         "status" => 0,
                //         "funds" => array(
                //             "balance" => round($client_details->balance,2)
                //         )
                //     );
                //     return response($msg,200)
                //     ->header('Content-Type', 'application/json');
                // }
                // $win = $datadecoded["amount"] == 0 ? 0 : 1;
                // $game_details = Helper::getInfoPlayerGameRound($datadecoded["user"]["token"]);
                // $json_data = array(
                //     "transid" => $datadecoded["transactionId"],
                //     "amount" => round($datadecoded["amount"],2),
                //     "roundid" => $datadecoded["roundId"],
                //     "payout_reason" => null,
                //     "win" => $win,
                // );
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
                $game = GameTransactionMDB::getGameTransactionByRoundId($datadecoded["roundId"],$client_details);
                if($game==null){
                    $msg = array(
                        "status" => 0,
                        "funds" => array(
                            "balance" => round($client_details->balance,2)
                        )
                    );
                    Helper::saveLog('refundAlreadyexist(Wazdan)', 50, $data, $msg);
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                $game_details = ProviderHelper::findGameDetails('game_code', $this->prefix, $datadecoded["gameId"]);

                $win_or_lost = round($datadecoded["amount"],2) == 0 && $game->pay_amount == 0 ? 0 : 1;
                $createGametransaction = array(
                    "win" => 5,
                    "pay_amount" =>$game->pay_amount+round($datadecoded["amount"],2),
                    "income" =>$game->income - round($datadecoded["amount"],2),
                    "entry_id" =>round($datadecoded["amount"],2) == 0 && $game->pay_amount == 0 ? 1 : 2,
                );
                $game_transactionid = GameTransactionMDB::updateGametransaction($createGametransaction,$game->game_trans_id,$client_details);

                //$transactionId= WazdanHelper::createWazdanGameTransactionExt($gametransactionid,$datadecoded,null,null,null,2); 
                $response = array(
                    "status" => 0,
                    "funds" => array(
                        "balance" => $client_details->balance + round($datadecoded["amount"],2)
                    )
                );
                $wingametransactionext = array(
                    "game_trans_id" => $game->game_trans_id,
                    "provider_trans_id" => $datadecoded["transactionId"],
                    "round_id" => $datadecoded["roundId"],
                    "amount" => round($datadecoded["amount"],2),
                    "game_transaction_type"=>2,
                    "provider_request" =>json_encode($datadecoded),
                    "mw_response" => json_encode($response)
                );
                $winGametransactionExtId = GameTransactionMDB::createGameTransactionExt($wingametransactionext,$client_details);
        
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
                //$client_response = ClientRequestHelper::fundTransfer($client_details,round($datadecoded["amount"],2),$game_details->game_code,$game_details->game_name,$transactionId,$gametransactionid,"credit");
                $client_response = ClientRequestHelper::fundTransfer_TG($client_details,round($datadecoded["amount"],2),$game_details->game_code,$game_details->game_name,$game->game_trans_id,'credit',false,$action_payload);
                if(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "200"){
                    $balance = round($client_response->fundtransferresponse->balance,2);
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    $msg = array(
                        "status" => 0,
                        "funds" => array(
                            "balance" => $balance
                        )
                    );
                    //Helper::updateGameTransactionExt($transactionId,$client_response->requestoclient,$msg,$client_response);
                    //Helper::saveLog('responseTime(WAZDANWIN)', 12, json_encode(["starting"=>$this->startTime,"response"=>microtime(true)]), microtime(true) - $this->startTime);
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
        Helper::saveLog('getFund(Wazdan)', 50, $data, "Initialize");
        if($datadecoded["user"]["token"]){
            $client_details = ProviderHelper::getClientDetails('token', $datadecoded["user"]["token"]);
            Helper::saveLog('GetFund (Wazdan)', 50, $data, $client_details);
            if($client_details){
                // $client = new Client([
                //     'headers' => [ 
                //         'Content-Type' => 'application/json',
                //         'Authorization' => 'Bearer '.$client_details->client_access_token
                //     ]
                // ]);
                
                // $guzzle_response = $client->post($client_details->player_details_url,
                //     ['body' => json_encode(
                //             [
                //                 "access_token" => $client_details->client_access_token,
                //                 "hashkey" => md5($client_details->client_api_key.$client_details->client_access_token),
                //                 "type" => "playerdetailsrequest",
                //                 "datesent" => "",
                //                 "gameid" => "",
                //                 "clientid" => $client_details->client_id,
                //                 "playerdetailsrequest" => [
                //                     "player_username"=>$client_details->username,
                //                     "client_player_id"=>$client_details->client_player_id,
                //                     "token" => $client_details->player_token,
                //                     "gamelaunch" => "true"
                //                 ]]
                //     )]
                // );
                // $client_response = json_decode($guzzle_response->getBody()->getContents());
                // Helper::saveLog('AuthPlayer(Wazdan)', 12, $data, $client_response);
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
   
}
