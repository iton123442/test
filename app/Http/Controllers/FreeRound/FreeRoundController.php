<?php

namespace App\Http\Controllers\FreeRound;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\ProviderHelper;
use App\Helpers\GameLobby;
use App\Models\ClientGameSubscribe;
use App\Models\GameSubProvider;
use App\Http\Controllers\TransferWalletAggregator\TWHelpers;
use App\Helpers\Helper;
use App\Helpers\FreeSpinHelper;
use App\Helpers\ClientHelper;
use DB;
class FreeRoundController extends Controller
{
    
    public function __construct(){
        $this->middleware('oauth', ['except' => []]);
    }
    public function freeRoundController(Request $request){
        if( !$request->has('client_id') || !$request->has('client_player_id') || !$request->has('game_provider') || !$request->has('game_code') || !$request->has('details') || !$request->has('freeround_id') ){
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
        $freeround_id = $request->client_id.'_'.$request->freeround_id;
        try{
            ProviderHelper::idenpotencyTable($freeround_id);
        }catch(\Exception $e){
            return response(["error_code"=>"403","error_description"=> "Transaction Already Exist!"],200)
                ->header('Content-Type', 'application/json');
        }
        //  CLIENT SUBSCRIPTION FILTER
         $subscription_checker = $this->checkGameAccess($request->input("client_id"), $request->input("game_code"), $provider_code);
         if(!$subscription_checker){
             $mw_response = ["error_code"=>"406","error_description"=>"Game Not Found"];
            return response($mw_response,200)
            ->header('Content-Type', 'application/json');
         }

        $checkProviderSupportFreeRound = $this->checkProviderSupportFreeRound($provider_code);
        if(!$checkProviderSupportFreeRound){
             $mw_response = ["error_code"=>"407","error_description"=>"Contact the Service Provider"];
            return response($mw_response,200)
            ->header('Content-Type', 'application/json');
         }
        
        $checkGameSupportFreeRound = $this->checkGameSupportFreeRound($provider_code, $request->input("game_code"));
        if(!$checkGameSupportFreeRound){
             $mw_response = ["error_code"=>"407","error_description"=>"Game Not Supported!"];
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
            ->header('Content-Type', 'application/json');;
        }
        $mw_response = ["error_code"=>"407","error_description"=>"Contact the Service"];
        if($this->addFreeGameProviderController($checkPlayerExist, $request->all(), $provider_code, $freeround_id ) == 200 ){
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
        // 0 => SUPPORTED
        // 1 => NOT SUPPORTED
        $provider = DB::select("SELECT * FROM sub_providers WHERE sub_provider_id = ".$sub_provider_id." and is_freespin = 0");
        $count = count($provider);
        return $count > 0 ? $provider[0]:null;
    }
    public function checkGameSupportFreeRound($sub_provider_id, $game_code){
         // 0 => SUPPORTED
        // 1 => NOT SUPPORTED
        $provider = DB::select("SELECT game_id FROM games WHERE sub_provider_id = ".$sub_provider_id." AND game_code = '".$game_code."' and is_freespin = 0");
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


    public function addFreeGameProviderController($player_details, $data, $sub_provder_id, $freeround_id){
        Helper::saveLog('Freespin '.  $sub_provder_id, $sub_provder_id,json_encode($data), 'CONTROLLER PORTAL');
        if ($sub_provder_id == 89) {
            return FreeSpinHelper::createFreeRoundSlotmill($player_details, $data, $sub_provder_id,$freeround_id);
        } elseif ($sub_provder_id == 56) {
            return FreeSpinHelper::createFreeRoundPNG($player_details, $data, $sub_provder_id,$freeround_id);
        } elseif ($sub_provder_id == 38) {
            return FreeSpinHelper::createFreeRoundMannaplay($player_details, $data, $sub_provder_id,$freeround_id);
        } elseif ($sub_provder_id == 105) {
            return FreeSpinHelper::createFreeRounNolimitCity($player_details, $data, $sub_provder_id,$freeround_id);
        } elseif ($sub_provder_id == 126) {
            return FreeSpinHelper::createFreeRoundQuickSpinD($player_details, $data, $sub_provder_id,$freeround_id);
        } elseif ($sub_provder_id == 127) {
            return FreeSpinHelper::createFreeRoundSpearHeadEm($player_details, $data, $sub_provder_id,$freeround_id);
        } elseif ($sub_provder_id == 44) {
            return FreeSpinHelper::BNGcreateFreeBet($player_details, $data, $sub_provder_id,$freeround_id);
        }  elseif ($sub_provder_id == 57) {
            return FreeSpinHelper::createFreeRoundWazdan($player_details, $data, $sub_provder_id,$freeround_id);
        }
        else {
            return 400;
        }
    }


    // @return
    // client_id
    // player id
    // round id
    // bet amount 
    // pay amount
    // game name
    // game_id
    // game_code
    // sub provider
    // status (win lose refund failed progressing)
    // create_at
    public function getQuery(Request $request)
    {
        // if($request->has('client_id')&&$request->has('player_id')&&$request->has('dateStart')&&$request->has('dateEnd')){
        Helper::saveLog('getQuery', $request->has('client_id') , json_encode($request->all()), "getQuery");
        if( !$request->has('client_id') || !$request->has('date') || !$request->has('page') || !$request->has('limit') ){
            $mw_response = ["error_code" => "404","error_description" => "Missing Paramater!"];
            return response($mw_response,200)
            ->header('Content-Type', 'application/json');
        }

        $Client_SecurityHash = $this->Client_SecurityHash($request->client_id);
        if($Client_SecurityHash !== true){
             $mw_response = ["error_code"=>"408","error_description"=>"Client Disabled"];
            return response($mw_response,200)
            ->header('Content-Type', 'application/json');
        }

        $from = date("Y-m-d H:i:s", strtotime($request->date));
        $to = date("Y-m-d H:i:s", strtotime($request->date." 23:59:59"));
        if($request->has('start_time')){ 
            $from = date("Y-m-d H:i:s", strtotime($request->date." ".$request->start_time));
        }
        if($request->has('end_time')){ 
            $to = date("Y-m-d H:i:s", strtotime($request->date." ".$request->end_time));
        }
       
        // $partition = TWHelpers::multiplePartition($from,$to);
        $page = $request->page * $request->limit;
        $and_player = "and player_id  = (SELECT player_id FROM players WHERE client_id = ".$request->client_id." AND client_player_id = '".$request->client_player_id."' LIMIT 1)  ";
        if ($request->client_player_id == "all") {
            $and_player = '';
        }
        try {
            $total_data = DB::select("
                select 
                count(freespin_id) total
                from freespin fs
                inner join players using (player_id)
                inner join clients using (client_id)
                where convert_tz(fs.created_at,'+00:00', '+08:00') BETWEEN '".$from."' AND '".$to."' AND client_id = ".$request->client_id."  ".$and_player.";
                ")[0];
            
            $query = "
                    select 
                    p.client_player_id, g.game_code,
                    (select sub_provider_name from sub_providers where sub_provider_id = g.sub_provider_id) as game_provider,
                    total_spin as rounds,
                    spin_remaining,
                    fs.denominations,
                    fs.lines,
                    fs.coins,
                    case when fs.status = 0 then 'pending' when fs.status = 1 then 'running' when fs.status = 2 then 'completed'   else 'failed' end as status,
                    date_expire,
                    fs.provider_trans_id,
                    fs.created_at
                    from freespin fs
                    inner join players p using (player_id)
                    inner join clients c using (client_id)
                    inner join games g using(game_id)
                where convert_tz(fs.created_at,'+00:00', '+08:00') BETWEEN '".$from."' AND '".$to."' AND c.client_id = ".$request->client_id." ".$and_player."
                order by freespin_id desc
                limit ".$page.", ".TWHelpers::getLimitAvailable($request->limit).";
            ";
            $details = DB::select($query);
            if (count($details) == 0) {
                $details = ["data" => null,"status" => ["code" => "200","message" => TWHelpers::getPTW_Message(200)]];
                Helper::saveLog('TW Logs', 5 , json_encode($request->all()), $query);
                return response()->json($details); 
            }

           
            $data = array();//this is to add data and reformat the $table object to datatables standard array
            foreach($details as $datas){
                $freeround = explode("_", $datas->provider_trans_id);
                $datatopass['freeround_id']=$freeround[1];
                $datatopass['client_player_id']=$datas->client_player_id;
                $datatopass['game_code']=$datas->game_code;
                $datatopass['rounds']=$datas->rounds;
                $datatopass['spin_remaining']=$datas->spin_remaining;
                $datatopass['denominations']=$datas->denominations;
                $datatopass['lines']=$datas->lines;
                $datatopass['coins']=$datas->coins;
                $datatopass['status']=$datas->status;
                $datatopass['date_expire']=$datas->date_expire;
                $datatopass['created_at']=$datas->created_at;
                $data[]=$datatopass;
            }
            $mw_response = [
                "data" => $data,
                "Total" => $total_data->total,
                "Page" => $request->page,
                "Limit" =>  TWHelpers::getLimitAvailable($request->limit),
                "status" => [
                    "code" => 200,
                    "message" => TWHelpers::getPTW_Message(200)
                ]
            ];
            Helper::saveLog('TW Logs', 5 , json_encode($request->all()), $query);
            return response()->json($mw_response); 
        }catch (\Exception $e){
            $mw_response = ["error_code"=>"407","error_description"=>"Contact the Service"];
            return response($mw_response,200)
            ->header('Content-Type', 'application/json');
        }
     
    }
}
