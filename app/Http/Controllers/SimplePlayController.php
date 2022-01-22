<?php

namespace App\Http\Controllers;

use App\Models\PlayerDetail;
use App\Models\PlayerSessionToken;
use App\Helpers\Helper;
use App\Helpers\GameTransaction;
use App\Helpers\GameSubscription;
use App\Helpers\GameRound;
use App\Helpers\Game;
use App\Helpers\CallParameters;
use App\Helpers\PlayerHelper;
use App\Helpers\TokenHelper;
use App\Helpers\ProviderHelper;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransactionMDB;
use App\Support\RouteParam;

use Illuminate\Http\Request;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;

use DB;

class SimplePlayController extends Controller
{
    public function __construct(){
        /*$this->middleware('oauth', ['except' => ['index']]);*/
        /*$this->middleware('authorize:' . __CLASS__, ['except' => ['index', 'store']]);*/
        $key = "g9G16nTs";
        $iv = 0;

        $this->key = $key;
        if( $iv == 0 ) {
            $this->iv = $key;
        } else {
            $this->iv = $iv;
        }
    }

    public function getBalance(Request $request) 
    {   

        $string = file_get_contents("php://input");
        $decrypted_string = $this->decrypt(urldecode($string));
        $query = parse_url('http://test.url?'.$decrypted_string, PHP_URL_QUERY);
        parse_str($query, $request_params);

        $client_code = RouteParam::get($request, 'brand_code');

        header("Content-type: text/xml; charset=utf-8");
        $response = '<?xml version="1.0" encoding="utf-8"?>';
        $response .= '<RequestResponse><error>1007</error></RequestResponse>';

        $playerId = $request_params['username'];         
        // Find the player and client details
        $client_details = ProviderHelper::getClientDetails('player_id', $playerId);

        $player_currency = $client_details->default_currency;
       

        if ($client_details != null) {

                header("Content-type: text/xml; charset=utf-8");
                $response = '<?xml version="1.0" encoding="utf-8"?>';
                $response .= '<RequestResponse><username>'.$request_params['username'].'</username><currency>'.$player_currency.'</currency><amount>'.$client_details->balance.'</amount><error>0</error></RequestResponse>';
            
        }
        
        Helper::saveLog('simpleplay_balance', 35, json_encode($request_params), $response);
        echo $response;

    }

