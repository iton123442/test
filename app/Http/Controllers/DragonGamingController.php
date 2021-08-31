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

class DragonGamingController extends Controller
{   

	public $client_api_key , $provider_db_id ;

	public function __construct(){
		$this->client_api_key = config("providerlinks.dragongaming.CLIENT_API_KEY");
		$this->provider_db_id = config("providerlinks.dragongaming.PROVIDER_ID");
	}

	public function getSession(Request $request) {
		$json_data = json_decode(file_get_contents("php://input"), true);

		if(!CallParameters::check_keys($json_data, 'token'))
		{
			$http_status = 200;
			$response = [
					"status" => 0, 
					"error_id" => 101,
					"error_code" => "INVALID_PARAMETERS",
					"error_message" => "Invalid Parameters"
				];
		}
		else
		{
			$http_status = 200;
			$response = [
				"status" => 0, 
				"error_id" => 102,
				"error_code" => "ACCOUNT_DOESNT_EXIST",
				"error_message" => "Account doesnt exist"
			];

			// Find the player and client details
			$client_details = ProviderHelper::getClientDetails('token', $json_data['token']);
			
			if ($client_details != null) {
				$http_status = 200;
				$response = [
					"status" => 1,
					"account_id" => $client_details->player_id,
					"username" => $client_details->username,
					"country" => $client_details->country_code,
					"token" => $client_details->player_token,
					"balance" => $this->_toPennies($client_details->balance),
					"currency" => $client_details->default_currency
				];
			}

		}

		return response()->json($response, $http_status);
	}

	public function getBalance(Request $request) {
		$json_data = json_decode(file_get_contents("php://input"), true);

		if(!CallParameters::check_keys($json_data, 'token'))
		{
			$http_status = 200;
			$response = [
					"status" => 0, 
					"error_id" => 101,
					"error_code" => "INVALID_PARAMETERS",
					"error_message" => "Invalid Parameters"
				];
		}
		else
		{
			$http_status = 200;
			$response = [
				"status" => 0, 
				"error_id" => 102,
				"error_code" => "ACCOUNT_DOESNT_EXIST",
				"error_message" => "Account doesnt exist"
			];

			// Find the player and client details
			$client_details = ProviderHelper::getClientDetails('player_id', $json_data['account_id']);

			if ($client_details != null) {
				$http_status = 200;
				$response = [
					"status" => 1,
					"account_id" => $client_details->player_id,
					"country" => $client_details->country_code,
					"token" => $client_details->player_token,
					"balance" => $this->_toPennies($client_details->balance),
					"currency" => $client_details->default_currency,
					"bonus_amount" => "0"
				];
			}
		}

		return response()->json($response, $http_status);
	}

