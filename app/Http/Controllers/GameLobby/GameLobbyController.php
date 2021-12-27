<?php

namespace App\Http\Controllers\GameLobby;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Game;
use App\Models\GameType;
use App\Models\GameProvider;
use App\Models\GameSubProvider;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Helpers\ClientHelper;
use App\Helpers\ClientRequestHelper;
use App\Helpers\DemoHelper;
use App\Helpers\GameLobby;
use App\Models\ClientGameSubscribe;
use Stripe\Balance;
use DB;
use GameLobby as GlobalGameLobby;

class GameLobbyController extends Controller
{

    public $image_url = 'https://bo-test.betrnk.games/';
    //
    public function __construct(){
        //$this->middleware('oauth', ['except' => ['index']]);
        /*$this->middleware('authorize:' . __CLASS__, ['except' => ['index', 'store']]);*/
    }
    public function getGameList(Request $request){
        if($request->has("client_id")){
            
            $excludedlist = ClientGameSubscribe::with("selectedProvider")->with("gameExclude")->with("subProviderExcluded")->where("client_id",$request->client_id)->get();
            if(count($excludedlist)>0){
                $gamesexcludeId=array();
                foreach($excludedlist[0]->gameExclude as $excluded){
                    array_push($gamesexcludeId,$excluded->game_id);
                }
                $subproviderexcludeId=array();
                foreach($excludedlist[0]->subProviderExcluded as $excluded){
                    array_push($subproviderexcludeId,$excluded->sub_provider_id);
                }
                if($request->has("type")){
                    $type = GameType::with("game.provider")->get();
                    return $type;
                }
                else{
                    $data = array();
                    $sub_providers = GameSubProvider::with(["games.game_type","games"=>function($q)use($gamesexcludeId){
                        $q->whereNotIn("game_id",$gamesexcludeId)->where("on_maintenance",0);
                    }])->whereNotIn("sub_provider_id",$subproviderexcludeId)->where("on_maintenance",0)->get(["sub_provider_id","sub_provider_name", "icon"]);
                    foreach($sub_providers as $sub_provider){
                        $subproviderdata = array(
                            "provider_id" => "sp".$sub_provider->sub_provider_id,
                            "provider_name" => $sub_provider->sub_provider_name,
                            "icon" => $sub_provider->icon,
                            "games_list" => array(),
                        );
                        foreach($sub_provider->games as $game){
                            if($game->game_type){
                                $game = array(
                                    "game_id" => $game->game_id,
                                    "game_name"=> $game->game_name,
                                    "game_code"=> $game->game_code,
                                    "min_bet"=> $game->min_bet == null ? 10 : $game->min_bet,
                                    "max_bet"=> $game->max_bet == null ? 1000 : $game->max_bet,
                                    "game_provider"=>$sub_provider->sub_provider_name,
                                    "game_type" => $game->game_type->game_type_name,
                                    "game_icon" => $game->icon,
                                );
                                array_push($subproviderdata["games_list"],$game);
                            }
                        }
                        array_push($data,$subproviderdata);
                    }
                    return $data;
                }
            }
            else{
                $msg = array(
                    "message" => "Client Id Doesnt Exist / Client doesnt have Subcription Yet!",
                );
                return response($msg,401)
                ->header('Content-Type', 'application/json');
            }
        }
        
    }


