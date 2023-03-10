<?php
namespace App\Helpers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use Carbon\Carbon;
use SimpleXMLElement;

use DB;

class AmuseGamingHelper{


    public static  function createPlayerAndCheckPlayer($client_details){
        if(config('providerlinks.amusegaming.modetype') == 'TEST'){
            $currency = 'TEST';
        }else{
            $currency = $client_details->default_currency;
        }
        $public_key = config('providerlinks.amusegaming.operator.'.$currency.'.public_key');
        $secret_key = config('providerlinks.amusegaming.operator.'.$currency.'.secret_key');
        $header = new Client([
            'headers' => [ 
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ]
        ]);
        try {
            /******************************************************************
             * 
             * CHECK PLAYER REQUEST 
             * 
             ******************************************************************/
            date_default_timezone_set("Asia/Hong_Kong");
            $param = [
                "pubkey" => $public_key,
                "time" => time(),
                "nonce" => md5( substr( str_shuffle( "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ" ), 0, 10 ).microtime() ),
                "requrl" => config('providerlinks.amusegaming.api_url').'casino/player_exists',
                "playerid" => $client_details->player_id,
            ];
            $param["hmac"] = base64_encode( hash_hmac( "sha1", http_build_query( $param )."ILN4kJYDx8", $secret_key, true ) );
            $response = $header->post( config('providerlinks.amusegaming.api_url').'casino/player_exists', [
                'form_params' => $param,
            ]);
            $checkplayer_response = json_decode($response->getBody()->getContents());
            Helper::saveLog('AMUSEGAMING GM CHECKPLAYER', 65, json_encode($param),  $checkplayer_response );
            if($checkplayer_response->exists){
                return true;
            } else { 
                 /******************************************************************
                 * 
                 * CHECK PLAYER REQUEST 
                 * 
                 ******************************************************************/
                $param = [
                    "pubkey" => $public_key,
                    "time" => time(),
                    "nonce" => md5( substr( str_shuffle( "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ" ), 0, 10 ).microtime() ),
                    "requrl" => config('providerlinks.amusegaming.api_url').'casino/create_player_if_not_exists',
                    "playerid" => $client_details->player_id,
                ];
                $param["hmac"] = base64_encode( hash_hmac( "sha1", http_build_query( $param )."ILN4kJYDx8", $secret_key, true ) );
                $response = $header->post( config('providerlinks.amusegaming.api_url').'casino/create_player_if_not_exists', [
                    'form_params' => $param,
                ]);
                $checkplayer_response = json_decode($response->getBody()->getContents());
                Helper::saveLog('AMUSEGAMING GM CREATE PLAYER', 65, json_encode($param),  $checkplayer_response );
                if($checkplayer_response->status == "true"){
                    return true;
                } else {
                    return false;
                }
            }
            return false;
        } catch (\Exception $e) {
            $msg = array(
                'err_message' => $e->getMessage(),
                'err_line' => $e->getLine(),
                'err_file' => $e->getFile()
            );
            Helper::saveLog('AMUSEGAMING GM CREATE PLAYER ERROR', 65, json_encode($msg),  $e->getMessage() );
            return false;
        }
        
    }

    public static  function requestTokenFromProvider($client_details,$type){
        if(config('providerlinks.amusegaming.modetype') == 'TEST'){
            $currency = 'TEST';
        }else{
            $currency = $client_details->default_currency;
        }
        $public_key = config('providerlinks.amusegaming.operator.'.$currency.'.public_key');
        $secret_key = config('providerlinks.amusegaming.operator.'.$currency.'.secret_key');
        $player_id = $client_details->player_id;
        if (isset($type) && $type == "demo") {
            $endpoint = "casino/request_demo_token";
        } else {
            $endpoint = "player/request_token";
        }
        $header = new Client([
            'headers' => [ 
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ]
        ]);
        try {
            /******************************************************************
             * 
             * CHECK PLAYER REQUEST 
             * 
             ******************************************************************/
            date_default_timezone_set("Asia/Hong_Kong");
            $param = [
                "pubkey" => $public_key,
                "time" => time(),
                "nonce" => md5( substr( str_shuffle( "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ" ), 0, 10 ).microtime() ),
                "requrl" => config('providerlinks.amusegaming.api_url').$endpoint,
                "playerid" => $player_id,
            ];
            $param["hmac"] = base64_encode( hash_hmac( "sha1", http_build_query( $param )."ILN4kJYDx8", $secret_key, true ) );
            $response = $header->post( config('providerlinks.amusegaming.api_url').$endpoint, [
                'form_params' => $param,
            ]);
            $response_client = json_decode($response->getBody()->getContents());
            Helper::saveLog('AMUSEGAMING GM CHECKPLAYER', 65, json_encode($param),  $response_client );
            if($response_client->status == "OK") {
                return $response_client->token;
            }
            return false;
        } catch (\Exception $e) {
            $msg = array(
                'err_message' => $e->getMessage(),
                'err_line' => $e->getLine(),
                'err_file' => $e->getFile()
            );
            Helper::saveLog('AMUSEGAMING GM CREATE PLAYER', 65, json_encode($msg),  $e->getMessage() );
            return false;
        }
    }


