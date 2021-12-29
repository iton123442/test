<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Helpers\CallParameters;
use App\Helpers\ClientRequestHelper;
use App\Helpers\ProviderHelper;
use App\Helpers\Helper;
use App\Helpers\Game;
use App\Helpers\FreeSpinHelper;
use App\Models\GameTransaction;
use App\Models\GameTransactionMDB;
use DB;

class MannaPlayController extends Controller
{   
	// MANNAPLAY UPDATE LATEST 2021-04-01
	// CONTROLLER (WIN PROCESS EXT)
	// GAMETRANSACTION (save = add column client_status)
	// PROVIDERHELPER (add new method => updateFlowStatus : updateGameTransactionV2Credit) (findGameTransaction add column mw_response)
	// FUNDTRANSFER CUT CALL (CHANGE UPDATE AND NO GENERATE EXT)

	public $client_api_key , $provider_db_id ;

	public function __construct(){
		// $this->client_api_key = config("providerlinks.manna.CLIENT_API_KEY");
		// $this->provider_db_id = config("providerlinks.manna.PROVIDER_ID");
		$this->provider_db_id = config("providerlinks.mannaplay.PROVIDER_ID");
	}


	public function CheckAuth($client_details, $api_key){
		if ($client_details->operator_id == 15){  // Operator id 15 / Everymatrix
            $CLIENT_API_KEY = config("providerlinks.mannaplay.15.CLIENT_API_KEY");
        }elseif($client_details->operator_id == 30){ // IDNPLAY
            $CLIENT_API_KEY = config("providerlinks.mannaplay.30.CLIENT_API_KEY");
        }else{
            $CLIENT_API_KEY = config("providerlinks.mannaplay.default.CLIENT_API_KEY");
        }

        if ($CLIENT_API_KEY == $api_key){
        	return true;
        }else{
        	return false;
        }
	}


	public function getBalance(Request $request) {

		$json_data = json_decode(file_get_contents("php://input"), true);
		$api_key = $request->header('apiKey');

		ProviderHelper::saveLogWithExeption('manna_balance HIT', $this->provider_db_id, file_get_contents("php://input"), $api_key);
		if(!CallParameters::check_keys($json_data, 'account', 'sessionId'))
		{
			$http_status = 200;
			$response = [
					"errorCode" =>  10102,
					"message" => "Post data is invalid!",
				];
		}
		else
		{
			// if ($this->client_api_key != $api_key) {
			// 	$http_status = 200;
			// 	$response = [
			// 		"errorCode" =>  10105,
			// 		"message" => "Authenticate fail!",
			// 	];
			// } else {
				$http_status = 200;
				$response = [
					"errorCode" =>  10204,
					"message" => "Account is not exist!",
				];
				// Find the player and client details
				$client_details = ProviderHelper::getClientDetails('token', $json_data['sessionId']);
				if ($client_details != null) {

					if (!$this->CheckAuth($client_details, $api_key)){
						$http_status = 200;
						$response = [
							"errorCode" =>  10105,
							"message" => "Authenticate fail!",
						];
						ProviderHelper::saveLogWithExeption('manna_balance FAILED AUTH', $this->provider_db_id, file_get_contents("php://input"), $api_key);
						return response()->json($response, $http_status);
					}

					$http_status = 200;
					$response = [
						"balance" => ProviderHelper::amountToFloat($client_details->balance)
					];
				}
			// }

		}
		ProviderHelper::saveLogWithExeption('manna_balance', $this->provider_db_id, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);
	}

