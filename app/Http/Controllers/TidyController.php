<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;

use GuzzleHttp\Client;
use Carbon\Carbon;
use App\Helpers\TidyHelper;
use App\Helpers\ClientRequestHelper;
use DB;


class TidyController extends Controller
{
	 public $prefix_id = 'TG';
	 public $provider_db_id = 23;
	 public $client_id, $API_URL, $prefix;
	 // const SECRET_KEY = 'f83c8224b07f96f41ca23b3522c56ef1'; // token

	 public function __construct(){
    	$this->client_id = config('providerlinks.tidygaming.client_id');
    	$this->API_URL = config('providerlinks.tidygaming.API_URL');
    	$this->startTime = microtime(true);
    	$this->prefix = "TIDY";
    }

	 public function autPlayer(Request $request){
	 	$playersid = explode('_', $request->username);
		$getClientDetails = ProviderHelper::getClientDetails('player_id',$playersid[1]);
		if($getClientDetails != null){
			$getPlayer = ProviderHelper::playerDetailsCall($getClientDetails->player_token);
			$get_code_currency = TidyHelper::currencyCode($getClientDetails->default_currency);
			$data_info = array(
				'check' => '1',
				'info' => [
					'username' => $getClientDetails->username,
					'nickname' => $getClientDetails->display_name,
					'currency' => $get_code_currency,	
					'enable'   => 1,
					'created_at' => $getClientDetails->created_at
				]
			);
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
				try{
					$player_details = Providerhelper::playerDetailsCall($client_details->player_token);
				} catch(\Exception $e){
					$errormessage = array(
						'error_code' 	=> '99-002',
						'error_msg'  	=> 'invalid_token',
						'request_uuid'	=> $request_uuid
					);
					Helper::saveLog('Tidy Check Balance FATAL ERROR', $this->provider_db_id, json_encode($request->all()), $errormessage);
					return $errormessage;
				}

		        $balance = floatval(number_format($player_details->playerdetailsresponse->balance, 2, '.', ''));
		        $save_bal = DB::table("player_session_tokens")->where("token_id","=",$client_details->token_id)->update(["balance" => $balance]);
		        
				$currency = $client_details->default_currency;
				$get_code_currency = TidyHelper::currencyCode($currency);

				$num = $player_details->playerdetailsresponse->balance;
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
		$transaction_uuid = $data->transaction_uuid;
		

		if ($data->client_id != $this->client_id) {
			$errormessage = array(
				'error_code' 	=> '99-001',
				'error_msg'  	=> 'invalid_partner',
				'request_uuid'	=> $request_uuid
			);
			Helper::saveLog('Tidy BET invalid_partner', $this->provider_db_id, json_encode($request->all()), $errormessage);
			return $errormessage;
		}


		try{
			ProviderHelper::idenpotencyTable($this->prefix.'_'.$transaction_uuid);
		}catch(\Exception $e){
			sleep(7);
			$bet_transaction = ProviderHelper::findGameExt($transaction_uuid, 1,'transaction_id');
			if ($bet_transaction != 'false') {
				//this will be trigger if error occur 10s
				Helper::saveLog('Tidy BET duplicate_transaction success', $this->provider_db_id, json_encode($request->all()),  $bet_transaction->mw_response);
				return response($bet_transaction->mw_response,200)
				->header('Content-Type', 'application/json');
			} 
			sleep(4);
			$response = ["message" => "resend provider"];
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

		$client_details = ProviderHelper::getClientDetails('token',$token);

		if ($client_details != null) 
		{

			// $errOccur = ProviderHelper::checkGameRestricted($game_details->game_id, $client_details->player_id);
			
			// if($errOccur == true){
			// 	$errormessage = array(
			// 	'error_code' 	=> '99-003',
			// 	'error_msg'  	=> 'invalid_game',
			// 	'request_uuid'	=> $request_uuid
			// 	);
			// 	Helper::saveLog('Tidy BET checkGameRestricted', $this->provider_db_id, json_encode($request->all()), $errormessage);
			// 	return $errormessage;
			// }


			$game_transaction_type = 1; // 1 Bet, 2 Win
			$token_id = $client_details->token_id;
			$bet_amount = $amount;
			$pay_amount = 0;
			$income = 0;
			$win_type = 0;
			$method = 1;
			$win_or_lost = 5; // 0 lost,  5 processing
			$payout_reason = 'Bet';
			$provider_trans_id = $transaction_uuid;

			$general_details = ["aggregator" => [], "provider" => [], "client" => []];
			$key_param = json_decode($json_encode, true);
			if (array_key_exists('reference_transaction_uuid', $key_param)) {
				$bet_id = $data->reference_transaction_uuid;
				$bet_transaction = ProviderHelper::findGameTransaction($bet_id, 'transaction_id',1);
				$amount = $bet_transaction->bet_amount + $bet_amount;
				$game_trans_id = $bet_transaction->game_trans_id;
				TidyHelper::updateGameTransactionBet($game_trans_id,$amount);
			} else {
				$game_trans_id  = ProviderHelper::createGameTransaction($token_id, $game_details->game_id, $bet_amount,  $pay_amount, $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id, $bet_id);
			} 
			$game_trans_ext_id = $this->createGameTransExt($game_trans_id,$provider_trans_id, $bet_id, $bet_amount, $game_transaction_type, $data, $data_response = null, $requesttosend = null, $client_response = null, $data_response = null);
			
			try {
				$client_response = ClientRequestHelper::fundTransferFunta($client_details,$bet_amount,$game_code,$game_details->game_name,$game_trans_ext_id,$game_trans_id,"debit",false);
				$save_bal = DB::table("player_session_tokens")->where("token_id","=",$client_details->token_id)->update(["balance" => $client_response->fundtransferresponse->balance]);
	        } catch (\Exception $e) {
	            $response = array(
					'error_code' 	=> '99-001',
					'error_msg'  	=> 'invalid_partner',
					'request_uuid'	=> $request_uuid
				);
				ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, $data, $response, 'FAILED', $e->getMessage(), $response, $general_details);
				ProviderHelper::updateGameTransactionStatus($game_trans_id, 2, 99);
				// ProviderHelper::createRestrictGame($game_details->game_id,$client_details->player_id,$game_trans_ext_id,json_encode(json_encode($response)));
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
							"currency" => TidyHelper::currencyCode($client_details->default_currency),
							"balance" =>  ProviderHelper::amountToFloat($num)
						];

						$this->updateGameTransactionExt(
							$game_trans_ext_id,
							$client_response->requestoclient,
							$response,
							$client_response->fundtransferresponse);
		    			Helper::saveLog('Tidy BET success', $this->provider_db_id, json_encode($request->all()), $response);
						break;
					
					case "402":
						$response = array(
							'error_code' 	=> '99-005',
							'error_msg'  	=> 'not_enough_balance',
							'request_uuid'	=> $request_uuid
						);
	          			ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, $data, $response, $client_response->requestoclient, $client_response, $client_response->fundtransferresponse, $general_details);
	          			ProviderHelper::updateGameTransactionStatus($game_trans_id, 2, 6);
	          			Helper::saveLog('Tidy BET not_enough_balance', $this->provider_db_id, json_encode($request->all()), $response);
						// ProviderHelper::createRestrictGame($game_details->game_id,$client_details->player_id,$game_trans_ext_id,json_encode(json_encode($response)));
						break;

					default:
						$response = array(
							'error_code' 	=> '99-005',
							'error_msg'  	=> 'not_enough_balance',
							'request_uuid'	=> $request_uuid
						);
	          			// ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, 'FAILED', $response, 'FAILED', $client_response, $client_response->fundtransferresponse, $general_details);
	          			ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, $data, $response, $client_response->requestoclient, $client_response, $client_response->fundtransferresponse, $general_details);
	          			ProviderHelper::updateGameTransactionStatus($game_trans_id, 2, 504);
	          			Helper::saveLog('Tidy BET not_enough_balance_default', $this->provider_db_id, json_encode($request->all()), $response);
	          			// ProviderHelper::createRestrictGame($game_details->game_id,$client_details->player_id,$game_trans_ext_id,json_encode(json_encode($response)));
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
		$transaction_uuid = $data->transaction_uuid; // MW PROVIDER
		$reference_transaction_uuid = $data->reference_transaction_uuid; //  MW -ROUND

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
		$transaction_check = ProviderHelper::findGameExt($transaction_uuid, 2,'transaction_id');

		if($transaction_check != 'false'){
			$response = [
				"uid" => $uid,
				"request_uuid" => $request_uuid,
				"currency" => TidyHelper::currencyCode($client_details->default_currency),
				"balance" =>  ProviderHelper::amountToFloat($client_details->balance)
			];
            Helper::saveLog('Tidy WIN duplicate_transaction', $this->provider_db_id, json_encode($request->all()), $response);
            return $response;
		}
		
		//CHECKING BET
		$bet_transaction = ProviderHelper::findGameTransaction($reference_transaction_uuid, 'transaction_id',1);

		if ($bet_transaction == 'false') {
			$errormessage = array(
				'error_code' 	=> '99-012',
				'error_msg'  	=> 'transaction_does_not_exist',
				'request_uuid'	=> $request_uuid
			);
			Helper::saveLog('Tidy WIN transaction_does_not_exist', $this->provider_db_id, json_encode($request->all()), $errormessage);
			return $errormessage;
		}
		
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
				"currency" => TidyHelper::currencyCode($client_details->default_currency),
				"balance" =>  ProviderHelper::amountToFloat($num)
			];


