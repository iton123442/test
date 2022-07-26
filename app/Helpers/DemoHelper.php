<?php
namespace App\Helpers;

use App\Helpers\GameLobby;
use App\Helpers\ProviderHelper;
use GuzzleHttp\Client;
use App\Services\AES;
use DB;

class DemoHelper{
    
    public static function DemoGame($json_data){

        $data = json_decode(json_encode($json_data));

        # Game Demo Endpoint  (ENDPOINT THAT ONLY GET TWO PARAMETERS game_code and game_provider)
        $exitUrl = isset($data->exitUrl) ? $data->exitUrl : '';
        $lang = isset($data->lang) ? $data->lang : '';

        $game_details = DemoHelper::findGameDetails($data->game_provider, $data->game_code);

        if($game_details == false){
            $response = array(
                "game_code" => $data->game_code,
                "url" => config('providerlinks.play_betrnk') . '/tigergames/api?msg=No Game Found',
                "game_launch" => false
            );
            return response($response,200)
            ->header('Content-Type', 'application/json');  
        }

        $provider_id = GameLobby::checkAndGetProviderId($data->game_provider);
        $provider_code = $provider_id->sub_provider_id; // SUBPROVIDER ID!
        
        if($provider_code == 33){ // Bole Gaming
            $response = array(
                "game_code" => $data->game_code,
                "url" => DemoHelper::getStaticUrl($data->game_code, $provider_code),
                "game_launch" => true
            );
        }
        elseif(in_array($provider_code, [39, 78, 79, 80, 81, 82, 83])){
            $response = array(
                "game_code" => $data->game_code,
                "url" => DemoHelper::oryxLaunchUrl($data->game_code, $lang, $exitUrl), 
                "game_launch" => true
            );
        }
        elseif($provider_code == 104){ // Manna Play Betrnk && Manna Play
            $response = array(
                "game_code" => $data->game_code,
                "url" => DemoHelper::getStaticUrl($data->game_code, $provider_code),
                "game_launch" => true
            );
        }
        elseif($provider_code == 60){ // ygg drasil direct
            $response = array(
                "game_code" => $data->game_code,
                "url" => DemoHelper::yggDrasil($data->game_code,$lang),
                "game_launch" => true
            );
        }
        elseif($provider_code == 49){ // pragmatic play
            Helper::saveLog('Game Launch Pragmatic Play Demo Tirgger', 26, json_encode($data), "im here!");
            if(isset($data->token)) {
                $client_details = ProviderHelper::getClientDetails("ptw",$data->client_player_id, 1, 'all', $data->client_id);
            }else{
                $client_details = false;
            }
            $response = array(
                "game_code" => $data->game_code,
                "url" => DemoHelper::demoPragamticPlay($data->game_code,$lang,$client_details),
                "game_launch" => true
            );
        }
        elseif($provider_code == 95){ // 5Men Gaming
            $game_provider = "5Men Gaming";
            $response = array(
                "game_code" => $data->game_code,
                "url" => DemoHelper::fivemenlaunchUrl($data->game_code,$data->token,$data->exitUrl,$game_provider),
                "game_launch" => true
            );
        }
        elseif($provider_code == 55){ // pgsoft
            $response = array(
                "game_code" => $data->game_code,
                "url" => DemoHelper::pgSoft($data->game_code,$lang, $exitUrl),
                "game_launch" => true
            );
        }
        elseif($provider_code == 40){  // Evoplay
            $response = array(
                "game_code" => $data->game_code,
                "url" => DemoHelper::evoplay($data->game_code,$lang, $exitUrl),
                "game_launch" => true
            );
        }
        elseif($provider_code == 75){  // KAGaming
            $response = array(
                "game_code" => $data->game_code,
                "url" => DemoHelper::kagaming($data->game_code,$lang,$exitUrl),
                "game_launch" => true
            );
        }

        elseif($provider_code==38){ // Mannaplay
            $response = array(
                "game_code" => $data->game_code,
                "url" => DemoHelper::mannaLaunchUrl($data->game_code,$lang,$exitUrl),
                "game_launch" => true
            );
        }
        elseif($provider_code==89){ //slotmill

            $response = array(
                "game_code" =>$data->game_code,
                "url" =>  DemoHelper::slotmill($data->game_code,$lang,$exitUrl),
                "game_launch" => true
            );
            
        }
        elseif($provider_code==56){ //playngo

            $response = array(
                "game_code" =>$data->game_code,
                "url" =>  DemoHelper::playngo($data),
                "game_launch" => true
            );
            
        }
        elseif($provider_code==109){ //playngo

            $response = array(
                "game_code" =>$data->game_code,
                "url" =>  DemoHelper::mancalaDemo($data),
                "game_launch" => true
            );
            
        }
        // elseif($provider_code == 34){ // EDP
        //     // / $client = new Client();
        //     // $guzzle_response = $client->get('https://edemo.endorphina.com/api/link/accountId/1002 /hash/' . md5("endorphina_4OfAKing@ENDORPHINA") . '/returnURL/' . $returnURL);
        //     // $guzzle_response = $client->get('http://edemo.endorphina.com/api/link/accountId/1002/hash/' . md5("endorphina2_SugarGliderDice@ENDORPHINA"));
        //     // $guzzle_response = $client->get('https://edemo.endorphina.com/api/link/accountId/1002/hash/5bb33a3ec5107d46fe5b02d77ba674d6');
        //     // $demoLink = file_get_contents('https://edemo.endorphina.com/api/link/accountId/1002/hash/5bb33a3ec5107d46fe5b02d77ba674d6');
        //     // return json_encode($demoLink);

        //     $game_code = $json_data['game_code'];
        //     $game_name = explode('_', $game_code);
        //     $game_code = explode('@', $game_name[1]);
        //     $game_gg = $game_code[0];
        //     $arr = preg_replace("([A-Z])", " $0", $game_gg);
        //     $arr = explode(" ", trim($arr));
        //     if (count($arr) == 1) {
        //         $url = 'https://endorphina.com/games/' . strtolower($arr[0]) . '/play';
        //     } else {
        //         $url = 'https://endorphina.com/games/' . strtolower($arr[0]) . '-' . strtolower($arr[1]) . '/play';
        //     }
        //     $msg = array(
        //         "game_code" => $json_data['game_code'],
        //         "url" => $url,
        //         "game_launch" => true
        //     );
        //     return response($msg, 200)
        //     ->header('Content-Type', 'application/json');
        // }
        else{
           

           try {
                $http_client = new Client();
                $response_client = $http_client->get('https://api-test.betrnk.games/public/game/launchurl/playforfun?game_code='.$data->game_code.'&provider_name='.$data->game_provider);
                $response = $response_client->getBody();
           } catch (\Throwable $th) {
                $response = array(
                    "game_code" => $data->game_code,
                    "url" => config('providerlinks.play_betrnk') . '/tigergames/api?msg=No Demo Available',
                    "game_launch" => false
                );
           }
        }

        return response($response,200)
            ->header('Content-Type', 'application/json');  
    }

