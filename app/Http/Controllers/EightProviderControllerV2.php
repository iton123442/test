<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\CallParameters;
use App\Helpers\ProviderHelper;
use App\Helpers\ClientRequestHelper;
use GuzzleHttp\Exception\GuzzleException;
use App\Models\GameTransactionMDB;
use GuzzleHttp\Client;
use Carbon\Carbon;

use DB;



/**
 * 8Provider (API Version 2 POST DATA METHODS)
 *
 * @version 1.0
 * @method index
 * @method gameBet
 * @method gameWin
 * @method gameRefund
 * Available Currencies
 * AUD,BRL,BTC,CAD,CNY,COP,CZK,EUR,GBP,GHS,HKD,HRK,IDR,INR,IRR,JPY,KRW,KZT,MDL,MMK,MYR,NOK,PLN,RUB,SEK,THB,TRY,TWD,UAH,USD,VND,XOF,ZAR
 */
class EightProviderControllerV2 extends Controller
{

	// public $api_url = 'http://api.8provider.com';
	// public $secret_key = 'c270d53d4d83d69358056dbca870c0ce';
	// public $project_id = '1042';
	public $provider_db_id = 19;

	public $api_url, $secret_key, $project_id = '';

	public function __construct(){
    	$this->api_url = config('providerlinks.evoplay.api_url');
    	$this->project_id = config('providerlinks.evoplay.project_id');
    	$this->secret_key = config('providerlinks.evoplay.secretkey');
    }

    /**
     * @return string
     *
     */
	public function getSignature($system_id, $callback_version, array $args, $system_key){
	    $md5 = array();
	    $md5[] = $system_id;
	    $md5[] = $callback_version;

	    $signature = $args['signature']; // store the signature
	    unset($args['signature']); // remove signature from the array

	    $args = array_filter($args, function($val){ return !($val === null || (is_array($val) && !$val));});
	    foreach ($args as $required_arg) {
	        $arg = $required_arg;
	        if (is_array($arg)) {
	            $md5[] = implode(':', array_filter($arg, function($val){ return !($val === null || (is_array($val) && !$val));}));
	        } else {
	            $md5[] = $arg;
	        }
	    };

	    $md5[] = $system_key;
	    $md5_str = implode('*', $md5);
	    $md5 = md5($md5_str);

	    if($md5 == $signature){  // Generate Hash And Check it also!
	    	return 'true';
	    }else{
	    	return 'false';
	    }
	}



	/**
	 * get player or insert token!
	 * @param $token [custom token using token and player id]
	 */
	public function getClientDetailsEvoPlay($customToken){
		$data = explode('TIGER', $customToken);
		if(count($data) == 1){
			$client_details = ProviderHelper::getClientDetails('token', $customToken);
			if($client_details == null){
				return null;
			}
			return $client_details;
		}else{
			$client_details = ProviderHelper::getClientDetails('player_id', $data[1]);
			if($client_details == null){
				$query = DB::select('select player_id from players where player_id = '.$data[1].'');
				$playerExist = count($query);
				if($playerExist > 0){
					DB::table('player_session_tokens')->insert(
                    array('player_id' => $data[1], 
                    	  'player_token' =>  $data[0], 
                    	  'status_id' => 1,
                    	  'balance' => 0
                    	)
                    );
                    $client_details = ProviderHelper::getClientDetails('player_id', $data[1]);
                    if($client_details == null){
                    	return null;
                    }
                    return $client_details;
				}else{
					return null;
				}
			}else{
				return $client_details; // with the latest token
			}
		}
	}

