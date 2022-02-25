<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransactionExt;
use App\Models\GameTransactionMDB;
use Carbon\Carbon;
use DB;

class PGSoftController extends Controller
{
    //
    public $provider_db_id = 31;
    public $prefix = "PGSOFT_";
    public function __construct(){
        $this->operator_token = config('providerlinks.pgsoft.operator_token');
        $this->secret_key = config('providerlinks.pgsoft.secret_key');
        $this->api_url = config('providerlinks.pgsoft.api_url');
    }

    public function verifySession(Request $request){
        Helper::saveLog('PGSoft VerifySession', $this->provider_db_id, json_encode($request->all(), JSON_FORCE_OBJECT), 'ENDPOINT HIT');
        $data = $request->all();
        if($this->validateData($data) != 'false'){
            return $this->validateData($data);
        }
        $client_details = ProviderHelper::getClientDetails('token',$data["operator_player_session"]);
        $data =  [
            "data" => [
                "player_name" => $this->prefix.$client_details->player_id,
                "nickname" => $client_details->display_name,
                "currency" => $client_details->default_currency
            ],
            "error" => null
        ];
        Helper::saveLog('PGSoft VerifySession Process', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT), $data);
        return json_encode($data, JSON_FORCE_OBJECT); 
    }
    
    public function cashGet(Request $request){ // Wallet Check Balance Endpoint Hit
        Helper::saveLog('PGSoft CashGet', $this->provider_db_id, json_encode($request->all(), JSON_FORCE_OBJECT ), 'ENDPOINT HIT');
        $data = $request->all();
        if($this->validateData($data) != 'false'){
            return $this->validateData($data);
        }
        $player_id =  ProviderHelper::explodeUsername('_', $data["player_name"]);
        $player_name = ProviderHelper::getClientDetails('player_id',$player_id);
        $currency = $player_name->default_currency;
        $num = $player_name->balance;
        $balance = (double)$num;
        $response =  [
            "data" => [
                "currency_code" => $currency,
                "balance_amount" => $balance,
                "updated_time" => $this->getMilliseconds()
            ],
            "error" => null
        ];
        Helper::saveLog('PGSoft CashGet Process', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
        return json_encode($response, JSON_FORCE_OBJECT); 
    }

    public function transferOut(Request $request){
        Helper::saveLog('PGSoft Bet ', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT), 'ENDPOINT HIT');
        $data = $request->all();
        if(($request->has('is_validate_bet') && $data["is_validate_bet"] == 'False') && 
            ($request->has('is_adjustment') && $data["is_adjustment"] == 'False' )){
                if($this->validateData($data) != 'false'){
                    return $this->validateData($data);
                }
        }

        $player_id =  ProviderHelper::explodeUsername('_', $data["player_name"]);
        $client_details = ProviderHelper::getClientDetails('player_id',$player_id);
        
        try{
            ProviderHelper::idenpotencyTable('PGSOFT_'.$data['transaction_id']);
        }catch(\Exception $e){
            // $bet_transaction = Providerhelper::findGameExt($data['transaction_id'], 1, 'transaction_id');
            $bet_transaction = GameTransactionMDB::findGameExt($data['transaction_id'], 1,'transaction_id', $client_details);
            if ($bet_transaction != 'false') {
                //this will be trigger if error occur 10s
                Helper::saveLog('PGSoft BET duplicate_transaction success', $this->provider_db_id, json_encode($request->all()),  $bet_transaction->mw_response);
                return response($bet_transaction->mw_response,200)
                ->header('Content-Type', 'application/json');
            } 
            $response = array(
                'data' => null,
                'error' => [
                    'code'  => '3021',
                    'message'   => 'No bet exist.'
                ]
            );
            Helper::saveLog('PGSoft BET No bet exist', $this->provider_db_id, json_encode($request->all()),  $response);
            return response($response,200)->header('Content-Type', 'application/json');
        }

        try{
            
            
            // $player_details = Providerhelper::playerDetailsCall($client_details->player_token);
            $game_details = $this->findGameCode('game_code', $this->provider_db_id, $data['game_id']);


            //Initialize
            $game_transaction_type = 1; // 1 Bet, 2 Win
            $game_code = $game_details->game_id;
            $token_id = $client_details->token_id;
            $bet_amount = $data['transfer_amount'];
            $pay_amount = 0;
            $income = 0;
            $win_type = 0;
            $method = 1;
            $win_or_lost = 0; // 0 lost,  5 processing
            $payout_reason = 'Bet';
            $provider_trans_id = $data['transaction_id']; //uniquee

            // $bet_transaction = Providerhelper::findGameExt($data['parent_bet_id'], 1, 'round_id');
            // $bet_transaction = $this->findGameTransaction($data['parent_bet_id'], 'round_id');
            $bet_transaction = GameTransactionMDB::findGameTransactionDetails($data['parent_bet_id'],'round_id', false, $client_details);
            //if the bet ransaction not equal to fales this will be the freespin
            if ($bet_transaction != "false") {
                
                
                if ($data["transfer_amount"] == 0.00) {
                    $response =  [
                        "data" => [
                            "currency_code" => $client_details->default_currency,
                            "balance_amount" => floatval(number_format((float)$client_details->balance, 2, '.', '')),
                            "updated_time" => $data["updated_time"]
                        ],
                        "error" => null
                    ]; 

                    $gameTransactionEXTData = array(
                        "game_trans_id" => $bet_transaction->game_trans_id,
                        "provider_trans_id" => $provider_trans_id,
                        "round_id" => $data["parent_bet_id"],
                        "amount" => $bet_amount,
                        "game_transaction_type"=> $game_transaction_type,
                        "provider_request" =>json_encode($data),
                        "mw_response" => json_encode($response),
                        'mw_request' => "freespin",
                        'client_response' => "freespin",
                        'transaction_detail' => "freespin",
                        'general_details' => "freespin",
                        );
                    $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

                    return response($response,200)->header('Content-Type', 'application/json');
                } else {
                    //side bet    

                    $entry_id = 1;
                    $amount = $bet_transaction->bet_amount + $bet_amount;
                    $pay_amount = $bet_transaction->pay_amount;
                    $income = $amount -  $bet_transaction->pay_amount ;
                    $multi_bet = true;

                    $game_trans_id = $bet_transaction->game_trans_id;

                    $gameTransactionEXTData = array(
                        "game_trans_id" => $game_trans_id,
                        "provider_trans_id" => $provider_trans_id,
                        "round_id" => $data["parent_bet_id"],
                        "amount" => $bet_amount,
                        "game_transaction_type"=> $game_transaction_type,
                        "provider_request" =>json_encode($data),
                        "mw_response" => null,
                        'mw_request' => null,
                        'client_response' => null,
                        'transaction_detail' => 'Null',
                        'general_details' => null,
                        );
                    $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
                    
                    $type = "debit";
                    $rollback = false;
                    try {
                        $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount,$game_details->game_code,$game_details->game_name,$game_trans_ext_id,$game_trans_id,$type,$rollback);
                        $save_bal = DB::table("player_session_tokens")->where("token_id","=",$client_details->token_id)->update(["balance" => $client_response->fundtransferresponse->balance]);
                    } catch (\Exception $e) {
                        $response = array(
                            'data' => null,
                            'error' => [
                            'code'  => '3033',
                            'message'   => 'Bet failed'
                            ]
                        );
                        $general_details = ["aggregator" => [], "provider" => [], "client" => []];

                        $updateTransactionEXt = array(
                            "provider_request" =>json_encode($data),
                            "mw_response" => json_encode($response),
                            'mw_request' => 'FAILED',
                            'client_response' => 'BET FAILED',
                            'transaction_detail' => $response,
                            'general_details' => $general_details,
                        );
                        GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                        Helper::saveLog('PGSoft transferOut 2nd failed', $this->provider_db_id, json_encode($request->all()), $response);
                        return $response;
                    }

                    if (isset($client_response->fundtransferresponse->status->code)) {
                        switch ($client_response->fundtransferresponse->status->code) {
                            case "200":
                                $response =  [
                                    "data" => [
                                        "currency_code" => $client_details->default_currency,
                                        "balance_amount" => floatval(number_format((float)$client_response->fundtransferresponse->balance, 2, '.', '')),
                                        "updated_time" => $data["updated_time"]
                                    ],
                                    "error" => null
                                ];
                                $this->updateGametransaction($client_details,$bet_transaction->game_trans_id,5,$pay_amount,$income,$entry_id,1,$amount,$multi_bet);

                                $updateTransactionEXt = array(
                                    'mw_request' => json_encode($client_response->requestoclient),
                                    "mw_response" =>json_encode($response),
                                    'client_response' => json_encode($client_response->fundtransferresponse),
                                    'transaction_detail' => 'success',
                                    'general_details' => 'success',
                                );
                                GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                                Helper::saveLog('PGSoft transferOut success', $this->provider_db_id, json_encode($request->all()), $response);
                                break;
                            
                            case "402":
                                $response = array(
                                    'data' => null,
                                    'error' => [
                                        'code'  => '3202',
                                        'message'   => 'No enough cash balance to bet.'
                                    ]
                                );
                                $updateTransactionEXt = array(
                                    'mw_request' => json_encode($client_response->requestoclient),
                                    "mw_response" =>json_encode($response),
                                    'client_response' => json_encode($client_response->fundtransferresponse),
                                    'transaction_detail' => json_encode($response),
                                    'general_details' => 'success',
                                );
                                GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                                Helper::saveLog('PGSoft BET not_enough_balance', $this->provider_db_id, json_encode($request->all()), $response);
                                // ProviderHelper::createRestrictGame($game_details->game_id,$client_details->player_id,$game_trans_ext_id,json_encode(json_encode($response)));
                                break;

                            default:
                                $response = array(
                                    'data' => null,
                                    'error' => [
                                        'code'  => '3033',
                                        'message'   => 'Bet failed.'
                                    ]
                                );
                               
                                $updateTransactionEXt = array(
                                    'mw_request' => json_encode($client_response->requestoclient),
                                    "mw_response" =>json_encode($response),
                                    'client_response' => json_encode($client_response->fundtransferresponse),
                                    'transaction_detail' => 'success',
                                    'general_details' => 'success',
                                );
                                GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                                Helper::saveLog('PGSoft BET not_enough_balance_default', $this->provider_db_id, json_encode($request->all()), $response);
                        }
                    }
                    return response($response,200)
                    ->header('Content-Type', 'application/json');
                }
                

            }
            //Create GameTransaction, GameExtension

            $gameTransactionData = array(
                "provider_trans_id" => $provider_trans_id,
                "token_id" => $token_id,
                "game_id" => $game_code,
                "round_id" => $data["parent_bet_id"],
                "bet_amount" => $bet_amount,
                "win" => $win_or_lost,
                "pay_amount" => $pay_amount,
                "income" => $income,
                "entry_id" =>$method,
            );
            // GameTransactionMDB
            $game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
           

            $gameTransactionEXTData = array(
                "game_trans_id" => $game_trans_id,
                "provider_trans_id" => $provider_trans_id,
                "round_id" => $data["parent_bet_id"],
                "amount" => $bet_amount,
                "game_transaction_type"=> $game_transaction_type,
                "provider_request" =>json_encode($data),
                );
            $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
            
            $type = "debit";
            $rollback = false;

            try {
                $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount,$game_details->game_code,$game_details->game_name,$game_trans_ext_id,$game_trans_id,$type,$rollback);
                $save_bal = DB::table("player_session_tokens")->where("token_id","=",$client_details->token_id)->update(["balance" => $client_response->fundtransferresponse->balance]);
            } catch (\Exception $e) {
                $response = array(
                    'data' => null,
                    'error' => [
                    'code'  => '3033',
                    'message'   => 'Bet failed'
                    ]
                );
                $general_details = ["aggregator" => [], "provider" => [], "client" => []];

                $updateTransactionEXt = array(
                    "provider_request" =>json_encode($data),
                    "mw_response" => json_encode($response),
                    'mw_request' => 'FAILED',
                    'client_response' => json_encode($e->getMessage()),
                    'transaction_detail' => json_encode($response),
                    'general_details' => $general_details,
                );
                GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);

                Helper::saveLog('PGSoft transferOut failed', $this->provider_db_id, json_encode($request->all()), $response);
                return $response;
            }
            if (isset($client_response->fundtransferresponse->status->code)) {
                switch ($client_response->fundtransferresponse->status->code) {
                    case "200":
                        $response =  [
                            "data" => [
                                "currency_code" => $client_details->default_currency,
                                "balance_amount" => floatval(number_format((float)$client_response->fundtransferresponse->balance, 2, '.', '')),
                                "updated_time" => $data["updated_time"]
                            ],
                            "error" => null
                        ];

                        $updateTransactionEXt = array(
                            'mw_request' => json_encode($client_response->requestoclient),
                            "mw_response" =>json_encode($response),
                            'client_response' => json_encode($client_response->fundtransferresponse),
                            'transaction_detail' => 'success',
                            'general_details' => 'success',
                        );
                        GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);

                        Helper::saveLog('PGSoft transferOut success', $this->provider_db_id, json_encode($request->all()), $response);
                        break;
                    
                    case "402":
                        $response = array(
                            'data' => null,
                            'error' => [
                                'code'  => '3202',
                                'message'   => 'No enough cash balance to bet.'
                            ]
                        );

                         $updateTransactionEXt = array(
                            'mw_request' => json_encode($client_response->requestoclient),
                            "mw_response" =>json_encode($response),
                            'client_response' => json_encode($client_response->fundtransferresponse),
                            'transaction_detail' => json_encode($response),
                            'general_details' => 'success',
                        );
                        GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);

                        Helper::saveLog('PGSoft BET not_enough_balance', $this->provider_db_id, json_encode($request->all()), $response);
                        // ProviderHelper::createRestrictGame($game_details->game_id,$client_details->player_id,$game_trans_ext_id,json_encode(json_encode($response)));
                        break;

                    default:
                        $response = array(
                            'data' => null,
                            'error' => [
                                'code'  => '3033',
                                'message'   => 'Bet failed.'
                            ]
                        );

                         $updateTransactionEXt = array(
                            'mw_request' => json_encode($client_response->requestoclient),
                            "mw_response" =>json_encode($response),
                            'client_response' => json_encode($client_response->fundtransferresponse),
                            'transaction_detail' => json_encode($response),
                            'general_details' => 'success',
                        );
                        GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);

                        Helper::saveLog('PGSoft BET not_enough_balance_default', $this->provider_db_id, json_encode($request->all()), $response);
                }
            }
            return response($response,200)
            ->header('Content-Type', 'application/json');
                
            
        }catch(\Exception $e){
            $response = array(
                "data" => null,
                "error" => [
                    'code' => '3033',
                    "message" => 'Bet failed.',
                ]
            );
            Helper::saveLog('PGSoft Bet error '.$data['transaction_id'], $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return response($response,200)
                ->header('Content-Type', 'application/json');
        }
    }

    public function transferIn(Request $request){
        Helper::saveLog('PGSoft Payout', $this->provider_db_id, json_encode($request->all(), JSON_FORCE_OBJECT),  "ENDPOINT HIT");
        $data = $request->all();
        $player_id =  ProviderHelper::explodeUsername('_', $data["player_name"]);
        $client_details = ProviderHelper::getClientDetails('player_id',$player_id);
        if(($request->has('is_validate_bet') && $data["is_validate_bet"] == 'False') && 
            ($request->has('is_adjustment') && $data["is_adjustment"] == 'False' )){
                if($this->validateData($data) != 'false'){
                    return $this->validateData($data);
                }
        }

        try{
            ProviderHelper::idenpotencyTable('PGSOFT_'.$data['transaction_id']);
        }catch(\Exception $e){
            // $bet_transaction = Providerhelper::findGameExt($data['transaction_id'], 2, 'transaction_id');
            $bet_transaction = GameTransactionMDB::findGameExt($data['transaction_id'], 2,'transaction_id', $client_details);
            if ($bet_transaction != 'false') {
                //this will be trigger if error occur 10s
                Helper::saveLog('PGSoft BET duplicate_transaction success', $this->provider_db_id, json_encode($request->all()),  $bet_transaction->mw_response);
                return response($bet_transaction->mw_response,200)
                ->header('Content-Type', 'application/json');
            } 
            $response = array(
                'data' => null,
                'error' => [
                    'code'  => '3021',
                    'message'   => 'No bet exist.'
                ]
            );
            Helper::saveLog('PGSoft BET No bet exist', $this->provider_db_id, json_encode($request->all()),  $response);
            return response($response,200)->header('Content-Type', 'application/json');
        }


        try {

           
            // $player_id =  ProviderHelper::explodeUsername('_', $data["player_name"]);
            // $client_details = ProviderHelper::getClientDetails('player_id',$player_id);
            $game_details = $this->findGameCode('game_code', $this->provider_db_id, $data['game_id']);

            // $bet_transaction = $this->findGameTransaction($data['parent_bet_id'], 'round_id');
            $bet_transaction = GameTransactionMDB::findGameTransactionDetails($data['parent_bet_id'], 'round_id',false, $client_details);
            //if the bet ransaction not equal to fales this will be the freespin
            if ($bet_transaction == "false") {

                $explode = explode('-',$data['transaction_id']);
                $transaction_type = (string)$explode[2];
                // return $transaction_type;
                //if not frespin or not bonus return error
                if ($transaction_type != '400' && $transaction_type != '403') {
                    $response = array(
                        'data' => null,
                        'error' => [
                            'code'  => '3021',
                            'message'   => 'No bet exist.'
                        ]
                    );
                    Helper::saveLog('PGSoft BET No bet exist', $this->provider_db_id, json_encode($request->all()),  $response);
                    return response($response,200)->header('Content-Type', 'application/json');
                } else {

                    $game_transaction_type = 1; // 1 Bet, 2 Win
                    $game_code = $game_details->game_id;
                    $token_id = $client_details->token_id;
                    $bet_amount = 0;
                    $pay_amount = $data['transfer_amount'];
                    $income = $bet_amount - $pay_amount;
                    $win_type = 0;
                    $method = $pay_amount == 0 ? 1 : 2;
                    $win_or_lost = 5; // 0 lost,  5 processing
                    $payout_reason = $transaction_type == '400' ? 'BonusToCash' : 'FreeGameToCash';
                    $provider_trans_id = $data['transaction_id']; //uniquee    

                     
                    $gameTransactionData = array(
                        "provider_trans_id" => $provider_trans_id,
                        "token_id" => $token_id,
                        "game_id" => $game_code,
                        "round_id" => $data["parent_bet_id"],
                        "bet_amount" => $bet_amount,
                        "win" => $win_or_lost,
                        "pay_amount" => $pay_amount,
                        "income" => $income,
                        "entry_id" => $method,
                        "trans_status" => 1,
                    );
                    $game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);


                    $gameTransactionEXTData = array(
                        "game_trans_id" => $game_trans_id,
                        "provider_trans_id" => $provider_trans_id,
                        "round_id" => $data["parent_bet_id"],
                        "amount" => $bet_amount,
                        "game_transaction_type"=> $game_transaction_type,
                        "provider_request" =>json_encode($data),
                    );
                    $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

                    $type = "debit";
                    $rollback = false;

                    try {
                        $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount,$game_details->game_code,$game_details->game_name,$game_trans_ext_id,$game_trans_id,$type,$rollback);
                    } catch (\Exception $e) {
                        $balance = $client_details->balance + $pay_amount;
                        $response =  [
                            "data" => [
                                "currency_code" => $client_details->default_currency,
                                "balance_amount" => floatval(number_format((float)$balance, 2, '.', '')),
                                "updated_time" => $data["updated_time"]
                            ],
                            "error" => null
                        ];
                        $general_details = ["aggregator" => [], "provider" => [], "client" => []];

                        $updateTransactionEXt = array(
                            "provider_request" => json_encode($data),
                            "mw_response" =>json_encode($response),
                            "mw_request"=>'NEED TO RESEND BET FREESPIN',
                            "client_response" =>'NEED TO RESEND BET FREESPIN',
                            "transaction_detail" =>'NEED TO RESEND BET FREESPIN',
                            "general_details" =>json_encode($general_details),
                        );
                        GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);


                       $gameTransactionEXTData = array(
                            "game_trans_id" => $game_trans_id,
                            "provider_trans_id" => $provider_trans_id,
                            "round_id" => $data["parent_bet_id"],
                            "amount" => $pay_amount,
                            "game_transaction_type"=> 2,
                            "provider_request" =>json_encode($data),
                            "mw_response" =>json_encode($response),
                            "mw_request"=> 'NEED TO RESEND WIN FREESPIN',
                            "client_response" => 'NEED TO RESEND WIN FREESPIN',
                            "transaction_detail" => 'NEED TO RESEND WIN FREESPIN',
                        );
                        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

                        Helper::saveLog('PGSoft transferOut failed', $this->provider_db_id, json_encode($request->all()), $response);
                        return response($response,200)->header('Content-Type', 'application/json');
                    }
                    if (isset($client_response->fundtransferresponse->status->code)) {
                        $save_bal = DB::table("player_session_tokens")->where("token_id","=",$client_details->token_id)->update(["balance" => $client_response->fundtransferresponse->balance]);
                        switch ($client_response->fundtransferresponse->status->code) {
                            case "200":
                                $response =  [
                                    "data" => [
                                        "currency_code" => $client_details->default_currency,
                                        "balance_amount" => floatval(number_format((float)$client_response->fundtransferresponse->balance, 2, '.', '')),
                                        "updated_time" => $data["updated_time"]
                                    ],
                                    "error" => null
                                ];
                                

                                $updateTransactionEXt = array(
                                    'mw_request' => json_encode($client_response->requestoclient),
                                    "mw_response" =>json_encode($response),
                                    'client_response' => json_encode($client_response->fundtransferresponse),
                                    'transaction_detail' => 'success',
                                    'general_details' => 'success',
                                );
                                GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);

                                Helper::saveLog('PGSoft transferOut success', $this->provider_db_id, json_encode($request->all()), $response);


                                $amount = $data['transfer_amount'];
                                $transaction_uuid = $data['transaction_id']; // MW PROVIDER
                                $reference_transaction_uuid = $data["parent_bet_id"]; //  MW -ROUND

                                $balance = $client_response->fundtransferresponse->balance + $amount;
                                ProviderHelper::_insertOrUpdate($client_details->token_id, $balance); 
                                $response =  [
                                    "data" => [
                                        "currency_code" => $client_details->default_currency,
                                        "balance_amount" => floatval(number_format((float)$balance, 2, '.', '')),
                                        "updated_time" => $data["updated_time"]
                                    ],
                                    "error" => null
                                ];


                                $gameTransactionEXTData = array(
                                    "game_trans_id" => $game_trans_id,
                                    "provider_trans_id" => $transaction_uuid,
                                    "round_id" => $reference_transaction_uuid,
                                    "amount" => $amount,
                                    "game_transaction_type"=> 2,
                                    "provider_request" =>json_encode($data),
                                    "mw_response" =>json_encode($response),
                                    "mw_request"=> 'NEEED TO SETTLE WIN',
                                    "client_response" => 'NNEEED TO SETTLE WIN',
                                    "transaction_detail" => 'NEEED TO SETTLE WIN',
                                );
                                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

                                // $win_or_lost = 5; // 0 lost,  5 processing
                                $win_or_lost = ($pay_amount) > 0 ?  1 : 0;    
                                $provider_request = [];
                                $body_details = [
                                    "type" => "credit",
                                    "win" => $win_or_lost,
                                    "token" => $client_details->player_token,
                                    "rollback" => false,
                                    "game_details" => [
                                        "game_id" => $game_details->game_id
                                    ],
                                    "game_transaction" => [
                                        "provider_trans_id" => $transaction_uuid,
                                        "round_id" => $reference_transaction_uuid,
                                        "amount" => $amount
                                    ],
                                    "connection_name" => $client_details->connection_name,
                                    "provider_request" => $provider_request,
                                    "provider_response" => $response,
                                    "game_trans_ext_id" => $game_trans_ext_id,
                                    "game_transaction_id" => $bet_transaction->game_trans_id

                                ];
                                try {
                                    $client = new Client();
                                    $guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-bgFundTransferV2MultiDB',
                                        [ 'body' => json_encode($body_details), 'timeout' => '3.00']
                                    );
                                    //THIS RESPONSE IF THE TIMEOUT NOT FAILED
                                    Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($body_details), $response);
                                    return response($response,200)->header('Content-Type', 'application/json');
                                } catch (\Exception $e) {
                                    Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($body_details), $response);
                                    return response($response,200)->header('Content-Type', 'application/json');
                                }

                                break;
                                    
                            default:
                                $balance = $client_details->balance + $pay_amount;
                                $response =  [
                                    "data" => [
                                        "currency_code" => $client_details->default_currency,
                                        "balance_amount" => floatval(number_format((float)$balance, 2, '.', '')),
                                        "updated_time" => $data["updated_time"]
                                    ],
                                    "error" => null
                                ];

                                 $updateTransactionEXt = array(
                                    'mw_request' => json_encode($client_response->requestoclient),
                                    "mw_response" =>json_encode($response),
                                    'client_response' => json_encode($client_response->fundtransferresponse),
                                    'transaction_detail' => 'success',
                                    'general_details' => 'success',
                                );
                                GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);


                                  $gameTransactionEXTData = array(
                                    "game_trans_id" => $game_trans_id,
                                    "provider_trans_id" => $provider_trans_id,
                                    "round_id" => $data["parent_bet_id"],
                                    "amount" => $pay_amount,
                                    "game_transaction_type"=> 2,
                                    "provider_request" =>json_encode($data),
                                    "mw_response" =>json_encode($response),
                                    "mw_request"=> 'NEED TO RESEND WIN AND BET FREESPIN',
                                    "client_response" => 'NEED TO RESEND WIN AND BET FREESPIN',
                                    "transaction_detail" => 'NEED TO RESEND WIN AND BET FREESPIN',
                                );
                                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

                                Helper::saveLog('PGSoft BET not_enough_balance_default', $this->provider_db_id, json_encode($request->all()), $response);
                        }
                    }
                    return response($response,200)
                    ->header('Content-Type', 'application/json');    
                } 


                

            } else
            {

                $amount = $data['transfer_amount'];
                $transaction_uuid = $data['transaction_id']; // MW PROVIDER
                $reference_transaction_uuid = $data["parent_bet_id"]; //  MW -ROUND

                $balance = $client_details->balance + $amount;
                ProviderHelper::_insertOrUpdate($client_details->token_id, $balance); 
                $response =  [
                    "data" => [
                        "currency_code" => $client_details->default_currency,
                        "balance_amount" => floatval(number_format((float)$balance, 2, '.', '')),
                        "updated_time" => $data["updated_time"]
                    ],
                    "error" => null
                ];

                
                $gameTransactionEXTData = array(
                    "game_trans_id" => $bet_transaction->game_trans_id,
                    "provider_trans_id" => $transaction_uuid,
                    "round_id" => $reference_transaction_uuid,
                    "amount" => $amount,
                    "game_transaction_type"=> 2,
                    "provider_request" =>json_encode($data),
                    "mw_response" =>json_encode($response),
                    "mw_request"=> null,
                    "client_response" => null,
                    "transaction_detail" => 'null',
                );
                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);


                $win_or_lost = ($amount + $bet_transaction->pay_amount) > 0 ?  1 : 0;
                $entry_id = ($amount + $bet_transaction->pay_amount) > 0 ?  2 : 1;
                $pay_amount = $bet_transaction->pay_amount + $amount;
                $income = $bet_transaction->bet_amount -  $pay_amount ;


                $this->updateGametransaction($client_details,$bet_transaction->game_trans_id,$win_or_lost,$pay_amount,$income,$entry_id,2);


                $provider_request = [];
                $body_details = [
                    "type" => "credit",
                    "win" => $win_or_lost,
                    "token" => $client_details->player_token,
                    "rollback" => false,
                    "game_details" => [
                        "game_id" => $game_details->game_id
                    ],
                    "game_transaction" => [
                        "provider_trans_id" => $transaction_uuid,
                        "round_id" => $reference_transaction_uuid,
                        "amount" => $amount
                    ],
                    "connection_name" => $client_details->connection_name,
                    "provider_request" => $provider_request,
                    "provider_response" => $response,
                    "game_trans_ext_id" => $game_trans_ext_id,
                    "game_transaction_id" => $bet_transaction->game_trans_id

                ];
                try {
                    $client = new Client();
                    $guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-bgFundTransferV2MultiDB',
                        [ 'body' => json_encode($body_details), 'timeout' => '3.00']
                    );
                    //THIS RESPONSE IF THE TIMEOUT NOT FAILED
                    Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($body_details), $response);
                    return response($response,200)->header('Content-Type', 'application/json');
                } catch (\Exception $e) {
                    Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($body_details), $response);
                    return response($response,200)->header('Content-Type', 'application/json');
                }
                
            }

        }catch(\Exception $e){
            $msg = array(
                "data" => null,
                "error" => [
                    'code' => '3001',
                    "message" => $e->getMessage(),
                ]
            );
            Helper::saveLog('PGSoft Bonus error '.$data['transaction_id'], $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT), $msg);
            return json_encode($msg, JSON_FORCE_OBJECT); 
        }
        
    }

    public function getMilliseconds(){
        return $milliseconds = round(microtime(true) * 1000);
    }

    public function validateData($data, $method_type='methodname'){
        $boolean = 'false';
        $client_details = ProviderHelper::getClientDetails('token',$data["operator_player_session"]);
        if($data["operator_token"] != $this->operator_token):
            $boolean = 'true';
            $errormessage = array(
                'data' => null,
                'error' => [
                'code'  => '1204',
                'message'   => 'Invalid operator'
                ]
            );
            Helper::saveLog('PGSoft error', $this->provider_db_id,  json_encode($data,JSON_FORCE_OBJECT), $errormessage);
        endif;
        if($data["secret_key"] != $this->secret_key):
            $boolean = 'true';
            $errormessage = array(
                'data' => null,
                'error' => [
                'code'  => '1204',
                'message'   => 'Invalid operator'
                ]
            );
            Helper::saveLog('PGSoft error', $this->provider_db_id,  json_encode($data,JSON_FORCE_OBJECT), $errormessage);
        endif;
        if($client_details == null){
            $boolean = 'true';
            $errormessage = array(
                'data' => null,
                'error' => [
                'code'  => '1302',
                'message'   => 'Invalid player session'
                ]
            );
            Helper::saveLog('PGSoft error', $this->provider_db_id, json_encode($data, JSON_FORCE_OBJECT),  $errormessage);
        }
        if (array_key_exists('player_name', $data)) {
            if($data["player_name"] == ''){
                $errormessage = array(
                    'data' => null,
                    'error' => [
                    'code'  => '3001',
                    'message'   => 'Value cannot be null.'
                    ]
                );
                Helper::saveLog('PGSoft error', $this->provider_db_id,  json_encode($data,JSON_FORCE_OBJECT), $errormessage);
                return json_encode($errormessage, JSON_FORCE_OBJECT); 
            }else {
                $player_id = substr($data["player_name"],0,7);
                if($player_id == $this->prefix){
                    $id =  ProviderHelper::explodeUsername('_', $data["player_name"]);
                    $player_details = ProviderHelper::getClientDetails('player_id',$id);
                }else {
                    $player_details = null;
                }
                if($player_details == null){
                    $errormessage = array(
                        'data' => null,
                        'error' => [
                        'code'  => '3005',
                        'message'   => 'Player wallet doesn\'t exist.'
                        ]
                    );
                    Helper::saveLog('PGSoft error', $this->provider_db_id,  json_encode($data,JSON_FORCE_OBJECT), $errormessage);
                    return json_encode($errormessage, JSON_FORCE_OBJECT); 
                }
                if((string)$player_details->player_id != (string)$id){
                    $errormessage = array(
                        'data' => null,
                        'error' => [
                        'code'  => '3005',
                        'message'   => 'Player wallet doesn\'t exist.'
                        ]
                    );
                    Helper::saveLog('PGSoft error', $this->provider_db_id,  json_encode($data,JSON_FORCE_OBJECT), $errormessage);
                    return json_encode($errormessage, JSON_FORCE_OBJECT); 
                 }
            }
        }
        if (array_key_exists('currency_code', $data)) {
            if($data["player_name"] ==''){
                $player_details = null;
            }else {
                $player_id = substr($data["player_name"],0,7);
                if($player_id == $this->prefix){
                    $id =  ProviderHelper::explodeUsername('_', $data["player_name"]);
                    $player_details = ProviderHelper::getClientDetails('player_id',$id);
                }else {
                    $player_details = null;
                }
            }
            if($player_details != null ){
                if($data["currency_code"] != $player_details->default_currency):
                    $boolean = 'true';
                    $errormessage = array(
                        'data' => null,
                        'error' => [
                        'code'  => '1034',
                        'message'   => 'Invalid request'
                        ]
                    );
                    Helper::saveLog('PGSoft error', $this->provider_db_id,  json_encode($data,JSON_FORCE_OBJECT), $errormessage);
                endif;
            }elseif($client_details != null) {
                if($data["currency_code"] != $client_details->default_currency):
                    $boolean = 'true';
                    $errormessage = array(
                        'data' => null,
                        'error' => [
                        'code'  => '1034',
                        'message'   => 'Invalid request'
                        ]
                    );
                    Helper::saveLog('PGSoft error', $this->provider_db_id,  json_encode($data,JSON_FORCE_OBJECT), $errormessage);
                endif;
            }
           
        }
        return $boolean == 'true'? $errormessage : 'false';
    }

    public  function updateReason($win) {
        $win_type = [
        "1" => 'Transaction updated to win',
        "2" => 'Transaction updated to bet',
        "3" => 'Transaction updated to Draw',
        "4" => 'Transaction updated to Refund',
        "5" => 'Transaction updated to Processing',
        ];
        if(array_key_exists($win, $win_type)){
            return $win_type[$win];
        }else{
            return 'Transaction Was Updated!';
        }   
    }
    
    public static function findGameCode($type, $provider_id, $identification) { 
        $array = [
            [1,"diaochan"],
            [2,"gem-saviour"],
            [3,"fortune-gods"],
            [4,"summon-conquer"],
            [6,"medusa2"],
            [7,"medusa"],
            [8,"peas-fairy"],
            [17,"wizdom-wonders"],
            [18,"hood-wolf"],
            [19,"steam-punk"],
            [24,"win-win-won"],
            [25,"plushie-frenzy"],
            [26,"fortune-tree"],
            [27,"restaurant-craze"],
            [28,"hotpot"],
            [29,"dragon-legend"],
            [33,"hip-hop-panda"],
            [34,"legend-of-hou-yi"],
            [35,"mr-hallow-win"],
            [36,"prosperity-lion"],
            [37,"santas-gift-rush"],
            [38,"gem-saviour-sword"],
            [39,"piggy-gold"],
            [40,"jungle-delight"],
            [41,"symbols-of-egypt"],
            [42,"ganesha-gold"],
            [43,"three-monkeys"],
            [44,"emperors-favour"],
            [45,"tomb-of-treasure"],
            [48,"double-fortune"],
            [52,"wild-inferno"],
            [53,"the-great-icescape"], 
            [65,"mahjong-ways"],                              
            [10,"joker-wild"],//tablegame
            [11,"blackjack-us"],//tablegame
            [12,"blackjack-eu"],//tablegame
            [31,"baccarat-deluxe"],//tablegame
            [50,"journey-to-the-wealth"],
            [21,"tiki-go"],
            [54,"captains-bounty"],
            [60,"leprechaun-riches"],
            [61,"flirting-scholar"],
            [59,"ninja-vs-samurai"],
            [64,"muay-thai-champion"],
            [63,"dragon-tiger-luck"],
            [57,"dragon-hatch"],
            [68,"fortune-mouse"],
            [20,"reel-love"],
            [62,"gem-saviour-conquest"],
            [67,"shaolin-soccer"],
            [71,"cai-shen-wins"],
            [70,"candy-burst"],
            [69,"bikini-paradise"],
            [74,"mahjong-ways2"],
            [73,"egypts-book-mystery"],
            [75,"ganesha-fortune"],
            [79,"dreams-of-macau"],
            [82,"phoenix-rises"],
            [83,"wild-fireworks"],
            [81,"dim-sum-mania"],
            [84,"queen-bounty"],
            [88,"jewels-prosper"],
            [85,"genies-wishes"],
            [87,"treasures-aztec"],
            [89,"lucky-neko"],
            [91,"gdn-ice-fire"],
            [92,"thai-river"],
            [93,"opera-dynasty"],
            [90,"sct-cleopatra"],
            [97,"jack-frosts"],
            [98,"fortune-ox"],
            [94, "bali-vacation"],
            [100, "candy-bonanza"],
            [95,"majestic-ts"],
            [104,"wild-bandito"],
            [103,"crypto-gold"],
            [106,"ways-of-qilin"],
            [105,"heist-stakes"],
            [101,"rise-of-apollo"],
            [109,"sushi-oishi"]
        ];
        $game_code = '';
        for ($row = 0; $row < count($array); $row++) {
            if($array[$row][0] == $identification){
                $game_code = $array[$row][1];
            }
        }
        $game_details = DB::table("games as g")
            ->join("providers as p","g.provider_id","=","p.provider_id");
        
        if ($type == 'game_code') {
            $game_details->where([
                 ["g.provider_id", "=", $provider_id],
                 ["g.game_code",'=', $game_code],
             ]);
        }
        $result= $game_details->first();
         return $result;
    }
    public  static function findGameExt($provider_identifier, $type) {
        $transaction_db = DB::table('game_transaction_ext as gte');
        if ($type == 'transaction_id') {
            $transaction_db->where([
                ["gte.provider_trans_id", "=", $provider_identifier]
            
            ]);
        }
        if ($type == 'round_id') {
            $transaction_db->where([
                ["gte.round_id", "=", $provider_identifier],
            ]);
        }  
        $result= $transaction_db->first();
        return $result ? $result : 'false';
    }

    public static function createGameTransExt($game_trans_id, $provider_trans_id, $round_id, $amount, $game_type, $provider_request, $mw_response, $mw_request, $client_response, $transaction_detail){
        $gametransactionext = array(
            "game_trans_id" => $game_trans_id,
            "provider_trans_id" => $provider_trans_id,
            "round_id" => $round_id,
            "amount" => $amount,
            "game_transaction_type"=>$game_type,
            "provider_request" => json_encode($provider_request),
            "mw_response" =>json_encode($mw_response),
            "mw_request"=>json_encode($mw_request),
            "client_response" =>json_encode($client_response),
            "transaction_detail" =>json_encode($transaction_detail)
        );
        $gamestransaction_ext_ID = DB::table("game_transaction_ext")->insertGetId($gametransactionext);
        return $gamestransaction_ext_ID;
    }

    public static function updateGameTransactionExt($gametransextid,$mw_request,$mw_response,$client_response){
        $gametransactionext = array(
            "mw_request"=>json_encode($mw_request),
            "mw_response" =>json_encode($mw_response),
            "client_response" =>json_encode($client_response),
            "transaction_detail" => "success",
        );
        DB::table('game_transaction_ext')->where("game_trans_ext_id",$gametransextid)->update($gametransactionext);
    }


    public static function playerDetailsCall($client_details, $refreshtoken=false, $type=1){
        
        if($client_details){
            $client = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$client_details->client_access_token
                ]
            ]);
            $datatosend = [
                "access_token" => $client_details->client_access_token,
                "hashkey" => md5($client_details->client_api_key.$client_details->client_access_token),
                "type" => "playerdetailsrequest",
                "datesent" => Helper::datesent(),
                "gameid" => "",
                "clientid" => $client_details->client_id,
                "playerdetailsrequest" => [
                    "player_username"=>$client_details->username,
                    "client_player_id" => $client_details->client_player_id,
                    "token" => $client_details->player_token,
                    "gamelaunch" => true,
                    "refreshtoken" => $refreshtoken
                ]
            ];

            try{    
                $guzzle_response = $client->post($client_details->player_details_url,
                    ['body' => json_encode($datatosend)]
                );
                $client_response = json_decode($guzzle_response->getBody()->getContents());
                
                if(isset($client_response->playerdetailsresponse->status->code) && $client_response->playerdetailsresponse->status->code != 200 || $client_response->playerdetailsresponse->status->code != '200'){
                    if($refreshtoken == true){
                        if(isset($client_response->playerdetailsresponse->refreshtoken) &&
                        $client_response->playerdetailsresponse->refreshtoken != false || 
                        $client_response->playerdetailsresponse->refreshtoken != 'false'){
                            DB::table('player_session_tokens')->insert(
                            array('player_id' => $client_details->player_id, 
                                  'player_token' =>  $client_response->playerdetailsresponse->refreshtoken, 
                                  'status_id' => '1')
                            );
                        }
                    }
                    return 'false';
                }else{
                    if($refreshtoken == true){
                        if(isset($client_response->playerdetailsresponse->refreshtoken) &&
                        $client_response->playerdetailsresponse->refreshtoken != false || 
                        $client_response->playerdetailsresponse->refreshtoken != 'false'){
                            DB::table('player_session_tokens')->insert(
                                array('player_id' => $client_details->player_id, 
                                      'player_token' =>  $client_response->playerdetailsresponse->refreshtoken, 
                                      'status_id' => '1')
                            );
                        }
                    }
                    return $client_response;
                }

            }catch (\Exception $e){
               return 'false';
            }
        }else{
            return 'false';
        }
    }

    public function findGameTransaction($identifier, $type, $entry_type='') {

        if ($type == 'transaction_id') {
            $where = 'where gt.provider_trans_id = "'.$identifier.'" AND gt.entry_id = '.$entry_type.'';
        }
        if ($type == 'game_transaction') {
            $where = 'where gt.game_trans_id = "'.$identifier.'"';
        }
        if ($type == 'round_id') {
            $where = 'where gt.round_id = "'.$identifier.'" ';
        }
        
        $filter = 'LIMIT 1';
        $query = DB::select('select gt.game_trans_id, gt.provider_trans_id, gt.game_id, gt.round_id, gt.bet_amount,gt.win, gt.pay_amount, gt.entry_id, gt.income, game_trans_ext_id from game_transactions gt inner join game_transaction_ext using(game_trans_id) '.$where.' '.$filter.'');
        $client_details = count($query);
        return $client_details > 0 ? $query[0] : 'false';
    }

    public function updateGametransaction($client_details,$game_trans_id,$win_type,$pay_amount,$income,$entry_id,$trans_status,$bet_amount=0,$multi_bet=false){
        $updateGameTransaction = [
            'win' => $win_type,
            'pay_amount' => $pay_amount,
            'income' => $income,
            'entry_id' => $entry_id,
            'trans_status' => $trans_status,
        ];
        $update = GameTransactionMDB::updateGametransaction($updateGameTransaction,$game_trans_id, $client_details);

        if($multi_bet == true){
            $updateGameTransaction = [
                'bet_amount' => $bet_amount,
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction,$game_trans_id, $client_details);  
        }

        return ($update ? true : false);
    }



    
}
