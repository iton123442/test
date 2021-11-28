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

class QuickspinDirectController extends Controller
{
    public function Authenticate(Request $req){
        Helper::saveLog('QuickSpinDirect verifyToken', 66, json_encode($req->all()),  "HIT" );
        $client_details = ProviderHelper::getClientDetails('token',$req['token']);
        if($client_details->country_code != null){
            $countryCode = $client_details->country_code;
        }else{
            $countryCode = "PH";
        }
        $formatBal = $balance = str_replace(".","", $client_details->balance);
        if($client_details != null){
            $res = [
                "customerid" => $client_details->player_id,
                "countrycode" => "PH",
                "cashiertoken" => $client_details->player_token,
                "customercurrency" => $client_details->default_currency,
                "balance" => (int)$formatBal,
                "jurisdiction" => $countryCode,
                "classification" => "vip",
                "playermessage" => [
                    "title" => "",
                    "message" => "",
                    "nonintrusive" => false
                ],
            ];
        }else{
            $res = [
                "errorcode" => "INVALID_TOKEN",
                "errormessage" => "authentication failed"
            ];
        }
        return $res;
    }
    public function getBalance(Request $req){
        // $client_details = ProviderHelper::getClientDetails('player_id', $req['customerid']);
        $client_details = ProviderHelper::getClientDetails('token', $req['cashiertoken']);
        $formatBal = $balance = str_replace(".","", $client_details->balance);
        if($client_details != null){
            $res = [
                "balance" => (int)$formatBal,
                "customercurrency" => $client_details->default_currency
            ];
        }else{
            $res = [
                "errorcode" => "UNHANDLED",
                "errormessage" => "internal server error"
            ];
        }
        return $res;
    }
    public function betProcess(Request $req){
        Helper::saveLog('QuickSpinDirect Bet', 66, json_encode($req->all()),  "HIT" );
        $provider_trans_id = $req['txid'];
        $round_id = $req['gamesessionid'];
        $game_code = $req['gameref'];
        $bet_amount = $req['amount']/100;
        $client_details = ProviderHelper::getClientDetails('player_id', $req['customerid']);
        if($client_details == null){
            $res = [
                "errorcode" => "UNHANDLED",
                "errormessage" => "internal server error"
            ];
            return $res;
        }
        $formatBal = $balance = str_replace(".","", $client_details->balance);
        try {
            ProviderHelper::idenpotencyTable($provider_trans_id);
        } catch (\Exception $e) {
            $getTransaction = GameTransactionMDB::findGameTransactionDetails($provider_trans_id, 'transaction_id',false, $client_details);
            $res = [
                "balance" => (int)$formatBal,
                "txid" => (int) $provider_trans_id,
                "remotetxid" => $getTransaction->game_trans_id,
            ];
            return $res;
        }

        $game_details = Game::find($game_code, config('providerlinks.quickspinDirect.provider_db_id'));
        $bet_transaction = GameTransactionMDB::findGameTransactionDetails($round_id, 'round_id',false, $client_details);

        if($bet_transaction != 'false'){
            $client_details->connection_name = $bet_transaction->connection_name;
            $amount = $bet_transaction->bet_amount + $bet_amount;
            $updateGameTransaction = [
                'win' => 5,
                'bet_amount' => $amount,
                'entry_id' => 1,
                'trans_status' => 1
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
            $game_transaction_id = $bet_transaction->game_trans_id;
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
            $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
            $gameTransactionEXTData = array(
                "game_trans_id" => $game_transaction_id,
                "provider_trans_id" => $provider_trans_id,
                "round_id" => $round_id,
                "amount" => $bet_amount,
                "game_transaction_type"=> 1,
                "provider_request" =>json_encode($req->all()),
            );
            $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details); 
            $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount, $game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, 'debit');
                    if(isset($client_response->fundtransferresponse->status->code)){
                        ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                        switch ($client_response->fundtransferresponse->status->code) {
                                case '200':
                                     $http_status = 200;
                                     $playerBal = sprintf('%.2f', $client_response->fundtransferresponse->balance);
                                     $formatBal = $balance = str_replace(".","", $playerBal);
                                     $res = [
                                        "balance" => (int)$formatBal,
                                        "txid" => (int)$provider_trans_id,
                                        "remotetxid" => (string)$game_transaction_id,
                                    ];

                                    $updateTransactionEXt = array(
                                        "provider_request" =>json_encode($req->all()),
                                        "mw_response" => json_encode($res),
                                        'mw_request' => json_encode($client_response->requestoclient),
                                        'client_response' => json_encode($client_response->fundtransferresponse),
                                        'transaction_detail' => 'success',
                                        'general_details' => 'success',
                                    
                                    );
                                    GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                                break;
                                case '402':
                                        // ProviderHelper::updateGameTransactionStatus($game_transaction_id, 2, 99);
                                    $http_status = 200;
                                    $res = [
                                        
                                         "errorcode" => "INSUFFICIENT_FUNDS",
                                         "errormessage" => "not enough funds for withdrawal"
                                      
                                    ];

                                    $updateTransactionEXt = array(
                                        "provider_request" =>json_encode($req->all()),
                                        "mw_response" => json_encode($res),
                                        'mw_request' => json_encode($client_response->requestoclient),
                                        'client_response' => json_encode($client_response->fundtransferresponse),
                                        'transaction_detail' => 'failed',
                                        'general_details' => 'failed',
                                    );
                                    GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                                break;
                        }
                    }
                    Helper::saveLog('QuickSpin Debit success', config("providerlinks.quickspinDirect.provider_db_id"), json_encode($req->all()), $res);
                    return response()->json($res, $http_status);

        }//end bet transaction condition
    }// end function bet process
    public function winProcess(Request $req){
        Helper::saveLog('QuickSpinDirect WIn', 66, json_encode($req->all()),  "HIT" );
        $provider_trans_id = $req['txid'];
        $round_id = $req['gamesessionid'];
        $game_code = $req['gameref'];
        $pay_amount = $req['amount']/100;
        $client_details = ProviderHelper::getClientDetails('player_id', $req['customerid']);
        if($client_details == null){
            $res = [
                "errorcode" => "UNHANDLED",
                "errormessage" => "internal server error"
            ];
            return $res;
        }
        $formatBal = $balance = str_replace(".","", $client_details->balance);
        $formattedBal = (int) $formatBal;
        $trans_type = $req['txtype'];//transaction type wether its free spin or normal
        try {
            ProviderHelper::idenpotencyTable($provider_trans_id);
        } catch (\Exception $e) {
            if($trans_type == 'freespinspayout'){
                $res = [
                    "balance" => (int)$formatBal,
                    "txid" => (int) $provider_trans_id,
                    "remotetxid" => $round_id,
                ];
            }else{
                $getTransaction = GameTransactionMDB::findGameTransactionDetails($provider_trans_id, 'transaction_id',false, $client_details);
                $res = [
                    "balance" => (int)$formatBal,
                    "txid" => (int) $provider_trans_id,
                    "remotetxid" => $getTransaction->game_trans_id,
                ];
            }
            return $res;
        }
        
        $game_details = Game::find($game_code, config('providerlinks.quickspinDirect.provider_db_id'));
        $bet_transaction = GameTransactionMDB::findGameTransactionDetails($round_id, 'round_id',false, $client_details);
        $winBalance = $formattedBal + $pay_amount;
        $win_or_lost = $pay_amount > 0 ?  1 : 0;
        $entry_id = $pay_amount > 0 ?  2 : 1;

        if(!$bet_transaction){
            $client_details->connection_name = $bet_transaction->connection_name;
            $income = $bet_transaction->bet_amount - $pay_amount;
            $res = [
                "balance" => (int) $winBalance,
                "txid" => (int) $provider_trans_id,
                "remotetxid" => (string)$bet_transaction->game_trans_id
            ];
        }else{
            $client_details->connection_name = $client_details->connection_name;
            $income = 0;
            $res = [
                "balance" => (int) $winBalance,
                "txid" => (int) $provider_trans_id,
                "remotetxid" => $round_id
            ];
        }
        
        if($trans_type == 'freespinspayout'){
            $gameTransactionData = array(
                "provider_trans_id" => $provider_trans_id,
                "token_id" => $client_details->token_id,
                "game_id" => $game_details->game_id,
                "round_id" => $round_id,
                "bet_amount" => 0,
                "win" => $win_or_lost,
                "pay_amount" => $pay_amount,
                "income" => 0,
                "entry_id" => 1,
            ); 
            $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
            $gameTransactionEXTData = array(
                "game_trans_id" => $game_transaction_id,
                "provider_trans_id" => $provider_trans_id,
                "round_id" => $round_id,
                "amount" => 0,
                "game_transaction_type"=> 1,
                "provider_request" =>json_encode($req->all()),
            );
            $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
            $game_trans_id = $game_transaction_id;
        }else{
            $updateGameTransaction = [
                  'win' => 5,
                  'pay_amount' => $pay_amount,
                  'income' => $income,
                  'entry_id' => $entry_id,
                  'trans_status' => 2
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
            $game_trans_id = $bet_transaction->game_trans_id;
        }
        $gameTransactionEXTData = array(
                  "game_trans_id" => json_encode($game_trans_id),
                  "provider_trans_id" => $provider_trans_id,
                  "round_id" => $round_id,
                  "amount" => $pay_amount,
                  "game_transaction_type"=> 2,
                  "provider_request" => json_encode($req->all()),
                  "mw_response" => json_encode($res),
              );
        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
        ProviderHelper::_insertOrUpdate($client_details->token_id, $winBalance/100); 

        $action_payload = [
            "type" => "custom", #genreral,custom :D # REQUIRED!
            "custom" => [
                "provider" => 'Quickspin',
                "client_connection_name" => $client_details->connection_name,
                "win_or_lost" => $win_or_lost,
                "entry_id" => $entry_id,
                "pay_amount" => $pay_amount,
                "income" => $income,
                "game_trans_ext_id" => $game_trans_ext_id
            ],
            "provider" => [
                "provider_request" => json_encode($req->all()), #R
                "provider_trans_id"=> $provider_trans_id, #R
                "provider_round_id"=> $round_id, #R
            ],
            "mwapi" => [
                "roundId"=>$game_trans_id, #R
                "type"=>2, #R
                "game_id" => $game_details->game_id, #R
                "player_id" => $client_details->player_id, #R
                "mw_response" => $res, #R
            ],
            'fundtransferrequest' => [
                'fundinfo' => [
                    'freespin' => false,
                ]
            ]
        ];
        $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$pay_amount,$game_details->game_code,$game_details->game_name,$game_trans_id,'credit',false,$action_payload);
        if($client_response != false){  

        $updateTransactionEXt = array(
              "provider_request" =>json_encode($req->all()),
              "mw_response" => json_encode($res),
              'mw_request' => json_encode($client_response->requestoclient),
              'client_response' => json_encode($client_response->fundtransferresponse),
              'transaction_detail' => 'Failed',
              'general_details' => 'Failed',
        );
        GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
      Helper::saveLog('QuickSpinD Win success', config('providerlinks.quickspinDirect.provider_db_id'), json_encode($req->all()), $res);
        return response($res,200)
                ->header('Content-Type', 'application/json');
    }else{
        $http_status = 500;
        $res = [
            "errorcode" => "UNHANDLED",
            "errormessage" => "internal server error"
        ];
        return response($res,$http_status)
                ->header('Content-Type', 'application/json');

      }

    }// end function win process
    public function rollbackProcess(Request $req){
        $client_details = ProviderHelper::getClientDetails('player_id', $req['customerid']);
        $formatBal = $balance = str_replace(".","", $client_details->balance);
        $formattedBal = (int) $formatBal;
        $provider_trans_id = $req['txid'];
        $bet_transaction_id = $req['originaltxid'];
        if($client_details == null){
            $res = [
                "errorcode" => "UNHANDLED",
                "errormessage" => "internal server error"
            ];
            return $res;
        }
        try {
            ProviderHelper::idenpotencyTable($provider_trans_id);
        } catch (\Exception $e) {
            $getTransaction = GameTransactionMDB::findGameTransactionDetails($provider_trans_id, 'transaction_id',false, $client_details);
            $res = [
                "balance" => (int)$formatBal,
                "txid" => (int) $provider_trans_id,
                "remotetxid" => $getTransaction->game_trans_id,
            ];
            return $res;
        }
        $bet_transaction = GameTransactionMDB::findGameTransactionDetails($bet_transaction_id, 'transaction_id',false, $client_details);
        if($bet_transaction == 'false'){
            $res = [
                "errorcode" => "TRANSACTION_DECLINED",
                "errormessage" => "transaction not found"
            ];
            return $res;
        }
        $game_details = Game::findbyid($bet_transaction->game_id);
        if($game_details == false){
            $res = [
                "errorcode" => "UNHANDLED",
                "errormessage" => "internal server error"
            ];
            return $res;
        }
        $client_details->connection_name = $bet_transaction->connection_name;
        $win_or_lost = 4;
        $entry_id = 2;
        $income = 0;
        $updateGameTransaction = [
            'win' => $win_or_lost,
            'pay_amount' => $bet_transaction->bet_amount,
            'income' => $income,
            'entry_id' => $entry_id,
            'trans_status' => 3
        ];
        GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
        $gameTransactionEXTData = array(
            "game_trans_id" => $bet_transaction->game_trans_id,
            "provider_trans_id" => $provider_trans_id,
            "round_id" => $bet_transaction_id,
            "amount" => $bet_transaction->bet_amount,
            "game_transaction_type"=> 3,
            "provider_request" =>json_encode($req->all()),
            "mw_response" => null,
        );
        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
        $client_response = ClientRequestHelper::fundTransfer($client_details, $bet_transaction->bet_amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $bet_transaction->game_trans_id, 'credit', "true");
        if (isset($client_response->fundtransferresponse->status->code)) {
                            
            switch ($client_response->fundtransferresponse->status->code) {
                case '200':
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                     $http_status = 200;
                     $playerBal = sprintf('%.2f', $client_response->fundtransferresponse->balance);
                     $formatBal = $balance = str_replace(".","", $playerBal);
                     $res = [
                        "balance" => (int)$formatBal,
                        "txid" => (int)$provider_trans_id,
                        "remotetxid" => (string)$bet_transaction->game_trans_id,
                    ];
            }

            $updateTransactionEXt = array(
                "provider_request" =>json_encode($req->all()),
                "mw_response" => json_encode($res),
                'mw_request' => json_encode($client_response->requestoclient),
                'client_response' => json_encode($client_response->fundtransferresponse),
                'transaction_detail' => 'success',
                'general_details' => 'success',
            );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);

        }
        Helper::saveLog('OnlyPlay', config("providerlinks.quickspinDirect.provider_db_id"), json_encode($req->all()),$res);
        return response($res,200)
                ->header('Content-Type', 'application/json');
    }
    public function freeRound($data){
        dd($data);

    }
}
