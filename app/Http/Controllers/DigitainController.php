<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\DigitainHelper;
use App\Helpers\ProviderHelper;
use App\Helpers\Helper;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransactionMDB;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use Carbon\Carbon;

use DB;

/**
 * 
 *  UPDATE 03-04-21
 *  @method charge
 *  @method promowin
 *  @method checkStatus
 *  @author's NOTe : Removed Early,Hold, Check Refund in Bet and Refund method
 * 
 *  UPDATED 06-27-20
 *	Api Documentation v3 -> v3.7.0-1
 *	Current State : v3 updating to v3.7.0-1 
 *  @author's NOTE: You cannot win if you dont bet! Bet comes first fellows!
 *	@author's NOTE: roundId is intentionally PREFIXED with RSG to separate from other roundid, safety first!
 *	@method refund method additionals = requests: holdEarlyRefund
 *	@method win method additionals = requests:  returnBetsAmount, bonusTicketId
 *	@method bet method additionals = requests:  checkRefunded, bonusTicketId
 *	@method betwin method additionals = requests:  bonusTicketId,   ,response: playerId, roundId, currencyId
 *	
 */
class DigitainController extends Controller
{
    // private $digitain_key = "BetRNK3184223";
    // private $operator_id = 'B9EC7C0A';
    // private $provider_db_id = 14;
    // private $provider_and_sub_name = 'Digitain'; // nothing todo with the provider


    public $digitain_key, $operator_id, $provider_db_id, $provider_and_sub_name = '';

    public function __construct(){
    	$this->digitain_key = config('providerlinks.digitain.digitain_key');
    	$this->operator_id = config('providerlinks.digitain.operator_id');
    	$this->provider_db_id = config('providerlinks.digitain.provider_db_id');
    	$this->provider_and_sub_name = config('providerlinks.digitain.provider_and_sub_name');
    }

    /**
	 *	Verify Signature
	 *	@return  [Bolean = True/False]
	 *
	 */
	public function authMethod($operatorId, $timestamp, $signature){
		$digitain_key = $this->digitain_key;
	    $operator_id = $operatorId;
	    $time_stamp = $timestamp;
	    $message = $time_stamp.$operator_id;
	    $hmac = hash_hmac("sha256", $message, $digitain_key);
		$result = false;
            if($hmac == $signature) {
			    $result = true;
            }
        return $result;
	}

	public function formatBalance($balance){
		// return formatBalance($balance);
		return floatval(number_format((float)$balance, 2, '.', ''));
	}

	/**
	 *	Create Signature
	 *	@return  [String]
	 *
	 */
	public function createSignature($timestamp){
	    $digitain_key = $this->digitain_key;
	    $operator_id = $this->operator_id;
	    $time_stamp = $timestamp;
	    $message = $time_stamp.$operator_id;
	    $hmac = hash_hmac("sha256", $message, $digitain_key);
	    return $hmac;
	}

	public function noBody(){
		return $response = [
			"timestamp" => date('YmdHisms'),
			"signature" => $this->createSignature(date('YmdHisms')),
			"errorCode" => 17 //RequestParameterMissing
		];
	}

	public function authError(){
		return $response = ["timestamp" => date('YmdHisms'),"signature" => $this->createSignature(date('YmdHisms')),"errorCode" => 12];
	}
	
	public function wrongOperatorID(){
		return $response = ["timestamp" => date('YmdHisms'),"signature" => $this->createSignature(date('YmdHisms')),"errorCode" => 15];
	}

	public function array_has_dupes($array) {
	   return count($array) !== count(array_unique($array));
	}

	/**
	 * Player Detail Request
	 * @return array [Client Player Data]
	 * 
	 */
    public function authenticate(Request $request)
    {	

		$json_data = json_decode(file_get_contents("php://input"), true);
		ProviderHelper::saveLogWithExeption('RSG authenticate - EH', $this->provider_db_id, file_get_contents("php://input"), 'ENDPOINT HIT');
		if($json_data == null){
			return $this->noBody();
		}
		if($json_data['operatorId'] != $this->operator_id){
			return $this->wrongOperatorID();
		}
		if (!$this->authMethod($json_data['operatorId'], $json_data['timestamp'], $json_data['signature'])){
			return $this->authError();
		}
		$client_details = ProviderHelper::getClientDetails('token', $json_data["token"]);	
		if ($client_details == null || $client_details == 'false'){
			$response = [
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				// "token" => $json_data['token'],
				"errorCode" => 2 // SessionNotFound
			];
			ProviderHelper::saveLogWithExeption('RSG authenticate', $this->provider_db_id, file_get_contents("php://input"), $response);
			return $response;
		}
		$token_check = DigitainHelper::tokenCheck($json_data["token"]);
		if($token_check != true){
			$response = [
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"errorCode" => 3 // SessionExpired!
			];
			ProviderHelper::saveLogWithExeption('RSG authenticate', $this->provider_db_id, file_get_contents("php://input"), $response);
			return $response;
		}
		$client_response = ProviderHelper::playerDetailsCall($client_details->player_token);
		if($client_response == 'false'){
			$response = [
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"errorCode" => 999, // client cannot be reached! http errors etc!
			];
			ProviderHelper::saveLogWithExeption('RSG authenticate', $this->provider_db_id, file_get_contents("php://input"), $response);
			return $response;
		}
		if(isset($client_response->playerdetailsresponse->status->code) &&
			     $client_response->playerdetailsresponse->status->code == "200"){

			$dob = isset($client_response->playerdetailsresponse->birthday) ? $client_response->playerdetailsresponse->birthday : '1996-03-01 00:00:00.000';
			$gender_pref = isset($client_response->playerdetailsresponse->gender) ? strtolower($client_response->playerdetailsresponse->gender) : 'male';
			$gender = ['male' => 1,'female' => 2];

			$response = [
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"errorCode" => 1,
				"playerId" => $client_details->player_id, // Player ID Here is Player ID in The MW DB, not the client!
				"userName" => $client_response->playerdetailsresponse->accountname,
				// "currencyId" => $client_response->playerdetailsresponse->currencycode,
				"currencyId" => $client_details->default_currency,
				"balance" => $this->formatBalance($client_response->playerdetailsresponse->balance),
				"birthDate" => $dob, // Optional
				"firstName" => $client_response->playerdetailsresponse->firstname, // required
				"lastName" => $client_response->playerdetailsresponse->lastname, // required
				"gender" => $gender[$gender_pref], // Optional
				"email" => $client_response->playerdetailsresponse->email,
				"isReal" => true
			];
		}
		ProviderHelper::saveLogWithExeption('RSG authenticate - SUCCESS', $this->provider_db_id, file_get_contents("php://input"), $response);
		return $response;
	}

	/**
	 * Get the player balance
	 * @author's NOTE [Error codes, 12 = Invalid Signature, 16 = invalid currency type, 999 = general error (HTTP)]
	 * @return  [<json>]
	 * 
	 */
	public function getBalance()
	{
		$json_data = json_decode(file_get_contents("php://input"), true);
		ProviderHelper::saveLogWithExeption('RSG getBalance - EH', $this->provider_db_id, file_get_contents("php://input"), 'ENDPOINT HIT');
		if($json_data == null){
			return $this->noBody();
		}
		if($json_data['operatorId'] != $this->operator_id){ //Wrong Operator Id 
			return $this->wrongOperatorID();
		}
		if(!$this->authMethod($json_data['operatorId'], $json_data['timestamp'], $json_data['signature'])){
			return $this->authError();
		}
		$client_details = ProviderHelper::getClientDetails('token', $json_data["token"]);	
		if ($client_details == null || $client_details == 'false'){
			$response = [
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"errorCode" => 2 // SessionNotFound
			];
			ProviderHelper::saveLogWithExeption('RSG getBalance', $this->provider_db_id, file_get_contents("php://input"), $response);
			return $response;
		}
		$token_check = DigitainHelper::tokenCheck($json_data["token"]);
		if($token_check != true){
			$response = [
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"errorCode" => 3 // Token is expired!
			];
			ProviderHelper::saveLogWithExeption('RSG getBalance', $this->provider_db_id, file_get_contents("php://input"), $response);
			return $response;
		}
		// $client_response = DigitainHelper::playerDetailsCall($client_details); // Object Version
		$client_response = ProviderHelper::playerDetailsCall($client_details->player_token); // General
		if($client_response == 'false'){
			$response = [
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"errorCode" => 999, // client cannot be reached! http errors etc!
			];
			return $response;
		}
		if($client_details->player_id != $json_data["playerId"]){
			$response = [
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"errorCode" => 4, 
			];
			return $response;
		}
		if($json_data["currencyId"] == $client_details->default_currency):
			$response = [
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"errorCode" => 1,
				"balance" => $this->formatBalance($client_response->playerdetailsresponse->balance),	
			];
		else:
			$response = [
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"token" => $json_data['token'],
				"errorCode" => 16, // Error Currency type
			];
		endif;
		return $response;
	}


	/**
	 * Call if Digitain wants a new token!
	 * @author's NOTE [Error codes, 12 = Invalid Signature, 999 = general error (HTTP)]
	 * @return  [<json>]
	 * 
	 */
	public function refreshtoken(){
		ProviderHelper::saveLogWithExeption('RSG refreshtoken - EH', $this->provider_db_id, file_get_contents("php://input"), 'ENDPOINT HIT');
		$json_data = json_decode(file_get_contents("php://input"), true);
		if($json_data == null){ //Wrong Operator Id 
			return $this->noBody();
		}
		if($json_data['operatorId'] != $this->operator_id){ //Wrong Operator Id 
			return $this->wrongOperatorID();
		}
		if(!$this->authMethod($json_data['operatorId'], $json_data['timestamp'], $json_data['signature'])){ 
			return $this->authError();
		}
		$client_details = ProviderHelper::getClientDetails('token', $json_data["token"]);	
		if ($client_details == null || $client_details == 'false'){ // SessionNotFound
			$response = ["timestamp" => date('YmdHisms'),"signature" => $this->createSignature(date('YmdHisms')),"errorCode" => 2];
			ProviderHelper::saveLogWithExeption('RSG refreshtoken', $this->provider_db_id, file_get_contents("php://input"), $response);
			return $response;
		}

		$token_check = DigitainHelper::tokenCheck($json_data["token"]);
		if($token_check != true){ // SessionExpired!
			$response = ["timestamp" => date('YmdHisms'),"signature" => $this->createSignature(date('YmdHisms')),"errorCode" => 3];
			ProviderHelper::saveLogWithExeption('RSG refreshtoken', $this->provider_db_id, file_get_contents("php://input"), $response);
			return $response;
		}


		if($json_data["changeToken"] == false){
			if($json_data["timestamp"] != null){
				if($json_data["tokenLifeTime"] == 0){
					DigitainHelper::increaseTokenLifeTime($json_data["tokenLifeTime"], $json_data["token"], 2);
				}else{
					DigitainHelper::increaseTokenLifeTime($json_data["tokenLifeTime"], $json_data["token"]);
				}
			}
		}

		if($json_data['changeToken']): // IF TRUE REQUEST ADD NEW TOKEN
			// $client_response = DigitainHelper::playerDetailsCall($client_details, true);
			$client_response = ProviderHelper::playerDetailsCall($client_details->player_token, true);
			if($client_response):

				DB::table('player_session_tokens')->insert(
                array('player_id' => $client_details->player_id, 
                	  'player_token' =>  $client_response->playerdetailsresponse->refreshtoken, 
                	  'status_id' => '1')
                );

				$game_details = Helper::getInfoPlayerGameRound($json_data["token"]);
				Helper::savePLayerGameRound($game_details->game_code, $client_response->playerdetailsresponse->refreshtoken, $this->provider_and_sub_name);

				$response = [
					"timestamp" => date('YmdHisms'),
					"signature" => $this->createSignature(date('YmdHisms')),
					"token" => $client_response->playerdetailsresponse->refreshtoken, // Return New Token!
					"errorCode" => 1
				];
			else:
				$response = [
					"timestamp" => date('YmdHisms'),
					"signature" => $this->createSignature(date('YmdHisms')),
					// "token" => $json_data['token'],
					"errorCode" => 999,
				];
			endif;
	 	else:
	 		$response = [
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"token" => $json_data['token'], // Return OLD Token
				"errorCode" => 1
			];
 		endif;
 		ProviderHelper::saveLogWithExeption('RSG refreshtoken', $this->provider_db_id, file_get_contents("php://input"), $response);
 		return $response;

	}