	public function debitProcess(Request $request){

        $json_data = json_decode(file_get_contents("php://input"), true);
		ProviderHelper::saveLogWithExeption('manna_debit', $this->provider_db_id, json_encode($json_data), "HITTTTTT");
		$api_key = $request->header('apiKey');
		if(!CallParameters::check_keys($json_data, 'account', 'sessionId', 'amount', 'game_id', 'round_id', 'transaction_id'))
		{
			$http_status = 200;
			$response = [
					"errorCode" =>  10102,
					"message" => "Post data is invalid!",
				];
		}
		else
		{
			// if ($this->client_api_key != $api_key) {
			// 	$http_status = 200;
			// 	$response = [
			// 		"errorCode" =>  10105,
			// 		"message" => "Authenticate fail!",
			// 	];
			// } else {

				$http_status = 200;
				$response = [
					"errorCode" =>  10204,
					"message" => "Account is not exist!",
				];
				// Find the player and client details
				$client_details = ProviderHelper::getClientDetails('token', $json_data['sessionId']);
				if ($client_details != null) {

					if (!$this->CheckAuth($client_details, $api_key)){
						$http_status = 200;
						$response = [
							"errorCode" =>  10105,
							"message" => "Authenticate fail!",
						];
						return response()->json($response, $http_status);
					}
					
					try{
						ProviderHelper::idenpotencyTable($json_data['round_id']);
					}catch(\Exception $e){
						$response = [
							"errorCode" =>  10209,
							"message" => "Round id is exists!",
						];
						return $response;
					}

					$response = [
						"errorCode" =>  10100,
						"message" => "Server is not ready!",
					];

					$json_data['income'] = $json_data['amount'];
					$json_data['roundid'] = $json_data['round_id'];
					$json_data['transid'] = $json_data['transaction_id'];
					$game_details = Game::find($json_data["game_id"], $this->provider_db_id);

					$gameTransactionData = array(
			            "provider_trans_id" => $json_data['transaction_id'],
			            "token_id" => $client_details->token_id,
			            "game_id" => $game_details->game_id,
			            "round_id" => $json_data['round_id'],
			            "bet_amount" => $json_data['amount'],
			            "win" => 5,
			            "pay_amount" => 0,
			            "income" => 0,
			            "entry_id" => 1,
			            /*"flow_status" => 0,*/
			        );
			        
			        $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);

					$bet_game_transaction_ext = array(
						"game_trans_id" => $game_transaction_id,
						"provider_trans_id" => $json_data['transaction_id'],
						"round_id" => $json_data['round_id'],
						"amount" => $json_data['amount'],
						"game_transaction_type" => 1,
						"provider_request" => json_encode($json_data),
					);

					$game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($bet_game_transaction_ext, $client_details); 
					
					$fund_extra_data = [
	                    'provider_name' => $game_details->provider_name
	                ]; 

					// change $json_data['round_id'] to $game_transaction_id
					// ProviderHelper::updateGameTransactionStatus($game_transaction_id, 5, 5);
			        $client_response = ClientRequestHelper::fundTransfer($client_details, $json_data['amount'], $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, 'debit', false, $fund_extra_data);
					
					if (isset($client_response->fundtransferresponse->status->code)) {
						/*ProviderHelper::updateGameTransactionFlowStatus($game_transaction_id, 1);*/
						switch ($client_response->fundtransferresponse->status->code) {
							case '200':
								ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
								$http_status = 200;
								$response = [
									"transaction_id" => $json_data['transaction_id'],
									"balance" => ProviderHelper::amountToFloat($client_response->fundtransferresponse->balance)
								];
								
								$data_to_update = array(
			                        "mw_response" => json_encode($response)
			                    );

			                    GameTransactionMDB::updateGametransactionEXT($data_to_update, $game_trans_ext_id, $client_details);

								break;
							case '402':
								/*ProviderHelper::updateGameTransactionStatus($game_transaction_id, 2, 99);*/
								$http_status = 200;
								$response = [
									"errorCode" =>  10203,
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
			                        /*ProviderHelper::saveLogWithExeption('betGameInsuficient(ICG)', 12, json_encode($e->getMessage().' '.$e->getLine()), $client_response->fundtransferresponse->status->message);*/
			                    } 

								break;
						}


					}
						
					ProviderHelper::saveLogWithExeption('manna_debit', $this->provider_db_id, json_encode($json_data), $response);
	                return response()->json($response, $http_status);

				}
			// }

		}
		ProviderHelper::saveLogWithExeption('MannaPlay debit error_response', $this->provider_db_id, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);

    }

    public function creditProcess(Request $request){
   		
        $json_data = json_decode(file_get_contents("php://input"), true);
		ProviderHelper::saveLogWithExeption('manna_creditProcess', $this->provider_db_id, json_encode($json_data), "HITTTTTT");
		$api_key = $request->header('apiKey');
		$http_status = 200;
		if(!CallParameters::check_keys($json_data, 'account', 'sessionId', 'amount', 'game_id', 'round_id', 'transaction_id'))
		{
			$response = [
					"errorCode" =>  10102,
					"message" => "Post data is invalid!",
				];
		}
		else
		{
			// if ($this->client_api_key != $api_key) {
			// 	$response = [
			// 		"errorCode" =>  10105,
			// 		"message" => "Authenticate fail!",
			// 	];
			// } else {

				$response = [
					"errorCode" =>  10204,
					"message" => "Account is not exist!",
				];
				// Find the player and client details
				$client_details = ProviderHelper::getClientDetails('token', $json_data['sessionId']);

				if ($client_details != null) {

					if (!$this->CheckAuth($client_details, $api_key)){
						$http_status = 200;
						$response = [
							"errorCode" =>  10105,
							"message" => "Authenticate fail!",
						];
						return response()->json($response, $http_status);
					}
					
					try{
						ProviderHelper::idenpotencyTable($json_data['transaction_id']);
					}catch(\Exception $e){
						$response = [
							"errorCode" =>  10208,
							"message" => "Transaction id is exists!",
						];
						return $response;
					}

					if($json_data['amount'] < 0) {
						$response = [
							"errorCode" =>  10201,
							"message" => "Warning value must not be less 0.",
						];
					}
					else
					{
						
						$game_details = Game::find($json_data["game_id"], $this->provider_db_id);
						
						$bet_transaction = GameTransactionMDB::getGameTransactionByTokenAndRoundId($json_data['sessionId'], $json_data['round_id'], $client_details);
						
						$winbBalance = $client_details->balance + $json_data["amount"];
						
						ProviderHelper::_insertOrUpdate($client_details->token_id, $winbBalance); 
						
						$response = [
								"transaction_id" => $json_data['transaction_id'],
								"balance" => ProviderHelper::amountToFloat($winbBalance)
							];
						
						$win = $json_data["amount"] == 0 && $bet_transaction->pay_amount == 0 ? 0 : 1;
			            $update_game_transaction = array(
		                    "win" => 5,
		                    "pay_amount" => $bet_transaction->pay_amount + $json_data["amount"],
		                    "income" => $bet_transaction->income - $json_data["amount"],
		                    "entry_id" => $json_data["amount"] == 0 && $bet_transaction->pay_amount == 0 ? 1 : 2,
		                );

			           	GameTransactionMDB::updateGametransaction($update_game_transaction, $bet_transaction->game_trans_id, $client_details);


		                $win_game_transaction_ext = array(
		                    "game_trans_id" => $bet_transaction->game_trans_id,
		                    "provider_trans_id" => $json_data["transaction_id"],
		                    "round_id" => $json_data["round_id"],
		                    "amount" => $json_data["amount"],
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
			                    "client_connection_name" => $client_details->connection_name,
								"win_or_lost" => $win
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
			            $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$json_data["amount"],$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',false,$action_payload);

					}
				}
			// }

		}
		ProviderHelper::saveLogWithExeption('MannaPlay credit error_response', $this->provider_db_id, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);

    }


    public function rollbackTransaction(Request $request) {
    	$json_data = json_decode(file_get_contents("php://input"), true);
		$api_key = $request->header('apiKey');
		$http_status = 200;
		if(!CallParameters::check_keys($json_data, 'account','sessionId', 'game_id', 'round_id', 'transaction_id','target_transaction_id')) {
			$response = [
					"errorCode" =>  10102,
					"message" => "Post data is invalid!",
				];
		}
		else
		{
			// if ($this->client_api_key != $api_key) {
			// 	$response = [
			// 		"errorCode" =>  10105,
			// 		"message" => "Authenticate fail!",
			// 	];
			// } else {

				$response = [
					"errorCode" =>  10204,
					"message" => "Account is not exist!",
				];
				// Find the player and client details
				$client_details = ProviderHelper::getClientDetails('token', $json_data['sessionId']);

				if ($client_details != null) {

					if (!$this->CheckAuth($client_details, $api_key)){
						$http_status = 200;
						$response = [
							"errorCode" =>  10105,
							"message" => "Authenticate fail!",
						];
						return response()->json($response, $http_status);
					}
					
					try{
						ProviderHelper::idenpotencyTable($json_data['transaction_id']);
					}catch(\Exception $e){
						$response = [
							"errorCode" =>  10208,
							"message" => "Transaction id is exists!",
						];
						return $response;
					}

					$game_transaction =  GameTransactionMDB::getGameTransactionDataByProviderTransactionIdAndEntryType($json_data["target_transaction_id"], 1, $client_details);

					$response = [
						"errorCode" =>  10210,
						"message" => "Target transaction id not found!",
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
		                    "provider_trans_id" => $json_data["transaction_id"],
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
									/*ProviderHelper::updateGameTransactionFlowStatus($game_transaction->game_trans_id, 5);*/
									ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
									$http_status = 200;
									$response = [
										"transaction_id" => $json_data['transaction_id'],
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
			// }

		}
		ProviderHelper::saveLogWithExeption('MannaPlay rollback', $this->provider_db_id, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);
	}

	public function freeRound(Request $request){
   		
		$json_data = json_decode(file_get_contents("php://input"), true);
		ProviderHelper::saveLogWithExeption('manna_freeRound', $this->provider_db_id, json_encode($json_data), "HITTTTTT");
		$api_key = $request->header('apiKey');
		if(!CallParameters::check_keys($json_data, 'account', 'sessionId', 'amount', 'game_id', 'round_id', 'transaction_id'))
		{
			$http_status = 200;
			$response = [
					"errorCode" =>  10102,
					"message" => "Post data is invalid!",
				];
		}
		else
		{

			$http_status = 200;
			$response = [
				"errorCode" =>  10204,
				"message" => "Account is not exist!",
			];
			// Find the player and client details
			$client_details = ProviderHelper::getClientDetails('token', $json_data['sessionId']);
			if ($client_details != null) {
				
				try{
					ProviderHelper::idenpotencyTable($json_data['round_id']);
				}catch(\Exception $e){
					$response = [
						"errorCode" =>  10209,
						"message" => "Round id is exists!",
					];
					return $response;
				}

				$response = [
					"errorCode" =>  10100,
					"message" => "Server is not ready!",
				];
				
				$json_data['income'] = $json_data['amount'];
				$json_data['roundid'] = $json_data['round_id'];
				$json_data['transid'] = $json_data['transaction_id'];
				$game_details = Game::find($json_data["game_id"], $this->provider_db_id);
				$amount_win = $json_data["amount"] + $json_data["jp_win"];
				$win_type = $amount_win > 0 ? 1 : 0;
				$gameTransactionData = array(
					"provider_trans_id" => $json_data['transaction_id'],
					"token_id" => $client_details->token_id,
					"game_id" => $game_details->game_id,
					"round_id" => $json_data['round_id'],
					"bet_amount" => 0,
					"win" => $win_type,
					"pay_amount" => $amount_win,
					"income" => 0 - $amount_win,
					"entry_id" => 1,
					/*"flow_status" => 0,*/
				);
				
				$game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);

				$bet_game_transaction_ext = array(
					"game_trans_id" => $game_transaction_id,
					"provider_trans_id" => $json_data['transaction_id'],
					"round_id" => $json_data['round_id'],
					"amount" => 0,
					"game_transaction_type" => 1,
					"provider_request" => json_encode($json_data),
				);

				$game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($bet_game_transaction_ext, $client_details); 
				
			
				$fund_extra_data = [
					'fundtransferrequest' => [
						'fundinfo' => [
							'freespin' => true
						]
						],
					'provider_name' => $game_details->provider_name
				];
				//getTransaction
				$getFreespin = FreeSpinHelper::getFreeSpinDetails($json_data["freeroundref"], "provider_trans_id" );

				if($getFreespin){
					//update transaction
						$status = ($getFreespin->spin_remaining - 1) == 0 ? 2 : 1;
						$updateFreespinData = [
							"status" => $status,
							"spin_remaining" => $getFreespin->spin_remaining - 1
						];
						$updateFreespin = FreeSpinHelper::updateFreeSpinDetails($updateFreespinData, $getFreespin->freespin_id);
						//create transction 
						$createFreeRoundTransaction = array(
							"game_trans_id" => $game_transaction_id,
							'freespin_id' => $getFreespin->freespin_id
						);
						FreeSpinHelper::createFreeRoundTransaction($createFreeRoundTransaction);
				}
				
				

				// change $json_data['round_id'] to $game_transaction_id
				// ProviderHelper::updateGameTransactionStatus($game_transaction_id, 5, 5);
				$client_response = ClientRequestHelper::fundTransfer($client_details, 0, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, 'debit', false, $fund_extra_data);
				
				if (isset($client_response->fundtransferresponse->status->code)) {
					/*ProviderHelper::updateGameTransactionFlowStatus($game_transaction_id, 1);*/
					switch ($client_response->fundtransferresponse->status->code) {
						case '200':
							ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
							$http_status = 200;
							$response = [
								"transaction_id" => $json_data['transaction_id'],
								"balance" => ProviderHelper::amountToFloat($client_response->fundtransferresponse->balance)
							];
							
							$data_to_update = array(
								"mw_response" => json_encode($response)
							);

							GameTransactionMDB::updateGametransactionEXT($data_to_update, $game_trans_ext_id, $client_details);

							$winbBalance = $client_response->fundtransferresponse->balance + $amount_win;
					
							ProviderHelper::_insertOrUpdate($client_details->token_id, $winbBalance); 
							
							$response = [
									"transaction_id" => $json_data['transaction_id'],
									"balance" => ProviderHelper::amountToFloat($winbBalance)
								];
							

							$win_game_transaction_ext = array(
								"game_trans_id" => $game_transaction_id,
								"provider_trans_id" => $json_data["transaction_id"],
								"round_id" => $json_data["round_id"],
								"amount" => $amount_win,
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
									"provider_name"=> $game_details->provider_name,
									"freespin" => "true",
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
										'freespin' => true
									],
								],
							];

							$client_response = ClientRequestHelper::fundTransfer_TG($client_details,$amount_win,$game_details->game_code,$game_details->game_name,$game_transaction_id,'credit',false,$action_payload);
							ProviderHelper::saveLogWithExeption('manna_freeround_response', $this->provider_db_id, json_encode($json_data), $response);
							return response()->json($response, $http_status);
							break;
						case '402':
							/*ProviderHelper::updateGameTransactionStatus($game_transaction_id, 2, 99);*/
							$http_status = 200;
							$response = [
								"errorCode" =>  10203,
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
								/*ProviderHelper::saveLogWithExeption('betGameInsuficient(ICG)', 12, json_encode($e->getMessage().' '.$e->getLine()), $client_response->fundtransferresponse->status->message);*/
							} 

							break;
					}


				}
					
				ProviderHelper::saveLogWithExeption('manna_freeround', $this->provider_db_id, json_encode($json_data), $response);
				return response()->json($response, $http_status);

			}

		}
		ProviderHelper::saveLogWithExeption('MannaPlay debit error_response', $this->provider_db_id, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);


    }

	

	
}