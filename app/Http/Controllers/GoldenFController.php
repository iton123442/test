<?php

namespace App\Http\Controllers;

use App\Helpers\ClientRequestHelper;
use App\Helpers\ProviderHelper;
use App\Helpers\Helper;
use Illuminate\Http\Request;
// use App\Helpers\ProviderHelper; # Migrated To GoldenFHelper Query Builder To RAW SQL - RiAN
use App\Helpers\GoldenFHelper; # Migrated To TransferWalletHelper (Centralization) Query Builder To RAW SQL DONT REMOVE COMMENT FOR NOW - RiAN
use App\Helpers\TransferWalletHelper;
use App\Helpers\SessionWalletHelper;
use App\Models\GameTransactionMDB;
use DB;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use Carbon\Carbon;
use Exception;

class GoldenFController extends Controller
{

    public $wtoken = '1223iLyCh4r1tyKunHah4h3';

    public $provider_db_id, $api_url, $operator_token, $secret_key, $wallet_code;
    public function __construct(){
        $this->api_url = config("providerlinks.goldenF.api_url");
        $this->provider_db_id = config("providerlinks.goldenF.provider_id");
        $this->secret_key = config("providerlinks.goldenF.secrete_key");
        $this->operator_token = config("providerlinks.goldenF.operator_token");
        $this->wallet_code = config("providerlinks.goldenF.wallet_code");
    }

    // public function changeEnvironment($client_details){
    //     if($client_details->default_currency == 'USD'){
    //         $this->api_url = config("providerlinks.goldenF.USD.api_url");
    //         $this->provider_db_id = config("providerlinks.goldenF.USD.provider_id");
    //         $this->secret_key = config("providerlinks.goldenF.USD.secrete_key");
    //         $this->operator_token = config("providerlinks.goldenF.USD.operator_token");
    //         $this->wallet_code = config("providerlinks.goldenF.USD.wallet_code");
    //     }elseif($client_details->default_currency == 'CNY'){
    //         $this->api_url = config("providerlinks.goldenF.CNY.api_url");
    //         $this->provider_db_id = config("providerlinks.goldenF.CNY.provider_id");
    //         $this->secret_key = config("providerlinks.goldenF.CNY.secrete_key");
    //         $this->operator_token = config("providerlinks.goldenF.CNY.operator_token");
    //         $this->wallet_code = config("providerlinks.goldenF.CNY.wallet_code");
    //     }
    // }



    /**
     * [GetPlayerBalance description]
     * @param Request $request [Trigger  by Play Game Iframe]
     * 
     */
    public function getPlayerBalance(Request $request)
    {   
        if(!$request->has("token")){
            $msg = array("status" =>"error","message" => "Token Invalid");
           TransferWalletHelper::saveLog('GetPlayerBalance', $this->provider_db_id,json_encode($request->all()), $msg);
            return response($msg,200)->header('Content-Type', 'application/json');
        }

        $client_details = TransferWalletHelper::getClientDetails('token', $request->token);
        if($client_details == null || $client_details == 'false'){
            $msg = array("status" =>"error","message" => "Token Invalid");
           TransferWalletHelper::saveLog('GetPlayerBalance', $this->provider_db_id,json_encode($request->all()), $msg);
            return response($msg,200)->header('Content-Type', 'application/json');
        }
        
        try {
            $client_response = TransferWalletHelper::playerDetailsCall($client_details);  
            $balance = round($client_response->playerdetailsresponse->balance,2);
            $msg = array(
                "status" => "ok",
                "message" => "Balance Request Success",
                "balance" => $balance
            );
           TransferWalletHelper::saveLog('GetPlayerBalance', $this->provider_db_id,json_encode($request->all()), $msg);
            return response($msg,200)->header('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $msg = array(
                "status" =>"error",
                "message" => $e->getMessage()
            );
           TransferWalletHelper::saveLog('GetPlayerBalance', $this->provider_db_id,json_encode($request->all()), $msg);
            return response($msg,200)->header('Content-Type', 'application/json');
        }

    }


    public function getPlayerWalletBalance(Request $request){
        $client_details = TransferWalletHelper::getClientDetails('token', $request->token);
        if($client_details == null || $client_details == 'false'){
            $msg = array("status" =>"error","message" => "Token Invalid");
           TransferWalletHelper::saveLog('GetPlayerBalance', $this->provider_db_id,json_encode($request->all()), $msg);
            return response($msg,200)->header('Content-Type', 'application/json');
        }
        
        $http = new Client();
        $response = $http->post(GoldenFHelper::changeEnvironment($client_details)->api_url."/GetPlayerBalance",[
           'form_params' => [
            'secret_key' => GoldenFHelper::changeEnvironment($client_details)->secret_key,
            'operator_token' => GoldenFHelper::changeEnvironment($client_details)->operator_token,
            'player_name' => "TG_".$client_details->player_id,
            'wallet_code' => GoldenFHelper::changeEnvironment($client_details)->wallet_code
            ]
        ]);
        $golden_response_balance = json_decode((string) $response->getBody(), true);
        $msg = array(
            "status" =>"success",
            "balance" => $golden_response_balance['data']['balance'],
            "message" => 'Something went wrong',
        );
        return response($msg,200)->header('Content-Type', 'application/json');
    }

    // public static function gameLaunch(){
    //     $operator_token = config("providerlinks.goldenF.operator_token");
    //     $api_url = config("providerlinks.goldenF.api_url");
    //     $secrete_key = config("providerlinks.goldenF.secrete_key");
    //     $provider_id = config("providerlinks.goldenF.provider_id");
    //     $client_details = TransferWalletHelper::getClientDetails('token','n58ec5e159f769ae0b7b3a0774fdbf80');
    //     $player_id = "TG_".$client_details->player_id;
    //     $gg = 'gps_knifethrow';
    //     $nickname = $client_details->username;
    //     $http = new Client();
    //     $gameluanch_url = $api_url."/Launch?secret_key=".$secrete_key."&operator_token=".$operator_token."&game_code=".$gg."&player_name=".$player_id."&nickname=".$nickname."&language=".$client_details->language;

    //     $response = $http->post($gameluanch_url);
    //     $get_url = json_decode($response->getBody()->getContents());

    //     // return $get_url->data->game_url;
    //     return json_encode($get_url);

    //     return 1;
    // }