    public function debitProcess(Request $request) 
    {
        $string = file_get_contents("php://input");
        $decrypted_string = $this->decrypt(urldecode($string));
        $query = parse_url('http://test.url?'.$decrypted_string, PHP_URL_QUERY);
        parse_str($query, $request_params);

        // $incoming_req = $this->encrypt($string);
        // $decrypted_string = $this->decrypt($incoming_req);
        // $query = parse_url('http://test.url?'.$decrypted_string, PHP_URL_QUERY);
        // parse_str($query, $request_params);

        $playerId = $request_params['username'];
        $amount = $request_params['amount'];
        $transaction_id = $request_params['txnid'];
        $game_code = $request_params['gamecode'];
        $round_id = $request_params['gameid'];

        header("Content-type: text/xml; charset=utf-8");
        $response = '<?xml version="1.0" encoding="utf-8"?>';
        $response .= '<RequestResponse><error>1007</error></RequestResponse>';

        $client_details = ProviderHelper::getClientDetails('player_id', $playerId);

        if ($client_details != null) {
            try{
                ProviderHelper::idenpotencyTable($transaction_id);
            }catch(\Exception $e){
                header("Content-type: text/xml; charset=utf-8");
                    $response = '<?xml version="1.0" encoding="utf-8"?>';
                    $response .= '<RequestResponse><error>9999</error></RequestResponse>';
                return $response;
            }
                $player_currency = $client_details->default_currency;
            
            //GameRound::create($json_data['roundid'], $player_details->token_id);

            // Check if the game is available for the client
            /*$subscription = new GameSubscription();
            $client_game_subscription = $subscription->check($client_details->client_id, 35, $request_params['gamecode']);

            if(!$client_game_subscription) {
                $response = '<?xml version="1.0" encoding="utf-8"?>';
                $response .= '<RequestResponse><username>'.$request_params['username'].'</username><currency>USD</currency><amount>0</amount><error>135</error></RequestResponse>';
            }
            else
            {*/

                // $json_data['amount'] = $amount;
                // $json_data['income'] = $amount;
                // $json_data['roundid'] = $transaction_id;
                // $json_data['transid'] = $transaction_id;

                $game_details = Game::find($game_code, config("providerlinks.simpleplay.PROVIDER_ID"));

                $gameTransactionData = array(
                    "provider_trans_id" => $transaction_id,
                    "token_id" => $client_details->token_id,
                    "game_id" => $game_details->game_id,
                    "round_id" => $round_id,
                    "bet_amount" => $amount,
                    "win" => 5,
                    "pay_amount" => 0,
                    "income" => $amount,
                    "entry_id" => 1,
                );
                $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData,$client_details);

                $bet_game_transaction_ext = array(
                    "game_trans_id" => $game_transaction_id,
                    "provider_trans_id" => $transaction_id,
                    "round_id" => $round_id,
                    "amount" => $amount,
                    "game_transaction_type" => 1,
                    "provider_request" => json_encode($request_params),
                );

                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($bet_game_transaction_ext, $client_details);

                $client_response = ClientRequestHelper::fundTransfer($client_details, $amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, 'debit');

                if (isset($client_response->fundtransferresponse->status->code)) {
                    $response = '<?xml version="1.0" encoding="utf-8"?>';
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    switch ($client_response->fundtransferresponse->status->code) {
                        case '200':
                            header("Content-type: text/xml; charset=utf-8");
                             $response = '<?xml version="1.0" encoding="utf-8"?>';
                             $response .= '<RequestResponse><username>'.$request_params['username'].'</username><currency>'.$player_currency.'</currency><amount>'.$client_response->fundtransferresponse->balance.'</amount><error>0</error></RequestResponse>';
                            $updateTransactionEXt = array(
                                "provider_request" =>json_encode($request_params),
                                "mw_response" => json_encode($response),
                                'mw_request' => json_encode($client_response->requestoclient),
                                'client_response' => json_encode($client_response->fundtransferresponse),
                                'transaction_detail' => 'success',
                                'general_details' => 'success',
                            );
                            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                          
                            break;
                        case '402':
                             header("Content-type: text/xml; charset=utf-8");
                                $response = '<?xml version="1.0" encoding="utf-8"?>';
                                $response .= '<RequestResponse><error>1004</error></RequestResponse>';
                            $updateGameTransaction = [
                                        'win' => 2,
                                        'trans_status' => 3,
                                    ];
                            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_transaction_id, $client_details);
                            $updateTransactionEXt = array(
                                "provider_request" =>json_encode($request_params),
                                "mw_response" => json_encode($response),
                                'mw_request' => json_encode($client_response->requestoclient),
                                'client_response' => json_encode($client_response->fundtransferresponse),
                                'transaction_detail' => 'FAILED',
                                'general_details' => 'FAILED',
                            );
                            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                            break;
                    }
                
                }

