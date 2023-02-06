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
    $balance = str_replace(".","", $client_details->balance);
    $format_balance = (int)$balance;
    if($client_details){
        return response()->json([
            'customerid' => $client_details->player_id,
            'countrycode' => $client_details->country_code,
            'cashiertoken' =>$token,
            'customercurrency' => $client_details->default_currency,
            'balance' => $format_balance,
            'jurisdiction' => '',
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
      $balance = str_replace(".","", $client_details->balance);
      $format_balance = (int)$balance;
      if($client_details){
        return response()->json([
            'balance' => $client_details->balance,
            'customercurrency' => $format_balance
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
        $game_code = $data['gameref'];
        $round_id = $data['gamesessionid'];
        $bet_amount = $data['amount']/100;
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
                "provider_trans_id" => $provider_transaction,
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
                "provider_trans_id" => $provider_transaction,
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
                ProviderHelper::saveLog("Relax Bet Fundtransfer Success",$this->provider_db_id,json_encode($request->all()),json_encode($extensionData));
                GameTransactionMDB::updateGametransactionEXT($extensionData,$game_trans_ext_id,$client_details);
                return response()->json([
                    "balance"=> $format_balance,
                    "txid"=>$provider_transaction,
                    "remotetxid"=>"tgbet_".$game_trans_id
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

    public function Win(Request $request){
    $data = $request->all();
    ProviderHelper::saveLog("Relax Win Request",$this->provider_db_id,json_encode($request->all()),"HIT!");
    $game_code = $data['gameref'];
    $win_amount = $data['amount'];
    $round_id = $data['gamesessionid'];
    $provider_transaction = $data['txid'];
    $player_id = $data['customerid'];
    $client_details = ProviderHelper::getClientDetails('player_id', $player_id);
    if($client_details){
        try{
            ProviderHelper::IdenpotencyTable($provider_transaction);
        }catch(\Exception $e){
            return response()->json([
                "errorcode"=>"CUSTOM_ERROR",
                "errormessage"=> "Duplicate Win"
            ]);
        }
        $gamedetails = ProviderHelper::findGameDetails('game_code',77, $game_code);
        if($gamedetails){
            if($win_amount == 0){
                $amount = 0;
            }else{
                $amount = $win_amount / 100;
            }
            $txtype = $data['txtype'];
            if (isset($txtype) && $txtype == 'freespinspayout') {
                $game = GametransactionMDB::GeneralGameTransactionByTransId($round_id, $client_details);
            } else {
                $game = GametransactionMDB::getGameTransactionByRoundId($round_id, $client_details);
            }
            //For Free Rounds
            //////
        $win = $amount + $game->pay_amount == 0 ? 0 : 1;
        $updateTransData = [
            "win" => $win,
            "pay_amount" => round($amount + $game->pay_amount,2),
            "income" => round($game->bet_amount-$game->pay_amount - $amount,2),
            "entry_id" => $amount == 0 ? 1 : 2,
        ];
        GameTransactionMDB::updateGametransaction($updateTransData,$game->game_trans_id,$client_details);
        $response =[
            "balance"=>$client_details->balance,
            "txid"=> $provider_transaction,
            "remotetxid"=>"tgwin_".$game->game_trans_id
        ];
        $gameExtensionData = [
            "game_trans_id" => $game->game_trans_id,
            "provider_trans_id" => $provider_transaction,
            "round_id" => $round_id,
            "amount" => $amount,
            "game_transaction_type" => 2,
            "provider_request" => json_encode($data),
        ];
        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameExtensionData,$client_details);
        $action_payload = [
            "type" => "custom", #genreral,custom :D # REQUIRED!
            "custom" => [
                "provider" => 'Hacksaw Gaming',
                "game_transaction_ext_id" => $game_trans_ext_id,
                "client_connection_name" => $client_details->connection_name,
                "win_or_lost" => $win,
            ],
            "provider" => [
                "provider_request" => $data,
                "provider_trans_id"=>$provider_transaction,
                "provider_round_id"=>$round_id,
                'provider_name' => $gamedetails->provider_name
            ],
            "mwapi" => [
                "roundId"=> $game->game_trans_id,
                "type" => 2,
                "game_id" => $gamedetails->game_id,
                "player_id" => $client_details->player_id,
                "mw_response" => $response,
            ]
        ];
        $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$amount,$gamedetails->game_code,$gamedetails->game_name,$game->game_trans_id,'credit',false,$action_payload);
        if(isset($client_response->fundtransferresponse->status->code) &&
        $client_response->fundtransferresponse->status->code == "200"){
            ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
            //SUCCESS FUNDTRANSFER
            $balance = round($client_response->fundtransferresponse->balance, 2);
            $bal = str_replace(".","", $balance);
            $format_balance = (int)$bal;
            $response =[
                "balance"=>$format_balance,
                "txid"=> $provider_transaction,
                "remotetxid"=>"tgwin_".$game->game_trans_id
            ];
            $msg = [
                "mw_response" => json_encode($response)
            ];
            GameTransactionMDB::updateGametransactionEXT($msg,$game_trans_ext_id,$client_details);
            // Helper::saveLog('Hacksaw Win', $this->provider_db_id, json_encode($data), 'Success HIT!');
            ProviderHelper::saveLog("Relax Win Fundtransfer Success",$this->provider_db_id,json_encode($request->all()),json_encode($response));
            return json_encode($response);
        }
        }else{
            return response()->json([
                'errorcode' => "CUSTOM_ERROR",
                'errormessage' =>"No Game Found"
               
            ]);

        }  
    }else{
        return response()->json([
            'errorcode' => "INVALID_TOKEN",
            'errormessage' =>"The token could not be verified."
           
        ]);
    }
    }

    public function rollback(Request $request){
        Helper::saveLog('RelaxGaming rollback', 144, json_encode($request), json_encode($request->all()));
        $data = $request->all();
        $player_id = $data['customerid'];
        $provider_txid = $data['txid'];
        $rollback_trans_id = $data['originaltxid'];
        $round_id = $data['gamesessionid'];
        $client_details = ProviderHelper::getClientDetails('player_id', $player_id);
        if($client_details){
            try{
                Helper::saveLog('RelaxGaming Rollback Idempotent', $this->provider_db_id, json_encode($data), 'Success HIT!');
                ProviderHelper::IdenpotencyTable($provider_txid);
            }catch(\Exception $e){
                return response()->json([
                    'errorcode' => "TRANSACTION_DECLINED",
                    'errormessage' =>"Duplicate Relax Transaction ID"      
                ]);
            }
            $bet_transaction = GameTransactionMDB::findGameTransactionDetails($rollback_trans_id, 'transaction_id',1, $client_details);
            if($bet_transaction == null){
                return response()->json([
                    'errorcode' => "TRANSACTION_DECLINED",
                    'errormessage' =>"Transaction Not Found"      
                ]);
            }
            $game_id = $bet_transaction->game_id;
            $game_details = ProviderHelper::findGameID($game_id);
            $balance = str_replace(".","", $client_details->balance);
            $format_balance = (int)$balance;
            $game_trans_id = $bet_transaction->game_trans_id;
            $pay_amount = $bet_transaction->bet_amount;
            $winBalance = $client_details->balance + $pay_amount;
            $win_or_lost = $pay_amount > 0 ?  1 : 0;
            $entry_id = $pay_amount > 0 ?  2 : 1;
            $income = $bet_transaction->bet_amount - $pay_amount;
    
            $updateGameTransaction = [
              'win' => 4,
              'pay_amount' => $pay_amount,
              'income' => $income,
              'entry_id' => 3,
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
            $gameExtensionData = [
                "game_trans_id" => $game_trans_id,
                "provider_trans_id" => $provider_txid,
                "round_id" => $round_id,
                "amount" => $pay_amount,
                "game_transaction_type" => 3,
                "provider_request" => json_encode($request),
            ];
            $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameExtensionData,$client_details);
    
            $fund_extra_data = [
                'provider_name' => "Relax Gaming"
            ];
            $client_response = ClientRequestHelper::fundTransfer($client_details,$pay_amount,$game_details->game_code,$game_details->game_name,$game_trans_ext_id,$game_trans_id,"credit",true,$fund_extra_data);
            if(isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "200"){
                ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                $balance = str_replace(',', '', number_format($client_response->fundtransferresponse->balance, 2));
                $bal = str_replace(".","", $balance);
                $format_balance = (int)$bal;
                $response = [
                    "balance" => $format_balance,
                    "txid" => $provider_txid,
                    'remotetxid' => "rollback_".$game_trans_id
                ];
                $dataToUpdate = array(
                    "mw_response" => json_encode($response),
                    "client_response" => json_encode($client_response),
                    "mw_request" => json_encode($client_response->requestoclient),
                    "transaction_detail" => 'SUCCESS',
                );
                GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$game_trans_ext_id,$client_details);
                return response($response,200)
                    ->header('Content-Type', 'application/json');
            }


        }else{
            return response()->json([
                'errorcode' => "INVALID_TOKEN",
                'errormessage' =>"The token could not be verified."      
            ]);

        }
    }
}
