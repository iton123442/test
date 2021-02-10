<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Helpers\ClientRequestHelper;
use App\Helpers\TransactionHelper;
use DB;
class BNGController extends Controller
{
    //
    protected $startTime;

    public function __construct() {
        $this->startTime = microtime(true);
    }
    public function index(Request $request){
        $data = json_decode($request->getContent(),TRUE);
       
        if($data["name"]== "login"){
            return $this->_authPlayer($data);
        }
        elseif($data["name"]== "transaction"){

            Helper::saveLog('transactionRequestBNG', 12, json_encode($data), "get request");
            if($data["args"]["bet"]!= null && $data["args"]["win"]!= null){
                // dd($data);
                $client_details = ProviderHelper::getClientDetails('token', $data["token"]);
               // NEW VERSION APPLY 2021-01-09
                try{
                    ProviderHelper::idenpotencyTable('BNG_'.$data["uid"]);
                }catch(\Exception $e){

                    $response =array(
                        "uid"=>$data["uid"],
                        "balance" => array(
                            "value" => (string)$client_details->balance,
                            "version" => $this->_getExtParameter()
                        ),
                    );
                    Helper::saveLog('betAlreadyExist(BNG)', 12, json_encode($data), $response);
                    Helper::saveLog('responseTime(BNG)', 12, json_encode(["stating"=>$this->startTime,"response"=>microtime(true)]), microtime(true) - $this->startTime);
                    return response($response,200)
                        ->header('Content-Type', 'application/json');
                }
                $game_details = Helper::getInfoPlayerGameRound($data["token"]);
                
                $bet_response = $this->_betGame($data, $client_details, $game_details);

                $balance = $bet_response["balance"]["value"];
                if($bet_response){
                    if(array_key_exists("error",$bet_response)){
                    Helper::saveLog('responseTime(BNG)', 12, json_encode(["stating"=>$this->startTime,"response"=>microtime(true)]), microtime(true) - $this->startTime);
                    return response($bet_response,200)
                    ->header('Content-Type', 'application/json');
                    }
                    else{
                        Helper::saveLog('responseTime(BNG)', 12, json_encode(["stating"=>$this->startTime,"response"=>microtime(true)]), microtime(true) - $this->startTime);
                        return $this->_winGame($data , $client_details, $game_details,$balance);
                    }
                }
                else{
                    $msg = array(
                        "uid" => $data["uid"],
                        "error"=>array(
                            "code" => "INVALID_TOKEN"
                        ),
                    );
                    return response($msg,200)->header('Content-Type', 'application/json');
                }
                // END NEW VERSION APPLY 2021-01-09

                // $game_transaction = TransactionHelper::checkGameTransactionData($data["uid"]);
                // if(!empty($game_transaction)){
                //     $response = json_decode($game_transaction[0]->mw_response,TRUE);
                //     Helper::saveLog('betAlreadyExist(BNG)', 12, json_encode($data), $response);
                //     Helper::saveLog('responseTime(BNG)', 12, json_encode(["stating"=>$this->startTime,"response"=>microtime(true)]), microtime(true) - $this->startTime);
                //     return response($response,200)
                //         ->header('Content-Type', 'application/json');
                // }
                // else{
                //     $bet_response = $this->_betGame($data);
                //     if($bet_response){
                //         if(array_key_exists("error",$bet_response)){
                //         Helper::saveLog('responseTime(BNG)', 12, json_encode(["stating"=>$this->startTime,"response"=>microtime(true)]), microtime(true) - $this->startTime);
                //         return response($bet_response,200)
                //         ->header('Content-Type', 'application/json');
                //         }
                //         else{
                //             Helper::saveLog('responseTime(BNG)', 12, json_encode(["stating"=>$this->startTime,"response"=>microtime(true)]), microtime(true) - $this->startTime);
                //             return $this->_winGame($data);
                //         }
                //     }
                //     else{
                //         $msg = array(
                //             "uid" => $data["uid"],
                //             "error"=>array(
                //                 "code" => "INVALID_TOKEN"
                //             ),
                //         );
                //         return response($msg,200)->header('Content-Type', 'application/json');
                //     }
                // }
            }
            elseif($data["args"]["bet"] == null && $data["args"]["win"]!= null){
                $client_details = ProviderHelper::getClientDetails('token', $data["token"]);
                $game_details = Helper::getInfoPlayerGameRound($data["token"]);
                $bet_response = $this->_betGame($data, $client_details, $game_details);
                $balance = $bet_response["balance"]["value"];
                return $this->_winGame($data , $client_details, $game_details,$balance);
            }
            elseif($data["args"]["bet"]!= null && $data["args"]["win"]== null){
                    $client_details = ProviderHelper::getClientDetails('token', $data["token"]);
                    $game_details = Helper::getInfoPlayerGameRound($data["token"]);
                    $bet_response = $this->_betGame($data, $client_details, $game_details);
                    if($bet_response){
                        if(array_key_exists("error",$bet_response)){
                            Helper::saveLog('responseTime(BNG)', 12, json_encode(["stating"=>$this->startTime,"response"=>microtime(true)]), microtime(true) - $this->startTime);
                            return response($bet_response,200)
                            ->header('Content-Type', 'application/json');
                        }
                        else{
                            Helper::saveLog('responseTime(BNG)', 12, json_encode(["stating"=>$this->startTime,"response"=>microtime(true)]), microtime(true) - $this->startTime);
                            return response($bet_response,200)
                            ->header('Content-Type', 'application/json');
                        }
                    }
                    else{
                        $msg = array(
                            "uid" => $data["uid"],
                            "error"=>array(
                                "code" => "INVALID_TOKEN"
                            ),
                        );
                        return response($msg,200)->header('Content-Type', 'application/json');
                    }
            }
            else{
                return "this";
            }
            //return $this->_betGame($data);
        }
        elseif($data["name"]=="rollback"){
            return $this->_rollbackGame($data);
        }
        elseif($data["name"]=="getbalance"){
            return $this->_getBalance($data);
        }
    }
    public function generateGame(Request $request){
        $url = "https://gate-stage.betsrv.com/op/tigergames-stage/api/v1/game/list/";
        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json'
            ]
        ]);
        $guzzle_response = $client->post($url,
                    ['body' => json_encode(
                            [
                                "api_token" => "hj1yPYivJmIX4X1I1Z57494re",
                                "provider_id" => 2
                            ]
                    )]
                );
        $client_response = json_decode($guzzle_response->getBody()->getContents(),TRUE);
        $data = array();
        foreach($client_response["items"] as $game_data){
           if($game_data["type"]=="TABLE"){
                if(array_key_exists("en",$game_data["i18n"])){
                    $game = array(
                        "game_type_id"=>5,
                        "provider_id"=>22,
                        "sub_provider_id"=>45,
                        "game_name"=>$game_data["i18n"]["en"]["title"],
                        "game_code"=>$game_data["game_id"],
                        "icon"=>"https:".$game_data["i18n"]["en"]["banner_path"]
                    );
                    array_push($data,$game);
                }
            }
        }
        DB::table('games')->insert($data);
        return $data;
    }
    public function gameLaunchUrl(Request $request){
        $token = $request->input('token');
        $game = $request->input('game_code');
        $lang = "en";
        $timestamp = Carbon::now()->timestamp;
        $title = $request->input('game_name');
        $exit_url = "https://daddy.betrnk.games";
        $gameurl =  config("providerlinks.boongo.PLATFORM_SERVER_URL")
                  .config("providerlinks.boongo.PROJECT_NAME").
                  "/game.html?wl=".config("providerlinks.boongo.WL").
                  "&token=".$token."&game=".$game."&lang=".$lang."&sound=1&ts=".
                  $timestamp."&quickspin=1&title=".$title."&platform=desktop".
                  "&exir_url=".urlencode($exit_url);
        return $gameurl;
    }
    private function _authPlayer($data){
        if($data["token"]){
            $client_details = ProviderHelper::getClientDetails('token', $data["token"]);
            if($client_details){
                // $client = new Client([
                //     'headers' => [ 
                //         'Content-Type' => 'application/json',
                //         'Authorization' => 'Bearer '.$client_details->client_access_token
                //     ]
                // ]);
                // $guzzle_response = $client->post($client_details->player_details_url,
                //     ['body' => json_encode(
                //             [
                //                 "access_token" => $client_details->client_access_token,
                //                 "hashkey" => md5($client_details->client_api_key.$client_details->client_access_token),
                //                 "type" => "playerdetailsrequest",
                //                 "datesent" => "",
                //                 "gameid" => "",
                //                 "clientid" => $client_details->client_id,
                //                 "playerdetailsrequest" => [
                //                     "player_username"=>$client_details->username,
                //                     "client_player_id"=>$client_details->client_player_id,
                //                     "token" => $client_details->player_token,
                //                     "gamelaunch" => "true"
                //                 ]]
                //     )]
                // );
                // $client_response = json_decode($guzzle_response->getBody()->getContents());
                // Helper::saveLog('AuthPlayer(BNG)', 12, json_encode(array("token"=>$data)),$client_response);
                //$balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
                $is_test= $client_details->test_player == 0?false:true;
                $brand = $client_details->client_code?$client_details->client_code:"TigerGames";
                $msg = array(
                    "uid" => $data["uid"],
                    "player"=>array(
                        "id"=> (string)$client_details->player_id,         
                        "brand"=> $brand,      
                        "currency"=> $client_details->default_currency,   
                        "mode"=> "REAL",       
                        "is_test"=> $is_test
                    ),
                    "balance"=>array(
                        "value"=> number_format($client_details->balance,2,'.', ''),
                        "version"=> $this->_getExtParameter()
                    ),
                    "tag"=>""
                );
                $this->_setExtParameter($this->_getExtParameter()+1);
                return response($msg,200)->header('Content-Type', 'application/json');
            }
            else{
                $msg = array(
                    "uid" => $data["uid"],
                    "error"=>array(
                        "code" => "INVALID_TOKEN"
                    ),
                );
                return response($msg,200)->header('Content-Type', 'application/json');
            }
        }    
    }
    private function _getBalance($data){
        if($data["token"]){
            $client_details = ProviderHelper::getClientDetails('token', $data["token"]);
            if($client_details){
                // $client = new Client([
                //     'headers' => [ 
                //         'Content-Type' => 'application/json',
                //         'Authorization' => 'Bearer '.$client_details->client_access_token
                //     ]
                // ]);
                // $guzzle_response = $client->post($client_details->player_details_url,
                //     ['body' => json_encode(
                //             [
                //                 "access_token" => $client_details->client_access_token,
                //                 "hashkey" => md5($client_details->client_api_key.$client_details->client_access_token),
                //                 "type" => "playerdetailsrequest",
                //                 "datesent" => "",
                //                 "gameid" => "",
                //                 "clientid" => $client_details->client_id,
                //                 "playerdetailsrequest" => [
                //                     "client_player_id"=>$client_details->client_player_id,
                //                     "token" => $client_details->player_token,
                //                     "gamelaunch" => "true"
                //                 ]]
                //     )]
                // );
                // $client_response = json_decode($guzzle_response->getBody()->getContents());
                // Helper::saveLog('AuthPlayer(BNG)', 12, json_encode(array("token"=>$data)),$client_response);
                //$balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
                $msg = array(
                    "uid" => $data["uid"],
                    "balance"=>array(
                        "value"=> number_format($client_details->balance,2,'.', ''),
                        "version"=> $this->_getExtParameter()
                    )
                );
                $this->_setExtParameter($this->_getExtParameter()+1);
                return response($msg,200)->header('Content-Type', 'application/json');
            }
        }
    }
    private function _betGame($data,$client_details,$game_details){
        //return $data["args"]["bet"];
        if($data["token"]){
            // $client_details = ProviderHelper::getClientDetails('token', $data["token"]);
            // if($client_details){

                $game_transaction = Helper::checkGameTransaction($data["uid"]);
                $bet = $data["args"]["bet"]==null ? 0:$data["args"]["bet"];
                $bet_amount = $game_transaction ? 0 : round($bet,2);
                $bet_amount = $bet_amount < 0 ? 0 :$bet_amount;

                if ($bet_amount > $client_details->balance) {
                    $response =array(
                        "uid"=>$data["uid"],
                        "balance" => array(
                            "value" =>number_format($client_details->balance,2,'.', ''),
                            "version" => $this->_getExtParameter()
                        ),
                        "error" => array(
                            "code"=> "FUNDS_EXCEED",
                        )
                    );
                    $this->_setExtParameter($this->_getExtParameter()+1);
                    return $response;
                }

                // $game_details = Helper::getInfoPlayerGameRound($data["token"]);
                $json_data = array(
                    "transid" => $data["uid"],
                    "amount" => round($data["args"]["bet"],2),
                    "roundid" => $data["args"]["round_id"]
                );
                $this->_setExtParameter($this->_getExtParameter()+1);
                if(!$game_transaction){
                    $gametransactionid=Helper::createGameTransaction('debit', $json_data, $game_details, $client_details);
                    $transactionId=Helper::createBNGGameTransactionExt($gametransactionid,$data,null,null,null,1);
                } 

                // $body_details = [
                //     "type" => "debit",
                //     "token" => $client_details->player_token,
                //     "rollback" => false,
                //     "game_details" => [
                //         "game_id" => $game_details->game_id
                //     ],
                //     "game_transaction" => [
                //         "provider_trans_id" => $data["uid"],
                //         "round_id" => $data["args"]["round_id"],
                //         "amount" => $bet_amount
                //     ],
                //     "provider_request" => $data,
                //     "game_trans_ext_id" => $transactionId,
                //     "game_transaction_id" => $gametransactionid
                //     // "provider_name" => "BNG" custom
                // ];
               
                // try{
                //     $client = new Client();
                //     $guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-fundtransfer',
                //         [ 'body' => json_encode($body_details), 'timeout' => '0.20']
                //     );
                // } catch(\Exception $e){
                //     $amount = $bet_amount;
                //     $balance = $client_details->balance - $amount;
                //     ProviderHelper::_insertOrUpdate($client_details->token_id, $balance); 
                //     $response =array(
                //         "uid"=>$data["uid"],
                //         "balance" => array(
                //             "value" => (string)$balance,
                //             "version" => $this->_getExtParameter()
                //         ),
                //     );
                //     return $response;
                // }

                $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount,$game_details->game_code,$game_details->game_name,$transactionId,$gametransactionid,"debit");
                $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
                if(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "200"){
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    $response =array(
                        "uid"=>$data["uid"],
                        "balance" => array(
                            "value" =>$balance,
                            "version" => $this->_getExtParameter()
                        ),
                    );
                    Helper::updateBNGGameTransactionExt($transactionId,$client_response->requestoclient,$response,$client_response);
                    return $response;
                }
                elseif(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "402"){
                    $response =array(
                        "uid"=>$data["uid"],
                        "balance" => array(
                            "value" =>number_format($client_details->balance,2,'.', ''),
                            "version" => $this->_getExtParameter()
                        ),
                        "error" => array(
                            "code"=> "FUNDS_EXCEED",
                        )
                    );
                    $this->_setExtParameter($this->_getExtParameter()+1);
                    Helper::updateBNGGameTransactionExt($transactionId,$client_response->requestoclient,$response,$client_response);
                    Helper::saveLog('betGameInsuficientN(BNG)', 12, json_encode($data), $response);
                    return $response;
                }
                

            // }
        } 
    }
    private function _winGame($data,$client_details,$game_details,$balance){
        //return $data["args"]["win"];
        if($data["token"]){
            // $client_details = ProviderHelper::getClientDetails('token', $data["token"]);
            // if($client_details){
                //$game_transaction = Helper::checkGameTransaction($json["transactionId"]);
                $game_transaction = Helper::checkGameTransaction($data["uid"],$data["args"]["round_id"],2);
                $win_amount = $game_transaction ? 0 : round($data["args"]["win"],2);
                $win_amount = $win_amount < 0 ? 0 :$win_amount;
                $win = $data["args"]["win"] == 0 ? 0 : 1;
                // $game_details = Helper::getInfoPlayerGameRound($data["token"]);
                $json_data = array(
                    "transid" => $data["uid"],
                    "amount" => round($data["args"]["win"],2),
                    "roundid" => $data["args"]["round_id"],
                    "payout_reason" => null,
                    "win" => $win,
                );
                $game = TransactionHelper::getGameTransaction($data["token"],$data["args"]["round_id"]);
                if(!$game){
                    $gametransactionid=Helper::createGameTransaction('credit', $json_data, $game_details, $client_details); 
                }
                else{
                    $json_data["amount"] = round($data["args"]["win"],2) + $game[0]->pay_amount;
                    $json_data["win"] = $json_data["amount"] > 0 ? 1 : 0; 
                    // NEW UPDATE 2021-01-09
                     $gameupdate = TransactionHelper::updateGameTransaction($game,$json_data,"credit");
                     //NEW UPDATE  2021-01-09
                    // if($win == 0){
                    //     $gameupdate = TransactionHelper::updateGameTransaction($game,$json_data,"debit");
                    // }else{
                    //    $gameupdate = TransactionHelper::updateGameTransaction($game,$json_data,"credit");
                    // }
                    $gametransactionid = $game[0]->game_trans_id;
                }
                $this->_setExtParameter($this->_getExtParameter()+1);
                if(!$game_transaction){
                    $transactionId=Helper::createBNGGameTransactionExt($gametransactionid,$data,null,null,null,2);
                } 
                // NEW VERSION APPLY 2021-01-09
                $body_details = [
                    "type" => "credit",
                    "token" => $client_details->player_token,
                    "rollback" => false,
                    "game_details" => [
                        "game_id" => $game_details->game_id
                    ],
                    "game_transaction" => [
                        "provider_trans_id" => $data["uid"],
                        "round_id" => $data["args"]["round_id"],
                        "amount" => round($win_amount,2)
                    ],
                    "provider_request" => $data,
                    "game_trans_ext_id" => $transactionId,
                    "game_transaction_id" => $gametransactionid

                ];
               
                try{
                    $client = new Client();
                    $guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-fundtransfer',
                        [ 'body' => json_encode($body_details), 'timeout' => '0.20']
                    );
                } catch(\Exception $e){
                    $amount = round($win_amount,2);
                    $balance = $balance + $amount;
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $balance); 
                    $response =array(
                        "uid"=>$data["uid"],
                        "balance" => array(
                            "value" => (string)$balance,
                            "version" => $this->_getExtParameter()
                        ),
                    );
                    return response($response,200)
                        ->header('Content-Type', 'application/json');
                }
                //// END NEW VERSION APPLY 2021-01-09


                // $client_response = ClientRequestHelper::fundTransfer($client_details,round($win_amount,2),$game_details->game_code,$game_details->game_name,$transactionId,$gametransactionid,"credit");
                // $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
                // if(isset($client_response->fundtransferresponse->status->code) 
                // && $client_response->fundtransferresponse->status->code == "200"){
                //     ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                //     $response =array(
                //         "uid"=>$data["uid"],
                //         "balance" => array(
                //             "value" =>$balance,
                //             "version" => $this->_getExtParameter()
                //         ),
                //     );
                //     Helper::updateBNGGameTransactionExt($transactionId,$client_response->requestoclient,$response,$client_response);
                //     return response($response,200)
                //         ->header('Content-Type', 'application/json');
                // }
                // else{
                //     return "something error with the client";
                // }


            // }
        } 
    }
    private function _rollbackGame($data){
        //return $data["args"]["bet"];
        if($data["token"]){
            $client_details = ProviderHelper::getClientDetails('token', $data["token"]);
            if($client_details){
                //$game_transaction = Helper::checkGameTransaction($json["transactionId"]);
                $rollbackchecker = Helper::checkGameTransaction($data["args"]["transaction_uid"],$data["args"]["round_id"],1);
                if(!$rollbackchecker){
                    $response =array(
                        "uid"=>$data["uid"],
                        "balance" => array(
                            "value" =>number_format(Helper::getBalance($client_details),2,'.', ''),
                            "version" => $this->_getExtParameter()
                        ),
                    );
                    $this->_setExtParameter($this->_getExtParameter()+1);
                    return response($response,200)
                        ->header('Content-Type', 'application/json');
                }
                $game_transaction = Helper::checkGameTransaction($data["uid"],$data["args"]["round_id"],3);
                $refund_amount = $game_transaction ? 0 : round($data["args"]["bet"],2);
                $refund_amount = $refund_amount < 0 ? 0 :$refund_amount;
                $win = $data["args"]["win"] == 0 ? 0 : 1;
                $game_details = Helper::getInfoPlayerGameRound($data["token"]);
                $json_data = array(
                    "transid" => $data["uid"],
                    "amount" => round($refund_amount,2),
                    "roundid" => $data["args"]["round_id"],
                );
                $game = TransactionHelper::getGameTransaction($data["token"],$data["args"]["round_id"]);
                if(!$game){
                    $gametransactionid=Helper::createGameTransaction('refund', $json_data, $game_details, $client_details); 
                }
                else{
                    $gameupdate = TransactionHelper::updateGameTransaction($game,$json_data,"refund");
                    $gametransactionid = $game[0]->game_trans_id;
                }
                $this->_setExtParameter($this->_getExtParameter()+1);
                if(!$game_transaction){
                    $transactionId=Helper::createBNGGameTransactionExt($gametransactionid,$data,null,null,null,3);
                }
                $client_response = ClientRequestHelper::fundTransfer($client_details,round($refund_amount,2),$game_details->game_code,$game_details->game_name,$transactionId,$gametransactionid,"credit",true);
                $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
                if(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "200"){
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    $response =array(
                        "uid"=>$data["uid"],
                        "balance" => array(
                            "value" =>$balance,
                            "version" => $this->_getExtParameter()
                        ),
                    );
                    Helper::updateBNGGameTransactionExt($transactionId,$client_response->requestoclient,$response,$client_response);
                    return response($response,200)
                        ->header('Content-Type', 'application/json');
                }
            }
        } 
    }
    private function _getClientDetails($type = "", $value = "") {

        $query = DB::table("clients AS c")
                 ->select('p.client_id', 'p.player_id', 'p.client_player_id','p.username', 'p.email', 'p.language', 'p.currency', 'pst.token_id', 'pst.player_token' , 'pst.status_id', 'p.display_name','c.default_currency', 'c.client_api_key', 'cat.client_token AS client_access_token', 'ce.player_details_url', 'ce.fund_transfer_url')
                 ->leftJoin("players AS p", "c.client_id", "=", "p.client_id")
                 ->leftJoin("player_session_tokens AS pst", "p.player_id", "=", "pst.player_id")
                 ->leftJoin("client_endpoints AS ce", "c.client_id", "=", "ce.client_id")
                 ->leftJoin("client_access_tokens AS cat", "c.client_id", "=", "cat.client_id");
                 
                if ($type == 'token') {
                    $query->where([
                        ["pst.player_token", "=", $value],
                        ["pst.status_id", "=", 1]
                    ]);
                }

                if ($type == 'player_id') {
                    $query->where([
                        ["p.player_id", "=", $value],
                        ["pst.status_id", "=", 1]
                    ]);
                }

                 $result= $query->first();

        return $result;
    }
    private function _getExtParameter(){
        $provider = DB::table("providers")->where("provider_id",22)->first();
        $ext_parameter = json_decode($provider->ext_parameter,TRUE);
        return $ext_parameter["version"];
    }
    private function _setExtParameter($newversion){
        DB::table("providers")->where("provider_id",22)->update(['ext_parameter->version'=>$newversion]);
    }
}
