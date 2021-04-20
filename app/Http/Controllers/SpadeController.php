<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use GuzzleHttp\Client;
use Carbon\Carbon;
use App\Helpers\ClientRequestHelper;
use DB;

class SpadeController extends Controller
{
	
    public function __construct(){
    	$this->prefix = config('providerlinks.spade.prefix');
    	$this->merchantCode = config('providerlinks.spade.merchantCode');
		$this->siteId = config('providerlinks.spade.siteId');
		$this->api_url = config('providerlinks.spade.api_url');
		$this->provider_db_id = config('providerlinks.spade.provider_id');
	}

	public function generateSerialNo(){
    	// $guid = vsprintf('%s%s-%s-4000-8%.3s-%s%s%s0',str_split(dechex( microtime(true) * 1000 ) . bin2hex( random_bytes(8) ),4));
    	$guid = substr("abcdefghijklmnopqrstuvwxyz1234567890", mt_rand(0, 25), 1).substr(md5(time()), 1);;
    	return $guid;
	}
	
	public function getGameList(Request $request){
		$api = $this->api_url;
		
		$requesttosend = [
			'serialNo' =>  $this->generateSerialNo(),
			'merchantCode' => $this->merchantCode,
			'currency' => 'USD'	
		];
		$client = new Client([
            'headers' => [ 
                'API' => "getGames",
                'DataType' => "JSON"
            ]
        ]);
		$guzzle_response = $client->post($api,['body' => json_encode($requesttosend)]);
		$client_response = json_decode($guzzle_response->getBody()->getContents());
		return json_encode($client_response);
	
	}
	
	public function index(Request $request){
		// if(!$request->header('API')){
		// 	$response = [
		// 		"msg" => "Missing Parameters",
		// 		"code" => 105
		// 	];
		// 	Helper::saveLog('Spade error API', $this->provider_db_id,  '', $response);
		// 	return $response;
		// }
		$header = [
            'API' => $request->header('API'),
        ];
		$data = file_get_contents("php://input");
		$details = json_decode($data);
		Helper::saveLog('Spade '.$header['API'], $this->provider_db_id,  json_encode($details), $header);
		
		if($details->merchantCode != $this->merchantCode){
			$response = [
				"msg" => "Merchant Not Found",
				"code" => 10113
			];
			Helper::saveLog('Spade index error', $this->provider_db_id,  json_encode($details), $response);
			return $response;
		}

		if($header['API'] == 'authorize'){
			return $this->_authorize($details,$header);
		}elseif($header['API'] == 'getBalance'){
			return $this->_getBalance($details,$header);
		}elseif($header['API'] == 'transfer'){
			return $this->_transfer($details,$header);
		}

	}

	public function _authorize($details,$header){
		$acctId =  ProviderHelper::explodeUsername('_', $details->acctId);
		$client_details = Providerhelper::getClientDetails('player_id', $acctId);
		if ($client_details != null) {
			$response = [
				"acctInfo" => [
					"acctId" => $this->prefix.'_'.$acctId,
					"balance" => floatval(number_format((float)$client_details->balance, 2, '.', '')),
					"userName" => $this->prefix.$acctId,
					"currency" => $client_details->default_currency,
					"siteId" => $this->siteId
				],
				"merchantCode" => $this->merchantCode,
				"msg" => "success",
				"code" => 0,
				"serialNo" => $details->serialNo
			];
			Helper::saveLog('Spade '.$header['API'].' process', $this->provider_db_id, json_encode($details), $response);
			return $response;
		} else {
			$response = [
				"msg" => "Invalid Acct ID",
				"code" => 113
			];
			Helper::saveLog('Spade '.$header['API'].' _authorize error', $this->provider_db_id,  json_encode($details), $response);
			return $response;
		}
		
	}

