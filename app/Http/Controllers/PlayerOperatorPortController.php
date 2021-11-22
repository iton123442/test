<?php

namespace App\Http\Controllers;

use App\Helpers\CallParameters;
use App\Helpers\ProviderHelper;
use App\Http\Controllers\TransferWalletAggregator\TWHelpers;
use Illuminate\Http\Request;
use DB;

class PlayerOperatorPortController extends Controller
{
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

		$client_details = TWHelpers::getClientDetails('ptw', $payload['client_player_id'], $payload['client_id']);		
		
		if ($client_details == null) {
			$http_status = 407;
			$response = [
				"errorCode" =>  1003,
				"message" => "Player not found.",
			];

			return response()->json($response, $http_status);
		}

		$http_status = 200;
		return response()->json($client_details, $http_status);
	}
}
