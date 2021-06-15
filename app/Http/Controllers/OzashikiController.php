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
					Helper::errorDebug('ozashiki_debug_1st step', $this->provider_db_id , json_encode($client_details), 'client_details');
					try{
						ProviderHelper::idenpotencyTable($json_data['round_id']);
					}catch(\Exception $e){
						$response = [
							"errorCode" =>  10209,
							"message" => "Round id is exists!",
						];
						return $response;
					}
					Helper::errorDebug('ozashiki_debug_2nd step', $this->provider_db_id , json_encode($json_data['round_id']), 'idempotency passed');
					$response = [
						"errorCode" =>  10100,
						"message" => "Server is not ready!",
					];

					$json_data['income'] = $json_data['amount'];
					$json_data['roundid'] = $json_data['round_id'];
					$json_data['transid'] = $json_data['transaction_id'];
					$game_details = Game::find($json_data["game_id"], $this->provider_db_id);
					
					Helper::errorDebug('ozashiki_debug_3rd step', $this->provider_db_id , json_encode($game_details), 'game details found');
					
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
			            "flow_status" => 0,
			        );
			        $game_transaction_id = GameTransaction::createGametransaction($gameTransactionData);

					Helper::errorDebug('ozashiki_debug_4th step', $this->provider_db_id , json_encode($game_transaction_id), 'game transaction saved');

					$game_trans_ext_id = ProviderHelper::createGameTransExtV2($game_transaction_id, $json_data['transaction_id'], $json_data['round_id'], $json_data['amount'], 1,$json_data);
					// change $json_data['round_id'] to $game_transaction_id
					// ProviderHelper::updateGameTransactionStatus($game_transaction_id, 5, 5);
			        
			        Helper::errorDebug('ozashiki_debug_5th step', $this->provider_db_id , json_encode($game_trans_ext_id), 'game transaction ext saved');

			        $client_response = ClientRequestHelper::fundTransfer($client_details, $json_data['amount'], $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, 'debit');

			        Helper::errorDebug('ozashiki_debug_6th step', $this->provider_db_id , json_encode($client_response), 'client fund transfer response');
					
					if (isset($client_response->fundtransferresponse->status->code)) {
						
						switch ($client_response->fundtransferresponse->status->code) {
							case '200':
								ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
								$http_status = 200;
								$response = [
									"transaction_id" => $json_data['transaction_id'],
									"balance" => ProviderHelper::amountToFloat($client_response->fundtransferresponse->balance)
								];
								break;
							case '402':
								ProviderHelper::updateGameTransactionStatus($game_transaction_id, 2, 99);
								$http_status = 200;
								$response = [
									"errorCode" =>  10203,
									"message" => "Insufficient balance",
								];
								break;
						}

						ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, $json_data, $response, $client_response->requestoclient, $client_response, $json_data);

						Helper::errorDebug('ozashiki_debug_7th step', $this->provider_db_id , '', 'update game trans ext success');


					}
						
					Helper::saveLog('ozashiki_debit', $this->provider_db_id, json_encode($json_data), $response);
	                return response()->json($response, $http_status);

				}
			}

		}
		Helper::saveLog('Ozashiki debit error_response', $this->provider_db_id, file_get_contents("php://input"), $response);
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
						$bet_transaction = ProviderHelper::findGameTransaction($json_data['round_id'], 'round_id',1);
						
						$winbBalance = $client_details->balance + $json_data["amount"];
						ProviderHelper::_insertOrUpdate($client_details->token_id, $winbBalance); 
						$response = [
								"transaction_id" => $json_data['transaction_id'],
								"balance" => ProviderHelper::amountToFloat($winbBalance)
							];
						
			            $win_or_lost = $json_data["amount"] > 0 ?  1 : 0;
			            $entry_id = $json_data["amount"] > 0 ?  2 : 1;
			           	$income = $bet_transaction->bet_amount -  $json_data["amount"] ;

			           	ProviderHelper::updateGameTransaction($bet_transaction->game_trans_id, $json_data["amount"], $income, $win_or_lost, $entry_id, "game_trans_id", 2);

		                $game_trans_ext_id = ProviderHelper::createGameTransExtV2($bet_transaction->game_trans_id, $json_data['transaction_id'], $json_data['round_id'], $json_data['amount'], 2,$json_data, $response);

						$action_payload = [
			                "type" => "custom", #genreral,custom :D # REQUIRED!
			                "custom" => [
			                    "provider" => 'Ozashiki',
			                    "win_or_lost" => $win_or_lost,
			                    "entry_id" => $entry_id,
			                    "pay_amount" => $json_data["amount"],
			                    "income" => $income,
			                    "game_trans_ext_id" => $game_trans_ext_id
			                ],
			                "provider" => [
			                    "provider_request" => $json_data, #R
			                    "provider_trans_id"=> $json_data['transaction_id'], #R
			                    "provider_round_id"=> $json_data['round_id'], #R
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
			            $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$json_data["amount"],$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',false,$action_payload);

					}
				}
			}

		}
		Helper::saveLog('Ozashiki credit error_response', $this->provider_db_id, file_get_contents("php://input"), $response);
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

					$game_transaction = ProviderHelper::findGameTransaction($json_data['target_transaction_id'],'transaction_id', 1);
					$response = [
						"errorCode" =>  10210,
						"message" => "Target transaction id not found!",
					];
					
					if ($game_transaction != 'false') {

						if ($game_transaction->win == 2) {
							return response()->json($response, $http_status);
						}

						$game_details = Game::find($json_data["game_id"], $this->provider_db_id);
						
						$win_or_lost = 4;
			            $entry_id = 2;
			           	$income = $game_transaction->bet_amount -  $game_transaction->bet_amount ;

			           	ProviderHelper::updateGameTransaction($game_transaction->game_trans_id, $game_transaction->bet_amount, $income, $win_or_lost, $entry_id, "game_trans_id", 4);

			           	$game_trans_ext_id = ProviderHelper::createGameTransExtV2($game_transaction->game_trans_id, $json_data['transaction_id'], $json_data['round_id'], $game_transaction->bet_amount, 3);

			           	$client_response = ClientRequestHelper::fundTransfer($client_details, $game_transaction->bet_amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction->game_trans_id, 'credit', "true");
					
						if (isset($client_response->fundtransferresponse->status->code)) {
							
							switch ($client_response->fundtransferresponse->status->code) {
								case '200':
									ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
									$http_status = 200;
									$response = [
										"transaction_id" => $json_data['transaction_id'],
										"balance" => ProviderHelper::amountToFloat($client_response->fundtransferresponse->balance)
									];
									break;
							}

							ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, $json_data, $response, $client_response->requestoclient, $client_response, $json_data);

						}
					}
				}
			}

		}
		Helper::saveLog('Ozashiki rollback', $this->provider_db_id, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);
	}

	

	
}