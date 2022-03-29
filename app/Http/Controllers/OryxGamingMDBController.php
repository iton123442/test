<?php

namespace App\Http\Controllers;

use App\Models\PlayerDetail;
use App\Models\PlayerSessionToken;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Helpers\GameTransaction;
use App\Helpers\GameSubscription;
use App\Helpers\GameRound;
use App\Helpers\Game;
use App\Helpers\CallParameters;
use App\Helpers\PlayerHelper;
use App\Helpers\TokenHelper;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransactionMDB;
use App\Support\RouteParam;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use DB;

class OryxGamingMDBController extends Controller
{

	public $prefix = 'ORYX'; 
	public $middleware_api;

    public function __construct(){
		/*$this->middleware('oauth', ['except' => ['index']]);*/
		/*$this->middleware('authorize:' . __CLASS__, ['except' => ['index', 'store']]);*/
		$this->middleware_api = config('providerlinks.oauth_mw_api.mwurl'); 
        $this->provider_db_id = 18;

	}

	public function show(Request $request) { }

	public function authPlayer(Request $request)
	{   
		Helper::saveLog('Oryx Auth', $this->provider_db_id, json_encode($request->all()), "ENDPOINT HIT AUth");
        $json_data = json_decode(file_get_contents("php://input"), true);
        Helper::saveLog('Oryx Auth', $this->provider_db_id, $json_data, "ENDPOINT HIT AUth");
		$client_code = RouteParam::get($request, 'brand_code');
		$token = RouteParam::get($request, 'token');

		if(!CallParameters::check_keys($json_data, 'gameCode')) {
				$http_status = 401;
				$response = [
							"responseCode" =>  "REQUEST_DATA_FORMAT",
							"errorDescription" => "Data format of request not as expected."
						];
		}
		else
		{
			$http_status = 402;
			$response = [
							"responseCode" =>  "TOKEN_NOT_VALID",
							"errorDescription" => "Token provided in request not valid in Wallet."
						];
			
			$client_details = ProviderHelper::getClientDetails('token', $token);
			$client_response = ClientRequestHelper::playerDetailsCall($client_details->player_token);
			ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->playerdetailsresponse->balance); 
			if ($client_details) {

				$http_status = 200;
				$response = [
					"playerId" => "$client_details->player_id",
					"currencyCode" => $client_details->default_currency, 
					"languageCode" => "ENG",
					"balance" => $this->_toPennies($client_response->playerdetailsresponse->balance),
					"sessionToken" => $token
				];
			}
		}