    public static  function AmuseGamingGameList($brand,$channel,$currency){
        if(config('providerlinks.amusegaming.modetype') == 'TEST'){
            $currency = 'TEST';
        }else{
            $currency = $currency;
        }
        $public_key = config('providerlinks.amusegaming.operator.'.$currency.'.public_key');
        $secret_key = config('providerlinks.amusegaming.operator.'.$currency.'.secret_key');
        $endpoint = "casino/list_games";
        $header = new Client([
            'headers' => [ 
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ]
        ]);
        try {
            /******************************************************************
             * 
             * CHECK PLAYER REQUEST 
             * 
             ******************************************************************/
           
            date_default_timezone_set("UTC");
            if($brand != ''){
                $param = [
                    "pubkey" => $public_key,
                    "time" => time(),
                    "nonce" => md5( substr( str_shuffle( "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ" ), 0, 10 ).microtime() ),
                    "requrl" => config('providerlinks.amusegaming.api_url').$endpoint,
                    "filter_brands" => $brand,
                    "filter_technology" => "html5",
                ];
            }else{
                $param = [
                    "pubkey" => $public_key,
                    "time" => time(),
                    "nonce" => md5( substr( str_shuffle( "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ" ), 0, 10 ).microtime() ),
                    "requrl" => config('providerlinks.amusegaming.api_url').$endpoint,
                ];
            }
            $param["hmac"] = base64_encode( hash_hmac( "sha1", http_build_query( $param )."ILN4kJYDx8", $secret_key, true ) );
            Helper::saveLog('AMUSEGAMING GAMELIST Param', 65, json_encode($param),  time() );
            $response = $header->post( config('providerlinks.amusegaming.api_url').$endpoint, [
                'form_params' => $param,
            ]);
            $response_client = json_decode($response->getBody()->getContents());
            Helper::saveLog('AMUSEGAMING GAMELIST', 65, json_encode($param),  $response_client );
            // if($response_client->status == "OK") {
            //     // return $response_client->token;
            // }
            return $response_client;
        } catch (\Exception $e) {
            $msg = array(
                'err_message' => $e->getMessage(),
                'err_line' => $e->getLine(),
                'err_file' => $e->getFile()
            );
            Helper::saveLog('AMUSEGAMING GM CREATE PLAYER', 65, json_encode($msg),  $e->getMessage() );
            return false;
        }
    }

    public static function getBrand($game_code,$provider_id){
        $game_details = DB::SELECT("SELECT game_code,game_name,game_name, sub_provider_id as brand_code, sub_provider_name as brand FROM games g INNER JOIN sub_providers  sp using (sub_provider_id) where game_code = '".$game_code."' and g.provider_id = ".$provider_id." ");
        // $brand = str_replace("AG ", "", $game_details[0]->brand);
        if($game_details[0]->brand == 'AG Microgaming' || $game_details[0]->brand_code == '121' ){
            $brand = 'microgaming';
        }elseif($game_details[0]->brand == 'AG Playtech' || $game_details[0]->brand_code == '122' ){
            $brand = 'playtech';
        }elseif($game_details[0]->brand == 'AG IGT' || $game_details[0]->brand_code == '123' ){
            $brand = 'igt';
        }elseif($game_details[0]->brand == 'AG Play\'n GO' || $game_details[0]->brand_code == '124'){
            $brand = 'playngo';
        }elseif($game_details[0]->brand == 'AG EGT Original' || $game_details[0]->brand_code == '125'){
            $brand = 'egtjackpot';
        }elseif($game_details[0]->brand == 'AG Wazdan' || $game_details[0]->brand_code == '118'){
            $brand = 'wazdan';
        }elseif($game_details[0]->brand == 'AG NetEnt' || $game_details[0]->brand_code == '115'){
            $brand = 'netent';
        }elseif($game_details[0]->brand == 'AG QuickSpin' || $game_details[0]->brand_code == '120'){
            $brand = 'quickspin';
        }elseif($game_details[0]->brand == 'AG Amatic' || $game_details[0]->brand_code == '116'){
            $brand = 'amatic';
        }elseif($game_details[0]->brand == 'AG Novomatic' || $game_details[0]->brand_code == '117'){
            $brand = 'novomatic';
        }elseif($game_details[0]->brand == 'AG PragmaticPlay' || $game_details[0]->brand_code == '119'){
            $brand = 'pragmaticplay';
        }
        return $brand;
    }
    public static function arrayToXml($array, $rootElement = null, $xml = null){
        $_xml = $xml; 
      
        // If there is no Root Element then insert root 
        if ($_xml === null) { 
            $_xml = new SimpleXMLElement($rootElement !== null ? "<?xml version='1.0' encoding='utf-8'?>\n".$rootElement."" : '<root/>'); 
        } 
        
        // Visit all key value pair 
        foreach ($array as $k => $v) { 
            
            // If there is nested array then 
            if (is_array($v)) {  
                
                // Call function for nested array 
                PNGHelper::arrayToXml($v, $k, $_xml->addChild($k)); 
                } 
                
            else { 
                
                // Simply add child element.  
                $_xml->addChild($k, $v); 
            } 
        } 
        
        return $_xml->asXML(); 
    }
}