<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransactionMDB;


class PlayTechController extends Controller
{

    public function __construct()
    {
        $this->provider_db_id = config('providerlinks.playtech.provider_db_id');
        $this->prefix = "PLAYTECH";
    }

    private static function checkMD5($request)
    {
        $hash = $request["hash"];
        unset($request["hash"]);
        ksort($request);
        $string = '';
        foreach ($request as $k => $v) {
            if (is_array($v)) {
                $v = json_encode($v, true);
            }
            $string .= $k . '=' . $v . '&';
        }
        $decode_request = md5(Str::replaceLast('&',  config('providerlinks.playtech.secret_key'), $string));
        // dd($decode_request);
        return ($hash == $decode_request) ? "true": "false" ;
  
    }

    public function auth(Request $request){
        
        // dd($request['ip']);
        Helper::saveLog('PlayTech Auth', 64, json_encode($request->all()),  "HIT" );
        $hashedata = $this->checkMD5($request->all());
        if ($hashedata == "true") {
            if ($request->brandId == config('providerlinks.playtech.brand_id') ) {
                $client_details = ProviderHelper::getClientDetails('token', $request->token);
                $country_code = (isset($client_details->country_code) && $client_details->country_code != '' ) ? $client_details->country_code : "JP";
                if ($client_details != null) {
                    $response = [
                        "requestId" => $request->requestId,
                        "playerId" => $client_details->player_id,
                        "playerName" => $client_details->display_name,
                        "playerSessionId" => $client_details->player_token,
                        "currency" =>$client_details->default_currency,
                        "country" => $country_code,
                        "error" => "0",
                        "message" => "success"
                    ];
                    Helper::saveLog('PlayTech Auth', $this->provider_db_id, json_encode($response),  "response" );
                    return response($response,200)
                            ->header('Content-Type', 'application/json');
                }
                $response = [
                    "requestId" => $request->requestId,
                    "error" => "P_06",
                    "message" => "Invalid token or token expired"
                ];
                Helper::saveLog('PlayTech Auth', $this->provider_db_id, json_encode($response),  "response" );
                return response($response,200)
                ->header('Content-Type', 'application/json');
            }
            $response = [
                "requestId" => $request->requestId,
                "error" => "P_03",
                "message" => "Invalid brandId"
            ];
            Helper::saveLog('PlayTech Auth', $this->provider_db_id, json_encode($response),  "response" );
            return response($response,200)
            ->header('Content-Type', 'application/json');
            
        }
        $response = [
            "requestId" => $request->requestId,
            "error" => "P_02",
            "message" => "Invalid hash"
        ];
        Helper::saveLog('PlayTech Auth', $this->provider_db_id, json_encode($response),  "response" );
        return response($response,200)
        ->header('Content-Type', 'application/json');
    }
    public function getBalance(Request $request){
        Helper::saveLog('PlayTech Balance Get', $this->provider_db_id, json_encode($request->all()),  "HIT" );
        $hashedata = $this->checkMD5($request->all());
        if($hashedata == "true"){
            if($request->brandId == config('providerlinks.playtech.brand_id')){
                $client_details = ProviderHelper::getClientDetails('token', $request->playerSessionId);
                if($client_details != null){
                    $response = [
                        "requestId" => $request->requestId,
                        "currency" => $client_details->default_currency,
                        "balance" => (float) $client_details->balance,
                        "error" => "0",
                        "message" => "success"
                    ];
                    Helper::saveLog('PlayTech Balance', $this->provider_db_id, json_encode($response),  "response" );
                    return response($response,200)
                            ->header('Content-Type', 'application/json');
                }//client details end
                $response = [
                    "requestId" => $request->requestId,
                    "error" => "P_04",
                    "message" => "Player not found"
                ];
                Helper::saveLog('PlayTech Balance', $this->provider_db_id, json_encode($response),  "response" );
                return response($response,200)
                ->header('Content-Type', 'application/json');
            }
            $response = [
                "requestId" => $request->requestId,
                "error" => "P_03",
                "message" => "Invalid brandId"
            ];
            Helper::saveLog('PlayTech Balance', $this->provider_db_id, json_encode($response),  "response" );
            return response($response,200)
            ->header('Content-Type', 'application/json');
        }
        $response = [
            "requestId" => $request->requestId,
            "error" => "P_02",
            "message" => "Invalid hash"
        ];
        Helper::saveLog('PlayTech Balance', $this->provider_db_id, json_encode($response),  "response" );
        return response($response,200)
        ->header('Content-Type', 'application/json');

    }

