<?php

namespace App\Http\Controllers;

// use App\Models\PlayerDetail;
// use App\Models\PlayerSessionToken;
use App\Helpers\Helper;
// use App\Helpers\GameTransaction;
// use App\Helpers\GameSubscription;
// use App\Helpers\GameRound;
// use App\Helpers\Game;
use App\Helpers\CallParameters;
// use App\Helpers\PlayerHelper;
// use App\Helpers\TokenHelper;
use App\Helpers\ProviderHelper;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransactionMDB; //NEW MULTI DB FUNCTION

// use App\Support\RouteParam;

use Illuminate\Http\Request;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;

use DB;

class SolidGamingController extends Controller
{
	public $prefix = 'SOLID_'; 
	public $brand_code;
    public function __construct(){
		$this->brand_code = config('providerlinks.solid.BRAND');
	}

	public function authPlayer(Request $request, $brand_code){
		$json_data = json_decode(file_get_contents("php://input"), true);
		
		$http_status = 400;
		$response = [
			"errorcode" =>  "BAD_REQUEST",
			"errormessage" => "The request was invalid.",
		];	
		if ($brand_code == $this->brand_code) {
			if(!CallParameters::check_keys($json_data, 'token')) {
				$http_status = 400;
				$response = [
					"errorcode" =>  "BAD_REQUEST",
					"errormessage" => "The request was invalid.",
				];	
			}
			else
			{
				$http_status = 404;
				$response = [
					"errorcode" =>  "INVALID_TOKEN",
					"errormessage" => "The provided token could not be verified/Token already authenticated",
				];
				$client_details = ProviderHelper::getClientDetails('token', $json_data['token']);
				if ($client_details) {
					$http_status = 200;
					$response = [
						"status" => "OK",
						"brand" => 'BETRNKMW',
						"playerid" => "$client_details->player_id",
						"currency" => $client_details->default_currency,
						"balance" => $client_details->balance,
						"testaccount" => ($client_details->test_player ? true : false),
						"wallettoken" => "",
						"country" => "",
						"affiliatecode" => "",
						"displayname" => $client_details->display_name,
					];
				}
			}
		}
		Helper::saveLog('solid_authentication', 2, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);

	}

	public function getPlayerDetails(Request $request, $brand_code){
		$json_data = json_decode(file_get_contents("php://input"), true);
		$http_status = 400;
		$response = [
			"errorcode" =>  "BAD_REQUEST",
			"errormessage" => "The request was invalid.",
		];	
		if ($brand_code == $this->brand_code) {
			if(!CallParameters::check_keys($json_data, 'playerid')) {
					$http_status = 400;
					$response = [
							"errorcode" =>  "BAD_REQUEST",
							"errormessage" => "The request was invalid.",
						];
			}
			else
			{
				$http_status = 404;
				$response = [
					"errorcode" =>  "PLAYER_NOT_FOUND",
					"errormessage" => "The provided playerid don’t exist.",
				];

				$client_details = ProviderHelper::getClientDetails('player_id', $json_data['playerid']);
				if ($client_details) {
					$http_status = 200;
					$response = [
						"status" => "OK",
						"brand" => 'BETRNKMW',
						"currency" => $client_details->default_currency,
						"testaccount" => ($client_details->test_player ? true : false),
						"country" => "",
						"affiliatecode" => "",
						"displayname" => $client_details->display_name,
					];
				}
			}
		}
		Helper::saveLog('solid_playerdetails', 2, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);

	}

	public function getBalance(Request $request, $brand_code){
		$json_data = json_decode(file_get_contents("php://input"), true);
		$http_status = 400;
		$response = [
			"errorcode" =>  "BAD_REQUEST",
			"errormessage" => "The request was invalid.",
		];	
		if ($brand_code == $this->brand_code) {
			if(!CallParameters::check_keys($json_data, 'playerid', 'gamecode', 'platform')) {
				$http_status = 400;
				$response = [
					"errorcode" =>  "BAD_REQUEST",
					"errormessage" => "The request was invalid.",
				];
			}
			else
			{
				$http_status = 404;
				$response = [
					"errorcode" =>  "PLAYER_NOT_FOUND",
					"errormessage" => "Player not found",
				];

				$client_details = ProviderHelper::getClientDetails('player_id', $json_data['playerid']);
				if ($client_details) {
					$http_status = 200;
					$response = [
						"status" => "OK",
						"currency" => $client_details->default_currency,
						"balance" => $client_details->balance,
					];
				}
			}
		}
		

		Helper::saveLog('solid_balance', 2, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);

	}

