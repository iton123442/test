<?php
namespace App\Helpers;

use GuzzleHttp\Client;
use App\Helpers\Helper;
use App\Helpers\GameLobby;
use App\Helpers\ProviderHelper;
use DB;
use DateTime;

class JustPlayHelper{

    public static function changeEnvironment($client_details){
        return config('providerlinks.justplay.'.$client_details->default_currency);
    }

    public static function createHash($array_data, $client_details){
        $raw = http_build_query($array_data); 
        $hash = base64_encode(hash_hmac('SHA256',$raw, JustPlayHelper::changeEnvironment($client_details)['password'], false));
        return $hash;
    }




}

