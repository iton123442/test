<?php

namespace App\Http\Controllers; 

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransaction;
use App\Models\GameTransactionMDB;
use App\Helpers\Game;
use Carbon\Carbon;
use DB;

class OnlyPlayController extends Controller
{

    public function __construct(){
        $this->secret_key = config('providerlinks.onlyplay.secret_key');
        $this->api_url = config('providerlinks.onlyplay.api_url');
        $this->provider_db_id = config('providerlinks.onlyplay.provider_db_id');
        $this->partner_id = config('providerlinks.onlyplay.partner_id');
    }

    public function getBalance(Request $request){
         // Helper::saveLog('OnlyPlay', $this->provider_db_id, json_encode($request->all()), "ENDPOINTHIT LAUNCH");
        $user_id = explode('TG_',$request->user_id);
        $get_client_details = ProviderHelper::getClientDetails("player_id",$user_id[1]);
        // dd($get_client_details);
        $balance = str_replace(".","", $get_client_details->balance);
        $formatBalance = (int) $balance;
        if(!$request->has('token')){
            $response = [
                'success' => false,
                'code' => 7837,
                'message' => 'Internal error'
            ];
            return response($response,200)
                    ->header('Content-Type', 'application/json');
        }

        $response = [
            'success' => true,
            'balance' =>  $formatBalance
        ];
        // Helper::saveLog('OnlyPlay', $this->provider_db_id, json_encode($request->all()), $response);
        return response($response,200)
                ->header('Content-Type', 'application/json');
    }

