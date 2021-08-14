<?php

namespace App\Http\Controllers;

use App\Models\PlayerDetail;
use App\Models\PlayerSessionToken;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Helpers\ClientRequestHelper;
use App\Helpers\GameTransaction;
use App\Helpers\GameSubscription;
use App\Helpers\GameRound;
use App\Helpers\Game;
use App\Helpers\CallParameters;
use App\Models\GameTransactionMDB;

use DB;
use Illuminate\Http\Request;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;

// Bole Gaming
class BoleGamingController extends Controller
{

   		public $AccessKeyId, $access_key_secret, $app_key, $login_url, $logout_url;
   		public $provider_db_id = 11;


		public function changeConfig($type, $identifier){
			$client_details = ProviderHelper::getClientDetails($type, $identifier);
			if($client_details != null){
				$this->AccessKeyId = config('providerlinks.bolegaming.'.$client_details->default_currency.'.AccessKeyId');
			    $this->access_key_secret = config('providerlinks.bolegaming.'.$client_details->default_currency.'.access_key_secret');
	   		    $this->app_key = config('providerlinks.bolegaming.'.$client_details->default_currency.'.app_key');
	   		    $this->login_url = config('providerlinks.bolegaming.'.$client_details->default_currency.'.login_url');
	   		    $this->logout_url = config('providerlinks.bolegaming.'.$client_details->default_currency.'.logout_url');
			}else{
				return false;
			}
		}

		/**
		 * generated signature
		 */	
		public function generateSign()
		{
			$nonce = rand();
			$timestamp = time();
			$key = $this->access_key_secret.$nonce.$timestamp;
			$signature = sha1($key);

			$sign = [
				"timestamp" => $timestamp,
				"nonce" => $nonce,
				"signature" => $signature,
			];

      	    return $sign;
		}

		/**
		 * Verify Http Request // UPDATE v2
		 * @param Operator ID, Player Account, sha1 Encryption, Operator sub id
		 */	
		public function chashen($operator_id, $player_account, $cha, $operator_sub_id=""){

			$app_key = $this->app_key;
		    $chashen = 'operator_id='.$operator_id.'&operator_sub_id='.$operator_sub_id.'&player_account='.$player_account.'&app_key='.$app_key.'';
			// $signature = hex2bin($cha_ashen);
			$cha1 = hash_hmac('sha1', $chashen, $app_key, true);
			$cha4 = base64_encode($cha1);
			$cha3 = strtoupper($cha4);

			if($cha == $cha3){
				return true;
			}else{
				return false;
			}
		}

		/**
		 *  NOT USED!
		 *  Logout the player
		 */	
		public function playerLogout(Request $request)
		{

			 ProviderHelper::saveLogWithExeption('BOLE_LOGOUT', $this->provider_db_id, 'logouted', 'BOLE CALL');
			 $sign = $this->generateSign();

			 $http = new Client();
	         // $response = $http->post('https://api.cdmolo.com:16800/v1/player/logout', [
	         $response = $http->post($this->logout_url, [
	            'form_params' => [
	                'player_account' => $request->username,
	                'AccessKeyId'=> $this->AccessKeyId,
	                'Timestamp'=> $sign['timestamp'],
	                'Nonce'=> $sign['nonce'],
	                'Sign'=> $sign['signature']
	            ],
	         ]);

	         $client_response = $response->getBody()->getContents();
	         return $client_response;
		}


		/**
		 *  NOT USED!
		 *  TEST
		 *  Get 30 Day Game Records
		 */	
		public function get30DayGameRecord()
		{
			 $sign = $this->generateSign();

			 $http = new Client();
	         // $response = $http->post('https://api.cdmolo.com:16800/v1/player/login', [
	         // $response = $http->post(config('providerlinks.bolegaming.logout_url'), [
	         $response = $http->post($this->logout_url, [
	            'form_params' => [
	                'start_time' => time(),
	                'end_time' => $request->username,
	                'game_code'=> $request->country_code,
	                'AccessKeyId'=> $this->AccessKeyId,
	                'Timestamp'=> $sign['timestamp'],
	                'Nonce'=> $sign['nonce'],
	                'Sign'=> $sign['signature']
	            ],
	         ]);

	         $client_response = $response->getBody()->getContents();
	         return $client_response;
		}