    # Providers That Has Static URL DEMO LINK IN THE DATABASE
    public static function getStaticUrl($game_code, $sub_provider_id){
         $game_demo = DB::table('games as g')
        ->select('g.game_demo')
        ->where('g.game_code', $game_code)
        ->where('g.sub_provider_id', $sub_provider_id)
        ->first();
        return $game_demo->game_demo;
    }

    public static function findGameDetails($game_provider, $game_code) {
        $provider_id = GameLobby::checkAndGetProviderId($game_provider);
        if($provider_id == null){ return false;}
        $provider_code = $provider_id->sub_provider_id;
        $game_details = DB::table("games as g")->leftJoin("providers as p","g.provider_id","=","p.provider_id");
        $game_details->where([
            ["g.sub_provider_id", "=", $provider_code],
            ["g.game_code",'=', $game_code],
        ]);
        $result= $game_details->first();
        return $result ? $result : false;
    }

    public static function oryxLaunchUrl($game_code, $lang, $exitUrl){
        $lang = $lang != '' ? (strtolower(ProviderHelper::getLangIso($lang)) != false ? strtolower(ProviderHelper::getLangIso($lang)) : 'ENG') : 'ENG';
        $exitUrl = $exitUrl != '' ? $exitUrl : '';
        $url = config("providerlinks.oryx.GAME_URL").$game_code.'/open?languageCode='.$lang.'&playMode=FUN&lobbyUrl='.$exitUrl.'';
        return $url;
    }

