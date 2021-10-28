<?php

namespace App\Http\Controllers\Gamelobby;

use DB;
use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Helpers\ClientHelper;

class GameDemoClientController extends Controller
{
    public function gameLaunchDemo(Request $request){

    	if( $request->has('game_code') && $request->has('provider_name') ){
			try {
				$client = new Client();
				$response = $client->get(config('providerlinks.tigergames'). '/api/gameluanch/demo?currency=USD&game_provider='.$request["provider_name"].'&game_code='.$request["game_code"]);
				$client_reponse = $response->getBody()->getContents();
				return response($client_reponse,200)->header('Content-Type', 'application/json');
			} catch (\Exception $e) {
				$response = array(
					"message" => ClientHelper::getClientErrorCode(10),
					"url" => config('providerlinks.play_betrnk').'/tigergames/api?msg='.ClientHelper::getClientErrorCode(10),
					"game_launch" => false
				);
				return response($response,200)->header('Content-Type', 'application/json');
			}
    		// $getClientErrorCode = $this->checkData($request->all());
    		// if( $getClientErrorCode != 200){
            //     $response = array(
            //         "message" => ClientHelper::getClientErrorCode($getClientErrorCode ),
            //         "url" => config('providerlinks.play_betrnk').'/tigergames/api?msg='.ClientHelper::getClientErrorCode($getClientErrorCode),
            //         "game_launch" => false
            //     );
            //     return response($response,200)->header('Content-Type', 'application/json');
            // } else {
            // 		$clientDetails = DB::table('clients')->where('client_id', $request['client_id'])->first();
            // 		// $gameDetails = DB::table('games')->where('game_id', $request["game_id"])->first();
            // 		$gameDetails = DB::select('
            // 			SELECT 
			// 					game_code,
			// 			     (select sub_provider_name from sub_providers where sub_provider_id = g.sub_provider_id) as game_provider
			// 				from games g
			// 			WHERE g.game_id = '.$request["game_id"].'
            // 			')[0];

            // 		$client = new Client();
			//         $requesttosend = [
			//             'currency'=> $clientDetails->default_currency,
			//             'game_provider' => $gameDetails->game_provider,
			//             'game_code'=>  $gameDetails->game_code,
			//         ];
			//         try {
			//         	$response = $client->get(config('providerlinks.tigergames'). '/api/gameluanch/demo?currency='.$clientDetails->default_currency.'&game_provider='.$gameDetails->game_provider.'&game_code='.$gameDetails->game_code);
			// 	        $client_reponse = $response->getBody()->getContents();
			// 	        return response($client_reponse,200)->header('Content-Type', 'application/json');
			//         } catch (\Exception $e) {
			//         	$response = array(
		    //                 "message" => ClientHelper::getClientErrorCode(10),
		    //                 "url" => config('providerlinks.play_betrnk').'/tigergames/api?msg='.ClientHelper::getClientErrorCode(10),
		    //                 "game_launch" => 13421421421
		    //             );
		    //             return response($response,200)->header('Content-Type', 'application/json');
			//         }
			        
            		
            // }
    	} else {
    		$response = array(
                "url" => config('providerlinks.play_betrnk').'/tigergames/api?msg='.ClientHelper::getClientErrorCode(10),
                "message" => "Missing Required Input",
                "game_launch" => false
            );
            return response($response,200)->header('Content-Type', 'application/json');
    	}
    }


    public static function checkData($data){

		// Client Filter [NOT FOUND or DEACTIVATED]
		$client = DB::table('clients')->where('client_id', $data['client_id'])->first();
		if($client == '' || $client == null){ return 1; } 
		if($client->status_id != 1 ){ return 2; }

		// Operator maintenance
		$operator = DB::table('operator')->where('operator_id', $client->operator_id)->first();

		if($operator == '' || $operator == null){ return 8; } 
		if(isset($operator->status_id) && $operator->status_id != 1 ){ return 9; }

		
		//maintenance ?
		$maintenance = DB::select("
			SELECT 
				on_maintenance as game_maitenance,
			     (select on_maintenance from sub_providers where sub_provider_id = g.sub_provider_id) as subprovider_maintenance,
			     (select on_maintenance from providers where provider_id = g.provider_id) as provider_maintenance
				FROM games g
			WHERE g.game_id = '".$data["game_id"]."' ");
		if ($maintenance == '' || $maintenance == null) { return 3; }
		if ($maintenance[0]->game_maitenance != 0 )        { return 4; }
		if ($maintenance[0]->subprovider_maintenance != 0) { return 6; }
		if ($maintenance[0]->provider_maintenance != 0)    { return 6; }


		return 200; // All Good Request May Proceed!
	}
}