    // client deduct 
    // deposit golden f
    public function TransferIn(Request $request)
    {
        #1 DEBIT OPERATION
        $data = $request->all();
        $game_details = TransferWalletHelper::getInfoPlayerGameRound($request->token);
        if ($game_details == false) {
            $msg = array("status" => "error", "message" => "Game Not Found");
            TransferWalletHelper::saveLog('TransferWallet TransferOut FAILED', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }

        $client_details = TransferWalletHelper::getClientDetails('token', $request->token);
        if ($client_details == 'false') {
            $msg = array("status" => "error", "message" => "Invalid Token or Token not found");
            TransferWalletHelper::saveLog('TransferWallet TransferOut FAILED', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }

        // $currency_availbale = config("providerlinks.goldenF");
        // if(!array_key_exists($client_details->default_currency, $currency_availbale)){
        //     $response = array(
        //         "status" => "error",
        //         "message" => "Currency Not Supported"
        //     );
        //     return response($response, 200)->header('Content-Type', 'application/json');
        // }

        # TransferWallet  (DENY DEPOSIT FOR ALREADY PLAYING PLAYER)
        # Check Multiple user Session
        $session_count = SessionWalletHelper::isMultipleSession($client_details->player_id, $request->token);
        if ($session_count) {
            $response = array(
                "status" => "error",
                "message" => "Multiple Session Detected!"
            );
            return response($response, 200)->header('Content-Type', 'application/json');
        }

        $client_response = TransferWalletHelper::playerDetailsCall($client_details); 
        $balance = round($client_response->playerdetailsresponse->balance,2);

        if(!is_numeric($request->amount)){
            $msg = array(
                "status" => "error",
                "message" => "Undefined Amount!"
            );
           TransferWalletHelper::saveLog('TransferIn Undefined Amount', $this->provider_db_id,json_encode($request->all()), $msg);
            return response($msg,200)->header('Content-Type', 'application/json');
        }

        if($balance < $request->amount){
            $msg = array(
                "status" => "error",
                "message" => "Not Enough Balance",
            );
           TransferWalletHelper::saveLog('TransferIn Low Balance', $this->provider_db_id,json_encode($request->all()), $msg);
            return response($msg,200)->header('Content-Type', 'application/json');
        }

        // if($client_details->default_currency != 'CNY'){
        //     $msg = array(
        //         "status" => "error",
        //         "message" => "Currency Not Supported",
        //     );
        //    TransferWalletHelper::saveLog('TransferIn Currency Not Supported', $this->provider_db_id,json_encode($request->all()), $msg);
        //     return response($msg,200)->header('Content-Type', 'application/json');
        // }

        // $json_data = array(
        //     "transid" => "GFTID".Carbon::now()->timestamp,
        //     "amount" => $request->amount,
        //     "roundid" => 0,
        // );

        $json_data = array(
            "transid" => $request->token,
            "amount" => $request->amount,
            "roundid" => 0,
        );

        $token_id = $client_details->token_id;
        $bet_amount = $request->amount;
        $pay_amount = 0;
        $win_or_lost = 0;
        $method = 1; 
        $payout_reason = 'Transfer IN Debit';
        $income = $bet_amount - $pay_amount;
        $round_id = $json_data['roundid'];
        $provider_trans_id = $json_data['transid'];
        $game_transaction_type = 1;

       TransferWalletHelper::saveLog('TransferIn', $this->provider_db_id,json_encode($request->all()), 'Golden IF HIT');
        $game = TransferWalletHelper::getGameTransaction($request->token,$json_data["roundid"]);
        if(!$game){
            // $gamerecord  = ProviderHelper::createGameTransaction($token_id, $game_details->game_code, $bet_amount,  $pay_amount, $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id, $round_id);
            // $game_transextension = TransferWalletHelper::createGameTransExtV2($gamerecord,$provider_trans_id, $round_id, $bet_amount, $game_transaction_type);
            $gamerecord = TransferWalletHelper::createGameTransaction('debit', $json_data, $game_details, $client_details); 
        }
        else{
            $gameupdate = TransferWalletHelper::updateGameTransaction($game,$json_data,"debit");
            $gamerecord = $game->game_trans_id;
        }

        

        $game_transextension = TransferWalletHelper::createGameTransExtV2($gamerecord,$provider_trans_id, $round_id, $bet_amount, $game_transaction_type);

        try {
          TransferWalletHelper::saveLog('TransferIn fundTransfer', $this->provider_db_id,json_encode($request->all()), 'Client Request');
           // $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount,$game_details->game_code,$game_details->game_name,$game_transextension,$gamerecord,"debit");
           $client_response = ClientRequestHelper::fundTransfer($client_details,$request->amount,$game_details->game_code,$game_details->game_name,$game_transextension,$gamerecord,"debit");
          TransferWalletHelper::saveLog('TransferIn fundTransfer', $this->provider_db_id,json_encode($request->all()), 'Client Responsed');
           
        } catch (\Exception $e) {
            $response = ["status" => "Server Timeout", "statusCode" =>  1, 'msg' => $e->getMessage()];
            if(isset($gamerecord)){
                TransferWalletHelper::updateGameTransactionStatus($gamerecord, 2, 99);
                TransferWalletHelper::updatecreateGameTransExt($game_transextension, 'FAILED', json_encode($response), 'FAILED', $e->getMessage(), 'FAILED', 'FAILED');
            }
           TransferWalletHelper::saveLog('TransferIn', $this->provider_db_id,json_encode($request->all()), $response);
            return $response;
        }

        if(isset($client_response->fundtransferresponse->status->code) && $client_response->fundtransferresponse->status->code == "200"){
            try {
                $http = new Client();
                $response = $http->post(GoldenFHelper::changeEnvironment($client_details)->api_url."/TransferIn",[
                   'form_params' => [
                    'secret_key' => GoldenFHelper::changeEnvironment($client_details)->secret_key,
                    'operator_token' => GoldenFHelper::changeEnvironment($client_details)->operator_token,
                    'player_name' => "TG_".$client_details->player_id,
                    'amount' => $request->amount,
                    'wallet_code' => GoldenFHelper::changeEnvironment($client_details)->wallet_code
                    ]
                ]);
                $golden_response = json_decode((string) $response->getBody(), true);
                TransferWalletHelper::saveLog('GoldenF TransferIn Success', $this->provider_db_id,json_encode($request->all()), $golden_response);

                # TransferWallet
                $token = SessionWalletHelper::checkIfExistWalletSession($request->token);
                if ($token == false) { // This token doesnt exist in wallet_session
                    SessionWalletHelper::createWalletSession($request->token, $request->all());
                }

            } catch (\Exception $e) {
                $response = ["status" => "error", 'message' => $e->getMessage()];
                if(isset($gamerecord)){
                    TransferWalletHelper::updateGameTransactionStatus($gamerecord, 2, 99);
                    TransferWalletHelper::updatecreateGameTransExt($game_transextension, 'FAILED', json_encode($response), 'FAILED', $e->getMessage(), 'FAILED', 'FAILED');
                }
               TransferWalletHelper::saveLog('GoldenF TransferIn Failed', $this->provider_db_id,json_encode($request->all()), $response);
                return $response;
            }

            SessionWalletHelper::updateSessionTime($request->token);
            $msg = array(
                "status" => "ok",
                "message" => "Transaction success",
                "balance" => round($client_response->fundtransferresponse->balance,2)
            );

            // $entry_id = 1; // Debit/Bet
            // ProviderHelper::updateGameTransaction($gamerecord, $pay_amount, $income, $win_or_lost, $entry_id);
            TransferWalletHelper::updatecreateGameTransExt($game_transextension, $data, $msg, $client_response->requestoclient, $client_response, $response, 'NO DATA');
            
            return response($msg,200)
                ->header('Content-Type', 'application/json');
        }
        elseif(isset($client_response->fundtransferresponse->status->code) && $client_response->fundtransferresponse->status->code == "402"){
            $msg = array(
                "status" =>8,
                "message" => array(
                    "text"=>"Insufficient funds",
                )
            ); 
            return response($msg,200)
            ->header('Content-Type', 'application/json');
        }

    }

    public function TransferOut(Request $request)
    {
       TransferWalletHelper::saveLog('GoldenF TransferOut Success', $this->provider_db_id,json_encode($request->all()), 'Closed triggered');
        $data = $request->all();
        $game_details = TransferWalletHelper::getInfoPlayerGameRound($request->token);
        if ($game_details == false) {
            $msg = array("status" => "error", "message" => "Game Not Found");
            TransferWalletHelper::saveLog('TransferWallet TransferOut FAILED', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }

        $json_data = array(
            "transid" => "GFTID".Carbon::now()->timestamp,
            // "amount" => $request->amount,
            "roundid" => 0,
        );

        $client_details = TransferWalletHelper::getClientDetails('token', $request->token);
        if ($client_details == 'false') {
            $msg = array("status" => "error", "message" => "Invalid Token or Token not found");
            TransferWalletHelper::saveLog('TransferWallet TransferOut FAILED', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }

        try {
            $http = new Client();
            $response = $http->post(GoldenFHelper::changeEnvironment($client_details)->api_url."/GetPlayerBalance",[
               'form_params' => [
                'secret_key' => GoldenFHelper::changeEnvironment($client_details)->secret_key,
                'operator_token' => GoldenFHelper::changeEnvironment($client_details)->operator_token,
                'player_name' => "TG_".$client_details->player_id,
                'wallet_code' => GoldenFHelper::changeEnvironment($client_details)->wallet_code
                ]
            ]);
            $golden_response_balance = json_decode((string) $response->getBody(), true);
           TransferWalletHelper::saveLog('GoldenF TransferOut GetPlayerBalance Success', $this->provider_db_id,json_encode($request->all()), $golden_response_balance);
            if(isset($golden_response_balance['data']['action_result']) && $golden_response_balance['data']['action_result'] == 'Success'){
                $TransferOut_amount = $golden_response_balance['data']['balance'];
            }else{
                $response = ["status" => "error", 'message' => 'cant connect'];
               TransferWalletHelper::saveLog('GoldenF TransferOut FAILED', $this->provider_db_id,json_encode($request->all()), $response);
                return $response;
            }
        } catch (\Exception $e) {
            $response = ["status" => "error", 'message' => $e->getMessage()];
           TransferWalletHelper::saveLog('GoldenF TransferOut Failed', $this->provider_db_id,json_encode($request->all()), $response);
            return $response;
        }


        if($request->has("token")&&$request->has("player_id")){
              TransferWalletHelper::saveLog('GoldenF TransferOut Processing withrawing', $this->provider_db_id,json_encode($request->all()), $response);
                $client_details = TransferWalletHelper::getClientDetails('token', $request->token);
          
                $game_details = TransferWalletHelper::getInfoPlayerGameRound($request->token);
                $json_data = array(
                    "transid" => Carbon::now()->timestamp,
                    "amount" => $TransferOut_amount,
                    "roundid" => 0,
                    "win"=>1,
                    "payout_reason" => "TransferOut from round",
                );
             
                $game = TransferWalletHelper::getGameTransaction($request->token,$json_data["roundid"]);
                if($game){
                    $gamerecord = $game->game_trans_id;
                }else{
                    SessionWalletHelper::deleteSession($request->token);
                    $response = ["status" => "error", 'message' => 'No Transaction Recorded'];
                    TransferWalletHelper::saveLog('GoldenF TransferOut Failed', $this->provider_db_id,json_encode($request->all()), $response);
                    return $response;
                }

               $token_id = $client_details->token_id;
                $bet_amount = $game->bet_amount;
                $pay_amount = $TransferOut_amount;
                $win_or_lost = 1;
                $method = 2; 
                $payout_reason = 'TransferOut Credit';
                $income = $bet_amount - $pay_amount;
                $round_id = $json_data['roundid'];
                $provider_trans_id = $json_data['transid'];
                $game_transaction_type = 2;

               TransferWalletHelper::saveLog('GoldenF TransferOut Processing withrawing 2', $this->provider_db_id,json_encode($request->all()), 'GG');
                try {
                    $http = new Client();
                    $response = $http->post(GoldenFHelper::changeEnvironment($client_details)->api_url."/TransferOut",[
                       'form_params' => [
                            'secret_key' => GoldenFHelper::changeEnvironment($client_details)->secret_key,
                            'operator_token' => GoldenFHelper::changeEnvironment($client_details)->operator_token,
                            'player_name' => "TG_".$client_details->player_id,
                            'amount' => $TransferOut_amount,
                            'wallet_code' => GoldenFHelper::changeEnvironment($client_details)->wallet_code
                        ]
                    ]);
                    $golden_response_balance = json_decode((string) $response->getBody(), true);
                   TransferWalletHelper::saveLog('GoldenF TransferOut Success Withdraw', $this->provider_db_id,json_encode($request->all()), $golden_response_balance);
                    if(isset($golden_response_balance['data']['action_result']) && $golden_response_balance['data']['action_result'] == 'Success'){

                        try {
                            $game_transextension = TransferWalletHelper::createGameTransExtV2($gamerecord,$provider_trans_id, $round_id, $bet_amount, $game_transaction_type);
                            $client_response = ClientRequestHelper::fundTransfer($client_details,$TransferOut_amount,$game_details->game_code,$game_details->game_name,$game_transextension,$gamerecord,"credit");
                           TransferWalletHelper::saveLog('GoldenF TransferOut Client Request', $this->provider_db_id,json_encode($request->all()), 'Request to client');
                        } catch (\Exception $e) {
                            $response = ["status" => "error", 'message' => $e->getMessage()];
                           TransferWalletHelper::saveLog('GoldenF TransferOut client_response failed', $this->provider_db_id,json_encode($request->all()), $response);
                            return $response;
                        }

                        if(isset($client_response->fundtransferresponse->status->code) && $client_response->fundtransferresponse->status->code == "200"){
                            $msg = array(
                                "status" => "ok",
                                "message" => "Transaction success",
                                "balance"   =>  round($client_response->fundtransferresponse->balance,2)
                            );
                            $gameupdate = TransferWalletHelper::updateGameTransaction($game,$json_data,"credit");
                            TransferWalletHelper::updatecreateGameTransExt($game_transextension, $data, $msg, $client_response->requestoclient, $client_response, $msg, 'NO DATA');

                            $this->getDepositAndWithdrawBets($gamerecord,$client_details);

                           SessionWalletHelper::deleteSession($request->token);
                           TransferWalletHelper::saveLog('GoldenF TransferOut Success Responded', $this->provider_db_id,json_encode($request->all()), 'SUCCESS GOLDENF');
                            return response($msg,200)
                                ->header('Content-Type', 'application/json');
                        }else{
                            $msg = array(
                                "status" => "ok",
                                "message" => "Transaction Failed Unknown Client Response",
                            );
                           TransferWalletHelper::saveLog('GoldenF TransferOut Success Responded but failed', $this->provider_db_id,json_encode($request->all()), 'FAILED GOLDENF');
                            return response($msg,200)
                                ->header('Content-Type', 'application/json');
                        }
                    }else{
                        $response = ["status" => "error", 'message' => 'cant connect'];
                        TransferWalletHelper::saveLog('GoldenF TransferOut Failed Withdraw', $this->provider_db_id,json_encode($request->all()), $response);
                        return $response;
                    }
                } catch (\Exception $e) {
                    $response = ["status" => "error", 'message' => $e->getMessage()];
                   TransferWalletHelper::saveLog('GoldenF TransferOut Failed', $this->provider_db_id,json_encode($request->all()), $response);
                    return $response;
                }
                
        } // END IF
    }

    public function getDepositAndWithdrawBets($game_trans_id,$client_details){

        try{
            $bet_deposit_rounds = DB::table('game_transaction_ext')->where('game_trans_id', $game_trans_id)->where('game_transaction_type', 1)->first();
            $win_deposit_rounds = DB::table('game_transaction_ext')->where('game_trans_id', $game_trans_id)->where('game_transaction_type', 2)->latest()->first();
            $http = new Client();
            $payload = [
                'secret_key' => GoldenFHelper::changeEnvironment($client_details)->secret_key,
                'operator_token' => GoldenFHelper::changeEnvironment($client_details)->operator_token,
                'player_name' =>  "TG_".$client_details->player_id,
                'start_time' => strtotime($bet_deposit_rounds->created_at),
                'end_time' => strtotime($win_deposit_rounds->created_at),
                'count' => 20000,
            ];
            $response = $http->post(GoldenFHelper::changeEnvironment($client_details)->api_url."/Bet/Record/Player/Get",[
             'form_params' => $payload 
            ]);
            $golden_response = json_decode((string) $response->getBody(), true);
            $request_info = [
                'request_body' =>  $payload,
                'response_body' => $golden_response
            ];
            TransferWalletHelper::saveLog('GoldenFBet Records', $this->provider_db_id,json_encode($request_info), 'GOLDENF');
            $game_transaction = [];
            foreach($golden_response['data']['betlogs'] as $bet){

                $game_transaction[$bet['bet_id']]['game_trans_id'] = $game_trans_id;
                $game_transaction[$bet['bet_id']]['provider_trans_id'] = $bet['bet_id'];
                $game_transaction[$bet['bet_id']]['round_id'] = $bet['parent_bet_id'];
                $game_transaction[$bet['bet_id']]['game_id'] = 1;
                // $game_transaction[$bet['bet_id']]['game_id'] = $bet['game_code'];
            
                if($bet['trans_type'] == 'Stake'){
                    $game_transaction[$bet['bet_id']]['bet_amount'] = $bet['bet_amount'];
                }
                if($bet['trans_type'] == 'Payoff'){
                    $game_transaction[$bet['bet_id']]['pay_amount'] = $bet['win_amount'];
                }

                $game_transaction[$bet['bet_id']]['token_id'] = $client_details->token_id;
                $game_transaction[$bet['bet_id']]['income'] = $game_transaction[$bet['bet_id']]['bet_amount']-$bet['win_amount'];

                if($bet['win_amount'] == 0){
                    $game_transaction[$bet['bet_id']]['entry_id'] = 1;
                    $game_transaction[$bet['bet_id']]['win'] = 0;
                }else{
                    $game_transaction[$bet['bet_id']]['entry_id'] = 2;
                    $game_transaction[$bet['bet_id']]['win'] = 1;
                }
            }   
            DB::table('tw_game_transactions')->insert($game_transaction);
            return $golden_response;
        }catch(Exception $e){
            $msg = [
                'msg' => $e->getMessage()
            ];
            TransferWalletHelper::saveLog('GoldenF Bet Records', $this->provider_db_id,json_encode($msg), $e->getMessage());
            return false;
        }
        
    }

    public function BetRecordGet(Request $request)
    {
       TransferWalletHelper::saveLog("GoldenF BetRecordGet req", $this->provider_id, json_encode($request->all()), "");
    }

    public function BetRecordPlayerGet($client_details, $game_transaction_id)
    {
        Helper::saveLog('GoldenF BetRecordPlayerGet', $this->provider_db_id, json_encode($client_details), 'ENDPOINT HIT');
        $client_details = ProviderHelper::getClientDetails('player_id', 10210);
        $http = new Client();
        $response = $http->post(GoldenFHelper::changeEnvironment($client_details)->api_url."/Bet/Record/Player/Get",[
        'form_params' => [
                'secret_key' => GoldenFHelper::changeEnvironment($client_details)->secret_key,
                'operator_token' => GoldenFHelper::changeEnvironment($client_details)->operator_token,
                'player_name' => "TG_48973",
                'start_time' => strtotime('2021-01-23 06:06:29'),
                'end_time' => strtotime('2021-02-23 06:06:29'),
                'count' => 20000,
            ]
        ]);
        $golden_response = json_decode((string) $response->getBody(), true);
        // dd($golden_response['data']['betlogs']);
        $game_transaction = [];
        foreach($golden_response['data']['betlogs'] as $bet){

            $game_transaction[$bet['bet_id']]['game_trans_id'] = 123;
            $game_transaction[$bet['bet_id']]['provider_trans_id'] = $bet['bet_id'];
            $game_transaction[$bet['bet_id']]['round_id'] = $bet['parent_bet_id'];
            $game_transaction[$bet['bet_id']]['game_id'] = $bet['game_code'];
           
            if($bet['trans_type'] == 'Stake'){
                $game_transaction[$bet['bet_id']]['bet_amount'] = $bet['bet_amount'];
            }
            if($bet['trans_type'] == 'Payoff'){
                $game_transaction[$bet['bet_id']]['pay_amount'] = $bet['win_amount'];
            }

            $game_transaction[$bet['bet_id']]['token_id'] = $client_details->token_id;
            $game_transaction[$bet['bet_id']]['income'] = $game_transaction[$bet['bet_id']]['bet_amount']-$bet['win_amount'];

            if($bet['win_amount'] == 0){
                 $game_transaction[$bet['bet_id']]['entry_id'] = 1;
                 $game_transaction[$bet['bet_id']]['win'] = 0;
            }else{
                $game_transaction[$bet['bet_id']]['entry_id'] = 2;
                $game_transaction[$bet['bet_id']]['win'] = 1;
            }
        }   
        DB::table('tw_game_transactions')->insert($game_transaction);
        return $golden_response;

        // $client_details = ProviderHelper::getClientDetails('player_id', 10210);
        // $http = new Client();
        // $response = $http->post(GoldenFHelper::changeEnvironment($client_details)->api_url."/Bet/Record/Player/Get",[
        // 'form_params' => [
        //         'secret_key' => GoldenFHelper::changeEnvironment($client_details)->secret_key,
        //         'operator_token' => GoldenFHelper::changeEnvironment($client_details)->operator_token,
        //         'player_name' => "TG_48973",
        //         'start_time' => strtotime('2021-01-23 06:06:29'),
        //         'end_time' => strtotime('2021-02-23 06:06:29'),
        //         'count' => 20000,
        //     ]
        // ]);
        // $golden_response = json_decode((string) $response->getBody(), true);
        // dd($golden_response['data']['betlogs']);
    }

    public function TransactionRecordGet(Request $request)
    {
       TransferWalletHelper::saveLog("GoldenF TransactionRecordGet req", $this->provider_id, json_encode($request->all()), "");
    }

    public function TransactionRecordPlayerGet(Request $request)
    {
       TransferWalletHelper::saveLog("GoldenF TransactionRecordPlayerGet req", $this->provider_id, json_encode($request->all()), "");
    }

    public function BetRecordDetail(Request $request)
    {
       TransferWalletHelper::saveLog("GoldenF BetRecordDetail req", $this->provider_id, json_encode($request->all()), "");
    }







    # Seamless Wallet Type
    /** 
     *  Seamless Wallet Player Ba
     * 
     */
    public function swPlayerBalance(Request $request){

        
        Helper::saveLog('GoldenF swPlayerBalance', $this->provider_db_id, json_encode($request->all()), 'ENDPOINT HIT');
        $data = json_decode(json_encode($request->all()));

        if(!$request->has("wtoken")){
          return $response = ["data" => null,"code" => 7101, 'msg'=> 'Verification code error'];
        }

        if($request->wtoken != $this->wtoken){
          return $response = ["data" => null,"code" => 7101, 'msg'=> 'Verification code error'];
        }

        $client_details = ProviderHelper::getClientDetails('player_id', $data->member_account);

        if($client_details == null || $client_details == 'false'){
            return $response = ["data" => null,"code" => 7103, 'msg'=> 'Member code does not exist'];
        }
        // $holdPlayerBalance = DB::table('player_balance')->where('player_id', $client_details->player_id)->latest()->first();
        // return (double)$client_details->balance.' '.(double)$holdPlayerBalance->balance;
        
        // dd($client_details->balance);
        
        // GoldenFHelper::holdPlayerBalance($client_details, 10);
        $calculatedbalance = GoldenFHelper::calculatedbalance($client_details);
        $response = [
            "data" => [
                "member_account" => $client_details->player_id,
                "vendor_code" => $data->vendor_code,
                "balance" => $this->formatAmounts($calculatedbalance),
            ],
            "code" => 0
        ];
        Helper::saveLog('GoldenF swPlayerBalance', $this->provider_db_id, json_encode($request->all()), $response);
        return $response;
    }


    /** 
     * BET/DEBIT
     */
 public function swTransferOut(Request $request){

        Helper::saveLog('GoldenF swTransferOut', $this->provider_db_id, json_encode($request->all()), 'ENDPOINT HIT');
        $data = json_decode(json_encode($request->all()));

        if(!$request->has("wtoken")){
           return $response = ["data" => null,"code" => 7101, 'msg'=> 'Verification code error'];
        }
        if($request->wtoken != $this->wtoken){
          return $response = ["data" => null,"code" => 7101, 'msg'=> 'Verification code error'];
        }

        $client_details = ProviderHelper::getClientDetails('player_id', $data->member_account);
        if($client_details == null || $client_details == 'false'){
            return $response = ["data" => null,"code" => 7103, 'msg'=> 'Member code does not exist'];
        }

        $game_information = Helper::getInfoPlayerGameRound($client_details->player_token);

        $general_details = ["aggregator" => [], "provider" => [], "client" => []];
        $general_details['client']['before_balance'] = ProviderHelper::amountToFloat($client_details->balance);
        $round_id = $data->bet_id;
        $provider_trans_id = $data->trace_id;
        $vendor_code = $data->vendor_code;
        $trans_type = $data->trans_type;
        
        $bet_amount  = $data->amount;
        $pay_amount =  0; 
        $method = 1;
        $income = $bet_amount-$pay_amount;
        $entry_id = 1;
        $game_transaction_type = 1;
        $win_or_lost = 5; // 0 lost,  5 processing
        $payout_reason = 'Game Bets and Win';
        $game_code = $game_information->game_id;
        $token_id = $client_details->token_id;
       
        // $game_ext_check = ProviderHelper::findGameExt($round_id, 1, 'round_id');
        $game_ext_check = $this->findGameExt($round_id, 1, 'round_id', $client_details);
        // $game_ext_check = $game_ext_check[0];
        // dd($game_ext_check->connection_name);
        if($game_ext_check != 'false'){ // Duplicate transaction
            $client_details->connection_name = $game_ext_check->connection_name;
            if($game_ext_check->transaction_detail != '"FAILED"' && $game_ext_check->transaction_detail != 'FAILED'){
                return $response = ["data" => null,"code" => 7104, 'msg' => 'Duplicate Transaction'];
            }
        }
        $gameTransactionData = array(
                "provider_trans_id" => $provider_trans_id,
                "token_id" => $token_id,
                "game_id" => $game_code,
                "round_id" => $round_id,
                "bet_amount" => $bet_amount,
                "pay_amount" => $pay_amount,
                "income" => $income,
                "win" => $win_or_lost,
                "entry_id" => $method,
            );
        $gamerecord = GameTransactionMDB::createGametransaction($gameTransactionData,$client_details);
        // $gamerecord  = ProviderHelper::createGameTransaction($token_id, $game_code, $bet_amount,  $pay_amount, $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id, $round_id);
        $gametransactionext = array(
                "game_trans_id" => $gamerecord,
                "provider_trans_id" => $provider_trans_id,
                "round_id" =>$round_id,
                "amount" => $bet_amount,
                "game_transaction_type"=> $game_transaction_type,
                "provider_request" => json_encode($data),
            );
        $game_transextension = GameTransactionMDB::createGameTransactionExt($gametransactionext,$client_details);
        // $game_transextension = ProviderHelper::createGameTransExtV2($gamerecord,$provider_trans_id, $round_id, $bet_amount, $game_transaction_type,$data);

        try {
            $client_response = ClientRequestHelper::fundTransfer($client_details,abs($bet_amount),$game_information->game_code,$game_information->game_name,$game_transextension,$gamerecord, 'debit');
        } catch (\Exception $e) {
            $response = ["data" => null,"code" => 7000, 'msg'=> 'Server Error'];
            // ProviderHelper::updateGameTransactionStatus($gamerecord, 2, 99);

            $dataToUpdate = [
                "provider_request" => $data,
                "mw_response" => json_encode($response),
                "mw_request"=> 'FAILED',
                "client_response" => $e->getMessage(),
                "transaction_detail" => 'FAILED',
                "general_details" => $general_details
            ];
            GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$game_transextension,$client_details);
            // ProviderHelper::updatecreateGameTransExt($game_transextension, $data, json_encode($response), 'FAILED', $e->getMessage(), 'FAILED', $general_details);
            ProviderHelper::saveLog('GoldenF FATAL ERROR', $this->provider_db_id, $response, $e->getMessage());
            return $response;
        }

        if(isset($client_response->fundtransferresponse->status->code) 
             && $client_response->fundtransferresponse->status->code == "200"){

            ProviderHelper::_insertOrUpdate($client_details->token_id, $this->formatAmounts($client_response->fundtransferresponse->balance));
            $response = [
                "balance" => $client_response->fundtransferresponse->balance,
                "status" => "success",
                "statusCode" =>  0
            ];
            $response = [
                "data" => [
                    "member_account" => $client_details->player_id,
                    "vendor_code" => $request->vendor_code,
                    "trans_type" => $request->trans_type,
                    "before_balance" => $general_details['client']['before_balance'],
                    "amount" =>  $bet_amount,
                    "balance" => $this->formatAmounts($client_response->fundtransferresponse->balance),
                ],
                "code" => 0
            ];
            $general_details['provider']['trans_at'] = strtotime(date('Y-m-d H:i:s'));
            $general_details['provider']['trace_id'] = $provider_trans_id;
            $general_details['provider']['member_account'] = $client_details->player_id;
            $general_details['provider']['vendor_code'] = $vendor_code;
            $general_details['provider']['trans_type'] = $trans_type;
            $general_details['provider']['before_balance'] = $this->formatAmounts($general_details['client']['before_balance']);
            $general_details['provider']['amount'] =  $this->formatAmounts($bet_amount);
            $general_details['provider']['balance'] = $this->formatAmounts($client_response->fundtransferresponse->balance);
            $dataToUpdate = [
                "provider_request" => json_encode($data),
                "mw_response" => json_encode($response),
                "mw_request"=> json_encode($client_response->requestoclient),
                "client_response" => json_encode($client_response),
                "transaction_detail" => json_encode($response),
                "general_details" => json_encode($general_details)
            ];
            GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$game_transextension,$client_details);
            // ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $response, $client_response->requestoclient, $client_response, $response,$general_details);
        }elseif(isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "402"){
            ProviderHelper::updateGameTransactionStatus($gamerecord, 2, 6);
            $response = ["data" => null,"code" => 7202, 'msg' => 'Low Balance'];
            $dataToUpdate = [
                "provider_request" => json_encode($data),
                "mw_response" => json_encode($response),
                "mw_request"=> 'FAILED',
                "client_response" => json_encode($client_response),
                "transaction_detail" => 'FAILED',
                "general_details" => json_encode($general_details)
            ];
            GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$game_transextension,$client_details);
            // ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $response,'FAILED', $client_response, 'FAILED', $general_details);
        }
        Helper::saveLog('GoldenF swTransferOut', $this->provider_db_id, json_encode($request->all()), $response);
        return $response;
    }
    

    public function swTransferIn(Request $request){
        Helper::saveLog('GoldenF swTransferIn', $this->provider_db_id, json_encode($request->all()), 'ENDPOINT HITT');
        $data = json_decode(json_encode($request->all()));

        if(!$request->has("wtoken")){
            return $response = ["data" => null,"code" => 7101, 'msg'=> 'Verification code error'];
        }
        if($request->wtoken != $this->wtoken){
        return $response = ["data" => null,"code" => 7101, 'msg'=> 'Verification code error'];
        }

        $client_details = ProviderHelper::getClientDetails('player_id', $data->member_account);
        if($client_details == null || $client_details == 'false'){
            return $response = ["data" => null,"code" => 7103, 'msg'=> 'Member code does not exist'];
        }

        $game_information = Helper::getInfoPlayerGameRound($client_details->player_token);

        $general_details = ["aggregator" => [], "provider" => [], "client" => []];
        $general_details['client']['before_balance'] = ProviderHelper::amountToFloat($client_details->balance);
        //dd($client_details->balance);
        $round_id = $data->bet_id;
        $provider_trans_id = $data->trace_id;
        $vendor_code = $data->vendor_code;
        $trans_type = $data->trans_type; 

        $isWinGreaterThanHoldedBalance = GoldenFHelper::isWinGreaterThanHoldedBalance($client_details, $data->amount);
        if($isWinGreaterThanHoldedBalance){
            $amount  = GoldenFHelper::alterWinAmount($client_details, $data->amount);
        }else{
            $amount  = 0;
        }

        $calculatedbalance = GoldenFHelper::calculatedbalance($client_details, $data->amount);

        // dd(GoldenFHelper::getHoldPlayerBalanceToBeDeducted($client_details));
        // $amount  = $data->amount;
        $pay_amount =  0; 
        $method = 1;
        $income = $amount-$pay_amount;
        $entry_id = 1;
        $game_transaction_type = 2;
        $win_or_lost = 5; // 0 lost,  5 processing
        $payout_reason = 'Game Bets and Win';
        $game_code = $game_information->game_id;
        $token_id = $client_details->token_id;
        //dd($client_details);
        $game_ext_check = $this->findGameExt($provider_trans_id, 2, 'transaction_id', $client_details);
        // $game_ext_check = ProviderHelper::findGameExt($provider_trans_id, 2, 'transaction_id');
        // $game_ext_check = ProviderHelper::findGameExt($round_id, 2, 'round_id');
        if($game_ext_check != 'false'){ // Duplicate transaction
            $client_details->connection_name = $game_ext_check->connection_name;
            if($game_ext_check->transaction_detail != '"FAILED"' && $game_ext_check->transaction_detail != 'FAILED'){
                return $response = ["data" => null,"code" => 7000, 'msg' => 'Duplicate Transaction'];
            }
        }
        
        $game_ext_check = $this->findGameExt($round_id, 1, 'round_id', $client_details);
        // dd($game_ext_check);
        // $game_ext_check = ProviderHelper::findGameExt($round_id, 1, 'round_id');
        if($game_ext_check == 'false'){ // Duplicate transaction
            return $response = ["data" => null,"code" => 7105, 'msg' => 'No results'];
        }
        $gamerecord = $game_ext_check->game_trans_id;
        $existing_bet = GameTransactionMDB::findGameTransactionDetails($gamerecord, 'game_transaction', false, $client_details);
        // $existing_bet = ProviderHelper::findGameTransaction($gamerecord,'game_transaction');
        $holded_balance = GoldenFHelper::getHoldPlayerBalanceToBeDeducted($client_details);
        $client_details->connection_name = $game_ext_check->connection_name;
        // update while on other server
       
        // $gamerecord  = ProviderHelper::createGameTransaction($token_id, $game_code, $amount,  $pay_amount, $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id, $round_id);
        $gametransactionext = array(
                "game_trans_id" => $gamerecord,
                "provider_trans_id" => $provider_trans_id,
                "round_id" =>$round_id,
                "amount" => $amount,
                "game_transaction_type"=> $game_transaction_type,
                "provider_request" => json_encode($data),
            );
        $game_transextension = GameTransactionMDB::createGameTransactionExt($gametransactionext,$client_details);
        // $game_transextension = ProviderHelper::createGameTransExtV2($gamerecord,$provider_trans_id, $round_id, $amount, $game_transaction_type,$data);
        $client_response = ClientRequestHelper::fundTransfer($client_details,abs($amount),$game_information->game_code,$game_information->game_name,$game_transextension,$gamerecord, 'credit');
        if(isset($client_response->fundtransferresponse->status->code) 
             && $client_response->fundtransferresponse->status->code == "200"){
            $bet_amount = $existing_bet->bet_amount;
            $pay_amount =  $existing_bet->pay_amount + $amount; //abs($data['amount']);
            $income = $bet_amount - $pay_amount;
    
            if($pay_amount > 0){
                $entry_id = 2; // Credit
                $win_or_lost = 1; // 0 lost,  5 processing
            }else{
                $entry_id = 1; // Debit
                $win_or_lost = 0; // 0 lost,  5 processing
            }
            $game_transaction_type = 2; // 1 Bet, 2 Win
            ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
            $response = [
                "data" => [
                    "member_account" => $client_details->player_id,
                    "vendor_code" => $request->vendor_code,
                    "trans_type" => $request->trans_type,
                    "before_balance" => $this->formatAmounts(GoldenFHelper::calculatedbalance($client_details)),
                    "amount" =>  $this->formatAmounts($data->amount),
                    "balance" => $this->formatAmounts($calculatedbalance),
                ],
                "code" => 0
            ];
            $after_balance =$calculatedbalance;
            $trans_data = array(
                  'win' => $win_or_lost,
                  'pay_amount' => $pay_amount,
                  'income' => $income,
                  'entry_id' => $entry_id,
                  // 'trans_status' => 2
            );
            GameTransactionMDB::updateGametransaction($trans_data,$gamerecord,$client_details);
            // ProviderHelper::updateGameTransaction($gamerecord, $pay_amount, $income, $win_or_lost, $entry_id);
        }elseif(isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "402"){
            $response = [
                "data" => [
                    "member_account" => $client_details->player_id,
                    "vendor_code" => $request->vendor_code,
                    "trans_type" => $request->trans_type,
                    "before_balance" => $this->formatAmounts(GoldenFHelper::calculatedbalance($client_details)),
                    "amount" =>  $this->formatAmounts($data->amount),
                    "balance" => $this->formatAmounts($calculatedbalance),
                ],
                "code" => 0
            ];
            $after_balance = $calculatedbalance;
            Providerhelper::createRestrictGame($game_information->game_id,$client_details->player_id,$game_transextension, $client_response->requestoclient);
            Helper::saveLog('GoldenF swTransferIn', $this->provider_db_id, json_encode($request->all()), $response);
            return $response;
        }

        $general_details['provider']['trans_at'] = strtotime(date('Y-m-d H:i:s'));
        $general_details['provider']['trace_id'] = $provider_trans_id;
        $general_details['provider']['member_account'] = $client_details->player_id;
        $general_details['provider']['vendor_code'] = $vendor_code;
        $general_details['provider']['trans_type'] = $trans_type;
        $general_details['provider']['before_balance'] = $this->formatAmounts(GoldenFHelper::calculatedbalance($client_details));
        $general_details['provider']['amount'] =  $this->formatAmounts($data->amount);
        $general_details['provider']['balance'] = $this->formatAmounts($after_balance);
        if($calculatedbalance > 0){
            GoldenFHelper::updateHoldPlayerBalance($client_details);
            ProviderHelper::_insertOrUpdate($client_details->token_id, $this->formatAmounts($calculatedbalance));
            // dd(1);
        }else{
            ProviderHelper::_insertOrUpdate($client_details->token_id, 0);
            GoldenFHelper::updateHoldPlayerBalance($client_details, $this->formatAmounts($calculatedbalance), 'custom');
            // dd(2);
        }
        $dataToUpdate = [
            "provider_request" => json_encode($data),
            "mw_response" => json_encode($response),
            "mw_request"=> json_encode($client_response->requestoclient),
            "client_response" => json_encode($client_response),
            "transaction_detail" => json_encode($response),
            "general_details" => json_encode($general_details)
        ];
        GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$game_transextension,$client_details);
        // ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $response, $client_response->requestoclient, $client_response, $response,$general_details);
        Helper::saveLog('GoldenF swTransferIn', $this->provider_db_id, json_encode($request->all()), $response);
        return $response;
    }
    

