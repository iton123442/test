<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\GameRound;
use App\Helpers\GameTransaction;
use App\Models\GameTransactionMDB;
use App\Helpers\Helper;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use App\Helpers\ClientRequestHelper;
use App\Helpers\ProviderHelper;
use App\Helpers\TransactionHelper;
use App\Helpers\FreeSpinHelper;
use DB;
use Illuminate\Support\Facades\DB as FacadesDB;

class EDPController extends Controller
{
     
    // private $edp_endo_url = "http://localhost:8080/api/sessions/seamless/rest/v1";
    // private $nodeid = 777;
    // private $secretkey = "E09A0EF00E5D4B23B169E8548067B8E3";
    private $edp_endo_url = "https://test.endorphina.com/api/sessions/seamless/rest/v1";
    private $nodeid = 1002;
    private $secretkey = "67498C0AD6BD4D2DB8FDFE59BD9039EB";
    public function index(Request $request){
        $sha1key = sha1($request->param.''.$this->secretkey);
        if($sha1key == $request->sign){
            $sha1key = sha1($this->nodeid.''.$request->param.''.$this->secretkey);
            $data2 = array(
                "param" => $request->param,
                "compare" => $sha1key."==".$request->sign,
                "sha1key" => $sha1key,
                "sign" => $request->sign
            );
            Helper::saveLog('AuthPlayer(EDP)', 2, json_encode($data2), "EDP Request");
            $data = array(
                "nodeId" => $this->nodeid,
                "param" => $request->param,
                "sign"=>$sha1key);
            return response($data,200)->header('Content-Type', 'application/json');
        }
        
    } 
    //this is just for testing
    public function gameLaunchUrl(Request $request){
        if($request->has('token')&&$request->has('client_id')
        &&$request->has('client_player_id')
        &&$request->has('username')
        &&$request->has('email')
        &&$request->has('display_name')
        &&$request->has('game_code')){
            if($token=Helper::checkPlayerExist($request->client_id,$request->client_player_id,$request->username,$request->email,$request->display_name,$request->token,$request->game_code))
            {
                
                $exiturl = "http://demo.freebetrnk.com/";
                $profile = "noexit.xml";
                $sha1key = sha1($exiturl.''.$this->nodeid.''.$profile.''.$token.''.$this->secretkey);
                $sign = $sha1key; 
                $gameLunchUrl = $this->edp_endo_url.'?exit='.$exiturl.'&nodeId='.$this->nodeid.'&profile='.$profile.'&token='.$token.'&sign='.$sign;
                Helper::savePLayerGameRound($request->game_code,$token);
                return array(
                    "url"=>$gameLunchUrl,
                    "game_lunch"=>true,
                );
                
            }
            
        }
        else{
            $response = array(
                "error_code"=>"BAD_REQUEST",
                "error_message"=> "request is invalid/missing a required input"
            );
            return response($response,400)
               ->header('Content-Type', 'application/json');
        }
        
    }
    public function playerSession(Request $request){
        $sha1key = sha1($request->token.''.$this->secretkey);
        if($sha1key == $request->sign){
            $game = Helper::getInfoPlayerGameRound($request->token);
            $client_details = ProviderHelper::getClientDetails('token', $request->token);
            $sessions =array(
                "player" => $client_details->player_id,
                "currency"=> $client_details->default_currency,
                "game"   => $game->game_code
            );
            $data2 = array(
                "token" => $request->token,
                "sign" => $request->sign
            );
            Helper::saveLog('PlayerSession(EDP)', 2, json_encode($data2), $sessions); 
            return response($sessions,200)
                   ->header('Content-Type', 'application/json');
        }
        else{
            $response = array(
                "error_code"=>"ACCESS_DENIED",
                "error_message"=> "request is invalid/missing a required input"
            );
            return response($response,401)
               ->header('Content-Type', 'application/json');
        }
    }
    public function getBalance(Request $request){
        $startTime =  microtime(true);
        $sha1key = sha1($request->token.''.$this->secretkey);
        if($sha1key == $request->sign){
            $client_details = ProviderHelper::getClientDetails('token', $request->token);
            if($client_details){
                $sendtoclient =  microtime(true);
                $client = new Client([
                    'headers' => [ 
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer '.$client_details->client_access_token
                    ]
                ]);
                
                $guzzle_response = $client->post($client_details->player_details_url,
                    ['body' => json_encode(
                            [
                                "access_token" => $client_details->client_access_token,
                                "hashkey" => md5($client_details->client_api_key.$client_details->client_access_token),
                                "type" => "playerdetailsrequest",
                                "datesent" => "",
                                "gameid" => "",
                                "clientid" => $client_details->client_id,
                                "playerdetailsrequest" => [
                                    "player_username"=>$client_details->username,
                                    "client_player_id"=>$client_details->client_player_id,
                                    "token" => $client_details->player_token,
                                    "gamelaunch" => "false"
                                ]]
                    )]
                );
                $client_response = json_decode($guzzle_response->getBody()->getContents());
                $client_response_time = microtime(true) - $sendtoclient;
                $sessions =array(
                    "balance"=>round($client_response->playerdetailsresponse->balance * 1000,2)
                );
                $game_details = $this->getInfoPlayerGameRound($request->token);
                $freespin_balance = FreeSpinHelper::getFreeSpinBalance($client_details->player_id,$game_details->game_id);
                if($freespin_balance != null){
                    $sessions =array(
                        "balance"=>round($client_response->playerdetailsresponse->balance * 1000,2),
                        "spins"=>$freespin_balance
                    );
                }
                Helper::saveLog('responseTime(EDP)', 12, json_encode(["type"=>"debitproccess","stating"=>$startTime,"response"=>microtime(true)]), ["response"=>microtime(true) - $startTime,"mw_response"=> microtime(true) - $startTime - $client_response_time,"clientresponse"=>$client_response_time]);
                return response($sessions,200)
                       ->header('Content-Type', 'application/json');
            }
        }
        else{
            $response = array(
                "code"=>"ACCESS_DENIED",
                "message"=> "request is invalid/missing a required input"
            );
            return response($response,401)
               ->header('Content-Type', 'application/json');
        }
    }
    public function betGame(Request $request){
        $startTime =  microtime(true);
        //Helper::saveLog('BetGame(EDP)', 2, json_encode($request->getContent()), "BEFORE BET");
        if($request->has("bonusId")){
            $sha1key = sha1($request->amount.''.$request->bonusId.''.$request->date.''.$request->gameId.''.$request->id.''.$request->token.''.$this->secretkey);
            // return $sha1key;
        }
        else{
            $sha1key = sha1($request->amount.''.$request->date.''.$request->gameId.''.$request->id.''.$request->token.''.$this->secretkey);
            //return $sha1key;
        }
        if($sha1key == $request->sign){
            $round_id = $request->token."-".$request->gameId;
            $client_details = ProviderHelper::getClientDetails('token', $request->token);
            // $transaction = TransactionHelper::checkGameTransactionData($request->id);
            $transaction = GameTransactionMDB::findGameExt($request->id,false,'transaction_id',$client_details);
            if($transaction != 'false'){
                $transactionData = json_decode($transaction->mw_response);
                $response = array(
                    "transactionId" => $transactionData->transactionId,
                    "balance" => $transactionData->balance
                );
                return response($response,402)
                    ->header('Content-Type', 'application/json');
            }
            if($client_details){
                // $game_transaction = Helper::checkGameTransaction($request->id);
                $gen_game_trans_id = ProviderHelper::idGenerate($client_details->connection_name,1);
                $gen_game_extid = ProviderHelper::idGenerate($client_details->connection_name,2);
                $game_transaction = GameTransactionMDB::checkGameTransactionExist($request->id,false,false,$client_details);
                $bet_amount = $game_transaction ? 0 : $request->amount;
                $bet_amount = $bet_amount < 0 ? 0 :$bet_amount;
                $game_details = Helper::getInfoPlayerGameRound($request->token);
                $json_data = array(
                    "transid" => $request->id,
                    "amount" => $request->amount / 1000,
                    "roundid" => $round_id
                );
                // $game = Helper::getGameTransaction($request->token,$request->gameId);

                $game = GameTransactionMDB::getGameTransactionByTokenAndRoundId($request->token, $round_id, $client_details);
                // $checkrefundid = Helper::checkGameTransaction($request->id,$request->gameId,3);
                $checkrefundid = GameTransactionMDB::checkGameTransactionExist($request->id,$round_id,3,$client_details);
                if($game == null && $checkrefundid == false){
                    // if($request->has('bonusId')){
                    //     $gametransactionid=$this->createGameTransaction('debit', $json_data, $game_details, $client_details, $is_freespin=1);
                    // }else{
                    //     $gametransactionid=$this->createGameTransaction('debit', $json_data, $game_details, $client_details);
                    // } 
                    $gameTransactionData = array(
                          "provider_trans_id" => $request->id,
                          "token_id" => $client_details->token_id,
                          "game_id" => $game_details->game_id,
                          "round_id" => $round_id,
                          "bet_amount" => $request->amount / 1000,
                          "win" => 5,
                          "pay_amount" => 0,
                          "income" => 0,
                          "entry_id" => 1,
                      );
                     GameTransactionMDB::createGametransactionV2($gameTransactionData,$gen_game_trans_id,$client_details); //create game_transaction
                     $gametransactionid = $gen_game_trans_id;
                }
                elseif($checkrefundid != false){
                    $gametransactionid = 0;
                }
                else{
                    $gametransactionid = $game->game_trans_id;
                }
                // $transactionId=$this->createGameTransactionExt($gametransactionid,$request,null,null,null,1,$client_details);
                $transactionId = $gen_game_extid;
                $sendtoclient =  microtime(true);
                if($request->has('bonusId')){
                    $client_response = ClientRequestHelper::fundTransfer($client_details,number_format(0  ,2, '.', ''),$game_details->game_code,$game_details->game_name,$transactionId,$gametransactionid,"debit");
                }else{
                    $client_response = ClientRequestHelper::fundTransfer($client_details,number_format($bet_amount/1000,2, '.', ''),$game_details->game_code,$game_details->game_name,$transactionId,$gametransactionid,"debit");
                }
                $client_response_time = microtime(true) - $sendtoclient;
                if(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "200"){
                    if($request->has('bonusId')){
                        $freespin = FreeSpinHelper::updateFreeSpinBalance($request->bonusId);
                        if($freespin !=null){
                            $sessions =array(
                                "transactionId" => $request->id,
                                "balance"=>round($client_response->fundtransferresponse->balance * 1000,2),
                                "bonus" => array(
                                    "id" => $request->bonusId,
                                    "bet" =>  "BONUS",
                                    "win" => "BOUS"
                                ),
                                "spins" => $freespin
                            );
                        }
                        else{
                            $sessions = array(
                                "code" =>"INSUFFICIENT_FUNDS",
                                "message"=>"Player has insufficient fundssssss"
        
                            );
                        }   
                    }
                    else{
                        $sessions =array(
                            "transactionId" => $request->id,
                            "balance"=>round($client_response->fundtransferresponse->balance * 1000,2)
                        );
                    }
                    $gameTransactionEXTData = array(
                          "game_trans_id" => $gen_game_trans_id,
                          "provider_trans_id" => $request->id,
                          "round_id" => $round_id,
                          "amount" => $bet_amount/1000,
                          "game_transaction_type"=> 1,
                          // "provider_request" =>json_encode($req),
                      );
                     GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$transactionId,$client_details); //create extension
                    // $this->updateGameTransactionExt($transactionId,$client_response->requestoclient,$sessions,$client_response,null,$client_details);
                    Helper::saveLog('responseTime(EDP)', 12, json_encode(["type"=>"debitproccess","stating"=>$startTime,"response"=>microtime(true)]), ["response"=>microtime(true) - $startTime,"mw_response"=> microtime(true) - $startTime - $client_response_time,"clientresponse"=>$client_response_time]);
                    return response($sessions,200)
                        ->header('Content-Type', 'application/json');
                }
                elseif(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "402"){
                    $response = array(
                        "code" =>"INSUFFICIENT_FUNDS",
                        "message"=>"Player has insufficient funds"

                    );
                    $this->createGameTransactionExt($gametransactionid,$request,$client_response->requestoclient,$response,$client_response,1,$client_details);
                    return response($response,402)
                    ->header('Content-Type', 'application/json');
                }
            }

        }
        else{
            $response = array(
                "code"=>"ACCESS_DENIED",
                "message"=> "request is invalid/missing a required input"
            );
            return response($response,401)
               ->header('Content-Type', 'application/json');
        }
    }
    public function winGame(Request $request){
        // dd($request->all());
        $startTime =  microtime(true);
        // this is to identify the diff type of win
        Helper::saveLog("EDP WIN",9,json_encode($request->getContent()),"BEFORE WIN PROCESS");
        if($request->has("progressive")&&$request->has("progressiveDesc")){
            $sha1key = sha1($request->amount.''.$request->date.''.$request->gameId.''.$request->id.''.$request->progressive.''.$request->progressiveDesc.''.$request->token.''.$this->secretkey);
            $payout_reason = "Jackpot for the Game Round ID ".$request->gameId;
            
        }
        elseif($request->amount == 0){
            $sha1key = sha1($request->amount.''.$request->date.''.$request->gameId.''.$request->token.''.$this->secretkey);
            $payout_reason = "Win 0 for the Game Round ID ".$request->gameId;
           //Helper::saveLog("debit",9,json_encode($request->getContent()),"WIN");
        }
        elseif($request->has("bonusId")){
            $sha1key = sha1($request->amount.''.$request->bonusId.''.$request->date.''.$request->gameId.''.$request->id.''.$request->token.''.$this->secretkey);
        }
        else{
            $sha1key = sha1($request->amount.''.$request->date.''.$request->gameId.''.$request->id.''.$request->token.''.$this->secretkey);
            $payout_reason = null;
        }
        if($sha1key == $request->sign){
            $round_id = $request->token."-".$request->gameId;
            $client_details = ProviderHelper::getClientDetails('token', $request->token);
            GameRound::create($request->gameId, $client_details->token_id);
            if($client_details){
                if($request->amount != 0){
                    $game_transaction = GameTransactionMDB::checkGameTransactionExist($request->id,false,false,$client_details);
                    // $game_transaction = Helper::checkGameTransaction($request->id);
                    $win_amount = $game_transaction ? 0 : $request->amount;
                    $win = 1;
                    $trans_id = $request->id;
                }
                else{
                    $getgametransaction = GameTransactionMDB::findGameTransactionDetails($round_id, 'round_id' ,false,$client_details);
                    $client_details->connection_name = $getgametransaction->connection_name;
                    $game_transaction = GameTransactionMDB::checkGameTransactionExist($getgametransaction->provider_trans_id,$round_id,2,$client_details);
                    // $getgametransaction = Helper::getGameTransaction($request->token,$request->gameId);
                    // $game_transaction = Helper::checkGameTransaction($getgametransaction->provider_trans_id,$request->gameId,2);
                    $win_amount = 0;
                    $win = 0;
                    $trans_id = $getgametransaction->provider_trans_id;
                    //Helper::saveLog("credit",9,json_encode($request->getContent()),$getgametransaction);
                }
                $game_details = Helper::getInfoPlayerGameRound($request->token);
                $json_data = array(
                    "transid" => $trans_id,
                    "amount" => $request->amount / 1000,
                    "roundid" => $round_id,
                    "win" => $request->has("bonusId")?7:$win
                );
                // $game = Helper::getGameTransaction($request->token,$request->gameId);
                $game = GameTransactionMDB::findGameTransactionDetails($round_id, 'round_id',false, $client_details);
                // $game = GameTransactionMDB::getGameTransactionByTokenAndRoundId($request->token,$request->gameId,$client_details);
                if($game == 'false'){
                    $response = array(
                        "code"=>"ACCESS_DENIED",
                        "message"=> "request is invalid/missing a required input"
                    );
                    return response($response,200)
                       ->header('Content-Type', 'application/json');
                }
                $transactionId = ProviderHelper::idGenerate($client_details->connection_name,2);
                $client_details->connection_name = $game->connection_name;
                $gameupdate = $this->updateGameTransaction($game,$json_data,"credit",$client_details);
                $gametransactionid = $game->game_trans_id;
                // $transactionId=$this->createGameTransactionExt($gametransactionid,null,null,null,null,2,$client_details);
                $wingametransactionext = array(
                    "game_trans_id" => $gametransactionid,
                    "provider_trans_id" => $trans_id,
                    "round_id" =>$round_id,
                    "amount" =>$request->amount / 1000,
                    "game_transaction_type"=>2,
                );
                // $winGametransactionExtId = GameTransactionMDB::createGameTransactionExt($wingametransactionext,$client_details);
                GameTransactionMDB::createGameTransactionExtV2($wingametransactionext,$transactionId,$client_details); //create game_transaction
                $sendtoclient =  microtime(true);
                $win_or_lost = $request->amount == 0 ? 0 : 1;
                if($request->has("bonusId")){
                    $freespin = FreeSpinHelper::getFreeSpinBalanceByFreespinId($request->bonusId);
                    $sessions =array(
                        "transactionId" => $trans_id,
                        "balance"=>round($client_details->balance * 1000,2) + number_format($win_amount/1000,2, '.', ''),
                        "spins" => $freespin
                    );
                }
                else{
                    $sessions =array(
                    "transactionId" => $trans_id,
                    "balance"=>round($client_details->balance * 1000,2) + number_format($win_amount/1000,2, '.', '')
                    );
                } 
                $action_payload = [
                    "type" => "custom", #genreral,custom :D # REQUIRED!
                    "custom" => [
                        "provider" => 'EDP',
                        "game_transaction_ext_id" => $transactionId,
                        "client_connection_name" => $client_details->connection_name,
                        "win_or_lost" => $win_or_lost,
                    ],
                    "provider" => [
                        "provider_request" => json_encode($request->all()), #R
                        "provider_trans_id"=>$trans_id, #R
                        "provider_round_id"=>$round_id, #R
                        'provider_name' => $game_details->provider_name
                    ],
                    "mwapi" => [
                        "roundId"=>$game->game_trans_id, #R
                        "type"=>2, #R
                        "game_id" => $game_details->game_id, #R
                        "player_id" => $client_details->player_id, #R
                        "mw_response" => $response, #R
                    ]
                ];
                $client_response = ClientRequestHelper::fundTransfer_TG($client_details,number_format($win_amount/1000,2, '.', ''),$game_details->game_code,$game_details->game_name,$transactionId,$gametransactionid,"credit");
                $client_response_time = microtime(true) - $sendtoclient;
                if(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "200"){
                    // if($request->has("bonusId")){
                    //     $freespin = FreeSpinHelper::getFreeSpinBalanceByFreespinId($request->bonusId);
                    //     $sessions =array(
                    //         "transactionId" => $trans_id,
                    //         "balance"=>round($client_response->fundtransferresponse->balance * 1000,2),
                    //         "spins" => $freespin
                    //     );
                    // }
                    // else{
                    //     $sessions =array(
                    //     "transactionId" => $trans_id,
                    //     "balance"=>round($client_response->fundtransferresponse->balance * 1000,2)
                    //     );
                    // } 
                $createGameTransactionLog = [
                      "connection_name" => $client_details->connection_name,
                      "column" =>[
                          "game_trans_ext_id" => $transactionId,
                          "request" => json_encode($request),
                          "response" => json_encode($response),
                          "log_type" => "provider_details",
                          "transaction_detail" => "success",
                      ]
                  ];
                ProviderHelper::queTransactionLogs($createGameTransactionLog);
                // $this->updateGameTransactionExt($transactionId,$client_response->requestoclient,$sessions,$client_response,null,$client_details);
                Helper::saveLog('responseTime(EDP)', 12, json_encode(["type"=>"debitproccess","stating"=>$startTime,"response"=>microtime(true)]), ["response"=>microtime(true) - $startTime,"mw_response"=> microtime(true) - $startTime - $client_response_time,"clientresponse"=>$client_response_time]);
                return response($sessions,200)
                       ->header('Content-Type', 'application/json');
                }
            }

        }
        else{
            $response = array(
                "code"=>"ACCESS_DENIED",
                "message"=> "request is invalid/missing a required input"
            );
            return response($response,401)
               ->header('Content-Type', 'application/json');
        }
    }
    public function refundGame(Request $request){
        Helper::saveLog('Refund(EDP)', 2, json_encode($request->getContent()), "all refund");
        $sha1key = sha1($request->amount.''.$request->date.''.$request->gameId.''.$request->id.''.$request->token.''.$this->secretkey);
        if($sha1key == $request->sign){
            $round_id = $request->token."-".$request->gameId;
            $client_details = ProviderHelper::getClientDetails('token', $request->token);
            $game_transaction = GameTransactionMDB::checkGameTransactionExist($request->id,$round_id,1,$client_details);
            $request->amount = $game_transaction?$request->amount:0;
            if($client_details){
                $game_details = Helper::getInfoPlayerGameRound($request->token);
                $json_data = array(
                    "transid" => $request->id,
                    "amount" => $request->amount / 1000,
                    "roundid" => $round_id
                );
                $game = GameTransactionMDB::findGameTransactionDetails($round_id,'round_id',false, $client_details);
                if($game != 'false'){
                    $client_details->connection_name = $game->connection_name;
                    $gameupdate = $this->updateGameTransaction($game,$json_data,"refund",$client_details);
                    $gametransactionid = $game->game_trans_id;        
                }
                else{
                    $gametransactionid=0;
                }

                $transactionId=$this->createGameTransactionExt($gametransactionid,$request,null,null,null,3,$client_details);
                $client_response = ClientRequestHelper::fundTransfer($client_details,number_format($request->amount/1000,2, '.', ''),$game_details->game_code,$game_details->game_name,$transactionId,$gametransactionid,"credit",true);
                
                $sessions =array(
                    "transactionId" => $request->id,
                    "balance"=>round($client_response->fundtransferresponse->balance * 1000,2)
                ); 
                $this->updateGameTransactionExt($transactionId,$client_response->requestoclient,$sessions,$client_response,null,$client_details);
                return response($sessions,200)
                       ->header('Content-Type', 'application/json');
            }
        }  
    }
    public function endGameSession(Request $request){
        return response([],200)
        ->header('Content-Type', 'application/json');
    }
    public function freeSpin(Request $request){
       $freespin_update = FreeSpinHelper::updateFreeSpinBalance($request->freespin_id,$request->amount);
       dd($freespin_update);
    }

    public function getInfoPlayerGameRound($player_token){
        $games = DB::select("SELECT g.game_name,g.game_id,g.game_code FROM player_game_rounds as pgr JOIN player_session_tokens as pst ON pst.player_token = pgr.player_token JOIN games as g ON g.game_id = pgr.game_id JOIN players as ply ON pst.player_id = ply.player_id WHERE pgr.player_token = '".$player_token."'");
        $count = count($games);
        return $count > 0 ? $games[0] : null;
    }
    public static function createGameTransaction($method, $request_data, $game_data, $client_data,$is_freespin=null){
        $trans_data = [
            "token_id" => $client_data->token_id,
            "game_id" => $game_data->game_id,
            "round_id" => $request_data["roundid"]
        ];

        switch ($method) {
            case "debit":
                    $trans_data["provider_trans_id"] = $request_data["transid"];
                    $trans_data["bet_amount"] = abs($request_data["amount"]);
                    $trans_data["win"] = 5;
                    $trans_data["pay_amount"] = 0;
                    $trans_data["entry_id"] = 1;
                    $trans_data["income"] = 0;
                break;
            case "credit":
                    $trans_data["provider_trans_id"] = $request_data["transid"];
                    $trans_data["bet_amount"] = 0;
                    $trans_data["win"] = 5;
                    $trans_data["pay_amount"] = abs($request_data["amount"]);
                    $trans_data["entry_id"] = 2;
                break;
            case "refund":
                    $trans_data["provider_trans_id"] = $request_data["transid"];
                    $trans_data["bet_amount"] = 0;
                    $trans_data["win"] = 0;
                    $trans_data["pay_amount"] = 0;
                    $trans_data["entry_id"] = 2;
                break;

            default:
        }
        /*var_dump($trans_data); die();*/
        // return DB::table('game_transactions')->insertGetId($trans_data);         
        return GameTransactionMDB::createGametransaction($trans_data, $client_data);
    }
    public static function createGameTransactionExt($gametransaction_id,$provider_request,$mw_request,$mw_response,$client_response,$game_transaction_type,$client_details){
        $gametransactionext = array(
            "game_trans_id" => $gametransaction_id,
            "round_id" =>$client_details->player_token."-".$provider_request->gameId,
            "amount" =>$provider_request->amount / 1000,
            "game_transaction_type"=>$game_transaction_type,
            "provider_request" =>json_encode($provider_request->getContent()),
            "mw_request"=>json_encode($mw_request),
            "mw_response" =>json_encode($mw_response),
            "client_response" =>json_encode($client_response),
        );
        if($provider_request->has("id")){
            $gametransactionext["provider_trans_id"] = $provider_request->id;
        }
        else{
            $gametransactionext["provider_trans_id"] = 0;
        }
        // $gamestransaction_ext_ID = DB::table("game_transaction_ext")->insertGetId($gametransactionext);
        // return $gamestransaction_ext_ID;
        return GameTransactionMDB::createGameTransactionExt($gametransactionext, $client_details);
    }
    public static function updateGameTransactionExt($gametransextid,$mw_request,$mw_response,$client_response,$time=null, $client_details){
        $gametransactionext = array(
            "mw_request"=>json_encode($mw_request),
            "mw_response" =>json_encode($mw_response),
            "client_response" =>json_encode($client_response),
            "general_details"=>$time,
        );
        // DB::table('game_transaction_ext')->where("game_trans_ext_id",$gametransextid)->update($gametransactionext);
        GameTransactionMDB::updateGametransactionEXT($gametransactionext,$gametransextid,$client_details);
    }
    public static function updateGameTransaction($existingdata,$request_data,$type,$client_details){
        // DB::enableQueryLog();
        switch ($type) {
            case "debit":
                    $trans_data["win"] = 0;
                    $trans_data["pay_amount"] = 0;
                    $trans_data["income"]=$existingdata->bet_amount-$request_data["amount"];
                    $trans_data["entry_id"] = 1;
                break;
            case "credit":
                    $trans_data["win"] = $request_data["win"];
                    $trans_data["pay_amount"] = abs($request_data["amount"]);
                    $trans_data["income"]=$existingdata->bet_amount-$request_data["amount"];
                    $trans_data["entry_id"] = 2;
                break;
            case "refund":
                    $trans_data["win"] = 4;
                    $trans_data["pay_amount"] = $request_data["amount"];
                    $trans_data["entry_id"] = 2;
                    $trans_data["income"]= $existingdata->bet_amount-$request_data["amount"];
                break;
            case "fail":
                $trans_data["win"] = 2;
                $trans_data["pay_amount"] = $request_data["amount"];
                $trans_data["entry_id"] = 1;
                $trans_data["income"]= 0;
            break;
            default:
        }
        /*var_dump($trans_data); die();*/
        // Helper::saveLog('TIMEupdateGameTransaction(EVG)', 189, json_encode(DB::getQueryLog()), "DB TIME");
        // return DB::table('game_transactions')->where("game_trans_id",$existingdata->game_trans_id)->update($trans_data);
        return GameTransactionMDB::updateGametransaction($trans_data, $existingdata->game_trans_id, $client_details);
    }

}

