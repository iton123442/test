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
        // Helper::saveLog('Naga Games Authorize', $this->provider_db_id, json_encode($data), 'Auth HIT!');
        $client_details = ProviderHelper::getClientDetails('token', $data['data']['playerToken']);
        // $hash = $this-> hashParam($data['data']);
        if($client_details){
            $response = array(
                "data"=> [
                "nativeId"=>"TG_" . $client_details->player_id,
                "currency"=>"USD",
                "balance"=>(int)round($client_details->balance,2)
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
        Helper::saveLog('NAGAGAMES GetBALANCE', $this->provider_db_id, json_encode($data), 'Balance HIT!');
        $hash = $this-> hashParam($data['data']);
        if($client_details){
            $response = array(
                "data"=> [
                "nativeId"=>"TG_" . $client_details->player_id,
                "currency"=>"USD",
                "balance"=> (int) round($client_details->balance,2)
                ],
                "error" => null
            );
            Helper::saveLog('NAGAGAMES GetBALANCE', $this->provider_db_id, json_encode($response), 'Success HIT!');
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
                            "balance"=> (int) round($client_details->balance,2),
                        ]
                    ];
                    $extensionData = [
                        "mw_request" => json_encode($client_response->requestoclient),
                        "mw_response" =>json_encode($response),
                        "client_response" => json_encode($client_response),
                        "transaction_detail" => "Success",
                        "general_details" => $data['data']['playerToken'] . "_" . $data['data']['gameCode'],
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
                        "balance"=> (int) round($client_details->balance,2),
                    ]
                ];
                $extensionData = [
                    "mw_request" => json_encode($client_response->requestoclient),
                    "mw_response" =>json_encode($response),
                    "client_response" => json_encode($client_response),
                    "transaction_detail" => "Success",
                    "general_details" => $data['data']['playerToken'] . "_" . $data['data']['gameCode'],
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
        $response = [
            "data"=> null,
            "error" => [
                "statusCode" => 500,
                "message" => "Cannot read properties of undefined (reading 'realMoney')"
            ],
        ];
        return response($response,200)->header('Content-Type', 'application/json');
    }

    public function payout (Request $request){
        $data = json_decode($request->getContent(),TRUE);
        $client_details = ProviderHelper::getClientDetails('token', $data['data']['playerToken']);
        Helper::saveLog('NAGAGAMES PayOut', $this->provider_db_id, json_encode($data), 'PayOut HIT!');
        if ($client_details){
            // $response =[
            //     "data" => [
            //         "currency"=>"USD",
            //         "balance"=> (int) round($client_details->balance,2),
            //     ]
            // ];
            // return response($response,200)->header('Content-Type', 'application/json');
            try{
                ProviderHelper::IdenpotencyTable($data['data']['transactionId']);
            }catch(\Exception $e){
                $response =[
                    "data" => [
                        "currency"=>"USD",
                        "balance"=> (int) round($client_details->balance,2)
                    ]
                ];
                return response($response,200)->header('Content-Type', 'application/json');
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
                            "balance"=> (int) $balance,
                        ]
                    ];
                    $extensionData = [
                        "mw_response" =>json_encode($response),
                        "mw_request" => json_encode($client_response->requestoclient),
                        "client_response" => json_encode($client_response),
                        "transaction_detail" => "Success",
                        "general_details" => $data['data']['playerToken'] . "_" . $data['data']['gameCode'],
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
                    "balance"=> (int) round($client_details->balance,2)
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
                            "balance"=> (int) $balance,
                        ]
                    ];
                    $msg = [
                        "mw_response" => json_encode($response)
                    ];
                    GameTransactionMDB::updateGametransactionEXT($msg,$game_trans_ext_id,$client_details);
                    Helper::saveLog('NAGAGAMES PayOut', $this->provider_db_id, json_encode($response), 'PayOut HIT!');
                    return response($response,200)->header('Content-Type', 'application/json');
                }
            }
        }
        $response = [
            "data"=> null,
            "error" => [
                "statusCode" => 500,
                "message" => "Cannot read properties of undefined (reading 'realMoney')"
            ],
        ];
        return response($response,200)->header('Content-Type', 'application/json');
    }

    public function cancelBet (Request $request){
        $data = json_decode($request->getContent(),TRUE);
        Helper::saveLog('NAGAGAMES Cancel', $this->provider_db_id, json_encode($data), 'Cancel HIT!');
        $betExt = ProviderHelper::getGeneralDetails(1, $data['data']['betId']);
        $explodedData = explode($betExt->general_details, "_");
        $client_details = ProviderHelper::getClientDetails('token', $explodedData[0]);
        Helper::saveLog('NAGAGAMES Cancel', $this->provider_db_id, json_encode($explodedData), "hit");
        if (json_encode($client_details)){
            try{
                ProviderHelper::IdenpotencyTable("CaB_".$data['data']['betId']);
            }catch(\Exception $e){
                $response =[
                    "data"=> [
                        "betId"=>$data['data']['betId'],
                        "status"=>"CANCELED"
                    ],
                    "error" => null
                ];
                return response($response,200)->header('Content-Type', 'application/json');
            }
            $provider_trans_id = "ref_" . $data['data']['betId'];
            $roundId = $data['data']['betId'];
            $win = 4;
            $gamedetails = ProviderHelper::findGameDetails('game_code', 74, $explodedData[1]);
            $game = GametransactionMDB::getGameTransactionByRoundId($roundId, $client_details);
            if ($game == null){
                $response = [
                    "data"=> null,
                    "error"=> [
                      "statusCode"=> 400,
                      "message"=> 'Transaction Seamless Already Resolved',
                    ],
                ];
                return response($response,400)->header('Content-Type', 'application/json');
            }
            $amount = round($game->bet_amount,2);
            $updateTransData = [
                "win" => $win,
                "pay_amount" => round($amount,2),
                "income" => round($game->bet_amount-$amount,2),
                "entry_id" => $amount == 0 ? 1 : 2,
            ];
            GameTransactionMDB::updateGametransaction($updateTransData,$game->game_trans_id,$client_details);
            $response =[
                "data"=> [
                    "betId"=>$roundId,
                    "status"=>"CANCELED"
                ],
                "error" => null
            ];
            $gameExtensionData = [
                "game_trans_id" => $game->game_trans_id,
                "provider_trans_id" => $provider_trans_id,
                "round_id" => $roundId,
                "amount" => $amount,
                "game_transaction_type" => 3,
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
                    "type" => 3,
                    "game_id" => $gamedetails->game_id,
                    "player_id" => $client_details->player_id,
                    "mw_response" => $response,
                ]
            ];
            $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$amount,$gamedetails->game_code,$gamedetails->game_name,$game->game_trans_id,'credit',true,$action_payload);
            if(isset($client_response->fundtransferresponse->status->code) &&
            $client_response->fundtransferresponse->status->code == "200"){
                $balance = round($client_response->fundtransferresponse->balance, 2);
                ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                //SUCCESS FUNDTRANSFER
                $response = array(
                    "data"=> [
                        "betId"=>$roundId,
                        "status"=>"CANCELED"
                    ],
                    "error" => null
                );
                $msg = [
                    "mw_response" => json_encode($response)
                ];
                GameTransactionMDB::updateGametransactionEXT($msg,$game_trans_ext_id,$client_details);
                Helper::saveLog('NAGAGAMES Cancel', $this->provider_db_id, json_encode($response), 'Cancel HIT!');
                return response($response,200)->header('Content-Type', 'application/json');
            }
        }
        $response = [
            "data"=> null,
            "error"=> [
              "statusCode"=> 400,
              "message"=> 'Transaction Seamless Already Resolved',
            ],
        ];
        return response($response,400)->header('Content-Type', 'application/json');
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

    // public function insertGameLaunchURL(Request $request){
    //     //Auto Bulk insert in table FreeRound Denomination!!
    //             $games = DB::select("Select * FROM games as g where g.sub_provider_id = ". $this->provider_db_id.";");
    //             $results =array();
    //             dump($games);
    //             foreach($games as $item){
    //                 $gametocompare = DB::select("select IFNULL (game_launch_url,0) as gameURL from games WHERE game_id = ".$item->game_id.";");
    //                 if($gametocompare->gameURL == 0){
    //                     dump($gametocompare);
    //                     try{
    //                         $brandCode = config('providerlinks.naga.brandCode');
    //                         $groupCode = config('providerlinks.naga.groupCode');;
    //                         $url = config('providerlinks.naga.api_url') .'?playerToken='.$request->token.'&groupCode='.$groupCode.'&brandCode='.$brandCode. "&sortBy=playCount&orderBy=DESC";
    //                         $client = new Client([
    //                             'headers' => [
    //                                 'Content-Type' => 'application/json' 
    //                             ],
    //                         ]);
    //                         $response = $client->get($url);
    //                         $response = json_decode($response->getBody(),TRUE);
    //                         // Helper::saveLog('NAGA FINDGAME', 141, json_encode($response), 'URL HIT!');
    //                         //Iterate every array to get the matching game code
    //                         foreach($response as $value) {
    //                             if ($value['code'] == $item->game_code){
    //                                 $link = $value['playUrl'];
    //                             }
    //                         }
    //                         dump($link);
    //                             $arraydenom = array(
    //                                 'game_launch_url' => $link,
    //                             ) ;
    //                             $result[] = $arraydenom;
    //                             DB::table('games')->insert($arraydenom);
                            
    //                     }
    //                     catch(\Exception $e) {
    //                         $msg = $e->getMessage().' '.$e->getLine().' '.$e->getFile();
    //                         $arr = array(
    //                             'Message' => $msg,
    //                             'Game Code' => $item->game_code
    //                         );
    //                         return $arr;
    //                     }
    //                 }
    //             }
        
    //             return $result;
                
    //         }
}
?>