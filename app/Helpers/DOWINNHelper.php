<?php
namespace App\Helpers;

use GuzzleHttp\Client;
use App\Helpers\Helper;
use App\Helpers\GameLobby;
use App\Helpers\ProviderHelper;
use App\Models\GameTransactionMDB;
use DB; 

class DOWINNHelper{

    public static function generateGameToken($token,$guid,$prefix,$player_details){
    //LOGIN||Auth
        $walletData = array(
            "token" => (string) $token,
            "sid" => (string) $guid,
            "userId" => (string) $player_details->client_player_id,
        );
        $dataToSend = [
            "account" => config('providerlinks.dowinn.user_agent'),
            "child" => $prefix.'_'.$player_details->player_id,
            "ip" => $player_details->player_ip_address,
            "limiId" => 1,
            "wallet" => json_encode($walletData),
        ];
        // Helper::saveLog('DOWINN STATUS CHECKER', 139, json_encode($dataToSend), 'REQUEST');
        $client = new Client([
            'headers' => [
                'Content-Type' => 'x-www-form-urlencoded' 
            ],
        ]);
        $response = $client->post(config('providerlinks.dowinn.api_url').'/login.do',
        ['form_params' => $dataToSend,]);
        $response = json_decode($response->getBody(),TRUE);
        // Helper::saveLog('DOWINN LOGIN/AUTH', 139, json_encode($response), 'LOGIN HIT!');
        return($response);
    }
    //CHECK PLAYER STATUS AND BALANCE
    public static function checkBalanceAndStatus($token,$guid,$prefix,$player_details){
        $walletData = array(
            "token" => (string) $token,
            "sid" => (string) $guid,
            "userId" => (string) $player_details->client_player_id,
        );
        $dataToSend = [
            "account" => config('providerlinks.dowinn.user_agent'),
            "child" => $prefix.'_'.$player_details->player_id,
            "wallet" => json_encode($walletData),
        ];
        // Helper::saveLog('DOWINN STATUS CHECKER', 139, json_encode($dataToSend), 'REQUEST');
        $client = new Client([
            'headers' => [
                'Content-Type' => 'x-www-form-urlencoded' 
            ],
        ]);
        $response = $client->post(config('providerlinks.dowinn.api_url').'/query.do',
        ['form_params' => $dataToSend,]);
        $response = json_decode($response->getBody(),TRUE);
        // Helper::saveLog('DOWINN STATUS CHECKER', 139, json_encode($response), 'CHECKER HIT!');
        return($response);
    }

    public static function totalBet($roundId,$client_details){
        $betTrans = GameTransactionMDB::findDOWINNGameExt($roundId,'notIncluded',1,$client_details,'BET_CANCELED');
        $betAmount = round($betTrans['0']->amount,2);
        return $betAmount;
    }
}