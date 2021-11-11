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

class SmartsoftGamingController extends Controller
{

    public function __construct(){
        $this->SecretHashKey = config('providerlinks.smartsoft.SecretHashKey');
        $this->api_url = config('providerlinks.smartsoft.api_url');
        $this->provider_db_id = config('providerlinks.smartsoft.provider_db_id');
        $this->PortalName = config('providerlinks.smartsoft.PortalName');
    }

    public function ActiveSession(Request $request){
        Helper::saveLog('Smartsoft Active Session', $this->provider_db_id, json_encode($request->all()), 'ENDPOINT HIT');
        $data = $request->all();
        $client_details = ProviderHelper::getClientDetails('token', $data['Token']);

        if($client_details == null){
            $response = [
                    "Error Code" => 500,
                    "Error Message" => 'Internal error'
            ];
            Helper::saveLog('Smartsoft Gameluanch client details error', $this->provider_db_id, json_encode($data), $response );
            return response($response,200)
                ->header('Content-Type', 'application/json');
        }
        $response = [
                "UserName" => $client_details->username,
                "SessionId" => $data['Token'],
                "ClientExternalKey" =>$client_details->player_id,
                "PortalName" => $this->PortalName,
                "CurrencyCode" => $client_details->default_currency
        ];
        Helper::saveLog('Smartsoft activate end', $this->provider_db_id, json_encode($data), $response );
        return response($response,200)
                ->header('Content-Type', 'application/json');
    }


    public function GetBalance(Request $request){
            $requestHeader = [
               "X-SessionId" => $request->header('X-SessionId'),
               "X-Signature" => $request->header('X-Signature'),
               "X-UserName" => $request->header('X-UserName'),
               "X-ClientExternalKey" => $request->header('X-ClientExternalKey')
            ];
            Helper::saveLog('Smarsoft getbalance', $this->provider_db_id, json_encode($requestHeader), 'ENDPOINT HIT');
           
            try {
                $client_details = ProviderHelper::getClientDetails('token', $request->header('X-SessionId'));
                $response = [
                       "CurrencyCode" => $client_details->default_currency,
                       "Amount" => (float) $client_details->balance
                ];
    
                Helper::saveLog('Smartsoft getbalance', $this->provider_db_id, json_encode($requestHeader), $response );
                return response($response,200)
                        ->header('Content-Type', 'application/json');
            } catch (\Exception $e) {
                Helper::saveLog('Smartsoft getbalance', $this->provider_db_id, json_encode($requestHeader), $e->getMessage());
                return $e->getMessage();
            }

    }