            /*}*/
        }
        
        Helper::saveLog('simpleplay_debit', 35, json_encode($request_params), $response);
        echo $response;
    }

    public function creditProcess(Request $request) 
    {
        $string = file_get_contents("php://input");
        $decrypted_string = $this->decrypt(urldecode($string));
        $query = parse_url('http://test.url?'.$decrypted_string, PHP_URL_QUERY);
        parse_str($query, $request_params);

        $playerId = $request_params['username'];
        $pay_amount = $request_params['amount'];
        $transaction_id = $request_params['txnid'];
        $game_code = $request_params['gamecode'];
        $round_id = $request_params['gameid'];
 
        header("Content-type: text/xml; charset=utf-8");
        $response = '<?xml version="1.0" encoding="utf-8"?>';
        $response .= '<RequestResponse><error>1007</error></RequestResponse>';

        $client_details = ProviderHelper::getClientDetails('player_id', $playerId);
        
        if ($client_details != null) {
            try{
                ProviderHelper::idenpotencyTable($transaction_id);
            }catch(\Exception $e){
                header("Content-type: text/xml; charset=utf-8");
                    $response = '<?xml version="1.0" encoding="utf-8"?>';
                    $response .= '<RequestResponse><error>9999</error></RequestResponse>';
                return $response;
            }
            $player_currency = $client_details->default_currency;
            
            // $subscription = new GameSubscription();
            // $client_game_subscription = $subscription->check($client_details->client_id, 35, $request_params['gamecode']);

            // if(!$client_game_subscription) {
            //     header("Content-type: text/xml; charset=utf-8");
                /*$response = '<?xml version="1.0" encoding="utf-8"?>';*/
                // $response .= '<RequestResponse><error>135</error></RequestResponse>';
            // }
            // else
            // {
                $bet_transaction = GameTransactionMDB::findGameTransactionDetails($round_id,'round_id', 1, $client_details);
                $win_or_lost = $pay_amount > 0 ?  1 : 0;
                $entry_id = $pay_amount > 0 ?  2 : 1;
                $income = $bet_transaction->bet_amount - $pay_amount;
                $game_details = Game::find($game_code, config("providerlinks.simpleplay.PROVIDER_ID"));
                $client_details->connection_name = $bet_transaction->connection_name;
                
                $winbBalance = $client_details->balance + $pay_amount;
                ProviderHelper::_insertOrUpdate($client_details->token_id, $winbBalance); 

                header("Content-type: text/xml; charset=utf-8");
                $response = '<?xml version="1.0" encoding="utf-8"?>';
                $response .= '<RequestResponse><username>'.$request_params['username'].'</username><currency>'.$player_currency.'</currency><amount>'.$winbBalance.'</amount><error>0</error></RequestResponse>';

                $updateGameTransaction = [
                      'win' => $win_or_lost,
                      'pay_amount' => $pay_amount,
                      'income' => $income,
                      'entry_id' => $entry_id,
                      'trans_status' => 2
                  ];
              GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
              $gameTransactionEXTData = array(
                      "game_trans_id" => json_encode($bet_transaction->game_trans_id),
                      "provider_trans_id" => $transaction_id,
                      "round_id" => $round_id,
                      "amount" => $pay_amount,
                      "game_transaction_type"=> 2,
                      "provider_request" => json_encode($request_params),
                      "mw_response" => json_encode($response),
                  );
              $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

                $action_payload = [
                            "type" => "custom", #genreral,custom :D # REQUIRED!
                            "custom" => [
                                "provider" => 'SimplePlay',
                                "client_connection_name" => $client_details->connection_name,
                                "win_or_lost" => $win_or_lost,
                                "entry_id" => $entry_id,
                                "pay_amount" => $pay_amount,
                                "income" => $income,
                                "game_trans_ext_id" => $game_trans_ext_id
                            ],
                            "provider" => [
                                "provider_request" => $request_params, #R
                                "provider_trans_id"=> $transaction_id, #R
                                "provider_round_id"=> $round_id, #R
                            ],
                            "mwapi" => [
                                "roundId"=>$round_id, #R
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
                  
            $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$pay_amount,$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',false,$action_payload);

            $updateTransactionEXt = array(
                  "provider_request" =>json_encode($request_params),
                  "mw_response" => json_encode($response),
                  'mw_request' => json_encode($client_response->requestoclient),
                  'client_response' => json_encode($client_response->fundtransferresponse),
                  'transaction_detail' => 'success',
                  'general_details' => 'success',
            );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);

            // }
        }
        
        Helper::saveLog('simpleplay_credit', 35, json_encode($request_params), $response);
        echo $response;

    }

    public function lostTransaction(Request $request) 
    {
        $string = file_get_contents("php://input");
        $decrypted_string = $this->decrypt(urldecode($string));
        $query = parse_url('http://test.url?'.$decrypted_string, PHP_URL_QUERY);
        parse_str($query, $request_params);

        $client_code = RouteParam::get($request, 'brand_code');

        $playerId = $request_params['username'];
        $pay_amount = 0;
        $transaction_id = $request_params['txnid'];
        $game_code = $request_params['gamecode'];
        $round_id = $request_params['gameid'];

        header("Content-type: text/xml; charset=utf-8");
        $response = '<?xml version="1.0" encoding="utf-8"?>';
        $response .= '<RequestResponse><error>1007</error></RequestResponse>';

        $client_details = ProviderHelper::getClientDetails('player_id', $playerId);

        if ($client_details != null) {
            try{
                ProviderHelper::idenpotencyTable($transaction_id);
            }catch(\Exception $e){
                header("Content-type: text/xml; charset=utf-8");
                    $response = '<?xml version="1.0" encoding="utf-8"?>';
                    $response .= '<RequestResponse><error>9999</error></RequestResponse>';
                return $response;
            }
                $player_currency = $client_details->default_currency;
            
            header("Content-type: text/xml; charset=utf-8");
                $response = '<?xml version="1.0" encoding="utf-8"?>';
                $response .= '<RequestResponse><username>'.$request_params['username'].'</username><currency>'.$player_currency.'</currency><amount>'.$client_details->balance.'</amount><error>0</error></RequestResponse>';
            //GameRound::create($json_data['roundid'], $player_details->token_id);

            // Check if the game is available for the client
            // $subscription = new GameSubscription();
            // $client_game_subscription = $subscription->check($client_details->client_id, 35, $request_params['gamecode']);

            /*if(!$client_game_subscription) {
                header("Content-type: text/xml; charset=utf-8");
                $response = '<?xml version="1.0" encoding="utf-8"?>';
                 $response .= '<RequestResponse><error>135</error></RequestResponse>';
            }
            else
            {*/
                $bet_transaction = GameTransactionMDB::findGameTransactionDetails($round_id,'round_id', 1, $client_details);
                $win_or_lost = 0;
                $entry_id = 2;
                $income = $bet_transaction->bet_amount - $pay_amount;
                $game_details = Game::find($game_code, config("providerlinks.simpleplay.PROVIDER_ID"));
                $client_details->connection_name = $bet_transaction->connection_name;
                
                // $winbBalance = $client_details->balance + $pay_amount;
                // ProviderHelper::_insertOrUpdate($client_details->token_id, $winbBalance); 
                /*header("Content-type: text/xml; charset=utf-8");
                $response = '<?xml version="1.0" encoding="utf-8"?>';
                $response .= '<RequestResponse><username>'.$request_params['username'].'</username><currency>USD</currency><amount>'.$winbBalance.'</amount><error>0</error></RequestResponse>';*/

                $updateGameTransaction = [
                      'win' => $win_or_lost,
                      'pay_amount' => $pay_amount,
                      'income' => $income,
                      'entry_id' => $entry_id,
                      'trans_status' => 2
                  ];
              GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
              $gameTransactionEXTData = array(
                      "game_trans_id" => $bet_transaction->game_trans_id,
                      "provider_trans_id" => $transaction_id,
                      "round_id" => $round_id,
                      "amount" => $pay_amount,
                      "game_transaction_type"=> 2,
                      "provider_request" => json_encode($request_params),
                      "mw_response" => json_encode($response),
                      'mw_request' => 'Lose Round',
                      'client_response' => 'Lose Round',
                      'transaction_detail' => 'success',
                      'general_details' => 'success',
                  );
              $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

            //   $action_payload = [
            //                 "type" => "custom", #genreral,custom :D # REQUIRED!
            //                 "custom" => [
            //                     "provider" => 'SimplePlay',
            //                     "client_connection_name" => $client_details->connection_name,
            //                     "win_or_lost" => $win_or_lost,
            //                     "entry_id" => $entry_id,
            //                     "pay_amount" => $pay_amount,
            //                     "income" => $income,
            //                     "game_trans_ext_id" => $game_trans_ext_id
            //                 ],
            //                 "provider" => [
            //                     "provider_request" => $request_params, #R
            //                     "provider_trans_id"=> $transaction_id, #R
            //                     "provider_round_id"=> $round_id, #R
            //                 ],
            //                 "mwapi" => [
            //                     "roundId"=>$round_id, #R
            //                     "type"=>2, #R
            //                     "game_id" => $game_details->game_id, #R
            //                     "player_id" => $client_details->player_id, #R
            //                     "mw_response" => $response, #R
            //                 ],
            //                 'fundtransferrequest' => [
            //                     'fundinfo' => [
            //                         'freespin' => false,
            //                     ]
            //                 ]
            //             ];
                  
            // $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$pay_amount,$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',false,$action_payload);

            // $updateTransactionEXt = array(
            //       "provider_request" =>json_encode($request_params),
            //       "mw_response" => json_encode($response),
            //       'mw_request' => json_encode($client_response->requestoclient),
            //       'client_response' => json_encode($client_response->fundtransferresponse),
            //       'transaction_detail' => 'success',
            //       'general_details' => 'success',
            // );
            // GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
        // }
        }
        
        Helper::saveLog('simpleplay_lost', 35, json_encode($request_params), $response);
        echo $response;
    }

    public function rollBackTransaction(Request $request) 
    {
        $string = file_get_contents("php://input");
        $decrypted_string = $this->decrypt(urldecode($string));
        $query = parse_url('http://test.url?'.$decrypted_string, PHP_URL_QUERY);
        parse_str($query, $request_params);

        $client_code = RouteParam::get($request, 'brand_code');

        $playerId = $request_params['username'];
        $rollback_amount = $request_params['amount'];
        $transaction_id = $request_params['txnid'];
        $game_code = $request_params['gamecode'];
        $round_id = $request_params['gameid'];

        header("Content-type: text/xml; charset=utf-8");
        $response = '<?xml version="1.0" encoding="utf-8"?>';
        $response .= '<RequestResponse><error>1007</error></RequestResponse>';

        $client_details = ProviderHelper::getClientDetails('player_id', $playerId);


        if ($client_details != null) {
            try{
                ProviderHelper::idenpotencyTable($transaction_id);
            }catch(\Exception $e){
                header("Content-type: text/xml; charset=utf-8");
                    $response = '<?xml version="1.0" encoding="utf-8"?>';
                    $response .= '<RequestResponse><error>9999</error></RequestResponse>';
                return $response;
            }
            
                $player_currency = $client_details->default_currency;
           
            // Check if the transaction exist
            // $game_transaction = GameTransaction::find($request_params['txn_reverse_id']);
            $bet_transaction = GameTransactionMDB::findGameTransactionDetails($request_params['txn_reverse_id'],'transaction_id', 1, $client_details);

            // If transaction is not found
            if($bet_transaction == 'false') {
                header("Content-type: text/xml; charset=utf-8");
                $response = '<?xml version="1.0" encoding="utf-8"?>';
                $response .= '<RequestResponse><error>1007</error></RequestResponse>';
            }
            else
            {   
                $balance = $client_details->balance + $rollback_amount;
                ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);

                header("Content-type: text/xml; charset=utf-8");
                $response = '<?xml version="1.0" encoding="utf-8"?>';
                $response .= '<RequestResponse><username>'.$request_params['username'].'</username><currency>'.$player_currency.'</currency><amount>'.$balance.'</amount><error>0</error></RequestResponse>';
                // If transaction is found, send request to the client
                // $json_data['roundid'] = 'N/A';
                // $json_data['transid'] = $request_params['txnid'];
                // $json_data['income'] = 0;
                
                $game_details = Game::find($game_code, config("providerlinks.simpleplay.PROVIDER_ID"));
                $win_or_lost = 4;
                $entry_id = 2;
                $income = 0 ;

                $updateGameTransaction = [
                    'win' => 4,
                    "pay_amount" => $rollback_amount,
                    'income' => $income,
                    'entry_id' => $entry_id,
                    'trans_status' => 3
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);

                $gameTransactionEXTData = array(
                      "game_trans_id" => json_encode($bet_transaction->game_trans_id),
                      "provider_trans_id" => $transaction_id,
                      "round_id" => $round_id,
                      "amount" => $rollback_amount,
                      "game_transaction_type"=> 3,
                      "provider_request" => json_encode($request_params),
                      "mw_response" => json_encode($response),
                  );
                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

                 $action_payload = [
                            "type" => "custom", #genreral,custom :D # REQUIRED!
                            "custom" => [
                                "provider" => 'SimplePlay',
                                "client_connection_name" => $client_details->connection_name,
                                "win_or_lost" => $win_or_lost,
                                "entry_id" => $entry_id,
                                "pay_amount" => $rollback_amount,
                                "income" => $income,
                                "game_trans_ext_id" => $game_trans_ext_id
                            ],
                            "provider" => [
                                "provider_request" => $request_params, #R
                                "provider_trans_id"=> $transaction_id, #R
                                "provider_round_id"=> $round_id, #R
                            ],
                            "mwapi" => [
                                "roundId"=>$round_id, #R
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
                $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$rollback_amount,$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'refund',false,$action_payload);

                $updateTransactionEXt = array(
                      "provider_request" =>json_encode($request_params),
                      "mw_response" => json_encode($response),
                      'mw_request' => json_encode($client_response->requestoclient),
                      'client_response' => json_encode($client_response->fundtransferresponse),
                      'transaction_detail' => 'success',
                      'general_details' => 'success',
                );
                GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
            }
            
        }
        

        Helper::saveLog('simpleplay_rollback', 35, json_encode($request_params), $response);
        echo $response;

    }

    private function _getClientDetails($type = "", $value = "") {
        $query = DB::table("clients AS c")
                 ->select('p.client_id', 'p.player_id', 'p.client_player_id', 'p.username', 'p.email', 'p.language', 'c.default_currency', 'c.default_currency AS currency', 'pst.token_id', 'pst.player_token' , 'pst.status_id', 'p.display_name', 'c.client_api_key', 'cat.client_token AS client_access_token', 'ce.player_details_url', 'ce.fund_transfer_url')
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

                if ($type == 'username') {
                    $query->where([
                        ["p.username", "=", $value],
                        ["pst.status_id", "=", 1]
                    ]);
                }

                 $result= $query->first();

        return $result;

    }

    private function encrypt($str) {
        return base64_encode( openssl_encrypt($str, 'DES-CBC', $this->key, OPENSSL_RAW_DATA, $this->iv  ) );
    }
    private function decrypt($str) {
        $str = openssl_decrypt(base64_decode($str), 'DES-CBC', $this->key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $this->iv);
        return rtrim($str, "\x01..\x1F");
    }
    private function pkcs5Pad($text, $blocksize) {
        $pad = $blocksize - (strlen ( $text ) % $blocksize);
        return $text . str_repeat ( chr ( $pad ), $pad );
    }

    private function sendResponse($content) {

        $response = '<?xml version="1.0" encoding="utf-8"?>';
        $response .= '<response><status>'.$type.'</status>';

                $response = $response.'<remarks>'.$cause.'</remarks></response>';
                return $response;
     }

}
