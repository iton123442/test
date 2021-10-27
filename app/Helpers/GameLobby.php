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
use App\Helpers\MGHelper;
use App\Helpers\EVGHelper;
use DOMDocument;
use App\Services\AES;
use Webpatser\Uuid\Uuid;

use DB;             
use Carbon\Carbon;
class GameLobby{
    public static function icgLaunchUrl($game_code,$token,$exitUrl,$provider,$lang="en"){
        $client = GameLobby::getClientDetails("token",$token);
        
        $game_list =GameLobby::icgGameUrl($client->default_currency);
        ProviderHelper::saveLogGameLaunch('GAMELAUNCH ICG', 11, json_encode($game_code), json_encode($game_list));
        foreach($game_list["data"] as $game){
            if($game["productId"] == $game_code){
                $lang = GameLobby::getLanguage("Iconic Gaming",$lang);
                Helper::savePLayerGameRound($game["productId"],$token,$provider);
                ProviderHelper::saveLogGameLaunch('GAMELAUNCH ICG', 11, json_encode($game_code), json_encode($game["href"]));
                return $game["href"].'&token='.$token.'&lang='.$lang.'&home_URL='.$exitUrl;
                
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
        // Helper::saveLog('TGG GAMELAUNCH TOPGRADEGAMES', 29, json_encode($requesttosend), json_decode($response->getBody()));
        $gameurl = isset($res['data']['link']) ? $res['data']['link'] : $exiturl;
        return $gameurl;   
      
        
    }
    public static function PlayStarLaunchURl($data){
        $client_details = ProviderHelper::getClientDetails('token',$data['token']);
        $url = config('providerlinks.playstar.api_url').'/launch/?host_id='.config('providerlinks.playstar.host_id')[$client_details->default_currency].'&game_id='.$data['game_code'].'&lang=en-US&access_token='.$data['token'];
        return $url;

    

    }

    public static function NoLimitLaunchUrl($data,$device){
        try {
        //     $client_details =ProviderHelper::getClientDetails('token',$data['token']);

        //     $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
        //     $isMob = is_numeric(strpos($ua, "mobile"));

        //     if($isMob == 1) {

        //           $url = 'https://prod.nlcasiacdn.net/loader/game-loader.html?device=mobile&language=en&operator=BETRNK&game='.$data['game_code'].'&token='.$data['token']; 
        //               return $url;
        //     }else {

        //     $url = 'https://prod.nlcasiacdn.net/loader/game-loader.html?device=desktop&language=en&operator=BETRNK&game='.$data['game_code'].'&token='.$data['token'];
        //      return $url;
        // }
        $url = 'https://prod.nlcasiacdn.net/loader/game-loader.html?device='.$device.'&language='.$data['lang'].'&operator=BETRNK&game='.$data['game_code'].'&token='.$data['token'];
        return $url;
         
        } catch (\Exception $e) {

            Helper::saveLog('Nolimit Gameluanch error', 23, json_encode('unable to launch'), $e->getMessage() );
            return $e->getMessage();
        }
        
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
    public static function pngLaunchUrl($game_code,$token,$provider,$exitUrl,$lang){
        $timestamp = Carbon::now()->timestamp;
        $exit_url = $exitUrl;
        $lang = GameLobby::getLanguage("PlayNGo",$lang);
        Helper::savePLayerGameRound($game_code,$token,$provider);
        $gameurl = config('providerlinks.png.root_url').'/casino/ContainerLauncher?pid='.config('providerlinks.png.pid').'&gid='.$game_code.'&channel='.
                   config('providerlinks.png.channel').'&lang='.$lang.'&practice='.config('providerlinks.png.practice').'&ticket='.$token.'&origin='.$exit_url;
        return $gameurl;
    }
    public static function edpLaunchUrl($game_code,$token,$provider,$exitUrl){
        $profile = "nofullscreen_money.xml";
        $sha1key = sha1($exitUrl.''.config("providerlinks.endorphina.nodeId").''.$profile.''.$token.''.config("providerlinks.endorphina.secretkey"));
        $sign = $sha1key; 
        Helper::savePLayerGameRound($game_code,$token,$provider);
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
    public static function boleLaunchUrl($game_code,$token,$exitUrl, $country_code='PH'){

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

    public static function evoplayLunchUrl($token,$game_code){
        $client_player_details = GameLobby::getClientDetails('token', $token);
        $requesttosend = [
          "project" => config('providerlinks.evoplay.project_id'),
          "version" => 1,
          "token" => $token,
          "game" => $game_code, //game_code, game_id
          "settings" =>  [
            'user_id'=> $client_player_details->player_id,
            'language'=> $client_player_details->language ? $client_player_details->language : 'en',
            'https' => true,
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

     public static function awsLaunchUrl($token,$game_code,$lang='en'){
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

    public static function rsgLaunchUrl($game_code,$token,$exitUrl,$lang='en', $provider_sub_name){
        $url = $exitUrl;
        $domain = parse_url($url, PHP_URL_HOST);
        Helper::savePLayerGameRound($game_code,$token,$provider_sub_name);
        $url = 'https://partnerapirgs.betadigitain.com/GamesLaunch/Launch?gameid='.$game_code.'&playMode=real&token='.$token.'&deviceType=1&lang='.$lang.'&operatorId=B9EC7C0A&mainDomain='.$domain.'';
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

    public static function cq9LaunchUrl($game_code, $token, $provider_sub_name){
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
            'lang'=> 'en',
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
        $url = $exitUrl;
        // $domain = parse_url($url, PHP_URL_HOST);
        $client_details = Providerhelper::getClientDetails('token', $token);
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
                $url = 'https://web.sa-globalxns.com/app.aspx?username='.config('providerlinks.sagaming.prefix').$client_details->player_id.'&token='.$login_token['Token'].'&lobby='.config('providerlinks.sagaming.lobby').'&lang='.$lang.'&returnurl='.$url.'';
                return $url;
            }else{
                return false;
            }
           
        }else{
            return false;
        }
       
    }

    public static function tidylaunchUrl( $game_code = null, $token = null){
        Helper::saveLog('Tidy Gameluanch', 23, "", "");
        try{
            $url = config('providerlinks.tidygaming.url_lunch');
            $client_details = Providerhelper::getClientDetails('token', $token);

            // $invite_code = config('providerlinks.tidygaming.usd_invite');
            // if ($client_details->default_currency == "THB" ) {
            //     $invite_code = config('providerlinks.tidygaming.thb_invite');
            // } elseif ($client_details->default_currency == "TRY") {
            //     $invite_code = config('providerlinks.tidygaming.try_invite');
            // } 
            $invite_code = config('providerlinks.tidygaming.currency')[$client_details->default_currency];
            
            $get_code_currency = TidyHelper::currencyCode($client_details->default_currency);
            $player_details = Providerhelper::playerDetailsCall($client_details->player_token);
            $requesttosend = [
                'client_id' =>  config('providerlinks.tidygaming.client_id'),
                'game_id' => $game_code,
                'username' => 'TGW_' . $client_details->player_id,
                'token' => $token,
                'uid' => 'TGW_'.$client_details->player_id,
                'currency' => $get_code_currency,
                'invite_code' => $invite_code
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
            ProviderHelper::saveLogGameLaunch('Tidy Gameluanch 102', 23, json_encode($requesttosend), $client_response);
            return $client_response->link;
        }catch(\Exception $e){
            $requesttosend = [
                'error' => 1010
            ];
            ProviderHelper::saveLogGameLaunch('Tidy Gameluanch 101', 23, json_encode($requesttosend), $e->getMessage() );
            return $e->getMessage();
        }
        
    }

     public static function slotmill($request){
        try {
            $client_details = Providerhelper::getClientDetails('token', $request["token"]);
            $url = config("providerlinks.slotmill")[$request["game_code"]]; 
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
            return $url = $url."?language=".$request["lang"]."&org=".config("providerlinks.slotmill.brand")."&currency=".$client_details->default_currency."&key=".$client_details->player_token;

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

    public static function tgglaunchUrl( $game_code = null, $token = null){
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
            'platform' => 'mobile'
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
        $url = "https://m.pgr-nmga.com/".$game_code."/index.html?language=en-us&bet_type=1&operator_token=".urlencode($operator_token)."&operator_player_session=".urlencode($token);
        return $url;
    }

    public static function boomingGamingUrl($data, $provider_name){
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
                'variant' => 'mobile', // mobile, desktop
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
            $error = [
                'error' => $e->getMessage()
            ];
            ProviderHelper::saveLogGameLaunch('Booming session error', config('providerlinks.booming.provider_db_id'), json_encode($data), $e->getMessage());
            return $error;
        }

    }

    public static function spadeLaunch($game_code,$token,$exitUrl,$lang='en_US'){
        $client_details = ProviderHelper::getClientDetails('token', $token);
        $domain =  $exitUrl;
        $url = 'https://lobby.silverkirinplay.com/TIGERG/auth/?acctId=TIGERG_'.$client_details->player_id.'&language='.$lang.'&token='.$token.'&game='.$game_code.'';
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
    
    public static function pragmaticplaylauncher($game_code = null, $token = null)
    {
        $stylename = config('providerlinks.tpp.secureLogin');
        $key = config('providerlinks.tpp.secret_key');
        $gameluanch_url = config('providerlinks.tpp.gamelaunch_url');
        $casinoId = config('providerlinks.tpp.casinoId');
        $wsUri = config('providerlinks.tpp.wsUri');

        $client_details = Providerhelper::getClientDetails('token', $token);
        $player_details = Providerhelper::playerDetailsCall($client_details->player_token);
        // $game_details = DB::select("SELECT * FROM games WHERE provider_id = '26' AND game_code = '".$game_code."' order by created  ");
        $game_details = DB::table('games')->where('provider_id','=',26)->where('game_code','=',$game_code)->orderBy('created_at','desc')->first();
        // $game_details = Helper::findGameDetails('game_code', 26, $game_code);

        $userid = "TGaming_".$client_details->player_id;
        $currency = $client_details->default_currency;
        $hashCreatePlayer = md5('currency='.$currency.'&externalPlayerId='.$userid.'&secureLogin='.$stylename.$key);

        $paramEncoded = urlencode("token=".$token."&symbol=".$game_code."&technology=H5&platform=WEB&language=en&lobbyUrl=daddy.betrnk.games");
        $url = "$gameluanch_url?key=$paramEncoded&stylename=$stylename";
        $result = json_encode($url);

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
        return $url;
        // }
    }

    public static function yggdrasillaunchUrl($data){
        $provider_id = config("providerlinks.ygg.provider_id");
        ProviderHelper::saveLogGameLaunch('YGG gamelaunch', $provider_id, json_encode($data), "Endpoing hit");
        $url = config("providerlinks.ygg.api_url");
        $org = config("providerlinks.ygg.Org");
        $client_details = ProviderHelper::getClientDetails('token',$data['token']);
        $player_details = Providerhelper::playerDetailsCall($client_details->player_token);
        try{
            $url = $url."gameid=".$data['game_code']."&lang=".$client_details->language."&currency=".$client_details->default_currency."&org=".$org."&channel=pc&key=".$data['token'];
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
                    ProviderHelper::saveLogGameLaunch('GoldenF get_url', $provider_id, json_encode($get_url), $data);
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

    private static function icgGameUrl($currency){
        $http = new Client();
        $response = $http->get(config("providerlinks.icgaminggames"), [
            'headers' =>[
                'Authorization' => 'Bearer '.GameLobby::icgConnect($currency),
                'Accept'     => 'application/json'
            ]
        ]);
        return json_decode((string) $response->getBody(), true);
    }
    private static function icgConnect($currency){
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

    public static function mannaLaunchUrl($game_code,$token,$exitUrl, $lang = ''){
        $client_details = GameLobby::getClientDetails('token', $token);
        $lang = GameLobby::getLanguage("Manna Play", $lang);
        // Authenticate New Token
        $auth_token = new Client([ // auth_token
                'headers' => [ 
                    'Content-Type' => 'application/json',
                    'apiKey' => config("providerlinks.manna.AUTH_API_KEY")
                ]
            ]);

        $auth_token_response = $auth_token->post(config("providerlinks.manna.AUTH_URL"),
                ['body' => json_encode(
                        [
                            "id" => "betrnk",
                            "account" => $client_details->player_id,
                            "currency" => $client_details->default_currency,
                            "sessionId" => $token,
                            "channel" => ($client_details->test_player ? "demo" : "")
                        ]
                )]
            );

        $auth_result = json_decode($auth_token_response->getBody()->getContents());

        // Generate Game Link
        $game_link = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/json',
                    'apiKey' => config("providerlinks.manna.AUTH_API_KEY"),
                    'token' => $auth_result->token
                ]
            ]);

        $game_link_response = $game_link->post(config("providerlinks.manna.GAME_LINK_URL"),
                ['body' => json_encode(
                        [
                            "account" => $client_details->player_id,
                            "sessionId" => $token,
                            "language" => $lang,
                            "gameId" => $game_code,
                            "exitUrl" => $exitUrl
                        ]
                )]
            );

        $link_result = json_decode($game_link_response->getBody()->getContents());
        
        return $link_result->url;
    }

    public static function ozashikiLaunchUrl($game_code,$token,$exitUrl, $lang = '') {
        /*$client_details = GameLobby::getClientDetails('token', $token);*/
        $client_details = ProviderHelper::getClientDetails('token', $token);
        $lang = GameLobby::getLanguage("Ozashiki", $lang);
        // Authenticate New Token
        $auth_token = new Client([ // auth_token
                'headers' => [ 
                    'Content-Type' => 'application/json',
                    'apiKey' => config("providerlinks.ozashiki.AUTH_API_KEY")
                ]
            ]);

        $auth_token_response = $auth_token->post(config("providerlinks.ozashiki.AUTH_URL"),
                ['body' => json_encode(
                        [
                            "id" => config("providerlinks.ozashiki.PLATFORM_ID"),
                            "account" => $client_details->player_id,
                            "currency" => $client_details->default_currency,
                            "sessionId" => $token,
                            "channel" => ($client_details->test_player ? "demo" : "")
                        ]
                )]
            );

        $auth_result = json_decode($auth_token_response->getBody()->getContents());

        // Generate Game Link
        $game_link = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/json',
                    'apiKey' => config("providerlinks.ozashiki.AUTH_API_KEY"),
                    'token' => $auth_result->token
                ]
            ]);

        $game_link_response = $game_link->post(config("providerlinks.ozashiki.GAME_LINK_URL"),
                ['body' => json_encode(
                        [
                            "account" => $client_details->player_id,
                            "sessionId" => $token,
                            "language" => $lang,
                            "gameId" => $game_code,
                            "exitUrl" => $exitUrl
                        ]
                )]
            );

        $link_result = json_decode($game_link_response->getBody()->getContents());
        return $link_result->url;
        
        /*switch($client_details->wallet_type){
            case 1:
                return $link_result->url;
            case 2:
                return TWGameLaunchHelper::TwLaunchUrl($token, 'Ozashiki', $link_result->url, $client_details->player_id, $exitUrl);
            case 3:
                return PureTransferWalletHelper::PTwLaunchUrl($token, 'Ozashiki', $link_result->url, $client_details->player_id, $exitUrl);
            default:
                return false;
        }*/
        // return $link_result->url;
    }

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
        $queryString = "method=LoginRequest&Key=".$secretKey."&Time=".$dateTime."&Username=".$client_details->username."&CurrencyType=".$client_details->default_currency."&GameCode=".$game_code."&Mobile=0";
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
            $data = "balance".$formatBalance."callback_url".config('providerlinks.oauth_mw_api.mwurl').'/api/onlyplay'."currency".$client_player_details->default_currency."decimals".$decimals."game_bundle".$game_code."langen"."partner_id".config('providerlinks.onlyplay.partner_id')."token".$token.'user_idTG_'.$client_player_details->player_id; 
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
                'user_id' => 'TG_'.$client_player_details->player_id,
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
                $country_code = $data['country_code'];
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
            $game_name = DB::select('SELECT game_name FROM games WHERE provider_id = 57 and game_code = '.$data['game_code'].'');
            $remove[] = "'";
            $remove[] = ' ';
            $game_details = $game_name[0];
            $get_name = str_replace($remove,'', $game_details->game_name);
 
            $game_url = 'https://ams5-games.ttms.co/casino/default/game/game.html?playerHandle='.$val.'&account='.$client_details->default_currency.'&gameName='.$get_name.'&gameType=0&gameId='.$data['game_code'].'&lang=en&deviceType=web&lsdId=TIGERGAMES';

            return $game_url;

        } catch (\Exception $e) {
            return $e->getMessage().' '.$e->getLine().' '.$e->getFile();
            // $error = '<gametoken uid="usd001">
            //             <error code="1002" message="unable to login" />
            //           </gametoken>';
            // Helper::saveLog('TopTrendGaming 1002', $provider, json_encode($requesttosend), $e->getMessage() );
            // return response($error,200) 
            //       ->header('Content-Type', 'application/xml');
        }


    }

    public static function PlayTechLaunch($data){
        Helper::saveLog('PlayTech GAMELUANCH', 68, json_encode($data),  "HIT" );
        $client_details = ProviderHelper::getClientDetails('token',$data['token']);
        $getGameDetails = Helper::findGameDetails( "game_code",config('providerlinks.playtech.provider_db_id'), $data['game_code']);
        if($getGameDetails->game_type_id == "15"){
            $gameUrl = config('providerlinks.playtech.api_url')."/launcher?gameCode=".$getGameDetails->info."&token=".$data['token']."&platform=web&language=en&playerId=".$client_details->player_id."&brandId=".config('providerlinks.playtech.brand_id')."&mode=1&backUrl=".$data['exitUrl'].'&tableAlias='.$data['game_code'];
            Helper::saveLog('PlayTech GAMELUANCH', 68, json_encode($gameUrl),  "HIT" );
            return $gameUrl;
        } else {
            $gameUrl = config('providerlinks.playtech.api_url')."/launcher?gameCode=".$data['game_code']."&token=".$data['token']."&platform=web&language=en&playerId=".$client_details->player_id."&brandId=".config('providerlinks.playtech.brand_id')."&mode=1&backUrl=".$data['exitUrl'];
            Helper::saveLog('PlayTech GAMELUANCH', 68, json_encode($gameUrl),  "HIT" );
            return $gameUrl;
        }

        return "false";
       
    }
    public static function FunkyGamesLaunch($data){
        Helper::saveLog('FunkyGames GAMELUANCH', 110, json_encode($data),  "HIT" );
        $client_details = ProviderHelper::getClientDetails('token',$data['token']);
        if (!empty($_SERVER['HTTP_CLIENT_IP']))   
          {
            $ip_address = $_SERVER['HTTP_CLIENT_IP'];
          }
        //whether ip is from proxy
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))  
          {
            $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
          }
        //whether ip is from remote address
        else
          {
            $ip_address = $_SERVER['REMOTE_ADDR'];
          }
        try {
            
                $paramsToSend = [
                    'gameCode' => $data['game_code'],
                    'userName' => $client_details->username,
                    'playerId' => $client_details->player_id,
                    'currency' => $client_details->default_currency,
                    'language' => 'en',
                    'playerIp' => $ip_address,
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
                    Helper::saveLog('funky games launch',config('providerlinks.funkygames.provider_db_id'), json_encode($paramsToSend), $game_luanch_response);
                // dd($game_luanch_response->data->gameUrl);
                // $gameUrl = $game_luanch_response->data->gameUrl."?token=".$game_luanch_response->data->token."&redirectUrl=https://daddy.betrnk.games/provider/FunkyGames";
                $gameUrl = $game_luanch_response->data->gameUrl."?token=".$game_luanch_response->data->token."&redirectUrl=".$data["exitUrl"];

                Helper::saveLog('funky games launch',config('providerlinks.funkygames.provider_db_id'), json_encode($paramsToSend), $gameUrl);
                return $gameUrl;

        } catch (\Exception $e) {

                return $e->getMessage();
                
        }
    }

}

?>
