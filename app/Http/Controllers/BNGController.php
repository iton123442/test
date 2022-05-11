<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Helpers\ClientRequestHelper;
use App\Helpers\TransactionHelper;
use App\Models\GameTransactionMDB;
use App\Helpers\FreeSpinHelper;
use App\Models\GameTransaction;
use DB;
class BNGController extends Controller
{
    protected $startTime;
    private $prefix = 22;
    public function __construct() {
        $this->startTime = microtime(true);
    }
    public function index(Request $request){
        $data = json_decode($request->getContent(),TRUE);
        $client_details = ProviderHelper::getClientDetails('token', $data["token"]);
        $invokeStart =  microtime(true);
        if($data["name"]== "login"){
            return $this->_authPlayer($data,$client_details);
        }
        elseif($data["name"]== "transaction"){
            $data = $this->_transaction($data,$client_details);
            $invokeEnd = microtime(true) - $invokeStart;
            Helper::saveLog('BNGTIMELOG(BNG)', 12, json_encode(["method" => "indexTransaction" ,"Time" => $invokeEnd]), "");
            return $data;
            //return $this->_betGame($data);
        }
        elseif($data["name"]=="rollback"){
            Helper::saveLog('BNGTIMELOG(BNG)', 12, json_encode(["method" => "indexTransaction" ,"Time" => $invokeStart]), $data["name"]);
            return $this->_rollbackGame($data,$client_details);
        }
        elseif($data["name"]=="getbalance"){
            return $this->_getBalance($data,$client_details);
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
    private function _authPlayer($data,$client_details){
        if($data["token"]){
            // $client_details = ProviderHelper::getClientDetails('token', $data["token"]);
            if($client_details){
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
                        "version"=>round(microtime(true) * 1000) //$this->_getExtParameter()
                    ),
                    "tag"=>""
                );
                //$this->_setExtParameter($this->_getExtParameter()+1);
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
    private function _getBalance($data,$client_details){
        if($data["token"]){
            //$client_details = ProviderHelper::getClientDetails('token', $data["token"]);
            if($client_details){
                Helper::saveLog('BNGMETHOD(BNG)', 12, json_encode(["method" =>"_getBalance","balance"=>$client_details->balance]), "");
                $msg = array(
                    "uid" => $data["uid"],
                    "balance"=>array(
                        "value"=> number_format($client_details->balance,2,'.', ''),
                        "version"=> round(microtime(true) * 1000)//$this->_getExtParameter()
                    )
                );
                //$this->_setExtParameter($this->_getExtParameter()+1);
                return response($msg,200)->header('Content-Type', 'application/json');
            }
        }
    }

    private function _transaction($data,$client_details){
        Helper::saveLog('BNGMETHOD(BNG)', 12, json_encode(["request_data" => $data]), "");
        // $game_transaction = GameTransactionMDB::getGameTransactionDataByProviderTransactionId($data["uid"],$client_details);
        try{
            ProviderHelper::idenpotencyTable($this->prefix.'_'.$data["uid"]);
        }catch(\Exception $e){
            if($data["args"]["bet"]!= null && $data["args"]["win"]!= null){
                if($client_details){
                    ProviderHelper::saveLogWithExeption("BNGCLIENTDETAILS",22,json_encode($client_details),"IF");
                    $gameTransactionExtChecker = GameTransactionMDB::getGameTransactionDataByProviderTransactionIdAndEntryType($data["uid"],2,$client_details);
                    ProviderHelper::saveLogWithExeption("BNGCLIENTDETAILS",22,json_encode($gameTransactionExtChecker),"IFgameTransactionExtChecker");
                    //check the gameTransaction if exist and check if not null 
                    if ($gameTransactionExtChecker != null && !empty($gameTransactionExtChecker)&& isset($gameTransactionExtChecker)){
                        // check the transation from the client if it is success or not found
                        $transactionClientChecker = TransactionHelper::CheckTransactionToClient($client_details,$gameTransactionExtChecker->game_trans_ext_id,$gameTransactionExtChecker->game_trans_id);
                        // if trasaction credit is found in the client side then we will response the same response as we did on the first request
                        if($transactionClientChecker){
                            // check if the gameTransactionExtChecker["mw_response"] is not empty.
                            if($gameTransactionExtChecker->mw_response != null && !empty($gameTransactionExtChecker->mw_response) && isset($gameTransactionExtChecker->mw_response)){
                                $decodedResponse = json_decode($gameTransactionExtChecker->mw_response);
                                $response =array(
                                    "uid"=>$decodedResponse->uid,
                                    "balance" => array(
                                        "value" => (string)$decodedResponse->balance->value,
                                        "version" => $decodedResponse->balance->version,//$this->_getExtParameter()
                                    ),
                                );
                                return response($response,200)->header('Content-Type', 'application/json'); 
                            }
                            // if we get an empty or null gameTransactionExtChecker["mw_response"] 
                            else{
                                $response =array(
                                    "uid"=>$data["uid"],
                                    "balance" => array(
                                        "value" => $client_details->balance,
                                        "version" => round(microtime(true) * 1000)//$this->_getExtParameter()
                                    ),
                                );
                                return response($response,200)
                                    ->header('Content-Type', 'application/json');
                            }
                        }
                        // gameTransactionExtChecker ins empty or null then we will consider it as failed on the  provider side and reafund the client side
                        else{
                            $response =array(
                                "uid"=>$data["uid"],
                                "balance" => array(
                                    "value" => "0.00",
                                    "version" => round(microtime(true) * 1000)//$this->_getExtParameter()
                                ),
                                "error" => array(
                                    "code"=> "OTHER_EXCEED",
                                )
                            );
                            $failedData = array(
                                "provider_transaction" => $data["uid"],
                                "round_id" => $data["args"]["round_id"],
                                "win_amount" => $data["args"]["win"],
                                "bet_amount" => $data["args"]["bet"],
                                "provider_id" => $this->prefix,
                            );
                            GameTransaction::createFailedTransaction($failedData);
                            return response($response,200)->header('Content-Type', 'application/json');
                        }
                    }
                    // gameTransactionExtChecker ins empty or null then we will consider it as failed on the  provider side and reafund the client side
                    else{
                        $response =array(
                            "uid"=>$data["uid"],
                            "balance" => array(
                                "value" => "0.00",
                                "version" => round(microtime(true) * 1000)//$this->_getExtParameter()
                            ),
                            "error" => array(
                                "code"=> "OTHER_EXCEED",
                            )
                        );
                        $failedData = array(
                            "provider_transaction" => $data["uid"],
                            "round_id" => $data["args"]["round_id"],
                            "win_amount" => $data["args"]["win"],
                            "bet_amount" => $data["args"]["bet"],
                            "provider_id" => $this->prefix,
                        );
                        GameTransaction::createFailedTransaction($failedData);
                        return response($response,200)->header('Content-Type', 'application/json');
                    }
                }else{
                    ProviderHelper::saveLogWithExeption("BNGCLIENTDETAILS",22,json_encode($client_details),"ELSE");
                    $response =array(
                        "uid"=>$data["uid"],
                        "balance" => array(
                            "value" => "0.00",
                            "version" => round(microtime(true) * 1000)//$this->_getExtParameter()
                        ),
                        "error" => array(
                            "code"=> "OTHER_EXCEED",
                        )
                    );
                    $failedData = array(
                        "provider_transaction" => $data["uid"],
                        "round_id" => $data["args"]["round_id"],
                        "win_amount" => $data["args"]["win"],
                        "bet_amount" => $data["args"]["bet"],
                        "provider_id" => $this->prefix,
                    );
                    GameTransaction::createFailedTransaction($failedData);
                    return response($response,200)->header('Content-Type', 'application/json');
                } 
            }elseif($data["args"]["bet"]== null && $data["args"]["win"]!= null){
                $failedData = array(
                    "provider_transaction" => $data["uid"],
                    "round_id" => $data["args"]["round_id"],
                    "win_amount" => $data["args"]["win"],
                    "provider_id" => $this->prefix,
                );
                GameTransaction::createFailedTransaction($failedData);
                $response =array(
                    "uid"=>$data["uid"],
                    "balance" => array(
                        "value" => (string)$client_details->balance,
                        "version" => round(microtime(true) * 1000)//$this->_getExtParameter()
                    ),
                );
                return response($response,200)
                    ->header('Content-Type', 'application/json');
            }elseif($data["args"]["bet"]!= null && $data["args"]["win"]== null){
                $response =array(
                    "uid"=>$data["uid"],
                    "balance" => array(
                        "value" =>(string)$client_details->balance,
                        "version" => round(microtime(true) * 1000)//$this->_getExtParameter()
                    ),
                    "error" => array(
                        "code"=> "OTHER_EXCEED",
                    )
                );
                $failedData = array(
                    "provider_transaction" => $data["uid"],
                    "round_id" => $data["args"]["round_id"],
                    "bet_amount" => $data["args"]["bet"],
                    "provider_id" => $this->prefix,
                );
                GameTransaction::createFailedTransaction($failedData);
                return response($response,200)->header('Content-Type', 'application/json'); 
            }  
        }
            //$client_details = ProviderHelper::getClientDetails('token', $data["token"]);
        if($client_details){
            //$game_details = Helper::getInfoPlayerGameRound($data["token"]);
            $game_details = ProviderHelper::findGameDetails('game_code', $this->prefix, $data["game_id"]);
            if($data["args"]["bet"]!= null && $data["args"]["win"]!= null){
                return $this->betNotNullWinNotNull($data,$client_details,$game_details);
            }
            elseif($data["args"]["bet"]== null && $data["args"]["win"]!= null){
                return $this->betNullWinNotNull($data,$client_details,$game_details);
            }
            elseif($data["args"]["bet"]!= null && $data["args"]["win"]== null){
                return $this->betNotNullWinNull($data,$client_details,$game_details);
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
    private function betNotNullWinNotNull($data,$client_details,$game_details){
        $gen_game_trans_id = ProviderHelper::idGenerate($client_details->connection_name,1);
        $gen_game_extid = ProviderHelper::idGenerate($client_details->connection_name,2);
        $betStart =  microtime(true);
        $win_or_lost = $data["args"]["win"] == 0 ? 0 : 1;
        $gameTransactionData = array(
            "provider_trans_id" => $data["uid"],
            "token_id" => $client_details->token_id,
            "game_id" => $game_details->game_id,
            "round_id" => $data["args"]["round_id"],
            "bet_amount" => $data["args"]["bet"],
            "win" => 5,
            "pay_amount" =>$data["args"]["win"],
            "income" =>$data["args"]["bet"]-$data["args"]["win"],
            "entry_id" =>$data["args"]["win"] == 0 ? 1 : 2,
        );
        // $game_transactionid = GameTransactionMDB::createGametransaction($gameTransactionData,$client_details);
        GameTransactionMDB::createGametransactionV2($gameTransactionData,$gen_game_trans_id,$client_details); //create game_transaction
        $betgametransactionext = array(
            "game_trans_id" => $gen_game_trans_id,
            "provider_trans_id" => $data["uid"],
            "round_id" => $data["args"]["round_id"],
            "amount" => $data["args"]["bet"],
            "game_transaction_type"=>1,
            // "provider_request" =>json_encode($data),
        );
        // $betGametransactionExtId = GameTransactionMDB::createGameTransactionExt($betgametransactionext,$client_details);
        GameTransactionMDB::createGameTransactionExtV2($betgametransactionext,$gen_game_extid,$client_details); //create game_transaction
        $body_details = [
            'provider_name' => $game_details->provider_name,
            'connection_timeout' => 1,
        ];
        if($data["args"]["bonus"] != null){
            $campaignId = $data['args']['bonus']['campaign'];
            $getFreespin = FreeSpinHelper::getFreeSpinDetails($campaignId, "provider_trans_id" );
            if($getFreespin){
                $getOrignalfreeroundID = explode("_",$campaignId);
                $body_details["fundtransferrequest"]["fundinfo"]["freeroundId"] = $getOrignalfreeroundID[1];
                $status = 2;
                $updateFreespinData = [
                    "status" => $status,
                    "win" => $getFreespin->win + $data["args"]["win"],
                    "spin_remaining" => $getFreespin->spin_remaining - $getFreespin->spin_remaining
                ];
                $updateFreespin = FreeSpinHelper::updateFreeSpinDetails($updateFreespinData, $getFreespin->freespin_id);
                if($status == 2 ){
                    $body_details["fundtransferrequest"]["fundinfo"]["freeroundend"] = true; //explod the provider trans use the original
                } else {
                    $body_details["fundtransferrequest"]["fundinfo"]["freeroundend"] = false; //explod the provider trans use the original
                }
                $createFreeRoundTransaction = array(
                    "game_trans_id" => $gen_game_trans_id,
                    'freespin_id' => $getFreespin->freespin_id
                );
                FreeSpinHelper::createFreeRoundTransaction($createFreeRoundTransaction);
            }
        }
        $client_response = ClientRequestHelper::fundTransfer($client_details,round($data["args"]["bet"],2),$game_details->game_code,$game_details->game_name,$gen_game_extid,$gen_game_trans_id,"debit",false,$body_details);
        if(isset($client_response->fundtransferresponse->status->code) 
        && $client_response->fundtransferresponse->status->code == "200"){
            $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
            $client_details->balance = $balance;
            ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
            $response =array(
                "uid"=>$data["uid"],
                "balance" => array(
                    "value" =>(string)$balance,
                    "version" => round(microtime(true) * 1000)//$this->_getExtParameter()
                ),
            );
            // $dataToUpdate = array(
            //     "mw_response" => json_encode($response)
            // );
            // GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$gen_game_extid,$client_details);
            //Helper::updateBNGGameTransactionExt($betGametransactionExtId,$client_response->requestoclient,$response,$client_response);
            $createGameTransactionLog = [
                      "connection_name" => $client_details->connection_name,
                      "column" =>[
                          "game_trans_ext_id" => $gen_game_extid,
                          "request" => json_encode($data),
                          "response" => json_encode($response),
                          "log_type" => "provider_details",
                          "transaction_detail" => "success",
                      ]
                  ];
            ProviderHelper::queTransactionLogs($createGameTransactionLog);
            $winStart =  microtime(true);
            //$this->_setExtParameter($this->_getExtParameter()+1);
            $response =array(
                "uid"=>$data["uid"],
                "balance" => array(
                    "value" =>$client_details->balance +$data["args"]["win"],
                    "version" => round(microtime(true) * 1000)//$this->_getExtParameter()
                ),
            );
            $wingen_game_extid = ProviderHelper::idGenerate($client_details->connection_name,2);
            $wingametransactionext = array(
                "game_trans_id" => $gen_game_trans_id,
                "provider_trans_id" => $data["uid"],
                "round_id" => $data["args"]["round_id"],
                "amount" => $data["args"]["win"],
                "game_transaction_type"=>2,
                // "provider_request" =>json_encode($data),
                // "mw_response" => json_encode($response)
            );
            // $winGametransactionExtId = GameTransactionMDB::createGameTransactionExt($wingametransactionext,$client_details);
            GameTransactionMDB::createGameTransactionExtV2($wingametransactionext,$wingen_game_extid,$client_details); //create game_transaction
            Helper::saveLog('createGameTransactionExt(BNG)', 12, json_encode($wingen_game_extid), "");

            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "provider" => 'bng',
                    "isUpdate" => false,
                    "game_transaction_ext_id" => $wingen_game_extid,
                    "client_connection_name" => $client_details->connection_name,
                    "win_or_lost" => $win_or_lost,
                ],
                "provider" => [
                    "provider_request" => $data, #R
                    "provider_trans_id"=>$data["uid"], #R
                    "provider_round_id"=>$data["args"]["round_id"], #R
                    'provider_name' => $game_details->provider_name
                ],
                "mwapi" => [
                    "roundId"=>$gen_game_trans_id, #R
                    "type"=>2, #R
                    "game_id" => $game_details->game_id, #R
                    "player_id" => $client_details->player_id, #R
                    "mw_response" => $response, #R
                ]
            ];
            $client_response = ClientRequestHelper::fundTransfer_TG($client_details,round($data["args"]["win"],2),$game_details->game_code,$game_details->game_name,$gen_game_trans_id,'credit',false,$action_payload);
            if(isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "200"){
                $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
                ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
                $response =array(
                    "uid"=>$data["uid"],
                    "balance" => array(
                        "value" =>(string)$balance,
                        "version" => round(microtime(true) * 1000)//$this->_getExtParameter()
                    ),
                );
                //Helper::updateBNGGameTransactionExt($transactionId,$client_response->requestoclient,$response,$client_response);
                $createGameTransactionLog = [
                      "connection_name" => $client_details->connection_name,
                      "column" =>[
                          "game_trans_ext_id" => $gen_game_extid,
                          "request" => json_encode($data),
                          "response" => json_encode($response),
                          "log_type" => "provider_details",
                          "transaction_detail" => "success",
                          ]
                      ];
                ProviderHelper::queTransactionLogs($createGameTransactionLog);
                $endWin = microtime(true) - $winStart;
                Helper::saveLog('BNGTIMELOG(BNG)', 12, json_encode(["method" => "WinTime" ,"Time" => $endWin]), "");
                return response($response,200)
                    ->header('Content-Type', 'application/json');
            }
        }
        elseif(isset($client_response->fundtransferresponse->status->code) 
        && $client_response->fundtransferresponse->status->code == "402"){
            $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
            $client_details->balance = $balance;
            $response =array(
                "uid"=>$data["uid"],
                "balance" => array(
                    "value" =>number_format($client_details->balance,2,'.', ''),
                    "version" => round(microtime(true) * 1000)//$this->_getExtParameter()
                ),
                "error" => array(
                    // "code"=> "FUNDS_EXCEED",
                    "code" => "OTHER_EXCEED"
                )
            );
            //$this->_setExtParameter($this->_getExtParameter()+1);
            try{
                // $dataToSave = array(
                //     "win"=>2,
                //     "transaction_reason" => "FAILED Due to low balance or Client Server Timeout"
                // );
                $dataToSave = array(
                    "win"=>2
                );
                GameTransactionMDB::updateGametransaction($dataToSave,$gen_game_trans_id,$client_details);
                // Helper::updateBNGGameTransactionExt($betGametransactionExtId,$client_response->fundtransferresponse->status->message,$response,'FAILED');
                $dataToUpdate = array(
                    "mw_response" => json_encode($response)
                );
                GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$gen_game_extid,$client_details);
                $createGameTransactionLog = [
                      "connection_name" => $client_details->connection_name,
                      "column" =>[
                          "game_trans_ext_id" => $gen_game_extid,
                          "request" => json_encode($data),
                          "response" => json_encode($response),
                          "log_type" => "provider_details",
                          "transaction_detail" => "Failed",
                      ]
                    ];
                 ProviderHelper::queTransactionLogs($createGameTransactionLog); 
            }catch(\Exception $e){
                Helper::saveLog('betGameInsuficient(BNG)', 12, json_encode($e->getMessage().' '.$e->getLine()), $client_response->fundtransferresponse->status->message);
            }
            // Helper::updateBNGGameTransactionExt($betGametransactionExtId,$client_response->requestoclient,$response,$client_response);
            return response($response,200)
                        ->header('Content-Type', 'application/json');
        }
        
    }
    private function betNullWinNotNull($data,$client_details,$game_details){
        $game = GameTransactionMDB::getGameTransactionByRoundId($data["args"]["round_id"],$client_details);
        $gen_game_extid = ProviderHelper::idGenerate($client_details->connection_name,2);
        if($game != null){
            $win_or_lost = 5;
            if($data["args"]["round_finished"] == true){
                $win_or_lost = $data["args"]["win"] == 0 && $game->pay_amount == 0 ? 0 : 1;
            }
            $createGametransaction = array(
                "win" => 5,
                "pay_amount" =>$game->pay_amount+$data["args"]["win"],
                "income" =>$game->income - $data["args"]["win"],
                "entry_id" =>$data["args"]["win"] == 0 && $game->pay_amount == 0 ? 1 : 2,
            );
            $game_transactionid = GameTransactionMDB::updateGametransaction($createGametransaction,$game->game_trans_id,$client_details);
            //$this->_setExtParameter($this->_getExtParameter()+1);
            
            // $client_response = ClientRequestHelper::fundTransfer($client_details,round($data["args"]["win"],2),$game_details->game_code,$game_details->game_name,$winGametransactionExtId,$game->game_trans_id,"credit");
            // $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
            $response =array(
                "uid"=>$data["uid"],
                "balance" => array(
                    "value" =>$client_details->balance +$data["args"]["win"],
                    "version" => round(microtime(true) * 1000)//$this->_getExtParameter()
                ),
            );
            $wingametransactionext = array(
                "game_trans_id" => $game->game_trans_id,
                "provider_trans_id" => $data["uid"],
                "round_id" =>$data["args"]["round_id"],
                "amount" =>$data["args"]["win"],
                "game_transaction_type"=>2,
                // "provider_request" =>json_encode($data),
                // "mw_response" => json_encode($response)
            );
            // $winGametransactionExtId = GameTransactionMDB::createGameTransactionExt($wingametransactionext,$client_details);
            GameTransactionMDB::createGameTransactionExtV2($wingametransactionext,$gen_game_extid,$client_details); //create game_transaction
            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "provider" => 'bng',
                    "game_transaction_ext_id" => $gen_game_extid,
                    "client_connection_name" => $client_details->connection_name,
                    "win_or_lost" => $win_or_lost,
                ],
                "provider" => [
                    "provider_request" => $data, #R
                    "provider_trans_id"=>$data["uid"], #R
                    "provider_round_id"=>$data["args"]["round_id"], #R
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
            $client_response = ClientRequestHelper::fundTransfer_TG($client_details,round($data["args"]["win"],2),$game_details->game_code,$game_details->game_name,$game->game_trans_id,'credit',false,$action_payload);
            if(isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "200"){
                $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
                ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                Helper::saveLog('BNGMETHOD(BNG)', 12, json_encode(["method" =>"betNullWinNotNull","balance"=>$balance,"response_balance"=>$client_response->fundtransferresponse->balance]), "");
                $response =array(
                    "uid"=>$data["uid"],
                    "balance" => array(
                        "value" =>(string)$balance,
                        "version" => round(microtime(true) * 1000)//$this->_getExtParameter()
                    ),
                );
                $createGameTransactionLog = [
                      "connection_name" => $client_details->connection_name,
                      "column" =>[
                          "game_trans_ext_id" => $gen_game_extid,
                          "request" => json_encode($data),
                          "response" => json_encode($response),
                          "log_type" => "provider_details",
                          "transaction_detail" => "success",
                      ]
                  ];
                ProviderHelper::queTransactionLogs($createGameTransactionLog);
                //Helper::updateBNGGameTransactionExt($winGametransactionExtId,$client_response->requestoclient,$response,$client_response);
                return response($response,200)
                    ->header('Content-Type', 'application/json');
            }else{
                $response =array(
                    "uid"=>"Error on Client Timeout"
                );
                return response($response,503)
                        ->header('Content-Type', 'application/json');
            }
        }
        else{
            Helper::saveLog('BNGMETHOD(BNG)', 12, json_encode("GameTransactionDoesnotExist"), "");
            return json_encode($game);
        }
    }
    private function betNotNullWinNull($data,$client_details,$game_details){
        $gen_game_trans_id = ProviderHelper::idGenerate($client_details->connection_name,1);
        $gen_game_extid = ProviderHelper::idGenerate($client_details->connection_name,2);
        $win_or_lost = 0;
        $dataToSave = array(
            "provider_trans_id" => $data["uid"],
            "token_id" => $client_details->token_id,
            "game_id" => $game_details->game_id,
            "round_id" => $data["args"]["round_id"],
            "bet_amount" => $data["args"]["bet"],
            "win" =>5,
            "pay_amount" =>0,
            "income" =>$data["args"]["bet"],
            "entry_id" =>1,
        );
        // $game_transactionid = GameTransactionMDB::createGametransaction($dataToSave,$client_details);
        GameTransactionMDB::createGametransactionV2($dataToSave,$gen_game_trans_id,$client_details); //create game_transaction
        $betgametransactionext = array(
            "game_trans_id" => $game_transactionid,
            "round_id" =>$data["args"]["round_id"],
            "amount" =>$data["args"]["bet"],
            "game_transaction_type"=>1,
            // "provider_request" =>json_encode($data),
        );
        // $betGametransactionExtId = GameTransactionMDB::createGameTransactionExt($betgametransactionext,$client_details);
        GameTransactionMDB::createGameTransactionExtV2($betgametransactionext,$gen_game_extid,$client_details); //create game_transaction
        $fund_extra_data = [
            'provider_name' => $game_details->provider_name
        ];
        $client_response = ClientRequestHelper::fundTransfer($client_details,round($data["args"]["bet"],2),$game_details->game_code,$game_details->game_name,$gen_game_extid,$gen_game_trans_id,"debit",false,$fund_extra_data);
        if(isset($client_response->fundtransferresponse->status->code) 
        && $client_response->fundtransferresponse->status->code == "200"){
            $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
            ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
            $response =array(
                "uid"=>$data["uid"],
                "balance" => array(
                    "value" =>$balance,
                    "version" => round(microtime(true) * 1000)//$this->_getExtParameter()
                ),
            );
            //$this->_setExtParameter($this->_getExtParameter()+1);
            // $dataToUpdate = array(
            //     "mw_response" => json_encode($response)
            // );
            // GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$gen_game_extid,$client_details);
            $createGameTransactionLog = [
                  "connection_name" => $client_details->connection_name,
                  "column" =>[
                      "game_trans_ext_id" => $gen_game_extid,
                      "request" => json_encode($data),
                      "response" => json_encode($response),
                      "log_type" => "provider_details",
                      "transaction_detail" => "Success",
                  ]
                ];
             ProviderHelper::queTransactionLogs($createGameTransactionLog); 
            return response($response,200)
                        ->header('Content-Type', 'application/json');
        }
        elseif(isset($client_response->fundtransferresponse->status->code) 
        && $client_response->fundtransferresponse->status->code == "402"){
            $response =array(
                "uid"=>$data["uid"],
                "balance" => array(
                    "value" =>number_format($client_details->balance,2,'.', ''),
                    "version" => round(microtime(true) * 1000)//$this->_getExtParameter()
                ),
                "error" => array(
                    "code"=> "FUNDS_EXCEED",
                )
            );
            //$this->_setExtParameter($this->_getExtParameter()+1);
            try{
                $dataToSave = array(
                    "win"=>2
                );
                GameTransactionMDB::updateGametransaction($dataToSave,$gen_game_trans_id,$client_details);
                $createGameTransactionLog = [
                  "connection_name" => $client_details->connection_name,
                  "column" =>[
                      "game_trans_ext_id" => $gen_game_extid,
                      "request" => json_encode($data),
                      "response" => json_encode($response),
                      "log_type" => "provider_details",
                      "transaction_detail" => "FAILED",
                  ]
                ];
             ProviderHelper::queTransactionLogs($createGameTransactionLog);
                $dataToUpdate = array(
                    "mw_response" => json_encode($response)
                );
                GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$gen_game_extid,$client_details);
            }catch(\Exception $e){
                Helper::saveLog('betGameInsuficient(BNG)', 12, json_encode($e->getMessage().' '.$e->getLine()), $client_response->fundtransferresponse->status->message);
            }
            // Helper::updateBNGGameTransactionExt($betGametransactionExtId,$client_response->requestoclient,$response,$client_response);
            
            return response($response,200)
                        ->header('Content-Type', 'application/json');
        }
    }
    // private function _betGame($data){
    //     //return $data["args"]["bet"];
    //     if($data["token"]){
    //         $client_details = ProviderHelper::getClientDetails('token', $data["token"]);
    //         if($client_details){
    //             $game_transaction = Helper::checkGameTransaction($data["uid"]);
    //             $bet = $data["args"]["bet"]==null ? 0:$data["args"]["bet"];
    //             $bet_amount = $game_transaction ? 0 : round($bet,2);
    //             $bet_amount = $bet_amount < 0 ? 0 :$bet_amount;
    //             $game_details = Helper::getInfoPlayerGameRound($data["token"]);
    //             $json_data = array(
    //                 "transid" => $data["uid"],
    //                 "amount" => round($data["args"]["bet"],2),
    //                 "roundid" => $data["args"]["round_id"]
    //             );
    //             $game = TransactionHelper::getGameTransaction($data['token'],$data["args"]["round_id"]);
    //             if(!$game){
    //                 $gametransactionid=Helper::createGameTransaction('debit', $json_data, $game_details, $client_details); 
    //             }
    //             else{
    //                 $gametransactionid= $game[0]->game_trans_id;
    //             }
    //             $this->_setExtParameter($this->_getExtParameter()+1);
    //             if(!$game_transaction){
    //                 $transactionId=Helper::createBNGGameTransactionExt($gametransactionid,$data,null,null,null,1);
    //             }  
                
                

    //         }
    //     } 
    // }
    // private function _winGame($data){
    //     //return $data["args"]["win"];
    //     if($data["token"]){
    //         $client_details = ProviderHelper::getClientDetails('token', $data["token"]);
    //         if($client_details){
    //             //$game_transaction = Helper::checkGameTransaction($json["transactionId"]);
    //             $game_transaction = Helper::checkGameTransaction($data["uid"],$data["args"]["round_id"],2);
    //             $win_amount = $game_transaction ? 0 : round($data["args"]["win"],2);
    //             $win_amount = $win_amount < 0 ? 0 :$win_amount;
    //             $win = $data["args"]["win"] == 0 ? 0 : 1;
    //             $game_details = Helper::getInfoPlayerGameRound($data["token"]);
    //             $json_data = array(
    //                 "transid" => $data["uid"],
    //                 "amount" => round($data["args"]["win"],2),
    //                 "roundid" => $data["args"]["round_id"],
    //                 "payout_reason" => null,
    //                 "win" => $win,
    //             );
    //             $game = TransactionHelper::getGameTransaction($data["token"],$data["args"]["round_id"]);
    //             if(!$game){
    //                 $gametransactionid=Helper::createGameTransaction('credit', $json_data, $game_details, $client_details); 
    //             }
    //             else{
    //                 $json_data["amount"] = round($data["args"]["win"],2)+ $game[0]->pay_amount;
    //                 if($win == 0){
    //                     $gameupdate = TransactionHelper::updateGameTransaction($game,$json_data,"debit");
    //                 }else{
    //                     $gameupdate = TransactionHelper::updateGameTransaction($game,$json_data,"credit");
    //                 }
    //                 $gametransactionid = $game[0]->game_trans_id;
    //             }
    //             $this->_setExtParameter($this->_getExtParameter()+1);
    //             if(!$game_transaction){
    //                 $transactionId=Helper::createBNGGameTransactionExt($gametransactionid,$data,null,null,null,2);
    //             } 
    //             $client_response = ClientRequestHelper::fundTransfer($client_details,round($win_amount,2),$game_details->game_code,$game_details->game_name,$transactionId,$gametransactionid,"credit");
    //             $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
    //             if(isset($client_response->fundtransferresponse->status->code) 
    //             && $client_response->fundtransferresponse->status->code == "200"){
    //                 ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
    //                 $response =array(
    //                     "uid"=>$data["uid"],
    //                     "balance" => array(
    //                         "value" =>$balance,
    //                         "version" => $this->_getExtParameter()
    //                     ),
    //                 );
    //                 Helper::updateBNGGameTransactionExt($transactionId,$client_response->requestoclient,$response,$client_response);
    //                 return response($response,200)
    //                     ->header('Content-Type', 'application/json');
    //             }
    //             else{
    //                 return "something error with the client";
    //             }
    //         }
    //     } 
    // }
    private function _rollbackGame($data,$client_details){
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
                            "version" => round(microtime(true) * 1000)//$this->_getExtParameter()
                        ),
                    );
                    //$this->_setExtParameter($this->_getExtParameter()+1);
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
                $game = ProviderHelper::findGameDetails('game_code', $this->prefix, $data["game_id"]);
                if(!$game){
                    $gametransactionid=Helper::createGameTransaction('refund', $json_data, $game_details, $client_details); 
                }
                else{
                    $gameupdate = TransactionHelper::updateGameTransaction($game,$json_data,"refund");
                    $gametransactionid = $game[0]->game_trans_id;
                }
                //$this->_setExtParameter($this->_getExtParameter()+1);
                if(!$game_transaction){
                    $transactionId=Helper::createBNGGameTransactionExt($gametransactionid,$data,null,null,null,3);
                }
                $fund_extra_data = [
                    'provider_name' => $game_details->provider_name
                ];
                $client_response = ClientRequestHelper::fundTransfer($client_details,round($refund_amount,2),$game_details->game_code,$game_details->game_name,$transactionId,$gametransactionid,"credit",true,$fund_extra_data);
                $balance = number_format($client_response->fundtransferresponse->balance,2,'.', '');
                if(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "200"){
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    $response =array(
                        "uid"=>$data["uid"],
                        "balance" => array(
                            "value" =>$balance,
                            "version" => round(microtime(true) * 1000)//$this->_getExtParameter()
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