		// Helper::saveLog('oryx_authentication', 18, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);

	}

	
	public function getBalance(Request $request) 
	{
        Helper::saveLog('Oryx GetBalance', $this->provider_db_id, json_encode($request->all()), "ENDPOINT HIT Balance");
		$data = json_decode(file_get_contents("php://input"), true);
		$client_code = RouteParam::get($request, 'brand_code');
		$player_id = RouteParam::get($request, 'player_id');

		if($data['gameCode'] == null) {
				$http_status = 401;
				$response = [
							"responseCode" =>  "REQUEST_DATA_FORMAT",
							"errorDescription" => "Data format of request not as expected."
						];
                return response($response,$http_status)->header('Content-Type', 'application/json');
		}if($data['sessionToken'] == null){
                $http_status = 402;
                $response = [
                                "responseCode" =>  "TOKEN_NOT_VALID",
                                "errorDescription" => "Token provided in request not valid in Wallet."
                            ];
                return response($response,$http_status)->header('Content-Type', 'application/json');
        }
			// Find the player and client details
			$client_details = ProviderHelper::getClientDetails('player_id', $player_id);
			if ($client_details) {
					$client_response = ClientRequestHelper::playerDetailsCall($client_details->player_token);
					ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->playerdetailsresponse->balance); 
					$http_status = 200;
						$response = [
							"balance" => $this->_toPennies($client_response->playerdetailsresponse->balance)
					];
			}
		
            return response($response,$http_status)->header('Content-Type', 'application/json');

	}

	public function gameTransaction(Request $request) 
	{	Helper::saveLog('Oryx Transaction', $this->provider_db_id, json_encode($request->all()), "ENDPOINT HIT Balance");
        $data = $request->all();
        $client_details = ProviderHelper::getClientDetails('player_id', $data['playerId']);
        if($client_details != null){
         if(isset($data['bet']['transactionId'])){
            $response = $this->gameBet($data, $client_details);
            return response($response,200)->header('Content-Type', 'application/json');
          }if($data['roundAction'] == 'CANCEL'){
			$response = $this->_isCancelled($transaction_id);
            return response($response,200)->header('Content-Type', 'application/json');
		  }
         if($data['roundAction']== 'CLOSE'){
            if(isset($data['win']['transactionId'])){
             $response = $this->gameWin($data, $client_details);
             return response($response,200)->header('Content-Type', 'application/json');
              }else{
                $response = $this->gameWin($data, $client_details);
                return response($response,200)->header('Content-Type', 'application/json');
              }
            }
        }else{
            $http_status = 402;
            $response = [
                "responseCode" =>  "TOKEN_NOT_VALID",
                "errorDescription" => "Token provided in request not valid in Wallet."
            ];

            return response($response,$http_status)->header('Content-Type', 'application/json');
            
        }
		
	}

    public function gameBet($data, $client_details){
            $payload = $data;
			Helper::saveLog('Oryx Bet', $this->provider_db_id, json_encode($payload),$client_details);
            $player_id = $payload['playerId'];
            $game_code = $payload['gameCode'];
            $provider_trans_id = $payload['bet']['transactionId'];
            $round_id = $payload['roundId'];
            $bet_amount = $payload['bet']['amount']/100;
            $client_details = ProviderHelper::getClientDetails('player_id', $payload['playerId']);
            try{
                ProviderHelper::idenpotencyTable($provider_trans_id);
            }catch(\Exception $e){
                $http_status = 401;
				$response = [
							"responseCode" =>  "REQUEST_DATA_FORMAT",
							"errorDescription" => "Data format of request not as expected."
						];
                return $response;
            }
            
            Helper::saveLog('Oryx Find Game Trans', $this->provider_db_id, json_encode($payload),$client_details);
            $game_details = Game::find($game_code, $this->provider_db_id);
            $find_bet = GameTransactionMDB::findGameTransactionDetails($provider_trans_id,'transaction_id',false,$client_details);
                if($find_bet != 'false'){
                    $client_details->connection_name = $find_bet->connection_name;
                    $amount = $find_bet->bet_amount + $bet_amount;
                    $game_transaction_id = $find_bet->game_trans_id;
                    $updateGameTransaction = [
                        'win'=>5,
                        'bet_amount'=>$amount,
                        'entry_id' => 1,
                        'trans_status'=>1
                    ];
                    //Helper::savelog('Oryx Sidebet success', $this->provider_db_id,json_encode($request->all()),$updateGameTransaction);
                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_transaction_id,$client_details);

                }else{
                    $gameTransactionData = array(
                        "provider_trans_id" => $provider_trans_id,
                        "token_id" => $client_details->token_id,
                        "game_id" => $game_details->game_id,
                        "round_id" => $round_id,
                        "bet_amount" => $bet_amount,
                        "win" => 5,
                        "pay_amount" => 0,
                        "income" => 0,
                        "entry_id" => 1,
                    ); 
                    $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
					Helper::saveLog('Oryx create trans', $this->provider_db_id, json_encode($payload),$client_details);
                    $gameTransactionEXTData = array(
                        "game_trans_id" => $game_transaction_id,
                        "provider_trans_id" => $provider_trans_id,
                        "round_id" => $round_id,
                        "amount" => $bet_amount,
                        "game_transaction_type"=> 1,
                        "provider_request" =>json_encode($payload),
                    );
                    $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details); 
					Helper::saveLog('Oryx create trans ext', $this->provider_db_id, json_encode($payload),$client_details);
                    $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount, $game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, 'debit');
                    if(isset($client_response->fundtransferresponse->status->code)){
                        $playerBal = sprintf('%.2f', $client_response->fundtransferresponse->balance);
                        ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                        switch ($client_response->fundtransferresponse->status->code) 
                           {
                                case '200':
                                    $http_status = 200;
                                    $response = [
                                        "responseCode" => "OK",
                                        "balance" => $this->_toPennies($client_response->fundtransferresponse->balance),
                                    ];
                                    $updateTransactionEXt = array(
                                        "provider_request" =>json_encode($payload),
                                        "mw_response" => json_encode($response),
                                        'mw_request' => json_encode($client_response->requestoclient),
                                        'client_response' => json_encode($client_response->fundtransferresponse),
                                        'transaction_detail' => 'success',
                                        'general_details' => 'success',
                                    
                                    );
                                    GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                                break;
                                case '402':
                                        // ProviderHelper::updateGameTransactionStatus($game_transaction_id, 2, 99);
                                    $http_status = 200;
                                    $response = [
                                        "responseCode" =>  "OUT_OF_MONEY",
                                        "errorDescription" => "Player ran out of money.",
                                        "balance" => $this->_toPennies($client_response->fundtransferresponse->balance)
                                    ];

                                    $updateTransactionEXt = array(
                                        "provider_request" =>$payload,
                                        "mw_response" => json_encode($response),
                                        'mw_request' => json_encode($client_response->requestoclient),
                                        'client_response' => json_encode($client_response->fundtransferresponse),
                                        'transaction_detail' => 'failed',
                                        'general_details' => 'failed',
                                    );
                                    GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                                break;
                            }
							Helper::saveLog('Oryx Success Bet', $this->provider_db_id, json_encode($payload),$response);
                            return $response;
                    }
                }

    }
    public function gameWin($data, $client_details){
    Helper::saveLog('Oryx Win', $this->provider_db_id, json_encode($data), "Win HIT");
            $payload = $data;
            $player_id = $payload['playerId'];
            $game_code = $payload['gameCode'];
            $round_id = $payload['roundId'];
            if(isset($payload['win']['amount'])){
               $pay_amount = $payload['win']['amount']/100;  
			   $provider_trans_id = $payload['win']['transactionId'];
            }else{
                $pay_amount = 0;
				$provider_trans_id = "ORYX".$payload['sessionToken'];
            }
            $client_details = ProviderHelper::getClientDetails('player_id', $player_id);
            if($client_details){
                try{
                    ProviderHelper::idenpotencyTable($provider_trans_id);
                }catch(\Exception $e){
                    $http_status = 401;
				$response = [
							"responseCode" =>  "REQUEST_DATA_FORMAT",
							"errorDescription" => "Data format of request not as expected."
						];
                return $response;;
                }

                $game_details = ProviderHelper::findGameDetails('game_code', $this->provider_db_id, $game_code);
                $bet_transaction = GameTransactionMDB::findGameTransactionDetails($round_id,'round_id', false, $client_details);
                $winbBalance = $client_details->balance + $pay_amount;
                ProviderHelper::_insertOrUpdate($client_details->token_id,$this->_toPennies($winbBalance));
                $win_or_lost = $pay_amount > 0 ?  1 : 0;
                $entry_id = $pay_amount > 0 ?  2 : 1;
                $response = [

                    "responseCode"=> "OK",
                    "balance"=> $this->_toPennies($winbBalance),
                ];
                $amount = $pay_amount + $bet_transaction->pay_amount; 
                $income = $bet_transaction->bet_amount -  $amount; 
                if($bet_transaction->pay_amount > 0){
                    $win_or_lost = 1;
                }else{
                    $win_or_lost = $pay_amount > 0 ?  1 : 0;
                }
                $updateGameTransaction = [
                    'win' => 5,
                    'pay_amount' => $amount,
                    'income' => $income,
                    'entry_id' => $entry_id,
                    'trans_status' => 2
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
                $gameTransactionEXTData = array(
                    "game_trans_id" => $bet_transaction->game_trans_id,
                    "provider_trans_id" => $provider_trans_id,
                    "round_id" => $round_id,
                    "amount" => $pay_amount,
                    "game_transaction_type"=> 2,
                    "provider_request" =>$payload,
                    "mw_response" => json_encode($response),
                );
                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
                $action_payload = [
                    "type" => "custom", #genreral,custom :D # REQUIRED!
                    "custom" => [
                        "provider" => 'Oryx Gaming',
                        "client_connection_name" => $client_details->connection_name,
                        "win_or_lost" => $win_or_lost,
                        "entry_id" => $entry_id,
                        "pay_amount" => $pay_amount,
                        "income" => $income,
                        "game_trans_ext_id" => $game_trans_ext_id
                    ],
                    "provider" => [
                        "provider_request" => $payload, #R
                        "provider_trans_id"=> $provider_trans_id, #R
                        "provider_round_id"=> $round_id, #R
                        "provider_name" => $game_details->provider_name,
                    ],
                    "mwapi" => [
                        "roundId"=>$bet_transaction->game_trans_id, #R
                        "type"=>2, #R
                        "game_id" => $game_details->game_id, #R
                        "player_id" => $client_details->player_id, #R
                        "mw_response" => $response, #R
                    ]
                ];
                $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$pay_amount,$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',false,$action_payload);
                $updateTransactionEXt = array(
                    "provider_request" =>$payload,
                    "mw_response" => json_encode($response),
                    'mw_request' => json_encode($client_response->requestoclient),
                    'client_response' => json_encode($client_response->fundtransferresponse),
                    'transaction_detail' => 'success',
                    'general_details' => 'success',
            );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
            //Helper::saveLog('Oryx Gaming Win success', $this->provider_db_id, $payload, $response);
                return $response;
            }else{
                $http_status = 402;
                $response = [
                    "responseCode" =>  "TOKEN_NOT_VALID",
                    "errorDescription" => "Token provided in request not valid in Wallet."
                ];
    
                return $response;

            }
           
    }
	
	public function gameTransactionV2(Request $request) 
	{
		$hit_time = microtime(true);
		Helper::saveLog('ORYX GAMETRAN v2', 18, file_get_contents("php://input"), 'ENDPOINT HIT');
		$json_data = json_decode(file_get_contents("php://input"), true);

		// if($this->_isIdempotent($json_data['transactionId'], true)) {
		// 	$http_status = 409;
		// 		$response = [
		// 					"responseCode" =>  "ERROR",
		// 					"errorDescription" => "This transaction is already processed."
		// 				];

		// 	return response()->json($response, $http_status);
		// }

		# Insert Idenpotent -RIAN
		try{
			ProviderHelper::idenpotencyTable($this->prefix.'_'.$json_data['transactionId']);
		}catch(\Exception $e){
			$http_status = 409;
			$response = [
				"responseCode" =>  "ERROR",
				"errorDescription" => "This transaction is already processed."
			];
			return response()->json($response, $http_status);
		}

		if(!CallParameters::check_keys($json_data, 'playerId', 'gameCode', 'action', 'sessionToken')) {
				$http_status = 401;
				$response = [
							"responseCode" =>  "REQUEST_DATA_FORMAT",
							"errorDescription" => "Data format of request not as expected."
						];
		}
		else
		{
			$http_status = 402;
			$response = [
							"responseCode" =>  "TOKEN_NOT_VALID",
							"errorDescription" => "Token provided in request not valid in Wallet."
						];

			$client_details = ProviderHelper::getClientDetails('player_id', $json_data['playerId']);

			if ($client_details) {

				$client = new Client([
				    'headers' => [ 
				    	'Content-Type' => 'application/json',
				    	'Authorization' => 'Bearer '.$client_details->client_access_token
				    ]
				]);

				if ($json_data["action"] == "CANCEL") {
					$game_transaction = GameTransaction::find($json_data['transactionId']);

					// If transaction is not found
					if(!$game_transaction) {
						$http_status = 408;
						$response = [
										"responseCode" =>  "TRANSACTION_NOT_FOUND",
										"errorDescription" => "Transaction provided by Oryx Hub was not found in platform (interesting for TransactionChange method)"
									];

						//GENERATE A CANCELLED TRANSACTION
						$json_data['roundid'] = $json_data['roundId'];
						$json_data['transid'] = $json_data['transactionId'];
						$json_data['amount'] = 0;
						$json_data['reason'] = 'Generated cancelled transaction (ORYX)';
						$json_data['income'] = 0;

						$game_details = Game::find($json_data["gameCode"], config("providerlinks.oryx.PROVIDER_ID"));
						$game_transaction_id = GameTransaction::save('cancelled', $json_data, $game_details, $client_details, $client_details);
					
						$game_trans_ext_id = ProviderHelper::createGameTransExtV2($game_transaction_id, $json_data['transactionId'], $json_data['roundId'], 0, 3);
					}
					else
					{
						// If transaction is found, send request to the client
						$json_data['roundid'] = $game_transaction->round_id;
						$json_data['income'] = 0;
						$json_data['transid'] = $game_transaction->game_trans_id;

						$game_details = Game::find($json_data["gameCode"], config("providerlinks.oryx.PROVIDER_ID"));
						
						$game_transaction_id = GameTransaction::rollbackTransaction($json_data['transactionId']);
						
						$game_trans_ext_id = ProviderHelper::createGameTransExtV2($game_transaction_id, $game_transaction->provider_trans_id, $game_transaction->round_id, $game_transaction->bet_amount, 3);
						
						// change $json_data['roundId'] to $game_transaction_id
               			$client_response = ClientRequestHelper::fundTransfer($client_details, $game_transaction->bet_amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, 'credit', true);
               			
						// If client returned a success response
						if($client_response->fundtransferresponse->status->code == "200") {
							ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance); // -RIAN
							$http_status = 200;
								$response = [
									"responseCode" => "OK",
									"balance" => $this->_toPennies($client_response->fundtransferresponse->balance),
								];

						}

						ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, $json_data, $response, $client_response->requestoclient, $client_response, $json_data);
					}
				}
				
			}
		}
		$stoptime  = microtime(true);
		$overall_time = ($stoptime - $hit_time) * 1000;
		Helper::saveLog('ORYX GT2 - TIME - '.floor($overall_time), 18, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);
	}

	public function roundFinished(Request $request) 
	{
		Helper::saveLog('roundFinished', 18, file_get_contents("php://input"), 'ENDPOINT HIT');
		$json_data = json_decode(file_get_contents("php://input"), true);
		$client_code = RouteParam::get($request, 'brand_code');
		$player_id = RouteParam::get($request, 'player_id');

		if(!CallParameters::check_keys($json_data, 'freeRoundId', 'playerId')) {
				$http_status = 401;
				$response = [
							"responseCode" =>  "REQUEST_DATA_FORMAT",
							"errorDescription" => "Data format of request not as expected."
						];
		}
		else
		{
			$http_status = 402;
			$response = [
							"responseCode" =>  "TOKEN_NOT_VALID",
							"errorDescription" => "Token provided in request not valid in Wallet."
						];

			// Find the player and client details
			$client_details = ProviderHelper::getClientDetails('player_id', $json_data['playerId']);

			if ($client_details) {

					$response = [
						"responseCode" => "OK",
						"balance" => $this->_toPennies($client_details->balance)
					];

					// $client_response = ClientRequestHelper::playerDetailsCall($client_details->player_token);
					
					// if(isset($client_response->playerdetailsresponse->status->code) 
					// && $client_response->playerdetailsresponse->status->code == "200") {
					// 	$http_status = 200;
					// 	$response = [
					// 		"responseCode" => "OK",
					// 		"balance" => $this->_toPennies($client_response->playerdetailsresponse->balance)
					// 	];
					// }
				/*}*/
			}
		}

		Helper::saveLog('oryx_round_finish', 18, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);

	}


	private function _getClientDetails($type = "", $value = "") {
		$query = DB::table("clients AS c")
				 ->select('p.client_id', 'p.player_id', 'p.client_player_id', 'p.username', 'p.email', 'p.language', 'c.default_currency', 'c.default_currency AS currency', 'pst.token_id', 'pst.player_token' , 'pst.status_id', 'p.display_name', 'c.client_api_key', 'cat.client_token AS client_access_token', 'ce.player_details_url', 'ce.fund_transfer_url')
				 ->leftJoin("players AS p", "c.client_id", "=", "p.client_id")
				 ->leftJoin("player_session_tokens AS pst", "p.player_id", "=", "pst.player_id")
				 ->leftJoin("client_endpoints AS ce", "c.client_id", "=", "ce.client_id")
				 ->leftJoin("client_access_tokens AS cat", "c.client_id", "=", "cat.client_id");
				 
				if ($type == 'token') {
					$query->where([
				 		["pst.player_token", "=", $value],
				 		["pst.status_id", "=", 1]
				 	]);
				}

				if ($type == 'player_id') {
					$query->where([
				 		["p.player_id", "=", $value],
				 		["pst.status_id", "=", 1]
				 	]);
				}

				 $result= $query->first();

		return $result;

	}

	private function _isIdempotent($transaction_id, $is_rollback = false) {
		$result = false;
		$query = DB::table('game_transaction_ext')
								->where('provider_trans_id', $transaction_id);
		if ($is_rollback == true) {
					$query->where([
				 		["game_transaction_type", "=", 3]
				 	]);
				}

		$transaction_exist = $query->first();

		if($transaction_exist) {
			$result = $transaction_exist;
		}

		return $result;								
	}

	private function _isCancelled($transaction_id, $is_rollback = false) {
		$result = false;
		$query = DB::table('game_transactions')
								->where('provider_trans_id', $transaction_id)
								->where('entry_id', 3);

		$transaction_cancelled = $query->first();

		if($transaction_cancelled) {
			$result = $transaction_cancelled;
		}

		return $result;								
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


	public function readWriteProcess(Request $request){
		// sleep(5);
		$response = [];
		// $data = file_get_contents("php://input");
		$details = json_decode(file_get_contents("php://input"), true);
		
		// $details = $request->all();
		// Helper::saveLog('readWriteProcess '. $details["type"], 18, json_encode($details), $response);
		$client_details = ProviderHelper::getClientDetails('token', $details["token"]);
		
		$provider_request = $details["provider_request"];
		$game_details = Game::find($provider_request["gameCode"], config("providerlinks.oryx.PROVIDER_ID"));
		if ($details["type"] == "debit") {
			$amount = $provider_request["amount"];
			$game_transaction_id = $details["game_trans_id"];
			$game_trans_ext_id =  $details["game_trans_ext_id"];
		} else {
			$amount = $provider_request["amount"];
			$game_transaction_id = GameTransaction::update('credit', $provider_request, $game_details, $client_details, $client_details);
			$game_trans_ext_id = ProviderHelper::createGameTransExtV2($game_transaction_id, $provider_request['win']['transactionId'], $provider_request['roundId'],$amount, 2,$provider_request);
		}

		$body_details = [
			'token' => $client_details->player_token,
			'type' => $details["type"],
			'rollback'=> false,
			"game_trans_id" => $game_transaction_id,
			"game_trans_ext_id" => $game_trans_ext_id,
			'provider_request' => $provider_request
		];
		try{
	 		$client = new Client();
	 		$guzzle_response = $client->post($this->middleware_api . '/api/oryx/fundTransfer',
	 			[ 'body' => json_encode($body_details), 'timeout' => '0.20']
	 		);
       	} catch(\Exception $e){
       		// Helper::saveLog('readWriteProcess passing_data', 18, json_encode($details), $body_details);
       	}

	}

	public function fundTransfer(Request $request){
		// sleep(5);
		$response = [];
		// $data = file_get_contents("php://input");
		$details = json_decode(file_get_contents("php://input"), true);
		
		// $details = $request->all();
		// Helper::saveLog('fundTransfer hit', 18, json_encode($details), $response);
		$client_details = ProviderHelper::getClientDetails('token', $details["token"]);
		
		$provider_request = $details["provider_request"];
		$game_details = Game::find($provider_request["gameCode"], config("providerlinks.oryx.PROVIDER_ID"));
		$amount = $provider_request["amount"];
		$game_trans_ext_id = $details["game_trans_ext_id"];
		$game_transaction_id = $details["game_trans_id"];

		$general_details = ["aggregator" => [], "provider" => [], "client" => []];
		try {
			$client_response = ClientRequestHelper::fundTransfer($client_details, $amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, $details["type"]);
		} catch (\Exception $e) {
   			$response = [
				"responseCode" =>  "OUT_OF_MONEY",
				"errorDescription" => "Player ran out of money.",
				"balance" => 0
			];
			ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, 'FAILED', $response, 'FAILED', 'FAILED', 'FAILED', 'FAILED');
			ProviderHelper::updateGameTransactionStatus($game_transaction_id, 2, 99);
			$mw_payload = ProviderHelper::fundTransfer_requestBody($client_details,$amount,$game_details->game_code,$game_details->game_name,$game_trans_ext_id,$game_transaction_id,$details["type"]);
			ProviderHelper::createRestrictGame($game_details->game_id, $client_details->player_id, $game_trans_ext_id, $mw_payload);
			Helper::saveLog('fundTransfer error credit', 18, json_encode($details), $e);
			// Helper::saveLog('fundTransfer FATAL ERROR', 18, json_encode($details),Helper::datesent());
			return $response;
		}
	     
	    if(isset($client_response->fundtransferresponse->status->code) && $client_response->fundtransferresponse->status->code == "200") 
	    {
			// updateting balance
			// ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance); 
			// Helper::saveLog('update balance hit', 18, json_encode($client_response), $client_response->fundtransferresponse->balance);

			if(array_key_exists("roundAction", $provider_request)) {
				if ($provider_request["roundAction"] == "CLOSE") {
					GameRound::end($provider_request['roundId']);
				}
			}
			$response = [
				"responseCode" => "OK",
				"balance" => $this->_toPennies($client_response->fundtransferresponse->balance),
			];
			$this->updateGameTransactionExt($game_trans_ext_id,$client_response->requestoclient,$response,$client_response->fundtransferresponse);
			// Helper::saveLog('fundTransfer done', 18,  json_encode($details), $response);

		} 
		elseif (isset($client_response->fundtransferresponse->status->code) && $client_response->fundtransferresponse->status->code == "402")
		// else
		{
			ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance); 
			$response = [
				"responseCode" =>  "OUT_OF_MONEY",
				"errorDescription" => "Player ran out of money.",
				"balance" => $this->_toPennies($client_response->fundtransferresponse->balance)
			];
			$this->updateGameTransactionExt($game_trans_ext_id,$client_response->requestoclient,$response,$client_response->fundtransferresponse);
			ProviderHelper::updateGameTransactionStatus($game_transaction_id, 2, 99);
			// ProviderHelper::createRestrictGame($game_details->game_id, $client_details->player_id, $game_trans_ext_id, $client_response->requestoclient);
			// Helper::saveLog('fundTransfer 402', 18, json_encode($details),Helper::datesent());
		}             
		
	}

	public static function updateGameTransactionExt($gametransextid,$mw_request,$mw_response,$client_response){
		$gametransactionext = array(
			"mw_request"=>json_encode($mw_request),
			"mw_response" =>json_encode($mw_response),
			"client_response" =>json_encode($client_response),
			"transaction_detail" => "success",
		);
		DB::table('game_transaction_ext')->where("game_trans_ext_id",$gametransextid)->update($gametransactionext);
	}
}
