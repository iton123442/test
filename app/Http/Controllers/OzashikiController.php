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

class OzashikiController extends Controller
{   
	// MANNAPLAY UPDATE LATEST 2021-04-01
	// CONTROLLER (WIN PROCESS EXT)
	// GAMETRANSACTION (save = add column client_status)
	// PROVIDERHELPER (add new method => updateFlowStatus : updateGameTransactionV2Credit) (findGameTransaction add column mw_response)
	// FUNDTRANSFER CUT CALL (CHANGE UPDATE AND NO GENERATE EXT)

	public $client_api_key , $provider_db_id ;

	public function __construct(){
		$this->client_api_key = config("providerlinks.ozashiki.CLIENT_API_KEY");
		$this->provider_db_id = config("providerlinks.ozashiki.PROVIDER_ID");
	}


	public function getBalance(Request $request) {

		$json_data = json_decode(file_get_contents("php://input"), true);
		$api_key = $request->header('apiKey');

		Helper::saveLog('ozashiki HIT', $this->provider_db_id, file_get_contents("php://input"), $api_key);
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
			if ($this->client_api_key != $api_key) {
				$http_status = 200;
				$response = [
					"errorCode" =>  10105,
					"message" => "Authenticate fail!",
				];
			} else {
				$http_status = 200;
				$response = [
					"errorCode" =>  10204,
					"message" => "Account is not exist!",
				];
				// Find the player and client details
				$client_details = ProviderHelper::getClientDetails('token', $json_data['sessionId']);
				if ($client_details != null) {
					$http_status = 200;
					$response = [
						"balance" => ProviderHelper::amountToFloat($client_details->balance)
					];
				}
			}

		}
		Helper::saveLog('ozashiki_balance', $this->provider_db_id, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);
	}

	public function debitProcess(Request $request){

        $json_data = json_decode(file_get_contents("php://input"), true);
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
			if ($this->client_api_key != $api_key) {
				$http_status = 200;
				$response = [
					"errorCode" =>  10105,
					"message" => "Authenticate fail!",
				];
			} else {

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
			            /* "flow_status" => 0, */
			        );

					$game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
			        /* $game_transaction_id = GameTransaction::createGametransaction($gameTransactionData); */

					$bet_game_transaction_ext = array(
						"game_trans_id" => $game_transaction_id,
						"provider_trans_id" => $json_data['transaction_id'],
						"round_id" => $json_data['round_id'],
						"amount" => $json_data['amount'],
						"game_transaction_type" => 1,
						"provider_request" => json_encode($json_data),
					);
	

					/* $game_trans_ext_id = ProviderHelper::createGameTransExtV2($game_transaction_id, $json_data['transaction_id'], $json_data['round_id'], $json_data['amount'], 1,$json_data); */
					
					$game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($bet_game_transaction_ext, $client_details); 
					
					// change $json_data['round_id'] to $game_transaction_id
					// ProviderHelper::updateGameTransactionStatus($game_transaction_id, 5, 5);
			        $fund_extra_data = [
	                    'provider_name' => $game_details->provider_name
	                ]; 

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
			                        /*Helper::saveLog('betGameInsuficient(ICG)', 12, json_encode($e->getMessage().' '.$e->getLine()), $client_response->fundtransferresponse->status->message);*/
			                    } 

								break;
						}


					}
						
					/*Helper::errorDebug('ozashiki_debit', config("providerlinks.ozashiki.PROVIDER_ID"), json_encode($json_data), $response);*/
	                return response()->json($response, $http_status);

				}
			}

		}

		/*Helper::errorDebug('ozashiki_debit', $this->provider_db_id, json_encode($json_data), $response);*/
		return response()->json($response, $http_status);

    }

    public function creditProcess(Request $request){
   		
        $json_data = json_decode(file_get_contents("php://input"), true);
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
			if ($this->client_api_key != $api_key) {
				$response = [
					"errorCode" =>  10105,
					"message" => "Authenticate fail!",
				];
			} else {

				$response = [
					"errorCode" =>  10204,
					"message" => "Account is not exist!",
				];
				// Find the player and client details
				$client_details = ProviderHelper::getClientDetails('token', $json_data['sessionId']);

				if ($client_details != null) {
					
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
						/*$bet_transaction = ProviderHelper::findGameTransaction($json_data['round_id'], 'round_id',1);*/
						
						$bet_transaction = GameTransactionMDB::getGameTransactionByTokenAndRoundId($json_data['sessionId'], $json_data['round_id'], $client_details);

						$winbBalance = $client_details->balance + $json_data["amount"];
						ProviderHelper::_insertOrUpdate($client_details->token_id, $winbBalance); 
						
						$response = [
								"transaction_id" => $json_data['transaction_id'],
								"balance" => ProviderHelper::amountToFloat($winbBalance)
							];
						
			            /*$win_or_lost = $json_data["amount"] > 0 ?  1 : 0;
			            $entry_id = $json_data["amount"] > 0 ?  2 : 1;
			           	$income = $bet_transaction->bet_amount -  $json_data["amount"] ;*/

			           	/*ProviderHelper::updateGameTransaction($bet_transaction->game_trans_id, $json_data["amount"], $income, $win_or_lost, $entry_id, "game_trans_id", 2);*/
			           	

			           	$create_game_transaction = array(
		                    "win" => $json_data["amount"] == 0 && $bet_transaction->pay_amount == 0 ? 0 : 1,
		                    "pay_amount" => $bet_transaction->pay_amount + $json_data["amount"],
		                    "income" => $bet_transaction->income - $json_data["amount"],
		                    "entry_id" => $json_data["amount"] == 0 && $bet_transaction->pay_amount == 0 ? 1 : 2,
		                );

		                GameTransactionMDB::updateGametransaction($create_game_transaction, $bet_transaction->game_trans_id, $client_details);

		                /*$game_trans_ext_id = ProviderHelper::createGameTransExtV2($bet_transaction->game_trans_id, $json_data['transaction_id'], $json_data['round_id'], $json_data['amount'], 2,$json_data, $response);*/

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
			                    "provider" => 'Ozashiki',
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

			            $client_response = ClientRequestHelper::fundTransfer_TG($client_details, $json_data["amount"],$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',false,$action_payload);

					}
				}
			}

		}
		/*Helper::errorDebug('ozashiki_credit', $this->provider_db_id, json_encode($json_data), $response);*/
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
			if ($this->client_api_key != $api_key) {
				$response = [
					"errorCode" =>  10105,
					"message" => "Authenticate fail!",
				];
			} else {

				$response = [
					"errorCode" =>  10204,
					"message" => "Account is not exist!",
				];
				// Find the player and client details
				$client_details = ProviderHelper::getClientDetails('token', $json_data['sessionId']);

				if ($client_details != null) {
					
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
					/*$game_transaction = ProviderHelper::findGameTransaction($json_data['target_transaction_id'],'transaction_id', 1);*/
					$response = [
						"errorCode" =>  10210,
						"message" => "Target transaction id not found!",
					];
					
					if ($game_transaction != 'false') {

						/*if ($game_transaction->win == 2) {
							return response()->json($response, $http_status);
						}*/

						$game_details = Game::find($json_data["game_id"], $this->provider_db_id);
						
						/*$win_or_lost = 4;
			            $entry_id = 2;
			           	$income = $game_transaction->bet_amount -  $game_transaction->bet_amount ;*/

			           	$update_game_transaction = array(
		                    "win" => 4,
		                    "pay_amount" => $game_transaction->amount,
		                    "income" => 0,
		                    "entry_id" => 2
		                );

			           	/*ProviderHelper::updateGameTransaction($game_transaction->game_trans_id, $game_transaction->bet_amount, $income, $win_or_lost, $entry_id, "game_trans_id", 4);*/
			           	GameTransactionMDB::updateGametransaction($update_game_transaction, $game_transaction->game_trans_id, $client_details);

			           	/*$game_trans_ext_id = ProviderHelper::createGameTransExtV2($game_transaction->game_trans_id, $json_data['transaction_id'], $json_data['round_id'], $game_transaction->bet_amount, 3);*/
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
									/* ProviderHelper::updateGameTransactionFlowStatus($game_transaction->game_trans_id, 5); */
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
			}

		}
		/*Helper::errorDebug('ozashiki_rollback', $this->provider_db_id, json_encode($json_data), $response);*/
		return response()->json($response, $http_status);
	}

}