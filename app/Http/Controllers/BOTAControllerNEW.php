<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Helpers\ClientRequestHelper;
use App\Helpers\TransactionHelper;
use App\Helpers\BOTAHelper;
use App\Models\GameTransactionMDB;
use App\Models\GameTransaction;
use DB;
use Exception;

class BOTAControllerNEW extends Controller{
// THIS IS BOTA NEW FLOW!
    protected $startTime;
    public function __construct() {
        $this->startTime = microtime(true);
        $this->provider_db_id = config('providerlinks.bota.provider_db_id'); //sub provider ID
        $this->api_key = config('providerlinks.bota.api_key');
        $this->api_url = config('providerlinks.bota.api_url');
        $this->prefix = config('providerlinks.bota.prefix');
        $this->providerID = 71; //Real provider ID
        $this->dateToday = date("Y/m/d");
    }

    public function index(Request $request){
        $data = json_decode($request->getContent(),TRUE);
        Helper::saveLog('BOTA Auth INDEX', $this->provider_db_id, json_encode($data), 'INDEX HIT!');
        $originalPlayerID = explode('_', $data["user"]);
        $client_details = ProviderHelper::getClientDetails('player_id', $originalPlayerID[1]);
        if($client_details){
            $playerChecker = BOTAHelper::botaPlayerChecker($client_details,'Verify');//Auth
            if(isset($playerChecker->result_code) && $playerChecker->result_code == 1){
                $msg = [
                    "result_code" => "1",
                    "result_msg" =>(string) "(no account)"
                ];
                return response($msg,200)->header('Content-Type', 'application/json');
                Helper::saveLog('BOTA NOT FOUND PLAYER',$this->provider_db_id, json_encode($msg), $data);
                    
            }else{
                //flow structure
                if($data['types'] == "balance") {
                    $result = $this->_getBalance($data,$client_details);
                    Helper::saveLog('BOTA Balance', $this->provider_db_id, json_encode($result), 'BALANCE HIT!');
                    return $result;
                }
                elseif($data['types'] == "bet") {
                        $result = $this->_betProcess($data,$client_details);
                        Helper::saveLog('BOTA BET',$this->provider_db_id, json_encode($result), 'BET HIT');
                        return $result;
                }
                elseif($data['types'] == "win") {
                    $result = $this->_winProcess($data,$client_details);
                    Helper::saveLog('BOTA WIN', $this->provider_db_id, json_encode($result), 'WIN HIT');
                    return $result;
                }
                elseif($data['types'] == "cancel") {
                    $result = $this->_cancelProcess($data,$client_details);
                    Helper::saveLog('BOTA CANCEL', $this->provider_db_id, json_encode($result), 'CANCEL HIT');
                    return $result;
                }
                else {
                    exit;
                }
                return response($data,200)->header('Content-Type', 'application/json');
            }
        
        }
        else{
            $msg = [
                "result_code" => "1",
                "result_msg" =>(string) "(no account)"
            ];
            return response($msg,200)->header('Content-Type', 'application/json');
            Helper::saveLog('BOTA TG NOTFOUND CLIENT DETAILS',$this->provider_db_id, json_encode($msg), $data);
        }
    }

    public function _getBalance($data,$client_details){
        Helper::saveLog('BOTA GetBALANCE', $this->provider_db_id, json_encode($data), 'Balance HIT!');
        if($client_details){
            $msg = array(
                "user" => $data['user'],
                "balance"=>(int) number_format($client_details->balance,2,'.', ''),
            );
            return response($msg,200)->header('Content-Type', 'application/json');
        }
    }

