<?php

namespace App\Http\Controllers;

use App\Models\PlayerDetail;
use App\Models\PlayerSessionToken;
use App\Helpers\Helper;
use App\Helpers\GameSubscription;
use App\Helpers\GameRound;
use App\Helpers\Game;
use App\Helpers\CallParameters;
use App\Helpers\PlayerHelper;
use App\Helpers\TokenHelper;
use App\Helpers\ProviderHelper;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransaction;
use App\Models\GameTransactionMDB;

use App\Support\RouteParam;

use Illuminate\Http\Request;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;

use DB;

class VivoController extends Controller
{
    public function __construct(){
    	$this->provider_db_id = config("providerlinks.vivo.PROVIDER_ID");
	}

	public function authPlayer(Request $request)
	{
		$client_details = ProviderHelper::getClientDetails('token', $request->token);
		
		header("Content-type: text/xml; charset=utf-8");
			$response = '<?xml version="1.0" encoding="utf-8"?>';
			$response .= '<VGSSYSTEM>
							<REQUEST>
								<TOKEN>'.$request->token.'</TOKEN>
								<HASH>'.$request->hash.'</HASH>
							</REQUEST>
							<TIME>'.Helper::datesent().'</TIME>
							<RESPONSE>
								<RESULT>FAILED</RESULT>
								<CODE>400</CODE>
							</RESPONSE>
						</VGSSYSTEM>';

		$hash = md5($request->token.config("providerlinks.vivo.PASS_KEY"));

		if($hash != $request->hash) {
			header("Content-type: text/xml; charset=utf-8");
			$response = '<?xml version="1.0" encoding="utf-8"?>';
			$response .= '<VGSSYSTEM>
							<REQUEST>
								<TOKEN>'.$request->token.'</TOKEN>
								<HASH>'.$request->hash.'</HASH>
							</REQUEST>
							<TIME>'.Helper::datesent().'</TIME>
							<RESPONSE>
								<RESULT>FAILED</RESULT>
								<CODE>500</CODE>
							</RESPONSE>
						</VGSSYSTEM>';
		}
		else
		{
			if ($client_details) {
				header("Content-type: text/xml; charset=utf-8");
			 		$response = '<?xml version="1.0" encoding="utf-8"?>';
			 		$response .= '<VGSSYSTEM>
			 						<REQUEST>
				 						<TOKEN>'.$request->token.'</TOKEN>
				 						<HASH>'.$request->hash.'</HASH>
			 						</REQUEST>
			 						<TIME>'.Helper::datesent().'</TIME>
			 						<RESPONSE>
			 							<RESULT>OK</RESULT>
			 							<USERID>'.$client_details->player_id.'</USERID>
			 							<USERNAME>'.$client_details->username.'</USERNAME>
			 							<FIRSTNAME></FIRSTNAME>
			 							<LASTNAME></LASTNAME>
			 							<EMAIL>'.$client_details->email.'</EMAIL>
			 							<CURRENCY>'.$client_details->default_currency.'</CURRENCY>
			 							<BALANCE>'.$client_details->balance.'</BALANCE>
			 							<GAMESESSIONID></GAMESESSIONID>
			 						</RESPONSE>
			 					</VGSSYSTEM>';
			}
			
		}

		/*Helper::errorDebug('vivo_authentication', config("providerlinks.vivo.PROVIDER_ID"), json_encode($request->all()), $response);*/
		echo $response;
	}

