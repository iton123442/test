<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Helpers\ClientRequestHelper;
use App\Helpers\TransactionHelper;
use App\Helpers\DOWINNHelper;
use App\Models\GameTransactionMDB;
use App\Models\GameTransaction;
use DB;
use Exception;

class DOWINNController extends Controller{

    protected $startTime;
    public function __construct() {
        $this->startTime = microtime(true);
        $this->provider_db_id = config('providerlinks.dowinn.provider_db_id'); //sub provider ID
        $this->api_key = config('providerlinks.dowinn.api_key');
        $this->api_url = config('providerlinks.dowinn.api_url');
        $this->prefix = config('providerlinks.dowinn.prefix');
        $this->providerID = 72; //Real provider ID
        $this->dateToday = date("Y/m/d");
    }

    // public function index(Request $request){
    //     $data = json_decode($request->getContent(),TRUE);
    //     $client_details = ProviderHelper::getClientDetails('token', $data['token']);
    //     if($client_details){
    //         if(isset($data['transaction']) && isset($data['game'])){
    //             if($data['transaction']['type'] == 'bet'){
    //                 $result = $this->bet($data,$client_details);
    //                 Helper::saveLog('DOWINN BET',$this->provider_db_id, json_encode($result), 'BET HIT');
    //                 return $result;
    //             }
    //             elseif($data['transaction']['type'] == 'award'){
    //                 $result = $this->payment($data,$client_details);
    //                 Helper::saveLog('DOWINN WIN',$this->provider_db_id, json_encode($result), 'WIN HIT');
    //                 return $result;
    //             }
    //             elseif($data['transaction']['type'] == 'cancel'){
    //                 $result = $this->cancel($data,$client_details);
    //                 Helper::saveLog('DOWINN CANCEL',$this->provider_db_id, json_encode($result), 'CANCEL HIT');
    //                 return $result;
    //             }
    //             elseif($data['transaction']['type'] == 'tip'){
    //                 $result = $this->tip($data,$client_details);
    //                 Helper::saveLog('DOWINN TIP',$this->provider_db_id, json_encode($result), 'TIP HIT');
    //                 return $result;
    //             }
    //         }else{
    //             $result = $this->balance($data,$client_details);
    //             Helper::saveLog('DOWINN BALANCE',$this->provider_db_id, json_encode($result), json_encode($data));
    //             return $result;
    //         }
    //     }
    // }

    public function balance(Request $request){
        $data = json_decode($request->getContent(),TRUE);
        $client_details = ProviderHelper::getClientDetails('token', $data['token']);
        Helper::saveLog('DOWINN GetBALANCE', $this->provider_db_id, json_encode($data), 'Balance HIT!');
        if($client_details){
            $response = array(
                "status" =>'OK',
                "balance"=>(int) number_format($client_details->balance,2,'.', ''),
                "uuid" => $data['uuid'],
            );
            return response($response,200)->header('Content-Type', 'application/json');
        }
    }

