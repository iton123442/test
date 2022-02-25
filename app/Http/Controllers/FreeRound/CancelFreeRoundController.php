<?php

namespace App\Http\Controllers\FreeRound;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\ProviderHelper;
use App\Helpers\Helper;
use App\Helpers\WazdanHelper;
use App\Helpers\FreeSpinHelper;
use DB;
class CancelFreeRoundController extends Controller
{
    
    // public function __construct(){
    //     $this->middleware('oauth', ['except' => ['index']]);
    // }
    public function cancelfreeRoundController(Request $request){
        if( !$request->has('client_id') || !$request->has('freeround_id') ){
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
        $freeround_id = $request->client_id."_".$request->freeround_id;
        $mw_response = ["error_code"=>"407","error_description"=>"Contact the Service"];
        if($this->cancelFreeGameProviderController($freeround_id ) == 200 ){
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


    public function Client_SecurityHash($client_id){
        $client_id = DB::table('clients')->where('client_id', $client_id)->first();
        if(!$client_id){ return 301;}
        if($client_id->status_id != 1 ){ return 202; }

        $operator = DB::table('operator')->where('operator_id', $client_id->operator_id)->first();
        if($operator == '' || $operator == null){ return 203; } 
        if(isset($operator->status_id) && $operator->status_id != 1 ){ return 204; }
        return true;
    }

    public function cancelFreeGameProviderController($freeround_id){
        $getFreespin = FreeSpinHelper::getFreeSpinDetails($freeround_id, "provider_trans_id" );
        if(isset($getFreespin->status)){
            if ($getFreespin->status == 0) {
                $game_details = ProviderHelper::findGameID($getFreespin->game_id);
                if($game_details){
                    if ($game_details->sub_provider_id == 56) {
                        // return FreeSpinHelper::createFreeRoundSlotmill($player_details, $data, $sub_provder_id,$freeround_id);// 200
                        return 200;
                    } elseif($game_details->sub_provider_id == 57) {
                        return FreeSpinHelper::cancelFreeRoundWazdan($freeround_id);
                    } elseif($game_details->sub_provider_id == 44) {
                        return FreeSpinHelper::cancelFreeRoundBNG($freeround_id);
                    }
                }
            }
        }
        return 400;
       
    }

}