	public function gameTransaction(Request $request) 
	{
		$json_data = $request->all();
		$client_code = RouteParam::get($request, 'brand_code');
		
		if($this->_isIdempotent($request->TransactionID)) {
			header("Content-type: text/xml; charset=utf-8");
			return '<?xml version="1.0" encoding="utf-8"?>'. $this->_isIdempotent($request->TransactionID)->mw_response;
		}
		
		$response = '';
		$response .= '<VGSSYSTEM><REQUEST><USERID>'.$request->userId.'</USERID><AMOUNT>'.$request->Amount.'</AMOUNT><TRANSACTIONID >'.$request->TransactionID.'</TRANSACTIONID><TRNTYPE>'.$request->TrnType.'</TRNTYPE><GAMEID>'.$request->gameId.'</GAMEID><ROUNDID>'.$request->roundId.'</ROUNDID><TRNDESCRIPTION>'.$request->TrnDescription.'</TRNDESCRIPTION><HISTORY>'.$request->History.'</HISTORY><ISROUNDFINISHED>'.$request->isRoundFinished.'</ISROUNDFINISHED><HASH>'.$request->hash.'</HASH></REQUEST><TIME>'.Helper::datesent().'</TIME><RESPONSE><RESULT>FAILED</RESULT><CODE>300</CODE></RESPONSE></VGSSYSTEM>';

		$client_details = ProviderHelper::getClientDetails('player_id', $request->userId);

		$hash = md5($request->userId.$request->Amount.$request->TrnType.$request->TrnDescription.$request->roundId.$request->gameId.$request->History.config("providerlinks.vivo.PASS_KEY"));

		if($hash != $request->hash) {
			
			$response = '<VGSSYSTEM><REQUEST><USERID>'.$request->userId.'</USERID><AMOUNT>'.$request->Amount.'</AMOUNT><TRANSACTIONID >'.$request->TransactionID.'</TRANSACTIONID><TRNTYPE>'.$request->TrnType.'</TRNTYPE><GAMEID>'.$request->gameId.'</GAMEID><ROUNDID>'.$request->roundId.'</ROUNDID><TRNDESCRIPTION>'.$request->TrnDescription.'</TRNDESCRIPTION><HISTORY>'.$request->History.'</HISTORY><ISROUNDFINISHED>'.$request->isRoundFinished.'</ISROUNDFINISHED><HASH>'.$request->hash.'</HASH></REQUEST><TIME>'.Helper::datesent().'</TIME><RESPONSE><RESULT>FAILED</RESULT><CODE>500</CODE></RESPONSE></VGSSYSTEM>';
		}
		else
		{
			if ($client_details) {
				$game_details = Helper::getInfoPlayerGameRound($client_details->player_token);
			/*GameRound::create($request->roundId, $client_details->token_id);*/

			/*if(!GameRound::check($request->roundId)) {
				$response = '<VGSSYSTEM><REQUEST><USERID>'.$request->userId.'</USERID><AMOUNT>'.$request->Amount.'</AMOUNT><TRANSACTIONID >'.$request->TransactionID.'</TRANSACTIONID><TRNTYPE>'.$request->TrnType.'</TRNTYPE><GAMEID>'.$request->gameId.'</GAMEID><ROUNDID>'.$request->roundId.'</ROUNDID><TRNDESCRIPTION>'.$request->TrnDescription.'</TRNDESCRIPTION><HISTORY>'.$request->History.'</HISTORY><ISROUNDFINISHED>'.$request->isRoundFinished.'</ISROUNDFINISHED><HASH>'.$request->hash.'</HASH></REQUEST><TIME>'.Helper::datesent().'</TIME><RESPONSE><RESULT>FAILED</RESULT><CODE>300</CODE></RESPONSE></VGSSYSTEM>';
			}
			else
			{*/
				if($request->TrnType == 'CANCELED_BET') {
					
					// Check if the transaction exist
					$game_transaction =  GameTransactionMDB::getGameTransactionDataByProviderTransactionIdAndEntryType($request->TransactionID, 1, $client_details);
					/*$game_transaction = ProviderHelper::findGameTransaction($request->TransactionID, 'transaction_id', 1);*/
					/*$game_transaction = GameTransaction::find($request->TransactionID);*/
					
					// If transaction is not found
					if(!$game_transaction) {
						$response = '<VGSSYSTEM><REQUEST><USERID>'.$request->userId.'</USERID><AMOUNT>'.$request->Amount.'</AMOUNT><TRANSACTIONID >'.$request->TransactionID.'</TRANSACTIONID><TRNTYPE>'.$request->TrnType.'</TRNTYPE><GAMEID>'.$request->gameId.'</GAMEID><ROUNDID>'.$request->roundId.'</ROUNDID><TRNDESCRIPTION>'.$request->TrnDescription.'</TRNDESCRIPTION><HISTORY>'.$request->History.'</HISTORY><ISROUNDFINISHED>'.$request->isRoundFinished.'</ISROUNDFINISHED><HASH>'.$request->hash.'</HASH></REQUEST><TIME>'.Helper::datesent().'</TIME><RESPONSE><RESULT>FAILED</RESULT><CODE>300</CODE></RESPONSE></VGSSYSTEM>';
					}
					else
					{
						// If transaction is found, send request to the client
						if ($game_transaction->win == 2) {
							return response()->json($response, $http_status);
						}

						// initial check
						// $game_details = Game::find($request->gameId, $this->provider_db_id);

						// if (!$game_details) {
						// 	//check if vivo active table
						// 	$game_details = Game::find($request->TrnDescription, $this->provider_db_id);
						// }
						
						/*$win_or_lost = 4;
			            $entry_id = 2;
			           	$income = $game_transaction->bet_amount -  $game_transaction->bet_amount ;*/

			           	$update_game_transaction = array(
		                    "win" => 4,
		                    "pay_amount" => $game_transaction->amount,
		                    "income" => 0,
		                    "entry_id" => 2
		                );

			           	/*ProviderHelper::updateGameTransactionV2Credit($game_transaction->game_trans_id, $game_transaction->bet_amount, $income, $win_or_lost, $entry_id, "game_trans_id", 4);*/
			           	GameTransactionMDB::updateGametransaction($update_game_transaction, $game_transaction->game_trans_id, $client_details);

			           	/*$game_trans_ext_id = ProviderHelper::createGameTransExtV2($game_transaction->game_trans_id, $request->TransactionID, $request->roundId, $game_transaction->bet_amount, 3);*/
			           	$refund_game_transaction_ext = array(
		                    "game_trans_id" => $game_transaction->game_trans_id,
		                    "provider_trans_id" => $request->TransactionID,
		                    "round_id" => $request->roundId,
		                    "amount" => $game_transaction->amount,
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
									ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
									
									$response = '<VGSSYSTEM><REQUEST><USERID>'.$request->userId.'</USERID><AMOUNT>'.$request->Amount.'</AMOUNT><TRANSACTIONID>'.$request->TransactionID.'</TRANSACTIONID><TRNTYPE>'.$request->TrnType.'</TRNTYPE><GAMEID>'.$request->gameId.'</GAMEID><ROUNDID>'.$request->roundId.'</ROUNDID><TRNDESCRIPTION>'.$request->TrnDescription.'</TRNDESCRIPTION><HISTORY>'.$request->History.'</HISTORY><ISROUNDFINISHED>'.$request->isRoundFinished.'</ISROUNDFINISHED><HASH>'.$request->hash.'</HASH></REQUEST><TIME>'.Helper::datesent().'</TIME><RESPONSE><RESULT>OK</RESULT><ECSYSTEMTRANSACTIONID>'.$game_transaction->game_trans_id.'</ECSYSTEMTRANSACTIONID><BALANCE>'.$client_response->fundtransferresponse->balance.'</BALANCE></RESPONSE></VGSSYSTEM>';	
									$data_to_update = array(
				                        "mw_response" => json_encode($response)
				                    );

				                    GameTransactionMDB::updateGametransactionEXT($data_to_update, $game_trans_ext_id, $client_details);

									break;
							}

						}

							
						}
					}
					else
					{
						if($request->TrnType == 'BET') {
							Helper::saveLog('Vivo Gaming BET', 34,json_encode($request->all()), 'HIT Bet process');
							try{
								ProviderHelper::idenpotencyTable($request->TransactionID);
							}catch(\Exception $e){
								$response = [
									"errorCode" =>  10209,
									"message" => "Transaction id exists!",
								];
								return $response;
							}

							$response = [
								"errorCode" =>  10100,
								"message" => "Server is not ready!",
							];


							$json_data['income'] = $request->Amount;
							$json_data['roundid'] = $request->roundId;
							$json_data['transid'] = $request->TransactionID;
							
							// initial check
							// $game_details = Game::find($request->gameId, $this->provider_db_id);

							// if (!$game_details) {
							// 	//check if vivo active table
							// 	$game_details = Game::find($request->TrnDescription, $this->provider_db_id);
							// }
							$bet_transaction = GameTransactionMDB::getGameTransactionByRoundId($request->roundId, $client_details);
							if($bet_transaction == null){
								$gameTransactionData = array(
						            "provider_trans_id" => $request->TransactionID,
						            "token_id" => $client_details->token_id,
						            "game_id" => $game_details->game_id,
						            "round_id" => $request->roundId,
						            "bet_amount" => $request->Amount,
						            "win" => 5,
						            "pay_amount" => 0,
						            "income" => 0,
						            "entry_id" => 1,
						        );

						        /*$game_transaction_id = GameTransaction::createGametransaction($gameTransactionData);*/
						        $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
						    }else{
						    	$updateGameTransaction = [
		                            "bet_amount" => $bet_transaction->bet_amount + $request->Amount,
		                        ];
		                        GameTransactionMDB::updateGametransaction($updateGameTransaction, $checkTransaction->game_trans_id, $client_details);
		                        $game_transaction_id = $bet_transaction->game_trans_id;
						    }

					        $bet_game_transaction_ext = array(
								"game_trans_id" => $game_transaction_id,
								"provider_trans_id" => $request->TransactionID,
								"round_id" => $request->roundId,
								"amount" => $request->Amount,
								"game_transaction_type" => 1,
								"provider_request" => json_encode($json_data),
							);

					        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($bet_game_transaction_ext, $client_details); 
							/*$game_trans_ext_id = ProviderHelper::createGameTransExtV2($game_transaction_id, $request->TransactionID, $request->roundId, $request->Amount, 1, $json_data);*/
							
							$fund_extra_data = [
			                    'provider_name' => $game_details->provider_name
			                ];

					        $client_response = ClientRequestHelper::fundTransfer($client_details, $request->Amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, 'debit', false, $fund_extra_data);
							
							if (isset($client_response->fundtransferresponse->status->code)) {
								switch ($client_response->fundtransferresponse->status->code) {
									case '200':
										ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
										$response = '<VGSSYSTEM><REQUEST><USERID>'.$request->userId.'</USERID><AMOUNT>'.$request->Amount.'</AMOUNT><TRANSACTIONID>'.$request->TransactionID.'</TRANSACTIONID><TRNTYPE>'.$request->TrnType.'</TRNTYPE><GAMEID>'.$request->gameId.'</GAMEID><ROUNDID>'.$request->roundId.'</ROUNDID><TRNDESCRIPTION>'.$request->TrnDescription.'</TRNDESCRIPTION><HISTORY>'.$request->History.'</HISTORY><ISROUNDFINISHED>'.$request->isRoundFinished.'</ISROUNDFINISHED><HASH>'.$request->hash.'</HASH></REQUEST><TIME>'.Helper::datesent().'</TIME><RESPONSE><RESULT>OK</RESULT><ECSYSTEMTRANSACTIONID>'.$game_transaction_id.'</ECSYSTEMTRANSACTIONID><BALANCE>'.$client_response->fundtransferresponse->balance.'</BALANCE></RESPONSE></VGSSYSTEM>';
										
										$data_to_update = array(
					                        "mw_response" => json_encode($response)
					                    );
					                    GameTransactionMDB::updateGametransactionEXT($data_to_update, $game_trans_ext_id, $client_details);

										break;
									case '402':
										
										$response = '<VGSSYSTEM><REQUEST><USERID>'.$request->userId.'</USERID><AMOUNT>'.$request->Amount.'</AMOUNT><TRANSACTIONID >'.$request->TransactionID.'</TRANSACTIONID><TRNTYPE>'.$request->TrnType.'</TRNTYPE><GAMEID>'.$request->gameId.'</GAMEID><ROUNDID>'.$request->roundId.'</ROUNDID><TRNDESCRIPTION>'.$request->TrnDescription.'</TRNDESCRIPTION><HISTORY>'.$request->History.'</HISTORY><ISROUNDFINISHED>'.$request->isRoundFinished.'</ISROUNDFINISHED><HASH>'.$request->hash.'</HASH></REQUEST><TIME>'.Helper::datesent().'</TIME><RESPONSE><RESULT>FAILED</RESULT><CODE>300</CODE></RESPONSE></VGSSYSTEM>';
										try {
					                        $data = array(
					                            "win"=> 2,
					                            "transaction_reason" => "FAILED Due to low balance or Client Server Timeout"
					                        );

					                        GameTransactionMDB::updateGametransaction($data, $game_transaction_id, $client_details);
					                        $data_to_update = array(
					                            "mw_response" => json_encode($response)
					                        );
					                        GameTransactionMDB::updateGametransactionEXT($data_to_update, $game_trans_ext_id, $client_details);
					                    } catch(\Exception $e) {
					                        /*Helper::saveLog('betGameInsuficient(ICG)', 12, json_encode($e->getMessage().' '.$e->getLine()), $client_response->fundtransferresponse->status->message);*/
					                    } 

										break;
										Helper::saveLog('Vivo Gaming BET', 34,json_encode($request->all()), json_encode($response));
								}

							}

						}

						elseif($request->TrnType == 'WIN') {
							Helper::saveLog('Vivo Gaming WIN', 34,json_encode($request->all()), 'HIT Win process');
							try{
								ProviderHelper::idenpotencyTable($request->TransactionID);
							}catch(\Exception $e){
								$response = [
									"errorCode" =>  10208,
									"message" => "Transaction id is exists!",
								];
								return $response;
							}

							if($request->Amount < 0) {
								$response = [
									"errorCode" =>  10201,
									"message" => "Warning value must not be less 0.",
								];
							}
							else
							{
								
								// initial check
								// $game_details = Game::find($request->gameId, $this->provider_db_id);

								// if (!$game_details) {
								// 	//check if vivo active table
								// 	$game_details = Game::find($request->TrnDescription, $this->provider_db_id);
								// }

								$bet_transaction = GameTransactionMDB::getGameTransactionByRoundId($request->roundId, $client_details);
								/*$bet_transaction = ProviderHelper::findGameTransaction($request->roundId, 'round_id', 1);*/
	
								$winbBalance = $client_details->balance + $request->Amount;
								ProviderHelper::_insertOrUpdate($client_details->token_id, $winbBalance); 
								
								$response = '<VGSSYSTEM><REQUEST><USERID>'.$request->userId.'</USERID><AMOUNT>'.$request->Amount.'</AMOUNT><TRANSACTIONID>'.$request->TransactionID.'</TRANSACTIONID><TRNTYPE>'.$request->TrnType.'</TRNTYPE><GAMEID>'.$request->gameId.'</GAMEID><ROUNDID>'.$request->roundId.'</ROUNDID><TRNDESCRIPTION>'.$request->TrnDescription.'</TRNDESCRIPTION><HISTORY>'.$request->History.'</HISTORY><ISROUNDFINISHED>'.$request->isRoundFinished.'</ISROUNDFINISHED><HASH>'.$request->hash.'</HASH></REQUEST><TIME>'.Helper::datesent().'</TIME><RESPONSE><RESULT>OK</RESULT><ECSYSTEMTRANSACTIONID>'.$bet_transaction->game_trans_id.'</ECSYSTEMTRANSACTIONID><BALANCE>'.$winbBalance.'</BALANCE></RESPONSE></VGSSYSTEM>';
								
					            /*$win_or_lost = $request->Amount > 0 ?  1 : 0;
					            $entry_id = $request->Amount > 0 ?  2 : 1;
					           	$income = $bet_transaction->bet_amount -  $request->Amount ;

					           	ProviderHelper::updateGameTransactionV2Credit($bet_transaction->game_trans_id, $request->Amount, $income, $win_or_lost, $entry_id, "game_trans_id", 2);*/

					           	$update_game_transaction = array(
				                    "win" => 5,
				                    "pay_amount" => $bet_transaction->pay_amount + $request->Amount,
				                    "income" => $bet_transaction->income - $request->Amount,
				                    "entry_id" => $request->Amount == 0 && $bet_transaction->pay_amount == 0 ? 1 : 2,
				                );

				                GameTransactionMDB::updateGametransaction($update_game_transaction, $bet_transaction->game_trans_id, $client_details);

				                /*$game_trans_ext_id = ProviderHelper::createGameTransExtV2($bet_transaction->game_trans_id, $request->TransactionID, $request->roundId, $request->Amount, 2,$json_data, $response);*/

				                $win_game_transaction_ext = array(
				                    "game_trans_id" => $bet_transaction->game_trans_id,
				                    "provider_trans_id" => $request->TransactionID,
				                    "round_id" => $request->roundId,
				                    "amount" => $request->Amount,
				                    "game_transaction_type"=> 2,
				                    "provider_request" =>json_encode($json_data),
				                    "mw_response" => json_encode($response)
				                );		                

								$game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($win_game_transaction_ext, $client_details);

								$action_payload = [
					                "type" => "custom", #genreral,custom :D # REQUIRED!
					                "custom" => [
					                    "provider" => 'VivoGaming',
					                    "game_trans_ext_id" => $game_trans_ext_id,
			                    		"client_connection_name" => $client_details->connection_name
					                ],
					                "provider" => [
					                    "provider_request" => $json_data, #R
					                    "provider_trans_id"=> $request->TransactionID, #R
					                    "provider_round_id"=> $request->roundId, #R
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

					            $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$request->Amount,$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',false,$action_payload);
					            Helper::saveLog('Vivo Gaming WIN', 34,json_encode($request->all()), json_encode($response));

							}

						}
					}
				}
			/*}*/
		}

		$transactiontype = ($request->TrnType == 'BET' ? "debit" : "credit");
		Helper::errorDebug('vivo_'.$transactiontype, config("providerlinks.vivo.PROVIDER_ID"), json_encode($request->all()), $response);


		header("Content-type: text/xml; charset=utf-8");
		$final_response =  '<?xml version="1.0" encoding="utf-8"?>';
		$final_response .= $response;
		echo $final_response;

	}

	public function transactionStatus(Request $request) 
	{
		$json_data = json_decode(file_get_contents("php://input"), true);

		header("Content-type: text/xml; charset=utf-8");
		$response = '<?xml version="1.0" encoding="utf-8"?>';
		$response .= '<VGSSYSTEM>
						<REQUEST>
							<USERID>'.$request->userId.'</USERID>
							<HASH>'.$request->hash.'</HASH>
						</REQUEST>
						<TIME>'.Helper::datesent().'</TIME>
						<RESPONSE>
							<RESULT>FAILED</RESULT>
							<CODE>302</CODE>
						</RESPONSE>
					</VGSSYSTEM>';

		$client_details = ProviderHelper::getClientDetails('player_id', $request->userId);

		$hash = md5($request->userId.$request->casinoTransactionId.config("providerlinks.vivo.PASS_KEY"));

		if($hash != $request->hash || $client_details == NULL) {
			header("Content-type: text/xml; charset=utf-8");
			$response = '<?xml version="1.0" encoding="utf-8"?>';
			$response .= '<VGSSYSTEM>
							<REQUEST>
								<USERID>'.$request->userId.'</USERID>
								<HASH>'.$request->hash.'</HASH>
							</REQUEST>
							<TIME>'.Helper::datesent().'</TIME>
							<RESPONSE>
								<RESULT>FAILED</RESULT>
								<CODE>500</CODE>
							</RESPONSE>
						</VGSSYSTEM>';
		}
		else
		{
			
			// Check if the transaction exist
			$game_transaction = GameTransactionMDB::getGameTransactionDataByProviderTransactionId($request->casinoTransactionId, $client_details);
			/*$game_transaction = GameTransaction::getGameTransactionDataByProviderTransactionId($request->casinoTransactionId);*/

			// If transaction is not found
			if($game_transaction) {
				header("Content-type: text/xml; charset=utf-8");
				$response = '<?xml version="1.0" encoding="utf-8"?>';
				$response .= '<VGSSYSTEM>
								<REQUEST>
									<USERID>'.$request->userId.'</USERID>
									<CASINOTRANSACTIONID>'.$request->casinoTransactionId.'</CASINOTRANSACTIONID>
									<HASH>'.$request->hash.'</HASH>
								</REQUEST>
								<TIME>'.Helper::datesent().'</TIME>
								<RESPONSE>
									<RESULT>OK</RESULT>
									<ECSYSTEMTRANSACTIONID>'.$game_transaction->game_trans_ext_id.'</ECSYSTEMTRANSACTIONID>
								</RESPONSE>
							</VGSSYSTEM>';
			}

		}

		/*Helper::errorDebug('vivo_status', config("providerlinks.vivo.PROVIDER_ID"), json_encode($request->all()), $response);*/
		echo $response;

	}

	public function getBalance(Request $request) 
	{
		header("Content-type: text/xml; charset=utf-8");
		$response = '<?xml version="1.0" encoding="utf-8"?>';
		$response .= '<VGSSYSTEM>
						<REQUEST>
							<USERID>'.$request->userId.'</USERID>
							<HASH>'.$request->hash.'</HASH>
						</REQUEST>
						<TIME>'.Helper::datesent().'</TIME>
						<RESPONSE>
							<RESULT>FAILED</RESULT>
							<CODE>302</CODE>
						</RESPONSE>
					</VGSSYSTEM>';

		$client_details = ProviderHelper::getClientDetails('player_id', $request->userId);

		$hash = md5($request->userId.config("providerlinks.vivo.PASS_KEY"));

		if($hash != $request->hash) {
			header("Content-type: text/xml; charset=utf-8");
			$response = '<?xml version="1.0" encoding="utf-8"?>';
			$response .= '<VGSSYSTEM>
							<REQUEST>
								<USERID>'.$request->userId.'</USERID>
								<HASH>'.$request->hash.'</HASH>
							</REQUEST>
							<TIME>'.Helper::datesent().'</TIME>
							<RESPONSE>
								<RESULT>FAILED</RESULT>
								<CODE>500</CODE>
							</RESPONSE>
						</VGSSYSTEM>';
		}
		else
		{
			if ($client_details) {
				header("Content-type: text/xml; charset=utf-8");
				$response = '<?xml version="1.0" encoding="utf-8"?>';
				$response .= '<VGSSYSTEM>
								<REQUEST>
									<USERID>'.$request->userId.'</USERID>
									<HASH>'.$request->hash.'</HASH>
								</REQUEST>
								<TIME>'.Helper::datesent().'</TIME>
								<RESPONSE>
									<RESULT>OK</RESULT>
									<BALANCE>'.$client_details->balance.'</BALANCE>
								</RESPONSE>
							</VGSSYSTEM>';
			
			}
		}

		/*Helper::errorDebug('vivo_balance', config("providerlinks.vivo.PROVIDER_ID"), json_encode($request->all()), $response);*/
		echo $response;

	}

	private function _getClientDetails($type = "", $value = "") {
		$query = DB::table("clients AS c")
				 ->select('p.client_id', 'p.player_id', 'p.client_player_id', 'p.username', 'p.email', 'p.language', 'c.default_currency AS currency','c.default_currency', 'pst.token_id', 'pst.player_token' , 'pst.status_id', 'p.display_name', 'c.client_api_key', 'cat.client_token AS client_access_token', 'ce.player_details_url', 'ce.fund_transfer_url')
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




}
