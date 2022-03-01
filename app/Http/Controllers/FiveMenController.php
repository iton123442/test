<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\TGGHelper;
use App\Helpers\ProviderHelper;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransactionMDB;
use App\Helpers\Game;
use Carbon\Carbon;
use DB;

class FiveMenController extends Controller
{
	public function __construct(){
    	$this->project_id = config('providerlinks.5men.project_id');
    	$this->api_key = config('providerlinks.5men.api_key');
    	$this->api_url = config('providerlinks.5men.api_url');
    	$this->startTime = microtime(true);
    	$this->prefix = "FIVEMEN";
    	$this->middleware_api = config('providerlinks.oauth_mw_api.mwurl'); 
	}
	
	// public $provider_db_id = 29; // 29 on test ,, 27 prod
	public $provider_db_id = 53; 

	public function index(Request $request){
		Helper::saveLog('5Men index '.$request->name, $this->provider_db_id, json_encode($request->all()), 'ENDPOINT HIT');

		$signature_checker = $this->getSignature($this->project_id, 2, $request->all(), $this->api_key,'check_signature');
		
		if($signature_checker == 'false'):
			$msg = array(
						"status" => 'error',
						"error" => ["scope" => "user","no_refund" => 1,"message" => "Signature is invalid!"]
					);
			Helper::saveLog('5Men Signature Failed '.$request->name, $this->provider_db_id, json_encode($request->all()), $msg);
			return $msg;
		endif;


		$client_details = ProviderHelper::getClientDetails('token',$request["token"]);

		if($client_details == null){
			$response = [
				'status' => 'error',
				'error' => [
					'scope' => "user",
					'message' => "not found",
					'detils' => ''
				]
			];
			return response($response,200)
                ->header('Content-Type', 'application/json');
		}

		if($request->name == 'init'){

			$response = $this->gameInit($request->all(), $client_details);
			return response($response,200)
                ->header('Content-Type', 'application/json');		
		}
		if($request->name == 'bet'){
			
			$response = $this->gameBet($request->all(), $client_details);
			return response($response,200)
                ->header('Content-Type', 'application/json');
		
		}
		if($request->name == 'win'){

			$response = $this->gameWin($request->all(), $client_details);
			return response($response,200)
                ->header('Content-Type', 'application/json');

		}

		if($request->name == 'refund'){

			$response = $this->gameRefund($request->all(), $client_details);
			return response($response,200)
                ->header('Content-Type', 'application/json');

		}

		
	}

public function gameBet($request, $client_details)
{	
	    // GAME DETAILS
		$string_to_obj = json_decode($request['data']['details']);
	    $game_id = $string_to_obj->game->game_id;

		$game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $game_id);

		//GET EXISTING BET IF TRUE MEANS ALREADY PROCESS 

		try{
			ProviderHelper::idenpotencyTable($this->prefix.'_'.$request['callback_id']);
		}catch(\Exception $e){

			$bet_transaction = GameTransactionMDB::findGameExt($request["callback_id"], 1,'round_id', $client_details);
            if ($bet_transaction != 'false') {
                if ($bet_transaction->mw_response == 'null') {
                   	$response = array(
						"status" => 'error',
						"error" => [
							'scope' => 'user',
							'no_refund'=> 0,
							"message" => "Internal error. Please reopen the game",
						]
					);
                }else {
                    $response = $bet_transaction->mw_response;
                }
				

            } else {
                $response = array(
					"status" => 'error',
					"error" => [
						'scope' => 'user',
						'no_refund'=> 0,
						"message" => "Internal error. Please reopen the game",
					]
				);
            } 


            Helper::saveLog('5Men bet found 1 ', $this->provider_db_id, json_encode($request), $response);
            return $response;
		}
		
		
		try {
			
			$game_transaction_type = 1; // 1 Bet, 2 Win
			$game_code = $game_details->game_code;
			$token_id = $client_details->token_id;
			$bet_amount = $request['data']['amount'];
			$pay_amount = 0;
			$income = 0;
			$method = 1;
			$win_or_lost = 5; // 0 lost,  5 processing
			$round_id = $request['data']['round_id']; // ROUND ID MW TRANSACTION
			$provider_trans_id = $request['data']['action_id']; // ROUND ID MW TRANSACTION
			$round_xt = $request['callback_id']; // PROVIDER TRANS ID MW
			//Create GameTransaction, GameExtension

			$gameTransactionData = array(
	            "provider_trans_id" => $provider_trans_id,
	            "token_id" => $client_details->token_id,
	            "game_id" => $game_details->game_id,
	            "round_id" => $round_id,
	            "bet_amount" => $bet_amount,
	            "win" => 5,
	            "pay_amount" => 0,
	            "income" => 0,
	            "entry_id" =>1,
	            "trans_status" =>1,
	        );

			$game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);

