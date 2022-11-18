<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ClientRequestHelper;
use App\Helpers\ProviderHelper;
use App\Helpers\Helper;
use App\Models\GameTransactionMDB;
use DB;
use DateTime;

class BGamingController extends Controller
{   

	public $client_api_key , $provider_db_id, $prefix_transc ;

	public function __construct(){
		$this->client_api_key = config("providerlinks.bgaming.CLIENT_API_KEY");
		$this->provider_db_id = config("providerlinks.bgaming.PROVIDER_ID");
        $this->prefix_transc = "BG_";
	}

	public function gameTransaction(Request $request){
	    Helper::saveLog('Bgaming Auth', $this->provider_db_id, json_encode($request->all()), $request->header('x-request-sign'));
	  	$json_data = $request->all();
	  	$client_details = ProviderHelper::getClientDetails('player_id', $json_data['user_id']);
		$request_sign = $request->header('x-request-sign');
        // $secret = config('providerlinks.bgaming.AUTH_TOKEN');
        $secret = config("providerlinks.bgaming.".$client_details->operator_id.".AUTH_TOKEN");
		$signature = hash_hmac('sha256',json_encode($json_data),$secret);
		if($signature != $request_sign){
            $response = [
                "code" =>  403,
                "message" => "Forbidden",
                "balance" => '0'
            ];
            return response($response,400)->header('Content-Type', 'application/json');
		}

		if($client_details == 'false'){
            $response = [
                    "code" =>  101,
                    "message" => "Player is invalid",
                    "balance" => 0
                ];
            return response($response,400)->header('Content-Type', 'application/json');
        }
        if(!isset($json_data["actions"])) {
            // $response = [
            //     "code" =>  403,
            //     "message" => "Forbidden",
            //     "balance" => '0'
            // ];
            // return response($response,400)->header('Content-Type', 'application/json');
            $response = $this->GetBalance($request->all(), $client_details);
			return response($response,200)
                ->header('Content-Type', 'application/json');	
        }
        if(count($json_data["actions"]) == 0){
            if($json_data["finished"] == true){
                $data = [
                    "user_id" => $json_data["user_id"],
                    "currency" => $json_data["currency"],
                    "game" => $json_data["game"],
                    "game_id" => $json_data["game_id"],
                    "session_id" => $json_data["session_id"],
                    "finished" => $json_data["finished"],
                    "actions" => [
                        [
                            "action" => "win",
                            "amount" => 0,
                            "action_id" => $json_data["game_id"].'_0',
                        ]
                    ]
                ];
                $client_details = ProviderHelper::getClientDetails('token_id', $client_details->token_id);
                $this->gameWIN($data, $client_details);
            }
            $response = $this->GetBalance($request->all(), $client_details);
			return response($response,200)
                ->header('Content-Type', 'application/json');	
        } else {
            // BET WEN ROLLBACK ONE TIME
            $status = 200;
            if(count($json_data["actions"]) == 1){
                if($json_data["actions"][0]["action"] == "bet"){
                    Helper::saveLog('Bgaming BET PROCESS', $this->provider_db_id, json_encode($request->all()), "HIT ENDPOINT");
                    $response = $this->gameBET($request->all(), $client_details);
                    if(!isset($response["code"])) {
                        if($json_data["finished"] == true){
                            $data = [
                                "user_id" => $json_data["user_id"],
                                "currency" => $json_data["currency"],
                                "game" => $json_data["game"],
                                "game_id" => $json_data["game_id"],
                                "session_id" => $json_data["session_id"],
                                "finished" => $json_data["finished"],
                                "actions" => [
                                    [
                                        "action" => "win",
                                        "amount" => 0,
                                        "action_id" => $json_data["actions"][0]["action_id"].'_0',
                                    ]
                                ]
                            ];
                            $client_details = ProviderHelper::getClientDetails('token_id', $client_details->token_id);
                            $this->gameWIN($data, $client_details);
                        }

                    } else {
                        $status = 412;
                    }
                    
                }
                
                if($json_data["actions"][0]["action"] == "win"){
                    Helper::saveLog('Bgaming WIN PROCESS', $this->provider_db_id, json_encode($request->all()), "HIT ENDPOINT");
                    $client_details = ProviderHelper::getClientDetails('token_id', $client_details->token_id);
                    $response = $this->gameWIN($request->all(), $client_details);
                }

                if($json_data["actions"][0]["action"] == "rollback"){
                    Helper::saveLog('Bgaming ROLLBACK PROCESS', $this->provider_db_id, json_encode($request->all()), "HIT ENDPOINT");
                    $response = $this->gameROLLBACK($request->all(), $client_details);
                }
                Helper::saveLog('Bgaming WIN AND BET RESPONSE', $this->provider_db_id, json_encode($request->all()), $response);
                return response($response,$status)
                ->header('Content-Type', 'application/json');
            }

            if(count($json_data["actions"]) == 2){
                if($json_data["actions"][0]["action"] == "bet" && $json_data["actions"][1]["action"] == "bet") {
                    Helper::saveLog('Bgaming BET and BET PROCESS', $this->provider_db_id, json_encode($request->all()), "HIT ENDPOINT");
                    $data = [
                        "user_id" => $json_data["user_id"],
                        "currency" => $json_data["currency"],
                        "game" => $json_data["game"],
                        "game_id" => $json_data["game_id"],
                        "session_id" => $json_data["session_id"],
                        "finished" => $json_data["finished"],
                        "actions" => [
                            [
                                "action" => $json_data["actions"][0]["action"],
                                "amount" => $json_data["actions"][0]["amount"],
                                "action_id" => $json_data["actions"][0]["action_id"],
                            ]
                        ]
                    ];
                    $bet_response = $this->gameBET($data, $client_details);
                    if(!isset($bet_response["code"])) {
                        $data = [
                            "user_id" => $json_data["user_id"],
                            "currency" => $json_data["currency"],
                            "game" => $json_data["game"],
                            "game_id" => $json_data["game_id"],
                            "session_id" => $json_data["session_id"],
                            "finished" => $json_data["finished"],
                            "actions" => [
                                [
                                    "action" => $json_data["actions"][1]["action"],
                                    "amount" => $json_data["actions"][1]["amount"],
                                    "action_id" => $json_data["actions"][1]["action_id"],
                                ]
                            ]
                        ];
                        $client_details = ProviderHelper::getClientDetails('token_id', $client_details->token_id);
                        $second_response = $this->gameBET($data, $client_details);
                        if(!isset($second_response["code"])) {
                            if($json_data["finished"] == true){
                                $data = [
                                    "user_id" => $json_data["user_id"],
                                    "currency" => $json_data["currency"],
                                    "game" => $json_data["game"],
                                    "game_id" => $json_data["game_id"],
                                    "session_id" => $json_data["session_id"],
                                    "finished" => $json_data["finished"],
                                    "actions" => [
                                        [
                                            "action" => "win",
                                            "amount" => 0,
                                            "action_id" => $json_data["actions"][0]["action_id"].'_0',
                                        ]
                                    ]
                                ];
                                $client_details = ProviderHelper::getClientDetails('token_id', $client_details->token_id);
                                $this->gameWIN($data, $client_details);
                            }
                            $response = [
                                "balance" => $second_response["balance"],
                                "game_id" => $data['game_id'],
                                "transactions" =>[
                                    [
                                        "action_id" => $json_data['actions'][0]['action_id'],
                                        "tx_id" =>  $bet_response["transactions"][0]["tx_id"],
                                        "processed_at" => $bet_response["transactions"][0]["processed_at"],
                                    ],
                                    [
                                        "action_id" => $json_data['actions'][1]['action_id'],
                                        "tx_id" =>  $second_response["transactions"][0]["tx_id"],
                                        "processed_at" => $second_response["transactions"][0]["processed_at"],
                                    ],
                                ],
                            ];
                            return response($response,200)
                                    ->header('Content-Type', 'application/json');

                        }  else {
                            $status = 412;
                        }
                        return response($second_response,$status)->header('Content-Type', 'application/json');
                    } else {
                        $status = 412;
                    }
                    return response($bet_response,$status)
                    ->header('Content-Type', 'application/json');
                } else if($json_data["actions"][0]["action"] == "bet" && $json_data["actions"][1]["action"] == "win") {
                    $data = [
                        "user_id" => $json_data["user_id"],
                        "currency" => $json_data["currency"],
                        "game" => $json_data["game"],
                        "game_id" => $json_data["game_id"],
                        "session_id" => $json_data["session_id"],
                        "finished" => $json_data["finished"],
                        "actions" => [
                            [
                                "action" => $json_data["actions"][0]["action"],
                                "amount" => $json_data["actions"][0]["amount"],
                                "action_id" => $json_data["actions"][0]["action_id"],
                            ]
                        ]
                    ];
                    $bet_response = $this->gameBET($data, $client_details);
                    if(!isset($bet_response["code"])) {
                        $data = [
                            "user_id" => $json_data["user_id"],
                            "currency" => $json_data["currency"],
                            "game" => $json_data["game"],
                            "game_id" => $json_data["game_id"],
                            "session_id" => $json_data["session_id"],
                            "finished" => $json_data["finished"],
                            "actions" => [
                                [
                                    "action" => $json_data["actions"][1]["action"],
                                    "amount" => $json_data["actions"][1]["amount"],
                                    "action_id" => $json_data["actions"][1]["action_id"],
                                ]
                            ]
                        ];
                        $client_details = ProviderHelper::getClientDetails('token_id', $client_details->token_id);
                        $win_response = $this->gameWIN($data, $client_details);
                        if(!isset($win_response["code"])) {
                            $response = [
                                "balance" => $win_response["balance"],
                                "game_id" => $data['game_id'],
                                "transactions" =>[
                                    [
                                        "action_id" => $json_data['actions'][0]['action_id'],
                                        "tx_id" =>  $bet_response["transactions"][0]["tx_id"],
                                        "processed_at" => $bet_response["transactions"][0]["processed_at"],
                                    ],
                                    [
                                        "action_id" => $json_data['actions'][1]['action_id'],
                                        "tx_id" =>  $win_response["transactions"][0]["tx_id"],
                                        "processed_at" => $win_response["transactions"][0]["processed_at"],
                                    ],
                                ],
                            ];
                            return response($response,200)
                                    ->header('Content-Type', 'application/json');
                        }
                    } else {
                        $status = 412;
                    }
                } else if($json_data["actions"][0]["action"] == "win" && $json_data["actions"][1]["action"] == "win") {
                    $data = [
                        "user_id" => $json_data["user_id"],
                        "currency" => $json_data["currency"],
                        "game" => $json_data["game"],
                        "game_id" => $json_data["game_id"],
                        "session_id" => $json_data["session_id"],
                        "finished" => $json_data["finished"],
                        "actions" => [
                            [
                                "action" => $json_data["actions"][0]["action"],
                                "amount" => $json_data["actions"][0]["amount"],
                                "action_id" => $json_data["actions"][0]["action_id"],
                            ]
                        ]
                    ];
                    $bet_response = $this->gameWIN($data, $client_details);
                    if(!isset($bet_response["code"])) {
                        $data = [
                            "user_id" => $json_data["user_id"],
                            "currency" => $json_data["currency"],
                            "game" => $json_data["game"],
                            "game_id" => $json_data["game_id"],
                            "session_id" => $json_data["session_id"],
                            "finished" => $json_data["finished"],
                            "actions" => [
                                [
                                    "action" => $json_data["actions"][1]["action"],
                                    "amount" => $json_data["actions"][1]["amount"],
                                    "action_id" => $json_data["actions"][1]["action_id"],
                                ]
                            ]
                        ];
                        $client_details = ProviderHelper::getClientDetails('token_id', $client_details->token_id);
                        $win_response = $this->gameWIN($data, $client_details);
                        if(!isset($win_response["code"])) {
                            $response = [
                                "balance" => $win_response["balance"],
                                "game_id" => $data['game_id'],
                                "transactions" =>[
                                    [
                                        "action_id" => $json_data['actions'][0]['action_id'],
                                        "tx_id" =>  $bet_response["transactions"][0]["tx_id"],
                                        "processed_at" => $bet_response["transactions"][0]["processed_at"],
                                    ],
                                    [
                                        "action_id" => $json_data['actions'][1]['action_id'],
                                        "tx_id" =>  $win_response["transactions"][0]["tx_id"],
                                        "processed_at" => $win_response["transactions"][0]["processed_at"],
                                    ],
                                ],
                            ];
                            return response($response,200)
                                    ->header('Content-Type', 'application/json');
                        }
                    } else {
                        $status = 412;
                    }
                } else if($json_data["actions"][0]["action"] == "rollback" && $json_data["actions"][1]["action"] == "rollback") {
                    $data = [
                        "user_id" => $json_data["user_id"],
                        "currency" => $json_data["currency"],
                        "game" => $json_data["game"],
                        "game_id" => $json_data["game_id"],
                        "session_id" => $json_data["session_id"],
                        "finished" => $json_data["finished"],
                        "actions" => [
                            [
                                "action" => $json_data["actions"][0]["action"],
                                "amount" => $json_data["actions"][0]["amount"],
                                "action_id" => $json_data["actions"][0]["action_id"],
                            ]
                        ]
                    ];
                    $bet_response = $this->gameROLLBACK($data, $client_details);
                    if(!isset($bet_response["code"])) {
                        $data = [
                            "user_id" => $json_data["user_id"],
                            "currency" => $json_data["currency"],
                            "game" => $json_data["game"],
                            "game_id" => $json_data["game_id"],
                            "session_id" => $json_data["session_id"],
                            "finished" => $json_data["finished"],
                            "actions" => [
                                [
                                    "action" => $json_data["actions"][1]["action"],
                                    "amount" => $json_data["actions"][1]["amount"],
                                    "action_id" => $json_data["actions"][1]["action_id"],
                                ]
                            ]
                        ];
                        $client_details = ProviderHelper::getClientDetails('token_id', $client_details->token_id);
                        $win_response = $this->gameROLLBACK($data, $client_details);
                        if(!isset($win_response["code"])) {
                            $response = [
                                "balance" => $win_response["balance"],
                                "game_id" => $data['game_id'],
                                "transactions" =>[
                                    [
                                        "action_id" => $json_data['actions'][0]['action_id'],
                                        "tx_id" =>  $bet_response["transactions"][0]["tx_id"],
                                        "processed_at" => $bet_response["transactions"][0]["processed_at"],
                                    ],
                                    [
                                        "action_id" => $json_data['actions'][1]['action_id'],
                                        "tx_id" =>  $win_response["transactions"][0]["tx_id"],
                                        "processed_at" => $win_response["transactions"][0]["processed_at"],
                                    ],
                                ],
                            ];
                            return response($response,200)
                                    ->header('Content-Type', 'application/json');
                        }
                    } else {
                        $status = 412;
                    }
                }
                return response($bet_response,$status)
                    ->header('Content-Type', 'application/json');
            }
            
        }
        
    }