	public function debitProcess(Request $request, $brand_code) {
		$json_data = json_decode(file_get_contents("php://input"), true);
		$http_status = 400;
		$response = [
			"errorcode" =>  "BAD_REQUEST",
			"errormessage" => "The request was invalid.",
		];
		if ($brand_code == $this->brand_code) {
			if(!CallParameters::check_keys($json_data, 'playerid', 'roundid', 'gamecode', 'platform', 'transid', 'currency', 'amount', 'reason', 'roundended') ) {
				$http_status = 400;
				$response = [
					"errorcode" =>  "BAD_REQUEST",
					"errormessage" => "The request was invalid.",
				];
			}
			else
			{
				$http_status = 404;
				$response = [
					"errorcode" =>  "PLAYER_NOT_FOUND",
					"errormessage" => "Player not found",
				];
				$client_details = ProviderHelper::getClientDetails('player_id', $json_data['playerid']);

				if ($client_details) {
					try{
						ProviderHelper::idenpotencyTable($this->prefix.$json_data['transid']);
					} catch(\Exception $e) {
						$bet_transaction = GameTransactionMDB::findGameExt($json_data["transid"], 1,'transaction_id', $client_details);
			            if ($bet_transaction != 'false') {
			                if ($bet_transaction->mw_response == 'null') {
								$response = [
									"errorcode" =>  "SESSION_NOT_FOUND",
									"errormessage" => "Session not found or session already expired",
								];
			                }else {
			                	$http_status = 200;
			                    $response = json_decode($bet_transaction->mw_response);
			                }
			                return response()->json($response, $http_status); 
			            } else {
			                $response = [
								"errorcode" =>  "SESSION_NOT_FOUND",
								"errormessage" => "Session not found or session already expired",
							];
			            }
			            Helper::saveLog('solid_debit_duplicate', 2, file_get_contents("php://input"), $response);
			            return response()->json($response, $http_status); 
					}
					$game_details = Helper::getInfoPlayerGameRound($client_details->player_token);
					$http_status = 404;
					$response = [
						"errorcode" =>  "GAME_NOT_FOUND",
						"errormessage" => "Game not found",
					];
					if ($game_details) {
						$gameTransactionData = array(
				            "provider_trans_id" => $json_data["transid"],
				            "token_id" => $client_details->token_id,
				            "game_id" => $game_details->game_id,
				            "round_id" => $json_data["roundid"],
				            "bet_amount" => $json_data["amount"],
				            "win" => 5,
				            "pay_amount" => 0,
				            "income" => 0,
				            "entry_id" =>1,
				            "trans_status" =>1,
				        );
				        $game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
				        $gameTransactionEXTData = array(
				            "game_trans_id" => $game_trans_id,
				            "provider_trans_id" => $json_data["transid"],
				            "round_id" => $json_data["roundid"],
				            "amount" => $json_data["amount"],
				            "game_transaction_type"=> 1,
				            "provider_request" =>json_encode($json_data),
				        );
				        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
		                try {
		                	$client_response = ClientRequestHelper::fundTransfer($client_details, $json_data['amount'], $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_trans_id, 'debit');
		                } catch (\Exception $e) {
		                	$http_status = 400;
							$response = [
								"errorcode" =>  "SESSION_NOT_FOUND",
								"errormessage" => "Session not found or session already expired",
							];
				            $updateTransactionEXt = array(
				                "mw_response" => json_encode($response),
				                'mw_request' => json_encode("FAILED"),
				                'client_response' => json_encode("FAILED"),
				                "transaction_detail" => "FAILED",
				                "general_details" =>"FAILED"
				            );
				            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
				            $updateGameTransaction = [
				                "win" => 2,
				                'trans_status' => 5
				            ];
				            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
				            Helper::saveLog('solid_debit', 2, file_get_contents("php://input"), $response);
							return response()->json($response, $http_status);
		                }
		                if (isset($client_response->fundtransferresponse->status->code)) {
		                	ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
				            switch ($client_response->fundtransferresponse->status->code) {
				                case "200":
				                    $http_status = 200;
									$response = [
										"status" => "OK",
										"currency" => $client_details->default_currency,
										"balance" => $client_response->fundtransferresponse->balance,
									];
				                    $updateTransactionEXt = array(
				                        "mw_response" => json_encode($response),
				                        'mw_request' => json_encode($client_response->requestoclient),
				                        'client_response' => json_encode($client_response->fundtransferresponse),
				                        'transaction_detail' => 'success',
				                        'general_details' => 'success',
				                    );
				                    break;
				                default:
				                    $http_status = 402;
									$response = [
										"errorcode" =>  "NOT_SUFFICIENT_FUNDS",
										"errormessage" => "Not sufficient funds",
									];
				                    $updateTransactionEXt = array(
				                        "mw_response" => json_encode($response),
				                        'mw_request' => json_encode($client_response->requestoclient),
				                        'client_response' => json_encode($client_response->fundtransferresponse),
				                        'transaction_detail' => 'FAILED',
				                        'general_details' => 'FAILED',
				                    );
				                    $updateGameTransaction = [
				                        "win" => 2,
				                        'trans_status' => 5
				                    ];
				                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
				            }
				            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
				        }  
					}
				}
			}

		}
		Helper::saveLog('solid_debit', 2, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);

	}

