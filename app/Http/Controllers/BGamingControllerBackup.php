<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Helpers\BGamingHelper;
use App\Helpers\ClientRequestHelper;
use App\Helpers\ProviderHelper;
use App\Helpers\Helper;
use App\Helpers\Game;
use App\Helpers\FreeSpinHelper;
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
        $secret = config('providerlinks.bgaming.AUTH_TOKEN');
		$signature = hash_hmac('sha256',json_encode($json_data),$secret);
		
		// if($signature != $request_sign){
        //     $response = [
        //         "code" =>  403,
        //         "message" => "Forbidden",
        //         "balance" => '0'
        //     ];
        //     return response($response,400)->header('Content-Type', 'application/json');
		// }

		if($client_details == 'false'){
            $response = [
                    "code" =>  101,
                    "message" => "Player is invalid",
                    "balance" => 0
                ];
            return response($response,400)->header('Content-Type', 'application/json');
        }
        
        if(count($json_data["actions"]) == 0){
            $response = $this->GetBalance($request->all(), $client_details);
			return response($response,200)
                ->header('Content-Type', 'application/json');	
        } else {
            // Transaction Process
            $round_id = $this->prefix_transc.$json_data["game_id"];
            // BET WEN ROLLBACK ONE TIME
            if(count($json_data["actions"]) == 1){
                if($json_data["actions"][0]["action"] == "bet"){
                    
                    
                }
            }
            
        }

        
        dd("1");
        
        // if(!isset($payload['actions'][0]['action'])){
		// 	$response = $this->GetBalance($request->all(), $client_details);
		// 	return response($response,200)
        //         ->header('Content-Type', 'application/json');	
		// }

        // if(isset($payload['actions'][0]['action'])){
        //     if(!isset($payload['actions'][1]['action'])){
        //         if($payload['actions'][0]['action'] == 'bet'){
        //             if(($payload['actions'][0]['amount'] / 100) > $client_details->balance){
        //                 $response = [
        //                     "code" => 100,
        //                     "message" => "Not enough funds",
        //                 ];
        //                 return response($response,412)->header('Content-Type', 'application/json');
        //             }else{
        // 			     $response = $this->gameBet($request->all(), $client_details);
        //                  return response($response,200)->header('Content-Type', 'application/json');
        //             }
        //         }
        //         if($payload['actions'][0]['action'] == 'win'){
        //             $response = $this->gameWin($request->all(), $client_details);
        //             return response($response,200)->header('Content-Type', 'application/json');
        //         }
        //     }else{
        //         if($payload['actions'][1]['action'] == 'win'){
        //             if(($payload['actions'][0]['amount'] / 100) > $client_details->balance){
        //                 $response = [
        //                     "code" => 100,
        //                     "message" => "Not enough funds",
        //                 ];
        //                 return response($response,412)->header('Content-Type', 'application/json');
        //             }else{
        //                 if($payload['actions'][0]['action'] == 'win' && $payload['actions'][1]['action'] == 'win'){
        //                      $response = $this->gameWin($request->all(), $client_details);
        //                      return response($response,200)->header('Content-Type', 'application/json');
        //                 }
        //                 $this->gameBet($request->all(), $client_details);
        //             }
        //             $response = $this->gameWin($request->all(), $client_details);
        //             return response($response,200)->header('Content-Type', 'application/json');
        //         }
        //         if($payload['actions'][0]['action'] == 'bet' && $payload['actions'][1]['action'] == 'bet'){
        //              $response = $this->gameBet($request->all(), $client_details);
        //              return response($response,200)->header('Content-Type', 'application/json');
        //         }
        //     }
        // }
  
    }

     /**
	 * Initialize the balance 
	 */
    public function GetBalance($request, $client_details){
        $balance = str_replace(".", "", $client_details->balance);
        $response = [
            "balance" => (float)$balance
        ];
        Helper::saveLog('BG Get balance Hit', $this->provider_db_id, json_encode($request), $response);	
        return $response;
	}

