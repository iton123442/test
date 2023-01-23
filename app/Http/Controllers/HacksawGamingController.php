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
        if($action_method == 'Authenticate'){
            if($secret_key != $this->secret_key){
                return response()->json([
                    '4' => "Invalid partner code"
                ]);
            }

        $client_details = ProviderHelper::getClientDetails('token', $data['token']);
        if($client_details == null){
            return response()->json([
                '2' => "Invalid user / token expired"
            ]);
        }
        dd($client_details);
        return response()->json([
            'externalPlayerId' => '',
            'name' => 'Christopher',
            'accountCurrency' => 'EUR',
            'accountBalance' => 9330,
            'externalSessionId' => '',
            'languageId' => 'en',
            'countryId' => 'RS',
            'birthDate' => '1980-01-01',
            'registrationDate' => '2018-01-11',
            'brandId' => 'brand_123',
            'gender' => 'm',
            'statusCode' => 0,
            'statusMessage' => ''
        ]);
        }

    }
    public function getBalance(Request $request){

      Helper::saveLog("TTG get Bal", $this->provider_db_id, json_encode($request->all()), "ENDPOINT HIT"); 
      $fileContents = file_get_contents("php://input");
      $json = json_encode(simplexml_load_string($fileContents));
      $array = json_decode($json,true);
      $user_id = explode('TGR_',$array['@attributes']['acctid']);
      $get_client_details = ProviderHelper::getClientDetails("player_id",$user_id[1]);
      if($get_client_details == null || $get_client_details->player_id != $user_id[1]){

          $response = '<cw type="getBalanceResp" err="1000" />';

         return response($response,200) 
        ->header('Content-Type', 'application/xml');

      }
      if($get_client_details->default_currency != $array['@attributes']['cur']){

        $response = '<cw type="getBalanceResp" err="1001" />';

         return response($response,200) 
        ->header('Content-Type', 'application/xml');
      }
      
      $formatBalance = number_format($get_client_details->balance,2,'.','');
      header("Content-type: application/xml; charset=UTF-8");
     
      $response = '<cw type="getBalanceResp" cur="'.$get_client_details->default_currency.'" amt="'.$formatBalance.'" err="0" />';
    
       return response($response,200) 
       ->header('Content-Type', 'application/xml');
      
    }

    
}

