<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ProviderHelper;
use App\Helpers\KAHelper;
use App\Helpers\TransferWalletHelper;
use App\Helpers\SessionWalletHelper;
use App\Helpers\ClientRequestHelper;
use GuzzleHttp\Exception\GuzzleException;
use App\Models\GameTransactionMDB;
use Carbon\Carbon;
use GuzzleHttp\Client;
use DB;

class KAGamingNFController extends Controller
{

    public $gamelaunch, $partnerName, $ka_api, $access_key, $secret_key, $tw_partnerName, $tw_access_key, $tw_secret_key = '';
    public $provider_db_id = 43; // Nothing todo with the provider
    public $prefix = 'KAGAMING';


    public function __construct()
    {
        $this->gamelaunch = config('providerlinks.kagaming.gamelaunch');
        $this->ka_api = config('providerlinks.kagaming.ka_api');
        $this->partnerName = config('providerlinks.kagaming.partner_name');
        $this->access_key = config('providerlinks.kagaming.access_key');
        $this->secret_key = config('providerlinks.kagaming.secret_key');

        $this->tw_partnerName = config('providerlinks.kagaming.tw_partner_name');
        $this->tw_access_key = config('providerlinks.kagaming.tw_access_key');
        $this->tw_secret_key = config('providerlinks.kagaming.tw_secret_key');
    }

    public function generateHash($msg=''){
        return hash_hmac('sha256', json_encode($msg), $this->secret_key);
    }

    public function verifyHash($request_body, $hashen){
        $data = json_decode($request_body);
        if(isset($data->hash)){
           unset($data->hash); 
        }
        $body = json_encode($data);
        $hash = hash_hmac('sha256', $body, $this->secret_key);
        if($hash == $hashen){
            return true;
        }else{
            return false;
        }
    }