    public function createFallbackLink($data){
        $log_id = Helper::saveLog('GAME LAUNCH', 99, json_encode($data), 'FAILED LAUNCH');
        $url =config('providerlinks.tigergames').'/tigergames/api?msg=Something went wrong please contact Tiger Games&id='.$log_id;
        return $url;
    }
    public function gameLaunchUrl(Request $request){

        // Save Every Gamelaunch from the client
        ProviderHelper::saveLogWithExeption('GAMELAUNCH LOG', 12, json_encode($request->all()), 'GAME REQUEST BODY');

        // Demo Handler
        // Required Parameter game_code, game_provider
        if ($request->has("demo") && $request->input("demo") == true) {
            if($request->has('game_code')
                &&$request->has('game_provider')){
                return DemoHelper::DemoGame($request->all());
            }
        }

        if($request->has('client_id')
        &&$request->has('client_player_id')
        &&$request->has('username')
        &&$request->has('email')
        &&$request->has('display_name')
        &&$request->has('game_code')
        &&$request->has('exitUrl')
        &&$request->has('game_provider')
        &&$request->has('token')){
            if($request->has('ip_address')){
                $ip_address = $request->ip_address;
            }
            else{
                $ip_address = "127.0.0.1";
            }
            $device = $request->has("device")? $request->input("device"): 'desktop'; // default desktop [desktop,mobile]
            $provider_id = GameLobby::checkAndGetProviderId($request->game_provider);
            if($provider_id){
                $provider_code = $provider_id->sub_provider_id;
            }else{
                return response(["error_code"=>"404","message"=>"Provider Code Doesnt Exist/Not Found"],200)
                 ->header('Content-Type', 'application/json');
            }
            // CLIENT SUBSCRIPTION FILTER
            
           $subscription_checker = $this->checkGameAccess($request->input("client_id"), $request->input("game_code"), $provider_code);
           if($request->input("client_id") == 92){
                return $subscription_checker;
            }
           if(!$subscription_checker){
               $log_id = Helper::saveLog('GAME LAUNCH NO SUBSCRIPTION', 1223, json_encode($request->all()), 'FAILED LAUNCH '.$request->input("client_id"));
               $msg = array(
                   "game_code" => $request->input("game_code"),
                   "url" => config('providerlinks.play_betrnk').'/tigergames/api?msg='.ClientHelper::getClientErrorCode(3).'&id='.$log_id,
                   "game_launch" => false
               );
               return $msg;
           }

            // // Filters
            if(ClientHelper::checkClientID($request->all()) != 200){
                $log_id = Helper::saveLog('GAME LAUNCH', 1223, json_encode($request->all()), 'FAILED LAUNCH '.$request->client_id);
                $msg = array(
                    // "error_code" => ClientHelper::checkClientID($request->all()),
                    "message" => ClientHelper::getClientErrorCode(ClientHelper::checkClientID($request->all())),
                    "url" => config('providerlinks.play_betrnk').'/tigergames/api?msg='.ClientHelper::getClientErrorCode(ClientHelper::checkClientID($request->all())).'&id='.$log_id,
                    "game_launch" => false
                );
                return response($msg,200)
                ->header('Content-Type', 'application/json');
            }
            
            
           $solid_gamings = [2, 3, 5, 6, 7, 8, 10, 9, 11, 13, 14, 15, 16, 19, 20, 21, 22, 23, 24, 25, 26, 28];

            $lang = $request->has("lang")?$request->input("lang"):"en";
            if($token=Helper::checkPlayerExist($request->client_id,$request->client_player_id,$request->username,$request->email,$request->display_name,$request->token,$ip_address)){

               

                # Check if player is allowed to play a specific game
                Helper::savePLayerGameRound( $request->input("game_code"), $request->input("token"), $request->input("game_provider"));
                // $checkplayer = ProviderHelper::checkClientPlayer($request->client_id, $request->client_player_id);
                // $check_game_details = ProviderHelper::getSubGameDetails($provider_code, $request->input("game_code"));
                // $isRestricted = ProviderHelper::checkGameRestricted($check_game_details->game_id,$checkplayer->player_id);
                // if($isRestricted){
                //     // $attempt_resend_transaction = ClientRequestHelper::fundTransferResend($isRestricted);
                //     // if(!$attempt_resend_transaction){
                //         $log_id = Helper::saveLog('GAME LAUNCH', 1223, json_encode($request->all()), 'FAILED LAUNCH GAME RESTRICTED '.$request->client_id);
                //         $msg = array(
                //             "message" => ClientHelper::getClientErrorCode(10),
                //             "url" => config('providerlinks.play_betrnk').'/tigergames/api?msg=Player is Restricted&id='.$log_id,
                //             "game_launch" => false
                //         );
                //         return response($msg,200)
                //         ->header('Content-Type', 'application/json');
                //     // }
                // }

                 # EXPERIMENTAL - GAME BALANCE INHOUSE (SAVE ALL PLAYER BALANCE)

                 $save_balance = ProviderHelper::saveBalance($request->token);

                 if($save_balance == false){
                     $log_id = Helper::saveLog('GAME LAUNCH', 1223, json_encode($request->all()), 'FAILED LAUNCH SAVE BALANCE'.$request->client_id);
                     $msg = array(
                         "message" => ClientHelper::getClientErrorCode(ClientHelper::checkClientID($request->all())),
                         "url" => config('providerlinks.play_betrnk').'/tigergames/api?msg='.ClientHelper::getClientErrorCode(ClientHelper::checkClientID($request->all())).'&id='.$log_id,
                         "game_launch" => false
                     );
                     return response($msg,200)
                     ->header('Content-Type', 'application/json');
                 }

                
                if($provider_code==35){
                    $url = GameLobby::icgLaunchUrl($request->game_code,$token,$request->exitUrl,$request->input('game_provider'),$lang);
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => $url,
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==44 || $provider_code==45){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::booongoLaunchUrl($request->game_code,$token,$request->input('game_provider'),$request->exitUrl),
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                
                elseif($provider_code==34){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::edpLaunchUrl($request->game_code,$token,$request->input('game_provider'),$request->exitUrl),
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==51){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::fcLaunchUrl($request->game_code,$token,$request->exitUrl,$request->input('game_provider')),
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==56){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::pngLaunchUrl($request->game_code,$token,$request->input('game_provider'),$request->exitUrl,$lang),
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==57){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::wazdanLaunchUrl($request->game_code,$token,$request->input('game_provider'),$request->exitUrl),
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==70){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::upgLaunchUrl($request->game_code,$token,$request->input('game_provider'),$request->exitUrl),
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==77){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::microgamingLaunchUrl($request->game_code,$token,$request->input('game_provider'),$request->exitUrl),
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code== 74 || $provider_code == 107  || $provider_code == 108 ){
                    Helper::saveLog('HIT_EVG_LAUNCHURL', 12, json_encode(["msg"=>$request->all() ,"player_id"=>$token]), ["authentication"]);
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::evolutionLaunchUrl($request->game_code,$token,$request->input('game_provider'),$request->exitUrl,$ip_address,$lang),
                        "game_launch" => true
                    );
                    Helper::saveLog('reqlaunchURL(EVG)', 12, json_encode(["msg"=> $msg,"player_id"=>$token]), ["authentication"]);
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                 elseif($provider_code==33){
                    $country_code =  $request->has('country_code') ? $request->country_code : 'PH';
                    $url = GameLobby::boleLaunchUrl($request->game_code,$token,$request->input('game_provider'),$request->exitUrl,$country_code);
                    if($url != false && $url != 'false'){
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $url,
                            "game_launch" => true
                        );
                    }else{
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $this->createFallbackLink($request->all()),
                            "game_launch" => false
                        );
                    }
                    return $msg;
                }
                elseif($provider_code==36){ // request->token
                    Helper::saveLog('DEMO CALL', 14, json_encode($request->all()), 'DEMO');
                    $lang = GameLobby::getLanguage($request->game_provider,$request->lang);
                    $url = GameLobby::rsgLaunchUrl($request->game_code,$request->token,$request->exitUrl,$lang,$request->input('game_provider'));
                    if($url != false && $url != 'false'){
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $url, //TEST
                            "game_launch" => true
                        );
                    }else{
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $this->createFallbackLink($request->all()),
                            "game_launch" => false
                        );
                    }
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                
                elseif($provider_code==52){ // request->token
                    $url = GameLobby::skyWindLaunch($request->game_code,$token);
                    if($url != false && $url != 'false'){
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $url,
                            "game_launch" => true
                        );
                    }else{
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $this->createFallbackLink($request->all()),
                            "game_launch" => false
                        );
                    }
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==54){ // request->token
                    // $url = GameLobby::cq9LaunchUrl($request->game_code,$token);
                    $url = GameLobby::cq9LaunchUrl($request->game_code,$token,$request->input('game_provider'), $request->exitUrl);
                    if($url != false && $url != 'false'){
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $url,
                            "game_launch" => true
                        );
                    }else{
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $this->createFallbackLink($request->all()),
                            "game_launch" => false
                        );
                    }
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==48){ // request->token
                    $url = GameLobby::saGamingLaunchUrl($request->game_code,$request->token,$request->exitUrl,$lang);
                    if($url != false && $url != 'false'){
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $url,
                            "game_launch" => true
                        );
                    }else{
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $this->createFallbackLink($request->all()),
                            "game_launch" => false
                        );
                    }
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==75){ // request->token
                    if($request->has('lang')){
                        $lang = ProviderHelper::getLanguage($request->game_provider,$request->lang,$type='name');
                    }
                    $url = GameLobby::kaGamingLaunchUrl($request->game_code,$request->token,$request->exitUrl,$lang, $request->all());
                    if($url != false && $url != 'false'){
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $url,
                            "game_launch" => true
                        );
                    }else{
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $this->createFallbackLink($request->all()),
                            "game_launch" => false
                        );
                    }
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==128){
                    $url = GameLobby::CrashGaming($request->all());
                    if($url != false && $url != 'false'){
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $url,
                            "game_launch" => true
                        );
                    }else{
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $this->createFallbackLink($request->all()),
                            "game_launch" => false
                        );
                    }
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==37){ // request->token
                    // $lang = GameLobby::getLanguage($request->game_provider,$request->lang);
                    $url = GameLobby::iaLaunchUrl($request->game_code,$request->token,$request->exitUrl);
                    if($url){
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            // "url" => "https://play.betrnk.games/loadgame?url=".urlencode($url)."&token=".$request->token,
                            "url" => $url,
                            "game_launch" => true
                        );
                        Helper::saveLog('IA Launch Game URL', 15, json_encode("https://play.betrnk.games/loadgame?url=".urlencode($url)."&token=".$request->token), "TEST URL");
                    }else{
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $this->createFallbackLink($request->all()),
                            "game_launch" => false
                        );
                    }
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==41){ // TEST LOTTERY
                    // Helper::saveLog('DEMO CALL', 11, json_encode($request->all()), 'DEMO');
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::betrnkLaunchUrl($request->token, $request->game_code), //TEST
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==43){
                    $lang = GameLobby::getLanguage($request->game_provider,$request->lang);
                    $url = GameLobby::awsLaunchUrl($request->token,$request->game_provider,$request->game_code,$lang,$request->exitUrl);
                    if($url != false && $url != 'false'){
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $url,
                            "game_launch" => true
                        );
                    }else{
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $this->createFallbackLink($request->all()),
                            "game_launch" => false
                        );
                    }
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==40){
                    $lang = GameLobby::getLanguage("EVOPLAY 8Provider",$request->input("lang"));
                    $url = GameLobby::evoplayLunchUrl($request->token,$request->game_code,$request->game_provider, $request->exitUrl,$lang);
                    if($url != false && $url != 'false'){
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $url,
                            "game_launch" => true
                        );
                    }else{
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $this->createFallbackLink($request->all()),
                            "game_launch" => false
                        );
                    }
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                // elseif($request->input('game_provider')=="Solid Gaming"){
                elseif(in_array($provider_code, $solid_gamings)){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::solidLaunchUrl($request->game_code,$request->token,$request->exitUrl), 
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif(in_array($provider_code, [38,130])){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::mannaLaunchUrl($request->game_code,$request->token,$request->exitUrl, $request->lang), 
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($request->input('game_provider')=="Aoyama Slots"){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::aoyamaLaunchUrl($request->game_code,$request->token,$request->exitUrl), 
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif(in_array($provider_code, [39, 79, 83, 84, 85, 86, 87])){
                    $lang = 'en';
                    if($request->has('lang')){
                        $lang = $request->lang;
                        /*Temporarily disabled*/
                        /*$lang = GameLobby::getLanguage($request->game_provider,$request->lang);*/
                    }
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::oryxLaunchUrl($request->game_code,$request->token,$request->exitUrl,$lang), 
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                } 

                elseif($provider_code==49){

                    $url = GameLobby::pragmaticplaylauncher($request->game_code, $request->token, $request->all());
                    if($url){
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $url,
                            "game_launch" => true
                        );
                    }else{
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $this->createFallbackLink($request->all()),
                            "game_launch" => false
                        );
                    }
                    return $msg;

                    // $msg = array(
                    //     "game_code" => $request->input("game_code"),
                    //     "url" => GameLobby::pragmaticplaylauncher($request->game_code,$request->token,$request->exitUrl), 
                    //     "game_launch" => true
                    // );
                    // return response($msg,200)
                    // ->header('Content-Type', 'application/json');
                } 
                elseif($provider_code==46){
                    
                    // $msg = array(
                    //     "game_code" => $request->input("game_code"),
                    //     "url" => GameLobby::tidylaunchUrl($request->game_code,$request->token), //TEST
                    //     "game_launch" => true
                    // );
                   
                    // if($request->has("game_code")  && $request->game_code == "1")
                    // {
                        
                    //     $url = GameLobby::funtaTransferLuanch($request->all());
                    // } 
                    // else 
                    // {
                    //     // SEAMLESS WALLET
                    //     $url = GameLobby::tidylaunchUrl($request->game_code,$request->token);
                    // }
                    $url = GameLobby::tidylaunchUrl($request->game_code,$request->token, $request->game_provider, $request->exitUrl);

                    // return $url;
                    if($url)
                    {
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $url,
                            "game_launch" => true
                        );
                    }
                    else
                    {
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $this->createFallbackLink($request->all()),
                            "game_launch" => false
                        );
                    }
                    // return $msg;
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                    
                }
                elseif($provider_code == 53){ 
                    $url = GameLobby::tgglaunchUrl($request->game_code,$request->token,$request->exitUrl,$request->input('game_provider'));
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => $url,
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code == 55){ 
                    $url = GameLobby::pgsoftlaunchUrl($request->game_code,$request->token);
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => $url,
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code == 58){ 
                    $url = GameLobby::boomingGamingUrl($request->all(), $request->input('game_provider') );
                    
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => $url,
                        "game_launch" => true
                    );
                    // Helper::saveLogCode('Booming GameCode', 36, json_encode($array), $url->session_id);

                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==59){
                    if($request->has('lang')){
                        $lang = GameLobby::getLanguage($request->game_provider,$request->lang);
                    }else{
                        $lang = GameLobby::getLanguage($request->game_provider, 'en');
                    }
                    $exitUrl = $request->has('exitUrl') ? $request->exitUrl : '';
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::spadeLaunch($request->game_code,$request->token,$exitUrl,$lang, $request->game_provider),
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code == 68){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::majagamesLaunch($request->game_code,$request->token),
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==73){
                    // if($request->has('lang')){
                    //     $lang = GameLobby::getLanguage($request->game_provider,$request->lang);
                    // }else{
                    //     $lang = GameLobby::getLanguage($request->game_provider, 'en');
                    // }
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::spadeCuracaoLaunch($request->game_code,$request->token,$request->lang),
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==76){
                    $url = GameLobby::netEntDirect($request->all());
                    if($url){
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $url,
                            "game_launch" => true
                        );
                    }else{
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $this->createFallbackLink($request->all()),
                            "game_launch" => false
                        );
                    }
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                
                elseif($provider_code==47){ 
                    // Helper::saveLog('DEMO CALL', 11, json_encode($request->all()), 'DEMO');
                    // $lang = GameLobby::getLanguage($request->game_provider,$request->lang);
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::habanerolaunchUrl($request->game_code, $request->token, $request->exitUrl, $request->input('game_provider')), //TEST
                        "game_launch" => true
                    );

                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==67){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::simplePlayLaunchUrl($request->game_code,$request->token,$request->exitUrl), 
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==60){ 
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::yggdrasillaunchUrl($request->all(), $request->input('game_provider')), //TEST
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                } 
                elseif($provider_code==71){ 
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::goldenFLaunchUrl($request->all()), //TEST
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                } 
                elseif(in_array($provider_code, [69, 61, 62, 63, 64, 65, 66, 114])){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::vivoGamingLaunchUrl($request->game_code,$request->token,$request->exitUrl, $request->input('game_provider')), 
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==88){ 
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::ultraPlayLaunchUrl($request->game_code,$request->token,$request->exitUrl), //TEST
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==89){ //slotmill

                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" =>  GameLobby::slotmill($request->all()),
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==97){ //Slotmill TW
                    Helper::saveLog('slotmillTW Gameluanch', 51, json_encode($request->all()), "Player response");
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" =>  GameLobby::slotmillTW($request->all()),
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==90){ //pgvirtual
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" =>  GameLobby::pgvirtual($request->all()),
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==92){ //funta transferwallet
                    $url = GameLobby::funtaTransferLuanch($request->all());
                    if($url)
                    {
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $url,
                            "game_launch" => true
                        );
                    }
                    else
                    {
                        $msg = array(
                            "game_code" => $request->input("game_code"),
                            "url" => $this->createFallbackLink($request->all()),
                            "game_launch" => false
                        );
                    }
                    // return $msg;
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                        
                  
                }
                elseif($provider_code==99){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::JustPlayLaunchURl($request->all()),
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }    
                elseif($provider_code==102){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::PlayStarLaunchUrl($request->all()),
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==100){
                    $url = GameLobby::fivemenlaunchUrl($request->game_code,$request->token,$request->exitUrl,$request->input('game_provider'));
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => $url,
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==94) { // DragonGaming
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" =>  GameLobby::dragonGamingLaunchUrl($request->all()),
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }elseif($provider_code==101){
                    $url = GameLobby::onlyplayLaunchUrl($request->game_code,$request->token,$request->exitUrl,$request->input('game_provider'),$request->lang);
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => $url,
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }elseif($provider_code==103){
                    $url = GameLobby::TopTrendGamingLaunchUrl($request->all());
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => $url,
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==104){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::ozashikiLaunchUrl($request->game_code,$request->token,$request->exitUrl, $request->lang), 
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }

                elseif($provider_code==105){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::NoLimitLaunchUrl($request->all(), $device), 
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code==106){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::SmartsoftLaunchUrl($request->all()), 
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code == 93){
                    Helper::saveLog('Bgaming Gameluanch', 49, json_encode($request->all()), "Gamelaunch response");
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::BGamingLaunchUrl($request->all(),$device), 
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code == 109){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::MancalaLaunchUrl($request->all()), 
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                } 
                elseif($provider_code == 110){
                    $URL = GameLobby::IDNPoker($request->all());
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "game_launch" => true
                    );
                    if ($URL != "false") {
                        $msg["url"] = $URL;
                    } else {
                        $msg["url"] = $this->createFallbackLink($request->all()) ;
                    }
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code == 112){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::FunkyGamesLaunch($request->all()), 
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code == 113){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::PlayTechLaunch($request->all()), 
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code == 126){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::QuickSpinDGameLaunch($request->all(), $device), 
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code == 127){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::SpearHeadGameLaunch($request->all(), $device), 
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                elseif($provider_code == 115 || $provider_code == 116 || $provider_code == 117 || $provider_code == 118 || $provider_code == 119 || $provider_code == 120 || $provider_code == 121 || $provider_code == 122 || $provider_code == 123 || $provider_code == 124 || $provider_code == 125){
                    $msg = array(
                        "game_code" => $request->input("game_code"),
                        "url" => GameLobby::AmuseGamingGameLaunch($request->all(),$device), 
                        "game_launch" => true
                    );
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }

            }
        }
        else{
            $msg = array(
                "error_code" => "INVALID_INPUT",
                "message" => "Missing Required Input"
            );
            return response($msg,200)
            ->header('Content-Type', 'application/json');
        }
    }
    public function getPlayerBalance(Request $request){
        if($request->has("token")){
            $player = GameLobby::getClientDetails("token",$request->token);
            $balance = DB::table("player_balance")->where("token_id",$player->token_id)->first();
            $gametransaction = DB::table("game_transactions")->select(DB::raw('SUM(bet_amount) as bet'),DB::raw('SUM(pay_amount) as win'))->where("token_id",$player->token_id)->first();
            $newbalance = (float)$balance->balance + (float)$gametransaction->win - (float)$gametransaction->bet;
            return $newbalance;
        }
        else{
            $msg = array(
                "error_code" => "TOKEN_INVALID",
                "message" => "Missing Required Input"
            );
            return response($msg,200)
            ->header('Content-Type', 'application/json');
        }
    }
    public function gameLobbyLaunchUrl(Request $request){
        $url = "https://daddy.betrnk.games/authenticate";
        if($request->has('client_id')
        &&$request->has('client_player_id')
        &&$request->has('username')
        &&$request->has('email')
        &&$request->has('display_name')
        &&$request->has('exitUrl')
        &&$request->has('token')){
           if($token=Helper::checkPlayerExist($request->client_id,$request->client_player_id,$request->username,$request->email,$request->display_name,$request->token)){
                $data = array(
                    "url" => $url."?token=".$token."&client_id=".$request->client_id."&user_id=".$request->client_player_id."&email=".$request->email."&displayname=".$request->display_name."&username=".$request->username."&exiturl=".$request->exitUrl,
                    "launch" => true
                );
                return $data;
            }
        }
        return "Invalid Input";
    }
    // TEST SINGLE PROVIDER
    public function getProviderDetails(Request $request, $provider_name){
        $clean_url = urldecode($provider_name);
        $providers = GameProvider::where("provider_name", $clean_url)
                    ->get(["provider_id","provider_name", "icon"]);
 
            $data = array();
            foreach($providers as $provider){
                $providerdata = array(
                    "provider_id" => $provider->provider_id,
                    "provider_name" => $provider->provider_name,
                    "icon" => $this->image_url.$provider->icon,
                    "games_list" => array(),
                );
                foreach($provider->games as $game){
                    $game = array(
                        "game_id" => $game->game_id,
                        "game_name"=>$game->game_name,
                        "game_code"=>$game->game_code,
                        "game_type" => $game->game_type->game_type_name,
                        "game_provider"=> $game->provider->provider_name,
                        "game_icon" => $game->icon,
                    );
                    array_push($providerdata["games_list"],$game);
                }
                array_push($data,$providerdata);
            }
            return $data;
    }

   
    public function checkGameAccess($client_id, $game_code, $sub_provider_id){

            $excludedlist = ClientGameSubscribe::with("selectedProvider")->with("gameExclude")->with("subProviderExcluded")->where("client_id",$client_id)->get();
           
            if(count($excludedlist)>0){  # No Excluded Provider
                $gamesexcludeId=array();
                foreach($excludedlist[0]->gameExclude as $excluded){
                    array_push($gamesexcludeId,$excluded->game_id);
                }
                $subproviderexcludeId=array();
                foreach($excludedlist[0]->subProviderExcluded as $excluded){
                    array_push($subproviderexcludeId,$excluded->sub_provider_id);
                }
                $data = array();
                $sub_providers = GameSubProvider::with(["games.game_type","games"=>function($q)use($gamesexcludeId){
                    $q->whereNotIn("game_id",$gamesexcludeId)->where("on_maintenance",0);
                }])->whereNotIn("sub_provider_id",$subproviderexcludeId)->where("on_maintenance",0)->get(["sub_provider_id","sub_provider_name", "icon"]);

                $sub_provider_subscribed = array();
                $provider_gamecodes = array();
                foreach($sub_providers as $sub_provider){
                    foreach($sub_provider->games as $game){
                        if($sub_provider->sub_provider_id == $sub_provider_id){
                            array_push($provider_gamecodes,$game->game_code);
                        }
                    }
                    array_push($sub_provider_subscribed,$sub_provider->sub_provider_id);
                }

                 if($client_id == 92){
                    $msg = [
                        'sub_providers' => $sub_providers,
                        'sub_provider_subscribed' => $sub_provider_subscribed,
                        'provider_gamecodes' => $provider_gamecodes,
                    ];
                    return $msg;
                }
                if(in_array($sub_provider_id, $sub_provider_subscribed)){
                    if(in_array($game_code, $provider_gamecodes)){
                        return true;
                    }else{
                        return false;
                    }
                }else{
                    return false;
                }
            }else{ 
                return false; # NO SUBSCRIBE RETURN FALSE
            }
   }

    public static function getLanguage(Request $request){
        return GameLobby::getLanguage($request->provider_name,$request->language);
    }

}
