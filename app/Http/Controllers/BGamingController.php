<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Helpers\BGamingHelper;
use App\Helpers\CallParameters;
use App\Helpers\ClientRequestHelper;
use App\Helpers\ProviderHelper;
use App\Helpers\Helper;
use App\Helpers\Game;
use App\Models\GameTransaction;
use App\Models\GameTransactionMDB;
use DB;
use DateTime;

class BGamingController extends Controller
{   

	public $client_api_key , $provider_db_id ;

	public function __construct(){
		$this->client_api_key = config("providerlinks.bgaming.CLIENT_API_KEY");
		$this->provider_db_id = config("providerlinks.bgaming.PROVIDER_ID");
	}

	public function gameTransaction(Request $request){
	  Helper::saveLog('Bgaming Auth', $this->provider_db_id, json_encode($request->all()), $request->header('x-request-sign'));
	  	$payload = $request->all();
	  	$client_details = ProviderHelper::getClientDetails('player_id', $payload['user_id']);
		$request_sign = $request->header('x-request-sign');
        $secret = config('providerlinks.bgaming.AUTH_TOKEN');
		$signature = hash_hmac('sha256',json_encode($payload),$secret);
        // dd($signature);
		Helper::saveLog('Bgaming signature', $this->provider_db_id, json_encode($signature), $request_sign);
		if($signature != $request_sign){
                $response = [
                            "code" =>  403,
                            "message" => "Forbidden",
                            "balance" => '0'
                        ];

         return response($response,400)->header('Content-Type', 'application/json');
		}
		if($client_details == 'false'){
            $http_status = 400;
                $response = [
                        "code" =>  101,
                        "message" => "Player is invalid",
                        "balance" => 0
                    ];
            return response()->json($response, $http_status);
        }
        if(!isset($payload['actions'][0]['action'])){
			$response = $this->GetBalance($request->all(), $client_details);
			return response($response,200)
                ->header('Content-Type', 'application/json');	
		}

        if(isset($payload['actions'][0]['action'])){
            if(!isset($payload['actions'][1]['action'])){
                if($payload['actions'][0]['action'] == 'bet'){
    			     $response = $this->gameBet($request->all(), $client_details);
                     return response($response,200)->header('Content-Type', 'application/json');
                }
                if($payload['actions'][0]['action'] == 'win'){
                    $response = $this->gameWin($request->all(), $client_details);
                    return response($response,200)->header('Content-Type', 'application/json');
                }
            }else{
                if($payload['actions'][1]['action'] == 'win'){
                    $this->gameBet($request->all(), $client_details);
                    $response = $this->gameWin($request->all(), $client_details);
                    return response($response,200)->header('Content-Type', 'application/json');
                }
                if($payload['actions'][0]['action'] == 'bet' && $payload['actions'][1]['action'] == 'bet'){
                     $response = $this->gameBet($request->all(), $client_details);
                     return response($response,200)->header('Content-Type', 'application/json');
                }
            }
        }
  
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
			 $game_code = preg_replace('/[^\\/\-a-z\s]/i', '', $provider_game_name);
			 $round_id = $payload['game_id'];
             $time = time();
             if(isset($payload['actions'][1]['action']) && $payload['actions'][1]['action'] == 'bet'){
                 $bet_amount = ($payload['actions'][0]['amount'] + $payload['actions'][1]['amount'])/100;
             }else{
			     $bet_amount = $bet_data['amount']/100;
             }
             $processtime = new DateTime('NOW');
		     $provider_trans_id = $bet_data['action_id'];
             $txn_explode = explode("-", $provider_trans_id);
             $txid = $txn_explode[4];
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
                      "tx_id" =>  (string)$txid,
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
            $gameTransactionData = array(
                            "provider_trans_id" => $provider_trans_id,
                            "token_id" => $client_details->token_id,
                            "game_id" => $game_details->game_id,
                            "round_id" => $round_id,
                            "bet_amount" => $bet_amount,
                            "win" => 0,
                            "pay_amount" => 0,
                            "income" => 0,
                            "entry_id" => 1,
                        ); 

                  Helper::saveLog('Bet gameTransactionData', $this->provider_db_id, json_encode($request), 'ENDPOINT HIT');
                $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
                $gameTransactionEXTData = array(
                    "game_trans_id" => $game_transaction_id,
                    "provider_trans_id" => $provider_trans_id,
                    "round_id" => $round_id,
                    "amount" => $bet_amount,
                    "game_transaction_type"=> 1,
                    "provider_request" =>json_encode($request),
                    );
                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details); 
                $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount, $game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, 'debit');

               if($client_response == false){
                  $response = [

                    'code' => 504,
                    'message' => 'Request timed out',
                    'balance' => '0'
                    ];

                    return $response;
                } 

               Helper::saveLog('bgaming after client_response', $this->provider_db_id, json_encode($payload), $client_details);
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
                                                "tx_id" =>  (string)$txid,
                                                "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                                              ],
                                            ];
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
                                                "tx_id" => (string)$txid,
                                                "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                                            ],
                                            [
                                              "action_id" =>$payload['actions'][1]['action_id'],
                                                "tx_id" => (string)$txid,
                                                "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                                            ],
                                           ],
                                          ];
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
                                              "tx_id" =>  (string)$txid,
                                              "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                                            ],
                                           ],
                                          ];
                                        return $response;
                                    }

                                $updateTransactionEXt = array(
                                    "provider_request" =>json_encode($request),
                                    "mw_response" => json_encode($response),
                                    'mw_request' => json_encode($client_response->requestoclient),
                                    'client_response' => json_encode($client_response->fundtransferresponse),
                                    'transaction_detail' => 'success',
                                    'general_details' => 'success',
                                );
                            Helper::saveLog('Bgaming after success updateTransactionEXt', $this->provider_db_id, json_encode($payload), $client_response);   
                            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                             Helper::saveLog('BG 200 Debit Success', $this->provider_db_id, json_encode($request), $response);	
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

        }
    }
	public  function gameWin($request, $client_details,$action_status = false){
		    Helper::saveLog('BG Credit Hit', $this->provider_db_id, json_encode($request), 'HIT!');	
			$time = time();
			$payload = $request;
            if(isset($payload['actions'][1]['action_id'])){
                $providertemp = "TG_".$payload['actions'][1]['action_id'];
            }else{
                // $providertemp = $time."_".$payload['actions'][0]['action_id'];
                $providertemp = "TG_".$payload['actions'][0]['action_id'];
            }
            $player_id = $payload['user_id'];
            $provider_game_name = $payload['game'];
			$game_code = preg_replace('/[^\\/\-a-z\s]/i', '', $provider_game_name);
            $client_details = ProviderHelper::getClientDetails('player_id', $player_id);
            ProviderHelper::_insertOrUpdate($client_details->token_id,$client_details->balance);
            if(isset($payload['actions'][1]['action'])){
            	if($payload['actions'][1]['action'] == 'win'){
            	$win_load = $payload['actions'][1]['action_id'];
            	$pay_amount = $payload['actions'][1]['amount']/100;
            	}
            }else{
            	if($action_status == true){
            		$pay_amount = 0;
            	}else{
            		$win_load = $payload['actions'][0]['action_id'];
            		$pay_amount = $payload['actions'][0]['amount']/100;
            	}         	
            }

            $txn_explode = explode("-", $win_load);
            $txnid = $txn_explode[4];
            try{
                ProviderHelper::idenpotencyTable($providertemp);
            }catch(\Exception $e){
                $balance = str_replace(".", "", $client_details->balance);
                $response = [
                    "balance" => (float)$balance,
                    "game_id" => $request['game_id'],
                    "transactions" =>[
                      [
                      "action_id" =>$win_load,
                      "tx_id" =>  (string)$txid,
                      "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                    ],
                   ],
                  ];
                return $response;
            }
            try{
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
	             $toint = (int)$bet_transaction->game_trans_id + 1;
	             $str = (string)$toint;
	             $processtime = new DateTime('NOW');
	             ProviderHelper::_insertOrUpdate($client_details->token_id,$winbBalance);
	             $client_details1 = ProviderHelper::getClientDetails('player_id', $player_id);
                 $balance = str_replace(".", "", $client_details1->balance);
	             Helper::saveLog('BG start to process and get bal win', $this->provider_db_id, json_encode($request),$balance);
                 if(isset($payload['actions'][1]['action'])){
                    if($payload['actions'][1]['action'] == 'win' ){
                         $response = [
                            "balance" => (float)$balance,
                            "game_id" => $request['game_id'],
                            "transactions" =>[
                                [
                                "action_id" =>$winaction_id,
                                "tx_id" => (string)$txnid,
                                "processed_at" => $processtime->format('Y-m-d\TH:i:s.u'),
                            ],
                            [
                              "action_id" =>$winaction_id,
                                "tx_id" =>(string)$txnid,
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
                        "action_id" =>$winaction_id,
                        "tx_id" =>  (string)$txnid,
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
                
                   $updateGameTransaction = [
                        'win' =>$win_or_lost,
                        'pay_amount' => $amount,
                        'income' => $income,
                        'entry_id' => $entry_id,
                        'trans_status' => 2
                    ];
               Helper::saveLog('BG find client_detailss', $this->provider_db_id, json_encode($request),$client_details1);
              GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
                    $gameTransactionEXTData = array(
                        "game_trans_id" => $bet_transaction->game_trans_id,
                        "provider_trans_id" => $provider_trans_id,
                        "round_id" => $round_id,
                        "amount" => $pay_amount,
                        "game_transaction_type"=> 2,
                        "provider_request" =>json_encode($request),
                        "mw_response" => json_encode($response),
                    );

                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
                           $action_payload = [
                                "type" => "custom", #genreral,custom :D # REQUIRED!
                                "custom" => [
                                    "provider" => 'BGaming',
                                    "client_connection_name" => $client_details->connection_name,
                                    "win_or_lost" => $win_or_lost,
                                    "entry_id" => $entry_id,
                                    "pay_amount" => $pay_amount,
                                    "income" => $income,
                                    "game_trans_ext_id" => $game_trans_ext_id
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
                 ProviderHelper::_insertOrUpdate($client_details1->token_id,$winbBalance);
                 Helper::saveLog('Bg Winbalance Response.', $this->provider_db_id, json_encode($request),$winbBalance);
                 Helper::saveLog('Bg Client Response.', $this->provider_db_id, json_encode($request),$client_response);
                 $updateTransactionEXt = array(
                    "provider_request" =>json_encode($request),
                    "mw_response" => json_encode($response),
                    'mw_request' => json_encode($client_response->requestoclient),
                    'client_response' => json_encode($client_response->fundtransferresponse),
                    'transaction_detail' => 'progressing',
                    'general_details' => 'progressing',
                );
                GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                  Helper::saveLog('BG Win Result', $this->provider_db_id, json_encode($request),$response);
                    return $response;

            }catch(\Exception $e){
                     $response = [
                            	"message" => $e
                            ];    
            Helper::saveLog('BG Callback error', $this->provider_db_id, json_encode($request,JSON_FORCE_OBJECT), $response);
           return $response;

            }// End of Catch 

		
	}

	public  function rollbackTransaction(Request $request){
		$payload = $request->all();
    	/*var_dump(BGamingHelper::generateSignature(config('providerlinks.bgaming.AUTH_TOKEN'), $payload)); die();*/

		 // check request signature
        if(!BGamingHelper::checkSignature($request->header('x-request-sign'), $payload)) {
            $http_status = 403;
                $response = [
                            "code" =>  403,
                            "message" => "Forbidden",
                            "balance" => 0
                        ];
            return response()->json($response, $http_status);
        }

        if(count($payload['actions']) > 0) {
        	$response_arr = [
        					"balance" => 0,
        					"game_id" => "",
        					"transactions" => [

        						]
        					];

        	foreach ($payload['actions'] as $action_key => $action_value) {
        		if($action_value['action'] == "rollback") {
        			$rollback_transaction_id = $action_value['action_id'];
        			$original_transaction_id = $action_value['original_action_id'];

        			//check for debit transaction
        			$game_transaction = ProviderHelper::findGameTransaction($original_transaction_id, 'transaction_id', 1);
        			$game_trans_type = 'debit';

    				if ($game_transaction != 'false') {
    					// debit transaction is found, proceed refund

						if ($game_transaction->win == 2) {
							return response()->json($response, $http_status);
						}

						$game_details = Game::findByGameID($game_transaction->game_id, $this->provider_db_id);
						$tmp_current_timestamp = date('Y-m-d').'T'.date('H:m:s').'Z';
						
						$win_or_lost = 4;
			            $entry_id = 2;
			           	$income = $game_transaction->bet_amount -  $game_transaction->bet_amount;

			           	$client_details = ProviderHelper::getClientDetails('token_id', $game_transaction->token_id);

			           	ProviderHelper::updateGameTransactionV2Credit($game_transaction->game_trans_id, $game_transaction->bet_amount, $income, $win_or_lost, $entry_id, "game_trans_id", 4);

			           	$game_trans_ext_id = ProviderHelper::createGameTransExtV2($game_transaction->game_trans_id, 'Rext-'.$game_transaction->round_id, $game_transaction->round_id, $game_transaction->bet_amount, 3);

			           	$client_response = ClientRequestHelper::fundTransfer($client_details, $game_transaction->bet_amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction->game_trans_id, 'credit', "true");
						
						if (isset($client_response->fundtransferresponse->status->code)) {
							
							switch ($client_response->fundtransferresponse->status->code) {
								case '200':
									ProviderHelper::updateGameTransactionFlowStatus($game_transaction->game_trans_id, 5);
									ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
									
									$response_arr['balance'] = ProviderHelper::amountToFloat($response_arr['balance']) + ProviderHelper::amountToFloat($client_response->fundtransferresponse->balance);
									array_push($response_arr['transactions'], ["action_id" => $rollback_transaction_id, "tx_id" => $game_trans_ext_id, "processed_at" => $tmp_current_timestamp]);

									$http_status = 200;
									$response = $response_arr;
									break;
							}

							ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, $payload, $response, $client_response->requestoclient, $client_response, $payload);

							/*var_dump(json_encode($response_arr)); die();

							echo 'process done'; die();*/
						}
					}
    			
        			if($game_transaction == 'false') {
        				
	        			//check again for credit transaction
	        			$game_transaction = ProviderHelper::findGameExt($original_transaction_id, 'transaction_id', 2);
        				$game_trans_type = 'credit';
        				var_dump($game_transaction); die();
        				if($game_transaction == 'false') {
        					$http_status = 200;
							$response = [
								"status" => 1, 
								"error_id" => 107,
								"error_code" => "ROUND_NOT_FOUND",
								"error_message" => "Target round not found."
							];

							return;
        				}
        			}

        			







        		}
        	}

        	var_dump($response_arr); die();
        	var_dump($game_transaction); die();

        }

		// Find the player and client details
		$client_details = ProviderHelper::getClientDetails('player_id', $json_data['account_id']);

		if ($client_details != null) {
			/*try{
				ProviderHelper::idenpotencyTable($json_data['transaction_id']);
			}catch(\Exception $e){
				$response = [
					"errorCode" =>  10208,
					"message" => "Transaction id is exists!",
				];
				return $response;
			}*/

			$game_transaction = ProviderHelper::findGameTransaction($json_data['round_id'], 'round_id', 1);
			
			$http_status = 200;
			$response = [
				"status" => 1, 
				"error_id" => 107,
				"error_code" => "ROUND_NOT_FOUND",
				"error_message" => "Target round not found."
			];
			
			if ($game_transaction != 'false') {

				if ($game_transaction->win == 2) {
					return response()->json($response, $http_status);
				}
				$provider_game_name = $$json_data["game_id"];
			    $game_code = preg_replace('/[^\\/\-a-z\s]/i', '', $provider_game_name);
				$game_details = Game::find($game_code, $this->provider_db_id);
			
				$win_or_lost = 4;
	            $entry_id = 2;
	           	$income = $game_transaction->bet_amount -  $game_transaction->bet_amount ;

	           	ProviderHelper::updateGameTransactionV2Credit($game_transaction->game_trans_id, $game_transaction->bet_amount, $income, $win_or_lost, $entry_id, "game_trans_id", 4);

	           	$game_trans_ext_id = ProviderHelper::createGameTransExtV2($game_transaction->game_trans_id, 'Rext-'.$json_data['round_id'], $json_data['round_id'], $game_transaction->bet_amount, 3);

	           	$client_response = ClientRequestHelper::fundTransfer($client_details, $game_transaction->bet_amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction->game_trans_id, 'credit', "true");
				
				if (isset($client_response->fundtransferresponse->status->code)) {
					
					switch ($client_response->fundtransferresponse->status->code) {
						case '200':
							ProviderHelper::updateGameTransactionFlowStatus($game_transaction->game_trans_id, 5);
							ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
							$http_status = 200;
							$response = [
								"status" => 1,
								"account_id" => $client_details->player_id,
								"country" => $client_details->country_code,
								"token" => $client_details->player_token,
								"balance" => ProviderHelper::amountToFloat($client_response->fundtransferresponse->balance),
								"currency" => $client_details->default_currency,
								"transaction_id" => 'R'.'-'.$game_transaction->game_trans_id,
								"bonus_amount" => "0"
							];
							break;
					}

					ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, $json_data, $response, $client_response->requestoclient, $client_response, $json_data);

				}
			}
		}
		
		Helper::saveLog('BGaming rollback', $this->provider_db_id, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);

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
	
}