<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransaction;
use App\Models\GameTransactionMDB;
use App\Helpers\FreeSpinHelper;
use App\Helpers\Game;
use Carbon\Carbon;
use DB;

class QTechController extends Controller
{
   public function verifySession(Request $request, $id){
        Helper::saveLog('QtechSession', 144, json_encode($request->all()),  "HIT_id:". $id );
        $walletSessionId = $request->header('Wallet-Session');
        $passKey = $request->header('Pass-Key');
        if($passKey != config('providerlinks.qtech.pass_key')){
            $response = [
                "code" => "LOGIN_FAILED",
                "message" => "The given pass-key is incorrect."
            ];
            return response($response,401)
                        ->header('Content-Type', 'application/json');
        }
        if(!ProviderHelper::getClientDetails('token',$walletSessionId)){
            $response = [
                "code" => "INVALID_TOKEN",
                "message" => "Missing, invalid or expired player (wallet) session token."
            ];
            return response($response,400)
                        ->header('Content-Type', 'application/json');
        }
        $client_details = ProviderHelper::getClientDetails('player_id',$id);
        if(!$client_details){
            $response = [
                "code" => "LOGIN_FAILED",
                "message" => "The given pass-key is incorrect."
            ];
            return response($response,401)
                        ->header('Content-Type', 'application/json');
        }
        $balance = str_replace(',', '', number_format($client_details->balance, 2));
        $response = [
            "balance" => (float) $balance,
            "currency" => $client_details->default_currency
        ];
        return response($response,200)
                        ->header('Content-Type', 'application/json');
    }

    public function getBalance(Request $request, $id){
        Helper::saveLog('QtechBalance', 144, json_encode($request->all()),  "HIT_id:". $id );
        $walletSessionId = $request->header('Wallet-Session');
        $passKey = $request->header('Pass-Key');
        if($passKey != config('providerlinks.qtech.pass_key')){
            $response = [
                "code" => "LOGIN_FAILED",
                "message" => "The given pass-key is incorrect."
            ];
            return response($response,401)
                        ->header('Content-Type', 'application/json');
        }
        $client_details = ProviderHelper::getClientDetails('player_id',$id);
        if(!$client_details){
            $response = [
                "code" => "LOGIN_FAILED",
                "message" => "The given pass-key is incorrect."
            ];
            return response($response,401)
                        ->header('Content-Type', 'application/json');
        }
        $balance = str_replace(',', '', number_format($client_details->balance, 2));
        $response = [
            "balance" => (float) $balance,
            "currency" => $client_details->default_currency
        ];
        return response($response,200)
                        ->header('Content-Type', 'application/json');
    }