	public function _getBalance($details,$header){
		$acctId =  ProviderHelper::explodeUsername('_', $details->acctId);
		$client_details = Providerhelper::getClientDetails('player_id', $acctId);
		if ($client_details != null) {
			$response = [
				"acctInfo" => [
					"acctId" => $this->prefix.'_'.$acctId,
					"balance" => floatval(number_format((float)$client_details->balance, 2, '.', '')),
					"userName" => $this->prefix.$acctId,
					"currency" => $client_details->default_currency,
				],
				"merchantCode" => $this->merchantCode,
				"msg" => "success",
				"code" => 0,
				"serialNo" => $details->serialNo
			];
			Helper::saveLog('Spade '.$header['API'].' process', $this->provider_db_id, json_encode($details), $response);
			return $response;
		} else {
			$response = [
				"msg" => "Invalid Acct ID",
				"code" => 113
			];
			Helper::saveLog('Spade '.$header['API'].' error', $this->provider_db_id,  json_encode($details), $response);
			return $response;
		}
    	
	}

	public function _transfer($details,$header){
		if($details->type == 1){
			return $this->_placeBet($details,$header);
		}else if($details->type == 2){
			return $this->_cancelBet($details,$header);
		}else if($details->type == 7){
			return $this->_bonus($details,$header);
		}else if($details->type == 4){
			return $this->_payout($details,$header);
		}
	}

	public function _placeBet($details,$header){
		$acctId =  ProviderHelper::explodeUsername('_', $details->acctId);
		$client_details = Providerhelper::getClientDetails('player_id', $acctId);

		if ($client_details == null) {
			$response = [
				"msg" => "Invalid Acct ID",
				"code" => 113
			];
			Helper::saveLog('Spade '.$header['API'].'bet error', $this->provider_db_id,  json_encode($details), $response);
			return $response;
		}

		try{
	 		ProviderHelper::idenpotencyTable($this->prefix.'_'.$details->transferId);
		}catch(\Exception $e){
		 	$response = [
				"msg" => "Acct Exist",
				"code" => 50099
			];
			Helper::saveLog('Spade '.$header['API'].' bet error', $this->provider_db_id,  json_encode($details), $response);
			return $response;
		}

		$game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $details->gameCode);

		try{
			//Initialize
			$pay_amount = 0;
			$income = 0;
			$method = 1;
			$win_or_lost = 5; // 0 lost,  5 processing
			$payout_reason = 'Bet';
			$provider_trans_id = $details->transferId;
			$bet_id = $details->serialNo;
			//Create GameTransaction, GameExtension
			$game_trans_id  = ProviderHelper::createGameTransaction($client_details->token_id, $game_details->game_id, $details->amount,  $pay_amount, $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id, $bet_id);
			$game_trans_ext_id = $this->createGameTransExt($game_trans_id,$provider_trans_id, $bet_id, $details->amount, 1, $details, $data_response = null, $requesttosend = null, $client_response = null, $data_response = null);
			
			//requesttosend, and responsetoclient client side
			
			try {
				$type = "debit";
				$rollback = "false";
				$client_response = ClientRequestHelper::fundTransfer($client_details,$details->amount,$game_details->game_code,$game_details->game_name,$game_trans_ext_id,$game_trans_id,$type,$rollback);
				$save_bal = DB::table("player_session_tokens")->where("token_id","=",$client_details->token_id)->update(["balance" => $client_response->fundtransferresponse->balance]);
	        } catch (\Exception $e) {
	            $response = [
					"msg" => "System Error",
					"code" => 1
				];
				ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, $details, $response, 'FAILED', $e->getMessage(), $response, 'FAILED');
				ProviderHelper::updateGameTransactionStatus($game_trans_id, 2, 99);
			    return $response;
	        }

