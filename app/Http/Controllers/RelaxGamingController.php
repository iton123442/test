<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransaction;
use App\Models\GameTransactionMDB;
use App\Helpers\FreeSpinHelper;
use App\Helpers\Game;
use Carbon\Carbon;
use DB;

class RelaxGamingController extends Controller
{
   public function verifyToken(Request $request){
    $data = $request->all();
    $token = $data['token'];
    $client_details = ProviderHelper::getClientDetails('token', $token);
    if($client_details){
        return response()->json([
            'customerid' => $client_details->player_id,
            'countrycode' => $client_details->country_code,
            'cashiertoken' =>$client_details->player_token,
            'customercurrency' => $client_details->default_currency,
            'balance' => $client_details->balance,
            'jurisdiction' => 'Success',
        ]);
    }else{
        return response()->json([
            'errorcode' => "INVALID_TOKEN",
            'errormessage' =>"The token could not be verified."     
        ]);
    }   
    }   
    public function getBalance(Request $request){
      $data =$request->all();
      $player_id = $data['customerid'];
      $client_details = ProviderHelper::getClientDetails('player_id', $player_id);
      if($client_details){
        return response()->json([
            'balance' => $client_details->balance,
            'customercurrency' => $client_details->country_code
        ]);
      }else{

        return response()->json([
            'errorcode' => "UNHANDLED",
            'errormessage' =>"Final fallback error code."
           
        ]);
      }
    }

    public function Bet(Request $request){
        $data =$request->all();
        $player_id = $data['customerid'];
        $game_code = $data['gameid'];
        $round_id = $data['gamesessionid'];
        $bet_amount = $data['amount'];
        $provider_transaction = $data['txid'];
        $client_details = ProviderHelper::getClientDetails('player_id', $player_id);
        if($client_details){
            $gamedetails = ProviderHelper::findGameDetails('game_code',77, $game_code);
            try{
                // ProviderHelper::saveLog("Hacksaw Idempotent Bet",$this->provider_db_id,json_encode($data),"Bet HIT!");
                ProviderHelper::idenpotencyTable("BET_".$provider_transaction);
            }catch(\Exception $e){
                $bet_transaction = GameTransactionMDB::findGameExt($provider_transaction, 1,'transaction_id', $client_details);
                if ($bet_transaction != 'false') {
                    //this will be trigger if error occur 10s
                    // Helper::saveLog('Hacksaw BET duplicate_transaction success', $this->provider_db_id, json_encode($data),  $bet_transaction->mw_response);
                    return response()->json([
                        "balance"=>$balance,
                        "txid"=> $bet_transaction->provider_transaction,
                        "remotetxid"=>0
                    ]);
                } 
                // sleep(4);
                return response()->json([
                    "errorcode"=>"UNHANDLED",
                    "errormessage"=> "Final fallback error code."
                ]);
            }
        }else{

          return response()->json([
              'errorcode' => "INVALID_TOKEN",
              'errormessage' =>"The token could not be verified."
             
          ]);
        }
      }
}