	public function creditProcess(Request $request, $brand_code){
		$json_data = json_decode(file_get_contents("php://input"), true);
		$http_status = 400;
		$response = [
			"errorcode" =>  "BAD_REQUEST",
			"errormessage" => "The request was invalid.",
		];
		if ($brand_code == $this->brand_code) {
			if(!CallParameters::check_keys($json_data, 'playerid', 'roundid', 'gamecode', 'platform', 'transid', 'currency', 'amount', 'reason', 'roundended')) {
				$http_status = 404;
				$response = [
					"errorcode" =>  "BAD_REQUEST",
					"errormessage" => "The request was invalid.",
				];
			}
			else
			{
				$http_status = 404;
				$response = [
					"errorcode" =>  "PLAYER_NOT_FOUND",
					"errormessage" => "Player not found",
				];
				$client_details = ProviderHelper::getClientDetails('player_id', $json_data['playerid']);
				if ($client_details) {
					try{
						ProviderHelper::idenpotencyTable($this->prefix.$json_data['transid']);
					} catch(\Exception $e) {
						$bet_transaction = GameTransactionMDB::findGameExt($json_data["transid"], 2,'transaction_id', $client_details);
			            if ($bet_transaction != 'false') {
			                if ($bet_transaction->mw_response != 'null') {
			                	$http_status = 200;
			                    $response = json_decode($bet_transaction->mw_response);
			                    return response()->json($response, $http_status); 
			                }
			            } 
			            Helper::saveLog('solid_debit_duplicate_continue', 2, file_get_contents("php://input"), $response);
					}
					$game_details = Helper::getInfoPlayerGameRound($client_details->player_token);
					$http_status = 404;
					$response = [
						"errorcode" =>  "GAME_NOT_FOUND",
						"errormessage" => "Game not found",
					];
					if ($game_details) {
						$http_status = 404;
						$response = [
							"errorcode" =>  "ROUND_NOT_FOUND",
							"errormessage" => "Round not found",
						];
						$bet_transaction = GameTransactionMDB::findGameTransactionDetails($request["roundid"], 'round_id',false, $client_details);
						if ($bet_transaction != 'false') {
							$http_status = 404;
							$response = [
								"errorcode" =>  "ROUND_ENDED",
								"errormessage" => "Game round have already been closed",
							];
							if ($bet_transaction->trans_status != 2) {
								$amount = $json_data["amount"];
								if (isset($json_data["payoutreason"])) {
									if($json_data['payoutreason'] == 'FREEROUND_WIN') {
										$amount = $bet_transaction->pay_amount + $json_data["amount"];
									}
								}
								$client_details->connection_name = $bet_transaction->connection_name;
								$balance = $client_details->balance + $json_data["amount"];
								ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
						        $http_status = 200;
								$response = [
									"status" => "OK",
									"currency" => $client_details->default_currency,
									"balance" => $balance,
								];
						        $gameTransactionEXTData = array(
						            "game_trans_id" => $bet_transaction->game_trans_id,
						            "provider_trans_id" => $request["transid"],
						            "round_id" => $request["roundid"],
						            "amount" => $json_data["amount"],
						            "game_transaction_type"=> 2,
						            "provider_request" =>json_encode($json_data),
						            "mw_response" => json_encode($response),
						        );
						        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

						        $win = $amount > 0  ?  1 : 0;  /// 1win 0lost
						        $entry_id = $amount > 0  ?  2 : 1; 
						        $trans_status = 1;
						        if (isset($json_data["roundended"])) {
									if ($json_data["roundended"] == "true") {
										$trans_status = 2;
									}
								}
						        $updateGameTransaction = [
						            'win' => 5,
						            'pay_amount' => $amount,
						            'income' => $bet_transaction->bet_amount - $amount,
						            'entry_id' => $entry_id,
						            'trans_status' => $trans_status
						        ];
						        GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
						        $action_payload = [
									"type" => "custom", #genreral,custom :D # REQUIRED!
									"custom" => [
										"provider" => 'SG',
										"win_or_lost" => $win,
										"game_trans_ext_id" => $game_trans_ext_id,
										"game_trans_id" => $bet_transaction->game_trans_id,
										"client_connection_name" => $client_details->connection_name,
									],
									"provider" => [
										"provider_request" => $json_data,
										"provider_trans_id"=> $json_data['transid'],
										"provider_round_id"=> $json_data['roundid'],
									],
									"mwapi" => [
										"roundId"=> $bet_transaction->game_trans_id,
										"type"=> 2,
										"game_id" => $game_details->game_id,
										"player_id" => $client_details->player_id,
										"mw_response" => $response,
									]
								];
								ClientRequestHelper::fundTransfer_TG($client_details, $json_data['amount'], $game_details->game_code, $game_details->game_name, $bet_transaction->game_trans_id, 'credit', false, $action_payload);
							}
						}
					}
				}
			}

		}
		Helper::saveLog('solid_credit', 2, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);
	}

