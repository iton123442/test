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
use App\Jobs\UpdateGametransactionJobs;
use App\Models\GameTransactionMDB;
use App\Models\GameTransaction;
use DB;
use Exception;

class NagaGamesController extends Controller{

    protected $startTime;
    public function __construct() {
        $this->startTime = microtime(true);
        $this->provider_db_id = config('providerlinks.naga.provider_db_id'); //sub provider ID
        $this->secretKey = config('providerlinks.naga.secretKey');
        $this->apiKey = config('providerlinks.naga.apiKey');
        $this->publicKey = config('providerlinks.naga.publicKey');
        $this->prefix = config('providerlinks.dowinn.prefix');
        $this->providerID = 72; //Real provider ID
        $this->dateToday = date("Y/m/d");
    }

    public function auth(Request $request){
        $data = json_decode($request->getContent(),TRUE);
        Helper::saveLog('Naga Games Authorize', $this->provider_db_id, json_encode($data), 'Auth HIT!');
        $client_details = ProviderHelper::getClientDetails('token', $data['data']['playerToken']);
        $hash = $this-> hashParam($data['data']);
        if($client_details){
            $response = array(
                "data"=> [
                "nativeId"=>"TG_" . $client_details->player_id,
                "currency"=>"USD",
                "balance"=>number_format($client_details->balance,2,'.', '')
                ],
                "error" => null
            );
            return response($response,200)->header('Content-Type', 'application/json');
        }
    }

    
    public function hashParam($sortData){
        // ksort($sortData);
        // $param = "";
        // $i = 0;
        $clean1 = hash_hmac('sha256',json_encode($sortData),$this->apiKey);
        Helper::saveLog('Naga Games Hasher1', $this->provider_db_id, json_encode($clean1), 'HASH!');
        // foreach($sortData as $key => $item){
        //     if($key != 'hash'){
        //         if($i == 0){
        //             $param .= $key ."=". $item;
        //         }else{
        //             $param .= "&".$key ."=". $item;
        
        $clean2 = hash_hmac('sha256',json_encode($sortData),$this->secretKey);
        Helper::saveLog('Naga Games Hasher2', $this->provider_db_id, json_encode($clean2), 'HASH!');
        //         }
        //         $i++;
        //     }
        // }
        // $str = str_replace("\n","",$param.$this->api_key);
        // $clean = str_replace("\r","",$str);
        $clean = hash_hmac('sha256',json_encode($sortData),$this->publicKey);
        Helper::saveLog('Naga Games Hasher3', $this->provider_db_id, json_encode($clean), 'HASH!');
        return $clean;
    }
    public function getBalance(Request $request){
        $data = json_decode($request->getContent(),TRUE);
        $client_details = ProviderHelper::getClientDetails('token', $data['data']['playerToken']);
        $hash = $this-> hashParam($data['data']);
        Helper::saveLog('NAGAGAMES GetBALANCE', $this->provider_db_id, json_encode($data), 'Balance HIT!');
        // $hash = $this-> hashParam($data);
        if($client_details){
            $response = array(
                "data"=> [
                "nativeId"=>"TG_" . $client_details->player_id,
                "currency"=>"USD",
                "balance"=>number_format($client_details->balance,2,'.', '')
                ],
                "error" => null
            );
            return response($response,200)->header('Content-Type', 'application/json');
        }
    }
    public function placeBet (Request $request){
        $data = json_decode($request->getContent(),TRUE);
        $client_details = ProviderHelper::getClientDetails('token', $data['data']['playerToken']);
        Helper::saveLog('NAGAGAMES Bet', $this->provider_db_id, json_encode($data), 'BET HIT!');
        if ($client_details){
            // $response = array(
            //     "data"=> [
            //     "currency"=>"USD",
            //     "balance"=>number_format($client_details->balance,2,'.', '')
            //     ],
            //     "error" => null
            // );
            // return response($response,200)->header('Content-Type', 'application/json');
            try{
                ProviderHelper::IdenpotencyTable($data['data']['transactionId']);
            }catch(\Exception $e){
                $response = array(
                    "data"=> null,
                    "error" => [
                        "statusCode" => 500,
                        "message" => "Cannot read properties of undefined (reading 'realMoney')"
                    ]
                );
                return response($response,200)->header('Content-Type', 'application/json');
            }
            $roundId = $data['data']['betId'];
            $provider_trans_id = $data['data']['transactionId'];
            $amount = $data['data']['amount'];
            $gamedetails = ProviderHelper::findGameDetails('game_code', 74, $data['data']['gameCode']);
            $bet_transaction = GameTransactionMDB::getGameTransactionByRoundId($roundId,$client_details);
            if($bet_transaction != null){
                //this is double bet
                $game_trans_id = $bet_transaction->game_trans_id;
                $updateTransaction = [
                    "win" => 5,
                    "trans_status" => 1,
                    "bet_amount" => $bet_transaction->bet_amount+$amount,
                ];
                GameTransactionMDB::updateGametransaction($updateTransaction,$game_trans_id,$client_details);
                $gametransExt_data = [
                    "game_trans_id" => $game_trans_id,
                    "provider_trans_id" => $provider_trans_id,
                    "round_id" => $roundId,
                    "amount" => $amount,
                    "game_transaction_type" => 1,
                    "provider_request" => json_encode($data),
                ];
                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gametransExt_data,$client_details);
                $client_response = ClientRequestHelper::fundTransfer($client_details,$amount,$gamedetails->game_code,$gamedetails->game_name,$game_trans_ext_id,$bet_transaction->game_trans_id,'debit');
                if(isset($client_response->fundtransferresponse->status->code)
                && $client_response->fundtransferresponse->status->code == "200"){
                    $balance = round($client_response->fundtransferresponse->balance, 2);
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    //SUCCESS FUNDTRANSFER
                    $response = [
                        "data" => [
                            "currency"=>"USD",
                            "balance"=>round($client_details->balance,2),
                        ]
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
                            "data"=> null,
                            "error" => [
                                "statusCode" => 500,
                                "message" => "Cannot read properties of undefined (reading 'realMoney')"
                            ],
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
                    Helper::saveLog("FAILED BET", 141,json_encode($client_response),"FAILED HIT!");
                    }
                }
            }
            $gameTransactionDatas = [
                "provider_trans_id" => $provider_trans_id,
                "token_id" => $client_details->token_id,
                "game_id" => $gamedetails->game_id,
                "round_id" => $roundId,
                "bet_amount" => $amount,
                "pay_amount" => 0,
                "win" => 5,
                "income" => 0,
                "entry_id" => 1
            ];
            $game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionDatas,$client_details);
            $gameExtensionData = [
                "game_trans_id" => $game_trans_id,
                "provider_trans_id" => $provider_trans_id,
                "round_id" => $roundId,
                "amount" => $amount,
                "game_transaction_type" => 1,
                "provider_request" => json_encode($data),
            ];
            $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameExtensionData,$client_details);
            $fund_extra_data = [
                'provider_name' => $gamedetails->provider_name
            ];
            $client_response = ClientRequestHelper::fundTransfer($client_details,$amount,$gamedetails->game_code,$gamedetails->game_name,$game_trans_ext_id,$game_trans_id,'debit',false,$fund_extra_data);
            if(isset($client_response->fundtransferresponse->status->code)
            && $client_response->fundtransferresponse->status->code == "200"){
                Helper::saveLog('NAGAGAMES Bet', $this->provider_db_id, json_encode($data), 'FUNDTRANSFER HIT!');
                $balance = round($client_response->fundtransferresponse->balance, 2);
                ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                //SUCCESS FUNDTRANSFER
                $response = [
                    "data" => [
                        "currency"=>"USD",
                        "balance"=>round($client_details->balance,2),
                    ]
                ];
                $extensionData = [
                    "mw_request" => json_encode($client_response->requestoclient),
                    "mw_response" =>json_encode($response),
                    "client_response" => json_encode($client_response),
                    "transaction_detail" => "Success",
                    "general_details" => "Success",
                ];
                GameTransactionMDB::updateGametransactionEXT($extensionData,$game_trans_ext_id,$client_details);
                Helper::saveLog('NAGAGAMES Bet', $this->provider_db_id, json_encode($response), 'Success HIT!');
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
                        "data"=> null,
                        "error" => [
                            "statusCode" => 500,
                            "message" => "Cannot read properties of undefined (reading 'realMoney')"
                        ],
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
                Helper::saveLog("FAILED BET", 141,json_encode($client_response),"FAILED HIT!");
                }
            }
        }
    }

    public function payout (Request $request){
        $data = json_decode($request->getContent(),TRUE);
        $client_details = ProviderHelper::getClientDetails('token', $data['data']['playerToken']);
        Helper::saveLog('NAGAGAMES PayOut', $this->provider_db_id, json_encode($data), 'PayOut HIT!');
        if ($client_details){
            // $response =[
            //     "data" => [
            //         "currency"=>"USD",
            //         "balance"=>round($client_details->balance,2),
            //     ]
            // ];
            // return response($response,200)->header('Content-Type', 'application/json');
            try{
                ProviderHelper::IdenpotencyTable($data['data']['transactionId']);
            }catch(\Exception $e){
                $response = [
                    "data"=> null,
                    "error" => [
                        "statusCode" => 500,
                        "message" => "Cannot read properties of undefined (reading 'realMoney')"
                    ],
                ];
                return response($response,400)->header('Content-Type', 'application/json');
            }
            $provider_trans_id = $data['data']['transactionId'];
            $roundId = $data['data']['betId'];
            $amount = $data['data']['amount'];
            $win = $amount == 0 ? 0 : 1;
            $gamedetails = ProviderHelper::findGameDetails('game_code', 74, $data['data']['gameCode']);
            $game = GametransactionMDB::getGameTransactionByRoundId($roundId, $client_details);
            if ($game == null){
                Helper::saveLog("NO BET FOUND", 141,json_encode($data),"HIT!");
                $gameTransactionDatas = [
                    "provider_trans_id" => $provider_trans_id,
                    "token_id" => $client_details->token_id,
                    "game_id" => $gamedetails->game_id,
                    "round_id" => $roundId,
                    "bet_amount" => $amount,
                    "pay_amount" => 0,
                    "win" => 5,
                    "income" => 0,
                    "entry_id" => 1
                ];
                $game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionDatas,$client_details);
                $gameExtensionData = [
                    "game_trans_id" => $game_trans_id,
                    "provider_trans_id" => $provider_trans_id,
                    "round_id" => $roundId,
                    "amount" => $amount,
                    "game_transaction_type" => 1,
                    "provider_request" => json_encode($data),
                ];
                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameExtensionData,$client_details);
                $fund_extra_data = [
                    'provider_name' => $gamedetails->provider_name
                ];
                $client_response = ClientRequestHelper::fundTransfer($client_details,$amount,$gamedetails->game_code,$gamedetails->game_name,$game_trans_ext_id,$game_trans_id,'debit',false,$fund_extra_data);
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
                        "data" => [
                            "currency"=>"USD",
                            "balance"=>round($client_details->balance,2),
                        ]
                    ];
                    $extensionData = [
                        "mw_response" =>json_encode($response),
                        "mw_request" => json_encode($client_response->requestoclient),
                        "client_response" => json_encode($client_response),
                        "transaction_detail" => "Success",
                        "general_details" => "Sucess",
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
                            "data"=> null,
                            "error" => [
                                "statusCode" => 500,
                                "message" => "Cannot read properties of undefined (reading 'realMoney')"
                            ],
                        ];
                        $updateExt = [
                            "mw_response" =>json_encode($response),
                            "mw_request" => json_encode('FAILED'),
                            "client_response" => json_encode($client_response),
                            "transaction_detail" => "FAILED",
                            "general_details" => "FAILED",
                        ];
                        GameTransactionMDB::updateGametransactionEXT($updateExt,$game_trans_ext_id,$client_details);
                        return response($response,400)->header('Content-Type', 'application/json');
                    }catch(\Exception $e){
                        Helper::saveLog("FAILED WIN", 141,json_encode($client_response),"FAILED HIT!");
                    }
                }
            }
            $updateTransData = [
                "win" => $win,
                "pay_amount" => round($amount,2),
                "income" => round($game->bet_amount-$amount,2),
                "entry_id" => $amount == 0 ? 1 : 2,
            ];
            GameTransactionMDB::updateGametransaction($updateTransData,$game->game_trans_id,$client_details);
            $response =[
                "data" => [
                    "currency"=>"USD",
                    "balance"=>number_format($client_details->balance,2,'.', '')
                ]
            ];
            $gameExtensionData = [
                "game_trans_id" => $game->game_trans_id,
                "provider_trans_id" => $provider_trans_id,
                "round_id" => $roundId,
                "amount" => $amount,
                "game_transaction_type" => 2,
                "provider_request" => json_encode($data),
            ];
            $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameExtensionData,$client_details);
            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "provider" => 'NagaGames',
                    "game_transaction_ext_id" => $game_trans_ext_id,
                    "client_connection_name" => $client_details->connection_name,
                    "win_or_lost" => $win,
                ],
                "provider" => [
                    "provider_request" => $data,
                    "provider_trans_id"=>$provider_trans_id,
                    "provider_round_id"=>$roundId,
                    'provider_name' => $gamedetails->provider_name
                ],
                "mwapi" => [
                    "roundId"=> $game->game_trans_id,
                    "type" => 2,
                    "game_id" => $gamedetails->game_id,
                    "player_id" => $client_details->player_id,
                    "mw_response" => $response,
                ]
            ];
            if($game->win == 4 || $game->win == 2){
                return response($response,200)->header('Content-Type', 'application/json');
            }
            else{
                $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$amount,$gamedetails->game_code,$gamedetails->game_name,$game->game_trans_id,'credit',false,$action_payload);
                if(isset($client_response->fundtransferresponse->status->code) &&
                $client_response->fundtransferresponse->status->code == "200"){
                    $balance = round($client_response->fundtransferresponse->balance, 2);
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    //SUCCESS FUNDTRANSFER
                    $response = [
                        "data" => [
                            "currency"=>"USD",
                            "balance"=>round($client_details->balance,2),
                        ]
                    ];
                    $msg = [
                        "mw_response" => $response,
                    ];
                    GameTransactionMDB::updateGametransactionEXT($msg,$game_trans_ext_id,$client_details);
                    return response($response,200)->header('Content-Type', 'application/json');
                }
            }
            Helper::saveLog('NAGAGAMES PayOut', $this->provider_db_id, json_encode($response), 'PayOut HIT!');
            return response($response,200)->header('Content-Type', 'application/json');
        }
    }

    public function cancelBet (Request $request){
        $data = json_decode($request->getContent(),TRUE);
        Helper::saveLog('NAGAGAMES Cancel', $this->provider_db_id, json_encode($data), 'Cancel HIT!');
            // try{
            //     ProviderHelper::IdenpotencyTable($data['data']['transactionId']);
            // }catch(\Exception $e){

            // }
        $response = array(
            "data"=> [
                "betId"=>$data['data']['betId'],
                "status"=>"CANCELED"
            ],
            "error" => null
        );
        return response($response,200)->header('Content-Type', 'application/json');
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
            $transId = $data['uuid'];
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
                        "bet_amount" => $refundAmount,
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
                        "general_details" => $data['transaction']['id'],
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
            // $refundedBet = GameTransactionMDB::getGameTransactionByGeneralDetailsEXT($data['transaction']['id'],$client_details);
            // $refundedBetId = $refundedBet->game_trans_ext_id;
            // $updateBetTransaction = [
            //     "general_details" => "BET_CANCELED",
            // ];
            // GameTransactionMDB::updateGametransactionEXT($updateBetTransaction,$refundedBetId,$client_details);
            // $totalBetThisRound = DOWINNHelper::totalBet($roundId,$client_details);
            $gameExtensionData = [
                "game_trans_id" => $game->game_trans_id,
                "provider_trans_id" => $transId,
                "round_id" => $roundId,
                "amount" => $refundAmount,
                "game_transaction_type" => 3,
                "provider_request" => json_encode($data),
            ];
            $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameExtensionData,$client_details);
            $updateTransData = [
                "win" => 5,
                "entry_id" => 2,
                "trans_status" => 2,
                "bet_amount" =>0,
                "income" => 0,
            ];
            GameTransactionMDB::updateGametransaction($updateTransData,$game->game_trans_id,$client_details);
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
                // Helper::saveLog("CANCEL FUNDTRANSFER", 139,json_encode($client_response),"FUNDTRANSFER SUCCESS!");
                $balance = round($client_response->fundtransferresponse->balance, 2);
                ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                //SUCCESS FUNDTRANSFER
                
                // $connection = GameTransactionMDB::getAvailableConnection($client_details->connection_name);
                // $sumOfTransactions = DB::select("SELECT * FROM (select game_trans_id, sum(amount) amount, game_transaction_type from {$connection['db_list'][1]}.game_transaction_ext gte 
                // WHERE transaction_detail = 'Success' AND game_trans_id = ".$game->game_trans_id." group by game_transaction_type) tbl order by game_transaction_type;");
                // $countSumTrans = count($sumOfTransactions);
                // if($countSumTrans != 'false'){
                //     if($countSumTrans > 2){
                //         $sumOfRefund = $sumOfTransactions['2']->amount;
                //         $finalUpdateDatas = [
                //             "win" => $win_or_lost,
                //             "bet_amount" => round($sumOfRefund,2),
                //         ];
                //     }
                //     elseif($countSumTrans == 2 && $sumOfTransactions['1']->game_transaction_type == 3){
                //         $sumOfRefund = $sumOfTransactions['1']->amount;
                //         $finalUpdateDatas = [
                //             "win" => $win_or_lost,
                //             "bet_amount" => round($sumOfRefund,2),
                //         ];
                //     }
                //     else{
                //         $sumOfRefund = $game->pay_amount+$refundAmount;
                //         $finalUpdateDatas = [
                //             "win" => $win_or_lost,
                //             "bet_amount" => round($sumOfRefund,2),
                //         ];

                //     }
                // }
                // $updateTransData = [
                //     "win" => $win_or_lost,
                //     "bet_amount" => $totalBetThisRound,
                // ];
                // GameTransactionMDB::updateGametransaction($updateTransData,$game->game_trans_id,$client_details);
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
                    "general_details" => $data['transaction']['id'],
                ];
                GameTransactionMDB::updateGametransactionEXT($extensionData,$game_trans_ext_id,$client_details);
                return response($response,200)->header('Content-Type', 'application/json');
            }
        }
    }
    
    public function tip(Request $request){
        $data = json_decode($request->getContent(),TRUE);
        $client_details = ProviderHelper::getClientDetails('token', $data['token']);
        Helper::saveLog("BET PROCESS", 139,json_encode($data),"BET ON PROCESSING!");
        if($client_details){
            $token = $client_details->player_token;
            $guid = substr("abcdefghijklmnopqrstuvwxyz1234567890", mt_rand(0, 25), 1).substr(md5(time()), 1);
            $playerChecker = DOWINNHelper::checkBalanceAndStatus($token,$guid,$this->prefix,$client_details);//this is authentication
            if($playerChecker['code'] == 0 && $playerChecker['ingame'] == true){
                try{
                    ProviderHelper::idenpotencyTable($this->prefix.'_'.$data['transaction']['id'].'_5');
                }catch(\Exception $e){
                    $response = array(
                        "code" =>'210',
                        "extra"=>'Duplicate Transaction number',
                    );
                    return response($response,200)->header('Content-Type', 'application/json');
                }
                $transId = $data['uuid'];
                $roundId = $data['transaction']['id'];
                $gamedetails = ProviderHelper::findGameDetails('game_code', $this->providerID, 'DOWINN');
                $betAmount = round($data['transaction']['amount'],2);
                $gameTransactionDatas = [
                    "provider_trans_id" => $transId,
                    "token_id" => $client_details->token_id,
                    "game_id" => $gamedetails->game_id,
                    "round_id" => $roundId,
                    "bet_amount" => $betAmount,
                    "pay_amount" => 0,
                    "win" => 0,
                    "income" => $betAmount,
                    "entry_id" => 1
                ];
                $game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionDatas,$client_details);
                $gameExtensionData = [
                    "game_trans_id" => $game_trans_id,
                    "provider_trans_id" => $transId,
                    "round_id" => $roundId,
                    "amount" => $betAmount,
                    "game_transaction_type" => 4,
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
                        "win" => 0,
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
                        "general_details" => $data['transaction']['id'],
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

    public function viewHistory(Request $request){
        $data = json_decode($request->getContent(),TRUE);
        // ($player_id, $startTime,$endTime)
        // $dataToSend = [
        //     "account" => config('providerlinks.dowinn.user_agent'),
        //     "beginTime" => $startTime,
        //     "endTime" => $endTime,
        //     "child" => $this->prefix.'_'.$player_id,
        // ];
        $dataToSend = [
            "account" => config('providerlinks.dowinn.user_agent'),
            "beginTime" => $data['Stime'],
            "endTime" => $data['Etime'],
            "child" => $this->prefix.'_'.$data['player_id'],
        ];
        $client = new Client([
            'headers' => [
                'Content-Type' => 'x-www-form-urlencoded'
            ],
        ]);
        $response = $client->post(config('providerlinks.dowinn.api_url'),'/history2.do',
         ['form_params' => $dataToSend]);
         $res = json_decode($response->getBody(),TRUE);
         return $res;
    }
}
?>