			$game_trans_ext_id = $this->createGameTransExt($bet_transaction->game_trans_id,$transaction_uuid, $reference_transaction_uuid, $amount, 2, $data, $response, $requesttosend = null, $client_response = null, $data_response = null);

			//Initialize data to pass
			$win = $amount > 0  ?  1 : 0;  /// 1win 0lost
			$type = $amount > 0  ? "credit" : "debit";
			$request_data = [
				'win' => 5,
				'amount' => $amount,
				'payout_reason' => ProviderHelper::updateReason(5),
			];
			//update transaction
			Helper::updateGameTransaction($bet_transaction,$request_data,$type);

			$body_details = [
	            "type" => "credit",
	            "win" => $win,
	            "token" => $client_details->player_token,
	            "rollback" => false,
	            "game_details" => [
	                "game_id" => $game_details->game_id
	            ],
	            "game_transaction" => [
	                "provider_trans_id" => $transaction_uuid,
	                "round_id" => $reference_transaction_uuid,
	                "amount" => $amount
	            ],
	            "provider_request" => $data,
	            "provider_response" => $response,
	            "game_trans_ext_id" => $game_trans_ext_id,
	            "game_transaction_id" => $bet_transaction->game_trans_id

	        ];
			try {
				$client = new Client();
		 		$guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-fundtransferV2',
		 			[ 'body' => json_encode($body_details), 'timeout' => '0.20']
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
		$game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $game_id);