public function gameBet($request, $client_details){	
	  Helper::saveLog('Bgaming bet', $this->provider_db_id, json_encode($request), 'ENDPOINT HIT');
	  		 $payload = $request;
			 // $bet_array_key = array_search('bet', array_column($payload['actions'], 'action'));
			 $bet_data = $payload['actions'][0];   
			 $player_id = $payload['user_id'];
			 $provider_game_name = $payload['game'];
             if($provider_game_name != 'DragonsGold100' ){
                $game_code = preg_replace('/[^\\/\-a-z\s]/i', '', $provider_game_name);
             }else{
                $game_code = $provider_game_name;
             }  
			 $round_id = $payload['game_id'];
             $time = time();
             if(isset($payload['actions'][1]['action']) && $payload['actions'][1]['action'] == 'bet'){
                 $bet_amount = ($payload['actions'][0]['amount'] + $payload['actions'][1]['amount'])/100;
             }else{
			     $bet_amount = $bet_data['amount']/100;
             }
             $processtime = new DateTime('NOW');
		     $provider_trans_id = $bet_data['action_id'];
             // $txn_explode = explode("-", $provider_trans_id);
             // $txid = $txn_explode[4];

            
			try{
                ProviderHelper::idenpotencyTable($provider_trans_id);
            }catch(\Exception $e){
                $game_transaction = GameTransactionMDB::findGameTransactionDetails($provider_trans_id, 'transaction_id',false, $client_details);
                $balance = str_replace(".", "", $client_details->balance);
                $response = [
                    "balance" => (float)$balance,
                    "game_id" => $request['game_id'],
                    "transactions" =>[
                      [
                      "action_id" =>$payload['actions'][0]['action_id'],
                      "tx_id" =>  $game_transaction->game_trans_id,
                      "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                    ],
                   ],
                  ];
                return $response;
            }
            if($client_details == null){
                $response = [
              		"code" => 404,
              		"message" => "Not found",
              		"balance" =>"0"

                ];
                return $response;
            }//End of client Details null
            $gen_game_trans_id = ProviderHelper::idGenerate($client_details->connection_name,1);
            $game_details = Game::find($game_code, $this->provider_db_id);
            $bet_transaction = GameTransactionMDB::findGameTransactionDetails($round_id, 'round_id',false, $client_details);
            if ($bet_transaction != 'false') {
                $client_details->connection_name = $bet_transaction->connection_name;
                $amount = $bet_transaction->bet_amount + $bet_amount;
                $game_transaction_id = $bet_transaction->game_trans_id;
                $updateGameTransaction = [
                    'win' => 5,
                    'bet_amount' => $amount,
                    'entry_id' => 1,
                    'trans_status' => 1
                ];
               Helper::saveLog(' Bgaming Sidebet success', $this->provider_db_id, json_encode($request), 'ENDPOINT HIT');
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
            }else{  
                    $gen_game_extid = ProviderHelper::idGenerate($client_details->connection_name,2);
                    $game_transaction_id = $gen_game_trans_id;
                    $gameTransactionData = array(
                                    "provider_trans_id" => $provider_trans_id,
                                    "token_id" => $client_details->token_id,
                                    "game_id" => $game_details->game_id,
                                    "round_id" => $round_id,
                                    "bet_amount" => $bet_amount,
                                    "win" => 5,
                                    "pay_amount" => 0,
                                    "income" => 0,
                                    "entry_id" => 1,
                                ); 

                          Helper::saveLog('Bet gameTransactionData', $this->provider_db_id, json_encode($request), 'ENDPOINT HIT');
                        // $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
                        GameTransactionMDB::createGametransactionV2($gameTransactionData,$game_transaction_id,$client_details); //create game_transaction
            }//end find bet trans
                        $gameTransactionEXTData = array(
                            "game_trans_id" => $game_transaction_id,
                            "provider_trans_id" => $provider_trans_id,
                            "round_id" => $round_id,
                            "amount" => $bet_amount,
                            "game_transaction_type"=> 1,
                            // "provider_request" =>json_encode($request),
                            );
                        // $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details); 
                        GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$gen_game_extid,$client_details); //create extension
                        
                try {
                    $fund_extra_data = [
                        'provider_name' => $game_details->provider_name
                    ];
                    $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount, $game_code, $game_details->game_name, $gen_game_extid, $game_transaction_id, 'debit', false, $fund_extra_data);
                } catch (\Exception $e) {
                    $response = [

                    'code' => 504,
                    'message' => 'Request timed out',
                    'balance' => '0'
                    ];

                    return $response;
                }
               Helper::saveLog('bgaming after client_response', $this->provider_db_id, json_encode($payload), $client_response->fundtransferresponse->status->code);
              if (isset($client_response->fundtransferresponse->status->code)) {

                            ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                            switch ($client_response->fundtransferresponse->status->code) {
                                case '200':
                                    if(isset($payload['actions'][1]['action']) && $payload['actions'][1]['action'] == 'win'){
                                         $http_status = 200;
                                         $fundtransfer_bal = number_format($client_response->fundtransferresponse->balance,2,'.','');
                                         $balance = str_replace(".", "", $fundtransfer_bal);
                                         $response = [
                                              "balance" =>(float)$balance,
                                              "game_id" => $payload['game_id'],
                                              "transaction" =>[
                                                "action_id" =>$payload['actions'][0]['action_id'],
                                                "tx_id" =>  $game_transaction_id,
                                                "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                                              ],
                                            ];
                                            $createGameTransactionLog = [
                                                  "connection_name" => $client_details->connection_name,
                                                  "column" =>[
                                                      "game_trans_ext_id" => $gen_game_extid,
                                                      "request" => json_encode($payload),
                                                      "response" => json_encode($response),
                                                      "log_type" => "provider_details",
                                                      "transaction_detail" => "SUCCESS",
                                                  ]
                                                ];
                                             ProviderHelper::queTransactionLogs($createGameTransactionLog); 
                                    }elseif(isset($payload['actions'][1]['action']) && $payload['actions'][1]['action'] == 'bet'){
                                        $http_status = 200;
                                        $fundtransfer_bal = number_format($client_response->fundtransferresponse->balance,2,'.','');
                                         $balance = str_replace(".", "", $fundtransfer_bal);
                                        $response = [
                                            "balance" => (float)$balance,
                                            "game_id" => $request['game_id'],
                                            "transactions" =>[
                                                [
                                                "action_id" =>$payload['actions'][0]['action_id'],
                                                "tx_id" => $game_transaction_id,
                                                "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                                            ],
                                            [
                                              "action_id" =>$payload['actions'][1]['action_id'],
                                                "tx_id" => $game_transaction_id,
                                                "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                                            ],
                                           ],
                                          ];
                                          $createGameTransactionLog = [
                                                  "connection_name" => $client_details->connection_name,
                                                  "column" =>[
                                                      "game_trans_ext_id" => $gen_game_extid,
                                                      "request" => json_encode($payload),
                                                      "response" => json_encode($response),
                                                      "log_type" => "provider_details",
                                                      "transaction_detail" => "SUCCESS",
                                                  ]
                                                ];
                                             ProviderHelper::queTransactionLogs($createGameTransactionLog); 
                                          return $response;
                                    }else{
                                        $http_status = 200;
                                         $fundtransfer_bal = number_format($client_response->fundtransferresponse->balance,2,'.','');
                                         $balance = str_replace(".", "", $fundtransfer_bal);
                                         $response = [
                                            "balance" => (float)$balance,
                                            "game_id" => $request['game_id'],
                                            "transactions" =>[
                                              [
                                              "action_id" =>$payload['actions'][0]['action_id'],
                                              "tx_id" =>  $game_transaction_id,
                                              "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                                            ],
                                           ],
                                          ];
                                          $createGameTransactionLog = [
                                                  "connection_name" => $client_details->connection_name,
                                                  "column" =>[
                                                      "game_trans_ext_id" => $gen_game_extid,
                                                      "request" => json_encode($payload),
                                                      "response" => json_encode($response),
                                                      "log_type" => "provider_details",
                                                      "transaction_detail" => "SUCCESS",
                                                  ]
                                                ];
                                             ProviderHelper::queTransactionLogs($createGameTransactionLog);
                                    }
                                    if(!isset($payload['actions'][1])){
                                            if($payload['finished'] == true){
                                                $gen_game_extid2 = ProviderHelper::idGenerate($client_details->connection_name,2);
                                                     $updateGameTransaction = [
                                                                'win' => 0,
                                                                'pay_amount' => 0,
                                                                'income' => $bet_amount,
                                                                'entry_id' => 1,
                                                                'trans_status' => 2
                                                            ];
                                                       Helper::saveLog('BG find client_detailss', $this->provider_db_id, json_encode($request),$client_details);
                                                      GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_transaction_id, $client_details);
                                                      $action_payload = [
                                                                        "type" => "custom", #genreral,custom :D # REQUIRED!
                                                                        "custom" => [
                                                                            "provider" => 'BGaming',
                                                                            "client_connection_name" => $client_details->connection_name,
                                                                            "win_or_lost" => 0,
                                                                            "entry_id" => 1,
                                                                            "pay_amount" => 0,
                                                                            "income" => $bet_amount,
                                                                            "game_transaction_ext_id" => $gen_game_extid2
                                                                        ],
                                                                        "provider" => [
                                                                            "provider_request" => $payload, #R
                                                                            "provider_trans_id"=> $provider_trans_id, #R
                                                                            "provider_round_id"=> $round_id, #R
                                                                        ],
                                                                        "mwapi" => [
                                                                            "roundId"=>$game_transaction_id, #R
                                                                            "type"=>2, #R
                                                                            "game_id" => $game_details->game_id, #R
                                                                            "player_id" => $client_details->player_id, #R
                                                                            "mw_response" => $response, #R
                                                                        ],
                                                                        'fundtransferrequest' => [
                                                                            'fundinfo' => [
                                                                                'freespin' => false,
                                                                            ]
                                                                        ]
                                                                    ];
                                                          Helper::saveLog('Bg Create Ext.', $this->provider_db_id, json_encode($request),$action_payload);
                                                         $client_response = ClientRequestHelper::fundTransfer_TG($client_details,0,$game_details->game_code,$game_details->game_name,$game_transaction_id,'credit',false,$action_payload);
                                                        $gen_game_extid2 = ProviderHelper::idGenerate($client_details->connection_name,2);
                                                        $gameTransactionEXTDataLose = array(
                                                            "game_trans_id" => $game_transaction_id,
                                                            "provider_trans_id" => $provider_trans_id,
                                                            "round_id" => $round_id,
                                                            "amount" => 0,
                                                            "game_transaction_type"=> 2,
                                                            // "provider_request" =>json_encode($request),
                                                            // "mw_response" => json_encode($response),
                                                            // 'mw_request' => json_encode($client_response->requestoclient),
                                                            // 'client_response' => json_encode($client_response->fundtransferresponse),
                                                            // 'transaction_detail' => 'success',
                                                            // 'general_details' => 'success',
                                                            );
                                                        // GameTransactionMDB::createGameTransactionExt($gameTransactionEXTDataLose,$client_details);
                                                        GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTDataLose,$gen_game_extid2,$client_details); //create extension
                                                        //  $updateTransactionEXt = array(
                                                        //     "provider_request" =>json_encode($request),
                                                        //     "mw_response" => json_encode($response),
                                                        //     'mw_request' => json_encode($client_response->requestoclient),
                                                        //     'client_response' => json_encode($client_response->fundtransferresponse),
                                                        //     'transaction_detail' => 'progressing',
                                                        //     'general_details' => 'progressing',
                                                        // );
                                                        // GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                                                         $createGameTransactionLog = [
                                                              "connection_name" => $client_details->connection_name,
                                                              "column" =>[
                                                                  "game_trans_ext_id" => $gen_game_extid2,
                                                                  "request" => json_encode($payload),
                                                                  "response" => json_encode($response),
                                                                  "log_type" => "provider_details",
                                                                  "transaction_detail" => "SUCCESS",
                                                              ]
                                                            ];
                                                         ProviderHelper::queTransactionLogs($createGameTransactionLog);
                                                         $createGameTransactionLogCl = [
                                                              "connection_name" => $client_details->connection_name,
                                                              "column" =>[
                                                                  "game_trans_ext_id" => $gen_game_extid2,
                                                                  "request" => json_encode($client_response->requestoclient),
                                                                  "response" => json_encode($client_response->fundtransferresponse),
                                                                  "log_type" => "client_details",
                                                                  "transaction_detail" => "SUCCESS",
                                                              ]
                                                            ];
                                                         ProviderHelper::queTransactionLogs($createGameTransactionLogCl);  
                                                        
                                            }else{
                                                //     $updateTransactionEXt = array(
                                                //         "provider_request" =>json_encode($request),
                                                //         "mw_response" => json_encode($response),
                                                //         'mw_request' => json_encode($client_response->requestoclient),
                                                //         'client_response' => json_encode($client_response->fundtransferresponse),
                                                //         'transaction_detail' => 'success',
                                                //         'general_details' => 'success',
                                                //     );
                                                // GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                                                $createGameTransactionLog = [
                                                      "connection_name" => $client_details->connection_name,
                                                      "column" =>[
                                                          "game_trans_ext_id" => $gen_game_extid,
                                                          "request" => json_encode($payload),
                                                          "response" => json_encode($response),
                                                          "log_type" => "provider_details",
                                                          "transaction_detail" => "SUCCESS",
                                                      ]
                                                    ];
                                                 ProviderHelper::queTransactionLogs($createGameTransactionLog); 
                                                Helper::saveLog('Bgaming after success updateTransactionEXt', $this->provider_db_id, json_encode($payload), $client_response);   
                                                 Helper::saveLog('BG 200 Debit Success', $this->provider_db_id, json_encode($request), $response);	
                                        }//end else payload finished true      
                                    }//end action if
				                    return $response;
                                    break;
                                case '402':
                                    // ProviderHelper::updateGameTransactionStatus($game_transaction_id, 2, 99);
                                    $http_status = 402;
                                    $response = [
                                        
                                         "Error Code" => 500,
                                         "Message" => "Internal Error"
                                      
                                    ];

                            //     $updateTransactionEXt = array(
                            //         "provider_request" =>json_encode($request->all()),
                            //         "mw_response" => json_encode($response),
                            //         'mw_request' => json_encode($client_response->requestoclient),
                            //         'client_response' => json_encode($client_response->fundtransferresponse),
                            //         'transaction_detail' => 'failed',
                            //         'general_details' => 'failed',
                            //     );
                            //      Helper::saveLog('Bgaming after 402 updateTransactionEXt', $this->provider_db_id, json_encode($payload), 'ENDPOINT HIT');   
                            // GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                                    $createGameTransactionLog = [
                                      "connection_name" => $client_details->connection_name,
                                      "column" =>[
                                          "game_trans_ext_id" => $gen_game_extid,
                                          "request" => json_encode($payload),
                                          "response" => json_encode($response),
                                          "log_type" => "provider_details",
                                          "transaction_detail" => "FAILED",
                                      ]
                                    ];
                                 ProviderHelper::queTransactionLogs($createGameTransactionLog); 
                            Helper::saveLog('BG Debit failed', $this->provider_db_id, json_encode($request), $response);	
				              return $response;
                                    break;
                            }             

        }
    }
	public  function gameWin($request, $client_details,$action_status = false){
		    Helper::saveLog('BG Credit Hit', $this->provider_db_id, json_encode($request), 'HIT!');	
			$time = time();
			$payload = $request;
            if(isset($payload['actions'][1]['action_id'])){
                $providertemp = "TG_".$payload['actions'][1]['action_id'];
                $provider_trans_id = $payload['actions'][1]['action_id'];
            }else{
                // $providertemp = $time."_".$payload['actions'][0]['action_id'];
                $providertemp = "TG_".$payload['actions'][0]['action_id'];
                $provider_trans_id = $payload['actions'][0]['action_id'];
            }
            $player_id = $payload['user_id'];
            $provider_game_name = $payload['game'];
            if($provider_game_name != 'DragonsGold100' ){
                $game_code = preg_replace('/[^\\/\-a-z\s]/i', '', $provider_game_name);
             }else{
                $game_code = $provider_game_name;
             } 
            $client_details = ProviderHelper::getClientDetails('player_id', $player_id);
            ProviderHelper::_insertOrUpdate($client_details->token_id,$client_details->balance);
            $processtime = new DateTime('NOW');
            if(isset($payload['actions'][1]['action'])){
                if($payload['actions'][0]['action'] == 'win' && $payload['actions'][1]['action'] == 'win'){
                    $win_load = $payload['actions'][0]['amount'] + $payload['actions'][1]['action_id'];
                    $pay_amount = ($payload['actions'][0]['amount'] + $payload['actions'][1]['amount'])/100;
                }else{
                	if($payload['actions'][1]['action'] == 'win'){
                    	$win_load = $payload['actions'][1]['action_id'];
                    	$pay_amount = $payload['actions'][1]['amount']/100;
                	}
                }
            }else{
            	if($action_status == true){
            		$pay_amount = 0;
            	}else{
            		$win_load = $payload['actions'][0]['action_id'];
            		$pay_amount = $payload['actions'][0]['amount']/100;
            	}         	
            }

            // $txn_explode = explode("-", $win_load);
            // $txnid = $txn_explode[4];
            try{
                ProviderHelper::idenpotencyTable($providertemp);
            }catch(\Exception $e){
                $game_transaction = GameTransactionMDB::findGameTransactionDetails($payload['game_id'], 'round_id',false, $client_details);
                $balance = str_replace(".", "", $client_details->balance);
                $response = [
                    "balance" => (float)$balance,
                    "game_id" => $payload['game_id'],
                    "transactions" =>[
                      [
                      "action_id" =>$win_load,
                      "tx_id" => $game_transaction->game_trans_id,
                      "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                    ],
                   ],
                  ];
                return $response;
            }
            // try{
	            Helper::saveLog('BG WinAmount', $this->provider_db_id, json_encode($request),$pay_amount);
	             $provider_trans_id = $action_status == true ? $providertemp : $win_load;
	             $round_id = $payload['game_id'];		 
                 $game_details = Game::find($game_code, $this->provider_db_id);
                 Helper::saveLog('BG find game_detailss', $this->provider_db_id, json_encode($request),$game_details);
                 $bet_transaction = GameTransactionMDB::findGameTransactionDetails($round_id,'round_id', 1, $client_details);
                 Helper::saveLog('BG after data request', $this->provider_db_id, json_encode($request),json_encode($bet_transaction));
                 $winbBalance = $client_details->balance + $pay_amount;
                 $winaction_id = $action_status == false ? $win_load  : $payload['actions'][0]['action_id'];
	             $http_status = 200;
	             $processtime = new DateTime('NOW');
	             ProviderHelper::_insertOrUpdate($client_details->token_id,$winbBalance);
                 $win_bal = number_format($winbBalance,2,'.','');
                 $balance = str_replace(".", "", $win_bal);
	             Helper::saveLog('BG start to process and get bal win', $this->provider_db_id, json_encode($request),$balance);
                 if(isset($payload['actions'][1]['action'])){
                    if($payload['actions'][1]['action'] == 'win' ){
                         $response = [
                            "balance" => (float)$balance,
                            "game_id" => $request['game_id'],
                            "transactions" =>[
                                [
                                "action_id" =>$payload['actions'][0]['action_id'],
                                "tx_id" => $bet_transaction->game_trans_id,
                                "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                            ],
                            [
                              "action_id" =>$winaction_id,
                                "tx_id" =>$bet_transaction->game_trans_id,
                                "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                            ],
                           ],
                          ];
                    }
                 }else{
                    $response = [
                      "balance" => (float)$balance,
                      "game_id" => $request['game_id'],
                      "transactions" =>[
                        [
                        "action_id" =>$payload['actions'][0]['action_id'],
                        "tx_id" =>  $bet_transaction->game_trans_id,
                        "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                      ],
                     ],
                    ];
                }
                 
                    $entry_id = $pay_amount > 0 ?  2 : 1;
                    $amount = $pay_amount + $bet_transaction->pay_amount;
                    $income = $bet_transaction->bet_amount -  $amount; 
                    if($bet_transaction->pay_amount > 0){
                        $win_or_lost = 1;
                    }else{
                        $win_or_lost = $pay_amount > 0 ?  1 : 0;
                    }
                    $gen_game_extid = ProviderHelper::idGenerate($client_details->connection_name,2);
                   $updateGameTransaction = [
                        'win' =>5,
                        'pay_amount' => $amount,
                        'income' => $income,
                        'entry_id' => $entry_id,
                        'trans_status' => 2
                    ];
               Helper::saveLog('BG find client_detailss', $this->provider_db_id, json_encode($request),$client_details);
              GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
                    $gameTransactionEXTData = array(
                        "game_trans_id" => $bet_transaction->game_trans_id,
                        "provider_trans_id" => $provider_trans_id,
                        "round_id" => $round_id,
                        "amount" => $pay_amount,
                        "game_transaction_type"=> 2,
                        // "provider_request" =>json_encode($request),
                        // "mw_response" => json_encode($response),
                    );

                // $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
                GameTransactionMDB::createGameTransactionExtv2($gameTransactionEXTData,$gen_game_extid,$client_details);
                           $action_payload = [
                                "type" => "custom", #genreral,custom :D # REQUIRED!
                                "custom" => [
                                    "provider" => 'BGaming',
                                    "client_connection_name" => $client_details->connection_name,
                                    "win_or_lost" => $win_or_lost,
                                    "entry_id" => $entry_id,
                                    "pay_amount" => $pay_amount,
                                    "income" => $income,
                                    "game_transaction_ext_id" => $gen_game_extid
                                ],
                                "provider" => [
                                    "provider_request" => $payload, #R
                                    "provider_trans_id"=> $provider_trans_id, #R
                                    "provider_round_id"=> $round_id, #R
                                ],
                                "mwapi" => [
                                    "roundId"=>$bet_transaction->game_trans_id, #R
                                    "type"=>2, #R
                                    "game_id" => $game_details->game_id, #R
                                    "player_id" => $client_details->player_id, #R
                                    "mw_response" => $response, #R
                                ],
                                'fundtransferrequest' => [
                                    'fundinfo' => [
                                        'freespin' => false,
                                    ]
                                ]
                            ];
                  Helper::saveLog('Bg Create Ext.', $this->provider_db_id, json_encode($request),$action_payload);
                 $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$pay_amount,$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',false,$action_payload);
                 ProviderHelper::_insertOrUpdate($client_details->token_id,$winbBalance);
                 Helper::saveLog('Bg Winbalance Response.', $this->provider_db_id, json_encode($request),$winbBalance);
                 Helper::saveLog('Bg Client Response.', $this->provider_db_id, json_encode($request),$client_response);
                //  $updateTransactionEXt = array(
                //     "provider_request" =>json_encode($request),
                //     "mw_response" => json_encode($response),
                //     'mw_request' => json_encode($client_response->requestoclient),
                //     'client_response' => json_encode($client_response->fundtransferresponse),
                //     'transaction_detail' => 'success',
                //     'general_details' => 'success',
                // );
                // GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                $createGameTransactionLog = [
                          "connection_name" => $client_details->connection_name,
                          "column" =>[
                              "game_trans_ext_id" => $gen_game_extid,
                              "request" => json_encode($request),
                              "response" => json_encode($response),
                              "log_type" => "provider_details",
                              "transaction_detail" => "SUCCESS",
                          ]
                        ];
                     ProviderHelper::queTransactionLogs($createGameTransactionLog); 
                  Helper::saveLog('2', $this->provider_db_id, json_encode($request),$response);
                    return $response;

           //  }catch(\Exception $e){
           //           $response = [
           //                  	"message" => $e
           //                  ];    
           //  Helper::saveLog('BG Callback error', $this->provider_db_id, json_encode($request,JSON_FORCE_OBJECT), $response);
           // return $response;

           //  }// End of Catch 

		
	}
    public function freeSpinSettlement(Request $request){
        Helper::saveLog('Bgaming freespin transact', $this->provider_db_id, json_encode($request->all()), "ENDPOINTHIT");
        try {
            $getFreespinTransaction = FreeSpinHelper::getFreeSpinDetails($request->issue_id, "provider_trans_id" );
            $client_details = ProviderHelper::getClientDetails('player_id', $getFreespinTransaction->player_id);
            try{
                ProviderHelper::idenpotencyTable("fs".$request->issue_id);
            }catch(\Exception $e){
                $balance = str_replace(".", "", $client_details->balance);
                $response = [
                    "balance" => (float)$balance,
                  ];
                return $response;
            }
            if($request->status != 'played'){
                $balance = str_replace(".", "", $client_details->balance);
                $response = [
                    "balance" => (float)$balance,
                  ];
                return $response;
            }
            $game_details = Game::findbyid($getFreespinTransaction->game_id);
            // dd($game_details);
            $pay_amount = $request->total_amount/100;
            $win_or_lost = $pay_amount > 0 ?  1 : 0;
            $entry_id = $pay_amount > 0 ?  2 : 1;
            $winbBalance = $client_details->balance + $pay_amount;
            $gameTransactionData = array(
                "provider_trans_id" => $request->issue_id,
                "token_id" => $client_details->token_id,
                "game_id" => $getFreespinTransaction->game_id,
                "round_id" => $request->issue_id,
                "bet_amount" => 0,
                "win" => 5,
                "pay_amount" => 0,
                "income" => 0,
                "entry_id" => 1,
            ); 
            $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
            $gameTransactionEXTDataBet = array(
                "game_trans_id" => $game_transaction_id,
                "provider_trans_id" => $request->issue_id,
                "round_id" => $request->issue_id,
                "amount" => 0,
                "game_transaction_type"=> 1,
                "provider_request" =>json_encode($request->all()),
            );
            $game_trans_ext_idbet = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTDataBet,$client_details);
            $client_response = ClientRequestHelper::fundTransfer($client_details,0, $game_details->game_code, $game_details->game_name, $game_trans_ext_idbet, $game_transaction_id, 'debit');
            if (isset($client_response->fundtransferresponse->status->code)) {
                switch ($client_response->fundtransferresponse->status->code) {
                    case '200':
                        $balance = str_replace(".", "", $client_details->balance);
                        $response = [
                            "balance" => (float)$balance,
                        ];
                        $updateTransactionEXt = array(
                                "mw_response" => json_encode($response),
                                'mw_request' => json_encode($client_response->requestoclient),
                                'client_response' => json_encode($client_response->fundtransferresponse),
                                'transaction_detail' => 'success',
                                'general_details' => 'success',
                            );
                        Helper::saveLog('Bgaming after success updateTransactionEXt', $this->provider_db_id, json_encode($request->all()), $client_response);   
                        GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_idbet,$client_details);
                    break;
                    case '402':
                        $response = [
                             "Error Code" => 500,
                             "Message" => "Internal Error"
                        ];
                        $updateTransactionEXt = array(
                            "provider_request" =>json_encode($request->all()),
                            "mw_response" => json_encode($response),
                            'mw_request' => json_encode($client_response->requestoclient),
                            'client_response' => json_encode($client_response->fundtransferresponse),
                            'transaction_detail' => 'failed',
                            'general_details' => 'failed',
                        );
                        GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_idbet,$client_details);
                    break;
                }//end switch client response
            }
            ProviderHelper::_insertOrUpdate($client_details->token_id,$winbBalance);
            $updateGameTransaction = [
                    'win' =>5,
                    'pay_amount' => $pay_amount,
                    'income' => 0 - $pay_amount,
                    'entry_id' => $entry_id,
                    'trans_status' => 2
                ];
            Helper::saveLog('BG find client_detailss', $this->provider_db_id, json_encode($request),$client_details);
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_transaction_id, $client_details);
            $gameTransactionEXTDataWin = array(
                "game_trans_id" => $game_transaction_id,
                "provider_trans_id" => $request->issue_id,
                "round_id" => $request->issue_id,
                "amount" => $pay_amount,
                "game_transaction_type"=> 2,
                "provider_request" =>json_encode($request->all()),
            );
            $game_trans_ext_idwin = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTDataWin,$client_details);
            $win_bal = number_format($winbBalance,2,'.','');
            $balance = str_replace(".", "", $win_bal);
            $responseWin = [
                "balance" => (float)$balance,
            ];
            $status = 2;
            $updateFreespinData = [
                "status" => 2,
                "spin_remaining" => 0
            ];
            FreeSpinHelper::updateFreeSpinDetails($updateFreespinData, $getFreespinTransaction->freespin_id);
                //create transction 
            if($status == 2) {
                $action_payload["fundtransferrequest"]["fundinfo"]["freeroundend"] = true;
            }  else {
                $action_payload["fundtransferrequest"]["fundinfo"]["freeroundend"] = false; //explod the provider trans use the original
            }
            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "provider" => 'BGaming',
                    "client_connection_name" => $client_details->connection_name,
                    "win_or_lost" => $win_or_lost,
                    "entry_id" => $entry_id,
                    "pay_amount" => $pay_amount,
                    "income" => 0 - $pay_amount,
                    "game_transaction_ext_id" => $game_trans_ext_idwin
                ],
                "provider" => [
                    "provider_request" => $request->all(), #R
                    "provider_trans_id"=> $request->issue_id, #R
                    "provider_round_id"=> $request->issue_id, #R
                ],
                "mwapi" => [
                    "roundId"=>$game_transaction_id, #R
                    "type"=>2, #R
                    "game_id" => $game_details->game_id, #R
                    "player_id" => $client_details->player_id, #R
                    "mw_response" => $responseWin, #R
                ],
                'fundtransferrequest' => [
                    'fundinfo' => [
                        'freespin' => false,
                    ]
                ]
            ];
            Helper::saveLog('Bg Create Ext FS.', $this->provider_db_id, json_encode($request->all()),$action_payload);
            $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$pay_amount,$game_details->game_code,$game_details->game_name,$game_transaction_id,'credit',false,$action_payload);
            $updateTransactionEXt = array(
                    "provider_request" =>json_encode($request->all()),
                    "mw_response" => json_encode($responseWin),
                    'mw_request' => json_encode($client_response->requestoclient),
                    'client_response' => json_encode($client_response->fundtransferresponse),
                    'transaction_detail' => 'success',
                    'general_details' => 'success',
                );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_idwin,$client_details);
            return $responseWin;
        } catch (\Exception $e) {
            $msg = array(
                'err_message' => $e->getMessage(),
                'err_line' => $e->getLine(),
                'err_file' => $e->getFile()
            );
            Helper::saveLog('Bg Create Ext FS err.', $this->provider_db_id, json_encode($request),json_encode($msg));
            return $msg;
        }

    }//end freespin action
	public  function rollbackTransaction(Request $request){
		$payload = $request->all();
		 // check request signature


        Helper::saveLog('Bgaming Refund updateTransactionEXt', $this->provider_db_id, json_encode($payload), 'ENDPOINT HIT'); 
        if(!BGamingHelper::checkSignature($request->header('x-request-sign'), $payload)) {
            $http_status = 403;
                $response = [
                            "code" =>  403,
                            "message" => "Forbidden",
                            "balance" => 0
                        ];
            return response()->json($response, $http_status);
        }
        $player_id = $payload['user_id'];
        $provider_game_name = $payload['game'];
        if($provider_game_name != 'DragonsGold100' ){
            $game_code = preg_replace('/[^\\/\-a-z\s]/i', '', $provider_game_name);
         }else{
            $game_code = $provider_game_name;
         } 
        $client_details = ProviderHelper::getClientDetails('player_id', $player_id);
        $rollback_id = $payload['actions'][0]['original_action_id'];
        $processtime = new DateTime('NOW');

        $getRefundTrans = GameTransactionMDB::findGameExt($rollback_id, 3, "transaction_id",$client_details);
        if($getRefundTrans != "false"){
            if(isset($payload['actions'][1]['action'])){
                if($payload['actions'][1]['action'] == 'rollback' ){
                     $response = [
                        "balance" => (float)$client_details->balance,
                        "game_id" => $request['game_id'],
                        "transactions" =>[
                            [
                            "action_id" =>$payload['actions'][0]['action_id'],
                            "tx_id" => $getRefundTrans->game_trans_id,
                            "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                        ],
                        [
                          "action_id" =>$payload['actions'][1]['action_id'],
                            "tx_id" =>$getRefundTrans->game_trans_id,
                            "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                        ],
                       ],
                      ];
                }
             }else{
                $response = [
                  "balance" => (float)$client_details->balance,
                  "game_id" => $request['game_id'],
                  "transactions" =>[
                    [
                    "action_id" =>$payload['actions'][0]['action_id'],
                    "tx_id" =>  $getRefundTrans->game_trans_id,
                    "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                  ],
                 ],
                ];
            }
            $updateGameTransaction = [
                'win' => 4,
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $getRefundTrans->game_trans_id, $client_details);
            return $response;
        }

        
        if($rollback_id == "unknown"){
            $balance = str_replace(".", "", $client_details->balance);
            $response = [
                "balance" => (float)$balance,
                "game_id" => $payload['game_id'],
                "transactions" =>[
                  [
                  "action_id" =>$payload['actions'][0]['action_id'],
                  "tx_id" => $payload['session_id'],
                  "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                ],
               ],
              ];
            return $response;
        }
        $provider_trans_id = $payload['actions'][0]['action_id'];
      
        // if(isset($payload['actions'][1]['action'])){
        //     if($payload['actions'][1]['action'] == 'rollback'){
        //     $rollback_load = $payload['actions'][1]['action_id'];
        //     }
        // }else{         
        //     $rollback_load = $payload['actions'][0]['action_id'];           	
        // }
        if($game_code != null){
            $game_details = Game::find($game_code, $this->provider_db_id);
        }else{
            $getGameID = GameTransactionMDB::findGameTransactionDetails($rollback_id,'transaction_id', false, $client_details);
            $game_details = Game::findByGameID($getGameID->game_id, $this->provider_db_id);
        }

		try{
                ProviderHelper::idenpotencyTable($provider_trans_id);
            }catch(\Exception $e){
                $balance = str_replace(".", "", $client_details->balance);
                $response = [
                    "balance" => (float)$balance,
                    "game_id" => $request['game_id'],
                    "transactions" =>[
                      [
                      "action_id" =>$payload['actions'][0]['action_id'],
                      "tx_id" =>  "",
                      "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                    ],
                   ],
                  ];
                return $response;
            }
            if(isset($payload['actions'][1]['original_action_id'])){
                $existing_bet = GameTransactionMDB::findGameExt($payload['actions'][1]['original_action_id'], false,'transaction_id', $client_details);
                $existing_bet2 = GameTransactionMDB::findGameExt($payload['actions'][0]['original_action_id'], false,'transaction_id', $client_details);
                $amount = $existing_bet->amount - $existing_bet2->amount;
            }else{
                $existing_bet = GameTransactionMDB::findGameExt($payload['actions'][0]['original_action_id'], false,'transaction_id', $client_details);
                $amount = $existing_bet->amount;
            }
            
            $game_trans_type = $existing_bet->game_transaction_type;
            if ($existing_bet != 'false') {
                // $rollback_action_id = $action_status == false ? $rollback_load  : $payload['actions'][0]['action_id'];
                $client_details->connection_name = $existing_bet->connection_name;
                if($game_trans_type == 2){
                    $type = "credit";
                    $balance_rollback = $client_details->balance - $amount;
                }else{
                    $type = "debit";
                    $balance_rollback =  $client_details->balance + $amount;
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $balance_rollback); 
                }
                $formatbal = number_format($balance_rollback,2,'.','');
                $balance = str_replace(".","", $formatbal);
                if(isset($payload['actions'][1]['action'])){
                    if($payload['actions'][1]['action'] == 'rollback' ){
                         $response = [
                            "balance" => (float)$balance,
                            "game_id" => $request['game_id'],
                            "transactions" =>[
                                [
                                "action_id" =>$payload['actions'][0]['action_id'],
                                "tx_id" => $existing_bet->game_trans_id,
                                "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                            ],
                            [
                              "action_id" =>$payload['actions'][1]['action_id'],
                                "tx_id" =>$existing_bet->game_trans_id,
                                "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                            ],
                           ],
                          ];
                    }
                 }else{
                    $response = [
                      "balance" => (float)$balance,
                      "game_id" => $request['game_id'],
                      "transactions" =>[
                        [
                        "action_id" =>$payload['actions'][0]['action_id'],
                        "tx_id" =>  $existing_bet->game_trans_id,
                        "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                      ],
                     ],
                    ];
                }
                $gameTransactionEXTData = array(
                    "game_trans_id" => $existing_bet->game_trans_id,
                    "provider_trans_id" => $provider_trans_id,
                    "round_id" => $rollback_id,
                    "amount" => $amount,
                    "game_transaction_type"=> 3,
                    "provider_request" =>json_encode($request->all()),
                    "mw_response" =>json_encode($response),
                );
                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
                
                $win_or_lost = 4;
                $entry_id = 2;
                $income = 0 ;
                $updateGameTransaction = [
                    'win' => $win_or_lost,
                    "pay_amount" => $amount,
                    'income' => $income,
                    'entry_id' => $entry_id,
                    'trans_status' => 3
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $existing_bet->game_trans_id, $client_details);
                if($type == 'credit'){
                    $client_response = ClientRequestHelper::fundTransfer($client_details,$amount, $game_code, $game_details->game_name, $game_trans_ext_id, $existing_bet->game_trans_id, 'debit');
                    if (isset($client_response->fundtransferresponse->status->code)) {
                        ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                            switch ($client_response->fundtransferresponse->status->code) {
                                case '200':
                                return $response;
                                break;
                                case '402':
                                    // ProviderHelper::updateGameTransactionStatus($game_transaction_id, 2, 99);
                                    $http_status = 402;
                                    $response = [
                                        
                                         "Error Code" => 500,
                                         "Message" => "Internal Error"
                                      
                                    ];

                                $updateTransactionEXt = array(
                                    "provider_request" =>json_encode($request->all()),
                                    "mw_response" => json_encode($response),
                                    'mw_request' => json_encode($client_response->requestoclient),
                                    'client_response' => json_encode($client_response->fundtransferresponse),
                                    'transaction_detail' => 'failed',
                                    'general_details' => 'failed',
                                );
                                     Helper::saveLog('Bgaming after 402 updateTransactionEXt', $this->provider_db_id, json_encode($payload), 'ENDPOINT HIT');   
                                GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                                Helper::saveLog('BG Debit failed', $this->provider_db_id, json_encode($request), $response);    
                                  return $response;
                                    break;
                            }
                    }
                }else{
                    $body_details = [
                        "type" => "credit",
                        "win" => $win_or_lost,
                        "token" => $client_details->player_token,
                        "rollback" => "true",
                        "game_details" => [
                            "game_id" => $game_details->game_id
                        ],
                        "game_transaction" => [
                            "amount" => $amount
                        ],
                        "connection_name" => $existing_bet->connection_name,
                        "game_trans_ext_id" => $game_trans_ext_id,
                        "game_transaction_id" => $existing_bet->game_trans_id
        
                    ];
        
                    try {
                        $client = new Client();
                         $guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-bgFundTransferV2MultiDB',
                             [ 'body' => json_encode($body_details), 'timeout' => '2.00']
                         );
                         //THIS RESPONSE IF THE TIMEOUT NOT FAILED
                        Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($request), $response);
                        return $response;
                    } catch (\Exception $e) {
                        Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($request), $response);
                        return $response;
                    }
                }
               
            }

	}

		private function _toPennies($value)
	{
	    return (float) str_replace(' ', '', intval(
	        strval(floatval(
	            preg_replace("/[^0-9.]/", "", $value)
	        ) * 100)
	    ));
	}

	private function _toDollars($value)
	{
		return (float) str_replace(' ', '', number_format(($value / 100), 2, '.', ' '));
	}
	public  function signatureChecker(Request $request){
        $payload = $request->all();
        $request_sign = $request->header('x-request-sign');
        $secret = config('providerlinks.bgaming.AUTH_TOKEN');
        $signature = hash_hmac('SHA256',json_encode($payload),"A95383137CE37E4E19EAD36DF59D589A");
        // $signature = hash_hmac('sha256','{"casino_id":"tigergames-int","issue_id":"12458114135","currency":"USD","games":["MechanicalOrange"],"valid_until":"2022-03-12T20:03:19Z","bet_level":3,"freespins_quantity":10,"user":{"id":"55011","email":"casino14@betrnk.com","firstname":"casino14","lastname":"casino14","nickname":"casino14 casino14","city":"PH","country":"PH","date_of_birth":"2021-01-29","gender":"m"}}','HZhPwLMXtHrmQUxjmMvBmCPM');
        dd($signature);

        Helper::saveLog('Bgaming signature', $this->provider_db_id, json_encode($signature), $request_sign);
        if($signature != $request_sign){
                $response = [
                            "code" =>  403,
                            "message" => "Forbidden",
                            "balance" => '0'
                        ];

         return response($response,400)->header('Content-Type', 'application/json');
        }else{
            $response = [
                            "code" =>  200,
                            "message" => "Correct signature",
                            "balance" => '0'
                        ];
            return $response;
        }
    }
}