	public function debitAndCreditProcess(Request $request, $brand_code) 
	{
		$json_data = json_decode(file_get_contents("php://input"), true);

		if(!CallParameters::check_keys($json_data, 'playerid', 'roundid', 'gamecode', 'platform', 'transid', 'currency', 'betamount', 'winamount', 'roundended')) {

				$http_status = 400;
				$response = [
						"errorcode" =>  "BAD_REQUEST",
						"errormessage" => "The request was invalid.",
					];
		}
		else
		{
			$http_status = 404;
			$response = [
							"errorcode" =>  "PLAYER_NOT_FOUND",
							"errormessage" => "Player not found",
						];

			$client_details = ProviderHelper::getClientDetails('player_id', $json_data['playerid']);
			if ($client_details) {
				
				try{
						ProviderHelper::idenpotencyTable($this->prefix.$json_data['transid']);
				} catch(\Exception $e) {
					$bet_transaction = GameTransactionMDB::findGameExt($json_data["transid"], 1,'transaction_id', $client_details);
		            if ($bet_transaction != 'false' && $bet_transaction->mw_response != "null") {
		            	$http_status = 402;
		            	$response = json_decode($bet_transaction->mw_response);
		            	if ($bet_transaction->general_details == "success") {
		            		$win_transaction = GameTransactionMDB::findGameExt($json_data["transid"], 2,'transaction_id', $client_details);
		            		$http_status = 200;
		            		$response = json_decode($win_transaction->mw_response);
		            	}
		            } else {
		            	$http_status = 403;
		                $response = [
							"errorcode" =>  "SESSION_NOT_FOUND",
							"errormessage" => "Session not found or session already expired",
						];
		            }
		            Helper::saveLog('solid_debit_duplicate', 2, file_get_contents("php://input"), $response);
		            return response()->json($response, $http_status); 
				}
				$game_details = Helper::getInfoPlayerGameRound($client_details->player_token);
				$http_status = 404;
				$response = [
					"errorcode" =>  "GAME_NOT_FOUND",
					"errormessage" => "Game not found",
				];
				if ($game_details) {
					$gameTransactionData = array(
			            "provider_trans_id" => $json_data["transid"],
			            "token_id" => $client_details->token_id,
			            "game_id" => $game_details->game_id,
			            "round_id" => $json_data["roundid"],
			            "bet_amount" => $json_data["betamount"],
			            "win" => 5,
			            "pay_amount" => 0,
			            "income" => 0,
			            "entry_id" => 1,
			            "trans_status" => 1,
			        );
			        $game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
			        $gameTransactionEXTData = array(
			            "game_trans_id" => $game_trans_id,
			            "provider_trans_id" => $json_data["transid"],
			            "round_id" => $json_data["roundid"],
			            "amount" => $json_data["betamount"],
			            "game_transaction_type"=> 1,
			            "provider_request" =>json_encode($json_data),
			        );
			        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
	                try {
	                	$client_response = ClientRequestHelper::fundTransfer($client_details, $json_data['betamount'], $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_trans_id, 'debit');
	                } catch (\Exception $e) {
	                	$http_status = 403;
						$response = [
							"errorcode" =>  "SESSION_NOT_FOUND",
							"errormessage" => "Session not found or session already expired",
						];
			            $updateTransactionEXt = array(
			                "mw_response" => json_encode($response),
			                'mw_request' => json_encode("FAILED"),
			                'client_response' => json_encode("FAILED"),
			                "transaction_detail" => "FAILED",
			                "general_details" =>"FAILED"
			            );
			            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
			            $updateGameTransaction = [
			                "win" => 2,
			                'trans_status' => 5
			            ];
			            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
			            Helper::saveLog('solid_debit_credit_fatal', 2, file_get_contents("php://input"), $response);
						return response()->json($response, $http_status);
	                }
	                if (isset($client_response->fundtransferresponse->status->code)) {
	                	ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
			            switch ($client_response->fundtransferresponse->status->code) {
			                case "200":
			                    $http_status = 200;
								$response = [
									"status" => "OK",
									"currency" => $client_details->default_currency,
									"balance" => $client_response->fundtransferresponse->balance,
								];
			                    $updateTransactionEXt = array(
			                        "mw_response" => json_encode($response),
			                        'mw_request' => json_encode($client_response->requestoclient),
			                        'client_response' => json_encode($client_response->fundtransferresponse),
			                        'transaction_detail' => 'success',
			                        'general_details' => 'success',
			                    );
			                    GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
			                    break;
			                default:
			                    $http_status = 402;
								$response = [
									"errorcode" =>  "NOT_SUFFICIENT_FUNDS",
									"errormessage" => "Not sufficient funds",
								];
			                    $updateTransactionEXt = array(
			                        "mw_response" => json_encode($response),
			                        'mw_request' => json_encode($client_response->requestoclient),
			                        'client_response' => json_encode($client_response->fundtransferresponse),
			                        'transaction_detail' => 'FAILED',
			                        'general_details' => 'FAILED',
			                    );
			                    GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
			                    $updateGameTransaction = [
			                        "win" => 2,
			                        'trans_status' => 5
			                    ];
			                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
			                    Helper::saveLog('solid_debit_credit_funds', 2, file_get_contents("php://input"), $response);
								return response()->json($response, $http_status);
			            }

			            // win process
						$balance = $client_response->fundtransferresponse->balance + $json_data["winamount"];
						ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
				        $http_status = 200;
						$response = [
							"status" => "OK",
							"currency" => $client_details->default_currency,
							"balance" => $balance,
						];
				        $gameTransactionEXTData = array(
				            "game_trans_id" => $game_trans_id,
				            "provider_trans_id" => $request["transid"],
				            "round_id" => $request["roundid"],
				            "amount" => $json_data["winamount"],
				            "game_transaction_type"=> 2,
				            "provider_request" =>json_encode($json_data),
				            "mw_response" => json_encode($response),
				            'transaction_detail' => 'success',
			                'general_details' => 'success',
				        );
				        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

				        $win = $json_data["winamount"] > 0  ?  1 : 0;  /// 1win 0lost
				        $entry_id = $json_data["winamount"] > 0  ?  2 : 1; 
				        $trans_status = 1;
				        if (isset($json_data["roundended"])) {
							if ($json_data["roundended"] == "true") {
								$trans_status = 2;
							}
						}
				        $updateGameTransaction = [
				            'win' => 5,
				            'pay_amount' => $json_data["winamount"],
				            'income' => $json_data["betamount"] - $json_data["winamount"],
				            'entry_id' => $entry_id,
				            'trans_status' => $trans_status
				        ];
				        GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
				        $action_payload = [
							"type" => "custom", #genreral,custom :D # REQUIRED!
							"custom" => [
								"provider" => 'SG',
								"win_or_lost" => $win,
								"game_trans_ext_id" => $game_trans_ext_id,
								"game_trans_id" => $game_trans_id,
								"client_connection_name" => $client_details->connection_name,
							],
							"provider" => [
								"provider_request" => $json_data,
								"provider_trans_id"=> $json_data['transid'],
								"provider_round_id"=> $json_data['roundid'],
							],
							"mwapi" => [
								"roundId"=> $game_trans_id,
								"type"=> 2,
								"game_id" => $game_details->game_id,
								"player_id" => $client_details->player_id,
								"mw_response" => $response,
							]
						];
						ClientRequestHelper::fundTransfer_TG($client_details, $json_data['winamount'], $game_details->game_code, $game_details->game_name,  $game_trans_id, 'credit', false, $action_payload);
			        }
				}
			}
		}
		
		Helper::saveLog('solid_debitandcredit', 2, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);
	}