    public function transaction(Request $request){
        Helper::saveLog('PlayTech transaction', $this->provider_db_id, json_encode($request->all()),  "HIT" );
        $hashedata = $this->checkMD5($request->all());
        if($hashedata == "true"){
            if($request->brandId == config('providerlinks.playtech.brand_id')){
                $client_details = ProviderHelper::getClientDetails('token', $request->playerSessionId);
                if($client_details != null){
                    $game_details = Helper::findGameDetails('game_code', $this->provider_db_id, $request->gameCode);
                    if($client_details->player_id != $request->playerId){
                        $response = [
                            "requestId" => $request->requestId,
                            "error" => "P_04",
                            "message" => "Player not found"
                        ];
                        // Helper::saveLog('PlayTech transaction IDOM', $this->provider_db_id, json_encode($response),  "response" );
                        return response($response,200)
                        ->header('Content-Type', 'application/json');
                    }
                    if ($game_details != null) {
                       foreach($request->trans  as $key =>  $value){
                            try{
                                ProviderHelper::idenpotencyTable($this->prefix.'_'.$value["transId"].'-'.$value["roundId"]);
                            }catch(\Exception $e){
                                $bet_transaction = GameTransactionMDB::findGameExt($value["transId"], false,'transaction_id', $client_details);
                                if ($bet_transaction != 'false') {
                                    if ($bet_transaction->mw_response == 'null') {
                                        $response = [
                                            "requestId" => $request->requestId,
                                            "error" => "T_04",
                                            "message" => "Bet limit was exceeded"
                                        ];
                                    }else {
                                        $bet_transaction = GameTransactionMDB::findGameTransactionDetails($bet_transaction->game_trans_id, 'game_transaction', false, $client_details);
                                        $response = [
                                            "requestId" => $request->requestId,
                                            "currency" => $client_details->default_currency,
                                            "balance" => (float) $client_details->balance,
                                            "error" => "0",
                                            "message" => "success"
                                        ];
                                        if($bet_transaction->win == 2 ){
                                            $response = [
                                                "requestId" => $request->requestId,
                                                "error" => "T_04",
                                                "message" => "Bet limit was exceeded"
                                            ];
                                        }
                                    }
                                    // Helper::saveLog('PlayTech transaction IDOM', $this->provider_db_id, json_encode($response),  "response" );
                                    return response($response,200)
                                    ->header('Content-Type', 'application/json');
                                } else {
                                    $response = [
                                        "requestId" => $request->requestId,
                                        "error" => "T_04",
                                        "message" => "Bet limit was exceeded"
                                    ];
                                } 
                                // Helper::saveLog('PlayTech transaction IDOM', $this->provider_db_id, json_encode($response),  "response" );
                                return response($response,200)
                                    ->header('Content-Type', 'application/json');
                            }
                            if ($value["transType"] == "bet" || $value["transType"] == "transIn" ){
                                $response = $this->betProcess($value, $client_details,$game_details,$request->requestId, $request->all() );
                            } elseif ($value["transType"] == "win" || $value["transType"] == "transOut"){
                                $response = $this->winProcess($value, $client_details,$game_details,$request->requestId, $request->all() );
                            } elseif ($value["transType"] == "cancel"){
                                $response = $this->cancelProcess($value, $client_details,$game_details,$request->requestId, $request->all() );
                            }
                            return response($response,200)
                                ->header('Content-Type', 'application/json');
                       }
                    }
                    $response = [
                        "requestId" => $request->requestId,
                        "error" => "G_01",
                        "message" => "Game not found"
                    ];
                    // Helper::saveLog('PlayTech transaction', $this->provider_db_id, json_encode($response),  "response" );
                    return response($response,200)
                    ->header('Content-Type', 'application/json');
                }
                $response = [
                    "requestId" => $request->requestId,
                    "error" => "P_08",
                    "message" => "Invalid session, the session does not exist"
                ];
                // Helper::saveLog('PlayTech transaction', $this->provider_db_id, json_encode($response),  "response" );
                return response($response,200)
                ->header('Content-Type', 'application/json');
            }
            $response = [
                "requestId" => $request->requestId,
                "error" => "P_03",
                "message" => "Invalid brandId"
            ];
            // Helper::saveLog('PlayTech transaction', $this->provider_db_id, json_encode($response),  "response" );
            return response($response,200)
            ->header('Content-Type', 'application/json');
        }
        $response = [
            "requestId" => $request->requestId,
            "error" => "P_02",
            "message" => "Invalid hash"
        ];
        // Helper::saveLog('PlayTech transaction', $this->provider_db_id, json_encode($response),  "response" );
        return response($response,200)
        ->header('Content-Type', 'application/json');
    }

