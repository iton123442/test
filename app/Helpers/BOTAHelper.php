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
            Helper::saveLog('bota PLAYER EXIST', 135, $details, 'VERIFICATION HIT');
			$datatosend = [ 
                "user_id" => config('providerlinks.bota.prefix')."_".$details->player_id
            ];
			$client = new Client([
                'headers' => [ 
                    'Content-type' => 'x-www-form-urlencoded',
                    'Authorization' => "Bearer ".config('providerlinks.bota.api_key'),
                    'User-Agent' => config('providerlinks.bota.user_agent')
                ]
            ]);
            $response = $client->get(config('providerlinks.bota.api_url').'/user/balance',
            ['form_params' => $datatosend,]);
            $responseBody = json_decode($response->getBody()->getContents());
            return $responseBody;
		} elseif($method == 'NoAccount'){
            Helper::saveLog('bota NEW PLAYER', 135, $details, 'Create new player');
			$datatosend = [
				"user_id" => config('providerlinks.bota.prefix')."_".$details->player_id,
				"user_name" => $details->username,
				"user_ip" => $details->player_ip_address
			];
			$client = new Client([
                'headers' => [ 
                    'Content-type' => 'x-www-form-urlencoded',
                    'Authorization' => "Bearer ".config('providerlinks.bota.api_key'),
                    'User-Agent' => config('providerlinks.bota.user_agent')
                ]
            ]);
            $response = $client->get(config('providerlinks.bota.api_url').'/user/create',
            ['form_params' => $datatosend,]);
            $responseBody = json_decode($response->getBody()->getContents());
            return $responseBody;
		}
	}
   	public static function botaGenerateGametoken($player_details){
    //Check player if exist in provider's side
	   $checkIfEXist = BOTAHelper::botaPlayerChecker($player_details,'Verify');
	   if($checkIfEXist->result_code == 1){
        //if none then Create user in provider's side
	        $checkIfEXist = BOTAHelper::botaPlayerChecker($player_details,'NoAccount');
	   }
	   $datafortoken = [
		   "user_id" => config('providerlinks.bota.prefix')."_".$player_details->player_id,
		   "user_ip" => $player_details->player_ip_address
	   ];
	   $client = new Client([
	         'headers' => [ 
               'Content-type' => 'x-www-form-urlencoded',
               'Authorization' => "Bearer ".config('providerlinks.bota.api_key'),
               'User-Agent' => config('providerlinks.bota.user_agent')
            ]
	   ]);
	   $response = $client->get(config('providerlinks.bota.api_url').'/game/token',
       ['form_params' => $datafortoken,]);
       $dataresponse = json_decode($response->getBody()->getContents());
	   Helper::saveLog('bota gametoken', 135, json_encode($dataresponse),'Token Created!');
	   return $dataresponse; 
    }

}