<?php

namespace App\Http\Controllers\FreeRound;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\GameLobby;
use App\Models\ClientGameSubscribe;
use App\Models\GameSubProvider;
use App\Helpers\Helper;
use App\Helpers\FreeSpinHelper;
use App\Helpers\ClientHelper;
use DB;
class FreeRoundController extends Controller
{
    
    public function __construct(){
        $this->middleware('oauth', ['except' => ['index']]);
    }
    public function freeRoundController(Request $request){
        if( !$request->has('client_id') || !$request->has('client_player_id') || !$request->has('game_provider') || !$request->has('game_code')|| !$request->has('details') ){
            $mw_response = ["error_code" => "404","error_description" => "Missing Paramater!"];
            return response($mw_response,200)
            ->header('Content-Type', 'application/json');
        }

        $provider_id = GameLobby::checkAndGetProviderId($request->game_provider);
        if($provider_id){
            $provider_code = $provider_id->sub_provider_id;
        }else{
            return response(["error_code"=>"405","error_description"=>"Provider Code Doesnt Exist or Not Found!"],200)
                ->header('Content-Type', 'application/json');
        }

        //  CLIENT SUBSCRIPTION FILTER
        //  $subscription_checker = $this->checkGameAccess($request->input("client_id"), $request->input("game_code"), $provider_code);
        //  if(!$subscription_checker){
        //      $mw_response = ["error_code"=>"406","error_description"=>"Game Not Found"];
        //     return response($mw_response,200)
        //     ->header('Content-Type', 'application/json');
        //  }

         $checkProviderSupportFreeRound = $this->checkProviderSupportFreeRound($provider_code);
        if(!$checkProviderSupportFreeRound){
             $mw_response = ["error_code"=>"407","error_description"=>"Contact the Service Provider"];
            return response($mw_response,200)
            ->header('Content-Type', 'application/json');
         }

         $Client_SecurityHash = $this->Client_SecurityHash($request->client_id);
        if($Client_SecurityHash !== true){
             $mw_response = ["error_code"=>"408","error_description"=>"Client Disabled"];
            return response($mw_response,200)
            ->header('Content-Type', 'application/json');
        }

        $checkPlayerExist = $this->checkPlayerExist($request->client_id, $request->client_player_id);
        if(!$checkPlayerExist){
            $mw_response = ["error_code"=>"409","error_description"=>"Player does not exist!"];
            return response($mw_response,200)
            ->header('Content-Type', 'application/json');
        }
        $mw_response = ["error_code"=>"407","error_description"=>"Contact the Service"];
        if($this->addFreeGameProviderController($checkPlayerExist, $request->all(), $provider_code ) == 200 ){
            $mw_response = [
                "data" => $request->all(),
                "status" => [
                    "code" => 200,
                    "message" => "Success!"
                ]
            ];
        }
        
        return $mw_response;

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

    public function checkProviderSupportFreeRound($sub_provider_id){
        $provider = DB::select("SELECT * FROM sub_providers WHERE sub_provider_id = ".$sub_provider_id." and is_freespin = 0");
        $count = count($provider);
        return $count > 0 ? $provider[0]:null;
    }

    public function checkPlayerExist($client_id, $client_player_id){
        $player = DB::select("SELECT p.*, c.default_currency  FROM players p inner join clients c using (client_id) WHERE client_id = ".$client_id." and client_player_id = '".$client_player_id."' ");
        $count = count($player);
        return $count > 0 ? $player[0]:false;
	}

    public function Client_SecurityHash($client_id){
        $client_id = DB::table('clients')->where('client_id', $client_id)->first();
        if(!$client_id){ return 301;}
        if($client_id->status_id != 1 ){ return 202; }

        $operator = DB::table('operator')->where('operator_id', $client_id->operator_id)->first();
        if($operator == '' || $operator == null){ return 203; } 
        if(isset($operator->status_id) && $operator->status_id != 1 ){ return 204; }
        return true;
    }


    public function addFreeGameProviderController($player_details, $data, $sub_provder_id){
        if ($sub_provder_id == 89) {
            return FreeSpinHelper::createFreeRoundSlotmill($player_details, $data, $sub_provder_id);
        } elseif ($sub_provder_id == 56) {
            return FreeSpinHelper::createFreeRoundPNG($player_details, $data, $sub_provder_id);
        } elseif ($sub_provder_id == 38) {
            return FreeSpinHelper::createFreeRoundMannaplay($player_details, $data, $sub_provder_id);
        }
    }
}