    public function transactions(Request $request){
        Helper::saveLog('QtechTransactions', 144, json_encode($request->all()), json_encode($request->txnType));
        $walletSessionId = $request->header('Wallet-Session');
        $passKey = $request->header('Pass-Key');   
        if($passKey != config('providerlinks.qtech.pass_key')){
            $response = [
                "code" => "LOGIN_FAILED",
                "message" => "The given pass-key is incorrect."
            ];
            return response($response,401)
                        ->header('Content-Type', 'application/json');
        }  

        $client_details = ProviderHelper::getClientDetails('player_id',$request->playerId);
        if(!$client_details){
            $response = [
                "code" => "LOGIN_FAILED",
                "message" => "The given pass-key is incorrect."
            ];
            return response($response,401)
                        ->header('Content-Type', 'application/json');
        }
        try {
            ProviderHelper::idenpotencyTable("QTech-Transactions".$request->txnId);
        } catch (\Exception $e) {
            $bet_transaction = GameTransactionMDB::findGameExtOrderByTransType($request->txnId,false,'transaction_id',$client_details);
            $balance = str_replace(',', '', number_format($client_details->balance, 2));
            if($bet_transaction != 'false'){
                if($bet_transaction->game_transaction_type == 2 || $bet_transaction->game_transaction_type == '2'){
                    $response = [
                        "balance" => (float) $balance,
                        "referenceId" => (string)$bet_transaction->game_trans_id,
                    ];
                    return response($response,200)
                        ->header('Content-Type', 'application/json');
                }else{
                    if($bet_transaction->transaction_detail == '"FAILED"' || $bet_transaction->transaction_detail == "FAILED" ){
                        $response = [
                          "code" => "INSUFFICIENT_FUNDS",
                          "message" =>"Not enough funds for the debit operation"
                        ];
                        return response($response,400)
                        ->header('Content-Type', 'application/json');
                    }
                    $response = [
                        "balance" => (float) $balance,
                        "referenceId" => (string)$bet_transaction->game_trans_id,
                    ];
                    return response($response,200)
                        ->header('Content-Type', 'application/json');
                }
                
            }else{
                $response = [
                    "balance" => (float) $balance,
                    "referenceId" => (string)$bet_transaction->game_trans_id,
                ];
                return response($response,200)
                        ->header('Content-Type', 'application/json');
            }
            
        }
        if($request->txnType == "DEBIT"){
            if(!ProviderHelper::getClientDetails('token',$walletSessionId)){
                $response = [
                    "code" => "INVALID_TOKEN",
                    "message" => "Missing, invalid or expired player (wallet) session token."
                ];
                return response($response,400)
                            ->header('Content-Type', 'application/json');
            } 
            if($request->amount > $client_details->balance){
                $response = [
                  "code" => "INSUFFICIENT_FUNDS",
                  "message" =>"Not enough funds for the debit operation"
                ];
                return response($response,400)
                            ->header('Content-Type', 'application/json');
            }
            return $this->debitProcess($request->all(),$client_details);
        }elseif($request->txnType == "CREDIT"){
            return $this->creditProcess($request->all(),$client_details);
        }
        
    }
    public function debitProcess($request,$client_details){
        $transaction_id = $request['txnId'];
        $round_id = $request['roundId'];
        $bet_amount = $request['amount'];
        $game_code = $request['gameId'];
        $game_details = ProviderHelper::findGameDetails('game_code',config('providerlinks.qtech.provider_db_id'), $game_code);
        $bet_transaction = GameTransactionMDB::findGameTransactionDetails($round_id, 'round_id',1, $client_details);

        if($bet_transaction != 'false'){
            $client_details->connection_name = $bet_transaction->connection_name;
            $amount = $bet_transaction->bet_amount + $bet_amount;
            $updateGameTransaction = [
                'bet_amount' => $amount,
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
            $game_transaction_id = $bet_transaction->game_trans_id;
        }else{
            $gameTransactionData = array(
                  "provider_trans_id" => $transaction_id,
                  "token_id" => $client_details->token_id,
                  "game_id" => $game_details->game_id,
                  "round_id" => $round_id,
                  "bet_amount" => $bet_amount,
                  "win" => 5,
                  "pay_amount" => 0,
                  "income" => 0,
                  "entry_id" => 1,
            );
            $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
        }//end bet transaction swf_oncondition(transition)
        if(isset($request['FREE_ROUND'])){
            $transaction_detail = "FREE_ROUND";
        }else{
            $transaction_detail = "SUCCESS";
        }
        $gameTransactionEXTData = array(
              "game_trans_id" => $game_transaction_id,
              "provider_trans_id" => $transaction_id,
              "round_id" => $round_id,
              "amount" => $bet_amount,
              "game_transaction_type"=> 1,
              "provider_request" =>json_encode($request),
        );
        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
        $fund_extra_data = [
            'provider_name' => $game_details->provider_name
        ];
        try {
            $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount,$game_details->game_code,$game_details->game_name, $game_trans_ext_id,$game_transaction_id, 'debit',false,$fund_extra_data);
        } catch (\Exception $e) {
            $updateGameTransaction = [
                  'win' => 2,
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_transaction_id, $client_details);
            $updateTransactionEXt = array(
                "provider_request" =>json_encode($request),
                'transaction_detail' => 'failed',
                'general_details' => 'failed',
            );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
            $response = [
              "code" => "INSUFFICIENT_FUNDS",
              "message" =>"Not enough funds for the debit operation"
            ];
            return response($response,400)
                        ->header('Content-Type', 'application/json');
        }
        if (isset($client_response->fundtransferresponse->status->code)) {
          ProviderHelper::_insertOrUpdateCache($client_details->token_id, $client_response->fundtransferresponse->balance);
          switch ($client_response->fundtransferresponse->status->code) {
                case '200':
                    $http_status = 200;
                    $balance = str_replace(',', '', number_format($client_response->fundtransferresponse->balance, 2));
                    $response = [
                            "balance" => (float) $balance,
                            "referenceId" => (string)$game_transaction_id
                    ];
                    $updateTransactionEXt = array(
                        "provider_request" =>json_encode($request),
                        "mw_response" => json_encode($response),
                        'mw_request' => json_encode($client_response->requestoclient),
                        'client_response' => json_encode($client_response->fundtransferresponse),
                        'transaction_detail' => $transaction_detail,
                        'general_details' => 'success',
                    );
                   GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                break;
                case '402':
                    $http_status = 400;
                    $updateGameTransaction = [
                          'win' => 2,
                    ];
                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_transaction_id, $client_details);
                    $response = [
                      "code" => "INSUFFICIENT_FUNDS",
                      "message" =>"Not enough funds for the debit operation"
                    ];

                    $updateTransactionEXt = array(
                        "provider_request" =>json_encode($request),
                        "mw_response" => json_encode($response),
                        'mw_request' => json_encode($client_response->requestoclient),
                        'client_response' => json_encode($client_response->fundtransferresponse),
                        'transaction_detail' => 'failed',
                        'general_details' => 'failed',
                    );
                    GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                break;
                default:
                    $http_status = 400;
                    $updateGameTransaction = [
                        'win' => 2,
                    ];
                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_transaction_id, $client_details);
                    $response = [
                      "code" => "INSUFFICIENT_FUNDS",
                      "message" =>"Not enough funds for the debit operation"
                    ];
                    $updateTransactionEXt = array(
                        "provider_request" =>json_encode($request),
                        "mw_response" => json_encode($response),
                        'mw_request' => json_encode($client_response->requestoclient),
                        'client_response' => json_encode($client_response->fundtransferresponse),
                        'transaction_detail' => 'failed',
                        'general_details' => 'failed',
                    );
                    GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
          }// End Switch
      }// End Fundtransfer
      return response($response,$http_status)
                        ->header('Content-Type', 'application/json');
    }

    public function creditProcess($request,$client_details){
        $transaction_id = $request['txnId'];
        $round_id = $request['roundId'];
        $pay_amount = $request['amount'];
        $game_code = $request['gameId'];
        $game_details = ProviderHelper::findGameDetails('game_code',config('providerlinks.qtech.provider_db_id'), $game_code);
        $bet_transaction = GameTransactionMDB::findGameTransactionDetails($round_id, 'round_id',false, $client_details);
        $game_trans_id = $bet_transaction->game_trans_id;
        $winBalance = $client_details->balance + $pay_amount;
        $win_or_lost = $pay_amount > 0 ?  1 : 0;
        $entry_id = $pay_amount > 0 ?  2 : 1;
        $income = $bet_transaction->bet_amount - $pay_amount;
        if(isset($request['FREE_ROUND'])){
            $transaction_detail = "FREE_ROUND";
        }else{
            $transaction_detail = "SUCCESS";
        }
        $balance = str_replace(',', '', number_format($winBalance, 2));
        $response = [
            "balance" => (float) $balance,
            "referenceId" => (string) $game_trans_id,
        ];

        $updateGameTransaction = [
          'win' => 5,
          'pay_amount' => $pay_amount,
          'income' => $income,
          'entry_id' => $entry_id,
        ];
        GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
        $gameExtensionData = [
            "game_trans_id" => $game_trans_id,
            "provider_trans_id" => $transaction_id,
            "round_id" => $round_id,
            "amount" => $pay_amount,
            "game_transaction_type" => 2,
            "provider_request" => json_encode($request),
        ];
        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameExtensionData,$client_details);
        ProviderHelper::_insertOrUpdate($client_details->token_id, $winBalance);
        
        $action_payload = [
              "type" => "custom", #genreral,custom :D # REQUIRED!
              "custom" => [
                  "provider" => 'qtech',
                  "client_connection_name" => $client_details->connection_name,
                  "win_or_lost" => $win_or_lost,
                  "entry_id" => $entry_id,
                  "pay_amount" => $pay_amount,
                  "income" => $income,
                  "game_trans_ext_id" => $game_trans_ext_id
              ],
              "provider" => [
                  "provider_request" => json_encode($request), #R
                  "provider_trans_id"=> $transaction_id, #R
                  "provider_round_id"=> $round_id, #R
              ],
              "mwapi" => [
                  "roundId"=>$game_trans_id, #R
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
        try {
            $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$pay_amount,$game_details->game_code,$game_details->game_name,$game_trans_id,'credit',false,$action_payload);
        } catch (\Exception $e) {
            $updateTransactionEXt = array(
                  "provider_request" =>json_encode($request),
                  'transaction_detail' => 'failed',
                  'general_details' => 'failed',
            );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
        }
        if(isset($client_response->fundtransferresponse->status->code) 
        && $client_response->fundtransferresponse->status->code == "200"){
            $updateTransactionEXt = array(
                  "provider_request" =>json_encode($request),
                  "mw_response" => json_encode($response),
                  'mw_request' => json_encode($client_response->requestoclient),
                  'client_response' => json_encode($client_response->fundtransferresponse),
                  'transaction_detail' => $transaction_detail,
                  'general_details' => 'success',
            );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
            return response($response,200)
                        ->header('Content-Type', 'application/json');
        }elseif (isset($client_response->fundtransferresponse->status->code) 
        && $client_response->fundtransferresponse->status->code == "402") {
          $response = [
                "code"=>"REQUEST_DECLINED",
                "message" => "General error. If request could not be processed."
          ];
          $updateTransactionEXt = array(
                "provider_request" =>json_encode($request),
                "mw_response" => json_encode($response),
                'mw_request' => json_encode($client_response->requestoclient),
                'client_response' => json_encode($client_response->fundtransferresponse),
                'transaction_detail' => 'FAILED',
                'general_details' => 'FAILED',
          );
          GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
          return response($response,400)
                        ->header('Content-Type', 'application/json');
        }else{
              $response = [
                "code"=>"REQUEST_DECLINED",
                "message" => "General error. If request could not be processed."
              ];
              $updateTransactionEXt = array(
                    "provider_request" =>json_encode($request),
                    "mw_response" => json_encode($response),
                    'mw_request' => json_encode($client_response->requestoclient),
                    'client_response' => json_encode($client_response->fundtransferresponse),
                    'transaction_detail' => 'FAILED',
                    'general_details' => 'FAILED',
              );
              GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
              return response($response,400)
                        ->header('Content-Type', 'application/json');
        }
    }  
    public function rollback(Request $request){
        Helper::saveLog('QtechTransactions rollback', 144, json_encode($request), json_encode($request['txnType']));
        $walletSessionId = $request->header('Wallet-Session');
        $passKey = $request->header('Pass-Key');   
        if($passKey != config('providerlinks.qtech.pass_key')){
            $response = [
                "code" => "LOGIN_FAILED",
                "message" => "The given pass-key is incorrect."
            ];
            return response($response,401)
                        ->header('Content-Type', 'application/json');
        }     
        $client_details = ProviderHelper::getClientDetails('player_id',$request->playerId);
        if(!$client_details){
            $response = [
                "code" => "LOGIN_FAILED",
                "message" => "The given pass-key is incorrect."
            ];
            return response($response,401)
                        ->header('Content-Type', 'application/json');
        }
        try {
            ProviderHelper::idenpotencyTable("QTech-Rollback".$request->txnId);
        } catch (\Exception $e) {
            $bet_transaction = GameTransactionMDB::findGameExt($request->txnId,3,'transaction_id',$client_details);
            $balance = str_replace(',', '', number_format($client_details->balance, 2));
            if($bet_transaction != 'false'){
                if($bet_transaction->transaction_detail == '"FAILED"' || $bet_transaction->transaction_detail == "FAILED" ){
                    $response = [
                      "code" => "REQUEST_DECLINED",
                      "message" =>"General error. Duplicate Transaction."
                    ];
                    return response($response,400)
                        ->header('Content-Type', 'application/json');
                }
                $response = [
                    "balance" => (float) $balance,
                    "referenceId" => (string) $bet_transaction->game_trans_id,
                ];
                return response($response,200)
                        ->header('Content-Type', 'application/json');
            }else{
                $bet_transaction = GameTransactionMDB::findGameExt($request->txnId,1,'transaction_id',$client_details);
                $response = [
                    "balance" => (float) $balance,
                    "referenceId" => (string) $bet_transaction->game_trans_id,
                ];
                return response($response,200)
                        ->header('Content-Type', 'application/json');
            }
            
        }
        $transaction_id = $request->txnId;
        $round_id = $request->roundId;
        $pay_amount = $request->amount;
        $game_code = $request->gameId;
        $game_details = ProviderHelper::findGameDetails('game_code',config('providerlinks.qtech.provider_db_id'), $game_code);
        $bet_transaction = GameTransactionMDB::findGameTransactionDetails($round_id, 'round_id',1, $client_details);
        if($bet_transaction == 'false'){
            $balance = str_replace(',', '', number_format($client_details->balance, 2));
            $response = [
                "balance" => (float) $balance,
            ];
            return response($response,200)
                    ->header('Content-Type', 'application/json');
        }
        $game_trans_id = $bet_transaction->game_trans_id;
        $winBalance = $client_details->balance + $pay_amount;
        $win_or_lost = $pay_amount > 0 ?  1 : 0;
        $entry_id = $pay_amount > 0 ?  2 : 1;
        $income = $bet_transaction->bet_amount - $pay_amount;

        $updateGameTransaction = [
          'win' => 4,
          'pay_amount' => $pay_amount,
          'income' => $income,
          'entry_id' => 3,
        ];
        GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
        $gameExtensionData = [
            "game_trans_id" => $game_trans_id,
            "provider_trans_id" => $transaction_id,
            "round_id" => $round_id,
            "amount" => $pay_amount,
            "game_transaction_type" => 3,
            "provider_request" => json_encode($request),
        ];
        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameExtensionData,$client_details);

        $fund_extra_data = [
            'provider_name' => $game_details->provider_name
        ];
        $client_response = ClientRequestHelper::fundTransfer($client_details,$pay_amount,$game_details->game_code,$game_details->game_name,$game_trans_ext_id,$game_trans_id,"credit",true,$fund_extra_data);
        if(isset($client_response->fundtransferresponse->status->code) 
        && $client_response->fundtransferresponse->status->code == "200"){
            ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
            $balance = str_replace(',', '', number_format($client_response->fundtransferresponse->balance, 2));
            $response = [
                "balance" => (float) $balance,
                "referenceId" => (string) $game_trans_id,
            ];
            // Helper::updateBNGGameTransactionExt($transactionId,$client_response->requestoclient,$response,$client_response);
            $dataToUpdate = array(
                "mw_response" => json_encode($response),
                "client_response" => json_encode($client_response),
                "mw_request" => json_encode($client_response->requestoclient),
                "transaction_detail" => 'SUCCESS',
            );
            GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$game_trans_ext_id,$client_details);
            return response($response,200)
                ->header('Content-Type', 'application/json');
        }
    }

    public function bonusRewards(Request $request){
        Helper::saveLog('QtechTransactions rollback', 144, json_encode($request->all()), "HIT");
        $walletSessionId = $request->header('Wallet-Session');
        $passKey = $request->header('Pass-Key'); 
        if($passKey != config('providerlinks.qtech.pass_key')){
            $response = [
                "code" => "LOGIN_FAILED",
                "message" => "The given pass-key is incorrect."
            ];
            return response($response,401)
                        ->header('Content-Type', 'application/json');
        }
        if($request->has('rewardType')){
                    
               
                $client_details = ProviderHelper::getClientDetails('player_id',$request->playerId);
                try {
                    ProviderHelper::idenpotencyTable("QTech-Rewards".$request->txnId);
                } catch (\Exception $e) {
                    $bet_transaction = GameTransactionMDB::findGameExt($request->txnId,false,'transaction_id',$client_details);
                    $balance = str_replace(',', '', number_format($client_details->balance, 2));
                    $response = [
                        "balance" => (float) $balance,
                        "referenceId" => (string) $bet_transaction->game_trans_id,
                    ];
                   return response($response,200)
                                ->header('Content-Type', 'application/json');
                }
                $balance = $client_details->balance + $request->amount;
                $gameTransactionData = array(
                      "provider_trans_id" => $request->txnId,
                      "token_id" => $client_details->token_id,
                      "game_id" => 0,
                      "round_id" => 0,
                      "bet_amount" => 0,
                      "win" => 2,
                      "pay_amount" => $request->amount,
                      "income" => 0,
                      "entry_id" => 1,
                );
                $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
                $gameExtensionData = [
                    "game_trans_id" => $game_transaction_id,
                    "provider_trans_id" => $request->txnId,
                    "round_id" => 0,
                    "amount" => $request->amount,
                    "game_transaction_type" => 2,
                    "provider_request" => json_encode($request->all()),
                ];
                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameExtensionData,$client_details);
                $response = [
                    "balance" => (float)$balance,
                    "referenceId" => (string) $game_transaction_id
                ];
                ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
                $action_payload = [
                      "type" => "custom", #genreral,custom :D # REQUIRED!
                      "custom" => [
                          "provider" => 'qtech',
                          "client_connection_name" => $client_details->connection_name,
                          "win_or_lost" => 2,
                          "entry_id" => 2,
                          "pay_amount" => $request->amount,
                          "income" => 0,
                          "game_trans_ext_id" => $game_trans_ext_id
                      ],
                      "provider" => [
                          "provider_request" => json_encode($request->all()), #R
                          "provider_trans_id"=> $request->txnId, #R
                          "provider_round_id"=> 0, #R
                      ],
                      "mwapi" => [
                          "roundId"=>$game_transaction_id, #R
                          "type"=>2, #R
                          "game_id" => 0, #R
                          "player_id" => $client_details->player_id, #R
                          "mw_response" => $response, #R
                      ],
                      'fundtransferrequest' => [
                          'fundinfo' => [
                              'freespin' => false,
                          ]
                      ]
                ];
                try {
                    $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$request->amount,0,$request->rewardTitle,$game_transaction_id,'credit',false,$action_payload);
                } catch (\Exception $e) {
                    $updateTransactionEXt = array(
                          "provider_request" =>json_encode($request->all()),
                          'transaction_detail' => 'failed',
                          'general_details' => 'failed',
                    );
                    GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                }
                if(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "200"){
                    $updateTransactionEXt = array(
                          "provider_request" =>json_encode($request->all()),
                          "mw_response" => json_encode($response),
                          'mw_request' => json_encode($client_response->requestoclient),
                          'client_response' => json_encode($client_response->fundtransferresponse),
                          'transaction_detail' => 'success',
                          'general_details' => 'success',
                    );
                    GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                    return response($response,200)
                                ->header('Content-Type', 'application/json');
                }elseif (isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "402") {
                  $response = [
                    "code"=>"REQUEST_DECLINED",
                    "message" => "General error. If request could not be processed."
                  ];
                  $updateTransactionEXt = array(
                        "provider_request" =>json_encode($request->all()),
                        "mw_response" => json_encode($response),
                        'mw_request' => json_encode($client_response->requestoclient),
                        'client_response' => json_encode($client_response->fundtransferresponse),
                        'transaction_detail' => 'FAILED',
                        'general_details' => 'FAILED',
                  );
                  GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                  return response($response,400)
                                ->header('Content-Type', 'application/json');
                }else{
                      $response = [
                        "code"=>"REQUEST_DECLINED",
                        "message" => "General error. If request could not be processed."
                      ];
                      $updateTransactionEXt = array(
                            "provider_request" =>json_encode($request->all()),
                            "mw_response" => json_encode($response),
                            'mw_request' => json_encode($client_response->requestoclient),
                            'client_response' => json_encode($client_response->fundtransferresponse),
                            'transaction_detail' => 'FAILED',
                            'general_details' => 'FAILED',
                      );
                      GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                      return response($response,400)
                                ->header('Content-Type', 'application/json');
                }
        }else{
           $response = [
                "code"=>"REQUEST_DECLINED",
                "message" => "General error. If request could not be processed."
            ];
            return response($response,400)
                        ->header('Content-Type', 'application/json'); 
        }
    }
}
