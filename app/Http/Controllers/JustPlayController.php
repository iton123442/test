<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
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
                "done" =>1,
                "balance" => $client_details->balance
               
            ];
             Helper::saveLog('Justplay Callback idom', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return json_encode($response, JSON_FORCE_OBJECT);
        }

        
        try {

             $game_details = Helper::findGameDetails("game_code",$this->provider_db_id, $data["id_game"]);
           
             $game_code = $game_details->game_code;
             $token_id = $client_details->token_id;
             $bet_amount = $data["bet"];
             $pay_amount = $data["win"];
             $income = $bet_amount - $pay_amount;
               
             $win_or_lost = $data["win"] == 0.0 ? 0 : 1;  /// 1win 0lost
             $entry_id = $data["win"] == 0.0 ? 1 : 2;// 1/bet/debit , 2//win/credit
             $provider_trans_id = $data['id_stat']; // 
             $round_id = $data['id_stat'];// this is round
             $payout_reason = ProviderHelper::updateReason(5);


             //Create GameTransaction, GameExtension
            $game_trans_id  = ProviderHelper::createGameTransaction($token_id, $game_details->game_id, $bet_amount,  $pay_amount, $entry_id, 5 , null, $payout_reason, $income, $provider_trans_id, $round_id);
            $game_trans_ext_id = $this->createGameTransExt($game_trans_id,$provider_trans_id, $round_id, $bet_amount, 1, $data, $data_response = null, $requesttosend = null, $client_response = null, $data_response = null);

                   //requesttosend, and responsetoclient client side
            $general_details = ["aggregator" => [], "provider" => [], "client" => []];

            try {
                $type = "debit";
                $rollback = false;
                $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount,$game_code,$game_details->game_name,$game_trans_ext_id,$game_trans_id,$type,$rollback);
                ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
            } catch (\Exception $e) {
                $response = array(
                    'done' => 0,
                    'message' => 'Generic validation error'
                );
                
                ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, $data, $response, 'FAILED', $e->getMessage(), $response, $general_details);
                ProviderHelper::updateGameTransactionStatus($game_trans_id, 2, 99);
                Helper::saveLog('JustPlay FATAL ERROR', $this->provider_db_id, json_encode($request->all(), JSON_FORCE_OBJECT),  $response);
                return json_encode($response, JSON_FORCE_OBJECT); 
            }
            if (isset($client_response->fundtransferresponse->status->code)) {

                switch ($client_response->fundtransferresponse->status->code) {
                    case "200":
                        $bet_response =  [
                            "balance" => (string)$client_response->fundtransferresponse->balance
                        ];
                        $this->updateGameTransactionExt($game_trans_ext_id,$client_response->requestoclient,$bet_response,$client_response->fundtransferresponse);
                       
                        break;

                    
                    case "402":
                        $data_response = array(
                            'done' =>0,
                            'message' => 'Technical error'
                        );

                        $this->updateGameTransactionExt($game_trans_ext_id,$client_response->requestoclient,$data_response,$client_response->fundtransferresponse);
                        ProviderHelper::updateGameTransactionStatus($game_trans_id, 2, 6);
                        return json_encode($data_response, JSON_FORCE_OBJECT); 
                        break;
                }
            }

            $balance = $client_response->fundtransferresponse->balance + $pay_amount;
            ProviderHelper::_insertOrUpdate($client_details->token_id, $balance); 
            $win_reponse =  [
                "done" => 1,
                "balance" => $balance,
                 "id_stat" => $game_trans_id
            ];



            $game_transextension = $this->createGameTransExt($game_trans_id,$provider_trans_id, $round_id, $pay_amount, 2, $data, $win_reponse, $requesttosend = null, $client_response = null, $data_response = null);
             
            $body_details = [
                "type" => "credit",
                "win" => $win_or_lost,
                "token" => $client_details->player_token,
                "rollback" => false,
                "game_details" => [
                "game_id" => $game_details->game_id
                ],
                "game_transaction" => [
                    "provider_trans_id" => $provider_trans_id,
                    "round_id" => $round_id,
                    "amount" => $pay_amount
                ],
                "provider_request" => $data,
                "provider_response" => $win_reponse,
                "game_trans_ext_id" => $game_transextension,
                "game_transaction_id" => $game_trans_id

            ];

            try{
                $client = new Client();
                $guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-fundtransferV2',
                    [ 'body' => json_encode($body_details), 'timeout' => '0.20']
                );
                Helper::saveLog($game_transextension, $this->provider_db_id, json_encode($bet_response), $win_reponse);
                return json_encode($win_reponse, JSON_FORCE_OBJECT);
            } catch(\Exception $e){
                Helper::saveLog($game_transextension, $this->provider_db_id, json_encode($bet_response), $win_reponse);
                return json_encode($win_reponse, JSON_FORCE_OBJECT);
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

        //update 2020/09/21
    public static function createGameTransExt($game_trans_id, $provider_trans_id, $round_id, $amount, $game_type, $provider_request, $mw_response, $mw_request, $client_response, $transaction_detail){
        $gametransactionext = array(
            "game_trans_id" => $game_trans_id,
            "provider_trans_id" => $provider_trans_id,
            "round_id" => $round_id,
            "amount" => $amount,
            "game_transaction_type"=>$game_type,
            "provider_request" => json_encode($provider_request),
            "mw_response" =>json_encode($mw_response),
            "mw_request"=>json_encode($mw_request),
            "client_response" =>json_encode($client_response),
            "transaction_detail" =>json_encode($transaction_detail)
        );
        $gamestransaction_ext_ID = DB::table("game_transaction_ext")->insertGetId($gametransactionext);
        return $gamestransaction_ext_ID;
    }

        public static function updateGameTransactionExt($gametransextid,$mw_request,$mw_response,$client_response,$time=null){
        $gametransactionext = array(
            "mw_request"=>json_encode($mw_request),
            "mw_response" =>json_encode($mw_response),
            "client_response" =>json_encode($client_response),
            "general_details"=>$time,
        );
        DB::table('game_transaction_ext')->where("game_trans_ext_id",$gametransextid)->update($gametransactionext);
    }
    


}
