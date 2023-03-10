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
        ProviderHelper::saveLog("Hacksaw Index Request",$this->provider_db_id,json_encode($request->all()),"HIT!");
        $data = $request->all();
        $action_method = $data['action'];
        $secret_key = $data['secret'];
        if(isset($data['token'])){
            $token = $data['token'];
            $client_details = ProviderHelper::getClientDetails('token', $token);
        }elseif(isset($data['externalPlayerId'])){
            $player_id = $data['externalPlayerId'];
            $client_details = ProviderHelper::getClientDetails('player_id', $player_id);
        }elseif(isset($data['externalSessionId'])){
            $player_token = $data['externalSessionId'];
            $client_details = ProviderHelper::getClientDetails('token', $player_token);
        }else{
            return response()->json([
                'statusCode' => 12,
                'statusMessage' => 'Invalid action.'
            ]); 
        }
        ProviderHelper::saveLog("Hacksaw Request",142,json_encode($data),"HIT!");
        if($client_details == null){
            return response()->json([
                'accountBalance' => $client_details->balance,
                
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
        elseif($action_method == 'Balance'){
            $balance = str_replace(".","", $client_details->balance);
            $format_balance = (int)$balance;
            return response()->json([
                'accountBalance' => $format_balance,
                'accountCurrency' => $client_details->default_currency,
                'statusCode' => 0,
                'statusMessage' => 'Success'
            ]);     
        }
        elseif($action_method == 'EndSession'){
            return response()->json([
                'statusCode' => 0,
                'statusMessage' => 'Success'
            ]); 
        }
        elseif($action_method == 'Bet'){
            // ProviderHelper::saveLog("Hacksaw Bet",142,json_encode($data),"Bet HIT!");
            return $response = $this->GameBet($request->all(),$client_details);
        }
        elseif($action_method == 'Win'){
            return $response = $this->GameWin($request->all(),$client_details);
        }
        elseif($action_method == 'Rollback'){
            // ProviderHelper::saveLog("Hacksaw Rollback",$this->provider_db_id,json_encode($data),"Rollback HIT!");
            return $response = $this->GameCancel($request->all(),$client_details);
        }else{
            return response()->json([
                'statusCode' => 12,
                'statusMessage' => 'Invalid action.'
            ]); 
        }
    }
    public function GameBet($request,$client_details){ 
        $data = $request;
        // ProviderHelper::saveLog("Hacksaw Function Bet",$this->provider_db_id,json_encode($data),"Bet HIT!");
        if($client_details){
            if($data['amount'] == 0){
                $amount = 0;
            }else{
                $amount = $data['amount'] / 100;
            }
            $roundId = $data['roundId'];
            $provider_trans_id = $data['transactionId'];
            $gamedetails = ProviderHelper::findGameDetails('game_code',75, $data['gameId']);
            try{
                // ProviderHelper::saveLog("Hacksaw Idempotent Bet",$this->provider_db_id,json_encode($data),"Bet HIT!");
                ProviderHelper::idenpotencyTable("BET_".$data['transactionId']);
            }catch(\Exception $e){
                $bet_transaction = GameTransactionMDB::findGameExt($data['transactionId'], 1,'transaction_id', $client_details);
                if ($bet_transaction != 'false') {
                    //this will be trigger if error occur 10s
                    // Helper::saveLog('Hacksaw BET duplicate_transaction success', $this->provider_db_id, json_encode($data),  $bet_transaction->mw_response);
                    $balance = str_replace(".","", $client_details->balance-$amount);
                    return response()->json([
                        "accountBalance"=>$balance,
                        "externalTransactionId"=> $roundId."_".$provider_trans_id,
                        "statusCode"=>0,
                        "statusMessage"=>""
                    ]);
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
            $bet_transaction = GameTransactionMDB::getGameTransactionByRoundId($roundId,$client_details);           
            if($bet_transaction != null){
                //Side Bet
                // $client_details->connection_name = $bet_transaction->connection_name;
                $amount = $bet_transaction->bet_amount + $amount;
                $game_transaction_id = $bet_transaction->game_trans_id;
                $updateGameTransaction = [
                    'win' => 5,
                    'bet_amount' => $amount,
                    'entry_id' => 1,
                    'trans_status' => 1
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
                //Freespin
                if(isset($data['freeRoundData'])){
                    //this is double bet
                    $game_trans_id = $bet_transaction->game_trans_id;
                    $updateTransaction = [
                        "win" => 5,
                        "trans_status" => 1,
                        "bet_amount" => $bet_transaction->bet_amount+$amount,
                    ];
                    GameTransactionMDB::updateGametransaction($updateTransaction,$game_trans_id,$client_details);
                    $gametransExt_data = [
                        "game_trans_id" => $game_trans_id,
                        "provider_trans_id" => $provider_trans_id,
                        "round_id" => $roundId,
                        "amount" => $amount,
                        "game_transaction_type" => 1,
                        "provider_request" => json_encode($data),
                    ];
                    $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gametransExt_data,$client_details);
                    $client_response = ClientRequestHelper::fundTransfer($client_details,$amount,$gamedetails->game_code,$gamedetails->game_name,$game_trans_ext_id,$bet_transaction->game_trans_id,'debit');
                    if(isset($client_response->fundtransferresponse->status->code)
                    && $client_response->fundtransferresponse->status->code == "200"){
                        Helper::saveLog('Hacksaw Freespin Bet', $this->provider_db_id, json_encode($data), 'FUNDTRANSFER HIT!');
                        $balance = round($client_response->fundtransferresponse->balance, 2);
                        $bal = str_replace(".","", $balance);
                        $format_balance = (int)$bal;
                        ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                        //SUCCESS FUNDTRANSFER
                        $response = [
                            "accountBalance"=> $format_balance,
                            "statusCode"=>0,
                            "externalTransactionId"=> $roundId."_".$provider_trans_id,
                            "statusMessage"=>"Success"
                        ];
                        $extensionData = [
                            "mw_request" => json_encode($client_response->requestoclient),
                            "mw_response" =>json_encode($response),
                            "client_response" => json_encode($client_response),
                            "transaction_detail" => "Success",
                            "general_details" => "Success",
                        ];
                        GameTransactionMDB::updateGametransactionEXT($extensionData,$game_trans_ext_id,$client_details);
                        // Helper::saveLog('Hacksaw Bet', $this->provider_db_id, json_encode($response), 'Success HIT!');
                        return response()->json([
                            "accountBalance"=> $format_balance,
                            "statusCode"=>0,
                            "externalTransactionId"=> $roundId."_".$provider_trans_id,
                            "statusMessage"=>"Success"
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
                            return response()->json([
                                "accountBalance"=>(int) $format_balance,
                                "externalTransactionId"=> $roundId."_".$provider_trans_id,
                                "statusCode"=>11,
                                "statusMessage"=>"General Error"
                            ]);
                        }catch(\Exception $e){
                        Helper::saveLog("FAILED FREESPIN BET", 142,json_encode($client_response),"FAILED HIT!");
                        }
                    }
                }
                return response()->json([
                    "accountBalance"=>(int) $format_balance,
                    "externalTransactionId"=> $roundId."_".$provider_trans_id,
                    "statusCode"=>11,
                    "statusMessage"=>"General Error"
                ]);
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
                $balance = round($client_response->fundtransferresponse->balance, 2);
                $bal = str_replace(".","", $balance);
                $format_balance = (int)$bal;
                ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                //SUCCESS FUNDTRANSFER
                $response = [
                    "accountBalance"=> $format_balance,
                    "statusCode"=>0,
                    "externalTransactionId"=> $roundId."_".$provider_trans_id,
                    "statusMessage"=>"Success"
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
                    "accountBalance"=> $format_balance,
                    "statusCode"=>0,
                    "externalTransactionId"=> $roundId."_".$provider_trans_id,
                    "statusMessage"=>"Success"
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
                    return response()->json([
                        "accountBalance"=>(int) $format_balance,
                        "externalTransactionId"=> $roundId."_".$provider_trans_id,
                        "statusCode"=>5,
                        "statusMessage"=>"Insufficient funds to place bet"
                    ]);
                }catch(\Exception $e){
                    Helper::saveLog("FAILED BET", $this->provider_db_id,json_encode($client_response),"FAILED HIT!");
                }
            }
        }else{
            return response()->json([
                'statusCode' => 2,
                'statusMessage' => 'Invalid user / token expired'
            ]);
        }
    }
    
    public function GameWin($request,$client_details){
        $data = $request;
        if($client_details){
            try{
                ProviderHelper::IdenpotencyTable("WIN_".$data['transactionId']);
            }catch(\Exception $e){
                $balance = str_replace(".","", $client_details->balance);
                $format_balance = (int)$balance;
                return response()->json([
                    "accountBalance"=>$format_balance,
                    "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                    "statusCode"=>0,
                    "statusMessage"=>""
                ]);
            }
            $provider_trans_id = $data['transactionId'];
            $roundId = $data['roundId'];
            if($data['amount'] == 0){
                $amount = 0;
            }else{
                $amount = $data['amount'] / 100;
            }
            $gamedetails = ProviderHelper::findGameDetails('game_code',75, $data['gameId']);
            $balance = str_replace(".","", $client_details->balance);
            $format_balance = (int)$balance;
            if(isset($data['betTransactionId'])){
                $game = GametransactionMDB::hacksawGetGameTransactionByTransId($data['betTransactionId'],$client_details);
            }else{
                $game = GametransactionMDB::getGameTransactionByRoundId($roundId, $client_details);
            }
            if ($game == null){
                // Helper::saveLog("NO BET FOUND", 141,json_encode($data),"HIT!");
                
                if(isset($data['freeRoundData'])){
                    $gameTransactionDatas = [
                        "provider_trans_id" => $provider_trans_id,
                        "token_id" => $client_details->token_id,
                        "game_id" => $gamedetails->game_id,
                        "round_id" => $roundId,
                        "bet_amount" => 0,
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
                        "amount" => 0,
                        "game_transaction_type" => 1,
                        "provider_request" => json_encode($data),
                    ];
                    $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameExtensionData,$client_details);
                    $fund_extra_data = [
                        'provider_name' => $gamedetails->provider_name
                    ];
                    $client_response = ClientRequestHelper::fundTransfer($client_details,0,$gamedetails->game_code,$gamedetails->game_name,$game_trans_ext_id,$game_trans_id,'debit',false,$fund_extra_data);
                    if(isset($client_response->fundtransferresponse->status->code)
                    && $client_response->fundtransferresponse->status->code == "200"){
                        $balance = round($client_response->fundtransferresponse->balance, 2);
                        $bal = str_replace(".","", $client_details->balance);
                        $format_balance = (int)$bal;
                        ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                        //SUCCESS FUNDTRANSFER
                        $win = $amount == 0 ? 0 : 1;
                        $updateTransData = [
                            "win" => $win,
                            "pay_amount" => round($amount,2),
                            "income" => round($amount,2),
                            "entry_id" => $amount == 0 ? 1 : 2,
                        ];
                        GameTransactionMDB::updateGametransaction($updateTransData,$game_trans_id,$client_details);
                        $response =[
                            "accountBalance"=>$format_balance,
                            "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                            "statusCode"=>0,
                            "statusMessage"=>""
                        ];
                        $gameExtensionData = [
                            "game_trans_id" => $game_trans_id,
                            "provider_trans_id" => $provider_trans_id,
                            "round_id" => $roundId,
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
                                "provider_trans_id"=>$provider_trans_id,
                                "provider_round_id"=>$roundId,
                                'provider_name' => $gamedetails->provider_name
                            ],
                            "mwapi" => [
                                "roundId"=> $game_trans_id,
                                "type" => 2,
                                "game_id" => $gamedetails->game_id,
                                "player_id" => $client_details->player_id,
                                "mw_response" => $response,
                            ]
                        ];
                        $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$amount,$gamedetails->game_code,$gamedetails->game_name,$game_trans_id,'credit',false,$action_payload);
                        if(isset($client_response->fundtransferresponse->status->code) &&
                        $client_response->fundtransferresponse->status->code == "200"){
                            $balance = round($client_response->fundtransferresponse->balance, 2);
                            $bal = str_replace(".","", $balance);
                            $format_balance = (int)$bal;
                            ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                            //SUCCESS FUNDTRANSFER
                            $response = [
                                "accountBalance"=>$format_balance,
                                "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                                "statusCode"=>0,
                                "statusMessage"=>""
                            ];
                            $msg = [
                                "mw_response" => json_encode($response)
                            ];
                            GameTransactionMDB::updateGametransactionEXT($msg,$game_trans_ext_id,$client_details);
                            // Helper::saveLog('Hacksaw Win', $this->provider_db_id, json_encode($data), 'Success HIT!');
                            return response()->json([
                                "accountBalance"=>$format_balance,
                                "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                                "statusCode"=>0,
                                "statusMessage"=>""
                            ]);
                        
                        }
                    }elseif(isset($client_response->fundtransferresponse->status->code)
                    && $client_response->fundtransferresponse->status->code == "402"){
                        try{    
                            $updateTrans = [
                                "win" => 2,
                                "trans_status" => 5
                            ];
                            GameTransactionMDB::updateGametransaction($updateTrans,$game_trans_id,$client_details);
                            $response = [
                                "accountBalance"=>$format_balance,
                                "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                                "statusCode"=>11,
                                "statusMessage"=>"General Error"
                            ];
                            $updateExt = [
                                "mw_response" =>json_encode($response),
                                "mw_request" => json_encode('FAILED'),
                                "client_response" => json_encode($client_response),
                                "transaction_detail" => "FAILED",
                                "general_details" => "FAILED",
                            ];
                            GameTransactionMDB::updateGametransactionEXT($updateExt,$game_trans_ext_id,$client_details);
                            return response()->json([
                                "accountBalance"=>$format_balance,
                                "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                                "statusCode"=>5,
                                "statusMessage"=>"Insufficient funds to place bet"
                            ]);
                        }catch(\Exception $e){
                            Helper::saveLog("FAILED WIN",$this->provider_db_id,json_encode($client_response),"FAILED HIT!");
                        }
                    }
                }else{
                    // Helper::saveLog("FAILED WIN",$this->provider_db_id,json_encode($data),$format_balance+$amount);
                    $amount = $data['amount'];
                    $balance = str_replace(".","", $client_details->balance+$amount);
                    return response()->json([
                        "accountBalance"=>(int)$balance,
                        "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                        "statusCode"=>0,
                        "statusMessage"=>""
                    ]);
                }
            }
            $win = $amount + $game->pay_amount == 0 ? 0 : 1;
            $updateTransData = [
                "win" => $win,
                "pay_amount" => round($amount + $game->pay_amount,2),
                "income" => round($game->bet_amount-$game->pay_amount - $amount,2),
                "entry_id" => $amount == 0 ? 1 : 2,
            ];
            GameTransactionMDB::updateGametransaction($updateTransData,$game->game_trans_id,$client_details);
            $response =[
                "accountBalance"=>$format_balance,
                "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                "statusCode"=>0,
                "statusMessage"=>""
            ];
            $gameExtensionData = [
                "game_trans_id" => $game->game_trans_id,
                "provider_trans_id" => $provider_trans_id,
                "round_id" => $roundId,
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
                    "provider_trans_id"=>$provider_trans_id,
                    "provider_round_id"=>$roundId,
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
            if($game->win == 4 || $game->win == 2){
                return response()->json([
                    "accountBalance"=>$format_balance,
                    "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                    "statusCode"=>0,
                    "statusMessage"=>""
                ]);
            }
            else{
                $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$amount,$gamedetails->game_code,$gamedetails->game_name,$game->game_trans_id,'credit',false,$action_payload);
                if(isset($client_response->fundtransferresponse->status->code) &&
                $client_response->fundtransferresponse->status->code == "200"){
                    $balance = round($client_response->fundtransferresponse->balance, 2);
                    $bal = str_replace(".","", $balance);
                    $format_balance = (int)$bal;
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    //SUCCESS FUNDTRANSFER
                    $response = [
                        "accountBalance"=>$format_balance,
                        "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                        "statusCode"=>0,
                        "statusMessage"=>""
                    ];
                    $msg = [
                        "mw_response" => json_encode($response)
                    ];
                    GameTransactionMDB::updateGametransactionEXT($msg,$game_trans_ext_id,$client_details);
                    // Helper::saveLog('Hacksaw Win', $this->provider_db_id, json_encode($data), 'Success HIT!');
                    return response()->json([
                        "accountBalance"=>$format_balance,
                        "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                        "statusCode"=>0,
                        "statusMessage"=>""
                    ]);
                }
            }
        }else{
            return response()->json([
                'statusCode' => 2,
                'statusMessage' => 'Invalid user / token expired'
            ]);
        }
    }
    public function GameCancel($request,$client_details){
        $data = $request;
        if($client_details){
            try{
                Helper::saveLog('Hacksaw Rollback Idempotent', $this->provider_db_id, json_encode($data), 'Success HIT!');
                ProviderHelper::IdenpotencyTable($data['transactionId']);
            }catch(\Exception $e){
                $balance = str_replace(".","", $client_details->balance);
                $format_balance = (int)$balance;
                return response()->json([
                    "accountBalance"=>$format_balance,
                    "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                    "statusCode"=>0,
                    "statusMessage"=>""
                ]);
            }
            $provider_trans_id = $data['transactionId'];
            $roundId = $data['roundId'];
            if($data['amount'] == 0){
                $amount = 0;
            }else{
                $amount = $data['amount'] / 100;
            }
            $gamedetails = ProviderHelper::findGameDetails('game_code',75, $data['gameId']);
            $game = GametransactionMDB::getGameTransactionByRoundId($roundId, $client_details);
            if($game == null){
                $game = GametransactionMDB::getGameTransactionDataByProviderTransactionId($data['rolledBackTransactionId'],$client_details);
                if($game ==null){
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
                    // ProviderHelper::saveLog("Hacksaw Create Trans Bet",$this->provider_db_id,json_encode($gameTransactionDatas),"Bet HIT!");
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
                        $balance = round($client_response->fundtransferresponse->balance, 2);
                        $bal = str_replace(".","", $balance);
                        $format_balance = (int)$bal;
                        ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                        $win = 4;
                        $updateTransData = [
                            "win" => $win,
                            "pay_amount" => round($amount,2),
                            "income" => 0,
                            "entry_id" => $amount == 0 ? 1 : 2,
                        ];
                        GameTransactionMDB::updateGametransaction($updateTransData,$game_trans_id,$client_details);
                        $response =[
                            "accountBalance"=>$format_balance,
                            "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                            "statusCode"=>0,
                            "statusMessage"=>""
                        ];
                        $gameExtensionData = [
                            "game_trans_id" => $game_trans_id,
                            "provider_trans_id" => $provider_trans_id,
                            "round_id" => $roundId,
                            "amount" => $amount,
                            "game_transaction_type" => 3,
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
                                "provider_trans_id"=>$provider_trans_id,
                                "provider_round_id"=>$roundId,
                                'provider_name' => $gamedetails->provider_name
                            ],
                            "mwapi" => [
                                "roundId"=> $game_trans_id,
                                "type" => 3,
                                "game_id" => $gamedetails->game_id,
                                "player_id" => $client_details->player_id,
                                "mw_response" => $response,
                            ]
                        ];
                        $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$amount,$gamedetails->game_code,$gamedetails->game_name,$game_trans_id,'credit',true,$action_payload);
                        if(isset($client_response->fundtransferresponse->status->code) &&
                        $client_response->fundtransferresponse->status->code == "200"){
                            $balance = round($client_response->fundtransferresponse->balance, 2);
                            $bal = str_replace(".","", $balance);
                            $format_balance = (int)$bal;
                            ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                            //SUCCESS FUNDTRANSFER
                            $response = [
                                "accountBalance"=>$format_balance,
                                "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                                "statusCode"=>0,
                                "statusMessage"=>""
                            ];
                            $msg = [
                                "mw_response" => json_encode($response)
                            ];
                            GameTransactionMDB::updateGametransactionEXT($msg,$game_trans_ext_id,$client_details);
                            // Helper::saveLog('Hacksaw Win', $this->provider_db_id, json_encode($data), 'Success HIT!');
                            return response()->json([
                                "accountBalance"=>$format_balance,
                                "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                                "statusCode"=>0,
                                "statusMessage"=>""
                            ]);
                        }
                        //SUCCESS FUNDTRANSFER
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
                            return response()->json([
                                "accountBalance"=>(int) $format_balance,
                                "externalTransactionId"=> $roundId."_".$provider_trans_id,
                                "statusCode"=>5,
                                "statusMessage"=>"Insufficient funds to place bet"
                            ]);
                        }catch(\Exception $e){
                        Helper::saveLog("FAILED BET", $this->provider_db_id,json_encode($client_response),"FAILED HIT!");
                        }
                    }
                };
            }
            $balance = str_replace(".","", $client_details->balance);
            $format_balance = (int)$balance;
            $win = 4;
            $updateTransData = [
                "win" => $win,
                "pay_amount" => round($amount + $game->pay_amount,2),
                "income" => round($game->bet_amount-$game->pay_amount - $amount,2),
                "entry_id" => $amount == 0 ? 1 : 2,
            ];
            GameTransactionMDB::updateGametransaction($updateTransData,$game->game_trans_id,$client_details);
            $response =[
                "accountBalance"=>$format_balance,
                "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                "statusCode"=>0,
                "statusMessage"=>"Success"
            ];
            $gameExtensionData = [
                "game_trans_id" => $game->game_trans_id,
                "provider_trans_id" => $provider_trans_id,
                "round_id" => $roundId,
                "amount" => $amount,
                "game_transaction_type" => 3,
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
                    "provider_trans_id"=>$provider_trans_id,
                    "provider_round_id"=>$roundId,
                    'provider_name' => $gamedetails->provider_name
                ],
                "mwapi" => [
                    "roundId"=> $game->game_trans_id,
                    "type" => 3,
                    "game_id" => $gamedetails->game_id,
                    "player_id" => $client_details->player_id,
                    "mw_response" => $response,
                ]
            ];
            $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$amount,$gamedetails->game_code,$gamedetails->game_name,$game->game_trans_id,'credit',true,$action_payload);
            if(isset($client_response->fundtransferresponse->status->code) &&
            $client_response->fundtransferresponse->status->code == "200"){
                $balance = round($client_response->fundtransferresponse->balance, 2);
                $bal = str_replace(".","", $balance);
                $format_balance = (int)$bal;
                ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                //SUCCESS FUNDTRANSFER
                $response = [
                    "accountBalance"=>$format_balance,
                    "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                    "statusCode"=>0,
                    "statusMessage"=>"Success"
                ];
                $msg = [
                    "mw_response" => json_encode($response)
                ];
                GameTransactionMDB::updateGametransactionEXT($msg,$game_trans_ext_id,$client_details);
                // Helper::saveLog('Hacksaw Win', $this->provider_db_id, json_encode($data), 'Success HIT!');
                return response()->json([
                    "accountBalance"=>$format_balance,
                    "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                    "statusCode"=>0,
                    "statusMessage"=>"Success"
                ]);
            }
        }else{
            return response()->json([
                'statusCode' => 2,
                'statusMessage' => 'Invalid user / token expired'
            ]);
        }
    }

 public function Rollback($request,$client_details){
    $data = $request;
    $reference_transaction_uuid = $data["rolledBackTransactionId"];
    $amount = $data["amount"]/100;
    $bal = str_replace(".","", $client_details->balance);
    $format_balance = (int)$bal;
    if($client_details != null){
        ProviderHelper::saveLog("Hacksaw Rollback",$this->provider_db_id,json_encode($data),"Rollback HIT!");
		$bet_transaction = GameTransactionMDB::findGameExt($reference_transaction_uuid, 3,'transaction_id', $client_details);
		if ($bet_transaction != 'false') {  
            Helper::saveLog('Hacksaw Roll duplicate_transaction success', $this->provider_db_id, json_encode($data),  $bet_transaction->mw_response);
			return response()->json([
                "accountBalance"=>$format_balance,
                "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                "statusCode"=>1,
                "statusMessage"=>"General/Server error"
            ]);
		} 
		try{
	 		ProviderHelper::idenpotencyTable("Rollback_".$data["transactionId"]);
		}catch(\Exception $e){
		 	// sleep(7);
		 	$bet_transaction = GameTransactionMDB::findGameExt($reference_transaction_uuid, 3,'transaction_id', $client_details);
		 	if ($bet_transaction != 'false') {
		 		//this will be trigger if error occur 10s
                 Helper::saveLog('Hacksaw BET duplicate_transaction success', $this->provider_db_id, json_encode($data),  $bet_transaction->mw_response);
                 return response()->json([
                    "accountBalance"=>$format_balance,
                    "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                    "statusCode"=>1,
                    "statusMessage"=>"General/Server error"
                ]);
				
				
		 	} 
		}
        $gamedetails = ProviderHelper::findGameDetails('game_code',75, $data['gameId']);
		$existing_bet = GameTransactionMDB::findGameTransactionDetails($reference_transaction_uuid, 'transaction_id',false, $client_details);
		if($existing_bet == 'false'){
            return response()->json([
                "accountBalance"=>$format_balance,
                "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                "statusCode"=>1,
                "statusMessage"=>"General/Server error"
            ]);
		}
		$client_details->connection_name = $existing_bet->connection_name;
		$already_process = GameTransactionMDB::findGameExt($reference_transaction_uuid, 2,'transaction_id', $client_details);
		if($already_process != 'false'){
			return response($already_process->mw_response,200)
		 		->header('Content-Type', 'application/json');
		}
		try{
			$num = $client_details->balance + $existing_bet->bet_amount;
			ProviderHelper::_insertOrUpdate($client_details->token_id, $num); 
			$response = [
				"accountBalance" => $format_balance ,
				"externalTransactionId" => $data['roundId']."_".$data['transactionId'],
				"statusCode" => 0,
				"statusMessage" =>"Success"
			];
			$create_gametransactionext = array(
				"game_trans_id" => $existing_bet->game_trans_id,
				"provider_trans_id" => $reference_transaction_uuid,
				"round_id" => $transaction_uuid,
				"amount" => $existing_bet->bet_amount,
				"game_transaction_type"=> 3,
				"provider_request" => json_encode($data),
				"mw_response" => json_encode($response)
			);
			$game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($create_gametransactionext,$client_details);
			$win_or_lost = 4;
            $entry_id = $existing_bet->bet_amount > 0 ?  2 : 1;
           	$income = 0;
			$updateGameTransaction = [
	            'win' => 5,
	            'pay_amount' => $existing_bet->bet_amount,
	            'income' => $income,
	            'entry_id' => $entry_id,
	            'trans_status' => 3
	        ];
        	GameTransactionMDB::updateGametransaction($updateGameTransaction, $existing_bet->game_trans_id, $client_details);
			$body_details = [
	            "type" => "credit",
	            "win" => $win_or_lost,
	            "token" => $client_details->player_token,
	            "rollback" => "true",
	            "game_details" => [
	                "game_id" => $game_details->game_id
	            ],
	            "game_transaction" => [
	                "amount" => $existing_bet->bet_amount
	            ],
	            "connection_name" => $existing_bet->connection_name,
	            "game_trans_ext_id" => $game_trans_ext_id,
	            "game_transaction_id" => $existing_bet->game_trans_id

	        ];
			try {
				$client = new Client();
		 		$guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-bgFundTransferV2MultiDB',
		 			[ 'body' => json_encode($body_details), 'timeout' => '2.00']
		 		);
		 		//THIS RESPONSE IF THE TIMEOUT NOT FAILED
                 ProviderHelper::saveLog("Hacksaw Rollback",$this->provider_db_id,json_encode($data),$response);
	            return $response;
			} catch (\Exception $e) {
	            HProviderHelper::saveLog("Hacksaw Rollback",$this->provider_db_id,json_encode($data),$response);
	            return $response;
			}
		}catch(\Exception $e){
            Helper::saveLog('Hacksaw Rollback error ='.$e->getMessage(), $this->provider_db_id, json_encode($data), "Rollback Error");
            return response()->json([
                "accountBalance"=>$format_balance,
                "externalTransactionId"=> $data['roundId']."_".$data['transactionId'],
                "statusCode"=>1,
                "statusMessage"=>"General/Server error"
            ]);
			
			
		}
    }
    }

    public function Gamelist(Request $request){
        $client = new Client();
            $partner_id = 'tigergames_stg';
            $currency = 'USD';
            $gameId = 1001;

            $response = $client->request('GET', 'https://api.hacksawgaming.com/api/v1/meta/' . $partner_id . '/gameList', [
                'query' => [
                    'currency' => $currency
                ]
            ]);

            $data = json_decode($response->getBody());

            return $data;
   }
}
