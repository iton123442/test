<?php

namespace App\Http\Controllers;

use App\Helpers\CallParameters;
use App\Helpers\ProviderHelper;
use Illuminate\Http\Request;
use DB;

class PlayerOperatorPortController extends Controller
{
	public $vcci_api_key ;

	public function __construct(){
		$this->middleware('oauth', ['except' => []]);
	}

	public function getPlayerOperatorDetails(Request $request) 
	{
		$payload = $request->all();
		
		$http_status = 403;
		$response = [
				"errorCode" =>  1000,
				"errorMessage" => "Invalid token.",
			];


		if(!CallParameters::check_keys($payload, 'client_player_id', 'client_id'))
		{
			$http_status = 405;
			$response = [
					"errorCode" =>  1001,
					"errorMessage" => "Post data is invalid.",
				];

			return response()->json($response, $http_status);
		}

		$client_details = ProviderHelper::getPlayerOperatorDetails('ptw', $payload['client_player_id'], $payload['client_id']);
		
		if ($client_details == null) {
			$http_status = 407;
			$response = [
				"errorCode" =>  1003,
				"message" => "Player not found.",
			];

			return response()->json($response, $http_status);
		}

		$http_status = 200;
		$response = [
			'client_id' => $client_details->client_id,
		    'country_code' => $client_details->country_code,
		    'player_id' => $client_details->player_id,
		    'email' => $client_details->email,
		    'client_player_id' => $client_details->client_player_id,
		    'language' => $client_details->language,
		    'tw_balance' => $client_details->tw_balance,
		    'currency' => $client_details->currency,
		    'test_player' => $client_details->test_player,
		    'username' => $client_details->username,
		    'created_at' => $client_details->created_at,
		    'client_url' => $client_details->client_url,
		    'default_currency' => $client_details->default_currency,
		    'wallet_type' => $client_details->wallet_type,
		    'display_name' => $client_details->display_name,
		    'client_api_key' => $client_details->client_api_key,
		    'client_code' => $client_details->client_code,
		    'client_access_token' => $client_details->client_access_token,
		    'operator_id' => $client_details->operator_id,
		    'player_details_url' => $client_details->player_details_url,
		    'fund_transfer_url' => $client_details->fund_transfer_url,
		    'transaction_checker_url' => $client_details->transaction_checker_url,
		    'connection_name' => $client_details->connection_name,
		];

		return response()->json($response, $http_status);
	}
}