    /**
     * Refund Cancel Payoff
     */
    public function swforceTransferOut(Request $request){
        Helper::saveLog('GoldenF swforceTransferOut', $this->provider_db_id, json_encode($request->all()), 'ENDPOINT HIT');
        $data = json_decode(json_encode($request->all()));

        if(!$request->has("wtoken")){
           return $response = ["data" => null,"code" => 7101, 'msg'=> 'Verification code error'];
        }
        if($request->wtoken != $this->wtoken){
          return $response = ["data" => null,"code" => 7101, 'msg'=> 'Verification code error'];
        }

        $client_details = ProviderHelper::getClientDetails('player_id', $data->member_account);
        if($client_details == null || $client_details == 'false'){
            return $response = ["data" => null,"code" => 7103, 'msg'=> 'Member code does not exist'];
        }

        $game_information = Helper::getInfoPlayerGameRound($client_details->player_token);

        $general_details = ["aggregator" => [], "provider" => [], "client" => []];
        $general_details['client']['before_balance'] = ProviderHelper::amountToFloat($client_details->balance);
        $round_id = $data->bet_id;
        $provider_trans_id = $data->trace_id;
        $vendor_code = $data->vendor_code;
        $trans_type = $data->trans_type; 
        
        $amount  = $data->amount;
        $pay_amount =  0; 
        $method = 1;
        $income = $amount-$pay_amount;
        $entry_id = 1;
        $game_transaction_type = 3;
        $win_or_lost = 5; // 0 lost,  5 processing
        $payout_reason = 'Game Refund Win';
        $game_code = $game_information->game_id;
        $token_id = $client_details->token_id;
       
        $calculatedbalance = GoldenFHelper::calculatedbalance($client_details, $data->amount);

        // $game_ext_check = ProviderHelper::findGameExt($round_id, 3, 'round_id');
        // if($game_ext_check != 'false'){ // Duplicate transaction
        //     if($game_ext_check->transaction_detail != '"FAILED"' && $game_ext_check->transaction_detail != 'FAILED'){
        //         return $response = ["data" => null,"code" => 7000, 'msg' => 'Duplicate Transaction'];
        //     }
        // }

        // $game_ext_check = ProviderHelper::findGameExt($round_id, 1, 'round_id');
        $game_ext_check = $this->findGameExt($round_id, 1, 'round_id', $client_details);
        if($game_ext_check == 'false'){ // Duplicate transaction
            $client_details->connection_name = $game_ext_check->connection_name;
            return $response = ["data" => null,"code" => 7105, 'msg' => 'No results'];
        }

        if($trans_type == 'cancelPayoff'){  # cancelPayoff | cancelStake
            $transaction_type = 'debit';
            if($client_details->balance < $data->amount){
                $hold_balance = abs($client_details->balance - $data->amount);
            }
        }else{
            $transaction_type = 'credit';
            $existing_hold_balance =  GoldenFHelper::getHoldPlayerBalanceToBeDeducted($client_details);
            if($existing_hold_balance != 0){
                $isWinGreaterThanHoldedBalance = GoldenFHelper::isWinGreaterThanHoldedBalance($client_details, $data->amount);
                if($isWinGreaterThanHoldedBalance){
                    $amount  = GoldenFHelper::alterWinAmount($client_details, $data->amount);
                }else{
                    $amount  = $data->amount; # false isWinGreaterThanHoldedBalance
                }
            }
        }

        // dd($client_details->balance);
        // dd($data->amount);
        $gamerecord = $game_ext_check->game_trans_id;
        $existing_bet = GameTransactionMDB::findGameTransactionDetails($gamerecord, 'game_transaction', false, $client_details);
        // $existing_bet = ProviderHelper::findGameTransaction($gamerecord,'game_transaction');
        $client_details->connection_name = $game_ext_check->connection_name;
        // $gamerecord  = ProviderHelper::createGameTransaction($token_id, $game_code, $amount,  $pay_amount, $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id, $round_id);
            $gametransactionext = array(
                    "game_trans_id" => $gamerecord,
                    "provider_trans_id" => $provider_trans_id,
                    "round_id" =>$round_id,
                    "amount" => $amount,
                    "game_transaction_type"=> $game_transaction_type,
                    "provider_request" => json_encode($data),
                );
            $game_transextension = GameTransactionMDB::createGameTransactionExt($gametransactionext,$client_details);
        // $game_transextension = ProviderHelper::createGameTransExtV2($gamerecord,$provider_trans_id, $round_id, $amount, $game_transaction_type,$data);
        try {
            $client_response = ClientRequestHelper::fundTransfer($client_details,abs($amount),$game_information->game_code,$game_information->game_name,$game_transextension,$gamerecord, $transaction_type, true);
        } catch (\Exception $e) {
            $response = ["data" => null, "code" => 7000, 'msg'=>'Server Internal Error'];
            // ProviderHelper::updateGameTransactionStatus($gamerecord, 2, 99);
            $dataToUpdate = [
                "provider_request" => json_encode($data),
                "mw_response" => json_encode($response),
                "mw_request"=> 'FAILED',
                "client_response" => json_encode($e->getMessage()),
                "transaction_detail" =>  'FAILED',
                "general_details" => json_encode($general_details)
            ];
            GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$game_transextension,$client_details);
            // ProviderHelper::updatecreateGameTransExt($game_transextension, $data, json_encode($response), 'FAILED', $e->getMessage(), 'FAILED', $general_details);
            ProviderHelper::saveLog('GoldenF FATAL ERROR', $this->provider_db_id, $response, $e->getMessage());
            // Providerhelper::createRestrictGame($game_information->game_id,$client_details->player_id,$game_transextension, $client_response->requestoclient);
            return $response;
        }

