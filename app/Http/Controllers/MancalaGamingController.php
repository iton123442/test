<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Helpers\CallParameters;
use App\Helpers\ClientRequestHelper;
use App\Helpers\ProviderHelper;
use App\Helpers\Helper;
use App\Helpers\Game;
use App\Models\GameTransaction;
use App\Models\GameTransactionMDB;
use DB;

class MancalaGamingController extends Controller
{   
	public $client_api_key , $provider_db_id ;

	public function __construct(){
		$this->client_api_key = config("providerlinks.mancala.CLIENT_API_KEY");
		$this->provider_db_id = config("providerlinks.mancala.PROVIDER_ID");
	}

	public function getBalance(Request $request) {
		$json_data = $request->all();

		if(!CallParameters::check_keys($json_data, 'SessionId', 'Hash', 'ExtraData'))
		{
			$http_status = 200;
			$response = [
					"Error" =>  4,
					"message" => "Internal service error.",
				];
		}
		else
		{
			if ($this->_hashGenerator(['Balance/', $json_data['SessionId']]) !== $json_data["Hash"]) {
				$http_status = 200;
				$response = [
					"Error" =>  1,
					"message" => "Hash Mismatch.",
				];
			} else {
				$http_status = 200;
				$response = [
					"Error" =>  12,
					"message" => "User is not identified.",
				];

				$session_token = Helper::getSessionTokenBySessionId($json_data['SessionId']);
 	
				// Find the player and client details
				$client_details = ProviderHelper::getClientDetails('token', $session_token->player_token);
				if ($client_details != null) {
					$http_status = 200;
					$response = [
						"Error" => 0,
						"Balance" => ProviderHelper::amountToFloat($client_details->balance)
					];
				}
			}

		}
		Helper::errorDebug('mancala_balance', config("providerlinks.mancala.PROVIDER_ID"), json_encode($json_data), $response);

		return response()->json($response, $http_status);
	}

