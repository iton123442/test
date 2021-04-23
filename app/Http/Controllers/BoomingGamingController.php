<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use Carbon\Carbon;
use DB;


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
                    'error' => '2099',
                    'message' => 'Generic validation error'
                );
                
                ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, $data, $response, 'FAILED', $e->getMessage(), $response, $general_details);
                ProviderHelper::updateGameTransactionStatus($game_trans_id, 2, 99);
                Helper::saveLog('Booming FATAL ERROR', $this->provider_db_id, json_encode($request->all(), JSON_FORCE_OBJECT),  $response);
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
                            'error' => 'low_balance',
                            'message' => 'You have insufficient balance to place a bet'
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
                "balance" => (string)$balance
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
                $bet_existing = $this->findGameExt($data['session_id'], $data["round"], 1, 'transaction_id');
              
                if ($bet_existing != 'false') {

                    if ($bet_existing->mw_response != "null") {
                            $to_array =  json_decode($bet_existing->mw_response);
                            if (isset($to_array->balance)) {
                                // check the win if the resposne okay
                                $win_existing = $this->findGameExt($data['session_id'], $data["round"], 2, 'transaction_id');
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

                                    $game_transextension = $this->createGameTransExt($bet_existing->game_trans_id,$provider_trans_id, $round_id, $pay_amount, 2, $data, $win_reponse, $requesttosend = null, $client_response = null, $data_response = null);

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
                        'message' => 'Unsupported parameters provided'
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

    public static function creteBoomingtransaction($gametransaction_id,$provider_request,$mw_request,$mw_response,$client_response, $game_transaction_type, $amount=null, $provider_trans_id=null, $round_id=null){
        $gametransactionext = array(
            "game_trans_id" => $gametransaction_id,
            "provider_trans_id" => $provider_trans_id,
            "round_id" => $round_id,
            "amount" => $amount,
            "game_transaction_type"=>$game_transaction_type,
            "provider_request" => json_encode($provider_request),
            "mw_request"=>json_encode($mw_request),
            "mw_response" =>json_encode($mw_response),
            "client_response" =>json_encode($client_response),
        );
        $gamestransaction_ext_ID = DB::table("game_transaction_ext")->insertGetId($gametransactionext);
        return $gametransactionext;
    }

    public  static function findGameExt($provider_identifier,$round, $game_transaction_type, $type) {
        $transaction_db = DB::table('game_transactions as gt')
                        ->select('gt.*', 'gte.mw_response')
                        ->leftJoin("game_transaction_ext AS gte", "gte.game_trans_id", "=", "gt.game_trans_id");
        if ($type == 'transaction_id') {
            $transaction_db->where([
                ["gte.provider_trans_id", "=", $provider_identifier],
                ["gte.round_id", "=", $round],
                ["gte.game_transaction_type", "=", $game_transaction_type],
            ]);
        }
        $result = $transaction_db->latest()->first(); // Added Latest (CQ9) 08-12-20 - Al
        return $result ? $result : 'false';
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
    
    public static function updateGameTransactionExt($gametransextid,$mw_request,$mw_response,$client_response){
        $gametransactionext = array(
            "mw_request"=>json_encode($mw_request),
            "mw_response" =>json_encode($mw_response),
            "client_response" =>json_encode($client_response),
        );
        DB::table('game_transaction_ext')->where("game_trans_ext_id",$gametransextid)->update($gametransactionext);
    }


    public static function playerDetailsCall($client_details, $refreshtoken=false, $type=1){
        if($client_details){
            $client = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$client_details->client_access_token
                ]
            ]);
            $datatosend = [
                "access_token" => $client_details->client_access_token,
                "hashkey" => md5($client_details->client_api_key.$client_details->client_access_token),
                "type" => "playerdetailsrequest",
                "datesent" => Helper::datesent(),
                "gameid" => "",
                "clientid" => $client_details->client_id,
                "playerdetailsrequest" => [
                    "player_username"=>$client_details->username,
                    "client_player_id" => $client_details->client_player_id,
                    "token" => $client_details->player_token,
                    "gamelaunch" => true,
                    "refreshtoken" => $refreshtoken
                ]
            ];

            // return $datatosend;
            try{    
                $guzzle_response = $client->post($client_details->player_details_url,
                    ['body' => json_encode($datatosend)]
                );
                $client_response = json_decode($guzzle_response->getBody()->getContents());
                
                // Helper::saveLog('ALDEBUG REQUEST SEND = '.$player_token,  99, json_encode($client_response), $datatosend);
                
                if(isset($client_response->playerdetailsresponse->status->code) && $client_response->playerdetailsresponse->status->code != 200 || $client_response->playerdetailsresponse->status->code != '200'){
                    if($refreshtoken == true){
                        if(isset($client_response->playerdetailsresponse->refreshtoken) &&
                        $client_response->playerdetailsresponse->refreshtoken != false || 
                        $client_response->playerdetailsresponse->refreshtoken != 'false'){
                            DB::table('player_session_tokens')->insert(
                            array('player_id' => $client_details->player_id, 
                                  'player_token' =>  $client_response->playerdetailsresponse->refreshtoken, 
                                  'status_id' => '1')
                            );
                        }
                    }
                    return 'false';
                }else{
                    if($refreshtoken == true){
                        if(isset($client_response->playerdetailsresponse->refreshtoken) &&
                        $client_response->playerdetailsresponse->refreshtoken != false || 
                        $client_response->playerdetailsresponse->refreshtoken != 'false'){
                            DB::table('player_session_tokens')->insert(
                                array('player_id' => $client_details->player_id, 
                                      'player_token' =>  $client_response->playerdetailsresponse->refreshtoken, 
                                      'status_id' => '1')
                            );
                        }
                    }
                    return $client_response;
                }

            }catch (\Exception $e){
               // Helper::saveLog('ALDEBUG client_player_id = '.$client_details->client_player_id,  99, json_encode($datatosend), $e->getMessage());
               return 'false';
            }
        }else{
            return 'false';
        }
    }

    
    
}