    public function index(){
        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json'
            ]
        ]);
        $body = [
            "partnerName" => 'TIGER',
            "accessKey" => $this->access_key,
            "language" => "en",
            "randomId" => 1,
        ];
        $guzzle_response = $client->post($this->ka_api.'gameList?hash='.$this->generateHash($body),
            ['body' => json_encode(
                    $body
            )]
        );
        $client_response = json_decode($guzzle_response->getBody()->getContents());

        $gamelist = array();
        foreach ($client_response->games as $key) {
            $game = [
                "game_name" => $key->gameName,
                "game_type" => $key->gameType,
                "game_code" => $key->gameId
            ];
            array_push($gamelist, $game);
        }

        return $gamelist;
    }

    public function formatBalance($amount){
        return round($amount*100,2);
    }

    public function formatAmounts($amount){
         return round($amount/100,2);
    }

    
    public function gameStart(Request $request){
        KAHelper::saveLog('KAGaming gameStart - EH', $this->provider_db_id, json_encode($request->all()), $request->input("hash"));
        $request_body = $request->getContent();
        // if(!$request->input("hash") != ''){
        //     return  $response = ["status" => "failed", "statusCode" =>  3];
        // }
        // if(!$this->verifyHash($request_body, $request->input("hash"))){
        //     return  $response = ["status" => "failed", "statusCode" =>  3];
        // }
        $data = json_decode($request_body);
        // $session_check = ProviderHelper::getClientDetailsCache('token',$data->token);
        // if($session_check == 'false'){
        //     return  $response = ["status" => "failed", "statusCode" =>  100];
        // }
        $client_details = ProviderHelper::getClientDetailsCache('player_id',$data->partnerPlayerId);
        if($client_details == 'false'){
            return  $response = ["status" => "failed", "statusCode" =>  4];
        }
        // $player_details = KAHelper::playerDetailsCall($client_details);
        // if($player_details == 'false'){
        //     return  $response = ["status" => "Server Timeout", "statusCode" =>  1];
        // }
        $response = [
            "playerId" => $client_details->player_id,
            "sessionId" => $client_details->player_token,
            // "balance" => $this->formatBalance($player_details->playerdetailsresponse->balance),
            "balance" => $this->formatBalance($client_details->balance),
            "currency" =>  $client_details->default_currency,
            "status" => "success",
            "statusCode" =>  0
        ];
        return $response;
    }


    public function playerBalance(Request $request){
        KAHelper::saveLog('KAGaming playerBalance - EH', $this->provider_db_id, json_encode($request->all()), 'ENDPOINT HIT');
        $request_body = $request->getContent();

        // if(!$request->input("hash") != ''){
        //     return  $response = ["status" => "failed", "statusCode" =>  3];
        // }
        // if(!$this->verifyHash($request_body, $request->input("hash"))){
        //     return  $response = ["status" => "failed", "statusCode" =>  3];
        // }
        $data = json_decode($request_body);
        // $session_check = ProviderHelper::getClientDetailsCache('token',$data->sessionId);
        // if($session_check == 'false'){
        //     return  $response = ["status" => "failed", "statusCode" =>  100];
        // }
        $client_details = ProviderHelper::getClientDetailsCache('player_id',$data->partnerPlayerId);
        if($client_details == 'false'){
            return  $response = ["status" => "failed", "statusCode" =>  4];
        }
        // $player_details = KAHelper::playerDetailsCall($client_details);
        // if($player_details == 'false'){
        //     return  $response = ["status" => "Server Timeout", "statusCode" =>  1];
        // }
        $response = [
            // "balance" => $this->formatBalance($player_details->playerdetailsresponse->balance),
            "balance" => $this->formatBalance($client_details->balance),
            "status" => "success",
            "statusCode" =>  0
        ];
        return $response;
    }

    
    /** 
     * EXPIREMENTAL (1 WAY CALL KAGAming) NOT USED
     * 
     */
    public function checkPlayOneWay($payload_request){
        $data = json_decode($payload_request);

        $general_details = ["aggregator" => [], "provider" => [], "client" => []];
        $freeGames = $data->freeGames; 
        $provider_trans_id = $data->transactionId;
        $round_id = $provider_trans_id.'_'.$data->round;
        $game_code = $data->gameId;

        if($freeGames == true){
            $bet_amount = 0;
            $is_freespin = true;
        }else{
            $bet_amount = $this->formatAmounts($data->betAmount);
            $is_freespin = false;
        }
        
        $amount = $bet_amount;
        $win_amount = $this->formatAmounts($data->winAmount);
        $payout_reason = 'Game Bets and Win';

        $client_details = ProviderHelper::getClientDetailsCache('player_id',$data->partnerPlayerId);
        // dd($client_details);
        if($client_details == 'false'){
            return  $response = ["status" => "failed", "statusCode" =>  4];
        }
        $game_information = ProviderHelper::findGameDetails('game_code', $this->provider_db_id, $game_code);
        if($game_information == null){ 
            return  $response = ["status" => "Game Not Found", "statusCode" =>  1];
        }

        $general_details['client']['before_balance'] = KAHelper::amountToFloat($client_details->balance);
        $general_details['client']['action'] = 'play';
        $game_transaction_type = 1; // 1 Bet, 2 Win
        $game_code = $game_information->game_id;
        $token_id = $client_details->token_id;
        

        $game_ext_check = KAHelper::findGameExt($round_id, 1, 'round_id');
        if($game_ext_check != 'false'){ // Duplicate transaction
            if($game_ext_check->transaction_detail != '"FAILED"' && $game_ext_check->transaction_detail != 'FAILED'){
                return  $response = ["status" => "Duplicate transaction", "statusCode" =>  1];
            }
        }

        $check_bet_round = KAHelper::findGameExt($provider_trans_id, 1, 'transaction_id');
        if($check_bet_round != 'false'){
            $pay_amount =  $win_amount; //abs($data['amount']);
            $income = $bet_amount - $pay_amount;
            $method = 1;
            // $entry_id = 2;
            // $win_or_lost = 5; // 0 lost,  5 processing

            $existing_bet_details = KAHelper::findGameTransaction($check_bet_round->game_trans_id, 'game_transaction');
            $gamerecord = $existing_bet_details->game_trans_id;
            $game_transextension = KAHelper::createGameTransExtV2($existing_bet_details->game_trans_id,$provider_trans_id, $round_id, $amount, $game_transaction_type,$data);
        }else{
            $pay_amount =  $win_amount; //abs($data['amount']);
            $income = $bet_amount - $pay_amount;
            $method = 1;
            if($pay_amount == 0) {
                $entry_id = 2;
                $win_or_lost = 0; // 0 lost,  5 processing
            }else{
                $entry_id = 2;
                $win_or_lost = 1; // 0 lost,  5 processing
            }
            #1 DEBIT OPERATION
            $gamerecord  = ProviderHelper::createGameTransaction($token_id, $game_code, $bet_amount,  $pay_amount, $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id, $round_id);
            $game_transextension = KAHelper::createGameTransExtV2($gamerecord,$provider_trans_id, $round_id, $bet_amount, $game_transaction_type,$data);
        }

        $client_response = KAHelper::fundTransferAll($client_details,$bet_amount,$win_amount,$game_information->game_code,$game_information->game_name,$game_transextension,$gamerecord);
             
        if(isset($client_response->fundtransferresponse->status->code) 
              && $client_response->fundtransferresponse->status->code == "200"){
              $response = [
                "balance" => $this->formatBalance($client_response->fundtransferresponse->balance),
                "status" => "success",
                "statusCode" =>  0
              ];
              ProviderHelper::_insertOrUpdateCache($client_details->token_id, $client_response->fundtransferresponse->balance);
              ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $response,$client_response->requestoclient, $client_response, 'SUCCESS', $general_details);
              $game_transextension = KAHelper::createGameTransExtV2($gamerecord,$provider_trans_id, $round_id, $win_amount, 2,$data,$response,$client_response->requestoclient, $client_response, 'SUCCESS', $general_details);
              if($check_bet_round != 'false'){
                    $pay_amount = $existing_bet_details->pay_amount + $win_amount;
                    $bet_amount = $existing_bet_details->bet_amount + $bet_amount;
                    $income = $bet_amount - $pay_amount; 
                    if($pay_amount > 0){ 
                        $win_or_lost = 1;
                        $entry_id = 2;
                     }else{
                        $win_or_lost = 0;
                        $entry_id = 1;
                     }
                    ProviderHelper::updateGameTransaction($gamerecord, $pay_amount, $income, $win_or_lost, $entry_id,'game_trans_id',$bet_amount,$multi_bet=true);
                }else{
                    $pay_amount = $win_amount;
                    $income = $bet_amount - $pay_amount;
                    if($pay_amount > 0){
                       $win_or_lost = 1;
                       $entry_id = 2;
                    }else{
                       $win_or_lost = 0;
                       $entry_id = 1;
                    }           
                    ProviderHelper::updateGameTransaction($gamerecord, $pay_amount, $income, $win_or_lost, $entry_id);
                }
        }elseif(isset($client_response->fundtransferresponse->status->code) 
              && $client_response->fundtransferresponse->status->code == "402"){
              $response = ["status" => "Low Balance", "statusCode" =>  200];
              if($check_bet_round == 'false'){
                ProviderHelper::updateGameTransactionStatus($gamerecord, 2, 99);
              }
              ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $response,$client_response->requestoclient, $client_response, 'FAILED', $general_details);
        }
        
        return $response;

    }

    public function checkPlay(Request $request){
        KAHelper::saveLog('KAGaming checkPlay - EH', $this->provider_db_id, json_encode($request->all()), $request->input("hash"));
        // $request_body = file_get_contents("php://input");
        $request_body = $request->getContent();
        
        // if(!$request->input("hash") != ''){
        //     return  $response = ["status" => "failed", "statusCode" =>  3];
        // }
        // if(!$this->verifyHash($request_body, $request->input("hash"))){
        //     return  $response = ["status" => "failed", "statusCode" =>  3];
        // }

        $data = json_decode($request_body);
        $general_details = ["aggregator" => [], "provider" => [], "client" => []];
        $freeGames = $data->freeGames; 
        $provider_trans_id = $data->transactionId;
        $round_id = $provider_trans_id.'_'.$data->round;
        $game_code = $data->gameId;

        if($freeGames == true){
            $bet_amount = 0;
            $is_freespin = true;
        }else{
            $bet_amount = $this->formatAmounts($data->betAmount);
            $is_freespin = false;
        }

        $amount = $bet_amount;
        $win_amount = $this->formatAmounts($data->winAmount);
        $pay_amount =  0; //abs($data['amount']);
        $method = 1;
        $income = $bet_amount - $pay_amount;
        $amount2 = $win_amount;
        $entry_id = 1;
        $win_or_lost = 5; // 0 lost,  5 processing
        $payout_reason = 'settled';
        $rounds_remaining = isset($data->roundsRemaining) ? $data->roundsRemaining : 0;
        $complete = isset($data->complete) ? $data->complete : false;
      
        $client_details = ProviderHelper::getClientDetailsCache('player_id',$data->partnerPlayerId);
        if($client_details == 'false'){
            return  $response = ["status" => "failed", "statusCode" =>  4];
        }

        $game_information = ProviderHelper::findGameDetails('game_code', $this->provider_db_id, $game_code);
        if($game_information == null){ 
            return  $response = ["status" => "Game Not Found", "statusCode" =>  1];
        }


        $game_ext_check = GameTransactionMDB::findGameExt($round_id, 1,'round_id', $client_details);
        if($game_ext_check != 'false'){ // Duplicate transaction
            if($game_ext_check->transaction_detail != '"FAILED"' && $game_ext_check->transaction_detail != 'FAILED'){
               // If Round has refund dont filter duplicate (PROCESS THE DATA)
               $game_ext_check_is_refund_success = GameTransactionMDB::findGameExt($round_id, 3, 'round_id', $client_details);
               if($game_ext_check_is_refund_success == 'false'){
                   return  $response = ["status" => "Duplicate transaction", "statusCode" =>  1];
               }
            }
        }

        $general_details['client']['before_balance'] = KAHelper::amountToFloat($client_details->balance);
        // $general_details['client']['before_balance'] = KAHelper::amountToFloat($player_details->playerdetailsresponse->balance);
        $general_details['client']['action'] = 'play';
        $game_transaction_type = 1; // 1 Bet, 2 Win
        $game_code = $game_information->game_id;
        $token_id = $client_details->token_id;

        $check_bet_round = GameTransactionMDB::findGameExt($provider_trans_id, 2,'transaction_id', $client_details);

        $fund_extra_data = [
            'fundtransferrequest' => [
                'fundinfo' => [
                    'freespin' => $is_freespin,
                ]
            ],
            'provider_name' => $game_information->provider_name
        ];
        $gamerecord = ProviderHelper::idGenerate($client_details->connection_name,1);
        $game_transextension = ProviderHelper::idGenerate($client_details->connection_name,2);
        try {
          $client_response = ClientRequestHelper::fundTransfer($client_details,abs($bet_amount),$game_information->game_code,$game_information->game_name,$game_transextension,$gamerecord, 'debit',false,$fund_extra_data);
          KAHelper::saveLog('KAGaming checkPlay CRID '.$gamerecord, $this->provider_db_id,json_encode($request->all()), $client_response);
        } catch (\Exception $e) {
          $response = ["status" => "Not Enough Balance", "statusCode" =>  200];
            if(isset($gamerecord)){
                if($check_bet_round == 'false'){
                    $gameTransactionData = array(
                        "provider_trans_id" => $provider_trans_id,
                        "token_id" => $token_id,
                        "game_id" => $game_code,
                        "round_id" => $round_id,
                        "bet_amount" => $bet_amount,
                        "win" => 2,
                        "pay_amount" => $pay_amount,
                        "income" =>  $income,
                        "entry_id" =>$method,
                    );
                    GameTransactionMDB::createGametransactionV2($gameTransactionData,$gamerecord,$client_details);
                    $gameTransactionEXTData = array(
                        "game_trans_id" => $gamerecord,
                        "provider_trans_id" => $provider_trans_id,
                        "round_id" => $round_id,
                        "amount" => $bet_amount,
                        "game_transaction_type"=> $game_transaction_type,
                        "provider_request" =>json_encode($data),
                        "mw_response" => json_encode($response),
                        'mw_request' => 'FAILED',
                        'client_response' => $e->getMessage(),
                        'transaction_detail' => 'FAILED',
                        'general_details' => json_encode($general_details),
                    );
                    GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$game_transextension,$client_details); //create extension
                }
            }
          KAHelper::saveLog('KAGaming checkPlay - FATAL ERROR', $this->provider_db_id, $response, KAHelper::datesent());
          return $response;
        }
        if(isset($client_response->fundtransferresponse->status->code) 
             && $client_response->fundtransferresponse->status->code == "200"){
                // ProviderHelper::updateGameTransactionFlowStatus($gamerecord, 2);
                # NEW FLOW WIN
                try {
                    ProviderHelper::_insertOrUpdateCache($client_details->token_id, $client_response->fundtransferresponse->balance);
                    $new_balance = $client_response->fundtransferresponse->balance + abs($win_amount);
                    $response = [
                        "balance" => $this->formatBalance($new_balance),
                        "status" => "success",
                        "statusCode" =>  0
                    ];
                    if($check_bet_round != 'false'){
                      // $is_multiple = true;
                      $existing_bet_details = GameTransactionMDB::findGameTransactionDetails($check_bet_round->game_trans_id, 'game_transaction',1, $client_details);
                      $gamerecord = $existing_bet_details->game_trans_id;
                      $gameTransactionEXTData = array(
                            "game_trans_id" => $existing_bet_details->game_trans_id,
                            "provider_trans_id" => $provider_trans_id,
                            "round_id" => $round_id,
                            "amount" => $amount,
                            "game_transaction_type"=> $game_transaction_type,
                            "provider_request" =>json_encode($data),
                            "mw_response" => json_encode($response),
                            'mw_request' => json_encode($client_response->requestoclient),
                            'client_response' => json_encode($client_response),
                            'transaction_detail' => json_encode($response),
                            'general_details' => json_encode($general_details),
                      );
                        GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$game_transextension,$client_details); //create extension

                        $pay_amount = $existing_bet_details->pay_amount + $win_amount;
                        $bet_amount = $existing_bet_details->bet_amount + $bet_amount;
                        $income = $bet_amount - $pay_amount; //$existing_bet_details->income;
                        if($pay_amount > 0){
                            if($rounds_remaining == 0 && $complete == true){
                                $win_or_lost = 1;
                            }
                            $entry_id = 2;
                        }else{
                            if($rounds_remaining == 0 && $complete == true){
                                $win_or_lost = 0;
                            }
                            $entry_id = 1;
                        }
                        $is_multiple = true;
                        $update_betamount = $bet_amount;

                        ## update on update transaction first Comment This If you want to rely on cut call
                        // ProviderHelper::updateGameTransaction($gamerecord, $pay_amount, $income, $win_or_lost, $entry_id,'game_trans_id',$bet_amount,$multi_bet=true);

                        // $updateGameTransaction = [
                        //     "bet_amount" => $bet_amount,
                        //     "pay_amount" => $pay_amount,
                        //     "income" =>  $income,
                        //     "win" => $win_or_lost,
                        //     "entry_id" => $entry_id,
                        // ];
                        // GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);

                    }else{
                        #1 DEBIT OPERATION
                        $flow_status = 0;
                        $gameTransactionData = array(
                            "provider_trans_id" => $provider_trans_id,
                            "token_id" => $token_id,
                            "game_id" => $game_code,
                            "round_id" => $round_id,
                            "bet_amount" => $bet_amount,
                            "win" => $win_or_lost,
                            "pay_amount" => $pay_amount,
                            "income" =>  $income,
                            "entry_id" =>$method,
                        );
                        GameTransactionMDB::createGametransactionV2($gameTransactionData,$gamerecord,$client_details);
                        $gameTransactionEXTData = array(
                            "game_trans_id" => $gamerecord,
                            "provider_trans_id" => $provider_trans_id,
                            "round_id" => $round_id,
                            "amount" => $bet_amount,
                            "game_transaction_type"=> $game_transaction_type,
                            "provider_request" =>json_encode($data),
                            "mw_response" => json_encode($response),
                            'mw_request' => json_encode($client_response->requestoclient),
                            'client_response' => json_encode($client_response),
                            'transaction_detail' => json_encode($response),
                            'general_details' => json_encode($general_details),
                        );
                        GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$game_transextension,$client_details); //create extension
                        $pay_amount = $win_amount;
                        $income = $bet_amount - $pay_amount;
                        if($pay_amount > 0){
                            if($rounds_remaining == 0 && $complete == true){
                                $win_or_lost = 1;
                            }
                            $entry_id = 2;
                        }else{
                            if($rounds_remaining == 0 && $complete == true){
                                $win_or_lost = 0;
                            }
                            $entry_id = 1;
                        }          
                        $is_multiple = false;
                        $update_betamount = 0;

                        ## update on update transaction first Comment This If you want to rely on cut call
                        // ProviderHelper::updateGameTransaction($gamerecord, $pay_amount, $income, $win_or_lost, $entry_id);
                        // $updateGameTransaction = [
                        //     "pay_amount" => $pay_amount,
                        //     "income" =>  $income,
                        //     "win" => $win_or_lost,
                        //     "entry_id" => $entry_id,
                        // ];
                        // GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
                    }
                    $credit_game_transextension = ProviderHelper::idGenerate($client_details->connection_name,2);
                    # Exclude from cut call auto generate EXT
                    $gameTransactionCREDITEXTData = array(
                        "game_trans_id" => $gamerecord,
                        "provider_trans_id" => $provider_trans_id,
                        "round_id" => $round_id,
                        "amount" => abs($win_amount),
                        "game_transaction_type"=> 2,
                        "provider_request" =>json_encode($data),
                        "mw_response" => json_encode($response),
                        // "transaction_detail" => json_encode($response),
                        "transaction_detail" => "FAILED",
                        "general_details" => json_encode($general_details),
                    );
                    $credit_game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionCREDITEXTData,$client_details);

                    $action_payload = [
                        "type" => "custom", #genreral,custom :D # REQUIRED!
                        "custom" => [
                            "game_transaction_ext_id" => $credit_game_transextension,
                            "client_connection_name" => $client_details->connection_name,
                            "provider" => 'kagaming',
                            "win_or_lost" => $win_or_lost,
                            "entry_id" => $entry_id,
                            "pay_amount" => $pay_amount,
                            "income" => $income,
                            "is_multiple" => $is_multiple,
                            "bet_amount" => $update_betamount
                        ],
                        "provider" => [
                            "provider_request" => $data, #R
                            "provider_trans_id"=> $provider_trans_id, #R
                            "provider_round_id"=> $round_id, #R
                            "provider_name" => $game_information->provider_name,
                        ],
                        "mwapi" => [
                            "roundId"=>$gamerecord, #R
                            "type"=>2, #R
                            "game_id" => $game_information->game_id, #R
                            "player_id" => $client_details->player_id, #R
                            "mw_response" => $response, #R
                        ],
                        'fundtransferrequest' => [
                            'fundinfo' => [
                                'freespin' => $is_freespin,
                            ]
                        ]
                    ];
                    try {
                        $client_response2 = ClientRequestHelper::fundTransfer_TG($client_details,abs($win_amount),$game_information->game_code,$game_information->game_name,$gamerecord,'credit',false,$action_payload);
                    } catch (\Exception $e) {
                        // return $e->getMessage().' '.$e->getLine().' '.$e->getFile();
                    }
                    if(isset($client_response->fundtransferresponse->status->code) 
                    && $client_response->fundtransferresponse->status->code == "200"){
                        $gameTransactionEXTData = array(
                            "game_trans_id" => $gamerecord,
                            "provider_trans_id" => $provider_trans_id,
                            "round_id" => $round_id,
                            "amount" => $pay_amount,
                            "game_transaction_type"=> 2,
                            "provider_request" =>json_encode($data),
                            "mw_response" => json_encode($response),
                            'mw_request' => json_encode($client_response->requestoclient),
                            'client_response' => json_encode($client_response->fundtransferresponse),
                            'transaction_detail' => 'success',
                            'general_details' => 'success',
                        );
                        // $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
                        GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$gen_game_extid,$client_details);  
                        return $response;
                    }
                } catch (\Exception $e) {
                    // return $e->getMessage().' '.$e->getLine().' '.$e->getFile();
                }
                // ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $response, $client_response->requestoclient, $client_response, $response,$general_details);
                ProviderHelper::_insertOrUpdateCache($client_details->token_id, $new_balance);
                # END NEW FLOW WIN  
        }elseif(isset($client_response->fundtransferresponse->status->code) 
                    && $client_response->fundtransferresponse->status->code == "402"){
            if($check_bet_round == 'false'){
                 if(ProviderHelper::checkFundStatus($client_response->fundtransferresponse->status->status)):
                        $updateGameTransaction = ["win" => 2,'trans_status' => 5];
                        GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
                else:
                    $updateGameTransaction = ["win" => 2,'trans_status' => 5];
                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
                endif;
            }
            $response = ["status" => "Low Balance", "statusCode" =>  200];
            $gameTransactionEXTData = array(
                "game_trans_id" => $gamerecord,
                "provider_trans_id" => $provider_trans_id,
                "round_id" => $round_id,
                "amount" => $pay_amount,
                "game_transaction_type"=> 2,
                "provider_request" =>json_encode($data),
                "mw_response" => json_encode($response),
                'mw_request' => json_encode($client_response->requestoclient),
                'client_response' => json_encode($client_response),
                'transaction_detail' => 'FAILED',
                'general_details' => json_encode($general_details),
            );
            // $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
            GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$gen_game_extid,$client_details);
        }else{ // Unknown Response Code
            if($check_bet_round == 'false'){
                $updateGameTransaction = ["win" => 2,'trans_status' => 5];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
            }
            $response = ["status" => "Not Enough Balance", "statusCode" =>  200];
            $gameTransactionEXTData = array(
                "game_trans_id" => $gamerecord,
                "provider_trans_id" => $provider_trans_id,
                "round_id" => $round_id,
                "amount" => $pay_amount,
                "game_transaction_type"=> 2,
                "provider_request" =>json_encode($data),
                "mw_response" => json_encode($response),
                'mw_request' => json_encode($client_response->requestoclient),
                'client_response' => json_encode($client_response),
                'transaction_detail' => 'FAILED',
                'general_details' => json_encode($general_details),
            );
            // $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
            GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$gen_game_extid,$client_details);
        }  
        return $response;
    }


    public function gameCredit(Request $request){
        KAHelper::saveLog('KAGaming gameCredit - EH', $this->provider_db_id, json_encode($request->all()), $request->input("hash"));

        $request_body = $request->getContent();
        // if(!$request->input("hash") != ''){
        //     return  $response = ["status" => "failed", "statusCode" =>  3];
        // }
        // if(!$this->verifyHash($request_body, $request->input("hash"))){
        //     return  $response = ["status" => "failed", "statusCode" =>  3];
        // }

        $data = json_decode($request_body);
        $general_details = ["aggregator" => [], "provider" => [], "client" => []];
        $amount = $this->formatAmounts($data->amount);

        $payout_reason = 'Credited Side Bets';
        $provider_trans_id = $data->transactionId;
        $game_code = $data->gameId;
        $complete = isset($data->complete) ? $data->complete : false;
        // $session_check = ProviderHelper::getClientDetailsCache('token',$data->sessionId);
        // if($session_check == 'false'){
        //     return  $response = ["status" => "failed", "statusCode" =>  100];
        // }
        $client_details = ProviderHelper::getClientDetailsCache('player_id',$data->partnerPlayerId);
        if($client_details == 'false'){
            return  $response = ["status" => "failed", "statusCode" =>  4];
        }
        // $player_details = KAHelper::playerDetailsCall($client_details);
        // if($player_details == 'false'){
        //     return  $response = ["status" => "Server Timeout", "statusCode" =>  1];
        // }
        $game_information = ProviderHelper::findGameDetails('game_code', $this->provider_db_id, $game_code);
        if($game_information == null){ 
            return  $response = ["status" => "Game Not Found", "statusCode" =>  1];
        }

        // $game_ext_check_win = KAHelper::findGameExt($provider_trans_id, 2, 'transaction_id');
        $game_ext_check_win = GameTransactionMDB::findGameExt($provider_trans_id, 2,'transaction_id', $client_details);
        if($game_ext_check_win != 'false'){
            $last_provider_request = json_decode($game_ext_check_win->provider_request);
            if(isset($last_provider_request->action) && $last_provider_request->action == 'credit'){
                if(isset($last_provider_request->creditIndex) && $last_provider_request->creditIndex == $data->creditIndex){
                    return  $response = ["status" => "Double transactionId with an action credit", "statusCode" =>  301];
                }
            }
            // $transaction_general_details = json_decode($game_ext_check_win->general_details);
            // dd($transaction_general_details);
            // if(isset($transaction_general_details->client->action) && $transaction_general_details->client->action == 'credit'){
            //     return  $response = ["status" => "Double transactionId with an action credit", "statusCode" =>  301];
            // }
        }

        # Insert Idenpotent (CREDIT)
        // try{
        //  ProviderHelper::idenpotencyTable($this->prefix.'_CREDIT_'.$provider_trans_id);
        // }catch(\Exception $e){
        //  return  $response = ["status" => "Double transactionId with an action credit", "statusCode" =>  301];
        // }

        // $game_ext_check = KAHelper::findGameExt($provider_trans_id, 1, 'transaction_id');
        $game_ext_check = GameTransactionMDB::findGameExt($provider_trans_id,1,'transaction_id', $client_details);
        if($game_ext_check == 'false'){ // Duplicate transaction
            return  $response = ["status" => "Licensee or operator denied crediting to player (cashable or bonus) / Transaction Not Found", "statusCode" =>  301];
        }
        $general_details['client']['before_balance'] = KAHelper::amountToFloat($client_details->balance);
        // $general_details['client']['before_balance'] = KAHelper::amountToFloat($player_details->playerdetailsresponse->balance);
        $general_details['client']['action'] = 'credit';


        $gamerecord = $game_ext_check->game_trans_id;
        // $existing_bet = KAHelper::findGameTransaction($gamerecord,'game_transaction');
        $existing_bet = GameTransactionMDB::findGameTransactionDetails($gamerecord, 'game_transaction',1, $client_details);
        $round_id = $existing_bet->round_id;
      
        $bet_amount = $existing_bet->bet_amount;
        $pay_amount =  $existing_bet->pay_amount + $amount; //abs($data['amount']);
        $income = $bet_amount - $pay_amount;

        if($pay_amount > 0){
            $entry_id = 2; // Credit
            $win_or_lost = 5; // 1 win,  5 processing
            if($complete == true){
                $win_or_lost = 1;
            }
        }else{
            $entry_id = 1; // Debit
            $win_or_lost = 5; // 0 lost,  5 processing
            if($complete == true){
                $win_or_lost = 0;
            }
        }

        $game_transaction_type = 2; // 1 Bet, 2 Win
        $game_code = $game_information->game_id;
        $token_id = $client_details->token_id;

        $updateGameTransaction = [
            "pay_amount" => $pay_amount,
            "income" =>  $income,
            "win" => $win_or_lost,
            "entry_id" => $entry_id,
        ];
        GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
        // ProviderHelper::updateGameTransaction($gamerecord, $pay_amount, $income, $win_or_lost, $entry_id);

         # NEW FLOW WIN
         try {
            ProviderHelper::_insertOrUpdateCache($client_details->token_id, $client_details->balance+abs($amount));
            $new_balance = $client_details->balance+abs($amount);
            $response = [
                "balance" => $this->formatBalance($new_balance),
                "status" => "success",
                "statusCode" =>  0
            ];

            if($pay_amount > 0){
                $entry_id = 2; // Credit
                $win_or_lost = 5; // 1 win,  5 processing
                if($complete == true){
                    $win_or_lost = 1;
                }
            }else{
                $entry_id = 1; // Debit
                $win_or_lost = 5; // 0 lost,  5 processing
                if($complete == true){
                    $win_or_lost = 0;
                }
            }

            $gameTransactionEXTData = array(
                "game_trans_id" => $gamerecord,
                "provider_trans_id" => $provider_trans_id,
                "round_id" => $round_id,
                "amount" => abs($amount),
                "game_transaction_type"=> 2,
                "provider_request" => json_encode($data),
                "transaction_detail" => json_encode($response),
                "mw_response" => json_encode($response),
                "general_details" => json_encode($general_details)
            );
            $game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "game_transaction_ext_id" => $game_transextension,
                    "client_connection_name" => $client_details->connection_name,
                    "provider" => 'kagaming',
                    "win_or_lost" => $win_or_lost,
                    "entry_id" => $entry_id,
                    "pay_amount" => $pay_amount,
                    "income" => $income,
                    "is_multiple" => false,
                ],
                "provider" => [
                    "provider_request" => $data, #R
                    "provider_trans_id"=> $provider_trans_id, #R
                    "provider_round_id"=> $round_id, #R
                    "provider_name" => $game_information->provider_name,
                ],
                "mwapi" => [
                    "roundId"=>$gamerecord, #R
                    "type"=>2, #R
                    "game_id" => $game_information->game_id, #R
                    "player_id" => $client_details->player_id, #R
                    "mw_response" => $response, #R
                ],
                'fundtransferrequest' => [
                    'fundinfo' => [
                        'freespin' => true,
                    ]
                ]
            ];
            $client_response2 = ClientRequestHelper::fundTransfer_TG($client_details,abs($amount),$game_information->game_code,$game_information->game_name,$gamerecord,'credit',false,$action_payload);
        } catch (\Exception $e) {
            return $e->getMessage().' '.$e->getLine().' '.$e->getFile();
        }
        // ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $response, $client_response->requestoclient, $client_response, $response,$general_details);
        ProviderHelper::_insertOrUpdateCache($client_details->token_id, $new_balance);
        return $response;
        # END NEW FLOW WIN  
    }


    public function gameRevoke(Request $request){
        KAHelper::saveLog('KAGaming gameRevoke - EH', $this->provider_db_id, json_encode($request->all()), $request->input("hash"));
        $request_body = $request->getContent();
        // if(!$request->input("hash") != ''){
        //     return  $response = ["status" => "failed", "statusCode" =>  3];
        // }
        // if(!$this->verifyHash($request_body, $request->input("hash"))){
        //     return  $response = ["status" => "failed", "statusCode" =>  3];
        // }
        $data = json_decode($request_body);
        $general_details = ["aggregator" => [], "provider" => [], "client" => []];
        $game_code = $data->gameId;
        $provider_trans_id = $data->transactionId;
        $round_id = $provider_trans_id.'_'.$data->round;

        // $session_check = ProviderHelper::getClientDetailsCache('token',$data->sessionId);
        // if($session_check == 'false'){
        //     return  $response = ["status" => "failed", "statusCode" =>  100];
        // }
        $client_details = ProviderHelper::getClientDetailsCache('player_id',$data->partnerPlayerId);
        if($client_details == 'false'){
            return  $response = ["status" => "failed", "statusCode" =>  4];
        }
        // $player_details = KAHelper::playerDetailsCall($client_details);
        // if($player_details == 'false'){
        //     return  $response = ["status" => "Server Timeout", "statusCode" =>  1];
        // }
        $game_information = ProviderHelper::findGameDetails('game_code', $this->provider_db_id, $game_code);
        if($game_information == null){ 
            return  $response = ["status" => "Game Not Found", "statusCode" =>  1];
        }
        // $transaction_details = KAHelper::findGameExt($round_id, 1, 'round_id');
        $transaction_details = GameTransactionMDB::findGameExt($round_id, 1,'round_id', $client_details);
        if($transaction_details == 'false'){ // Duplicate transaction
            return  $response = ["status" => "revoke Transaction does not exist", "statusCode" =>  400];
        }
        if($transaction_details->transaction_detail == '"FAILED"' || $transaction_details->transaction_detail == 'FAILED'){
            $is_round_0 = explode('_', $transaction_details->round_id);
            if((int)$is_round_0[1] == 0){
              return  $response = ["status" => "Transaction no longer revocable", "statusCode" =>  401];
            }   
        }
        // $existing_bet = KAHelper::findGameTransaction($transaction_details->game_trans_id,'game_transaction');
        $existing_bet = GameTransactionMDB::findGameTransactionDetails($transaction_details->game_trans_id, 'game_transaction', 1,$client_details);

        $check_revoked = GameTransactionMDB::findGameExt($round_id, 3,'round_id', $client_details);
        if($check_revoked != 'false'){ // Duplicate transaction
            return  $response = ["status" => "Transaction no longer revocable", "statusCode" =>  401];
        }
        $general_details['client']['before_balance'] = KAHelper::amountToFloat($client_details->balance);
        // $general_details['client']['before_balance'] = KAHelper::amountToFloat($player_details->playerdetailsresponse->balance);

        // $all_round = $this->findAllGameExt($provider_trans_id, 'all', $round_id);
        $all_round = GameTransactionMDB::findGameExtAll($provider_trans_id,'all', $client_details);
        $bet_amounts = array();
        $win_amounts = array();
        if(count($all_round) != 0){
            foreach ($all_round as $key) {
                $the_round = explode('_', $key->round_id);
                if((int)$the_round[1] >= $data->round){
                    if($key->game_transaction_type == 1){
                        array_push($bet_amounts, $key->amount);
                    }elseif($key->game_transaction_type == 2){
                        array_push($win_amounts, $key->amount);
                    }
                }
            }
        }
        $refund_amount = array_sum($bet_amounts)-array_sum($win_amounts);
        $method = $transaction_details->game_transaction_type;
        $entry_id = $transaction_details->game_transaction_type;
        // $win_or_lost = 4; // 0 lost,  5 processing
        $payout_reason = 'Refund - Revoked';
        $game_code = $data->gameId;

        $game_transaction_type = 3; // 1 Bet, 2 Win
        $game_code = $game_information->game_id;
        $token_id = $client_details->token_id;

        $gamerecord = $transaction_details->game_trans_id;

        if($refund_amount < 0){
           $transaction_type = 'debit';
        //    $pay_amount =  0; //abs($data['amount']);
           $pay_amount =  $existing_bet->pay_amount - abs($refund_amount);
           $income = $existing_bet->bet_amount - $pay_amount;
           // if(KAHelper::amountToFloat($player_details->playerdetailsresponse->balance) < abs($refund_amount)){
           if(KAHelper::amountToFloat($client_details->balance) < abs($refund_amount)){
                 return  $response = ["status" => "Insufficient balance", "statusCode" =>  200];
           }
        }else{
           $transaction_type = 'credit';
           $pay_amount =  $existing_bet->pay_amount + abs($refund_amount);
           $income = $existing_bet->bet_amount - $pay_amount;
        }

        // if($pay_amount > 0){
        //     if(count($all_round) != 0){
        //         $win_or_lost = 1; // 1 win,  5 processing
        //     }else{
        //         $win_or_lost = 4; // 4 refund,  5 processing
        //     }
        // }else{
        //     $win_or_lost = 4; // 4 refund,  5 processing
        // }

        if($pay_amount > 0){
            if(count($all_round) == 1){
                $win_or_lost = 4; // 4 refund,  5 processing
            }else{
                $win_or_lost = 1; // 1 win,  5 processing
            }
        }else{
            $win_or_lost = 4; // 4 refund,  5 processing
            $pay_amount = 0;
        }

        #1 DEBIT OPERATION
        // $game_transextension = KAHelper::createGameTransExtV2($gamerecord,$provider_trans_id, $round_id, abs($refund_amount), $game_transaction_type, $data);
        $gameTransactionEXTData = array(
            "game_trans_id" => $gamerecord,
            "provider_trans_id" => $provider_trans_id,
            "round_id" => $round_id,
            "amount" => abs($refund_amount),
            "game_transaction_type"=> $game_transaction_type,
            "provider_request" =>json_encode($data),
        );
        $game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

        $fund_extra_data = [
            'provider_name' => $game_information->provider_name
        ];

        try {
          $client_response = ClientRequestHelper::fundTransfer($client_details,abs($refund_amount),$game_information->game_code,$game_information->game_name,$game_transextension,$gamerecord, $transaction_type, true,$fund_extra_data);
          KAHelper::saveLog('KAGaming gameRevoke CRID '.$gamerecord, $this->provider_db_id,json_encode($request->all()), $client_response);
           
        } catch (\Exception $e) {
          $response = ["status" => "Server Timeout", "statusCode" =>  1];
          $updateTransactionEXt = array(
                "provider_request" =>json_encode($data),
                "mw_response" => json_encode($response),
                'client_response' => json_encode($e->getMessage()),
                'transaction_detail' => 'FAILED',
                'general_details' => json_encode($general_details),
          );
          GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
        //   ProviderHelper::updatecreateGameTransExt($game_transextension, 'FAILED', $response, 'FAILED', $e->getMessage(), 'FAILED', $general_details);
          KAHelper::saveLog('KAGaming gameRevoke - FATAL ERROR', $this->provider_db_id, json_encode($response), KAHelper::datesent());
          return $response;
        }
        if(isset($client_response->fundtransferresponse->status->code) 
             && $client_response->fundtransferresponse->status->code == "200"){
            ProviderHelper::_insertOrUpdateCache($client_details->token_id, $client_response->fundtransferresponse->balance);
            #2 CREDIT OPERATION   
            $general_details['client']['after_balance'] = KAHelper::amountToFloat($client_response->fundtransferresponse->balance);
            $response = [
                "balance" => $this->formatBalance($client_response->fundtransferresponse->balance),
                "status" => "success",
                "statusCode" =>  0
            ];
            // ProviderHelper::updateGameTransaction($gamerecord, $pay_amount, $income, $win_or_lost, $entry_id);
            // ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $response, $client_response->requestoclient, $client_response, $response,$general_details);
            $updateGameTransaction = [
                "pay_amount" => $pay_amount,
                "income" =>  $income,
                "win" => $win_or_lost,
                "entry_id" => $entry_id,
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
            $updateTransactionEXt = array(
                "provider_request" =>json_encode($data),
                "mw_response" => json_encode($response),
                'mw_request' => json_encode($client_response->requestoclient),
                'client_response' => json_encode($client_response),
                'transaction_detail' => json_encode($response),
                'general_details' => json_encode($general_details),
            );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
        }elseif(isset($client_response->fundtransferresponse->status->code) 
                    && $client_response->fundtransferresponse->status->code == "402"){
            $response = ["status" => "success", "statusCode" =>  200];
            // ProviderHelper::updatecreateGameTransExt($game_transextension, 'FAILED', $data, 'FAILED', $client_response, 'FAILED', $general_details);
            $updateTransactionEXt = array(
                    "provider_request" =>json_encode($data),
                    "mw_response" => json_encode($response),
                    'client_response' => json_encode($client_response),
                    'transaction_detail' => 'FAILED',
                    'general_details' => json_encode($general_details),
            );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
        }else{ // Unknown Response Code
            $response = ["status" => "Client Error", "statusCode" =>  1];
            $updateTransactionEXt = array(
                    "provider_request" =>json_encode($data),
                    "mw_response" => json_encode($response),
                    'client_response' => json_encode($client_response),
                    'transaction_detail' => 'FAILED',
                    'general_details' => json_encode($general_details),
            );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
            KAHelper::saveLog('KAGaming gameRevoke - FATAL ERROR', $this->provider_db_id, $response, KAHelper::datesent());
        }  
        return $response;
    }


    public function gameEnd(Request $request){
        KAHelper::saveLog('KAGaming gameEnd - EH', $this->provider_db_id, json_encode($request->all()), 'ENDPOINT HIT');
        $request_body = $request->getContent();

        // if(!$request->input("hash") != ''){
        //     return  $response = ["status" => "failed", "statusCode" =>  3];
        // }
        // if(!$this->verifyHash($request_body, $request->input("hash"))){
        //     return  $response = ["status" => "failed", "statusCode" =>  3];
        // }
        $data = json_decode($request_body);
        // $session_check = ProviderHelper::getClientDetailsCache('token',$data->sessionId);
        // if($session_check == 'false'){
        //     return  $response = ["status" => "failed", "statusCode" =>  100];
        // }
        $client_details = ProviderHelper::getClientDetailsCache('player_id',$data->partnerPlayerId);
        if($client_details == 'false'){
            return  $response = ["status" => "failed", "statusCode" =>  4];
        }
        // $player_details = KAHelper::playerDetailsCall($client_details);
        // if($player_details == 'false'){
        //     return  $response = ["status" => "Server Timeout", "statusCode" =>  1];
        // }
        $response = [
            "balance" => $this->formatBalance($client_details->balance),
            // "balance" => $this->formatBalance($player_details->playerdetailsresponse->balance),
            "status" => "success",
            "statusCode" =>  0
        ];
        return $response;
    }

    public function checkPlayerWallet(Request $request)
    {
        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]);


        $body = [
            "username" => '98',
            "partnerName" => $this->tw_partnerName,
            "gameName" => 'Fastbreak',
            "currency" => 'USD',
            "accessKey" => $this->tw_access_key,
            'online' => true
        ];

        // $body['hash'] = hash_hmac('sha256', json_encode($body), $this->tw_secret_key);
        // return json_encode($body);

        $guzzle_response = $client->post(
            $this->ka_api . 'wallet/balance?hash=' . $this->generateHash($body, 2),
            ['body' => json_encode(
                $body
            )]
        );
        $client_response = json_decode($guzzle_response->getBody()->getContents());

        dd($client_response);
    }


    public function checkPlayerTransactions(Request $request)
    {
        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]);


        $body = [
            "username" => 'RiANDRAFT',
            "partnerName" => $this->tw_partnerName,
            "gameName" => 'Fastbreak',
            "currency" => 'USD',
            "accessKey" => $this->tw_access_key,
            'walletTransactionId' => '0BA7193D0486277FCA3125BD017A2A2D'
        ];

        // $body['hash'] = hash_hmac('sha256', json_encode($body), $this->tw_secret_key);
        // return json_encode($body);

        $guzzle_response = $client->post(
            $this->ka_api . 'wallet/checkTransaction?hash=' . $this->generateHash($body, 2),
            ['body' => json_encode(
                $body
            )]
        );
        $client_response = json_decode($guzzle_response->getBody()->getContents());

        dd($client_response);
    }



    public  function findAllGameExt($provider_identifier, $type, $second_identifier='') {
        $transaction_db = DB::table('game_transaction_ext as gte');
        if ($type == 'transaction_id') {
            $transaction_db->where([
                ["gte.provider_trans_id", "=", $provider_identifier],
            ]);
        }
        if ($type == 'round_id') {
            $transaction_db->where([
                ["gte.round_id", "=", $provider_identifier],
            ]);
        }  
        if ($type == 'all') {
            $transaction_db->where([
                // ["gte.round_id", "=", $second_identifier],
                ["gte.provider_trans_id", "=", $provider_identifier],
                ["gte.transaction_detail", "!=", '"FAILED"'],
            ]);
        }  
        // $result = $transaction_db->latest()->get();
        $result = $transaction_db->get();
        return $result ? $result : 'false';
    }


}