	/**
	 * @author's note single method that will handle 4 API Calls
	 * @param name = bet, win, refund,
	 * 
	 */
	public function index(Request $request){
		// DB::enableQueryLog();
		ProviderHelper::saveLogWithExeption('8P index '.$request->name, $this->provider_db_id, json_encode($request->all()), 'ENDPOINT HIT');

		// $signature_checker = $this->getSignature($this->project_id, 2, $request->all(), $this->secret_key);
		// if($signature_checker == 'false'):
		// 	$msg = array(
		// 				"status" => 'error',
		// 				"error" => ["scope" => "user","no_refund" => 1,"message" => "Signature is invalid!"]
		// 			);
		// 	ProviderHelper::saveLogWithExeption('8P Signature Failed '.$request->name, $this->provider_db_id, json_encode($request->all()), $msg);
		// 	return $msg;
		// endif;

		$data = $request->all();
		if($request->name == 'init'){

			// $client_details = ProviderHelper::getClientDetails('token', $data['token']);
			$client_details = $this->getClientDetailsEvoPlay($data['token']);
			if($client_details == null){
				$response = ['status' => 'error','data' => ['scope' => 'user','message' => 'player not found']];
				return $response;
			}
			
			$response = array(
				'status' => 'ok',
				'data' => [
					'balance' => (string)$client_details->balance,
					// 'balance' => (string)$player_details->playerdetailsresponse->balance,
					'currency' => $client_details->default_currency,
				],
			);
			ProviderHelper::saveLogWithExeption('8P GAME INIT', $this->provider_db_id, json_encode($data), $response);
			return $response;

		}elseif($request->name == 'bet'){

			// $client_details = ProviderHelper::getClientDetails('token', $data['token']);
			$client_details = $this->getClientDetailsEvoPlay($data['token']);
			if($client_details == null){
				$response = ['status' => 'error','data' => ['scope' => 'user','message' => 'player not found']];
				return $response;
			}

			$game_ext = GameTransactionMDB::findGameExt($data['callback_id'], 1,'transaction_id', $client_details);
			if($game_ext == 'false'): // NO BET
				$string_to_obj = json_decode($data['data']['details']);
			    $game_id = $string_to_obj->game->game_id;
			    $game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $game_id);	
			   
				// $client_details = ProviderHelper::getClientDetails('token', $data['token']); // Moved Upper Part

				# Check Game Restricted
				// $restricted_player = ProviderHelper::checkGameRestricted($game_details->game_id, $client_details->player_id);
				// if($restricted_player){
				// 	$msg = array(
				// 		"status" => 'error',
				// 		"error" => ["scope" => "user", "no_refund" => 1, "message" => "Not enough money"]
				// 	);
				// 	return $msg;
				// }

				if ($client_details->balance < $data['data']['amount']) :
					$msg = array(
							"status" => 'error',
							"error" => ["scope" => "user", "no_refund" => 1, "message" => "Not enough money"]
						);
					return $msg;
				endif;
				try {
					ProviderHelper::saveLogWithExeption('8Provider gameBet 1', $this->provider_db_id, json_encode($data), 1);
					$payout_reason = 'Bet';
			 		$win_or_lost = 5; 
			 		# $win_or_lost = 0; // 0 Lost, 1 win, 3 draw, 4 refund, 5 processing  # (PROGRESSING MODIFIED! #01-12-21)
			 		$method = 1; // 1 bet, 2 win
					$token_id = $client_details->token_id;
					$pay_amount = 0;
			 	    $bet_payout = 0; // Bet always 0 payout!
			 	    $income = $data['data']['amount'] - $pay_amount;
			 	    $provider_trans_id = $data['callback_id'];
					$round_id = $data['data']['round_id'];


					// $game_trans = ProviderHelper::idGenerate($client_details->connection_name,1);
            		// $game_transextension = ProviderHelper::idGenerate($client_details->connection_name,2);
					$check_round_exist = GameTransactionMDB::findGameExt($round_id, 1,'round_id', $client_details);
					if ($check_round_exist == 'false') {
						$gameTransactionData = array(
							"provider_trans_id" => $provider_trans_id,
							"token_id" => $token_id,
							"game_id" => $game_details->game_id,
							"round_id" => $round_id,
							"bet_amount" =>  $data['data']['amount'],
							"win" => $win_or_lost,
							"pay_amount" => $pay_amount,
							"income" =>  $income,
							"entry_id" =>$method,
						);
						// GameTransactionMDB::createGametransactionV2($gameTransactionData,$game_trans,$client_details);
						$game_trans = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
						$gameTransactionEXTData = array(
							"game_trans_id" => $game_trans,
							"provider_trans_id" => $provider_trans_id,
							"round_id" => $round_id,
							"amount" => $data['data']['amount'],
							"game_transaction_type"=> 1,
							// "provider_request" =>json_encode($data),
						);
						// GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$game_transextension,$client_details);
						$game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
					} else {
						// $game_transextension = ProviderHelper::idGenerate($client_details->connection_name,2);
						$game_transaction = GameTransactionMDB::findGameTransactionDetails($check_round_exist->game_trans_id, 'game_transaction',false, $client_details);
						$bet_amount = $game_transaction->bet_amount + $data['data']['amount'];
						$game_trans = $check_round_exist->game_trans_id;
						$gameTransactionEXTData = array(
							"game_trans_id" => $game_trans,
							"provider_trans_id" => $provider_trans_id,
							"round_id" => $round_id,
							"amount" => $data['data']['amount'],
							"game_transaction_type"=> 1,
							// "provider_request" =>json_encode($data),
						);
						// GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$game_transextension,$client_details);
						$game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
					}

					$action = [
						"provider_name" => "evoplay"
					];

					try {
						$client_response = ClientRequestHelper::fundTransfer($client_details,ProviderHelper::amountToFloat($data['data']['amount']),$game_details->game_code,$game_details->game_name,$game_transextension,$game_trans,'debit',false,$action);
			       	     ProviderHelper::saveLogWithExeption('8Provider gameBet CRID '.$round_id, $this->provider_db_id, json_encode($data), $client_response);
					} catch (\Exception $e) {
						$msg = array("status" => 'error',"message" => $e->getMessage());
						$updateGameTransaction = ["win" => 2,'trans_status' => 5];
						GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);
						$updateTransactionEXt = array(
							"provider_request" =>json_encode($data),
							"mw_response" => json_encode($msg),
							'client_response' => $e->getMessage(),
						);
						GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
						
						# Separate Log
						// $createGameTransactionLog = [
	     //                      "connection_name" => $client_details->connection_name,
	     //                      "column" =>[
	     //                          "game_trans_ext_id" => $game_transextension,
	     //                          "request" => json_encode($data),
	     //                          "response" => json_encode($e->getMessage()),
	     //                          "log_type" => "provider_details",
	     //                          "transaction_detail" => "FAILED",
	     //                      ]
      //                   ];
	                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
						return $msg;
					}


					if(isset($client_response->fundtransferresponse->status->code) 
					             && $client_response->fundtransferresponse->status->code == "200"){
						ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance); # INHOUSE
						if ($check_round_exist != 'false') {
							$income = $bet_amount - ($game_transaction->pay_amount + $game_transaction->income);
							$win_lost = $income < 0 ? 0 : 1;
							$updateGameTransaction = [
								"pay_amount" => $pay_amount,
								"bet_amount" => $bet_amount,
								"income" =>  $income,
								"win" => $win_or_lost,
								"entry_id" => $game_transaction->entry_id,
							];
							GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);

						}			

