<?php
namespace App\Helpers;

use GuzzleHttp\Client;
use App\Helpers\Helper;
use App\Helpers\GameLobby;
use App\Helpers\ProviderHelper;
use App\Models\GameTransactionMDB;
use DB; 

class NagaGamesHelper{

    //FIND THE GAME URL
    public static function findGameUrl($token,$gameCode,$client_details){
        $brandCode = config('providerlinks.naga.brandCode');
        $groupCode = config('providerlinks.naga.groupCode');
        $url = config('providerlinks.naga.api_url') .'/client/game?groupCode='.$groupCode.'&brandCode='.$brandCode;
        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json' 
            ],
        ]);
        $response = $client->get($url);
        $response = json_decode($response->getBody(),TRUE);
        foreach($response as $item) {
            if ($item['code'] == $gameCode){
                $link = $item['playUrl'];
            }
        }
        $toSend = [
            "game_launch_url" => $link
        ];
        GameTransactionMDB::updateGameLaunchURL($toSend,$gameCode,$client_details);
        
        return($link);
    }
    
    //Get the bet's sstatus
    public static function viewBetHistory($betId){
        $url = config('providerlinks.naga.api_url') .'/operator/bet/history?betId='. $betId.'&groupCode='.$this->groupCode.'&brandCode='.$this->brandCode;
        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json' 
            ],
        ]);
        $response = $client->get($url);
        $response = json_decode($response->getBody(),TRUE);
         return $response['betStatus'];
    }
}