    public static function yggDrasil($game_code,$lang){
        $lang = $lang != '' ? (strtolower(ProviderHelper::getLangIso($lang)) != false ? strtolower(ProviderHelper::getLangIso($lang)) : 'en') : 'en';
        // return 'https://static-pff-tw.248ka.com/init/launchClient.html?gameid='.$game_code.'&lang='.$lang.'&currency=USD&org='.config('providerlinks.ygg.Org').'&channel=pc';
        return 'https://static-fra.pff-ygg.com/init/launchClient.html?gameid='.$game_code.'&lang='.$lang.'&currency=EUR&org=DEMO&channel=pc';
    }

    public static function demoPragamticPlay($game_code,$lang,$client_details){
        $lang = $lang != '' ? (strtolower(ProviderHelper::getLangIso($lang)) != false ? strtolower(ProviderHelper::getLangIso($lang)) : 'en') : 'en';
        if($client_details == false){ $currency = 'USD'; $lang = 'en';}else{ $currency = $client_details->default_currency; $lang = $client_details->language; }
        // return 'https://demogamesfree.pragmaticplay.net/gs2c/openGame.do?lang=en&cur='.$currency.'&gameSymbol='.$game_code.'&lobbyURL=https://daddy.betrnk.games&stylename=some_secureLogin';
        $url = 'https://demogamesfree.pragmaticplay.net/gs2c/openGame.do?lang='.$lang.'&cur='.$currency.'&gameSymbol='.$game_code.'&lobbyURL=https://daddy.betrnk.games&stylename=some_secureLogin';
        Helper::saveLog('Game Launch Pragmatic Play Demo Url', 26, $url, json_encode($client_details));
        return $url;
    }

    // YGG DONT SUPPORT RETURN URL
    public static function pgSoft($game_code,$lang,$exitUrl){
        $operator_token = config('providerlinks.pgsoft.operator_token');
        $lang = $lang != '' ? (strtolower(ProviderHelper::getLangIso($lang)) != false ? strtolower(ProviderHelper::getLangIso($lang)) : 'en') : 'en';
        $url = 'https://m.pg-redirect.net/'.$game_code.'/index.html?language='.$lang.'&bet_type=2&operator_token='.urlencode($operator_token);
        return $url;
    }

    public static function kagaming($game_code,$lang,$exitUrl){
        $lang = $lang != '' ? (strtolower(ProviderHelper::getLangIso($lang)) != false ? strtolower(ProviderHelper::getLangIso($lang)) : 'en') : 'en';
        $url = '' . config('providerlinks.kagaming.gamelaunch') . '/?g=' . $game_code . '&l='.$exitUrl.'&p=' . config('providerlinks.kagaming.partner_name') . '&u=1&t=RiANDRAFT&da=charity&cr=USD&loc='.$lang.'&m=1&tl=GUIOGUIO' . '&ak=' . config('providerlinks.kagaming.access_key') . '';
        return $url;
    }