		$existing_bet =  ProviderHelper::findGameExt($reference_transaction_uuid,1,'transaction_id');
		
		if($existing_bet == 'false'){
			$data_response = array(
				'error_code' 	=> '99-012',
				'error_msg'  	=> 'transaction_does_not_exist',
				'request_uuid'	=> $request_uuid
			);
			Helper::saveLog('Tidy Rollback error', $this->provider_db_id, json_encode(file_get_contents("php://input")), $data_response);
			return $data_response;
		}

		$already_process =  ProviderHelper::findGameExt($reference_transaction_uuid,2,'round_id');
		if($already_process != 'false'){
			$data_response = [
				"uid" => $uid,
				"request_uuid" => $request_uuid,
				"currency" => TidyHelper::currencyCode($client_details->default_currency),
				"balance" => ProviderHelper::amountToFloat($client_details->balance)
			];
			return $data_response;
		}

		$refund_call = ProviderHelper::findGameExt($reference_transaction_uuid, 3,'round_id');
		if($refund_call != 'false'){
			$data_response = [
				"uid" => $uid,
				"request_uuid" => $request_uuid,
				"currency" => TidyHelper::currencyCode($client_details->default_currency),
				"balance" => ProviderHelper::amountToFloat($client_details->balance)
			];
			Helper::saveLog('Tidy Rollback idom', $this->provider_db_id, json_encode(file_get_contents("php://input")), $data_response);
			return $data_response;
		}
		