	        if (isset($client_response->fundtransferresponse->status->code)) {
	        	switch ($client_response->fundtransferresponse->status->code) {
					case "200":
						$num = $client_response->fundtransferresponse->balance;
						$response = [
							"transferId" => (string)$game_trans_ext_id,
							"merchantCode" => $this->merchantCode,
							"acctId" => $details->acctId,
							"balance" => floatval(number_format((float)$num, 2, '.', '')),
							"msg" => "success",
							"code" => 0,
							"serialNo" => $details->serialNo,
						];

						$this->updateGameTransactionExt($game_trans_ext_id,$client_response->requestoclient,$response,$client_response->fundtransferresponse);
						break;
					
					case "402":
						$response = [
							"msg" => "BET INSUFFICIENT BALANCE",
							"code" => 11101
						];
	          			$this->updateGameTransactionExt($game_trans_ext_id,$client_response->requestoclient,$response,$client_response->fundtransferresponse);
	          			ProviderHelper::updateGameTransactionStatus($game_trans_id, 2, 6);
						break;
				}
	        }
		    return $response;
		}catch(\Exception $e){
			$response = [
				"msg" => "System Error",
				"code" => 1
			];
			Helper::saveLog('Spade '.$header['API'].' bet error = '.$e->getMessage(), $this->provider_db_id,  json_encode($details), $response);
			return $response;
		}		
	}

	public function _payout($details,$header){
		$acctId =  ProviderHelper::explodeUsername('_', $details->acctId);
		$client_details = Providerhelper::getClientDetails('player_id', $acctId);
		$game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $details->gameCode);

		if ($client_details == null) {
			$response = [
				"msg" => "Invalid Acct ID",
				"code" => 113
			];
			return $response;
		}

		try{
	 		ProviderHelper::idenpotencyTable($this->prefix.'_'.$details->transferId);
		}catch(\Exception $e){
			if (!isset($details->specialGame->type)) {
				$response = [
					"msg" => "Acct Exist",
					"code" => 50099
				];
				return $response;
			} 
		 	
		}

		//CHECKING if BET EXISTING game_transaction_ext IF FALSE no bet record
		if (isset($details->specialGame->type) && $details->specialGame->type == "Free") {
			$bet_transaction = $this->findGameTransactionExstingBet($details->referenceId, 'transaction_id');

		} else {
			$bet_transaction = ProviderHelper::findGameTransaction($details->referenceId, 'transaction_id',1);
			
		}
		if($bet_transaction == 'false'){
			$response = [
				"msg" => "Invalid Parameters",
				"code" => 106
			];
			return $response;
		}

		try{
			//get details on game_transaction
			// $bet_transaction = ProviderHelper::findGameTransaction($existing_bet->game_trans_id, 'game_transaction');
			$round_id = $bet_transaction->game_trans_id;

			$num = $client_details->balance + $details->amount;
			ProviderHelper::_insertOrUpdate($client_details->token_id, $num); 
			//temporary
			$response = [
				"transferId" => 'TEM_11111',
				"merchantCode" => $this->merchantCode,
				"acctId" => $details->acctId,
				"balance" => floatval(number_format((float)$num, 2, '.', '')),
				"msg" => "success",
				"code" => 0,
				"serialNo" => $details->serialNo
			];

			$game_trans_ext_id = $this->createGameTransExt($bet_transaction->game_trans_id,$details->transferId, $details->referenceId, $details->amount, 2, $details, $response, $requesttosend = null, $client_response = null, $data_response = null);

			$response["transferId"] = (string)$game_trans_ext_id;
			$total_payamount_win = $bet_transaction->pay_amount + $details->amount;
			//Initialize data to pass
			$win = $total_payamount_win > 0  ?  1 : 0;  /// 1win 0lost
			$type = $total_payamount_win > 0  ? "credit" : "debit";
			$request_data = [
				'win' => 5,
				'amount' => $total_payamount_win,
				'payout_reason' => $this->updateReason(1),
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
	                "provider_trans_id" => $details->referenceId,
	                "round_id" => $details->transferId,
	                "amount" => $details->amount
	            ],
	            "provider_request" => $details,
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
	            return $response;
			} catch (\Exception $e) {
	            return $response;
			}
		}catch(\Exception $e){
			$response = [
				"msg" => "System Error",
				"code" => 1
			];
			return $response;
		}
	}

	public function _cancelbet($details,$header){
		$acctId =  ProviderHelper::explodeUsername('_', $details->acctId);
		$client_details = Providerhelper::getClientDetails('player_id', $acctId);
		$game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $details->gameCode);
		
		if ($client_details == null) {
			$response = [
				"msg" => "Invalid Acct ID",
				"code" => 113
			];
			return $response;
		}

		try{
	 		ProviderHelper::idenpotencyTable($this->prefix.'_'.$details->transferId);
		}catch(\Exception $e){
			if (!isset($details->specialGame->type)) {
				$response = [
					"msg" => "Acct Exist",
					"code" => 50099
				];
				return $response;
			} 
		 	
		}

		//CHECKING if BET EXISTING game_transaction_ext IF FALSE no bet record
		$bet_transaction = ProviderHelper::findGameTransaction($details->referenceId, 'transaction_id',1);
			
		if($bet_transaction == 'false'){
			$response = [
				"msg" => "Reference No Not found",
				"code" => 109
			];
			return $response;
		}

		try{
			//get details on game_transaction
			// $bet_transaction = ProviderHelper::findGameTransaction($existing_bet->game_trans_id, 'game_transaction');
			$round_id = $bet_transaction->game_trans_id;

			$num = $client_details->balance + $details->amount;
			ProviderHelper::_insertOrUpdate($client_details->token_id, $num); 
			//temporary
			$response = [
				"transferId" => 'TEM_11111',
				"merchantCode" => $this->merchantCode,
				"acctId" => $details->acctId,
				"balance" => floatval(number_format((float)$num, 2, '.', '')),
				"msg" => "success",
				"code" => 0,
				"serialNo" => $details->serialNo
			];

			$game_trans_ext_id = $this->createGameTransExt($bet_transaction->game_trans_id,$details->transferId, $details->referenceId, $details->amount, 3, $details, $response, $requesttosend = null, $client_response = null, $data_response = null);

			$response["transferId"] = (string)$game_trans_ext_id;
			$total_payamount_win = $bet_transaction->pay_amount + $details->amount;
			//Initialize data to pass
			$win = 4;  /// 1win 0lost
			$type = "credit";
			$request_data = [
				'win' => 5,
				'amount' => $total_payamount_win,
				'payout_reason' => $this->updateReason(4),
			];
			//update transaction
			Helper::updateGameTransaction($bet_transaction,$request_data,$type);

			$body_details = [
	            "type" => "credit",
	            "win" => $win,
	            "token" => $client_details->player_token,
	            "rollback" => true,
	            "game_details" => [
	                "game_id" => $game_details->game_id
	            ],
	            "game_transaction" => [
	                "provider_trans_id" => $details->referenceId,
	                "round_id" => $details->transferId,
	                "amount" => $details->amount
	            ],
	            "provider_request" => $details,
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
	            return $response;
			} catch (\Exception $e) {
	            return $response;
			}
		}catch(\Exception $e){
			$response = [
				"msg" => "System Error",
				"code" => 1
			];
			return $response;
		}
	}
	public function _bonus($details,$header){
		return '_bonus';
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
		);
		DB::table('game_transaction_ext')->where("game_trans_ext_id",$gametransextid)->update($gametransactionext);
	}

	public  function updateReason($win) {
        $win_type = [
        "1" => 'Transaction updated to win',
        "2" => 'Transaction updated to bet',
        "3" => 'Transaction updated to Draw',
        "4" => 'Transaction updated to Refund',
        "5" => 'Transaction updated to Processing',
        ];
        if(array_key_exists($win, $win_type)){
            return $win_type[$win];
        }else{
            return 'Transaction Was Updated!';
        }   
    }

    public static  function findGameTransactionExstingBet($identifier, $type) {

    	if ($type == 'transaction_id') {
		 	$where = 'where gt.provider_trans_id = "'.$identifier.'" ';
		}
		if ($type == 'game_transaction') {
		 	$where = 'where gt.game_trans_id = "'.$identifier.'"';
		}
		if ($type == 'round_id') {
			$where = 'where gt.round_id = "'.$identifier.'" ';
		}
	 	
	 	$filter = 'LIMIT 1';
    	$query = DB::select('select gt.game_trans_id, gt.provider_trans_id, gt.game_id, gt.round_id, gt.bet_amount,gt.win, gt.pay_amount, gt.entry_id, gt.income from game_transactions gt '.$where.' '.$filter.'');
    	$client_details = count($query);
		return $client_details > 0 ? $query[0] : 'false';
    }
}
