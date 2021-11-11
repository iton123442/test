<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Helpers\ClientRequestHelper;
use App\Helpers\AWSHelper;
use App\Helpers\GameLobby;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use App\Models\GameTransactionMDB;
use Carbon\Carbon;
use DB;

/**
 * @author's note : Provider has feature Front End and API Blocking IP
 * @author's note : There are two kinds of method in here Single Wallet Callback and Backoffice Calls
 * @author's note : Backoffice call for directly communicate to the Provider Backoffice!
 * @author's note : Single Wallet call is the main methods tobe checked!
 * @author's note : Username/Player is Prefixed with the merchant_id_TG(player_id)
 * @method  [playerRegister] Register The Player to AWS Provider (DEPRECATED CENTRALIZED) (BO USED)
 * @method  [launchGame] Request Gamelaunch (DEPRECATED CENTRALIZED) (BO USED)
 * @method  [gameList] List Of All Gamelist (DEPRECATED CENTRALIZED)
 * @method  [playerManage] Disable/Enable a player in AWS Backoffice
 * @method  [playerStatus] Check Player Status in AWS Backoffice (BO USED)
 * @method  [playerBalance] Check Player Balance in AWS Backoffice (BO USED)
 * @method  [fundTransfer] Transfer fund to Player in AWS Backoffice (BO USED) (NOT USED)
 * @method  [queryStatus] Check the fund stats of a Player in AWS Backoffice (BO USED) (NOT USED)
 * @method  [queryOrder] Check the order stats in AWS Backoffice (BO USED) (NOT USED)
 * @method  [playerLogout] Logout the player
 * @method  [singleBalance] Single Wallet : Check Player Balance
 * @method  [singleFundTransfer] Single Fund Transfer : Transfer fund Debit/Credit
 * @method  [singleFundQuery] Check Fund
 *
 * 01_User Registration, 02_User Enablement, 03_User Status, 07_Launch Game 26, 08_User Kick-out (BO METHOD WE ONLY NEED)
 */
class AWSController extends Controller
{

	public $api_url, $merchant_id, $merchant_key = '';
	public $provider_db_id = 21;
	public $prefix = 'AWS';

	public function __construct()
	{
		$this->api_url = config('providerlinks.aws.api_url');
		$this->merchant_id = config('providerlinks.aws.merchant_id');
		$this->merchant_key = config('providerlinks.aws.merchant_key');
	}

	/**
	 * SINGLE WALLET
	 * @author's Signature combination for every callback
	 * @param [obj] $[details] [<json to obj>]
	 * @param [int] $[signature_type] [<1 = balance, 2 = fundtransfer, fundquery>] // SIGNATURE TYPE 2 REMOVED
	 *
	 */
	// public function signatureCheck($details, $signature_type){
	// 	if($signature_type == 1){
	// 		$signature = md5($this->merchant_id.$details->currentTime.$details->accountId.$details->currency.base64_encode($this->merchant_key));
	// 	}

	// 	if($signature == $details->sign){
	// 		return true;
	// 	}else{
	// 		return false;
	// 	}
	// }

	/**
	 * SINGLE WALLET
	 * @author's note : Player Single Balance 
	 *
	 */
	public function singleBalance(Request $request)
	{
		$data = file_get_contents("php://input");
		$details = json_decode($data);
		AWSHelper::saveLog('AWS singleBalance - HIT 1', $this->provider_db_id, $data, $details);
		$prefixed_username = explode("_TG", $details->accountId);
		$client_details = AWSHelper::getClientDetails('player_id', $prefixed_username[1]);
		if ($client_details == 'false') {
			$response = [
				"msg" => "Player Not Found - Client Failed To Respond",
				"code" => 100,
			];
			AWSHelper::saveLog('AWS singleBalance - Hit 2', $this->provider_db_id, $data, $response);
			return $response;
		}

		if (!AWSHelper::findMerchantIdByClientId($client_details->client_id)) {
			$response = [
				"msg" => "Player Not Found - Client Failed To Respond",
				"code" => 100,
			];
			AWSHelper::saveLog('AWS singleBalance - Client ID NOT FOUND', $this->provider_db_id, $data, $response);
			return $response;
		}

		// AWSHelper::saveLog('AWS singleBalance - CURRENCY CHECK', $this->provider_db_id, $data, 'CHECK');
		$merchant_id = AWSHelper::findMerchantIdByClientId($client_details->client_id)['merchant_id'];
		$merchant_key = AWSHelper::findMerchantIdByClientId($client_details->client_id)['merchant_key'];

		$signature = md5($merchant_id . $details->currentTime . $details->accountId . $details->currency . base64_encode($merchant_key));

		if ($signature != $details->sign) {
			$response = [
				"msg" => "Sign check encountered error, please verify sign is correct",
				"code" => 9200
			];
			AWSHelper::saveLog('AWS Single Error Sign', $this->provider_db_id, $data, $response);
			return $response;
		}
		AWSHelper::saveLog('AWS singleBalance - KEY CHECK', $this->provider_db_id, $data, 'CHECK DONE');

		// AWSHelper::saveLog('AWS singleBalance - CURRENCY CHECK', $this->provider_db_id, $data, 'CHECK');
		$provider_reg_currency = AWSHelper::getProviderCurrency($this->provider_db_id, $client_details->default_currency);
		if ($provider_reg_currency == 'false') {
			$response = [
				"msg" => "Currency not found",
				"code" => 102
			];
			AWSHelper::saveLog('AWS Single Currency Not Found', $this->provider_db_id, $data, $response);
			return $response;
		}
		// AWSHelper::saveLog('AWS singleBalance - CURRENCY CHECK', $this->provider_db_id, $data, 'CHECK DONE');

		// $player_details = AWSHelper::playerDetailsCall($client_details->player_token);
		AWSHelper::saveLog('AWS singleBalance - playerDetailsCall', $this->provider_db_id, $data, 'CHECK');
		// $player_details = AWSHelper::playerDetailsCall($client_details);
		// if($player_details != 'false'){
		$response = [
			"msg" => "success",
			"code" => 0,
			"data" => [
				"currency" => $client_details->default_currency,
				// "balance"=> floatval(number_format((float)$player_details->playerdetailsresponse->balance, 2, '.', '')),
				"balance" => floatval(number_format((float) $client_details->balance, 2, '.', '')),
				"bonusBalance" => 0
			]
		];
		// }else{
		// 	$response = [
		// 		"msg"=> "User balance retrieval error",
		// 		"code"=> 2211,
		// 		"data"=> []
		// 	];
		// }
		AWSHelper::saveLog('AWS singleBalance - SUCCESS', $this->provider_db_id, $data, $response);
		return $response;
	}


