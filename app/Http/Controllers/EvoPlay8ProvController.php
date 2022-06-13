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
class EvoPlay8ProvController extends Controller
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
	 * @author's note single method that will handle 4 API Calls
	 * @param name = bet, win, refund,
	 * 
	 */
	public function index(Request $request){
		// DB::enableQueryLog();
		ProviderHelper::saveLog('8P index '.$request->name, $this->provider_db_id, json_encode($request->all()), 'ENDPOINT HIT');

		// $signature_checker = $this->getSignature($this->project_id, 2, $request->all(), $this->secret_key);
		// if($signature_checker == 'false'):
		// 	$msg = array(
		// 				"status" => 'error',
		// 				"error" => ["scope" => "user","no_refund" => 1,"message" => "Signature is invalid!"]
		// 			);
		// 	ProviderHelper::saveLog('8P Signature Failed '.$request->name, $this->provider_db_id, json_encode($request->all()), $msg);
		// 	return $msg;
		// endif;

		$data = $request->all();
		if($request->name == 'init'){

			$client_details = ProviderHelper::getClientDetails('token', $data['token']);
			// $player_details = $this->playerDetailsCall($client_details);
			$response = array(
				'status' => 'ok',
				'data' => [
					'balance' => (string)$client_details->balance,
					// 'balance' => (string)$player_details->playerdetailsresponse->balance,
					'currency' => $client_details->default_currency,
				],
			);
			ProviderHelper::saveLog('8P GAME INIT', $this->provider_db_id, json_encode($data), $response);
			return $response;

		}elseif($request->name == 'bet'){

			$client_details = ProviderHelper::getClientDetails('token', $data['token']);
			// $game_ext = $this->checkTransactionExist($data['callback_id'], 1);
			// $game_ext = GameTransactionMDB::findGameTransactionDetails($data['callback_id'], 'transaction_id',1, $client_details);
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

				// $player_details = $this->playerDetailsCall($client_details);
				// if ($player_details->playerdetailsresponse->balance < $data['data']['amount']) :
				if ($client_details->balance < $data['data']['amount']) :
					$msg = array(
							"status" => 'error',
							"error" => ["scope" => "user", "no_refund" => 1, "message" => "Not enough money"]
						);
					// ProviderHelper::saveLog('8Provider gameBet PC', $this->provider_db_id, json_encode($player_details), $msg);
					return $msg;
				endif;
				try {
					ProviderHelper::saveLog('8Provider gameBet 1', $this->provider_db_id, json_encode($data), 1);
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


					// $check_round_exist = ProviderHelper::findGameExt($round_id, 1, 'round_id');
					// if ($check_round_exist == 'false') {
					// 	$game_trans = ProviderHelper::createGameTransaction($token_id, $game_details->game_id, $data['data']['amount'],  $pay_amount, $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id, $round_id);
					// 	$game_transextension = ProviderHelper::createGameTransExtV2($game_trans, $provider_trans_id, $round_id, $data['data']['amount'], 1);
					// } else {
					// 	$game_transaction = ProviderHelper::findGameTransaction($check_round_exist->game_trans_id, 'game_transaction');
					// 	$bet_amount = $game_transaction->bet_amount + $data['data']['amount'];
					// 	$game_trans = $check_round_exist->game_trans_id;
					// 	$game_transextension = ProviderHelper::createGameTransExtV2($game_trans, $provider_trans_id, $round_id, $data['data']['amount'], 1);
					// }

					$game_trans = ProviderHelper::idGenerate($client_details->connection_name,1);
            		$game_transextension = ProviderHelper::idGenerate($client_details->connection_name,2);
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
						GameTransactionMDB::createGametransactionV2($gameTransactionData,$game_trans,$client_details);
						$gameTransactionEXTData = array(
							"game_trans_id" => $game_trans,
							"provider_trans_id" => $provider_trans_id,
							"round_id" => $round_id,
							"amount" => $data['data']['amount'],
							"game_transaction_type"=> 1,
							// "provider_request" =>json_encode($data),
						);
						GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$game_transextension,$client_details);
					} else {
						$game_transextension = ProviderHelper::idGenerate($client_details->connection_name,2);
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
						GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$game_transextension,$client_details);
					}


					try {
						$client_response = ClientRequestHelper::fundTransfer($client_details,ProviderHelper::amountToFloat($data['data']['amount']),$game_details->game_code,$game_details->game_name,$game_transextension,$game_trans,'debit');
			       	     ProviderHelper::saveLog('8Provider gameBet CRID '.$round_id, $this->provider_db_id, json_encode($data), $client_response);
					} catch (\Exception $e) {
						$msg = array("status" => 'error',"message" => $e->getMessage());
						// ProviderHelper::updateGameTransactionStatus($game_trans, 99, 99);
						// ProviderHelper::updatecreateGameTransExt($game_transextension, 'FAILED', $msg, 'FAILED', $e->getMessage(), 'FAILED', 'FAILED');
						// ProviderHelper::saveLog('8Provider gameBet - FATAL ERROR', $this->provider_db_id, json_encode($data), Helper::datesent());
						// return $msg;
						$updateGameTransaction = ["win" => 2,'trans_status' => 5];
						GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);
						// $updateTransactionEXt = array(
						// 	"provider_request" =>json_encode($data),
						// 	"mw_response" => json_encode($msg),
						// 	"client_response" => json_encode($client_response),
						// 	// 'client_response' => $e->getMessage(),
						// );
						// GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
						$createGameTransactionLog = [
	                          "connection_name" => $client_details->connection_name,
	                          "column" =>[
	                              "game_trans_ext_id" => $game_transextension,
	                              "request" => json_encode($data),
	                              "response" => json_encode($e->getMessage()),
	                              "log_type" => "provider_details",
	                              "transaction_detail" => "FAILED",
	                          ]
	                        ];
	                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
						return $msg;
					}


					if(isset($client_response->fundtransferresponse->status->code) 
					             && $client_response->fundtransferresponse->status->code == "200"){
						ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance); # INHOUSE
						if ($check_round_exist != 'false') {
							$income = $bet_amount - ($game_transaction->pay_amount + $game_transaction->income);
							$win_lost = $income < 0 ? 0 : 1;
							// $this->updateBetTransactionWithBet($game_trans, $game_transaction->pay_amount, $bet_amount, $income, $win_lost, $game_transaction->entry_id);
							$updateGameTransaction = [
								"pay_amount" => $pay_amount,
								"bet_amount" => $bet_amount,
								"income" =>  $income,
								"win" => $win_or_lost,
								"entry_id" => $game_transaction->entry_id,
							];
							GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);

						}			

						ProviderHelper::saveLog('8Provider gameBet 2', $this->provider_db_id, json_encode($data), 2);
						$response = array(
							'status' => 'ok',
							'data' => [
								'balance' => (string)$client_response->fundtransferresponse->balance,
								'currency' => $client_details->default_currency,
							],
					 	);
						// $updateTransactionEXt = array(
						// 	"provider_request" =>json_encode($data),
						// 	"mw_response" => json_encode($response),
						// 	'mw_request' => json_encode($client_response->requestoclient),
						// 	'client_response' => json_encode($client_response),
						// 	'transaction_detail' => json_encode($response),
						// );
						// GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
						$createGameTransactionLog = [
		                          "connection_name" => $client_details->connection_name,
		                          "column" =>[
		                              "game_trans_ext_id" => $game_transextension,
		                              "request" => json_encode($data),
		                              "response" => json_encode($response),
		                              "log_type" => "provider_details",
		                              "transaction_detail" => "Success",
		                          ]
		                        ];
		                    ProviderHelper::queTransactionLogs($createGameTransactionLog);

					}elseif(isset($client_response->fundtransferresponse->status->code) 
					            && $client_response->fundtransferresponse->status->code == "402"){
						$updateGameTransaction = ["win" => 2,'trans_status' => 5];
						GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);
						$response = array(
							"status" => 'error',
							"error" => ["scope" => "user","no_refund" => 1,"message" => "Not enough money"]
						);
						// $updateTransactionEXt = array(
						// 	"provider_request" =>json_encode($data),
						// 	"mw_response" => json_encode($response),
						// 	"client_response" => json_encode($client_response),
						// );
						// GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
						$createGameTransactionLog = [
	                          "connection_name" => $client_details->connection_name,
	                          "column" =>[
	                              "game_trans_ext_id" => $game_transextension,
	                              "request" => json_encode($data),
	                              "response" => json_encode($response),
	                              "log_type" => "provider_details",
	                              "transaction_detail" => "FAILED",
	                          ]
	                        ];
	                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
					}
			   		// $trans_ext = $this->create8PTransactionExt($game_trans, $data, $requesttosend, $client_response, $client_response,$data, 1, $data['data']['amount'], $provider_trans_id,$round_id);
			   		ProviderHelper::saveLog('8Provider gameBet', $this->provider_db_id, json_encode($data), $response);
				  	return $response;
				}catch(\Exception $e){
					$msg = array(
						"status" => 'error',
						"error" => ["scope" => "user","no_refund" => 1,"message" =>  $e->getMessage().' '.$e->getLine()]
					);
					// $updateTransactionEXt = array(
					// 	"provider_request" =>json_encode($data),
					// 	"mw_response" => json_encode($msg),
					// 	'client_response' => $e->getMessage(),
					// );
					// GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
					$createGameTransactionLog = [
                          "connection_name" => $client_details->connection_name,
                          "column" =>[
                              "game_trans_ext_id" => $game_transextension,
                              "request" => json_encode($data),
                              "response" => json_encode($response),
                              "log_type" => "provider_details",
                              "transaction_detail" => "FAILED",
                          ]
                        ];
                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
					return $msg;
				}
		    else:
		    	// NOTE IF CALLBACK WAS ALREADY PROCESS PROVIDER DONT NEED A ERROR RESPONSE! LEAVE IT AS IT IS!
		    	ProviderHelper::saveLog('8Provider gameBet 3 Duplicate', $this->provider_db_id, json_encode($data), 3);
				$client_details = ProviderHelper::getClientDetails('token', $data['token']);
				$response = array(
					'status' => 'ok',
					'data' => [
						'balance' => (string)$client_details->balance,
						// 'balance' => (string)$player_details->playerdetailsresponse->balance,
						'currency' => $client_details->default_currency,
					],
			 	 );
				ProviderHelper::saveLog('8Provider'.$data['data']['round_id'], $this->provider_db_id, json_encode($data), $response);
				return $response;
		    endif;

		}elseif($request->name == 'win'){

		$string_to_obj = json_decode($data['data']['details']);
		$game_id = $string_to_obj->game->game_id;
		$game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $game_id);
		$client_details = ProviderHelper::getClientDetails('token', $data['token']);
		// $player_details = $this->playerDetailsCall($client_details);

		// $game_ext = $this->checkTransactionExist($data['callback_id'], 2); // Find if this callback in game extension
		// $game_ext = GameTransactionMDB::findGameTransactionDetails($data['callback_id'], 'transaction_id',2, $client_details);
		$game_ext = GameTransactionMDB::findGameExt($data['callback_id'], 2,'transaction_id', $client_details);
		ProviderHelper::saveLog('8P game_ext', $this->provider_db_id, json_encode($data), 'game_ext');
		if($game_ext == 'false'):
			
			// $existing_bet = $this->findGameTransaction($data['data']['round_id'], 'round_id', 1); // Find if win has bet record
			$existing_bet = GameTransactionMDB::findGameTransactionDetails($data['data']['round_id'], 'round_id',false, $client_details);

			ProviderHelper::saveLog('8P existing_bet', $this->provider_db_id, json_encode($data), 'existing_bet');
			// $client_details = ProviderHelper::getClientDetails('token', $data['token']);
			if($existing_bet != 'false'): // Bet is existing, else the bet is already updated to win
					 // No Bet was found check if this is a free spin and proccess it!
					ProviderHelper::saveLog('8P existing_bet = false', $this->provider_db_id, json_encode($data), 'existing_bet = false');
				    if(isset($string_to_obj->game->action) && $string_to_obj->game->action == 'freespin'):
				    	ProviderHelper::saveLog('8Provider freespin 1', $this->provider_db_id, json_encode($data), 1);
				  	    	// $client_details = ProviderHelper::getClientDetails('token', $data['token']);
							try {
								ProviderHelper::saveLog('8P FREESPIN', $this->provider_db_id, json_encode($data), 'FREESPIN');
								$payout_reason = 'Free Spin';
						 		$win_or_lost = 1; // 0 Lost, 1 win, 3 draw, 4 refund, 5 processing
						 		$method = 2; // 1 bet, 2 win
						 	    $token_id = $client_details->token_id;
						 	    $provider_trans_id = $data['callback_id'];
						 	    $round_id = $data['data']['round_id'];
						
						 	    $bet_payout = 0; // Bet always 0 payout!
						 	    $income = '-'.$data['data']['amount']; // NEgative

								// $game_ext = ProviderHelper::findGameExt($round_id, 1, 'round_id');
								$game_ext = GameTransactionMDB::findGameExt($round_id, 1,'round_id', $client_details);
								if($game_ext != 'false'){
									// $game_trans = $game_ext->game_trans_id;
									// $existing_bet = $this->findGameTransID($game_ext->game_trans_id);
									// $payout = $existing_bet->pay_amount+$data['data']['amount'];
									// $this->updateBetTransaction($game_ext->game_trans_id, $payout, $existing_bet->bet_amount-$payout, $existing_bet->win, $existing_bet->entry_id);
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
									// $game_ext = ProviderHelper::findGameExt($round_id, 2, 'round_id');
									$game_ext = GameTransactionMDB::findGameExt($round_id, 2,'round_id', $client_details);
									if($game_ext != 'false'){
										$game_trans = $game_ext->game_trans_id;
										// $existing_bet = $this->findGameTransID($game_ext->game_trans_id);
										// $payout = $existing_bet->pay_amount+$data['data']['amount'];
										// $this->updateBetTransaction($game_ext->game_trans_id, $payout, $existing_bet->bet_amount-$payout, $existing_bet->win, $existing_bet->entry_id);
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
										$gamerecord = ProviderHelper::idGenerate($client_details->connection_name,1);
										// $game_trans = ProviderHelper::createGameTransaction($token_id, $game_details->game_id, 0, $data['data']['amount'], $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id, $round_id);
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
										// $gamerecord = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
										GameTransactionMDB::createGametransactionv2($gameTransactionData,$gamerecord,$client_details);
									}
									
								}
						 	    $game_transextension = ProviderHelper::idGenerate($client_details->connection_name,2);
								// $game_transextension = ProviderHelper::createGameTransExtV2($game_trans,$provider_trans_id, $round_id, $data['data']['amount'], $method); // method 5 freespin?
								$gameTransactionEXTData = array(
									"game_trans_id" => $game_trans,
									"provider_trans_id" => $provider_trans_id,
									"round_id" => $round_id,
									"amount" => $data['data']['amount'],
									"game_transaction_type"=> $method,
									// "provider_request" =>json_encode($data),
								);
								// $game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
								GameTransactionMDB::createGameTransactionExtv2($gameTransactionEXTData,$game_transextension,$client_details);

								# Check if data is already finished
								// $final_action = $string_to_obj->final_action;
								$final_action = $data['data']['final_action'];
								$endround = $final_action == 1 || $final_action == true ? true : false;

								$action = [
									"provider_name" => "evoplay",
									"endround" => $endround
								];

								try {
									$client_response = ClientRequestHelper::fundTransfer($client_details,ProviderHelper::amountToFloat($data['data']['amount']),$game_details->game_code,$game_details->game_name,$game_transextension,$game_trans,'credit',$action);
								    ProviderHelper::saveLog('8Provider Win Freespin CRID = '.$round_id, $this->provider_db_id, json_encode($data), $client_response);
								} catch (\Exception $e) {
									// $msg = array("status" => 'error',"message" => $e->getMessage());
									// ProviderHelper::updatecreateGameTransExt($game_transextension, 'FAILED', $msg, 'FAILED', $e->getMessage(), 'FAILED', 'FAILED');
									ProviderHelper::saveLog('8Provider Freespin - FATAL ERROR', $this->provider_db_id, json_encode($data), Helper::datesent());
									// return $msg;
									$msg = array("status" => 'error',"message" => $e->getMessage().' '.$e->getFile().' '.$e->getLine());
									$updateGameTransaction = ["win" => 2,'trans_status' => 5];
									GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);
									// $updateTransactionEXt = array(
									// 	"provider_request" =>json_encode($data),
									// 	"mw_response" => json_encode($msg),
									// 	"client_response" => json_encode($client_response),
									// );
									// GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
									$createGameTransactionLog = [
				                          "connection_name" => $client_details->connection_name,
				                          "column" =>[
				                              "game_trans_ext_id" => $game_transextension,
				                              "request" => json_encode($data),
				                              "response" => json_encode($msg),
				                              "log_type" => "provider_details",
				                              "transaction_detail" => "FAILED",
				                          ]
				                        ];
				                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
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

									// ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $response, $client_response->requestoclient, $client_response, $response);
									// $updateTransactionEXt = array(
									// 	"provider_request" =>json_encode($data),
									// 	"mw_response" => json_encode($response),
									// 	'mw_request' => json_encode($client_response->requestoclient),
									// 	"client_response" => json_encode($client_response),
									// );
									// GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
									$createGameTransactionLog = [
				                          "connection_name" => $client_details->connection_name,
				                          "column" =>[
				                              "game_trans_ext_id" => $game_transextension,
				                              "request" => json_encode($data),
				                              "response" => json_encode($response),
				                              "log_type" => "provider_details",
				                              "transaction_detail" => "SUCCESS",
				                          ]
				                        ];
				                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
									ProviderHelper::saveLog('8P FREESPIN', $this->provider_db_id, json_encode($data), $response);
							  		return $response;
								}else{
									$response = array(
										"status" => 'error',
										"error" => ["scope" => "user","no_refund" => 1,"message" => 'Client Down']
								 	);
									// $updateTransactionEXt = array(
									// 	"provider_request" =>json_encode($data),
									// 	"mw_response" => json_encode($msg),
									// 	'mw_request' => json_encode($client_response->requestoclient),
									// 	"client_response" => json_encode($client_response),
									// );
									// GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
									$createGameTransactionLog = [
				                          "connection_name" => $client_details->connection_name,
				                          "column" =>[
				                              "game_trans_ext_id" => $game_transextension,
				                              "request" => json_encode($client_response->requestoclient),
				                              "response" => json_encode($msg),
				                              "log_type" => "provider_details",
				                              "transaction_detail" => "ERROR",
				                          ]
				                        ];
				                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
									ProviderHelper::saveLog('8P FREESPIN', $this->provider_db_id, json_encode($data), $response);
							  		return $response;
								}


							}catch(\Exception $e){
								$msg = array(
									"status" => 'error',
									"message" => $e->getMessage().' '.$e->getFile().' '.$e->getLine(),
								);
								ProviderHelper::saveLog('8P ERROR FREESPIN - FATAL ERROR', $this->provider_db_id, json_encode($data), $e->getMessage().' '.$e->getFile().' '.$e->getLine());
								return $msg;
							}
					else:
							# EXISTING BET AND NORMAL WIN
							try {
								ProviderHelper::saveLog('8P win normal 1', $this->provider_db_id, json_encode($data), 'normal');
								$amount = $data['data']['amount'];
								$round_id = $data['data']['round_id'];

								# NEW ADDED 01-12-21
								if($data['data']['amount'] == 0){
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
								$game_transextension = ProviderHelper::idGenerate($client_details->connection_name,2);
								$gameTransactionEXTData = array(
									"game_trans_id" => $existing_bet->game_trans_id,
									"provider_trans_id" => $data['callback_id'],
									"round_id" => $round_id,
									"amount" => ProviderHelper::amountToFloat($data['data']['amount']),
									"game_transaction_type"=> 2,
									// "provider_request" =>json_encode($data),
								);
								// $game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
								GameTransactionMDB::createGameTransactionExtv2($gameTransactionEXTData,$game_transextension,$client_details);
								
								# Check if data is already finished
								// $final_action = $string_to_obj->final_action;
								$final_action = $data['data']['final_action'];
								$final_action = $data['data']['final_action'];
								$endround = $final_action == 1 || $final_action == true ? true : false;


								// $this->updateBetTransaction($existing_bet->game_trans_id, $amount, $income, $win, $entry_id);
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

								// $game_transextension = ProviderHelper::createGameTransExtV2($existing_bet->game_trans_id,$data['callback_id'], $round_id, $data['data']['amount'], 2);

								try {
									ProviderHelper::saveLog('Win Call Request', $this->provider_db_id, json_encode($data), 1);
									$client_response = ClientRequestHelper::fundTransfer_TG($client_details,ProviderHelper::amountToFloat($data['data']['amount']),$game_details->game_code,$game_details->game_name,$existing_bet->game_trans_id,'credit', false, $action_payload);
									# $client_response = ClientRequestHelper::fundTransfer($client_details,ProviderHelper::amountToFloat($data['data']['amount']),$game_details->game_code,$game_details->game_name,$game_transextension,$existing_bet->game_trans_id,'credit');
									// ProviderHelper::saveLog('Win Response Receive', $this->provider_db_id, json_encode($data), 1);
									// ProviderHelper::saveLog('8Provider gameWin CRID = '.$round_id, $this->provider_db_id, json_encode($data), $client_response);
								} catch (\Exception $e) {
									$msg = array("status" => 'error',"message" => $e->getMessage().' '.$e->getFile().' '.$e->getLine());
									// ProviderHelper::updatecreateGameTransExt($game_transextension, 'FAILED', $msg, 'FAILED', $e->getMessage(), 'FAILED', 'FAILED');
									// ProviderHelper::saveLog('8Provider gameWin - FATAL ERROR', $this->provider_db_id, json_encode($data), Helper::datesent());
									return $msg;
								}

								if(isset($client_response->fundtransferresponse->status->code) 
									&& $client_response->fundtransferresponse->status->code == "200"){
									ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance); #INHOUSE
									# $this->updateBetTransaction($existing_bet->game_trans_id, $amount, $income, $win, $entry_id);
									$response = array(
										'status' => 'ok',
										'data' => [
											'balance' => (string)$client_response->fundtransferresponse->balance,
											'currency' => $client_details->default_currency,
										],
									);
									$createGameTransactionLog = [
				                          "connection_name" => $client_details->connection_name,
				                          "column" =>[
				                              "game_trans_ext_id" => $game_transextension,
				                              "request" => json_encode($data),
				                              "response" => json_encode($response),
				                              "log_type" => "provider_details",
				                              "transaction_detail" => json_encode($client_response),
				                          ]
				                        ];
				                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
									# ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $response, $client_response->requestoclient, $client_response, $response);
								}else{
									$response = array(
										"status" => 'error',
										"error" => ["scope" => "user","no_refund" => 1,"message" => 'Client Down']
								 	);
									// $updateTransactionEXt = array(
									// 	"provider_request" =>json_encode($data),
									// 	"mw_response" => json_encode($msg),
									// 	'mw_request' => json_encode($client_response->requestoclient),
									// 	"client_response" => json_encode($client_response),
									// );
									// GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
									$createGameTransactionLog = [
				                          "connection_name" => $client_details->connection_name,
				                          "column" =>[
				                              "game_trans_ext_id" => $game_transextension,
				                              "request" => json_encode($client_response->requestoclient),
				                              "response" => json_encode($msg),
				                              "log_type" => "provider_details",
				                              "transaction_detail" => json_encode($client_response),
				                          ]
				                        ];
				                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
									ProviderHelper::saveLog('8P FREESPIN', $this->provider_db_id, json_encode($data), $response);
							  		return $response;
								}
								// ProviderHelper::saveLog('8Provider Win', $this->provider_db_id, json_encode($data), $response);
								return $response;

							}catch(\Exception $e){
								$msg = array(
									"status" => 'error',
									"error" => ["scope" => "user","no_refund" => 1,"message" =>  $e->getMessage().' '.$e->getLine()]
								);
								ProviderHelper::saveLog('8P ERROR WIN', $this->provider_db_id, json_encode($data), $e->getMessage());
								return $msg;
							}
				    endif;	
			else: 
					ProviderHelper::saveLog('8Provider LOG THE FP', $this->provider_db_id, json_encode($data), 1);
				    if(isset($string_to_obj->game->action) && $string_to_obj->game->action == 'freespin'):
				    	ProviderHelper::saveLog('8Provider freespin 1', $this->provider_db_id, json_encode($data), 1);
				  	    // $client_details = ProviderHelper::getClientDetails('token', $data['token']);
							try {
								ProviderHelper::saveLog('8P FREESPIN', $this->provider_db_id, json_encode($data), 'FREESPIN');
								$payout_reason = 'Free Spin';
						 		$win_or_lost = 1; // 0 Lost, 1 win, 3 draw, 4 refund, 5 processing
						 		$method = 2; // 1 bet, 2 win
						 	    $token_id = $client_details->token_id;
						 	    $provider_trans_id = $data['callback_id'];
						 	    $round_id = $data['data']['round_id'];
						
						 	    $bet_payout = 0; // Bet always 0 payout!
						 	    $income = '-'.$data['data']['amount']; // NEgative

								 $game_ext = GameTransactionMDB::findGameExt($round_id, 1,'round_id', $client_details);
								 if($game_ext != 'false'){
									 // $game_trans = $game_ext->game_trans_id;
									 // $existing_bet = $this->findGameTransID($game_ext->game_trans_id);
									 // $payout = $existing_bet->pay_amount+$data['data']['amount'];
									 // $this->updateBetTransaction($game_ext->game_trans_id, $payout, $existing_bet->bet_amount-$payout, $existing_bet->win, $existing_bet->entry_id);
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
									 // $game_ext = ProviderHelper::findGameExt($round_id, 2, 'round_id');
									 $game_ext = GameTransactionMDB::findGameExt($round_id, 2,'round_id', $client_details);
									 if($game_ext != 'false'){
										 $game_trans = $game_ext->game_trans_id;
										 // $existing_bet = $this->findGameTransID($game_ext->game_trans_id);
										 // $payout = $existing_bet->pay_amount+$data['data']['amount'];
										 // $this->updateBetTransaction($game_ext->game_trans_id, $payout, $existing_bet->bet_amount-$payout, $existing_bet->win, $existing_bet->entry_id);
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
										 // $game_trans = ProviderHelper::createGameTransaction($token_id, $game_details->game_id, 0, $data['data']['amount'], $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id, $round_id);
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
										 // $gamerecord = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
										 $gamerecord = ProviderHelper::idGenerate($client_details->connection_name,1);
			     						 GameTransactionMDB::createGametransactionV2($gameTransactionData,$gamerecord,$client_details); //create game_transaction
									 }
									 
								 }
						 	    
								$gameTransactionEXTData = array(
									"game_trans_id" => $game_trans,
									"provider_trans_id" => $provider_trans_id,
									"round_id" => $round_id,
									"amount" => $data['data']['amount'],
									"game_transaction_type"=> $method,
									// "provider_request" =>json_encode($data),
								);
								$game_transextension = ProviderHelper::idGenerate($client_details->connection_name,1);
								GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$game_transextension,$client_details);

								# Check if data is already finished
								// $final_action = $string_to_obj->final_action;
								$final_action = $data['data']['final_action'];
								$endround = $final_action == 1 || $final_action == true ? true : false;

								$action = [
									"provider_name" => "evoplay",
									"endround" => $endround
								];

								try {
									$client_response = ClientRequestHelper::fundTransfer($client_details,ProviderHelper::amountToFloat($data['data']['amount']),$game_details->game_code,$game_details->game_name,$game_transextension,$game_trans,'credit',$action);
								    ProviderHelper::saveLog('8Provider Win Freespin CRID = '.$round_id, $this->provider_db_id, json_encode($data), $client_response);
								} catch (\Exception $e) {
									$msg = array(
										"status" => 'error',
										"error" => ["scope" => "user","no_refund" => 1,"message" =>  $e->getMessage().' '.$e->getLine()]
									);
									$updateGameTransaction = ["win" => 2,'trans_status' => 5];
									GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);
									// $updateTransactionEXt = array(
									// 	"provider_request" =>json_encode($data),
									// 	"mw_response" => json_encode($msg),
									// 	"client_response" => json_encode($client_response),
									// );
									// GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
									$createGameTransactionLog = [
				                          "connection_name" => $client_details->connection_name,
				                          "column" =>[
				                              "game_trans_ext_id" => $game_transextension,
				                              "request" => json_encode($data),
				                              "response" => json_encode($msg),
				                              "log_type" => "provider_details",
				                              "transaction_detail" => "FAILED",
				                          ]
				                        ];
				                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
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

									// ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $response, $client_response->requestoclient, $client_response, $response);
									// $updateTransactionEXt = array(
									// 	"provider_request" =>json_encode($data),
									// 	"mw_response" => json_encode($response),
									// 	'mw_request' => json_encode($client_response->requestoclient),
									// 	"client_response" => json_encode($client_response),
									// );
									// GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
									$createGameTransactionLog = [
				                          "connection_name" => $client_details->connection_name,
				                          "column" =>[
				                              "game_trans_ext_id" => $game_transextension,
				                              "request" => json_encode($client_response->requestoclient),
				                              "response" => json_encode($response),
				                              "log_type" => "provider_details",
				                              "transaction_detail" => json_encode($client_response),
				                          ]
				                        ];
				                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
									ProviderHelper::saveLog('8P FREESPIN', $this->provider_db_id, json_encode($data), $response);
							  		return $response;
								}else{
									$response = array(
										"status" => 'error',
										"error" => ["scope" => "user","no_refund" => 1,"message" => 'Client Down']
								 	);
									// $updateTransactionEXt = array(
									// 	"provider_request" =>json_encode($data),
									// 	"mw_response" => json_encode($msg),
									// 	'mw_request' => json_encode($client_response->requestoclient),
									// 	"client_response" => json_encode($client_response),
									// );
									// GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
									$createGameTransactionLog = [
				                          "connection_name" => $client_details->connection_name,
				                          "column" =>[
				                              "game_trans_ext_id" => $game_transextension,
				                              "request" => json_encode($client_response->requestoclient),
				                              "response" => json_encode($response),
				                              "log_type" => "provider_details",
				                              "transaction_detail" => json_encode($client_response),
				                          ]
				                        ];
				                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
									ProviderHelper::saveLog('8P FREESPIN', $this->provider_db_id, json_encode($data), $response);
							  		return $response;
								}

							}catch(\Exception $e){
								$msg = array(
									"status" => 'error',
									// "message" => $e->getMessage(),
									"error" => ["scope" => "user","message" => $e->getMessage().' '.$e->getFile().' '.$e->getLine()]
								);
								ProviderHelper::saveLog('8P ERROR FREESPIN - FATAL ERROR', $this->provider_db_id, json_encode($data), $e->getMessage().' '.$e->getFile().' '.$e->getLine());
								return $msg;
							}
				    else:
					ProviderHelper::saveLog('8Provider win Player Balance', $this->provider_db_id, json_encode($data), 1);
					$response = array(
						'status' => 'ok',
						'data' => [
							'balance' => (string)$client_details->balance,
							'currency' => $client_details->default_currency,
						],
				 	);
					ProviderHelper::saveLog('8Provider'.$data['data']['round_id'], $this->provider_db_id, json_encode($data), $response);
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
			 	ProviderHelper::saveLog('8Provider win Player Balance', $this->provider_db_id, json_encode($data), 1);
				ProviderHelper::saveLog('8Provider'.$data['data']['round_id'], $this->provider_db_id, json_encode($data), $response);
				return $response;
		endif;

		}elseif($request->name == 'refund'){

			$client_details = ProviderHelper::getClientDetails('token', $data['token']);

			$string_to_obj = json_decode($data['data']['details']);
			$game_id = $string_to_obj->game->game_id;
			$game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $game_id);

			// $game_refund = ProviderHelper::findGameExt($data['callback_id'], 4, 'transaction_id'); // Find if this callback in game extension	
			$game_refund = GameTransactionMDB::findGameExt($data['callback_id'], 3,'transaction_id', $client_details);
			if($game_refund == 'false'): // NO REFUND WAS FOUND PROCESS IT!
			
			// $client_details = ProviderHelper::getClientDetails('token', $data['token']);
			// $player_details = $this->playerDetailsCall($client_details);
			// if ($player_details == 'false') {
			// 	$msg = array("status" => 'error', "message" => $e->getMessage());
			// 	ProviderHelper::saveLog('8Provider gameRefund - FATAL ERROR', $this->provider_db_id, json_encode($data), Helper::datesent());
			// 	return $msg;
			// }

			// $game_transaction_ext = ProviderHelper::findGameExt($data['data']['refund_round_id'], 1, 'round_id'); // Find GameEXT
			$game_transaction_ext = GameTransactionMDB::findGameExt($data['data']['refund_round_id'], 1,'round_id', $client_details);
			if($game_transaction_ext == 'false'):
				// $player_details = $this->playerDetailsCall($data['token']);
				// $client_details = ProviderHelper::getClientDetails('token', $data['token']);
				$response = array(
					'status' => 'ok',
					'data' => [
						'balance' => (string)$client_details->balance,
						// 'balance' => (string)$player_details->playerdetailsresponse->balance,
						'currency' => $client_details->default_currency,
					],
				);
				ProviderHelper::saveLog('8Provider'.$data['data']['refund_round_id'], $this->provider_db_id, json_encode($data), $response);
				return $response;
			endif;

			// $game_transaction_ext_refund = ProviderHelper::findGameExt($data['data']['refund_round_id'], 4, 'round_id'); // Find GameEXT
			$game_transaction_ext_refund = GameTransactionMDB::findGameExt($data['data']['refund_round_id'], 3,'round_id', $client_details);
			if($game_transaction_ext_refund != 'false'):
				// $player_details = $this->playerDetailsCall($data['token']);
				// $client_details = ProviderHelper::getClientDetails('token', $data['token']);
				$response = array(
					'status' => 'ok',
					'data' => [
						// 'balance' => (string)$player_details->playerdetailsresponse->balance,
						'balance' => (string)$client_details->balance,
						'currency' => $client_details->default_currency,
					],
				);
				ProviderHelper::saveLog('8Provider'.$data['data']['refund_round_id'], $this->provider_db_id, json_encode($data), $response);
				return $response;
			endif;


			// $existing_transaction = $this->findGameTransID($game_transaction_ext->game_trans_id);
			$existing_transaction = GameTransactionMDB::findGameTransactionDetails($game_transaction_ext->game_trans_id, 'game_transaction',false, $client_details);
			if($existing_transaction != 'false'): // IF BET WAS FOUND PROCESS IT!
				$transaction_type = $game_transaction_ext->game_transaction_type == 1 ? 'credit' : 'debit'; // 1 Bet
				// $client_details = ProviderHelper::getClientDetails('token', $data['token']);
				if($transaction_type == 'debit'):
					// $player_details = $this->playerDetailsCall($data['token']);
					// if($player_details->playerdetailsresponse->balance < $data['data']['amount']):
					if($client_details->balance < $data['data']['amount']):
						$msg = array(
							"status" => 'error',
							"error" => ["scope" => "user","no_refund" => 1,"message" => "Not enough money"]
						);
						return $msg;
					endif;
				endif;

				try {


					// $this->updateBetTransaction($existing_transaction->game_trans_id, $existing_transaction->pay_amount, $existing_transaction->income, 4, $existing_transaction->entry_id); // UPDATE BET TO REFUND!

					// $game_transextension = ProviderHelper::createGameTransExtV2($existing_transaction->game_trans_id,$data['callback_id'], $data['data']['refund_round_id'], $data['data']['amount'], 4);
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

					$action = [
						"provider_name" => "evoplay",
						"endround" => $endround
					];

					try {
						$client_response = ClientRequestHelper::fundTransfer($client_details,ProviderHelper::amountToFloat($data['data']['amount']),$game_details->game_code,$game_details->game_name,$game_transextension,$existing_transaction->game_trans_id, $transaction_type,$action);
						ProviderHelper::saveLog('8Provider Refund CRID '.$data['data']['refund_round_id'], $this->provider_db_id, json_encode($data), $client_response);
					} catch (\Exception $e) {
						$msg = array("status" => 'error',"message" => $e->getMessage().' '.$e->getFile().' '.$e->getLine());
						ProviderHelper::updatecreateGameTransExt($game_transextension, 'FAILED', $msg, 'FAILED', $e->getMessage().' '.$e->getFile().' '.$e->getLine(), 'FAILED', 'FAILED');
						ProviderHelper::saveLog('8Provider gameRefund - FATAL ERROR', $this->provider_db_id, json_encode($data), Helper::datesent());
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
						// $this->updateBetTransaction($existing_transaction->game_trans_id, $existing_transaction->pay_amount, $existing_transaction->income, 4, $existing_transaction->entry_id);
						// ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $response, $client_response->requestoclient, $client_response, $response);
						// ProviderHelper::saveLog('8P REFUND', $this->provider_db_id, json_encode($data), $response);
						// return $response;
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
					ProviderHelper::saveLog('8P ERROR REFUND', $this->provider_db_id, json_encode($data), $e->getMessage());
					return $msg;
				}
			else:
				// NO BET WAS FOUND DO NOTHING
				// $player_details = $this->playerDetailsCall($data['token']);
				// $client_details = ProviderHelper::getClientDetails('token', $data['token']);
				$response = array(
					'status' => 'ok',
					'data' => [
						'balance' => (string)$client_details->balance,
						// 'balance' => (string)$player_details->playerdetailsresponse->balance,
						'currency' => $client_details->default_currency,
					],
				);
				ProviderHelper::saveLog('8Provider'.$data['data']['refund_round_id'], $this->provider_db_id, json_encode($data), $response);
				return $response;
			endif;
			else:
				// NOTE IF CALLBACK WAS ALREADY PROCESS/DUPLICATE PROVIDER DONT NEED A ERROR RESPONSE! LEAVE IT AS IT IS!
				// $player_details = $this->playerDetailsCall($data['token']);
				// $client_details = ProviderHelper::getClientDetails('token', $data['token']);
				$response = array(
					'status' => 'ok',
					'data' => [
						'balance' => (string)$client_details->balance,
						// 'balance' => (string)$player_details->playerdetailsresponse->balance,
						'currency' => $client_details->default_currency,
					],
				);
				ProviderHelper::saveLog('8Provider'.$data['data']['refund_round_id'], $this->provider_db_id, json_encode($data), $response);
				return $response;
			endif;
		}
	}

	public function checkTransactionExist($identifier, $transaction_type){
		$query = DB::select('select `game_transaction_type` from game_transaction_ext where `provider_trans_id`  = "'.$identifier.'" AND `game_transaction_type` = "'.$transaction_type.'" LIMIT 1');
    	$data = count($query);
		return $data > 0 ? $query[0] : 'false';
	}

	public  function findGameTransaction($identifier, $type, $entry_type='') {

    	if ($type == 'transaction_id') {
		 	$where = 'where gt.provider_trans_id = "'.$identifier.'" AND gt.entry_id = '.$entry_type.'';
		}
		if ($type == 'game_transaction') {
		 	$where = 'where gt.game_trans_id = "'.$identifier.'"';
		}
		if ($type == 'round_id') {
			$where = 'where gt.round_id = "'.$identifier.'" AND gt.entry_id = '.$entry_type.'';
		}
	 	
	 	$filter = 'LIMIT 1';
    	$query = DB::select('select *, (select transaction_detail from game_transaction_ext where game_trans_id = gt.game_trans_id order by game_trans_id limit 1) as transaction_detail from game_transactions gt '.$where.' '.$filter.'');
    	$client_details = count($query);
		return $client_details > 0 ? $query[0] : 'false';
    }

	public function findGameTransID($game_trans_id){
		$query = DB::select('select `game_trans_id`,`token_id`, `provider_trans_id`, `round_id`, `bet_amount`, `win`, `pay_amount`, `income`, `entry_id` from game_transactions where `game_trans_id`  = '.$game_trans_id.' LIMIT 1');
    	$data = count($query);
		return $data > 0 ? $query[0] : 'false';
	}


	/**
	 * Create Game Extension Logs bet/Win/Refund
	 * @param [int] $[gametransaction_id] [<ID of the game transaction>]
	 * @param [json array] $[provider_request] [<Incoming Call>]
	 * @param [json array] $[mw_request] [<Outgoing Call>]
	 * @param [json array] $[mw_response] [<Incoming Response Call>]
	 * @param [json array] $[client_response] [<Incoming Response Call>]
	 * 
	 */
	public  function create8PTransactionExt($gametransaction_id,$provider_request,$mw_request,$mw_response,$client_response, $transaction_detail,$game_transaction_type, $amount=null, $provider_trans_id=null, $round_id=null){
		$gametransactionext = array(
			"game_trans_id" => $gametransaction_id,
			"provider_trans_id" => $provider_trans_id,
			"round_id" => $round_id,
			"amount" => $amount,
			"game_transaction_type"=>$game_transaction_type,
			"provider_request" => json_encode($provider_request),
			"mw_request"=>json_encode($mw_request),
			"mw_response" =>json_encode($mw_response),
			"client_response" =>json_encode($client_response),
			"transaction_detail" =>json_encode($transaction_detail),
		);
		$gamestransaction_ext_ID = DB::table("game_transaction_ext")->insertGetId($gametransactionext);
		return $gamestransaction_ext_ID;
	}

	public  function saveLog($method, $provider_id = 0, $request_data, $response_data) {
		$data = [
				"method_name" => $method,
				"provider_id" => $provider_id,
				"request_data" => json_encode(json_decode($request_data)),
				"response_data" => json_encode($response_data)
			];
		return DB::table('seamless_request_logs')->insertGetId($data);
		// return DB::table('debug')->insertGetId($data);
	}


	/**
	 * Find bet and update to win 
	 * @param [int] $[round_id] [<ID of the game transaction>]
	 * @param [int] $[pay_amount] [<amount to change>]
	 * @param [int] $[income] [<bet - payout>]
	 * @param [int] $[win] [<0 Lost, 1 win, 3 draw, 4 refund, 5 processing>]
	 * @param [int] $[entry_id] [<1 bet, 2 win>]
	 * 
	 */
	public  function updateBetTransaction($round_id, $pay_amount, $income, $win, $entry_id) {
   	    $update = DB::table('game_transactions')
                // ->where('round_id', $round_id)
   	  		    ->where('game_trans_id', $round_id)
                ->update(['pay_amount' => $pay_amount, 
	        		  'income' => $income, 
	        		  'win' => $win, 
	        		  'entry_id' => $entry_id,
	        		  'transaction_reason' => $this->updateReason($win),
	    		]);
		return ($update ? true : false);
	}


	public function updateBetTransactionWithBet($round_id, $pay_amount, $bet_amount, $income, $win, $entry_id)
	{
		DB::table('game_transactions')
			->where('game_trans_id', $round_id)
			->update([
				'pay_amount' => $pay_amount,
				'bet_amount' => $bet_amount,
				'income' => $income,
				'win' => $win,
				'entry_id' => $entry_id,
				'transaction_reason' => $this->updateReason($win),
			]);
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