	/**
	 * @author's NOTE:
	 * allOrNone - When True, if any of the items fail, the Partner should reject all items NO LOGIC YET!
	 * checkRefunded - no logic yet
	 * ignoreExpiry - no logic yet, expiry should be handle in the refreshToken call
	 * changeBalance - no yet implemented always true (RSG SIDE)
	 * UPDATE 4 filters - Player Low Balance, Currency code dont match, already exist, The playerId was not found
	 * @author's NOTE [Error codes, 12 = Invalid Signature, 6 = Player Low Balance!, 16 = Currency code dont match, 999 = general error (HTTP), 8 = already exist, 4 = The playerId was not found]
	 * 
	 */
	 public function bet(Request $request){

	 	ProviderHelper::saveLogWithExeption('RSG bet - EH', $this->provider_db_id, file_get_contents("php://input"), 'ENDPOINT HIT');
		$json_data = json_decode(file_get_contents("php://input"), true);
		
		if($json_data == null){
			return $this->noBody();
		}
		if($json_data['operatorId'] != $this->operator_id){ //Wrong Operator Id 
			return $this->wrongOperatorID();
		}
		if(!$this->authMethod($json_data['operatorId'], $json_data['timestamp'], $json_data['signature'])){ 
			return $this->authError();
		}

		$items_array = array(); // ITEMS INFO

		# Missing Parameters
		if(!isset($json_data['providerId']) || !isset($json_data['allOrNone']) || !isset($json_data['signature']) || !isset($json_data['timestamp']) || !isset($json_data['operatorId']) || !isset($json_data['items'])){
			$response = array(
					 "timestamp" => date('YmdHisms'),
				     "signature" => $this->createSignature(date('YmdHisms')),
					 "errorCode" => 17,
					 "items" => $items_array,
   			);	
			return $response;
		}

		#ALL OR NONE AMEND
		if ($json_data['allOrNone'] == 'true') { // IF ANY ITEM FAILED DONT PROCESS IT
			return $this->betAllOrNone($request->all());
		}
		#ALL OR NONE AMEND

		$items_array = array(); // ITEMS INFO
		// $isset_before_balance = false;
		foreach ($json_data['items'] as $key){
			$general_details = ["aggregator" => [],"provider" => [],"client" => []];

			# Missing Parameters
			if(!isset($key['info']) || !isset($key['txId']) || !isset($key['betAmount']) || !isset($key['token']) || !isset($key['playerId']) || !isset($key['roundId']) || !isset($key['gameId'])){
				$items_array[] = [
					"info" => $key['info'], 
					"errorCode" => 17, 
					"metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
        	    ]; 
				continue;
			}
			// Provider Details Logger
			$general_details['provider']['operationType'] = $key['operationType'];
			$general_details['provider']['currencyId'] = $key['currencyId'];
			$general_details['provider']['amount'] = $key['betAmount'];
			$general_details['provider']['txCreationDate'] = $json_data['timestamp'];
			$general_details['provider']['txId'] = $key['txId'];
			// Provider Details Logger

			# if bet operation type is not in the bet operation types
			if ($this->getBetWinOpType($key['operationType']) == false) {
				$items_array[] = array(
					"info" => $key['info'],
					"errorCode" => 19, // error operation type for this bet not in the bet operation types
					"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
				);
				continue;
			}

			# if bet is in the change balance 0 oepration type table make the amount zero
			if ($this->betWithNoChangeBalanceOT($key['operationType'])) {
				if ($key['changeBalance'] == true) {
					$items_array[] = array(
						"info" => $key['info'],
						"errorCode" => 19, // error operation type
						"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
					);
					continue;
				} else {
					$key['betAmount'] = 0;  // make the bet amount zero (free bets/artificial bets)
				}
			}

			$is_exist_gameid = $this->getGameId($key["gameId"]);
			if($is_exist_gameid == false){
				$items_array[] = [
					 "info" => $key['info'], 
					 "errorCode" => 11, 
					 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
        	    ]; 
        	    continue;
			}
			$key["gameId"] = $is_exist_gameid; // Overwrite GameId

			$game_details = ProviderHelper::findGameDetails('game_code', $this->provider_db_id, $key["gameId"]);
			if($game_details == null){ // Game not found
				$items_array[] = [
					 "info" => $key['info'], 
					 "errorCode" => 11, 
					 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
        	    ]; 
        	    continue;
			}
			$client_details = ProviderHelper::getClientDetails('token', $key["token"]);	
			if($client_details == null || $client_details == 'false'){ // SessionNotFound
				$items_array[] = [
					 "info" => $key['info'], 
					 "errorCode" => 2, 
					 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
        	    ];  
				continue;
			}
			if($client_details != null){ // SessionNotFound
				if($client_details->player_id != $key["playerId"]){
					$items_array[] = [
						 "info" => $key['info'], 
						 "errorCode" => 4, 
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
	        	    ];  
	        	    continue;
				}
				// $client_player = DigitainHelper::playerDetailsCall($client_details);
				$client_player = ProviderHelper::playerDetailsCall($client_details->player_token);
				if($client_player == 'false'){ 
					$items_array[] = [
						 "info" => $key['info'], 
						 "errorCode" => 999, 
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
	        	    ];   
					continue;
				}
				if($key['currencyId'] != $client_details->default_currency){
	        		$items_array[] = [
						 "info" => $key['info'], 
						 "errorCode" => 16, 
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : ''
	        	    ];   
	        	    continue;
				}
				if(abs($client_player->playerdetailsresponse->balance) < $key['betAmount']){
			        $items_array[] = array(
						 "info" => $key['info'], 
						 "errorCode" => 6, 
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : ''
		   			);
		   			continue;
				}

				// if($isset_before_balance == false){
					$general_details['client']['beforebalance'] = $this->formatBalance(abs($client_player->playerdetailsresponse->balance));
					// $isset_before_balance = true;
				// }
				
				if($key['ignoreExpiry'] != 'false'){
			 		$token_check = DigitainHelper::tokenCheck($key["token"]);
					if($token_check != true){
						$items_array[] = array(
							 "info" => $key['info'], 
							 "errorCode" => 3, 
							 "metadata" => isset($key['metadata']) ? $key['metadata'] : ''
			   			);
						continue;
					}
				}
			}
			// $check_bet_exist = DigitainHelper::findGameExt($key['txId'], 1,'transaction_id');
			$check_bet_exist = GameTransactionMDB::findGameExt($key['txId'], 1,'transaction_id', $client_details);
			if($check_bet_exist != 'false'){
				$items_array[] = [
					 "info" => $key['info'],
					 "errorCode" => 8,
					 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
        	    ];  
        	    continue;
			} 


			if(isset($key['roundId'])){
				// $is_round_has_refunded = $this->checkTransactionExt('round_id', $key['roundId'], 3);
				$is_round_has_refunded = GameTransactionMDB::findGameExt($key['roundId'], 3,'round_id', $client_details);
				if($is_round_has_refunded != null && $is_round_has_refunded != false && $is_round_has_refunded != "false"){
					$items_array[] = [
						 "info" => $key['info'],
						 "errorCode" => 14, // this transaction is not found
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
					]; 
					continue;
				}
			}

			$operation_type = isset($key['operationType']) ? $key['operationType'] : 1;
	 		$payout_reason = 'Bet : '.$this->getOperationType($operation_type);
	 		$win_or_lost = 5; // 0 Lost, 1 win, 3 draw, 4 refund, 5 processing
	 		$method = 1; 
	 	    $token_id = $client_details->token_id;
	 	    if(isset($key['roundId'])){
	 	    	$round_id = $key['roundId'];
	 	    }else{
	 	    	$round_id = 'RSGNOROUNDID';
	 	    }
	 	    if(isset($key['txId'])){
	 	    	$provider_trans_id = $key['txId'];
	 	    }else{
	 	    	$provider_trans_id = 'RSGNOTXID';
	 	    }
	 	    // $game_details = DigitainHelper::findGameDetails('game_code', $this->provider_db_id, $key['gameId']);	
	 	    $bet_payout = 0; // Bet always 0 payout!
	 	    $income = $key['betAmount'] - $bet_payout;

	 		// $game_trans = ProviderHelper::createGameTransaction($token_id, $game_details->game_id, $key['betAmount'],  $bet_payout, $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id, $round_id);
	   		// $game_transextension = ProviderHelper::createGameTransExtV2($game_trans, $provider_trans_id, $round_id, abs($key['betAmount']), 1,$request->all());

	   		$gameTransactionData = array(
				"provider_trans_id" => $provider_trans_id,
				"token_id" => $token_id,
				"game_id" => $game_details->game_id,
				"round_id" => $round_id,
				"bet_amount" => $key['betAmount'],
				"win" => $win_or_lost,
				"pay_amount" => $bet_payout,
				"income" =>  $income,
				"entry_id" =>$method,
			);
			$game_trans = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
			$gameTransactionEXTData = array(
				"game_trans_id" => $game_trans,
				"provider_trans_id" => $provider_trans_id,
				"round_id" => $round_id,
				"amount" => abs($key['betAmount']),
				"game_transaction_type"=> 1,
				"provider_request" =>json_encode($request->all()),
			);
			$game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

			try {
				 $client_response = ClientRequestHelper::fundTransfer($client_details,abs($key['betAmount']),$game_details->game_code,$game_details->game_name,$game_transextension,$game_trans,'debit');
			} catch (\Exception $e) {
				$items_array[] = array(
					 "info" => $key['info'], 
					 "errorCode" => 999, 
					 "metadata" => isset($key['metadata']) ? $key['metadata'] : ''
	   			);
	   			if(isset($game_trans)){
				    // ProviderHelper::updateGameTransactionStatus($game_trans, 2, 99);
				    // ProviderHelper::updatecreateGameTransExt($game_transextension, 'FAILED', $json_data, 'FAILED', $e->getMessage(), 'FAILED', $general_details);  

				    $updateGameTransaction = ["win" => 2];
				    GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);  
				    $updateTransactionEXt = array(
						"mw_response" => json_encode($response),
						'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
						'client_response' => json_encode($e->getMessage().' '.$e->getLine().' '.$e->getFile()),
					);
					GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
				}
	   			continue;
			}

			if(isset($client_response->fundtransferresponse->status->code) 
	            && $client_response->fundtransferresponse->status->code == "200"){
			    	$items_array[] = [
		    	    	 "externalTxId" => $game_transextension,
						 "balance" => $this->formatBalance($client_response->fundtransferresponse->balance),
						 "info" => $key['info'], 
						 "errorCode" => 1, 
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : ''
		    	    ];  
		    	    $general_details['client']['afterbalance'] = $this->formatBalance(abs($client_response->fundtransferresponse->balance));
			    	$general_details['aggregator']['externalTxId'] = $game_transextension;
			    	$general_details['aggregator']['transaction_status'] = 'SUCCESS';
				ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
			    $general_details['provider']['bet'] = $this->formatBalance(abs($key['betAmount']));
			    // ProviderHelper::updatecreateGameTransExt($game_transextension,  $json_data, $items_array, $client_response->requestoclient, $client_response, 'SUCCESS', $general_details);
			    $updateTransactionEXt = array(
					"mw_response" => json_encode($json_data),
					'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
					'client_response' => json_encode($client_response),
					'transaction_detail' => 'SUCCESS',
					'general_details' => json_encode($general_details)
				);
				GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
	    	    continue;
			}elseif(isset($client_response->fundtransferresponse->status->code) 
	            && $client_response->fundtransferresponse->status->code == "402"){

				// if(ProviderHelper::checkFundStatus($client_response->fundtransferresponse->status->status)):
				//      ProviderHelper::updateGameTransactionStatus($game_trans, 2, 6);
				// else:
				//    ProviderHelper::updateGameTransactionStatus($game_trans, 2, 99);
				// endif;

				$updateGameTransaction = ["win" => 2];
				GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);  

				$items_array[] = array(
					 "info" => $key['info'], 
					 "errorCode" => 6, 
					 "metadata" => isset($key['metadata']) ? $key['metadata'] : ''
	   			);
	   			$general_details['client']['afterbalance'] = $this->formatBalance(abs($client_response->fundtransferresponse->balance));
			    $general_details['aggregator']['externalTxId'] = $game_transextension;
			    $general_details['aggregator']['transaction_status'] = 'FAILED';
	   			// ProviderHelper::updatecreateGameTransExt($game_transextension,  $json_data, $items_array, $client_response->requestoclient, $client_response, 'FAILED', $general_details);
	   			$updateTransactionEXt = array(
					"mw_response" => json_encode($items_array),
					'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
					'client_response' => json_encode($client_response),
					'transaction_detail' => 'FAILED',
					'general_details' => json_encode($general_details)
				);
				GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
	   			continue;
			}

		} // END FOREACH

		$response = array(
			 "timestamp" => date('YmdHisms'),
		     "signature" => $this->createSignature(date('YmdHisms')),
			 "errorCode" => 1,
			 "items" => $items_array,
		);	
		ProviderHelper::saveLogWithExeption('RSG bet - SUCCESS', $this->provider_db_id, file_get_contents("php://input"), $response);
		return $response;
	}

	/* BET ALL OR NONE LOGIC */
	public function betAllOrNone($json_data)
	{

		$items_array = array(); // ITEMS INFO
		$all_bets_amount = array();
		$duplicate_txid_request = array();
		// $all_or_none_data_to_process = array();

		$global_error = 1;
		$error_encounter = 0;
		# All or none is true

		$total_bets = array_sum($all_bets_amount);
		$isset_allbets_amount = 0;
		$i = 0;

		$json_data_ii = array();

		foreach ($json_data['items'] as $key => $value) { // FOREACH CHECK

			# Missing Parameters
			if (!isset($value['info']) || !isset($value['txId']) || !isset($value['betAmount']) || !isset($value['token']) || !isset($value['playerId']) || !isset($value['roundId']) || !isset($value['gameId'])) {
				$items_array[] = [
					"info" => isset($value['info']) ? $value['info'] : '', // Info from RSG, MW Should Return it back!
					"errorCode" => 17, // transaction already refunded
					"metadata" => isset($value['metadata']) ? $value['metadata'] : '' // Optional but must be here!
				];
				$global_error = 17;
				$error_encounter = 1;
				$value['tg_error'] = 17;
				array_push($json_data_ii, $value);
				continue;
			}

			# if bet operation type is not in the bet operation types
			if ($this->getBetWinOpType($value['operationType']) == false) {
				$items_array[] = array(
					"info" => $value['info'],
					"errorCode" => 19, // error operation type for this bet not in the bet operation types
					"metadata" => isset($value['metadata']) ? $value['metadata'] : ''
				);
				$global_error = 19;
				$error_encounter = 1;
				$value['tg_error'] = 19;
				array_push($json_data_ii, $value);
				continue;
			}

			# if bet is in the change balance 0 oepration type table make the amount zero
			if ($this->betWithNoChangeBalanceOT($value['operationType'])) {
				if ($value['changeBalance'] == true) {
					$items_array[] = array(
						"info" => $value['info'],
						"errorCode" => 19, // error operation type
						"metadata" => isset($value['metadata']) ? $value['metadata'] : ''
					);
					$global_error = 19;
					$error_encounter = 1;
					$value['tg_error'] = 19;
					array_push($json_data_ii, $value);
					continue;
				} else {
					$value['betAmount'] = 0;  // make the bet amount zero (free bets/artificial bets)
				}
			}

			if ($isset_allbets_amount == 0) { # Calculate all total bets
				foreach ($json_data['items'] as $key => $key_amount) {
					array_push($all_bets_amount, $key_amount['betAmount']);
					array_push($duplicate_txid_request, $key_amount['txId']);  // Checking for same txId in the call
				}
				$isset_allbets_amount = 1;
			}

			$client_details = ProviderHelper::getClientDetails('token', $value["token"]);
			if ($client_details == null || $client_details == 'false') { // SessionNotFound
				$items_array[] = [
					"info" => isset($value['info']) ? $value['info'] : '', // Info from RSG, MW Should Return it back!
					"errorCode" => 2, // transaction already refunded
					"metadata" => isset($value['metadata']) ? $value['metadata'] : '' // Optional but must be here!
				];
				$global_error = 2;
				$value['tg_error'] = 2;
        	    array_push($json_data_ii, $value);
				$error_encounter = 1;
				continue;
			}
			$value['client_details'] =  $client_details;

			$is_exist_gameid = $this->getGameId($value["gameId"]);
			if($is_exist_gameid == false){
				$items_array[] = [
					 "info" => $value['info'], 
					 "errorCode" => 11, 
					 "metadata" => isset($value['metadata']) ? $value['metadata'] : '' 
        	    ]; 
        	    $value['tg_error'] = 11;
        	    $error_encounter = 1;
        	    array_push($json_data_ii, $value);
        	    continue;
			}

			$game_details = ProviderHelper::findGameDetails('game_code', $this->provider_db_id, $value["gameId"]);
			if ($game_details == null) { // Game not found
				$items_array[] = [
					"info" => isset($value['info']) ? $value['info'] : '', // Info from RSG, MW Should Return it back!
					"errorCode" => 11, // transaction already refunded
					"metadata" => isset($value['metadata']) ? $value['metadata'] : '' // Optional but must be here!
				];
				$global_error = 11;
				$value['tg_error'] = 11;
        	    array_push($json_data_ii, $value);
				$error_encounter = 1;
				continue;
			}
			$value['game_details'] =  $game_details;

			if ($client_details != null && $client_details == 'false') { // Wrong Player ID
				if ($client_details->player_id != $value["playerId"]) {
					$items_array[] = [
						"info" => isset($value['info']) ? $value['info'] : '', // Info from RSG, MW Should Return it back!
						"errorCode" => 4, // transaction already refunded
						"metadata" => isset($value['metadata']) ? $value['metadata'] : '' // Optional but must be here!
					];
					$global_error = 4;
					$error_encounter = 1;
					$value['tg_error'] = 4;
					array_push($json_data_ii, $value);
					continue;
				}
				if ($value['currencyId'] != $client_details->default_currency) {
					$items_array[] = [
						"info" => isset($value['info']) ? $value['info'] : '', // Info from RSG, MW Should Return it back!
						"errorCode" => 16, // transaction already refunded
						"metadata" => isset($value['metadata']) ? $value['metadata'] : '' // Optional but must be here!
					];
					$global_error = 16;
					$error_encounter = 1;
					$value['tg_error'] = 16;
					array_push($json_data_ii, $value);
					continue;
				}
			
				$json_data['items'][$i - 1]['client_player'] = $client_details;
				if (abs($client_details->balance) < $total_bets) {
					$items_array[] = [
						"info" => isset($value['info']) ? $value['info'] : '', // Info from RSG, MW Should Return it back!
						"errorCode" => 6, // transaction already refunded
						"metadata" => isset($value['metadata']) ? $value['metadata'] : '' // Optional but must be here!
					];
					$global_error = 6;
					$error_encounter = 1;
					$value['tg_error'] = 6;
					array_push($json_data_ii, $value);
					continue;
				}
				if ($value['ignoreExpiry'] != 'false') {
					$token_check = DigitainHelper::tokenCheck($value["token"]);
					if ($token_check != true) { // Token is expired!
						$items_array[] = [
							"info" => isset($value['info']) ? $value['info'] : '', // Info from RSG, MW Should Return it back!
							"errorCode" => 3, // transaction already refunded
							"metadata" => isset($value['metadata']) ? $value['metadata'] : '' // Optional but must be here!
						];
						$global_error = 3;
						$error_encounter = 1;
						$value['tg_error'] = 3;
						array_push($json_data_ii, $value);
						continue;
					}
				}
			}
			// $check_bet_exist = DigitainHelper::findGameExt($key['txId'], 1, 'transaction_id');
			$check_bet_exist = GameTransactionMDB::findGameExt($value['txId'], 1,'transaction_id', $client_details);
			if ($check_bet_exist != 'false') { // Bet Exist!
				$items_array[] = [
					"info" => $value['info'],
					"errorCode" => 8, // this transaction is not found
					"metadata" => isset($value['metadata']) ? $value['metadata'] : ''
				];
				$global_error = 8;
				$error_encounter = 1;
				$value['tg_error'] = 8;
				array_push($json_data_ii, $value);
				continue;
			}
			// $json_data['items'][$i - 1]['check_bet_exist'] = $check_bet_exist;
			$value['check_bet_exist'] =  $check_bet_exist;


			if(isset($value['roundId'])){
				// $is_round_has_refunded = $this->checkTransactionExt('round_id', $value['roundId'], 3);
				$is_round_has_refunded = GameTransactionMDB::findGameExt($value['roundId'], 3,'round_id', $client_details);
				if($is_round_has_refunded != null && $is_round_has_refunded != false && $is_round_has_refunded != 'false'){
					$items_array[] = [
						 "info" => $value['info'],
						 "errorCode" => 14, // this transaction is not found
						 "metadata" => isset($value['metadata']) ? $value['metadata'] : '' 
					]; 
					$value['tg_error'] = 14;
					array_push($json_data_ii, $value);
					continue;
				}
				// $json_data['items'][$i - 1]['is_round_has_refunded'] = $is_round_has_refunded;
				$value['is_round_has_refunded'] =  $is_round_has_refunded;
			}

			if ($this->array_has_dupes($duplicate_txid_request)) {
				$global_error = 8; // Duplicate TxId in the call
				$error_encounter = 1;
				$value['tg_error'] = 8;
				array_push($json_data_ii, $value);
				continue;
			}
			array_push($json_data_ii, $value);
		} // END FOREACH CHECK
	

		# If Anything Failed Return Error Message
		if ($error_encounter != 0) { // ELSE PROCEED TO CLIENT TRANSFERING
			$response = array(
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"errorCode" => $global_error,
				"items" => $items_array,
			);
			return $response;
		} else {
			$items_array = array(); // ITEMS INFO
			foreach ($json_data_ii as $key) {
				$general_details = ["aggregator" => [], "provider" => [], "client" => []];
				// Provider Details Logger
				$general_details['provider']['operationType'] = $key['operationType'];
				$general_details['provider']['currencyId'] = $key['currencyId'];
				$general_details['provider']['amount'] = $key['betAmount'];
				$general_details['provider']['txCreationDate'] = $json_data['timestamp'];
				$general_details['provider']['txId'] = $key['txId'];
				// Provider Details Logger
				$operation_type = isset($key['operationType']) ? $key['operationType'] : 1;
				$payout_reason = 'Bet : ' . $this->getOperationType($operation_type);
				$win_or_lost = 5; // 0 Lost, 1 win, 3 draw, 4 refund, 5 processing
				$method = 1;

				$client_details = ProviderHelper::getClientDetails('token', $key["token"]);
				if ($client_details == null || $client_details == 'false') { // SessionNotFound
					$items_array[] = [
						"info" => isset($key['info']) ? $key['info'] : '', // Info from RSG, MW Should Return it back!
						"errorCode" => 2, // transaction already refunded
						"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
					];
					$global_error = 2;
					$error_encounter = 1;
					continue;
				}

				$token_id = $client_details->token_id;
				if (isset($key['roundId'])) {
					$round_id = $key['roundId'];
				} else {
					$round_id = 'RSGNOROUNDID';
				}
				if (isset($key['txId'])) {
					$provider_trans_id = $key['txId'];
				} else {
					$provider_trans_id = 'RSGNOTXID';
				}

				$client_details = $key['client_details'];
				$game_details = $key['game_details'];

				$bet_payout = 0; // Bet always 0 payout!
				$income = $key['betAmount'] - $bet_payout;

				$gameTransactionData = array(
					"provider_trans_id" => $provider_trans_id,
					"token_id" => $token_id,
					"game_id" => $game_details->game_id,
					"round_id" => $round_id,
					"bet_amount" => $key['betAmount'],
					"win" => $win_or_lost,
					"pay_amount" => $bet_payout,
					"income" =>  $income,
					"entry_id" =>$method,
				);
				$game_trans = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
				$gameTransactionEXTData = array(
					"game_trans_id" => $game_trans,
					"provider_trans_id" => $provider_trans_id,
					"round_id" => $round_id,
					"amount" => abs($key['betAmount']),
					"game_transaction_type"=> 1,
					"provider_request" =>json_encode($json_data),
				);
				$game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

				try {
					$client_response = ClientRequestHelper::fundTransfer($client_details, abs($key['betAmount']), $game_details->game_code, $game_details->game_name, $game_transextension, $game_trans, 'debit');
					ProviderHelper::saveLogWithExeption('RSG bet CRID = ' . $game_trans, $this->provider_db_id, file_get_contents("php://input"), $client_response);
				} catch (\Exception $e) {
					$items_array[] = array(
						"info" => $key['info'],
						"errorCode" => 999,
						"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
					);
					if (isset($game_trans)) {
						$updateGameTransaction = ["win" => 2];
						GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);  
						$updateTransactionEXt = array(
							"mw_response" => json_encode($response),
							'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
							'client_response' => json_encode($e->getMessage().' '.$e->getLine().' '.$e->getFile()),
						);
						GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
					}
					continue;
				}

				if (
					isset($client_response->fundtransferresponse->status->code)
					&& $client_response->fundtransferresponse->status->code == "200"
				) {
					
					$items_array[] = [
						"externalTxId" => $game_transextension,
						"balance" => $this->formatBalance($client_response->fundtransferresponse->balance),
						"info" => $key['info'],
						"errorCode" => 1,
						"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
					];
					$general_details['client']['afterbalance'] = $this->formatBalance(abs($client_response->fundtransferresponse->balance));
					$general_details['aggregator']['externalTxId'] = $game_transextension;
					$general_details['aggregator']['transaction_status'] = 'SUCCESS';

					$general_details['provider']['bet'] = $this->formatBalance(abs($key['betAmount']));
					ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);

					// ProviderHelper::updatecreateGameTransExt($game_transextension,  $json_data, $items_array, $client_response->requestoclient, $client_response, 'SUCCESS', $general_details);

					$updateTransactionEXt = array(
						"mw_response" => json_encode($items_array),
						'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
						'client_response' => json_encode($client_response),
						'transaction_detail' => 'SUCCESS',
						'general_details' => json_encode($general_details)
					);
					GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
					continue;
				} elseif (
					isset($client_response->fundtransferresponse->status->code)
					&& $client_response->fundtransferresponse->status->code == "402"
				) {

					$updateGameTransaction = ["win" => 2];
					GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);  

					$items_array[] = array(
						"info" => $key['info'],
						"errorCode" => 6,
						"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
					);
					$general_details['client']['afterbalance'] = $this->formatBalance(abs($client_response->fundtransferresponse->balance));
					$general_details['aggregator']['externalTxId'] = $game_transextension;
					$general_details['aggregator']['transaction_status'] = 'FAILED';

					// ProviderHelper::updatecreateGameTransExt($game_transextension,  $json_data, $json_data, $client_response->requestoclient, $client_response, 'FAILED', $general_details);

					$updateTransactionEXt = array(
						"mw_response" => json_encode($json_data),
						'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
						'client_response' => json_encode($client_response),
						'transaction_detail' => 'FAILED',
						'general_details' => json_encode($general_details)
					);
					GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
					continue;
				}
			} // END FOREACH

			$response = array(
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"errorCode" => 1,
				"items" => $items_array,
			);
			ProviderHelper::saveLogWithExeption('RSG bet - SUCCESS', $this->provider_db_id, file_get_contents("php://input"), $response);
			return $response;
		}
	}

	/**
	 *	
	 * @author's NOTE
	 * @author's NOTE [Error codes, 12 = Invalid Signature, 999 = general error (HTTP), 8 = already exist, 16 = error currency code]	
	 * if incorrect playerId ,incorrect gameId,incorrect roundId,incorrect betTxId, should be return errorCode 7
	 *
	 */
	public function win(Request $request){
		ProviderHelper::saveLogWithExeption('RSG win - EH', $this->provider_db_id, file_get_contents("php://input"), 'ENDPOINT HIT');
		$json_data = json_decode(file_get_contents("php://input"), true);
		if($json_data == null){
			return $this->noBody();
		}
		if($json_data['operatorId'] != $this->operator_id){
			return $this->wrongOperatorID();
		}
		if (!$this->authMethod($json_data['operatorId'], $json_data['timestamp'], $json_data['signature'])){
			return $this->authError();
		}

		$items_array = array(); // ITEMS INFO
	
		# Missing Parameters
		if(!isset($json_data['providerId']) || !isset($json_data['allOrNone']) || !isset($json_data['signature']) || !isset($json_data['timestamp']) || !isset($json_data['operatorId']) || !isset($json_data['items'])){
			$response = array(
					 "timestamp" => date('YmdHisms'),
				     "signature" => $this->createSignature(date('YmdHisms')),
					 "errorCode" => 17,
					 "items" => $items_array,
   			);	
			return $response;
		}

		#ALL OR NONE WIN
		if ($json_data['allOrNone'] == 'true') {
			return $this->winallOrNone($request->all());
		}
		#ALL OR NONE WIN
		
		$items_array = array(); // ITEMS INFO
		// $isset_before_balance = false;
		foreach ($json_data['items'] as $key){
				$general_details = ["aggregator" => [],"provider" => [],"client" => []];
				if(!isset($key['info'])  || !isset($key['winAmount'])){
	 				//|| !isset($key['playerId']) || !isset($key['gameId']) || !isset($key['roundId'])
					$items_array[] = [
						 "info" => $key['info'], // Info from RSG, MW Should Return it back!
						 "errorCode" => 17, //The playerId was not found
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
	        	    ];  
					continue;
				}


				# Multi DB Filter
				if (!isset($key['playerId']) && $key['playerId'] == ''){
					$items_array[] = [
						 "info" => isset($key['info']) ? $key['info'] : '', 
						 "errorCode" => 4,
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
	        	    ];
					continue;
				}

				$client_details = ProviderHelper::getClientDetails('player_id', $key['playerId']);
		    	if ($client_details == null || $client_details == 'false'){ // SessionNotFound
					$items_array[] = [
						 "info" => isset($key['info']) ? $key['info'] : '', 
						 "errorCode" => 4,
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
	        	    ];
					continue;
				}


 				if(isset($key['betTxId']) && $key['betTxId'] != ''){
		 		 	$datatrans = GameTransactionMDB::findGameExt($key['betTxId'], 1,'transaction_id', $client_details);
		 		 	$transaction_identifier = $key['betTxId'];
 					$transaction_identifier_type = 'provider_trans_id';
		 		 	if($datatrans == 'false'): // Transaction Not Found!
			 		 	$items_array[] = [
							 "info" => isset($key['info']) ? $key['info'] : '', 
							 "errorCode" => 7, 
							 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
		        	    ];
						continue;	
		 			endif;
		 		}else{ // use originalTxid instead
					$datatrans = GameTransactionMDB::findGameExt($key['roundId'], 1,'round_id', $client_details);
		 			$transaction_identifier = $key['roundId'];
 					$transaction_identifier_type = 'round_id';
		 			if($datatrans == 'false'): // Transaction Not Found!
			 			$items_array[] = [
							 "info" => isset($key['info']) ? $key['info'] : '', 
							 "errorCode" => 7, 
							 "metadata" => isset($key['metadata']) ? $key['metadata'] : ''
		        	    ];
						continue;	
		 			endif;
		 		}


		 		if($datatrans != false){// Bet for this round is already Refunded
		 			$bet_transaction_status = GameTransactionMDB::findGameTransactionDetails($datatrans->game_trans_id, 'game_transaction', false, $client_details);
		 			if($bet_transaction_status->win == 4){
		 				$items_array[] = [
							 "info" => isset($key['info']) ? $key['info'] : '', 
							 "errorCode" => 14, 
							 "metadata" => isset($key['metadata']) ? $key['metadata'] : ''
		        	    ];
						continue;	
		 			}
	 			}
		 		if($client_details != null){ // Wrong Player ID
					if($key['currencyId'] != $client_details->default_currency){
						$items_array[] = [
							 "info" => isset($key['info']) ? $key['info'] : '', 
							 "errorCode" => 16, 
							 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
		        	    ];
						continue;
					}
				}

		 		$check_win_exist = GameTransactionMDB::findGameExt($key['txId'], 2,'transaction_id', $client_details);
	 			if($check_win_exist != false && $check_win_exist != "false"){
	 				$items_array[] = [
						 "info" => $key['info'], 
						 "errorCode" => 8, 
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
	        	    ];  
	        	    continue;
	 			}

	 			if(isset($key['betTxId']) && $key['betTxId'] != null){
	 				$db_bet_full_request = json_decode($datatrans->provider_request);
		 			$bet_previous_count = count($db_bet_full_request->items);
		 			if($bet_previous_count < 1){
		 				foreach ($db_bet_full_request->items as $key) {
		 					if($key->txId == $key['betTxId']){
		 						$gameId = $key->gameId;
		 					}
		 				}
		 			}else{
		 				$gameId = $db_bet_full_request->items[0]->gameId;
		 			}
	 			}else{
	 				$gameId = $key["gameId"];
	 			}
	 
	 			$is_exist_gameid = $this->getGameId($gameId);
				if($is_exist_gameid == false){
					$items_array[] = [
						 "info" => $key['info'], 
						 "errorCode" => 11, 
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
	        	    ]; 
	        	    continue;
				}
				$gameId = $is_exist_gameid; // Overwrite GameId

 				$game_details = ProviderHelper::findGameDetails('game_code', $this->provider_db_id, $gameId);
				if($game_details == null){ // Game not found
					$items_array[] = [
						 "info" => $key['info'],
						 "errorCode" => 11,  // Game Not Found
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
	        	    ]; 
	        	    continue;
				}

				if($key['currencyId'] != $client_details->default_currency){
					$items_array[] = [
						 "info" => $key['info'], // Info from RSG, MW Should Return it back!
						 "errorCode" => 16, // Currency code dont match!
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
	        	    ];   
	        	    continue;
				}

				$bet_info = json_decode($datatrans->general_details);

				# if win operation type is not the bet win operation type filter
				if(isset($bet_info->provider->operationType)){
					if ($this->getBetWinOpType($bet_info->provider->operationType) == false) {
						$items_array[] = array(
							"info" => $key['info'],
							"errorCode" => 19, 
							"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
						);
						continue;
					}elseif($this->getBetWinOpType($bet_info->provider->operationType) != $key['operationType']){
						$items_array[] = array(
							"info" => $key['info'],
							"errorCode" => 19,
							"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
						);
						continue;
					}
				}
				
				$general_details['client']['beforebalance'] = $this->formatBalance($client_details->balance);
			
				$general_details['provider']['operationType'] = $key['operationType'];
				$general_details['provider']['currencyId'] = $key['currencyId'];
				$general_details['provider']['amount'] = $key['winAmount'];
				$general_details['provider']['txCreationDate'] = $json_data['timestamp'];
				$general_details['provider']['txId'] = $key['txId'];

				$gameTransactionEXTData = array(
					"game_trans_id" => $datatrans->game_trans_id,
					"provider_trans_id" => $key['txId'],
					"round_id" => $datatrans->round_id,
					"amount" => abs($key['winAmount']),
					"game_transaction_type"=> 2,
					"provider_request" =>json_encode($request->all()),
				);
				$game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

				try {
				 $client_response = ClientRequestHelper::fundTransfer($client_details,abs($key['winAmount']),$game_details->game_code,$game_details->game_name,$game_transextension,$datatrans->game_trans_id,'credit');
				} catch (\Exception $e) {
					$items_array[] = array(
						 "info" => $key['info'], 
						 "errorCode" => 999, 
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : ''
						);
					$updateTransactionEXt = array(
						"mw_response" => json_encode($json_data),
						'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
						'client_response' => json_encode($e->getMessage().' '.$e->getLine().' '.$e->getFile()),
						'general_details' => json_encode($general_details)
					);
					GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);


					ProviderHelper::saveLogWithExeption('RSG win - FATAL ERROR', $this->provider_db_id, json_encode($items_array), DigitainHelper::datesent());
						continue;
				}

				if(isset($client_response->fundtransferresponse->status->code) 
				             && $client_response->fundtransferresponse->status->code == "200"){
					$general_details['provider']['win'] = $this->formatBalance($client_response->fundtransferresponse->balance);
					$general_details['client']['afterbalance'] = $this->formatBalance($client_response->fundtransferresponse->balance);
					$general_details['aggregator']['externalTxId'] = $game_transextension;
					$general_details['aggregator']['transaction_status'] = 'SUCCESS';

					if($key['winAmount'] != 0){
		 	  			if($bet_transaction_status->bet_amount > $key['winAmount']){
		 	  				$win = 0; // lost
		 	  				$entry_id = 1; //lost
		 	  				$income = $bet_transaction_status->bet_amount - $key['winAmount'];
		 	  			}else{
		 	  				$win = 1; //win
		 	  				$entry_id = 2; //win
		 	  				$income = $bet_transaction_status->bet_amount - $key['winAmount'];
		 	  			}
	 	  				$updateGameTransaction = [
	 	  					  'pay_amount' => $key['winAmount'], 
			                  'income' => $income, 
			                  'win' => $win, 
			                  'entry_id' => $entry_id,
			            ];
						GameTransactionMDB::updateGametransaction($updateGameTransaction, $datatrans->game_trans_id, $client_details); 
		 	  		}else{
		 	  			$updateGameTransaction = [
	 	  					  'pay_amount' => $bet_transaction_status->pay_amount, 
			                  'income' => $bet_transaction_status->income, 
			                  'win' => 0, 
			                  'entry_id' => $bet_transaction_status->entry_id,
			            ];
						GameTransactionMDB::updateGametransaction($updateGameTransaction, $datatrans->game_trans_id, $client_details);
		 	  		}
				
					ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);

					$updateTransactionEXt = array(
						"mw_response" => json_encode($items_array),
						'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
						'client_response' => json_encode($client_response),
						'transaction_detail' => 'SUCCESS',
						'general_details' => json_encode($general_details)
					);
					GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
		 	  		

		 	  		if(isset($key['returnBetsAmount']) && $key['returnBetsAmount'] == true){
		 	  			if(isset($key['betTxId'])){
	        	    		// $datatrans = $this->findTransactionRefund($key['betTxId'], 'transaction_id');
	        	    		$check_bet_exist = GameTransactionMDB::findGameExt($key['betTxId'], 1,'transaction_id', $client_details);
	        	    	}else{
	        	    		// $datatrans = $this->findTransactionRefund($key['roundId'], 'round_id');
	        	    		$check_bet_exist = GameTransactionMDB::findGameExt($key['roundId'], 1,'round_id', $client_details);
	        	    	}
        	    		$gg = json_decode($datatrans->provider_request);
				 		$total_bets = array();
				 		foreach ($gg->items as $gg_tem) {
							array_push($total_bets, $gg_tem->betAmount);
				 		}
				 		$items_array[] = [
		        	    	 "externalTxId" => $game_transextension, // MW Game Transaction Id
							 "balance" => $this->formatBalance($client_response->fundtransferresponse->balance),
							 "betsAmount" => $this->formatBalance(array_sum($total_bets)),
							 "info" => $key['info'], // Info from RSG, MW Should Return it back!
							 "errorCode" => 1,
							 "metadata" => isset($key['metadata']) ? $key['metadata'] : '', // Optional but must be here!
		        	    ];
		 	  		}else{
		 	  			$items_array[] = [
		        	    	 "externalTxId" => $game_transextension, // MW Game Transaction Id
							 "balance" => $this->formatBalance($client_response->fundtransferresponse->balance),
							 "info" => $key['info'], // Info from RSG, MW Should Return it back!
							 "errorCode" => 1,
							 "metadata" => isset($key['metadata']) ? $key['metadata'] : '', // Optional but must be here!
		        	    ];
		 	  		}

				}else{ // Unknown Response Code
					# Old Setup NO Auto Credit Success Response [March 4, 2021]
					// $items_array[] = array(
					// 	"info" => $key['info'], 
					// 	"errorCode" => 999, 
					// 	"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
					// );
					// $general_details['aggregator']['externalTxId'] = $game_transextension;
					// $general_details['aggregator']['transaction_status'] = 'FAILED';
					// ProviderHelper::updatecreateGameTransExt($game_transextension, 'FAILED', $json_data, 'FAILED', $client_response, 'FAILED', $general_details);
					// ProviderHelper::saveLogWithExeption('RSG win - FATAL ERROR', $this->provider_db_id, $items_array, DigitainHelper::datesent());
					// continue;
					# End Old Setup NO Auto Credit Success Response

					# Credit Auto Success Response
					$general_details['provider']['win'] = $this->formatBalance($client_details->balance+$key['winAmount']);
					$general_details['client']['afterbalance'] = $this->formatBalance($client_details->balance+$key['winAmount']);
					$general_details['aggregator']['externalTxId'] = $game_transextension;
					$general_details['aggregator']['transaction_status'] = 'SUCCESS';

					if(isset($key['returnBetsAmount']) && $key['returnBetsAmount'] == true){
		 	  			if(isset($key['betTxId'])){
	        	    		// $datatrans = $this->findTransactionRefund($key['betTxId'], 'transaction_id');
	        	    		$check_bet_exist = GameTransactionMDB::findGameExt($key['betTxId'], 1,'transaction_id', $client_details);
	        	    	}else{
	        	    		// $datatrans = $this->findTransactionRefund($key['roundId'], 'round_id');
	        	    		$check_bet_exist = GameTransactionMDB::findGameExt($key['roundId'], 1,'round_id', $client_details);
	        	    	}
        	    		$gg = json_decode($datatrans->provider_request);
				 		$total_bets = array();
				 		foreach ($gg->items as $gg_tem) {
							array_push($total_bets, $gg_tem->betAmount);
				 		}
				 		$items_array[] = [
		        	    	 "externalTxId" => $game_transextension, // MW Game Transaction Id
							 "balance" => $this->formatBalance($client_details->balance+$key['winAmount']),
							 "betsAmount" => $this->formatBalance(array_sum($total_bets)),
							 "info" => $key['info'], // Info from RSG, MW Should Return it back!
							 "errorCode" => 1,
							 "metadata" => isset($key['metadata']) ? $key['metadata'] : '', // Optional but must be here!
		        	    ];
		 	  		}else{
		 	  			$items_array[] = [
		        	    	 "externalTxId" => $game_transextension, // MW Game Transaction Id
							 "balance" => $this->formatBalance($client_details->balance+$key['winAmount']),
							 "info" => $key['info'], // Info from RSG, MW Should Return it back!
							 "errorCode" => 1,
							 "metadata" => isset($key['metadata']) ? $key['metadata'] : '', // Optional but must be here!
		        	    ];
		 	  		}
					ProviderHelper::_insertOrUpdate($client_details->token_id, $client_details->balance+$key['winAmount']);
		 	  		$updateTransactionEXt = array(
						"mw_response" => json_encode($items_array),
						'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
						'client_response' => json_encode($client_response),
						'transaction_detail' => 'FAILED',
						'general_details' => json_encode($general_details)
					);
					GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
					# END Credit Auto Success Response
				}    
		} // END FOREACH
		$response = array(
			 "timestamp" => date('YmdHisms'),
		     "signature" => $this->createSignature(date('YmdHisms')),
			 "errorCode" => 1,
			 "items" => $items_array,
		);	
		ProviderHelper::saveLogWithExeption('RSG win - SUCCESS', $this->provider_db_id, file_get_contents("php://input"), $response);
		return $response;
	}

	/* WIN ALL OR NONE LOGIC */
	public function winallOrNone($json_data){

		// # 1 CHECKER 
		$items_array = array(); // ITEMS INFO
		$all_wins_amount = array();
		$duplicate_txid_request = array();

		$error_encounter = 0;
	    $datatrans_status = true;
	    $global_error = 1;
		$isset_allwins_amount = 0;
		
		$json_data_ii = array();
	
		foreach ($json_data['items'] as $key => $value ) { // FOREACH CHECK

				if (!isset($value['info'])  || !isset($value['winAmount'])) {
					$items_array[] = [
						"info" => isset($value['info']) ? $value['info'] : '', // Info from RSG, MW Should Return it back!
						"errorCode" => 17, // transaction already refunded
						"metadata" => isset($value['metadata']) ? $value['metadata'] : '' // Optional but must be here!
					];
					$global_error = 17;
					$error_encounter = 1;
					$value['tg_error'] = 17;
					array_push($json_data_ii, $value);
					continue;
				}

				# Multi DB Filter
				if (!isset($value['playerId']) && $value['playerId'] == ''){
					$items_array[] = [
						 "info" => isset($value['info']) ? $value['info'] : '', 
						 "errorCode" => 4,
						 "metadata" => isset($value['metadata']) ? $value['metadata'] : '' 
	        	    ];
	        	    $global_error = 4;
	        	    $error_encounter = 1;
	        	    $value['tg_error'] = 4;
					array_push($json_data_ii, $value);
					continue;
				}

				$client_details = ProviderHelper::getClientDetails('player_id', $value['playerId']);
		    	if ($client_details == null || $client_details == 'false'){ // SessionNotFound
					$items_array[] = [
						 "info" => isset($value['info']) ? $value['info'] : '', 
						 "errorCode" => 4,
						 "metadata" => isset($value['metadata']) ? $value['metadata'] : '' 
	        	    ];
	        	    $error_encounter = 1;
	        	    $value['tg_error'] = 4;
					array_push($json_data_ii, $value);
					continue;
				}

				$is_exist_gameid = $this->getGameId($value["gameId"]);
				if($is_exist_gameid == false){
					$items_array[] = [
						 "info" => $value['info'], 
						 "errorCode" => 11, 
						 "metadata" => isset($value['metadata']) ? $value['metadata'] : '' 
	        	    ]; 
	        	    $global_error = 11;
	        	    $error_encounter = 1;
	        	    $value['tg_error'] = 11;
					array_push($json_data_ii, $value);
	        	    continue;
				}
				$value["gameId"] = $is_exist_gameid; // Overwrite GameId


				$game_details = ProviderHelper::findGameDetails('game_code', $this->provider_db_id, $value["gameId"]);
				if ($game_details == null && $error_encounter == 0) { // Game not found
					$items_array[] = [
						"info" => isset($value['info']) ? $value['info'] : '', // Info from RSG, MW Should Return it back!
						"errorCode" => 11, // transaction already refunded
						"metadata" => isset($value['metadata']) ? $value['metadata'] : '' // Optional but must be here!
					];
					$global_error = 11;
					$error_encounter = 1;
					$value['tg_error'] = 11;
					array_push($json_data_ii, $value);
					continue;
				}
				// $json_data['items'][$i - 1]['game_details'] = $game_details;
				$value['game_details'] =  $game_details;

				if ($isset_allwins_amount == 0) {
					foreach ($json_data['items'] as $key => $key_amount) {
						array_push($all_wins_amount, $key_amount['winAmount']);
						array_push($duplicate_txid_request, $key_amount['txId']);  // Checking for same txId in the call
					} # 1 CHECKER
					$isset_allwins_amount = 1;
				}

				if (isset($value['betTxId']) && $value['betTxId'] != '') {
					$datatrans = GameTransactionMDB::findGameExt($value['betTxId'], 1,'transaction_id', $client_details);
					$transaction_identifier = $value['betTxId'];
					$transaction_identifier_type = 'provider_trans_id';
					if ($datatrans == 'false') :
						$items_array[] = [
							"info" => isset($value['info']) ? $value['info'] : '',
							"errorCode" => 7,
							"metadata" => isset($value['metadata']) ? $value['metadata'] : ''
						];
						$global_error = 7;
						$error_encounter = 1;
						$value['tg_error'] = 7;
						array_push($json_data_ii, $value);
						continue;
					endif;
				} else {
					$datatrans = GameTransactionMDB::findGameExt($value['roundId'], 1,'round_id', $client_details);
					$transaction_identifier = $value['roundId'];
					$transaction_identifier_type = 'round_id';
					if ($datatrans == 'false') : // Transaction Not Found!
						$items_array[] = [
							"info" => isset($value['info']) ? $value['info'] : '',
							"errorCode" => 7,
							"metadata" => isset($value['metadata']) ? $value['metadata'] : ''
						];
						$global_error = 7;
						$error_encounter = 1;
						$value['tg_error'] = 7;
						array_push($json_data_ii, $value);
						continue;
					endif;
				}
				if ($datatrans != 'false') { // Bet for this round is already Refunded
					$bet_transaction_status = GameTransactionMDB::findGameTransactionDetails($datatrans->game_trans_id, 'game_transaction', false, $client_details);
					if ($bet_transaction_status->win == 4) {
						$items_array[] = [
							"info" => isset($value['info']) ? $value['info'] : '',
							"errorCode" => 14,
							"metadata" => isset($value['metadata']) ? $value['metadata'] : ''
						];
						$global_error = 14;
						$error_encounter = 1;
						$value['tg_error'] = 14;
						array_push($json_data_ii, $value);
						continue;
					}
				}

				$value['transaction_identifier'] = $transaction_identifier;
				$value['transaction_identifier_type'] = $transaction_identifier_type;
				$value['datatrans'] = $datatrans;

				if ($client_details != null || $client_details == 'false') { 
					if ($value['currencyId'] != $client_details->default_currency) {
						$items_array[] = [
							"info" => isset($value['info']) ? $value['info'] : '',
							"errorCode" => 16,
							"metadata" => isset($value['metadata']) ? $value['metadata'] : ''
						];
						$global_error = 16;
						$error_encounter = 1;
						$value['tg_error'] = 16;
						array_push($json_data_ii, $value);
						continue;
					}
				}
				$value['client_details'] = $client_details;
				$value['client_player'] = $client_details;

				// $check_win_exist = $this->gameTransactionEXTLog('provider_trans_id', $key['txId'], 2);
				$check_win_exist = GameTransactionMDB::findGameExt($value['txId'], 2,'transaction_id', $client_details);
				if ($check_win_exist != false && $check_win_exist != "false"){
					$items_array[] = [
						"info" => isset($value['info']) ? $value['info'] : '',
						"errorCode" => 8,
						"metadata" => isset($value['metadata']) ? $value['metadata'] : ''
					];
					$global_error = 8;
					$error_encounter = 1;
					$value['tg_error'] = 8;
					array_push($json_data_ii, $value);
					continue;
				}
				$value['check_win_exist'] = $check_win_exist;
				// $json_data['items'][$i - 1]['check_win_exist'] = $check_win_exist;

				# if win operation type is not the bet win operation type filter
				if (isset($datatrans->general_details)){
					$bet_info = json_decode($datatrans->general_details);
					if (isset($bet_info->provider->operationType)) {
						if ($this->getBetWinOpType($bet_info->provider->operationType) == false) {
							$items_array[] = array(
								"info" => $value['info'],
								"errorCode" => 19,
								"metadata" => isset($value['metadata']) ? $value['metadata'] : ''
							);
							$global_error = 19;
							$error_encounter = 1;
							$value['tg_error'] = 19;
							array_push($json_data_ii, $value);
							continue;
						} elseif ($this->getBetWinOpType($bet_info->provider->operationType) != $value['operationType']) {
							$items_array[] = array(
								"info" => $value['info'],
								"errorCode" => 19,
								"metadata" => isset($value['metadata']) ? $value['metadata'] : ''
							);
							$global_error = 19;
							$error_encounter = 1;
							$value['tg_error'] = 19;
							array_push($json_data_ii, $value);
							continue;
						}
					}
				}

				if ($this->array_has_dupes($duplicate_txid_request)) {
					$items_array[] = [
						"info" => isset($value['info']) ? $value['info'] : '',
						"errorCode" => 8,
						"metadata" => isset($value['metadata']) ? $value['metadata'] : ''
					];
					$global_error = 8; // Duplicate TxId in the call
					$error_encounter = 1;
					$value['tg_error'] = 8;
					array_push($json_data_ii, $value);
					continue;
				}

		} // END FOREACH CHECK

		if ($error_encounter != 0) { // ELSE PROCEED TO CLIENT TRANSFERING
			$response = array(
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"errorCode" => $global_error,
				"items" => $items_array,
			);
			return $response;
		}else{
			$items_array = array(); // ITEMS INFO
			// $isset_before_balance = false;
			foreach ($json_data_ii as $key) {
				$general_details = ["aggregator" => [], "provider" => [], "client" => []];

				$client_details = ProviderHelper::getClientDetails('player_id', $key['playerId']);
		    	if ($client_details == null || $client_details == 'false'){ // SessionNotFound
					$items_array[] = [
						 "info" => isset($key['info']) ? $key['info'] : '', 
						 "errorCode" => 4,
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
	        	    ];
					continue;
				}

				$transaction_identifier = $key['transaction_identifier'];
				$transaction_identifier_type = $key['transaction_identifier_type'];
				$datatrans = $key['datatrans'];
				// $client_player = $key['client_player'];
				$check_win_exist = $key['check_win_exist'];
			
				if (isset($key['betTxId']) && $key['betTxId'] != null) {
					$db_bet_full_request = json_decode($datatrans->provider_request);
					$bet_previous_count = count($db_bet_full_request->items);
					if ($bet_previous_count < 1) {
						foreach ($db_bet_full_request->items as $key) {
							if ($key->txId == $key['betTxId']) {
								$gameId = $key->gameId;
							}
						}
					} else {
						$gameId = $db_bet_full_request->items[0]->gameId;
					}
				} else {
					$gameId = $key["gameId"];
				}

				// $game_details = $key['game_details'];
				$bet_info = json_decode($datatrans->general_details);

				// $general_details['client']['beforebalance'] = $this->formatBalance($client_player->playerdetailsresponse->balance);
				$general_details['client']['beforebalance'] = $this->formatBalance($client_details->balance);
				
				$general_details['provider']['operationType'] = $key['operationType'];
				$general_details['provider']['currencyId'] = $key['currencyId'];
				$general_details['provider']['amount'] = $key['winAmount'];
				$general_details['provider']['txCreationDate'] = $json_data['timestamp'];
				$general_details['provider']['txId'] = $key['txId'];

				// $game_transextension = ProviderHelper::createGameTransExtV2($datatrans->game_trans_id, $key['txId'], $datatrans->round_id, abs($key['winAmount']), 2,$json_data);
				$gameTransactionEXTData = array(
					"game_trans_id" => $datatrans->game_trans_id,
					"provider_trans_id" => $key['txId'],
					"round_id" => $datatrans->round_id,
					"amount" => abs($key['winAmount']),
					"game_transaction_type"=> 2,
					"provider_request" =>json_encode($json_data),
				);
				$game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

				try {
					$client_response = ClientRequestHelper::fundTransfer($client_details, abs($key['winAmount']), $game_details->game_code, $game_details->game_name, $game_transextension, $datatrans->game_trans_id, 'credit');
				} catch (\Exception $e) {
					$items_array[] = array(
						"info" => $key['info'],
						"errorCode" => 999,
						"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
					);
					// ProviderHelper::updatecreateGameTransExt($game_transextension, 'FAILED', $json_data, 'FAILED', $e->getMessage(), 'FAILED', $general_details);
					$updateTransactionEXt = array(
						"mw_response" => json_encode($json_data),
						'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
						'client_response' => json_encode($e->getMessage().' '.$e->getLine().' '.$e->getFile()),
						'general_details' => json_encode($general_details)
					);
					GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);

					ProviderHelper::saveLogWithExeption('RSG win - FATAL ERROR', $this->provider_db_id, json_encode($items_array), DigitainHelper::datesent());
					continue;
				}

				if (
					isset($client_response->fundtransferresponse->status->code)
					&& $client_response->fundtransferresponse->status->code == "200"
				) {
					$general_details['provider']['win'] = $this->formatBalance($client_response->fundtransferresponse->balance);
					$general_details['client']['afterbalance'] = $this->formatBalance($client_response->fundtransferresponse->balance);
					$general_details['aggregator']['externalTxId'] = $game_transextension;
					$general_details['aggregator']['transaction_status'] = 'SUCCESS';
					ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
					if ($key['winAmount'] != 0) {
						if ($bet_transaction_status->bet_amount > $key['winAmount']) {
							$win = 0; // lost
							$entry_id = 1; //lost
							$income = $bet_transaction_status->bet_amount - $key['winAmount'];
						} else {
							$win = 1; //win
							$entry_id = 2; //win
							$income = $bet_transaction_status->bet_amount - $key['winAmount'];
						}
						// $updateTheBet = DigitainHelper::updateBetToWin($datatrans->game_trans_id, $key['winAmount'], $income, $win, $entry_id);
						$updateGameTransaction = [
	 	  					  'pay_amount' => $key['winAmount'], 
			                  'income' => $income, 
			                  'win' => $win, 
			                  'entry_id' => $entry_id,
			            ];
						GameTransactionMDB::updateGametransaction($updateGameTransaction, $datatrans->game_trans_id, $client_details); 
					} else {
						// $updateTheBet = DigitainHelper::updateBetToWin($datatrans->game_trans_id, $bet_transaction_status->pay_amount, $bet_transaction_status->income, 0, $bet_transaction_status->entry_id);
						$updateGameTransaction = [
	 	  					  'pay_amount' => $bet_transaction_status->pay_amount, 
			                  'income' => $bet_transaction_status->income, 
			                  'win' => $win, 
			                  'entry_id' => $bet_transaction_status->entry_id,
			            ];
						GameTransactionMDB::updateGametransaction($updateGameTransaction, $datatrans->game_trans_id, $client_details);
					}

					// Update this bet that it has won
					// if ($transaction_identifier_type == 'provider_trans_id') {
					// 	$this->updateGameExtTransDetails('BETWON', 'provider_trans_id', $key['betTxId'], 1);
					// } else {
					// 	$this->updateGameExtTransDetails('BETWON', 'round_id', $key['roundId'], 1);
					// }

					// ProviderHelper::updatecreateGameTransExt($game_transextension,  $json_data, $items_array, $client_response->requestoclient, $client_response, 'SUCCESS', $general_details);

					$updateTransactionEXt = array(
						"mw_response" => json_encode($items_array),
						'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
						'client_response' => json_encode($client_response),
						'transaction_detail' => 'SUCCESS',
						'general_details' => json_encode($general_details)
					);
					GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);

					if (isset($key['returnBetsAmount']) && $key['returnBetsAmount'] == true) {
						if(isset($key['betTxId'])){
	        	    		// $datatrans = $this->findTransactionRefund($key['betTxId'], 'transaction_id');
	        	    		$check_bet_exist = GameTransactionMDB::findGameExt($key['betTxId'], 1,'transaction_id', $client_details);
	        	    	}else{
	        	    		// $datatrans = $this->findTransactionRefund($key['roundId'], 'round_id');
	        	    		$check_bet_exist = GameTransactionMDB::findGameExt($key['roundId'], 1,'round_id', $client_details);
	        	    	}
						$gg = json_decode($datatrans->provider_request);
						$total_bets = array();
						foreach ($gg->items as $gg_tem) {
							array_push($total_bets, $gg_tem->betAmount);
						}
						$items_array[] = [
							"externalTxId" => $game_transextension, // MW Game Transaction Id
							"balance" => $this->formatBalance($client_response->fundtransferresponse->balance),
							"betsAmount" => $this->formatBalance(array_sum($total_bets)),
							"info" => $key['info'], // Info from RSG, MW Should Return it back!
							"errorCode" => 1,
							"metadata" => isset($key['metadata']) ? $key['metadata'] : '', // Optional but must be here!
						];
					} else {
						$items_array[] = [
							"externalTxId" => $game_transextension, // MW Game Transaction Id
							"balance" => $this->formatBalance($client_response->fundtransferresponse->balance),
							"info" => $key['info'], // Info from RSG, MW Should Return it back!
							"errorCode" => 1,
							"metadata" => isset($key['metadata']) ? $key['metadata'] : '', // Optional but must be here!
						];
					}
				} else { // Unknown Response Code
					# Old Setup NO Auto Credit Success Response [March 4, 2021]
					// $items_array[] = array(
					// 	"info" => $key['info'], 
					// 	"errorCode" => 999, 
					// 	"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
					// );
					// $general_details['aggregator']['externalTxId'] = $game_transextension;
					// $general_details['aggregator']['transaction_status'] = 'FAILED';
					// ProviderHelper::updatecreateGameTransExt($game_transextension, 'FAILED', $json_data, 'FAILED', $client_response, 'FAILED', $general_details);
					// ProviderHelper::saveLogWithExeption('RSG win - FATAL ERROR', $this->provider_db_id, $items_array, DigitainHelper::datesent());
					// continue;
					# End Old Setup NO Auto Credit Success Response

					# Credit Auto Success Response
					ProviderHelper::_insertOrUpdate($client_details->token_id, $client_details->balance+$key['winAmount']);
					$general_details['provider']['win'] = $this->formatBalance($client_details->balance+$key['winAmount']);
					$general_details['client']['afterbalance'] = $this->formatBalance($client_details->balance+$key['winAmount']);
					$general_details['aggregator']['externalTxId'] = $game_transextension;
					$general_details['aggregator']['transaction_status'] = 'SUCCESS';

					if(isset($key['returnBetsAmount']) && $key['returnBetsAmount'] == true){
		 	  			if(isset($key['betTxId'])){
	        	    		// $datatrans = $this->findTransactionRefund($key['betTxId'], 'transaction_id');
	        	    		$check_bet_exist = GameTransactionMDB::findGameExt($key['betTxId'], 1,'transaction_id', $client_details);
	        	    	}else{
	        	    		// $datatrans = $this->findTransactionRefund($key['roundId'], 'round_id');
	        	    		$check_bet_exist = GameTransactionMDB::findGameExt($key['roundId'], 1,'round_id', $client_details);
	        	    	}
        	    		$gg = json_decode($datatrans->provider_request);
				 		$total_bets = array();
				 		foreach ($gg->items as $gg_tem) {
							array_push($total_bets, $gg_tem->betAmount);
				 		}
				 		$items_array[] = [
		        	    	 "externalTxId" => $game_transextension, // MW Game Transaction Id
							 "balance" => $this->formatBalance($client_details->balance+$key['winAmount']),
							 "betsAmount" => $this->formatBalance(array_sum($total_bets)),
							 "info" => $key['info'], // Info from RSG, MW Should Return it back!
							 "errorCode" => 1,
							 "metadata" => isset($key['metadata']) ? $key['metadata'] : '', // Optional but must be here!
		        	    ];
		 	  		}else{
		 	  			$items_array[] = [
		        	    	 "externalTxId" => $game_transextension, // MW Game Transaction Id
							 "balance" => $this->formatBalance($client_details->balance+$key['winAmount']),
							 "info" => $key['info'], // Info from RSG, MW Should Return it back!
							 "errorCode" => 1,
							 "metadata" => isset($key['metadata']) ? $key['metadata'] : '', // Optional but must be here!
		        	    ];
		 	  		}
					// Providerhelper::createRestrictGame($game_details->game_id,$client_details->player_id,$game_transextension, $client_response->requestoclient);
		 	  		// ProviderHelper::updatecreateGameTransExt($game_transextension,  $json_data, $items_array, $client_response->requestoclient, $client_response, 'FAILED', $general_details);
		 	  		$updateTransactionEXt = array(
						"mw_response" => json_encode($items_array),
						'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
						'client_response' => json_encode($client_response),
						'transaction_detail' => 'FAILED',
						'general_details' => json_encode($general_details)
					);
					GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
					# END Credit Auto Success Response


				}
			} // END FOREACH
			$response = array(
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"errorCode" => 1,
				"items" => $items_array,
			);
			ProviderHelper::saveLogWithExeption('RSG win - SUCCESS', $this->provider_db_id, file_get_contents("php://input"), $response);
			return $response;
		}

	}

	/**
	 *	
	 * NOTE
	 * Accept Bet and Win At The Same Time!
	 */
	public function betwin(Request $request){
		ProviderHelper::saveLogWithExeption('RSG betwin - EH', $this->provider_db_id, file_get_contents("php://input"), 'ENDPOINT HIT');
		$json_data = json_decode(file_get_contents("php://input"), true);
		if($json_data == null){
			return $this->noBody();
		}
		if($json_data['operatorId'] != $this->operator_id){ //Wrong Operator Id 
			return $this->wrongOperatorID();
		}
		if(!$this->authMethod($json_data['operatorId'], $json_data['timestamp'], $json_data['signature'])){ 
			return $this->authError();
		}
		
		$items_array = array();
		$duplicate_txid_request = array();
		$all_bets_amount = array();
		$isset_allbets_amount = 0;

		# Missing Parameters
		if(!isset($json_data['providerId']) || !isset($json_data['allOrNone']) || !isset($json_data['signature']) || !isset($json_data['timestamp']) || !isset($json_data['operatorId']) || !isset($json_data['items'])){
			$response = array(
					 "timestamp" => date('YmdHisms'),
				     "signature" => $this->createSignature(date('YmdHisms')),
					 "errorCode" => 17,
					 "items" => $items_array,
   			);	
			return $response;
		}

		if($json_data['allOrNone'] == 'true'){
			return	$this->betwinallOrNone($request->all());
		}

		$items_array = array(); // ITEMS INFO
		$duplicate_txid_request = array();
		$all_bets_amount = array();
		$isset_allbets_amount = 0;
		foreach ($json_data['items'] as $key){
				$general_details = ["aggregator" => [],"provider" => [],"client" => []];
				$general_details2 = ["aggregator" => [],"provider" => [],"client" => []];

				# Missing item param
				if(!isset($key['txId']) || !isset($key['betAmount']) || !isset($key['winAmount']) || !isset($key['token']) || !isset($key['playerId']) || !isset($key['roundId']) || !isset($key['gameId']) || !isset($key['betInfo']) || !isset($key['winInfo'])){
					 $items_array[] = [
						 "betInfo" => isset($key['betInfo']) ? $key['betInfo'] : '', // Betinfo
					     "winInfo" => isset($key['winInfo']) ? $key['winInfo'] : '', // IWininfo
						 "errorCode" => 17, //The playerId was not found
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
	        	    ]; 
					continue;
				}

				$is_exist_gameid = $this->getGameId($key["gameId"]);
				if($is_exist_gameid == false){
					$items_array[] = [
						 "info" => isset($key['betInfo']) ? $key['betInfo'] : "", 
						 "errorCode" => 11, 
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
	        	    ]; 
	        	    continue;
				}
				$key["gameId"] = $is_exist_gameid; // Overwrite GameId

				$game_details = ProviderHelper::findGameDetails('game_code', $this->provider_db_id, $key["gameId"]);
					if($game_details == null){ // Game not found
					$items_array[] = [
						 "betInfo" => $key['betInfo'], // Betinfo
					     "winInfo" => $key['winInfo'], // IWininfo
						 "errorCode" => 11, //The playerId was not found
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
	        	    ];  
	        	    continue;
				}
				if($isset_allbets_amount == 0){ # Calculate all total bets
					foreach ($json_data['items'] as $key) {
						array_push($all_bets_amount, $key['betAmount']);
						array_push($duplicate_txid_request, $key['txId']);  // Checking for same txId in the call
					}
					$isset_allbets_amount = 1;
				}
				$client_details = ProviderHelper::getClientDetails('token', $key["token"]);	
				if($client_details == null || $client_details == 'false'){
		 			$items_array[] = [
						 "betInfo" => $key['betInfo'], // Betinfo
					     "winInfo" => $key['winInfo'], // IWininfo
						 "errorCode" => 2, 
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
	        	    ];  
	        	    continue;
		 		}
		 		if($key['ignoreExpiry'] != 'false'){
			 		$token_check = DigitainHelper::tokenCheck($key["token"]);
					if($token_check != true){
						$items_array[] = [
							 "betInfo" => $key['betInfo'], // Betinfo
						     "winInfo" => $key['winInfo'], // IWininfo
							 "errorCode" => 3, 
							 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
		        	    ];  
						continue;
					}
				}
		 		if($client_details->player_id != $key["playerId"]){
					$items_array[] = [
						"betInfo" => $key['betInfo'], // Betinfo
					    "winInfo" => $key['winInfo'], // IWininfo
						"errorCode" => 4, 
						"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
	        	    ];  
					continue;
				}
				if($key['currencyId'] != $client_details->default_currency){
					$items_array[] = [
						 "betInfo" => $key['betInfo'], // Betinfo
					     "winInfo" => $key['winInfo'], // IWininfo
						 "errorCode" => 16, // Currency code dont match!
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
	        	    ];   
	        	    continue;
				}
	 			// $check_win_exist = $this->gameTransactionEXTLog('provider_trans_id',$key['txId'], 1); 
 			    $check_win_exist = GameTransactionMDB::findGameExt($key['txId'], 1,'transaction_id', $client_details);
	 			if($check_win_exist != false && $check_win_exist != "false"){
	 				$items_array[] = [
						 "betInfo" => $key['betInfo'], // Betinfo
					     "winInfo" => $key['winInfo'], // IWininfo
						 "errorCode" => 8, 
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
	        	    ];  
	        	    continue;
	 			}
	 			// $check_win_exist = $this->gameTransactionEXTLog('provider_trans_id',$key['txId'], 2);
	 			$check_win_exist = GameTransactionMDB::findGameExt($key['txId'], 2,'transaction_id', $client_details);
	 			if($check_win_exist != false  && $check_win_exist != "false"){
	 				$items_array[] = [
						 "betInfo" => $key['betInfo'], // Betinfo
					     "winInfo" => $key['winInfo'], // IWininfo
						 "errorCode" => 8, 
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
	        	    ]; 
	        	    continue;
	 			}

				# Provider Transaction Logger
				$general_details['client']['beforebalance'] = $this->formatBalance($client_details->balance);
				$general_details['provider']['operationType'] = $key['betOperationType'];
				$general_details['provider']['currencyId'] = $key['currencyId'];
				$general_details['provider']['amount'] = $key['betAmount'];
				$general_details['provider']['txCreationDate'] = $json_data['timestamp'];
				$general_details['provider']['txId'] = $key['txId'];

				$general_details2['provider']['operationType'] = $key['winOperationType'];
				$general_details2['provider']['currencyId'] = $key['currencyId'];
				$general_details2['provider']['amount'] = $key['winAmount'];
				$general_details2['provider']['txCreationDate'] = $json_data['timestamp'];
				$general_details2['provider']['txId'] = $key['txId'];
				# Provider Transaction Logger
				
				## DEBIT
				$payout_reason = 'Bet : '.$this->getOperationType($key['betOperationType']);
		 		$win_or_lost = 5;
		 		$method = 1;
		 		$income = null; // Sample
		 	    $token_id = $client_details->token_id;
		 	    if(isset($key['roundId'])){
		 	    	$round_id = $key['roundId'];
		 	    }else{
		 	    	$round_id = 1;
		 	    }
		 	    if(isset($key['txId'])){
		 	    	$provider_trans_id = $key['txId'];
		 	    }else{
		 	    	$provider_trans_id = null;
		 	    }

				$gameTransactionData = array(
					"provider_trans_id" => $provider_trans_id,
					"token_id" => $token_id,
					"game_id" => $game_details->game_id,
					"round_id" => $round_id,
					"bet_amount" => $key['betAmount'],
					"win" => $win_or_lost,
					"pay_amount" => 0,
					"income" =>  $income,
					"entry_id" =>$method
				);
				$game_trans = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
				$gameTransactionEXTData = array(
					"game_trans_id" => $game_trans,
					"provider_trans_id" => $provider_trans_id,
					"round_id" => $round_id,
					"amount" => abs($key['betAmount']),
					"game_transaction_type"=> 1,
					"provider_request" =>json_encode($request->all()),
				);
				$game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

				try {
				  $client_response = ClientRequestHelper::fundTransfer($client_details,abs($key['betAmount']),$game_details->game_code,$game_details->game_name,$game_transextension,$game_trans,'debit');
				} catch (\Exception $e) {
					$items_array[] = array(
						"info" => isset($key['betInfo']) ? $key['betInfo'] : "", 
						"errorCode" => 999, 
						"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
						);

					if(isset($game_trans)){
						$updateGameTransaction = ["win" => 2];
						GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);  
						$updateTransactionEXt = array(
							"mw_response" => json_encode($response),
							'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
							'client_response' => json_encode($e->getMessage().' '.$e->getLine().' '.$e->getFile()),
						);
						GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);

						ProviderHelper::saveLogWithExeption('RSG betwin - FATAL ERROR', $this->provider_db_id, json_encode($items_array), DigitainHelper::datesent());
							continue;
					}
				}

				if(isset($client_response->fundtransferresponse->status->code) 
				             && $client_response->fundtransferresponse->status->code == "200"){

					ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
					# Update For THe Bet/Debit Transaction
					$general_details['client']['afterbalance'] = $this->formatBalance($client_response->fundtransferresponse->balance);
					$general_details['aggregator']['externalTxId'] = $game_transextension;
					$general_details['aggregator']['transaction_status'] = 'SUCCESS';

					$updateTransactionEXt = array(
						"mw_response" => json_encode($items_array),
						'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
						'client_response' => json_encode($client_response),
						'transaction_detail' => 'SUCCESS',
						'general_details' => json_encode($general_details)
					);
					GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);

					# Transaction For Credit
					$gameTransactionEXTData = array(
						"game_trans_id" => $game_trans,
						"provider_trans_id" => $provider_trans_id,
						"round_id" => $round_id,
						"amount" => abs($key['winAmount']),
						"game_transaction_type"=> 2,
						"provider_request" =>json_encode($request->all()),
					);
					$game_transextension2 = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

					$client_response2 = ClientRequestHelper::fundTransfer($client_details,abs($key['winAmount']),$game_details->game_code,$game_details->game_name,$game_transextension2,$game_trans,'credit');
					
					# If Credit Failed Modify The DB Update to progressing but response success to the provider
					if(isset($client_response2->fundtransferresponse->status->code) 
						&& $client_response2->fundtransferresponse->status->code == "402"){
						ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance+abs($key['winAmount']));
						$general_details2['client']['beforebalance'] = $this->formatBalance($client_response->fundtransferresponse->balance);
						$general_details2['client']['afterbalance'] =  $this->formatBalance($client_response->fundtransferresponse->balance+abs($key['winAmount']));
						$general_details2['aggregator']['externalTxId'] = $game_transextension2;
						$general_details2['aggregator']['transaction_status'] = 'SUCCESS';
						$items_array[] = [
							"externalTxId" => $game_transextension2, // MW Game Transaction Only Save The Last Game Transaction Which is the credit!
							"balance" => $this->formatBalance($client_response->fundtransferresponse->balance+abs($key['winAmount'])),
							"betInfo" => $key['betInfo'], // Betinfo
							"winInfo" => $key['winInfo'], // IWininfo
							"errorCode" => 1,
							"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
						];	

						$updateTransactionEXt = array(
							"mw_response" => json_encode($items_array),
							'mw_request' => isset($client_response2->requestoclient) ? json_encode($client_response2->requestoclient) : 'FAILED',
							'client_response' => json_encode($client_response2),
							'transaction_detail' => 'FAILED',
							'general_details' => json_encode($general_details2)
						);
						GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension2,$client_details);

					}else{
						ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response2->fundtransferresponse->balance);
						$general_details2['client']['beforebalance'] = $this->formatBalance($client_response->fundtransferresponse->balance);
						$general_details2['client']['afterbalance'] = $this->formatBalance($client_response2->fundtransferresponse->balance);
						$general_details2['aggregator']['externalTxId'] = $game_transextension2;
						$general_details2['aggregator']['transaction_status'] = 'SUCCESS';

						# CREDIT
						$items_array[] = [
							"externalTxId" => $game_transextension2, // MW Game Transaction Only Save The Last Game Transaction Which is the credit!
							"balance" => $this->formatBalance($client_response2->fundtransferresponse->balance),
							"betInfo" => $key['betInfo'], // Betinfo
							"winInfo" => $key['winInfo'], // IWininfo
							"errorCode" => 1,
							"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
						];
						$payout_reason = 'Win : '.$this->getOperationType($key['winOperationType']);
						$win_or_lost = 1;
						$method = 2;
						$token_id = $client_details->token_id;
						if(isset($key['roundId'])){
							$round_id = $key['roundId'];
						}else{
							$round_id = 1;
						}
						if(isset($key['txId'])){
							$provider_trans_id = $key['txId'];
						}else{
							$provider_trans_id = null;
						}
						if(isset($key['betTxId'])){  // Bet TxtID Removed in RSG
							// $bet_transaction_detail = $this->findGameTransaction($key['betTxId']);
							$bet_transaction_detail = GameTransactionMDB::findGameTransactionDetails($game_trans, 'game_transaction', false, $client_details);
							$bet_transaction = $bet_transaction_detail->bet_amount;
						}else{
							// $bet_transaction_detail = $this->findPlayerGameTransaction($key['roundId'], $key['playerId']);
							$bet_transaction_detail = GameTransactionMDB::findGameTransactionDetails($game_trans, 'game_transaction', false, $client_details);
							$bet_transaction = $bet_transaction_detail->bet_amount;
						}
						$income = $bet_transaction - $key['winAmount']; // Sample	
						if($key['winAmount'] != 0){
							if($bet_transaction_detail->bet_amount > $key['winAmount']){
								$win = 0; // lost
								$entry_id = 1; //lost
								$income = $bet_transaction_detail->bet_amount - $key['winAmount'];
							}else{
								$win = 1; //win
								$entry_id = 2; //win
								$income = $bet_transaction_detail->bet_amount - $key['winAmount'];
							}
							$updateGameTransaction = [
								  'pay_amount' => $key['winAmount'], 
							      'income' => $income, 
							      'win' => $win, 
							      'entry_id' => $entry_id,
							];
							GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details); 
							// $updateTheBet = DigitainHelper::updateBetToWin($game_trans, $key['winAmount'], $income, $win, $entry_id);
						}
						$updateTransactionEXt = array(
							"mw_response" => json_encode($items_array),
							'mw_request' => isset($client_response2->requestoclient) ? json_encode($client_response2->requestoclient) : 'FAILED',
							'client_response' => json_encode($client_response2),
							'transaction_detail' => 'SUCCESS',
							'general_details' => json_encode($general_details2)
						);
						GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension2,$client_details);
					}

				}elseif(isset($client_response->fundtransferresponse->status->code) 
				            && $client_response->fundtransferresponse->status->code == "402"){

					$updateGameTransaction = ['win' => $win];
					GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details); 

					$general_details['client']['afterbalance'] = $this->formatBalance($client_response->fundtransferresponse->balance);
					$general_details['aggregator']['externalTxId'] = $game_transextension;
					$general_details['aggregator']['transaction_status'] = 'SUCCESS';
					
					$items_array[] = [
						 "betInfo" => $key['betInfo'], // Betinfo
						 "winInfo" => $key['winInfo'], // IWininfo
						 "errorCode" => 6, // Player Low Balance!
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
	        	    ]; 

	        	    $updateTransactionEXt = array(
						"mw_response" => json_encode($items_array),
						'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
						'client_response' => json_encode($client_response),
						'transaction_detail' => 'FAILED',
						'general_details' => json_encode($general_details)
					);
					GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
	        	    // ProviderHelper::updatecreateGameTransExt($game_transextension,  $json_data, $items_array, $client_response->requestoclient, $client_response, 'FAILED', $general_details);
	        	    continue;
				}
		} # End Foreach
		$response = array(
			 "timestamp" => date('YmdHisms'),
		     "signature" => $this->createSignature(date('YmdHisms')),
			 "errorCode" => 1,
			 "items" => $items_array,
		);
		ProviderHelper::saveLogWithExeption('RSG BETWIN - SUCCESS', $this->provider_db_id, file_get_contents("php://input"), $response);
		return $response;
	}

	/* BETWIN ALL OR NONE LOGIC */
	public function betwinallOrNone($json_data){

		$items_array = array();
		$duplicate_txid_request = array();
		$all_bets_amount = array();
		$isset_allbets_amount = 0;

		$error_encounter = 0;
		$global_error = 1;
	
		$json_data_ii = array();

		foreach ($json_data['items'] as $key => $value) { // FOREACH CHECK
			
				# Missing item param
				if (!isset($value['txId']) || !isset($value['betAmount']) || !isset($value['winAmount']) || !isset($value['token']) || !isset($value['playerId']) || !isset($value['roundId']) || !isset($value['gameId'])) {
					$items_array[] = [
						"betInfo" => isset($value['betInfo']) ? $value['betInfo'] : '', // Info from RSG, MW Should Return it back!
						"winInfo" => isset($value['winInfo']) ? $value['winInfo'] : '', // Info from RSG, MW Should Return it back!
						"errorCode" => 17, // transaction already refunded
						"metadata" => isset($value['metadata']) ? $value['metadata'] : '' // Optional but must be here!
					];
					$global_error = $global_error == 1 ? 17 : $global_error;
					$error_encounter = 1;
					$value['tg_error'] = $global_error;
					array_push($json_data_ii, $value);
					continue;
				}

				$is_exist_gameid = $this->getGameId($value["gameId"]);
				if($is_exist_gameid == false){
					$items_array[] = [
						 "info" => isset($value['betInfo']) ? $value['betInfo'] : '', 
						 "errorCode" => 11, 
						 "metadata" => isset($value['metadata']) ? $value['metadata'] : '' 
	        	    ]; 
	        	    $global_error = 11;
	        	    $error_encounter = 1;
	        	    $value['tg_error'] = $global_error;
					array_push($json_data_ii, $value);
	        	    continue;
				}
				$value["gameId"] = $is_exist_gameid; // Overwrite GameId

				$game_details = ProviderHelper::findGameDetails('game_code', $this->provider_db_id, $value["gameId"]);
				if ($game_details == null) { // Game not found
					$items_array[] = [
						"betInfo" => isset($value['betInfo']) ? $value['betInfo'] : '', // Info from RSG, MW Should Return it back!
						"winInfo" => isset($value['winInfo']) ? $value['winInfo'] : '', // Info from RSG, MW Should Return it back!
						"errorCode" => 11, // transaction already refunded
						"metadata" => isset($value['metadata']) ? $value['metadata'] : '' // Optional but must be here!
					];
					$global_error = $global_error == 1 ? 11 : $global_error;
					$error_encounter = 1;
					$value['tg_error'] = $global_error;
					array_push($json_data_ii, $value);
					continue;
				}
				// $json_data['items'][$i - 1]['game_details'] = $game_details;
				$value['game_details'] = $game_details;


				if ($isset_allbets_amount == 0) { # Calculate all total bets
					foreach ($json_data['items'] as  $key => $key_amount) {
						array_push($all_bets_amount, $key_amount['betAmount']);
						array_push($duplicate_txid_request, $key_amount['txId']);  // Checking for same txId in the call
					}
					$isset_allbets_amount = 1;
				}
				$client_details = ProviderHelper::getClientDetails('token', $value["token"]);
				if ($client_details == null || $client_details == 'false') { // SessionNotFound
					$items_array[] = [
						"betInfo" => isset($value['betInfo']) ? $value['betInfo'] : '', // Info from RSG, MW Should Return it back!
						"winInfo" => isset($value['winInfo']) ? $value['winInfo'] : '', // Info from RSG, MW Should Return it back!
						"errorCode" => 2, // transaction already refunded
						"metadata" => isset($value['metadata']) ? $value['metadata'] : '' // Optional but must be here!
					];
					$global_error = $global_error == 1 ? 2 : $global_error;
					$error_encounter = 1;
					$value['tg_error'] = $global_error;
					array_push($json_data_ii, $value);
					continue;
				}
				// $json_data['items'][$i - 1]['client_details'] = $client_details;
				$value['client_details'] = $client_details;

				if ($value['ignoreExpiry'] != 'false') {
					$token_check = DigitainHelper::tokenCheck($value["token"]);
					if ($token_check != true) {
						$items_array[] = [
							"betInfo" => isset($value['betInfo']) ? $value['betInfo'] : '', // Info from RSG, MW Should Return it back!
							"winInfo" => isset($value['winInfo']) ? $value['winInfo'] : '', // Info from RSG, MW Should Return it back!
							"errorCode" => 3, // transaction already refunded
							"metadata" => isset($value['metadata']) ? $value['metadata'] : '' // Optional but must be here!
						];
						$global_error = $global_error == 1 ? 3 : $global_error;
						$error_encounter = 1;
						$value['tg_error'] = $global_error;
						array_push($json_data_ii, $value);
						continue;
					}
				}
				if ($client_details == null || $client_details == 'false') {
					if ($client_details->player_id != $value["playerId"]) {
						$items_array[] = [
							"betInfo" => isset($value['betInfo']) ? $value['betInfo'] : '',
							"winInfo" => isset($value['winInfo']) ? $value['winInfo'] : '',
							"errorCode" => 4, // transaction already refunded
							"metadata" => isset($value['metadata']) ? $value['metadata'] : ''
						];
						$global_error = $global_error == 1 ? 4 : $global_error;
						$error_encounter = 1;
						$value['tg_error'] = $global_error;
						array_push($json_data_ii, $value);
						continue;
					}
					if ($value['currencyId'] != $client_details->default_currency) {
						$items_array[] = [
							"betInfo" => isset($value['betInfo']) ? $value['betInfo'] : '',
							"winInfo" => isset($value['winInfo']) ? $value['winInfo'] : '',
							"errorCode" => 16, // transaction already refunded
							"metadata" => isset($value['metadata']) ? $value['metadata'] : ''
						];
						$global_error = $global_error == 1 ? 16 : $global_error;
						$error_encounter = 1;
						$value['tg_error'] = $global_error;
						array_push($json_data_ii, $value);
						continue;
					}
				}

				// $check_win_exist = $this->gameTransactionEXTLog('provider_trans_id', $key['txId'], 1);
				$check_win_exist = GameTransactionMDB::findGameExt($value['txId'], 1,'transaction_id', $client_details);
				if ($check_win_exist != false && $check_win_exist != "false"){
					$items_array[] = [
						"betInfo" => isset($value['betInfo']) ? $value['betInfo'] : '',
						"winInfo" => isset($value['winInfo']) ? $value['winInfo'] : '',
						"errorCode" => 8,
						"metadata" => isset($value['metadata']) ? $value['metadata'] : ''
					];
					$global_error = $global_error == 1 ? 8 : $global_error;
					$error_encounter = 1;
					$value['tg_error'] = $global_error;
					array_push($json_data_ii, $value);
					continue;
				}
				// $json_data['items'][$i - 1]['check_win_exist'] = $check_win_exist;
				$value['check_win_exist'] = $check_win_exist;

				// $check_win_exist = $this->gameTransactionEXTLog('provider_trans_id', $key['txId'], 2);
				$check_win_exist = GameTransactionMDB::findGameExt($value['txId'], 2,'transaction_id', $client_details);
				if ($check_win_exist != false && $check_win_exist != "false"){
					$items_array[] = [
						"betInfo" => isset($value['betInfo']) ? $value['betInfo'] : '',
						"winInfo" => isset($value['winInfo']) ? $value['winInfo'] : '',
						"errorCode" => 8,
						"metadata" => isset($value['metadata']) ? $value['metadata'] : ''
					];
					$global_error = $global_error == 1 ? 8 : $global_error;
					$error_encounter = 1;
					$value['tg_error'] = $global_error;
					array_push($json_data_ii, $value);
					continue;
				}
				// $json_data['items'][$i - 1]['check_win_exist'] = $check_win_exist;
				$value['check_win_exist'] = $check_win_exist;

				if ($this->array_has_dupes($duplicate_txid_request)) {
					$items_array[] = [
						"betInfo" => isset($value['betInfo']) ? $value['betInfo'] : '',
						"winInfo" => isset($value['winInfo']) ? $value['winInfo'] : '',
						"errorCode" => 8,
						"metadata" => isset($value['metadata']) ? $value['metadata'] : ''
					];
					$global_error = $global_error == 1 ? 8 : $global_error;
					$error_encounter = 1;
					$value['tg_error'] = $global_error;
					array_push($json_data_ii, $value);
					continue;
				}
		} // END FOREACH CHECK
		if ($error_encounter != 0) { // ELSE PROCEED TO CLIENT TRANSFERING
			$response = array(
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"errorCode" => $global_error,
				"items" => $items_array,
			);
			return $response;
		}else{

			$items_array = array(); // ITEMS INFO
			$duplicate_txid_request = array();
			$all_bets_amount = array();
			$isset_allbets_amount = 0;

			foreach ($json_data_ii as $key) {

				$general_details = ["aggregator" => [], "provider" => [], "client" => []];
				$general_details2 = ["aggregator" => [], "provider" => [], "client" => []];

				// $game_details = $key['game_details'];
		
				if ($isset_allbets_amount == 0) { # Calculate all total bets
					foreach ($json_data['items'] as $key) {
						array_push($all_bets_amount, $key['betAmount']);
						array_push($duplicate_txid_request, $key['txId']);  // Checking for same txId in the call
					}
					$isset_allbets_amount = 1;
				}

				// $client_details = $key['client_details'];
				// $client_player = $key['client_player'];
			
				# Provider Transaction Logger
				$general_details['client']['beforebalance'] = $this->formatBalance($client_details->balance);
				$general_details['provider']['operationType'] = $key['betOperationType'];
				$general_details['provider']['currencyId'] = $key['currencyId'];
				$general_details['provider']['amount'] = $key['betAmount'];
				$general_details['provider']['txCreationDate'] = $json_data['timestamp'];
				$general_details['provider']['txId'] = $key['txId'];

				$general_details2['provider']['operationType'] = $key['winOperationType'];
				$general_details2['provider']['currencyId'] = $key['currencyId'];
				$general_details2['provider']['amount'] = $key['winAmount'];
				$general_details2['provider']['txCreationDate'] = $json_data['timestamp'];
				$general_details2['provider']['txId'] = $key['txId'];
				# Provider Transaction Logger

				## DEBIT
				$payout_reason = 'Bet : ' . $this->getOperationType($key['betOperationType']);
				$win_or_lost = 5;
				$method = 1;
				$income = null; // Sample
				$token_id = $client_details->token_id;
				if (isset($key['roundId'])) {
					$round_id = $key['roundId'];
				} else {
					$round_id = 1;
				}
				if (isset($key['txId'])) {
					$provider_trans_id = $key['txId'];
				} else {
					$provider_trans_id = null;
				}

				// $game_trans = ProviderHelper::createGameTransaction($token_id, $game_details->game_id, $key['betAmount'],  0, $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id, $round_id);
				// $game_transextension = ProviderHelper::createGameTransExtV2($game_trans, $key['txId'], $key['roundId'], abs($key['betAmount']), 1,$json_data);

				$gameTransactionData = array(
					"provider_trans_id" => $provider_trans_id,
					"token_id" => $token_id,
					"game_id" => $game_details->game_id,
					"round_id" => $round_id,
					"bet_amount" => $key['betAmount'],
					"win" => $win_or_lost,
					"pay_amount" => 0,
					"income" =>  $income,
					"entry_id" =>$method
				);
				$game_trans = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
				$gameTransactionEXTData = array(
					"game_trans_id" => $game_trans,
					"provider_trans_id" => $provider_trans_id,
					"round_id" => $round_id,
					"amount" => abs($key['betAmount']),
					"game_transaction_type"=> 1,
					"provider_request" =>json_encode($json_data),
				);
				$game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

				try {
					$client_response = ClientRequestHelper::fundTransfer($client_details, abs($key['betAmount']), $game_details->game_code, $game_details->game_name, $game_transextension, $game_trans, 'debit');
				} catch (\Exception $e) {
					$items_array[] = array(
						"info" => $key['info'],
						"errorCode" => 999,
						"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
					);

					$updateGameTransaction = ["win" => 2];
					GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);  
					$updateTransactionEXt = array(
						"mw_response" => json_encode($response),
						'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
						'client_response' => json_encode($e->getMessage().' '.$e->getLine().' '.$e->getFile()),
					);
					GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);

					ProviderHelper::saveLogWithExeption('RSG betwin - FATAL ERROR', $this->provider_db_id, json_encode($items_array), DigitainHelper::datesent());
						continue;
				}

				if (
					isset($client_response->fundtransferresponse->status->code)
					&& $client_response->fundtransferresponse->status->code == "200"
				) {
					ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
					# CREDIT
					$general_details['client']['afterbalance'] = $this->formatBalance($client_response->fundtransferresponse->balance);
					$general_details['aggregator']['externalTxId'] = $game_transextension;
					$general_details['aggregator']['transaction_status'] = 'SUCCESS';

					// $game_transextension2 = ProviderHelper::createGameTransExtV2($game_trans, $key['txId'], $key['roundId'], abs($key['winAmount']), 2,$json_data);

					$updateTransactionEXt = array(
						"mw_response" => json_encode($items_array),
						'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
						'client_response' => json_encode($client_response),
						'transaction_detail' => 'SUCCESS',
						'general_details' => json_encode($general_details)
					);
					GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);

					# Transaction For Credit
					$gameTransactionEXTData = array(
						"game_trans_id" => $game_trans,
						"provider_trans_id" => $provider_trans_id,
						"round_id" => $round_id,
						"amount" => abs($key['winAmount']),
						"game_transaction_type"=> 2,
						"provider_request" =>json_encode($json_data),
					);
					$game_transextension2 = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);



					$client_response2 = ClientRequestHelper::fundTransfer($client_details, abs($key['winAmount']), $game_details->game_code, $game_details->game_name, $game_transextension2, $game_trans, 'credit');

					# If Credit Failed Modify The DB Update to progressing but response success to the provider
					if(isset($client_response2->fundtransferresponse->status->code) 
						&& $client_response2->fundtransferresponse->status->code == "402"){
						ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance+abs($key['winAmount']));
						$general_details2['client']['beforebalance'] = $this->formatBalance($client_response->fundtransferresponse->balance);
						$general_details2['client']['afterbalance'] =  $this->formatBalance($client_response->fundtransferresponse->balance+abs($key['winAmount']));
						$general_details2['aggregator']['externalTxId'] = $game_transextension2;
						$general_details2['aggregator']['transaction_status'] = 'SUCCESS';
						$items_array[] = [
							"externalTxId" => $game_transextension2, // MW Game Transaction Only Save The Last Game Transaction Which is the credit!
							"balance" => $this->formatBalance($client_response->fundtransferresponse->balance+abs($key['winAmount'])),
							"betInfo" => $key['betInfo'], // Betinfo
							"winInfo" => $key['winInfo'], // IWininfo
							"errorCode" => 1,
							"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
						];	
						// ProviderHelper::updatecreateGameTransExt($game_transextension,  $json_data, $items_array, $client_response->requestoclient, $client_response, 'SUCCESS', $general_details);
						// ProviderHelper::updatecreateGameTransExt($game_transextension2,  $json_data, $items_array, $client_response2->requestoclient, $client_response2, 'FAILED', $general_details2);
						// Providerhelper::createRestrictGame($game_details->game_id,$client_details->player_id,$game_transextension2, $client_response2->requestoclient);

						$updateTransactionEXt = array(
							"mw_response" => json_encode($items_array),
							'mw_request' => isset($client_response2->requestoclient) ? json_encode($client_response2->requestoclient) : 'FAILED',
							'client_response' => json_encode($client_response2),
							'transaction_detail' => 'FAILED',
							'general_details' => json_encode($general_details2)
						);
						GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension2,$client_details);
					}else{
						ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response2->fundtransferresponse->balance);
						$general_details2['client']['beforebalance'] = $this->formatBalance($client_response->fundtransferresponse->balance);
						$general_details2['client']['afterbalance'] = $this->formatBalance($client_response2->fundtransferresponse->balance);
						$general_details2['aggregator']['externalTxId'] = $game_transextension2;
						$general_details2['aggregator']['transaction_status'] = 'SUCCESS';

						# CREDIT
						$items_array[] = [
							"externalTxId" => $game_transextension2, // MW Game Transaction Only Save The Last Game Transaction Which is the credit!
							"balance" => $this->formatBalance($client_response2->fundtransferresponse->balance),
							"betInfo" => $key['betInfo'], // Betinfo
							"winInfo" => $key['winInfo'], // IWininfo
							"errorCode" => 1,
							"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
						];
						$payout_reason = 'Win : '.$this->getOperationType($key['winOperationType']);
						$win_or_lost = 1;
						$method = 2;
						$token_id = $client_details->token_id;
						if(isset($key['roundId'])){
							$round_id = $key['roundId'];
						}else{
							$round_id = 1;
						}
						if(isset($key['txId'])){
							$provider_trans_id = $key['txId'];
						}else{
							$provider_trans_id = null;
						}
						if(isset($key['betTxId'])){  // Bet TxtID Removed in RSG
							// $bet_transaction_detail = $this->findGameTransaction($key['betTxId']);
							$bet_transaction_detail = GameTransactionMDB::findGameTransactionDetails($game_trans, 'game_transaction', false, $client_details);
							$bet_transaction = $bet_transaction_detail->bet_amount;
						}else{
							// $bet_transaction_detail = $this->findPlayerGameTransaction($key['roundId'], $key['playerId']);
							$bet_transaction_detail = GameTransactionMDB::findGameTransactionDetails($game_trans, 'game_transaction', false, $client_details);
							$bet_transaction = $bet_transaction_detail->bet_amount;
						}
						$income = $bet_transaction - $key['winAmount']; // Sample	
						if($key['winAmount'] != 0){
							if($bet_transaction_detail->bet_amount > $key['winAmount']){
								$win = 0; // lost
								$entry_id = 1; //lost
								$income = $bet_transaction_detail->bet_amount - $key['winAmount'];
							}else{
								$win = 1; //win
								$entry_id = 2; //win
								$income = $bet_transaction_detail->bet_amount - $key['winAmount'];
							}
							$updateGameTransaction = [
								  'pay_amount' => $key['winAmount'], 
							      'income' => $income, 
							      'win' => $win, 
							      'entry_id' => $entry_id,
							];
							GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details); 
							// $updateTheBet = DigitainHelper::updateBetToWin($game_trans, $key['winAmount'], $income, $win, $entry_id);
						}
						// $this->updateGameExtTransDetails('BETWON','game_trans_ext_id', $game_transextension,1);
						$updateTransactionEXt = array(
							"mw_response" => json_encode($items_array),
							'mw_request' => isset($client_response2->requestoclient) ? json_encode($client_response2->requestoclient) : 'FAILED',
							'client_response' => json_encode($client_response2),
							'transaction_detail' => 'SUCCESS',
							'general_details' => json_encode($general_details2)
						);
						GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension2,$client_details);
					}
				} elseif (
					isset($client_response->fundtransferresponse->status->code)
					&& $client_response->fundtransferresponse->status->code == "402"
				) {

					// if (ProviderHelper::checkFundStatus($client_response->fundtransferresponse->status->status)) :
					// 	ProviderHelper::updateGameTransactionStatus($game_trans, 2, 6);
					// else :
					// 	ProviderHelper::updateGameTransactionStatus($game_trans, 2, 99);
					// endif;

					$updateGameTransaction = ['win' => $win];
					GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details); 

					$general_details['client']['afterbalance'] = $this->formatBalance($client_response->fundtransferresponse->balance);
					$general_details['aggregator']['externalTxId'] = $game_transextension;
					$general_details['aggregator']['transaction_status'] = 'SUCCESS';

					$items_array[] = [
						"betInfo" => $key['betInfo'], // Betinfo
						"winInfo" => $key['winInfo'], // IWininfo
						"errorCode" => 6, // Player Low Balance!
						"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
					];
					$updateTransactionEXt = array(
						"mw_response" => json_encode($items_array),
						'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
						'client_response' => json_encode($client_response),
						'transaction_detail' => 'FAILED',
						'general_details' => json_encode($general_details)
					);
					GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
					continue;
				}
				## DEBIT
			} # END FOREACH
			$response = array(
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"errorCode" => 1,
				"items" => $items_array,
			);
			ProviderHelper::saveLogWithExeption('RSG BETWIN - SUCCESS', $this->provider_db_id, file_get_contents("php://input"), $response);
			return $response;
		}
	}

	/**
	 * 
	 * Refund Find Logs According to gameround, or TransactionID and refund whether it  a bet or win
	 *
	 * refundOriginalBet (No proper explanation on the doc!)	
	 * originalTxtId = either its winTxd or betTxd	
	 * refundround is true = always roundid	
	 * if roundid is missing always originalTxt, same if originaltxtid use roundId
	 *
	 */
	public function refund(Request $request){
		ProviderHelper::saveLogWithExeption('RSG refund - EH', $this->provider_db_id, file_get_contents("php://input"), 'ENDPOINT HIT');
		$json_data = json_decode(file_get_contents("php://input"), true);
		if($json_data == null){
			return $this->noBody();
		}
		if($json_data['operatorId'] != $this->operator_id){ //Wrong Operator Id 
			return $this->wrongOperatorID();
		}
		if(!$this->authMethod($json_data['operatorId'], $json_data['timestamp'], $json_data['signature'])){ 
			return $this->authError();
		}

		# Missing Parameters
		if(!isset($json_data['items']) || !isset($json_data['operatorId']) || !isset($json_data['timestamp']) || !isset($json_data['signature']) || !isset($json_data['allOrNone']) || !isset($json_data['providerId'])){
			$response = array(
					 "timestamp" => date('YmdHisms'),
				     "signature" => $this->createSignature(date('YmdHisms')),
					 "errorCode" => 17,
					 "items" => [],
   			);	
			return $response;
		}

		#ALL OR NONE AMEND
		if ($json_data['allOrNone'] == 'true') { 
			return $this->refundallOrNone($request->all());
		}
		#ALL OR NONE AMEND

		// ALL GOOD PROCESS EVERYTHING
		$items_array = array();
		$transaction_to_refund = array();

		$transaction_to_refund = array();
 		$is_bet = array();
		$is_bet_amount = array();
 		$is_win = array();
 		$is_win_amount = array();

		foreach ($json_data['items'] as $key) { 
			$general_details = ["aggregator" => [], "provider" => [], "client" => []];


			# 001 (FILTER MDB ONLY ACCEPT REQUET THAT HAS PLAYERID PARAM)
			if(!isset($key['playerId'])) {
	 		    $items_array[] = [
					 "info" => $key['info'],
					 "errorCode" => 4, 
					 "metadata" => isset($key['metadata']) ? $key['metadata'] : ''
			    ];  
				continue;
			}
			if($key['playerId'] == "") {
	 		    $items_array[] = [
					 "info" => $key['info'],
					 "errorCode" => 4, 
					 "metadata" => isset($key['metadata']) ? $key['metadata'] : ''
			    ];  
			    ProviderHelper::saveLogWithExeption("digitain_refund", $this->provider_db_id, json_encode($items_array), 'MISSING PLAYER ID');
				continue;
			}
			$client_details = ProviderHelper::getClientDetails('player_id', $key['playerId']);
 		    if($client_details == null || $client_details == 'false'){
 		    	$items_array[] = [
					 "info" => $key['info'],
					 "errorCode" => 4, 
					 "metadata" => isset($key['metadata']) ? $key['metadata'] : ''
			    ];  
				continue;
 		    }
 		    # END 001

			// $idempotik = $this->gameTransactionEXTLog('provider_trans_id',$key['txId'], 3); 
			$idempotik = GameTransactionMDB::findGameExt($key['txId'], 3,'transaction_id', $client_details);
			if($idempotik != false && $idempotik != "false"){
				$items_array[] = [
						"errorCode" => 14, // TransactionAlreadyRolledBack
						"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
				];  
				continue;
			}
			if($key['refundRound'] == true){  // Use round id always

				// $datatrans = $this->findTransactionRefund($key['roundId'], 'round_id');
				$datatrans = GameTransactionMDB::findGameExt($key['roundId'], 1,'round_id', $client_details);
				if($datatrans == false){
					$items_array[] = [
						 "info" => $key['info'],
						 "errorCode" => 7, // this transaction is not found
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
				    ]; 
					continue;
				}

				$transaction_identifier = $key['roundId'];
				$transaction_identifier_type = 'round_id';

				$provider_request_payload = json_decode($datatrans->provider_request);
				if(isset($provider_request_payload->promoWinAmount) || isset($provider_request_payload->chargeAmount)){
					if(isset($provider_request_payload->playerId)  && $provider_request_payload->playerId != $key['playerId']){
						$items_array[] = [
							 "info" => $key['info'],
							 "errorCode" => 7, // this transaction is not found
							 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
						]; 
						continue;
					}
				}else{
					$db_provider_request_data = $this->findObjDataItem($datatrans->provider_request, $key['roundId'], 'playerId');
					if(isset($key['playerId']) && $key['playerId'] != $db_provider_request_data){
						$items_array[] = [
							 "info" => $key['info'],
							 "errorCode" => 7, // this transaction is not found
							 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
						]; 
						continue;
					}
				}
				$player_id = $key['playerId'];
			}else{ // use both round id and orignaltxtid
				// $datatrans = $this->findTransactionRefund($key['originalTxId'], 'transaction_id');
				$datatrans = GameTransactionMDB::findGameExt($key['originalTxId'], 1,'transaction_id', $client_details);
				$transaction_identifier = $key['originalTxId'];
				$transaction_identifier_type = 'provider_trans_id';
				// if($datatrans != false){
			    //    $player_id = ProviderHelper::getClientDetails('token_id', $datatrans->token_id)->player_id; // IF EXIT
				// }else{
				// 	$player_id = $key['playerId']; // IF NOT DID NOT EXIST
				// }
			}

	    	if($datatrans != 'false'){
				$entry_type = $datatrans->game_transaction_type == 1 ? 'debit' : 'credit';
	    		if($key['refundRound'] == true){
					// $all_rounds = $this->getAllRounds($datatrans->round_id);
					$all_rounds = GameTransactionMDB::findGameExtAll($datatrans->round_id, 'allround', $client_details);
	    			foreach ($all_rounds as $al_round) {
	    				if($al_round->game_transaction_type == 1){
	    					$bet_item = [
								"game_trans_id" => $al_round->game_trans_id,
								"game_trans_ext_id"  => $al_round->game_trans_ext_id,
								"amount" => $al_round->amount,
								"game_transaction_type" => $al_round->game_transaction_type,
							];
							$is_bet_amount[] = $al_round->amount;
							$is_bet[] = $bet_item;
							$transaction_to_refund[] = $bet_item;
	    				}else{
	    					$win_item = [
								"game_trans_id" => $al_round->game_trans_id,
								"game_trans_ext_id"  => $al_round->game_trans_ext_id,
								"amount" => $al_round->amount,
								"game_transaction_type" => $al_round->game_transaction_type,
							];
							$is_win_amount[] = $al_round->amount;
							$is_win[] = $win_item;
							$transaction_to_refund[] = $win_item;
	    				}
	    			}
	    		}else{
					// $check_bet_exist_transaction = DigitainHelper::findGameExt($datatrans->round_id, $datatrans->game_transaction_type,'round_id');
	    			// its a bet round
	    			if($datatrans->game_transaction_type == 1){
						$provider_request_payload = json_decode($datatrans->provider_request);
						if(isset($provider_request_payload->promoWinAmount)){
							$entry_type = 'credit';
							$refund_amount_tobe_used = $provider_request_payload->promoWinAmount;
							$win_item = [
								"game_trans_id" => $datatrans->game_trans_id,
								"game_trans_ext_id"  => $datatrans->game_trans_ext_id,
								"amount" => $refund_amount_tobe_used,
								"game_transaction_type" => $datatrans->game_transaction_type,
							];
							$is_win[] = $win_item;
							$transaction_to_refund[] = $win_item;
							$is_win_amount[] = $refund_amount_tobe_used;
						}else{
							$bet_item = [
								"game_trans_id" => $datatrans->game_trans_id,
								"game_trans_ext_id"  => $datatrans->game_trans_ext_id,
								"amount" => $datatrans->amount,
								"game_transaction_type" => $datatrans->game_transaction_type,
							];
							$is_bet[] = $bet_item;
							$transaction_to_refund[] = $bet_item;
							$is_bet_amount[] = $datatrans->amount;

							// $is_bet_has_won = $this->checkIfBetHasWon('provider_trans_id', $key['originalTxId'], 1);
							// if($is_bet_has_won != null){
							if(isset($datatrans->transaction_detail ) && $datatrans->transaction_detail == "BETWON"){
								$items_array[] = [
									"info" => $key['info'],
									"errorCode" => 20,
									"metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
								]; 
								continue;
							}
						}
	    			}else{
	    			// its a win round
	    				$win_item = [
							"game_trans_id" => $datatrans->game_trans_id,
							"game_trans_ext_id"  => $datatrans->game_trans_ext_id,
							"amount" => $datatrans->amount,
							"game_transaction_type" => $datatrans->game_transaction_type,
						];
						$is_win[] = $win_item;
						$transaction_to_refund[] = $win_item;
						$is_win_amount[] = $datatrans->amount;
	    			}
	    		}
			}


			# IF BET IS ALREADY WON WHEN REFUNDROUND IS FALSE
			if(count($transaction_to_refund) > 0){
				if($key['refundRound'] == false){
					if($entry_type == 'debit'){
						if(count($is_win) > 0){ // This Bet Has Already Wonned
							$items_array[] = [
								 "info" => $key['info'],
								 "errorCode" => 20,
								 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
						    ]; 
							continue;
						}
					}
				}
			}

			if($datatrans != false){ // TRANSACTION IS FOUND
					$game_details = Helper::getInfoPlayerGameRound($client_details->player_token);
					$round_id = $transaction_identifier;
					$bet_amount = $datatrans->amount;
					$entry_id = 3;
					$win = 4; //3 draw, 4 refund, 1 lost win is refunded
					$pay_amount = 0;
  				    $income = 0;
			 			
					if($key['refundRound'] == false){ // 1 Transaction to refund
						if($entry_type == 'credit'){ 
							$is_win_amount = count($is_win) > 0 ? array_sum($is_win_amount) : 0;
						    $amount = $is_win_amount;
						    $transactiontype = 'debit';
						    if(abs($client_player->playerdetailsresponse->balance) < abs($amount)){
								$items_array[] = [
							 	 	"info" => $key['info'],
								 	"errorCode" => 6, // Player Low Balance!
									"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
				        	    ]; 
			        	   	    continue;
				        	}
				        	$win = 1; //3 draw, 4 refund, 1 lost win is refunded
	  						$entry_id = 2;
	  						$pay_amount = $amount;
	  						$income = $bet_amount - $pay_amount;
						}else{ 
							$is_bet_amount = count($is_bet) > 0 ? array_sum($is_bet_amount) : 0;
							$pay_amount = $is_bet_amount;
							$amount = $is_bet_amount;
							$income = $bet_amount - $pay_amount;
						    $transactiontype = 'credit';
						}
					
					}else{
						$is_bet_amount = count($is_bet) > 0 ? array_sum($is_bet_amount) : 0;
					    $is_win_amount = count($is_win) > 0 ? array_sum($is_win_amount) : 0;
					    $amount = abs($is_bet_amount)-abs($is_win_amount);
						$pay_amount = $bet_amount;
						$income = $bet_amount - $pay_amount;
						if($is_win_amount > $is_bet_amount){
		  					$transactiontype = 'debit'; // overwrite the transaction type
		  					if(abs($client_details->balance) < abs($amount)){
								$items_array[] = [
							 	 	"info" => $key['info'],
								 	"errorCode" => 6, // Player Low Balance!
									"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
				        	    ]; 
			        	   	    continue;
				        	}
		  				}else{
		  					$transactiontype = 'credit'; // overwrite the transaction type
		  				}
					}
					
	  				// $game_transextension = ProviderHelper::createGameTransExtV2($datatrans->game_trans_id, $key['txId'], $round_id, abs($amount), 3,$request->all());

	  				$gameTransactionEXTData = array(
						"game_trans_id" => $datatrans->game_trans_id,
						"provider_trans_id" => $key['txId'],
						"round_id" => $round_id,
						"amount" => abs($amount),
						"game_transaction_type"=> 3,
						"provider_request" =>json_encode($request->all()),
					);
					$game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
								 	
					try {
					$client_response = ClientRequestHelper::fundTransfer($client_details,abs($amount),$game_details->game_code,$game_details->game_name,$game_transextension,$datatrans->game_trans_id,$transactiontype,true);
					} catch (\Exception $e) {
						$items_array[] = array(
							 "info" => $key['info'], 
							 "errorCode" => 999, 
							 "metadata" => isset($key['metadata']) ? $key['metadata'] : ''
						);

						// ProviderHelper::updatecreateGameTransExt($game_transextension, file_get_contents("php://input"), $json_data, 'FAILED', $e->getMessage().' '.$e->getLine(), 'FAILED', 'FAILED');
						// ProviderHelper::saveLogWithExeption($datatrans->game_trans_id, $this->provider_db_id, json_encode($items_array), 'FATAL ERROR');
						// 	continue;

						$updateGameTransaction = ["win" => 2];
						GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);  
						$updateTransactionEXt = array(
							"mw_response" => json_encode($response),
							'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
							'client_response' => json_encode($e->getMessage().' '.$e->getLine().' '.$e->getFile()),
						);
						GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
						ProviderHelper::saveLogWithExeption($datatrans->game_trans_id, $this->provider_db_id, json_encode($items_array), 'FATAL ERROR');
						continue;
					}

					if(isset($client_response->fundtransferresponse->status->code) 
					             && $client_response->fundtransferresponse->status->code == "200"){
							ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
				 		    # Provider Transaction Logger
				 		    $general_details['client']['beforebalance'] = $this->formatBalance($client_details->balance);
							// $general_details['provider']['operationType'] = $key['operationType'];
							$general_details['provider']['currencyId'] = $client_details->default_currency;
							$general_details['provider']['txCreationDate'] = $json_data['timestamp'];
							$general_details['provider']['txId'] = $key['txId'];
							# Provider Transaction Logger

							$general_details['provider']['amount'] = $amount; // overall amount
							$general_details['client']['afterbalance'] = $this->formatBalance($client_response->fundtransferresponse->balance);
							$general_details['aggregator']['externalTxId'] = $game_transextension;
							$general_details['aggregator']['transaction_status'] = 'SUCCESS';

							// $updateTheBet = $this->updateBetToWin($datatrans->round_id, $pay_amount, $income, $win, $entry_id);
							// $updateTheBet = DigitainHelper::updateBetToWin($datatrans->game_trans_id, $pay_amount, $income, $win, $entry_id);

							$updateGameTransaction = [
								  'pay_amount' => $pay_amount, 
							      'income' => $income, 
							      'win' => $win, 
							      'entry_id' => $entry_id,
							];
							GameTransactionMDB::updateGametransaction($updateGameTransaction, $datatrans->game_trans_id, $client_details); 
							$items_array[] = [
			        	    	 "externalTxId" => $game_transextension, // MW Game Transaction Id
								 "balance" => $this->formatBalance($client_response->fundtransferresponse->balance),
								 "info" => $key['info'], // Info from RSG, MW Should Return it back!
								 "errorCode" => 1,
								 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
			        	    ];
							// ProviderHelper::updatecreateGameTransExt($game_transextension,  $json_data, $items_array, $client_response->requestoclient, $client_response, 'SUCCESS', $general_details);

							$updateTransactionEXt = array(
								"mw_response" => json_encode($items_array),
								'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
								'client_response' => json_encode($client_response),
								'transaction_detail' => 'SUCCESS',
								'general_details' => json_encode($general_details)
							);
							GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
							continue;

					}elseif(isset($client_response->fundtransferresponse->status->code) 
					            && $client_response->fundtransferresponse->status->code == "402"){

						$general_details['provider']['amount'] = $amount; // overall amount
						$general_details['client']['afterbalance'] = $this->formatBalance($client_response->fundtransferresponse->balance);
						$general_details['aggregator']['externalTxId'] = $game_transextension;
						$general_details['aggregator']['transaction_status'] = 'FAILED';

						$items_array[] = [
					 	 	"info" => $key['info'],
						 	"errorCode" => 6, // Player Low Balance!
							"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
		        	    ]; 
		        	    // ProviderHelper::updatecreateGameTransExt($game_transextension,  $json_data, $items_array, $client_response->requestoclient, $client_response, 'FAILED', $general_details);

		        	    $updateTransactionEXt = array(
							"mw_response" => json_encode($items_array),
							'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
							'client_response' => json_encode($client_response),
							'transaction_detail' => 'FAILED',
							'general_details' => json_encode($general_details)
						);
						GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);

		        	    continue;
					}
			}else{
					$items_array[] = [
						 "info" => $key['info'],
						 "errorCode" => 7, // this transaction is not found
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
				    ]; 
					continue;
			}

		} # END FOREARCH

		$response = array(
			 "timestamp" => date('YmdHisms'),
		     "signature" => $this->createSignature(date('YmdHisms')),
			 "errorCode" => 1,
			 "items" => $items_array,
		);	
		ProviderHelper::saveLogWithExeption('RSG refund - SUCCESS', $this->provider_db_id, file_get_contents("php://input"), $response);
		return $response;
	}



	/*  Refund All or none Logic */
	public function refundallOrNone($json_data) {
		$items_allOrNone = array(); // ITEMS TO ROLLBACK IF ONE OF THE ITEMS FAILED!
		$items_revert_update = array(); // If failed revert changes
		$items_array = array();
		$error_encounter = 0;
		$global_error = 1;

		// Outside the loop
		$transaction_to_refund = array();
		$is_bet = array();
		$is_win = array();
		$refund_duplicate_txid_request = array();
		$i=0;

		foreach ($json_data['items'] as $key) { // #1 FOREACH CHECK
		$i++;

				# 001 (FILTER MDB ONLY ACCEPT REQUET THAT HAS PLAYERID PARAM)
				if(!isset($key['playerId'])) {
		 		    $items_array[] = [
						 "info" => $key['info'],
						 "errorCode" => 4, 
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : ''
				    ];  
				    $global_error = $global_error == 1 ? 4 : $global_error;
					continue;
				}
				if($key['playerId'] == "") {
		 		    $items_array[] = [
						 "info" => $key['info'],
						 "errorCode" => 4, 
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : ''
				    ];  
				    $global_error = $global_error == 1 ? 4 : $global_error;
				    ProviderHelper::saveLogWithExeption("digitain_refund", $this->provider_db_id, json_encode($items_array), 'MISSING PLAYER ID');
					continue;
				}
				$client_details = ProviderHelper::getClientDetails('player_id', $key['playerId']);
	 		    if($client_details == null || $client_details == 'false'){
	 		    	$items_array[] = [
						 "info" => $key['info'],
						 "errorCode" => 4, 
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : ''
				    ];  
				    $global_error = $global_error == 1 ? 4 : $global_error;
					$error_encounter = 1;
					continue;
	 		    }
	 		    # END 001

				// $idempotik = $this->gameTransactionEXTLog('provider_trans_id',$key['txId'], 3); 
				// if($idempotik != false){
				// 	$items_array[] = [
				// 		    "errorCode" => 14, // TransactionAlreadyRolledBack
				// 			"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
				// 	];  
				// 	$global_error = $global_error == 1 ? 8 : $global_error;
				// 	$error_encounter = 1;
				// 	continue;
				// }
				$idempotik = GameTransactionMDB::findGameExt($key['txId'], 3,'transaction_id', $client_details);
				if($idempotik != false && $idempotik != "false"){
					$items_array[] = [
							"errorCode" => 14, // TransactionAlreadyRolledBack
							"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
					];  
					$global_error = $global_error == 1 ? 8 : $global_error;
					$error_encounter = 1;
					continue;
				}

				// Duplicate Checker
				array_push($refund_duplicate_txid_request, $key['txId']);
				if ($this->array_has_dupes($refund_duplicate_txid_request)) {
					$items_array[] = [
						"info" => $key['info'],
						"errorCode" => 8,
						"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
					];
					$global_error = $global_error == 1 ? 8 : $global_error;
					$error_encounter = 1;
					continue;
				}

				if ($key['refundRound'] == true) {  // Use round id always
					// $datatrans = $this->findTransactionRefund($key['roundId'], 'round_id');
					$datatrans = GameTransactionMDB::findGameExt($key['roundId'], 1,'round_id', $client_details);
					if ($datatrans == false) {
						$items_array[] = [
							"info" => $key['info'],
							"errorCode" => 7, // this transaction is not found
							"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
						];
						$global_error = $global_error == 1 ? 7 : $global_error;
						$error_encounter = 1;
						continue;
					}
					$transaction_identifier = $key['roundId'];
					$transaction_identifier_type = 'round_id';

					$provider_request_payload = json_decode($datatrans->provider_request);
					if(isset($provider_request_payload->promoWinAmount) || isset($provider_request_payload->chargeAmount)){
						if(isset($provider_request_payload->playerId)  && $provider_request_payload->playerId != $key['playerId']){
							$items_array[] = [
								 "info" => $key['info'],
								 "errorCode" => 7, // this transaction is not found
								 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
							]; 
							$global_error = $global_error == 1 ? 7 : $global_error;
							$error_encounter = 1;
							continue;
						}
					}else{
						$db_provider_request_data = $this->findObjDataItem($datatrans->provider_request, $key['roundId'], 'playerId');
						if(isset($key['playerId']) && $key['playerId'] != $db_provider_request_data){
							$items_array[] = [
								 "info" => $key['info'],
								 "errorCode" => 7, // this transaction is not found
								 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
							]; 
							$global_error = $global_error == 1 ? 7 : $global_error;
							$error_encounter = 1;
							continue;
						}
					}
					$player_id = $key['playerId'];
				} else { // use both round id and orignaltxtid
					// $datatrans = $this->findTransactionRefund($key['originalTxId'], 'transaction_id');
					$datatrans = GameTransactionMDB::findGameExt($key['originalTxId'], 1,'transaction_id', $client_details);
					$transaction_identifier = $key['originalTxId'];
					$transaction_identifier_type = 'provider_trans_id';
					// if ($datatrans != false) {
					// 	$player_id = ProviderHelper::getClientDetails('token_id', $datatrans->token_id)->player_id; // IF EXIT
					// } else {
					// 	$player_id = $key['playerId']; // IF NOT DID NOT EXIST
					// }
				}

				$json_data['items'][$i - 1]['datatrans'] = $datatrans;
				$json_data['items'][$i - 1]['transaction_identifier'] = $transaction_identifier;
				$json_data['items'][$i - 1]['transaction_identifier_type'] = $transaction_identifier_type;
				$json_data['items'][$i - 1]['player_id'] = $client_details->player_id;

				if ($datatrans != false) {
					$entry_type = $datatrans->game_transaction_type == 1 ? 'debit' : 'credit';
					if ($key['refundRound'] == true) {
						// $all_rounds = $this->getAllRounds($datatrans->round_id);
						$all_rounds = GameTransactionMDB::findGameExtAll($datatrans->round_id, 'round_id', $client_details);
		    			foreach ($all_rounds as $al_round) {
		    				if($al_round->game_transaction_type == 1){
		    					$bet_item = [
									"game_trans_id" => $al_round->game_trans_id,
									"game_trans_ext_id"  => $al_round->game_trans_ext_id,
									"amount" => $al_round->amount,
									"game_transaction_type" => $al_round->game_transaction_type,
								];
								$is_bet_amount[] = $al_round->amount;
								$is_bet[] = $bet_item;
								$transaction_to_refund[] = $bet_item;
		    				}else{
		    					$win_item = [
									"game_trans_id" => $al_round->game_trans_id,
									"game_trans_ext_id"  => $al_round->game_trans_ext_id,
									"amount" => $al_round->amount,
									"game_transaction_type" => $al_round->game_transaction_type,
								];
								$is_win_amount[] = $al_round->amount;
								$is_win[] = $win_item;
								$transaction_to_refund[] = $win_item;
		    				}
		    			}
					} else {
						if($datatrans->game_transaction_type == 1){
							$provider_request_payload = json_decode($datatrans->provider_request);
							if(isset($provider_request_payload->promoWinAmount)){
								$entry_type = 'credit';
								$refund_amount_tobe_used = $provider_request_payload->promoWinAmount;
								$win_item = [
									"game_trans_id" => $datatrans->game_trans_id,
									"game_trans_ext_id"  => $datatrans->game_trans_ext_id,
									"amount" => $refund_amount_tobe_used,
									"game_transaction_type" => $datatrans->game_transaction_type,
								];
								$is_win[] = $win_item;
								$transaction_to_refund[] = $win_item;
								$is_win_amount[] = $refund_amount_tobe_used;
							}else{
								$bet_item = [
									"game_trans_id" => $datatrans->game_trans_id,
									"game_trans_ext_id"  => $datatrans->game_trans_ext_id,
									"amount" => $datatrans->amount,
									"game_transaction_type" => $datatrans->game_transaction_type,
								];
								$is_bet[] = $bet_item;
								$transaction_to_refund[] = $bet_item;
								$is_bet_amount[] = $datatrans->amount;

								// if($is_bet_has_won != null){
								if(isset($datatrans->transaction_detail ) && $datatrans->transaction_detail == "BETWON"){
									$items_array[] = [
										"info" => $key['info'],
										"errorCode" => 20,
										"metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
									]; 
									$global_error = $global_error == 1 ? 20 : $global_error;
									$error_encounter = 1;
									continue;
								}
							}
		    			}else{
		    			// its a win round
		    				$win_item = [
								"game_trans_id" => $datatrans->game_trans_id,
								"game_trans_ext_id"  => $datatrans->game_trans_ext_id,
								"amount" => $datatrans->amount,
								"game_transaction_type" => $datatrans->game_transaction_type,
							];
							$is_win[] = $win_item;
							$transaction_to_refund[] = $win_item;
							$is_win_amount[] = $datatrans->amount;
		    			}
					}
				}


				# IF BET IS ALREADY WON WHEN REFUNDROUND IS FALSE
				if (count($transaction_to_refund) > 0) {
					if ($key['refundRound'] == false) {
						if ($entry_type == 'debit') {
							if (count($is_win) > 0) { // This Bet Has Already Wonned
								$items_array[] = [
									"info" => $key['info'],
									"errorCode" => 20,
									"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
								];
								$global_error = $global_error == 1 ? 20 : $global_error;
								$error_encounter = 1;
								continue;
							}
						}
					}
				}

				// $client_details = ProviderHelper::getClientDetails('player_id', $player_id);
				// if ($client_details == null || $client_details == 'false') {
				// 	$items_array[] = [
				// 		"info" => $key['info'],
				// 		"errorCode" => 4,
				// 		"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
				// 	];
				// 	$global_error = $global_error == 1 ? 4 : $global_error;
				// 	$error_encounter = 1;
				// 	continue;
				// }
				$json_data['items'][$i - 1]['client_details'] = $client_details;

				// $client_player = DigitainHelper::playerDetailsCall($client_details);
				// if ($client_player == 'false') { // client cannot be reached! http errors etc!
				// 	$items_array[] = [
				// 		"info" => $key['info'],
				// 		"errorCode" => 999,
				// 		"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
				// 	];
				// 	$global_error = $global_error == 1 ? 999 : $global_error;
				// 	$error_encounter = 1;
				// 	continue;
				// }
				$json_data['items'][$i - 1]['client_player'] = $client_details;

		} // #1 END FOREACH CHECK

		if ($error_encounter != 0) { 
			$response = array(
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"errorCode" => $global_error,
				"items" => $items_array,
			);
			return $response;
		}else{

			$items_array = array();
			$transaction_to_refund = array();
			$transaction_to_refund = array();
			$is_bet = array();
			$is_win = array();
			$is_bet_amount = array();
			$is_win_amount = array();

			foreach ($json_data['items'] as $key) {
				$general_details = ["aggregator" => [], "provider" => [], "client" => []];


				if ($key['refundRound'] == true) {  // Use round id always
					$datatrans = $key['datatrans'];
					$transaction_identifier = $key['transaction_identifier'];
					$transaction_identifier_type = $key['transaction_identifier_type'];
					$player_id = $key['playerId'];
				} else { // use both round id and orignaltxtid
					$datatrans = $key['datatrans'];
					$transaction_identifier = $key['transaction_identifier'];
					$transaction_identifier_type = $key['transaction_identifier_type'];
					$player_id = $key['playerId'];
				}

				if ($datatrans != false) {
					$entry_type = $datatrans->game_transaction_type == 1 ? 'debit' : 'credit';
					if ($key['refundRound'] == true) {
						// $check_bet_exist_transaction = DigitainHelper::findGameExt($datatrans->round_id, 1, 'round_id');
						$check_bet_exist_transaction = GameTransactionMDB::findGameExt($datatrans->round_id, 1,'round_id', $client_details);
						foreach ($all_rounds as $al_round) {
		    				if($al_round->game_transaction_type == 1){
		    					$bet_item = [
									"game_trans_id" => $al_round->game_trans_id,
									"game_trans_ext_id"  => $al_round->game_trans_ext_id,
									"amount" => $al_round->amount,
									"game_transaction_type" => $al_round->game_transaction_type,
								];
								$is_bet_amount[] = $al_round->amount;
								$is_bet[] = $bet_item;
								$transaction_to_refund[] = $bet_item;
		    				}else{
		    					$win_item = [
									"game_trans_id" => $al_round->game_trans_id,
									"game_trans_ext_id"  => $al_round->game_trans_ext_id,
									"amount" => $al_round->amount,
									"game_transaction_type" => $al_round->game_transaction_type,
								];
								$is_win_amount[] = $al_round->amount;
								$is_win[] = $win_item;
								$transaction_to_refund[] = $win_item;
		    				}
		    			}
					} else {
						if($datatrans->game_transaction_type == 1){
		    				$provider_request_payload = json_decode($datatrans->provider_request);
								if(isset($provider_request_payload->promoWinAmount)){
									$entry_type = 'credit';
									$refund_amount_tobe_used = $provider_request_payload->promoWinAmount;
									$win_item = [
										"game_trans_id" => $datatrans->game_trans_id,
										"game_trans_ext_id"  => $datatrans->game_trans_ext_id,
										"amount" => $refund_amount_tobe_used,
										"game_transaction_type" => $datatrans->game_transaction_type,
									];
									$is_win[] = $win_item;
									$transaction_to_refund[] = $win_item;
									$is_win_amount[] = $refund_amount_tobe_used;
								}else{
									$bet_item = [
										"game_trans_id" => $datatrans->game_trans_id,
										"game_trans_ext_id"  => $datatrans->game_trans_ext_id,
										"amount" => $datatrans->amount,
										"game_transaction_type" => $datatrans->game_transaction_type,
									];
									$is_bet[] = $bet_item;
									$transaction_to_refund[] = $bet_item;
									$is_bet_amount[] = $datatrans->amount;

									// $is_bet_has_won = $this->checkTransactionExt('round_id', $datatrans->round_id, 2);
									// $is_bet_has_won = $this->checkIfBetHasWon('provider_trans_id', $key['originalTxId'], 1);
									// if($is_bet_has_won != null){
									if(isset($datatrans->transaction_detail ) && $datatrans->transaction_detail == "BETWON"){
										$items_array[] = [
											"info" => $key['info'],
											"errorCode" => 20,
											"metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
										]; 
										$global_error = $global_error == 1 ? 20 : $global_error;
										$error_encounter = 1;
										continue;
									}
								}
		    			}else{
		    			// its a win round
		    				$win_item = [
								"game_trans_id" => $datatrans->game_trans_id,
								"game_trans_ext_id"  => $datatrans->game_trans_ext_id,
								"amount" => $datatrans->amount,
								"game_transaction_type" => $datatrans->game_transaction_type,
							];
							$is_win[] = $win_item;
							$transaction_to_refund[] = $win_item;
							$is_win_amount[] = $datatrans->amount;
		    			}
					}
				}


				# IF BET IS ALREADY WON WHEN REFUNDROUND IS FALSE
				if (count($transaction_to_refund) > 0) {
					if ($key['refundRound'] == false) {
						if ($entry_type == 'debit') {
							if (count($is_win) > 0) { // This Bet Has Already Wonned
								$items_array[] = [
									"info" => $key['info'],
									"errorCode" => 20,
									"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
								];
								continue;
							}
						}
					}
				}
				$client_details = $key['client_details'];
				// $client_player = $key['client_player'];
				

				if ($datatrans != false) { // TRANSACTION IS FOUND
					$game_details = Helper::getInfoPlayerGameRound($client_details->player_token);
					$round_id = $transaction_identifier;
					$bet_amount = $datatrans->amount;
					$entry_id = 3;
					$win = 4; //3 draw, 4 refund, 1 lost win is refunded
					$pay_amount = 0;
					$income = 0;

					if($key['refundRound'] == false){ // 1 Transaction to refund
						if($entry_type == 'credit'){ 
							$is_win_amount = count($is_win) > 0 ? array_sum($is_win_amount) : 0;
						    $amount = $is_win_amount;
						    $transactiontype = 'debit';
						    if(abs($client_player->playerdetailsresponse->balance) < abs($amount)){
								$items_array[] = [
							 	 	"info" => $key['info'],
								 	"errorCode" => 6, // Player Low Balance!
									"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
				        	    ]; 
			        	   	    continue;
				        	}
				        	$win = 1; //3 draw, 4 refund, 1 lost win is refunded
	  						$entry_id = 2;
	  						$pay_amount = $amount;
	  						$income = $bet_amount - $pay_amount;
						}else{ 
							$is_bet_amount = count($is_bet) > 0 ? array_sum($is_bet_amount) : 0;
							$pay_amount = $is_bet_amount;
							$amount = $is_bet_amount;
							$income = $bet_amount - $pay_amount;
						    $transactiontype = 'credit';
						}
					
					}else{
						$is_bet_amount = count($is_bet) > 0 ? array_sum($is_bet_amount) : 0;
					    $is_win_amount = count($is_win) > 0 ? array_sum($is_win_amount) : 0;
					    $amount = abs($is_bet_amount)-abs($is_win_amount);
						$pay_amount = $bet_amount;
						$income = $bet_amount - $pay_amount;
						if($amount < 0){
		  					$transactiontype = 'debit'; // overwrite the transaction type
		  					if(abs($client_player->playerdetailsresponse->balance) < abs($amount)){
								$items_array[] = [
							 	 	"info" => $key['info'],
								 	"errorCode" => 6, // Player Low Balance!
									"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
				        	    ]; 
			        	   	    continue;
				        	}
		  				}else{
		  					$transactiontype = 'credit'; // overwrite the transaction type
		  				}
					}


					// $game_transextension = ProviderHelper::createGameTransExtV2($datatrans->game_trans_id, $key['txId'], $round_id, abs($amount), 3,$json_data);

					$gameTransactionEXTData = array(
						"game_trans_id" => $datatrans->game_trans_id,
						"provider_trans_id" => $key['txId'],
						"round_id" => $round_id,
						"amount" => abs($amount),
						"game_transaction_type"=> 3,
						"provider_request" =>json_encode($json_data),
					);
					$game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
								 	

					try {
						$client_response = ClientRequestHelper::fundTransfer($client_details, abs($amount), $game_details->game_code, $game_details->game_name, $game_transextension, $datatrans->game_trans_id, $transactiontype, true);
					} catch (\Exception $e) {
						$items_array[] = array(
							"info" => $key['info'],
							"errorCode" => 999,
							"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
						);
						$updateGameTransaction = ["win" => 2];
						GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);  
						$updateTransactionEXt = array(
							"mw_response" => json_encode($response),
							'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
							'client_response' => json_encode($e->getMessage().' '.$e->getLine().' '.$e->getFile()),
						);
						GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
						ProviderHelper::saveLogWithExeption($datatrans->game_trans_id, $this->provider_db_id, json_encode($items_array), 'FATAL ERROR');
						continue;
					}

					if (
						isset($client_response->fundtransferresponse->status->code)
						&& $client_response->fundtransferresponse->status->code == "200"
					) {
						ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
						# Provider Transaction Logger
						$general_details['client']['beforebalance'] = $this->formatBalance($client_details->balance);
						// $general_details['provider']['operationType'] = $key['operationType'];
						$general_details['provider']['currencyId'] = $client_details->default_currency;
						$general_details['provider']['txCreationDate'] = $json_data['timestamp'];
						$general_details['provider']['txId'] = $key['txId'];
						# Provider Transaction Logger

						$general_details['provider']['amount'] = $amount; // overall amount
						$general_details['client']['afterbalance'] = $this->formatBalance($client_details->balance);
						$general_details['aggregator']['externalTxId'] = $game_transextension;
						$general_details['aggregator']['transaction_status'] = 'SUCCESS';

						// $updateTheBet = DigitainHelper::updateBetToWin($datatrans->game_trans_id, $pay_amount, $income, $win, $entry_id);

						$updateGameTransaction = [
							  'pay_amount' => $pay_amount, 
						      'income' => $income, 
						      'win' => $win, 
						      'entry_id' => $entry_id,
						];
						GameTransactionMDB::updateGametransaction($updateGameTransaction, $datatrans->game_trans_id, $client_details);

						$items_array[] = [
							"externalTxId" => $game_transextension, // MW Game Transaction Id
							"balance" => $this->formatBalance($client_response->fundtransferresponse->balance),
							"info" => $key['info'], // Info from RSG, MW Should Return it back!
							"errorCode" => 1,
							"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
						];
						// ProviderHelper::updatecreateGameTransExt($game_transextension,  $json_data, $items_array, $client_response->requestoclient, $client_response, 'SUCCESS', $general_details);

						$updateTransactionEXt = array(
							"mw_response" => json_encode($items_array),
							'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
							'client_response' => json_encode($client_response),
							'transaction_detail' => 'SUCCESS',
							'general_details' => json_encode($general_details)
						);
						GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
						continue;
					} elseif (
						isset($client_response->fundtransferresponse->status->code)
						&& $client_response->fundtransferresponse->status->code == "402"
					) {

						$general_details['provider']['amount'] = $amount; // overall amount
						$general_details['client']['afterbalance'] = $this->formatBalance($client_response->fundtransferresponse->balance);
						$general_details['aggregator']['externalTxId'] = $game_transextension;
						$general_details['aggregator']['transaction_status'] = 'FAILED';

						$items_array[] = [
							"info" => $key['info'],
							"errorCode" => 6, // Player Low Balance!
							"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
						];
						// ProviderHelper::updatecreateGameTransExt($game_transextension,  $json_data, $items_array, $client_response->requestoclient, $client_response, 'FAILED', $general_details);

						$updateTransactionEXt = array(
							"mw_response" => json_encode($items_array),
							'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
							'client_response' => json_encode($client_response),
							'transaction_detail' => 'FAILED',
							'general_details' => json_encode($general_details)
						);
						GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
						continue;
					} 
				} else {
						$items_array[] = [
							"info" => $key['info'],
							"errorCode" => 7, // this transaction is not found
							"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
						];
						continue;
				}
			} # END FOREARCH

			$response = array(
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"errorCode" => 1,
				"items" => $items_array,
			);
			ProviderHelper::saveLogWithExeption('RSG refund - SUCCESS', $this->provider_db_id, file_get_contents("php://input"), $response);
			return $response;
		}
	}

	/**
	 * Amend Win
	 */
	public function amend(Request $request){
		ProviderHelper::saveLogWithExeption('RSG amend - EH', $this->provider_db_id, file_get_contents("php://input"), 'ENDPOINT HIT');
		$json_data = json_decode(file_get_contents("php://input"), true);
		if($json_data == null){
			return $this->noBody();
		}
		if (!$this->authMethod($json_data['operatorId'], $json_data['timestamp'], $json_data['signature'])){
			return $this->authError();
		}
		if($json_data['operatorId'] != $this->operator_id){
			return $this->wrongOperatorID();
		}
		# Missing Parameters
		if(!isset($json_data['providerId']) || !isset($json_data['allOrNone']) || !isset($json_data['signature']) || !isset($json_data['timestamp']) || !isset($json_data['operatorId']) || !isset($json_data['items'])){
			$response = array(
					 "timestamp" => date('YmdHisms'),
				     "signature" => $this->createSignature(date('YmdHisms')),
					 "errorCode" => 17,
					 "items" => [],
   			);	
			return $response;
		}

		#ALL OR NONE AMEND
		if ($json_data['allOrNone'] == 'true') {
			return $this->amendallOrNone($request->all());
		}
		#END ALL OR NONE

		$items_array = array(); // ITEMS INFO
		$duplicate_txid_request = array();
	    $all_bets_amount = array();
		$isset_allbets_amount = 0;
		// ALL GOOD PROCESS IT

		foreach ($json_data['items'] as $key) {
			$general_details = ["aggregator" => [],"provider" => [],"client" => []];
			if(!isset($key['playerId']) || !isset($key['gameId']) || !isset($key['roundId']) || !isset($key['txId']) || !isset($key['winTxId']) || !isset($key['winOperationType']) || !isset($key['currencyId']) || !isset($key['info']) || !isset($key['amendAmount'])){
				$items_array[] = [
					 "info" => isset($key['info']) ? $key['info'] : '', // Info from RSG, MW Should Return it back!
					 "errorCode" => 17, //The playerId was not found
					 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
        	    ];  
				continue;
			}
			$client_details = ProviderHelper::getClientDetails('player_id', $key['playerId']);
			if($client_details == null || $client_details == 'false'){
				$items_array[] = [
					 "info" => $key['info'], // Info from RSG, MW Should Return it back!
					 "errorCode" => 4, //The playerId was not found
					 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
        	    ];  
				continue;
			}

			$is_exist_gameid = $this->getGameId($key["gameId"]);
			if($is_exist_gameid == false){
				$items_array[] = [
					 "info" => $key['info'], 
					 "errorCode" => 11, 
					 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
        	    ]; 
        	    continue;
			}
			$key["gameId"] = $is_exist_gameid; // Overwrite GameId

			$game_details = ProviderHelper::findGameDetails('game_code', $this->provider_db_id, $key["gameId"]);
			if($game_details == null){ // Game not found
				$items_array[] = [
					 "info" => isset($key['info']) ? $key['info'] : '', // Info from RSG, MW Should Return it back!
					 "errorCode" => 11, // transaction already refunded
					 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
        	    ];
				continue;
			}
			if($isset_allbets_amount == 0){ # Calculate all total bets
				foreach ($json_data['items'] as $key) {
					array_push($duplicate_txid_request, $key['txId']);
					array_push($all_bets_amount, $key['amendAmount']);
				}
				$isset_allbets_amount = 1;
			}

			
			

			if(isset($key['winTxId'])){
					// $checkLog = DigitainHelper::findGameExt($key['winTxId'], 2, 'transaction_id'); // Amend can amend Bet and Win Select All Possible?

				    $checkLog = GameTransactionMDB::findGameExt($key['winTxId'], 2,'transaction_id', $client_details);
					if($checkLog != 'false'){

						# If Not Included in the Operation Types
						if($this->getOperationType($key['winOperationType']) == 'false'){
							$items_array[] = [
								"info" => $key['info'], 
								"errorCode" => 18,  
								"metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
						   ];
						   $error_encounter= 1;
						   continue;
						}

						# If not in win operation type
						if($this->getWinOpType($key['winOperationType']) == false){
							$items_array[] = [
								"info" => $key['info'], 
								"errorCode" => 18,  
								"metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
						   ]; 
						   $error_encounter= 1;
						   continue;
						}

						$db_operation_type = 1;
						$debit_operation_type = 37;  
						if($key['operationType'] != 37 && $key['operationType'] != 38){
							$items_array[] = [
								 "info" => $key['info'], 
								 "errorCode" => 19,  // Invalid Data
								 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
			        	    ]; 
			        	    // $global_error = $global_error == 1 ? 19 : $global_error;
							$error_encounter= 1;
							continue;
						}

						# If the Transaction Is Bet Denied IT
						if($checkLog->game_transaction_type == 1){ 
								$items_array[] = [
									"info" => $key['info'], 
									"errorCode" => 18,  
									"metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
							   ]; 
							   $error_encounter= 1;
							   continue;
						}

						# Amount not match the record
						if($checkLog->amount < $key['amendAmount']){
							$items_array[] = [
								 "info" => $key['info'], 
								 "errorCode" => 18, 
								 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
			        	    ]; 
			        	    // $global_error = $global_error == 1 ? 18 : $global_error;
							$error_encounter= 1;
							continue;
						}
						
						# The Win Type Dont Match the record
						if(isset($checkLog->general_details)){
							$db_general_details = json_decode($checkLog->general_details);
							if(isset($db_general_details->provider->operationType)){
								if($key['winOperationType'] != $db_general_details->provider->operationType){
									$items_array[] = [
										"info" => $key['info'], 
										"errorCode" => 19, 
										"metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
									]; 
									// $global_error = $global_error == 1 ? 19 : $global_error;
									$error_encounter= 1;
									continue;
								}
							}
						}
						# RoundId Dont Match The Record
						if($checkLog->round_id != $key['roundId']){
							$items_array[] = [
								 "info" => $key['info'], 
								 "errorCode" => 7, 
								 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
			        	    ]; 
			        	    // $global_error = $global_error == 1 ? 7 : $global_error;
							$error_encounter= 1;
							continue;
						}
					}else{
						// Not found bet or win go away!
						$items_array[] = [
							 "info" => $key['info'], // Info from RSG, MW Should Return it back!
							 "errorCode" => 7, // Win Transaction not found
							 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
		        	    ]; 
		        	    // $global_error = $global_error == 1 ? 7 : $global_error;
						$error_encounter= 1;
						continue;
					}
			}

			if($key['currencyId'] != $client_details->default_currency){
				$items_array[] = [
					 "info" => $key['info'], // Info from RSG, MW Should Return it back!
					 "errorCode" => 16, // Currency code dont match!
					 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
        	    ];   	
				continue;
			} 
			// $is_refunded = DigitainHelper::findGameExt($key['txId'], 3, 'transaction_id');
		    $is_refunded = GameTransactionMDB::findGameExt($key['txId'], 3,'transaction_id', $client_details);
			if($is_refunded != 'false'){
				$items_array[] = [
					 "info" => $key['info'], // Info from RSG, MW Should Return it back!
					 "errorCode" => 8, // transaction already refunded
					 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
        	    ]; 
				continue;
			}
			// $client_response = DigitainHelper::playerDetailsCall($client_details);
			// if($client_response == 'false'){
			// 	$items_array[] = [
			// 		 "info" => $key['info'],
			// 		 "errorCode" => 999,
			// 		 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
   //      	    ]; 
			// 	continue;
			// }

			$general_details['client']['beforebalance'] = $this->formatBalance($client_details->balance);
			// $gametransaction_details = DigitainHelper::findGameTransaction($checkLog->game_trans_id,'game_transaction');
			$gametransaction_details = GameTransactionMDB::findGameTransactionDetails($checkLog->game_trans_id, 'game_transaction', false, $client_details);

			// 37 Amend correction withdrawing money
			// 38 Amend  correction depositing money.
			if(isset($key['operationType'])){
				if($key['operationType'] == 37){ 
					$transaction_type = 'debit'; 
				}elseif($key['operationType'] == 38){
					$transaction_type = 'credit'; 
				}else{
					$items_array[] = [
						 "info" => $key['info'], 
						 "errorCode" => 19, 
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
	        	    ]; 
					continue;
				}
			}

	 		$amount = $key['amendAmount'];

 		    $token_id = $client_details->token_id;
	 	    if(isset($key['roundId'])){
	 	    	$round_id = $key['roundId'];
	 	    }else{
	 	    	$round_id = 'RSGNOROUND';
	 	    }
	 	    if(isset($key['txId'])){
	 	    	$provider_trans_id = $key['txId'];
	 	    }else{
	 	    	$provider_trans_id = 'RSGNOPROVIDERTXID';
	 	    }
	 	    $round_id = $key['roundId'];

			if($key['winOperationType'] == 1){ // This is for bet
					$win = $gametransaction_details->win; //win
					$entry_id = $gametransaction_details->entry_id; // BET
					$pay_amount = $gametransaction_details->pay_amount;
					$income = $gametransaction_details->income;
					if($key['operationType'] == 37){ // CREADIT/ADD
						$the_transaction_bet = $gametransaction_details->bet_amount + $amount;
					}else{ // DEBIT/SUBTRACT
						$the_transaction_bet = $gametransaction_details->bet_amount - $amount;
					}
			}else{
				if($key['operationType'] == 37){ // CREADIT/ADD
					$pay_amount = $gametransaction_details->pay_amount + $amount;
					$income = $gametransaction_details->bet_amount - $pay_amount;
				}else{ // DEBIT/SUBTRACT
					$pay_amount = $gametransaction_details->pay_amount - $amount;
					$income = $gametransaction_details->bet_amount - $pay_amount;
				}
				if($pay_amount > $gametransaction_details->bet_amount){
					$win = 4; //refund
					$entry_id = 1; //lost
				}else{
					$win = 4; //refund
					$entry_id = 2; //win
				}
			}

 			// $game_transextension = ProviderHelper::createGameTransExtV2($gametransaction_details->game_trans_id,$provider_trans_id, $round_id, abs($amount), 3,$request->all());

 			$gameTransactionEXTData = array(
				"game_trans_id" => $gametransaction_details->game_trans_id,
				"provider_trans_id" => $provider_trans_id,
				"round_id" => $round_id,
				"amount" => abs($amount),
				"game_transaction_type"=> 3,
				"provider_request" =>json_encode($request->all()),
			);
			$game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

	 		try {
			 $client_response = ClientRequestHelper::fundTransfer($client_details,abs($amount),$game_details->game_code,$game_details->game_name,$game_transextension,$gametransaction_details->game_trans_id,$transaction_type,true);
			//  ProviderHelper::saveLogWithExeption('RSG amend CRID = '.$gametransaction_details->game_trans_id, $this->provider_db_id, file_get_contents("php://input"), $client_response);
			} catch (\Exception $e) {
			$items_array[] = array(
				 "info" => $key['info'], 
				 "errorCode" => 999, 
				 "metadata" => isset($key['metadata']) ? $key['metadata'] : ''
			);
			$updateTransactionEXt = array(
				"mw_response" => json_encode($response),
				'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
				'client_response' => json_encode($e->getMessage().' '.$e->getLine().' '.$e->getFile()),
			);
			GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
			ProviderHelper::saveLogWithExeption('RSG win - FATAL ERROR', $this->provider_db_id, json_encode($items_array), DigitainHelper::datesent());
				continue;
			}

			if(isset($client_response->fundtransferresponse->status->code) 
			             && $client_response->fundtransferresponse->status->code == "200"){
				ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
				$general_details['provider']['operationType'] = $key['operationType'];
				$general_details['provider']['currencyId'] = $key['currencyId'];
				$general_details['provider']['amount'] = abs($amount);
				$general_details['provider']['txCreationDate'] = $json_data['timestamp'];
				$general_details['provider']['txId'] = $key['txId'];
				$general_details['client']['afterbalance'] = $this->formatBalance($client_response->fundtransferresponse->balance);
				$general_details['aggregator']['externalTxId'] = $game_transextension;
				$general_details['aggregator']['transaction_status'] = 'SUCCESS';

				if($key['winOperationType'] == 1){
					// $updateTheBet = DigitainHelper::updateBetToWin($gametransaction_details->game_trans_id, $pay_amount, $income, $win, $entry_id, 2, $the_transaction_bet);
					$updateGameTransaction = [
						  'pay_amount' => $pay_amount, 
						  'bet_amount' => $the_transaction_bet, 
					      'income' => $income, 
					      'win' => $win, 
					      'entry_id' => $entry_id,
					];
					GameTransactionMDB::updateGametransaction($updateGameTransaction, $gametransaction_details->game_trans_id, $client_details); 
				
				}else{
					// $updateTheBet = DigitainHelper::updateBetToWin($gametransaction_details->game_trans_id, $pay_amount, $income, $win, $entry_id);
					$updateGameTransaction = [
						  'pay_amount' => $pay_amount, 
					      'income' => $income, 
					      'win' => $win, 
					      'entry_id' => $entry_id,
					];
					GameTransactionMDB::updateGametransaction($updateGameTransaction, $gametransaction_details->game_trans_id, $client_details); 
				}

				$items_array[] = [
        	    	 "externalTxId" => $game_transextension, // MW Game Transaction Id
					 "balance" => $this->formatBalance($client_response->fundtransferresponse->balance),
					 "info" => $key['info'], // Info from RSG, MW Should Return it back!
					 "errorCode" => 1,
					 "metadata" => isset($key['metadata']) ? $key['metadata'] : ''// Optional but must be here!
        	    ];
				// ProviderHelper::updatecreateGameTransExt($game_transextension,  $json_data, $items_array, $client_response->requestoclient, $client_response, 'SUCCESS', $general_details);

				$updateTransactionEXt = array(
					"mw_response" => json_encode($items_array),
					'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
					'client_response' => json_encode($client_response),
					'transaction_detail' => 'SUCCESS',
					'general_details' => json_encode($general_details)
				);
				GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);

			}elseif(isset($client_response->fundtransferresponse->status->code) 
			            && $client_response->fundtransferresponse->status->code == "402"){

				$general_details['provider']['operationType'] = $key['operationType'];
				$general_details['provider']['currencyId'] = $key['currencyId'];
				$general_details['provider']['amount'] = abs($amount);
				$general_details['provider']['txCreationDate'] = $json_data['timestamp'];
				$general_details['provider']['txId'] = $key['txId'];

				$general_details['client']['afterbalance'] = $this->formatBalance($client_response->fundtransferresponse->balance);
				$general_details['aggregator']['externalTxId'] = $game_transextension;
				$general_details['aggregator']['transaction_status'] = 'SUCCESS';

				$items_array[] = array(
						 "info" => $key['info'], 
						 "errorCode" => 6, 
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : ''
		   		);
		   		// ProviderHelper::updatecreateGameTransExt($game_transextension,  $json_data, $items_array, $client_response->requestoclient, $client_response, 'FAILED', $general_details);

		   		$updateTransactionEXt = array(
					"mw_response" => json_encode($items_array),
					'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
					'client_response' => json_encode($client_response),
					'transaction_detail' => 'FAILED',
					'general_details' => json_encode($general_details)
				);
				GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
			} 
		} // END FOREACH
		$response = array(
					 "timestamp" => date('YmdHisms'),
				     "signature" => $this->createSignature(date('YmdHisms')),
					 "errorCode" => 1,
					 "items" => $items_array,
		);	
		ProviderHelper::saveLogWithExeption('RSG amend - SUCCESS', $this->provider_db_id, file_get_contents("php://input"), $response);
		return $response;
	}


	public function amendallOrNone($json_data){

		$items_array = array(); // ITEMS INFO
		
		$error_encounter = 0;
		$duplicate_txid_request = array();
		$all_bets_amount = array();
		$isset_allbets_amount = 0;
		$global_error = 1;
		$i=0;
		foreach ($json_data['items'] as $key) { // FOREACH CHECK
		$i++;
				# Missing item param
				if (!isset($key['playerId']) || !isset($key['gameId']) || !isset($key['roundId']) || !isset($key['txId']) || !isset($key['winTxId']) || !isset($key['winOperationType']) || !isset($key['currencyId']) || !isset($key['info']) || !isset($key['amendAmount'])) {
					$items_array[] = [
						"info" => isset($key['info']) ? $key['info'] : '', // Info from RSG, MW Should Return it back!
						"errorCode" => 17, //The playerId was not found
						"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
					];
					$global_error = $global_error == 1 ? 17 : $global_error;
					$error_encounter = 1;
					continue;
				}
				if ($isset_allbets_amount == 0) { # Calculate all total bets
					foreach ($json_data['items'] as $key) {
						array_push($duplicate_txid_request, $key['txId']);
						array_push($all_bets_amount, $key['amendAmount']);
					}
					$isset_allbets_amount = 1;
				}
				if ($this->array_has_dupes($duplicate_txid_request)) {
					$items_array[] = [
						"info" => $key['info'],
						"errorCode" => 8,
						"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
					];
					$global_error = $global_error == 1 ? 8 : $global_error;
					$error_encounter = 1;
					continue;
				}
				$client_details = ProviderHelper::getClientDetails('player_id', $key['playerId']);
				if ($client_details == nul && $client_details == 'false') {
					$items_array[] = [
						"info" => $key['info'], // Info from RSG, MW Should Return it back!
						"errorCode" => 4, //The playerId was not found
						"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
					];
					$global_error = $global_error == 1 ? 7 : $global_error;
					$error_encounter = 1;
					continue;
				}
				$json_data['items'][$i - 1]['client_details'] = $client_details;

				// $client_response = DigitainHelper::playerDetailsCall($client_details);
				// if ($client_response == 'false') {
				// 	$items_array[] = [
				// 		"info" => $key['info'], // Info from RSG, MW Should Return it back!
				// 		"errorCode" => 4, //The playerId was not found
				// 		"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
				// 	];
				// 	$global_error = $global_error == 1 ? 7 : $global_error;
				// 	$error_encounter = 1;
				// 	continue;
				// }
				$json_data['items'][$i - 1]['client_response'] = $client_details;

				$is_exist_gameid = $this->getGameId($key["gameId"]);
				if($is_exist_gameid == false){
					$items_array[] = [
						 "info" => $key['info'], 
						 "errorCode" => 11, 
						 "metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
	        	    ]; 
	        	    continue;
				}
				$key["gameId"] = $is_exist_gameid; // Overwrite GameId

				$game_details = ProviderHelper::findGameDetails('game_code', $this->provider_db_id, $key["gameId"]);
				if ($game_details == null) { // Game not found
					$items_array[] = [
						"info" => isset($key['info']) ? $key['info'] : '', // Info from RSG, MW Should Return it back!
						"errorCode" => 11, // transaction already refunded
						"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
					];
					$global_error = $global_error == 1 ? 11 : $global_error;
					$error_encounter = 1;
					continue;
				}
				$json_data['items'][$i - 1]['game_details'] = $game_details;


				if (isset($key['winTxId'])) {
					// $checkLog = DigitainHelper::findGameExt($key['winTxId'], 123, 'transaction_id'); // Amend can amend Bet and Win Select All Possible?

					$checkLog = GameTransactionMDB::findGameExt($key['winTxId'], 2,'transaction_id', $client_details);
					if ($checkLog != 'false') {

						# If Not Included in the Operation Types
						if($this->getOperationType($key['winOperationType']) == 'false'){
							$items_array[] = [
								"info" => $key['info'], 
								"errorCode" => 18,  
								"metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
						   ];
						   $global_error = $global_error == 1 ? 18 : $global_error;
						   $error_encounter = 1;
						   continue;
						}

						# If not in win operation type
						if($this->getWinOpType($key['winOperationType']) == false){
							$items_array[] = [
								"info" => $key['info'], 
								"errorCode" => 18,  
								"metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
						   ]; 
						   $global_error = $global_error == 1 ? 18 : $global_error;
						   $error_encounter = 1;
						   continue;
						}

						$db_operation_type = 1;
						$debit_operation_type = 37; 
						if ($key['operationType'] != 37 && $key['operationType'] != 38) {
							$items_array[] = [
								"info" => $key['info'],
								"errorCode" => 19,
								"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
							];
							$global_error = $global_error == 1 ? 19 : $global_error;
							$error_encounter = 1;
							continue;
						}
						if($checkLog->game_transaction_type == 1){
							$items_array[] = [
								"info" => $key['info'], 
								"errorCode" => 18,  // Invalid Data
								"metadata" => isset($key['metadata']) ? $key['metadata'] : '' 
							]; 
							$global_error = $global_error == 1 ? 18 : $global_error;
							$error_encounter= 1;
						}
						if ($checkLog->amount < $key['amendAmount']) {
							$items_array[] = [
								"info" => $key['info'],
								"errorCode" => 18,
								"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
							];
							$global_error = $global_error == 1 ? 18 : $global_error;
							$error_encounter = 1;
							continue;
						}
						if (isset($checkLog->general_details)) {
							$db_general_details = json_decode($checkLog->general_details);
							if (isset($db_general_details->provider->operationType)) {
								if ($key['winOperationType'] != $db_general_details->provider->operationType) {
									$items_array[] = [
										"info" => $key['info'],
										"errorCode" => 19,
										"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
									];
									$global_error = $global_error == 1 ? 19 : $global_error;
									$error_encounter = 1;
									continue;
								}
							}
						}
						if ($checkLog->round_id != $key['roundId']) {
							$items_array[] = [
								"info" => $key['info'],
								"errorCode" => 7,
								"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
							];
							$global_error = $global_error == 1 ? 7 : $global_error;
							$error_encounter = 1;
							continue;
						}

						$json_data['items'][$i - 1]['checkLog'] = $checkLog;
					} else {
						// Not found bet or win go away!
						$items_array[] = [
							"info" => $key['info'], // Info from RSG, MW Should Return it back!
							"errorCode" => 7, // Win Transaction not found
							"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
						];
						$global_error = $global_error == 1 ? 7 : $global_error;
						$error_encounter = 1;
						continue;
					}
				}

				// $is_refunded = DigitainHelper::findGameExt($key['txId'], 3, 'transaction_id');
				$is_refunded = GameTransactionMDB::findGameExt($key['txId'], 3,'transaction_id', $client_details);
				if ($is_refunded != 'false') {
					$items_array[] = [
						"info" => $key['info'], // Info from RSG, MW Should Return it back!
						"errorCode" => 8, // transaction already refunded
						"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
					];
					$global_error = $global_error == 1 ? 8 : $global_error;
					$error_encounter = 1;
					continue;
				}
				$json_data['items'][$i - 1]['is_refunded'] = $is_refunded;

				if ($key['currencyId'] != $client_details->default_currency) {
					$items_array[] = [
						"info" => $key['info'], // Info from RSG, MW Should Return it back!
						"errorCode" => 16, // Currency code dont match!
						"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
					];
					$global_error = $global_error == 1 ? 16 : $global_error;
					$error_encounter = 1;
					continue;
				}
		} // END FOREACH CHECK


		if ($error_encounter != 0) { 
			$response = array(
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"errorCode" => $global_error,
				"items" => $items_array,
			);
			return $response;
		}else{
			$items_array = array(); // ITEMS INFO
			$duplicate_txid_request = array();
			$all_bets_amount = array();
			$isset_allbets_amount = 0;
			// ALL GOOD PROCESS IT

			foreach ($json_data['items'] as $key) {
				$general_details = ["aggregator" => [], "provider" => [], "client" => []];
				if (!isset($key['playerId']) || !isset($key['gameId']) || !isset($key['roundId']) || !isset($key['txId']) || !isset($key['winTxId']) || !isset($key['winOperationType']) || !isset($key['currencyId']) || !isset($key['info']) || !isset($key['amendAmount'])) {
					$items_array[] = [
						"info" => isset($key['info']) ? $key['info'] : '', // Info from RSG, MW Should Return it back!
						"errorCode" => 17, //The playerId was not found
						"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
					];
					continue;
				}

				$client_details = $key['client_details'];
				$game_details = $key["game_details"];
				
				if ($isset_allbets_amount == 0) { # Calculate all total bets
					foreach ($json_data['items'] as $key) {
						array_push($duplicate_txid_request, $key['txId']);
						array_push($all_bets_amount, $key['amendAmount']);
					}
					$isset_allbets_amount = 1;
				}

				if (isset($key['winTxId'])) {
					$checkLog = $key['checkLog']; // iswin?
				}
			
				$is_refunded = $key['is_refunded'];
				$client_response = $key['client_response'];
			
				$general_details['client']['beforebalance'] = $this->formatBalance($client_details->balance);
				// $gametransaction_details = DigitainHelper::findGameTransaction($checkLog->game_trans_id, 'game_transaction');
				$gametransaction_details = GameTransactionMDB::findGameTransactionDetails($checkLog->game_trans_id, 'game_transaction', false, $client_details);
				// 37 Amend correction withdrawing money
				// 38 Amend  correction depositing money.
				if (isset($key['operationType'])) {
					if ($key['operationType'] == 37) {
						$transaction_type = 'debit';
					} elseif ($key['operationType'] == 38) {
						$transaction_type = 'credit';
					} else {
						$items_array[] = [
							"info" => $key['info'],
							"errorCode" => 19,
							"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
						];
						continue;
					}
				}

				$amount = $key['amendAmount'];

				$token_id = $client_details->token_id;
				if (isset($key['roundId'])) {
					$round_id = $key['roundId'];
				} else {
					$round_id = 'RSGNOROUND';
				}
				if (isset($key['txId'])) {
					$provider_trans_id = $key['txId'];
				} else {
					$provider_trans_id = 'RSGNOPROVIDERTXID';
				}
				$round_id = $key['roundId'];

				if ($key['winOperationType'] == 1) { // This is for bet
					$win = $gametransaction_details->win; //win
					$entry_id = $gametransaction_details->entry_id; // BET
					$pay_amount = $gametransaction_details->pay_amount;
					$income = $gametransaction_details->income;
					if ($key['operationType'] == 37) { // CREADIT/ADD
						$the_transaction_bet = $gametransaction_details->bet_amount + $amount;
					} else { // DEBIT/SUBTRACT
						$the_transaction_bet = $gametransaction_details->bet_amount - $amount;
					}
				} else {
					if ($key['operationType'] == 37) { // CREADIT/ADD
						$pay_amount = $gametransaction_details->pay_amount + $amount;
						$income = $gametransaction_details->bet_amount - $pay_amount;
					} else { // DEBIT/SUBTRACT
						$pay_amount = $gametransaction_details->pay_amount - $amount;
						$income = $gametransaction_details->bet_amount - $pay_amount;
					}
					if ($pay_amount > $gametransaction_details->bet_amount) {
						$win = 4; //refund
						$entry_id = 1; //lost
					} else {
						$win = 4; //refund
						$entry_id = 2; //win
					}
				}

				// $game_transextension = ProviderHelper::createGameTransExtV2($gametransaction_details->game_trans_id, $provider_trans_id, $round_id, abs($amount), 3,$json_data);

				$gameTransactionEXTData = array(
					"game_trans_id" => $gametransaction_details->game_trans_id,
					"provider_trans_id" => $provider_trans_id,
					"round_id" => $round_id,
					"amount" => abs($amount),
					"game_transaction_type"=> 3,
					"provider_request" =>json_encode($json_data),
				);
				$game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

				try {
					$client_response = ClientRequestHelper::fundTransfer($client_details, abs($amount), $game_details->game_code, $game_details->game_name, $game_transextension, $gametransaction_details->game_trans_id, $transaction_type, true);
					// ProviderHelper::saveLogWithExeption('RSG amend CRID = ' . $gametransaction_details->game_trans_id, $this->provider_db_id, file_get_contents("php://input"), $client_response);
				} catch (\Exception $e) {
					$items_array[] = array(
						"info" => $key['info'],
						"errorCode" => 999,
						"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
					);
					$updateTransactionEXt = array(
						"mw_response" => json_encode($response),
						'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
						'client_response' => json_encode($e->getMessage().' '.$e->getLine().' '.$e->getFile()),
					);
					GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
					ProviderHelper::saveLogWithExeption('RSG win - FATAL ERROR', $this->provider_db_id, json_encode($items_array), DigitainHelper::datesent());
					continue;
				}

				if (
					isset($client_response->fundtransferresponse->status->code)
					&& $client_response->fundtransferresponse->status->code == "200"
				) {
					ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
					$general_details['provider']['operationType'] = $key['operationType'];
					$general_details['provider']['currencyId'] = $key['currencyId'];
					$general_details['provider']['amount'] = abs($amount);
					$general_details['provider']['txCreationDate'] = $json_data['timestamp'];
					$general_details['provider']['txId'] = $key['txId'];
					$general_details['client']['afterbalance'] = $this->formatBalance($client_response->fundtransferresponse->balance);
					$general_details['aggregator']['externalTxId'] = $game_transextension;
					$general_details['aggregator']['transaction_status'] = 'SUCCESS';

					if ($key['winOperationType'] == 1) {
						$updateGameTransaction = [
							  'pay_amount' => $pay_amount, 
							  'bet_amount' => $the_transaction_bet, 
						      'income' => $income, 
						      'win' => $win, 
						      'entry_id' => $entry_id,
						];
						GameTransactionMDB::updateGametransaction($updateGameTransaction, $gametransaction_details->game_trans_id, $client_details); 
					} else {
						$updateGameTransaction = [
							  'pay_amount' => $pay_amount, 
						      'income' => $income, 
						      'win' => $win, 
						      'entry_id' => $entry_id,
						];
						GameTransactionMDB::updateGametransaction($updateGameTransaction, $gametransaction_details->game_trans_id, $client_details); 
					}

					$items_array[] = [
						"externalTxId" => $game_transextension, // MW Game Transaction Id
						"balance" => $this->formatBalance($client_response->fundtransferresponse->balance),
						"info" => $key['info'], // Info from RSG, MW Should Return it back!
						"errorCode" => 1,
						"metadata" => isset($key['metadata']) ? $key['metadata'] : '' // Optional but must be here!
					];

					$updateTransactionEXt = array(
						"mw_response" => json_encode($items_array),
						'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
						'client_response' => json_encode($client_response),
						'transaction_detail' => 'SUCCESS',
						'general_details' => json_encode($general_details)
					);
					GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
				} elseif (
					isset($client_response->fundtransferresponse->status->code)
					&& $client_response->fundtransferresponse->status->code == "402"
				) {

					$general_details['provider']['operationType'] = $key['operationType'];
					$general_details['provider']['currencyId'] = $key['currencyId'];
					$general_details['provider']['amount'] = abs($amount);
					$general_details['provider']['txCreationDate'] = $json_data['timestamp'];
					$general_details['provider']['txId'] = $key['txId'];

					$general_details['client']['afterbalance'] = $this->formatBalance($client_response->fundtransferresponse->balance);
					$general_details['aggregator']['externalTxId'] = $game_transextension;
					$general_details['aggregator']['transaction_status'] = 'SUCCESS';

					$items_array[] = array(
						"info" => $key['info'],
						"errorCode" => 6,
						"metadata" => isset($key['metadata']) ? $key['metadata'] : ''
					);
					$updateTransactionEXt = array(
						"mw_response" => json_encode($items_array),
						'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
						'client_response' => json_encode($client_response),
						'transaction_detail' => 'FAILED',
						'general_details' => json_encode($general_details)
					);
					GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
				}
			} // END FOREACH
			$response = array(
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"errorCode" => 1,
				"items" => $items_array,
			);
			ProviderHelper::saveLogWithExeption('RSG amend - SUCCESS', $this->provider_db_id, file_get_contents("php://input"), $response);
			return $response;
		}
	}


	public function PromoWin(Request $request){
		ProviderHelper::saveLogWithExeption('RSG PromoWin - EH', $this->provider_db_id, file_get_contents("php://input"), 'ENDPOINT HIT');
		$json_data = json_decode(file_get_contents("php://input"), true);
		$general_details = ["aggregator" => [], "provider" => [], "client" => []];
		// $response = array(
		// 		 "timestamp" => date('YmdHisms'),
		// 	     "signature" => $this->createSignature(date('YmdHisms')),
		// 		 "errorCode" => 999,
		// 		 "message" => 'TIGER GAMES DONT SUPPORT PROMOWIN AND BUNOS YET!',
		// );	
		// return $response;
		if($json_data == null){
			return $this->noBody();
		}
		if (!$this->authMethod($json_data['operatorId'], $json_data['timestamp'], $json_data['signature'])){
			return $this->authError();
		}
		if($json_data['operatorId'] != $this->operator_id){
			return $this->wrongOperatorID();
		}
		# Missing Parameters
		if(!isset($json_data['providerId']) || !isset($json_data['operatorId']) || !isset($json_data['signature']) || !isset($json_data['timestamp']) || !isset($json_data['playerId']) || !isset($json_data['promoWinAmount']) || !isset($json_data['currencyId']) || !isset($json_data['txId'])){
			$response = array(
					 "timestamp" => date('YmdHisms'),
				     "signature" => $this->createSignature(date('YmdHisms')),
					 "errorCode" => 17,
					 "items" => [],
   			);	
			return $response;
		}
		$client_details = ProviderHelper::getClientDetails('player_id', $json_data['playerId']);
		if($client_details == null || $client_details == 'false'){
			$response = [
				//  "info" => $json_data['info'], // Info from RSG, MW Should Return it back!
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				 "errorCode" => 4, //The playerId was not found
				 "metadata" => isset($json_data['metadata']) ? $json_data['metadata'] : '' // Optional but must be here!
    	    ];  
			return $response;
		}

		// $idenputik = DigitainHelper::findGameExt($json_data['txId'], 1, 'transaction_id');
		$idenputik = GameTransactionMDB::findGameExt($json_data['txId'], 1,'transaction_id', $client_details);
		if($idenputik != 'false'){
			if(isset($idenputik->general_details) && $idenputik->general_details != null){
				$general_details_decode = json_decode($idenputik->general_details);
				$general_details_after_balance = isset($general_details_decode->client->afterbalance) ? $general_details_decode->client->afterbalance : 0;
			}
			$response = [
				 "timestamp" => date('YmdHisms'),
				 "signature" => $this->createSignature(date('YmdHisms')),
				 "errorCode" => 8, // transaction already exist
				 "balance" => $general_details_after_balance,
				 "metadata" => isset($json_data['metadata']) ? $json_data['metadata'] : '', // Optional but must be here!
				//  "msg" => 'Duplicate Call'
    	    ]; 
			return $response;
		}

		if($json_data['currencyId'] != $client_details->default_currency){
			$response = [
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				//  "info" => $json_data['info'], // Info from RSG, MW Should Return it back!
				 "errorCode" => 16, // Currency code dont match!
				 "metadata" => isset($json_data['metadata']) ? $json_data['metadata'] : '' // Optional but must be here!
    	    ];   	
			return $response;
		} 
		// $is_refunded = DigitainHelper::findGameExt($json_data['txId'], 2, 'transaction_id');
		$is_refunded = GameTransactionMDB::findGameExt($json_data['txId'], 2,'transaction_id', $client_details);
		if($is_refunded != 'false'){
			$response = [
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				//  "info" => $json_data['info'], // Info from RSG, MW Should Return it back!
				 "errorCode" => 8, // transaction already refunded
				 "metadata" => isset($json_data['metadata']) ? $json_data['metadata'] : '' // Optional but must be here!
    	    ]; 
			return $response;
		}
		// $client_response = DigitainHelper::playerDetailsCall($client_details);
		// if($client_response == 'false'){
		// 	$response = [
		// 		"timestamp" => date('YmdHisms'),
		// 		"signature" => $this->createSignature(date('YmdHisms')),
		// 		//  "info" => $json_data['info'], // Info from RSG, MW Should Return it back!
		// 		"errorCode" => 999, // transaction already refunded
		// 		"metadata" => isset($json_data['metadata']) ? $json_data['metadata'] : '' // Optional but must be here!
  //   	    ]; 
		// 	return $response;
		// }
		$general_details['client']['beforebalance'] = $this->formatBalance($client_details->balance);

		$game_details = Helper::getInfoPlayerGameRound($client_details->player_token);

		$token_id = $client_details->token_id;
		$bet_amount = 0;
		$promo_amount = $json_data['promoWinAmount'];
		$income = 0;
		$provider_trans_id = $json_data['txId'];
		$round_id = $json_data['txId'];
		$method = 0;
		$win_or_lost = 5;
		$payout_reason = 'PROMO WIN';

		// $game_trans = ProviderHelper::createGameTransaction($token_id, $game_details->game_id, $bet_amount, 0, $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id, $round_id);
		// $game_transextension = ProviderHelper::createGameTransExtV2($game_trans,$provider_trans_id , $provider_trans_id , $bet_amount, 1,$request->all());

		$gameTransactionData = array(
			"provider_trans_id" => $provider_trans_id,
			"token_id" => $token_id,
			"game_id" => $game_details->game_id,
			"round_id" => $round_id,
			"bet_amount" => $bet_amount,
			"win" => $win_or_lost,
			"pay_amount" => 0,
			"income" =>  $income,
			"entry_id" =>$method,
		);
		$game_trans = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
		$gameTransactionEXTData = array(
			"game_trans_id" => $game_trans,
			"provider_trans_id" => $provider_trans_id,
			"round_id" => $round_id,
			"amount" => $bet_amount,
			"game_transaction_type"=> 1,
			"provider_request" =>json_encode($request->all()),
		);
		$game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

		try {
			$client_response = ClientRequestHelper::fundTransfer($client_details,abs($bet_amount),$game_details->game_code,$game_details->game_name,$game_transextension,$game_trans,'debit');
			// ProviderHelper::saveLogWithExeption('RSG PromoWin CRID = '.$game_trans, $this->provider_db_id, file_get_contents("php://input"), $client_response);
		} catch (\Exception $e) {
			$response = [
				 "timestamp" => date('YmdHisms'),
				 "signature" => $this->createSignature(date('YmdHisms')),
				//  "info" => $json_data['info'], // Info from RSG, MW Should Return it back!
				 "errorCode" => 999, // transaction already refunded
				 "info"=> $json_data['info'],
				 "metadata" => isset($json_data['metadata']) ? $json_data['metadata'] : '' // Optional but must be here!
		    ]; 
			$updateGameTransaction = ["win" => 2];
			GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);  
			$updateTransactionEXt = array(
				"mw_response" => json_encode($response),
				'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
				'client_response' => json_encode($e->getMessage().' '.$e->getLine().' '.$e->getFile()),
			);
			GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
			ProviderHelper::saveLogWithExeption('RSG PromoWin - FATAL ERROR', $this->provider_db_id, json_encode($response), DigitainHelper::datesent());
			return $response;
		}

		if(isset($client_response->fundtransferresponse->status->code) 
             && $client_response->fundtransferresponse->status->code == "200"){

			// $game_transextension2 = ProviderHelper::createGameTransExtV2($game_trans,$provider_trans_id, $provider_trans_id, abs($promo_amount), 2);

			$gameTransactionEXTData = array(
				"game_trans_id" => $game_trans,
				"provider_trans_id" => $provider_trans_id,
				"round_id" => $round_id,
				"amount" => abs($promo_amount),
				"game_transaction_type"=> 2,
				"provider_request" =>json_encode($request->all()),
			);
			$game_transextension2 = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

			$client_response2 = ClientRequestHelper::fundTransfer($client_details,abs($promo_amount),$game_details->game_code,$game_details->game_name,$game_transextension2,$game_trans,'credit');

			if(isset($client_response2->fundtransferresponse->status->code) 
			&& $client_response2->fundtransferresponse->status->code == "402"){
				// $general_details['provider']['operationType'] = $this->getOperationcampaignType($json_data['campaignType']);
				$general_details['provider']['currencyId'] = $json_data['currencyId'];
				$general_details['provider']['amount'] = abs($promo_amount);
				$general_details['provider']['txCreationDate'] = $json_data['timestamp'];
				$general_details['provider']['txId'] = $json_data['txId'];

				$general_details['client']['afterbalance'] = $this->formatBalance($client_details->balance+abs($promo_amount));
				$general_details['aggregator']['externalTxId'] = $game_transextension2;
				$general_details['aggregator']['transaction_status'] = 'SUCCESS';

				$response = [
					"timestamp"=> date('YmdHisms'),
					"signature"=> $this->createSignature(date('YmdHisms')),
					// "operationType"=> $this->getOperationcampaignType($json_data['campaignType']), // win tournament = 35, bunos win = 5, 
					"txCreationDate"=> $json_data['timestamp'],
					"externalTxId"=> $game_transextension2,
					"currencyId"=> $client_details->default_currency,
					"balance"=> $this->formatBalance($client_details->balance+abs($promo_amount)),
					"bonusBalance"=> 0, // Tiger games dont have bunos wallet yet!
					// "info"=> $json_data['info'],
					"errorCode"=> 1,
					"metadata"=>  isset($json_data['metadata']) ? $json_data['metadata'] : ''
				];
				// ProviderHelper::updatecreateGameTransExt($game_transextension,  $json_data, $response, $client_response->requestoclient, $client_response, 'SUCCESS', $general_details);
				// ProviderHelper::updatecreateGameTransExt($game_transextension2,  $json_data, $response, $client_response2->requestoclient, $client_response2, 'FAILED', $general_details);
				
				$updateTransactionEXt = array(
					"mw_response" => json_encode($items_array),
					'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
					'client_response' => json_encode($client_response),
					'transaction_detail' => 'SUCCESS',
					'general_details' => json_encode($general_details)
				);
				GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);

				$updateTransactionEXt2 = array(
					"mw_response" => json_encode($items_array),
					'mw_request' => isset($client_response2->requestoclient) ? json_encode($client_response2->requestoclient) : 'FAILED',
					'client_response' => json_encode($client_response2),
					'transaction_detail' => 'FAILED',
					'general_details' => json_encode($general_details)
				);
				GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt2,$game_transextension2,$client_details);
			}else{
				// $general_details['provider']['operationType'] = $this->getOperationcampaignType($json_data['campaignType']);
				$general_details['provider']['currencyId'] = $json_data['currencyId'];
				$general_details['provider']['amount'] = abs($promo_amount);
				$general_details['provider']['txCreationDate'] = $json_data['timestamp'];
				$general_details['provider']['txId'] = $json_data['txId'];

				$general_details['client']['afterbalance'] = $this->formatBalance($client_response2->fundtransferresponse->balance);
				$general_details['aggregator']['externalTxId'] = $game_transextension2;
				$general_details['aggregator']['transaction_status'] = 'SUCCESS';

				$response = [
					"timestamp"=> date('YmdHisms'),
					"signature"=> $this->createSignature(date('YmdHisms')),
					// "operationType"=> $this->getOperationcampaignType($json_data['campaignType']), // win tournament = 35, bunos win = 5, 
					"txCreationDate"=> $json_data['timestamp'],
					"externalTxId"=> $game_transextension2,
					"currencyId"=> $client_details->default_currency,
					"balance"=> $this->formatBalance($client_response2->fundtransferresponse->balance),
					"bonusBalance"=> 0, // Tiger games dont have bunos wallet yet!
					// "info"=> $json_data['info'],
					"errorCode"=> 1,
					"metadata"=>  isset($json_data['metadata']) ? $json_data['metadata'] : ''
				];

				// ProviderHelper::updatecreateGameTransExt($game_transextension,  $json_data, $response, $client_response->requestoclient, $client_response, 'SUCCESS', $general_details);
				// ProviderHelper::updatecreateGameTransExt($game_transextension2,  $json_data, $response, $client_response2->requestoclient, $client_response2, 'SUCCESS', $general_details);
				// $updateTheBet = DigitainHelper::updateBetToWin($game_trans, $promo_amount, '-'.$promo_amount, 1, 2);

				$updateGameTransaction = [
					  'pay_amount' => $promo_amount, 
				      'income' => '-'.$promo_amount, 
				      'win' => 1, 
				      'entry_id' => 2,
				];
				GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details); 

				$updateTransactionEXt = array(
					"mw_response" => json_encode($response),
					'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
					'client_response' => json_encode($client_response),
					'transaction_detail' => 'SUCCESS',
					'general_details' => json_encode($general_details)
				);
				GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);

				$updateTransactionEXt2 = array(
					"mw_response" => json_encode($response),
					'mw_request' => isset($client_response2->requestoclient) ? json_encode($client_response2->requestoclient) : 'FAILED',
					'client_response' => json_encode($client_response2),
					'transaction_detail' => 'SUCCESS',
					'general_details' => json_encode($general_details)
				);
				GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt2,$game_transextension2,$client_details);
			}

		}
		return $response;
	}

	/**	
	 *  Charge Dont Have Callback win, (Tips, DropBet)
	 * 
	 */
	public function makeCharge(Request $request){
		ProviderHelper::saveLogWithExeption('RSG makeCharge - EH', $this->provider_db_id, file_get_contents("php://input"), 'ENDPOINT HIT');
		$json_data = json_decode(file_get_contents("php://input"), true);
		$general_details = ["aggregator" => [], "provider" => [], "client" => []];
	
		if($json_data == null){
			return $this->noBody();
		}
		if (!$this->authMethod($json_data['operatorId'], $json_data['timestamp'], $json_data['signature'])){
			return $this->authError();
		}
		if($json_data['operatorId'] != $this->operator_id){
			return $this->wrongOperatorID();
		}
		# Missing Parameters
		if(!isset($json_data['providerId']) || !isset($json_data['operatorId']) || !isset($json_data['signature']) || !isset($json_data['timestamp']) || !isset($json_data['playerId'])  || !isset($json_data['currencyId']) || !isset($json_data['txId']) || !isset($json_data['chargeAmount'])){
			$response = array(
					 "timestamp" => date('YmdHisms'),
				     "signature" => $this->createSignature(date('YmdHisms')),
					 "errorCode" => 17,
					 "items" => [],
   			);	
			return $response;
		}
		$client_details = ProviderHelper::getClientDetails('token', $json_data['token']);
		if($client_details == null || $client_details == 'false'){
			$response = [
				 "timestamp" => date('YmdHisms'),
				 "signature" => $this->createSignature(date('YmdHisms')),
				//  "info" => $json_data['info'], // Info from RSG, MW Should Return it back!
				 "errorCode" => 2, //The playerId was not found
				 "metadata" => isset($json_data['metadata']) ? $json_data['metadata'] : '' // Optional but must be here!
    	    ];  
			return $response;
		}
		$token_check = DigitainHelper::tokenCheck($json_data["token"]);
		if($token_check != true){
			$response = [
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"errorCode" => 3 // SessionExpired!
			];
			ProviderHelper::saveLogWithExeption('RSG authenticate', $this->provider_db_id, file_get_contents("php://input"), $response);
			return $response;
		}
		if($client_details->player_id != $json_data['playerId']){
			$response = [
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				// "info" => $json_data['info'], // Info from RSG, MW Should Return it back!
				"errorCode" => 4, //The playerId was not found
				"metadata" => isset($json_data['metadata']) ? $json_data['metadata'] : '' // Optional but must be here!
		   ];  
		   return $response;
		}
		if($json_data['currencyId'] != $client_details->default_currency){
		$response = [
				 "timestamp" => date('YmdHisms'),
				 "signature" => $this->createSignature(date('YmdHisms')),
				 "errorCode" => 16, // Currency code dont match!
				 "metadata" => isset($json_data['metadata']) ? $json_data['metadata'] : '' // Optional but must be here!
    	    ];   	
			return $response;
		} 
		// $is_refunded = DigitainHelper::findGameExt($json_data['txId'], 1, 'transaction_id');
		$is_refunded = GameTransactionMDB::findGameExt($json_data['txId'], 1,'transaction_id', $client_details);
		if($is_refunded != 'false'){
			if(isset($is_refunded->general_details) && $is_refunded->general_details != null){
				$general_details_decode = json_decode($is_refunded->general_details);
				$general_details_after_balance = isset($general_details_decode->client->afterbalance) ? $general_details_decode->client->afterbalance : 0;
			}
			$response = [
				 "timestamp" => date('YmdHisms'),
				 "signature" => $this->createSignature(date('YmdHisms')),
				 "errorCode" => 8, // transaction already refunded
				 "balance" => $general_details_after_balance,
				 "metadata" => isset($json_data['metadata']) ? $json_data['metadata'] : '' // Optional but must be here!
    	    ]; 
			return $response;
		}
		$general_details['client']['beforebalance'] = $this->formatBalance($client_details->balance);

		$is_exist_gameid = $this->getGameId($json_data["gameId"]);
		if($is_exist_gameid == false){
			$response = [
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"errorCode" => 11, // Currency code dont match!
				"metadata" => isset($json_data['metadata']) ? $json_data['metadata'] : '' // Optional but must be here!
		   ];   	
		   return $response;
		}
		$json_data["gameId"] = $is_exist_gameid; // Overwrite GameId

		$game_details = ProviderHelper::findGameDetails('game_code', $this->provider_db_id, $json_data["gameId"]);
		if($game_details == null){ // Game not found
			$response = [
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"errorCode" => 11, // Currency code dont match!
				"metadata" => isset($json_data['metadata']) ? $json_data['metadata'] : '' // Optional but must be here!
		   ];   	
		   return $response;
		}

		$token_id = $client_details->token_id;
		$bet_amount = $json_data['chargeAmount'];
		$income = 0;
		$provider_trans_id = $json_data['txId'];
		$round_id = $json_data['txId'];
		$method = 2;
		$win_or_lost = 5;
		$payout_reason = 'CHARGE';

		// $game_trans = ProviderHelper::createGameTransaction($token_id, $game_details->game_id, $bet_amount, 0, $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id, $round_id);
		// $game_transextension = ProviderHelper::createGameTransExtV2($game_trans,$provider_trans_id , $provider_trans_id , $bet_amount, 1,$request->all());

		$gameTransactionData = array(
			"provider_trans_id" => $provider_trans_id,
			"token_id" => $token_id,
			"game_id" => $game_details->game_id,
			"round_id" => $round_id,
			"bet_amount" => $bet_amount,
			"win" => $win_or_lost,
			"pay_amount" => 0,
			"income" =>  $income,
			"entry_id" =>$method,
		);
		$game_trans = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
		$gameTransactionEXTData = array(
			"game_trans_id" => $game_trans,
			"provider_trans_id" => $provider_trans_id,
			"round_id" => $round_id,
			"amount" => $bet_amount,
			"game_transaction_type"=> 1,
			"provider_request" =>json_encode($request->all()),
		);
		$game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

		try {
			$client_response = ClientRequestHelper::fundTransfer($client_details,abs($bet_amount),$game_details->game_code,$game_details->game_name,$game_transextension,$game_trans,'debit');
		} catch (\Exception $e) {
			$response = [
				 "timestamp" => date('YmdHisms'),
				 "signature" => $this->createSignature(date('YmdHisms')), 
				//  "info" => $json_data['info'], // Info from RSG, MW Should Return it back!
				 "errorCode" => 999, // transaction already refunded
				 "info"=> $json_data['info'],
				 "metadata" => isset($json_data['metadata']) ? $json_data['metadata'] : '' // Optional but must be here!
		    ]; 
			$updateGameTransaction = ["win" => 2];
			GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details);  
			$updateTransactionEXt = array(
				"mw_response" => json_encode($response),
				'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
				'client_response' => json_encode($e->getMessage().' '.$e->getLine().' '.$e->getFile()),
			);
			GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
			ProviderHelper::saveLogWithExeption('RSG PromoWin - FATAL ERROR', $this->provider_db_id, json_encode($response), DigitainHelper::datesent());
			return $response;
		}

		if(isset($client_response->fundtransferresponse->status->code) 
             && $client_response->fundtransferresponse->status->code == "200"){

			// $game_transextension2 = ProviderHelper::createGameTransExtV2($game_trans,$provider_trans_id, $provider_trans_id, 0, 2,$request->all());

			$gameTransactionEXTData = array(
				"game_trans_id" => $game_trans,
				"provider_trans_id" => $provider_trans_id,
				"round_id" => $round_id,
				"amount" => 0,
				"game_transaction_type"=> 2,
				"provider_request" =>json_encode($request->all()),
			);
			$game_transextension2 = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

			$client_response2 = ClientRequestHelper::fundTransfer($client_details,0,$game_details->game_code,$game_details->game_name,$game_transextension2,$game_trans,'credit');

			$general_details['provider']['operationType'] = $this->getOperationcampaignType($json_data['operationType']);
			$general_details['provider']['currencyId'] = $json_data['currencyId'];
			$general_details['provider']['amount'] =  $json_data['chargeAmount'];
			$general_details['provider']['txCreationDate'] = $json_data['timestamp'];
			$general_details['provider']['txId'] = $json_data['txId'];

			$general_details['client']['afterbalance'] = $this->formatBalance($client_details->balance);
			$general_details['aggregator']['externalTxId'] = $game_transextension2;
			$general_details['aggregator']['transaction_status'] = 'SUCCESS';

			if(isset($client_response2->fundtransferresponse->status->code) 
				&& $client_response2->fundtransferresponse->status->code == "402"){
			    $response = [
					"timestamp"=> date('YmdHisms'),
					"signature"=> $this->createSignature(date('YmdHisms')),
					"externalTxId"=> $game_transextension2,
					"balance"=> $this->formatBalance($client_details->balance-$bet_amount),
					"errorCode"=> 1,
					"metadata"=>  isset($json_data['metadata']) ? $json_data['metadata'] : ''
				];
				// ProviderHelper::updatecreateGameTransExt($game_transextension,  $json_data, $response, $client_response->requestoclient, $client_response, 'SUCCESS', $general_details);
				// ProviderHelper::updatecreateGameTransExt($game_transextension2,  $json_data, $response, $client_response2->requestoclient, $client_response2, 'FAILED', $general_details);
				// Providerhelper::createRestrictGame($game_details->game_id,$client_details->player_id,$game_transextension2, $client_response2->requestoclient);

				$updateTransactionEXt = array(
					"mw_response" => json_encode($items_array),
					'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
					'client_response' => json_encode($client_response),
					'transaction_detail' => 'SUCCESS',
					'general_details' => json_encode($general_details)
				);
				GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);

				$updateTransactionEXt2 = array(
					"mw_response" => json_encode($items_array),
					'mw_request' => isset($client_response2->requestoclient) ? json_encode($client_response2->requestoclient) : 'FAILED',
					'client_response' => json_encode($client_response2),
					'transaction_detail' => 'FAILED',
					'general_details' => json_encode($general_details)
				);
				GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt2,$game_transextension2,$client_details);
			}else{
				$general_details['client']['afterbalance'] = $this->formatBalance($client_response2->fundtransferresponse->balance);
				$response = [
					"timestamp"=> date('YmdHisms'),
					"signature"=> $this->createSignature(date('YmdHisms')),
					"externalTxId"=> $game_transextension2,
					"balance"=> $this->formatBalance($client_response2->fundtransferresponse->balance),
					"errorCode"=> 1,
					"metadata"=>  isset($json_data['metadata']) ? $json_data['metadata'] : ''
				];

				$updateGameTransaction = [
					  'pay_amount' => 0, 
				      'income' => $bet_amount, 
				      'win' => 0, 
				      'entry_id' => 2,
				];
				GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans, $client_details); 

				$updateTransactionEXt = array(
					"mw_response" => json_encode($response),
					'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
					'client_response' => json_encode($client_response),
					'transaction_detail' => 'SUCCESS',
					'general_details' => json_encode($general_details)
				);
				GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);

				$updateTransactionEXt2 = array(
					"mw_response" => json_encode($response),
					'mw_request' => isset($client_response2->requestoclient) ? json_encode($client_response2->requestoclient) : 'FAILED',
					'client_response' => json_encode($client_response2),
					'transaction_detail' => 'SUCCESS',
					'general_details' => json_encode($general_details)
				);
				GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt2,$game_transextension2,$client_details);
			}
		}
		return $response;
	}

	public function CheckTxStatus(){
		ProviderHelper::saveLogWithExeption('RSG CheckTxStatus - EH', $this->provider_db_id, file_get_contents("php://input"), 'ENDPOINT HIT');
		$json_data = json_decode(file_get_contents("php://input"), true);
		if($json_data == null){
			return $this->noBody();
		}
		if($json_data['operatorId'] != $this->operator_id){
			return $this->wrongOperatorID();
		}
		if (!$this->authMethod($json_data['operatorId'], $json_data['timestamp'], $json_data['signature'])){
			return $this->authError();
		}
		# Missing Parameters
		if(!isset($json_data['externalTxId'])){
			if(!isset($json_data['providerTxId'])){
				$response = array(
							"timestamp" => date('YmdHisms'),
							"signature" => $this->createSignature(date('YmdHisms')),
							"errorCode" => 17,
							"items" => [],
					);	
				return $response;
			}
		}elseif(!isset($json_data['providerTxId'])){
			if(!isset($json_data['externalTxId'])){
				$response = array(
							"timestamp" => date('YmdHisms'),
							"signature" => $this->createSignature(date('YmdHisms')),
							"errorCode" => 17,
							"items" => [],
					);	
				return $response;
			}
		}

		// if no externalTxId find the provider TxId instead
		if(isset($json_data['externalTxId']) && $json_data['externalTxId'] != null){
			// $transaction_general_details = $this->findTransactionRefund($json_data['externalTxId'], 'game_trans_ext_id');
			$transaction_general_details = GameTransactionMDB::findGameExt($json_data['externalTxId'], 1,'game_trans_ext_id', $client_details);
		}else{
			// return $json_data['providerTxId'];
			// $transaction_general_details = $this->findTransactionRefund($json_data['providerTxId'], 'transaction_id');
			$transaction_general_details = GameTransactionMDB::findGameExt($json_data['providerTxId'], 1,'transaction_id', $client_details);
		}
	    if($transaction_general_details != false){
	    	$general_details = json_decode($transaction_general_details->general_details);
			$txStatus = $general_details->aggregator->transaction_status == 'SUCCESS' ? true : false;
			$response = [
				"timestamp" => date('YmdHisms'),
			    "signature" => $this->createSignature(date('YmdHisms')),
				"txStatus" => $txStatus,  // true if transaction process successfully
				// "operationType" => $general_details->provider->operationType, // transaction operation type
				"txCreationDate" => $general_details->provider->txCreationDate, // transaction created date
				"externalTxId" => $general_details->aggregator->externalTxId, // aggregator identifier
				"balanceBefore" => $general_details->client->beforebalance, // players before balance;
				"balanceAfter" => $general_details->client->afterbalance, // players after balance
				"currencyId" => $general_details->provider->currencyId, // players currency
				"amount" => $general_details->provider->amount, // amount of the transaction
				"errorCode" => 1 // error code
			];
			if(isset($general_details->provider->operationType)){
				$response['operationType'] = $general_details->provider->operationType;
			}
	    }else{
			$response = [
				"timestamp" => date('YmdHisms'),
				"signature" => $this->createSignature(date('YmdHisms')),
				"errorCode" => 7 // error code
			];
	    }
		return $response;
	}




	public function checkTransactionExt($column, $identifier,$type){
		$query = DB::select('select game_trans_ext_id from game_transaction_ext where `'.$column.'` = "'.$identifier.'" AND game_transaction_type = '.$type.'');
		$data = count($query);
		return $data > 0 ? $query[0] : null;
	}

	public function updateGameExtTransDetails($data, $column, $identifier,$type){
		$query = DB::select('update game_transaction_ext SET transaction_detail = "'.$data.'" where `'.$column.'` = "'.$identifier.'" AND game_transaction_type = '.$type.'');
		$data = count($query);
		return $data > 0 ? $query[0] : null;
	}


	public function checkIfBetHasWon($column, $identifier,$type){
		$query = DB::select('select game_trans_ext_id from game_transaction_ext where `'.$column.'` = "'.$identifier.'" AND  transaction_detail = "BETWON" AND game_transaction_type = '.$type.'');
		$data = count($query);
		return $data > 0 ? $query[0] : null;
	}


	/**
	 * Pull out data from the Game exstension logs!
	 * @param $trans_type = round_id/provider_trans_id
	 * @param $trans_identifier = identifier
	 * @param $type = 1 = lost, 2 = win, 3 = refund
	 * 
	 */
	public  function gameTransactionEXTLog($trans_type,$trans_identifier,$type=false){
		$game = DB::table('game_transaction_ext')
				   ->where($trans_type, $trans_identifier)
				   ->where('game_transaction_type',$type)
				   ->first();
		return $game ? $game :false;
	}


    /**
	 * Find The Transactions For Refund, Providers Transaction ID
	 * 
	 */
    public  function findTransactionRefund($transaction_id, $type) {


    	if ($type == 'transaction_id') {
		 	$where = 'where gte.provider_trans_id = "'.$transaction_id.'"';
		}
		if ($type == 'game_trans_ext_id') {
		 	$where = 'where gte.game_trans_ext_id = "'.$transaction_id.'"';
		}
		if ($type == 'round_id') {
			$where = 'where gte.round_id = "'.$transaction_id.'"';
		}

	 	$filter = 'LIMIT 1';

		$query = DB::select('select `gt`.`game_trans_id`, `gt`.`provider_trans_id`, `gt`.`token_id`, `gt`.`game_id`, `gt`.`round_id`, `gt`.`bet_amount`, `gt`.`win`, `gt`.`pay_amount`, `gt`.`income`, `gt`.`entry_id`, `gte`.`game_trans_ext_id`, `gte`.`amount`, `gte`.`game_transaction_type`, `gte`.`provider_request`, `gte`.`general_details` from `game_transactions` as `gt` left join `game_transaction_ext` as `gte` on `gte`.`game_trans_id` = `gt`.`game_trans_id` '.$where.' '.$filter.'');
		$client_details = count($query);
		return $client_details > 0 ? $query[0] : false;


		// $transaction_db = DB::table('game_transactions as gt')
		// 		    	// ->select('gt.*', 'gte.transaction_detail')
		// 		    	->select('gt.game_trans_id', 'gt.provider_trans_id', 'gt.token_id', 'gt.game_id', 'gt.round_id', 'gt.bet_amount', 'gt.win', 'gt.pay_amount', 'gt.income','gt.entry_id','gte.game_trans_ext_id','gte.amount','gte.game_transaction_type', 'gte.provider_request')
		// 			    ->leftJoin("game_transaction_ext AS gte", "gte.game_trans_id", "=", "gt.game_trans_id");
	    //    if ($type == 'transaction_id') {
		// 	$transaction_db->where([
		//  		["gte.provider_trans_id", "=", $transaction_id],
		//  	]);
		// }
		// if ($type == 'game_trans_ext_id') {
		// 	$transaction_db->where([
		//  		["gte.game_trans_ext_id", "=", $transaction_id],
		//  	]);
		// }
		// if ($type == 'round_id') {
		// 	$transaction_db->where([
		//  		["gte.round_id", "=", $transaction_id],
		//  	]);
		// }
		// if ($type == 'bet') { // TEST
		// 	$transaction_db->where([
		//  		["gt.round_id", "=", $transaction_id],
		//  		["gt.payout_reason",'like', '%BET%'],
		//  	]);
		// }
		// if ($type == 'refundbet') { // TEST
		// 	$transaction_db->where([
		//  		["gt.round_id", "=", $transaction_id],
		//  	]);
		// }
		// $result= $transaction_db
	 	// 		->latest('token_id')
	 	// 		->first();

		// if($result){
		// 	return $result;
		// }else{
		// 	return false;
		// }
	}

	/**
	 * Get all rounds
	 * 
	 */
	public function getAllRounds($round_id){
		$all_rounds = DB::table('game_transaction_ext')->where('round_id', $round_id)->where('transaction_detail', "!=", '"FAILED"')->get();
		return $all_rounds;
	}

	/**
	 * Get data to the bet item (inside the provider request)
	 * 
	 */
	public function findObjDataItem($items_json_data, $if_selector, $dato_to_get){
		$item = json_decode($items_json_data);
		$item_count = count($item->items);
		if($item_count < 1){
			foreach ($item->items as $key) {
				if($key->$if_selector == $key[$if_selector]){
					return $data = $key->$dato_to_get;
				}
			}
		}else{
			$data = $item->items[0]->$dato_to_get;
		}
		return $data;
	}

	/**
	 * Find The Transactions For Win/bet, Providers Transaction ID
	 * 
	 */
	public  function findGameTransaction($transaction_id) {
		$transaction_db = DB::table('game_transactions as gt')
	 				   ->where('gt.provider_trans_id', $transaction_id)
	 				   ->latest()
	 				   ->first();
	   	return $transaction_db ? $transaction_db : false;
	}

	/**
	 * Find The Transactions For Win/bet, Providers Transaction ID
	 */
	public  function findPlayerGameTransaction($round_id, $player_id) {
	    $player_game = DB::table('game_transactions as gts')
		    		->select('*')
		    		->join('player_session_tokens as pt','gts.token_id','=','pt.token_id')
                    ->join('players as pl','pt.player_id','=','pl.player_id')
                    ->where('pl.player_id', $player_id)
                    ->where('gts.round_id', $round_id)
                    ->first();
        // $json_data = json_encode($player_game);
	    return $player_game;
	}

	/**
	 * Find The Transactions For Refund, Providers Transaction ID
	 * @return  [<string>]
	 * 
	 */
    public  function getOperationcampaignType($operation_type) {
  		// 1- Tournament
		// 2- Bonus Award
		// 3- Chat Game Winning
    	$operation_types = [
    		'1' => 35, // win tournament
    		'2' => 5, // bunos win
    		'3' => 65, // FreeWinAmount
    	];
    	if(array_key_exists($operation_type, $operation_types)){
    		return $operation_types[$operation_type];
    	}else{
    		return 35;
    	}
	}

	/**
	 * Find The Transactions For Refund, Providers Transaction ID
	 * @return  [<string>]
	 * 
	 */
    public  function getOperationType($operation_type) {

    	$operation_types = [
    		'1' => 'General Bet',
    		'2' => 'General Win',
    		'3' => 'Refund',
    		'4' => 'Bonus Bet',
    		'5' => 'Bonus Win',
    		'6' => 'Round Finish',
    		'7' => 'Insurance Bet',
    		'8' => 'Insurance Win',
    		'9' => 'Double Bet',
    		'10' => 'Double Win',
    		'11' => 'Split Bet',
    		'12' => 'Split Win',
    		'13' => 'Ante Bet',
    		'14' => 'Ante Win',
    		'15' => 'General Bet Behind',
    		'16' => 'General Win Behind',
    		'17' => 'Split Bet Behind',
    		'18' => 'Split Win Behind',
    		'19' => 'Double Bet Behind',
    		'20' => 'Double Win Behind',
    		'21' => 'Insurance Bet Behind',
    		'22' => 'Insurance Win Behind',
    		'23' => 'Call Bet',
    		'24' => 'Call Win',
    		'25' => 'Jackpot Bet',
    		'26' => 'Jackpot Win',
    		'27' => 'Tip',
    		'28' => 'Free Bet Win',
    		'29' => 'Free Spin Win',
    		'30' => 'Gift Bet',
    		'31' => 'Gift Win',
    		'32' => 'Deposit',
    		'33' => 'Withdraw',
    		'34' => 'Fee',
    		'35' => 'Win Tournament',
    		'36' => 'Cancel Fee',
    		'37' => 'Amend Credit',
    		'38' => 'Amend Debit',
    		'39' => 'Feature Trigger Bet',
    		'40' => 'Feature Trigger Win',
    		'42' => 'Game Data Change Bet',
    		'45' => 'Take Risk Bet',
    		'46' => 'Take Risk Win',
    		'49' => 'Cards Combination Win',
    		'52' => 'Dice Combination Win',
    		'55' => 'Magic Card Win',
    		'57' => 'Doubling Bet',
    		'58' => 'Doubling Win',
    		'60' => 'Shop Bet',
    		'61' => 'Shop Win',
    		'68' => 'Tournament King Win',
    		'71' => 'Drop Bet',
    		'72' => 'Drop Win',
    		'74' => 'WinRakeRaceTournament',
    		'75' => 'Cashback',
    	];
    	if(array_key_exists($operation_type, $operation_types)){
    		return $operation_types[$operation_type];
    	}else{
    		return 'false';
    	}

	}

	/**
	 * Find The Bet Win Operation Type
	 * @return  [<string>]
	 * 
	 */
	public  function getWinOpType($operation_type)
	{
		$operation_types = [
			'2' => 2,
			'5' => 5,
			'6' => 6,
			'8' => 8,
			'109' => 10,
			'12' => 12,
			'14' => 14,
			'16' => 16,
			'18' => 18,
			'20' => 20,
			'22' => 22,
			'24' => 24,
			'26' => 26,
			'31' => 31,
			'35' => 35,
			'46' => 46,
			'49' => 49,
			'52' => 52,
			'55' => 55,
			'58' => 58,
			'61' => 61,
			'68' => 68,
		];
		if (array_key_exists($operation_type, $operation_types)) {
			return $operation_types[$operation_type];
		} else {
			return false;
		}
	}

	/**
	 * Find The Bet Win Operation Type
	 * @return  [<string>]
	 * 
	 */
	public  function getBetWinOpType($operation_type)
	{
		$operation_types = [
			'1' => 2,
			'4' => 5,
			'7' => 8,
			'9' => 10,
			'11' => 12,
			'13' => 14,
			'15' => 16,
			'17' => 18,
			'19' => 20,
			'21' => 22,
			'23' => 24,
			'25' => 26,
			'27' => 2,  // no corresponding win (nmust check)
			// '28' => 2,  // missing in documentation
			'30' => 31,
			// '32' => 2, // missing in documentation
			'34' => 35, 
			// '39' => 2, // missing in documentation
			'42' => 43,
			'45' => 46,
			'48' => 49,
			'51' => 52,
			'54' => 55,
			'57' => 58,
			'60' => 61,
			'64' => 65,
			'67' => 68,
		];
		if (array_key_exists($operation_type, $operation_types)) {
			return $operation_types[$operation_type];
		} else {
			return false;
		}
	}

	/**
	 * Find The Bet Win Operation Type
	 * @return  [<string>]
	 * 
	 */
	public  function getWinBetOpType($operation_type)
	{
		$operation_types = [
			'2' => 1,
			'5' => 4,
			'8' => 7,
			'10' => 9,
			'12' => 11,
			'14' => 13,
			'16' => 15,
			'17' => 18,
			'20' => 19,
			'22' => 21,
			'24' => 23,
			'26' => 25,
			'31' => 30,
			'35' => 34,
			'43' => 42,
			'46' => 45,
			'49' => 48,
			'52' => 51,
			'55' => 54,
			'58' => 57,
			'61' => 60,
			'65' => 64,
			'68' => 67,
		];
		if (array_key_exists($operation_type, $operation_types)) {
			return $operation_types[$operation_type];
		} else {
			return false;
		}
	}