        if(isset($client_response->fundtransferresponse->status->code) 
             && $client_response->fundtransferresponse->status->code == "200"){
            // ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
            $bet_amount = $existing_bet->bet_amount;
            $pay_amount =  $existing_bet->pay_amount - $amount; //abs($data['amount']);
            $income = $bet_amount - $pay_amount;
    
            if($pay_amount > 0){
                $entry_id = 2; // Credit
                $win_or_lost = 1; // 0 lost,  5 processing
            }else{
                $entry_id = 1; // Debit
                $win_or_lost = 4; // 0 lost,  5 processing
            }
            $game_transaction_type = 2; // 1 Bet, 2 Win

            if($transaction_type == 'credit'){  # cancelPayoff | cancelStake
                if($existing_hold_balance != 0){
                    if($isWinGreaterThanHoldedBalance){
                        $before_balance = GoldenFHelper::calculatedbalance($client_details);
                        $balance = GoldenFHelper::calculatedbalance($client_details, abs($amount));
                    }else{
                        $before_balance = GoldenFHelper::calculatedbalance($client_details);
                        $balance = GoldenFHelper::calculatedbalance($client_details, abs($amount));
                    }
                }else{
                    $before_balance = GoldenFHelper::calculatedbalance($client_details);
                    $balance = GoldenFHelper::calculatedbalance($client_details, abs($amount));
                }
            }else{
                if($client_details->balance < $data->amount){
                    GoldenFhelper::addHoldBalancePlayer($client_details, $hold_balance);
                    DB::table('players')->where('player_id', $client_details->player_id)->update(['player_status' => 2]);
                }
                $before_balance = GoldenFHelper::calculatedbalance($client_details);
                $balance = GoldenFHelper::calculatedbalance($client_details, $amount, 'debit');
            }
            $response = [
                "data" => [
                    "member_account" => $client_details->player_id,
                    "vendor_code" => $request->vendor_code,
                    "trans_type" => $request->trans_type,
                    "before_balance" => $this->formatAmounts($before_balance),
                    "amount" =>  $this->formatAmounts($amount),
                    "balance" => $this->formatAmounts($balance),
                ],
                "code" => 0
            ];
            $general_details['provider']['trans_at'] = strtotime(date('Y-m-d H:i:s'));
            $general_details['provider']['trace_id'] = $provider_trans_id;
            $general_details['provider']['member_account'] = $client_details->player_id;
            $general_details['provider']['vendor_code'] = $vendor_code;
            $general_details['provider']['trans_type'] = $trans_type;
            $general_details['provider']['before_balance'] = $this->formatAmounts($before_balance);
            $general_details['provider']['amount'] =  $this->formatAmounts($amount);
            $general_details['provider']['balance'] = $this->formatAmounts($balance);
            // ProviderHelper::updateGameTransaction($gamerecord, $pay_amount, $income, $win_or_lost, $entry_id);
            $dataToUpdate = [
                "provider_request" => json_encode($data),
                "mw_response" => json_encode($response),
                "mw_request"=> json_encode($client_response->requestoclient),
                "client_response" => json_encode($client_response),
                "transaction_detail" =>  json_encode($response),
                "general_details" => json_encode($general_details)
            ];
            GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$game_transextension,$client_details);
            // ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $response, $client_response->requestoclient, $client_response, $response,$general_details);
        }elseif(isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "402"){
            # NEED TO HOLD THE AMOUNT TO (player_balance)
            if($transaction_type == 'credit'){  # cancelPayoff | cancelStake
                if($existing_hold_balance != 0){
                    if($isWinGreaterThanHoldedBalance){
                        $before_balance = GoldenFHelper::calculatedbalance($client_details);
                        $balance = GoldenFHelper::calculatedbalance($client_details, abs($amount));
                    }else{
                        $before_balance = GoldenFHelper::calculatedbalance($client_details);
                        $balance = GoldenFHelper::calculatedbalance($client_details, abs($amount));
                    }
                }
            }else{
                $before_balance = GoldenFHelper::calculatedbalance($client_details);
                if($client_details->balance < $data->amount){
                    GoldenFhelper::addHoldBalancePlayer($client_details, $hold_balance);
                    ProviderHelper::_insertOrUpdate($client_details->token_id, 0);
                    $balance = $client_details->balance - $data->amount;
                    // $msg = ['bb' => GoldenFHelper::calculatedbalance($client_details), 'cb' => $client_details->balance, 'amount'=>$data->amount];
                }else{
                    $balance = GoldenFHelper::calculatedbalance($client_details) - $data->amount;
                }
                
            }

            $response = [
                "data" => [
                    "member_account" => $client_details->player_id,
                    "vendor_code" => $request->vendor_code,
                    "trans_type" => $request->trans_type,
                    "before_balance" => $this->formatAmounts($before_balance),
                    "amount" =>  $this->formatAmounts($amount),
                    "balance" => $this->formatAmounts($balance),
                ],
                "code" => 0,
                // "msg" => $msg
            ];
            $general_details['provider']['trans_at'] = strtotime(date('Y-m-d H:i:s'));
            $general_details['provider']['trace_id'] = $provider_trans_id;
            $general_details['provider']['member_account'] = $client_details->player_id;
            $general_details['provider']['vendor_code'] = $vendor_code;
            $general_details['provider']['trans_type'] = $trans_type;
            $general_details['provider']['before_balance'] = $this->formatAmounts($before_balance);
            $general_details['provider']['amount'] =  $this->formatAmounts($amount);
            $general_details['provider']['balance'] = $this->formatAmounts($balance);
            // $response = ["data" => null,"code" => 7202, 'msg' => 'Not Enough Balance'];
            $dataToUpdate = [
                "provider_request" => json_encode($data),
                "mw_response" => json_encode($response),
                "mw_request"=> json_encode($client_response->requestoclient),
                "client_response" => json_encode($client_response),
                "transaction_detail" =>  "TOO_MUCH_ROLLBACK",
                "general_details" => json_encode($general_details)
            ];
            GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$game_transextension,$client_details);
            // ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $response, $client_response->requestoclient, $client_response, "TOO_MUCH_ROLLBACK",$general_details);
            // Helper::saveLog('GoldenF swforceTransferOut', $this->provider_db_id, json_encode($request->all()), $response);
            return $response;
        }
        ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
        Helper::saveLog('GoldenF swforceTransferOut', $this->provider_db_id, json_encode($request->all()), $response);
        return $response;
    }

    public function swQuerytranslog(Request $request){
        Helper::saveLog('GoldenF swQuerytranslog', $this->provider_db_id, json_encode($request->all()), 'ENDPOINT HIT');
        $data = json_decode(json_encode($request->all()));

        if(!$request->has("wtoken")){
            return $response = ["data" => null,"code" => 7101, 'msg'=> 'Verification code error'];
        }
        if($request->wtoken != $this->wtoken){
        return $response = ["data" => null,"code" => 7101, 'msg'=> 'Verification code error'];
        }

        $client_details = ProviderHelper::getClientDetails('player_id', $data->member_account);
        if($client_details == null || $client_details == 'false'){
            return $response = ["data" => null,"code" => 7103, 'msg'=> 'Member code does not exist'];
        }

        $trace_id_information = $this->findTraceID($request->trace_id);
        if($trace_id_information == 'false'){
            return $response = ["data" => null,"code" => 7105];
        }

        $general_details = json_decode($trace_id_information->general_details);
        $response = [
            "data" => [
                "trans_at" => strtotime($trace_id_information->created_at),
                "trace_id" => $general_details->provider->trace_id,
                "member_account" => (string)$general_details->provider->member_account,
                "vendor_code" =>$general_details->provider->vendor_code,
                "trans_type" => $general_details->provider->trans_type,
                "before_balance" => $this->formatAmounts($general_details->provider->before_balance),
                "amount" =>  $this->formatAmounts($general_details->provider->amount),
                "after_balance" => $this->formatAmounts($general_details->provider->balance),
            ],
            "code" => 0
        ];
        Helper::saveLog('GoldenF swQuerytranslog', $this->provider_db_id, json_encode($request->all()), $response);
        return $response;
    }


    public function formatAmounts($number){
       return (double)number_format((float)$number, 2, '.', '');
    }

    // public function rollbackHoldBalanceAsRound($client_details, $betamount, $game_information){
  
    //     $general_details = ["aggregator" => [], "provider" => [], "client" => []];
    //     $provider_trans_id = $client_details->player_id.'_'.$amount;
    //     $round_id = $client_details->player_id.'_'.$amount;

    //     if($freeGames == true){
    //         $bet_amount = 0;
    //         $is_freespin = true;
    //     }else{
    //         $bet_amount = $data->betAmount;
    //         $is_freespin = false;
    //     }

    //     $amount = $betamount;
    //     $win_amount = 0;
    //     $pay_amount =  0;
    //     $method = 1;
    //     $income = $bet_amount - $pay_amount;
    //     $entry_id = 1;
    //     $win_or_lost = 5; // 0 lost,  5 processing
    //     $payout_reason = 'Rollback Hold FUnd';
        
    //     // $session_check = ProviderHelper::getClientDetails('token',$data->sessionId);
    //     // if($session_check == 'false'){
    //     //     return  $response = ["status" => "failed", "statusCode" =>  100];
    //     // }
    //     $client_details = ProviderHelper::getClientDetails('player_id',$data->partnerPlayerId);
    //     if($client_details == 'false'){
    //         return  $response = ["status" => "failed", "statusCode" =>  4];
    //     }
    //     // $player_details = ProviderHelper::playerDetailsCall($client_details);
    //     // if($player_details == 'false'){
    //     //     return  $response = ["status" => "Server Timeout", "statusCode" =>  1];
    //     // }
  
    //     $game_ext_check = ProviderHelper::findGameExt($round_id, 1, 'round_id');
    //     if($game_ext_check != 'false'){ // Duplicate transaction
    //         if($game_ext_check->transaction_detail != '"FAILED"' && $game_ext_check->transaction_detail != 'FAILED'){
    //             return  $response = ["status" => "Duplicate transaction", "statusCode" =>  1];
    //         }
    //     }

    //     $general_details['client']['before_balance'] = ProviderHelper::amountToFloat($client_details->balance);
    //     $game_transaction_type = 1; // 1 Bet, 2 Win
    //     $game_code = $game_information->game_id;
    //     $token_id = $client_details->token_id;

    //     $check_bet_round = ProviderHelper::findGameExt($provider_trans_id, 1, 'transaction_id');
    //     if($check_bet_round != 'false'){
    //       $existing_bet_details = ProviderHelper::findGameTransaction($check_bet_round->game_trans_id, 'game_transaction');
    //       $gamerecord = $existing_bet_details->game_trans_id;
    //       $game_transextension = ProviderHelper::createGameTransExtV2($existing_bet_details->game_trans_id,$provider_trans_id, $round_id, $amount, $game_transaction_type,$data);
    //     }else{
    //        #1 DEBIT OPERATION
    //        $gamerecord  = ProviderHelper::createGameTransaction($token_id, $game_code, $bet_amount,  $pay_amount, $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id, $round_id);
    //        $game_transextension = ProviderHelper::createGameTransExtV2($gamerecord,$provider_trans_id, $round_id, $bet_amount, $game_transaction_type,$data);
    //     }
        
    //     try {
    //       $client_response = ClientRequestHelper::fundTransfer($client_details,abs($bet_amount),$game_information->game_code,$game_information->game_name,$game_transextension,$gamerecord, 'debit');
    //       ProviderHelper::saveLog('KAGaming checkPlay CRID '.$gamerecord, $this->provider_db_id,json_encode($request->all()), $client_response);
           
    //     } catch (\Exception $e) {
    //       $response = ["status" => "Not Enough Balance", "statusCode" =>  200];
    //         if(isset($gamerecord)){
    //             if($check_bet_round == 'false'){
    //                 ProviderHelper::updateGameTransactionStatus($gamerecord, 2, 99);
    //                 ProviderHelper::updatecreateGameTransExt($game_transextension, $data, json_encode($response), 'FAILED', $e->getMessage(), 'FAILED', $general_details);
    //             }
    //         }
    //       return $response;
    //     }
    //     if(isset($client_response->fundtransferresponse->status->code) 
    //          && $client_response->fundtransferresponse->status->code == "200"){
    //             # OLD FLOW
    //             ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
    //             $response = [
    //                 "balance" => $client_response->fundtransferresponse->balance,
    //                 "status" => "success",
    //                 "statusCode" =>  0
    //             ];
    //             ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $response, $client_response->requestoclient, $client_response, $response,$general_details);
    //             #2 CREDIT OPERATION   
    //             $game_transextension_credit = ProviderHelper::createGameTransExtV2($gamerecord,$provider_trans_id, $round_id, $win_amount, 2);
    //             $client_response_credit = ClientRequestHelper::fundTransfer($client_details,abs($win_amount),$game_information->game_code,$game_information->game_name,$game_transextension_credit,$gamerecord, 'credit');

    //             if(isset($client_response_credit->fundtransferresponse->status->code) 
    //              && $client_response_credit->fundtransferresponse->status->code == "402"){
    //                 $response = [
    //                     "balance" => $client_details->balance+$win_amount,
    //                     "status" => "success",
    //                     "statusCode" =>  0
    //                 ];
    //                 Providerhelper::updateGameTransactionExtCustom($game_transextension_credit, $data,$response);
    //                 // Providerhelper::createRestrictGame($game_information->game_id,$client_details->player_id,$game_transextension_credit, $client_response_credit->requestoclient);
    //                 return $response;
    //             }

    //             $general_details['client']['after_balance'] = ProviderHelper::amountToFloat($client_response_credit->fundtransferresponse->balance);
    //             ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response_credit->fundtransferresponse->balance);
    //             $response = [
    //                 "balance" => $client_response_credit->fundtransferresponse->balance,
    //                 "status" => "success",
    //                 "statusCode" =>  0
    //             ];
    //             ProviderHelper::updateGameTransaction($gamerecord, $pay_amount, $income, $win_or_lost, $entry_id);
    //             ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $response, $client_response->requestoclient, $client_response, $response,$general_details);
    //             ProviderHelper::updatecreateGameTransExt($game_transextension_credit, $data, $response, $client_response_credit->requestoclient, $client_response_credit, $response,$general_details);
    //     }elseif(isset($client_response->fundtransferresponse->status->code) 
    //                 && $client_response->fundtransferresponse->status->code == "402"){
    //         if($check_bet_round == 'false'){
    //              if(ProviderHelper::checkFundStatus($client_response->fundtransferresponse->status->status)):
    //                    ProviderHelper::updateGameTransactionStatus($gamerecord, 2, 6);
    //             else:
    //                ProviderHelper::updateGameTransactionStatus($gamerecord, 2, 99);
    //             endif;
    //         }
    //       ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $response,'FAILED', $client_response, 'FAILED', $general_details);
    //     }
    //     return $response;
    // }

    public  static function findTraceID($provider_trans_id) {
        $transaction_db = DB::table('game_transaction_ext as gte');
        $transaction_db->where([
            ["gte.provider_trans_id", "=", $provider_trans_id],
            ["gte.transaction_detail", "!=", '"FAILED"'], // TEST OVER ALL
        ]);
        $result = $transaction_db->latest()->first(); 
        return $result ? $result : 'false';
    }
    public  static function findGameExt($provider_identifier, $game_transaction_type=false, $type,$client_details)
    {
        $game_trans_type = '';
        if($game_transaction_type != false){
            $game_trans_type = "and gte.game_transaction_type = ". $game_transaction_type;
        }
        if ($type == 'transaction_id') {
            $where = 'where gte.provider_trans_id = "'.$provider_identifier.'" '.$game_trans_type.' AND gte.game_transaction_type = '.$game_transaction_type.' AND gte.transaction_detail != "FAILED"' ;
        }
        if ($type == 'round_id') {
            $where = 'where gte.round_id = "' . $provider_identifier.'" '.$game_trans_type.' AND gte.game_transaction_type = '.$game_transaction_type.' AND gte.transaction_detail != "FAILED"' ;
        }
        if ($type == 'game_transaction_ext_id') {
            $where = 'where gte.provider_trans_id = "' . $provider_identifier . '" ';
        }
        if ($type == 'game_trans_id') {
            $where = 'where gte.game_trans_id = "' . $provider_identifier . '" ';
        }
        try {
            $connection_name = $client_details->connection_name;
            $details = [];
            $connection = config("serverlist.server_list.".$client_details->connection_name.".connection_name");
            $status = GameTransactionMDB::checkDBConnection($connection);
            if ( ($connection != null) && $status) {
                $connection = config("serverlist.server_list.".$client_details->connection_name);
                $details = DB::connection($connection["connection_name"])->select('select * from `'.$connection['db_list'][0].'`.`game_transaction_ext` as gte ' . $where . ' LIMIT 1');
            }
            if ( !(count($details) > 0) )  {
                $connection_list = config("serverlist.server_list");
                foreach($connection_list as $key => $connection){
                    $status = GameTransactionMDB::checkDBConnection($connection["connection_name"]);
                    if($status && $connection_name != $connection["connection_name"]){
                        $data = DB::connection( $connection["connection_name"] )->select('select * from `'.$connection['db_list'][0].'`.`game_transaction_ext` as gte ' . $where . ' LIMIT 1');
                        if ( count($data) > 0  ) {
                            $connection_name = $key;// key is the client connection_name
                            $details = $data;
                            break;
                        }
                    }
                }
            }

            $count = count($details);
            if ($count > 0) {
                //apend on the details the connection which mean to rewrite the client_details
                $details[0]->connection_name = $connection_name;
            }
            return $count > 0 ? $details[0] : 'false';
        } catch (\Exception $e) {
            return 'false';
        }

    }

}