			$gameTransactionEXTData = array(
	            "game_trans_id" => $game_trans_id,
	            "provider_trans_id" => $provider_trans_id,
	            "round_id" => $round_xt,
	            "amount" => $bet_amount,
	            "game_transaction_type"=> 1,
	            "provider_request" =>json_encode($request),
	        );
	        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
	        
			try {
				$client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount,$game_code,$game_details->game_name,$game_trans_ext_id,$game_trans_id,"debit",false);
	        } catch (\Exception $e) {
			    $response = array(
					"status" => 'error',
					"error" => [
						'scope' => 'user',
						'no_refund'=> 0,
						"message" => "Internal error. Please reopen the game",
					]
				);
		        $updateTransactionEXt = array(
		            "mw_response" => json_encode($response),
		            'mw_request' => json_encode("FAILED"),
		            'client_response' => json_encode("FAILED"),
		            "transaction_detail" =>"FAILED",
					"general_details" => "FAILED",
		        );
		        GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);

				$updateGameTransaction = [
	                "win" => 2,
	                'trans_status' => 5,
	            ];
	            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
				Helper::saveLog('5Men BET FATAL ERROR', $this->provider_db_id, json_encode($request), $response);
			    return $response;
	        }

	        if (isset($client_response->fundtransferresponse->status->code)) {
	        	ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
	        	switch ($client_response->fundtransferresponse->status->code) {
					case "200":
						$response = array(
							'status' => 'ok',
							'data' => [
								'balance' => (string)$client_response->fundtransferresponse->balance,
								'currency' => $client_details->default_currency,
							],
					  	);
			            
			            $update_gametransactionext = array(
							"mw_response" =>json_encode($response),
							"mw_request"=>json_encode($client_response->requestoclient),
							"client_response" =>json_encode($client_response->fundtransferresponse),
							"transaction_detail" =>"success",
							"general_details" => "success",
						);
				        GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_id,$client_details);
				        Helper::saveLog('5Men success BET PROCESS ', $this->provider_db_id, json_encode($request), $response);
						break;
					case "402":

						$response = array(
							'status' => 'error',
							'error' => [
								'scope' => "user",
								'no_refund' => 1,
								'message' => "Not enough money",
							]
					  	);
	          			$update_gametransactionext = array(
							"mw_response" =>json_encode($response),
							"mw_request"=>json_encode($client_response->requestoclient),
							"client_response" =>json_encode($client_response->fundtransferresponse),
							"transaction_detail" =>"FAILED",
							"general_details" => "FAILED",
						);
				        GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_id,$client_details);

				        
						$updateGameTransaction = [
			                "win" => 2,
			                'trans_status' => 5
			            ];
			            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
	          			Helper::saveLog('5Men success BET Not enough money ', $this->provider_db_id, json_encode($request), $response);
						// ProviderHelper::createRestrictGame($game_details->game_id,$client_details->player_id,$game_trans_ext_id,json_encode(json_encode($response)));
						break;
					default:
						$response = array(
							"status" => 'error',
							"error" => [
								'scope' => 'user',
								'no_refund'=> 0,
								"message" => "Internal error. Please reopen the game",
							]
						);
	          			$update_gametransactionext = array(
							"mw_response" =>json_encode($response),
							"mw_request"=>json_encode($client_response->requestoclient),
							"client_response" =>json_encode($client_response->fundtransferresponse),
							"transaction_detail" =>"FAILED",
							"general_details" => "FAILED",
						);
				        GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_id,$client_details);
	          			$updateGameTransaction = [
			                "win" => 2,
			                'trans_status' => 5
			            ];
			            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
	          			Helper::saveLog('5Men success BET FAILED ', $this->provider_db_id, json_encode($request), $response);
				}

				return $response;
	        }

		} catch(\Exception $e) {
			$msg = array(
				"status" => 'error',
				"error" => [
					'scope' => 'user',
					'no_refund'=> 1,
					"message" => "System error",
				]
			);
			Helper::saveLog('5Men ERROR BET catch', $this->provider_db_id, json_encode($request), $msg);
			return $msg;
		}

	}

	public  function gameWin($request, $client_details){

		$string_to_obj = json_decode($request['data']['details']);
	    $game_id = $string_to_obj->game->game_id;

		$game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $game_id);

		//GET EXISTING BET IF TRUE MEANS ALREADY PROCESS 

		try{
			ProviderHelper::idenpotencyTable($this->prefix.'_'.$request['callback_id']);
		}catch(\Exception $e){

			$bet_transaction = GameTransactionMDB::findGameExt($request["callback_id"], 2,'round_id', $client_details);
            if ($bet_transaction != 'false') {
                if ($bet_transaction->mw_response == 'null') {
                   	$response = array(
						"status" => 'error',
						"error" => [
							'scope' => 'user',
							'no_refund'=> 0,
							"message" => "Internal error. Please reopen the game",
						]
					);
                }else {
                    $response = $bet_transaction->mw_response;
                }
				

            } else {
                $response = array(
					"status" => 'error',
					"error" => [
						'scope' => 'user',
						'no_refund'=> 0,
						"message" => "Internal error. Please reopen the game",
					]
				);
            } 


            Helper::saveLog('5Men bet found 1 ', $this->provider_db_id, json_encode($request), $response);
            return $response;
		}
		

		$reference_transaction_uuid = $request['data']['action_id'];
		$existing_bet = GameTransactionMDB::findGameTransactionDetails($reference_transaction_uuid, 'transaction_id',false, $client_details);
		if (isset($string_to_obj->game->action)) {

			if ($string_to_obj->game->action == 'spin' || $string_to_obj->game->action == 'double') {
				if ($existing_bet != 'false') {
					$client_details->connection_name = $existing_bet->connection_name;
					$amount = $request['data']['amount'];
					$transaction_uuid = $request['callback_id'];

		           	$balance = $client_details->balance + $amount;
		        	ProviderHelper::_insertOrUpdate($client_details->token_id, $balance); 
					$response = array(
						'status' => 'ok',
						'data' => [
							'balance' => (string)$balance,
							'currency' => $client_details->default_currency,
						],
				  	);

		            $gameTransactionEXTData = array(
			            "game_trans_id" => $existing_bet->game_trans_id,
			            "provider_trans_id" => $reference_transaction_uuid,
			            "round_id" => $transaction_uuid,
			            "amount" => $amount,
			            "game_transaction_type"=> 2,
			            "provider_request" =>json_encode($request),
			            "mw_response" =>json_encode($response),
			        );
			        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
		            
			        $win_or_lost = $amount > 0 ?  1 : 0;
		            $entry_id = $amount > 0 ?  2 : 1;
		           	$income = $existing_bet->bet_amount -  $amount ;
			        $updateGameTransaction = [
			            'win' => 5,
			            "pay_amount" => $amount,
			            'income' => $income,
			            'entry_id' => $entry_id,
			            'trans_status' => 2
			        ];
		        	GameTransactionMDB::updateGametransaction($updateGameTransaction, $existing_bet->game_trans_id, $client_details);

			        $body_details = [
			            "type" => "credit",
			            "win" => $win_or_lost,
			            "token" => $client_details->player_token,
			            "rollback" => false,
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
				} else {
					$response = array(
						'status' => 'error',
						'error' => [
							'scope' => "user",
							'no_refund' => 1,
							'message' => "Transaction not found",
						]
				  	);
				  	Helper::saveLog("5Men not found transaction Spin", $this->provider_db_id, json_encode($request), $response);
			        return $response;
				}
			} elseif ($string_to_obj->game->action == 'freespin') {
				$reference_transaction_uuid = $request['data']['round_id'];
				Helper::saveLog("5Men freespin", $this->provider_db_id, json_encode($request), "HIT");
				if ($existing_bet == 'false') {
					$existing_bet = GameTransactionMDB::findGameTransactionDetails($reference_transaction_uuid, 'round_id',false, $client_details);
				}
				$client_details->connection_name = $existing_bet->connection_name;
				$reference_transaction_uuid = $request['data']['action_id'];
				$amount = $request['data']['amount'];
				$transaction_uuid = $request['callback_id'];

				$balance = $client_details->balance + $amount;
	        	ProviderHelper::_insertOrUpdate($client_details->token_id, $balance); 
				$response = array(
					'status' => 'ok',
					'data' => [
						'balance' => (string)$balance,
						'currency' => $client_details->default_currency,
					],
			  	);

				$gameTransactionEXTData = array(
		            "game_trans_id" => $existing_bet->game_trans_id,
		            "provider_trans_id" => $reference_transaction_uuid,
		            "round_id" => $transaction_uuid,
		            "amount" => $amount,
		            "game_transaction_type"=> 2,
		            "provider_request" =>json_encode($request),
		            "mw_response" =>json_encode($response),
		        );
		        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

				
				$pay_amount = $existing_bet->pay_amount + $amount;
				$income = $existing_bet->bet_amount - $pay_amount;
				$entry_id = $pay_amount > 0 ? 2 : 1;
				$win_or_lost = $pay_amount > 0 ? 1 : 0;

				$updateGameTransaction = [
		            'win' => 5,
		            "pay_amount" => $pay_amount,
		            'income' => $income,
		            'entry_id' => $entry_id,
		            'trans_status' => 2
		        ];
		        GameTransactionMDB::updateGametransaction($updateGameTransaction, $existing_bet->game_trans_id, $client_details);

				$body_details = [
		            "type" => "credit",
		            "win" => $win_or_lost,
		            "token" => $client_details->player_token,
		            "rollback" => false,
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
			} elseif ($string_to_obj->game->action == 'collect') {
				$response = array(
					'status' => 'ok',
					'data' => [
						'balance' => (string)$client_details->balance,
						'currency' => $client_details->default_currency,
					],
				);
				Helper::saveLog('5Men collect', $this->provider_db_id, json_encode($request), $response);
				return $response;
			} else {

				$response = array(
					'status' => 'ok',
					'data' => [
						'balance' => (string)$client_details->balance,
						'currency' => $client_details->default_currency,
					],
				);
				Helper::saveLog('5Men win deefault', $this->provider_db_id, json_encode($request), $response);
				return $response;

			}

			
		} else {

			$response = array(
				'status' => 'ok',
				'data' => [
					'balance' => (string)$client_details->balance,
					'currency' => $client_details->default_currency,
				],
			);
			Helper::saveLog('5Men win else deefault', $this->provider_db_id, json_encode($request), $response);
			return $response;
		}
		
	}

	public  function gameRefund($data, $client_details){

		$string_to_obj = json_decode($data['data']['details']);
	    $game_id = $string_to_obj->game->game_id;

		$game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $game_id);

		//GET EXISTING BET IF TRUE MEANS ALREADY PROCESS 

		try{
			ProviderHelper::idenpotencyTable($this->prefix.'_'.$data['callback_id']);
		}catch(\Exception $e){

			$bet_transaction = GameTransactionMDB::findGameExt($data["callback_id"], 3,'round_id', $client_details);
            if ($bet_transaction != 'false') {
                if ($bet_transaction->mw_response == 'null') {
                   	$response = array(
						"status" => 'error',
						"error" => [
							'scope' => 'user',
							'no_refund'=> 0,
							"message" => "Internal error. Please reopen the game",
						]
					);
                }else {
                    $response = $bet_transaction->mw_response;
                }
				

            } else {
                $response = array(
					"status" => 'error',
					"error" => [
						'scope' => 'user',
						'no_refund'=> 0,
						"message" => "Internal error. Please reopen the game",
					]
				);
            } 


            Helper::saveLog('5Men bet found 1 ', $this->provider_db_id, json_encode($data), $response);
            return $response;
		}

		$reference_transaction_uuid = $data['data']['refund_round_id'];
		// $existing_bet = GameTransactionMDB::findGameTransactionDetails($reference_transaction_uuid, 'transaction_id',false, $client_details);
		$existing_bet = GameTransactionMDB::findGameExt($data["data"]["refund_callback_id"], 1,'round_id', $client_details);
		if ($existing_bet != 'false') {
			$client_details->connection_name = $existing_bet->connection_name;
			$amount = $data['data']['amount'];
			$transaction_uuid = $data['callback_id'];

           	$balance = $client_details->balance + $amount;
        	ProviderHelper::_insertOrUpdate($client_details->token_id, $balance); 
			$response = array(
				'status' => 'ok',
				'data' => [
					'balance' => (string)$balance,
					'currency' => $client_details->default_currency,
				],
		  	);
            $gameTransactionEXTData = array(
	            "game_trans_id" => $existing_bet->game_trans_id,
	            "provider_trans_id" => $reference_transaction_uuid,
	            "round_id" => $transaction_uuid,
	            "amount" => $amount,
	            "game_transaction_type"=> 3,
	            "provider_request" =>json_encode($data),
	            "mw_response" =>json_encode($response),
	        );
	        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
            
	        $win_or_lost = 4;
            $entry_id = 2;
           	$income = 0 ;
	        $updateGameTransaction = [
	            'win' => 5,
	            "pay_amount" => $amount,
	            'income' => $income,
	            'entry_id' => $entry_id,
	            'trans_status' => 3
	        ];
        	GameTransactionMDB::updateGametransaction($updateGameTransaction, $existing_bet->game_trans_id, $client_details);

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
	            Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($data), $response);
	            return $response;
			} catch (\Exception $e) {
	            Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($data), $response);
	            return $response;
			}
		} else {
			$response = array(
				'status' => 'error',
				'error' => [
					'scope' => "user",
					'no_refund' => 1,
					'message' => "Transaction not found",
				]
		  	);
		  	Helper::saveLog("5Men not found transaction Spin", $this->provider_db_id, json_encode($data), $response);
	        return $response;
		}

	}
	
	public static function getSignature($system_id, $version, array $args, $system_key,$type){
		$md5 = array();
		$md5[] = $system_id;
		$md5[] = $version;
	
		if($type == 'check_signature'){
			$signature = $args['signature']; // store the signature
			unset($args['signature']); // remove signature from the array
		}

		foreach ($args as $required_arg) {
			$arg = $required_arg;
			if(is_array($arg)){
				if(count($arg)) {
					$recursive_arg = '';
					array_walk_recursive($arg, function($item) use (& $recursive_arg) {
						if(!is_array($item)) { $recursive_arg .= ($item . ':');} 
					});
					$md5[] = substr($recursive_arg, 0, strlen($recursive_arg)-1); // get rid of last
				} else {
					$md5[] = '';
				}
			} else {
				$md5[] = $arg;
			}
		};

		$md5[] = $system_key;
		$md5_str = implode('*', $md5);
		$md5 = md5($md5_str);
		if($type == 'check_signature'){
			if($md5 == $signature){  // Generate Hash And Check it also!
				return 'true';
			}else{
				return 'false';
			}
		}elseif($type == 'get_signature') {
			return $md5;
		}
	}

	public static function getSignatureApi($system_id, $version, $args, $system_key,$type){
		$md5 = array();
		$md5[] = $system_id;
		$md5[] = $version;
		$md5[] = $args;
	
		if($type == 'check_signature'){
			$signature = $args['signature']; // store the signature
			unset($args['signature']); // remove signature from the array
		}

		$md5[] = $system_key;
		$md5_str = implode('*', $md5);
		$md5 = md5($md5_str);
		if($type == 'check_signature'){
			if($md5 == $signature){  // Generate Hash And Check it also!
				return 'true';
			}else{
				return 'false';
			}
		}elseif($type == 'get_signature') {
			return $md5;
		}
	}

	public function getGamelist(Request $request){
		$data = [
			'signature' => 'c36ed829bafac814e7812f1c6bde1713',
		];
		$signature =  $this->getSignature($this->project_id, 1,$data,$this->api_key,'get_signature');
		
		$url = $this->api_url.'/game/getlist';
        $requesttosend = [
            'project' =>  $this->project_id,
			'version' => 1 ,
			'signature' => $signature
		
		];
        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$signature
            ]
        ]);
        $guzzle_response = $client->post($url,['body' => json_encode($requesttosend)]);
		$client_response = json_decode($guzzle_response->getBody()->getContents());
		return json_encode($client_response);
		
	}


	public function getURL(){
		$token = 'n58ec5e159f769ae0b7b3a0774fdbf80';
		$client_player_details = $this->getClientDetails('token', $token);
        $requesttosend = [
          "project" => config('providerlinks.5men.project_id'),
          "version" => 1,
          "token" => $token,
          "game" => 498, //game_code, game_id
          "settings" =>  [
            'user_id'=> $client_player_details->player_id,
            'language'=> $client_player_details->language ? $client_player_details->language : 'en',
          ],
          "denomination" => '1', // game to be launched with values like 1.0, 1, default
          "currency" => $client_player_details->default_currency,
          "return_url_info" => 1, // url link
          "callback_version" => 2, // POST CALLBACK
        ];
        
        $signature =  ProviderHelper::getSignature($requesttosend, $this->api_key);
        $requesttosend['signature'] = $signature;
		$url = $this->api_url.'/game/getURL';
		$client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        ]);
        $response = $client->post($url,[
            'form_params' => $requesttosend,
        ]);
        $res = json_decode($response->getBody(),TRUE);
        return isset($res['data']['link']) ? $res['data']['link'] : false;
	}


	/**
	 * Initialize the balance 
	 */
	public function gameInit($request, $client_details){
		$response = [
			'status' => 'ok',
			'data' => [
				'balance' => (string)$client_details->balance,
				'currency' => $client_details->default_currency,
				'display_name' => $client_details->display_name
			]
		];
		TGGHelper::saveLog('5Men Balance Response ', $this->provider_db_id, json_encode($request), $response);
		return $response;
	}

	public static function updateBetTransaction($round_id, $pay_amount, $income, $win, $entry_id) {
		$update = DB::table('game_transactions')
			 // ->where('round_id', $round_id)
			 ->where('game_trans_id', $round_id) 
			 ->update(['pay_amount' => $pay_amount, 
				   'income' => $income, 
				   'win' => $win, 
				   'entry_id' => $entry_id,
				   'transaction_reason' => TGGHelper::updateReason($win),
				   'payout_reason' => TGGHelper::updateReason($win),
			 ]);
	 return ($update ? true : false);
 	}
 	public function getRTP(Request $request){
 		// getSignature($system_id, $version, array $args, $system_key,$type);

 		if($request['methodName'] == 'getavailablebets' || $request['methodName'] == 'getavailablebetsinmoney'){
 			$datass = [
 				'game' => $request['game'],
 				'currency' => $request['currency']
 			];
 			$signature_checker = $this->getSignature($this->project_id, 2, $datass, $this->api_key,'get_signature');
 			$requesttosend = [
	            'project' =>  $this->project_id,
				'version' => 2,
				'game' => $request['game'],
				'currency' => $request['currency'],
				'signature' => $signature_checker
			
			];
 		}else{
 			$signature_checker = $this->getSignatureApi($this->project_id, 2, $request['game'], $this->api_key,'get_signature');
 			$requesttosend = [
	            'project' =>  $this->project_id,
				'version' => 2,
				'game' => $request['game'],
				'signature' => $signature_checker
			
			];
 		}
 		$game_details = Game::find($request["game"], $this->provider_db_id);
 		$url = $this->api_url.'/game/'.$request['methodName'];

		$client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json'
            ]
        ]);
        if($request['methodName'] == 'getavailablepayouts'){
        	$data = 'RTPs';
        }elseif($request['methodName'] == 'getavailablelanguages'){
        	$data = 'Lang';
        }else{
        	$data = 'data';
        }
		$guzzle_response = $client->post($url,['body' => json_encode($requesttosend)]);
		$client_response = json_decode($guzzle_response->getBody()->getContents());
		$resBody = [
			'GameName' => $game_details->game_name,
			'GameCode' => $game_details->game_code,
			 $data => $client_response->data
		];
		return json_encode($resBody);

 		// return $signature_checker;
 	}
}