public  function getGameId($game_id)
	{
	$game_ids = [
		// BetOnGames
		'19' => '19', // Keno8
		'2010' => '2010', // Keno 10
		'2011' => '2011', // betongameskeno10 mobile
		'2012' => '2012', // betongameskeno8 mobile
		'2013' => '2013', // Crash Mobile
		'2014' => '2014', // Crash
		'5236' => '5236', // Hilo
		'5237' => '5237', // Hilo Mobile
		'5339' => '5339', // SicBo
		'5935' => '5935', // Rocketon
		'6492' => '6492', // Penalty
		'8098' => '8098', // Keno Express
		'8100' => '8100', // BlackJack
		'11506' => '11506', // BlackJack
		'9895' => '9895', // Keno Express
		'5730' => '5730', // Penalty
		'5510' => '5510', // Rocketon
		'5337' => '5337', // Sicbo

		// SkillGames
		'6210' => '6210', // Joker Classic
		'6211' => '6211', // p2pmineSweeper desktop
		'6212' => '6212', // p2pmineSweeper mobile
		'6213' => '6213', // Hokm Mobile
		'6214' => '6214', // Hokm
		'6215' => '6215', // Chingachung
		'6216' => '6216', // Chingachung
		'6217' => '6217', // Tournament Mobile
		'6218' => '6218', // Tournament Desktop
		'6219' => '6219', // Joker Short
		'6220' => '6220', // Joker Short
		'6221' => '6221', // Joker Classic
		'6222' => '6222', // Pasoor
		'6223' => '6223', // Pasoor
		'6224' => '6224', // CB21
		'6225' => '6225', // Asian
		'6226' => '6226', // Asian
		'6227' => '6227', // p2pbeloteopen_mobile
		'6228' => '6228', // p2pbeloteclassic_mobile
		'6229' => '6229', // p2pbeloteopen_desktop
		'6230' => '6230', // p2p belote classic desktop
		'6231' => '6231', // pioner
		'6232' => '6232', // pioner
		'6233' => '6233', // inout
		'6234' => '6234', // inout
		'6235' => '6235', // Backgammon mobile
		'6237' => '6237', // Hyper mobile
		'6238' => '6238', // Hyper
		'6239' => '6239', // Nackgammon
		'6240' => '6240', // Nackgammon mobile
		'6241' => '6241', // Long mobile
		'6242' => '6242', // Long desktop
		'6243' => '6243', // Dominoes
		'6244' => '6244', // Dominoes Fives
		'6245' => '6245', // Dominoes Threes
		'6246' => '6246', // Dominoes Block
		'6247' => '6247', // Dominoes
		'6248' => '6248', // Dominoes Fives
		'6249' => '6249', // Dominoes Threes
		'6250' => '6250', // DominoesBlock
		'6251' => '6251', // game21
		'6252' => '6252', // toto 21
		'6253' => '6253', // game21
		'6254' => '6254', // cb 21
		'6255' => '6255', // cw 21
		'6256' => '6256', // cw 21
		'6257' => '6257', // ib 21
		'6273' => '6273', // skillgames_mobile
		'6274' => '6274', // skillgames_desktop
	];
	if (array_key_exists($game_id, $game_ids)) {
		return $game_ids[$game_id];
	} else {
		return false;
	}
}


	/**
	 * 
	 * 4	- CB0 bunos bet
	 * 25	- CB0 jackpot bet
	 * 30	- CB0 gift bet
	 * 48	- CB0 cards combination bet
	 * 51	- CB0 dice combinataion bet
	 * 60	- CB0 shop bet
	 * 64	- CB0 free amount
	 * 67	- CB0 tournament king bet
	 * 
	 */
	public function betWithNoChangeBalanceOT($operation_type){

		$zero_operation_type = [4,25,30,48,51,54,60,64,67];
		if(in_array($operation_type, $zero_operation_type)){
			return true;
		}else{
			return false;
		}
	
	}

}