		/**
		 *  Balance Update 
		 *      3 Types Of Game
		 *  	Slot Games
		 *  	Table Games (Mahjong)
		 *  	Table Games (BlackJack and Poker)
		 */	
		public function playerWalletCost(Request $request)
		{
			$contest_games = [
			    'blackjack','ermj','gyzjmj','hbmj','hzmj','hnmj','gdmj','dzmj','zjh','sangong','tbnn','qydz','blnn','mjxzdd','mjxlch'
			];

			$json_data = json_decode($request->getContent());
			ProviderHelper::saveLogWithExeption('BOLE playerWalletCost EH', $this->provider_db_id, $request->getContent(), Helper::datesent());
			$general_details = ["aggregator" => [], "provider" => [], "client" => []];
			$client_details = ProviderHelper::getClientDetails('player_id', $json_data->player_account);
			if($client_details == null){
				// $data = ["resp_msg" => ["code" => 43101,"message" => 'the user does not exist',"errors" => []]];
				$data = ["data" => [],"status" => ["code" => -1,"msg" => "User does not exist"]];
				return $data;
			}
			
			$total_failed_req = count($this::findAllFailedGameExt($json_data->report_id, 'transaction_id', $client_details));
			if($total_failed_req > 2){
				$data = [
					"data" => [],
					"status" => [
						"code" => 3,
						"msg" => "Order Duplicate (This Transaction failed due to internal error, code 3 to stop the call)"
					]
				];
				ProviderHelper::saveLogWithExeption('BOLE playerWalletCost - FATAL ERROR '.$json_data->report_id, $this->provider_db_id, $request->getContent(), $data);
				return $data;
			}


			$client_currency_check = ProviderHelper::getProviderCurrency($this->provider_db_id, $client_details->default_currency);
			if($client_currency_check == 'false'){
				// $data = ["resp_msg" => ["code" => 43900,"message" => 'game service error',"errors" => []]];
				$data = [
					"data" => [],
					"status" => [
						"code" => -1,
						"msg" => "Failed Currency Dont Match"
					]
				];
				return $data;
			}
			$this->changeConfig('player_id', $json_data->player_account);
			$hashen = $this->chashen($json_data->operator_id, $json_data->player_account, $json_data->sha1);
			if(!$hashen){
	            // $data = ["resp_msg" => ["code" => 43006,"message" => 'signature error',"errors" => []]];
	            $data = [
					"data" => [],
					"status" => [
						"code" => -1,
						"msg" => "Signature Error"
					]
				];
	            ProviderHelper::saveLogWithExeption('BOLE UNKNOWN CALL', $this->provider_db_id, $request->getContent(), 'UnknownboleReq');
				return $data;
			}

			$player_details_resp = Providerhelper::playerDetailsCall($client_details->player_token);
			if($player_details_resp == 'false'){
				$data = ["data" => [],"status" => ["code" => -1,"msg" => "Failed connecting to client"]];
                // $data = ["resp_msg" => ["code" => 43900,"message" => 'game service error',"errors" => []]];
			    ProviderHelper::saveLogWithExeption('BOLE playerWalletCost - FATAL ERROR FAILED TO REACH PLAYER', $this->provider_db_id, $request->getContent(), $data);
				return $data;
			}

			// LOGGER
			$general_details['client']['player_id'] = $player_details_resp->playerdetailsresponse->accountid;
			$general_details['client']['before_balance'] = floatval(number_format((float)$player_details_resp->playerdetailsresponse->balance, 2, '.', ''));
			// END LOGGER

				if($json_data->type == 20){

						$transaction_type = $json_data->cost_info->gain_gold < 0 ? 'debit' : 'credit';
					    if($json_data->game_code == 'slot'){
					    	$game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $json_data->game_code.'_'.$json_data->cost_info->scene);
					    }else{
					    	$game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $json_data->game_code);
					    }
					    if($game_details == null){
				    		$data = ["resp_msg" => ["code" => 43201,"message" => 'the game does not exist',"errors" => []]];
				    		$data = [
								"data" => [],
								"status" => [
									"code" => -1,
									"msg" => "Game Does Not Exist"
								]
							];
							return $data;
					    }


					    $db_game_name = $game_details->game_name;
						$db_game_code = $game_details->game_code;