    private static function betProcess($request,$client_details,$game_details,$requestId, $data){
        $bet_transaction = GameTransactionMDB::findGameTransactionDetails($request["roundId"], 'round_id',false, $client_details);
        if ($bet_transaction != 'false' && $request["endRound"] == 1) {
            $response = [
                "requestId" => $requestId,
                "error" => "R_01",
                "message" => "Round is closed when bet"
            ];
			// Helper::saveLog('PlayTech transaction BET', $this->provider_db_id, json_encode($response),  "response" );
			return $response;
		}
        $failed = 2;
        if ($bet_transaction != 'false' ) {
            $failed = 5;
            $updateGameTransaction = [
                "bet_amount" => $bet_transaction->bet_amount + $request["amount"]
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
            $game_trans_id = $bet_transaction->game_trans_id;
		} else {
            $gameTransactionData = array(
                "provider_trans_id" => $request["transId"],
                "token_id" => $client_details->token_id,
                "game_id" => $game_details->game_id,
                "round_id" => $request["roundId"],
                "bet_amount" => $request["amount"],
                "win" => 5,
                "pay_amount" => 0,
                "income" => 0,
                "entry_id" =>1,
                "trans_status" =>1,
                "operator_id" => $client_details->operator_id,
                "client_id" => $client_details->client_id,
                "player_id" => $client_details->player_id,
            );
            $game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
        }

        
        $gameTransactionEXTData = array(
            "game_trans_id" => $game_trans_id,
            "provider_trans_id" => $request["transId"],
            "round_id" => $request["roundId"],
            "amount" => $request["amount"],
            "game_transaction_type"=> 1,
            "provider_request" =>json_encode($data),
        );
        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
        #NEGATIVE AMOUNT
        if($request["amount"] < 0 ){
            $response = [
                "requestId" => $requestId,
                "error" => "P_01",
                "message" => "Invalid request. This error can be returned if required parameters are missing or they have incorrect format"
            ];
            $updateGameTransaction = [
                "win" => $failed,
                'trans_status' => 5
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
            // Helper::saveLog('PlayTech transaction FARAL ERROR BET', $this->provider_db_id, json_encode($response),  "response" );
            return $response;
        }
        $fund_extra_data = [
            'provider_name' => $game_details->provider_name
        ];
        try {
            $client_response = ClientRequestHelper::fundTransferFunta($client_details,$request["amount"],$game_details->game_code,$game_details->game_name,$game_trans_ext_id,$game_trans_id,"debit",false,$fund_extra_data);
        } catch (\Exception $e) {
            $response = [
                "requestId" => $requestId,
                "error" => "T_04",
                "message" => "Bet limit was exceeded"
            ];
            $updateTransactionEXt = array(
                "mw_response" => json_encode($response),
                'mw_request' => json_encode("FAILED"),
                'client_response' => json_encode("FAILED"),
                "transaction_detail" => "FAILED",
                "general_details" =>"FAILED",
            );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
            $updateGameTransaction = [
                "win" => $failed,
                'trans_status' => 5
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
            // Helper::saveLog('PlayTech transaction FARAL ERROR BET', $this->provider_db_id, json_encode($response),  "response" );
            return $response;
        }
        if (isset($client_response->fundtransferresponse->status->code)) {
            switch ($client_response->fundtransferresponse->status->code) {
                case "200":
                     ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    $balance = $client_response->fundtransferresponse->balance;
                    $response = [
                        "requestId" => $requestId,
                        "currency" => $client_details->default_currency,
                        "balance" => (float) $balance,
                        "error" => "0",
                        "message" => "success"
                    ];
                    $update_gametransactionext = array(
                        "mw_response" =>json_encode($response),
                        "mw_request"=>json_encode($client_response->requestoclient),
                        "client_response" =>json_encode($client_response->fundtransferresponse),
                        "transaction_detail" =>"success",
                        "general_details" => "success",
                    );
                    GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_id,$client_details);
                    break;
                case "402":
                    $response = [
                        "requestId" => $requestId,
                        "error" => "T_01",
                        "message" => "Player Insufficient Funds"
                    ];
                    $update_gametransactionext = array(
                        "mw_response" =>json_encode($response),
                        "mw_request"=>json_encode($client_response->requestoclient),
                        "client_response" =>json_encode($client_response->fundtransferresponse),
                        "transaction_detail" => "FAILED",
                        "general_details" => "FAILED",
                    );
                    GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_id,$client_details);
                    $updateGameTransaction = [
                        "win" => $failed,
                        'trans_status' => 5
                    ];
                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
                    // ProviderHelper::createRestrictGame($game_details->game_id,$client_details->player_id,$game_trans_ext_id,json_encode(json_encode($response)));
                    break;
                default:
                    $response = [
                        "requestId" => $requestId,
                        "error" => "T_04",
                        "message" => "Bet limit was exceeded"
                    ];
                    $update_gametransactionext = array(
                        "mw_response" =>json_encode($response),
                        "mw_request"=>json_encode($client_response->requestoclient),
                        "client_response" =>json_encode($client_response->fundtransferresponse),
                        "transaction_detail" => "FAILED",
                        "general_details" => "FAILED",
                    );
                    GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_id,$client_details);
                    $updateGameTransaction = [
                        "win" => $failed,
                        'trans_status' => 5
                    ];
                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
            }
        }
        // Helper::saveLog('PlayTech transaction BET', $this->provider_db_id, json_encode($response),  "response" );
        return $response;
    }

