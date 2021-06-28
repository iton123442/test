<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use Carbon\Carbon;
use DB;
use App\Models\GameTransactionMDB;


class BoomingGamingController extends Controller
{

    public function __construct(){
        $this->api_key = config('providerlinks.booming.api_key');
        $this->api_secret = config('providerlinks.booming.api_secret');
        $this->api_url = config('providerlinks.booming.api_url');
        $this->provider_db_id = config('providerlinks.booming.provider_db_id');
    }
    
    public function gameList(){
        $nonce = date('mdYhisu');
        $url =  $this->api_url.'/v2/games';
        $requesttosend = "";
        $sha256 =  hash('sha256', $requesttosend);
        $concat = '/v2/games'.$nonce.$sha256;
        $secrete = hash_hmac('sha512', $concat, $this->api_secret);

        
        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/vnd.api+json',
                'X-Bg-Api-Key' => $this->api_key,
                'X-Bg-Nonce'=> $nonce,
                'X-Bg-Signature' => $secrete
            ]
        ]);
       $guzzle_response = $client->get($url);
       $client_response = json_decode($guzzle_response->getBody()->getContents());
       return json_encode($client_response);
    }

    //THIS IS PART OF GAMELAUNCH GET SESSION AND URL
    public function callBack(Request $request){
        $data = $request->all();
        $playersid = explode('_', $data["player_id"]);
        $client_details = ProviderHelper::getClientDetails('player_id',$playersid[1]);
        Helper::saveLog('Booming', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT),"Callback");
        if($client_details == null){
           
            $errormessage = array(
                'error' => '2012',
                'message' => 'Invalid Player ID'
            );
            Helper::saveLog('Booming Callback error', $this->provider_db_id, json_encode($request->all(), JSON_FORCE_OBJECT),  $errormessage);
            return json_encode($errormessage, JSON_FORCE_OBJECT); 
        }

        try{
            ProviderHelper::idenpotencyTable("BOOMING_".$data["session_id"]."_".$data["round"]);
        }catch(\Exception $e){
            $data_response =  [
                "balance" => (string)$client_details->balance
            ];
            Helper::saveLog('Booming Callback idom', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT), $data_response);
            return json_encode($data_response, JSON_FORCE_OBJECT);
        }

        try{
                
            // $player_details = $this->playerDetailsCall($client_details);
            $game_details = Helper::getInfoPlayerGameRoundBooming($client_details->player_token);
            if($game_details == null){
                return   $response = ['error' => '2010','message' => 'Unsupported parameters provided'];
            }
            $game_code = $game_details->game_code;
            $token_id = $client_details->token_id;
            $bet_amount = $data["bet"];
            $pay_amount = $data["win"];
            $income = $bet_amount - $pay_amount;
            $win_or_lost = $data["win"] == 0.0 ? 0 : 1;  /// 1win 0lost
            $entry_id = $data["win"] == 0.0 ? 1 : 2;// 1/bet/debit , 2//win/credit
            $provider_trans_id = $data['session_id']; // this is customerid
            $round_id = $data['round'];// this is round
            $payout_reason = ProviderHelper::updateReason(5);
            
            //Create GameTransaction, GameExtension

            $gameTransactionData = array(
                        "provider_trans_id" => $data['session_id'],
                        "token_id" => $client_details->token_id,
                        "game_id" => $game_details->game_code,
                        "round_id" => $data['round'],
                        "bet_amount" => $bet_amount,
                        "win" => $win_or_lost,
                        "pay_amount" => $pay_amount,
                        "income" => $income,
                        "entry_id" => $entry_id,
                    ); 
            $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
            if($game_transaction_id == false){
                  Helper::saveLog('Booming Game trans False', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT),"Callback");
                return $response = ["error" => '2010', 'message'=> 'Unsupported parameters provided'];
            }
            $gameTransactionEXTData= array(
                "game_trans_id" => $game_transaction_id,
                "provider_trans_id" => $data['session_id'],
                "round_id" => $data['round'],
                "amount" => $bet_amount,
                "game_transaction_type"=> 1,
                "provider_request" =>json_encode($request->all()),
                );
             $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
              if($game_trans_ext_id == false){
                Helper::saveLog('Booming game_trans_ext_id False', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT),"Callback");
                return $response = ["error" => '2010', 'message'=> 'Unsupported parameters provided'];
            }
            //requesttosend, and responsetoclient client side
            $general_details = ["aggregator" => [], "provider" => [], "client" => []];
            try {
                $type = "debit";
                $rollback = false;
                $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount,$game_code,$game_details->game_name,$game_trans_ext_id,$game_transaction_id,$type,$rollback);
                ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
            } catch (\Exception $e) {
                $response = array(
                    'error' => '2099',
                    'message' => 'Generic validation error'
                );
                
                ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, $data, $response, 'FAILED', $e->getMessage(), $response, $general_details);
                ProviderHelper::updateGameTransactionStatus($game_transaction_id, 2, 99);
                Helper::saveLog('Booming FATAL ERROR', $this->provider_db_id, json_encode($request->all(), JSON_FORCE_OBJECT),  $response);
                return json_encode($response, JSON_FORCE_OBJECT); 
            }

            if (isset($client_response->fundtransferresponse->status->code)) {

                switch ($client_response->fundtransferresponse->status->code) {
                    case "200":
                        $response =  [
                            "balance" => (string)$client_response->fundtransferresponse->balance
                        ];
                       
                        break;
                    
                    case "402":
                        $data_response = array(
                            'error' => 'low_balance',
                            'message' => 'You have insufficient balance to place a bet'
                        );
                       
                        return json_encode($data_response, JSON_FORCE_OBJECT); 
                        break;
                }

                $updateTransactionEXt = array(
                                "provider_request" =>json_encode($request->all()),
                                "mw_response" => json_encode($response),
                                'mw_request' => json_encode($client_response->requestoclient),
                                'client_response' => json_encode($client_response->fundtransferresponse),
                                'transaction_detail' => 'success',
                                'general_details' => 'success',
                            );
                           
                GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);

            }
            
            $balance = $client_response->fundtransferresponse->balance + $pay_amount;
            ProviderHelper::_insertOrUpdate($client_details->token_id, $balance); 
            $win_response =  [
                "balance" => (string)$balance
            ];

            $gameTransactionEXTData = array(
                "game_trans_id" => $game_transaction_id,
                "provider_trans_id" => $data['session_id'],
                "round_id" => $data['round'],
                "amount" => $pay_amount,
                "game_transaction_type"=> 2,
                "provider_request" =>json_encode($request->all()),
                );
            $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details); 

            $body_details = [
                "type" => "credit",
                "win" => $win_or_lost,
                "token" => $client_details->player_token,
                "rollback" => false,
                "game_details" => [
                    "game_id" => $game_details->game_id
                ],
                "game_transaction" => [
                    "provider_trans_id" => $data['session_id'],
                    "round_id" => $data['round'],
                    "amount" => $pay_amount
                ],
                "provider_request" => $data,
                "provider_response" => $win_response,
                "game_trans_ext_id" => $game_trans_ext_id,
                "game_transaction_id" => $game_transaction_id

            ];

             $updateTransactionEXt = array(
                    "provider_request" =>json_encode($request->all()),
                    "mw_response" => json_encode($win_response),
                    'mw_request' => json_encode($client_response->requestoclient),
                    'client_response' => json_encode($client_response->fundtransferresponse),
                    'transaction_detail' => 'success',
                    'general_details' => 'success',
                );
                GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
            try{
                $client = new Client();
                $guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-fundtransferV2',
                    [ 'body' => json_encode($body_details), 'timeout' => '0.20']
                );
                Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($response), $win_response);
                return json_encode($win_response, JSON_FORCE_OBJECT);
            } catch(\Exception $e){
                Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($response), $win_response);
                return json_encode($win_response, JSON_FORCE_OBJECT);
            } 
        
               
        }catch(\Exception $e){
            $msg = array(
                'error' => '3001',
                'message' => $e->getMessage(),
            );
            Helper::saveLog('Booming Callback error', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT), $msg);
            return json_encode($msg, JSON_FORCE_OBJECT); 
        }

        
    }
    public function rollBack(Request $request){
        $header = [
            'bg_nonce' => $request->header('bg-nonce'),
            'bg_signature' => $request->header('bg-signature')
        ];
        Helper::saveLog('Booming', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT),"Rollback");
        $data = $request->all();
        $playersid = explode('_', $data["player_id"]);
        $client_details = ProviderHelper::getClientDetails('player_id',$playersid[1]);
        if($client_details == null){  
            $errormessage = array(
                'error' => '2012',
                'message' => 'Invalid Player ID'
            );
            Helper::saveLog('Booming Callback error', $this->provider_db_id, json_encode($request->all(), JSON_FORCE_OBJECT),  $errormessage);
            return json_encode($errormessage, JSON_FORCE_OBJECT); 
        }
        try{
            ProviderHelper::idenpotencyTable("BOOMING_".$data["session_id"]."_".$data["round"]);
            $errormessage = [
                'error' => '2010',
                'message' => 'Unsupported parameters provided'
            ];
            Helper::saveLog('Booming', $this->provider_db_id,  json_encode($request->all(),JSON_FORCE_OBJECT), $errormessage);
            return json_encode($errormessage, JSON_FORCE_OBJECT);
        }catch(\Exception $e){

            try {
                $game_details = Helper::getInfoPlayerGameRoundBooming($client_details->player_token);
                    if($game_details == false){
                Helper::saveLog('Booming game_details False', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT),"Callback");
                return $response = ["error" => '2010', 'message'=> 'Unsupported parameters provided'];
            }    
                $bet_existing = GameTransactionMDB::findGameExt($data["round"], 2,'round_id', $client_details);
                if ($bet_existing != 'false') {

                    if ($bet_existing->mw_response != "null") {
                            $to_array =  json_decode($bet_existing->mw_response);
                            if (isset($to_array->balance)) {
                                // check the win if the resposne okay
                                $win_existing =  GameTransactionMDB::findGameTransactionDetails($data["round"],'round_id',1 , $client_details);
                                if ($win_existing != 'false') {
                                    //return if reponse is true
                                    
                                    $win_reponse =  [
                                         "return" => config('providerlinks.tigergames') . '/provider/Booming%20Games'
                                    ];

                                    Helper::saveLog('Booming', $this->provider_db_id,  json_encode($request->all(),JSON_FORCE_OBJECT), $win_reponse);
                                    
                                    return json_encode($win_reponse, JSON_FORCE_OBJECT); 

                                } else {
                                    //proceess the win if not exist
                                    $game_code = $game_details->game_code;
                                    $token_id = $client_details->token_id;
                                    $bet_amount = $data["bet"];
                                    $pay_amount = $data["win"];
                                    $income = $bet_amount - $pay_amount;
                                    $win_or_lost = $data["win"] == 0.0 ? 0 : 1;  /// 1win 0lost
                                    $entry_id = $data["win"] == 0.0 ? 1 : 2;// 1/bet/debit , 2//win/credit
                                    $provider_trans_id = $data['session_id']; // this is customerid
                                    $round_id = $data['round'];// this is round
                                    $payout_reason = ProviderHelper::updateReason(5);

                                    //update player blaance
                                    $balance = $client_details->balance + $pay_amount;
                                    ProviderHelper::_insertOrUpdate($client_details->token_id, $balance); 
                                    $win_reponse =  [
                                        "balance" => (string)$balance
                                    ];

                                $gameTransactionEXTData = array(
                                                "game_trans_id" => $bet_existing->game_trans_id,
                                                "provider_trans_id" => $data['session_id'],
                                                "round_id" => $data['round'],
                                                "amount" => $pay_amount,
                                                "game_transaction_type"=> 3,
                                                "provider_request" =>json_encode($request->all()),
                                                );
                                $game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
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
                                        "game_transaction_id" => $bet_existing->game_trans_id

                                    ];

                                    try{
                                        $client = new Client();
                                        $guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-fundtransferV2',
                                            [ 'body' => json_encode($body_details), 'timeout' => '0.20']
                                        );
                                        Helper::saveLog("Booming", $this->provider_db_id, json_encode($request->all()), $win_reponse);
                                        $win_reponse =  [
                                             "return" => config('providerlinks.tigergames') . '/provider/Booming%20Games'
                                        ];
                                        return json_encode($win_reponse, JSON_FORCE_OBJECT); 
                                    } catch(\Exception $e){
                                        Helper::saveLog("Booming", $this->provider_db_id, json_encode($request->all()), $win_reponse);
                                        $win_reponse =  [
                                             "return" => config('providerlinks.tigergames') . '/provider/Booming%20Games'
                                        ];
                                        return json_encode($win_reponse, JSON_FORCE_OBJECT); 
                                    } 

                                }

                            } else {
                                //return error message for bet mw_response
                                $data_response = array(
                                    'error' => 'low_balance',
                                    'message' => 'You have insufficient balance to place a bet'
                                );
                                Helper::saveLog('Booming', $this->provider_db_id,  json_encode($request->all(),JSON_FORCE_OBJECT), $data_response);
                                return json_encode($data_response, JSON_FORCE_OBJECT); 
                            }
                            
                     } else {
                        //if null the mwresponse then third part
                        $errormessage = [
                            'error' => '2010',
                            'message' => 'Unsupported parameters provided'
                        ];
                        Helper::saveLog('Booming', $this->provider_db_id,  json_encode($request->all(),JSON_FORCE_OBJECT), $errormessage);
                        return json_encode($errormessage, JSON_FORCE_OBJECT);
                     }

                } else {
                    $errormessage = [
                        'error' => '2010',
                        'message' => 'Unsupported parameters provided1'
                    ];
                    Helper::saveLog('Booming', $this->provider_db_id,  json_encode($request->all(),JSON_FORCE_OBJECT), $errormessage);
                    return json_encode($errormessage, JSON_FORCE_OBJECT);
                }
                
            } catch (\Exception $e) {
                $errormessage = [
                    'error' => '2099',
                    'message' => $e->getMessage()
                ];
                Helper::saveLog('Booming', $this->provider_db_id,  json_encode($request->all(),JSON_FORCE_OBJECT), $errormessage);
                return json_encode($errormessage, JSON_FORCE_OBJECT);
            }
        }

    }

    
}
