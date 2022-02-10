<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;

use GuzzleHttp\Client;
use Carbon\Carbon;
use App\Helpers\TidyHelper;
use App\Helpers\ClientRequestHelper;
// use App\Models\GameTransactionExt;
use App\Models\GameTransactionMDB;
use DB;


class TidyController extends Controller
{
	 public $prefix_id = 'TG';
	 public $provider_db_id = 23;
	 public $client_id, $API_URL, $prefix;
	 // const SECRET_KEY = 'f83c8224b07f96f41ca23b3522c56ef1'; // token

	/*
	* MARVIN
	* UPDATE NEW FLOW STATUS APRIL 04 2021
	* FILE UPDATE
	* FUNDTRANSFER PROCESSOR ADDING STATUS UPDATE
			MEHTOD NAME =>  bgFundTransferV2
	* CONTROLLER ADDING NEW FLOW STATUS
	*/
	 public function __construct(){
    	$this->client_id = config('providerlinks.tidygaming.client_id');
    	$this->API_URL = config('providerlinks.tidygaming.API_URL');
    	$this->startTime = microtime(true);
    	$this->prefix = "TGOP_";
    }

	 public function autPlayer(Request $request){
		ProviderHelper::saveLogWithExeption('Tidy Auth ', $this->provider_db_id, json_encode($request->all()), 'ENDPOINT HIT');
	 	$playersid = explode('_', $request->username);
		$getClientDetails = ProviderHelper::getClientDetails('player_id',$playersid[1]);
		if($getClientDetails != null){
			// $getPlayer = ProviderHelper::playerDetailsCall($getClientDetails->player_token);
			$get_code_currency = TidyHelper::getcurrencyCode($getClientDetails->default_currency, $getClientDetails->client_id);
			$data_info = array(
				'check' => '1',
				'info' => [
					'username' => $this->prefix.$getClientDetails->username,
					'nickname' => $getClientDetails->display_name,
					'currency' => $get_code_currency,	
					'enable'   => 1,
					'created_at' => $getClientDetails->created_at
				]
			);
			Helper::saveLog('Tidy autPlayer', $this->provider_db_id,  json_encode($request->all()),json_encode( $data_info));
			return response($data_info,200)->header('Content-Type', 'application/json');

		}else {
			$errormessage = array(
				'error_code' 	=> '08-025',
				'error_msg'  	=> 'not_found',
				'request_uuid'	=> $request->request_uuid
			);

			return response($errormessage,200)->header('Content-Type', 'application/json');
		}
	 }


