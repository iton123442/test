<?php
namespace App\Helpers;

use GuzzleHttp\Client;
use App\Helpers\Helper;
use App\Helpers\GameLobby;
use App\Helpers\ProviderHelper;
use DB; 

class BOTAHelper{
    public static function botaPlayerChecker($details,$method){
		if($method == 'Verify'){
			$datatosend = [ "user_id" => config('providerlinks.bota.prefix')."_".$details->player_id,];
			$client = new Client([
				'headers' => [ 
					'Content-Type' => 'application/x-www-form-urlencoded',
				]
			]);
			$response = $client->post(config('providerlinks.bota.api_url').'/user/balance',[
				'form_params' => $datatosend,
			]);
	
			$responsedecoded = json_decode($response->getBody(),TRUE);
			Helper::saveLog('bota PLAYER EXIST', 135, json_encode($response), $responsedecoded);
			return $responsedecoded; 
		} elseif($method == 'NoAccount'){
			$datatosend = [
				"user_id" => config('providerlinks.bota.prefix')."_".$details->player_id,
				"user_name" => $details->username,
				"user_ip" => $details->player_ip_address
			];
			$client = new Client([
				'headers' => [ 
					'Content-Type' => 'application/x-www-form-urlencoded',
				]
			]);
			$response = $client->post(config('providerlinks.bota.api_url').'/user/create',[
				'form_params' => $datatosend,
			]);
	
			$responsedecoded = json_decode($response->getBody(),TRUE);
			Helper::saveLog('bota NEW PLAYER CREATED', 135, json_encode($response), $responsedecoded);
			return $responsedecoded; 
		}
	}
   	public static function botaGenerateGametoken($player_details){
    //Check player if exist in provider's side
	   $checkIfEXist = ProviderHelper::botaPlayerChecker($player_details,'Verify');
	   if($checkIfEXist->result_code == 1){
           //if none then Create user in provider's side
		    $checkIfEXist = ProviderHelper::botaPlayerChecker($player_details,'NoAccount');
	   }

	   $datafortoken = [
		   "user_id" => config('providerlinks.bota.prefix')."_".$player_details->player_id,
		   "user_ip" => $player_details->player_ip_address
	   ];
	   $client = new Client([
		   'headers' => [ 
			   'Content-Type' => 'application/x-www-form-urlencoded',
		   ]
	   ]);
	   $response = $client->post(config('providerlinks.bota.api_url').'/game/token',[
		   'form_params' => $datafortoken,
	   ]);
	   $res = json_decode($response->getBody(),TRUE);
	   Helper::saveLog('bota gametoken', 135, json_encode($response), $res);
	   return $res; 
   }

}