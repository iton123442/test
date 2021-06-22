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
        try{
            ProviderHelper::idenpotencyTable($this->prefix.'_'.$data["uid"]);
        }catch(\Exception $e){
                    if($client_details){
                        $game_details = ProviderHelper::findGameDetails('game_code', $this->prefix, $data["game_id"]);
                        if($data["args"]["bet"]!= null && $data["args"]["win"]!= null){
                            $game_transaction = GameTransaction::getGameTransactionDataByProviderTransactionId($data["uid"]);
                            if(!empty($game_transaction)){
                                $response = json_decode($game_transaction->mw_response);
                                if($response != null && !empty($response)){
                                    // $response = json_decode($game_transaction->mw_response);
                                    $resp = array(
                                        "uid"=> $response->uid,
                                        "balance" => array(
                                            "value" => (string)$response->balance->value,
                                            "version"=> $response->balance->version
                                        )
                                    );
                                    return response($resp,200)
                                     ->header('Content-Type', 'application/json');
                                }else{
                                    return $this->betNotNullWinNotNullUpdate($data,$client_details,$game_details,$game_transaction);
                                }
                            }
                        }
                        elseif($data["args"]["bet"]== null && $data["args"]["win"]!= null){
                            $game_transaction = GameTransaction::getGameTransactionDataByProviderTransactionId($data["uid"]);
                            if(!empty($game_transaction)){
                                return $this->betNullWinNotNullUpdate($data,$client_details,$game_details);
                            }
                        }
                        // elseif($data["args"]["bet"]!= null && $data["args"]["win"]== null){
                        //     return $this->betNotNullWinNull($data,$client_details,$game_details);
                        // }
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
    private function betNotNullWinNotNullUpdate($data,$client_details,$game_details,$game_transaction){
        $betStart =  microtime(true);
        $gameTransactionData = array(
            "provider_trans_id" => $data["uid"],
            "token_id" => $client_details->token_id,
            "game_id" => $game_details->game_id,
            "round_id" => $data["args"]["round_id"],
            "bet_amount" => $data["args"]["bet"],
            "win" =>$data["args"]["win"] == 0 ? 0 : 1,
            "pay_amount" =>$data["args"]["win"],
            "income" =>$data["args"]["bet"]-$data["args"]["win"],
            "entry_id" =>$data["args"]["win"] == 0 ? 1 : 2,
        );
        GameTransaction::updateGametransaction($gameTransactionData,$game_transaction->game_trans_id);
        $betgametransactionext = array(
            "game_trans_id" => $game_transaction->game_trans_id,
            "provider_trans_id" => $data["uid"],
            "round_id" => $data["args"]["round_id"],
            "amount" => $data["args"]["bet"],
            "game_transaction_type"=>1,
            "provider_request" =>json_encode($data),
        );
        GameTransaction::updateGametransactionEXT($betgametransactionext,$game_transaction->game_trans_ext_id);
        $client_response = ClientRequestHelper::fundTransfer($client_details,round($data["args"]["bet"],2),$game_details->game_code,$game_details->game_name,$game_transaction->game_trans_ext_id,$game_transaction->game_trans_id,"debit");
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
            $dataToUpdate = array(
                "mw_response" => json_encode($response)
            );
            GameTransaction::updateGametransactionEXT($dataToUpdate,$game_transaction->game_trans_ext_id);
            //Helper::updateBNGGameTransactionExt($betGametransactionExtId,$client_response->requestoclient,$response,$client_response);
            $winGameTransactionExt=GameTransactionMDB::getGameTransactionDataByProviderTransactionIdAndEntryType($data["uid"],2,$client_details);
            $response =array(
                "uid"=>$data["uid"],
                "balance" => array(
                    "value" =>$client_details->balance +$data["args"]["win"],
                    "version" => round(microtime(true) * 1000)//$this->_getExtParameter()
                ),
            );
            if($winGameTransactionExt!=null){
                $wingametransactionext = array(
                    "game_trans_id" => $game_transaction->game_trans_id,
                    "provider_trans_id" => $data["uid"],
                    "round_id" => $data["args"]["round_id"],
                    "amount" => $data["args"]["win"],
                    "game_transaction_type"=>2,
                    "provider_request" =>json_encode($data),
                    "mw_response" => json_encode($response)
                );
                GameTransaction::updateGametransactionEXT($wingametransactionext,$winGameTransactionExt->game_trans_ext_id);
            }else{
                $wingametransactionext = array(
                    "game_trans_id" => $game_transaction->game_trans_id,
                    "provider_trans_id" => $data["uid"],
                    "round_id" => $data["args"]["round_id"],
                    "amount" => $data["args"]["win"],
                    "game_transaction_type"=>2,
                    "provider_request" =>json_encode($data),
                    "mw_response" => json_encode($response)
                );
                $winGametransactionExtId = GameTransaction::createGameTransactionExt($wingametransactionext);
            }
            $winStart =  microtime(true);
            //$this->_setExtParameter($this->_getExtParameter()+1);
            $response =array(
                "uid"=>$data["uid"],
                "balance" => array(
                    "value" =>$client_details->balance +$data["args"]["win"],
                    "version" => round(microtime(true) * 1000)//$this->_getExtParameter()
                ),
            );

            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "provider" => "bng",
                    //"isUpdate" => $winGameTransactionExt==null?false:true,
                    "game_transaction_ext_id" => $winGameTransactionExt==null?$winGametransactionExtId:$winGameTransactionExt->game_trans_ext_id,
                    //"client_connection_name" => $client_details->connection_name,
                ],
                "provider" => [
                    "provider_request" => $data, #R
                    "provider_trans_id"=>$data["uid"], #R
                    "provider_round_id"=>$data["args"]["round_id"], #R
                ],
                "mwapi" => [
                    "roundId"=>$game_transaction->game_trans_id, #R
                    "type"=>2, #R
                    "game_id" => $game_details->game_id, #R
                    "player_id" => $client_details->player_id, #R
                    "mw_response" => $response, #R
                ]
            ];
            $client_response = ClientRequestHelper::fundTransfer_TG($client_details,round($data["args"]["win"],2),$game_details->game_code,$game_details->game_name,$game_transaction->game_trans_id,'credit',false,$action_payload);
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
                    "code"=> "FUNDS_EXCEED",
                )
            );
            //$this->_setExtParameter($this->_getExtParameter()+1);
            try{
                $dataToSave = array(
                    "win"=>2,
                    "transaction_reason" => "FAILED Due to low balance or Client Server Timeout"
                );
                GameTransaction::updateGametransaction($dataToSave,$game_transactionid);
                Helper::updateBNGGameTransactionExt($game_transaction->game_trans_ext_id,$client_response->fundtransferresponse->status->message,$response,'FAILED');
            }catch(\Exception $e){
                Helper::saveLog('betGameInsuficient(BNG)', 12, json_encode($e->getMessage().' '.$e->getLine()), $client_response->fundtransferresponse->status->message);
            }
            Helper::updateBNGGameTransactionExt($game_transaction->game_trans_ext_id,$client_response->requestoclient,$response,$client_response);
            return response($response,200)
                        ->header('Content-Type', 'application/json');
        }
        
    }
    private function betNullWinNotNullUpdate($data,$client_details,$game_details){
        $game = GameTransaction::getGameTransactionByRoundId($data["args"]["round_id"]);
        if($game != null){
            // $createGametransaction = array(
            //     "win" =>$data["args"]["win"] == 0 && $game->pay_amount == 0 ? 0 : 1,
            //     "pay_amount" =>$game->pay_amount+$data["args"]["win"],
            //     "income" =>$game->income - $data["args"]["win"],
            //     "entry_id" =>$data["args"]["win"] == 0 && $game->pay_amount == 0 ? 1 : 2,
            // );
            // $game_transactionid = GameTransaction::updateGametransaction($createGametransaction,$game->game_trans_id);
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
            $winGameTransactionExt=GameTransaction::getGameTransactionDataByProviderTransactionIdAndEntryType($data["uid"],2);
            if($winGameTransactionExt!=null){
                $wingametransactionext = array(
                    "game_trans_id" => $game->game_trans_id,
                    "provider_trans_id" => $data["uid"],
                    "round_id" => $data["args"]["round_id"],
                    "amount" => $data["args"]["win"],
                    "game_transaction_type"=>2,
                    "provider_request" =>json_encode($data),
                    "mw_response" => json_encode($response)
                );
                GameTransaction::updateGametransactionEXT($wingametransactionext,$winGameTransactionExt->game_trans_ext_id);
            }else{
                $wingametransactionext = array(
                    "game_trans_id" => $game->game_trans_id,
                    "provider_trans_id" => $data["uid"],
                    "round_id" => $data["args"]["round_id"],
                    "amount" => $data["args"]["win"],
                    "game_transaction_type"=>2,
                    "provider_request" =>json_encode($data),
                    "mw_response" => json_encode($response)
                );
                $winGametransactionExtId = GameTransaction::createGameTransactionExt($wingametransactionext);
            }

            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "provider" => "bng",
                    //"isUpdate" => $winGameTransactionExt==null?false:true,
                    "game_transaction_ext_id" => $winGameTransactionExt==null?$winGametransactionExtId:$winGameTransactionExt->game_trans_ext_id,
                    //"client_connection_name" => $client_details->connection_name,
                ],
                "provider" => [
                    "provider_request" => $data, #R
                    "provider_trans_id"=>$data["uid"], #R
                    "provider_round_id"=>$data["args"]["round_id"], #R
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
    private function betNotNullWinNotNull($data,$client_details,$game_details){
        $betStart =  microtime(true);
        $gameTransactionData = array(
            "provider_trans_id" => $data["uid"],
            "token_id" => $client_details->token_id,
            "game_id" => $game_details->game_id,
            "round_id" => $data["args"]["round_id"],
            "bet_amount" => $data["args"]["bet"],
            "win" =>$data["args"]["win"] == 0 ? 0 : 1,
            "pay_amount" =>$data["args"]["win"],
            "income" =>$data["args"]["bet"]-$data["args"]["win"],
            "entry_id" =>$data["args"]["win"] == 0 ? 1 : 2,
        );
        $game_transactionid = GameTransaction::createGametransaction($gameTransactionData);
        $betgametransactionext = array(
            "game_trans_id" => $game_transactionid,
            "provider_trans_id" => $data["uid"],
            "round_id" => $data["args"]["round_id"],
            "amount" => $data["args"]["bet"],
            "game_transaction_type"=>1,
            "provider_request" =>json_encode($data),
        );
        $betGametransactionExtId = GameTransaction::createGameTransactionExt($betgametransactionext);
        $client_response = ClientRequestHelper::fundTransfer($client_details,round($data["args"]["bet"],2),$game_details->game_code,$game_details->game_name,$betGametransactionExtId,$game_transactionid,"debit");
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
            $dataToUpdate = array(
                "mw_response" => json_encode($response)
            );
            GameTransaction::updateGametransactionEXT($dataToUpdate,$betGametransactionExtId);
            //Helper::updateBNGGameTransactionExt($betGametransactionExtId,$client_response->requestoclient,$response,$client_response);
            $winStart =  microtime(true);
            //$this->_setExtParameter($this->_getExtParameter()+1);
            $response =array(
                "uid"=>$data["uid"],
                "balance" => array(
                    "value" =>$client_details->balance +$data["args"]["win"],
                    "version" => round(microtime(true) * 1000)//$this->_getExtParameter()
                ),
            );
            $wingametransactionext = array(
                "game_trans_id" => $game_transactionid,
                "provider_trans_id" => $data["uid"],
                "round_id" => $data["args"]["round_id"],
                "amount" => $data["args"]["win"],
                "game_transaction_type"=>2,
                "provider_request" =>json_encode($data),
                "mw_response" => json_encode("from controller")
            );
            $winGametransactionExtId = GameTransaction::createGameTransactionExt($wingametransactionext);
            Helper::saveLog('createGameTransactionExt(BNG)', 12, json_encode($winGametransactionExtId), "");
            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "provider" => "bng",
                    //"isUpdate" => false,
                    "game_transaction_ext_id" => $winGametransactionExtId,
                    //"client_connection_name" => $client_details->connection_name,
                ],
                "provider" => [
                    "provider_request" => $data, #R
                    "provider_trans_id"=>$data["uid"], #R
                    "provider_round_id"=>$data["args"]["round_id"], #R
                ],
                "mwapi" => [
                    "roundId"=>$game_transactionid, #R
                    "type"=>2, #R
                    "game_id" => $game_details->game_id, #R
                    "player_id" => $client_details->player_id, #R
                    "mw_response" => "from funstransfer", #R
                ]
            ];
            $client_response = ClientRequestHelper::fundTransfer_TG($client_details,round($data["args"]["win"],2),$game_details->game_code,$game_details->game_name,$game_transactionid,'credit',false,$action_payload);
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
                    "code"=> "FUNDS_EXCEED",
                )
            );
            //$this->_setExtParameter($this->_getExtParameter()+1);
            try{
                $dataToSave = array(
                    "win"=>2,
                    "transaction_reason" => "FAILED Due to low balance or Client Server Timeout"
                );
                GameTransaction::updateGametransaction($dataToSave,$game_transactionid);
                Helper::updateBNGGameTransactionExt($betGametransactionExtId,$client_response->fundtransferresponse->status->message,$response,'FAILED');
            }catch(\Exception $e){
                Helper::saveLog('betGameInsuficient(BNG)', 12, json_encode($e->getMessage().' '.$e->getLine()), $client_response->fundtransferresponse->status->message);
            }
            Helper::updateBNGGameTransactionExt($betGametransactionExtId,$client_response->requestoclient,$response,$client_response);
            return response($response,200)
                        ->header('Content-Type', 'application/json');
        }
        
    }
    private function betNullWinNotNull($data,$client_details,$game_details){
        $game = GameTransaction::getGameTransactionByRoundId($data["args"]["round_id"]);
        if($game != null){
            $createGametransaction = array(
                "win" =>$data["args"]["win"] == 0 && $game->pay_amount == 0 ? 0 : 1,
                "pay_amount" =>$game->pay_amount+$data["args"]["win"],
                "income" =>$game->income - $data["args"]["win"],
                "entry_id" =>$data["args"]["win"] == 0 && $game->pay_amount == 0 ? 1 : 2,
            );
            $game_transactionid = GameTransaction::updateGametransaction($createGametransaction,$game->game_trans_id);
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
                "provider_request" =>json_encode($data),
                "mw_response" => json_encode($response)
            );
            $winGametransactionExtId = GameTransaction::createGameTransactionExt($wingametransactionext);
            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "provider" => "bng",
                    "game_transaction_ext_id" => $winGametransactionExtId,
                    //"client_connection_name" => $client_details->connection_name,
                ],
                "provider" => [
                    "provider_request" => $data, #R
                    "provider_trans_id"=>$data["uid"], #R
                    "provider_round_id"=>$data["args"]["round_id"], #R
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
        $dataToSave = array(
            "provider_trans_id" => $data["uid"],
            "token_id" => $client_details->token_id,
            "game_id" => $game_details->game_id,
            "round_id" => $data["args"]["round_id"],
            "bet_amount" => $data["args"]["bet"],
            "win" =>0,
            "pay_amount" =>0,
            "income" =>$data["args"]["bet"],
            "entry_id" =>1,
        );
        $game_transactionid = GameTransaction::createGametransaction($dataToSave);
        $betgametransactionext = array(
            "game_trans_id" => $game_transactionid,
            "round_id" =>$data["args"]["round_id"],
            "amount" =>$data["args"]["bet"],
            "game_transaction_type"=>1,
            "provider_request" =>json_encode($data),
        );
        $betGametransactionExtId = GameTransaction::createGameTransactionExt($betgametransactionext);
        $client_response = ClientRequestHelper::fundTransfer($client_details,round($data["args"]["bet"],2),$game_details->game_code,$game_details->game_name,$betGametransactionExtId,$game_transactionid,"debit");
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
            $dataToUpdate = array(
                "mw_response" => json_encode($reponse)
            );
            GameTransaction::updateGametransactionEXT($dataToUpdate,$betGametransactionExtId);
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
                    "win"=>2,
                    "transaction_reason" => "FAILED Due to low balance or Client Server Timeout"
                );
                GameTransaction::updateGametransaction($dataToSave,$game_transactionid);
                $dataToUpdate = array(
                    "mw_response" => json_encode($response)
                );
                GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$betGametransactionExtId,$client_details);
            }catch(\Exception $e){
                Helper::saveLog('betGameInsuficient(BNG)', 12, json_encode($e->getMessage().' '.$e->getLine()), $client_response->fundtransferresponse->status->message);
            }
            Helper::updateBNGGameTransactionExt($betGametransactionExtId,$client_response->requestoclient,$response,$client_response);
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
                $game = TransactionHelper::getGameTransaction($data["token"],$data["args"]["round_id"]);
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
                $client_response = ClientRequestHelper::fundTransfer($client_details,round($refund_amount,2),$game_details->game_code,$game_details->game_name,$transactionId,$gametransactionid,"credit",true);
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
