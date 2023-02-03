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

    public function __construct(){
    	$this->provider_db_id = config('providerlinks.relaxgaming.provider_id');
        $this->key = config('providerlinks.relaxgaming.password');
        $this->api_url = config('providerlinks.relaxgaming.api');
    }

   public function verifyToken(Request $request){
    $data = $request->all();
    ProviderHelper::saveLog("Relax Auth Request",$this->provider_db_id,json_encode($request->all()),"HIT!");
    $token = $data['token'];
    $client_details = ProviderHelper::getClientDetails('token', $token);
    if($client_details){
        return response()->json([
            'customerid' => $client_details->player_id,
            'countrycode' => $client_details->country_code,
            'cashiertoken' =>$client_details->player_token,
            'customercurrency' => $client_details->default_currency,
            'balance' => $client_details->balance,
            'jurisdiction' => 'CW',
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
      ProviderHelper::saveLog("Relax Balance Request",$this->provider_db_id,json_encode($request->all()),"HIT!");
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
        ProviderHelper::saveLog("Relax Bet Request",$this->provider_db_id,json_encode($request->all()),"HIT!");
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
            $bet_transaction = GameTransactionMDB::getGameTransactionByRoundId($round_id,$client_details);
            if($bet_transaction != null){
                 //Side Bet
                // $client_details->connection_name = $bet_transaction->connection_name;
                $amount = $bet_transaction->bet_amount + $bet_amount;
                $game_transaction_id = $bet_transaction->game_trans_id;
                $updateGameTransaction = [
                    'win' => 5,
                    'bet_amount' => $bet_amount,
                    'entry_id' => 1,
                    'trans_status' => 1
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
            }
            
            $gameTransactionDatas = [
                "provider_trans_id" => $provider_trans_id,
                "token_id" => $client_details->token_id,
                "game_id" => $gamedetails->game_id,
                "round_id" => $round_id,
                "bet_amount" => $bet_amount,
                "pay_amount" => 0,
                "win" => 5,
                "income" => 0,
                "entry_id" => 1
            ];
            $game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionDatas,$client_details);
            $gameExtensionData = [
                "game_trans_id" => $game_trans_id,
                "provider_trans_id" => $provider_trans_id,
                "round_id" => $round_id,
                "amount" => $bet_amount,
                "game_transaction_type" => 1,
                "provider_request" => json_encode($data),
            ];
            $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameExtensionData,$client_details);
            $fund_extra_data = [
                'provider_name' => $gamedetails->provider_name
            ];
            $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount,$gamedetails->game_code,$gamedetails->game_name,$game_trans_ext_id,$game_trans_id,'debit',false,$fund_extra_data);
            if(isset($client_response->fundtransferresponse->status->code)
            && $client_response->fundtransferresponse->status->code == "200"){
                $balance = round($client_response->fundtransferresponse->balance, 2);
                $bal = str_replace(".","", $balance);
                $format_balance = (int)$bal;
                ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                //SUCCESS FUNDTRANSFER
                $response = [
                    "balance"=> $format_balance,
                    "txid"=>$provider_transaction,
                    "remotetxid"=>$game_trans_id
                ];
                $extensionData = [
                    "mw_request" => json_encode($client_response->requestoclient),
                    "mw_response" =>json_encode($response),
                    "client_response" => json_encode($client_response),
                    "transaction_detail" => "Success",
                    "general_details" => "Success",
                ];
                GameTransactionMDB::updateGametransactionEXT($extensionData,$game_trans_ext_id,$client_details);
                return response()->json([
                    "balance"=> $format_balance,
                    "txid"=>$provider_transaction,
                    "remotetxid"=>$game_trans_id
                ]);
                // sleep(30);
                // return response($response,200)->header('Content-Type', 'application/json');
            }elseif(isset($client_response->fundtransferresponse->status->code)
            && $client_response->fundtransferresponse->status->code == "402"){
                $balance = round($client_response->fundtransferresponse->balance, 2);
                $format_balance = str_replace(".","", $client_details->balance);
                try{    
                    $updateTrans = [
                        "win" => 2,
                        "trans_status" => 5
                    ];
                    GameTransactionMDB::updateGametransaction($updateTrans,$game_trans_id,$client_details);
                    $response = [
                        "errorcode"=>"INSUFFICIENT_FUNDS",
                        "errormessage"=> "There are insufficient funds to go through with the withdrawal."
                    ];
                    $updateExt = [
                        "mw_request" => json_encode('FAILED'),
                        "mw_response" =>json_encode($response),
                        "client_response" => json_encode($client_response),
                        "transaction_detail" => "FAILED",
                        "general_details" => "FAILED",
                    ];
                    GameTransactionMDB::updateGametransactionEXT($updateExt,$game_trans_ext_id,$client_details);
                    return response()->json([
                        "errorcode"=>"INSUFFICIENT_FUNDS",
                        "errormessage"=> "There are insufficient funds to go through with the withdrawal."
                    ]);
                }catch(\Exception $e){
                    Helper::saveLog("FAILED BET", $this->provider_db_id,json_encode($client_response),"FAILED HIT!");
                }
            }
        }else{

          return response()->json([
              'errorcode' => "INVALID_TOKEN",
              'errormessage' =>"The token could not be verified."
             
          ]);
        }
      }
}