    public function Deposit(Request $request){
         Helper::saveLog('Smartsoft bet', $this->provider_db_id, json_encode($request->all()), 'ENDPOINT HIT');
            $data = $request->all();
            $client_details = ProviderHelper::getClientDetails('token', $request->header('X-SessionId'));
            // dd($client_details->balance);
            $bet_amount = $data['Amount'];
            $game_code = $data['TransactionInfo']['GameName'];
            $provider_trans_id = $data['TransactionId'];
            $round_id = $data['TransactionInfo']['RoundId'];
            $currency = $data['CurrencyCode'];
            $gamenumber = $data['TransactionInfo']['GameNumber'];
            try{
                ProviderHelper::idenpotencyTable($provider_trans_id);
            }catch(\Exception $e){
                $response = [
                  "Error Code" => 112,
                  "Message" => "Loss Limit"
                ];
                return $response;
            }

            if($client_details == null){
                $response = [
                  
                  "Error Code" => 112,
                  "Error Message" => "Loss Limit"
                ];
                return $response;
            }
               $game_details = Game::find($game_code, $this->provider_db_id);
       try{
         $bet_transaction = GameTransactionMDB::findGameTransactionDetails($round_id, 'round_id',false, $client_details);
         if ($bet_transaction != 'false') {
                $client_details->connection_name = $bet_transaction->connection_name;
                $amount = $bet_transaction->bet_amount + $bet_amount;
                $game_transaction_id = $bet_transaction->game_trans_id;
                $updateGameTransaction = [
                    'win' => 5,
                    'bet_amount' => $amount,
                    'entry_id' => 1,
                    'trans_status' => 1
                ];
               Helper::saveLog(' smartsoft Sidebet success', $this->provider_db_id, json_encode($request->all()), 'ENDPOINT HIT');
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
            }else{
                $gameTransactionData = array(
                    "provider_trans_id" => $provider_trans_id,
                    "token_id" => $client_details->token_id,
                    "game_id" => $game_details->game_id,
                    "round_id" => $round_id,
                    "bet_amount" => $bet_amount,
                    "win" => 5,
                    "pay_amount" => 0,
                    "income" => 0,
                    "entry_id" => 1,
                        ); 

                   Helper::saveLog(' smartsoft after gameTransactionData', $this->provider_db_id, json_encode($request->all()), 'ENDPOINT HIT');
                $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
                } 
                $gameTransactionEXTData = array(
                    "game_trans_id" => $game_transaction_id,
                    "provider_trans_id" => $provider_trans_id,
                    "round_id" => $round_id,
                    "amount" => $bet_amount,
                    "game_transaction_type"=> 1,
                    "provider_request" =>json_encode($request->all()),
                    );

                 Helper::saveLog(' smartsoft after  gameTransactionEXTData', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');
                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details); 
                $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount, $game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, 'debit');
                if($client_response == false){

                    $response = [

                        'Error Code' => 500,
                        'message' => 'Internal error'
                    ];

                    return $response;
                }
                 Helper::saveLog(' smartsoft after  client_response', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');     
                          
                        if (isset($client_response->fundtransferresponse->status->code)) {

                            ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                           
                            switch ($client_response->fundtransferresponse->status->code) {
                                case '200':
                                
                                         $http_status = 200;
                                     $response = [
                                                
                                              "TransactionId" => $provider_trans_id,
                                              "Balance" => (float) $client_response->fundtransferresponse->balance
                                            ];

                                $updateTransactionEXt = array(
                                    "provider_request" =>json_encode($request->all()),
                                    "mw_response" => json_encode($response),
                                    'mw_request' => json_encode($client_response->requestoclient),
                                    'client_response' => json_encode($client_response->fundtransferresponse),
                                    'transaction_detail' => 'success',
                                    'general_details' => 'success',
                                );
                                 Helper::saveLog('smartsoft after success updateTransactionEXt', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');   
                            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);

                                    break;
                                case '402':
                                    // ProviderHelper::updateGameTransactionStatus($game_transaction_id, 2, 99);
                                    $http_status = 200;
                                    $response = [
                                        
                                         "Error Code" => 500,
                                         "Message" => "Internal Error"
                                      
                                    ];

                                $updateTransactionEXt = array(
                                    "provider_request" =>json_encode($request->all()),
                                    "mw_response" => json_encode($response),
                                    'mw_request' => json_encode($client_response->requestoclient),
                                    'client_response' => json_encode($client_response->fundtransferresponse),
                                    'transaction_detail' => 'failed',
                                    'general_details' => 'failed',
                                );
                                 Helper::saveLog('smartsoft after 402 updateTransactionEXt', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');   
                            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                                    break;
                            }
                               


                        }
                            
                        Helper::saveLog('Smartsoft Debit', $this->provider_db_id, json_encode($data), $response);
                        return response()->json($response, $http_status);
        }catch(\Exception $e){
           $response= [
                            "Error Code" => 112,
                             "Message" => $e->getMessage()
                    ]; 
            Helper::saveLog('Smartsoft bet error', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT), $e->getMessage());
            return json_encode($response, JSON_FORCE_OBJECT); 
        }// End Catch

    }

