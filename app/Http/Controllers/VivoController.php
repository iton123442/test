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

		echo $response;
	}

	public function gameTransaction(Request $request) 
	{	
		$json_data = $request->all();
		$micTime = microtime(true);
		Helper::saveLog('Vivo Gaming Requests'.$request->TrnType, 34,json_encode($request->all()), $micTime);
		$client_code = RouteParam::get($request, 'brand_code');
		
		try{
			ProviderHelper::idenpotencyTable($request->TrnType.$request->TransactionID);
		}catch(\Exception $e){
			$response = [
				"errorCode" =>  10209,
				"message" => "Transaction id exists!",
			];
			return $response;
		}

		$response = '';
		$response .= '<VGSSYSTEM><REQUEST><USERID>'.$request->userId.'</USERID><AMOUNT>'.$request->Amount.'</AMOUNT><TRANSACTIONID >'.$request->TransactionID.'</TRANSACTIONID><TRNTYPE>'.$request->TrnType.'</TRNTYPE><GAMEID>'.$request->gameId.'</GAMEID><ROUNDID>'.$request->roundId.'</ROUNDID><TRNDESCRIPTION>'.$request->TrnDescription.'</TRNDESCRIPTION><HISTORY>'.$request->History.'</HISTORY><ISROUNDFINISHED>'.$request->isRoundFinished.'</ISROUNDFINISHED><HASH>'.$request->hash.'</HASH></REQUEST><TIME>'.Helper::datesent().'</TIME><RESPONSE><RESULT>FAILED</RESULT><CODE>300</CODE></RESPONSE></VGSSYSTEM>';

		$client_details = ProviderHelper::getClientDetails('player_id', $request->userId);
		$hash = md5($request->userId.$request->Amount.$request->TrnType.$request->TrnDescription.$request->roundId.$request->gameId.$request->History.config("providerlinks.vivo.PASS_KEY"));
		if($hash != $request->hash) {
			$response = '<VGSSYSTEM><REQUEST><USERID>'.$request->userId.'</USERID><AMOUNT>'.$request->Amount.'</AMOUNT><TRANSACTIONID >'.$request->TransactionID.'</TRANSACTIONID><TRNTYPE>'.$request->TrnType.'</TRNTYPE><GAMEID>'.$request->gameId.'</GAMEID><ROUNDID>'.$request->roundId.'</ROUNDID><TRNDESCRIPTION>'.$request->TrnDescription.'</TRNDESCRIPTION><HISTORY>'.$request->History.'</HISTORY><ISROUNDFINISHED>'.$request->isRoundFinished.'</ISROUNDFINISHED><HASH>'.$request->hash.'</HASH></REQUEST><TIME>'.Helper::datesent().'</TIME><RESPONSE><RESULT>FAILED</RESULT><CODE>500</CODE></RESPONSE></VGSSYSTEM>';
		}
		$getSideBet = strpos($request->History, 'sideBet21');
		$getSideBetPair = strpos($request->History, 'sideBetPpair');
		switch ($request->TrnType){
			case "BET":
				if($getSideBet != false){
					sleep(0.5);
					Helper::saveLog('Vivo Gaming BET SideBet', 34,json_encode($request->all()), 'HIT sideBet process');
					return $this->betProcess($request->all(),$client_details);
				}
				if($getSideBetPair != false){
					sleep(1);
					Helper::saveLog('Vivo Gaming BET SideBetPair', 34,json_encode($request->all()), 'HIT sideBetPpair process');
					return $this->betProcess($request->all(),$client_details);
				}
				return $this->betProcess($request->all(),$client_details);
			break;
			case "WIN":
				$getSideBetW = strpos($request->History, 'SIDE_BET');
				$getSideBetW = strpos($request->History, 'BLACKJACK:WIN;');
				if($getSideBetW){
					sleep(0.5);
					Helper::saveLog('Vivo Gaming BET getSideBetW', 34,json_encode($request->all()), 'HIT sideBetPpair process');
					return $this->winProcess($request->all(),$client_details);
				}
				if(str_contains($request->History,'BLACKJACK:WIN;1')){
					sleep(0.5);
					return $this->winProcess($request->all(),$client_details);
				}elseif(str_contains($request->History,'BLACKJACK:WIN;2')){
					sleep(0.5);
					return $this->winProcess($request->all(),$client_details);
				}elseif(str_contains($request->History,'BLACKJACK:WIN;3')){
					sleep(0.5);
					return $this->winProcess($request->all(),$client_details);
				}elseif(str_contains($request->History,'BLACKJACK:WIN;4')){
					sleep(0.5);
					return $this->winProcess($request->all(),$client_details);
				}elseif(str_contains($request->History,'BLACKJACK:WIN;5')){
					sleep(0.5);
					return $this->winProcess($request->all(),$client_details);
				}elseif(str_contains($request->History,'BLACKJACK:WIN;6')){
					sleep(0.5);
					return $this->winProcess($request->all(),$client_details);
				}elseif(str_contains($request->History,'BLACKJACK:WIN;7')){
					sleep(0.5);
					return $this->winProcess($request->all(),$client_details);
				}
				return $this->winProcess($request->all(),$client_details);
				
			break;
			case "CANCEL_BET":
				return $this->cancelBet($request->all(),$client_details);
			break;
		}
	}

	public function cancelBet($data,$client_details)
	{
		// Check if the transaction exist
		$game_details = Helper::getInfoPlayerGameRound($client_details->player_token);
		$game_transaction =  GameTransactionMDB::findGameExt($data["roundId"], 1, "round_id",$client_details);
		if(!$game_transaction) {
			$response = '<VGSSYSTEM><REQUEST><USERID>'.$data["userId"].'</USERID><AMOUNT>'.$data["Amount"].'</AMOUNT><TRANSACTIONID >'.$data["TransactionID"].'</TRANSACTIONID><TRNTYPE>'.$data["TrnType"].'</TRNTYPE><GAMEID>'.$data["gameId"].'</GAMEID><ROUNDID>'.$data["roundId"].'</ROUNDID><TRNDESCRIPTION>'.$data["TrnDescription"].'</TRNDESCRIPTION><HISTORY>'.$data["History"].'</HISTORY><ISROUNDFINISHED>'.$data["isRoundFinished"].'</ISROUNDFINISHED><HASH>'.$data["hash"].'</HASH></REQUEST><TIME>'.Helper::datesent().'</TIME><RESPONSE><RESULT>FAILED</RESULT><CODE>300</CODE></RESPONSE></VGSSYSTEM>';
		}
		else
		{

           	$update_game_transaction = array(
                "entry_id" => 2
            );
           	GameTransactionMDB::updateGametransaction($update_game_transaction, $game_transaction->game_trans_id, $client_details);

           	$refund_game_transaction_ext = array(
                "game_trans_id" => $game_transaction->game_trans_id,
                "provider_trans_id" => $data["TransactionID"],
                "round_id" => $data["roundId"],
                "amount" => $game_transaction->amount,
                "game_transaction_type"=> 3,
                "provider_request" =>json_encode($data),
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
						
						$response = '<VGSSYSTEM><REQUEST><USERID>'.$data["userId"].'</USERID><AMOUNT>'.$data["Amount"].'</AMOUNT><TRANSACTIONID>'.$data["TransactionID"].'</TRANSACTIONID><TRNTYPE>'.$data["TrnType"].'</TRNTYPE><GAMEID>'.$data["gameId"].'</GAMEID><ROUNDID>'.$data["roundId"].'</ROUNDID><TRNDESCRIPTION>'.$data["TrnDescription"].'</TRNDESCRIPTION><HISTORY>'.$data["History"].'</HISTORY><ISROUNDFINISHED>'.$data["isRoundFinished"].'</ISROUNDFINISHED><HASH>'.$data["hash"].'</HASH></REQUEST><TIME>'.Helper::datesent().'</TIME><RESPONSE><RESULT>OK</RESULT><ECSYSTEMTRANSACTIONID>'.$game_transaction->game_trans_id.'</ECSYSTEMTRANSACTIONID><BALANCE>'.$client_response->fundtransferresponse->balance.'</BALANCE></RESPONSE></VGSSYSTEM>';	
						$data_to_update = array(
	                        "mw_response" => json_encode($response)
	                    );

	                    GameTransactionMDB::updateGametransactionEXT($data_to_update, $game_trans_ext_id, $client_details);

						break;
				}

			}
		}	
		header("Content-type: text/xml; charset=utf-8");
		$final_response =  '<?xml version="1.0" encoding="utf-8"?>';
		$final_response .= $response;
		return $final_response;
	}
	public function betProcess($data,$client_details)
	{
		$game_details = Helper::getInfoPlayerGameRound($client_details->player_token);
		Helper::saveLog('Vivo Gaming BET', 34,json_encode($data), 'HIT Bet process');
		try{
			ProviderHelper::idenpotencyTable('VIVO_'.$data["roundId"]);
			$gameTransactionData = array(
	            "provider_trans_id" => $data["TransactionID"],
	            "token_id" => $client_details->token_id,
	            "game_id" => $game_details->game_id,
	            "round_id" => $data["roundId"],
	            "bet_amount" => $data["Amount"],
	            "win" => 5,
	            "pay_amount" => 0,
	            "income" => 0,
	            "entry_id" => 1,
	        );
	        $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
		}catch(\Exception $e){
			$bet_transaction = GameTransactionMDB::getGameTransactionByRoundId($data["roundId"], $client_details);
			if($bet_transaction == null){
				$response = '<VGSSYSTEM><REQUEST><USERID>'.$data["userId"].'</USERID><AMOUNT>'.$data["Amount"].'</AMOUNT><TRANSACTIONID >'.$data["TransactionID"].'</TRANSACTIONID><TRNTYPE>'.$data["TrnType"].'</TRNTYPE><GAMEID>'.$data["gameId"].'</GAMEID><ROUNDID>'.$data["roundId"].'</ROUNDID><TRNDESCRIPTION>'.$data["TrnDescription"].'</TRNDESCRIPTION><HISTORY>'.$data["History"].'</HISTORY><ISROUNDFINISHED>'.$data["isRoundFinished"].'</ISROUNDFINISHED><HASH>'.$data["hash"].'</HASH></REQUEST><TIME>'.Helper::datesent().'</TIME><RESPONSE><RESULT>FAILED</RESULT><CODE>300</CODE></RESPONSE></VGSSYSTEM>';
				return $response;
			}
			$game_transaction_id = $bet_transaction->game_trans_id;
			$amount = $bet_transaction->bet_amount + $data["Amount"];
			$updateGameTransaction = [
            	"bet_amount" => $amount,
	        ];
	        GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_transaction_id, $client_details);
		}
		$bet_game_transaction_ext = array(
			"game_trans_id" => $game_transaction_id,
			"provider_trans_id" => $data["TransactionID"],
			"round_id" => $data["roundId"],
			"amount" => $data["Amount"],
			"game_transaction_type" => 1,
			"provider_request" => json_encode($data),
			"general_details" => $data["History"],
		);
        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($bet_game_transaction_ext, $client_details);
        // $bet = GameTransactionMDB::findGameExtVivo($game_transaction_id,1,$client_details);
        // $updateGameTransaction = [
        //     "bet_amount" => $bet->amount,
        // ];
        // GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_transaction_id, $client_details);

		$fund_extra_data = [
            'provider_name' => $game_details->provider_name
        ];
        Helper::saveLog('Vivo Gaming BET prio fundTransfer', 34,json_encode($data), $client_details->balance - $data["Amount"]);
       
        $client_response = ClientRequestHelper::fundTransfer($client_details, $data["Amount"], $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, 'debit', false, $fund_extra_data);
		if (isset($client_response->fundtransferresponse->status->code)) {
			switch ($client_response->fundtransferresponse->status->code) {
				case '200':
					ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
					$response = '<VGSSYSTEM><REQUEST><USERID>'.$data["userId"].'</USERID><AMOUNT>'.$data["Amount"].'</AMOUNT><TRANSACTIONID>'.$data["TransactionID"].'</TRANSACTIONID><TRNTYPE>'.$data["TrnType"].'</TRNTYPE><GAMEID>'.$data["gameId"].'</GAMEID><ROUNDID>'.$data["roundId"].'</ROUNDID><TRNDESCRIPTION>'.$data["TrnDescription"].'</TRNDESCRIPTION><HISTORY>'.$data["History"].'</HISTORY><ISROUNDFINISHED>'.$data["isRoundFinished"].'</ISROUNDFINISHED><HASH>'.$data["hash"].'</HASH></REQUEST><TIME>'.Helper::datesent().'</TIME><RESPONSE><RESULT>OK</RESULT><ECSYSTEMTRANSACTIONID>'.$game_transaction_id.'</ECSYSTEMTRANSACTIONID><BALANCE>'.$client_response->fundtransferresponse->balance.'</BALANCE></RESPONSE></VGSSYSTEM>';
					$data_to_update = array(
                        "mw_response" => json_encode($response),
                        "transaction_detail" => "success"
                    );
                    GameTransactionMDB::updateGametransactionEXT($data_to_update, $game_trans_ext_id, $client_details);
					break;
				case '402':
					
					$response = '<VGSSYSTEM><REQUEST><USERID>'.$data["userId"].'</USERID><AMOUNT>'.$data["Amount"].'</AMOUNT><TRANSACTIONID >'.$data["TransactionID"].'</TRANSACTIONID><TRNTYPE>'.$data["TrnType"].'</TRNTYPE><GAMEID>'.$data["gameId"].'</GAMEID><ROUNDID>'.$data["roundId"].'</ROUNDID><TRNDESCRIPTION>'.$data["TrnDescription"].'</TRNDESCRIPTION><HISTORY>'.$data["History"].'</HISTORY><ISROUNDFINISHED>'.$data["isRoundFinished"].'</ISROUNDFINISHED><HASH>'.$data["hash"].'</HASH></REQUEST><TIME>'.Helper::datesent().'</TIME><RESPONSE><RESULT>FAILED</RESULT><CODE>300</CODE></RESPONSE></VGSSYSTEM>';
					try {
                        $datas = array(
                            "win"=> 2,
                            "transaction_reason" => "FAILED Due to low balance or Client Server Timeout"
                        );

                        GameTransactionMDB::updateGametransaction($datas, $game_transaction_id, $client_details);
                        $data_to_update = array(
                            "mw_response" => json_encode($response)
                        );
                        GameTransactionMDB::updateGametransactionEXT($data_to_update, $game_trans_ext_id, $client_details);
                    } catch(\Exception $e) {
                        /*Helper::saveLog('betGameInsuficient(ICG)', 12, json_encode($e->getMessage().' '.$e->getLine()), $client_response->fundtransferresponse->status->message);*/
                        Helper::saveLog('Vivo Gaming BET', 34,json_encode($data), json_encode($response));
                    } 

					break;
					Helper::saveLog('Vivo Gaming BET', 34,json_encode($data), json_encode($response));
			}

		}
		header("Content-type: text/xml; charset=utf-8");
		$final_response =  '<?xml version="1.0" encoding="utf-8"?>';
		$final_response .= $response;
		return $final_response;
	}

	public function winProcess($data,$client_details){
		$game_details = Helper::getInfoPlayerGameRound($client_details->player_token);
		Helper::saveLog('Vivo Gaming WIN', 34,json_encode($data), "ENDPOINTHIT");
		if($data["Amount"] < 0) {
			$response = [
				"errorCode" =>  10201,
				"message" => "Warning value must not be less 0.",
			];
		}
			$bet_transaction = GameTransactionMDB::getGameTransactionByRoundId($data["roundId"], $client_details);
			if($bet_transaction == null){
				$response = '<VGSSYSTEM><REQUEST><USERID>'.$data["userId"].'</USERID><AMOUNT>'.$data["Amount"].'</AMOUNT><TRANSACTIONID >'.$data["TransactionID"].'</TRANSACTIONID><TRNTYPE>'.$data["TrnType"].'</TRNTYPE><GAMEID>'.$data["gameId"].'</GAMEID><ROUNDID>'.$data["roundId"].'</ROUNDID><TRNDESCRIPTION>'.$data["TrnDescription"].'</TRNDESCRIPTION><HISTORY>'.$data["History"].'</HISTORY><ISROUNDFINISHED>'.$data["isRoundFinished"].'</ISROUNDFINISHED><HASH>'.$data["hash"].'</HASH></REQUEST><TIME>'.Helper::datesent().'</TIME><RESPONSE><RESULT>FAILED</RESULT><CODE>300ss</CODE></RESPONSE></VGSSYSTEM>';
				return $response;
			}
			
			$winbBalance = $client_details->balance + $data["Amount"];
			ProviderHelper::_insertOrUpdate($client_details->token_id, $winbBalance); 
			
			$response = '<VGSSYSTEM><REQUEST><USERID>'.$data["userId"].'</USERID><AMOUNT>'.$data["Amount"].'</AMOUNT><TRANSACTIONID>'.$data["TransactionID"].'</TRANSACTIONID><TRNTYPE>'.$data["TrnType"].'</TRNTYPE><GAMEID>'.$data["gameId"].'</GAMEID><ROUNDID>'.$data["roundId"].'</ROUNDID><TRNDESCRIPTION>'.$data["TrnDescription"].'</TRNDESCRIPTION><HISTORY>'.$data["History"].'</HISTORY><ISROUNDFINISHED>'.$data["isRoundFinished"].'</ISROUNDFINISHED><HASH>'.$data["hash"].'</HASH></REQUEST><TIME>'.Helper::datesent().'</TIME><RESPONSE><RESULT>OK</RESULT><ECSYSTEMTRANSACTIONID>'.$bet_transaction->game_trans_id.'</ECSYSTEMTRANSACTIONID><BALANCE>'.$winbBalance.'</BALANCE></RESPONSE></VGSSYSTEM>';
			
	       	if($bet_transaction->pay_amount > 0){
	       		$win_or_lost = 1;
	        }else{
	            $win_or_lost = $data["Amount"] > 0 ?  1 : 0;
	        }
	        $win_game_transaction_ext = array(
	            "game_trans_id" => $bet_transaction->game_trans_id,
	            "provider_trans_id" => $data["TransactionID"],
	            "round_id" => $data["roundId"],
	            "amount" => $data["Amount"],
	            "game_transaction_type"=> 2,
	            "provider_request" =>json_encode($data),
	            "mw_response" => json_encode($response),
	            "general_details" => $data["History"],
	        );		                
			$game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($win_game_transaction_ext, $client_details);
			$update_game_transaction = array(
	            "win" => 5,
	            "pay_amount" => $bet_transaction->pay_amount + $data["Amount"],
	            "income" => $bet_transaction->bet_amount - $data["Amount"],
	            "entry_id" => $data["Amount"] == 0 && $bet_transaction->pay_amount == 0 ? 1 : 2,
	        );
	        GameTransactionMDB::updateGametransaction($update_game_transaction, $bet_transaction->game_trans_id, $client_details);

			$action_payload = [
	            "type" => "custom", #genreral,custom :D # REQUIRED!
	            "custom" => [
	            	"win_or_lost" => $win_or_lost,
	                "provider" => 'VivoGaming',
	                "game_trans_ext_id" => $game_trans_ext_id,
	        		"client_connection_name" => $client_details->connection_name
	            ],
	            "provider" => [
	                "provider_request" => $data, #R
	                "provider_trans_id"=> $data["TransactionID"], #R
	                "provider_round_id"=> $data["roundId"], #R
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
	        try {
        	 	$client_response = ClientRequestHelper::fundTransfer_TG($client_details,$data["Amount"],$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',false,$action_payload);
	        	Helper::saveLog('Vivo Gaming WIN', 34,json_encode($data), json_encode($response));
	        } catch (\Exception $e) {
	        	Helper::saveLog('Vivo Gaming WIN ERROR CATCHED', 34,json_encode($data), json_encode($response));
	        }
	       

			header("Content-type: text/xml; charset=utf-8");
			$final_response =  '<?xml version="1.0" encoding="utf-8"?>';
			$final_response .= $response;
			return $final_response;
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