	public function rollBackTransaction(Request $request, $brand_code) 
	{
		$json_data = json_decode(file_get_contents("php://input"), true);
		$http_status = 400;
		$response = [
			"errorcode" =>  "BAD_REQUEST",
			"errormessage" => "The request was invalid.",
		];
		if ($brand_code == $this->brand_code) {

			if(!CallParameters::check_keys($json_data, 'playerid', 'roundid')) {
				$http_status = 400;
				$response = [
					"errorcode" =>  "BAD_REQUEST",
					"errormessage" => "The request was invalid.",
				];
			}
			else
			{
				$http_status = 404;
				$response = [
					"errorcode" =>  "PLAYER_NOT_FOUND",
					"errormessage" => "The provided playerid don’t exist.",
				];

				$client_details = ProviderHelper::getClientDetails('player_id', $json_data['playerid']);
				if ($client_details) {
					try{
						ProviderHelper::idenpotencyTable($this->prefix.$json_data['roundid']."-REFUND");
					} catch(\Exception $e) {
						$bet_transaction = GameTransactionMDB::findGameExt($json_data["roundid"], 3,'transaction_id', $client_details);
			            if ($bet_transaction != 'false') {
			                if ($bet_transaction->mw_response != 'null') {
			                	$http_status = 200;
			                    $response = json_decode($bet_transaction->mw_response);
			                    return response($response,200)->header('Content-Type', 'application/json');
			                }
			            } 
			            Helper::saveLog('solid_debit_duplicate_continue', 2, file_get_contents("php://input"), $response);
			            return 1;
					}
					$game_details = Helper::getInfoPlayerGameRound($client_details->player_token);
					$http_status = 404;
					$response = [
						"errorcode" =>  "GAME_NOT_FOUND",
						"errormessage" => "Game not found",
					];
					if ($game_details) {
						$http_status = 404;
						$response = [
							"errorcode" =>  "ROUND_NOT_FOUND",
							"errormessage" => "Round not found",
						];

						if (isset($json_data["originaltransid"])) {
							$http_status = 404;
							$response = [
								"errorcode" =>  "ROUND_NOT_FOUND",
								"errormessage" => "Round not found",
							];
							$bet_transaction = GameTransactionMDB::findGameTransactionDetails($json_data["originaltransid"], 'transaction_id',false, $client_details);
							if ($bet_transaction != 'false') {
								$http_status = 404;
								$response = [
									"errorcode" =>  "ROUND_ENDED",
									"errormessage" => "Game round have already been closed",
								];
								if ($bet_transaction->trans_status != 2) {
									
									$client_details->connection_name = $bet_transaction->connection_name;
									$balance = $client_details->balance + $bet_transaction->bet_amount;
									ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
							        $http_status = 200;
									$response = [
										"status" => "OK",
										"currency" => $client_details->default_currency,
										"balance" => $balance,
									];
							        $gameTransactionEXTData = array(
							            "game_trans_id" => $bet_transaction->game_trans_id,
							            "provider_trans_id" => $json_data["roundid"],
							            "round_id" => $json_data["originaltransid"],
							            "amount" => $bet_transaction->bet_amount,
							            "game_transaction_type"=> 3,
							            "provider_request" =>json_encode($json_data),
							            "mw_response" => json_encode($response),
							        );
							        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

							        $win = 4; /// 1win 0lost
							        $trans_status = 1;
							        if (isset($json_data["roundended"])) {
										if ($json_data["roundended"] == "true") {
											$trans_status = 2;
										}
									}
							        $updateGameTransaction = [
							            'win' => 5,
							            'pay_amount' => $bet_transaction->bet_amount,
							            'income' => 0,
							            'entry_id' => 2,
							            'trans_status' => $trans_status
							        ];
							        GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
							        $action_payload = [
										"type" => "custom", #genreral,custom :D # REQUIRED!
										"custom" => [
											"provider" => 'SG',
											"win_or_lost" => $win,
											"game_trans_ext_id" => $game_trans_ext_id,
											"game_trans_id" => $bet_transaction->game_trans_id,
											"client_connection_name" => $client_details->connection_name,
										],
										"provider" => [
											"provider_request" => $json_data,
											"provider_trans_id"=> $json_data['roundid'],
											"provider_round_id"=> $json_data['originaltransid'],
										],
										"mwapi" => [
											"roundId"=> $bet_transaction->game_trans_id,
											"type"=> 2,
											"game_id" => $game_details->game_id,
											"player_id" => $client_details->player_id,
											"mw_response" => $response,
										]
									];
									ClientRequestHelper::fundTransfer_TG($client_details, $bet_transaction->bet_amount , $game_details->game_code, $game_details->game_name, $bet_transaction->game_trans_id, 'credit', 'true', $action_payload);
								}
							}
						} else 
						{
							$http_status = 404;
							$response = [
								"errorcode" =>  "ROUND_NOT_FOUND",
								"errormessage" => "Round not found",
							];
							$bet_transaction = GameTransactionMDB::findGameTransactionDetails($json_data["roundid"], 'round_id',false, $client_details);
						
							if ($bet_transaction != 'false' ) {
								$client_details->connection_name = $bet_transaction->connection_name;// change connection 
								$http_status = 404;
								$response = [
									"errorcode" =>  "ROUND_ENDED",
									"errormessage" => "Game round have already been closed",
								];
								if ($bet_transaction->trans_status != 2) {
									$getDebitDetails = $this->getTransactionDebitExtension( $bet_transaction->game_trans_id, false, 'game_trans_id', $client_details);
									if ($getDebitDetails != 'false') {

										$betamount_to_rollback_total = 0;
										for ($i=0; $i < count($getDebitDetails) ; $i++) { 
											$betamount_to_rollback_total += $getDebitDetails[$i]->amount;
										}
										

										$balance = $client_details->balance + $betamount_to_rollback_total;
										ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
								        $http_status = 200;
										$response = [
											"status" => "OK",
											"currency" => $client_details->default_currency,
											"balance" => $balance,
										];
								        $gameTransactionEXTData = array(
								            "game_trans_id" => $bet_transaction->game_trans_id,
								            "provider_trans_id" => $json_data["roundid"],
								            "round_id" => $json_data["roundid"],
								            "amount" => $betamount_to_rollback_total,
								            "game_transaction_type"=> 3,
								            "provider_request" =>json_encode($json_data),
								            "mw_response" => json_encode($response),
								            'transaction_detail' => 'success',
				                        	'general_details' => 'success',
								        );
								        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

								        $win = 4; /// 1win 0lost
								        $trans_status = 1;
								        if (isset($json_data["roundended"])) {
											if ($json_data["roundended"] == "true") {
												$trans_status = 2;
											}
										}
								        $updateGameTransaction = [
								            'win' => 5,
								            'pay_amount' => $betamount_to_rollback_total,
								            'income' => $bet_transaction->bet_amount - $betamount_to_rollback_total,
								            'entry_id' => 2,
								            'trans_status' => $trans_status
								        ];
								        GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
								        $action_payload = [
											"type" => "custom", #genreral,custom :D # REQUIRED!
											"custom" => [
												"provider" => 'SG',
												"win_or_lost" => $win,
												"game_trans_ext_id" => $game_trans_ext_id,
												"game_trans_id" => $bet_transaction->game_trans_id,
												"client_connection_name" => $client_details->connection_name,
											],
											"provider" => [
												"provider_request" => $json_data,
												"provider_trans_id"=> $json_data['roundid'],
												"provider_round_id"=> $json_data['roundid'],
											],
											"mwapi" => [
												"roundId"=> $bet_transaction->game_trans_id,
												"type"=> 2,
												"game_id" => $game_details->game_id,
												"player_id" => $client_details->player_id,
												"mw_response" => $response,
											]
										];
										ClientRequestHelper::fundTransfer_TG($client_details, $bet_transaction->bet_amount , $game_details->game_code, $game_details->game_name, $bet_transaction->game_trans_id, 'credit', 'true', $action_payload);

									}
								}
							}
						}

					}
				}
			}
		}

		Helper::saveLog('solid_rollback', 2, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);

	}