		try{
			$bet_transaction = ProviderHelper::findGameTransaction($existing_bet->game_trans_id, 'game_transaction');
			$game_transextension = $this->createGameTransExt($bet_transaction->game_trans_id,$transaction_uuid, $reference_transaction_uuid, $bet_transaction->bet_amount, 3, $data, $data_response = null, $requesttosend = null, $client_response = null, $data_response = null);
			
			//get game_trans_id and game_trans_ext
			$transaction_id = ProviderHelper::findGameExt($transaction_uuid, 3,'transaction_id');

			//requesttosend, and responsetoclient client side
			$type = "credit";
			$rollback = "true";
			$client_response = ClientRequestHelper::fundTransfer($client_details,$bet_transaction->bet_amount,$game_details->game_code,$game_details->game_name,$transaction_id->game_trans_ext_id,$transaction_id->game_trans_id,$type,$rollback);

			$num = $client_response->fundtransferresponse->balance;
			$data_response = [
				"uid" => $uid,
				"request_uuid" => $request_uuid,
				"currency" => TidyHelper::currencyCode($client_details->default_currency),
				"balance" => ProviderHelper::amountToFloat($num)
			];
 
			$type = "refund";
			$request_data = [
				'amount' => $bet_transaction->bet_amount,
				'transid' => $transaction_uuid,
				'roundid' => $reference_transaction_uuid
			];
			//update transaction
			Helper::updateGameTransaction($bet_transaction,$request_data,$type);
			$this->updateGameTransactionExt($transaction_id->game_trans_ext_id,$client_response->requestoclient,$client_response->fundtransferresponse,$data_response);
		    Helper::saveLog('Tidy Win Processed', $this->provider_db_id, json_encode(file_get_contents("php://input")), $data_response);
			return $data_response;
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


	public  static function rollbackTransaction($round_id,$win, $entry_id) {
   	    $update = DB::table('game_transactions')
                ->where('round_id', $round_id)
                ->update([
	        		  'win' => $win, 
	        		  'entry_id' => $entry_id,
	        		  'transaction_reason' => ProviderHelper::updateReason($win),
	    		]);
		return ($update ? true : false);
	}

	 public  static function findGameExt($provider_identifier, $type) {
		$transaction_db = DB::table('game_transaction_ext as gte');
        if ($type == 'transaction_id') {
			$transaction_db->where([
		 		["gte.provider_trans_id", "=", $provider_identifier]
		 	
		 	]);
		}
		if ($type == 'round_id') {
			$transaction_db->where([
		 		["gte.round_id", "=", $provider_identifier],
		 	]);
		}  
		$result= $transaction_db->first();
		return $result ? $result : 'false';
	}

	public static function createGameTransExt($game_trans_id, $provider_trans_id, $round_id, $amount, $game_type, $provider_request, $mw_response, $mw_request, $client_response, $transaction_detail){
		$gametransactionext = array(
			"game_trans_id" => $game_trans_id,
			"provider_trans_id" => $provider_trans_id,
			"round_id" => $round_id,
			"amount" => $amount,
			"game_transaction_type"=>$game_type,
			"provider_request" => json_encode($provider_request),
			"mw_response" =>json_encode($mw_response),
			"mw_request"=>json_encode($mw_request),
			"client_response" =>json_encode($client_response),
			"transaction_detail" =>json_encode($transaction_detail)
		);
		$gamestransaction_ext_ID = DB::table("game_transaction_ext")->insertGetId($gametransactionext);
		return $gamestransaction_ext_ID;
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

