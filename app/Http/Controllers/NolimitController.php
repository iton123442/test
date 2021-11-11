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

class NolimitController extends Controller

{
    
        public function __construct(){
        $this->provider_db_id = config('providerlinks.nolimit.provider_db_id');
        $this->api_url = config('providerlinks.nolimit.api_url');
        $this->operator =config('providerlinks.nolimit.operator');
        $this->operator_key = config('providerlinks.nolimit.operator_key');
        $this->groupid = config('providerlinks.nolimit.Group_ID');
    
        
    
    
      }
    public function index(Request $request)
    {

         // dd($request->all());
        $data = $request->all();
        $method = $data['method'];
        if($method == 'wallet.validate-token'){//
            try {
                Helper::saveLog('Nolimit validate-token', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');
                $client_details = ProviderHelper::getClientDetails('token', $data['params']['token']);
                $user_id = $client_details->player_id;
                if($client_details == null){
                    $response = [
                        "jsonrpc" =>  '2.0',
                        "error" => [
                                'code' => '',
                                'message' => 'Server Error',
                                'data' => [
                                    'code' => 15001,
                                    'message' => 'Authentication failed',
                                ],
                            ],
                        "id" => $data['id'],
                    ];
                    Helper::saveLog('Nolimit Gameluanch client details error', $this->provider_db_id, json_encode($data), $response );
                    return response($response,200)
                        ->header('Content-Type', 'application/json');
                }
                if($this->operator_key != $data['params']['identification']['key']){
                    $response = [
                        "jsonrpc" =>  '2.0',
                        "error" => [
                                'code' => '',
                                'message' => 'Server Error',
                                'data' => [
                                    'code' => 15001,
                                    'message' => 'Authentication failed',
                                ],
                            ],
                        "id" => $data['id'],
                    ];
                    Helper::saveLog('Nolimit Gameluanch operator key error', $this->provider_db_id, json_encode($data), $response );
                    return response($response,200)
                        ->header('Content-Type', 'application/json');
                }

                $response = [
                    "jsonrpc" => "2.0",
                    "result" => [
                        "userId" => $user_id,
                        "username" => $client_details->username,
                        "balance" => [
                            "amount" => $client_details->balance,
                            "currency" => $client_details->default_currency,
                        ],
                    ],
                    "id" => $data['id']
                ];
                Helper::saveLog('Nolimit validate end', $this->provider_db_id, json_encode($data), $response );
                return response($response,200)
                        ->header('Content-Type', 'application/json');
            } catch (Exception $e) {
                Helper::saveLog('Nolimit validate error', $this->provider_db_id, json_encode($data), $e->getMessage() );
                return $e->getMessage();
            } // End Catch
        }//End of Method Validate  
        if ($method == 'wallet.balance') {
            $data = $request->all();
            Helper::saveLog('Nolimit getbalance', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');
            $client_details = ProviderHelper::getClientDetails('player_id', $data['params']['userId']);
            $response = [
                    "jsonrpc" => "2.0",
                    "result" => [
                        "balance" => [
                            "amount" => $client_details->balance,
                            "currency" => $client_details->default_currency,
                        ],
                    ],
                "id" => $data['id']
            ];
            try {
                Helper::saveLog('Nolimit getbalance', $this->provider_db_id, json_encode($data), $response );
                return response($response,200)
                        ->header('Content-Type', 'application/json');
            } catch (\Exception $e) {
                Helper::saveLog('Nolimit getbalance', $this->provider_db_id, json_encode($data), $e->getMessage());
                return $e->getMessage();
            }
        }//end getbalance

        if($method == 'wallet.withdraw')
        {
            Helper::saveLog('Nolimit bet', $this->provider_db_id, json_encode($request->all()), 'ENDPOINT HIT');
            $data = $request->all();
            $client_details = ProviderHelper::getClientDetails('player_id', $data['params']['userId']);
            $bet_amount = $data['params']['withdraw']['amount'];
            $game_code = $data['params']['information']['game'];
            $provider_trans_id = $data['params']['information']['uniqueReference'];
            $round_id = $data['params']['information']['gameRoundId'];
            try{
                ProviderHelper::idenpotencyTable($round_id);
            }catch(\Exception $e){
                $response = [
                'jsonrpc' => '2.0',
                'result' => [
                    'error' => [
                        'code' => "14005",
                        'message' => "Responsible gaming, bet not allowed.",
                        ],
                    ]
                ];
                return $response;
            }

            if($client_details == null){
                $response = [
                'jsonrpc' => '2.0',
                'result' => [
                    'error' => [
                        'code' => "14005",
                        'message' => "Responsible gaming, bet not allowed.",
                        ],
                    ]
                ];
                return $response;
            }
               $game_details = Game::find($game_code, $this->provider_db_id);
       try{
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

                   Helper::saveLog('after gameTransactionData', $this->provider_db_id, json_encode($request->all()), 'ENDPOINT HIT');
                $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
                  
                $gameTransactionEXTData = array(
                    "game_trans_id" => $game_transaction_id,
                    "provider_trans_id" => $provider_trans_id,
                    "round_id" => $round_id,
                    "amount" => $bet_amount,
                    "game_transaction_type"=> 1,
                    "provider_request" =>json_encode($request->all()),
                    );

                 Helper::saveLog('after  gameTransactionEXTData', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');
                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details); 
                $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount, $game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, 'debit');
                if($client_response == false){

                    $response = [

                        'code' => 15002,
                        'message' => 'Invalid request.'
                    ];

                    return $response;
                }
                 Helper::saveLog('after  client_response', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');     
                          
                        if (isset($client_response->fundtransferresponse->status->code)) {

                            ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                           
                            switch ($client_response->fundtransferresponse->status->code) {
                                case '200':
                                
                                         $http_status = 200;
                                     $response = [
                                                    'jsonrpc' => '2.0',
                                                    'result' =>[
                                                    'balance' =>[
                                                            'amount' => (string) $client_response->fundtransferresponse->balance,
                                                            'currency' => $client_details->default_currency,

                                                    ],
                                                    'transactionId' =>$game_transaction_id,
                                                  ],
                                                  'id'=>$data['id']
                                            ];

                                $updateTransactionEXt = array(
                                    "provider_request" =>json_encode($request->all()),
                                    "mw_response" => json_encode($response),
                                    'mw_request' => json_encode($client_response->requestoclient),
                                    'client_response' => json_encode($client_response->fundtransferresponse),
                                    'transaction_detail' => 'success',
                                    'general_details' => 'success',
                                );
                                 Helper::saveLog('after success updateTransactionEXt', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');   
                            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);

                                    break;
                                case '402':
                                    ProviderHelper::updateGameTransactionStatus($game_transaction_id, 2, 99);
                                    $http_status = 200;
                                    $response = [
                                        
                                        'jsonrpc' => '2.0',
                                        'error' => [
                                            'code' => -3200,
                                            'message' => 'Server error',
                                            'data' => [

                                                'code' =>14001,
                                                'message'=> "Insuficient funds",
                                            ],
                                        ],

                                        'id' => $data['id']
                                      
                                    ];

                                $updateTransactionEXt = array(
                                    "provider_request" =>json_encode($request->all()),
                                    "mw_response" => json_encode($response),
                                    'mw_request' => json_encode($client_response->requestoclient),
                                    'client_response' => json_encode($client_response->fundtransferresponse),
                                    'transaction_detail' => 'failed',
                                    'general_details' => 'failed',
                                );
                                 Helper::saveLog('after 402 updateTransactionEXt', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');   
                            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                                    break;
                            }
                               


                        }
                            
                        Helper::saveLog('Nolimit Debit', $this->provider_db_id, json_encode($data), $response);
                        return response()->json($response, $http_status);
        }catch(\Exception $e){
           $response = [
                            'jsonrpc' => '2.0',
                            'result' => [
                                'error' => [
                                    'code' => "15002",
                                    'message' => "Invalid Request",
                                    ],
                                ],
                    ]; 
             $updateTransactionEXt = array(
                                    "provider_request" =>json_encode($request->all()),
                                    "mw_response" => json_encode($response),
                                    'mw_request' => json_encode($client_response->requestoclient),
                                    'client_response' => json_encode($client_response->fundtransferresponse),
                                    'transaction_detail' => 'failed',
                                    'general_details' => 'failed',
                                );
                                 Helper::saveLog(' failed updateTransactionEXt', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');   
                            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
            Helper::saveLog('Nolimit bet error', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return json_encode($response, JSON_FORCE_OBJECT); 
        }// End Catch
        }//end walletwithdraw

         if($method == 'wallet.deposit'){   
            Helper::saveLog('NOLIMIT credit process', $this->provider_db_id, json_encode($request->all()),"ENDPOINTHIT WIN");
            $data = $request->all();
            $client_details = ProviderHelper::getClientDetails('player_id', $data['params']['userId']);
            $pay_amount = $data['params']['deposit']['amount'];
            $provider_trans_id = $data['params']['information']['uniqueReference'];
            $round_id = $data['params']['information']['gameRoundId'];
            $game_code = $data['params']['information']['game'];
            $balance = $client_details->balance;
            $bet_transaction = GameTransactionMDB::findGameTransactionDetails($round_id,'round_id', 1, $client_details);
            Helper::saveLog('NOLIMIT after data request', $this->provider_db_id, json_encode($request->all()),"ENDPOINTHIT WIN");
            try{
                ProviderHelper::idenpotencyTable($provider_trans_id);
            }catch(\Exception $e){

            try{
            if($bet_transaction->pay_amount != $pay_amount){

                    $response = [
                        'jsonrpc' => '2.0',
                        'result' => [
                            'error' => [
                                'code' => "15002",
                                'message' => "Invalid Request",
                                ],
                            ],
                        ];
                        return response($response,200)->header('Content-Type', 'application/json');
                }
                     
                $response = [
                    'jsonrpc' => '2.0',
                    'result' => [

                        'balance' => [

                            'amount' => $balance,
                            'currency' => $client_details->default_currency,
                        ],

                        'transactionID' =>$bet_transaction->game_trans_id,

                    ],
                    'id' => $data['id']
                ];

              return response($response,200)->header('Content-Type', 'application/json');
            }catch(\Exception $e){


                     $response = [
                        'jsonrpc' => '2.0',
                        'result' => [
                            'error' => [
                                'code' => "15002",
                                'message' => "Invalid Request",
                                ],
                            ],
                        ];
                        return response($response,200)->header('Content-Type', 'application/json');

            }
              
            }
            if($client_details == null) {
                $response = [
                'jsonrpc' => '2.0',
                'result' => [
                    'error' => [
                        'code' => "14002",
                        'message' => "Token Error.",
                        ],
                    ],
                ];
                return $response;
            }
          try{
                Helper::saveLog('NOLIMIT start to process', $this->provider_db_id, json_encode($request->all()),"ENDPOINTHIT WIN");
                $game_details = Game::find($game_code, $this->provider_db_id);
                 Helper::saveLog('NOLIMIT find game_detailss', $this->provider_db_id, json_encode($request->all()),"ENDPOINTHIT WIN");
                $client_details->connection_name = $bet_transaction->connection_name;
                $winbBalance = $balance + $pay_amount; 
                ProviderHelper::_insertOrUpdate($client_details->token_id, $winbBalance);

                $response = [
                    'jsonrpc' => '2.0',
                    'result' => [

                        'balance' => [

                            'amount' => (string) $winbBalance,
                            'currency' => $client_details->default_currency,
                        ],

                        'transactionID' =>$bet_transaction->game_trans_id,

                    ],
                    'id' => $data['id']
                ];
                $entry_id = $pay_amount > 0 ?  2 : 1;
            
                    $amount = $pay_amount + $bet_transaction->pay_amount;
                    $income = $bet_transaction->bet_amount -  $amount; 
                    // if($bet_transaction->pay_amount > 0){
                    //     $win_or_lost = 1;
                    // }else{
                    //     $win_or_lost = $pay_amount > 0 ?  1 : 0;
                    // }
                    $win_or_lost = $pay_amount == 0 && $bet_transaction->pay_amount == 0 ? 0 : 1;
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
                                    "provider" => 'Nolimit City',
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
          
                  Helper::saveLog('Nolimit Win Result', $this->provider_db_id, json_encode($request->all()),$response);
                    return response($response,200)
                            ->header('Content-Type', 'application/json');

            }catch(\Exception $e){
                     $response = [
                            'jsonrpc' => '2.0',
                            'result' => [
                                'error' => [
                                    'code' => "15002",
                                    'message' => "Invalid Request",
                                    ],
                                ],
                            ];    
            Helper::saveLog('Nolimit Callback error', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
           return response($response,200)->header('Content-Type', 'application/json');

            }// End of Catch 
        }// End of Deposit

        
       if($method == 'wallet.rollback' ){

        Helper::saveLog('Nolimit refund', $this->provider_db_id, json_encode($request->all()),"ENDPOINTHIT refund");
        $data = $request->all();
        $provider_trans_id = $data['params']['information']['uniqueReference'];
        $round_id = $data['params']['information']['gameRoundId'];
        $game_code = $data['params']['information']['game'];
        $game_details = Game::find($game_code, $this->provider_db_id);
        $client_details = ProviderHelper::getClientDetails('player_id', $data['params']['userId']);
        try{

            ProviderHelper::idenpotencyTable($provider_trans_id);

        }catch(\Exception $e){
                    $bet_transaction = GameTransactionMDB::findGameExt($round_id, 2,'round_id', $client_details);
                    if ($bet_transaction != 'false') {
                        if ($bet_transaction->mw_response == 'null') {
                            $response = array(
                                "jsonrpc" => '2.0',
                                "error" => [
                                    'code' => -32000,
                                    "message" => "Server error",
                                    "data" => [

                                        "code" => 14003,
                                        "message" => "Not found",

                                    ],
                                ],

                                "id" => $data['id']
                            );
                        }else {
                            $response = $bet_transaction->mw_response;
                        }
                        

                    } else {
                               
                    $response = array(
                            'jsonrpc' => '2.0',
                            'result' => [
                                'balance' =>[
                                    "amount" => $client_details->balance,
                                    "currency" => $client_details->default_currency,

                                ],

                            'transactionId' => $provider_trans_id,
                            ],

                            'id'=>$data['id']
                        );
                    } 


                    Helper::saveLog('Nolimit bet found 1 ', $this->provider_db_id, json_encode($request), $response);
                   return response($response,200)->header('Content-Type', 'application/json');
             } // End catch error

                $existing_bet = GameTransactionMDB::findGameExt($round_id, 1,'round_id', $client_details);
                if($existing_bet != 'false'){

                        $client_details->connection_name = $existing_bet->connection_name;
                        $amount = $existing_bet->amount;
                        $balance = $client_details->balance + $amount;
                        ProviderHelper::_insertOrUpdate($client_details->token_id, $balance); 
                        $response = array(
                            'jsonrpc' => '2.0',
                            'result' => [
                                'balance' =>[
                                    "amount" => $client_details->balance,
                                    "currency" => $client_details->default_currency,

                                ],

                            'transactionId' => $provider_trans_id,
                            ],

                            'id'=>$data['id']
                        );
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
                            'win' => 5,
                            "pay_amount" => $amount,
                            'income' => $income,
                            'entry_id' => $entry_id,
                            'trans_status' => 3
                        ];
                        GameTransactionMDB::updateGametransaction($updateGameTransaction, $existing_bet->game_trans_id, $client_details);

                        $body_details = [
                            "type" => "credit",
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
                            Helper::saveLog("Success Nolimit Refund", $this->provider_db_id, json_encode($data), $response);
                            return $response;
                        } catch (\Exception $e) {
                            Helper::saveLog("Nolimit Failed Refund", $this->provider_db_id, json_encode($data), $response);
                            return $response;
                        }

                }else{

                     $response = array(
                                "jsonrpc" => '2.0',
                                "error" => [
                                    'code' => -32000,
                                    "message" => "Server error",
                                    "data" => [

                                        "code" => 14002,
                                        "message" => "Token error",
                                    ],
                                ],

                                "id" => $data['id']
                            );
                     Helper::saveLog("Nolimit not found transaction Spin", $this->provider_db_id, json_encode($data), $response);
                   return response($response,200)->header('Content-Type', 'application/json');

                }

        }// End of Rollback

   }//End Function


}//End Class controller