	public function endPlayerRound(Request $request, $brand_code) 
	{
		$json_data = json_decode(file_get_contents("php://input"), true);
		$http_status = 400;
		$response = [
			"errorcode" =>  "BAD_REQUEST",
			"errormessage" => "The request was invalid.",
		];
		if ($brand_code == $this->brand_code) {
			$http_status = 404;
			$response = [
				"errorcode" =>  "PLAYER_NOT_FOUND",
				"errormessage" => "The provided playerid don’t exist.",
			];
			$client_details = ProviderHelper::getClientDetails('player_id', $json_data['playerid']);
			if ($client_details/* && $player_details != NULL*/) {
				$http_status = 404;
				$response = [
					"errorcode" =>  "ROUND_NOT_FOUND",
					"errormessage" => "Round not found",
				];
				$bet_transaction = GameTransactionMDB::findGameTransactionDetails($request["roundid"], 'round_id',false, $client_details);
				if ($bet_transaction != 'false') {
					$client_details->connection_name = $bet_transaction->connection_name;
					if ($bet_transaction->trans_status == 2) {
						$http_status = 400;
						$response = [
							"errorcode" =>  "ROUND_ENDED",
							"errormessage" => "Game round have already been closed",
						];
					} else {
				        $http_status = 200;
						$response = [
							"status" => "OK",
							"currency" => $client_details->default_currency,
							"balance" => $client_details->balance,
						];
						$updateGameTransaction = [
				            'trans_status' => 2
				        ];
				        GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
					}
				}
			}
		}
		Helper::saveLog('solid_endplayer', 2, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);

	}

