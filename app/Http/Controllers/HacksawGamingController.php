<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransaction;
use App\Models\GameTransactionMDB;
use App\Helpers\Game;
use Carbon\Carbon;
use DB;
// use DOMDocument;
// use SimpleXMLElement;

class HacksawGamingController extends Controller
{ 
  public function __construct(){
        $this->provider_db_id = config('providerlinks.hacksawgaming.provider_db_id');
        $this->secret_key = config('providerlinks.hacksawgaming.secret');
    }
    public function hacksawIndex(Request $request){
        $data = $request->all();
        ProviderHelper::saveLog("Hacksaw Request",75,json_encode($data),"HIT!");
        $action_method = $data['action'];
        $secret_key = $data['secret'];
        if(isset($data['token'])){
            $token = $data['token'];
            $client_details = ProviderHelper::getClientDetails('token', $token);
        }else{
            try{
                $player_id = $data['externalPlayerId'];
                $client_details = ProviderHelper::getClientDetails('player_id', $player_id);
            }catch(\Exception $e){
                $player_token = $data['externalSessionId'];
                $client_details = ProviderHelper::getClientDetails('token', $player_token);  
            }          
        }
        if($client_details == null){
            return response()->json([
                'accountBalance' => $client_details->balance,
                'accountCurrency' => $client_details->default_currency,
                'statusCode' => 2,
                'statusMessage' => 'Invalid user / token expired'
            ]);
        }
        if($secret_key != $this->secret_key){
            return response()->json([
                'accountBalance' => $client_details->balance,
                'accountCurrency' => $client_details->default_currency,
                'statusCode' => 4,
                'statusMessage' => 'Invalid partner code'
            ]);
        }
        if($action_method == 'Authenticate'){
        $balance = str_replace(".","", $client_details->balance);
        $format_balance = (int)$balance;
        return response()->json([
            'externalPlayerId' => $client_details->player_id,
            'accountCurrency' => $client_details->default_currency,
            'externalSessionId' =>$client_details->player_token,
            'accountBalance' => $format_balance,
            'statusCode' => 0,
            'statusMessage' => 'Success'
        ]);
        }
        if($action_method == 'Balance'){
            $balance = str_replace(".","", $client_details->balance);
            $format_balance = (int)$balance;
            return response()->json([
                'accountBalance' => $format_balance,
                'accountCurrency' => $client_details->default_currency,
                'statusCode' => 0,
                'statusMessage' => 'Success'
            ]);     
        }
        if($action_method == 'EndSession'){
            return response()->json([
                'statusCode' => 0,
                'statusMessage' => 'Success'
            ]); 
        }
        if($action_method == 'Bet'){
            $response = $this->GameBet($request->all(), $client_details);
            return response($response,200)
                ->header('Content-Type', 'application/json');
        }
    }
    public function GameBet($request){ 
        $data = $request;
       
    }  
}