	public function debitProcess(Request $request){
        $json_data = $request->all();
		Helper::errorDebug('mancala_debit', config("providerlinks.mancala.PROVIDER_ID"), json_encode($json_data), '');
		if(!CallParameters::check_keys($json_data, 'Amount', 'SessionId', 'TransactionId', 'RoundId', 'Hash', 'ExtraData'))
		{
			$http_status = 200;
			$response = [
					"Error" =>  4,
					"message" => "Internal service error.",
				];
		}
		else
		{	
			// dd($this->_hashGenerator(['Credit/', $json_data['SessionId'], $json_data['TransactionId'], $json_data['RoundId'], $json_data['Amount']]));
			if ($this->_hashGenerator(['Credit/', $json_data['SessionId'], $json_data['TransactionId'], $json_data['RoundId'], $json_data['Amount']]) !== $json_data["Hash"]) {
				$http_status = 200;
				$response = [
					"Error" =>  1,
					"message" => "Hash Mismatch.",
				];
			} else {

				$http_status = 200;
				$response = [
					"Error" =>  12,
					"message" => "User is not identified.",
				];
				
				// Find the player and client details
				$session_token = Helper::getSessionTokenBySessionId($json_data['SessionId']);
				$client_details = ProviderHelper::getClientDetails('token', $session_token->player_token);
				if ($client_details != null) {
					
					try{
						ProviderHelper::idenpotencyTable($json_data['TransactionId']);
					}catch(\Exception $e){
						$response = [
							"Error" =>  0,
							"Balance" => ProviderHelper::amountToFloat($client_details->balance),
						];
						return $response;
					}

					$response = [
						"Error" =>  10100,
						"message" => "Server is not ready!",
					];

					$json_data['income'] = $json_data['Amount'];
					$json_data['roundid'] = $json_data['RoundId'];
					$json_data['transid'] = $json_data['TransactionId'];
					/*$game_details = Game::find($json_data["game_id"], $this->provider_db_id);*/
					$game_details = Helper::getInfoPlayerGameRound($session_token->player_token);

					$gameTransactionData = array(
			            "provider_trans_id" => $json_data['TransactionId'],
			            "token_id" => $client_details->token_id,
			            "game_id" => $game_details->game_id,
			            "round_id" => $json_data['RoundId'],
			            "bet_amount" => $json_data['Amount'],
			            "win" => 5,
			            "pay_amount" => 0,
			            "income" => 0,
			            "entry_id" => 1
			        );
			        
			        $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);

					$bet_game_transaction_ext = array(
						"game_trans_id" => $game_transaction_id,
						"provider_trans_id" => $json_data['TransactionId'],
						"round_id" => $json_data['RoundId'],
						"amount" => $json_data['Amount'],
						"game_transaction_type" => 1,
						"provider_request" => json_encode($json_data),
					);

					$game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($bet_game_transaction_ext, $client_details); 
					
					$fund_extra_data = [
	                    'provider_name' => $game_details->provider_name
	                ]; 

					// change $json_data['round_id'] to $game_transaction_id
					// ProviderHelper::updateGameTransactionStatus($game_transaction_id, 5, 5);
			        $client_response = ClientRequestHelper::fundTransfer($client_details, $json_data['Amount'], $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, 'debit', false, $fund_extra_data);
					
					if (isset($client_response->fundtransferresponse->status->code)) {
						
						switch ($client_response->fundtransferresponse->status->code) {
							case '200':
								ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
								$http_status = 200;
								$response = [
									"Error" => 0,
									"Balance" => ProviderHelper::amountToFloat($client_response->fundtransferresponse->balance)
								];
								
								$data_to_update = array(
			                        "mw_response" => json_encode($response)
			                    );

			                    GameTransactionMDB::updateGametransactionEXT($data_to_update, $game_trans_ext_id, $client_details);

								break;
							case '402':
								$http_status = 200;
								$response = [
									"Error" =>  10203,
									"message" => "Insufficient balance",
								];

								try{
			                        $data = array(
			                            "win"=> 2,
			                            "transaction_reason" => "FAILED Due to low balance or Client Server Timeout"
			                        );

			                        GameTransactionMDB::updateGametransaction($data, $game_transaction_id, $client_details);
			                        $data_to_update = array(
			                            "mw_response" => json_encode($response)
			                        );
			                        GameTransactionMDB::updateGametransactionEXT($data_to_update, $game_trans_ext_id, $client_details);
			                    }catch(\Exception $e){
			                        
			                    } 

								break;
						}


					}
						
	                return response()->json($response, $http_status);

				}
			}

		}