    public function _betProcess($data,$client_details){
        Helper::saveLog('BOTA betProcess', $this->provider_db_id, json_encode($data), 'BET Initialized');
        if($client_details){
            try{
                ProviderHelper::idenpotencyTable($this->prefix.$data['detail']['gameNo'].'_'.$data['detail']['shoeNo'].'_1');
            }catch(\Exception $e){//if bet exist
                $cancelBetRoundID = $data['detail']['shoeNo'].$data['detail']['gameNo'];
                $cancelbetExt = GameTransactionMDB::findBOTAGameExt($cancelBetRoundID,'round_id',3,$client_details);
                if($cancelbetExt == 'false'){
                    $betHistory = BOTAHelper::getBettingList($client_details,$this->dateToday);
                    if($betHistory->result_count != 0){
                        $myRoundID = $betHistory->result_value[0]->c_shoe_idx.$betHistory->result_value[0]->c_game_idx;
                    }else {
                        $myRoundID = 'false';
                    }
                    if($myRoundID == $cancelBetRoundID){//IF bet already exist
                        $result = $this->_newBetProcess($data,$client_details);//double bet
                        Helper::saveLog('BOTA DOUBLEBET',$this->provider_db_id, json_encode($result), 'DOUBLEBET HIT');
                        return $result;
                    }
                    else{
                        $msg = array(
                            "user" => $data['user'],
                            "balance" =>(int) round($client_details->balance,2),
                            "confirm" => "ok"
                        );
                        Helper::saveLog('BOTA BET DUPLICATE RETURN', $this->provider_db_id, json_encode($msg), 'BET FAILED');
                        return response($msg,200)
                        ->header('Content-Type', 'application/json');
                    }
                }
                else{
                    $result = $this->_reBetProcess($data,$client_details);
                    Helper::saveLog('BOTA REBET',$this->provider_db_id, json_encode($result), 'REBET HIT');
                    return $result;
                }
            }
            $gamedetails = ProviderHelper::findGameDetails('game_code', $this->providerID, 'BOTA');
            $bet_transaction = GameTransactionMDB::findGameTransactionDetails($this->prefix.'_'.$data['detail']['shoeNo'].$data['detail']['gameNo'], 'transaction_id', false, $client_details);
            $game_trans_id = ProviderHelper::idGenerate($client_details->connection_name, 1); // ID generator
            $bettransactionExtId = ProviderHelper::idGenerate($client_details->connection_name, 2);
            if($bet_transaction != "false"){//check if bet transaction is existing
                $client_details->connection_name = $bet_transaction->connection_name;
                $game_trans_id = $bet_transaction->game_trans_id;
                $updateGameTransaction = [
                    'win' => 5,
                    'bet_amount' => $bet_transaction->bet_amount + round($data['price'],2),
                    'entry_id' => 1,
                    'trans_status' => 1
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
            }
            else{
            $gameTransactionData = array(
                "provider_trans_id" => $this->prefix.'_'.$data['detail']['shoeNo'].$data['detail']['gameNo'],
                "token_id" => $client_details->token_id,
                "game_id" => $gamedetails->game_id,
                "round_id" => $data['detail']['shoeNo'].$data['detail']['gameNo'],
                "bet_amount" => round($data['price'],2),
                "pay_amount" => 0,
                "win" => 5,
                "income" => 0,
                "entry_id" => 1
            );
            GameTransactionMDB::createGametransactionV2($gameTransactionData, $game_trans_id,$client_details);
            }
            $bettransactionExt = array(
                "game_trans_id" => $game_trans_id,
                "provider_trans_id" => $this->prefix.'_'.$data['detail']['shoeNo'].$data['detail']['gameNo'],
                "round_id" => $data['detail']['shoeNo'].$data['detail']['gameNo'],
                "amount" => round($data['price'],2),
                "game_transaction_type" => 1,
                // "provider_request" => json_encode($data),
            );
            GameTransactionMDB::createGameTransactionExtV2($bettransactionExt, $bettransactionExtId,$client_details);
            $fund_extra_data = [
                'provider_name' => $gamedetails->provider_name
            ]; 
            $client_response = ClientRequestHelper::fundTransfer($client_details,round($data["price"],2),$gamedetails->game_code,$gamedetails->game_name,$bettransactionExtId,$game_trans_id,'debit',false,$fund_extra_data);
            if(isset($client_response->fundtransferresponse->status->code)
            && $client_response->fundtransferresponse->status->code == "200"){
                $balance = round($client_response->fundtransferresponse->balance, 2);
                ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                $response = array(
                    "user" => $data['user'],
                    "balance" =>(int) $balance,
                    "confirm" => "ok"
                );
                // $dataToUpdate =[
                //     "mw_response" =>json_encode($response),
                // ];
                // GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$bettransactionExtId, $client_details);
                $createGameTransactionLog = [
                    "connection_name" => $client_details->connection_name,
                    "column" =>[
                        "game_trans_ext_id" => $bettransactionExtId,
                        "request" => json_encode($data),
                        "response" => json_encode($response),
                        "log_type" => "provider_details",
                        "transaction_detail" => "success",
                    ]
                ];
                ProviderHelper::queTransactionLogs($createGameTransactionLog);
                return response($response, 200)->header('Content-type', 'application/json');
            }
            elseif(isset($client_response->fundtransferresponse->status->code)
            && $client_response->fundtransferresponse->status->code == "402"){
                $msg = array(
                    "status" => [
                        "code" => $client_response->fundtransferresponse->status->code,
                        "stauts" => $client_response->fundtransferresponse->status->status,
                        "message" =>$client_response->fundtransferresponse->status->message,
                    ],
                    "balance" => round($client_response->fundtransferresponse->balance, 2),
                    "currencycode" => $client_response->fundtransferresponse->currencycode
                );//error response
                $response = [
                    "result_code" => "5",
                    "result_message" => "User Insufficient amount"
                ];
                try{
                    $datatosend = array(
                    "win" => 2
                    );
                    $createGameTransactionLog = [
                        "connection_name" => $client_details->connection_name,
                        "column" =>[
                            "game_trans_ext_id" => $bettransactionExtId,
                            "request" => json_encode($data),
                            "response" => json_encode($response),
                            "log_type" => "provider_details",
                            "transaction_detail" => "success",
                        ]
                    ];
                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
                    GameTransactionMDB::updateGametransactionV2($datatosend,$game_trans_id,$client_details);
                    // $updateData = array(
                    //     "mw_response" => json_encode($msg)
                    // );
                    // GameTransactionMDB::updateGametransactionEXT($updateData, $bettransactionExtId, $client_details);
                }catch(\Exception $e){
                Helper::savelog('Insuficient Bet(BOTA)', $this->provider_db_id, json_encode($e->getMessage(),$client_response->fundtransferresponse->status->message));
                }
                return response($response, 200)->header('Content-Type', 'application/json');
            }
        }
        else{
            $msg = [
                "result_code" => "1",
                "result_msg" =>(string) "(no account)"
            ];
            return response($msg,200)->header('Content-Type', 'application/json');
            Helper::saveLog('BOTA TG FOUND CLIENT DETAILS',$this->provider_db_id, json_encode($msg), $data);
        }
    }

    public function _reBetProcess($data, $client_details){
        Helper::saveLog('REBET Processing', $this->provider_db_id, json_encode($data), 'REBET Initialized!');
        if(isset($client_details)){
            $betDetails = BOTAHelper::getBettingList($client_details,$this->dateToday);
            if($betDetails->result_count != 0){//check if bet history is not null
                $refundRoundID = $data['detail']['shoeNo'].$data['detail']['gameNo'];
                $checkRefundCount = GameTransactionMDB::findBOTAGameExt($refundRoundID,'round_id',3,$client_details);
                if(count($checkRefundCount) > 1 && $checkRefundCount != 'false'){//if canceled 2 times
                    try{
                        $newProvTransID = $this->prefix.'R_'.$betDetails->result_value[0]->c_idx;//last bet idx
                        ProviderHelper::idenpotencyTable($newProvTransID.'_44');//rebet
                    }catch(\Exception $e){//if bet exist
                        $msg = array(
                            "user" => $data['user'],
                            "balance" =>(int) round($client_details->balance,2),
                            "confirm" => "ok"
                        );
                        Helper::saveLog('BOTA REBET DUPLICATE RETURN', $this->provider_db_id, json_encode($msg), 'TRIPLE BET FAILED');
                        return response($msg,200)
                        ->header('Content-Type', 'application/json');
                    }
                }
                else{
                    try{
                        $newProvTransID = $this->prefix.'_'.$betDetails->result_value[0]->c_idx;//last bet idx
                        ProviderHelper::idenpotencyTable($newProvTransID.'_4');//rebet
                    }catch(\Exception $e){//if bet exist
                        $msg = array(
                            "user" => $data['user'],
                            "balance" =>(int) round($client_details->balance,2),
                            "confirm" => "ok"
                        );
                        Helper::saveLog('BOTA REBET DUPLICATE RETURN', $this->provider_db_id, json_encode($msg), 'REBET FAILED');
                        return response($msg,200)
                        ->header('Content-Type', 'application/json');
                    }
                }
                
            }
            else{//if no bet history
                $refundRoundID = $data['detail']['shoeNo'].$data['detail']['gameNo'];
                $checkRefundCount = GameTransactionMDB::findBOTAGameExt($refundRoundID,'round_id',3,$client_details);
                if(count($checkRefundCount) > 1 && $checkRefundCount != 'false'){//if canceled 2 times
                    try{
                        $newProvTransID = $this->prefix.'R_'.$refundRoundID;//last bet idx
                        ProviderHelper::idenpotencyTable($newProvTransID.'_44R');//rebet
                    }catch(\Exception $e){//if bet exist
                        $msg = array(
                            "user" => $data['user'],
                            "balance" =>(int) round($client_details->balance,2),
                            "confirm" => "ok"
                        );
                        Helper::saveLog('BOTA REBET DUPLICATE RETURN', $this->provider_db_id, json_encode($msg), 'TRIPLE BET FAILED');
                        return response($msg,200)
                        ->header('Content-Type', 'application/json');
                    }
                }
                else{
                    try{
                        $newProvTransID = $refundRoundID;//last bet idx
                        ProviderHelper::idenpotencyTable($newProvTransID.'_4R');//rebet
                    }catch(\Exception $e){//if bet exist
                        $msg = array(
                            "user" => $data['user'],
                            "balance" =>(int) round($client_details->balance,2),
                            "confirm" => "ok"
                        );
                        Helper::saveLog('BOTA REBET DUPLICATE RETURN', $this->provider_db_id, json_encode($msg), 'REBET FAILED');
                        return response($msg,200)
                        ->header('Content-Type', 'application/json');
                    }
                }
                
            }
            $gamedetails = ProviderHelper::findGameDetails('game_code', $this->providerID, 'BOTA');
            $game = GameTransactionMDB::getGameTransactionByRoundId($data['detail']['shoeNo'].$data['detail']['gameNo'],$client_details);
            if($game == null){
                $bet_transaction = GameTransactionMDB::findGameTransactionDetails($newProvTransID, 'transaction_id', false, $client_details);
                $game_trans_id = ProviderHelper::idGenerate($client_details->connection_name, 1); // ID generator
                $bettransactionExtId = ProviderHelper::idGenerate($client_details->connection_name, 2);
                if($bet_transaction != "false"){//check if bet transaction is existing
                    $client_details->connection_name = $bet_transaction->connection_name;
                    $game_trans_id = $bet_transaction->game_trans_id;
                    $updateGameTransaction = [
                        'win' => 5,
                        'bet_amount' => $bet_transaction->bet_amount + round($data["price"],2),
                        'entry_id' => 1,
                        'trans_status' => 1
                    ];
                    GameTransactionMDB::updateGametransactionV2($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
                }
                else{
                    $gameTransactionData = array(
                        "provider_trans_id" => $newProvTransID,
                        "token_id" => $client_details->token_id,
                        "game_id" => $gamedetails->game_id,
                        "round_id" => $data['detail']['shoeNo'].$data['detail']['gameNo'],
                        "bet_amount" => round($data['price'],2),
                        "pay_amount" => 0,
                        "win" => 5,
                        "income" => 0,
                        "entry_id" => 1
                    );
                    GameTransactionMDB::createGametransactionV2($gameTransactionData,$game_trans_id,$client_details);
                }
                $bettransactionExt = array(
                    "game_trans_id" => $game_trans_id,
                    "provider_trans_id" => $newProvTransID,
                    "round_id" => $data['detail']['shoeNo'].$data['detail']['gameNo'],
                    "amount" => round($data['price'],2),
                    "game_transaction_type" => 1,
                    // "provider_request" => json_encode($data),
                );
                GameTransactionMDB::createGameTransactionExtV2($bettransactionExt, $bettransactionExtId,$client_details);
                $fund_extra_data = [
                    'provider_name' => $gamedetails->provider_name
                ]; 
                $client_response = ClientRequestHelper::fundTransfer($client_details,round($data["price"],2),$gamedetails->game_code,$gamedetails->game_name,$game_trans_id,$bettransactionExtId,'debit',false,$fund_extra_data);
                if(isset($client_response->fundtransferresponse->status->code)
                && $client_response->fundtransferresponse->status->code == "200"){
                    $balance = round($client_response->fundtransferresponse->balance, 2);
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    $response = array(
                        "user" => $data['user'],
                        "balance" =>(int) $balance,
                        "confirm" => "ok"
                    );
                    $createGameTransactionLog = [
                        "connection_name" => $client_details->connection_name,
                        "column" =>[
                            "game_trans_ext_id" => $bettransactionExtId,
                            "request" => json_encode($data),
                            "response" => json_encode($response),
                            "log_type" => "provider_details",
                            "transaction_detail" => "success",
                        ]
                    ];
                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
                    return response($msg, 200)->header('Content-type', 'application/json');
                }
            }
            $bettransactionExtId = ProviderHelper::idGenerate($client_details->connection_name, 2);
            $bettransactionExt = array(
                "game_trans_id" => $game->game_trans_id,
                "provider_trans_id" => $newProvTransID,
                "round_id" => $data['detail']['shoeNo'].$data['detail']['gameNo'],
                "amount" => round($data['price'],2),
                "game_transaction_type" => 1,
                // "provider_request" => json_encode($data),
            );
            GameTransactionMDB::createGameTransactionExtV2($bettransactionExt, $bettransactionExtId,$client_details);
            $updateGameTransaction = [
                'win' => 5,
                'pay_amount' => 0,
                'income' => 0,
                'entry_id' => 1,
                'trans_status' => 1
            ];
            GameTransactionMDB::updateGametransactionV2($updateGameTransaction, $game->game_trans_id, $client_details); 
            $client_response = ClientRequestHelper::fundtransfer($client_details,round($data["price"],2),$gamedetails->game_code,$gamedetails->game_name,$bettransactionExtId,$game->game_trans_id,"debit");
            if(isset($client_response->fundtransferresponse->status->code)
            && $client_response->fundtransferresponse->status->code == "200"){
                $balance = round($client_response->fundtransferresponse->balance, 2);
                ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                $msg = array(
                    "user" => $data['user'],
                    "balance" =>(int) $balance,
                    "confirm" => "ok"
                );
                $newBet = $game->bet_amount+$data["price"];
                $updateBet = [
                    "bet_amount" => round($newBet,2)
                ];
                // $dataToUpdate =[
                //     "mw_response" =>json_encode($response),
                // ];
                // GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$bettransactionExtId, $client_details);
                GameTransactionMDB::updateGametransactionV2($updateBet, $game->game_trans_id, $client_details); 
                $createGameTransactionLog = [
                    "connection_name" => $client_details->connection_name,
                    "column" =>[
                        "game_trans_ext_id" => $bettransactionExtId,
                        "request" => json_encode($data),
                        "response" => json_encode($response),
                        "log_type" => "provider_details",
                        "transaction_detail" => "success",
                    ]
                ];
                ProviderHelper::queTransactionLogs($createGameTransactionLog);
                return response($msg, 200)->header('Content-type', 'application/json');
            }
            elseif(isset($client_response->fundtransferresponse->status->code)
            && $client_response->fundtransferresponse->status->code == "402"){
                $msg = array(
                    "status" => [
                        "code" => $client_response->fundtransferresponse->status->code,
                        "stauts" => $client_response->fundtransferresponse->status->status,
                        "message" =>$client_response->fundtransferresponse->status->message,
                    ],
                    "balance" => round($client_response->fundtransferresponse->balance, 2),
                    "currencycode" => $client_response->fundtransferresponse->currencycode
                );//error response
                $response = [
                    "result_code" => "5",
                    "result_message" => "User Insufficient amount"
                ];
                try{
                    $datatosend = array(
                    "win" => 2
                    );
                    $createGameTransactionLog = [
                        "connection_name" => $client_details->connection_name,
                        "column" =>[
                            "game_trans_ext_id" => $bettransactionExtId,
                            "request" => json_encode($data),
                            "response" => json_encode($response),
                            "log_type" => "provider_details",
                            "transaction_detail" => "success",
                        ]
                    ];
                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
                    GameTransactionMDB::updateGametransactionV2($datatosend,$game->game_trans_id,$client_details);
                    // $updateData = array(
                    //     "mw_response" => json_encode($msg)
                    // );
                    // GameTransactionMDB::updateGametransactionEXT($updateData, $bettransactionExtId, $client_details);
                }catch(\Exception $e){
                Helper::savelog('Insuficient Bet(BOTA)', $this->provider_db_id, json_encode($e->getMessage(),$client_response->fundtransferresponse->status->message));
                }
                return response($response, 200)->header('Content-Type', 'application/json');
            }
        }
        else{
            $msg = array(
                "result_code" => "1",
                "result_message" => "(NoAccount)"
            );
            
            return response($msg, 200)->header('Content-Type', 'application/json');
        }
    }

    public function _newBetProcess($data, $client_details){
        Helper::saveLog('DOUBLEBET Processing', $this->provider_db_id, json_encode($data), 'DOUBLE Initialized!');
        if(isset($client_details)){
            $betDetails = BOTAHelper::getBettingList($client_details,$this->dateToday);
            if($betDetails->result_count != 0){//check if bet history is not null
                $betRoundID = $this->prefix.'_'.$betDetails->result_value[0]->c_idx;
                $myRoundID = $betDetails->result_value[0]->c_shoe_idx.$betDetails->result_value[0]->c_game_idx;
                $checkBetTransaction = GameTransactionMDB::findBOTAGameExt($betRoundID,'transaction_id',1,$client_details);
                if($checkBetTransaction != 'false' && $checkBetTransaction[0]->round_id == $myRoundID){//if bet 2 times
                    try{
                        $newProvTransID = $this->prefix.'D_'.$betDetails->result_value[0]->c_idx;//last bet idx
                        ProviderHelper::idenpotencyTable($newProvTransID.'_55');//rebet
                    }catch(\Exception $e){//if bet exist
                        $msg = array(
                            "user" => $data['user'],
                            "balance" =>(int) round($client_details->balance,2),
                            "confirm" => "ok"
                        );
                        Helper::saveLog('BOTA DOUBLE DUPLICATE RETURN', $this->provider_db_id, json_encode($msg), 'DOUBLE BET FAILED');
                        return response($msg,200)
                        ->header('Content-Type', 'application/json');
                    }
                }
                else{
                    try{
                        $newProvTransID = $this->prefix.'_'.$betDetails->result_value[0]->c_idx;//last bet idx
                        ProviderHelper::idenpotencyTable($newProvTransID.'_5');//rebet
                    }catch(\Exception $e){//if bet exist
                        $msg = array(
                            "user" => $data['user'],
                            "balance" =>(int) round($client_details->balance,2),
                            "confirm" => "ok"
                        );
                        Helper::saveLog('BOTA DOUBLE DUPLICATE RETURN', $this->provider_db_id, json_encode($msg), 'DOUBLE FAILED');
                        return response($msg,200)
                        ->header('Content-Type', 'application/json');
                    }
                }
                
            }
            else{//IF NO BET HISTORY
                $refundRoundID = $data['detail']['shoeNo'].$data['detail']['gameNo'];
                $checkRefundCount = GameTransactionMDB::findBOTAGameExt($refundRoundID,'round_id',3,$client_details);
                if(count($checkRefundCount) == 2){//if canceled 2 times
                    try{
                        $newProvTransID = $this->prefix.'D_'.$refundRoundID;//last bet idx
                        ProviderHelper::idenpotencyTable($newProvTransID.'_55D');//rebet
                    }catch(\Exception $e){//if bet exist
                        $msg = array(
                            "user" => $data['user'],
                            "balance" =>(int) round($client_details->balance,2),
                            "confirm" => "ok"
                        );
                        Helper::saveLog('BOTA DOUBLE DUPLICATE RETURN', $this->provider_db_id, json_encode($msg), 'DOUBLE BET FAILED');
                        return response($msg,200)
                        ->header('Content-Type', 'application/json');
                    }
                }
                else{
                    try{
                        $newProvTransID = $refundRoundID;//last bet idx
                        ProviderHelper::idenpotencyTable($newProvTransID.'_5D');//rebet
                    }catch(\Exception $e){//if bet exist
                        $msg = array(
                            "user" => $data['user'],
                            "balance" =>(int) round($client_details->balance,2),
                            "confirm" => "ok"
                        );
                        Helper::saveLog('BOTA DOUBLE DUPLICATE RETURN', $this->provider_db_id, json_encode($msg), 'DOUBLE FAILED');
                        return response($msg,200)
                        ->header('Content-Type', 'application/json');
                    }
                }
                
            }
            $gamedetails = ProviderHelper::findGameDetails('game_code', $this->providerID, 'BOTA');
            $game = GameTransactionMDB::getGameTransactionByRoundId($data['detail']['shoeNo'].$data['detail']['gameNo'],$client_details);
            if($game == null){
                $bet_transaction = GameTransactionMDB::findGameTransactionDetails($newProvTransID, 'transaction_id', false, $client_details);
                $game_trans_id = ProviderHelper::idGenerate($client_details->connection_name, 1); // ID generator
                $bettransactionExtId = ProviderHelper::idGenerate($client_details->connection_name, 2);
                if($bet_transaction != "false"){//check if bet transaction is existing
                    $client_details->connection_name = $bet_transaction->connection_name;
                    $game_trans_id = $bet_transaction->game_trans_id;
                    $updateGameTransaction = [
                        'win' => 5,
                        'bet_amount' => $bet_transaction->bet_amount + round($data["price"],2),
                        'entry_id' => 1,
                        'trans_status' => 1
                    ];
                    GameTransactionMDB::updateGametransactionV2($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
                }
                else{
                    $gameTransactionData = array(
                        "provider_trans_id" => $newProvTransID,
                        "token_id" => $client_details->token_id,
                        "game_id" => $gamedetails->game_id,
                        "round_id" => $data['detail']['shoeNo'].$data['detail']['gameNo'],
                        "bet_amount" => round($data['price'],2),
                        "pay_amount" => 0,
                        "win" => 5,
                        "income" => 0,
                        "entry_id" => 1
                    );
                    GameTransactionMDB::createGametransactionV2($gameTransactionData,$game_trans_id,$client_details);
                }
                $bettransactionExt = array(
                    "game_trans_id" => $game_trans_id,
                    "provider_trans_id" => $newProvTransID,
                    "round_id" => $data['detail']['shoeNo'].$data['detail']['gameNo'],
                    "amount" => round($data['price'],2),
                    "game_transaction_type" => 1,
                    // "provider_request" => json_encode($data),
                );
                GameTransactionMDB::createGameTransactionExtV2($bettransactionExt, $bettransactionExtId,$client_details);
                $fund_extra_data = [
                    'provider_name' => $gamedetails->provider_name
                ]; 
                $client_response = ClientRequestHelper::fundTransfer($client_details,round($data["price"],2),$gamedetails->game_code,$gamedetails->game_name,$game_trans_id,$bettransactionExtId,'debit',false,$fund_extra_data);
                if(isset($client_response->fundtransferresponse->status->code)
                && $client_response->fundtransferresponse->status->code == "200"){
                    $balance = round($client_response->fundtransferresponse->balance, 2);
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    $response = array(
                        "user" => $data['user'],
                        "balance" =>(int) $balance,
                        "confirm" => "ok"
                    );
                    $createGameTransactionLog = [
                        "connection_name" => $client_details->connection_name,
                        "column" =>[
                            "game_trans_ext_id" => $bettransactionExtId,
                            "request" => json_encode($data),
                            "response" => json_encode($response),
                            "log_type" => "provider_details",
                            "transaction_detail" => "success",
                        ]
                    ];
                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
                    return response($msg, 200)->header('Content-type', 'application/json');
                }
            }
            $bettransactionExtId = ProviderHelper::idGenerate($client_details->connection_name, 2);
            $bettransactionExt = array(
                "game_trans_id" => $game->game_trans_id,
                "provider_trans_id" => $newProvTransID,
                "round_id" => $data['detail']['shoeNo'].$data['detail']['gameNo'],
                "amount" => round($data['price'],2),
                "game_transaction_type" => 1,
                // "provider_request" => json_encode($data),
            );
            GameTransactionMDB::createGameTransactionExtV2($bettransactionExt, $bettransactionExtId,$client_details);
            $updateGameTransaction = [
                'win' => 5,
                'pay_amount' => 0,
                'income' => 0,
                'entry_id' => 1,
                'trans_status' => 1
            ];
            GameTransactionMDB::updateGametransactionV2($updateGameTransaction, $game->game_trans_id, $client_details); 
            $client_response = ClientRequestHelper::fundtransfer($client_details,round($data["price"],2),$gamedetails->game_code,$gamedetails->game_name,$bettransactionExtId,$game->game_trans_id,"debit");
            if(isset($client_response->fundtransferresponse->status->code)
            && $client_response->fundtransferresponse->status->code == "200"){
                $balance = round($client_response->fundtransferresponse->balance, 2);
                ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                $msg = array(
                    "user" => $data['user'],
                    "balance" =>(int) $balance,
                    "confirm" => "ok"
                );
                $newBet = $game->bet_amount+$data["price"];
                $updateBet = [
                    "bet_amount" => round($newBet,2)
                ];
                // $dataToUpdate =[
                //     "mw_response" =>json_encode($response),
                // ];
                // GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$bettransactionExtId, $client_details);
                GameTransactionMDB::updateGametransactionV2($updateBet, $game->game_trans_id, $client_details); 
                $createGameTransactionLog = [
                    "connection_name" => $client_details->connection_name,
                    "column" =>[
                        "game_trans_ext_id" => $bettransactionExtId,
                        "request" => json_encode($data),
                        "response" => json_encode($response),
                        "log_type" => "provider_details",
                        "transaction_detail" => "success",
                    ]
                ];
                ProviderHelper::queTransactionLogs($createGameTransactionLog);
                return response($msg, 200)->header('Content-type', 'application/json');
            }
            elseif(isset($client_response->fundtransferresponse->status->code)
            && $client_response->fundtransferresponse->status->code == "402"){
                $msg = array(
                    "status" => [
                        "code" => $client_response->fundtransferresponse->status->code,
                        "stauts" => $client_response->fundtransferresponse->status->status,
                        "message" =>$client_response->fundtransferresponse->status->message,
                    ],
                    "balance" => round($client_response->fundtransferresponse->balance, 2),
                    "currencycode" => $client_response->fundtransferresponse->currencycode
                );//error response
                $response = [
                    "result_code" => "5",
                    "result_message" => "User Insufficient amount"
                ];
                try{
                    $datatosend = array(
                    "win" => 2
                    );
                    $createGameTransactionLog = [
                        "connection_name" => $client_details->connection_name,
                        "column" =>[
                            "game_trans_ext_id" => $bettransactionExtId,
                            "request" => json_encode($data),
                            "response" => json_encode($response),
                            "log_type" => "provider_details",
                            "transaction_detail" => "failed",
                        ]
                    ];
                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
                    GameTransactionMDB::updateGametransactionV2($datatosend,$game->game_trans_id,$client_details);
                    // $updateData = array(
                    //     "mw_response" => json_encode($msg)
                    // );
                    // GameTransactionMDB::updateGametransactionEXT($updateData, $bettransactionExtId, $client_details);
                }catch(\Exception $e){
                Helper::savelog('Insuficient Bet(BOTA)', $this->provider_db_id, json_encode($e->getMessage(),$client_response->fundtransferresponse->status->message));
                }
                return response($response, 200)->header('Content-Type', 'application/json');
            }
        }
        else{
            $msg = array(
                "result_code" => "1",
                "result_message" => "(NoAccount)"
            );
            
            return response($msg, 200)->header('Content-Type', 'application/json');
        }
    }

    public function _winProcess($data,$client_details){
        Helper::saveLog('Win Processing', $this->provider_db_id, json_encode($data), 'Win Initialized!');
        // $response = array(
        //                     "user" => $data['user'],
        //                     "balance" =>(int) $client_details->balance,
        //                     "confirm" => "ok"
        //                 );
        //                 Helper::saveLog('BOTA Success fundtransfer', $this->provider_db_id, json_encode($response), "HIT!");
        //                 return response($response,200)
        //                     ->header('Content-Type', 'application/json');
        if(isset($client_details)){
            try{
                ProviderHelper::idenpotencyTable($this->prefix.$data['detail']['gameNo'].'_'.$data['idx'].'_2');
            }catch(\Exception $e){
                $msg = array(
                    "user" => $data['user'],
                    "balance" =>(int) round($client_details->balance,2),
                    "confirm" => "ok"
                );
                Helper::saveLog('BOTA WIN DUPLICATE RETURN', $this->provider_db_id, json_encode($msg), 'WIN DUPE');
                return response($msg,200)
                ->header('Content-Type', 'application/json');
            }
            $gamedetails = ProviderHelper::findGameDetails('game_code', $this->providerID, 'BOTA');
            $game = GameTransactionMDB::getGameTransactionByRoundId($data['detail']['shoeNo'].$data['detail']['gameNo'],$client_details);
            if($game == null){
                $bet_transaction = GameTransactionMDB::findGameTransactionDetails($this->prefix.'_'.$data['detail']['shoeNo'].$data['detail']['gameNo'], 'transaction_id', false, $client_details);
                $game_trans_id = ProviderHelper::idGenerate($client_details->connection_name, 1); // ID generator
                $bettransactionExtId = ProviderHelper::idGenerate($client_details->connection_name, 2);
                if($bet_transaction != "false"){//check if bet transaction is existing
                    $client_details->connection_name = $bet_transaction->connection_name;
                    $game_trans_id = $bet_transaction->game_trans_id;
                    $updateGameTransaction = [
                        'win' => 5,
                        'bet_amount' => $bet_transaction->bet_amount + round($data["price"],2),
                        'entry_id' => 1,
                        'trans_status' => 1
                    ];
                    GameTransactionMDB::updateGametransactionV2($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
                }
                else{
                    $gameTransactionData = array(
                        "provider_trans_id" => $this->prefix.'_'.$data['detail']['shoeNo'].$data['detail']['gameNo'],
                        "token_id" => $client_details->token_id,
                        "game_id" => $gamedetails->game_id,
                        "round_id" => $data['detail']['shoeNo'].$data['detail']['gameNo'],
                        "bet_amount" => round($data['price'],2),
                        "pay_amount" => 0,
                        "win" => 5,
                        "income" => 0,
                        "entry_id" => 1
                    );
                    GameTransactionMDB::createGametransactionV2($gameTransactionData,$game_trans_id,$client_details);
                }
                $bettransactionExt = array(
                    "game_trans_id" => $game_trans_id,
                    "provider_trans_id" => $this->prefix.'_'.$data['detail']['shoeNo'].$data['detail']['gameNo'],
                    "round_id" => $data['detail']['shoeNo'].$data['detail']['gameNo'],
                    "amount" => round($data['price'],2),
                    "game_transaction_type" => 1,
                    // "provider_request" => json_encode($data),
                );
                GameTransactionMDB::createGameTransactionExtV2($bettransactionExt, $bettransactionExtId,$client_details);
                $fund_extra_data = [
                    'provider_name' => $gamedetails->provider_name
                ]; 
                $client_response = ClientRequestHelper::fundTransfer($client_details,round($data["price"],2),$gamedetails->game_code,$gamedetails->game_name,$game_trans_id,$bettransactionExtId,'debit',false,$fund_extra_data);
                if(isset($client_response->fundtransferresponse->status->code)
                && $client_response->fundtransferresponse->status->code == "200"){
                    $balance = round($client_response->fundtransferresponse->balance, 2);
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    $response = array(
                        "user" => $data['user'],
                        "balance" =>(int) $balance,
                        "confirm" => "ok"
                    );
                    $createGameTransactionLog = [
                        "connection_name" => $client_details->connection_name,
                        "column" =>[
                            "game_trans_ext_id" => $bettransactionExtId,
                            "request" => json_encode($data),
                            "response" => json_encode($response),
                            "log_type" => "provider_details",
                            "transaction_detail" => "success",
                        ]
                    ];
                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
                    return response($msg, 200)->header('Content-type', 'application/json');
                }
            }
            $win_or_lost = $data["price"] == 0 && $game->pay_amount == 0 ? 0 : 1;
            $createGametransaction = array(
                "win" => 5,
                "pay_amount" =>$game->pay_amount+round($data["price"],2),
                "income" =>$game->bet_amount - round($data["price"],2),
                "entry_id" =>round($data["price"],2) == 0 && $game->pay_amount == 0 ? 1 : 2,
            );
            GameTransactionMDB::updateGametransactionV2($createGametransaction,$game->game_trans_id,$client_details);
            $winTransactionExtID = ProviderHelper::idGenerate($client_details->connection_name, 2);
            $winTransactionExt = array(
                "game_trans_id" => $game->game_trans_id,
                "provider_trans_id"=>$data['idx'],
                "round_id"=>$data['detail']['shoeNo'].$data['detail']['gameNo'],
                "amount"=>$data['price'],
                "game_transaction_type"=> 2,
                // "provider_request" => json_encode($data),
            );
           GameTransactionMDB::createGameTransactionExtV2($winTransactionExt, $winTransactionExtID,$client_details);
            $updateGameTransaction = [
                'win' => $win_or_lost,
                'pay_amount' => round($data["price"],2),
                'income' => $game->bet_amount - round($data["price"],2),
                'entry_id' => round($data["price"],2) == 0 && $game->pay_amount == 0 ? 1 : 2,
                'trans_status' => 2
            ];
            GameTransactionMDB::updateGametransactionV2($updateGameTransaction, $game->game_trans_id, $client_details);
            $client_response = ClientRequestHelper::fundtransfer($client_details,round($data["price"],2),$gamedetails->game_code,$gamedetails->game_name,$winTransactionExtID,$game->game_trans_id,'credit');
            if(isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "200"){
                $balance = round($client_response->fundtransferresponse->balance,2);
                ProviderHelper::_insertOrUpdate($client_details->token_id,$balance,);
                $response = array(
                    "user" => $data['user'],
                    "balance" =>(int) $balance,
                    "confirm" => "ok"
                );
                // $dataToUpdate =[
                //     "mw_response" =>json_encode($response),
                // ];
                // GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$winTransactionExtID, $client_details);
                $createGameTransactionLog = [
                    "connection_name" => $client_details->connection_name,
                    "column" =>[
                        "game_trans_ext_id" => $winTransactionExtID,
                        "request" => json_encode($data),
                        "response" => json_encode($response),
                        "log_type" => "provider_details",
                        "transaction_detail" => "success",
                    ]
                ];
                ProviderHelper::queTransactionLogs($createGameTransactionLog);
                Helper::saveLog('BOTA Success fundtransfer', $this->provider_db_id, json_encode($response), "HIT!");
                return response($response,200)
                    ->header('Content-Type', 'application/json');
            }
            elseif(isset($client_response->fundtransferresponse->status->code)
            && $client_response->fundtransferresponse->status->code == "402"){
                $msg = array(
                    "status" => [
                        "code" => $client_response->fundtransferresponse->status->code,
                        "status" => $client_response->fundtransferresponse->status->status,
                        "message" =>$client_response->fundtransferresponse->status->message,
                    ],
                    "balance" => round($client_response->fundtransferresponse->balance, 2),
                    "currencycode" => $client_response->fundtransferresponse->currencycode
                );
                $response = [
                    "result_code" => "5",
                    "result_message" => "User Insufficient amount"
                ];
                try{
                    $datatosend = array(
                    "win" => 2
                    );
                    $createGameTransactionLog = [
                        "connection_name" => $client_details->connection_name,
                        "column" =>[
                            "game_trans_ext_id" => $winTransactionExtID,
                            "request" => json_encode($data),
                            "response" => json_encode($response),
                            "log_type" => "provider_details",
                            "transaction_detail" => "failed",
                        ]
                    ];
                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
                    GameTransactionMDB::updateGametransactionV2($datatosend,$game->game_trans_id,$client_details);
                    // $updateData = array(
                    //     "mw_response" => json_encode($msg)
                    // );
                    // GameTransactionMDB::updateGametransactionEXT($updateData, $winTransactionExtID, $client_details);
                }catch(\Exception $e){
                Helper::savelog('WIN FAILED(BOTA)', $this->provider_db_id, json_encode($e->getMessage(),$client_response->fundtransferresponse->status->message));
                }
                return response($response, 200)->header('Content-Type', 'application/json');
            }
        }
        else {
            $msg = array(
                "result_code" => "1",
                "result_message" => "(NoAccount)"
            );
            
            return response($msg, 200)->header('Content-Type', 'application/json');
        }
    }

    public function _cancelProcess($data,$client_details){
        Helper::saveLog('Cancel Processing', $this->provider_db_id, json_encode($data), 'Cancel Initialized!');
        if(isset($client_details)){
            try{
                ProviderHelper::idenpotencyTable($this->prefix.$data['detail']['gameNo'].'_'.$data['idx'].'_3');
            }catch(\Exception $e){
                $msg = array(
                    "user" => $data['user'],
                    "balance" =>(int) round($client_details->balance,2),
                    "confirm" => "ok"
                );
                Helper::saveLog('BOTA CANCEL DUPLICATE RETURN', $this->provider_db_id, json_encode($msg), 'CANCEL DUPE');
                return response($msg,200)
                ->header('Content-Type', 'application/json');
            }
            $gameExt = GameTransactionMDB::findGameTransactionDetails($this->prefix.'_'.$data['detail']['shoeNo'].$data['detail']['gameNo'], 'transaction_id',false, $client_details);
            Helper::saveLog('Cancel Processing', $this->provider_db_id, json_encode($gameExt), 'GAMEEXT Initialized!');
            $gamedetails = ProviderHelper::findGameDetails('game_code', $this->providerID, 'BOTA');
            if($gameExt==null){
                $msg = array(
                    "user" => $data['user'],
                    "balance" =>(int) round($client_details->balance,2),
                    "confirm" => "ok"
                );
                Helper::saveLog('BOTA CANCEL DUPLICATE REFUND', $this->provider_db_id, json_encode($msg), 'REFUND ALREADY EXIST!');
                return response($msg,200)
                ->header('Content-Type', 'application/json');
            }
            // $data['detail']['shoeNo'].$data['detail']['gameNo'] = $gameExt->round_id;
            $updateGameTransaction = array(
                "win" => 4,
                "pay_amount" => round($data['price'],2),
                "income" => $gameExt->bet_amount - round($data['price'],2),
                "entry_id" => 2
            );
            GameTransactionMDB::updateGametransactionV2($updateGameTransaction, $gameExt->game_trans_id, $client_details);
            $game_trans_ext_id = ProviderHelper::idGenerate($client_details->connection_name, 2);
            $refundgametransExt = array(
                "game_trans_id" => $gameExt->game_trans_id,
                "provider_trans_id" => $data['idx'],
                "round_id"=>$data['detail']['shoeNo'].$data['detail']['gameNo'],
                "amount" => round($data['price'],2),
                "game_transaction_type" => 3,
                // "provider_request" => json_encode($data),
            );
            GameTransactionMDB::createGameTransactionExtV2($refundgametransExt, $game_trans_ext_id,$client_details);
            $client_response = ClientRequestHelper::fundTransfer($client_details,round($data["price"],2),$gamedetails->game_code,$gamedetails->game_name,$game_trans_ext_id,$gameExt->game_trans_id,'credit',true);
            Helper::saveLog('BOTA CANCEL HIT FUNDTRANSFER', $this->provider_db_id, json_encode($gameExt), $game_trans_ext_id);
            if(isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "200"){
                $balance = round($client_response->fundtransferresponse->balance,2);
                $client_details->balance = $balance;
                ProviderHelper::_insertOrUpdate($client_details->token_id,$balance,);
                $response = array(
                    "user" => $data['user'],
                    "balance" =>(int) $balance,
                    "confirm" => "ok"
                );
                $newBet = $gameExt->bet_amount-$data["price"];
                $updateBet = [
                    "bet_amount" => round($newBet,2)
                ];
                // $dataToUpdate =[
                //     "mw_response" =>json_encode($response),
                // ];
                // GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$game_trans_ext_id, $client_details);
                GameTransactionMDB::updateGametransactionV2($updateBet, $gameExt->game_trans_id, $client_details); 
                $createGameTransactionLog = [
                    "connection_name" => $client_details->connection_name,
                    "column" =>[
                        "game_trans_ext_id" => $game_trans_ext_id,
                        "request" => json_encode($data),
                        "response" => json_encode($response),
                        "log_type" => "provider_details",
                        "transaction_detail" => "success",
                    ]
                ];
                ProviderHelper::queTransactionLogs($createGameTransactionLog);
                Helper::saveLog('BOTA Success fundtransfer', $this->provider_db_id, json_encode($response), "(Cancel)HIT!");
                return response($response,200)
                    ->header('Content-Type', 'application/json');
            }
            elseif(isset($client_response->fundtransferresponse->status->code)
            && $client_response->fundtransferresponse->status->code == "402"){
                $msg = array(
                    "status" => [
                        "code" => $client_response->fundtransferresponse->status->code,
                        "status" => $client_response->fundtransferresponse->status->status,
                        "message" =>$client_response->fundtransferresponse->status->message,
                    ],
                    "balance" => round($client_response->fundtransferresponse->balance, 2),
                    "currencycode" => $client_response->fundtransferresponse->currencycode
                );
                $response = [
                    "result_code" => "5",
                    "result_message" => "User Insufficient amount"
                ];
                try{
                    $datatosend = array(
                    "win" => 2
                    );
                    $createGameTransactionLog = [
                        "connection_name" => $client_details->connection_name,
                        "column" =>[
                            "game_trans_ext_id" => $game_trans_ext_id,
                            "request" => json_encode($data),
                            "response" => json_encode($response),
                            "log_type" => "provider_details",
                            "transaction_detail" => "failed",
                        ]
                    ];
                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
                    GameTransactionMDB::updateGametransactionV2($datatosend,$gameExt->game_trans_id,$client_details);
                    // $updateData = array(
                    //     "mw_response" => json_encode($msg)
                    // );
                    // GameTransactionMDB::updateGametransactionEXT($updateData, $game_trans_ext_id, $client_details);
                }catch(\Exception $e){
                    Helper::savelog('REFUND FAILED(BOTA)', $this->provider_db_id, json_encode($e->getMessage(),$client_response->fundtransferresponse->status->message));
                }
                return response($response, 200)->header('Content-Type', 'application/json');
            }
        }
        else {
            $msg = array(
                "result_code" => "1",
                "result_message" => "(NoAccount)"
            );
            
            return response($msg, 200)->header('Content-Type', 'application/json');
        }
    }
}
?>