					    // LOGGER
					    $general_details['aggregator']['game_id'] = $game_details->game_id;
					    // END LOGGER

						$token_id = $client_details->token_id;
		                $bet_amount = abs($json_data->cost_info->bet_num);
						
						//Updated By Sir Randy
						$pay_amount = abs($json_data->cost_info->gain_gold);

						// WIN LOST OR DRAW 
						$win_or_lost = $transaction_type == 'debit' ? 0 : 1;

						// SLot Games
						if(	$json_data->game_code == 'slot') {
							$income = $bet_amount - $json_data->amount;									
							$pay_amount = $json_data->amount;
							$transaction_type = $pay_amount == 0 ? 'debit' : 'credit';
							$win_or_lost = $pay_amount == 0 ? 0 : 1;
						}

						// Multi Games / Baccarat and rbwar
		                if($json_data->game_code == 'baccarat' || $json_data->game_code == 'rbwar'){

		                	$income = $bet_amount - $json_data->amount;	
							$pay_amount = abs($json_data->amount); // amount should be used here for logging!

							if($json_data->cost_info->gain_gold  == 0){
								$win_or_lost = 3; //For draw!
								$income = $bet_amount - $json_data->amount;	
							}elseif($json_data->cost_info->gain_gold  < 0){
								$income = $bet_amount - $json_data->amount;	
							}
		                }

		                // Contest Games / Mahjongs, BlackJack
						if(in_array($json_data->game_code, $contest_games)){
							// OLD
							// $pay_amount = $json_data->cost_info->gain_gold;
							// $income = $bet_amount - $json_data->cost_info->gain_gold;	
							// if($json_data->cost_info->gain_gold  == 0){
							// 	$income = 0; // If zero it means it was a draw	
							// 	$win_or_lost = 3; // DRAW
							// }elseif($json_data->cost_info->gain_gold  < 0){ 
							//     // NEGATIVE GAIN_GOLD IT MEANS LOST! and GAIN_GOLD WILL BE ALWAYS BET_NUM negative value
							// 	$pay_amount = 0; // IF NEGATIVE PUT IT AS ZERO
							// 	$income = $bet_amount - $pay_amount;	
							// }else{
							// 	$pay_amount_income = $bet_amount + $json_data->cost_info->gain_gold;
							// 	$income = $bet_amount - $pay_amount_income;	
							// 	$pay_amount = $json_data->cost_info->gain_gold;
							// }
							// END OLD
							$pay_amount =  $json_data->amount;
							$income = $bet_amount - $pay_amount;
							if($json_data->cost_info->gain_gold  == 0){
								$win_or_lost = 3; //For draw!
							}elseif($json_data->cost_info->gain_gold  < 0){
								$win_or_lost = 1;
							}
							
		                }

		                $method = $transaction_type == 'debit' ? 1 : 2;
		                $payout_reason = $json_data->cost_info->taxes > 0 ? 'with tax deduction' : null;

						$provider_trans_id = $json_data->report_id;