	/**
     * checkGameTransactionExtenstion
     *
     * @param  client_details = [client_details]{object}
     * @param  provider_identifier = [unique_transaction_provider]{string}
     * @param  game_transaction_type = [false], [1], [2],[3]{int}
     * @param  type = [transaction_id], [round_id],[game_transaction_ext_id],[game_trans_id]{string}
     * @return [details]{object},[false]
     */
    public  static function getTransactionDebitExtension($provider_identifier, $game_transaction_type=false, $type,$client_details)
    {
        $game_trans_type = '';
        if($game_transaction_type != false){
            $game_trans_type = "and gte.game_transaction_type = ". $game_transaction_type;
        }
        
        if ($type == 'game_trans_id') {
            $where = 'where gte.game_trans_id = "' . $provider_identifier . '" AND gte.game_transaction_type = 1 AND gte.general_details != "FAILED"   ';
        }
        try {
            $connection_name = $client_details->connection_name;
            $details = [];
            $connection = config("serverlist.server_list.".$client_details->connection_name.".connection_name");
            $status = GameTransactionMDB::checkDBConnection($connection);
            if ( ($connection != null) && $status) {
                $connection = config("serverlist.server_list.".$client_details->connection_name);
                $details = DB::connection($connection["connection_name"])->select('select * from `'.$connection['db_list'][0].'`.`game_transaction_ext` as gte ' . $where . '');
            }
            if ( !(count($details) > 0) )  {
                $connection_list = config("serverlist.server_list");
                foreach($connection_list as $key => $connection){
                    $status = GameTransactionMDB::checkDBConnection($connection["connection_name"]);
                    if($status && $connection_name != $connection["connection_name"]){
                        $data = DB::connection( $connection["connection_name"] )->select('select * from `'.$connection['db_list'][0].'`.`game_transaction_ext` as gte ' . $where . '');
                        if ( count($data) > 0  ) {
                            $connection_name = $key;// key is the client connection_name
                            $details = $data;
                            break;
                        }
                    }
                }
            }

            $count = count($details);
            if ($count > 0) {
                //apend on the details the connection which mean to rewrite the client_details
                $details[0]->connection_name = $connection_name;
            }
            return $count > 0 ? $details : 'false';
        } catch (\Exception $e) {
            return 'false';
        }

    }


}