	public function checkPlayOneWay($data, $client_details){
		$details = json_decode($data);

		// $explode1 = explode('"betAmount":', $data);
		// $explode2 = explode('amount":', $explode1[0]);
		// $amount_in_string = trim(str_replace(',', '', $explode2[1]));
		// $amount_in_string = trim(str_replace('"', '', $amount_in_string));

		// $merchant_id = AWSHelper::findMerchantIdByClientId($client_details->client_id)['merchant_id'];
		// $merchant_key = AWSHelper::findMerchantIdByClientId($client_details->client_id)['merchant_key'];

		// $signature = md5($merchant_id . $details->currentTime . $amount_in_string . $details->accountId . $details->currency . $details->txnId . $details->txnTypeId . $details->gameId . base64_encode($merchant_key));
		// if ($signature != $details->sign) {
		// 	$response = [
		// 		"msg" => "Sign check encountered error, please verify sign is correct",
		// 		"code" => 9200
		// 	];
		// 	AWSHelper::saveLog('AWS Single Error Sign', $this->provider_db_id, $data, $response);
		// 	return $response;
		// }

		$provider_reg_currency = Providerhelper::getProviderCurrency($this->provider_db_id, $client_details->default_currency);
		if ($provider_reg_currency == 'false') {
			$response = [
				"msg" => "Currency not found",
				"code" => 102
			];
			AWSHelper::saveLog('AWS singleFundTransfer - Currency Not Found', $this->provider_db_id, $data, $response);
			return $response;
		}

		$game_details = AWSHelper::findGameDetails('game_code', $this->provider_db_id, $details->gameId);
		if ($game_details == null) {
			$response = [
				"msg" => "Game not found",
				"code" => 1100
			];
			AWSHelper::saveLog('AWS singleFundTransfer - Game Not Found', $this->provider_db_id, $data, $response);
			return $response;
		}

		$transaction_type = $details->winAmount > 0 ? 'credit' : 'debit';
		$game_transaction_type = $transaction_type == 'debit' ? 1 : 2; // 1 Bet, 2 Win

		$game_code = $game_details->game_id;
		$token_id = $client_details->token_id;
		$bet_amount = abs($details->betAmount);
		$win_amount = abs($details->winAmount);

		$restricted_player = ProviderHelper::checkGameRestricted($game_details->game_id, $client_details->player_id);
		if ($restricted_player) {
			$response = ["msg" => "Fund transfer encountered error - Player Restricted", "code" => 2205, "data" => []];
			return $response;
		}

		$method = 1;
		$pay_amount = $win_amount; // payamount zero
		$income = $bet_amount - $pay_amount;

		if($pay_amount > 0){
			$win_type = 1;
		}else{
			$win_type = 0;
		}

		$win_or_lost = $win_type; // 0 lost,  5 processing
		$payout_reason = AWSHelper::getOperationType($details->txnTypeId);
		$provider_trans_id = $details->txnId;

		// try {
		// 	ProviderHelper::idenpotencyTable($this->prefix . '_' . $details->txnId);
		// } catch (\Exception $e) {
		// 	$response = [
		// 		"msg" => "marchantTransId already exist",
		// 		"code" => 2200,
		// 		"data" => [
		// 			"currency" => $client_details->default_currency,
		// 			"balance" => floatval(number_format((float) $client_details->balance, 2, '.', '')),
		// 			"bonusBalance" => 0
		// 		]
		// 	];
		// 	AWSHelper::saveLog('AWS singleFundTransfer - Order Already Exist', $this->provider_db_id, $details, $e->getMessage() . ' ' . $e->getLine());
		// 	return $response;
		// }

		if ($transaction_type == 'debit') {
			if ($bet_amount > $client_details->balance) {
				$response = [
					"msg" => "Insufficient balance",
					"code" => 1201
				];
				AWSHelper::saveLog('AWS singleFundTransfer - Insufficient Balance', $this->provider_db_id, $details, $response);
				return $response;
			}
		}
		
		$bet_amount_2way = abs($details->betAmount);
		$win_amount_2way = abs($details->winAmount);

		if ($bet_amount_2way == 0) {
			$is_freespin = true;
		} else {
			$is_freespin = false;
		}

		$gamerecord  = AWSHelper::createGameTransaction($token_id, $game_code, $bet_amount,  $pay_amount, $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id, $provider_trans_id);
		$game_transextension = AWSHelper::createGameTransExtV2($gamerecord, $provider_trans_id, $provider_trans_id, $bet_amount_2way, 1, $details);
		$client_response = AWSHelper::fundTransferAll($client_details,$bet_amount_2way,$win_amount_2way,$game_details->game_code,$game_details->game_name,$game_transextension,$gamerecord);
		if (isset($client_response->fundtransferresponse->status->code)
		&& $client_response->fundtransferresponse->status->code == "200"){
			$new_balance = $client_details->balance - $bet_amount_2way;
			$new_balance = $new_balance + $win_amount_2way;
			ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
			$response = [
				"msg" => "success",
				"code" => 0,
				"data" => [
					"currency" => $client_details->default_currency,
					"amount" => (float) $details->amount,
					"accountId" => $details->accountId,
					"txnId" => $details->txnId,
					"eventTime" => date('Y-m-d H:i:s'),
					"balance" => floatval(number_format((float) $client_response->fundtransferresponse->balance, 2, '.', '')),
					"bonusBalance" => 0
				]
			];
			AWSHelper::updatecreateGameTransExt($game_transextension, $details, $response, $client_response->requestoclient, $client_response, $client_response);
			$game_transextension = AWSHelper::createGameTransExtV2($gamerecord,$provider_trans_id, $provider_trans_id, $win_amount_2way, 2,$details,$response,$client_response->requestoclient, $client_response, 'SUCCESS', 'SUCCESS');
		} elseif (isset($client_response->fundtransferresponse->status->code)
			&& $client_response->fundtransferresponse->status->code == "402"){
			AWSHelper::updateGameTransactionStatus($gamerecord, 2, 6);
			$response = [
				"msg" => "Insufficient balance",
				"code" => 1201
			];
		}
		return $response;
	}

