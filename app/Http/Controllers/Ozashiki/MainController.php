<?php

namespace App\Http\Controllers\Ozashiki;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\CallParameters;
use App\Http\Controllers\Ozashiki\Seamless;
use App\Http\Controllers\Ozashiki\SemiTransfer;
use App\Helpers\ProviderHelper;
use App\Helpers\Helper;

class MainController extends Controller
{   

	public $client_api_key , $provider_db_id ;

	public function __construct(){
		$this->client_api_key = config("providerlinks.ozashiki.CLIENT_API_KEY");
		$this->provider_db_id = config("providerlinks.ozashiki.PROVIDER_ID");
	}

	use Seamless , SemiTransfer
	{
       Seamless::debitProcess as seamlessDebitProcess;
       Seamless::creditProcess as seamlessCreditProcess;
       Seamless::rollbackTransaction as seamlessRollbackTransaction;

       SemiTransfer::debitProcess as SemiTransferDebitProcess;
       SemiTransfer::creditProcess as SemiTransferCreditProcess;
       SemiTransfer::rollbackTransaction as SemiTransferRollbackTransaction;
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
					//if the client wallet type is 1 inhosue seamless balance else tw_balance
					$balance = $client_details->wallet_type == 1 ? $client_details->balance : $client_details->tw_balance;
					$response = [
						"balance" => bcdiv($balance, 1, 2)
					];
				}
			}

		}
		Helper::saveLog('Ozashiki_balance', $this->provider_db_id, file_get_contents("php://input"), $response);
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
					
					switch($client_details->wallet_type){
			            case 1:
			                $seamlesswallet = $this->seamlessDebitProcess($json_data,$client_details);
			                $invokeEnd = microtime(true) - LARAVEL_START;
			                Helper::saveLog('Ozashiki', $this->provider_db_id, json_encode(["method" => "seamlessGameBet" ,"Time" => $invokeEnd,"data"=>$seamlesswallet]), $seamlesswallet);
			                return response()->json($seamlesswallet, $http_status);
			            case 2:
			                $semitransferwallet = $this->SemiTransferDebitProcess($json_data,$client_details);
			                $invokeEnd = microtime(true) - LARAVEL_START;
			                Helper::saveLog('Ozashiki TW', $this->provider_db_id, json_encode(["method" => "SemiTWGameBet" ,"Time" => $invokeEnd,"data"=>$semitransferwallet]), "");
			                return response()->json($semitransferwallet, $http_status);
			            case 3:
			                $semitransferwallet = $this->SemiTransferDebitProcess($json_data,$client_details);
			                $invokeEnd = microtime(true) - LARAVEL_START;
			                Helper::saveLog('Ozashiki TW', $this->provider_db_id, json_encode(["method" => "SemiTWGameBet" ,"Time" => $invokeEnd,"data"=>$semitransferwallet]), "");
			                return response()->json($semitransferwallet, $http_status);
			        }

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
					
					switch($client_details->wallet_type){
			            case 1:
			                $seamlesswallet = $this->seamlessCreditProcess($json_data,$client_details);
			                $invokeEnd = microtime(true) - LARAVEL_START;
			                Helper::saveLog('Ozashiki', $this->provider_db_id, json_encode(["method" => "seamlessCreditProcess" ,"Time" => $invokeEnd,"data"=>$seamlesswallet]), $seamlesswallet);
			                return response()->json($seamlesswallet, $http_status);
			            case 2:
			                $semitransferwallet = $this->SemiTransferCreditProcess($json_data,$client_details);
			                $invokeEnd = microtime(true) - LARAVEL_START;
			                Helper::saveLog('Ozashiki TW', $this->provider_db_id, json_encode(["method" => "SemiTWGameBet" ,"Time" => $invokeEnd,"data"=>$semitransferwallet]), "");
			                return response()->json($semitransferwallet, $http_status);
			            case 3:
			                $semitransferwallet = $this->SemiTransferCreditProcess($json_data,$client_details);
			                $invokeEnd = microtime(true) - LARAVEL_START;
			                Helper::saveLog('Ozashiki TW', $this->provider_db_id, json_encode(["method" => "SemiTWGameBet" ,"Time" => $invokeEnd,"data"=>$semitransferwallet]), "");
			                return response()->json($semitransferwallet, $http_status);
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
					switch($client_details->wallet_type){
			            case 1:
			                $seamlesswallet = $this->seamlessRollbackTransaction($json_data,$client_details);
			                $invokeEnd = microtime(true) - LARAVEL_START;
			                Helper::saveLog('Ozashiki', $this->provider_db_id, json_encode(["method" => "seamlessRollbackTransaction" ,"Time" => $invokeEnd,"data"=>$seamlesswallet]), $seamlesswallet);
			                return response()->json($seamlesswallet, $http_status);
			            case 2:
			            	$semitransferwallet = $this->SemiTransferRollbackTransaction($json_data,$client_details);
			                $invokeEnd = microtime(true) - LARAVEL_START;
			                Helper::saveLog('Ozashiki TW', $this->provider_db_id, json_encode(["method" => "SemiTransferRollbackTransaction" ,"Time" => $invokeEnd,"data"=>$semitransferwallet]), "");
			                return response()->json($semitransferwallet, $http_status);
			            case 3:
			            	$semitransferwallet = $this->SemiTransferRollbackTransaction($json_data,$client_details);
			                $invokeEnd = microtime(true) - LARAVEL_START;
			                Helper::saveLog('Ozashiki TW', $this->provider_db_id, json_encode(["method" => "SemiTransferRollbackTransaction" ,"Time" => $invokeEnd,"data"=>$semitransferwallet]), "");
			                return response()->json($semitransferwallet, $http_status);
			                
			        }

				}
			}

		}
		Helper::saveLog('Ozashiki rollback', $this->provider_db_id, file_get_contents("php://input"), $response);
		return response()->json($response, $http_status);
	}


	
}