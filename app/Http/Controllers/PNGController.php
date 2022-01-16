<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use SimpleXMLElement;
use App\Helpers\PNGHelper;
use App\Helpers\FreeSpinHelper;
use GuzzleHttp\Client;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransactionMDB;
use DB;
class PNGController extends Controller
{
    public $provider_db_id, $middleware_api, $prefix;
    
    public function __construct()
    {
        // $this->provider_db_id = config('providerlinks.png.provider_id');
        $this->prefix = "PNG_"; // for idom name
    }
    public function authenticate(Request $request){
        $data = $request->getContent();
        $xmlparser = new SimpleXMLElement($data);
        $accessToken = "secrettoken";
        if($xmlparser->username){
            $client_details = ProviderHelper::getClientDetails('token', $xmlparser->username);
            if($client_details){
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
                                    "gamelaunch" => true
                                ]]
                    )]
                );
                $client_response = json_decode($guzzle_response->getBody()->getContents());
                Helper::saveLog('AuthPlayer(PNG)', 12, json_encode($xmlparser),$client_response);

                $array_data = array(
                    "externalId" => $client_details->player_id,
                    "statusCode" => 0,
                    "statusMessage" => "ok",
                    "userCurrency" => $client_details->default_currency,
                    "country" => isset($client_details->country_code) ? $client_details->country_code : 'TH',
                    "birthdate"=> "1990-04-29",
                    "externalGameSessionId" => $xmlparser->username,
                    "real"=> number_format($client_response->playerdetailsresponse->balance,2,'.', ''),
                );
                return PNGHelper::arrayToXml($array_data,"<authenticate/>");
            }
            else{
                $array_data = array(
                    "statusCode" => 4,
                );
                return PNGHelper::arrayToXml($array_data,"<authenticate/>");
            }
        }
        else{
            $array_data = array(
                "statusCode" => 4,
            );
            return PNGHelper::arrayToXml($array_data,"<authenticate/>");
        }
        
    }
    public function reserve(Request $request){
        $data = $request->getContent();
        $xmlparser = new SimpleXMLElement($data);
        Helper::saveLog('PNG reserve MDB', 50,json_encode($xmlparser), 'endpoint hit');
        if($xmlparser->externalGameSessionId){
            $client_details = ProviderHelper::getClientDetails('token', $xmlparser->externalGameSessionId);
            if($client_details){
                try{
                    ProviderHelper::idenpotencyTable($this->prefix.$xmlparser->transactionId);
                }catch(\Exception $e){
                    $bet_transaction = GameTransactionMDB::findGameExt($xmlparser->transactionId, 1,'transaction_id', $client_details);
                    if ($bet_transaction != 'false' && $bet_transaction->general_details != 'failed') {
                        $array_data = array(
                            "real" => round($client_details->balance,2),
                            "statusCode" => 0,
                        );
                        Helper::saveLog('PNG reserve MDB', 50,json_encode($array_data), 'RESPONSE');
                        return PNGHelper::arrayToXml($array_data,"<reserve/>");   
                    } else {
                        $array_data = array(
                            "statusCode" => 7,
                        );
                        Helper::saveLog('PNG reserve MDB', 50,json_encode($array_data), 'RESPONSE');
                        return PNGHelper::arrayToXml($array_data,"<reserve/>");
                    } 
                }
                $game_details = Helper::getInfoPlayerGameRound($xmlparser->externalGameSessionId);
                $game = GameTransactionMDB::findGameTransactionDetails($xmlparser->roundId,'round_id', false, $client_details);
                // $game = Helper::getGameTransaction($xmlparser->externalGameSessionId,$xmlparser->roundId);
                if($game == 'false'){
                    $gameTransactionData = array(
                        "provider_trans_id" => $xmlparser->transactionId,
                        "token_id" => $client_details->token_id,
                        "game_id" => $game_details->game_id,
                        "round_id" => $xmlparser->roundId,
                        "bet_amount" => (float)$xmlparser->real,
                        "pay_amount" =>0,
                        "income" => 0,
                        "win" => 5,
                        "entry_id" =>1,
                    );
                    $gametransactionid = GameTransactionMDB::createGametransaction($gameTransactionData,$client_details);
                }
                else{
                    $json_data = array(
                        "transid" => $xmlparser->transactionId,
                        "amount" => (float)$xmlparser->real,
                        "roundid" => $xmlparser->roundId
                    );
                    $client_details->connection_name = $game->connection_name;
                    $gametransactionid = $game->game_trans_id;
                }
                $wingametransactionext = array(
                    "game_trans_id" => $gametransactionid,
                    "provider_trans_id" => $xmlparser->transactionId,
                    "round_id" =>$xmlparser->roundId,
                    "amount" =>(float)$xmlparser->real,
                    "game_transaction_type"=>1,
                    "provider_request" => json_encode($xmlparser),
                    "mw_response" => 'null'
                );
                $transactionId = GameTransactionMDB::createGameTransactionExt($wingametransactionext,$client_details);
                // $transactionId=PNGHelper::createPNGGameTransactionExt($gametransactionid,$xmlparser,null,null,null,1);
                $client_response = ClientRequestHelper::fundTransfer($client_details,(float)$xmlparser->real,$game_details->game_code,$game_details->game_name,$transactionId,$gametransactionid,"debit");
                if(isset($client_response->fundtransferresponse->status->code) && $client_response->fundtransferresponse->status->code == "200"){
                    $balance = round($client_response->fundtransferresponse->balance,2);
                    if($game != 'false'){
                        $this->updateGameTransaction($game,$json_data,'debit',$client_details);
                    }
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
                    $array_data = array(
                        "real" => $balance,
                        "statusCode" => 0,
                    );
                    $dataToUpdate = array(
                        "mw_response" => json_encode($array_data),
                    );
                    GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$transactionId,$client_details);
                    Helper::saveLog('PNG reserve MDB', 50,json_encode($array_data), 'RESPONSE');
                    return PNGHelper::arrayToXml($array_data,"<reserve/>");
                }
                elseif(isset($client_response->fundtransferresponse->status->code) && $client_response->fundtransferresponse->status->code == "402"){
                    if($game == 'false'){
                        $updateGameTransaction = [
                            "win" => 2
                        ];
                        GameTransactionMDB::updateGametransaction($updateGameTransaction, $gametransactionid, $client_details);
                    }
                    $array_data = array(
                        "statusCode" => 7,
                    );
                    $dataToUpdate = array(
                        "mw_response" => json_encode($array_data),
                        "general_details" => "failed"
                    );
                    GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$transactionId,$client_details);
                    Helper::saveLog('PNG reserve MDB', 50,json_encode($array_data), 'RESPONSE');
                    return PNGHelper::arrayToXml($array_data,"<reserve/>");
                } else {
                    if($game == 'false'){
                        $updateGameTransaction = [
                            "win" => 2
                        ];
                        GameTransactionMDB::updateGametransaction($updateGameTransaction, $gametransactionid, $client_details);
                    }
                    $array_data = array(
                        "statusCode" => 7,
                    );
                    $dataToUpdate = array(
                        "mw_response" => json_encode($array_data),
                        "general_details" => "failed"
                    );
                    GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$transactionId,$client_details);
                    Helper::saveLog('PNG reserve MDB', 50,json_encode($array_data), 'RESPONSE');
                    return PNGHelper::arrayToXml($array_data,"<reserve/>");
                }
            }
            else{
                $array_data = array(
                    "statusCode" => 4,
                );
                Helper::saveLog('PNG reserve MDB', 50,json_encode($array_data), 'RESPONSE');
                return PNGHelper::arrayToXml($array_data,"<authenticate/>");
            }
        } 
    }
    public function release(Request $request){
        $startTime =  microtime(true);
        $data = $request->getContent();
        $xmlparser = new SimpleXMLElement($data);
        Helper::saveLog('PNGReleasechecker(PNG)', 189, json_encode($xmlparser), "Checker");
        $accessToken = "secrettoken";
        $client_details = ProviderHelper::getClientDetails('token',$xmlparser->externalGameSessionId);
        if($client_details){
            if($xmlparser->roundId == 0){
                $array_data = array(
                    "real" => $client_details->balance,
                    "statusCode" => 0,
                );
                return PNGHelper::arrayToXml($array_data,"<release/>");
            }
            try{
                ProviderHelper::idenpotencyTable($this->prefix.$xmlparser->transactionId);
            }catch(\Exception $e){
                $array_data = array(
                    "real" => round($client_details->balance,2),
                    "statusCode" => 0,
                );
                Helper::saveLog('PNG reserve MDB', 50,json_encode($array_data), 'RESPONSE');
                return PNGHelper::arrayToXml($array_data,"<reserve/>");
            }
            // $returnWinTransaction = GameTransactionMDB::checkGameTransactionExist($xmlparser->transactionId,false,false,$client_details);
            // if($returnWinTransaction){
            //     $array_data = array(
            //         "real" => round(Helper::getBalance($client_details),2),
            //         "statusCode" => 0,
            //     );
            //     return PNGHelper::arrayToXml($array_data,"<release/>");
            // }
            $win = $xmlparser->real == 0 ? 0 : 1;
            $game_details = Helper::getInfoPlayerGameRound($xmlparser->externalGameSessionId);
            $json_data = array(
                "transid" => $xmlparser->transactionId,
                "amount" => (float)$xmlparser->real,
                "roundid" => $xmlparser->roundId,
                // "payout_reason" => null,
                "win" => $win,
            );
            $game = GameTransactionMDB::findGameTransactionDetails($xmlparser->roundId,'round_id', false, $client_details);
            $fund_extra_data = [];
            $is_freespin = false;
            if($game == 'false'){
                $entry_id = 1;
                $gameTransactionData = array(
                    "provider_trans_id" => $xmlparser->transactionId,
                    "token_id" => $client_details->token_id,
                    "game_id" => $game_details->game_id,
                    "round_id" => $xmlparser->roundId,
                    "bet_amount" => 0,
                    "pay_amount" => (float)$xmlparser->real,
                    "income" => 0,
                    "win" => 5,
                );
                $gametransactionid = GameTransactionMDB::createGametransaction($gameTransactionData,$client_details);

                $income = 0 - (float)$xmlparser->real;
                
                if(isset($xmlparser->freegameExternalId) && $xmlparser->freegameExternalId != "") {
                    $is_freespin = true;
                    $fund_extra_data = [
                        'fundtransferrequest' => [
                            'fundinfo' => [
                                'freespin' => true
                            ]
                        ]
                    ];
                    //getTransaction
                    $getFreespin = FreeSpinHelper::getFreeSpinDetails($xmlparser->freegameExternalId, "provider_trans_id" );

                    if($getFreespin){
                        //update transaction
                            $status = ($getFreespin->spin_remaining - 1) == 0 ? 2 : 1;
                            $updateFreespinData = [
                                "status" => $status,
                                "spin_remaining" => $getFreespin->spin_remaining - 1
                            ];
                            $updateFreespin = FreeSpinHelper::updateFreeSpinDetails($updateFreespinData, $getFreespin->freespin_id);
                            //create transction 
                            $createFreeRoundTransaction = array(
                                "game_trans_id" => $gametransactionid,
                                'freespin_id' => $getFreespin->freespin_id
                            );
                            FreeSpinHelper::createFreeRoundTransaction($createFreeRoundTransaction);
                    }
                }


                $wingametransactionext = array(
                    "game_trans_id" => $gametransactionid,
                    "provider_trans_id" => $xmlparser->transactionId,
                    "round_id" =>$xmlparser->roundId,
                    "amount" => 0,
                    "game_transaction_type"=>1,
                    "provider_request" => json_encode($xmlparser),
                );
                $transactionId = GameTransactionMDB::createGameTransactionExt($wingametransactionext,$client_details);
                // $transactionId=PNGHelper::createPNGGameTransactionExt($gametransactionid,$xmlparser,null,null,null,1);
                $client_response = ClientRequestHelper::fundTransfer($client_details,0,$game_details->game_code,$game_details->game_name,$transactionId,$gametransactionid,"debit",false,$fund_extra_data);
                if(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "200"){
                    $balance = round($client_response->fundtransferresponse->balance,2);
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
                    $array_data2 = array(
                        "real" => $balance,
                        "statusCode" => 0,
                    );
                    
                    $dataToUpdate = array(
                        "mw_response" => json_encode($array_data2),
                    );
                    GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$transactionId,$client_details);
                    // Helper::updateGameTransactionExt($transactionId,$client_response->requestoclient,$array_data,$client_response);
                }
                elseif(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "402"){
                    $array_data = array(
                        "statusCode" => 7,
                    );
                    $dataToUpdate = array(
                        "mw_response" => json_encode($array_data),
                        "client_response" => json_encode($client_response)
                    );
                    GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$transactionId,$client_details);
                }
            }
            else{
                $entry_id = 2;
                $client_details->connection_name = $game->connection_name;
                //$json_data["amount"] = round($data["args"]["win"],2)+ $game->pay_amount;
                
                $gametransactionid = $game->game_trans_id;
                $income = $game->bet_amount - (float)$xmlparser->real;
            }
            $wingametransactionext = array(
                "game_trans_id" => $gametransactionid,
                "provider_trans_id" => $xmlparser->transactionId,
                "round_id" =>$xmlparser->roundId,
                "amount" =>(float)$xmlparser->real,
                "game_transaction_type"=>2,
                "provider_request" => json_encode($xmlparser),
                "mw_response" => 'null'
            );
            $transactionId = GameTransactionMDB::createGameTransactionExt($wingametransactionext,$client_details);
            // $transactionId = PNGHelper::createPNGGameTransactionExt($gametransactionid,$xmlparser,null,null,null,2);
            $balance = round($client_details->balance,2) + (float)$xmlparser->real;
            ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
            $array_data = array(
                "real" => $balance,
                "statusCode" => 0,
            );
            $action_payload = [
                    "type" => "custom", #genreral,custom :D # REQUIRED!
                    "custom" => [
                        "provider" => 'PlayNGo',
                        "client_connection_name" => $client_details->connection_name,
                        "win_or_lost" => $win,
                        "entry_id" => $entry_id,
                        "pay_amount" => (float)$xmlparser->real,
                        "income" => $income,
                        "game_trans_ext_id" => $transactionId
                    ],
                    "provider" => [
                        "provider_request" => $xmlparser, #R
                        "provider_trans_id"=> $xmlparser->transactionId, #R
                        "provider_round_id"=> $xmlparser->roundId, #R
                    ],
                    "mwapi" => [
                        "roundId"=>$gametransactionid, #R
                        "type"=>2, #R
                        "game_id" => $game_details->game_id, #R
                        "player_id" => $client_details->player_id, #R
                        "mw_response" => $array_data, #R
                    ],
                    'fundtransferrequest' => [
                        'fundinfo' => [
                            'freespin' => $is_freespin,
                        ]
                    ]
            ];


            $sendtoclient =  microtime(true);  
            $client_response = ClientRequestHelper::fundTransfer_TG($client_details,(float)$xmlparser->real,$game_details->game_code,$game_details->game_name,$gametransactionid,'credit',false,$action_payload);
            $client_response_time = microtime(true) - $sendtoclient;


            if(isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "200"){
                if($game != 'false'){
                    $this->updateGameTransaction($game,$json_data,'credit',$client_details);
                }
                $dataToUpdate = array(
                    "mw_response" => json_encode($array_data),
                );
                GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$transactionId,$client_details);
                // ProviderHelper::saveLogProcessTime($gametransactionid, 1334, json_encode(["type"=>"debitproccess","stating"=>$startTime,"response"=>microtime(true)]), ["response"=>microtime(true) - $startTime,"mw_response"=> microtime(true) - $startTime - $client_response_time,"clientresponse"=>$client_response_time]);
                // Helper::updateGameTransactionExt($transactionId,$client_response->requestoclient,$array_data,$client_response);
                return PNGHelper::arrayToXml($array_data,"<release/>");
            }elseif (isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "402") {
                $array_data = array(
                    "statusCode" => 7,
                );
                $dataToUpdate = array(
                    "mw_response" => json_encode($array_data),
                );
                GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$transactionId,$client_details);
                return PNGHelper::arrayToXml($array_data,"<release/>");
            }
            // else{
            //     $dataToUpdate = array(
            //         "mw_response" => "failed",
            //     );
            //     GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$transactionId,$client_details);
            //     return "something error with the client";
            // }
        }
        else{
            $array_data = array(
                "statusCode" => 4,
            );
            return PNGHelper::arrayToXml($array_data,"<authenticate/>");
        }
    }
    public function balance(Request $request){
        $data = $request->getContent();
        $xmlparser = new SimpleXMLElement($data);
        Helper::saveLog('PNG balance MDB', 50,json_encode($xmlparser), 'endpoint hit');
        // $accessToken = "secrettoken";
        // if($accessToken != $xmlparser->accessToken){
        //     $array_data = array(
        //         "statusCode" => 4,
        //     );
        //     return PNGHelper::arrayToXml($array_data,"<balance/>");
        // }
        if($xmlparser->externalGameSessionId){
            $client_details = ProviderHelper::getClientDetails('token', $xmlparser->externalGameSessionId);
            if($client_details){
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
                                    "client_player_id"=>$client_details->client_player_id,
                                    "token" => $client_details->player_token,
                                    "gamelaunch" => true
                                ]]
                    )]
                );
                $client_response = json_decode($guzzle_response->getBody()->getContents());
                Helper::saveLog('AuthPlayer(PNG)', 12, json_encode($xmlparser),$client_response);

                $array_data = array(
                    "statusCode" => 0,
                    "real"=> number_format($client_response->playerdetailsresponse->balance,2,'.', ''),
                );
                return PNGHelper::arrayToXml($array_data,"<balance/>");
            }
            else{
                $array_data = array(
                    "statusCode" => 4,
                );
                return PNGHelper::arrayToXml($array_data,"<balance/>");
            }
        }
    }
    public function cancelReserve(Request $request){
        $startTime =  microtime(true);
        $data = $request->getContent();
        $xmlparser = new SimpleXMLElement($data);
        $accessToken = "secrettoken";
        if($xmlparser->externalGameSessionId){
            $client_details = ProviderHelper::getClientDetails('token', $xmlparser->externalGameSessionId);
        if($client_details){
            $reservechecker = GameTransactionMDB::checkGameTransactionExist($xmlparser->transactionId,false,false,$client_details);
            $rollbackchecker = $this->checkGameTransactionExist($xmlparser->transactionId,false,3,$client_details);
            if($reservechecker==false){
                $array_data = array(
                    "statusCode" => 0,
                    "externalTransactionId"=>""
                );
                Helper::saveLog('refundAlreadyexist(PNG)', 50,json_encode($xmlparser), $array_data);
                return PNGHelper::arrayToXml($array_data,"<cancelReserve/>");
            }
            if($rollbackchecker){
                $array_data = array(
                    "statusCode" => 0,
                    "externalTransactionId"=>$rollbackchecker->game_trans_ext_id
                );
                Helper::saveLog('refundAlreadyexist(PNG)', 50,json_encode($xmlparser), $array_data);
                return PNGHelper::arrayToXml($array_data,"<cancelReserve/>");
            }
                $win = 0;
                $game_details = Helper::getInfoPlayerGameRound($client_details->player_token);
                $json_data = array(
                    "transid" => $xmlparser->transactionId,
                    "amount" => (float)$xmlparser->real,
                    "roundid" => 0,
                );
                // $game = GameTransactionMDB::getGameTransactionByTokenAndRoundId($xmlparser->externalGameSessionId,$xmlparser->roundId,$client_details);
                $game = GameTransactionMDB::findGameTransactionDetails($xmlparser->roundId,'round_id', false, $client_details);
                // $client_details->connection_name = $game->connection_name;
                // $game = Helper::getGameTransaction($xmlparser->externalGameSessionId,$xmlparser->roundId);
                if($game == 'false'){

                    $gameTransactionData = array(
                        "provider_trans_id" => $xmlparser->transactionId,
                        "token_id" => $client_details->token_id,
                        "game_id" => $game_details->game_id,
                        "round_id" => $xmlparser->roundId,
                        "bet_amount" => 0,
                        "pay_amount" =>0,
                        "income" => 0,
                        "win" => 0,
                        "entry_id" =>2,
                    );
                    $gametransactionid = GameTransactionMDB::createGametransaction($gameTransactionData,$client_details);
                    // $gametransactionid=Helper::createGameTransaction('refund', $json_data, $game_details, $client_details); 



                }
                else{
                    $client_details->connection_name = $game->connection_name;
                    $this->updateGameTransaction($game,$json_data,'refund',$client_details);
                    // $gameupdate = Helper::updateGameTransaction($game,$json_data,"refund");
                    $gametransactionid = $game->game_trans_id;

                }

                $refundgametransactionext = array(
                    "game_trans_id" => $gametransactionid,
                    "provider_trans_id" => $xmlparser->transactionId,
                    "round_id" =>$xmlparser->roundId,
                    "amount" =>abs((float)$xmlparser->real),
                    "game_transaction_type"=>2,
                    "provider_request" =>$xmlparser,
                    "mw_response" => 'null'
                );
                $transactionId=GameTransactionMDB::createGameTransactionExt($refundgametransactionext,$client_details);
                // $transactionId=PNGHelper::createPNGGameTransactionExt($gametransactionid,$xmlparser,null,null,null,3);

                $sendtoclient =  microtime(true);
                $client_response = ClientRequestHelper::fundTransfer($client_details,(float)$xmlparser->real,$game_details->game_code,$game_details->game_name,$transactionId,$gametransactionid,"credit",true);
                $client_response_time = microtime(true) - $sendtoclient;


                $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
                
                if(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "200"){
                    $array_data = array(
                        "statusCode" => 0,
                    );
                    $dataToUpdate = array(
                        "mw_response" => json_encode($array_data),
                    );
                    GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$transactionId,$client_details);
                    // Helper::updateGameTransactionExt($transactionId,$client_response->requestoclient,$array_data,$client_response);
                    ProviderHelper::saveLogProcessTime($gametransactionid, 1334, json_encode(["type"=>"cancelprocess","stating"=>$startTime,"response"=>microtime(true)]), ["response"=>microtime(true) - $startTime,"mw_response"=> microtime(true) - $startTime - $client_response_time,"clientresponse"=>$client_response_time]);
                    return PNGHelper::arrayToXml($array_data,"<cancelReserve/>");
                }
            }
            else{
                $array_data = array(
                    "statusCode" => 4,
                );
                return PNGHelper::arrayToXml($array_data,"<cancelReserve/>");
            }
        } 
    }
    
    public static function updateGameTransaction($existingdata,$request_data,$type,$client_details){
        switch ($type) {
            case "debit":
                    $trans_data["win"] = 5;
                    $trans_data["bet_amount"] = $existingdata->bet_amount + $request_data["amount"];
                    $trans_data["pay_amount"] = 0;
                    $trans_data["entry_id"] = 1;
                break;
            case "credit":
                    $trans_data["win"] = 5;
                    $trans_data["pay_amount"] = abs($request_data["amount"]);
                    $trans_data["income"]=$existingdata->bet_amount-$request_data["amount"];
                    $trans_data["entry_id"] = 2;
                    // $trans_data["payout_reason"] = $request_data["payout_reason"];
                break;
            case "refund":
                    $trans_data["win"] = 4;
                    $trans_data["pay_amount"] = $request_data["amount"];
                    $trans_data["entry_id"] = 2;
                    $trans_data["income"]= $existingdata->bet_amount-$request_data["amount"];
                    // $trans_data["payout_reason"] = "Refund of this transaction ID: ".$request_data["transid"]."of GameRound ".$request_data["roundid"];
                break;

            default:
        }
        /*var_dump($trans_data); die();*/
        return GameTransactionMDB::updateGametransaction($trans_data,$existingdata->game_trans_id,$client_details);
        // return DB::table('game_transactions')->where("game_trans_id",$existingdata->game_trans_id)->update($trans_data);
    }
    public static function checkGameTransactionExist($provider_transaction_id,$round_id=false,$type=false,$client_details){
        $connection = GameTransactionMDB::getAvailableConnection($client_details->connection_name);
        if($connection != null){
            $select = "SELECT game_transaction_type FROM ";
            $db = "{$connection['db_list'][0]}.game_transaction_ext ";
            if($type&&$round_id){
                $where =  "WHERE round_id = '{$round_id}' AND provider_trans_id='{$provider_transaction_id}' AND game_transaction_type = {$type}";
            }
            elseif($type&&$provider_transaction_id){
                $where =  "WHERE provider_trans_id='{$provider_transaction_id}' AND game_transaction_type={$type} limit 1";
            }
            else{
                $where =  "WHERE provider_trans_id='{$provider_transaction_id}' limit 1";
            }
            $game = DB::connection($connection["connection_name"])->select($select.$db.$where);
            return $game ? $game :false;
        }else{
            return false;
        }
    }
}