						ProviderHelper::saveLogWithExeption('8Provider gameBet 2', $this->provider_db_id, json_encode($data), 2);
						$response = array(
							'status' => 'ok',
							'data' => [
								'balance' => (string)$client_response->fundtransferresponse->balance,
								'currency' => $client_details->default_currency,
							],
					 	);
						$updateTransactionEXt = array(
							"provider_request" =>json_encode($data),
							"mw_response" => json_encode($response),
							'mw_request' => json_encode($client_response->requestoclient),
							'client_response' => json_encode($client_response),
							'transaction_detail' => json_encode($response),
						);
						GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
						# Separate Log
						// $createGameTransactionLog = [
		    //                       "connection_name" => $client_details->connection_name,
		    //                       "column" =>[
		    //                           "game_trans_ext_id" => $game_transextension,
		    //                           "request" => json_encode($data),
		    //                           "response" => json_encode($response),
		    //                           "log_type" => "provider_details",
		    //                           "transaction_detail" => "Success",
		    //                       ]
		    //                     ];
	     //                ProviderHelper::queTransactionLogs($createGameTransactionLog);

					}elseif(isset($client_response->fundtransferresponse->status->code) 
					            && $client_response->fundtransferresponse->status->code == "402"){
						$updateGameTransaction = ["win" => 2,'trans_status' => 5];
						GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);
						$response = array(
							"status" => 'error',
							"error" => ["scope" => "user","no_refund" => 1,"message" => "Not enough money"]
						);
						$updateTransactionEXt = array(
							"provider_request" =>json_encode($data),
							"mw_response" => json_encode($response),
							"client_response" => json_encode($client_response),
						);
						GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
						// $createGameTransactionLog = [
	     //                      "connection_name" => $client_details->connection_name,
	     //                      "column" =>[
	     //                          "game_trans_ext_id" => $game_transextension,
	     //                          "request" => json_encode($data),
	     //                          "response" => json_encode($response),
	     //                          "log_type" => "provider_details",
	     //                          "transaction_detail" => "FAILED",
	     //                      ]
	     //                    ];
	     //                ProviderHelper::queTransactionLogs($createGameTransactionLog);
					}
			   		ProviderHelper::saveLogWithExeption('8Provider gameBet', $this->provider_db_id, json_encode($data), $response);
				  	return $response;
				}catch(\Exception $e){
					$msg = array(
						"status" => 'error',
						"error" => ["scope" => "user","no_refund" => 1,"message" =>  $e->getMessage().' '.$e->getLine()]
					);
					$updateTransactionEXt = array(
						"provider_request" =>json_encode($data),
						"mw_response" => json_encode($msg),
						'client_response' => $e->getMessage(),
					);
					GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
					// $createGameTransactionLog = [
     //                      "connection_name" => $client_details->connection_name,
     //                      "column" =>[
     //                          "game_trans_ext_id" => $game_transextension,
     //                          "request" => json_encode($data),
     //                          "response" => json_encode($response),
     //                          "log_type" => "provider_details",
     //                          "transaction_detail" => "FAILED",
     //                      ]
     //                    ];
     //                ProviderHelper::queTransactionLogs($createGameTransactionLog);
					return $msg;
				}
		    else:
		    	// NOTE IF CALLBACK WAS ALREADY PROCESS PROVIDER DONT NEED A ERROR RESPONSE! LEAVE IT AS IT IS!
		    	ProviderHelper::saveLogWithExeption('8Provider gameBet 3 Duplicate', $this->provider_db_id, json_encode($data), 3);
				// $client_details = ProviderHelper::getClientDetails('token', $data['token']);
				$client_details = $this->getClientDetailsEvoPlay($data['token']);
				if($client_details == null){
					$response = ['status' => 'error','data' => ['scope' => 'user','message' => 'player not found']];
					return $response;
				}

				$response = array(
					'status' => 'ok',
					'data' => [
						'balance' => (string)$client_details->balance,
						'currency' => $client_details->default_currency,
					],
			 	 );
				ProviderHelper::saveLogWithExeption('8Provider'.$data['data']['round_id'], $this->provider_db_id, json_encode($data), $response);
				return $response;
		    endif;

		}elseif($request->name == 'win'){

		$string_to_obj = json_decode($data['data']['details']);
		$game_id = $string_to_obj->game->game_id;
		$game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $game_id);

		// $client_details = ProviderHelper::getClientDetails('token', $data['token']);
		$client_details = $this->getClientDetailsEvoPlay($data['token']);
		if($client_details == null){
			$response = ['status' => 'error','data' => ['scope' => 'user','message' => 'player not found']];
			return $response;
		}

		$game_ext = GameTransactionMDB::findGameExt($data['callback_id'], 2,'transaction_id', $client_details);
		ProviderHelper::saveLogWithExeption('8P game_ext', $this->provider_db_id, json_encode($data), 'game_ext');
		if($game_ext == 'false'):
			
			// Find if win has bet record
			$existing_bet = GameTransactionMDB::findGameTransactionDetails($data['data']['round_id'], 'round_id',false, $client_details);

			ProviderHelper::saveLogWithExeption('8P existing_bet', $this->provider_db_id, json_encode($data), 'existing_bet');
			if($existing_bet != 'false'): // Bet is existing, else the bet is already updated to win
					// No Bet was found check if this is a free spin and proccess it!
				    if(isset($string_to_obj->game->action) && $string_to_obj->game->action == 'freespin'):
							try {
								# Freespin
								$amount = $data['data']['amount'];
								$payout_reason = 'Free Spin';
						 		$win_or_lost = $existing_bet->pay_amount + $amount > 0 ? 1 : 0; // 0 Lost, 1 win, 3 draw, 4 refund, 5 processing
						 		$method = 2; // 1 bet, 2 win
						 	    $token_id = $client_details->token_id;
						 	    $provider_trans_id = $data['callback_id'];
						 	    $round_id = $data['data']['round_id'];

						 	    $bet_payout = 0; // Bet always 0 payout!
						 	    $income = '-'.$data['data']['amount']; // NEgative

								// $game_ext = ProviderHelper::findGameExt($round_id, 1, 'round_id');
								$game_ext = GameTransactionMDB::findGameExt($round_id, 1,'round_id', $client_details);
								if($game_ext != 'false'){
									$game_trans = $game_ext->game_trans_id;
									// $payout = $existing_bet->pay_amount+$data['data']['amount'];
									// $updateGameTransaction = [
									// 	"pay_amount" => $payout,
									// 	"income" =>  $existing_bet->bet_amount-$payout,
									// 	"win" => $existing_bet->win,
									// 	"entry_id" => $existing_bet->entry_id,
									// ];
									// GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);
								}else{
									$game_ext = GameTransactionMDB::findGameExt($round_id, 2,'round_id', $client_details);
									if($game_ext != 'false'){
										$game_trans = $game_ext->game_trans_id;
										$game_trans = $game_ext->game_trans_id;
										$payout = $existing_bet->pay_amount+$data['data']['amount'];
										$updateGameTransaction = [
											"pay_amount" => $payout,
											"income" =>  $existing_bet->bet_amount-$payout,
											"win" => $existing_bet->win,
											"entry_id" => $existing_bet->entry_id,
										];
										GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);
									}else{
										// $gamerecord = ProviderHelper::idGenerate($client_details->connection_name,1);
										$gameTransactionData = array(
											"provider_trans_id" => $provider_trans_id,
											"token_id" => $token_id,
											"game_id" => $game_details->game_id,
											"round_id" => $round_id,
											"bet_amount" =>  0,
											"win" => $win_or_lost,
											"pay_amount" => $data['data']['amount'],
											"income" =>  $income,
											"entry_id" =>$method,
										);
										$gamerecord = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
										// GameTransactionMDB::createGametransactionv2($gameTransactionData,$gamerecord,$client_details);
									}
									
								}
						 	    // $game_transextension = ProviderHelper::idGenerate($client_details->connection_name,2);
								$gameTransactionEXTData = array(
									"game_trans_id" => $game_trans,
									"provider_trans_id" => $provider_trans_id,
									"round_id" => $round_id,
									"amount" => $data['data']['amount'],
									"game_transaction_type"=> $method,
									"provider_request" =>json_encode($data),
								);
								$game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
								// GameTransactionMDB::createGameTransactionExtv2($gameTransactionEXTData,$game_transextension,$client_details);

								# Check if data is already finished
								// $final_action = $string_to_obj->final_action;
								$final_action = $data['data']['final_action'];
								$endround = $final_action == 1 || $final_action == true ? true : false;

								// $action = [
								// 	"provider_name" => "evoplay",
								// 	"endround" => $endround
								// ];

								$response = array(
									'status' => 'ok',
									'data' => [
										'balance' => (string)$client_details->balance+$amount,
										'currency' => $client_details->default_currency,
									],
							 	);

								$win = $existing_bet->pay_amount + $amount > 0 ? 1 : 0;

								$action_payload = [
									"type" => "custom", #genreral,custom :D # REQUIRED!
									"custom" => [
										"game_transaction_ext_id" => $game_transextension,
										"client_connection_name" => $client_details->connection_name,
										"provider" => 'evoplay',
										"win_or_lost" => $win,
										"entry_id" => $existing_bet->entry_id,
										"pay_amount" => $existing_bet->pay_amount + $amount,
										"income" => $existing_bet->bet_amount - ($existing_bet->pay_amount + $amount),
										"endround" => $endround,
									],
									"provider" => [
										"provider_request" => $data, #R
										"provider_trans_id"=> $data['callback_id'], #R
										"provider_round_id"=> $round_id, #R
									],
									"mwapi" => [
										"roundId"=>$existing_bet->game_trans_id, #R
										"type"=>2, #R
										"game_id" => $game_details->game_id, #R
										"player_id" => $client_details->player_id, #R
										"mw_response" => $response, #R
									],
								];

								try {
									// $client_response = ClientRequestHelper::fundTransfer($client_details,ProviderHelper::amountToFloat($data['data']['amount']),$game_details->game_code,$game_details->game_name,$game_transextension,$game_trans,'credit',false,$action);
									$client_response = ClientRequestHelper::fundTransfer_TG($client_details,ProviderHelper::amountToFloat($amount),$game_details->game_code,$game_details->game_name,$game_trans,'credit', false, $action_payload);
								} catch (\Exception $e) {
									$msg = array("status" => 'error',"message" => $e->getMessage().' '.$e->getFile().' '.$e->getLine());
									$updateGameTransaction = ["win" => 2,'trans_status' => 5];
									GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);
									$updateTransactionEXt = array(
										"provider_request" =>json_encode($data),
										"mw_response" => json_encode($msg),
										'client_response' => $e->getMessage(),
									);
									GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);

									// $createGameTransactionLog = [
				     //                      "connection_name" => $client_details->connection_name,
				     //                      "column" =>[
				     //                          "game_trans_ext_id" => $game_transextension,
				     //                          "request" => json_encode($data),
				     //                          "response" => json_encode($msg),
				     //                          "log_type" => "provider_details",
				     //                          "transaction_detail" => "FAILED",
				     //                      ]
			      //                   ];
				     //                ProviderHelper::queTransactionLogs($createGameTransactionLog);
				                    ProviderHelper::saveLogWithExeptionWithExeption('8P ERROR FREESPIN - CLIENT CALL', $this->provider_db_id, json_encode($data), $msg);
									return $msg;
								}

								if(isset($client_response->fundtransferresponse->status->code) 
								    && $client_response->fundtransferresponse->status->code == "200"){
									ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance); #INHOUSE
									$response = array(
										'status' => 'ok',
										'data' => [
											'balance' => (string)$client_response->fundtransferresponse->balance,
											'currency' => $client_details->default_currency,
										],
								 	 );

									$updateTransactionEXt = array(
										"provider_request" =>json_encode($data),
										"mw_response" => json_encode($response),
										'mw_request' => json_encode($client_response->requestoclient),
										"client_response" => json_encode($client_response),
									);
									GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
									// $createGameTransactionLog = [
				     //                      "connection_name" => $client_details->connection_name,
				     //                      "column" =>[
				     //                          "game_trans_ext_id" => $game_transextension,
				     //                          "request" => json_encode($data),
				     //                          "response" => json_encode($response),
				     //                          "log_type" => "provider_details",
				     //                          "transaction_detail" => "SUCCESS",
				     //                      ]
				     //                    ];
				     //                ProviderHelper::queTransactionLogs($createGameTransactionLog);
									ProviderHelper::saveLogWithExeption('8P FREESPIN', $this->provider_db_id, json_encode($data), $response);
							  		return $response;
								}else{
									$response = array(
										"status" => 'error',
										"error" => ["scope" => "user","no_refund" => 1,"message" => 'Client Down']
								 	);
									$updateTransactionEXt = array(
										"provider_request" =>json_encode($data),
										"mw_response" => json_encode($msg),
										'mw_request' => json_encode($client_response->requestoclient),
										"client_response" => json_encode($client_response),
									);
									GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
									// $createGameTransactionLog = [
				     //                      "connection_name" => $client_details->connection_name,
				     //                      "column" =>[
				     //                          "game_trans_ext_id" => $game_transextension,
				     //                          "request" => json_encode($client_response->requestoclient),
				     //                          "response" => json_encode($msg),
				     //                          "log_type" => "provider_details",
				     //                          "transaction_detail" => "ERROR",
				     //                      ]
				     //                    ];
				     //                ProviderHelper::queTransactionLogs($createGameTransactionLog);
									ProviderHelper::saveLogWithExeption('8P FREESPIN', $this->provider_db_id, json_encode($data), $response);
							  		return $response;
								}


							}catch(\Exception $e){
								$msg = array(
									"status" => 'error',
									"message" => $e->getMessage().' '.$e->getFile().' '.$e->getLine(),
								);
								ProviderHelper::saveLogWithExeptionWithExeption('8P ERROR FREESPIN - FATAL ERROR', $this->provider_db_id, json_encode($data), $e->getMessage().' '.$e->getFile().' '.$e->getLine());
								return $msg;
							}
					else:
							# EXISTING BET AND NORMAL WIN
							try {
								$amount = $data['data']['amount'];
								$round_id = $data['data']['round_id'];

								# NEW ADDED 01-12-21
								if($existing_bet->pay_amount + $amount == 0){
									$win = 0; //lose
									$entry_id = 1; //win
								}else{
									$win = 1; //win
									$entry_id = 2; //win
								}
								$income = $existing_bet->bet_amount - $amount;

								$response = array(
									'status' => 'ok',
									'data' => [
										'balance' => (string)$client_details->balance + $data['data']['amount'],
										'currency' => $client_details->default_currency,
									],
								);
								// $game_transextension = ProviderHelper::idGenerate($client_details->connection_name,2);
								$gameTransactionEXTData = array(
									"game_trans_id" => $existing_bet->game_trans_id,
									"provider_trans_id" => $data['callback_id'],
									"round_id" => $round_id,
									"amount" => ProviderHelper::amountToFloat($data['data']['amount']),
									"game_transaction_type"=> 2,
									"provider_request" =>json_encode($data),
								);
								$game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
								// GameTransactionMDB::createGameTransactionExtv2($gameTransactionEXTData,$game_transextension,$client_details);
								
								# Check if data is already finished
								// $final_action = $string_to_obj->final_action;
								$final_action = $data['data']['final_action'];
								$final_action = $data['data']['final_action'];
								$endround = $final_action == 1 || $final_action == true ? true : false;

								$action_payload = [
									"type" => "custom", #genreral,custom :D # REQUIRED!
									"custom" => [
										"game_transaction_ext_id" => $game_transextension,
										"client_connection_name" => $client_details->connection_name,
										"provider" => 'evoplay',
										"win_or_lost" => $win,
										"entry_id" => $entry_id,
										"pay_amount" => $existing_bet->pay_amount + $amount,
										"income" => $existing_bet->bet_amount - ($existing_bet->pay_amount + $amount),
										"endround" => $endround,
									],
									"provider" => [
										"provider_request" => $data, #R
										"provider_trans_id"=> $data['callback_id'], #R
										"provider_round_id"=> $round_id, #R
									],
									"mwapi" => [
										"roundId"=>$existing_bet->game_trans_id, #R
										"type"=>2, #R
										"game_id" => $game_details->game_id, #R
										"player_id" => $client_details->player_id, #R
										"mw_response" => $response, #R
									],
								];


								try {
									$client_response = ClientRequestHelper::fundTransfer_TG($client_details,ProviderHelper::amountToFloat($data['data']['amount']),$game_details->game_code,$game_details->game_name,$existing_bet->game_trans_id,'credit', false, $action_payload);
								} catch (\Exception $e) {
									$msg = array("status" => 'error',"message" => $e->getMessage().' '.$e->getFile().' '.$e->getLine());
									$updateTransactionEXt = array(
										"provider_request" =>json_encode($data),
										"mw_response" => json_encode($msg),
										'client_response' => $e->getMessage(),
									);
									GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
									return $msg;
								}

								if(isset($client_response->fundtransferresponse->status->code) 
									&& $client_response->fundtransferresponse->status->code == "200"){
									ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
									$response = array(
										'status' => 'ok',
										'data' => [
											'balance' => (string)$client_response->fundtransferresponse->balance,
											'currency' => $client_details->default_currency,
										],
									);
									// $createGameTransactionLog = [
				     //                      "connection_name" => $client_details->connection_name,
				     //                      "column" =>[
				     //                          "game_trans_ext_id" => $game_transextension,
				     //                          "request" => json_encode($data),
				     //                          "response" => json_encode($response),
				     //                          "log_type" => "provider_details",
				     //                          "transaction_detail" => json_encode($client_response),
				     //                      ]
				     //                    ];
				     //                ProviderHelper::queTransactionLogs($createGameTransactionLog);
									# ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $response, $client_response->requestoclient, $client_response, $response);
								}else{
									$response = array(
										"status" => 'error',
										"error" => ["scope" => "user","no_refund" => 1,"message" => 'Client Down']
								 	);
									$updateTransactionEXt = array(
										"provider_request" =>json_encode($data),
										"mw_response" => json_encode($msg),
										'mw_request' => json_encode($client_response->requestoclient),
										"client_response" => json_encode($client_response),
									);
									GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
									// $createGameTransactionLog = [
				     //                      "connection_name" => $client_details->connection_name,
				     //                      "column" =>[
				     //                          "game_trans_ext_id" => $game_transextension,
				     //                          "request" => json_encode($client_response->requestoclient),
				     //                          "response" => json_encode($msg),
				     //                          "log_type" => "provider_details",
				     //                          "transaction_detail" => json_encode($client_response),
				     //                      ]
				     //                    ];
				     //                ProviderHelper::queTransactionLogs($createGameTransactionLog);
							  		return $response;
								}
								return $response;

							}catch(\Exception $e){
								$msg = array(
									"status" => 'error',
									"error" => ["scope" => "user","no_refund" => 1,"message" =>  $e->getMessage().' '.$e->getLine()]
								);
								ProviderHelper::saveLogWithExeptionWithExeption('8P ERROR WIN', $this->provider_db_id, json_encode($data), $msg);
								return $msg;
							}
				    endif;	
			else: 
				    if(isset($string_to_obj->game->action) && $string_to_obj->game->action == 'freespin'):
							try {
								# Freespin
								$payout_reason = 'Free Spin';
						 		$win_or_lost = $existing_bet->pay_amount + $amount > 0 ? 1 : 0; // 0 Lost, 1 win, 3 draw, 4 refund, 5 processing
						 		$method = 2; // 1 bet, 2 win
						 	    $token_id = $client_details->token_id;
						 	    $provider_trans_id = $data['callback_id'];
						 	    $round_id = $data['data']['round_id'];
						
						 	    $bet_payout = 0; // Bet always 0 payout!
						 	    $income = '-'.$data['data']['amount']; // NEgative

								 $game_ext = GameTransactionMDB::findGameExt($round_id, 1,'round_id', $client_details);
								 if($game_ext != 'false'){
									 $game_trans = $game_ext->game_trans_id;
									 // $payout = $existing_bet->pay_amount+$data['data']['amount'];
									 // $updateGameTransaction = [
										//  "pay_amount" => $payout,
										//  "income" =>  $existing_bet->bet_amount-$payout,
										//  "win" => $existing_bet->win,
										//  "entry_id" => $existing_bet->entry_id,
									 // ];
									 // GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);
								 }else{
									 // $game_ext = ProviderHelper::findGameExt($round_id, 2, 'round_id');
									 $game_ext = GameTransactionMDB::findGameExt($round_id, 2,'round_id', $client_details);
									 if($game_ext != 'false'){
										 $game_trans = $game_ext->game_trans_id;
										 $game_trans = $game_ext->game_trans_id;
										 $payout = $existing_bet->pay_amount+$data['data']['amount'];
										 $updateGameTransaction = [
											 "pay_amount" => $payout,
											 "income" =>  $existing_bet->bet_amount-$payout,
											 "win" => $existing_bet->win,
											 "entry_id" => $existing_bet->entry_id,
										 ];
										 GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);
									 }else{
										 $gameTransactionData = array(
											 "provider_trans_id" => $provider_trans_id,
											 "token_id" => $token_id,
											 "game_id" => $game_details->game_id,
											 "round_id" => $round_id,
											 "bet_amount" =>  0,
											 "win" => $win_or_lost,
											 "pay_amount" => $data['data']['amount'],
											 "income" =>  $income,
											 "entry_id" =>$method,
										 );
										 $gamerecord = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
										 // $gamerecord = ProviderHelper::idGenerate($client_details->connection_name,1);
			     						 // GameTransactionMDB::createGametransactionV2($gameTransactionData,$gamerecord,$client_details);
									 }
									 
								 }
						 	    
								$gameTransactionEXTData = array(
									"game_trans_id" => $game_trans,
									"provider_trans_id" => $provider_trans_id,
									"round_id" => $round_id,
									"amount" => $data['data']['amount'],
									"game_transaction_type"=> $method,
									"provider_request" =>json_encode($data),
								);
								// $game_transextension = ProviderHelper::idGenerate($client_details->connection_name,1);
								// GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$game_transextension,$client_details);
								$game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

								# Check if data is already finished
								// $final_action = $string_to_obj->final_action;
								$final_action = $data['data']['final_action'];
								$endround = $final_action == 1 || $final_action == true ? true : false;

								// $action = [
								// 	"provider_name" => "evoplay",
								// 	"endround" => $endround
								// ];

								$response = array(
									'status' => 'ok',
									'data' => [
										'balance' => (string)$client_details->balance+$amount,
										'currency' => $client_details->default_currency,
									],
							 	);

								$win = $existing_bet->pay_amount + $amount > 0 ? 1 : 0;

								$action_payload = [
									"type" => "custom", #genreral,custom :D # REQUIRED!
									"custom" => [
										"game_transaction_ext_id" => $game_transextension,
										"client_connection_name" => $client_details->connection_name,
										"provider" => 'evoplay',
										"win_or_lost" => $win,
										"entry_id" => $existing_bet->entry_id,
										"pay_amount" => $existing_bet->pay_amount + $amount,
										"income" => $existing_bet->bet_amount - ($existing_bet->pay_amount + $amount),
										"endround" => $endround,
									],
									"provider" => [
										"provider_request" => $data, #R
										"provider_trans_id"=> $data['callback_id'], #R
										"provider_round_id"=> $round_id, #R
									],
									"mwapi" => [
										"roundId"=>$existing_bet->game_trans_id, #R
										"type"=>2, #R
										"game_id" => $game_details->game_id, #R
										"player_id" => $client_details->player_id, #R
										"mw_response" => $response, #R
									],
								];

								try {
									$client_response = ClientRequestHelper::fundTransfer_TG($client_details,ProviderHelper::amountToFloat($amount),$game_details->game_code,$game_details->game_name,$game_trans,'credit', false, $action_payload);

									// $client_response = ClientRequestHelper::fundTransfer($client_details,ProviderHelper::amountToFloat($data['data']['amount']),$game_details->game_code,$game_details->game_name,$game_transextension,$game_trans,'credit',false,$action);
								} catch (\Exception $e) {
									$msg = array(
										"status" => 'error',
										"error" => ["scope" => "user","no_refund" => 1,"message" =>  $e->getMessage().' '.$e->getLine()]
									);
									$updateGameTransaction = ["win" => 2,'trans_status' => 5];
									GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);
									$updateTransactionEXt = array(
										"provider_request" =>json_encode($data),
										"mw_response" => json_encode($msg),
										"client_response" => json_encode($client_response),
									);
									GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
									// $createGameTransactionLog = [
				     //                      "connection_name" => $client_details->connection_name,
				     //                      "column" =>[
				     //                          "game_trans_ext_id" => $game_transextension,
				     //                          "request" => json_encode($data),
				     //                          "response" => json_encode($msg),
				     //                          "log_type" => "provider_details",
				     //                          "transaction_detail" => "FAILED",
				     //                      ]
				     //                    ];
				     //                ProviderHelper::queTransactionLogs($createGameTransactionLog);
				                    ProviderHelper::saveLogWithExeptionWithExeption('8P ERROR FREESPIN - FATAL ERROR', $this->provider_db_id, json_encode($data), $msg);
									return $msg;
								}

								if(isset($client_response->fundtransferresponse->status->code) 
								    && $client_response->fundtransferresponse->status->code == "200"){
									ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance); #INHOUSE
									$response = array(
										'status' => 'ok',
										'data' => [
											'balance' => (string)$client_response->fundtransferresponse->balance,
											'currency' => $client_details->default_currency,
										],
								 	);

									$updateTransactionEXt = array(
										"provider_request" =>json_encode($data),
										"mw_response" => json_encode($response),
										'mw_request' => json_encode($client_response->requestoclient),
										"client_response" => json_encode($client_response),
									);
									GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
									// $createGameTransactionLog = [
				     //                      "connection_name" => $client_details->connection_name,
				     //                      "column" =>[
				     //                          "game_trans_ext_id" => $game_transextension,
				     //                          "request" => json_encode($client_response->requestoclient),
				     //                          "response" => json_encode($response),
				     //                          "log_type" => "provider_details",
				     //                          "transaction_detail" => json_encode($client_response),
				     //                      ]
				     //                    ];
				     //                ProviderHelper::queTransactionLogs($createGameTransactionLog);
							  		return $response;
								}else{
									$response = array(
										"status" => 'error',
										"error" => ["scope" => "user","no_refund" => 1,"message" => 'Client Down']
								 	);
									$updateTransactionEXt = array(
										"provider_request" =>json_encode($data),
										"mw_response" => json_encode($msg),
										'mw_request' => json_encode($client_response->requestoclient),
										"client_response" => json_encode($client_response),
									);
									GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
									// $createGameTransactionLog = [
				     //                      "connection_name" => $client_details->connection_name,
				     //                      "column" =>[
				     //                          "game_trans_ext_id" => $game_transextension,
				     //                          "request" => json_encode($client_response->requestoclient),
				     //                          "response" => json_encode($response),
				     //                          "log_type" => "provider_details",
				     //                          "transaction_detail" => json_encode($client_response),
				     //                      ]
				     //                    ];
				     //                ProviderHelper::queTransactionLogs($createGameTransactionLog);
							  		return $response;
								}

							}catch(\Exception $e){
								$msg = array(
									"status" => 'error',
									// "message" => $e->getMessage(),
									"error" => ["scope" => "user","message" => $e->getMessage().' '.$e->getFile().' '.$e->getLine()]
								);
								ProviderHelper::saveLogWithExeptionWithExeption('8P ERROR FREESPIN - FATAL ERROR', $this->provider_db_id, json_encode($data), $msg);
								return $msg;
							}
				    else:
						$response = array(
							'status' => 'ok',
							'data' => [
								'balance' => (string)$client_details->balance,
								'currency' => $client_details->default_currency,
							],
					 	);
						return $response;  
				endif;
			endif;
		else:
			    // NOTE IF CALLBACK WAS ALREADY PROCESS PROVIDER DONT NEED A ERROR RESPONSE! LEAVE IT AS IT IS!
				$response = array(
					'status' => 'ok',
					'data' => [
						'balance' => (string)$client_details->balance,
						// 'balance' => (string)$player_details->playerdetailsresponse->balance,
						'currency' => $client_details->default_currency,
					],
			 	);
				return $response;
		endif;

		}elseif($request->name == 'refund'){

			// $client_details = ProviderHelper::getClientDetails('token', $data['token']);
			$client_details = $this->getClientDetailsEvoPlay($data['token']);
			if($client_details == null){
				$response = ['status' => 'error','data' => ['scope' => 'user','message' => 'player not found']];
				return $response;
			}

			$string_to_obj = json_decode($data['data']['details']);
			$game_id = $string_to_obj->game->game_id;
			$game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $game_id);

			$game_refund = GameTransactionMDB::findGameExt($data['callback_id'], 3,'transaction_id', $client_details);
			if($game_refund == 'false'): // NO REFUND WAS FOUND PROCESS IT!
			

				$game_transaction_ext = GameTransactionMDB::findGameExt($data['data']['refund_round_id'], 1,'round_id', $client_details);
				if($game_transaction_ext == 'false'):
					$response = array(
						'status' => 'ok',
						'data' => [
							'balance' => (string)$client_details->balance,
							// 'balance' => (string)$player_details->playerdetailsresponse->balance,
							'currency' => $client_details->default_currency,
						],
					);
					ProviderHelper::saveLogWithExeption('8Provider'.$data['data']['refund_round_id'], $this->provider_db_id, json_encode($data), $response);
					return $response;
				endif;

				$game_transaction_ext_refund = GameTransactionMDB::findGameExt($data['data']['refund_round_id'], 3,'round_id', $client_details);
				if($game_transaction_ext_refund != 'false'):
					$response = array(
						'status' => 'ok',
						'data' => [
							// 'balance' => (string)$player_details->playerdetailsresponse->balance,
							'balance' => (string)$client_details->balance,
							'currency' => $client_details->default_currency,
						],
					);
					ProviderHelper::saveLogWithExeption('8Provider'.$data['data']['refund_round_id'], $this->provider_db_id, json_encode($data), $response);
					return $response;
				endif;


			$existing_transaction = GameTransactionMDB::findGameTransactionDetails($game_transaction_ext->game_trans_id, 'game_transaction',false, $client_details);
			if($existing_transaction != 'false'): // IF BET WAS FOUND PROCESS IT!
				$transaction_type = $game_transaction_ext->game_transaction_type == 1 ? 'credit' : 'debit'; // 1 Bet
				if($transaction_type == 'debit'):
					if($client_details->balance < $data['data']['amount']):
						$msg = array(
							"status" => 'error',
							"error" => ["scope" => "user","no_refund" => 1,"message" => "Not enough money"]
						);
						return $msg;
					endif;
				endif;

				try {
					$gameTransactionEXTData = array(
						"game_trans_id" => $existing_transaction->game_trans_id,
						"provider_trans_id" => $data['callback_id'],
						"round_id" => $data['data']['refund_round_id'],
						"amount" => $data['data']['amount'],
						"game_transaction_type"=> 3,
						"provider_request" =>json_encode($data),
					);
					$game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

					# Check if data is already finished
					// $final_action = $string_to_obj->final_action;
					$final_action = isset($data['data']['final_action']) ? $data['data']['final_action'] : true;
					$endround = $final_action == 1 || $final_action == true ? true : false;

					$totalBetCount = [];
					$allRoundCount = GameTransactionMDB::findGameExtAll($data['data']['refund_round_id'],'allround', $client_details);
					if(count($allRoundCount) != 0){
						foreach ($allRoundCount as $key) {
		                    if($key->game_transaction_type == 1){
		                        array_push($totalBetCount, $key->amount);
		                    }
			            }
					}

					if(count($totalBetCount) == 1){
						$endround = true;
					}

					$action = [
						"provider_name" => "evoplay",
						"endround" => $endround
					];

					try {
						$client_response = ClientRequestHelper::fundTransfer($client_details,ProviderHelper::amountToFloat($data['data']['amount']),$game_details->game_code,$game_details->game_name,$game_transextension,$existing_transaction->game_trans_id, $transaction_type,true,$action);
						ProviderHelper::saveLogWithExeption('8Provider Refund CRID '.$data['data']['refund_round_id'], $this->provider_db_id, json_encode($data), $client_response);
					} catch (\Exception $e) {
						$msg = array("status" => 'error',"message" => $e->getMessage().' '.$e->getFile().' '.$e->getLine());
						$updateTransactionEXt = array(
							"provider_request" =>json_encode($data),
							"mw_response" => json_encode($msg),
							'client_response' => $e->getMessage(),
						);
						GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
						ProviderHelper::saveLogWithExeption('8Provider gameRefund - FATAL ERROR', $this->provider_db_id, json_encode($data), Helper::datesent());
						return $msg;
					}

					if(isset($client_response->fundtransferresponse->status->code) 
								&& $client_response->fundtransferresponse->status->code == "200"){
						ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance); #INHOUSE
						$response = array(
							'status' => 'ok',
							'data' => [
								'balance' => (string)$client_response->fundtransferresponse->balance,
								'currency' => $client_details->default_currency,
							],
						);
						$updateGameTransaction = ["win" => 4];
						GameTransactionMDB::updateGametransaction($updateGameTransaction, $existing_transaction->game_trans_id, $client_details);
						$updateTransactionEXt = array(
							"provider_request" =>json_encode($data),
							"mw_response" => json_encode($response),
							'mw_request' => json_encode($client_response->requestoclient),
							'client_response' => json_encode($client_response),
							'transaction_detail' => json_encode($response),
						);
						GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
					}else{
						$msg = array("status" => 'error',"message" => 'Client Down');
						$updateTransactionEXt = array(
							"provider_request" =>json_encode($data),
							"mw_response" => json_encode($response),
							'mw_request' => json_encode($client_response->requestoclient),
							'client_response' => json_encode($client_response),
							'transaction_detail' => json_encode($response),
						);
						GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
					}
					return $response;
				}catch(\Exception $e){
					$msg = array(
						"status" => 'error',
						"error" => ["scope" => "user","message" => $e->getMessage()]
					);
					ProviderHelper::saveLogWithExeption('8P ERROR REFUND', $this->provider_db_id, json_encode($data), $e->getMessage());
					return $msg;
				}
			else:
				// NO BET WAS FOUND DO NOTHING
				$response = array(
					'status' => 'ok',
					'data' => [
						'balance' => (string)$client_details->balance,
						// 'balance' => (string)$player_details->playerdetailsresponse->balance,
						'currency' => $client_details->default_currency,
					],
				);
				ProviderHelper::saveLogWithExeption('8Provider'.$data['data']['refund_round_id'], $this->provider_db_id, json_encode($data), $response);
				return $response;
			endif;
			else:
				// NOTE IF CALLBACK WAS ALREADY PROCESS/DUPLICATE PROVIDER DONT NEED A ERROR RESPONSE! LEAVE IT AS IT IS!
				$response = array(
					'status' => 'ok',
					'data' => [
						'balance' => (string)$client_details->balance,
						// 'balance' => (string)$player_details->playerdetailsresponse->balance,
						'currency' => $client_details->default_currency,
					],
				);
				ProviderHelper::saveLogWithExeption('8Provider'.$data['data']['refund_round_id'], $this->provider_db_id, json_encode($data), $response);
				return $response;
			endif;
		}
	}


	/**
	 * Find bet and update to win 
	 * @param [int] $[win] [< Win TYPE>][<0 Lost, 1 win, 3 draw, 4 refund, 5 processing>]
	 * 
	 */
	public  function updateReason($win) {
		$win_type = [
		 "1" => 'Transaction updated to win',
		 "2" => 'Transaction updated to bet',
		 "3" => 'Transaction updated to Draw',
		 "4" => 'Transaction updated to Refund',
		 "5" => 'Transaction updated to Processing',
		];
		if(array_key_exists($win, $win_type)){
    		return $win_type[$win];
    	}else{
    		return 'Transaction Was Updated!';
    	}
	}

}
