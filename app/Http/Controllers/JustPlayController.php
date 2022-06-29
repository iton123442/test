<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransactionMDB;
use Carbon\Carbon;
use DB;

class JustPlayController extends Controller
{

    public function __construct(){
        $this->api_key = config('providerlinks.justplay.api_key');
        $this->api_url = config('providerlinks.justplay.api_url');
        $this->provider_db_id = config('providerlinks.justplay.provider_db_id');
    }





    public function callback(Request $request){
        Helper::saveLog("Justplay", $this->provider_db_id, json_encode($request->all()), "ENDPOINT HIT");
        $data = $request->all();
        $client_details = ProviderHelper::getClientDetails('player_id',$data['id_customer']);
    
        if($client_details == null){
           
            $errormessage = array(
                'done' => 0,
                'message' => 'technical error'
            );
            Helper::saveLog('JustPlay Callback error', $this->provider_db_id, json_encode($request->all(), JSON_FORCE_OBJECT),  $errormessage);
            return json_encode($errormessage, JSON_FORCE_OBJECT); 
        }

        try{    
         ProviderHelper::idenpotencyTable($data["id_stat"]);
        }  

        catch(\Exception $e){
            $response =  [
                "done" =>0,
                "message" => 'technical error'
               
            ];
             Helper::saveLog('Justplay Callback idom', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return json_encode($response, JSON_FORCE_OBJECT);
        }

        
    try {

             $game_details = Helper::findGameDetails("game_code",$this->provider_db_id, $data["id_game"]);
             $bet_amount = $data["bet"];//
             $pay_amount = $data["win"];//
             $income = $bet_amount - $pay_amount;
             $win_or_lost = $data["win"] == 0.0 ? 0 : 1;  /// 1win 0lost
             $entry_id = $data["win"] == 0.0 ? 1 : 2;// 1/bet/debit , 2//win/credit
             $provider_trans_id = $data['id_stat']; // 
             $round_id = $data['id_stat'];// this is round
             //Create GameTransaction, GameExtension
             $game_transaction_id = ProviderHelper::idGenerate($client_details->connection_name, 1); // ID generator
             $game_trans_ext_id = ProviderHelper::idGenerate($client_details->connection_name, 2);
             Helper::saveLog("Justplay dapit sa game transaction", $this->provider_db_id, json_encode($request->all()), "ENDPOINT HIT");
                   //requesttosend, and responsetoclient client side
            $general_details = ["aggregator" => [], "provider" => [], "client" => []];

            try {
                $type = "debit";
                $rollback = false;
                ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                $gameTransactionData = array(
                    "provider_trans_id" => $data['id_stat'],
                    "token_id" => $client_details->token_id,
                    "game_id" => $game_details->game_id,
                    "round_id" => $data['id_stat'],
                    "bet_amount" => $bet_amount,
                    "win" => $win_or_lost,
                    "pay_amount" => $pay_amount,
                    "income" => $income,
                    "entry_id" => $entry_id,
                ); 
                GameTransactionMDB::createGametransactionV2($gameTransactionData,$game_transaction_id,$client_details); //create game_transaction
                Helper::saveLog("Justplay game_transaction_id", $this->provider_db_id, json_encode($request->all()), "ENDPOINT HIT");
                    $gameTransactionEXTData = array(
                        "game_trans_id" => $game_transaction_id,
                        "provider_trans_id" => $data['id_stat'],
                        "round_id" => $data['id_stat'],
                        "amount" => $bet_amount,
                        "game_transaction_type"=> 1,
                        // "provider_request" =>json_encode($request->all()),
                        );
                    Helper::saveLog("Justplay nga game trann extension", $this->provider_db_id, json_encode($request->all()), "ENDPOINT HIT");
                GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$game_trans_ext_id,$client_details); //create extension
            } catch (\Exception $e) {
                $response = array(
                    'done' => 0,
                    'message' => 'Generic validation error'
                );
                
                ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, $data, $response, 'FAILED', $e->getMessage(), $response, $general_details);
                ProviderHelper::updateGameTransactionStatus($game_transaction_id, 2, 99);
                Helper::saveLog('JustPlay FATAL ERROR', $this->provider_db_id, json_encode($request->all(), JSON_FORCE_OBJECT),  $response);
                return json_encode($response, JSON_FORCE_OBJECT); 
            }
            $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount,$game_details->game_code,$game_details->game_name,$game_trans_ext_id,$game_transaction_id,$type,$rollback);
                 Helper::saveLog("Justplay client_response", $this->provider_db_id, json_encode($request->all()), "ENDPOINT HIT");
            if (isset($client_response->fundtransferresponse->status->code)) {

                switch ($client_response->fundtransferresponse->status->code) {
                    case "200":
                      $http_status = 200;
                       Helper::saveLog("Justplay insert update sa case 200", $this->provider_db_id, json_encode($request->all()), "ENDPOINT HIT");
                      ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                        $response =  [
                            "balance" => (string)$client_response->fundtransferresponse->balance
                        ];
                        break;
                    case "402":
                    $http_status = 200;
                    ProviderHelper::updateGameTransactionStatus($game_transaction_id, 2, 99);
                        $error_response = array(
                            'done' =>0,
                            'message' => 'Technical error'
                        );

                        return json_encode($error_response, JSON_FORCE_OBJECT); 
                        break;
                } 
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
            }
            $balance = $client_response->fundtransferresponse->balance + $pay_amount;
             Helper::saveLog("Justplay before win response", $this->provider_db_id, json_encode($request->all()), "ENDPOINT HIT");
            ProviderHelper::_insertOrUpdate($client_details->token_id, $balance); 
            $game_trans_ext_id_win = ProviderHelper::idGenerate($client_details->connection_name, 2);
            $win_response =  [
                "done" => 1,
                "balance" => $balance,
                 "id_stat" => $game_transaction_id,
            ];
            