	/**
	 * SINGLE WALLET
	 * @author's note : Player Single Fund Transfer 
	 * @author's note : Transfer amount, support 2 decimal places, negative number is withdraw/debit, positive number is deposit/credit
	 *
	 */
	public function singleFundTransfer(Request $request)
	{

		$data = file_get_contents("php://input");
		$details = json_decode($data);
		AWSHelper::saveLog('AWS singleFundTransfer - HIT 1', $this->provider_db_id, file_get_contents("php://input"), Helper::datesent());
		$prefixed_username = explode("_TG", $details->accountId);

		// AWSHelper::saveLog('AWS singleFundTransfer - getClientDetails CHECK', $this->provider_db_id, $data, 'CHECK');
		$client_details = AWSHelper::getClientDetails('player_id', $prefixed_username[1]);
		if ($client_details == 'false') {
			$response = [
				"msg" => "Player Not Found - Client Failed To Respond",
				"code" => 100,
			];
			AWSHelper::saveLog('AWS singleFundTransfer - client_details not found', $this->provider_db_id, $data, $response);
			return $response;
		}

		$signature_amount = json_decode($data);
		$amount_in_string = (string)$signature_amount->amount;

		$merchant_id = AWSHelper::findMerchantIdByClientId($client_details->client_id)['merchant_id'];
		$merchant_key = AWSHelper::findMerchantIdByClientId($client_details->client_id)['merchant_key'];
		
		// $signature = md5($merchant_id . $details->currentTime . $amount_in_string . $details->accountId . $details->currency . $details->txnId . $details->txnTypeId . $details->gameId . base64_encode($merchant_key));
		// if ($signature != $details->sign) {
		// 	$response = [
		// 		"msg" => "Sign check encountered error, please verify sign is correct",
		// 		"code" => 9200
		// 	];
		// 	AWSHelper::saveLog('AWS Single Error Sign', $this->provider_db_id, $data, $response);
		// 	return $response;
		// }
		// # 01 END

		// if($client_details->api_version == 2){
		// 	return $this->checkPlayOneWay($request->getContent(), $client_details);
		// }

		// $provider_reg_currency = Providerhelper::getProviderCurrency($this->provider_db_id, $client_details->default_currency);
		// if ($provider_reg_currency == 'false') {
		// 	$response = [
		// 		"msg" => "Currency not found",
		// 		"code" => 102
		// 	];
		// 	AWSHelper::saveLog('AWS singleFundTransfer - Currency Not Found', $this->provider_db_id, $data, $response);
		// 	return $response;
		// }

		$game_details = AWSHelper::findGameDetails('game_code', $this->provider_db_id, $details->gameId);
		if ($game_details == null) {
			$response = [
				"msg" => "Game not found",
				"code" => 1100
			];
			AWSHelper::saveLog('AWS singleFundTransfer - Game Not Found', $this->provider_db_id, $data, $response);
			return $response;
		}

		$transaction_type = $details->winAmount > 0 ? 'credit' : 'debit';
		$game_transaction_type = $transaction_type == 'debit' ? 1 : 2; // 1 Bet, 2 Win

		$game_code = $game_details->game_id;
		$token_id = $client_details->token_id;
		$bet_amount = abs($details->betAmount);


		# Check Game Restricted
		$restricted_player = ProviderHelper::checkGameRestricted($game_details->game_id, $client_details->player_id);
		if ($restricted_player) {
			$response = ["msg" => "Fund transfer encountered error - Player Restricted", "code" => 2205, "data" => []];
			return $response;
		}
		

		$method = 1;
		$pay_amount = 0; // payamount zero
		$income = $bet_amount - $pay_amount;
		$win_type = 5;

		$win_or_lost = $win_type; // 0 lost,  5 processing
		$payout_reason = AWSHelper::getOperationType($details->txnTypeId);
		$provider_trans_id = $details->txnId;

		# Insert Idenpotent
		// try {
		// 	ProviderHelper::idenpotencyTable($this->prefix . '_' . $details->txnId);
		// } catch (\Exception $e) {
		// 	$response = [
		// 		"msg" => "marchantTransId already exist",
		// 		"code" => 2200,
		// 		"data" => [
		// 			"currency" => $client_details->default_currency,
		// 			// "balance"=> floatval(number_format((float)$player_details->playerdetailsresponse->balance, 2, '.', '')),
		// 			"balance" => floatval(number_format((float) $client_details->balance, 2, '.', '')),
		// 			"bonusBalance" => 0
		// 		]
		// 	];
		// 	AWSHelper::saveLog('AWS singleFundTransfer - Order Already Exist', $this->provider_db_id, $data, $e->getMessage() . ' ' . $e->getLine());
		// 	return $response;
		// }

		AWSHelper::saveLog('AWS singleFundTransfer - findGameExt CHECK', $this->provider_db_id, $data, 'CHECK');
		// $game_ext_check = AWSHelper::findGameExt($details->txnId, $game_transaction_type, 'transaction_id');
		$game_ext_check = GameTransactionMDB::findGameExt($details->txnId, 2,'transaction_id', $client_details);
		if($game_ext_check != 'false'){
			$response = [
			"msg"=> "marchantTransId already exist",
			"code"=> 2200,
			"data"=> [
					"currency"=> $client_details->default_currency,
					// "balance"=> floatval(number_format((float)$player_details->playerdetailsresponse->balance, 2, '.', '')),
					"balance"=> floatval(number_format((float)$client_details->balance, 2, '.', '')),
					"bonusBalance"=> 0
				]
			];
			AWSHelper::saveLog('AWS singleFundTransfer - Order Already Exist', $this->provider_db_id, $data, $response);
			return $response;
		}

		if ($transaction_type == 'debit') {
			// if($bet_amount > $player_details->playerdetailsresponse->balance){
			if ($bet_amount > $client_details->balance) {
				$response = [
					"msg" => "Insufficient balance",
					"code" => 1201
				];
				AWSHelper::saveLog('AWS singleFundTransfer - Insufficient Balance', $this->provider_db_id, $data, $response);
				return $response;
			}
		}

		
		try {
			$flow_status = 0;
			$bet_amount_2way = abs($details->betAmount);
			$win_amount_2way = abs($details->winAmount);
			if ($bet_amount_2way == 0) {
				$is_freespin = true;
			} else {
				$is_freespin = false;
			}
			if(AWSHelper::isNegativeBalance($win_amount_2way, $client_details)){
				if($is_freespin != true){
					$response = ["msg" => "Insufficient balance","code" => 1201];
					return $response;
				}
			}
			
			// $gamerecord  = ProviderHelper::createGameTransaction($token_id, $game_code, $bet_amount,  $pay_amount, $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id, $provider_trans_id);
			// $game_transextension1 = AWSHelper::createGameTransExtV2($gamerecord, $provider_trans_id, $provider_trans_id, $bet_amount_2way, 1);

			$gameTransactionData = array(
				"provider_trans_id" => $provider_trans_id,
				"token_id" => $token_id,
				"game_id" => $game_code,
				"round_id" => $provider_trans_id,
				"bet_amount" => $bet_amount,
				"win" => $win_or_lost,
				"pay_amount" => $pay_amount,
				"income" =>  $income,
				"entry_id" =>$method,
			);
			$gamerecord = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
			$gameTransactionEXTData = array(
				"game_trans_id" => $gamerecord,
				"provider_trans_id" => $provider_trans_id,
				"round_id" => $provider_trans_id,
				"amount" => $bet_amount_2way,
				"game_transaction_type"=> 1,
				"provider_request" =>json_encode($details),
			);
			$game_transextension1 = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

			try {
				$fund_extra_data = [
					'fundtransferrequest' => [
						'fundinfo' => [
							'freespin' => $is_freespin,
						]
					],
					'provider_name' => $game_details->provider_name
				];
				$client_response = ClientRequestHelper::fundTransfer($client_details, abs($bet_amount_2way), $game_details->game_code, $game_details->game_name, $game_transextension1, $gamerecord, 'debit', false, $fund_extra_data);
			} catch (\Exception $e) {
				// return $e->getMessage().' '.$e->getLine().' '.$e->getFile();
				$response = ["msg" => "Fund transfer encountered error", "code" => 2205, "data" => []];
				if (isset($gamerecord)) {
					// AWSHelper::updateGameTransactionStatus($gamerecord, 2, 99);
					// AWSHelper::updatecreateGameTransExt($game_transextension1, 'FAILED', $response, 'FAILED', $e->getMessage() . ' ' . $e->getLine(), false, 'FAILED');
					
					$updateGameTransaction = ["win" => 2];
					GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
					$updateTransactionEXt = array(
						"mw_response" => json_encode($response),
						'mw_request' => isset($client_response->requestoclient) ? json_encode($client_response->requestoclient) : 'FAILED',
						'client_response' => json_encode($e->getMessage().' '.$e->getLine().' '.$e->getFile()),
						'transaction_detail' => json_encode($response),
					);
					GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension1,$client_details);
				}
				AWSHelper::saveLog('AWS singleFundTransfer - FATAL ERROR', $this->provider_db_id, json_encode($response), $e->getMessage() . ' ' . $e->getLine());
				return $response;
			}

			if (isset($client_response->fundtransferresponse->status->code)
				&& $client_response->fundtransferresponse->status->code == "200") {
				// ProviderHelper::updateGameTransactionFlowStatus($gamerecord, 2);
				// AWSHelper::updatecreateGameTransExt($game_transextension1, $details, $client_response, $client_response->requestoclient, $client_response, $client_response);
				
				# $game_transextension2 = AWSHelper::createGameTransExtV2($gamerecord,$provider_trans_id, $provider_trans_id, $win_amount_2way, 2);
				try {
					$new_balance = $client_details->balance - $bet_amount_2way;
					$new_balance = $new_balance + $win_amount_2way;
					ProviderHelper::_insertOrUpdate($client_details->token_id, $new_balance);

					$response = [
						"msg" => "success",
						"code" => 0,
						"data" => [
							"currency" => $client_details->default_currency,
							"amount" => (float) $details->amount,
							"accountId" => $details->accountId,
							"txnId" => $details->txnId,
							"eventTime" => date('Y-m-d H:i:s'),
							"balance" => floatval(number_format((float) $new_balance, 2, '.', '')),
							"bonusBalance" => 0
						]
					];

					if ($transaction_type == 'credit') {
						$method = 2;
						$pay_amount =  $details->winAmount;
						$win_type = 1;
						$income = $bet_amount - $pay_amount;
					} else {
						$method = 1;
						$pay_amount = $details->winAmount; // payamount zero
						$income = $bet_amount - $pay_amount;
						$win_type = 0;
					}

					// ProviderHelper::updateGameTransactionFlowStatus($gamerecord, 2);
					// ProviderHelper::updateGameTransaction($gamerecord, $pay_amount, $income,  $win_type, $method);
					$updateGameTransaction = [
						"pay_amount" => $pay_amount,
						"income" =>  $income,
						// "win" => $win_type,
						"entry_id" => $method,
					];
					GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
					$gameTransactionCRIDETEXTData = array(
						"game_trans_id" => $gamerecord,
						"provider_trans_id" => $provider_trans_id,
						"round_id" => $provider_trans_id,
						"amount" => $pay_amount,
						"game_transaction_type"=> 2,
						"provider_request" =>json_encode($details),
					);
					$game_transextension2 = GameTransactionMDB::createGameTransactionExt($gameTransactionCRIDETEXTData,$client_details);

					$action_payload = [
						"type" => "custom", #genreral,custom :D # REQUIRED!
						"custom" => [
							"game_transaction_ext_id" => $game_transextension2,
							"client_connection_name" => $client_details->connection_name,
							"provider" => 'allwayspin',
							'pay_amount' => $pay_amount,
							'income' => $income,
							'win_or_lost' => $win_type,
							'entry_id' => $method
						],
						"provider" => [
							"provider_request" => $details, #R
							"provider_trans_id" => $provider_trans_id, #R
							"provider_round_id" => $provider_trans_id, #R
							"provider_name" => $game_details->provider_name
						],
						"mwapi" => [
							"roundId" => $gamerecord, #R
							"type" => 2, #R
							"game_id" => $game_details->game_id, #R
							"player_id" => $client_details->player_id, #R
							"mw_response" => $response, #R
						],
						'fundtransferrequest' => [
							'fundinfo' => [
								'freespin' => $is_freespin,
							]
						]
					];

					
					# $client_response2_requestBody = ProviderHelper::fundTransfer_requestBody($client_details,abs($win_amount_2way),$game_details->game_code,$game_details->game_name,$game_transextension2,$gamerecord,'credit');
					# $client_response2 = ClientRequestHelper::fundTransfer_TG($client_details,abs($win_amount_2way),$game_details->game_code,$game_details->game_name,$game_transextension2,$gamerecord,'credit',false,$action_payload);
					$client_response2 = ClientRequestHelper::fundTransfer_TG($client_details, abs($win_amount_2way), $game_details->game_code, $game_details->game_name, $gamerecord, 'credit', false, $action_payload);
				} catch (\Exception $e) {
					// return $e->getMessage() . ' ' . $e->getLine() . ' ' . $e->getFile();
					return $response;
				}

				if (isset($client_response2->fundtransferresponse->status->code)
					&& $client_response2->fundtransferresponse->status->code == "200") {
					$response = [
						"msg" => "success",
						"code" => 0,
						"data" => [
							"currency" => $client_details->default_currency,
							"amount" => (float) $details->amount,
							"accountId" => $details->accountId,
							"txnId" => $details->txnId,
							"eventTime" => date('Y-m-d H:i:s'),
							"balance" => floatval(number_format((float) $new_balance, 2, '.', '')),
							"bonusBalance" => 0
						]
					];

					$updateTransactionDEBITEXt = array("mw_response" => json_encode($response));
					GameTransactionMDB::updateGametransactionEXT($updateTransactionDEBITEXt,$game_transextension1,$client_details);

					# AWSHelper::updatecreateGameTransExt($game_transextension2, $details, $response, $client_response2->requestoclient, $client_response,$response);
				} elseif (isset($client_response2->fundtransferresponse->status->code)
					&& $client_response2->fundtransferresponse->status->code == "402") {
					// if (ProviderHelper::checkFundStatus($client_response->fundtransferresponse->status->status)) :
					// 	AWSHelper::updateGameTransactionStatus($gamerecord, 2, 6);
					// else :
					// 	AWSHelper::updateGameTransactionStatus($gamerecord, 2, 99);
					// endif;

					$updateGameTransaction = ["win" => 2];
					GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
					$response = [
						"msg" => "Insufficient balance",
						"code" => 1201
					];

					# Game Restrict (failed win)
					# Providerhelper::createRestrictGame($game_details->game_id,$client_details->player_id,$game_transextension2, 'FAILED');
				}
			} elseif (isset($client_response->fundtransferresponse->status->code)
				&& $client_response->fundtransferresponse->status->code == "402") {
				// if (ProviderHelper::checkFundStatus($client_response->fundtransferresponse->status->status)) :
				// 	AWSHelper::updateGameTransactionStatus($gamerecord, 2, 6);
				// else :
				// 	AWSHelper::updateGameTransactionStatus($gamerecord, 2, 99);
				// endif;

				$updateGameTransaction = ["win" => 2];
					GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
				$response = [
					"msg" => "Insufficient balance",
					"code" => 1201
				];
			}
			AWSHelper::saveLog('AWS singleFundTransfer SUCCESS = ' . $gamerecord, $this->provider_db_id, $data, $response);
			return $response;
		} catch (\Exception $e) {
			$response = ["msg" => "Fund transfer encountered error", "code" => 2205];
			AWSHelper::saveLog('AWS singleFundTransfer - FATAL ERROR', $this->provider_db_id, $data, $e->getMessage() . ' ' . $e->getLine());
			return $response;
		}
	}

	/**
	 * SINGLE WALLET
	 * @author's note : PROVDER NOTE Query is prepare for if have transfer error, we can call "Query" to merchant to  make sure merchant received or not
	 * this function just check order not increase or decrease action.  (Debit/Credit)
	 *
	 */
	public function singleFundQuery(Request $request)
	{
		$data = file_get_contents("php://input");
		$details = json_decode($data);
		AWSHelper::saveLog('AWS singleFundQuery EH', $this->provider_db_id, $data, Helper::datesent());

		$explode1 = explode('"betAmount":', $data);
		$explode2 = explode('amount":', $explode1[0]);
		$amount_in_string = trim(str_replace(',', '', $explode2[1]));
		$amount_in_string = trim(str_replace('"', '', $amount_in_string));

		// if($signature != $details->sign){
		// 	$response = [
		// 		"msg"=> "Sign check encountered error, please verify sign is correct",
		// 		"code"=> 9200
		// 	];
		// 	AWSHelper::saveLog('AWS singleFundQuery - Error Sign', $this->provider_db_id, $data, $response);
		// 	return $response;
		// }

		$prefixed_username = explode("_TG", $details->accountId);
		$client_details = AWSHelper::getClientDetails('player_id', $prefixed_username[1]);
		$player_details = AWSHelper::playerDetailsCall($client_details);
		// $player_details = AWSHelper::playerDetailsCall($client_details->player_token);

		if (!AWSHelper::findMerchantIdByClientId($client_details->client_id)) {
			$response = [
				"msg" => "Player Not Found - Client Failed To Respond",
				"code" => 100,
			];
			AWSHelper::saveLog('AWS singleFundQuery - Error Sign', $this->provider_db_id, $data, $response);
			return $response;
		}

		$merchant_id = AWSHelper::findMerchantIdByClientId($client_details->client_id)['merchant_id'];
		$merchant_key = AWSHelper::findMerchantIdByClientId($client_details->client_id)['merchant_key'];

		$signature = md5($merchant_id . $details->currentTime . $amount_in_string . $details->accountId . $details->currency . $details->txnId . $details->txnTypeId . $details->gameId . base64_encode($merchant_key));

		if ($player_details == 'false') {
			$response = [
				"msg" => "Fund transfer encountered error",
				"code" => 2205
			];
			AWSHelper::saveLog('AWS singleFundQuery - Error Sign', $this->provider_db_id, $data, $response);
			return $response;
		}

		$transaction_type = $details->amount > 0 ? 'credit' : 'debit';
		$game_transaction_type = $transaction_type == 'debit' ? 1 : 2; // 1 Bet, 2 Win
		$game_ext_check = AWSHelper::findGameExt($details->txnId, $game_transaction_type, 'transaction_id');
		// dd($game_ext_check);
		if ($game_ext_check != 'false') { // The Transaction Has Been Processed!
			$response = [
				"msg" => "success",
				"code" => 0,
				"data" => [
					"currency" => $client_details->default_currency,
					"amount" => (float) $details->amount,
					"accountId" => $details->accountId,
					"txnId" => $details->txnId,
					"eventTime" => date('Y-m-d H:i:s'),
					"balance" => floatval(number_format((float) $player_details->playerdetailsresponse->balance, 2, '.', '')),
					"bonusBalance" => 0
				]
			];
			return $response;
		} else {  // No Transaction Was Found 
			$response = [
				"msg" => "Transfer history record not found",
				"code" => 106,
				"data" => [
					"currency" => $client_details->default_currency,
					"amount" => (float) $details->amount,
					"accountId" => $details->accountId,
					"txnId" => $details->txnId,
					"eventTime" => date('Y-m-d H:i:s'),
					"balance" => floatval(number_format((float) $player_details->playerdetailsresponse->balance, 2, '.', '')),
					"bonusBalance" => 0
				]
			];
		}
		AWSHelper::saveLog('AWS singleFundQuery - SUCCESS', $this->provider_db_id, $data, $response);
		return $response;
	}

	/**
	 * MERCHANT BACKOFFICE
	 * @author's note : this is centralized in the gamelaunch (DEPRECATED/CENTRALIZED)
	 *
	 */
	// public function playerRegister(Request $request)
	// {
	//    $register_player = AWSHelper::playerRegister($request->token);
	//     // dd($register_player);
	//    // $register_player->code == 2217 || $register_player->code == 0;
	// }

	/**
	 * MERCHANT BACKOFFICE
	 * @author's NOTE : Launch Game (DEPRECATED/CENTRALIZED)
	 *
	 */
	// public function launchGame(Request $request){
	// 	$lang = GameLobby::getLanguage('All Way Spin','en');
	// 	$client_details = AWSHelper::getClientDetails('token', $request->token);
	// 	$client = new Client([
	// 	    'headers' => [ 
	// 	    	'Content-Type' => 'application/json',
	// 	    ]
	// 	]);
	// 	$requesttosend = [
	// 		"merchantId" => $this->merchant_id,
	// 		"currentTime" => AWSHelper::currentTimeMS(),
	// 		"username" => $this->merchant_id.'_TG'.$client_details->player_id,
	// 		"playmode" => 0, // Mode of gameplay, 0: official
	// 		"device" => 1, // Identifying the device. Device, 0: mobile device 1: webpage
	// 		"gameId" => 'AWS_1',
	// 		"language" => $lang,
	// 	];
	// 	$requesttosend['sign'] = AWSHelper::hashen($requesttosend);
	// 	$guzzle_response = $client->post($this->api_url.'/api/login',
	// 	    ['body' => json_encode($requesttosend)]
	// 	);
	//     $provider_response = json_decode($guzzle_response->getBody()->getContents());
	//     AWSHelper::saveLog('AWS BO Launch Game', 21, json_encode($requesttosend), $provider_response);
	//     return isset($provider_response->data->gameUrl) ? $provider_response->data->gameUrl : 'false';
	// }


	/**
	 * MERCHANT BACKOFFICE
	 * @author's NOTE : Get All Game List (NOT/USED) ONE TIME USAGE ONLY
	 *
	 */
	// public function gameList(Request $request){
	// 	$client = new Client([
	// 	    'headers' => [ 
	// 	    	'Content-Type' => 'application/json',
	// 	    ]
	// 	]);
	// 	$requesttosend = [
	// 		"merchantId" => $this->merchant_id,
	// 		"currentTime" => AWSHelper::currentTimeMS(),
	// 		"language" => 'en_US'
	// 	];
	// 	$requesttosend['sign'] = AWSHelper::hashen(AWSHelper::currentTimeMS(),$this->merchant_id);
	// 	$guzzle_response = $client->post($this->api_url.'/game/list',
	// 	    ['body' => json_encode($requesttosend)]
	// 	);
	//     $client_response = json_decode($guzzle_response->getBody()->getContents());
	//     return json_encode($client_response);
	// }


	/**
	 * MERCHANT BACKOFFICE (NOT/USED)
	 * @author's NOTE : This is only if we want to disable/enable a player on this provider 
	 * @param   $[request->status] [<enable or disable>]
	 *
	 */
	public function playerManage(Request $request)
	{
		AWSHelper::saveLog('AWS BO Player Manage', $this->provider_db_id, file_get_contents("php://input"), 'ENDPOINT HIT');
		$status = $request->has('status') ? $request->status : 'enable';
		$client_details = AWSHelper::getClientDetails('token', $request->token);
		$client = new Client([
			'headers' => [
				'Content-Type' => 'application/json',
			]
		]);
		$requesttosend = [
			"merchantId" => $this->merchant_id,
			"currentTime" => AWSHelper::currentTimeMS(),
			"username" => $this->merchant_id . '_' . $client_details->player_id,
			"sign" => $this->hashen($this->merchant_id . '_' . $client_details->player_id, AWSHelper::currentTimeMS()),
		];
		$guzzle_response = $client->post(
			$this->api_url . '/user/' . $status,
			['body' => json_encode($requesttosend)]
		);
		$client_response = json_decode($guzzle_response->getBody()->getContents());
		return $client_response;
	}

	/**
	 * MERCHANT BACKOFFICE (NOT/USED)
	 * @author's NOTE : This is only if we want to check the player status on this provider
	 *
	 */
	public function playerStatus(Request $request)
	{
		AWSHelper::saveLog('AWS BO Player Status', $this->provider_db_id, file_get_contents("php://input"), 'ENDPOINT HIT');
		$status = $request->has('status') ? $request->status : 'enable';
		$client_details = AWSHelper::getClientDetails('token', $request->token);
		$client = new Client([
			'headers' => [
				'Content-Type' => 'application/json',
			]
		]);
		$requesttosend = [
			"merchantId" => $this->merchant_id,
			"currentTime" => AWSHelper::currentTimeMS(),
			"username" => $this->merchant_id . '_' . $client_details->player_id,
			"sign" => $this->hashen($this->merchant_id . '_' . $client_details->player_id, AWSHelper::currentTimeMS()),
		];
		$guzzle_response = $client->post(
			$this->api_url . '/user/status',
			['body' => json_encode($requesttosend)]
		);
		$client_response = json_decode($guzzle_response->getBody()->getContents());
		return $client_response;
	}

	/**
	 * MERCHANT BACKOFFICE (NOT/USED)
	 * @author's NOTE : This is only if we want to check the player balance on this provider
	 *
	 */
	public function playerBalance(Request $request)
	{
		$client_details = AWSHelper::getClientDetails('token', $request->token);
		$client = new Client([
			'headers' => [
				'Content-Type' => 'application/json',
			]
		]);
		$requesttosend = [
			"merchantId" => $this->merchant_id,
			"currentTime" => AWSHelper::currentTimeMS(),
			"username" => $this->merchant_id . '_' . $client_details->player_id,
			"sign" => $this->hashen($this->merchant_id . '_' . $client_details->player_id, AWSHelper::currentTimeMS()),
		];
		$guzzle_response = $client->post(
			$this->api_url . '/user/balance',
			['body' => json_encode($requesttosend)]
		);
		$client_response = json_decode($guzzle_response->getBody()->getContents());
		return $client_response;
	}

	/**
	 * MERCHANT BACKOFFICE (NOT/USED) (NOT APLLICABLE IN THE SINGLE WALLET)
	 * @author's NOTE : Fund Transfer
	 *
	 */
	public function fundTransfer(Request $request)
	{
		AWSHelper::saveLog('AWS BO Fund Transfer', $this->provider_db_id, file_get_contents("php://input"), 'ENDPOINT HIT');
		$client_details = AWSHelper::getClientDetails('token', $request->token);
		$client = new Client([
			'headers' => [
				'Content-Type' => 'application/json',
			]
		]);
		$requesttosend = [
			"merchantId" => $this->merchant_id,
			"currentTime" => AWSHelper::currentTimeMS(),
			"username" => $this->merchant_id . '_' . $client_details->player_id,
			"amount" => $request->amount,
			"merchantTransId" => 'AWSF2019123199999', // for each player account, the transfer transaction code has to be unique
			"sign" => $this->hashen($this->merchant_id . '_' . $client_details->player_id, AWSHelper::currentTimeMS()),
		];
		$guzzle_response = $client->post(
			$this->api_url . '/user/balance',
			['body' => json_encode($requesttosend)]
		);
		$client_response = json_decode($guzzle_response->getBody()->getContents());
		return $client_response;
	}

	/**
	 * MERCHANT BACKOFFICE (NOT/USED) (NOT APLLICABLE IN THE SINGLE WALLET)
	 * @author's NOTE : Query status of fund transfer
	 *
	 */
	public function queryStatus(Request $request)
	{
		AWSHelper::saveLog('AWS BO Query Status', $this->provider_db_id, file_get_contents("php://input"), 'ENDPOINT HIT');
		$client_details = AWSHelper::getClientDetails('token', $request->token);
		$client = new Client([
			'headers' => [
				'Content-Type' => 'application/json',
			]
		]);
		$requesttosend = [
			"merchantId" => $this->merchant_id,
			"currentTime" => AWSHelper::currentTimeMS(),
			"username" => $this->merchant_id . '_' . $client_details->player_id,
			"merchantTransId" => 'AWSF2019123199999', // for each player account, the transfer transaction code has to be unique
			"sign" => $this->hashen($this->merchant_id . '_' . $client_details->player_id, AWSHelper::currentTimeMS()),
		];
		$guzzle_response = $client->post(
			$this->api_url . '/fund/queryStatus',
			['body' => json_encode($requesttosend)]
		);
		$client_response = json_decode($guzzle_response->getBody()->getContents());
		return $client_response;
	}

	/**
	 * MERCHANT BACKOFFICE (NOT/USED) (NOT APLLICABLE IN THE SINGLE WALLET)
	 * @author's NOTE : Query status of fund transfer
	 *
	 */
	public function queryOrder(Request $request)
	{
		AWSHelper::saveLog('AWS BO Query Order', $this->provider_db_id, file_get_contents("php://input"), 'ENDPOINT HIT');
		$client_details = AWSHelper::getClientDetails('token', $request->token);
		$client = new Client([
			'headers' => [
				'Content-Type' => 'application/json',
			]
		]);
		$requesttosend = [
			"merchantId" => $this->merchant_id,
			"currentTime" => AWSHelper::currentTimeMS(),
			"username" => $this->merchant_id . '_' . $client_details->player_id,
			"sign" => $this->hashen($this->merchant_id . '_' . $client_details->player_id, AWSHelper::currentTimeMS()),
		];
		$guzzle_response = $client->post(
			$this->api_url . '/order/query',
			['body' => json_encode($requesttosend)]
		);
		$client_response = json_decode($guzzle_response->getBody()->getContents());
		return $client_response;
	}


	/**
	 * MERCHANT BACKOFFICE
	 * @author's NOTE : Logout the player
	 *
	 */
	public function playerLogout(Request $request)
	{
		AWSHelper::saveLog('AWS BO Player Logout', $this->provider_db_id, file_get_contents("php://input"), 'ENDPOINT HIT');
		$client_details = AWSHelper::getClientDetails('token', $request->token);
		$client = new Client([
			'headers' => [
				'Content-Type' => 'application/json',
			]
		]);
		$requesttosend = [
			"merchantId" => $this->merchant_id,
			"currentTime" => AWSHelper::currentTimeMS(),
			"username" => $this->merchant_id . '_' . $client_details->player_id,
			"sign" => $this->hashen($this->merchant_id . '_' . $client_details->player_id, AWSHelper::currentTimeMS()),
		];
		$guzzle_response = $client->post(
			$this->api_url . '/api/logout',
			['body' => json_encode($requesttosend)]
		);
		$client_response = json_decode($guzzle_response->getBody()->getContents());
		return $client_response;
	}



	/**
	 * HELPER
	 * Find Game Transaction
	 * @param [string] $[round_ids] [<round id for bets>]
	 * @param [string] $[type] [<0 Lost, 1 win, 3 draw, 4 refund, 5 processing>]
	 * 
	 */
	public  function getAllGameTransaction($round_ids, $type)
	{
		$game_transactions = DB::table("game_transactions")
			->where('win', $type)
			->whereIn('round_id', $round_ids)
			->get();
		return (count($game_transactions) > 0 ? $game_transactions : 'false');
	}
}
