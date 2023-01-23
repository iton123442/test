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
            try{
                $player_id = $data['externalPlayerId'];
                $client_details = ProviderHelper::getClientDetails('player_id', $player_id);
            }catch(\Exception $e){
                $player_token = $data['externalSessionId'];
                $client_details = ProviderHelper::getClientDetails('token', $player_token);  
            }          
        }
        ProviderHelper::saveLog("Hacksaw Request",142,json_encode($data),"HIT!");
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
            // ProviderHelper::saveLog("Hacksaw Request",142,json_encode($data),"BET HIT!");
            $response = $this->GameBet($request->all());
            return response($response,200)
                ->header('Content-Type', 'application/json');
        }
        if($action_method == 'Rollback'){
            // ProviderHelper::saveLog("Hacksaw Request",142,json_encode($data),"WIN HIT!");
            return response()->json([
                "accountBalance"=>$format_balance,
                "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                "statusCode"=>0,
                "statusMessage"=>""
            ]);
            // $response = $this->GameWin($request->all());
            // return response($response,200)
            //     ->header('Content-Type', 'application/json');
        }
    }
    public function GameBet($request){ 
        $data = $request;
        try{
            $player_id = $data['externalPlayerId'];
            $client_details = ProviderHelper::getClientDetails('player_id', $player_id);
        }catch(\Exception $e){
            $player_token = $data['externalSessionId'];
            $client_details = ProviderHelper::getClientDetails('token', $player_token);  
        }
        if($client_details){
            $roundId = $data['roundId'];
            $provider_trans_id = $data['transactionId'];
            try{
                ProviderHelper::idenpotencyTable("BET_".$data['transactionId']);
            }catch(\Exception $e){
                $bet_transaction = GameTransactionMDB::findGameExt($data['transactionId'], 1,'transaction_id', $client_details);
                if ($bet_transaction != 'false') {
                    //this will be trigger if error occur 10s
                    Helper::saveLog('Hacksaw BET duplicate_transaction success', $this->provider_db_id, json_encode($request->all()),  $bet_transaction->mw_response);
                    return response($bet_transaction->mw_response,200)
                    ->header('Content-Type', 'application/json');
                } 
                // sleep(4);
                $balance = str_replace(".","", $client_details->balance);
                return response()->json([
                    "accountBalance"=>$balance,
                    "externalTransactionId"=> $roundId."_".$provider_trans_id,
                    "statusCode"=>11,
                    "statusMessage"=>"General error"
                ]);
            }
            $amount = $data['amount'] / 100;
            $gamedetails = ProviderHelper::findGameDetails('game_code', 75, $data['gameId']);
            $bet_transaction = GameTransactionMDB::getGameTransactionByRoundId($roundId,$client_details);
            if($bet_transaction != null){

            }
            $gameTransactionDatas = [
                "provider_trans_id" => $provider_trans_id,
                "token_id" => $client_details->token_id,
                "game_id" => $gamedetails->game_id,
                "round_id" => $roundId,
                "bet_amount" => $amount,
                "pay_amount" => 0,
                "win" => 5,
                "income" => 0,
                "entry_id" => 1
            ];
            $game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionDatas,$client_details);
            $gameExtensionData = [
                "game_trans_id" => $game_trans_id,
                "provider_trans_id" => $provider_trans_id,
                "round_id" => $roundId,
                "amount" => $amount,
                "game_transaction_type" => 1,
                "provider_request" => json_encode($data),
            ];
            $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameExtensionData,$client_details);
            $fund_extra_data = [
                'provider_name' => $gamedetails->provider_name
            ];
            $client_response = ClientRequestHelper::fundTransfer($client_details,$amount,$gamedetails->game_code,$gamedetails->game_name,$game_trans_ext_id,$game_trans_id,'debit',false,$fund_extra_data);
            if(isset($client_response->fundtransferresponse->status->code)
            && $client_response->fundtransferresponse->status->code == "200"){
                Helper::saveLog('Hacksaw Bet', $this->provider_db_id, json_encode($data), 'FUNDTRANSFER HIT!');
                $balance = round($client_response->fundtransferresponse->balance, 2);
                $bal = str_replace(".","", $balance);
                $format_balance = (int)$bal;
                ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                //SUCCESS FUNDTRANSFER
                $response = [
                    "accountBalance"=> $format_balance,
                    "statusCode"=>0,
                    "externalTransactionId"=> $roundId."_".$provider_trans_id,
                    "statusMessage"=>""
                ];
                $extensionData = [
                    "mw_request" => json_encode($client_response->requestoclient),
                    "mw_response" =>json_encode($response),
                    "client_response" => json_encode($client_response),
                    "transaction_detail" => "Success",
                    "general_details" => "Success",
                ];
                GameTransactionMDB::updateGametransactionEXT($extensionData,$game_trans_ext_id,$client_details);
                Helper::saveLog('Hacksaw Bet', $this->provider_db_id, json_encode($response), 'Success HIT!');
                return response()->json([
                    "accountBalance"=> $format_balance,
                    "statusCode"=>0,
                    "externalTransactionId"=> $roundId."_".$provider_trans_id,
                    "statusMessage"=>""
                ]);
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
                        "accountBalance"=>(int) $format_balance,
                        "externalTransactionId"=> $roundId."_".$provider_trans_id,
                        "statusCode"=>5,
                        "statusMessage"=>"Insufficient funds to place bet"
                    ];
                    $updateExt = [
                        "mw_request" => json_encode('FAILED'),
                        "mw_response" =>json_encode($response),
                        "client_response" => json_encode($client_response),
                        "transaction_detail" => "FAILED",
                        "general_details" => "FAILED",
                    ];
                    GameTransactionMDB::updateGametransactionEXT($updateExt,$game_trans_ext_id,$client_details);
                    return response($response,200)->header('Content-Type', 'application/json');
                }catch(\Exception $e){
                Helper::saveLog("FAILED BET", 142,json_encode($client_response),"FAILED HIT!");
                }
            }
        }else{
            return response()->json([
                'statusCode' => 2,
                'statusMessage' => 'Invalid user / token expired'
            ]);
        }
    }
    
    public function GameWin($request){
        $data = $request;
        try{
            $player_id = $data['externalPlayerId'];
            $client_details = ProviderHelper::getClientDetails('player_id', $player_id);
        }catch(\Exception $e){
            $player_token = $data['externalSessionId'];
            $client_details = ProviderHelper::getClientDetails('token', $player_token);  
        }
        if($client_details){
            $balance = str_replace(".","", $client_details->balance);
            $format_balance = (float)$balance;
            Helper::saveLog('Hacksaw Rollback', $this->provider_db_id, json_encode($data), 'Success HIT!');
            return response()->json([
                "accountBalance"=>$format_balance,
                "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                "statusCode"=>0,
                "statusMessage"=>""
            ]);
        }else{
            return response()->json([
                'statusCode' => 2,
                'statusMessage' => 'Invalid user / token expired'
            ]);
        }
    }
}

