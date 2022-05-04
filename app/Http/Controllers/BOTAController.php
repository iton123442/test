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

class BOTAController extends Controller{

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
        }$msg = [
            "result_code" => "1",
            "result_msg" =>(string) "(no account)"
        ];
        return response($msg,200)->header('Content-Type', 'application/json');
        Helper::saveLog('BOTA TG FOUND CLIENT DETAILS',$this->provider_db_id, json_encode($msg), $data);
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
                $cancelBetRoundID = $data['detail']['shoeNo'].$data['detail']['shoeNo'].$data['detail']['gameNo'];
                $cancelbetExt = GameTransactionMDB::findGameExt($cancelBetRoundID,false,'round_id',$client_details);
                if($cancelbetExt == false){
                    $msg = array(
                        "user" => $data['user'],
                        "balance" =>(int) round($client_details->balance,2),
                        "confirm" => "ok"
                    );
                    Helper::saveLog('BOTA BET DUPLICATE RETURN', $this->provider_db_id, json_encode($msg), 'BET FAILED');
                    return response($msg,200)
                    ->header('Content-Type', 'application/json');
                }
                else{
                    $result = $this->_reBetProcess($data,$client_details);
                    Helper::saveLog('BOTA REBET',$this->provider_db_id, json_encode($result), 'REBET HIT');
                    return $result;
                }
            }
            $gamedetails = ProviderHelper::findGameDetails('game_code', $this->providerID, 'BOTA');
            $bet_transaction = GameTransactionMDB::findGameTransactionDetails($this->prefix.'_'.$data['detail']['shoeNo'].$data['detail']['gameNo'], 'transaction_id', false, $client_details);
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
            $game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
            }
            $bettransactionExt = array(
                "game_trans_id" => $game_trans_id,
                "provider_trans_id" => $this->prefix.'_'.$data['detail']['shoeNo'].$data['detail']['gameNo'],
                "round_id" => $data['detail']['shoeNo'].$data['detail']['gameNo'],
                "amount" => round($data['price'],2),
                "game_transaction_type" => 1,
                "provider_request" => json_encode($data),
            );
            $bettransactionExtId = GameTransactionMDB::createGameTransactionExt($bettransactionExt, $client_details);
            $fund_extra_data = [
                'provider_name' => $gamedetails->provider_name
            ]; 
            $client_response = ClientRequestHelper::fundTransfer($client_details,round($data["price"],2),$gamedetails->game_code,$gamedetails->game_name,$game_trans_id,$bettransactionExtId,"debit",false,$fund_extra_data);
            if(isset($client_response->fundtransferresponse->status->code)
            && $client_response->fundtransferresponse->status->code == "200"){
                $balance = round($client_response->fundtransferresponse->balance, 2);
                ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                $msg = array(
                    "user" => $data['user'],
                    "balance" =>(int) $balance,
                    "confirm" => "ok"
                );
                $updateData = array(
                    "mw_response" => json_encode($msg)
                );
                GameTransactionMDB::updateGametransactionEXT($updateData,$bettransactionExtId, $client_details);
                return response($msg, 200)->header('Content-type', 'application/json');
            }
            elseif(isset($client_response->fundtransferresponse->status->code)
            && $client_response->fundtransferresponse->status->code == "402"){
                $response = array(
                    "status" => [
                        "code" => $client_response->fundtransferresponse->status->code,
                        "stauts" => $client_response->fundtransferresponse->status->status,
                        "message" =>$client_response->fundtransferresponse->status->message,
                    ],
                    "balance" => round($client_response->fundtransferresponse->balance, 2),
                    "currencycode" => $client_response->fundtransferresponse->currencycode
                );//error response
                try{
                    $datatosend = array(
                    "win" => 2
                    );
                    GameTransactionMDB::updateGametransaction($datatosend,$game_trans_id,$client_details);
                    $updateData = array(
                        "mw_response" => json_encode($response)
                    );
                    GameTransactionMDB::updateGametransactionEXT($updateData, $bettransactionExtId, $client_details);
                }catch(\Exception $e){
                Helper::savelog('Insuficient Bet(BOTA)', $this->provider_db_id, json_encode($e->getMessage(),$client_response->fundtransferresponse->status->message));
                }
                return response($response, 200)->header('Content-Type', 'application/json');
            }
        }
    }

    public function _reBetProcess($data, $client_details){
        Helper::saveLog('REBET Processing', $this->provider_db_id, json_encode($data), 'REBET Initialized!');
        if(isset($client_details)){
            $betDetails = BOTAHelper::getBettingList($client_details,$this->dateToday);
            if($betDetails->result_count != 0){
                $newProvTransID = $this->prefix.'_'.$betDetails->result_value[0]->c_idx;
                try{
                    Helper::saveLog('IDEMPOTENT BOTA', $this->provider_db_id, json_encode($newProvTransID), 'IDEMPOTENCY HIT!');
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
                $gamedetails = ProviderHelper::findGameDetails('game_code', $this->providerID, 'BOTA');
                $game = GameTransactionMDB::getGameTransactionByRoundId($data['detail']['shoeNo'].$data['detail']['gameNo'],$client_details);
                if($game == null){
                    $bet_transaction = GameTransactionMDB::findGameTransactionDetails($newProvTransID, 'transaction_id', false, $client_details);
                    if($bet_transaction != "false"){//check if bet transaction is existing
                        $client_details->connection_name = $bet_transaction->connection_name;
                        $game_trans_id = $bet_transaction->game_trans_id;
                        $updateGameTransaction = [
                            'win' => 5,
                            'bet_amount' => $bet_transaction->bet_amount + round($data["price"],2),
                            'entry_id' => 1,
                            'trans_status' => 1
                        ];
                        GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
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
                        GameTransactionMDB::createGametransaction($gameTransactionData,$client_details);
                    }
                    $bettransactionExt = array(
                        "game_trans_id" => $game_trans_id,
                        "provider_trans_id" => $newProvTransID,
                        "round_id" => $data['detail']['shoeNo'].$data['detail']['gameNo'],
                        "amount" => round($data['price'],2),
                        "game_transaction_type" => 1,
                        "provider_request" => json_encode($data),
                    );
                    $bettransactionExtId = GameTransactionMDB::createGameTransactionExt($bettransactionExt, $client_details);
                    $fund_extra_data = [
                        'provider_name' => $gamedetails->provider_name
                    ]; 
                    $client_response = ClientRequestHelper::fundTransfer($client_details,round($data["price"],2),$gamedetails->game_code,$gamedetails->game_name,$game_trans_id,$bettransactionExtId,"debit",false,$fund_extra_data);
                    if(isset($client_response->fundtransferresponse->status->code)
                    && $client_response->fundtransferresponse->status->code == "200"){
                        $balance = round($client_response->fundtransferresponse->balance, 2);
                        ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                        $msg = array(
                            "user" => $data['user'],
                            "balance" =>(int) $balance,
                            "confirm" => "ok"
                        );
                        $updateData = array(
                            "mw_response" => json_encode($msg)
                        );
                        GameTransactionMDB::updateGametransactionEXT($updateData,$bettransactionExtId, $client_details);
                        return response($msg, 200)->header('Content-type', 'application/json');
                    }
                }
                $response = array(
                    "user" => $data['user'],
                    "balance" =>(int) round($client_details->balance,2),
                    "confirm" => "ok"
                );
                $bettransactionExt = array(
                    "game_trans_id" => $game->game_trans_id,
                    "provider_trans_id" => $newProvTransID,
                    "round_id" => $data['detail']['shoeNo'].$data['detail']['gameNo'],
                    "amount" => round($data['price'],2),
                    "game_transaction_type" => 1,
                    "provider_request" => json_encode($data),
                );
                $bettransactionExtId = GameTransactionMDB::createGameTransactionExt($bettransactionExt, $client_details);
                $action_payload = [
                    "type" => "custom", #genreral,custom :D # REQUIRED!
                    "custom" => [
                        "provider" => 'BOTA',
                        "isUpdate" => false,
                        "game_transaction_ext_id" => $bettransactionExtId,
                        "client_connection_name" => $client_details->connection_name
                    ],
                    "provider" => [
                        "provider_request" => $data, #R
                        "provider_trans_id"=>$newProvTransID, #R
                        "provider_round_id"=>$data['detail']['shoeNo'].$data['detail']['gameNo'], #R
                        'provider_name' => $gamedetails->provider_name
                    ],
                    "mwapi" => [
                        "roundId"=>$game->game_trans_id, #R
                        "type"=>1, #R
                        "game_id" => $gamedetails->game_id, #R
                        "player_id" => $client_details->player_id, #R
                        "mw_response" => $response, #R
                    ]
                ];
                $updateGameTransaction = [
                    'win' => 5,
                    'pay_amount' => 0,
                    'income' => 0,
                    'entry_id' => 1,
                    'trans_status' => 1
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $game->game_trans_id, $client_details); 
                $client_response = ClientRequestHelper::fundTransfer_TG($client_details,round($data["price"],2),$gamedetails->game_code,$gamedetails->game_name,$game->game_trans_id,"debit",false,$action_payload);
                if(isset($client_response->fundtransferresponse->status->code)
                && $client_response->fundtransferresponse->status->code == "200"){
                    $balance = round($client_response->fundtransferresponse->balance, 2);
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    $msg = array(
                        "user" => $data['user'],
                        "balance" =>(int) $balance,
                        "confirm" => "ok"
                    );
                    $updateData = array(
                        "mw_response" => json_encode($msg)
                    );
                    GameTransactionMDB::updateGametransactionEXT($updateData,$bettransactionExtId, $client_details);
                    return response($msg, 200)->header('Content-type', 'application/json');
                }
                elseif(isset($client_response->fundtransferresponse->status->code)
                && $client_response->fundtransferresponse->status->code == "402"){
                    $response = array(
                        "status" => [
                            "code" => $client_response->fundtransferresponse->status->code,
                            "stauts" => $client_response->fundtransferresponse->status->status,
                            "message" =>$client_response->fundtransferresponse->status->message,
                        ],
                        "balance" => round($client_response->fundtransferresponse->balance, 2),
                        "currencycode" => $client_response->fundtransferresponse->currencycode
                    );//error response
                    try{
                        $datatosend = array(
                        "win" => 2
                        );
                        GameTransactionMDB::updateGametransaction($datatosend,$game_trans_id,$client_details);
                        $updateData = array(
                            "mw_response" => json_encode($response)
                        );
                        GameTransactionMDB::updateGametransactionEXT($updateData, $bettransactionExtId, $client_details);
                    }catch(\Exception $e){
                    // Helper::savelog('Insuficient Bet(BOTA)', $this->provider_db_id, json_encode($e->getMessage(),$client_response->fundtransferresponse->status->message));
                    }
                    return response($response, 200)->header('Content-Type', 'application/json');
                }
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
                if($bet_transaction != "false"){//check if bet transaction is existing
                    $client_details->connection_name = $bet_transaction->connection_name;
                    $game_trans_id = $bet_transaction->game_trans_id;
                    $updateGameTransaction = [
                        'win' => 5,
                        'bet_amount' => $bet_transaction->bet_amount + round($data["price"],2),
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
                    GameTransactionMDB::createGametransaction($gameTransactionData,$client_details);
                }
                $bettransactionExt = array(
                    "game_trans_id" => $game_trans_id,
                    "provider_trans_id" => $this->prefix.'_'.$data['detail']['shoeNo'].$data['detail']['gameNo'],
                    "round_id" => $data['detail']['shoeNo'].$data['detail']['gameNo'],
                    "amount" => round($data['price'],2),
                    "game_transaction_type" => 1,
                    "provider_request" => json_encode($data),
                );
                $bettransactionExtId = GameTransactionMDB::createGameTransactionExt($bettransactionExt, $client_details);
                $fund_extra_data = [
                    'provider_name' => $gamedetails->provider_name
                ]; 
                $client_response = ClientRequestHelper::fundTransfer($client_details,round($data["price"],2),$gamedetails->game_code,$gamedetails->game_name,$game_trans_id,$bettransactionExtId,"debit",false,$fund_extra_data);
                if(isset($client_response->fundtransferresponse->status->code)
                && $client_response->fundtransferresponse->status->code == "200"){
                    $balance = round($client_response->fundtransferresponse->balance, 2);
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    $msg = array(
                        "user" => $data['user'],
                        "balance" =>(int) $balance,
                        "confirm" => "ok"
                    );
                    $updateData = array(
                        "mw_response" => json_encode($msg)
                    );
                    GameTransactionMDB::updateGametransactionEXT($updateData,$bettransactionExtId, $client_details);
                    return response($msg, 200)->header('Content-type', 'application/json');
                }
            }
            $win_or_lost = $data["price"] == 0 && $game->pay_amount == 0 ? 0 : 1;
            $createGametransaction = array(
                "win" => 5,
                "pay_amount" =>$game->pay_amount+round($data["price"],2),
                "income" =>$game->income - round($data["price"],2),
                "entry_id" =>round($data["price"],2) == 0 && $game->pay_amount == 0 ? 1 : 2,
            );
            GameTransactionMDB::updateGametransaction($createGametransaction,$game->game_trans_id,$client_details);
            //for game Extension
            $response = array(
                "user" => $data['user'],
                "balance" =>(int) round($client_details->balance,2),
                "confirm" => "ok"
            );
            $winTransactionExt = array(
                "game_trans_id" => $game->game_trans_id,
                "provider_trans_id"=>$data['idx'],
                "round_id"=>$data['detail']['shoeNo'].$data['detail']['gameNo'],
                "amount"=>$data['price'],
                "game_transaction_type"=> 2,
                "provider_request"=> json_encode($data),
                "mw_response"=>json_encode($response),
            );
            $winTransactionExtID = GameTransactionMDB::createGameTransactionExt($winTransactionExt, $client_details);
            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "provider" => 'BOTA',
                    "isUpdate" => false,
                    "game_transaction_ext_id" => $winTransactionExtID,
                    "client_connection_name" => $client_details->connection_name,
                    "win_or_lost" => $win_or_lost,
                ],
                "provider" => [
                    "provider_request" => $data, #R
                    "provider_trans_id"=>$data['idx'], #R
                    "provider_round_id"=>$data['detail']['shoeNo'].$data['detail']['gameNo'], #R
                    'provider_name' => $gamedetails->provider_name
                ],
                "mwapi" => [
                    "roundId"=>$game->game_trans_id, #R
                    "type"=>2, #R
                    "game_id" => $gamedetails->game_id, #R
                    "player_id" => $client_details->player_id, #R
                    "mw_response" => $response, #R
                ]
            ];
            $updateGameTransaction = [
                'win' => $win_or_lost,
                'pay_amount' => round($data["price"],2),
                'income' => $game->income - round($data["price"],2),
                'entry_id' => round($data["price"],2) == 0 && $game->pay_amount == 0 ? 1 : 2,
                'trans_status' => 2
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game->game_trans_id, $client_details);
            Helper::savelog('CreateGameTransactionExt(BOTA)', $this->provider_db_id, json_encode($action_payload),'EXT HIT');
            $client_response = ClientRequestHelper::fundTransfer_TG($client_details,round($data["price"],2),$gamedetails->game_code,$gamedetails->game_name,$game->game_trans_id,'credit',false,$action_payload);
            if(isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "200"){
                $balance = round($client_response->fundtransferresponse->balance,2);
                ProviderHelper::_insertOrUpdate($client_details->token_id,$balance,);
                $response = array(
                    "user" => $data['user'],
                    "balance" =>(int) $balance,
                    "confirm" => "ok"
                );
                $updateData = array(
                    "mw_response" => json_encode($response)
                );
                GameTransactionMDB::updateGametransactionEXT($updateData,$winTransactionExtID, $client_details);
                Helper::saveLog('BOTA Success fundtransfer', $this->provider_db_id, json_encode($response), "HIT!");
                return response($response,200)
                    ->header('Content-Type', 'application/json');
            }
            elseif(isset($client_response->fundtransferresponse->status->code)
            && $client_response->fundtransferresponse->status->code == "402"){
                $response = array(
                    "status" => [
                        "code" => $client_response->fundtransferresponse->status->code,
                        "status" => $client_response->fundtransferresponse->status->status,
                        "message" =>$client_response->fundtransferresponse->status->message,
                    ],
                    "balance" => round($client_response->fundtransferresponse->balance, 2),
                    "currencycode" => $client_response->fundtransferresponse->currencycode
                );
                try{
                    $datatosend = array(
                    "win" => 2
                    );
                    GameTransactionMDB::updateGametransaction($datatosend,$game_trans_id,$client_details);
                    $updateData = array(
                        "mw_response" => json_encode($response)
                    );
                    GameTransactionMDB::updateGametransactionEXT($updateData, $bettransactionExtId, $client_details);
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
            $gameExt = GameTransactionMDB::getGameTransactionDataByProviderTransactionIdAndEntryType($this->prefix.'_'.$data['detail']['shoeNo'].$data['detail']['gameNo'], 1, $client_details);
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
            $data['detail']['shoeNo'].$data['detail']['gameNo'] = $gameExt->round_id;
            $updateGameTransaction = array(
                "win" => 4,
                "pay_amount" => round($data['price'], 2),
                "income" => $gameExt->amount - round($data['price'], 2),
                "entry_id" => 2
            );
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $gameExt->game_trans_id, $client_details);
            $refundgametransExt = array(
                "game_trans_id" => $gameExt->game_trans_id,
                "provider_trans_id" => $data['idx'],
                "round_id"=>$data['detail']['shoeNo'].$data['detail']['gameNo'],
                "amount" => round($data['price'], 2),
                "game_transaction_type" => 3,
                "provider_request" => json_encode($data),
            );
            $refundgametransExtID = GameTransactionMDB::createGameTransactionExt($refundgametransExt, $client_details);
            $response = array(
                "user" => $data['user'],
                "balance" =>(int) round($client_details->balance,2),
                "confirm" => "ok"
            );
            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "provider" => 'BOTA',
                    "game_transaction_ext_id" => $refundgametransExtID,
                    "client_connection_name" => $client_details->connection_name,
                ],
                "provider" => [
                    "provider_request" => $data, #R
                    "provider_trans_id"=>$data['idx'], #R
                    "provider_round_id"=>$data['detail']['shoeNo'].$data['detail']['gameNo'], #R
                    'provider_name' => $gamedetails->provider_name
                ],
                "mwapi" => [
                    "roundId"=>$gameExt->game_trans_id, #R
                    "type"=>3, #R
                    "game_id" => $gamedetails->game_id, #R
                    "player_id" => $client_details->player_id, #R
                    "mw_response" => $response, #R
                ]
            ];
            $updateGameTransaction = [
                'win' => 4,
                'pay_amount' => $data['price'],
                'income' => $gameExt->amount - round($data["price"],2),
                'entry_id' => round($data["price"],2) == 0 && $gameExt->pay_amount == 0 ? 1 : 2,
                'trans_status' => 2
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $gameExt->game_trans_id, $client_details);
            $client_response = ClientRequestHelper::fundTransfer_TG($client_details,round($data['price'],2),$gamedetails->game_code, $gamedetails->game_name,$gameExt->game_trans_id,"credit",true,$action_payload);
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
                $dataToUpdate = array(
                    "mw_response" => json_encode($response)
                );
                GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$refundgametransExtID,$client_details);
                Helper::saveLog('BOTA Success fundtransfer', $this->provider_db_id, json_encode($response), "(Cancel)HIT!");
                return response($response,200)
                    ->header('Content-Type', 'application/json');
            }
            elseif(isset($client_response->fundtransferresponse->status->code)
            && $client_response->fundtransferresponse->status->code == "402"){
                $response = array(
                    "status" => [
                        "code" => $client_response->fundtransferresponse->status->code,
                        "status" => $client_response->fundtransferresponse->status->status,
                        "message" =>$client_response->fundtransferresponse->status->message,
                    ],
                    "balance" => round($client_response->fundtransferresponse->balance, 2),
                    "currencycode" => $client_response->fundtransferresponse->currencycode
                );
                try{
                    $datatosend = array(
                    "win" => 2
                    );
                    GameTransactionMDB::updateGametransaction($datatosend,$gameExt->game_trans_id,$client_details);
                    $updateData = array(
                        "mw_response" => json_encode($response)
                    );
                    GameTransactionMDB::updateGametransactionEXT($updateData, $refundgametransExtID, $client_details);
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