<?php
namespace App\Helpers;

use GuzzleHttp\Client;
use App\Helpers\Helper;
use App\Helpers\GameLobby;
use App\Helpers\ProviderHelper;
use DB; 

class DOWINNHelper{

    public static function generateGameToken($token,$guid,$prefix,$player_details){
    //LOGIN||Auth
        $walletData = array(
            "token" => (string) $token,
            "sid" => (string) $guid,
            "userId" => $player_details->client_player_id,
        );
        $dataToSend = [
            "account" => config('providerlinks.dowinn.user_agent'),
            "child" => $prefix.'_'.$player_details->player_id,
            "ip" => $player_details->player_ip_address,
            "wallet" => json_encode($walletData),
        ];
        $client = new Client([
            'headers' => [
                'Content-Type' => 'x-www-form-urlencoded' 
            ],
        ]);
        $response = $client->post(config('providerlinks.dowinn.api_url').'/login.do',
        ['form_params' => $dataToSend,]);
        $response = json_decode($response->getBody(),TRUE);
        return($response);
    }

}