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
        $groupCode = config('providerlinks.naga.groupCode');;
        $url = config('providerlinks.naga.api_url') .'?playerToken='.$token.'&groupCode='.$groupCode.'&brandCode='.$brandCode. "&sortBy=playCount&orderBy=DESC";
        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json' 
            ],
        ]);
        $response = $client->get($url);
        $response = json_decode($response->getBody(),TRUE);
        // Helper::saveLog('NAGA FINDGAME', 141, json_encode($response), 'URL HIT!');
        //Iterate every array to get the matching game code
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
}