    /**
     * Initialize the balance 
     */
    private function GetBalance($request, $client_details){
        $balance = str_replace(".", "", $client_details->balance);
        $response = [
            "balance" => (float)$balance
        ];
        Helper::saveLog('BG Get balance Hit', $this->provider_db_id, json_encode($request), $response);	
        return $response;
	}
    private function gameBET($data, $client_details){ 
        $round_id = $this->prefix_transc.$data["game_id"];
        $transactionId = $this->prefix_transc.$data["actions"][0]["action_id"];
        $amount = $data["actions"][0]["amount"] / 100;
        $processtime = new DateTime('NOW');
        try{
            ProviderHelper::idenpotencyTable($transactionId);
        }catch(\Exception $e){
            $bet_transaction = GameTransactionMDB::findGameExt($transactionId, 1,'transaction_id', $client_details);
            if ($bet_transaction != 'false') {
                if($bet_transaction->transaction_detail == '"FAILED"' || $bet_transaction->transaction_detail == "FAILED" ){
                    $response = [
                        "code" => 100,
                        "message" => "Player has not enough funds to process an action.",
                        "balance" => 0
                    ];
                    Helper::saveLog('Bgaming BET IDEMPOTENT', $this->provider_db_id, json_encode($data), $response);
                    return $response;
                }
                $balance = number_format(round($client_details->balance,2),2,'.','');
                $balance = str_replace(".", "", $balance);
                $response = [
                    "balance" => (float)$balance,
                    "game_id" => $data['game_id'],
                    "transactions" =>[
                        [
                            "action_id" =>$data['actions'][0]['action_id'],
                            "tx_id" =>  $bet_transaction->game_trans_ext_id,
                            "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                        ],
                    ],
                ];
                Helper::saveLog('Bgaming BET IDEMPOTENT', $this->provider_db_id, json_encode($data), $response);
                return $response;
            } 
            $response = [
                "code" => 100,
                "message" => "Player has not enough funds to process an action.",
                "balance" => 0
            ];
            Helper::saveLog('Bgaming BET IDEMPOTENT', $this->provider_db_id, json_encode($data), $response);
            return $response;
        }
        $game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $data["game"]);
        $bet_transaction = GameTransactionMDB::findGameTransactionDetails($round_id,'round_id', false, $client_details);
        if($bet_transaction == 'false'){
            $gameTransactionData = array(
                "provider_trans_id" => $transactionId,
                "token_id" => $client_details->token_id,
                "game_id" => $game_details->game_id,
                "round_id" => $round_id,
                "bet_amount" => $amount,
                "win" => 5,
                "pay_amount" => 0,
                "entry_id" => 1,
            );
            $game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionData,$client_details);
        } else {
            $client_details->connection_name = $bet_transaction->connection_name;
            $game_trans_id = $bet_transaction->game_trans_id;
        }
        $game_transaction_extension = array(
            "game_trans_id" => $game_trans_id,
            "provider_trans_id" => $transactionId,
            "round_id" => $round_id,
            "amount" => $amount,
            "game_transaction_type"=>1,
            "provider_request" => json_encode($data),
            "mw_response" => 'null',
            "transaction_detail" => "FAILED",
            "general_details" => "FAILED"
        );
        $game_transaction_ext_id = GameTransactionMDB::createGameTransactionExt($game_transaction_extension,$client_details);
        $body_details = [
            'provider_name' => $game_details->provider_name,
            'connection_timeout' => 3,
        ];
        $client_response = ClientRequestHelper::fundTransfer($client_details, $amount ,$game_details->game_code,$game_details->game_name,$game_transaction_ext_id,$game_trans_id,"debit", false, $body_details);
        if(isset($client_response->fundtransferresponse->status->code) && $client_response->fundtransferresponse->status->code == "200"){
            $balance = round($client_response->fundtransferresponse->balance,2);
            $balance = number_format($balance,2,'.','');
            if($bet_transaction != 'false'){
                $updateGameTransaction = [
                    "bet_amount" => $amount + $bet_transaction->bet_amount,
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
            }
            ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
            $balance = str_replace(".", "", $balance);
            $response = [
                "balance" => (float)$balance,
                "game_id" => $data['game_id'],
                "transactions" =>[
                    [
                        "action_id" =>$data['actions'][0]['action_id'],
                        "tx_id" =>  $game_transaction_ext_id,
                        "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                    ],
                ],
              ];
            $dataToUpdate = array(
                "mw_response" => json_encode($response),
                "mw_request" => json_encode($client_response->requestoclient),
                "client_response" => json_encode($client_response),
                "transaction_detail" => "SUCCESS",
                "general_details" => "SUCCESS"
            );
            GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$game_transaction_ext_id,$client_details);
        } elseif(isset($client_response->fundtransferresponse->status->code) && $client_response->fundtransferresponse->status->code == "402") {
            if($bet_transaction == 'false'){
                $updateGameTransaction = [
                    "win" => 2
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
            }
            $balance = round($client_response->fundtransferresponse->balance,2);
            $balance = number_format($balance,2,'.','');
            $balance = str_replace(".", "", $balance);
            $response = [
                "code" => 100,
                "message" => "Player has not enough funds to process an action.",
                "balance" => (float)$balance
            ];
            $dataToUpdate = array(
                "mw_response" => json_encode($response),
                "mw_request" => json_encode($client_response->requestoclient),
                "client_response" => json_encode($client_response),
                "transaction_detail" => "FAILED",
                "general_details" => "FAILED"
            );
            GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$game_transaction_ext_id,$client_details);
        }
        Helper::saveLog('Bgaming BET RESPONSE', $this->provider_db_id, json_encode($data), $response);
        return $response;
    }