						if(in_array($json_data->game_code, $contest_games)){
							// OLD
							// $gamerecord  = ProviderHelper::createGameTransaction($token_id, $game_details->game_id, $bet_amount,  $pay_amount, $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id);
							// END OLD
							$check_game_ext = GameTransactionMDB::findGameExt($json_data->report_id, 1, 'transaction_id',$client_details);
							if($check_game_ext == 'false'){
								// $data = ["resp_msg" => ["code" => 2,"message" => "order does not exist","errors" => []]];
								$data = [
									"data" => [
										"balance" => floatval(number_format((float)$player_details_resp->playerdetailsresponse->balance, 2, '.', '')),
										"currency" => $client_details->default_currency
									],
									"status" => [
										"code" => 2,
										"msg" => "order does not exist"
									]
								];
								// $data = ["resp_msg" => ["code" => 43303,"message" => "order does not exist","errors" => []]];
								return $data;
							}
							$existing_bet = GameTransactionMDB::findGameTransactionDetails($check_game_ext->game_trans_id, 'game_transaction', false, $client_details);
							$client_details->connection_name = $existing_bet->connection_name;
							// $existing_bet = ProviderHelper::findGameTransaction($check_game_ext->game_trans_id, 'game_transaction');
							if($pay_amount == 0){
								$method = 1;
								$win_or_lost = 0;
							}else{
								$method = 2;
								$win_or_lost = 1;
							}
							$trans_data = array(
						 		  'win' => $win_or_lost,
		                          'pay_amount' => $pay_amount,
		                          'income' => $income,
		                          'entry_id' => $method,
		                          // 'trans_status' => 2
							);
							GameTransactionMDB::updateGametransaction($trans_data,$existing_bet->game_trans_id,$client_details);
							// ProviderHelper::updateBetTransaction($existing_bet->game_trans_id, $pay_amount, $income, $win_or_lost, $method);
							// $update = DB::table('game_transactions')
		     //          	    ->where('round_id', $existing_bet->round_id)
		     //           		->update(['pay_amount' => $pay_amount, 
				   //      		  'income' => $existing_bet->bet_amount - $pay_amount, 
				   //      		  'game_id' => $game_details->game_id, 
				   //      		  'win' => $win_or_lost, 
				   //      		  'entry_id' => $method,
				   //      		  'transaction_reason' => ProviderHelper::updateReason($win_or_lost),
			    // 			]);
						}else{
							$check_game_ext = GameTransactionMDB::findGameExt($json_data->report_id, 1, 'transaction_id',$client_details);
							if($check_game_ext == 'false'){
								// $data = ["resp_msg" => ["code" => 2,"message" => "order does not exist","errors" => []]];
								$data = [
									"data" => [
										"balance" => floatval(number_format((float)$player_details_resp->playerdetailsresponse->balance, 2, '.', '')),
										"currency" => $client_details->default_currency
									],
									"status" => [
										"code" => 2,
										"msg" => "order does not exist"
									]
								];
								// $data = ["resp_msg" => ["code" => 43303,"message" => "order does not exist","errors" => []]];
								ProviderHelper::saveLogWithExeption('BOLE playerWalletCost - ORDER NOT EXIST '.$json_data->report_id, $this->provider_db_id,$request->getContent(), $data);
								return $data;
							}
							$existing_bet = GameTransactionMDB::findGameTransactionDetails($check_game_ext->game_trans_id, 'game_transaction', false, $client_details);
							$client_details->connection_name = $existing_bet->connection_name;
							// ProviderHelper::updateBetTransaction($existing_bet->game_trans_id, $pay_amount, $income, $win_or_lost, $method);
							$trans_data = array(
						 		  'win' => $win_or_lost,
		                          'pay_amount' => $pay_amount,
		                          'income' => $income,
		                          'entry_id' => $method,
		                          // 'trans_status' => 2
							);
							GameTransactionMDB::updateGametransaction($trans_data,$existing_bet->game_trans_id,$client_details);
							// $update = DB::table('game_transactions')
		     //          	    // ->where('round_id', $existing_bet->round_id)
		     //          	    ->where('round_id', $existing_bet->round_id)
		     //           		->update(['pay_amount' => $pay_amount, 
				   //      		  'income' => $income, 
				   //      		  'game_id' => $game_details->game_id, 
				   //      		  'win' => $win_or_lost, 
				   //      		  'entry_id' => $method,
				   //      		  'transaction_reason' => ProviderHelper::updateReason($win_or_lost),
			    // 			]);
						}

						if(in_array($json_data->game_code, $contest_games)){

								// if($json_data->cost_info->gain_gold  == 0){
								// 	$pay_amount = $json_data->cost_info->gain_gold;
								// // }elseif($json_data->cost_info->gain_gold  < 0){ 
								// }elseif($json_data->cost_info->gain_gold  > 0){ 
								//     $transaction_type = 'debit';
								// 	$pay_amount = $json_data->cost_info->gain_gold;
								// }else{
								// 	$pay_amount = $json_data->cost_info->gain_gold;
								// 	// $income = $bet_amount - $pay_amount;	
								// }

								$pay_amount = abs($json_data->amount);
								$transaction_type = 'credit';

						}elseif($json_data->game_code == 'slot'){
								$pay_amount = abs($json_data->amount);
								$transaction_type = 'credit';
						}elseif($json_data->game_code == 'baccarat' || $json_data->game_code == 'rbwar'){ 
								$pay_amount = abs($json_data->amount);
								$transaction_type = 'credit';
						}