    public function debitProcess(Request $request){
        $data = $request->all();
        Helper::saveLog('OnlyPlay', $this->provider_db_id, json_encode($request->all()), "ENDPOINTHIT BET");
        $user_id = explode('TG_',$request->user_id);
        $get_client_details = ProviderHelper::getClientDetails("player_id",$user_id[1]);
        $bet_amount = $request->amount/100;
        $gen_game_trans_id = ProviderHelper::idGenerate($get_client_details->connection_name,1);
        $gen_game_extid = ProviderHelper::idGenerate($get_client_details->connection_name,2);
            try{
                ProviderHelper::idenpotencyTable($request->tx_id);
            }catch(\Exception $e){
                $response = [
                    "success" =>  false,
                    "code" => 7837,
                    "message" => "Internal Error",
                ];
                return $response;
            }
            if($get_client_details == null){
                $response = [
                    "success" =>  false,
                    "code" => 2401,
                    "message" => "Session not found or expired",
                ];
                return $response;
            }

            
        try{
                $game_details = Game::find($request->game_bundle, $this->provider_db_id);

                //v1
                // $gameTransactionData = array(
                //     "provider_trans_id" => $request->tx_id,
                //     "token_id" => $get_client_details->token_id,
                //     "game_id" => $game_details->game_id,
                //     "round_id" => $request->round_id,
                //     "bet_amount" => $bet_amount,
                //     "win" => 5,
                //     "pay_amount" => 0,
                //     "income" => 0,
                //     "entry_id" =>1,
                // );
                // // GameTransactionMDB
                // $game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionData, $get_client_details);

                // $gameTransactionEXTData = array(
                //     "game_trans_id" => $game_trans_id,
                //     "provider_trans_id" => $request->tx_id,
                //     "round_id" => $request->round_id,
                //     "amount" => $bet_amount,
                //     "game_transaction_type"=> 1,
                //     "provider_request" =>json_encode($request->all()),
                //     );
                // $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$get_client_details);

                try {
                    $fund_extra_data = [
                        'provider_name' => $game_details->provider_name
                    ];
                    $client_response = ClientRequestHelper::fundTransfer($get_client_details, $bet_amount, $game_details->game_code, $game_details->game_name, $gen_game_extid, $gen_game_trans_id, 'debit', false, $fund_extra_data);
                } catch (\Exception $e) {
                    return $e->getMessage().' '.$e->getLine().' '.$e->getFile();
                }
                            if (isset($client_response->fundtransferresponse->status->code)) {
                                $fundtransfer_bal = number_format($client_response->fundtransferresponse->balance,2,'.','');
                                $balance = str_replace(".","", $fundtransfer_bal);
                                $formatBalance = (int) $balance;
                                ProviderHelper::_insertOrUpdate($get_client_details->token_id, $client_response->fundtransferresponse->balance);
                                switch ($client_response->fundtransferresponse->status->code) {
                                    case '200':
                                        $response = [
                                            'success' => true,
                                            'balance' => $formatBalance
                                        ];
                                        $gameTransactionData = array(
                                            "provider_trans_id" => $request->tx_id,
                                            "token_id" => $get_client_details->token_id,
                                            "game_id" => $game_details->game_id,
                                            "round_id" => $request->round_id,
                                            "bet_amount" => $bet_amount,
                                            "win" => 5,
                                            "pay_amount" => 0,
                                            "income" => 0,
                                            "entry_id" =>1,
                                        );
                                       GameTransactionMDB::createGametransactionV2($gameTransactionData,$gen_game_trans_id,$get_client_details); //create game_transaction
                                       $gameTransactionEXTData = array(
                                            "game_trans_id" => $gen_game_trans_id,
                                            "provider_trans_id" => $request->tx_id,
                                            "round_id" => $request->round_id,
                                            "amount" => $bet_amount,
                                            "game_transaction_type"=> 1,
                                        );
                                       GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$gen_game_extid,$get_client_details); //create extension
                                       $createGameTransactionLog = [
                                          "connection_name" => $get_client_details->connection_name,
                                          "column" =>[
                                              "game_trans_ext_id" => $gen_game_extid,
                                              "request" => json_encode($request->all()),
                                              "response" => json_encode($response),
                                              "log_type" => "provider_details",
                                              "transaction_detail" => "SUCCESS",
                                          ]
                                        ];
                                        ProviderHelper::queTransactionLogs($createGameTransactionLog);
                                        break;
                                    case '402':
                                        $updateGameTransaction = [
                                                'win' => 2
                                            ];
                                        GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $get_client_details);
                                        $response = [
                                                "success" =>  false,
                                                "code" => 7837,
                                                "message" => "Internal Error",
                                        ];
                                        $createGameTransactionLog = [
                                          "connection_name" => $get_client_details->connection_name,
                                          "column" =>[
                                              "game_trans_ext_id" => $gen_game_extid,
                                              "request" => json_encode($request->all()),
                                              "response" => json_encode($response),
                                              "log_type" => "provider_details",
                                              "transaction_detail" => "FAILED",
                                          ]
                                        ];
                                        ProviderHelper::queTransactionLogs($createGameTransactionLog);
                                    break;
                                    default:
                                        $updateGameTransaction = [
                                                'win' => 2
                                            ];
                                        GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $get_client_details);
                                        $response = [
                                            "success" =>  false,
                                            "code" => 7837,
                                            "message" => "Internal Error",
                                        ];
                                        $createGameTransactionLog = [
                                          "connection_name" => $get_client_details->connection_name,
                                          "column" =>[
                                              "game_trans_ext_id" => $gen_game_extid,
                                              "request" => json_encode($request->all()),
                                              "response" => json_encode($response),
                                              "log_type" => "provider_details",
                                              "transaction_detail" => "FAILED",
                                          ]
                                        ];
                                        ProviderHelper::queTransactionLogs($createGameTransactionLog);
                                }
                            }
                            
                           
                Helper::saveLog('OnlyPlay debit', $this->provider_db_id, json_encode($request->all()), $response);
                return response($response,200)
                        ->header('Content-Type', 'application/json');
            } catch (\Exception $e) {
                $msg = array(
                    'error' => '1',
                    'message' => $e->getMessage(),
                );
             return json_encode($msg,JSON_FORCE_OBJECT);
            }
    }

    public function creditProcess(Request $request){
        $data = $request->all();
        Helper::saveLog('OnlyPlay credit', $this->provider_db_id, json_encode($request->all()),"ENDPOINTHIT WIN");
        $user_id = explode('TG_',$request->user_id);
        $get_client_details = ProviderHelper::getClientDetails("player_id",$user_id[1]);
        $pay_amount = $request->amount/100;
        $balance = str_replace(".","", $get_client_details->balance);
        $formatBalance = (int) $balance;
        $gen_game_extid = $this->generateId("ext");

            try{
                ProviderHelper::idenpotencyTable($request->tx_id);
            }catch(\Exception $e){
                $response = [
                    "success" =>  false,
                    "code" => 7837,
                    "message" => "Internal Error",
                ];
                return $response;
            }
            if($get_client_details == null) {
                $response = [
                    "success" =>  false,
                    "code" => 2401,
                    "message" => "Session not found or expired",
                ];
                return $response;

            }
        try {
                    $game_details = Game::find($request->game_bundle, $this->provider_db_id);
    
                    // multi DB
                    // $bet_transaction = GameTransactionMDB::findGameTransactionDetails($request->round_id,'round_id', false, $get_client_details);
                    $bet_transaction = GameTransactionMDB::findGameTransactionDetailsV2($request->round_id,'round_id', false, $get_client_details);
                    $get_client_details->connection_name = $bet_transaction->connection_name;
                    $winbBalance = ($formatBalance/100) + $pay_amount;
                    $win_bal = number_format($winbBalance,2,'.','');
                    $balance = str_replace(".","", $win_bal);
                    $formatWinBalance = (int) $balance;
                    if($bet_transaction == false){
                        $response = [
                            "success" =>  false,
                            "code" => 7837,
                            "message" => "Internal Error",
                        ];
                        return $response;
                    }
                    ProviderHelper::_insertOrUpdate($get_client_details->token_id, $winbBalance);
                    $response = [
                        'success' => true,
                        'balance' => $formatWinBalance
                    ];
                    $entry_id = $pay_amount > 0 ?  2 : 1;

                    $amount = $pay_amount + $bet_transaction->pay_amount;
                    $income = $bet_transaction->bet_amount -  $amount; 

                    if($bet_transaction->pay_amount > 0){
                        $win_or_lost = 1;
                    }else{
                        $win_or_lost = $pay_amount > 0 ?  1 : 0;
                    }
                    
                    // $updateGameTransaction = [
                    //     'win' => 5,
                    //     'pay_amount' => $amount,
                    //     'income' => $income,
                    //     'entry_id' => $entry_id,
                    //     'trans_status' => 2
                    // ];
                    // GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $get_client_details);
    
    
                    // $gameTransactionEXTData = array(
                    //     "game_trans_id" => $bet_transaction->game_trans_id,
                    //     "provider_trans_id" => $request->tx_id,
                    //     "round_id" => $request->round_id,
                    //     "amount" => $pay_amount,
                    //     "game_transaction_type"=> 2,
                    //     "provider_request" =>json_encode($request->all()),
                    //     "mw_response" => json_encode($response),
                    // );
                    // $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$get_client_details);

                    $updateGameTransaction = [
                        'win' => 5,
                        'pay_amount' => $amount,
                        'income' => $income,
                        'entry_id' => $entry_id,
                        'trans_status' => 2
                    ];
                 GameTransactionMDB::updateGametransactionV2($updateGameTransaction, $bet_transaction->game_trans_id, $get_client_details);

                    $gameTransactionEXTData = array(
                        "game_trans_id" => $bet_transaction->game_trans_id,
                        "provider_trans_id" => $request->tx_id,
                        "round_id" => $request->round_id,
                        "amount" => $pay_amount,
                        "game_transaction_type"=> 2,
                        // "provider_request" =>json_encode($request->all()),
                        // "mw_response" => json_encode($response),
                    );
                    GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$gen_game_extid,$get_client_details);
                    $createGameTransactionLog = [
                              "connection_name" => $get_client_details->connection_name,
                              "column" =>[
                                  "game_trans_ext_id" => $gen_game_extid,
                                  "request" => json_encode($request->all()),
                                  "response" => json_encode($response),
                                  "log_type" => "provider_details",
                                  "transaction_detail" => "SUCCESS",
                              ]
                          ];
                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
    
                                $action_payload = [
                                    "type" => "custom", #genreral,custom :D # REQUIRED!
                                    "custom" => [
                                        "provider" => 'OnlyPlay',
                                        "client_connection_name" => $get_client_details->connection_name,
                                        "win_or_lost" => $win_or_lost,
                                        "entry_id" => $entry_id,
                                        "pay_amount" => $pay_amount,
                                        "income" => $income,
                                        "game_trans_ext_id" => $gen_game_extid
                                    ],
                                    "provider" => [
                                        "provider_request" => $data, #R
                                        "provider_trans_id"=> $request->tx_id, #R
                                        "provider_round_id"=> $request->round_id, #R
                                    ],
                                    "mwapi" => [
                                        "roundId"=>$bet_transaction->game_trans_id, #R
                                        "type"=>2, #R
                                        "game_id" => $game_details->game_id, #R
                                        "player_id" => $get_client_details->player_id, #R
                                        "mw_response" => $response, #R
                                    ],
                                    'fundtransferrequest' => [
                                        'fundinfo' => [
                                            'freespin' => false,
                                        ]
                                    ]
                                ];
                    $client_response = ClientRequestHelper::fundTransfer_TG($get_client_details,$pay_amount,$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',false,$action_payload);
                   
                    // $updateTransactionEXt = array(
                    //     "provider_request" =>json_encode($request->all()),
                    //     "mw_response" => json_encode($response),
                    //     'mw_request' => json_encode($client_response->requestoclient),
                    //     'client_response' => json_encode($client_response->fundtransferresponse),
                    //     'transaction_detail' => 'success',
                    //     'general_details' => 'success',
                    // );
                    // GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$get_client_details);
            Helper::saveLog('OnlyPlay', $this->provider_db_id, json_encode($request->all()),$response);
            return response($response,200)
                    ->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $msg = array(
                'error' => '1',
                'message' => $e->getMessage(),
            );
         return json_encode($msg,JSON_FORCE_OBJECT);
        }
    }

    public function rollbackProcess(Request $request){
        $data = $request->all();
         Helper::saveLog('OnlyPlay', $this->provider_db_id, json_encode($request->all()),"ENDPOINTHIT ROLLBACK");
        $user_id = explode('TG_',$request->user_id);
        $get_client_details = ProviderHelper::getClientDetails("player_id",$user_id[1]);
        $bet_transaction = GameTransactionMDB::findGameTransactionDetails($request->round_id,'round_id', 1, $get_client_details);
        // $balance = str_replace(".","", $get_client_details->balance);
        // $formatBalance = (int) $balance;
        // if($bet_transaction != 'false'){
        //     $get_failed_trans = GameTransactionMDB::findGameExt($bet_transaction->game_trans_id,1,'game_trans_id', $get_client_details);
        //     if($get_failed_trans != false){
        //         if($get_failed_trans->transaction_detail == 'failed'){
        //             $response = [
        //                 'success' => true,
        //                 'balance' => $formatBalance
        //             ];
        //             return $balance;
        //         }
        //     }
        // }

            try{
                ProviderHelper::idenpotencyTable($request->tx_id);
            }catch(\Exception $e){
                $response = [
                    "success" =>  false,
                    "code" => 7837,
                    "message" => "Internal Error",
                ];
                return $response;
            }

            
            // if ($bet_transaction->win == 2) {
            //     return response()->json($response);
            // }
            $game_details = Game::find($request->game_bundle, $this->provider_db_id);
            $get_client_details->connection_name = $bet_transaction->connection_name;
            $win_or_lost = 4;
            $entry_id = 2;
            $income = $bet_transaction->bet_amount -  $bet_transaction->bet_amount ;

            $updateGameTransaction = [
                'win' => $win_or_lost,
                'pay_amount' => $bet_transaction->bet_amount,
                'income' => $income,
                'entry_id' => $entry_id,
                'trans_status' => 3
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $get_client_details);

            $gameTransactionEXTData = array(
                    "game_trans_id" => $bet_transaction->game_trans_id,
                    "provider_trans_id" => $request->tx_id,
                    "round_id" => $request->round_id,
                    "amount" => $bet_transaction->bet_amount,
                    "game_transaction_type"=> 3,
                    "provider_request" =>json_encode($request->all()),
                    "mw_response" => null,
            );
            $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$get_client_details);

            $client_response = ClientRequestHelper::fundTransfer($get_client_details, $bet_transaction->bet_amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $bet_transaction->game_trans_id, 'credit', "true");

            if (isset($client_response->fundtransferresponse->status->code)) {
                            
                switch ($client_response->fundtransferresponse->status->code) {
                    case '200':
                        ProviderHelper::_insertOrUpdate($get_client_details->token_id, $client_response->fundtransferresponse->balance);
                        $fundtransfer_bal = number_format($client_response->fundtransferresponse->balance,2,'.','');
                        $balance = str_replace(".","", $fundtransfer_bal);
                        $formatBalance = (int) $balance;
                        $response = [
                            "success" => true,
                            "balance" => $formatBalance
                        ];
                        $updateTransactionEXt = array(
                            "provider_request" =>json_encode($request->all()),
                            "mw_response" => json_encode($response),
                            'mw_request' => json_encode($client_response->requestoclient),
                            'client_response' => json_encode($client_response->fundtransferresponse),
                            'transaction_detail' => 'success',
                            'general_details' => 'success',
                        );
                        GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$get_client_details);
                        Helper::saveLog('OnlyPlay', $this->provider_db_id, json_encode($request->all()),$response);
                        return response($response,200)
                        ->header('Content-Type', 'application/json');
                        break;
                }

               

            }

        

    }
    public function generateId($type){
        if($type == "ext"){
            return shell_exec('date +%s%N');
        }
        if($type == "transid"){
            return shell_exec('date +%s%N');
        }
    }
    public function createSignature(Request $request){
        $data = "partner_id515round_id".$request->round_id."token".$request->token;
        return ProviderHelper::onlyplaySignature($data,$this->secret_key);
    }

    public function getRoundInfo(Request $request){
        $data = "partner_id515round_id".$request->round_id."token".$request->token;
        $signature = providerHelper::onlyplaySignature($data,config('providerlinks.onlyplay.secret_key'));
        $url = 'https://int.stage.onlyplay.net/integration/onlyplay/round_info';
        $requesttosend =[
            'partner_id' => config('providerlinks.onlyplay.partner_id'),
            'round_id' => $request->round_id,
            'sign' => $signature,
            'token' => $request->token,

        ];

        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json'
            ]
        ]);
        $guzzle_response = $client->post($url,['body' => json_encode($requesttosend)]
        );
        $get_round_response = json_decode($guzzle_response->getBody()->getContents());
        // dd($get_round_response);
        return json_encode($get_round_response);
    }
}