    private function gameWIN($data, $client_details){ 
        $round_id = $this->prefix_transc.$data["game_id"];
        $transactionId = $this->prefix_transc.$data["actions"][0]["action_id"];
        $amount = $data["actions"][0]["amount"] / 100;
        $processtime = new DateTime('NOW');
        $game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $data["game"]);
        $bet_transaction = GameTransactionMDB::findGameTransactionDetails($round_id,'round_id', false, $client_details);
        if($bet_transaction == 'false'){
            $response = [
                "code" => 100,
                "message" => "Player has not enough funds to process an action.",
                "balance" => 0
            ];
            Helper::saveLog('Bgaming WIN RESPONSE', $this->provider_db_id, json_encode($data), $response);
            return $response;
        }
        $isGameExtFailed = GameTransactionMDB::findGameExt($round_id, 1,'round_id', $client_details);
        if($isGameExtFailed != 'false'){ 
            if($isGameExtFailed->transaction_detail == '"FAILED"' || $isGameExtFailed->transaction_detail == "FAILED" ){
                $response = [
                    "code" => 100,
                    "message" => "Player has not enough funds to process an action.",
                    "balance" => 0
                ];
                $game_transaction_extension = array(
                    "game_trans_id" => $bet_transaction->game_trans_id,
                    "provider_trans_id" => $transactionId,
                    "round_id" => $round_id,
                    "amount" => $amount,
                    "game_transaction_type"=> 2,
                    "provider_request" => json_encode($data),
                    "mw_response" => json_encode($response)
                );
                $winGametransactionExtId = GameTransactionMDB::createGameTransactionExt($game_transaction_extension,$client_details);
                Helper::saveLog('Bgaming WIN RESPONSE', $this->provider_db_id, json_encode($data), $response);
                return $response;
            }
        }
        try{
            ProviderHelper::idenpotencyTable($transactionId);
        }catch(\Exception $e){
            $win_transaction = GameTransactionMDB::findGameExt($transactionId, 2,'transaction_id', $client_details);
            if($win_transaction != 'false'){ 
                $balance = number_format(round($client_details->balance,2),2,'.','');
                $balance = str_replace(".", "", $balance);
                $response = [
                    "balance" => (float)$balance,
                    "game_id" => $data['game_id'],
                    "transactions" =>[
                        [
                            "action_id" =>$data['actions'][0]['action_id'],
                            "tx_id" =>  $win_transaction->game_trans_ext_id,
                            "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                        ],
                    ],
                ];
                Helper::saveLog('Bgaming WIN IDEMPOTENCE', $this->provider_db_id, json_encode($data), $response);
                return $response;
            }
            $balance = number_format(round($client_details->balance,2),2,'.','');
            $balance = str_replace(".", "", $balance);
            $response = [
                "balance" => (float)$balance,
                "game_id" => $data['game_id'],
                "transactions" =>[
                    [
                        "action_id" =>$data['actions'][0]['action_id'],
                        "tx_id" =>  $bet_transaction->game_trans_ext_id,
                        "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                    ],
                ],
            ];
            Helper::saveLog('Bgaming WIN IDEMPOTENCE', $this->provider_db_id, json_encode($data), $response);
            return $response;
        }
        $client_details->connection_name = $bet_transaction->connection_name;
        $game_transaction_extension = array(
            "game_trans_id" => $bet_transaction->game_trans_id,
            "provider_trans_id" => $transactionId,
            "round_id" => $round_id,
            "amount" => $amount,
            "game_transaction_type"=> 2,
            "provider_request" => json_encode($data),
        );
        $game_transaction_ext_id = GameTransactionMDB::createGameTransactionExt($game_transaction_extension,$client_details);
        $entry_id = $amount + $bet_transaction->pay_amount == 0 ? 1 : 2; 
        $updateGameTransaction = [
            "pay_amount" => $amount + $bet_transaction->pay_amount,
            "income" => $bet_transaction->bet_amount - ( $amount + $bet_transaction->pay_amount ),
            "entry_id" => $entry_id,
        ];
        GameTransactionMDB::updateGametransaction($updateGameTransaction,$bet_transaction->game_trans_id, $client_details);

        $balance = round($client_details->balance,2) + $amount;
        $balance = number_format($balance,2,'.','');
        ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
        $win = ($amount + $bet_transaction->pay_amount) == 0 ? 0 : 1;
        $balance = str_replace(".", "", $balance);
        $response = [
            "balance" => (float)$balance,
            "game_id" => $data['game_id'],
            "transactions" =>[
                [
                    "action_id" =>$data['actions'][0]['action_id'],
                    "tx_id" =>  $game_transaction_ext_id,
                    "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                ],
            ],
        ];
        $action_payload = [
            "type" => "custom", #genreral,custom :D # REQUIRED!
            "custom" => [
                "provider" => "BGaming",
                "client_connection_name" => $client_details->connection_name,
                "win_or_lost" => $win,
                "pay_amount" => $amount,
                "game_transaction_ext_id" => $game_transaction_ext_id
            ],
            "provider" => [
                "provider_request" => $data, #R
                "provider_trans_id"=> $transactionId, #e
                "provider_round_id"=> $round_id, #R
            ],
            "mwapi" => [
                "roundId"=> $bet_transaction->game_trans_id, #R
                "type"=>2, #R
                "game_id" => $game_details->game_id, #R
                "player_id" => $client_details->player_id, #R
                "mw_response" => $response, #R
            ]
        ];
        $client_response = ClientRequestHelper::fundTransfer_TG($client_details, $amount,$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',false,$action_payload);
        if(isset($client_response->fundtransferresponse->status->code) ){
            $dataToUpdate = array(
                "mw_request" => json_encode($client_response->requestoclient),
                "client_response" => json_encode($client_response),
                "mw_response" => json_encode($response),
                "transaction_detail" => "SUCCESS"
            );
            GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$game_transaction_ext_id,$client_details);
        }
        Helper::saveLog('Bgaming WIN RESPONSE', $this->provider_db_id, json_encode($data), $response);
        return $response;
    }

    private function gameROLLBACK($data, $client_details){ 
        $round_id = $this->prefix_transc.$data["game_id"];
        $transactionId = $this->prefix_transc.$data["actions"][0]["action_id"];
        $original_ID_transaction = $this->prefix_transc.$data["actions"][0]["original_action_id"];
        $processtime = new DateTime('NOW');
        $game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $data["game"]);
        $bet_transaction = GameTransactionMDB::findGameTransactionDetails($round_id,'round_id', false, $client_details);
        if($bet_transaction == 'false'){
            $response = [
                "code" => 100,
                "message" => "Player has not enough funds to process an action.",
                "balance" => 0
            ];
            Helper::saveLog('Bgaming WIN RESPONSE', $this->provider_db_id, json_encode($data), $response);
            return $response;
        }
        $isGameExtFailed = GameTransactionMDB::findGameExt($original_ID_transaction, 1,'transaction_id', $client_details);
        $amount = $isGameExtFailed->amount;
        if($isGameExtFailed != 'false'){ 
            if($isGameExtFailed->transaction_detail == '"FAILED"' || $isGameExtFailed->transaction_detail == "FAILED" ){
                $response = [
                    "code" => 100,
                    "message" => "Player has not enough funds to process an action.",
                    "balance" => 0
                ];
                $game_transaction_extension = array(
                    "game_trans_id" => $bet_transaction->game_trans_id,
                    "provider_trans_id" => $transactionId,
                    "round_id" => $round_id,
                    "amount" => $amount,
                    "game_transaction_type"=> $isGameExtFailed->amount,
                    "provider_request" => json_encode($data),
                    "mw_response" => json_encode($response)
                );
                $winGametransactionExtId = GameTransactionMDB::createGameTransactionExt($game_transaction_extension,$client_details);
                Helper::saveLog('Bgaming WIN RESPONSE', $this->provider_db_id, json_encode($data), $response);
                return $response;
            }
        }
        try{
            ProviderHelper::idenpotencyTable($transactionId);
        }catch(\Exception $e){
            $win_transaction = GameTransactionMDB::findGameExt($transactionId, 3,'transaction_id', $client_details);
            if($win_transaction != 'false'){ 
                $balance = number_format(round($client_details->balance,2),2,'.','');
                $balance = str_replace(".", "", $balance);
                $response = [
                    "balance" => (float)$balance,
                    "game_id" => $data['game_id'],
                    "transactions" =>[
                        [
                            "action_id" =>$data['actions'][0]['action_id'],
                            "tx_id" =>  $win_transaction->game_trans_ext_id,
                            "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                        ],
                    ],
                ];
                Helper::saveLog('Bgaming WIN IDEMPOTENCE', $this->provider_db_id, json_encode($data), $response);
                return $response;
            }
            $balance = number_format(round($client_details->balance,2),2,'.','');
            $balance = str_replace(".", "", $balance);
            $response = [
                "balance" => (float)$balance,
                "game_id" => $data['game_id'],
                "transactions" =>[
                    [
                        "action_id" =>$data['actions'][0]['action_id'],
                        "tx_id" =>  $bet_transaction->game_trans_ext_id,
                        "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                    ],
                ],
            ];
            Helper::saveLog('Bgaming WIN IDEMPOTENCE', $this->provider_db_id, json_encode($data), $response);
            return $response;
        }
        $client_details->connection_name = $bet_transaction->connection_name;
        $game_transaction_extension = array(
            "game_trans_id" => $bet_transaction->game_trans_id,
            "provider_trans_id" => $transactionId,
            "round_id" => $round_id,
            "amount" => $amount,
            "game_transaction_type"=> 3,
            "provider_request" => json_encode($data),
        );
        $game_transaction_ext_id = GameTransactionMDB::createGameTransactionExt($game_transaction_extension,$client_details);
        $updateGameTransaction = [
            "pay_amount" => $amount + $bet_transaction->pay_amount,
            "income" => $bet_transaction->bet_amount - ( $amount + $bet_transaction->pay_amount ),
            "entry_id" => 2,
        ];
        GameTransactionMDB::updateGametransaction($updateGameTransaction,$bet_transaction->game_trans_id, $client_details);

        $balance = round($client_details->balance,2) + $amount;
        $balance = number_format($balance,2,'.','');
        ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
        $balance = str_replace(".", "", $balance);
        $response = [
            "balance" => (float)$balance,
            "game_id" => $data['game_id'],
            "transactions" =>[
                [
                    "action_id" =>$data['actions'][0]['action_id'],
                    "tx_id" =>  $game_transaction_ext_id,
                    "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                ],
            ],
        ];
        $action_payload = [
            "type" => "custom", #genreral,custom :D # REQUIRED!
            "custom" => [
                "provider" => "BGaming",
                "client_connection_name" => $client_details->connection_name,
                "win_or_lost" => 4,
                "pay_amount" => $amount,
                "game_transaction_ext_id" => $game_transaction_ext_id
            ],
            "provider" => [
                "provider_request" => $data, #R
                "provider_trans_id"=> $transactionId, #e
                "provider_round_id"=> $round_id, #R
            ],
            "mwapi" => [
                "roundId"=> $bet_transaction->game_trans_id, #R
                "type"=>2, #R
                "game_id" => $game_details->game_id, #R
                "player_id" => $client_details->player_id, #R
                "mw_response" => $response, #R
            ]
        ];
        $client_response = ClientRequestHelper::fundTransfer_TG($client_details, $amount,$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',true,$action_payload);
        if(isset($client_response->fundtransferresponse->status->code) ){
            $dataToUpdate = array(
                "mw_request" => json_encode($client_response->requestoclient),
                "client_response" => json_encode($client_response),
                "mw_response" => json_encode($response),
                "transaction_detail" => "SUCCESS"
            );
            GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$game_transaction_ext_id,$client_details);
        }
        Helper::saveLog('Bgaming WIN RESPONSE', $this->provider_db_id, json_encode($data), $response);
        return $response;    
    }

}