    private static function winProcess($request,$client_details,$game_details,$requestId, $data){
        $bet_transaction = GameTransactionMDB::findGameTransactionDetails($request["roundId"], 'round_id',false, $client_details);
        if ($bet_transaction == 'false') {
            $response = [
                "requestId" => $requestId,
                "error" => "R_02",
                "message" => "Invalid round when win"
            ];
			// Helper::saveLog('PlayTech transaction WIN', $this->provider_db_id, json_encode($response),  "response" );
			return $response;
		}
        $client_details->connection_name = $bet_transaction->connection_name;
        $balance = $client_details->balance + $request["amount"];
        ProviderHelper::_insertOrUpdate($client_details->token_id, $balance); 
        $response = [
            "requestId" => $requestId,
            "currency" => $client_details->default_currency,
            "balance" => (float) $balance,
            "error" => "0",
            "message" => "success"
        ];
        $create_gametransactionext = array(
            "game_trans_id" =>$bet_transaction->game_trans_id,
            "provider_trans_id" => $request["transId"],
            "round_id" => $request["roundId"],
            "amount" => $request["amount"],
            "game_transaction_type"=> 2,
            "provider_request" => json_encode($data),
            "mw_response" => json_encode($response)
        );
        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($create_gametransactionext,$client_details);
        $win_or_lost = $bet_transaction->pay_amount + $request["amount"] > 0 ?  1 : 0;
        $entry_id =$bet_transaction->pay_amount + $request["amount"] > 0 ?  2 : 1;
        $income = $bet_transaction->bet_amount - ($bet_transaction->pay_amount + $request["amount"]) ;
        $updateGameTransaction = [
            'win' => 5,
            'pay_amount' => $bet_transaction->pay_amount + $request["amount"],
            'income' => $income,
            'entry_id' => $entry_id,
            'trans_status' => 2
        ];
        GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
        $body_details = [
            "type" => "credit",
            "win" => $win_or_lost,
            "token" => $client_details->player_token,
            "rollback" => false,
            "game_details" => [
                "game_id" => $game_details->game_id
            ],
            "game_transaction" => [
                "amount" => $request["amount"],
            ],
            "connection_name" => $bet_transaction->connection_name,
            "game_trans_ext_id" => $game_trans_ext_id,
            "game_transaction_id" => $bet_transaction->game_trans_id

        ];
        try {
            $client = new Client();
            $guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-bgFundTransferV2MultiDB',
                [ 'body' => json_encode($body_details), 'timeout' => '2.00']
            );
            //THIS RESPONSE IF THE TIMEOUT NOT FAILED
            // Helper::saveLog('PlayTech transaction WIN', $this->provider_db_id, json_encode($response),  "response" );
            return $response;
        } catch (\Exception $e) {
            // Helper::saveLog('PlayTech transaction WIN', $this->provider_db_id, json_encode($response),  "response" );
            return $response;
        }
    }

    private static function cancelProcess($request,$client_details,$game_details,$requestId, $data){
        $bet_transaction = GameTransactionMDB::findGameTransactionDetails($request["roundId"], 'round_id',false, $client_details);
        if ($bet_transaction == 'false') {
            $response = [
                "requestId" => $requestId,
                "error" => "T_03",
                "message" => "Transaction does not exist when cancel"
            ];
			// Helper::saveLog('PlayTech transaction WIN', $this->provider_db_id, json_encode($response),  "response" );
			return $response;
		}
        $transaction = GameTransactionMDB::findGameExt($request["referenceId"], false,'transaction_id', $client_details);
        if($transaction == "false") {
            $response = [
                "requestId" => $requestId,
                "error" => "T_03",
                "message" => "Transaction does not exist when cancel"
            ];
			// Helper::saveLog('PlayTech transaction WIN', $this->provider_db_id, json_encode($response),  "response" );
			return $response;
        }
        $client_details->connection_name = $bet_transaction->connection_name;
        $balance = $client_details->balance + $request["amount"];
        ProviderHelper::_insertOrUpdate($client_details->token_id, $balance); 
        $response = [
            "requestId" => $requestId,
            "currency" => $client_details->default_currency,
            "balance" => (float) $balance,
            "error" => "0",
            "message" => "success"
        ];
        $create_gametransactionext = array(
            "game_trans_id" =>$bet_transaction->game_trans_id,
            "provider_trans_id" => $request["transId"],
            "round_id" => $request["roundId"],
            "amount" => $request["amount"],
            "game_transaction_type"=> 3,
            "provider_request" => json_encode($data),
            "mw_response" => json_encode($response)
        );
        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($create_gametransactionext,$client_details);
        $income = $bet_transaction->bet_amount - ($bet_transaction->pay_amount + $request["amount"]) ;
        $updateGameTransaction = [
            'win' => 5,
            'pay_amount' => $bet_transaction->pay_amount + $request["amount"],
            'income' => $income,
            'entry_id' =>2,
            'trans_status' => 2
        ];
        GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
        $body_details = [
            "type" => "credit",
            "win" => 4,
            "token" => $client_details->player_token,
            "rollback" => true,
            "game_details" => [
                "game_id" => $game_details->game_id
            ],
            "game_transaction" => [
                "amount" => $request["amount"],
            ],
            "connection_name" => $bet_transaction->connection_name,
            "game_trans_ext_id" => $game_trans_ext_id,
            "game_transaction_id" => $bet_transaction->game_trans_id

        ];
        try {
            $client = new Client();
            $guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-bgFundTransferV2MultiDB',
                [ 'body' => json_encode($body_details), 'timeout' => '2.00']
            );
            //THIS RESPONSE IF THE TIMEOUT NOT FAILED
            // Helper::saveLog('PlayTech transaction WIN', $this->provider_db_id, json_encode($response),  "response" );
            return $response;
        } catch (\Exception $e) {
            // Helper::saveLog('PlayTech transaction WIN', $this->provider_db_id, json_encode($response),  "response" );
            return $response;
        }

    }

    
}