						try
						{	
							$gametransactionext = array(
			                    "game_trans_id" => $existing_bet->game_trans_id,
			                    "provider_trans_id" => $provider_trans_id,
			                    "round_id" =>$json_data->report_id,
			                    "amount" => $pay_amount,
			                    "game_transaction_type"=>2,
			                    "provider_request" => 'FAILED',
			                    "mw_response" => 'FAILED'
			                );
			                $game_transextension = GameTransactionMDB::createGameTransactionExt($gametransactionext,$client_details);
							// $game_transextension = ProviderHelper::createGameTransExtV2($existing_bet->game_trans_id,$provider_trans_id, $json_data->report_id, $pay_amount, 2);

							// LOGGER
							$general_details['aggregator']['transaction_type'] = $transaction_type;
							$general_details['aggregator']['game_trans_id'] = $existing_bet->game_trans_id;
							$general_details['aggregator']['game_trans_ext_id'] = $game_transextension;
							$general_details['provider']['amount '] = abs($pay_amount);
							// END LOGGER

							try {
								$client_response = ClientRequestHelper::fundTransfer($client_details,abs($pay_amount),$db_game_code,$db_game_name,$game_transextension,$existing_bet->game_trans_id,$transaction_type);
								ProviderHelper::saveLogWithExeption('BOLE playerWalletCost CRID '.$existing_bet->game_trans_id, $this->provider_db_id,$request->getContent(), $client_response);
							   
							} catch (\Exception $e) {
								$data = ["data" => [],"status" => ["code" => -1,"msg" => "Client Failure"]];

								$this::updatecreateGameTransExt($game_transextension, 'FAILED', $data, 'FAILED', $e->getMessage(), 'FAILED', $general_details,$client_details);
								ProviderHelper::saveLogWithExeption('BOLE playerWalletCost - FATAL ERROR', $this->provider_db_id, $data, Helper::datesent());
								return $data;
							}

			                if(isset($client_response->fundtransferresponse->status->code) 
					            && $client_response->fundtransferresponse->status->code == "200"){
			                	// LOGGER
							    $general_details['client']['after_balance'] = floatval(number_format((float)$client_response->fundtransferresponse->balance, 2, '.', ''));
							    $general_details['client']['status_code'] = $client_response->fundtransferresponse->status->code;
							    // END LOGGER

								$data = [
									"data" => [
										"balance" => floatval(number_format((float)$client_response->fundtransferresponse->balance, 2, '.', '')), 
										"currency" => $client_details->default_currency,
									],
									"status" => [
										"code" => 0,
										"msg" => "success"
									]
								];

								$this::updatecreateGameTransExt($game_transextension, json_decode($request->getContent()), $data, $client_response->requestoclient, $client_response, $data, $general_details,$client_details);

							}elseif(isset($client_response->fundtransferresponse->status->code) 
					            && $client_response->fundtransferresponse->status->code == "402"){
								// LOGGER
							    $general_details['client']['after_balance'] = floatval(number_format((float)$client_response->fundtransferresponse->balance, 2, '.', ''));
							    $general_details['client']['status_code'] = $client_response->fundtransferresponse->status->code;
							    // END LOGGER

								$data = [
									"data" => [
										"balance" => floatval(number_format((float)$client_response->fundtransferresponse->balance, 2, '.', '')),
										"currency" => $client_details->default_currency,
									],
									"status" => [
										"code" => 1,
										"msg" => "Insufficient Balance"
									]
								];
								$this::updatecreateGameTransExt($game_transextension, 'FAILED', $data, 'FAILED', $client_response, 'FAILED', $general_details,$client_details);

							}

							// OLD 
							// if(in_array($json_data->game_code, $contest_games)){
						 //    	$game_transextension = ProviderHelper::createGameTransExt($gamerecord,$provider_trans_id, $json_data->report_id, $pay_amount, 2, json_decode($request->getContent()), $data, $requesttosend, $client_response, $data);
							// }else{
							// 	$game_transextension = ProviderHelper::createGameTransExt($existing_bet->game_trans_id,$provider_trans_id, $json_data->report_id, $pay_amount, 2, json_decode($request->getContent()), $data, $requesttosend, $client_response, $data);
							// }
							// END OLD
							
							ProviderHelper::saveLogWithExeption('BOLE playerWalletCost  - SUCCESS', $this->provider_db_id, $request->getContent(), $data);
							return $data;
		               }catch (\Exception $e){
			                // $data = ["resp_msg" => ["code" => -1,"message" => 'Failed connecting to client',"errors" => []]];
			                $data = ["data" => [],"status" => ["code" => -1,"msg" => "Failed connecting to client"]];
			                // $data = ["resp_msg" => ["code" => 43900,"message" => 'game service error',"errors" => []]];
						    ProviderHelper::saveLogWithExeption('BOLE playerWalletCost - FATAL ERROR', $this->provider_db_id, $request->getContent(), $e->getMessage());
							return $data;
			           }


				}else{ // No Body Content (All ways be called first) 10 and 11
						// OLD
						// if(in_array($json_data->game_code, $contest_games)){
					 //   		$client_response = Providerhelper::playerDetailsCall($client_details->player_token);
						// 	$data = [
						// 		"data" => [
						// 			"balance" => floatval(number_format((float)$client_response->playerdetailsresponse->balance, 2, '.', '')),
						// 			"currency" => $client_details->default_currency,
						// 		],
						// 		"status" => [
						// 			"code" => 0,
						// 			"msg" => "success"
						// 		]
						// 	];
						// 	ProviderHelper::saveLogWithExeption('BOLE WALLET CALL GBI TG ONLY', $this->provider_db_id, $request->getContent(), $data);
						// 	return $data;
					 //    }else{
					 //    	$game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $json_data->game_code);
					 //    	if($game_details == null){
					 //    		$data = ["resp_msg" => ["code" => 43201,"message" => 'the game does not exist',"errors" => []]];
						// 		return $data;
						//     }
				  //   		$db_game_name = $game_details->game_name;
						// 	$db_game_code = $game_details->game_code;
						// 	$game_id = $game_details->game_id;
					 //    }
					    // END OLD

					    // TABLE GAME TEST
					    $game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $json_data->game_code);
				    	if($game_details == null){
				    		// $data = ["resp_msg" => ["code" => -1,"message" => 'Failed (the game does not exist)',"errors" => []]];
				    		// $data = ["resp_msg" => ["code" => 43201,"message" => 'the game does not exist',"errors" => []]];
				    		$data = [
								"data" => [],
								"status" => [
									"code" => -1,
									"msg" => "Failed (the game does not exist)"
								]
							];
							return $data;
					    }
			    		$db_game_name = $game_details->game_name;
						$db_game_code = $game_details->game_code;
						$game_id = $game_details->game_id;
						// TABLE GAME TEST

						// LOGGER
					    $general_details['aggregator']['game_id'] = $game_id;
					    // END LOGGER

					    $pay_amount = $json_data->amount;

					    try {
					    	
					    	 // TEST
			                $transaction_type = 'debit';
			                $game_transaction_type = 1; // 1 Bet, 2 Win
			                $game_code = $game_id;
			                $token_id = $client_details->token_id;
			                $bet_amount = $pay_amount; 
			                // $pay_amount = 0;
			                $income = 0;
			                $win_type = 0;
			                $method = 1;
			                $win_or_lost = 5; // 0 lost,  5 processing
			                $payout_reason = 'Bet';
			                $provider_trans_id = $json_data->report_id;
			                $round_id = $json_data->report_id;
			                // TEST

			                $gameTransactionData = array(
			                    "token_id" => $token_id,
								"game_id" => $game_code,
								"round_id" => $round_id,
								"bet_amount" => $bet_amount,
								"provider_trans_id" => $provider_trans_id,
								"pay_amount" => 0,
								"income" => $income,
								"entry_id" => $method,
								"win" => $win_or_lost,
			                );
			                $gamerecord = GameTransactionMDB::createGametransaction($gameTransactionData,$client_details);
							// $gamerecord  = ProviderHelper::createGameTransaction($token_id, $game_code, $bet_amount,  0, $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id, $round_id);
							$gametransactionext = array(
			                    "game_trans_id" => $gamerecord,
			                    "provider_trans_id" => $provider_trans_id,
			                    "round_id" => $round_id,
			                    "amount" => $bet_amount,
			                    "game_transaction_type"=> $game_transaction_type,
			                );
			                $game_transextension = GameTransactionMDB::createGameTransactionExt($gametransactionext,$client_details);
							// $game_transextension = ProviderHelper::createGameTransExtV2($gamerecord,$provider_trans_id, $round_id, $bet_amount, $game_transaction_type);

							// LOGGER
							$general_details['aggregator']['transaction_type'] = $transaction_type;
							$general_details['aggregator']['game_trans_id'] = $gamerecord;
							$general_details['aggregator']['game_trans_ext_id'] = $game_transextension;
							$general_details['provider']['amount '] = abs($pay_amount);
							// END LOGGER

							try {
								$client_response = ClientRequestHelper::fundTransfer($client_details,abs($pay_amount),$db_game_code,$db_game_name,$game_transextension,$gamerecord,$transaction_type);
							    ProviderHelper::saveLogWithExeption('BOLE playerWalletCost CRID '.$gamerecord, $this->provider_db_id, $request->getContent(), $client_response);
							} catch (\Exception $e) {
								$data = ["data" => [],"status" => ["code" => -1,"msg" => "Client Failure"]];
								if(isset($gamerecord)){
									// ProviderHelper::updateGameTransactionStatus($gamerecord, 2, 99);
							        $this::updatecreateGameTransExt($game_transextension, 'FAILED', $data, 'FAILED', $e->getMessage(), 'FAILED', $general_details,$client_details);
								}
								ProviderHelper::saveLogWithExeption('BOLE playerWalletCost - FATAL ERROR', $this->provider_db_id, $data, Helper::datesent());
								return $data;
							}

							if(isset($client_response->fundtransferresponse->status->code) 
					            && $client_response->fundtransferresponse->status->code == "200"){
								// LOGGER
							    $general_details['client']['after_balance'] = floatval(number_format((float)$client_response->fundtransferresponse->balance, 2, '.', ''));
								$general_details['client']['status_code'] = $client_response->fundtransferresponse->status->code;
							    // END LOGGER
								$data = [
									"data" => [
										"balance" => floatval(number_format((float)$client_response->fundtransferresponse->balance, 2, '.', '')),
										"currency" => $client_details->default_currency,
									],
									"status" => [
										"code" => 0,
										"msg" => "success"
									]
								];
								// $jData = json_decode($request->getContent());
								// $dataToUpdate = [
								// 	"provider_request" => json_encode($jData),
								// 	"mw_response" => json_encode($data),
								// 	"mw_request"=> $client_response->requestoclient,
								// 	"client_response" => $client_response,
								// 	"transaction_detail" => json_encode($data),
								// 	"general_details" => json_encode($general_details)
								// ];
								// GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$game_transextension,$client_details);

								$this::updatecreateGameTransExt($game_transextension, json_decode($request->getContent()), $data, $client_response->requestoclient, $client_response, $data, $general_details,$client_details);

							}elseif(isset($client_response->fundtransferresponse->status->code) 
					            && $client_response->fundtransferresponse->status->code == "402"){
								// if(ProviderHelper::checkFundStatus($client_response->fundtransferresponse->status->status)):
								// 	ProviderHelper::updateGameTransactionStatus($gamerecord, 2, 6);
								// else:
								//    ProviderHelper::updateGameTransactionStatus($gamerecord, 2, 99);
								// endif;
								// $data = ["resp_msg" => ["code" => 43802,"message" => "there is not enough gold","errors" => []]];
								// $data = ["resp_msg" => ["code" => 1,"message" => "Insufficient Balance" ,"errors" => []]];
								// LOGGER
							    $general_details['client']['after_balance'] = floatval(number_format((float)$client_response->fundtransferresponse->balance, 2, '.', ''));
							    $general_details['client']['status_code'] = $client_response->fundtransferresponse->status->code;
							    // END LOGGER
								$data = [
									"data" => [
										"balance" => floatval(number_format((float)$client_response->fundtransferresponse->balance, 2, '.', '')),
										"currency" => $client_details->default_currency,
									],
									"status" => [
										"code" => 1,
										"msg" => "Insufficient Balance"
									]
								];
								
								$this::updatecreateGameTransExt($game_transextension, json_decode($request->getContent()), $data, $client_response->requestoclient, $client_response, 'FAILED', $general_details,$client_details);

							}

							ProviderHelper::saveLogWithExeption('BOLE playerWalletCost - SUCCESS', $this->provider_db_id, $request->getContent(), $data);
							return $data;

					    } catch (\Exception $e) {
					    	// $data = ["resp_msg" => ["code" => -1,"message" => 'Failed',"errors" => []]];
					  //   	if(isset($gamerecord)){
							// 		ProviderHelper::updateGameTransactionStatus($gamerecord, 2, 99);
							// }
					    	$data = [
								"data" => [],
								"status" => [
									"code" => -1,
									"msg" => "Server Error"
								]
							];
					    	// $data = ["resp_msg" => ["code" => 43900,"message" => 'game service error',"errors" => []]];
						    ProviderHelper::saveLogWithExeption('BOLE playerWalletCost - FATAL ERROR', $this->provider_db_id, $request->getContent(), $e->getMessage());
							return $data;
					    }

				}

		}

		public function playerWalletBalance(Request $request)
		{
			$json_data = json_decode($request->getContent());
			$client_details = ProviderHelper::getClientDetails('player_id', $json_data->player_account);
			$this->changeConfig('player_id', $client_details->player_id);
			$hashen = $this->chashen($json_data->operator_id, $json_data->player_account, $json_data->sha1);
			if(!$hashen){
				$data = [
					"data" => [],
					"status" => [
						"code" => -1,
						"msg" => "signature error"
					]
				];
		        ProviderHelper::saveLogWithExeption('BOLE UNKNOWN CALL', $this->provider_db_id, $request->getContent(), 'UnknownboleReq');
				return $data;
			}

			$client_details = Providerhelper::getClientDetails('player_id', $json_data->player_account);
			if($client_details != null)
			{
				$client_response = Providerhelper::playerDetailsCall($client_details->player_token);
				$data = [
					"data" => [
						"balance" => floatval(number_format((float)$client_response->playerdetailsresponse->balance, 2, '.', '')),
						"currency" => $client_details->default_currency,
					],
					"status" => [
						"code" => 0,
						"msg" => "success"
					]
				];
			}else{
				$data = [
					"data" => [],
					"status" => [
						"code" => -1,
						"msg" => "the user does not exist"
					]
				];
			}
			return $data;
		}

		public static function findAllFailedGameExt($provider_identifier, $type, $client_details) {
		$connection = GameTransactionMDB::getAvailableConnection($client_details->connection_name);
		$transaction_db = DB::connection($connection["connection_name"])->table(''.$connection['db_list'][0].'.game_transaction_ext as gte');
	        if ($type == 'transaction_id') {
				$transaction_db->where([
			 		["gte.provider_trans_id", "=", $provider_identifier],
			 		["gte.transaction_detail", "=", '"FAILED"'] // Intentionally qouted for DB QUERY
			 	]);
			}
			if ($type == 'round_id') {
				$transaction_db->where([
			 		["gte.round_id", "=", $provider_identifier],
			 		["gte.transaction_detail", "=", '"FAILED"'] // Intentionally qouted for DB QUERY
			 	]);
			}  
			$result = $transaction_db->latest()->get();
			return $result ? $result : 'false';
		}
		public  static function updatecreateGameTransExt($game_trans_ext_id, $provider_request, $mw_response, $mw_request, $client_response,$transaction_detail,$general_details='NO DATA',$client_details) {
		// DB::enableQueryLog();
		$connection = GameTransactionMDB::getAvailableConnection($client_details->connection_name);
   	    $update = DB::connection($connection["connection_name"])
   	    		->table($connection['db_list'][0].'.game_transaction_ext')
                ->where('game_trans_ext_id', $game_trans_ext_id)
                ->update([
					"provider_request" => json_encode($provider_request),
					"mw_response" =>json_encode($mw_response),
					"mw_request"=>json_encode($mw_request),
					"client_response" =>json_encode($client_response),
					"transaction_detail" =>json_encode($transaction_detail),
					"general_details" =>json_encode($general_details)
	    		]);
	    // ProviderHelper::saveLogWithExeption('updatecreateGameTransExt', 999, json_encode(DB::getQueryLog()), "TIME updatecreateGameTransExt");
		return ($update ? true : false);
		}
		


}