	// One time usage
	public function getGamelist(Request $request){
 		$url = $this->API_URL.'/api/game/outside/list';
 	    $requesttosend = [
            'client_id' => $this->client_id
        ];
        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.TidyHelper::generateToken($requesttosend)
            ]
        ]);
        $guzzle_response = $client->get($url);
        $client_response = json_decode($guzzle_response->getBody()->getContents());
        return json_encode($client_response);
	 }

	// TEST
 	public function demoUrl(Request $request){
			$url = $this->API_URL.'/api/game/outside/demo/link';
	 	    $requesttosend = [
                'client_id' => $this->client_id,
                'game_id'	=> 1,
                'back_url'  => 'http://localhost:9090',
                'quality'	=> 'MD',
                'lang'		=> 'en'
            ];
            $client = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.TidyHelper::generateToken($requesttosend)
                ]
            ]);
            $guzzle_response = $client->post($url);
            $client_response = json_decode($guzzle_response->getBody()->getContents());

            return $client_response;
	}


	/* SEAMLESS METHODS */
	public function checkBalance(Request $request){
		// Helper::saveLog('Tidy Check Balance', $this->provider_db_id,  json_encode(file_get_contents("php://input")), 'ENDPOINT HIT');
		Helper::saveLog('Tidy Check Balance', $this->provider_db_id,  json_encode($request->all()), 'ENDPOINT HIT');
		//$data = json_decode(file_get_contents("php://input")); // INCASE RAW JSON / CHANGE IF NOT ARRAY
		$header = $request->header('Authorization');
		$enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);
		$token = $data->token;
		$request_uuid = $data->request_uuid;
		$client_details = ProviderHelper::getClientDetails('token',$token);
		if ($data->client_id != $this->client_id) {
			$errormessage = array(
				'error_code' 	=> '99-001',
				'error_msg'  	=> 'invalid_partner',
				'request_uuid'	=> $request_uuid
			);
			Helper::saveLog('Tidy Check Balance invalid_partner', $this->provider_db_id, json_encode($request->all()), $errormessage);
			return $errormessage;
		}
		if($client_details != null){
				$currency = $client_details->default_currency;
				$get_code_currency = TidyHelper::getcurrencyCode($currency, $client_details->client_id);
				$num = $client_details->balance;
				$reponse =  array(	
		 			 "uid"			=> $this->prefix_id.'_'.$client_details->player_id,
					 "request_uuid" => $request_uuid,
					 "currency"		=> $get_code_currency,
					 "balance" 		=> ProviderHelper::amountToFloat($num)
			 	);
				Helper::saveLog('Tidy Check Balance Response', $this->provider_db_id, json_encode($request->all()), $reponse);
				return $reponse;
		}else{
			$errormessage = array(
				'error_code' 	=> '99-002',
				'error_msg'  	=> 'invalid_token',
				'request_uuid'	=> $request_uuid
			);
			Helper::saveLog('Tidy Check Balance invalid_token', $this->provider_db_id, json_encode($request->all()), $errormessage);
			return $errormessage;
		}
	}

	public function gameBet(Request $request){
		$header = $request->header('Authorization');
	    Helper::saveLog('Tidy Authorization Logger BET', $this->provider_db_id, json_encode($request->all()), $header);
	    $enc_body = file_get_contents("php://input");
     	parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);
		$game_code = $data->game_id;
		$token = $data->token;
		$amount = $data->amount;
		$uid = $data->uid;
		$bet_id = $data->bet_id;
		$request_uuid = $data->request_uuid;
		$transaction_uuid = $data->transaction_uuid; //Provider Transaction ID	_column
		if ($data->client_id != $this->client_id) {
			$errormessage = array(
				'error_code' 	=> '99-001',
				'error_msg'  	=> 'invalid_partner',
				'request_uuid'	=> $request_uuid
			);
			Helper::saveLog('Tidy BET invalid_partner', $this->provider_db_id, json_encode($request->all()), $errormessage);
			return $errormessage;
		}
		$client_details = ProviderHelper::getClientDetails('token',$token);
		try{
	 		ProviderHelper::idenpotencyTable($this->prefix.'_'.$transaction_uuid);
		}catch(\Exception $e){
		 	$bet_transaction = GameTransactionMDB::findGameExt($transaction_uuid, 1,'transaction_id', $client_details);
		 	if ($bet_transaction != 'false') {
		 		//this will be trigger if error occur 10s
		 		Helper::saveLog('Tidy BET duplicate_transaction success', $this->provider_db_id, json_encode($request->all()),  $bet_transaction->mw_response);
		 		return response($bet_transaction->mw_response,200)
		 		->header('Content-Type', 'application/json');
		 	} 
		 	// sleep(4);
		 	$response = array(
				'error_code' 	=> '99-005',
				'error_msg'  	=> 'system is busy',
				'request_uuid'	=> $request_uuid
			);
		 	Helper::saveLog('Tidy BET duplicate_transaction resend', $this->provider_db_id, json_encode($request->all()),  $response);
		 	return response($response,200)->header('Content-Type', 'application/json');
		 }
		$game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $game_code);
		if ($game_details == null) {
			$errormessage = array(
				'error_code' 	=> '99-003',
				'error_msg'  	=> 'invalid_game',
				'request_uuid'	=> $request_uuid
			);
			Helper::saveLog('Tidy BET invalid_game', $this->provider_db_id, json_encode($request->all()), $errormessage);
			return $errormessage;
		}
		if ($client_details != null) 
		{
			$game_transaction_type = 1; // 1 Bet, 2 Win
			$token_id = $client_details->token_id;
			$bet_amount = $amount;
			$provider_trans_id = $transaction_uuid;
			$general_details = ["aggregator" => [], "provider" => [], "client" => []];
			$key_param = json_decode($json_encode, true);
			if (array_key_exists('reference_transaction_uuid', $key_param)) {
				$bet_id = $data->reference_transaction_uuid;
				// $bet_transaction = TidyHelper::findGameTransaction($bet_id, 'transaction_id',1);
				$bet_transaction = GameTransactionMDB::findGameTransactionDetails($bet_id, 'transaction_id',1, $client_details);
				$client_details->connection_name = $bet_transaction->connection_name;
				$amount = $bet_transaction->bet_amount + $bet_amount;
				$game_trans_id = $bet_transaction->game_trans_id;
				$updateGameTransaction = [
		            'win' => 5,
		            'bet_amount' => $amount,
		            'entry_id' => 1,
		            'trans_status' => 1
		        ];
		        GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
			} else {
				$gameTransactionData = array(
		            "provider_trans_id" => $provider_trans_id,
		            "token_id" => $client_details->token_id,
		            "game_id" => $game_details->game_id,
		            "round_id" => $bet_id,
		            "bet_amount" => $bet_amount,
		            "win" => 5,
		            "pay_amount" => 0,
		            "income" => 0,
		            "entry_id" =>1,
		            "trans_status" =>1,
		            "operator_id" => $client_details->operator_id,
		            "client_id" => $client_details->client_id,
		            "player_id" => $client_details->player_id,
		        );
		        $game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
			} 
			$gameTransactionEXTData = array(
	            "game_trans_id" => $game_trans_id,
	            "provider_trans_id" => $provider_trans_id,
	            "round_id" => $bet_id,
	            "amount" => $bet_amount,
	            "game_transaction_type"=> 1,
	            "provider_request" =>json_encode($request->all()),
	        );
	        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
	        $fund_extra_data = [
	            'provider_name' => $game_details->provider_name
	        ];
			try {
				$client_response = ClientRequestHelper::fundTransferFunta($client_details,$bet_amount,$game_code,$game_details->game_name,$game_trans_ext_id,$game_trans_id,"debit",false,$fund_extra_data);
				ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
	        } catch (\Exception $e) {
	            $response = array(
					'error_code' 	=> '99-005',
					'error_msg'  	=> 'system is busy',
					'request_uuid'	=> $request_uuid
				);
		        $updateTransactionEXt = array(
		            "mw_response" => json_encode($response),
		            'mw_request' => json_encode("FAILED"),
		            'client_response' => json_encode("FAILED"),
		            "transaction_detail" => "FAILED",
					"general_details" =>"FAILED",
		        );
		        GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
				$updateGameTransaction = [
	                "win" => 2,
	                'trans_status' => 5
	            ];
	            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
				Helper::saveLog('Tidy BET FATAL ERROR', $this->provider_db_id, json_encode($request->all()), $response);
			    return $response;
	        }
	        if (isset($client_response->fundtransferresponse->status->code)) {
	        	switch ($client_response->fundtransferresponse->status->code) {
					case "200":
						$num = $client_response->fundtransferresponse->balance;
						$response = [
							"uid" => $uid,
							"request_uuid" => $request_uuid,
							"currency" => TidyHelper::getcurrencyCode($client_details->default_currency,$client_details->client_id),
							"balance" =>  ProviderHelper::amountToFloat($num)
						];

						$update_gametransactionext = array(
							"mw_response" =>json_encode($response),
							"mw_request"=>json_encode($client_response->requestoclient),
							"client_response" =>json_encode($client_response->fundtransferresponse),
							"transaction_detail" =>"success",
							"general_details" => "success",
						);
				        GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_id,$client_details);
		    			Helper::saveLog('Tidy BET success', $this->provider_db_id, json_encode($request->all()), $response);
						break;
					case "402":
						$response = array(
							'error_code' 	=> '00-000',
							'error_msg'  	=> 'not_enough_balance',
							'request_uuid'	=> $request_uuid
						);
	          			$update_gametransactionext = array(
							"mw_response" =>json_encode($response),
							"mw_request"=>json_encode($client_response->requestoclient),
							"client_response" =>json_encode($client_response->fundtransferresponse),
							"transaction_detail" => "FAILED",
							"general_details" => "FAILED",
						);
				        GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_id,$client_details);
						$updateGameTransaction = [
			                "win" => 2,
			                'trans_status' => 5
			            ];
			            if (array_key_exists('reference_transaction_uuid', $key_param)) {
			            	$updateGameTransaction = [
				                "win" => 5,
				                'bet_amount' => $bet_transaction->bet_amount,
				            ];
						}
			            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
	          			Helper::saveLog('Tidy BET not_enough_balance', $this->provider_db_id, json_encode($request->all()), $response);
						// ProviderHelper::createRestrictGame($game_details->game_id,$client_details->player_id,$game_trans_ext_id,json_encode(json_encode($response)));
						break;
					default:
						$response = array(
							'error_code' 	=> '99-005',
							'error_msg'  	=> 'system is busy',
							'request_uuid'	=> $request_uuid
						);
	          			$update_gametransactionext = array(
							"mw_response" =>json_encode($response),
							"mw_request"=>json_encode($client_response->requestoclient),
							"client_response" =>json_encode($client_response->fundtransferresponse),
							"transaction_detail" => "FAILED",
							"general_details" => "FAILED",
						);
				        GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_id,$client_details);
						$updateGameTransaction = [
			                "win" => 2,
			                'trans_status' => 5
			            ];
			            if (array_key_exists('reference_transaction_uuid', $key_param)) {
			            	$updateGameTransaction = [
				                "win" => 5,
				                'bet_amount' => $bet_transaction->bet_amount,
				            ];
						}
			            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
	          			Helper::saveLog('Tidy BET not_enough_balance_default', $this->provider_db_id, json_encode($request->all()), $response);
				}
	        }
		    return $response;
		} else 
		{
			$errormessage = array(
				'error_code' 	=> '08-025',
				'error_msg'  	=> 'not_found',
				'request_uuid'	=> $request_uuid
			);
			Helper::saveLog('Tidy BET not_found', $this->provider_db_id, json_encode($request->all()), $errormessage);
			return $errormessage;
		}
	}

	public function gameWin(Request $request){
		//HEADER AUTHORIZATION
		$header = $request->header('Authorization');
		Helper::saveLog('Tidy Authorization Logger WIN', $this->provider_db_id, json_encode($request->all()), $header);
		//JSON_FORMAT CONVERT
	    $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);
		//INITIALIZE DATA
		$game_code = $data->game_id;
		$token = $data->token;
		$amount = $data->amount;
		$uid = $data->uid;
		$request_uuid = $data->request_uuid;
		$transaction_uuid = $data->transaction_uuid; // MW round_id
		$reference_transaction_uuid = $data->reference_transaction_uuid; //  MW -provider_transaction_id
		//CHECKING TOKEN
		if ($data->client_id != $this->client_id) {
			$errormessage = array(
				'error_code' 	=> '99-001',
				'error_msg'  	=> 'invalid_partner',
				'request_uuid'	=> $request_uuid
			);
			Helper::saveLog('Tidy WIN invalid_partner', $this->provider_db_id, json_encode($request->all()), $errormessage);
			return $errormessage;
		}
		$client_details = ProviderHelper::getClientDetails('token',$token);
		//CHECKING WIN EXISTING game_transaction_ext IF WIN ALREADY PROCESS
		// $transaction_check = GameTransactionExt::findGameExt($transaction_uuid, 2,'transaction_id');
		try{
	 		ProviderHelper::idenpotencyTable($this->prefix.'_'.$transaction_uuid);
		}catch(\Exception $e){
		 	// sleep(7);
		 	// $bet_transaction = GameTransactionExt::findGameExt($transaction_uuid, 1,'transaction_id');
		 	$bet_transaction = GameTransactionMDB::findGameExt($reference_transaction_uuid, 2,'transaction_id', $client_details);
		 	if ($bet_transaction != 'false') {
		 		//this will be trigger if error occur 10s
		 		Helper::saveLog('Tidy BET duplicate_transaction success', $this->provider_db_id, json_encode($request->all()),  $bet_transaction->mw_response);
		 		return response($bet_transaction->mw_response,200)
		 		->header('Content-Type', 'application/json');
		 	} 

		 	$response = array(
				'error_code' 	=> '99-005',
				'error_msg'  	=> 'system is busy',
				'request_uuid'	=> $request_uuid
			);
		 	Helper::saveLog('Tidy BET duplicate_transaction resend', $this->provider_db_id, json_encode($request->all()),  $response);
		 	return response($response,200)->header('Content-Type', 'application/json');
		 }
		//CHECKING BET
		$bet_transaction = GameTransactionMDB::findGameTransactionDetails($reference_transaction_uuid, 'transaction_id',1, $client_details);
		if ($bet_transaction == 'false') {
			$errormessage = array(
				'error_code' 	=> '99-012',
				'error_msg'  	=> 'transaction_does_not_exist',
				'request_uuid'	=> $request_uuid
			);
			Helper::saveLog('Tidy WIN transaction_does_not_exist', $this->provider_db_id, json_encode($request->all()), $errormessage);
			return $errormessage;
		}
		$client_details->connection_name = $bet_transaction->connection_name;
		$game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $game_code);
		if ($game_details == null) {
			$errormessage = array(
				'error_code' 	=> '99-003',
				'error_msg'  	=> 'invalid_game',
				'request_uuid'	=> $request_uuid
			);
			Helper::saveLog('Tidy WIN invalid_game', $this->provider_db_id, json_encode($request->all()), $errormessage);
			return $errormessage;
		}
		try{
			$num = $client_details->balance + $amount;
			ProviderHelper::_insertOrUpdate($client_details->token_id, $num); 
			$response = [
				"uid" => $uid,
				"request_uuid" => $request_uuid,
				"currency" => TidyHelper::getcurrencyCode($client_details->default_currency,$client_details->client_id),
				"balance" =>  ProviderHelper::amountToFloat($num)
			];
			$create_gametransactionext = array(
				"game_trans_id" =>$bet_transaction->game_trans_id,
				"provider_trans_id" => $reference_transaction_uuid,
				"round_id" => $transaction_uuid,
				"amount" => $amount,
				"game_transaction_type"=> 2,
				"provider_request" => json_encode($data),
				"mw_response" => json_encode($response)
			);
			$game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($create_gametransactionext,$client_details);
			$win_or_lost = $amount > 0 ?  1 : 0;
            $entry_id = $amount > 0 ?  2 : 1;
           	$income = $bet_transaction->bet_amount -  $amount ;
			$updateGameTransaction = [
	            'win' => 5,
	            'pay_amount' => $amount,
	            'income' => $income,
	            'entry_id' => $entry_id,
	            'trans_status' => 2
	        ];
        	GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
			$body_details = [
	            "type" => "credit",
	            "win" => $win_or_lost,
	            "token" => $client_details->player_token,
	            "rollback" => false,
	            "game_details" => [
	                "game_id" => $game_details->game_id
	            ],
	            "game_transaction" => [
	                "amount" => $amount
	            ],
	            "connection_name" => $bet_transaction->connection_name,
	            "game_trans_ext_id" => $game_trans_ext_id,
	            "game_transaction_id" => $bet_transaction->game_trans_id

	        ];
			try {
				$client = new Client();
		 		$guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-bgFundTransferV2MultiDB',
		 			[ 'body' => json_encode($body_details), 'timeout' => '2.00']
		 		);
		 		//THIS RESPONSE IF THE TIMEOUT NOT FAILED
	            Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($request->all()), $response);
	            return $response;
			} catch (\Exception $e) {
	            Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($request->all()), $response);
	            return $response;
			}
		}catch(\Exception $e){
			$errormessage = array(
				'error_code' 	=> '08-025',
				'error_msg'  	=> 'not_found',
				'request_uuid'	=> $request_uuid
			);
			Helper::saveLog('Tidy WIN not_found', $this->provider_db_id, json_encode($request->all()), $errormessage);
			return $errormessage;
		}
		
	}


	public function gameRollback(Request $request){
		$header = $request->header('Authorization');
	    Helper::saveLog('Tidy Authorization Logger Rollback', $this->provider_db_id, json_encode(file_get_contents("php://input")), $header);
	    $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);
		$game_id = $data->game_id;
		$uid = $data->uid;
		$token = $data->token;
		$request_uuid = $data->request_uuid;
		$transaction_uuid = $data->transaction_uuid; // MW - provider identifier 
		$reference_transaction_uuid = $data->reference_transaction_uuid; //  MW - round id
		$client_details = ProviderHelper::getClientDetails('token',$token);
		if($client_details == null){
			$data_response = [
				'error' => '99-011' 
			];
			Helper::saveLog('Tidy Rollback error', $this->provider_db_id, json_encode(file_get_contents("php://input")), $data_response);
			return $data_response;
		}
		try{
	 		ProviderHelper::idenpotencyTable($this->prefix.'_'.$transaction_uuid);
		}catch(\Exception $e){
		 	// sleep(7);
		 	$bet_transaction = GameTransactionMDB::findGameExt($reference_transaction_uuid, 3,'transaction_id', $client_details);
		 	if ($bet_transaction != 'false') {
		 		//this will be trigger if error occur 10s
		 		Helper::saveLog('Tidy BET duplicate_transaction success', $this->provider_db_id, json_encode($request->all()),  $bet_transaction->mw_response);
		 		return response($bet_transaction->mw_response,200)
		 		->header('Content-Type', 'application/json');
		 	} 

		 	$response = array(
				'error_code' 	=> '99-005',
				'error_msg'  	=> 'system is busy',
				'request_uuid'	=> $request_uuid
			);
		 	Helper::saveLog('Tidy BET duplicate_transaction resend', $this->provider_db_id, json_encode($request->all()),  $response);
		 	return response($response,200)->header('Content-Type', 'application/json');
		}
		$game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $game_id);
		$existing_bet = GameTransactionMDB::findGameTransactionDetails($reference_transaction_uuid, 'transaction_id',false, $client_details);
		if($existing_bet == 'false'){
			$data_response = array(
				'error_code' 	=> '99-012',
				'error_msg'  	=> 'transaction_does_not_exist',
				'request_uuid'	=> $request_uuid
			);
			Helper::saveLog('Tidy Rollback error', $this->provider_db_id, json_encode(file_get_contents("php://input")), $data_response);
			return $data_response;
		}
		$client_details->connection_name = $existing_bet->connection_name;
		$already_process = GameTransactionMDB::findGameExt($reference_transaction_uuid, 2,'transaction_id', $client_details);
		if($already_process != 'false'){
			return response($already_process->mw_response,200)
		 		->header('Content-Type', 'application/json');
		}
		try{
			$num = $client_details->balance + $existing_bet->bet_amount;
			ProviderHelper::_insertOrUpdate($client_details->token_id, $num); 
			$response = [
				"uid" => $uid,
				"request_uuid" => $request_uuid,
				"currency" => TidyHelper::getcurrencyCode($client_details->default_currency,$client_details->client_id),
				"balance" =>  ProviderHelper::amountToFloat($num)
			];
			$create_gametransactionext = array(
				"game_trans_id" => $existing_bet->game_trans_id,
				"provider_trans_id" => $reference_transaction_uuid,
				"round_id" => $transaction_uuid,
				"amount" => $existing_bet->bet_amount,
				"game_transaction_type"=> 3,
				"provider_request" => json_encode($data),
				"mw_response" => json_encode($response)
			);
			$game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($create_gametransactionext,$client_details);
			$win_or_lost = 4;
            $entry_id = $existing_bet->bet_amount > 0 ?  2 : 1;
           	$income = 0;
			$updateGameTransaction = [
	            'win' => 5,
	            'pay_amount' => $existing_bet->bet_amount,
	            'income' => $income,
	            'entry_id' => $entry_id,
	            'trans_status' => 3
	        ];
        	GameTransactionMDB::updateGametransaction($updateGameTransaction, $existing_bet->game_trans_id, $client_details);
			$body_details = [
	            "type" => "credit",
	            "win" => $win_or_lost,
	            "token" => $client_details->player_token,
	            "rollback" => "true",
	            "game_details" => [
	                "game_id" => $game_details->game_id
	            ],
	            "game_transaction" => [
	                "amount" => $existing_bet->bet_amount
	            ],
	            "connection_name" => $existing_bet->connection_name,
	            "game_trans_ext_id" => $game_trans_ext_id,
	            "game_transaction_id" => $existing_bet->game_trans_id

	        ];
			try {
				$client = new Client();
		 		$guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-bgFundTransferV2MultiDB',
		 			[ 'body' => json_encode($body_details), 'timeout' => '2.00']
		 		);
		 		//THIS RESPONSE IF THE TIMEOUT NOT FAILED
	            Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($request->all()), $response);
	            return $response;
			} catch (\Exception $e) {
	            Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($request->all()), $response);
	            return $response;
			}
		}catch(\Exception $e){
			$data_response = array(
				'error_code' 	=> '99-012',
				'error_msg'  	=> 'transaction_does_not_exist',
				'request_uuid'	=> $request_uuid
			);
			Helper::saveLog('Tidy Rollback error ='.$e->getMessage(), $this->provider_db_id, json_encode(file_get_contents("php://input")), $data_response);
			return $data_response;
		}
	}



}

