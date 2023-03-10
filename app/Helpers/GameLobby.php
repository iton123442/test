<?php
namespace App\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use App\Helpers\Helper;
use App\Helpers\IAHelper;
use App\Helpers\AWSHelper;
use App\Helpers\SAHelper;
use App\Helpers\TidyHelper;
use App\Helpers\TransferWalletHelper;
use App\Helpers\DragonGamingHelper;
use App\Helpers\FCHelper;
use App\Helpers\ProviderHelper;
use App\Helpers\DigitainHelper;
use App\Helpers\MGHelper;
use App\Helpers\EVGHelper;
use App\Helpers\BOTAHelper;
use App\Helpers\DOWINNHelper;
use App\Helpers\NagaGamesHelper;
use DOMDocument;
use App\Services\AES;
use Webpatser\Uuid\Uuid;

use DB;             
use Carbon\Carbon;
class GameLobby{
    public static function icgLaunchUrl($game_code,$token,$exitUrl,$provider,$lang="en"){
        $client = GameLobby::getClientDetails("token",$token);
        
        $game_list =GameLobby::icgGameUrl($client->default_currency);
        ProviderHelper::saveLogGameLaunch('GAMELAUNCH GAMELIST', 11, json_encode($game_code), json_encode($game_list));
        foreach($game_list["data"] as $game){
            if($game["productId"] == $game_code){
                $lang = GameLobby::getLanguage("Iconic Gaming",$lang);
                Helper::savePLayerGameRound($game["productId"],$token,$provider);
                ProviderHelper::saveLogGameLaunch('GAMELAUNCH ICG', 11, json_encode($game_code), json_encode($game["href"]));
                return $game["href"].'&token='.$token.'&lang='.$lang.'&home_URL='.$exitUrl.'&showPanel=false';
                
            }
        }
    }
    public static function fcLaunchUrl($game_code,$token,$exitUrl,$provider,$lang="en"){
        $client_details = ProviderHelper::getClientDetails("token",$token);
        Helper::savePLayerGameRound($game_code,$token,$provider);
        $data = FCHelper::loginGame($client_details->player_id,$game_code,1,$exitUrl,$client_details->default_currency);
        ProviderHelper::saveLogGameLaunch('GAMELAUNCH FC', 11, json_encode($game_code), json_encode($data));
        return $data["Url"];
    }
    public static function booongoLaunchUrl($game_code,$token,$provider,$exitUrl){
        $lang = "en";
        $timestamp = Carbon::now()->timestamp;
        $exit_url = $exitUrl;
        Helper::savePLayerGameRound($game_code,$token,$provider);
        $gameurl =  config("providerlinks.boongo.PLATFORM_SERVER_URL")
                  .config("providerlinks.boongo.PROJECT_NAME").
                  "/game.html?wl=".config("providerlinks.boongo.WL").
                  "&token=".$token."&game=".$game_code."&lang=".$lang."&sound=1&ts=".
                  $timestamp."&quickspin=1&platform=desktop".
                  "&exir_url=".$exit_url;
        return $gameurl;
    }
    public static function wazdanLaunchUrl($game_code,$token,$provider,$exitUrl){
        $client_details = ProviderHelper::getClientDetails('token', $token);
        if($client_details){
            $lang = "en";
            $timestamp = Carbon::now()->timestamp;
            $exit_url = $exitUrl;
            Helper::savePLayerGameRound($game_code,$token,$provider);
            $operator_data = config('providerlinks.wazdan.operator_data');
            $wazdan_operator = "tigergames";
            if(array_key_exists($client_details->operator_id,$operator_data)){
                $wazdan_operator = $operator_data[$client_details->operator_id];
            }
            $gameurl = config('providerlinks.wazdan.gamelaunchurl').config('providerlinks.wazdan.partnercode').'/gamelauncher?operator='.$wazdan_operator.
                  '&game='.$game_code.'&mode=real&token='.$token.'&license='.config('providerlinks.wazdan.license').'&lang='.$lang.'&platform=desktop';
            return $gameurl;
        }
        else{
            return 'false';
        }
        
    }
    public static function fivemenlaunchUrl( $game_code = null, $token = null, $exiturl, $provider){
        $client_player_details = Providerhelper::getClientDetails('token', $token);

        $requesttosend = [
          "project" => config('providerlinks.5men.project_id'),
          "version" => 1,
          "token" => $token,
          "game" => $game_code, //game_code, game_id
          "settings" =>  [
            'user_id'=> $client_player_details->player_id,
            'language'=> $client_player_details->language ? $client_player_details->language : 'en',
            'https' => 1,
            'platform' => 'mobile'
          ],
          "denomination" => 'default', // game to be launched with values like 1.0, 1, default
          "currency" => $client_player_details->default_currency,
          "return_url_info" => 1, // url link
          "callback_version" => 2, // POST CALLBACK
        ];

        $signature =  ProviderHelper::getSignature($requesttosend, config('providerlinks.5men.api_key'));
        Helper::saveLog('5men create signature', 53, json_encode($signature), $requesttosend);
        $requesttosend['signature'] = $signature;

        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        ]);

        $response = $client->post(config('providerlinks.5men.api_url').'/game/geturl',[
            'form_params' => $requesttosend,
        ]);
        $res = json_decode($response->getBody(),TRUE);
        Helper::saveLog('5men gamelaunch', 53, json_encode($response), $res);
        $gameurl = isset($res['data']['link']) ? $res['data']['link'] : $exiturl;
        Helper::saveLog('5men gamelaunchfinal', 53, json_encode($response), $gameurl);
        return $gameurl;   
      
        
    }
    public static function PlayStarLaunchURl($data){
        $client_details = ProviderHelper::getClientDetails('token',$data['token']);
        $url = config('providerlinks.playstar.api_url').'/launch/?host_id='.config('providerlinks.playstar.host_id')[$client_details->default_currency].'&game_id='.$data['game_code'].'&lang=en-US&access_token='.$data['token'];
        return $url;
    }

    public static function NoLimitLaunchUrl($data,$device){
        try {
        $client_details =ProviderHelper::getClientDetails('token',$data['token']);
        if($client_details->operator_id == '20'){
            $operator = config("providerlinks.nolimit.".$client_details->operator_id.".operator");
        }else if ($client_details->operator_id == '15'){
            $operator = config("providerlinks.nolimit.".$client_details->operator_id.".operator");
        }else{ // TigerGames
            $operator = config("providerlinks.nolimit.".$client_details->operator_id.".operator");
        }
        $url = config("providerlinks.nolimit.api_url").'device=mobile&lobbyUrl='.$data['exitUrl'].'&hideExitButton=false'.'&language='.$data['lang'].'&operator='.config("providerlinks.nolimit.operator").'&game='.$data['game_code'].'&token='.$data['token'];
        return $url;
        } catch (\Exception $e) {

            Helper::saveLog('Nolimit Gameluanch error', 23, json_encode('unable to launch'), $e->getMessage() );
            return $e->getMessage();
        }
        
    }

    public static function BGamingLaunchUrl($request_data, $device){
        Helper::saveLog('Bgaming create session', 49, json_encode($request_data), "BGamingLaunchUrl");
        $client_player_details = Providerhelper::getClientDetails('token', $request_data['token']);
        /* CREATE SESSION REQUEST */
        list($registration_date, $registration_time) = explode(" ", $client_player_details->created_at);
       
        // default tigergames
        $casinoId = config("providerlinks.bgaming.CASINO_ID"); 
        $gcp_URL = config("providerlinks.bgaming.GCP_URL");
        $auth_token = config("providerlinks.bgaming.AUTH_TOKEN");

        // if(isset(config("providerlinks.bgaming.")[$client_player_details->operator_id])){
        $casinoId = config("providerlinks.bgaming.".$client_player_details->operator_id.".CASINO_ID");
        $gcp_URL = config("providerlinks.bgaming.".$client_player_details->operator_id.".GCP_URL");
        $auth_token = config("providerlinks.bgaming.".$client_player_details->operator_id.".AUTH_TOKEN");
        // }

        // if($client_player_details->operator_id == 1){
        //     $casinoId = config("providerlinks.bgaming.KONI_CASINO_ID"); 
        //     $gcp_URL = config("providerlinks.bgaming.KONI_GCP_URL");
        //     $auth_token = config("providerlinks.bgaming.KONI_AUTH_TOKEN");
        // }
      
        // $casinoId = config("providerlinks.bgaming.KONIBET");
        Helper::saveLog('Bgaming create session', 49, json_encode($casinoId), $casinoId);
        $requesttosend = [
            "casino_id" => $casinoId,
            "game" => $request_data['game_code'],
            "currency" => $client_player_details->default_currency,
            "locale" => $request_data['lang'],
            "ip" => $request_data['ip_address'],
            "client_type" => $device,
            "urls" => [
                "deposit_url" => $request_data['exitUrl'],
                "return_url" => $request_data['exitUrl']
            ],
            "user" => [
                "id" => $client_player_details->player_id,
                "firstname" => $client_player_details->username,
                "lastname" => $client_player_details->username,
                "nickname" => $client_player_details->display_name,
                "city" => $request_data['country_code'],
                "country" => $request_data['country_code'],
                "date_of_birth" => "2021-01-29",
                "gender" => "m",
                "registered_at" => $registration_date
            ]
        ];
        $signature = hash_hmac('sha256',json_encode($requesttosend), $auth_token );
        Helper::saveLog('Bgaming create session', 499, json_encode($requesttosend), $casinoId);
        Helper::saveLog('Bgaming create session', 49, json_encode($requesttosend), $signature);
        $game_launch = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json',
                'X-REQUEST-SIGN' => $signature
            ]
        ]);
        Helper::saveLog('Bgaming create session', 49, json_encode($requesttosend), $game_launch);
        $game_launch_response = $game_launch->post($gcp_URL."/sessions",
                ['body' => json_encode($requesttosend)]
            );
        
        Helper::saveLog('Bgaming Launhing', 49, json_encode($request_data), json_encode($game_launch_response));
        $game_launch_url = json_decode($game_launch_response->getBody()->getContents());
        
        return $game_launch_url->launch_options->game_url;        
    }


    public static function SmartsoftLaunchUrl($data){
        $client_details = ProviderHelper::getClientDetails('token',$data['token']);
        $portal = config('providerlinks.smartsoft.PortalName');
        $game_details = Helper::findGameDetails("game_code",60, $data["game_code"]);
        $game_type = $game_details->info;
        $url = config('providerlinks.smartsoft.api_url').'/Loader.aspx?GameCategory='.$game_type.'&GameName='.$data['game_code'].'&Token='.$data['token'].'&PortalName='.$portal.'&Lang=en&ReturnUrl='.$data["exitUrl"];
        ProviderHelper::saveLogGameLaunch('Smartsoft Gameluanch ', 60, json_encode($data), 'Endpoint Hit');
        return $url;
    }
    public static function pngLaunchUrl($game_code,$token,$provider,$exitUrl,$lang,$device){
        if($device != 'desktop'){
            $device = 'mobile';
        }
        $client_details = ProviderHelper::getClientDetails('token', $token);
        $timestamp = Carbon::now()->timestamp;
        $exit_url = $exitUrl;
        $lang = GameLobby::getLanguage("PlayNGo",$lang);
        Helper::savePLayerGameRound($game_code,$token,$provider);
        $pid = ($client_details->operator_id == 17) ? config('providerlinks.png.pid2') : config('providerlinks.png.pid');
        // $gameurl = config('providerlinks.png.root_url').'/casino/ContainerLauncher?pid='.$pid.'&gid='.$game_code.'&channel='.$device.'&lang='.$lang.'&practice='.config('providerlinks.png.practice').'&ticket='.$token.'&origin='.$exit_url;
        // return $gameurl;
        $key = "LUGTPyr6u8sRjCfh";
        $aes = new AES($key);
        $data = array(
            'root_url' => config('providerlinks.png.root_url'),
            'exitUrl' => $exit_url,
            'ticket' => $token,
            'game_code' => $game_code,
            'pid' => $pid,
            'lang' => $lang,
            'practice' => config('providerlinks.png.practice'),
            'channel' => $device
        );
        $encoded_data = $aes->AESencode(json_encode($data));
        $urlencode = urlencode(urlencode($encoded_data));

        // if ($client_details->operator_id == 1){
            $gameurl = config('providerlinks.play_tigergames').'/api/playngo/tgload/'.$urlencode; 
        // }else{
        //    $gameurl = config('providerlinks.play_betrnk').'/api/playngo/load/'.$urlencode; 
        // }
        return $gameurl;
    }
    public static function edpLaunchUrl($game_code,$token,$provider,$exitUrl){
        $profile = "nofullscreen_money.xml";
        $sha1key = sha1($exitUrl.''.config("providerlinks.endorphina.nodeId").''.$profile.''.$token.''.config("providerlinks.endorphina.secretkey"));
        $sign = $sha1key; 
        ProviderHelper::saveLogGameLaunch('GAMELAUNCH EDP', 11, json_encode(config("providerlinks.endorphina.url").'?exit='.$exitUrl.'&nodeId='.config("providerlinks.endorphina.nodeId").'&profile='.$profile.'&token='.$token.'&sign='.$sign), json_encode($sign));
        return config("providerlinks.endorphina.url").'?exit='.$exitUrl.'&nodeId='.config("providerlinks.endorphina.nodeId").'&profile='.$profile.'&token='.$token.'&sign='.$sign;
    }

    public static function JustPlayLaunchURl($data){
        $client_details = ProviderHelper::getClientDetails('token',$data['token']);
        $time = microtime(true);
        $gg = 'id_user='.JustPlayHelper::changeEnvironment(($client_details))['id_user'].'time='.$time.'&id_customer='.$client_details->player_id.'&balance='.$client_details->balance.'&callback_url='.config('providerlinks.oauth_mw_api.mwurl').'/api/justplay/callback&id_game='.$data['game_code'];
        parse_str($gg, $outputArray);
        $url = config('providerlinks.justplay.api_url').'/api/v1/login?id_user='.JustPlayHelper::changeEnvironment(($client_details))['id_user'].'time='.$time.'&id_customer='.$client_details->player_id.'&balance='.$client_details->balance.'&callback_url='.config('providerlinks.oauth_mw_api.mwurl').'/api/justplay/callback&id_game='.$data['game_code'].'&Hash='.JustPlayHelper::createHash($outputArray,$client_details);
        $http = new Client();
        $response = $http->get($url);
        $get_url = json_decode($response->getBody()->getContents());
        return $get_url->url;

    }
    public static function microgamingLaunchUrl($game_code,$token,$provider,$exitUrl){
        $client_details = ProviderHelper::getClientDetails('token', $token);
        Helper::savePLayerGameRound($game_code,$token,$provider);
        $url = MGHelper::launchGame($token,$client_details->player_id,$game_code);
        return $url;
    }public static function upgLaunchUrl($game_code,$token,$provider,$exitUrl){
        $client_details = ProviderHelper::getClientDetails('token', $token);
        Helper::savePLayerGameRound($game_code,$token,$provider);
        $url = MGHelper::launchGame($token,$client_details->player_id,$game_code);
        return $url;
    }
    public static function evolutionLaunchUrl($game_code,$token,$provider,$exitUrl,$player_ip,$lang){
        $client_details = ProviderHelper::getClientDetails('token', $token);
        $lang = GameLobby::getLanguage("EvolutionGaming Direct",$lang);
        Helper::savePLayerGameRound($game_code,$token,$provider);
        $url = EVGHelper::gameLaunch($token,$player_ip,$game_code,$lang,$exitUrl,config('providerlinks.evolution.env'));
        return $url;
    }
    public static function boleLaunchUrl($game_code,$token,$game_provider,$exitUrl, $country_code='PH'){

        $client_details = ProviderHelper::getClientDetails('token', $token);
        if($client_details != null){
            $AccessKeyId = config('providerlinks.bolegaming.'.$client_details->default_currency.'.AccessKeyId');
            $access_key_secret = config('providerlinks.bolegaming.'.$client_details->default_currency.'.access_key_secret');
            $app_key = config('providerlinks.bolegaming.'.$client_details->default_currency.'.app_key');
            $login_url = config('providerlinks.bolegaming.'.$client_details->default_currency.'.login_url');
            $logout_url = config('providerlinks.bolegaming.'.$client_details->default_currency.'.logout_url');
        }else{
            return false;
        }

        $scene_id = '';
        if(strpos($game_code, 'slot') !== false) {
            if($game_code == 'slot'){
                $scene_id = "";
                $game_code = 'slot'; 
            }else{
                $game_code = explode("_", $game_code);
                $scene_id = $game_code[1];
                $game_code = 'slot'; 
            }
        }else{
            $game_code = $game_code;
        }

        $nonce = rand();
        $timestamp = time();
        $key = $access_key_secret.$nonce.$timestamp;
        $signature = sha1($key);
        $sign = [
            "timestamp" => $timestamp,
            "nonce" => $nonce,
            "signature" => $signature,
        ];
        try {
            $http = new Client();
            $data = [
                'game_code' => $game_code,
                'scene' => $scene_id,
                'player_account' => $client_details->player_id,
                'country'=> $country_code,
                'ip'=> $_SERVER['REMOTE_ADDR'],
                'AccessKeyId'=> $AccessKeyId,
                'Timestamp'=> $sign['timestamp'],
                'Nonce'=> $sign['nonce'],
                'Sign'=> $sign['signature'],
                //'op_pay_url' => 'http://middleware.freebetrnk.com/public/api/bole/wallet',
                'op_race_return_type' => 1, // back to previous game
                'op_return_type' => 3, //hide home button for games test
                //'op_home_url' => 'https://demo.freebetrnk.com/casino', //hide home button for games test
                'ui_hot_list_disable' => 1, //hide latest game menu
                'ui_category_disable' => 1 //hide category list
            ];
            $response = $http->post($login_url, [
                'form_params' => $data,
            ]);
            $client_response = json_decode($response->getBody()->getContents());
            ProviderHelper::saveLogGameLaunch('GAMELAUNCH BOLE', 11, json_encode($data), json_decode($response->getBody()));
            return isset($client_response->resp_data->url) ? $client_response->resp_data->url : false;
        } catch (\Exception $e) {
            return false;        
        }
        
    }

    public static function evoplayLunchUrl($token,$game_code,$game_provider,$exit_url,$lang){
        $client_player_details = GameLobby::getClientDetails('token', $token);

        $customToken = $token.'TIGER'.$client_player_details->player_id;

        $requesttosend = [
          "project" => config('providerlinks.evoplay.project_id'),
          "version" => 1,
          "token" => $customToken ,
          "game" => $game_code, //game_code, game_id
          "settings" =>  [
            'user_id'=> $client_player_details->player_id,
            'language'=> $lang,
            'https' => true,
            'exit_url' => isset($exit_url) ? $exit_url : ""
          ],
          "denomination" => '1', // game to be launched with values like 1.0, 1, default
          "currency" => $client_player_details->default_currency,
          "return_url_info" => true, // url link
          "callback_version" => 2, // POST CALLBACK
        ];
        $signature =  ProviderHelper::getSignature($requesttosend, config('providerlinks.evoplay.secretkey'));
        $requesttosend['signature'] = $signature;
        ProviderHelper::saveLogGameLaunch('GAMELAUNCH EVOPLAY', 15, json_encode($requesttosend), json_encode($requesttosend));
        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        ]);

        try {
             $response = $client->post(config('providerlinks.evoplay.api_url').'/game/geturl',[
                'form_params' => $requesttosend,
            ]);
        } catch (\Exception $e) {
            ProviderHelper::saveLogGameLaunch('GAMELAUNCH EVOPLAY', 15, json_encode($requesttosend), $e->getMessage());
        }
       
        $res = json_decode($response->getBody(),TRUE);
        ProviderHelper::saveLogGameLaunch('8Provider GAMELAUNCH EVOPLAY', 15, json_encode($requesttosend), json_decode($response->getBody()));
        return isset($res['data']['link']) ? $res['data']['link'] : false;
    }

     public static function awsLaunchUrl($token,$provider,$game_code,$lang='en',$exit_url=null){
        $client_details = ProviderHelper::getClientDetails('token', $token);
        if($client_details == 'false'){
            return 'false';
        }
        $provider_reg_currency = Providerhelper::getProviderCurrency(21, $client_details->default_currency);
        if($provider_reg_currency == 'false'){
            return 'false';
        }
        if(!AWSHelper::findMerchantIdByClientId($client_details->client_id)){
            return 'false';
        }
        $merchant_id = AWSHelper::findMerchantIdByClientId($client_details->client_id)['merchant_id'];
        $player_check = AWSHelper::playerCheck($token);
        if(!$player_check){
            ProviderHelper::saveLogGameLaunch('AWS Launch Game Failed', 21, json_encode($client_details), $client_details);
            return 'false';
        }
        
        if($player_check->code == 100){ // Not Registered!
            $register_player = AWSHelper::playerRegister($token);
            if($register_player->code != 2217 && $register_player->code != 0){
                 ProviderHelper::saveLogGameLaunch('AWS Launch Game Failed', 21, json_encode($register_player), $register_player);
                 return 'false';
            }
        }
        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json',
            ]
        ]);
        $requesttosend = [
            "merchantId" => $merchant_id,
            "currency" => $client_details->default_currency,
            "currentTime" => AWSHelper::currentTimeMS(),
            "username" => $merchant_id.'_TG'.$client_details->player_id,
            "playmode" => 0, // Mode of gameplay, 0: official
            "device" => 1, // Identifying the device. Device, 0: mobile device 1: webpage
            "gameId" => $game_code,
            "language" => $lang,
        ];
        $requesttosend['sign'] = AWSHelper::hashen($requesttosend, $client_details->player_token);
        $requesttosend['merchHomeUrl'] = isset($exit_url) ? $exit_url : "www.google.com";
        $guzzle_response = $client->post(config('providerlinks.aws.api_url').'/api/login',
            ['body' => json_encode($requesttosend)]
        );
        $provider_response = json_decode($guzzle_response->getBody()->getContents());
        ProviderHelper::saveLogGameLaunch('AWS Launch Game', 21, json_encode($requesttosend), $provider_response);
        return isset($provider_response->data->gameUrl) ? $provider_response->data->gameUrl : 'false';
    }

    public static function betrnkLaunchUrl($token){

        $http = new Client();
        $player_details = GameLobby::playerDetailsCall($token);
        $response = $http->post('http://betrnk-lotto.com/api/v1/index.php', [
            'form_params' => [
                'cmd' => 'auth', // auth request command
                'username' => 'freebetrnk',  // client subscription acc
                'password' => 'w34KM)!##$$#',
                'merchant_user'=> $player_details->playerdetailsresponse->username,
                'merchant_user_balance'=> $player_details->playerdetailsresponse->balance,
            ],
        ]);

        $game_url = json_decode((string) $response->getBody(), true)["response"]["game_url"];
        return $game_url.'&player_token='.$token;
    }

    public static function rsgLaunchUrl($game_code,$token,$exitUrl,$lang='en', $provider_sub_name, $device){

        // $gameinfo = DigitainHelper::findGameDetails(config;('providerlinks.digitain.provider_db_id'), $game_code);
        $gameId = $game_code;
        if ($device == 'mobile'){
            $gameinfo = DigitainHelper::HasMobileGameCode($game_code);
            if ($gameinfo != false){
                $gameId = $gameinfo;
            }else{
                $gameId = $game_code;
            }
        }  

        $url = $exitUrl;
        $domain = parse_url($url, PHP_URL_HOST);
        Helper::savePLayerGameRound($game_code,$token,$provider_sub_name);
        $url = config('providerlinks.digitain.api_url').'/GamesLaunch/Launch?gameid='.$gameId.'&playMode=real&token='.$token.'&deviceType=1&lang='.$lang.'&operatorId='.config('providerlinks.digitain.operator_id').'&mainDomain='.$domain.'';
        return $url;
    }

    

    public static function skyWindLaunch($game_code, $token){
        $player_login = SkyWind::userLogin();

        $client_details = ProviderHelper::getClientDetails('token', $token, 2);

        ProviderHelper::saveLogGameLaunch('Skywind Game Launch', config('providerlinks.skywind.provider_db_id'), json_encode($client_details), $client_details);

        $client = new Client([
              'headers' => [ 
                  'Content-Type' => 'application/json',
                  'X-ACCESS-TOKEN' => $player_login->accessToken,
              ]
        ]);
        // $url = ''.config('providerlinks.skywind.api_url').'/fun/games/'.$game_code.'';
         // $url = ''.config('providerlinks.skywind.api_url').'/players/'.config('providerlinks.skywind.seamless_username').'/games/'.$game_code.'?playmode=real&ticket='.$token.'';

        // TG8_98
        // $url = ''.config('providerlinks.skywind.api_url').'/players/TG'.$client_details->client_id.'_'.$client_details->player_id.'/games/'.$game_code.'?playmode=real&ticket='.$token.'';
        
        $url = 'https://api.gcpstg.m27613.com/v1/players/TG'.$client_details->client_id.'_'.$client_details->player_id.'/games/'.$game_code.'?playmode=real&ticket='.$token.'';
        try {

            $response = $client->get($url);
            $response = json_encode(json_decode($response->getBody()->getContents()));
            ProviderHelper::saveLogGameLaunch('Skywind Game Launch = '.$url, config('providerlinks.skywind.provider_db_id'), $response, $url);
            $url = json_decode($response, true);
            return isset($url['url']) ? $url['url'] : 'false';
            
        } catch (\Exception $e) {
            ProviderHelper::saveLogGameLaunch('Skywind Game Launch Failed = '.$url, config('providerlinks.skywind.provider_db_id'), json_encode($player_login), $e->getMessage());
            return 'false';
        }
    }

    public static function cq9LaunchUrl($game_code, $token, $provider_sub_name,$lang){
        Helper::savePLayerGameRound($game_code,$token,$provider_sub_name);
        $client_details = ProviderHelper::getClientDetails('token', $token);
        // $player_details = Providerhelper::playerDetailsCall($client_details->player_token);
        $api_tokens = config('providerlinks.cqgames.api_tokens');
        if(array_key_exists($client_details->default_currency, $api_tokens)){
            $auth = $api_tokens[$client_details->default_currency];
            // $auth = $api_tokens['USD'];
        }else{
            return 'false';
        }
        $client = new Client([
            'headers' => [ 
                'Authorization' => $auth,
                // 'Authorization' => config('providerlinks.cqgames.api_token'),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        ]);
        $requesttosend = [
            'account'=> 'TG'.$client_details->client_id.'_'.$client_details->player_id,
            'gamehall'=> 'cq9',
            'gamecode'=> $game_code,
            'gameplat'=> 'WEB',
            'lang'=> $lang,
        ];
        $response = $client->post(config('providerlinks.cqgames.api_url').'/gameboy/player/sw/gamelink', [
            'form_params' => $requesttosend,
        ]);
        $game_launch = json_decode((string)$response->getBody(), true);
        ProviderHelper::saveLogGameLaunch('CQ9 Game Launch', config('providerlinks.cqgames.pdbid'), json_encode($game_launch), $requesttosend);
        foreach ($game_launch as $key => $value) {
            $url = isset($value['url']) ? $value['url'] : 'false';
            return $url;
        }
    }
    
    public static function kaGamingLaunchUrl($game_code,$token,$exitUrl,$lang='en'){
        // $domain = parse_url($url, PHP_URL_HOST);
        $client_details = Providerhelper::getClientDetails('token', $token);
        if(in_array($client_details->client_id, [92])){
            $url = '-1';
        }else{
           $url = $exitUrl; 
        }
        $url = ''.config('providerlinks.kagaming.gamelaunch').'/?g='.$game_code.'&p='.config('providerlinks.kagaming.partner_name').'&u='.$client_details->player_id.'&t='.$token.'&cr='.$client_details->default_currency.'&loc='.$lang.'&t='.$token.'&l='.$url.'&da='.$client_details->username.'&tl=TIGERGAMES'.'&ak='.config('providerlinks.kagaming.access_key').'';
        return $url;
    }

    public static function saGamingLaunchUrl($game_code,$token,$exitUrl,$lang='en'){
        $url = $exitUrl;
        $lang = SAHelper::lang($lang);
        $domain = parse_url($url, PHP_URL_HOST);
        $client_details = Providerhelper::getClientDetails('token', $token);
        if(!empty($client_details)){
            $check_user = SAHelper::userManagement(config('providerlinks.sagaming.prefix').$client_details->player_id, 'VerifyUsername');
            if(isset($check_user->IsExist) && $check_user->IsExist == true){
                $login_token = SAHelper::userManagement(config('providerlinks.sagaming.prefix').$client_details->player_id, 'LoginRequest');
            }else{
                $check_user = SAHelper::userManagement(config('providerlinks.sagaming.prefix').$client_details->player_id, 'RegUserInfo');
                $login_token = SAHelper::userManagement(config('providerlinks.sagaming.prefix').$client_details->player_id, 'LoginRequest');
            }

            if(isset($login_token['Token'])){
                $url = 'https://www.sai.slgaming.net/app.aspx?username='.config('providerlinks.sagaming.prefix').$client_details->player_id.'&token='.$login_token['Token'].'&lobby='.config('providerlinks.sagaming.lobby').'&lang='.$lang.'&returnurl='.$url.'';
                return $url;
            }else{
                return false;
            }
           
        }else{
            return false;
        }
       
    }

    public static function CrashGaming($data){
        try{
            $url = 'https://dev.crashbetrnk.com/gamelaunch?authtoken='.config('providerlinks.crashgaming.authToken');
            $client_details = Providerhelper::getClientDetails('token', $data['token']);
            $requesttosend = [
                'session_id' =>  $client_details->player_token,
                'user_id' => (string)$client_details->player_id,
                'user_name' => (string)$client_details->player_id,
                'currency_code' => $client_details->default_currency,
                'balance' => (int)$client_details->balance,
                'uuid' => $client_details->player_token,
                'exit_url' => isset($data['exitUrl']) ? $data['exitUrl'] : ""
            ];
            $client = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.config('providerlinks.crashgaming.authToken')
                ]
            ]);
            $guzzle_response = $client->post($url,['body' => json_encode($requesttosend)]
            );
            $client_response = json_decode($guzzle_response->getBody()->getContents());
            ProviderHelper::saveLogGameLaunch('CrashGaming', config('providerlinks.crashgaming.pdbid'), json_encode($requesttosend), $client_response);
            return $client_response->url;
            // return $client_response->link;
            // $url = 'https://dev.crashbetrnk.com/?session_id=y0ce41415db035cf889d6953ce18ef26';
            // $url = 'https://dev.crashbetrnk.com/?session_id='.$client_details->player_token;
            return $url;
        }catch(\Exception $e){
            ProviderHelper::saveLogGameLaunch('CrashGaming', config('providerlinks.crashgaming.pdbid'), json_encode($requesttosend), $e->getMessage().' '.$e->getLine());
            return false;
        }
    }

    public static function tidylaunchUrl( $game_code = null, $token = null,  $game_provider = null ,$exit_url){
        Helper::saveLog('Tidy Gameluanch', 23, "", "");
        try{
            $url = config('providerlinks.tidygaming.url_lunch');
            $client_details = Providerhelper::getClientDetails('token', $token);

            $supportClientPrefix_k = config('providerlinks.tidygaming.support_1to1_denomination_prefixK');
            $currency = $client_details->default_currency;
            if (in_array( $client_details->client_id, $supportClientPrefix_k)) {
               $currency = "k".$client_details->default_currency;
            }
            $invite_code = config('providerlinks.tidygaming.currency')[$currency];
            $get_code_currency = TidyHelper::currencyCode($currency);
            // $player_details = Providerhelper::playerDetailsCall($client_details->player_token);
            $requesttosend = [
                'client_id' =>  config('providerlinks.tidygaming.client_id'),
                'game_id' => $game_code,
                'username' => 'TGOP_' . $client_details->player_id,
                'token' => $token,
                'uid' => 'TGOP_'.$client_details->player_id,
                'currency' => $get_code_currency,
                'invite_code' => $invite_code,
                'back_url' => $exit_url
            ];
            $client = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.TidyHelper::generateToken($requesttosend)
                ]
            ]);
            $guzzle_response = $client->post($url,['body' => json_encode($requesttosend)]
            );
            $client_response = json_decode($guzzle_response->getBody()->getContents());
            ProviderHelper::saveLogGameLaunch('Tidy Gameluanch 102', 23, json_encode($requesttosend), $client);
            if(isset($client_response->link)){
                return $client_response->link;
            } 
            return false;
        }catch(\Exception $e){
            $requesttosend = [
                'error' => 1010
            ];
            ProviderHelper::saveLogGameLaunch('Tidy Gameluanch 101', 23, json_encode($requesttosend), $e->getMessage() );
            // return $e->getMessage();
            return false;
        }
        
    }

     public static function slotmill($request){
        try {
            $client_details = Providerhelper::getClientDetails('token', $request["token"]);
            $exit_url = $request["exitUrl"] ? $request["exitUrl"] : "";
            $getGameDetails = Helper::findGameDetails( "game_code",config('providerlinks.slotmill.provider_db_id'), $request['game_code']);
            // $url = config("providerlinks.slotmill")[$request["game_code"]]; 
            // if ($request["game_code"] == "19002") {
            //    $url = config("providerlinks.slotmill.treasures"); 
            // } elseif ($request["game_code"] == "19003") {
            //    $url = config("providerlinks.slotmill.starspell"); 
            // } elseif ($request["game_code"] == "19005") {
            //    $url =  config("providerlinks.slotmill.wildfire"); 
            // } elseif ($request["game_code"] == "19007") {
            //    $url =  config("providerlinks.slotmill.vikings"); 
            // } elseif ($request["game_code"] == "19008") {
            //    $url =  config("providerlinks.slotmill.outlaws"); 
            // }
            return $url = $getGameDetails->info."/?language=".$request["lang"]."&org=".config("providerlinks.slotmill.brand")."&currency=".$client_details->default_currency."&key=".$client_details->player_token."&homeurl=".$exit_url;

        } catch (\Exception $e){
            return $request["exitUrl"];
        }
    }

    public static function pgvirtual($request){
        // $client_details = Providerhelper::getClientDetails('token', $request["token"]);
        
        // $pg_uuid = Uuid::generate()->string;
        // if(Helper::isValidUUID($pg_uuid)) {
        //     // $pg_uuid = str_replace("-","",$pg_uuid);
        //     // Helper::playerGameRoundUuid($request["game_code"],$request["token"],$request["game_provider"],$pg_uuid);
        //     Helper::playerGameRoundUuid($request["game_code"],$pg_uuid,$request["game_provider"],$request["token"]);
        //     // Helper::playerGameRoundUuid($request["game_code"],$pg_uuid,$request["game_provider"],$request["lang"]);
        //     $url = config("providerlinks.pgvirtual.game_url").$pg_uuid; 
        //     return $url;
        // } else {
        //     Helper::saveLog('PGVirtual gamelaunch invalid uuid',47, $request["token"], $pg_uuid);
        //     return $request["exitUrl"];
        // }
        $client_details = Providerhelper::getClientDetails('token', $request["token"]);
        
        $pg_uuid = Uuid::generate()->string;
        if(Helper::isValidUUID($pg_uuid)) {
            Helper::createPGVitualGameRoundSessionRound($request["game_code"],$request["token"],$request["game_provider"],$pg_uuid,$client_details);
            Helper::createPGVitualGameRoundSession($request["game_code"],$request["game_provider"],$pg_uuid,$client_details);
            $url = config("providerlinks.pgvirtual.game_url").$pg_uuid; 
            return $url;
        } else {
            Helper::saveLog('PGVirtual gamelaunch invalid uuid',47, $request["token"], $pg_uuid);
            return $request["exitUrl"];
        }
        
    }

    public static function tgglaunchUrl( $game_code = null, $token = null,$exitUrl = null){
        $client_player_details = Providerhelper::getClientDetails('token', $token);
        $requesttosend = [
          "project" => config('providerlinks.tgg.project_id'),
          "version" => 1,
          "token" => $token,
          "game" => $game_code, //game_code, game_id
          "settings" =>  [
            'user_id'=> $client_player_details->player_id,
            'language'=> $client_player_details->language ? $client_player_details->language : 'en',
            'https' => 1,
            'platform' => 'mobile',
            'exit_url' => $exitUrl
          ],
          "denomination" => 'default', // game to be launched with values like 1.0, 1, default
          "currency" => $client_player_details->default_currency,
          "return_url_info" => 1, // url link
          "callback_version" => 2, // POST CALLBACK
        ];
        $signature =  ProviderHelper::getSignature($requesttosend, config('providerlinks.tgg.api_key'));
        $requesttosend['signature'] = $signature;
        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        ]);
        $response = $client->post(config('providerlinks.tgg.api_url').'/game/geturl',[
            'form_params' => $requesttosend,
        ]);
        $res = json_decode($response->getBody(),TRUE);
        ProviderHelper::saveLogGameLaunch('TGG GAMELAUNCH TOPGRADEGAMES', 29, json_encode($requesttosend), json_decode($response->getBody()));
        return isset($res['data']['link']) ? $res['data']['link'] : false;
        
    }

    public static function pgsoftlaunchUrl( $game_code = null, $token = null){
        $operator_token = config('providerlinks.pgsoft.operator_token');
        $url = "https://m.pg-redirect.net/".$game_code."/index.html?language=en-us&bet_type=1&operator_token=".urlencode($operator_token)."&operator_player_session=".urlencode($token);
        ProviderHelper::saveLogGameLaunch('PGSOFT GAMELAUNCH', 55, json_encode($url),$url);
        return $url;
    }

    public static function boomingGamingUrl($data, $provider_name, $device){
        ProviderHelper::saveLogGameLaunch('Booming session ', config('providerlinks.booming.provider_db_id'), json_encode($data), "ENDPOINT HIT");
        $url = config('providerlinks.booming.api_url').'/v2/session';
        $client_details = ProviderHelper::getClientDetails('token',$data["token"]);
        Helper::savePLayerGameRoundBooming($data["game_code"],$data["token"],$provider_name);
        try{
         
            $nonce = $client_details->token_id;

            $requesttosend = array (
                'game_id' => $data["game_code"],
                'balance' => $client_details->balance,
                'locale' => 'en',
                'variant' => "mobile", // mobile, desktop
                'currency' => $client_details->default_currency,
                'player_id' => 'TG_'.$client_details->player_id,
                'callback' =>  config('providerlinks.booming.call_back'),
                'rollback_callback' =>  config('providerlinks.booming.roll_back')
            );

            $sha256 =  hash('sha256', json_encode($requesttosend, JSON_FORCE_OBJECT));
            $concat = '/v2/session'.$nonce.$sha256;
            $secrete = hash_hmac('sha512', $concat, config('providerlinks.booming.api_secret'));
            $client = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/vnd.api+json',
                    'X-Bg-Api-Key' => config('providerlinks.booming.api_key'),
                    'X-Bg-Nonce'=> $nonce,
                    'X-Bg-Signature' => $secrete
                ]
            ]);
            $guzzle_response = $client->post($url,  ['body' => json_encode($requesttosend)]);
            $client_response = json_decode($guzzle_response->getBody()->getContents());
           
            return $client_response->play_url;

        }catch(\Exception $e){
            ProviderHelper::saveLogGameLaunch('Booming session error', config('providerlinks.booming.provider_db_id'), json_encode($data), $e->getMessage());
            return false;
        }

    }

    public static function spadeLaunch($game_code,$token,$exitUrl,$lang='en_US'){
        $client_details = ProviderHelper::getClientDetails('token', $token);
        $domain =  $exitUrl;
        // $url = 'https://lobby.silverkirinplay.com/TIGERG/auth/?acctId=TIGERG_'.$client_details->player_id.'&language='.$lang.'&token='.$token.'&game='.$game_code.'';
        $url = 'https://lobby-egame-staging.sgplay.net/TIGERG/auth/?acctId=TIGERG_'.$client_details->player_id.'&language='.$lang.'&token='.$token.'&game='.$game_code.'';
        return $url;
    }
    
    public static function majagamesLaunch($game_code,$token){
        try{
            if($game_code == 'tapbet'){
                //arcade game
                $client_details = ProviderHelper::getClientDetails('token',$token);
                $requesttosend = [
                    'player_unique_token' => $token.'_'.$client_details->player_id,
                    'player_name' => $client_details->username,
                    'currency' => $client_details->default_currency,
                    'is_demo' => false,
                    'language' =>  "en"
                ];
                $client = new Client([
                    'headers' => [ 
                        'Authorization' => config('providerlinks.majagames.auth')
                    ]
                ]);
                $guzzle_response = $client->post(config('providerlinks.majagames.tapbet_api_url').'/launch-game',  ['form_params' => $requesttosend]);
                $client_response = json_decode($guzzle_response->getBody()->getContents());
                return $client_response->data->game_url;
            }else { 
                // this is for slot game
                $client_details = ProviderHelper::getClientDetails('token',$token);
                $requesttosend = [
                    'player_unique_id' => config('providerlinks.majagames.prefix').$client_details->player_id,
                    'player_name' => $client_details->username,
                    'player_currency' => $client_details->default_currency,
                    'game_id' => $game_code,
                    'is_demo' => false,
                    'agent_code' =>  config('providerlinks.majagames.prefix').$client_details->player_id,
                    'agent_name' =>  $client_details->username
                ];
                $client = new Client([
                    'headers' => [ 
                        'Authorization' => config('providerlinks.majagames.auth')
                    ]
                ]);
                $guzzle_response = $client->post(config('providerlinks.majagames.api_url').'/launch-game',  ['form_params' => $requesttosend]);
                $client_response = json_decode($guzzle_response->getBody()->getContents());
                return $client_response->data->game_url;
            }
        }catch(\Exception $e){
            $error_code = [
                'error_code' => 500,
                'error_msg' => $e->getMessage()
            ];
            ProviderHelper::saveLogGameLaunch('MajaGames gamelaunch error', config('providerlinks.majagames.provider_id'), json_encode($error_code), $e->getMessage());
        }
        
    }

    public static function spadeCuracaoLaunch($game_code,$token,$lang){
        $client_details = ProviderHelper::getClientDetails('token', $token);
        $url = config('providerlinks.spade_curacao.lobby_url').'acctId=TIGERG_'.$client_details->player_id.'&language='.$lang.'&token='.$token.'&game='.$game_code.'';
        return $url;
    }
    public static function habanerolaunchUrl( $game_code = null, $token = null){
        // $brandID = "2416208c-f3cb-ea11-8b03-281878589203";
        // $apiKey = "3C3C5A48-4FE0-4E27-A727-07DE6610AAC8";
        $brandID = config('providerlinks.habanero.brandID');
        $apiKey = config('providerlinks.habanero.apiKey');
        $api_url = config('providerlinks.habanero.api_url');

        $client_details = Providerhelper::getClientDetails('token', $token);
        $player_details = Providerhelper::playerDetailsCall($client_details->player_token);

        // $url = "https://app-test.insvr.com/go.ashx?brandid=$brandID&keyname=$game_code&token=$token&mode=real&locale=en&mobile=0";
        $url = $api_url."brandid=$brandID&keyname=$game_code&token=$token&mode=real&locale=en&mobile=0";
        ProviderHelper::saveLogGameLaunch('HBN gamelaunch', 24, json_encode($url), "");
        return $url;
    }
    
    public static function pragmaticplaylauncher($game_code = null, $token = null, $data, $device)
    {
        $stylename = config('providerlinks.tpp.secureLogin');
        $key = config('providerlinks.tpp.secret_key');
        $gameluanch_url = config('providerlinks.tpp.gamelaunch_url');
        $casinoId = config('providerlinks.tpp.casinoId');
        $wsUri = config('providerlinks.tpp.wsUri');
        $host = config('providerlinks.tpp.host');

        $client_details = Providerhelper::getClientDetails('token', $token);
        $player_details = Providerhelper::playerDetailsCall($client_details->player_token);
        // $game_details = DB::select("SELECT * FROM games WHERE provider_id = '26' AND game_code = '".$game_code."' order by created  ");
        $game_details = DB::table('games')->where('provider_id','=',26)->where('game_code','=',$game_code)->orderBy('created_at','desc')->first();
        // $game_details = Helper::findGameDetails('game_code', 26, $game_code);
        if($device == 'desktop'){ 
            $device = 'WEB';
        }else{ 
            $device = 'MOBILE'; 
        }
        $userid = "TGaming_".$client_details->player_id;
        $currency = $client_details->default_currency;
        $hash = md5("currency=".$currency."&language=".$data['lang']."&lobbyUrl=".$data['exitUrl']."&platform=".$device."&secureLogin=".$stylename."&stylename=".$stylename."&symbol=".$game_code."&technology=H5&token=".$token."".$key);
        // $hashCreatePlayer = md5('currency='.$currency.'&externalPlayerId='.$userid.'&secureLogin='.$stylename.$key);
        try{
            $form_body = [
                "currency" => $currency,
                "language" => $data['lang'],
                "lobbyUrl" => $data['exitUrl'],
                "platform" => $device,
                "secureLogin" => $stylename,
                "stylename" => $stylename,
                "symbol" => $game_code,
                "technology" => "H5",
                "token" => $token,
                "hash" => $hash
            ];
            $client = new Client();
            $guzzle_response = $client->post($host,  ['form_params' => $form_body]);
            $client_response = json_decode($guzzle_response->getBody()->getContents());
            Helper::saveLog('Game Launch Pragmatic Play', 26, json_encode($form_body), json_encode($client_response));
            $url = $client_response->gameURL;
            return $url;

        
        }catch(\Exception $e){
            $msg = array(
                'err_message' => $e->getMessage(),
                'err_line' => $e->getLine(),
                'err_file' => $e->getFile()
            );
            ProviderHelper::saveLog('pragmatic gamelaunch err', 26, json_encode($msg), $e->getMessage());
            return $msg;
        }
        // $paramEncoded = urlencode("token=".$token."&symbol=".$game_code."&technology=H5&platform=WEB&language=en&lobbyUrl=daddy.betrnk.games");
        // $url = "$gameluanch_url?key=$paramEncoded&stylename=$stylename";
        // $result = json_encode($url);

        // $aes = new AES();
        // $data = array(
        //     'url' => $url,
        //     'wsUri' => $wsUri,
        //     'tableId' => $game_code,
        //     'casinoId' => $casinoId,
        // );
        // $encoded_data = $aes->AESencode(json_encode($data));
        // if($game_details->game_type_id == '15'){
        //     Helper::saveLog('start game url PP DGA', 26, $result,"$result");
        //     // return "http://play.betrnk.games:81/loadgame/pragmatic-play-dga?param=".urlencode($encoded_data);
        //     $url = config("providerlinks.play_betrnk")."/loadgame/pragmatic-play-dga?param=".urlencode($encoded_data);
        //     return $url;
        // }else{
        //     Helper::saveLog('start game url PP', 26, $result,"$result");
        // return $url;
        // }
    }

    public static function yggdrasillaunchUrl($data,$device){
        $provider_id = config("providerlinks.ygg.provider_id");
        if($device == 'desktop'){$channel = 'pc';}else{ $channel = 'mobile';}
        ProviderHelper::saveLogGameLaunch('YGG gamelaunch', $provider_id, json_encode($data), "Endpoing hit");
        $url = config("providerlinks.ygg.api_url");
        $org = config("providerlinks.ygg.Org");
        $client_details = ProviderHelper::getClientDetails('token',$data['token']);
        $player_details = Providerhelper::playerDetailsCall($client_details->player_token);
        try{
            $url = $url."gameid=".$data['game_code']."&lang=".$data['lang']."&currency=".$client_details->default_currency."&org=".$org."&channel=".$channel."&home=".$data['exitUrl']."&key=".$data['token'];
            ProviderHelper::saveLogGameLaunch('YGG gamelaunch', $provider_id, json_encode($data), $url);
            return $url;
        }catch(\Exception $e){
            $error = [
                'error' => $e->getMessage()
            ];
            ProviderHelper::saveLogGameLaunch('YGG gamelaunch', $provider_id, json_encode($data), $e->getMessage());
            return $error;
        }

    }

    public static function goldenFLaunchUrl($data){
        $key = "LUGTPyr6u8sRjCfh";
        $aes = new AES($key);
        $operator_token = config("providerlinks.goldenF.operator_token");
        $api_url = config("providerlinks.goldenF.api_url");
        $secret_key = config("providerlinks.goldenF.secrete_key");
        $provider_id = config("providerlinks.goldenF.provider_id");
        $client_details = ProviderHelper::getClientDetails('token',$data['token']);
        $player_id = $client_details->player_id;
        // $player_id = "TG_".$client_details->player_id;
        $nickname = $client_details->username;
        $response_bag = array();
       // if($client_details->wallet_id == 1){
            try{
                $http = new Client();
                $form_body = [
                    'secret_key' => GoldenFHelper::changeEnvironment($client_details)->secret_key,
                    'operator_token' => GoldenFHelper::changeEnvironment($client_details)->operator_token,
                    'player_name' => $client_details->player_id,
                    'currency' => $client_details->default_currency,
                ];
                $parameters = [
                    'form_body' => $form_body,
                    'url' => GoldenFHelper::changeEnvironment($client_details)->api_url."/Player/Create",
                ];
                $response = $http->post(GoldenFHelper::changeEnvironment($client_details)->api_url."/Player/Create",[
                   'form_params' => $form_body
                ]);
                $golden_response = json_decode((string) $response->getBody(), true);
                $response_bag["parameters"] = $parameters;
                $response_bag["golden_response"] = $golden_response;
                ProviderHelper::saveLogGameLaunch('GoldenF create_player', $provider_id, json_encode($parameters), $golden_response);
                if(isset($golden_response['data']['action_result']) && $golden_response['data']['action_result'] == "Success"){
                    $gameluanch_url = GoldenFHelper::changeEnvironment($client_details)->api_url."/Launch?secret_key=".GoldenFHelper::changeEnvironment($client_details)->secret_key."&operator_token=".GoldenFHelper::changeEnvironment($client_details)->operator_token."&game_code=".$data['game_code']."&player_name=".$player_id."&nickname=".$nickname."&language=".$client_details->language;
                    $response = $http->post($gameluanch_url);
                    $get_url = json_decode($response->getBody()->getContents());
                    $response_bag["gameluanch_url"] = $gameluanch_url;
                    $response_bag["get_url"] = $get_url;
                    // return $response_bag;
                    ProviderHelper::saveLogGameLaunch('GoldenF get_url', $provider_id, json_encode($get_url),$gameluanch_url);
                    if(isset($get_url->data->action_result) && $get_url->data->action_result == 'Success'){
                        // TransferWalletHelper::savePLayerGameRound($data['game_code'],$data['token'],$data['game_provider']); // Save Player Round
                        return $get_url->data->game_url;
                    }else{
                        // return config('providerlinks.play_betrnk').'/tigergames/api?msg='.ClientHelper::getClientErrorCode(10);
                        return config('providerlinks.play_betrnk').'/tigergames/api?msg=Something went wrong url failed';
                    }
                }else{
                    // return config('providerlinks.play_betrnk').'/tigergames/api?msg='.ClientHelper::getClientErrorCode(10);
                    return config('providerlinks.play_betrnk').'/tigergames/api?msg=Something went wrong in creation';
                }
            }catch(\Exception $e){
                $error = ['error' => $e->getMessage()];
                ProviderHelper::saveLogGameLaunch('GoldenF gamelaunch err', $provider_id, json_encode($data), $e->getMessage());
                // return config('providerlinks.play_betrnk').'/tigergames/api?msg='.ClientHelper::getClientErrorCode(10);
                return config('providerlinks.play_betrnk').'/tigergames/api?msg='.$e->getMessage();
            }
        // }else{
        //     // # IFRAME GAMELAUNCH    
        //     try{
        //         $http = new Client();
        //         $form_body = [
        //             'secret_key' => GoldenFHelper::changeEnvironment($client_details)->secret_key,
        //             'operator_token' => GoldenFHelper::changeEnvironment($client_details)->operator_token,
        //             'player_name' => "TG_".$client_details->player_id,
        //             'currency' => $client_details->default_currency,
        //         ];
        //         $parameters = [
        //             'form_body' => $form_body,
        //             'url' => GoldenFHelper::changeEnvironment($client_details)->api_url."/Player/Create",
        //         ];
        //         $response = $http->post(GoldenFHelper::changeEnvironment($client_details)->api_url."/Player/Create",[
        //         'form_params' => $form_body
        //         ]);
        //         $golden_response = json_decode((string) $response->getBody(), true);
        //         TransferWalletHelper::saveLog('GoldenF create_player', $provider_id, json_encode($parameters), $golden_response);
        //         if(isset($golden_response['data']['action_result']) && $golden_response['data']['action_result'] == "Success"){
        //             $gameluanch_url = GoldenFHelper::changeEnvironment($client_details)->api_url."/Launch?secret_key=".GoldenFHelper::changeEnvironment($client_details)->secret_key."&operator_token=".GoldenFHelper::changeEnvironment($client_details)->operator_token."&game_code=".$data['game_code']."&player_name=".$player_id."&nickname=".$nickname."&language=".$client_details->language;
        //             $response = $http->post($gameluanch_url);
        //             $get_url = json_decode($response->getBody()->getContents());

        //             if(isset($get_url->data->action_result) && $get_url->data->action_result == 'Success'){
        //                 TransferWalletHelper::savePLayerGameRound($data['game_code'],$data['token'],$data['game_provider']); // Save Player Round
        //                 TransferWalletHelper::saveLog('GoldenF gamelaunch', $provider_id, json_encode($data), $gameluanch_url);
        //                 $data = array(
        //                     "url" => urlencode($get_url->data->game_url),
        //                     "token" => $client_details->player_token,
        //                     "player_id" => $player_id,
        //                     // "system_player_id" => $client_details->player_id,
        //                     "exitUrl" => $data['exitUrl'],
        //                 );
        //                 $encoded_data = $aes->AESencode(json_encode($data));
        //                 return config('providerlinks.play_betrnk')."/loadgame/goldenf?param=".urlencode($encoded_data);
        //             }else{
        //                 return config('providerlinks.play_betrnk').'/tigergames/api?msg='.ClientHelper::getClientErrorCode(10);
        //             }
        //         }else{
        //             return config('providerlinks.play_betrnk').'/tigergames/api?msg='.ClientHelper::getClientErrorCode(10);
        //         }
        //     }catch(\Exception $e){
        //         $error = ['error' => $e->getMessage()];
        //         TransferWalletHelper::saveLog('GoldenF gamelaunch err', $provider_id, json_encode($data), $e->getMessage());
        //         return config('providerlinks.play_betrnk').'/tigergames/api?msg='.ClientHelper::getClientErrorCode(10);
        //     }
        // }
    }

    public static function iaLaunchUrl($game_code,$token,$exitUrl)
    {
        $player_details = Providerhelper::getClientDetails('token', $token);
        $provider_reg_currency = Providerhelper::getProviderCurrency(15, $player_details->default_currency);
        if($provider_reg_currency == 'false'){
            return 'false';
        }
        $username = config('providerlinks.iagaming.prefix').$player_details->client_id.'_'.$player_details->player_id;
        $currency_code = $player_details->default_currency; 
        $params = [
                "register_username" => $username,
                "lang" => 2,
                "currency_code" => $currency_code,
        ];
        $uhayuu = IAHelper::hashen($params);
        $header = ['pch:'. config('providerlinks.iagaming.pch')];
        $timeout = 5;
        $client_response = IAHelper::curlData(config('providerlinks.iagaming.url_register'), $uhayuu, $header, $timeout);
        ProviderHelper::saveLogGameLaunch('IA Launch Game', 15, json_encode($client_response), $params);
        $data = json_decode(IAHelper::rehashen($client_response[1], true));
        if($data->status): // IF status is 1/true //user already register
            $data = IAHelper::userlunch($username);
            return $data;
        else: // Else User is successfull register
            $data = IAHelper::userlunch($username);
            return $data;
        endif;  
    }

    public static function icgGameUrl($currency){
        $http = new Client();
        $response = $http->get(config("providerlinks.icgaminggames"), [
            'headers' =>[
                'Authorization' => 'Bearer '.GameLobby::icgConnect($currency),
                'Accept'     => 'application/json'
            ]
        ]);
        ProviderHelper::saveLogGameLaunch('GAMELAUNCH AUTH', 11, $currency, GameLobby::icgConnect($currency));
        return json_decode((string) $response->getBody(), true);
    }
    public static function icgConnect($currency){
        $http = new Client();
        switch($currency){
            case "JPY":
                $username = config("providerlinks.icgagents.jpyagents.username");
                $password = config("providerlinks.icgagents.jpyagents.password");
            break;
            case "CNY":
                $username = config("providerlinks.icgagents.cnyagents.username");
                $password = config("providerlinks.icgagents.cnyagents.password");
            break;
            case "EUR":
                $username = config("providerlinks.icgagents.euragents.username");
                $password = config("providerlinks.icgagents.euragents.password");
            break;
            case "KRW":
                $username = config("providerlinks.icgagents.krwagents.username");
                $password = config("providerlinks.icgagents.krwagents.password");
            break;
            case "PHP":
                $username = config("providerlinks.icgagents.phpagents.username");
                $password = config("providerlinks.icgagents.phpagents.password");
            break;
            case "THB":
                $username = config("providerlinks.icgagents.thbagents.username");
                $password = config("providerlinks.icgagents.thbagents.password");
            break;
            case "TRY":
                $username = config("providerlinks.icgagents.tryagents.username");
                $password = config("providerlinks.icgagents.tryagents.password");
            break;
            case "TWD":
                $username = config("providerlinks.icgagents.twdagents.username");
                $password = config("providerlinks.icgagents.twdagents.password");
            break;
            case "RUB":
                $username = config("providerlinks.icgagents.rubagents.username");
                $password = config("providerlinks.icgagents.rubagents.password");
            break;
            case "IRR":
                $username = config("providerlinks.icgagents.irragents.username");
                $password = config("providerlinks.icgagents.irragents.password");
            break;
            case "VND":
                $username = config("providerlinks.icgagents.vndagents.username");
                $password = config("providerlinks.icgagents.vndagents.password");
            break;
            case "MMK":
                $username = config("providerlinks.icgagents.mmkagents.username");
                $password = config("providerlinks.icgagents.mmkagents.password");
            break;
            default:
                $username = config("providerlinks.icgagents.usdagents.username");
                $password = config("providerlinks.icgagents.usdagents.password");

        }
        $response = $http->post(config("providerlinks.icgaminglogin"), [
            'form_params' => [
                'username' => $username,
                'password' => $password,
            ],
        ]);

        return json_decode((string) $response->getBody(), true)["token"];
    }
    public static function getClientDetails($type = "", $value = "") {

        $query = DB::table("clients AS c")
                 ->select('p.client_id', 'p.player_id', 'p.username', 'p.email', 'p.language', 'p.currency', 'p.test_player', 'pst.token_id', 'pst.player_token' , 'pst.status_id', 'p.display_name', 'c.client_code','c.default_currency','c.default_language','c.client_api_key', 'cat.client_token AS client_access_token', 'ce.player_details_url', 'ce.fund_transfer_url')
                 ->leftJoin("players AS p", "c.client_id", "=", "p.client_id")
                 ->leftJoin("player_session_tokens AS pst", "p.player_id", "=", "pst.player_id")
                 ->leftJoin("client_endpoints AS ce", "c.client_id", "=", "ce.client_id")
                 ->leftJoin("client_access_tokens AS cat", "c.client_id", "=", "cat.client_id");
                 
                if ($type == 'token') {
                    $query->where([
                        ["pst.player_token", "=", $value],
                        ["pst.status_id", "=", 1]
                    ]);
                }

                if ($type == 'player_id') {
                    $query->where([
                        ["p.player_id", "=", $value],
                        ["pst.status_id", "=", 1]
                    ]);
                }

                 $result= $query->first();

        return $result;

    }

    public static function solidLaunchUrl($game_code,$token,$exitUrl){
        $client_code = config("providerlinks.solid.BRAND");
        $launch_url = config("providerlinks.solid.LAUNCH_URL");
        // $url = $launch_url.$client_code.'/'.$game_code.'?language=en&currency=USD&token='.$token.'&exiturl='.$exitUrl.'';

        $client_details = Providerhelper::getClientDetails('token', $token); // New
        $url = $launch_url.$client_code.'/'.$game_code.'?language=en&currency='.$client_details->default_currency.'&token='.$token.'&exiturl='.$exitUrl.'';
        return $url;
    }

    public static function mannaLaunchUrl($game_code,$token,$exitUrl, $lang = '', $clientID){
        $client_details = Providerhelper::getClientDetails('token', $token);
        $lang = GameLobby::getLanguage("Manna Play", $lang);

        try {
            ProviderHelper::saveLogGameLaunch('MannaPlay Auth Response', 15, json_encode($client_details), $clientID);
            // Authenticate New Token
            if ($client_details->operator_id == 15){ // EveryMatix Config
                $api_key = config("providerlinks.mannaplay.15.API_KEY");
                $auth_api_key = config("providerlinks.mannaplay.15.AUTH_API_KEY");
                $platform_id = config("providerlinks.mannaplay.15.PLATFORM_ID");
            }elseif($client_details->operator_id == 30){ // IDNPLAY
                $api_key = config("providerlinks.mannaplay.30.API_KEY");
                $auth_api_key = config("providerlinks.mannaplay.30.AUTH_API_KEY");
                $platform_id = config("providerlinks.mannaplay.30.PLATFORM_ID");
            }else{
                $api_key = config("providerlinks.mannaplay.default.API_KEY");
                $auth_api_key = config("providerlinks.mannaplay.default.AUTH_API_KEY");
                $platform_id = config("providerlinks.mannaplay.default.PLATFORM_ID");
            }
            $getGameDetails = Helper::findGameDetails( "game_code",16, $game_code);
            // $token_generate_tg = ProviderHelper::getEncryptToken($client_details->token_id, $client_details->player_id, $getGameDetails->game_id, $client_details->player_token);

            $auth_token = new Client([ // auth_token
                'headers' => [ 
                    'Content-Type' => 'application/json',
                    'apiKey' => $auth_api_key

                ]
            ]);

            $auth_token_body =  [
                "id" => $platform_id,
                "account" => $client_details->player_id,
                "currency" => $client_details->default_currency,
                "sessionId" => $client_details->player_token,
                "channel" => ($client_details->test_player ? "demo" : "")
            ];

            try {
               $auth_token_response = $auth_token->post(config("providerlinks.mannaplay.AUTH_URL").$platform_id.'/authenticate/auth_token',
                    ['body' => json_encode($auth_token_body)]
                );
                $auth_result = json_decode($auth_token_response->getBody()->getContents());
                ProviderHelper::saveLogGameLaunch('MannaPlay Auth Response', 15, json_encode($auth_token_body), $auth_result);
            } catch (\Exception $e) {
                 ProviderHelper::saveLogGameLaunch('MannaPlay Error', 15, json_encode($auth_token_body), $e->getMessage().' '.$e->getLine());
                return $exitUrl;
            }
            // Generate Game Link
            $game_link = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/json',
                    'apiKey' => $auth_api_key,
                    'token' => $auth_result->token
                ]
            ]);

            $game_link_body =  [
             "account" => $client_details->player_id,
             "sessionId" => $client_details->player_token,
             "language" => $lang,
             "gameId" => $game_code,
             "exitUrl" => $exitUrl
            ];

            $game_link_response = $game_link->post(config("providerlinks.mannaplay.GAME_LINK_URL").$platform_id.'/gameLink/link',
                    ['body' => json_encode($game_link_body)]
            );
            $link_result = json_decode($game_link_response->getBody()->getContents());
            ProviderHelper::saveLogGameLaunch('MannaPlay Error', 15, json_encode($game_link_body), $link_result);
            return $link_result->url;
        } catch (\Exception $e) {
            ProviderHelper::saveLogGameLaunch('MannaPlay Error', 15, json_encode($client_details), $e->getMessage().' '.$e->getLine());
            return $exitUrl;
        }

       
    }

    // public static function ozashikiLaunchUrl($game_code,$token,$exitUrl, $lang = '') {
    //     /*$client_details = GameLobby::getClientDetails('token', $token);*/
    //     $client_details = ProviderHelper::getClientDetails('token', $token);
    //     $lang = GameLobby::getLanguage("Ozashiki", $lang);
    //     // Authenticate New Token

    //     try {
    //         $auth_token = new Client([ // auth_token
    //             'headers' => [ 
    //                 'Content-Type' => 'application/json',
    //                 'apiKey' => config("providerlinks.ozashiki.AUTH_API_KEY")
    //             ]
    //         ]);
    //         $auth_token_response = $auth_token->post(config("providerlinks.ozashiki.AUTH_URL"),
    //                 ['body' => json_encode(
    //                         [
    //                             "id" => config("providerlinks.ozashiki.PLATFORM_ID"),
    //                             "account" => $client_details->player_id,
    //                             "currency" => $client_details->default_currency,
    //                             "sessionId" => $token,
    //                             "channel" => ($client_details->test_player ? "demo" : "")
    //                         ]
    //                 )]
    //             );

    //         $auth_result = json_decode($auth_token_response->getBody()->getContents());

    //         ProviderHelper::saveLogGameLaunch('MannaPlay Ozashiki Auth Response', 15, json_encode($client_details), $auth_result);
    //     } catch (\Exception $e) {
    //          ProviderHelper::saveLogGameLaunch('MannaPlay Ozashiki', 15, json_encode($client_details), $e->getMessage());
    //         return $exitUrl;
    //     }

    //     // Generate Game Link
    //     $game_link = new Client([
    //         'headers' => [ 
    //             'Content-Type' => 'application/json',
    //             'apiKey' => config("providerlinks.ozashiki.AUTH_API_KEY"),
    //             'token' => $auth_result->token
    //         ]
    //     ]);

    //     $game_link_response = $game_link->post(config("providerlinks.ozashiki.GAME_LINK_URL"),
    //             ['body' => json_encode(
    //                     [
    //                         "account" => $client_details->player_id,
    //                         "sessionId" => $token,
    //                         "language" => $lang,
    //                         "gameId" => $game_code,
    //                         "exitUrl" => $exitUrl
    //                     ]
    //             )]
    //         );

    //     $link_result = json_decode($game_link_response->getBody()->getContents());
    //     ProviderHelper::saveLogGameLaunch('MannaPlay Ozashiki Link', 15, json_encode($link_result), $auth_result);
    //     return $link_result->url;
        
    //     /*switch($client_details->wallet_type){
    //         case 1:
    //             return $link_result->url;
    //         case 2:
    //             return TWGameLaunchHelper::TwLaunchUrl($token, 'Ozashiki', $link_result->url, $client_details->player_id, $exitUrl);
    //         case 3:
    //             return PureTransferWalletHelper::PTwLaunchUrl($token, 'Ozashiki', $link_result->url, $client_details->player_id, $exitUrl);
    //         default:
    //             return false;
    //     }*/
    //     // return $link_result->url;
    // }

    public static function dragonGamingLaunchUrl($request_data) {
        $client_details = ProviderHelper::getClientDetails('token', $request_data['token']);
        $game_type_id = DragonGamingHelper::getGameType($request_data['game_code'], config("providerlinks.dragongaming.PROVIDER_ID"));
        
        $game_type_arr = [
            '1' => 'slots',
            '5' => 'table_games'
        ];

        $game_launch = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/json'
                ]
            ]);
        
        $game_launch_response = $game_launch->post(config("providerlinks.dragongaming.API_BASE_URL")."games/game-launch/",
                ['body' => json_encode(
                        [
                            "api_key" => config("providerlinks.dragongaming.API_KEY"),
                            "session_id" => $request_data['token'],
                            "provider" => "dragongaming",
                            "game_type" => $game_type_arr[$game_type_id],
                            "game_id" => $request_data['game_code'],
                            "platform" => "desktop",
                            "language" => "en",
                            "amount_type" => ($client_details->test_player ? "fun" : "real"),
                            "lobby_url" => $request_data['exitUrl'],
                            "deposit_url" => "",
                            "context" => [
                                "id" => $client_details->player_id,
                                "username" => $client_details->username,
                                "country" => $client_details->country_code,
                                "currency" => $client_details->default_currency,
                                ]
                        ]
                )]
            );
        

        $game_launch_url = json_decode($game_launch_response->getBody()->getContents());

        if($game_launch_url) {
            return $game_launch_url->result->launch_url;   
        }

        return $request_data['exitUrl'];    
    }

    public static function MancalaLaunchUrl($request_data) {
        $client_details = ProviderHelper::getClientDetails('token', $request_data['token']);
        $game_launch = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/json'
                ]
            ]);
        $hash = md5("GetToken/".config("providerlinks.mancala.GUID").$request_data['game_code'].$client_details->player_id.$client_details->default_currency.config("providerlinks.mancala.API_KEY"));
        $datatosend = [
                "ClientGuid" => config("providerlinks.mancala.GUID"),
                "GameId" => $request_data['game_code'],
                "UserId" => $client_details->player_id,
                "Currency" => $client_details->default_currency,
                "Lang" => "EN",
                "ClientType" => 1,
                "IsVirtual" => false,
                "ApiVersion" => "v2",
                "Hash" => $hash,
                "DemoMode" => false,
                "ExtraData" => $request_data['token'],
            ];
        Helper::saveLog('Mancala GAMELAUNCH',"mancala", json_encode($datatosend), "");
        $game_launch_response = $game_launch->post(config("providerlinks.mancala.RGS_URL")."/GetToken",
                ['body' => json_encode($datatosend)]
            );

        $game_launch_url = json_decode($game_launch_response->getBody()->getContents());

        if($game_launch_url !== NULL) {
            
            Helper::playerGameRoundUuid($request_data["game_code"], $request_data["token"], $request_data["game_provider"], $game_launch_url->Token);
            
            return $game_launch_url->IframeUrl."&backurl=".$request_data['exitUrl'];  
        }
        
        return $request_data['exitUrl'];     
    }


    public static function aoyamaLaunchUrl($game_code,$token,$exitUrl){
        /*$client_details = GameLobby::getClientDetails('token', $token);*/
        $client_code = 'BETRNKMW'; /*$client_details->client_code ? $client_details->client_code : 'BETRNKMW';*/
        $url = $exitUrl;
        $url = 'https://svr.betrnk.games/winwin/';
        return $url;
    }

    public static function oryxLaunchUrl($game_code,$token,$exitUrl){
        $url = $exitUrl;
        $url = config("providerlinks.oryx.GAME_URL").$game_code.'/open?token='.$token.'&languageCode=ENG&playMode=REAL&lobbyUrl='.$exitUrl;
        return $url;
    }

    public static function vivoGamingLaunchUrl($game_code,$token,$exitUrl,$provider){
        $client_details = Providerhelper::getClientDetails('token', $token); // New
        $operator_id = config("providerlinks.vivo.OPERATOR_ID");
        $server_id = config("providerlinks.vivo.SERVER_ID");
        Helper::savePLayerGameRound($game_code,$token,$provider);
        switch ($provider) {
            case 'Vivo Gaming':
                // vivo live lobby
                $url = config("providerlinks.vivo.VIVO_URL").'?token='.$token.'&operatorid='.$operator_id.'&serverid='.$server_id.'&IsSwitchLobby=true&Application=gtm-lobby&language=EN&IsInternalPop=True';

                // intetionally case for dynamic table IDs, they might add more soon
                if(strpos($game_code, 'GameRound:TableID') !== false){

                    $table_id_arr = [
                        'roulette' => [1, 43, 245, 244, 183, 13, 230, 177, 167, 266, 26, 229, 168, 182],
                        'baccarat' => [3, 28, 27, 180, 181, 239, 240, 241, 242, 243, 154, 155, 156, 157, 158, 44, 14, 17, 21, 160, 161, 162, 163],
                        'blackjack' => [16, 18, 212],
                        'casinoholdem' => [256]
                    ]; 
                    
                    $table_id = preg_replace('/[^0-9]/', '', $game_code);  

                    switch ($game_code) {
                        case in_array($table_id, $table_id_arr['roulette']):
                            // roulette
                             $url = config("providerlinks.vivo.VIVO_URL").'?token='.$token.'&operatorid='.$operator_id.'&tableid='.$table_id.'&serverid='.$server_id.'&modeselection=2D&language=EN&&Application=roulette';
                            break;

                        case in_array($table_id, $table_id_arr['baccarat']):
                            // baccarat
                            $url = config("providerlinks.vivo.VIVO_URL").'?token='.$token.'&operatorid='.$operator_id.'&tableid='.$table_id.'&serverid='.$server_id.'&language=EN&Application=baccarat';
                            break;

                        case in_array($table_id, $table_id_arr['blackjack']):
                            // blackjack
                            $url = config("providerlinks.vivo.VIVO_URL").'?token='.$token.'&operatorid='.$operator_id.'&tableid='.$table_id.'&serverid='.$server_id.'&language=EN&Application=blackjack';
                            break;
                        
                        case in_array($table_id, $table_id_arr['casinoholdem']):
                            // casino hold'em
                            $url = config("providerlinks.vivo.VIVO_URL").'?token='.$token.'&operatorid='.$operator_id.'&tableid='.$table_id.'&serverid='.$server_id.'&language=EN&Application=casinoholdem';
                            break;

                        default:
                            // launch lobby instead
                            $url = config("providerlinks.vivo.VIVO_URL").'?token='.$token.'&operatorid='.$operator_id.'&serverid='.$server_id.'&IsSwitchLobby=true&Application=gtm-lobby&language=EN&IsInternalPop=True';
                            break;
                    }

                }    

                break;

            case 'Betsoft':
                $url = config("providerlinks.vivo.BETSOFT_URL").'?Token='.$token.'&GameID='.$game_code.'&OperatorId='.$operator_id.'&lang=EN&cashierUrl=&homeUrl=';
                break;

            case 'Spinomenal':
                $url = config("providerlinks.vivo.SPINOMENAL_URL").'?token='.$token.'&operatorID='.$operator_id.'&GameID='.$game_code.'&PlatformId=1';
                break;

            case 'Tom Horn':
                $url = config("providerlinks.vivo.TOMHORN_URL").'?GameID='.$game_code.'&Token='.$token.'&lang=EN&OperatorID='.$operator_id.'';
                break;

            case 'Nucleus':
                $url = config("providerlinks.vivo.NUCLEUS_URL").'?token='.$token.'&operatorid='.$operator_id.'&GameID='.$game_code.'';
                break;

             case 'Platipus':
                $launch_id = substr($game_code, strpos($game_code, "-") + 1);

                $url = config("providerlinks.vivo.PLATIPUS_URL").'?token='.$token.'&operatorID='.$operator_id.'&room=125&gameconfig='.$launch_id.'';
                break;

            case 'Leap':
                // $url = config("providerlinks.vivo.LEAP_URL").'?tableguid=JHN3978RJH39UR93USDF34&token='.$token.'&OperatorId='.$operator_id.'&language=en&cashierUrl=&homeUrl=&GameID='.$game_code.'&mode=real&skinid=37&siteid=1&currency=USD';

                $url = config("providerlinks.vivo.LEAP_URL").'?tableguid=JHN3978RJH39UR93USDF34&token='.$token.'&OperatorId='.$operator_id.'&language=en&cashierUrl=&homeUrl=&GameID='.$game_code.'&mode=real&skinid=37&siteid=1&currency='.$client_details->default_currency.'';
                break;

            case 'ArrowsEdge':
                $url = config("providerlinks.vivo.ArrowsEdge_URL").'?tableguid=JJMCHE34297J22JKDX22&token='.$token.'&OperatorId='.$operator_id.'&homeUrl=&language=en&GameID='.$game_code.'&cashierUrl=&gameMode=real&currency='.$client_details->default_currency.'';
                break;
            case '7 Mojos':
                $get_game_type = DragonGamingHelper::getGameType($game_code, config("providerlinks.vivo.PROVIDER_ID"));
                
                $game_type_arr = [
                    '1' => 'slots',
                    '5' => 'live'
                ];

                if($game_type_arr[$get_game_type] == 'slots') {
                    $url = config("providerlinks.vivo.7MOJOS_URL").'?tableguid=J37809HJDJ348HNH232221&token='.$token.'&operatorID='.$operator_id.'&operatorToken='. config("providerlinks.vivo.OPERATOR_TOKEN") .'&homeURL='.$exitUrl.'&gameid='.$game_code.'&mode=real&language=EN&currency='.$client_details->default_currency.'&host=https://de-se.svmsrv.com&gametype=slots';
                }
                else
                {
                    $url = config("providerlinks.vivo.7MOJOS_URL").'?tableguid=J37809HJDJ348HNH232221&token='.$token.'&operatorID='.$operator_id.'&operatorToken='. config("providerlinks.vivo.OPERATOR_TOKEN") .'&homeURL='.$exitUrl.'&gameid='.$game_code.'&mode=real&language=EN&currency='.$client_details->default_currency.'&host=https://de-lce.svmsrv.com&gametype=live';
                }
                break;
            case 'Red Rake':
                $url = config("providerlinks.vivo.RedRake").'?token='.$token.'&operatorID='.$operator_id.'&language=en&gameid='.$game_code.'&mode=real&currency='.$client_details->default_currency.'&tableguid=F9677AB921EB4EEC867355A2BA234929';
                break;
            default:
                # code...
                break;
        }
        return $url;
    }

    public static function simplePlayLaunchUrl($game_code,$token,$exitUrl){
        $url = $exitUrl;
        $dateTime = date("YmdHis", strtotime(Helper::datesent()));
        $secretKey = config("providerlinks.simpleplay.SECRET_KEY");
        $md5Key = config("providerlinks.simpleplay.MD5_KEY");
        
        $client_details = Providerhelper::getClientDetails('token', $token);

        /* [START] LoginRequest */
        $queryString = "method=LoginRequest&Key=".$secretKey."&Time=".$dateTime."&Username=".$client_details->player_id."&CurrencyType=".$client_details->default_currency."&GameCode=".$game_code."&Mobile=0";
        $hashedString = md5($queryString.$md5Key.$dateTime.$secretKey);
        $response = ProviderHelper::simplePlayAPICall($queryString, $hashedString);
        $url = (string) $response['data']->GameURL;
        /* [END] LoginRequest */


        /* [START] RegUserInfo */
        /* $queryString = "method=RegUserInfo&Key=".$secretKey."&Time=".$dateTime."&Username=".$client_details->username."&CurrencyType=".$client_details->default_currency;

        $hashedString = md5($queryString.$md5Key.$dateTime.$secretKey);

        $response = ProviderHelper::simplePlayAPICall($queryString, $hashedString);
        var_dump($response); die(); */
        /* [END] RegUserInfo */

        return $url;
    }

     public static function ultraPlayLaunchUrl($game_code,$token,$exitUrl){
        $url = $exitUrl;

        $url = config("providerlinks.ultraplay.domain_url").'/UI/ExternalLogin?loginToken='.$token.'&deviceType=desktop&lang=en-US&oddformat=decimal';
        return $url;
    }

    
    
    public static function getLanguage($provider_name,$language){
        $provider_language = DB::table("providers")->where("provider_name",$provider_name)->get();
        $languages = json_decode($provider_language[0]->languages,TRUE);
        if(array_key_exists($language,$languages)){
            return $languages[$language];
        }
        else{
            return $languages["en"];
        }
    }


     /**
     * Client Player Details API Call
     * @return [Object]
     * @param $[player_token] [<players token>]
     * @param $[refreshtoken] [<Default False, True token will be requested>]
     * 
     */
    public static function playerDetailsCall($player_token, $refreshtoken=false){
        $client_details = DB::table("clients AS c")
                     ->select('p.client_id', 'p.player_id', 'p.username', 'p.email', 'p.client_player_id', 'p.language', 'p.currency', 'pst.token_id', 'pst.player_token' , 'c.client_url', 'c.default_currency', 'pst.status_id', 'p.display_name', 'c.client_api_key', 'cat.client_token AS client_access_token', 'ce.player_details_url', 'ce.fund_transfer_url')
                     ->leftJoin("players AS p", "c.client_id", "=", "p.client_id")
                     ->leftJoin("player_session_tokens AS pst", "p.player_id", "=", "pst.player_id")
                     ->leftJoin("client_endpoints AS ce", "c.client_id", "=", "ce.client_id")
                     ->leftJoin("client_access_tokens AS cat", "c.client_id", "=", "cat.client_id")
                     ->where("pst.player_token", "=", $player_token)
                     ->latest('token_id')
                     ->first();
        if($client_details){
            try{
                $client = new Client([
                    'headers' => [ 
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer '.$client_details->client_access_token
                    ]
                ]);
                $datatosend = ["access_token" => $client_details->client_access_token,
                    "hashkey" => md5($client_details->client_api_key.$client_details->client_access_token),
                    "type" => "playerdetailsrequest",
                    "clientid" => $client_details->client_id,
                    "playerdetailsrequest" => [
                        "client_player_id" => $client_details->client_player_id,
                        "token" => $player_token,
                        // "playerId" => $client_details->client_player_id,
                        // "currencyId" => $client_details->currency,
                        "gamelaunch" => false,
                        "refreshtoken" => $refreshtoken
                    ]
                ];
                $guzzle_response = $client->post($client_details->player_details_url,
                    ['body' => json_encode($datatosend)]
                );
                $client_response = json_decode($guzzle_response->getBody()->getContents());
                return $client_response;
            }catch (\Exception $e){
               return false;
            }
        }else{
            return false;
        }
    }


    public static function netEntDirect($request){
        try {
            $client_details = Providerhelper::getClientDetails('token', $request["token"]);
            $game_code = $request["game_code"];
            
            //process sessionID
            $url = "https://".config("providerlinks.netent.casinoID")."-api.casinomodule.com/ws-jaxws/services/casino";
            $game_link = new Client([
                    'headers' => [ 
                        'Content-Type' => 'application/json'
                    ]
                ]);
            $game_link_response = $game_link->post($url, 
                ['body' => '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:api="http://casinomodule.com/api">
                                <soapenv:Header/>
                                <soapenv:Body>
                                    <api:loginUserDetailed>
                                        <userName>TG_'.$client_details->player_id.'</userName>
                                        <merchantId>'.config("providerlinks.netent.merchantId").'</merchantId>
                                        <merchantPassword>'.config("providerlinks.netent.merchantPassword").'</merchantPassword>
                                        <currencyISOCode>'.$client_details->default_currency.'</currencyISOCode>
                                    </api:loginUserDetailed>
                                </soapenv:Body>
                            </soapenv:Envelope>'
            ]);
            $response = $game_link_response->getBody();
            $dom = new DOMDocument;
            $dom->loadXML($response);
            $sessionID = $dom->getElementsByTagName('loginUserDetailedReturn')->item(0)->nodeValue;
           
            $staticServerURL = "https://".config("providerlinks.netent.casinoID")."-static.casinomodule.com";
            $gameServerURL = "https://".config("providerlinks.netent.casinoID")."-game.casinomodule.com";
            $aes = new AES();
            $data = array(
                "gameId" => $request["game_code"],
                "staticServerURL" => urlencode($staticServerURL),
                "gameServerURL" => urlencode($gameServerURL),
                "sessionId" => $sessionID,
                "casinoID" => config("providerlinks.netent.casinoID"),
                "lobbyUrl" => urlencode($request["exitUrl"])
            );
            $encoded_data = $aes->AESencode(json_encode($data));
            // return urlencode($encoded_data);
            // return "http://localhost:2020/loadgame/netent_direct?param=".urlencode($encoded_data);
            return config("providerlinks.play_betrnk")."/loadgame/netent_direct?param=".urlencode($encoded_data);
        } catch (\Exception $e){
            return false;
        }
    }
    public static function getGameByGameId($game_id){
        $game = DB::select("SELECT game_id, game_type_id,provider_id,sub_provider_id,game_name,game_code,on_maintenance FROM games WHERE game_id = '".$game_id."'");
        $count = count($game);
        return $count > 0 ? $game[0]:null;
    }
    public static function checkAndGetProviderId($provider_name){
        $provider = DB::select("SELECT * FROM sub_provider_code WHERE sub_provider_name = '".$provider_name."'");
        $count = count($provider);
        return $count > 0 ? $provider[0]:null;
    }
    public static function onlyplayLaunchUrl( $game_code = null, $token = null, $exiturl, $provider,$lang)
    {
         try{
            $client_player_details = Providerhelper::getClientDetails('token', $token);
            $balance = str_replace(".","", $client_player_details->balance);
            // $balance = (int) $client_player_details->balance;
            $formatBalance = (int) $balance;
            $decimals = 2;
            // dd($formatBalance);
            $data = "balance".$formatBalance."callback_url".config('providerlinks.oauth_mw_api.mwurl').'/api/onlyplay'."currency".$client_player_details->default_currency."decimals".$decimals."game_bundle".$game_code."langen"."partner_id".config('providerlinks.onlyplay.partner_id')."token".$token.'user_idTG_'.$token; 
            $signature = providerHelper::onlyplaySignature($data,config('providerlinks.onlyplay.secret_key'));
            $url = config('providerlinks.onlyplay.api_url');
            $requesttosend = [
                'balance' => $formatBalance,
                'callback_url' => config('providerlinks.oauth_mw_api.mwurl').'/api/onlyplay',
                'currency' => $client_player_details->default_currency,
                'decimals' => 2,
                'game_bundle' => $game_code,
                'lang' => $lang,
                'partner_id' => config('providerlinks.onlyplay.partner_id'),
                'sign' => $signature,
                'token' => $token,
                'user_id' => 'TG_'.$token,
            ];
            
            $client = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/json'
                ]
            ]);
            $guzzle_response = $client->post($url,['body' => json_encode($requesttosend)]
            );
            $game_luanch_response = json_decode($guzzle_response->getBody()->getContents());
            // dd($game_luanch_response);
            Helper::saveLog('onlyplay launch',$provider, json_encode($requesttosend), $game_luanch_response);
            return $game_luanch_response->url;
        }catch(\Exception $e){
            $requesttosend = [
                  "success" => false,
                  "code" => 2001,
                  "message" => "Game not found, wrong bundle see GameList"
            ];
            Helper::saveLog('onlyPlay 2001', $provider, json_encode($requesttosend), $e->getMessage() );
            return $e->getMessage();
        }

    }
    public static function TopTrendGamingLaunchUrl($data){
        try {
            $client_details = ProviderHelper::getClientDetails('token',$data['token']);
            if($client_details->country_code == null){
                $country_code = "PH";
                // $country_code = $data['country_code'];
            }else{
                $country_code = $client_details->country_code;
            }
            $client = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/xml'
                ]
            ]);
            // $url = 'https://ams5-api.ttms.co:8443/cip/gametoken/TGR_'.$client_details->player_id;
            $url = config('providerlinks.toptrendgaming.api_url').'TGR_'.$client_details->player_id;
            $guzzle_response = $client->post($url,[
                'body' => '<logindetail>
                                <player account="'.$client_details->default_currency.'" country="'.$country_code.'" firstName="" lastName="" userName="'.$client_details->username.'" 
                                nickName="" tester="0" partnerId="TIGERGAMES" commonWallet="1" />
                                <partners>
                                    <partner partnerId="zero" partnerType="0" />
                                    <partner partnerId="TIGERGAMES" partnerType="1" />
                                </partners>
                            </logindetail>'
            ]
            );
            $game_luanch_response = $guzzle_response->getBody();
            $json = json_encode(simplexml_load_string($game_luanch_response));
            $array = json_decode($json,true);
            $val = $array["@attributes"]["token"];
            // dd($val);
            $game_name = DB::select('SELECT game_name FROM games WHERE provider_id = '.config("providerlinks.toptrendgaming.provider_db_id").' and game_code = '.$data['game_code'].'');
            $remove[] = "'";
            $remove[] = ' ';
            $game_details = $game_name[0];
            $get_name = str_replace($remove,'', $game_details->game_name);

            $game_url = config("providerlinks.toptrendgaming.game_api_url").'/casino/default/game/game.html?playerHandle='.$val.'&account='.$client_details->default_currency.'&gameName='.$get_name.'&gameType=0&gameId='.$data['game_code'].'&lang=en&deviceType=web&lsdId=TIGERGAMES';

            return $game_url;

        } catch (\Exception $e) {
            $msg = array(
                'err_message' => $e->getMessage(),
                'err_line' => $e->getLine(),
                'err_file' => $e->getFile()
            );
            Helper::saveLog('TopTrendGaming Error', 56, json_encode($msg), json_encode($msg) );
            return $e->getMessage().' '.$e->getLine().' '.$e->getFile();
            // $error = '<gametoken uid="usd001">
            //             <error code="1002" message="unable to login" />
            //           </gametoken>';
            // return response($error,200) 
            //       ->header('Content-Type', 'application/xml');
        }
    }

    public static function PlayTechLaunch($data){
        Helper::saveLog('PlayTech GAMELUANCH', 68, json_encode($data),  "HIT" );
        $client_details = ProviderHelper::getClientDetails('token',$data['token']);
        $getGameDetails = Helper::findGameDetails( "game_code",config('providerlinks.playtech.provider_db_id'), $data['game_code']);
        if($getGameDetails->game_type_id == "15"){
            $gameUrl = config('providerlinks.playtech.api_url')."/launcher?gameCode=".$getGameDetails->secondary_game_code."&token=".$data['token']."&platform=web&language=en&playerId=".$client_details->player_id."&brandId=".config('providerlinks.playtech.brand_id')."&mode=1&backUrl=".$data['exitUrl'].'&tableAlias='.$data['game_code'];
            Helper::saveLog('PlayTech GAMELUANCH', 68, json_encode($gameUrl),  "HIT" );
            return $gameUrl;
        } else {
            $is_support  = $getGameDetails->info ==  ''  ? 'web' : $getGameDetails->info;
            $gameUrl = config('providerlinks.playtech.api_url')."/launcher?gameCode=".$data['game_code']."&token=".$data['token']."&platform=".$is_support."&language=en&playerId=".$client_details->player_id."&brandId=".config('providerlinks.playtech.brand_id')."&mode=1&backUrl=".$data['exitUrl'];
            Helper::saveLog('PlayTech GAMELUANCH', 68, json_encode($gameUrl),  "HIT" );
            return $gameUrl;
        }

        // OLD
        // if($getGameDetails->game_type_id == "15"){
        //     $gameUrl = config('providerlinks.playtech.api_url')."/launcher?gameCode=".$getGameDetails->info."&token=".$data['token']."&platform=web&language=en&playerId=".$client_details->player_id."&brandId=".config('providerlinks.playtech.brand_id')."&mode=1&backUrl=".$data['exitUrl'].'&tableAlias='.$data['game_code'];
        //     Helper::saveLog('PlayTech GAMELUANCH', 68, json_encode($gameUrl),  "HIT" );
        //     return $gameUrl;
        // } else {
        //     $is_support  = $getGameDetails->info ==  ''  ? 'web' : $getGameDetails->info;
        //     $gameUrl = config('providerlinks.playtech.api_url')."/launcher?gameCode=".$data['game_code']."&token=".$data['token']."&platform=".$is_support."&language=en&playerId=".$client_details->player_id."&brandId=".config('providerlinks.playtech.brand_id')."&mode=1&backUrl=".$data['exitUrl'];
        //     Helper::saveLog('PlayTech GAMELUANCH', 68, json_encode($gameUrl),  "HIT" );
        //     return $gameUrl;
        // }

        return "false";
       
    }
    public static function FunkyGamesLaunch($data){
        Helper::saveLog('FunkyGames GAMELUANCH', 110, json_encode($data),  "HIT" );
        $client_details = ProviderHelper::getClientDetails('token',$data['token']);
        try {
            
                $paramsToSend = [
                    'gameCode' => $data['game_code'],
                    'userName' => $client_details->username,
                    'playerId' => $client_details->player_id,
                    'currency' => $client_details->default_currency,
                    'language' => 'en',
                    'playerIp' => $data['ip_address'],
                    'sessionId' => $data['token'],
                    'isTestAccount' => true
                ];
                $client = new Client([
                    'headers' => [ 
                        'Content-Type' => 'application/json',
                        'Authentication' => config('providerlinks.funkygames.Authentication'),
                        'User-Agent' => config('providerlinks.funkygames.User-Agent'),
                        'X-Request-ID' => $client_details->player_id,
                    ]
                ]);
                $url = config('providerlinks.funkygames.api_url').'Funky/Game/LaunchGame';
                $guzzle_response = $client->post($url,['body' => json_encode($paramsToSend)]
                    );
                $game_luanch_response = json_decode($guzzle_response->getBody()->getContents());
                // Helper::saveLog('funky games launch',config('providerlinks.funkygames.provider_db_id'), json_encode($paramsToSend), $game_luanch_response);

                $responseAndRequest = [
                    'Authentication' => config('providerlinks.funkygames.Authentication'),
                    'User-Agent' => config('providerlinks.funkygames.User-Agent'),
                    "Response" => json_encode($game_luanch_response),
                ];
                ProviderHelper::saveLogGameLaunch('FunkyGamesLaunch', config('providerlinks.funkygames.provider_db_id'), json_encode($paramsToSend), $responseAndRequest);
                // dd($game_luanch_response->data->gameUrl);
                // $gameUrl = $game_luanch_response->data->gameUrl."?token=".$game_luanch_response->data->token."&redirectUrl=https://daddy.betrnk.games/provider/FunkyGames";
                $gameUrl = $game_luanch_response->data->gameUrl."?token=".$game_luanch_response->data->token."&redirectUrl=";

                Helper::saveLog('funky games launch',config('providerlinks.funkygames.provider_db_id'), json_encode($paramsToSend), $gameUrl);
                return $gameUrl;

        } catch (\Exception $e) {

                return $e->getMessage();
                
        }
    }
    public static function AmuseGamingGameLaunch($data,$device){
        Helper::saveLog('AMUSEGAMING LAUNCH', 65, json_encode($data),  "HIT" );

        try {
            $proivder_db_id = config('providerlinks.amusegaming.provider_db_id');
            $launch_url = config('providerlinks.amusegaming.launch_url');
            $api_url = config('providerlinks.amusegaming.api_url');
            $client_details = ProviderHelper::getClientDetails('token',$data['token']);
            // Helper::saveLog('AMUSEGAMING LAUNCH 1', 65, json_encode($client_details->player_id),  $client_details );
            $getDetails = AmuseGamingHelper::createPlayerAndCheckPlayer($client_details);
            // Helper::saveLog('AMUSEGAMING LAUNCH createPlayerAndCheckPlayer', 65, json_encode($getDetails),  $getDetails );
            if ($getDetails) {
                $token = AmuseGamingHelper::requestTokenFromProvider($client_details, "real");
                if($token != "false"){
                    $getGameDetails = Helper::findGameDetails( "game_code", $proivder_db_id, $data['game_code']);
                    $brand = AmuseGamingHelper::getBrand($data['game_code'],$proivder_db_id);
                    $url = $launch_url."?token=".$token. "&brand=".$brand."&technology=html5&game=".$getGameDetails->game_code."&closeURL=".$data['exitUrl']."&server=api4.slotomatic.net";
                    Helper::saveLog('AMUSEGAMING LAUNCH URL', 65, json_encode($data),  $url );
                    return $url;
                }
            }
            Helper::saveLog('AMUSEGAMING LAUNCH', 65, json_encode($data),  $getDetails );
            return "false";
        } catch (\Exception $e) {
            $msg = array(
                'err_message' => $e->getMessage(),
                'err_line' => $e->getLine(),
                'err_file' => $e->getFile()
            );
            Helper::saveLog('AMUSEGAMING LAUNCH ERROR', 65, json_encode($msg),  $e->getMessage() );
            return $e->getMessage();
        }
    }
    
    public static function QuickSpinDGameLaunch($data,$device){
        Helper::saveLog('QuickSpin Direct LAUNCH', 66, json_encode($data),  "HIT" );
        try {
            if($device == 'desktop'){
                $channel = 'web';
            }else{
                $channel = 'mobile';
            }
            $getGameDetails = Helper::findGameDetails( "game_code", config('providerlinks.quickspinDirect.provider_db_id'), $data['game_code']);
            $gameUrl = config("providerlinks.quickspinDirect.api_url")."/casino/launcher.html?moneymode=real&lang=en_US&gameid=".$getGameDetails->game_code."&partner=tigergames&partnerid=2076&channel=".$channel."&ticket=".$data['token'];
            return $gameUrl;
        } catch (\Exception $e) {
            Helper::saveLog('QuickSpinDirect LAUNCH ERROR', 65, json_encode($e->getMessage()),  $e->getMessage() );
            return $e->getMessage();
        }
    }

    public static function SpearHeadGameLaunch($data, $device){
        try {
            Helper::saveLog('SpearHeadGameLaunch ', 67, json_encode($data),  "HIT" );
            $gameUrl = config('providerlinks.spearhead.api_url').config('providerlinks.spearhead.opid')."/".$data['game_code']."?language=en&casinolobbyurl=".$data['exitUrl']."&_sid=".$data['token'];
            Helper::saveLog('SpearHeadGameLaunch2 ', 67, json_encode($data),$gameUrl);
            return $gameUrl;
        } catch (\Exception $e) {
            Helper::saveLog('SpearHeadLAUNCH ERROR', 65, json_encode($e->getMessage()),  $e->getMessage() );
            return $e->getMessage();
        }
    }
    public static function IDNPoker($request){
        Helper::saveLog('IDNPOKER GAMELUANCH', 110, json_encode($request),  "HIT" );
        Helper::savePLayerGameRound($request["game_code"],$request['token'], $request["game_provider"]);
        try {
            $client_details = ProviderHelper::getClientDetails('token',$request['token']);
            $key = "LUGTPyr6u8sRjCfh";
            $aes = new AES($key);
            // $player_id = config('providerlinks.idnpoker.prefix').$client_details->player_id;
            $player_id = $client_details->username;
            $auth_token = IDNPokerHelper::getAuthPerOperator($client_details, config('providerlinks.idnpoker.type')); 

            $default_frame = config('providerlinks.play_tigergames');
            if ($client_details->operator_id == 37) {
                $default_frame = 'https://kbpoker.69master.cc';
            }
            /***************************************************************
            *
            * CHECK PLAYER IF EXIST
            *
            ****************************************************************/
            $data = IDNPokerHelper::playerDetails($player_id,$auth_token);
            /***************************************************************
            *
            * IF PLAYER NOT EXIST THEN CREATE PLAYER
            *
            ****************************************************************/
            if ($data != "false") {
                if (isset($data["error"])) {
                    $data = IDNPokerHelper::registerPlayer($player_id,$auth_token);
                }
                $data = IDNPokerHelper::playerDetails($player_id,$auth_token);
                if(isset($data["userid"]) && isset($data["username"])) {
                    /***************************************************************
                    *
                    * CHECK IF PLAYER RESTRICTION
                    *
                    ****************************************************************/
                    $data = IDNPokerHelper::checkPlayerRestricted($client_details->player_id); // HANDLE IF PLAYER WANT TO NEW REQUEST URL
                    if($data == "false"){
                        /***************************************************************
                        *
                        * GET URL / OR LOGIN TO PROVIDER
                        *
                        ****************************************************************/
                        $data = IDNPokerHelper::gameLaunchURLLogin($request, $player_id, $client_details,$auth_token);
                        if(isset($data["lobby_url"])){
                            // do deposit
                            // $player_details = ProviderHelper::playerDetailsCall($request['token']);
                            // if(isset($player_details->playerdetailsresponse->status->code) && $player_details->playerdetailsresponse->status->code == 200){
                                // ProviderHelper::_insertOrUpdate($client_details->token_id,$player_details->playerdetailsresponse->balance);                            
                                switch($client_details->wallet_type){
                                    case 1: 
                                        // SEAMLESS TYPE CLIENT
                                        // BUT PROVDER TRANSFER WALLET
                                        try {
                                            $http = new Client();
                                            $response = $http->post(config('providerlinks.oauth_mw_api.mwurl').'/api/idnpoker/makeDeposit', [
                                                'form_params' => [
                                                    'token' => $request['token'],
                                                    'player_id'=> $client_details->player_id,
                                                    'amount' => $client_details->balance,
                                                ],
                                                'headers' =>[
                                                    'Accept'     => 'application/json'
                                                ]
                                            ]);
                                            $iframe_data = json_decode((string) $response->getBody(), true);
                                            Helper::saveLog('IDNPOKER GAMELUANCH MAKEDEPOSIT', 110, json_encode($iframe_data),  $client_details );
                                            if (isset($iframe_data['status']) && $iframe_data['status'] != 'ok' ) {
                                                return "false";
                                                // return config('providerlinks.play_tigergames').'/online-poker?msg=Something went wrong please contact Tiger Games!';
                                            }

                                            $data_to_send_play = array(
                                                "url" => $data["lobby_url"],
                                                "token" => $client_details->player_token,
                                                "player_id" => $client_details->player_id,
                                                "exitUrl" => isset($request['exitUrl']) ? $request['exitUrl'] : '',
                                            );
                                            $encoded_data = $aes->AESencode(json_encode($data_to_send_play));
                                            // return urlencode($encoded_data);
                                            return $default_frame . "/loadgame/idnpoker?param=" . urlencode($encoded_data);
                                        } catch (\Exception $e) {
                                            Helper::saveLog('IDNPOKER GAMELUANCH MAKEDEPOSIT ERROR', 110, $client_details,  $e->getMessage() );
                                            return "false";
                                        }
                                       
                                    case 2:
                                        //TRANSFER WALLET CLIENT
                                        //TRANSFER WALLET SA PROIVDER
                                        return $data["lobby_url"];
                                    default:
                                        return "false";
                                        // return config('providerlinks.play_tigergames').'/online-poker?msg=Something went wrong please contact Tiger Games!';
                                }
                            // }else{
                            //     return "false";
                            //     // return config('providerlinks.play_tigergames').'/online-poker?msg=Something went wrong please contact Tiger Games!';
                            // }
                            
                        }
                    } else {
                        try {
                            $http = new Client();
                            $response = $http->post(config('providerlinks.oauth_mw_api.mwurl').'/api/idnpoker/retryWithdrawalWallet', [
                                'form_params' => [
                                    'player_id'=> $client_details->player_id,
                                ],
                                'headers' =>[
                                    'Accept'     => 'application/json'
                                ]
                            ]);
                            $iframe_data = json_decode((string) $response->getBody(), true);
                            Helper::saveLog('IDNPOKER GAMELUANCH MAKEDEPOSIT RETRY', 110, json_encode($iframe_data),  json_encode($iframe_data) );
                            if (isset($iframe_data['status']) && $iframe_data['status'] != 'ok' ) {
                                // return "false";
                                // return config('providerlinks.play_tigergames').'/online-poker?msg=Currently in the process of withdrawal. Please wait a moment and try again!';
                            }
                            // return config('providerlinks.play_tigergames').'/online-poker?msg=Currently in the process of withdrawal. Please wait a moment and try again!';
                        } catch (\Exception $e) {
                            Helper::saveLog('IDNPOKER GAMELUANCH MAKEDEPOSIT RETRY', 110, json_encode("error"),  $e->getMessage() );
                            // return "false";
                            // return config('providerlinks.play_tigergames').'/online-poker?msg=Currently in the process of withdrawal. Please wait a moment and try again!';
                        }
                        return config('providerlinks.play_tigergames').'/online-poker?msg=Currently in the process of withdrawal. Please wait a moment and try again!';
                    }
                    
                }
                return "false";
                // return config('providerlinks.play_tigergames').'/online-poker?msg=Something went wrong please contact Tiger Games!';
            } else {
                return "false";
                // return config('providerlinks.play_tigergames').'/online-poker?msg=Something went wrong please contact Tiger Games!';
            }
        } catch (\Exception $e) {
            Helper::saveLog('IDNPOKER GAMELUANCH ERROR', 110, json_encode($request),  $e->getMessage() );
            return "false";
            // return config('providerlinks.play_tigergames').'/online-poker?msg=Something went wrong please contact Tiger Games!';
        }
    }


    public static function ygglaunchUrl($data,$device){
        $provider_id = config("providerlinks.ygg002.provider_db_id");
        if($device == 'desktop'){$channel = 'pc';$fullscreen='yes';}else{ $channel = 'mobile';$fullscreen='no';}
        ProviderHelper::saveLogGameLaunch('YGG 002 gamelaunch', $provider_id, json_encode($data), "Endpoing hit");
        $api_url = config("providerlinks.ygg002.api_url");
        $org = config("providerlinks.ygg002.Org");
        $key = config("providerlinks.ygg002.key");
        $client_details = ProviderHelper::getClientDetails('token',$data['token']);
        try{
            $requesttosend = [
                "loginname" => "TG002_".$client_details->player_id,
                "key" => $data['token'],
                "currency" => $client_details->default_currency,
                "lang" => $data['lang'],
                "gameid" => $data['game_code'],
                "org" => $org,
                "channel" => $channel,
                "home" => $data['exitUrl'],
                "fullscreen" => $fullscreen,
            ];
            $client = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ]
            ]);
            $response = $client->post($api_url,[
                'form_params' => $requesttosend,
            ]);
            $res = json_decode($response->getBody(),TRUE);
            ProviderHelper::saveLogGameLaunch('YGG 002 gamelaunch0', $provider_id, json_encode($res),json_encode($requesttosend));
            $url = $res['data']['launchurl'];
            ProviderHelper::saveLogGameLaunch('YGG 002 gamelaunch1', $provider_id, json_encode($requesttosend), $url);
            ProviderHelper::saveLogGameLaunch('YGG 002 gamelaunch2', $provider_id, json_encode($data), $url);
            return $url;
        }catch(\Exception $e){
            $error = [
                'err_message' => $e->getMessage(),
                'err_line' => $e->getLine(),
                'err_file' => $e->getFile()
            ];
            ProviderHelper::saveLogGameLaunch('YGG 002 gamelaunch Err', $provider_id, json_encode($data), json_encode($error));
            return $error;
        }

    }
    public static function botaLaunchUrl($request){
        $exit_url = $request['exitUrl'];
        $provider = $request['game_provider'];
        $token = $request['token'];
        $game_code = $request['game_code'];
        $get_player_details = ProviderHelper::getClientDetails('token',$request['token']);
        $prefix = config('providerlinks.bota.prefix');
        $gameToken = BOTAHelper::botaGenerateGametoken($get_player_details);
        Helper::saveLog('bota gametoken', 135, json_encode($get_player_details), json_encode($gameToken));
        Helper::savePLayerGameRound($game_code,$token,$provider);
        $game_launch_url = config('providerlinks.bota.gamelaunch_url').$gameToken->result_value->token;
        // $requesttosend = [
        //     "token" => $gameToken->result_value->token,
        //     "user_id" => $prefix."_".$get_player_details->player_id,
        //     "game" => $game_code
        // ];
        // $client = new Client([
        //     'headers' => [ 
        //         'Content-type' => 'x-www-form-urlencoded',
        //         'Authorization' => "Bearer ".config('providerlinks.bota.api_key'),
        //         'User-Agent' => config('providerlinks.bota.user_agent')
        //     ]
        // ]);
        
        // $response = $client->get(config('providerlinks.bota.api_url').'/game/url',
        // ['form_params' => $requesttosend,]);
        
        // $dataresponse = json_decode($response->getBody()->getContents());
        Helper::saveLog('bota gamelaunch', 135, json_encode($game_launch_url), 'Initialized');
        $gameurl = isset($game_launch_url) ? $game_launch_url : $exit_url;
        Helper::saveLog('bota gamelaunchfinal', 135, json_encode($gameurl), 'Gamelaunch Success');
        return $gameurl;  
    }

    public static function dowinnLaunchUrl($request){
        $exit_url = $request['exitUrl'];
        $provider = $request['game_provider'];
        $token = $request['token'];
        $game_code = $request['game_code'];
        $get_player_details = ProviderHelper::getClientDetails('token',$request['token']);
        $prefix = config('providerlinks.dowinn.prefix');
        // $logintoken = hash_hmac('sha256', json_encode($request), config("providerlinks.dowinn.user_agent"));
        $guid = substr("abcdefghijklmnopqrstuvwxyz1234567890", mt_rand(0, 25), 1).substr(md5(time()), 1);
        $responsebod = DOWINNHelper::generateGameToken($token,$guid,$prefix,$get_player_details);
        // Helper::saveLog('dowinn gametoken', 139, json_encode($get_player_details), json_encode($logintoken));
        // Helper::savePLayerGameRound($game_code,$token,$provider);
        $game_launch_url = config('providerlinks.dowinn.gamelaunch_url').'?token='.$responsebod['token'].'&p='.$responsebod['p'].'&v='.$responsebod['v'];
        // Helper::saveLog('dowinn gamelaunch', 139, json_encode($game_launch_url), 'Initialized');
        $gameurl = isset($game_launch_url) ? $game_launch_url : $exit_url;
        // Helper::saveLog('dowinn gamelaunchfinal', 139, json_encode($gameurl), 'Gamelaunch Success');
        return $gameurl;  
    }

    public static function nagaLaunchUrl($request){
        $exit_url = $request['exitUrl'];
        $game_code = $request['game_code'];
        $brandCode = config('providerlinks.naga.brandCode');
        $groupCode = config('providerlinks.naga.groupCode');
        $client_details = ProviderHelper::getClientDetails('token', $request['token']);
        $getURL = ProviderHelper::findGameLaunchURL('game_code', 141, $game_code,$client_details);
        Helper::saveLog('Naga gamelaunchfinal', 141, json_encode($getURL), 'Gamelaunch INITIATE');
        if ($getURL->game_launch_url == '0'){
            $url = NagaGamesHelper::findGameUrl($request['token'],$game_code,$client_details);
        }else{
            $url = $getURL->game_launch_url;
        }
        // $url = NagaGamesHelper::findGameUrl($request['token'],$game_code);

        // $game_launch_url = $gameUrl.'playerToken='.$request['token'].'&groupCode='.$groupCode.'&brandCode='.$brandCode. "&sortBy=playCount&orderBy=DESC";
        // Helper::saveLog('dowinn gametoken', 131, json_encode($get_player_details), json_encode($logintoken));
        // Helper::savePLayerGameRound($game_code,$token,$provider);

        $game_launch_url = $url.'?playerToken='.$request['token'].'&groupCode='.$groupCode.'&brandCode='.$brandCode. "&gameCode=" . $game_code . "&redirectUrl=".$exit_url;
        // $game_launch_url = 'https://stg-bonanza.azureedge.net/?playerToken='.$request['token'].'&groupCode='.$groupCode.'&brandCode='.$brandCode. "&gameCode=" . $game_code . "&redirectUrl=".$exit_url;
        // Helper::saveLog('dowinn gamelaunch', 139, json_encode($game_launch_url), 'Initialized');
        $gameLaunchURL = isset($game_launch_url) ? $game_launch_url : $exit_url;
        Helper::saveLog('Naga gamelaunchfinal', 141, json_encode($gameLaunchURL), 'Gamelaunch Success');
        return $gameLaunchURL;  
    }

    public static function hacksawgaming($data,$device){
        try {
            $client_details =ProviderHelper::getClientDetails('token',$data['token']);
            $url = config("providerlinks.hacksawgaming.api_url").'language='.$data['lang'].'&channel='.$device.'&gameid='.$data['game_code'].'&mode=live&token='.$data['token'].'&lobbyurl='.$data['exitUrl'].'&currency='.$client_details->default_currency.'&partner='.config('providerlinks.hacksawgaming.partnerid');
            return $url;
            } catch (\Exception $e) {
                Helper::saveLog('Hacksaw Gameluanch error', 23, json_encode('unable to launch'), $e->getMessage() );
                return $e->getMessage();
            }
    }
    public static function gamingCorpsLaunchUrl($data,$device){
        try {
            $client_details =ProviderHelper::getClientDetails('token',$data['token']);
            $url = config("providerlinks.gamingcorps.gamelaunch_url").'game_code='.$data['game_code'].'&language='.$data['lang'].'&currency='.$client_details->default_currency.'&casino_token='.config("providerlinks.gamingcorps.casino_token").'&player_uid='.$client_details->player_id.'&platform='.$device.'&auto_spin=false&max_bet=false';
            return $url;
        } catch (\Exception $e) {
            Helper::saveLog('Gaming Corps Gameluanch error', 23, json_encode('unable to launch'), $e->getMessage() );
            return $e->getMessage();
        }
    }
    public static function qtechLaunchUrl($data,$device){
        try {
            $client_details =ProviderHelper::getClientDetails('token',$data['token']);
            $request_url = config("providerlinks.qtech.api_url")."/v1/auth/token?grant_type=password&response_type=token&username=".config("providerlinks.qtech.username")."&password=".config("providerlinks.qtech.password");
            $accessToken = ProviderHelper::qtGetAccessToken($request_url);
            $api_url = config("providerlinks.qtech.api_url")."/v1/games/".$data['game_code']."/launch-url";
            $requesttosend = [
                'playerId' => $client_details->player_id,
                'currency' => $client_details->default_currency,
                'country' => "CN",
                'gender' => "M",
                'birthDate' => "1986-01-01",
                'lang' => "en_US",
                'mode' => "real",
                'device' => $device,
                'returnUrl' => "https://daddy.betrnk.games",
                'walletSessionId' => $data['token']
            ];
            Helper::saveLog('Qtech Gameluanch', 144, json_encode($requesttosend), "access_token:".$accessToken." token:".$data['token'] );
            $client = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$accessToken
                ]
            ]);
            $response = $client->post($api_url,[
                'body' => json_encode($requesttosend),
            ]);
            $res = json_decode($response->getBody(),TRUE);
            Helper::saveLog('Qtech Gameluanch', 144, json_encode($requesttosend), json_encode($res) );
            return $res['url'];
        } catch (\Exception $e) {
            Helper::saveLog('Qtech Gameluanch Error', 144, json_encode('unable to launch'), $e->getMessage() );
            return $e->getMessage();
        }
    }

    public static function pragmaticplayV2launcher($game_code = null, $token = null, $data, $device)
    {
        $stylename = config('providerlinks.ppv2.secureLogin');
        Helper::saveLog('Pragmatic Play Gameluanch', 143, json_encode($data), json_encode($stylename));
        $key = config('providerlinks.ppv2.secret_key');
        $host = config('providerlinks.ppv2.host');

        $client_details = Providerhelper::getClientDetails('token', $token);
        $player_details = Providerhelper::playerDetailsCall($client_details->player_token);
        $game_details = DB::table('games')->where('provider_id','=',26)->where('game_code','=',$game_code)->orderBy('created_at','desc')->first();
        if($device == 'desktop'){ 
            $device = 'WEB';
        }else{ 
            $device = 'MOBILE'; 
        }
        $userid = "TGaming_".$client_details->player_id;
        $currency = $client_details->default_currency;
        $hash = md5("currency=".$currency."&language=".$data['lang']."&lobbyUrl=".$data['exitUrl']."&platform=".$device."&secureLogin=".$stylename."&stylename=".$stylename."&symbol=".$game_code."&technology=H5&token=".$token."".$key);
        try{
            $form_body = [
                "currency" => $currency,
                "language" => $data['lang'],
                "lobbyUrl" => $data['exitUrl'],
                "platform" => $device,
                "secureLogin" => $stylename,
                "stylename" => $stylename,
                "symbol" => $game_code,
                "technology" => "H5",
                "token" => $token,
                "hash" => $hash
            ];
            Helper::saveLog('Pragmatic Play Gameluanch', 143, json_encode($form_body), "HIT");
            $client = new Client();
            $guzzle_response = $client->post($host,  ['form_params' => $form_body]);
            $client_response = json_decode($guzzle_response->getBody()->getContents());
            Helper::saveLog('Pragmatic Play Gameluanch', 143, json_encode($form_body), json_encode($client_response));
            $url = $client_response->gameURL;
            return $url;

        
        }catch(\Exception $e){
            $msg = array(
                'err_message' => $e->getMessage(),
                'err_line' => $e->getLine(),
                'err_file' => $e->getFile()
            );
            Helper::saveLog('Pragmatic Play Gameluanch ERR', 143, json_encode($msg), json_encode($msg));
            return $msg;
        }
    }
    public static function relaxgaming($data,$device){
        try {
            $client_details =ProviderHelper::getClientDetails('token',$data['token']);
            if($device == 'desktop'){
                $channel = 'web';
            }
            $url = config("providerlinks.relaxgaming.url").'lang='.$data['lang'].'&channel='.$channel.'&gameid='.$data['game_code'].'&moneymode=real&ticket='.$data['token'].'&homeurl='.$data['exitUrl'].'&currency='.$client_details->default_currency.'&partnerid='.config('providerlinks.relaxgaming.partnerid').'&partner='.config('providerlinks.relaxgaming.partner');
            return $url;
            } catch (\Exception $e) {
                Helper::saveLog('relaxgaming Gameluanch error', 77, json_encode('unable to launch'), $e->getMessage() );
                return $e->getMessage();
            }
    }
}

?>