		Helper::saveLog('Mancala debit error_response', $this->provider_db_id, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);

    }

    public function creditProcess(Request $request){
        $json_data = $request->all();
        Helper::errorDebug('mancala_credit', config("providerlinks.mancala.PROVIDER_ID"), json_encode($json_data), '');
		$http_status = 200;
		if(!CallParameters::check_keys($json_data, 'Amount', 'SessionId', 'TransactionId', 'RoundId', 'Hash', 'ExtraData'))
		{
			$http_status = 200;
			$response = [
					"Error" =>  4,
					"message" => "Internal service error.",
				];
		}
		else
		{
			if ($this->_hashGenerator(['Debit/', $json_data['SessionId'], $json_data['TransactionId'], $json_data['RoundId'], $json_data['Amount']]) !== $json_data["Hash"]) {
				$http_status = 200;
				$response = [
					"Error" =>  1,
					"message" => "Hash Mismatch.",
				];
			} else {

				$http_status = 200;
				$response = [
					"Error" =>  12,
					"message" => "User is not identified.",
				];
				// Find the player and client details
				
				$session_token = Helper::getSessionTokenBySessionId($json_data['SessionId']);
				$client_details = ProviderHelper::getClientDetails('token', $session_token->player_token);
				if ($client_details != null) {
					
					try{
						ProviderHelper::idenpotencyTable($json_data['TransactionId']);
					}catch(\Exception $e){
						$response = [
							"Error" =>  0,
							"Balance" => ProviderHelper::amountToFloat($client_details->balance),
						];
						return $response;
					}

					if($json_data['Amount'] < 0) {
						$response = [
							"Error" =>  10201,
							"message" => "Warning value must not be less 0.",
						];
					}
					else
					{
						$win_or_lost = $json_data["Amount"] == 0 && $bet_transaction->pay_amount == 0 ? 0 : 1;
						$game_details = Helper::getInfoPlayerGameRound($session_token->player_token);
						
						$bet_transaction = GameTransactionMDB::getGameTransactionByTokenAndRoundId($session_token->player_token, $json_data['RoundId'], $client_details);

						$game_transaction = GameTransactionMDB::findGameTransactionDetails($bet_transaction->game_trans_id, 'game_transaction', false, $client_details);
						$client_details->connection_name = $game_transaction->connection_name;

						$winbBalance = $client_details->balance + $json_data["Amount"];
						
						ProviderHelper::_insertOrUpdate($client_details->token_id, $winbBalance); 
						
						$response = [
								"Error" => 0,
								"Balance" => ProviderHelper::amountToFloat($winbBalance)
							];
						
			            $update_game_transaction = array(
		                    "win" => 5,
		                    "pay_amount" => $bet_transaction->pay_amount + $json_data["Amount"],
		                    "income" => $bet_transaction->income - $json_data["Amount"],
		                    "entry_id" => $json_data["Amount"] == 0 && $bet_transaction->pay_amount == 0 ? 1 : 2,
		                );

			           	GameTransactionMDB::updateGametransaction($update_game_transaction, $bet_transaction->game_trans_id, $client_details);


		                $win_game_transaction_ext = array(
		                    "game_trans_id" => $bet_transaction->game_trans_id,
		                    "provider_trans_id" => $json_data["TransactionId"],
		                    "round_id" => $json_data["RoundId"],
		                    "amount" => $json_data["Amount"],
		                    "game_transaction_type"=> 2,
		                    "provider_request" =>json_encode($json_data),
		                    "mw_response" => json_encode($response)
		                );

		                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($win_game_transaction_ext, $client_details);

						$action_payload = [
			                "type" => "custom", #genreral,custom :D # REQUIRED!
			                "custom" => [
			                    "provider" => 'MancalaGaming',
			                    "game_trans_ext_id" => $game_trans_ext_id,
			                    "win_or_lost" => $win_or_lost,
			                    "client_connection_name" => $client_details->connection_name
			                ],
			                "provider" => [
			                    "provider_request" => $json_data, #R
			                    "provider_trans_id"=> $json_data['TransactionId'], #R
			                    "provider_round_id"=> $json_data['RoundId'], #R
			                    "provider_name"=> $game_details->provider_name
			                ],
			                "mwapi" => [
			                    "roundId"=>$bet_transaction->game_trans_id, #R
			                    "type"=>2, #R
			                    "game_id" => $game_details->game_id, #R
			                    "player_id" => $client_details->player_id, #R
			                    "mw_response" => $response, #R
			                ]
			            ];
			            $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$json_data["Amount"],$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',false,$action_payload);

					}
				}
			}

		}
		Helper::saveLog('Mancala credit error_response', $this->provider_db_id, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);

    }


    public function rollbackTransaction(Request $request) {
    	$json_data = $request->all();
    	Helper::errorDebug('mancala_rollback', config("providerlinks.mancala.PROVIDER_ID"), json_encode($json_data), '');
		$http_status = 200;
		if(!CallParameters::check_keys($json_data, 'Amount','SessionId', 'TransactionId', 'RefundTransactionId', 'RoundId', 'Hash', 'ExtraData')) {
			$response = [
					"Error" =>  10102,
					"message" => "Post data is invalid!",
				];
		}
		else
		{
			if ($this->_hashGenerator(['RefundId', $json_data['SessionId'], $json_data['TransactionId'], $json_data['RefundTransactionId'], $json_data['RoundId'], $json_data['Amount']]) !== $json_data["Hash"]) {

				$http_status = 200;
				$response = [
					"Error" =>  10105,
					"message" => "Authenticate fail!",
				];
			} else {

				$response = [
					"Error" =>  10204,
					"message" => "Account is not exist!",
				];
				// Find the player and client details
				$session_token = Helper::getSessionTokenBySessionId($json_data['SessionId']);
				$client_details = ProviderHelper::getClientDetails('token', $session_token->player_token);

				if ($client_details != null) {
					
					try{
						ProviderHelper::idenpotencyTable($json_data['RefundTransactionId']);
					}catch(\Exception $e){
						$response = [
							"Error" =>  0,
							"Balance" => ProviderHelper::amountToFloat($client_details->balance),
						];
						return $response;
					}

					// $game_transaction =  GameTransactionMDB::getGameTransactionDataByProviderTransactionIdAndEntryType($json_data["RefundTransactionId"], 1, $client_details);
					$game_transaction =  GameTransactionMDB::findGameTransactionDetails($json_data["TransactionId"], 'transaction_id', false, $client_details);

					$response = [
						"Error" =>  10210,
						"message" => "Target transaction id not found!",
					];
					
					if ($game_transaction != 'false') {

						if ($game_transaction->win == 2) {
							return response()->json($response, $http_status);
						}

						$game_details = Helper::getInfoPlayerGameRound($session_token->player_token);
						$client_details->connection_name = $game_transaction->connection_name;
						$update_game_transaction = array(
		                    "win" => 4,
		                    "pay_amount" => $game_transaction->bet_amount,
		                    "income" => 0,
		                    "entry_id" => 2
		                );

			           	GameTransactionMDB::updateGametransaction($update_game_transaction, $game_transaction->game_trans_id, $client_details);

			           	$refund_game_transaction_ext = array(
		                    "game_trans_id" => $game_transaction->game_trans_id,
		                    "provider_trans_id" => $json_data["TransactionId"],
		                    "round_id" => $json_data["RoundId"],
		                    "amount" => $game_transaction->bet_amount,
		                    "game_transaction_type"=> 3,
		                    "provider_request" =>json_encode($json_data),
		                );

			           	$game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($refund_game_transaction_ext, $client_details);

			           	$fund_extra_data = [
		                    'provider_name' => $game_details->provider_name
		                ];  

			           	$client_response = ClientRequestHelper::fundTransfer($client_details, $game_transaction->bet_amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction->game_trans_id, 'credit', "true", $fund_extra_data);
					
						if (isset($client_response->fundtransferresponse->status->code)) {
							
							switch ($client_response->fundtransferresponse->status->code) {
								case '200':
									/*ProviderHelper::updateGameTransactionFlowStatus($game_transaction->game_trans_id, 5);*/
									ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
									$http_status = 200;
									$response = [
										"Error" => 0,
										"balance" => ProviderHelper::amountToFloat($client_response->fundtransferresponse->balance)
									];

									$data_to_update = array(
				                        "mw_response" => json_encode($response)
				                    );

									GameTransactionMDB::updateGametransactionEXT($data_to_update, $game_trans_ext_id, $client_details);

									break;
							}
						}
					}
				}
			}

		}
		Helper::saveLog('Mancala rollback', $this->provider_db_id, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);
	}

	
	private function _hashGenerator($values = [])
	{
		array_push($values, config("providerlinks.mancala.API_KEY"));
	
		$string = "";
		foreach ($values as $value) {
			$string .= $value;
		}

		return md5($string);
	}
	
}