    public function Withdraw(Request $request){
        Helper::saveLog('Smartsoft credit process', $this->provider_db_id, json_encode($request->all()),"ENDPOINTHIT");
        $data = $request->all();
        $method = $data['TransactionType'];
        $client_details = ProviderHelper::getClientDetails('token', $request->header('X-SessionId'));
        $pay_amount = $data['Amount'];
        $game_code = $data['TransactionInfo']['GameName'];
        $provider_trans_id = $data['TransactionId'];
        $round_id = $data['TransactionInfo']['RoundId'];
        $currency = $data['CurrencyCode'];
        $gamenumber = $data['TransactionInfo']['GameNumber'];
        $balance = $client_details->balance;
        $bet_transaction = GameTransactionMDB::findGameTransactionDetails($round_id,'round_id', false, $client_details);
        if($client_details == null){
            $response = [
              "Error Code" => 500,
              "Message" => "Internal Error"
            ];
            return $response;
        }
        if($method == 'CloseRound'){
            try {
                    Helper::saveLog('closeRound', $this->provider_db_id, json_encode($request->all()),"ENDPOINTHIT");
                    $game_details = Game::find($game_code, $this->provider_db_id);
                    $client_details->connection_name = $bet_transaction->connection_name;

                    $response = [
                        "TransactionId" => $provider_trans_id,  
                        "Balance" => (float)$client_details->balance                                           
                    ];

                    if($bet_transaction->pay_amount > 0){
                        $win_or_lost = $bet_transaction->win;
                    }else{
                        $win_or_lost = 0;
                    }

                    $entry_id = $pay_amount > 0 ?  2 : 1;
                    $amount = $bet_transaction->pay_amount;
                    $income = $bet_transaction->income;

                    $updateGameTransaction = [
                        'win' => 5,
                        'pay_amount' => $amount,
                        'income' => $income,
                        'entry_id' => $entry_id,
                        'trans_status' => 2
                    ];
                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);

                    $gameTransactionEXTData = array(
                        "game_trans_id" => $bet_transaction->game_trans_id,
                        "provider_trans_id" => $provider_trans_id,
                        "round_id" => $round_id,
                        "amount" => $pay_amount,
                        "game_transaction_type"=> 2,
                        "provider_request" =>json_encode($request->all()),
                        "mw_response" => json_encode($response),
                    );
                    $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
                    
                    $action_payload = [
                            "type" => "custom", #genreral,custom :D # REQUIRED!
                            "custom" => [
                                "provider" => 'Smartsoft Gaming',
                                "client_connection_name" => $client_details->connection_name,
                                "win_or_lost" => $win_or_lost,
                                "entry_id" => $entry_id,
                                "pay_amount" => $pay_amount,
                                "income" => $income,
                                "game_trans_ext_id" => $game_trans_ext_id
                            ],
                            "provider" => [
                                "provider_request" => $data, #R
                                "provider_trans_id"=> $provider_trans_id, #R
                                "provider_round_id"=> $round_id, #R
                            ],
                            "mwapi" => [
                                "roundId"=>$bet_transaction->game_trans_id, #R
                                "type"=>2, #R
                                "game_id" => $game_details->game_id, #R
                                "player_id" => $client_details->player_id, #R
                                "mw_response" => $response, #R
                            ],
                            'fundtransferrequest' => [
                                'fundinfo' => [
                                    'freespin' => false,
                                ]
                            ]
                        ];
                    $updateTransactionEXt = array(
                        "provider_request" =>json_encode($request->all()),
                        "mw_response" => json_encode($response),
                    );
                    GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                    $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$pay_amount,$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',false,$action_payload);

                  Helper::saveLog('SmartSoft Closeround Result', $this->provider_db_id, json_encode($request->all()),$response);
                  return response($response,200)->header('Content-Type', 'application/json');
            } catch (\Exception $e) {
                $response = [
                  "Error Code" => 500,
                  "Message" => $e->getMessage()
                ];    
                Helper::saveLog('SmartSoft Callback error', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
                return response($response,200)->header('Content-Type', 'application/json');
            }
        }//close round
        // ------------------------------------------------------------------------------------------------------------------------------
        try{
            ProviderHelper::idenpotencyTable($provider_trans_id);
        }catch(\Exception $e){
            try{
                if($bet_transaction->pay_amount != $pay_amount){
                     $response = [
                          "Error Code" => 500,
                          "Message" => "Internal Error"
                        ];
                            return response($response,200)->header('Content-Type', 'application/json');
                    }
                    $response = [
                         "TransactionId" => $provider_trans_id,
                         "Balance" => (float) $balance
                    ];
              return response($response,200)->header('Content-Type', 'application/json');
            }catch(\Exception $e){
             $response = [
                    "Error Code" => 500,
                    "Message" => $e->getMessage()
                ];
             return response($response,200)->header('Content-Type', 'application/json');
            }
        }
        if($method == 'ClearBet'){
            try {
                $game_details = Game::find($game_code, $this->provider_db_id);
                $client_details->connection_name = $bet_transaction->connection_name;
                $winbBalance = $balance + $pay_amount; 
                ProviderHelper::_insertOrUpdate($client_details->token_id, $winbBalance);
                $response = [
                    "TransactionId" => $provider_trans_id,
                    "Balance" => (float)$winbBalance                                           
                ];
                $entry_id = $pay_amount > 0 ?  2 : 1;
            
                $amount = $pay_amount + $bet_transaction->pay_amount;
                $income = $bet_transaction->bet_amount -  $amount; 
                $win_or_lost = 4;
               
                $updateGameTransaction = [
                    'win' => $win_or_lost,
                    'pay_amount' => $amount,
                    'income' => $income,
                    'entry_id' => $entry_id,
                    'trans_status' => 2
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
                $gameTransactionEXTData = array(
                    "game_trans_id" => $bet_transaction->game_trans_id,
                    "provider_trans_id" => $provider_trans_id,
                    "round_id" => $round_id,
                    "amount" => $pay_amount,
                    "game_transaction_type"=> 3,
                    "provider_request" =>json_encode($request->all()),
                    "mw_response" => json_encode($response),
                );

                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);


                    $action_payload = [
                        "type" => "custom", #genreral,custom :D # REQUIRED!
                        "custom" => [
                            "provider" => 'Smartsoft Gaming',
                            "client_connection_name" => $client_details->connection_name,
                            "win_or_lost" => $win_or_lost,
                            "entry_id" => $entry_id,
                            "pay_amount" => $pay_amount,
                            "income" => $income,
                            "game_trans_ext_id" => $game_trans_ext_id
                        ],
                        "provider" => [
                            "provider_request" => $data, #R
                            "provider_trans_id"=> $provider_trans_id, #R
                            "provider_round_id"=> $round_id, #R
                        ],
                        "mwapi" => [
                            "roundId"=>$bet_transaction->game_trans_id, #R
                            "type"=>2, #R
                            "game_id" => $game_details->game_id, #R
                            "player_id" => $client_details->player_id, #R
                            "mw_response" => $response, #R
                        ],
                        'fundtransferrequest' => [
                            'fundinfo' => [
                                'freespin' => false,
                            ]
                        ]
                    ];
                $updateTransactionEXt = array(
                    "provider_request" =>json_encode($request->all()),
                    "mw_response" => json_encode($response),
                );
                GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$pay_amount,$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',false,$action_payload);
                
                  Helper::saveLog('SmartSoft Clear Bet Result', $this->provider_db_id, json_encode($request->all()),$response);
                  return response($response,200)->header('Content-Type', 'application/json');

            } catch (\Exception $e) {
                $response = [
                  "Error Code" => 500,
                  "Message" => $e->getMessage()
                ];    
                Helper::saveLog('SmartSoft Callback error', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
                   return response($response,200)->header('Content-Type', 'application/json');
            }
        }//clear bet -------
        // ------------------------------------------------------------------------------------------------------------------------------------------
        if($method == 'WinAmount'){
            try{
                Helper::saveLog('Smartsoft start to process', $this->provider_db_id, json_encode($request->all()),"ENDPOINTHIT WIN");
                $game_details = Game::find($game_code, $this->provider_db_id);
                Helper::saveLog('Smartsoft find game_detailss', $this->provider_db_id, json_encode($request->all()),"ENDPOINTHIT WIN");
                $client_details->connection_name = $bet_transaction->connection_name;
                $winbBalance = $balance + $pay_amount; 
                ProviderHelper::_insertOrUpdate($client_details->token_id, $winbBalance);

                $response = [
                    "TransactionId" => $provider_trans_id,
                    "Balance" => (float)$winbBalance                                           
                ];
                $entry_id = $pay_amount > 0 ?  2 : 1;
            
                $amount = $pay_amount + $bet_transaction->pay_amount;
                $income = $bet_transaction->bet_amount -  $amount; 
                if($bet_transaction->pay_amount > 0){
                    $win_or_lost = 1;
                }else{
                    $win_or_lost = $pay_amount > 0 ?  1 : 0;
                }
                $updateGameTransaction = [
                    'win' => $win_or_lost,
                    'pay_amount' => $amount,
                    'income' => $income,
                    'entry_id' => $entry_id,
                    'trans_status' => 2
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
                $gameTransactionEXTData = array(
                    "game_trans_id" => $bet_transaction->game_trans_id,
                    "provider_trans_id" => $provider_trans_id,
                    "round_id" => $round_id,
                    "amount" => $pay_amount,
                    "game_transaction_type"=> 2,
                    "provider_request" =>json_encode($request->all()),
                    "mw_response" => json_encode($response),
                );
                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
                    $action_payload = [
                        "type" => "custom", #genreral,custom :D # REQUIRED!
                        "custom" => [
                            "provider" => 'Smartsoft Gaming',
                            "client_connection_name" => $client_details->connection_name,
                            "win_or_lost" => $win_or_lost,
                            "entry_id" => $entry_id,
                            "pay_amount" => $pay_amount,
                            "income" => $income,
                            "game_trans_ext_id" => $game_trans_ext_id
                        ],
                        "provider" => [
                            "provider_request" => $data, #R
                            "provider_trans_id"=> $provider_trans_id, #R
                            "provider_round_id"=> $round_id, #R
                        ],
                        "mwapi" => [
                            "roundId"=>$bet_transaction->game_trans_id, #R
                            "type"=>2, #R
                            "game_id" => $game_details->game_id, #R
                            "player_id" => $client_details->player_id, #R
                            "mw_response" => $response, #R
                        ],
                        'fundtransferrequest' => [
                            'fundinfo' => [
                                'freespin' => false,
                            ]
                        ]
                    ];
                 $updateTransactionEXt = array(
                    "provider_request" =>json_encode($request->all()),
                    "mw_response" => json_encode($response),
                );
                GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                 $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$pay_amount,$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',false,$action_payload);
                
                  Helper::saveLog('SmartSoft Win Result', $this->provider_db_id, json_encode($request->all()),$response);
                  return response($response,200)->header('Content-Type', 'application/json');
            }catch(\Exception $e){
                    $response = [
                      "Error Code" => 500,
                      "Message" => $e->getMessage()
                    ];    
                   Helper::saveLog('SmartSoft Callback error', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
                   return response($response,200)->header('Content-Type', 'application/json');
            }// End of Catch 
        }
      }//end function



    public function Rollback(Request $request){
        Helper::saveLog('Smartsoft refund', $this->provider_db_id, json_encode($request->all()),"ENDPOINTHIT refund");
        $data = $request->all();
        $rollback_id = $data['TransactionId'];
        $provider_trans_id = $data['CurrentTransactionId'];
        $round_id = $data['TransactionInfo']['RoundId'];
        // $game_code = $data['TransactionInfo']['GameName'];
        $client_details = ProviderHelper::getClientDetails('token', $request->header('X-SessionId'));
        if($data['TransactionInfo']['GameName'] != null){
            $game_details = Game::find($data['TransactionInfo']['GameName'], $this->provider_db_id);
        }else{
            $getGameID = GameTransactionMDB::findGameTransactionDetails($rollback_id,'transaction_id', false, $client_details);
            $game_details = Game::findByGameID($getGameID->game_id, $this->provider_db_id);
        }
        try{
            ProviderHelper::idenpotencyTable($provider_trans_id);
        }catch(\Exception $e){
                    $bet_transaction = GameTransactionMDB::findGameExt($round_id, 3,'round_id', $client_details);
                    if ($bet_transaction != 'false') {
                        if ($bet_transaction->mw_response == 'null') {
                            $response = array(
                            "Error Code" => $request->header('X-ErrorCode'),
                             "Message" => $request->header('X-ErrorMessage')
                            );
                        }else {
                            $response = $bet_transaction->mw_response;
                        }
                        

                    } else {
                               
                    $response = array(
                        
                         "Error Code" => 112,
                          "Message" =>"Limit loss"
                        );
                    } 


                    Helper::saveLog('Smartsoft bet found 1 ', $this->provider_db_id, json_encode($request), $response);
                   return response($response,200)->header('Content-Type', 'application/json');
             } // End catch error
        $existing_bet = GameTransactionMDB::findGameExt($rollback_id, false,'transaction_id', $client_details);
        $game_trans_type = $existing_bet->game_transaction_type;
                if($existing_bet != 'false'){
                        $client_details->connection_name = $existing_bet->connection_name;
                        $amount = $existing_bet->amount;
                        if($game_trans_type == 1){
                            $type = "credit";
                            $balance = $client_details->balance + $amount;
                            ProviderHelper::_insertOrUpdate($client_details->token_id, $balance); 
                        }else{
                            $type = "debit";
                            $balance =  $client_details->balance - $amount;
                            ProviderHelper::_insertOrUpdate($client_details->token_id, $balance); 
                        }
                        $response = [

                                "TransactionId" => $existing_bet->game_trans_id,
                                "Balance" => $balance

                        ];
                        $gameTransactionEXTData = array(
                            "game_trans_id" => $existing_bet->game_trans_id,
                            "provider_trans_id" => $provider_trans_id,
                            "round_id" => $round_id,
                            "amount" => $amount,
                            "game_transaction_type"=> 3,
                            "provider_request" =>json_encode($data),
                            "mw_response" =>json_encode($response),
                        );
                        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
                        
                        $win_or_lost = 4;
                        $entry_id = 2;
                        $income = 0 ;
                        $updateGameTransaction = [
                            'win' => $win_or_lost,
                            "pay_amount" => $amount,
                            'income' => $income,
                            'entry_id' => $entry_id,
                            'trans_status' => 3
                        ];
                        GameTransactionMDB::updateGametransaction($updateGameTransaction, $existing_bet->game_trans_id, $client_details);

                         $body_details = [
                            "type" => $type,
                            "win" => $win_or_lost,
                            "token" => $client_details->player_token,
                            "rollback" => "true",
                            "game_details" => [
                                "game_id" => $game_details->game_id
                            ],
                            "game_transaction" => [
                                "amount" => $amount
                            ],
                            "connection_name" => $existing_bet->connection_name,
                            "game_trans_ext_id" => $game_trans_ext_id,
                            "game_transaction_id" => $existing_bet->game_trans_id

                        ];
                        try {
                            $client = new Client();
                            $guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-bgFundTransferV2MultiDB',
                                [ 'body' => json_encode($body_details), 'timeout' => '2.00']
                            );
                            //THIS RESPONSE IF THE TIMEOUT NOT FAILED
                            Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($data), $response);
                            return $response;
                        } catch (\Exception $e) {
                            Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($data), $response);
                            return $response;
                        }

                }else{

                     $response = array(
                             "Error Code" => 112,
                             "Message" => 'Loss Limit'
                            );
                     Helper::saveLog("Smartsoft not found transaction Spin", $this->provider_db_id, json_encode($data), $response);
                   return response($response,200)->header('Content-Type', 'application/json');

                }
    }



}