	public function debitProcess(Request $request){

        $json_data = json_decode(file_get_contents("php://input"), true);
	
		if(!CallParameters::check_keys($json_data, 'token', 'account_id', 'amount', 'amount_type', 'currency', 'game_id', 'transaction_id', 'round_id', 'game_type', 'game_name', 'note'))
		{
			$http_status = 200;
			$response = [
					"status" => 0, 
					"error_id" => 101,
					"error_code" => "INVALID_PARAMETERS",
					"error_message" => "Invalid Parameters"
				];
		}
		else
		{

			$http_status = 200;
			$response = [
				"status" => 0, 
				"error_id" => 102,
				"error_code" => "ACCOUNT_DOESNT_EXIST",
				"error_message" => "Account doesnt exist"
			];

			// Find the player and client details
			$client_details = ProviderHelper::getClientDetails('player_id', $json_data['account_id']);
			
			if ($client_details != null) {
				if($json_data['game_id'] != 1171) {
					try{
						ProviderHelper::idenpotencyTable($json_data['round_id']);
					}catch(\Exception $e){
						$http_status = 200;
						$response = [
							"status" => 0, 
							"error_id" => 103,
							"error_code" => "ROUND_EXISTS",
							"error_message" => "Round exists"
						];

						return response()->json($response, $http_status);
					}
				}
				
				$http_status = 200;
				$response = [
					"status" => 0, 
					"error_id" => 104,
					"error_code" => "SERVER_NOT_READY",
					"error_message" => "Server is not ready"
				];

				$json_data['income'] = $this->_toDollars($json_data['amount']);
				$json_data['roundid'] = $json_data['round_id'];
				$json_data['transid'] = $json_data['transaction_id'];

				$game_details = Game::find($json_data["game_id"], $this->provider_db_id);
				
				$gameTransactionData = array(
		            "provider_trans_id" => $json_data['transaction_id'],
		            "token_id" => $client_details->token_id,
		            "game_id" => $game_details->game_id,
		            "round_id" => $json_data['round_id'],
		            "bet_amount" => $this->_toDollars($json_data['amount']),
		            "win" => 5,
		            "pay_amount" => 0,
		            "income" => 0,
		            "entry_id" => 1
		        );

		        $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);

		        $bet_game_transaction_ext = array(
					"game_trans_id" => $game_transaction_id,
					"provider_trans_id" => $json_data['transaction_id'],
					"round_id" => $json_data['round_id'],
					"amount" => $this->_toDollars($json_data['amount']),
					"game_transaction_type" => 1,
					"provider_request" => json_encode($json_data),
				);

		        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($bet_game_transaction_ext, $client_details); 

		        $fund_extra_data = [
                    'provider_name' => $game_details->provider_name
                ]; 


                $client_response = ClientRequestHelper::fundTransfer($client_details, $this->_toDollars($json_data['amount']), $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, 'debit', false, $fund_extra_data);

				if (isset($client_response->fundtransferresponse->status->code)) {
					/*ProviderHelper::updateGameTransactionFlowStatus($game_transaction_id, 1);*/
					switch ($client_response->fundtransferresponse->status->code) {
						case '200':
							ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
							$http_status = 200;
							$response = [
								"status" => 1,
								"account_id" => $client_details->player_id,
								"country" => $client_details->country_code,
								"token" => $client_details->player_token,
								"balance" => $this->_toPennies($client_response->fundtransferresponse->balance),
								"currency" => $client_details->default_currency,
								"transaction_id" => $json_data['transaction_id'],
								"bonus_amount" => "0"
							];

							$data_to_update = array(
		                        "mw_response" => json_encode($response)
		                    );

		                    GameTransactionMDB::updateGametransactionEXT($data_to_update, $game_trans_ext_id, $client_details);

							break;
						case '402':
							$http_status = 200;
							$response = [
								"status" => 0, 
								"error_id" => 105,
								"error_code" => "INSUFFICIENT_FUNDS",
								"error_message" => "Player balance is insufficient."
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
					
			}

		}

		return response()->json($response, $http_status);

    }

    public function creditProcess(Request $request){
        $json_data = json_decode(file_get_contents("php://input"), true);
		
		$http_status = 200;
		if(!CallParameters::check_keys($json_data, 'token', 'account_id', 'amount', 'amount_type', 'currency', 'game_id', 'transaction_id', 'round_id', 'game_type', 'game_name', 'note'))
		{
			$http_status = 200;
			$response = [
					"status" => 0, 
					"error_id" => 101,
					"error_code" => "INVALID_PARAMETERS",
					"error_message" => "Invalid Parameters"
				];
		}
		else
		{

			$http_status = 200;
			$response = [
				"status" => 0, 
				"error_id" => 102,
				"error_code" => "ACCOUNT_DOESNT_EXIST",
				"error_message" => "Account doesnt exist"
			];

			// Find the player and client details
			$client_details = ProviderHelper::getClientDetails('player_id', $json_data['account_id']);

			if ($client_details != null) {
				
				try{
					ProviderHelper::idenpotencyTable($json_data['transaction_id']);
				}catch(\Exception $e){
					$http_status = 200;
					$response = [
						"status" => 0, 
						"error_id" => 103,
						"error_code" => "ROUND_EXISTS",
						"error_message" => "Round exists"
					];

					return response()->json($response, $http_status);
				}

				if($this->_toDollars($json_data['amount']) < 0) {
					$http_status = 200;
					$response = [
						"status" => 0, 
						"error_id" => 106,
						"error_code" => "INVALID_AMOUNT",
						"error_message" => "Amount is invalid."
					];
				}
				else
				{
					
					$game_details = Game::find($json_data["game_id"], $this->provider_db_id);
					
					$bet_transaction = GameTransactionMDB::getGameTransactionByTokenAndRoundId($json_data['token'], (string) $json_data['round_id'], $client_details);
					
					$winbBalance = $client_details->balance + $this->_toDollars($json_data["amount"]);

					ProviderHelper::_insertOrUpdate($client_details->token_id, $winbBalance); 

					$response = [
								"status" => 1,
								"account_id" => $client_details->player_id,
								"country" => $client_details->country_code,
								"token" => $client_details->player_token,
								"balance" => $this->_toPennies($winbBalance),
								"currency" => $client_details->default_currency,
								"transaction_id" => 'C'.'-'.$bet_transaction->game_trans_id,
								"bonus_amount" => "0"
							];

		            $update_game_transaction = array(
	                    "win" => $this->_toDollars($json_data["amount"]) == 0 && $bet_transaction->pay_amount == 0 ? 0 : 1,
	                    "pay_amount" => $bet_transaction->pay_amount + $this->_toDollars($json_data["amount"]),
	                    "income" => $bet_transaction->income - $this->_toDollars($json_data["amount"]),
	                    "entry_id" => $this->_toDollars($json_data["amount"]) == 0 && $bet_transaction->pay_amount == 0 ? 1 : 2,
	                );

	                GameTransactionMDB::updateGametransaction($update_game_transaction, $bet_transaction->game_trans_id, $client_details);

	                $win_game_transaction_ext = array(
	                    "game_trans_id" => $bet_transaction->game_trans_id,
	                    "provider_trans_id" => $json_data["transaction_id"],
	                    "round_id" => $json_data["round_id"],
	                    "amount" => $this->_toDollars($json_data["amount"]),
	                    "game_transaction_type"=> 2,
	                    "provider_request" =>json_encode($json_data),
	                    "mw_response" => json_encode($response)
	                );

	                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($win_game_transaction_ext, $client_details);

	                $action_payload = [
		                "type" => "custom", #genreral,custom :D # REQUIRED!
		                "custom" => [
		                    "provider" => 'MannaPlay',
		                    "game_trans_ext_id" => $game_trans_ext_id,
		                    "client_connection_name" => $client_details->connection_name
		                ],
		                "provider" => [
		                    "provider_request" => $json_data, #R
		                    "provider_trans_id"=> $json_data['transaction_id'], #R
		                    "provider_round_id"=> $json_data['round_id'], #R
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


		            $client_response = ClientRequestHelper::fundTransfer_TG($client_details, $this->_toDollars($json_data["amount"]),$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',false,$action_payload);
				}
			}

		}

		Helper::saveLog('DragonGaming credit error_response', $this->provider_db_id, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);

    }


    public function rollbackTransaction(Request $request) {
    	$json_data = json_decode(file_get_contents("php://input"), true);
		
		$http_status = 200;
		if(!CallParameters::check_keys($json_data, 'token', 'account_id', 'amount', 'amount_type', 'currency', 'game_id', 'round_id', 'game_type', 'game_name', 'note')) {
			$http_status = 200;
			$response = [
					"status" => 0, 
					"error_id" => 101,
					"error_code" => "INVALID_PARAMETERS",
					"error_message" => "Invalid Parameters"
				];
		}
		else
		{
			$http_status = 200;
			$response = [
				"status" => 0, 
				"error_id" => 102,
				"error_code" => "ACCOUNT_DOESNT_EXIST",
				"error_message" => "Account doesnt exist"
			];

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

				$game_transaction = GameTransactionMDB::getGameTransactionByRoundId((string) $json_data['round_id'], $client_details);
				
				$http_status = 200;
				$response = [
					"status" => 0, 
					"error_id" => 107,
					"error_code" => "ROUND_NOT_FOUND",
					"error_message" => "Target round not found."
				];
				
				if ($game_transaction != 'false') {

					if ($game_transaction->win == 2) {
						return response()->json($response, $http_status);
					}

					$game_details = Game::find($json_data["game_id"], $this->provider_db_id);
				
					$update_game_transaction = array(
	                    "win" => 4,
	                    "pay_amount" => $game_transaction->amount,
	                    "income" => 0,
	                    "entry_id" => 2
	                );

		           	GameTransactionMDB::updateGametransaction($update_game_transaction, $game_transaction->game_trans_id, $client_details);

		           	$refund_game_transaction_ext = array(
	                    "game_trans_id" => $game_transaction->game_trans_id,
	                    "provider_trans_id" => 'N/A',
	                    "round_id" => $json_data["round_id"],
	                    "amount" => $game_transaction->amount,
	                    "game_transaction_type"=> 3,
	                    "provider_request" =>json_encode($json_data),
	                );

		           	$game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($refund_game_transaction_ext, $client_details);

		           	$fund_extra_data = [
	                    'provider_name' => $game_details->provider_name
	                ];  

		           	$client_response = ClientRequestHelper::fundTransfer($client_details, $game_transaction->amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction->game_trans_id, 'credit', "true", $fund_extra_data);
					
					if (isset($client_response->fundtransferresponse->status->code)) {
						
						switch ($client_response->fundtransferresponse->status->code) {
							case '200':
								ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);

								$http_status = 200;
								$response = [
									"status" => 1,
									"account_id" => $client_details->player_id,
									"country" => $client_details->country_code,
									"token" => $client_details->player_token,
									"balance" => $this->_toPennies($client_response->fundtransferresponse->balance),
									"currency" => $client_details->default_currency,
									"transaction_id" => 'R'.'-'.$game_transaction->game_trans_id,
									"bonus_amount" => "0"
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
		
		Helper::saveLog('DragonGaming rollback', $this->provider_db_id, file_get_contents("php://input"), $response);
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