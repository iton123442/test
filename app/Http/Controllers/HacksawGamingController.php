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
        $action_method = $data['action'];
        $secret_key = $data['secret'];
        if(isset($data['token'])){
            $token = $data['token'];
            $client_details = ProviderHelper::getClientDetails('token', $token);
        }else{
            $player_id = $data['externalPlayerId'];
            $client_details = ProviderHelper::getClientDetails('player_id', $player_id);
        }
        if($client_details == null){
            return response()->json([
                '2' => "Invalid user / token expired"
            ]);
        }
        if($action_method == 'Authenticate'){
            if($secret_key != $this->secret_key){
                return response()->json([
                    '4' => "Invalid partner code"
                ]);
            }
        $balance = (int)$client_details->balance;
        return response()->json([
            'externalPlayerId' => $client_details->player_id,
            'accountCurrency' => $client_details->default_currency,
            'externalSessionId' =>$client_details->player_token,
            'accountBalance' => $balance,
            'statusCode' => 0,
            'statusMessage' => 'Success'
        ]);
        }
        if($action_method == 'Balance'){
            $response = $this->getBalance($request->all());
            return response($response,200)
                ->header('Content-Type', 'application/json');   
        }

    }
    public function getBalance($request){
        $data = $request;
        $token = $data['externalSessionId'];
        $client_details = ProviderHelper::getClientDetails('token', $token); 
        $balance = (int)$client_details->balance;
        return response()->json([
            'accountBalance' => $balance,
            'accountCurrency' => $client_details->default_currency,
            'statusCode' => 0,
            'statusMessage' => 'Success'
        ]);  
    }

    
}