    public function bet(Request $request){
        $data = json_decode($request->getContent(),TRUE);
        $client_details = ProviderHelper::getClientDetails('token', $data['token']);
        Helper::saveLog("BET PROCESS", 139,json_encode($data),"BET ON PROCESSING!");
        if($client_details){
            $token = $client_details->player_token;
            $guid = substr("abcdefghijklmnopqrstuvwxyz1234567890", mt_rand(0, 25), 1).substr(md5(time()), 1);
            $playerChecker = DOWINNHelper::checkBalanceAndStatus($token,$guid,$this->prefix,$client_details);//this is authentication
            if($playerChecker['code'] == 0 && $playerChecker['ingame'] == true){
                try{
                    ProviderHelper::idenpotencyTable($this->prefix.'_'.$data['transaction']['id'].'_1');
                }catch(\Exception $e){
                    $response = array(
                        "code" =>'210',
                        "extra"=>'Duplicate Transaction number',
                    );
                    return response($response,200)->header('Content-Type', 'application/json');
                }
                $transId = $data['uuid'];
                $roundId = $data['transaction']['roundId'];
                $gamedetails = ProviderHelper::findGameDetails('game_code', $this->providerID, 'DOWINN');
                $bet_transaction = GameTransactionMDB::getGameTransactionByRoundId($roundId,$client_details);
                if($bet_transaction != null){
                    //this is double bet
                    $game_trans_id = $bet_transaction->game_trans_id;
                    $updateTransaction = [
                        "win" => 5,
                        "trans_status" => 1
                    ];
                    GameTransactionMDB::updateGametransaction($updateTransaction,$game_trans_id,$client_details);
                    $gametransExt_data = [
                        "game_trans_id" => $game_trans_id,
                        "provider_trans_id" => $transId,
                        "round_id" => $roundId,
                        "amount" => $data['transaction']['amount'],
                        "game_transaction_type" => 1,
                        "provider_request" => json_encode($data),
                    ];
                    $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gametransExt_data,$client_details);
                    $client_response = ClientRequestHelper::fundTransfer($client_details,$data['transaction']['amount'],$gamedetails->game_code,$gamedetails->game_name,$game_trans_ext_id,$bet_transaction->game_trans_id,'debit');
                    if(isset($client_response->fundtransferresponse->status->code)
                    && $client_response->fundtransferresponse->status->code == "200"){
                        $balance = round($client_response->fundtransferresponse->balance, 2);
                        ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                        //SUCCESS FUNDTRANSFER
                        $updateTransData = [
                            "win" => 5,
                            "bet_amount" => $bet_transaction->bet_amount + $data['transaction']['amount'],
                        ];
                        GameTransactionMDB::updateGametransaction($updateTransData,$game_trans_id,$client_details);
                        $response = [
                            "status" => 'OK',
                            "balance" => $balance,
                            "uuid" => $data['uuid'],
                        ];
                        $extensionData = [
                            "mw_request" => json_encode($client_response->requestoclient),
                            "mw_response" =>json_encode($response),
                            "client_response" => json_encode($client_response),
                            "transaction_detail" => "Success",
                            "general_details" => "Success",
                        ];
                        GameTransactionMDB::updateGametransactionEXT($extensionData,$game_trans_ext_id,$client_details);
                        return response($response,200)->header('Content-Type', 'application/json');
                    }elseif(isset($client_response->fundtransferresponse->status->code)
                    && $client_response->fundtransferresponse->status->code == "402"){
                        try{    
                            $updateTrans = [
                                "win" => 2,
                                "trans_status" => 5
                            ];
                            GameTransactionMDB::updateGametransaction($updateTrans,$game_trans_id,$client_details);
                            $response = [
                                "code" => 51,
                                "extra" => "Invalid Request"
                            ];
                            $updateExt = [
                                "mw_request" => json_encode('FAILED'),
                                "mw_response" =>json_encode($response),
                                "client_response" => json_encode($client_response),
                                "transaction_detail" => "FAILED",
                                "general_details" => "FAILED",
                            ];
                            GameTransactionMDB::updateGametransactionEXT($updateExt,$game_trans_ext_id,$client_details);
                            return response($response,200)->header('Content-Type', 'application/json');
                        }catch(\Exception $e){
                        Helper::saveLog("FAILED BET", 139,json_encode($client_response),"FAILED HIT!");
                        }
                    }
                }//END OF DOUBLE BET
                $betAmount = round($data['transaction']['amount'],2);
                $gameTransactionDatas = [
                    "provider_trans_id" => $transId,
                    "token_id" => $client_details->token_id,
                    "game_id" => $gamedetails->game_id,
                    "round_id" => $roundId,
                    "bet_amount" => $betAmount,
                    "pay_amount" => 0,
                    "win" => 5,
                    "income" => 0,
                    "entry_id" => 1
                ];
                $game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionDatas,$client_details);
                $gameExtensionData = [
                    "game_trans_id" => $game_trans_id,
                    "provider_trans_id" => $transId,
                    "round_id" => $roundId,
                    "amount" => $betAmount,
                    "game_transaction_type" => 1,
                    "provider_request" => json_encode($data),
                ];
                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameExtensionData,$client_details);
                $fund_extra_data = [
                    'provider_name' => $gamedetails->provider_name
                ];
                $client_response = ClientRequestHelper::fundTransfer($client_details,$betAmount,$gamedetails->game_code,$gamedetails->game_name,$game_trans_ext_id,$game_trans_id,'debit',false,$fund_extra_data);
                if(isset($client_response->fundtransferresponse->status->code)
                && $client_response->fundtransferresponse->status->code == "200"){
                    $balance = round($client_response->fundtransferresponse->balance, 2);
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    //SUCCESS FUNDTRANSFER
                    $updateTransData = [
                        "win" => 5,
                    ];
                    GameTransactionMDB::updateGametransaction($updateTransData,$game_trans_id,$client_details);
                    $response = [
                        "status" => 'OK',
                        "balance" => $balance,
                        "uuid" => $data['uuid'],
                    ];
                    $extensionData = [
                        "mw_response" =>json_encode($response),
                        "client_response" => json_encode($client_response),
                        "mw_request" => json_encode($client_response->requestoclient),
                        "transaction_detail" => "Success",
                        "general_details" => "Success",
                    ];
                    GameTransactionMDB::updateGametransactionEXT($extensionData,$game_trans_ext_id,$client_details);
                    return response($response,200)->header('Content-Type', 'application/json');
                }elseif(isset($client_response->fundtransferresponse->status->code)
                && $client_response->fundtransferresponse->status->code == "402"){
                    try{    
                        $updateTrans = [
                            "win" => 2,
                            "trans_status" => 5
                        ];
                        GameTransactionMDB::updateGametransaction($updateTrans,$game_trans_id,$client_details);
                        $response = [
                            "code" => 51,
                            "extra" => "Invalid Request"
                        ];
                        $updateExt = [
                            "mw_response" =>json_encode($response),
                            "client_response" => json_encode($client_response),
                            "mw_request" => json_encode("FAILED"),
                            "transaction_detail" => "FAILED",
                            "general_details" => "FAILED",
                        ];
                        GameTransactionMDB::updateGametransactionEXT($updateExt,$game_trans_ext_id,$client_details);
                        return response($response,200)->header('Content-Type', 'application/json');
                    }catch(\Exception $e){
                    Helper::saveLog("FAILED BET", 139,json_encode($client_response),"FAILED HIT!");
                    }
                }
            }
        }
    }

    public function payment(Request $request){
        $data = json_decode($request->getContent(),TRUE);
        $client_details = ProviderHelper::getClientDetails('token', $data['token']);
        Helper::saveLog("WIN PROCESS", 139,json_encode($data),"WIN ON PROCESSING!");
        if($client_details){
            try{
                ProviderHelper::idenpotencyTable($this->prefix.'_'.$data['transaction']['id'].'_2');
            }catch(\Exception $e){
                $response = array(
                    "code" =>'210',
                    "extra"=>'Duplicate Transaction number',
                );
                return response($response,200)->header('Content-Type', 'application/json');
            }
            $transId = $data['uuid'];
            $roundId = $data['transaction']['roundId'];
            $cancelTransExt = GameTransactionMDB::findDOWINNGameExt($roundId,'round_id',3,$client_details);
            if($cancelTransExt['0']->amount != null){
                $cancelTotal = $cancelTransExt['0']->amount;
            }else{
                $cancelTotal = 0;
            }
            $gamedetails = ProviderHelper::findGameDetails('game_code', $this->providerID, 'DOWINN');
            $winAmount = round($data['transaction']['amount'],2);
            $game = GameTransactionMDB::getGameTransactionByRoundId($roundId,$client_details);
            $win_or_lost = $data['transaction']['amount'] == 0 ? 0 : 1;
            $afterBalance = $data['transaction']['amount'] + $client_details->balance;
            if($game == null){
                Helper::saveLog("NO BET FOUND", 139,json_encode($data),"HIT!");
                $gameTransactionDatas = [
                    "provider_trans_id" => $transId,
                    "token_id" => $client_details->token_id,
                    "game_id" => $gamedetails->game_id,
                    "round_id" => $roundId,
                    "bet_amount" => $winAmount,
                    "pay_amount" => 0,
                    "win" => 5,
                    "income" => 0,
                    "entry_id" => 1
                ];
                $game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionDatas,$client_details);
                $gameExtensionData = [
                    "game_trans_id" => $game_trans_id,
                    "provider_trans_id" => $transId,
                    "round_id" => $roundId,
                    "amount" => $winAmount,
                    "game_transaction_type" => 1,
                    "provider_request" => json_encode($data),
                ];
                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameExtensionData,$client_details);
                $fund_extra_data = [
                    'provider_name' => $gamedetails->provider_name
                ];
                $client_response = ClientRequestHelper::fundTransfer($client_details,$winAmount,$gamedetails->game_code,$gamedetails->game_name,$game_trans_ext_id,$game_trans_id,'debit',false,$fund_extra_data);
                if(isset($client_response->fundtransferresponse->status->code)
                && $client_response->fundtransferresponse->status->code == "200"){
                    $balance = round($client_response->fundtransferresponse->balance, 2);
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    //SUCCESS FUNDTRANSFER
                    $updateTransData = [
                        "win" => 5,
                    ];
                    GameTransactionMDB::updateGametransaction($updateTransData,$game_trans_id,$client_details);
                    $response = [
                        "status" => 'OK',
                        "balance" => $balance,
                        "uuid" => $data['uuid'],
                    ];
                    $extensionData = [
                        "mw_response" =>json_encode($response),
                        "mw_request" => json_encode($client_response->requestoclient),
                        "client_response" => json_encode($client_response),
                        "transaction_detail" => "Success",
                        "general_details" => "Success",
                    ];
                    GameTransactionMDB::updateGametransactionEXT($extensionData,$game_trans_ext_id,$client_details);
                    return response($response,200)->header('Content-Type', 'application/json');
                }elseif(isset($client_response->fundtransferresponse->status->code)
                && $client_response->fundtransferresponse->status->code == "402"){
                    try{    
                        $updateTrans = [
                            "win" => 2,
                            "trans_status" => 5
                        ];
                        GameTransactionMDB::updateGametransaction($updateTrans,$game_trans_id,$client_details);
                        $response = [
                            "code" => 51,
                            "extra" => "Invalid Request"
                        ];
                        $updateExt = [
                            "mw_response" =>json_encode($response),
                            "mw_request" => json_encode('FAILED'),
                            "client_response" => json_encode($client_response),
                            "transaction_detail" => "FAILED",
                            "general_details" => "FAILED",
                        ];
                        GameTransactionMDB::updateGametransactionEXT($updateExt,$game_trans_ext_id,$client_details);
                        return response($response,200)->header('Content-Type', 'application/json');
                    }catch(\Exception $e){
                        Helper::saveLog("FAILED WIN", 139,json_encode($client_response),"FAILED HIT!");
                    }
                }
            }
            $realBet = round($game->bet_amount-$cancelTotal,2);
            $updateTransData = [
                "win" => 5,
                "entry_id" => $winAmount == 0 && $game->pay_amount == 0 ? 1 : 2,
                "trans_status" => 2,
                "bet_amount" => $realBet,
                "income" => $realBet-$winAmount,
            ];
            GameTransactionMDB::updateGametransaction($updateTransData,$game->game_trans_id,$client_details);
            $gameExtensionData = [
                "game_trans_id" => $game->game_trans_id,
                "provider_trans_id" => $transId,
                "round_id" => $roundId,
                "amount" => $winAmount,
                "game_transaction_type" => 2,
                "provider_request" => json_encode($data),
            ];
            $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameExtensionData,$client_details);
            $response = [
                "status" => "OK",
                "balance" => round($afterBalance,2),
                "uuid" =>$data['uuid'],
            ];
            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "provider" => 'DOWINN',
                    "game_transaction_ext_id" => $game_trans_ext_id,
                    "client_connection_name" => $client_details->connection_name,
                    "win_or_lost" => $win_or_lost,
                ],
                "provider" => [
                    "provider_request" => json_encode($data),
                    "provider_trans_id"=>$transId,
                    "provider_round_id"=>$roundId,
                ],
                "mwapi" => [
                    "roundId"=> $game->game_trans_id,
                    "type" => 2,
                    "game_id" => $gamedetails->game_id,
                    "player_id" => $client_details->player_id,
                    "mw_response" => json_encode($response),
                ]
            ];
            $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$winAmount,$gamedetails->game_code,$gamedetails->game_name,$game->game_trans_id,'credit',false,$action_payload);
            if(isset($client_response->fundtransferresponse->status->code) &&
            $client_response->fundtransferresponse->status->code == "200"){
                $winTransExt = GameTransactionMDB::findDOWINNGameExt($roundId,'round_id',2,$client_details);
                $balance = round($client_response->fundtransferresponse->balance, 2);
                ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                //SUCCESS FUNDTRANSFER
                if($winTransExt['0']->amount != null){
                    if($winTransExt['0']->amount > 1){
                        $updateTransData = [
                            "win" => 1,
                            "pay_amount" => round($winTransExt['0']->amount,2),
                        ];
                        GameTransactionMDB::updateGametransaction($updateTransData,$game->game_trans_id,$client_details);
                    }
                    else{
                        $updateTransData = [
                            "win" => 0,
                            "pay_amount" => round($winTransExt['0']->amount,2),
                        ];
                        GameTransactionMDB::updateGametransaction($updateTransData,$game->game_trans_id,$client_details);
                    }
                }else{
                    $updateTransData = [
                        "win" => $win_or_lost,
                        "pay_amount" => $game->pay_amount+$winAmount,
                    ];
                    GameTransactionMDB::updateGametransaction($updateTransData,$game->game_trans_id,$client_details);

                }
                $response = [
                    "status" => 'OK',
                    "balance" => $balance,
                    "uuid" => $data['uuid'],
                ];
                $extensionData = [
                    "mw_response" =>json_encode($response),
                    "client_response" => json_encode($client_response),
                    "mw_request" => json_encode($client_response->requestoclient),
                    "transaction_detail" => "Success",
                    "general_details" => "Success",
                ];
                GameTransactionMDB::updateGametransactionEXT($extensionData,$game_trans_ext_id,$client_details);
                return response($response,200)->header('Content-Type', 'application/json');
            }
        }
    }

    public function cancel(Request $request){
        $data = json_decode($request->getContent(),TRUE);
        $client_details = ProviderHelper::getClientDetails('token', $data['token']);
        Helper::saveLog("CANCEL PROCESS", 139,json_encode($data),"CANCEL ON PROCESSING!");
        if($client_details){
            try{
                ProviderHelper::idenpotencyTable($this->prefix.'_'.$data['transaction']['id'].'_3');
            }catch(\Exception $e){
                $response = array(
                    "code" =>'210',
                    "extra"=>'Duplicate Transaction number',
                );
                return response($response,200)->header('Content-Type', 'application/json');
            }
            $transId = $data['transaction']['id'];
            $roundId = $data['transaction']['roundId'];
            $gamedetails = ProviderHelper::findGameDetails('game_code', $this->providerID, 'DOWINN');
            $refundAmount = round($data['transaction']['amount'],2);
            $game = GameTransactionMDB::getGameTransactionByRoundId($roundId,$client_details);
            $win_or_lost = 4;
            $afterBalance = $data['transaction']['amount'] + $client_details->balance;
            if($game == null){
                Helper::saveLog("NO BET FOUND", 139,json_encode($data),"HIT!");
                $gameTransactionDatas = [
                    "provider_trans_id" => $transId,
                    "token_id" => $client_details->token_id,
                    "game_id" => $gamedetails->game_id,
                    "round_id" => $roundId,
                    "bet_amount" => $refundAmount,
                    "pay_amount" => 0,
                    "win" => 5,
                    "income" => 0,
                    "entry_id" => 1
                ];
                $game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionDatas,$client_details);
                $gameExtensionData = [
                    "game_trans_id" => $game_trans_id,
                    "provider_trans_id" => $transId,
                    "round_id" => $roundId,
                    "amount" => $refundAmount,
                    "game_transaction_type" => 1,
                    "provider_request" => json_encode($data),
                ];
                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameExtensionData,$client_details);
                $fund_extra_data = [
                    'provider_name' => $gamedetails->provider_name
                ];
                $client_response = ClientRequestHelper::fundTransfer($client_details,$refundAmount,$gamedetails->game_code,$gamedetails->game_name,$game_trans_ext_id,$game_trans_id,'debit',false,$fund_extra_data);
                if(isset($client_response->fundtransferresponse->status->code)
                && $client_response->fundtransferresponse->status->code == "200"){
                    $balance = round($client_response->fundtransferresponse->balance, 2);
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    //SUCCESS FUNDTRANSFER
                    $updateTransData = [
                        "win" => 5,
                    ];
                    GameTransactionMDB::updateGametransaction($updateTransData,$game_trans_id,$client_details);
                    $response = [
                        "status" => 'OK',
                        "balance" => $balance,
                        "uuid" => $data['uuid'],
                    ];
                    $extensionData = [
                        "mw_response" =>json_encode($response),
                        "mw_request" => json_encode($client_response->requestoclient),
                        "client_response" => json_encode($client_response),
                        "transaction_detail" => "Success",
                        "general_details" => "Success",
                    ];
                    GameTransactionMDB::updateGametransactionEXT($extensionData,$game_trans_ext_id,$client_details);
                    return response($response,200)->header('Content-Type', 'application/json');
                }elseif(isset($client_response->fundtransferresponse->status->code)
                && $client_response->fundtransferresponse->status->code == "402"){
                    try{    
                        $updateTrans = [
                            "win" => 2,
                            "trans_status" => 5
                        ];
                        GameTransactionMDB::updateGametransaction($updateTrans,$game_trans_id,$client_details);
                        $response = [
                            "code" => 51,
                            "extra" => "Invalid Request"
                        ];
                        $updateExt = [
                            "mw_response" =>json_encode($response),
                            "mw_request" => json_encode('FAILED'),
                            "client_response" => json_encode($client_response),
                            "transaction_detail" => "FAILED",
                            "general_details" => "FAILED",
                        ];
                        GameTransactionMDB::updateGametransactionEXT($updateExt,$game_trans_ext_id,$client_details);
                        return response($response,200)->header('Content-Type', 'application/json');
                    }catch(\Exception $e){
                        Helper::saveLog("FAILED CANCEL", 139,json_encode($client_response),"FAILED HIT!");
                    }
                }
            }
            $updateTransData = [
                "win" => 5,
                "entry_id" => 2,
                "trans_status" => 2,
                "pay_amount" => $game->pay_amount+$refundAmount,
                "income" => round($game->bet_amount-$refundAmount,2),
            ];
            GameTransactionMDB::updateGametransaction($updateTransData,$game->game_trans_id,$client_details);
            $gameExtensionData = [
                "game_trans_id" => $game->game_trans_id,
                "provider_trans_id" => $transId,
                "round_id" => $roundId,
                "amount" => $refundAmount,
                "game_transaction_type" => 3,
                "provider_request" => json_encode($data),
            ];
            $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameExtensionData,$client_details);
            $response = [
                "status" => "OK",
                "balance" => round($afterBalance,2),
                "uuid" =>$data['uuid'],
            ];
            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "provider" => 'DOWINN',
                    "game_transaction_ext_id" => $game_trans_ext_id,
                    "client_connection_name" => $client_details->connection_name,
                    "win_or_lost" => $win_or_lost,
                ],
                "provider" => [
                    "provider_request" => json_encode($data),
                    "provider_trans_id"=>$transId,
                    "provider_round_id"=>$roundId,
                ],
                "mwapi" => [
                    "roundId"=> $game->game_trans_id,
                    "type" => 3,
                    "game_id" => $gamedetails->game_id,
                    "player_id" => $client_details->player_id,
                    "mw_response" => json_encode($response),
                ]
            ];
            $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$refundAmount,$gamedetails->game_code,$gamedetails->game_name,$game->game_trans_id,'credit',false,$action_payload);
            if(isset($client_response->fundtransferresponse->status->code) &&
            $client_response->fundtransferresponse->status->code == "200"){
                Helper::saveLog("CANCEL FUNDTRANSFER", 139,json_encode($client_response),"FUNDTRANSFER SUCCESS!");
                $balance = round($client_response->fundtransferresponse->balance, 2);
                ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                //SUCCESS FUNDTRANSFER
                $updateTransData = [
                    "win" => $win_or_lost,
                ];
                GameTransactionMDB::updateGametransaction($updateTransData,$game->game_trans_id,$client_details);
                $response = [
                    "status" => 'OK',
                    "balance" => $balance,
                    "uuid" => $data['uuid'],
                ];
                $extensionData = [
                    "mw_response" =>json_encode($response),
                    "client_response" => json_encode($client_response),
                    "mw_request" => json_encode($client_response->requestoclient),
                    "transaction_detail" => "Success",
                    "general_details" => "Success",
                ];
                GameTransactionMDB::updateGametransactionEXT($extensionData,$game_trans_ext_id,$client_details);
                return response($response,200)->header('Content-Type', 'application/json');
            }
        }
    }
    
    public function tip(Request $request){
        $data = json_decode($request->getContent(),TRUE);
        $client_details = ProviderHelper::getClientDetails('token', $data['token']);
        if($client_details){
            $token = $client_details->player_token;
            $guid = substr("abcdefghijklmnopqrstuvwxyz1234567890", mt_rand(0, 25), 1).substr(md5(time()), 1);
            $playerChecker = DOWINNHelper::checkBalanceAndStatus($token,$guid,$this->prefix,$client_details);//this is authentication
            if($playerChecker->code == 0 && $playerChecker->ingame != 'false'){
                try{
                    ProviderHelper::idenpotencyTable($this->prefix.'_'.$data['transaction']['id'].'_4');
                }catch(\Exception $e){
                    $response = array(
                        "status" =>'OK',
                        "balance"=>(int) number_format($client_details->balance,2,'.', ''),
                        "uuid" => $data['uuid'],
                    );
                    return response($response,200)->header('Content-Type', 'application/json');
                }
            }else{
                //error msg
                //Member offline
            }
        }
    }

    public function limitList(Request $request){
        $dataToSend = [
            "account" => config('providerlinks.dowinn.user_agent'),
        ];
        // Helper::saveLog('DOWINN STATUS CHECKER', 139, json_encode($dataToSend), 'REQUEST');
        $client = new Client([
            'headers' => [
                'Content-Type' => 'x-www-form-urlencoded' 
            ],
        ]);
        $response = $client->post(config('providerlinks.dowinn.api_url').'/limits.do',
        ['form_params' => $dataToSend,]);
        $response = json_decode($response->getBody(),TRUE);
        // Helper::saveLog('DOWINN LOGIN/AUTH', 139, json_encode($response), 'LOGIN HIT!');
        return($response);
    }
}
?>