    public static function playngo($request){ //56
        $lang = isset($request->lang) ? $request->lang : 'en';
        $lang = GameLobby::getLanguage("PlayNGo",$lang);
        $key = "LUGTPyr6u8sRjCfh";
        $aes = new AES($key);
        $data = array(
            'root_url' => config('providerlinks.png.root_url'),
            'exitUrl' => isset($request->exitUrl) ? $request->exitUrl : 'www.google.com',
            'game_code' => $request->game_code, //gid in png
            'pid' => config('providerlinks.png.pid'),
            'lang' => $lang,
            'practice' => 1, //demo
            'channel' => isset($request->device) ? $request->device : 'desktop'
        );
        $encoded_data = $aes->AESencode(json_encode($data));
        $urlencode = urlencode(urlencode($encoded_data));
        $gameurl = config('providerlinks.play_tigergames').'/api/playngo/tgload/'.$urlencode; 
        return $gameurl;
    }
    public static function mancalaDemo($request){ //56
        $game_launch = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/json'
                ]
            ]);
        $hash = md5("GetToken/".config("providerlinks.mancala.PARTNER_ID").$request->game_code.config("providerlinks.mancala.API_KEY"));
        $datatosend = [
                "PartnerId" => config("providerlinks.mancala.PARTNER_ID"),
                "GameId" => $request->game_code,
                "Lang" => "EN",
                "ClientType" => 1,
                "IsVirtual" => false,
                "Hash" => $hash,
                "DemoMode" => true,
                "ExtraData" => "data"
            ];
        $game_launch_response = $game_launch->post(config("providerlinks.mancala.RGS_URL")."/GetToken",
                ['body' => json_encode($datatosend)]
            );

        $game_launch_url = json_decode($game_launch_response->getBody()->getContents());

        if($game_launch_url !== NULL) {
            Helper::playerGameRoundUuid($request->game_code, $request->token, $request->game_provider, $game_launch_url->Token);
            return $game_launch_url->IframeUrl."&backurl=".$request->exitUrl;  
        } 
    }
    public static function evoplay($game_code, $lang, $exit_url){
        $lang = $lang != '' ? (strtolower(ProviderHelper::getLangIso($lang)) != false ? strtolower(ProviderHelper::getLangIso($lang)) : 'en') : 'en';
        $requesttosend = [
          "project" => config('providerlinks.evoplay.project_id'),
          "version" => 1,
          "token" => 'demo',
          "game" => $game_code, //game_code, game_id
          "settings" =>  [
            'language'=>$lang,
            'https' => true,
            'exit_url' => isset($exit_url) ? $exit_url : "",
          ],
          "denomination" => '1', // game to be launched with values like 1.0, 1, default
          "currency" => 'USD',
          "return_url_info" => true, // url link
          "callback_version" => 2, // POST CALLBACK
        ];
        $signature =  ProviderHelper::getSignature($requesttosend, config('providerlinks.evoplay.secretkey'));
        $requesttosend['signature'] = $signature;
        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        ]);
        $response = $client->post(config('providerlinks.evoplay.api_url').'/game/geturl',[
            'form_params' => $requesttosend,
        ]);
        $res = json_decode($response->getBody(),TRUE);
        return isset($res['data']['link']) ? $res['data']['link'] : false;
    }
    public static function fivemenlaunchUrl( $game_code = null, $token = null, $exiturl, $provider){
        $requesttosend = [
          "project" => config('providerlinks.5men.project_id'),
          "version" => 1,
          "token" => "demo",
          "game" => $game_code, //game_code, game_id
          "settings" =>  [
            'user_id'=> "14602",
            'language'=> "en",
            'https' => 1,
            'platform' => 'mobile'
          ],
          "denomination" => '1', // game to be launched with values like 1.0, 1, default
          "currency" => "USD",
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

    public static function mannaLaunchUrl($game_code, $lang ,$exitUrl){
        $lang = GameLobby::getLanguage("Manna Play", $lang);
        // Authenticate New Token
        try {
            // Generate Game Link
            $game_link = new Client();
            $game_link_response = $game_link->post(config("providerlinks.mannaplay.GAME_LINK_URL").'tigergame/gameLink/link',
                    ['body' => json_encode(
                            [
                                "mode" => "demo",
                                "language" => $lang,
                                "gameId" => $game_code,
                                "exitUrl" => $exitUrl
                            ]
                    )]
                );
            $link_result = json_decode($game_link_response->getBody()->getContents());
            return $link_result->url;
        } catch (\Exception $e) {
            return $exitUrl;
        }

       
    }

    public static function slotmill($game_code, $lang ,$exitUrl){
        // Authenticate New Token
        $lang = $lang == '' ? 'en' : $lang; 
        $getGameDetails = Helper::findGameDetails( "game_code",config('providerlinks.slotmill.provider_db_id'), $game_code);
        try {
            return $url = $getGameDetails->game_demo."/?language=".$lang."&org=".config("providerlinks.slotmill.brand")."&currency=USD&homeurl=".$exitUrl;
        } catch (\Exception $e) {
            return $exitUrl;
        }
       
    }
}