           Helper::saveLog("Justplay after win response", $this->provider_db_id, json_encode($request->all()), "ENDPOINT HIT");
            $gameTransactionEXTData = array(
                "game_trans_id" => $game_transaction_id,
                "provider_trans_id" => $data['id_stat'],
                "round_id" => $data['id_stat'],
                "amount" => $pay_amount,
                "game_transaction_type"=> 2,
            );
            GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$game_trans_ext_id_win,$client_details); //create extension
             Helper::saveLog("Justplay before sa body_details", $this->provider_db_id, json_encode($request->all()), "ENDPOINT HIT");
            $body_details = [
                "type" => "credit",
                "win" => $win_or_lost,
                "token" => $client_details->player_token,
                "rollback" => false,
                "game_details" => [
                "game_id" => $game_details->game_id
                ],
                "game_transaction" => [
                    "provider_trans_id" => $data['id_stat'],
                    "round_id" => $data['id_stat'],
                    "amount" => $pay_amount
                ],
                "provider_request" => $data,
                "provider_response" => $win_response,
                "game_trans_ext_id" => $game_trans_ext_id_win,
                "game_transaction_id" => $game_transaction_id

            ];
             Helper::saveLog("Justplay after body details", $this->provider_db_id, json_encode($request->all()), "ENDPOINT HIT");
             $createGameTransactionLog = [
                "connection_name" => $client_details->connection_name,
                "column" =>[
                    "game_trans_ext_id" => $game_trans_ext_id_win,
                    "request" => json_encode($data),
                    "response" => json_encode($response),
                    "log_type" => "provider_details",
                    "transaction_detail" => "success",
                ]
            ];
            ProviderHelper::queTransactionLogs($createGameTransactionLog);    

            try{
                $client = new Client();
                $guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bgFundTransferV2MultiDB',
                    [ 'body' => json_encode($body_details), 'timeout' => '0.20']
                );

                Helper::saveLog($game_transaction_id, $this->provider_db_id, json_encode($response), $win_response);
                return json_encode($win_response, JSON_FORCE_OBJECT);
            } catch(\Exception $e){
                Helper::saveLog($game_transaction_id, $this->provider_db_id, json_encode($response), $win_response);
                return json_encode($win_response, JSON_FORCE_OBJECT);
            } 


        }
        catch(\Exception $e){
            $msg = array(
                'done' => 0,
                'message' => 'Technical error',
            );
            Helper::saveLog('JustPlay Callback error', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT), $msg);
            return json_encode($msg, JSON_FORCE_OBJECT); 
        }


    }


}
