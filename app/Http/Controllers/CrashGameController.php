<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Helpers\ClientRequestHelper;
use App\Helpers\GameLobby;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use App\Models\GameTransactionMDB;
use Carbon\Carbon;
use DB;

/**
 * @author's note : Crash Game
 *
 */
class CrashGameController extends Controller
{

    public $authToken, $provider_db_id;
    public $provider_name = 'crashgaming';

    public function __construct()
    {
        $this->provider_db_id = config('providerlinks.crashgaming.pdbid');
        $this->authToken = config('providerlinks.crashgaming.authToken');
    }

	public function Balance(Request $request){
        $request_body = $request->getContent();
        $data = json_decode($request_body);

        if ($request->header('AuthToken') != $this->authToken){
            return  $response = ["status" => "AuthToken Mismatched", "uuid" =>  $data->player_token];
        }

        $client_details = ProviderHelper::getClientDetails('player_id',$data->userId);
        if($client_details == 'false'){
            return  $response = ["status" => "Insufficient Fund", "uuid" =>  $data->player_token];
        }
        $response = [
            "status" => true,
            "balance" => $client_details->balance,
            "uuid" =>  $client_details->player_token,
        ];
        return $response;
	}

	public function Debit(Request $request){
        $request_body = $request->getContent();
        $data = json_decode($request_body);

        if ($request->header('AuthToken') != $this->authToken){
            return  $response = ["status" => "AuthToken Mismatched", "uuid" =>  $data->player_token];
        }

        $client_details = ProviderHelper::getClientDetails('player_id',$data->userId);
        if($client_details == 'false'){
            return  $response = ["status" => "Insufficient Fund", "uuid" =>  $data->player_token];
        }

        $provider_trans_id = $data->uuid; // for checking idenpotent use this!
        $round_id = $data->transaction->refId;
        $game_code = $data->game->id;
        $game_transaction_type = 1;
		$token_id = $client_details->token_id;
        $bet_amount = $data->transaction->amount;
        $pay_amount =  0;
        $method = 1;
        $income = $bet_amount - $pay_amount;
        $entry_id = 1;
        $win_or_lost = 5; // 0 lost,  5 processing
        $payout_reason = 'settled';

        $general_details['client']['before_balance'] = $client_details->balance;

        $game_information = ProviderHelper::findGameDetails('game_code', $this->provider_db_id, $game_code);
        if($game_information == null){ 
            return  $response = ["status" => "Game Not Found", "statusCode" =>  1];
        }

        $game_ext_check = GameTransactionMDB::findGameExt($provider_trans_id, 1,'transaction_id', $client_details);
        if($game_ext_check != 'false'){ // Duplicate transaction
            dd("DUPLICATE NA CHOY!");
        	$response = ["status" => "Insuffecient Fund", "uuid" =>  $client_details->player_token];
        	return $response;
        }

        #1 DEBIT OPERATION
        $flow_status = 0;
        $gameTransactionData = array(
            "provider_trans_id" => $provider_trans_id,
            "token_id" => $token_id,
            "game_id" => $game_code,
            "round_id" => $round_id,
            "bet_amount" => $bet_amount,
            "win" => $win_or_lost,
            "pay_amount" => $pay_amount,
            "income" =>  $income,
            "entry_id" =>$method,
        );
        $gamerecord = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
        $gameTransactionEXTData = array(
            "game_trans_id" => $gamerecord,
            "provider_trans_id" => $provider_trans_id,
            "round_id" => $round_id,
            "amount" => $bet_amount,
            "game_transaction_type"=> $game_transaction_type,
            "provider_request" =>json_encode($data),
        );
        $game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);


        $fund_extra_data = [
            'fundtransferrequest' => [
                'fundinfo' => [
                    'freespin' => false,
                ]
            ],
            'provider_name' => $game_information->provider_name
        ];
        
        try {
          $client_response = ClientRequestHelper::fundTransfer($client_details,abs($bet_amount),$game_information->game_code,$game_information->game_name,$game_transextension,$gamerecord, 'debit',false,$fund_extra_data);
          ProviderHelper::saveLogWithExeption('CrashGaming checkPlay CRID '.$gamerecord, $this->provider_db_id,json_encode($request->all()), $client_response);
        } catch (\Exception $e) {
            $response = ["status" => "Insuffecient Fund", "uuid" =>  $client_details->player_token];
            if(isset($gamerecord)){
                if($check_bet_round == 'false'){
                    $updateGameTransaction = [
                        "win" => 2,
                        'trans_status' => 5
                    ];
                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
                    $updateTransactionEXt = array(
                        "provider_request" =>json_encode($data),
                        "mw_response" => json_encode($response),
                        // 'mw_request' => 'FAILED',
                        'client_response' => $e->getMessage() ." ". $e->getLine(),
                        'transaction_detail' => 'FAILED',
                        'general_details' => json_encode($general_details),
                    );
                    GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
                }
            }
          ProviderHelper::saveLogWithExeption('CrashGaming checkPlay - FATAL ERROR', $this->provider_db_id, $response, KAHelper::datesent());
          return $response;
        }


        if(isset($client_response->fundtransferresponse->status->code) 
             && $client_response->fundtransferresponse->status->code == "200"){

        	ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
            $new_balance = $client_response->fundtransferresponse->balance;
            $response = [
	            "status" => true,
	            "balance" => $client_details->balance,
	            "uuid" =>  $client_details->player_token,
	        ];
            $updateTransactionEXt = array(
                "provider_request" =>json_encode($data),
                "mw_response" => json_encode($response),
                'mw_request' => json_encode($client_response->requestoclient),
                'client_response' => json_encode($client_response),
                'transaction_detail' => json_encode($response),
                'general_details' => json_encode($general_details),
            );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
            $updateGameTransaction = [
                "pay_amount" => $pay_amount,
                "income" =>  $income,
                "win" => $win_or_lost,
                "entry_id" => $entry_id,
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
	        return $response;

       	}else{

       		$updateGameTransaction = ["win" => 2,'trans_status' => 5];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
			$response = ["status" => "Insuffecient Fund", "uuid" =>  $client_details->player_token];
            $updateTransactionEXt = array(
                "provider_request" =>json_encode($data),
                "mw_response" => json_encode($response),
                'mw_request' => json_encode($client_response->requestoclient),
                'client_response' => json_encode($client_response),
                'transaction_detail' => 'FAILED',
                'general_details' => json_encode($general_details),
            );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
            return $response;
            
       	}
	}



    public function Credit(Request $request){
        $request_body = $request->getContent();
        $data = json_decode($request_body);

        if ($request->header('AuthToken') != $this->authToken){
            return  $response = ["status" => "AuthToken Mismatched", "uuid" =>  $data->player_token];
        }

        $client_details = ProviderHelper::getClientDetails('player_id',$data->userId);
        if($client_details == 'false'){
            return  $response = ["status" => "Insufficient Fund", "uuid" =>  $data->player_token];
        }

        $provider_trans_id = $data->uuid; // for checking idenpotent use this!
        $round_id = $data->transaction->refId;
        $game_code = $data->game->id;
        $game_transaction_type = 1;
        $token_id = $client_details->token_id;
        $win_amount = $data->transaction->amount;
        $pay_amount =  0;
        $method = 1;
        $income = $win_amount - $pay_amount;
        $entry_id = 1;
        $payout_reason = 'settled';

        $general_details['client']['before_balance'] = $client_details->balance;

        $game_information = ProviderHelper::findGameDetails('game_code', $this->provider_db_id, $game_code);
        if($game_information == null){ 
            return  $response = ["status" => "Game Not Found", "statusCode" =>  1];
        }

        $game_ext_check = GameTransactionMDB::findGameExt($round_id, 1,'round_id', $client_details);
        if($game_ext_check != 'false'){ // Duplicate transaction
            dd("DUPLICATE NA CHOY!");
            $response = ["status" => "No Bet Exist", "uuid" =>  $client_details->player_token];
            return $response;
        }

         # NEW FLOW WIN
         try {
            ProviderHelper::_insertOrUpdate($client_details->token_id, $client_details->balance+abs($win_amount));
            $new_balance = $client_details->balance+abs($win_amount);
            $response = [
                "balance" => $this->formatBalance($new_balance),
                "status" => "success",
                "statusCode" =>  0
            ];

            if($pay_amount > 0){
                $entry_id = 2; // Credit
                $win_or_lost = 1; // 0 lost,  5 processing
            }else{
                $entry_id = 1; // Debit
                $win_or_lost = 0; // 0 lost,  5 processing
            }

            $gameTransactionEXTData = array(
                "game_trans_id" => $gamerecord,
                "provider_trans_id" => $provider_trans_id,
                "round_id" => $round_id,
                "amount" => abs($win_amount),
                "game_transaction_type"=> 2,
                "provider_request" => json_encode($data),
                "transaction_detail" => json_encode($response),
                "mw_response" => json_encode($response),
                "general_details" => json_encode($general_details)
            );
            $game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "game_transaction_ext_id" => $game_transextension,
                    "client_connection_name" => $client_details->connection_name,
                    "provider" => $this->provider_name,
                    "win_or_lost" => $win_or_lost,
                    "entry_id" => $entry_id,
                    "pay_amount" => $pay_amount,
                    "income" => $income,
                    "is_multiple" => false,
                ],
                "provider" => [
                    "provider_request" => $data, #R
                    "provider_trans_id"=> $provider_trans_id, #R
                    "provider_round_id"=> $round_id, #R
                    "provider_name" => $game_information->provider_name,
                ],
                "mwapi" => [
                    "roundId"=>$gamerecord, #R
                    "type"=>2, #R
                    "game_id" => $game_information->game_id, #R
                    "player_id" => $client_details->player_id, #R
                    "mw_response" => $response, #R
                ],
                'fundtransferrequest' => [
                    'fundinfo' => [
                        'freespin' => true,
                    ]
                ]
            ];
            $client_response2 = ClientRequestHelper::fundTransfer_TG($client_details,abs($win_amount),$game_information->game_code,$game_information->game_name,$gamerecord,'credit',false,$action_payload);

            ProviderHelper::_insertOrUpdate($client_details->token_id, $new_balance);
            return $response;
        } catch (\Exception $e) {
            return $e->getMessage().' '.$e->getLine().' '.$e->getFile();
        }
        # END NEW FLOW WIN  
    }


    public function Refund(Request $request){
        $request_body = $request->getContent();
        $data = json_decode($request_body);

        if ($request->header('AuthToken') != $this->authToken){
            return  $response = ["status" => "AuthToken Mismatched", "uuid" =>  $data->player_token];
        }

        $client_details = ProviderHelper::getClientDetails('player_id',$data->userId);
        if($client_details == 'false'){
            return  $response = ["status" => "Insufficient Fund", "uuid" =>  $data->player_token];
        }

        $provider_trans_id = $data->uuid; // for checking idenpotent use this!
        $round_id = $data->transaction->refId;
        $game_code = $data->game->id;
        $game_transaction_type = 3;
        $token_id = $client_details->token_id;
        $refund_amount = $data->transaction->amount;
        $pay_amount =  $data->transaction->amount;
        $method = 1;
        $win_or_lost = 1;
        $income = $refund_amount + $pay_amount;
        $entry_id = 1;
        $payout_reason = 'settled';

        // $general_details['client']['before_balance'] = $client_details->balance;

        $game_information = ProviderHelper::findGameDetails('game_code', $this->provider_db_id, $game_code);
        if($game_information == null){ 
            return  $response = ["status" => "Game Not Found", "statusCode" =>  1];
        }

        $game_ext_check = GameTransactionMDB::findGameExt($round_id, 1,'round_id', $client_details);
        if($game_ext_check == 'false'){ // Duplicate transaction
            $response = ["status" => "No Bet Exist", "uuid" =>  $client_details->player_token];
            return $response;
        }

        $refund_ext_check = GameTransactionMDB::findGameExt($provider_trans_id, 3,'transaction_id', $client_details);
        if($refund_ext_check != 'false'){ // Duplicate transaction
            $response = ["status" => "Duplicate", "uuid" =>  $client_details->player_token];
            return $response;
        }

        $gamerecord = $game_ext_check->game_trans_id;
        $gameTransactionEXTData = array(
            "game_trans_id" => $gamerecord,
            "provider_trans_id" => $provider_trans_id,
            "round_id" => $round_id,
            "amount" => $refund_amount,
            "game_transaction_type"=> $game_transaction_type,
            "provider_request" =>json_encode($data),
        );
        $game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

        try {
            $client_response = ClientRequestHelper::fundTransfer($client_details,$refund_amount,$game_information->game_code,$game_information->game_name,$game_transextension,$game_ext_check->game_trans_id, 'credit');
            ProviderHelper::saveLogWithExeption('CrashGaming Refund', $this->provider_db_id, json_encode($data), $client_response);
        } catch (\Exception $e) {
            $response = ["status" => "request failed error encouter", "uuid" =>  $client_details->player_token];
            $updateTransactionEXt = array(
                "provider_request" =>json_encode($data),
                "mw_response" => json_encode($response),
                'client_response' => json_encode($e->getMessage() ." ". $e->getLine()),
            );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
            return $response;
        }

        if(isset($client_response->fundtransferresponse->status->code) 
             && $client_response->fundtransferresponse->status->code == "200"){

            ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
            $new_balance = $client_response->fundtransferresponse->balance;
            $response = [
                "status" => true,
                "balance" => $client_details->balance,
                "uuid" =>  $client_details->player_token,
            ];
            $updateTransactionEXt = array(
                "provider_request" =>json_encode($data),
                "mw_response" => json_encode($response),
                'mw_request' => json_encode($client_response->requestoclient),
                'client_response' => json_encode($client_response),
            );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
            $updateGameTransaction = [
                "pay_amount" => $pay_amount,
                "income" =>  $income,
                "win" => $win_or_lost,
                "entry_id" => $entry_id,
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
            return $response;

        }else{
            $updateGameTransaction = ["win" => 2,'trans_status' => 5];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
            $response = ["status" => "Insuffecient Fund", "uuid" =>  $client_details->player_token];
            $updateTransactionEXt = array(
                "provider_request" =>json_encode($data),
                "mw_response" => json_encode($response),
                'mw_request' => json_encode($client_response->requestoclient),
                'client_response' => json_encode($client_response),
                'transaction_detail' => 'FAILED',
            );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
            return $response;
        }

    }

    public function Cancel(Request $request){
        $request_body = $request->getContent();
        $data = json_decode($request_body);

        if ($request->header('AuthToken') != $this->authToken){
            return  $response = ["status" => "AuthToken Mismatched", "uuid" =>  $data->player_token];
        }

        $client_details = ProviderHelper::getClientDetails('player_id',$data->userId);
        if($client_details == 'false'){
            return  $response = ["status" => "Insufficient Fund", "uuid" =>  $data->player_token];
        }

        $provider_trans_id = $data->uuid; // for checking idenpotent use this!
        $round_id = $data->transaction->refId;
        $game_code = $data->game->id;
        $game_transaction_type = 3;
        $token_id = $client_details->token_id;
        $cancel_amount = $data->transaction->amount;
        $pay_amount =  $data->transaction->amount;
        $method = 1;
        $win_or_lost = 1;
        $income = $cancel_amount - $pay_amount;
        $entry_id = 1;
        $payout_reason = 'settled';

        // $general_details['client']['before_balance'] = $client_details->balance;

        $game_information = ProviderHelper::findGameDetails('game_code', $this->provider_db_id, $game_code);
        if($game_information == null){ 
            return  $response = ["status" => "Game Not Found", "statusCode" =>  1];
        }

        $game_ext_check = GameTransactionMDB::findGameExt($round_id, 1,'round_id', $client_details);
        if($game_ext_check == 'false'){ // Duplicate transaction
            $response = ["status" => "No Bet Exist", "uuid" =>  $client_details->player_token];
            return $response;
        }

        $refund_ext_check = GameTransactionMDB::findGameExt($provider_trans_id, 3,'transaction_id', $client_details);
        if($refund_ext_check != 'false'){ // Duplicate transaction
            $response = ["status" => "Duplicate", "uuid" =>  $client_details->player_token];
            return $response;
        }

        $gamerecord = $game_ext_check->game_trans_id;
        $gameTransactionEXTData = array(
            "game_trans_id" => $gamerecord,
            "provider_trans_id" => $provider_trans_id,
            "round_id" => $round_id,
            "amount" => $cancel_amount,
            "game_transaction_type"=> $game_transaction_type,
            "provider_request" =>json_encode($data),
        );
        $game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

        try {
            $client_response = ClientRequestHelper::fundTransfer($client_details,$cancel_amount,$game_information->game_code,$game_information->game_name,$game_transextension,$game_ext_check->game_trans_id, 'credit');
            ProviderHelper::saveLogWithExeption('CrashGaming Refund', $this->provider_db_id, json_encode($data), $client_response);
        } catch (\Exception $e) {
            $response = ["status" => "request failed error encouter", "uuid" =>  $client_details->player_token];
            $updateTransactionEXt = array(
                "provider_request" =>json_encode($data),
                "mw_response" => json_encode($response),
                'client_response' => json_encode($e->getMessage() ." ". $e->getLine()),
            );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
            return $response;
        }

        if(isset($client_response->fundtransferresponse->status->code) 
             && $client_response->fundtransferresponse->status->code == "200"){

            ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
            $new_balance = $client_response->fundtransferresponse->balance;
            $response = [
                "status" => true,
                "balance" => $client_details->balance,
                "uuid" =>  $client_details->player_token,
            ];
            $updateTransactionEXt = array(
                "provider_request" =>json_encode($data),
                "mw_response" => json_encode($response),
                'mw_request' => json_encode($client_response->requestoclient),
                'client_response' => json_encode($client_response),
            );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
            $updateGameTransaction = [
                "pay_amount" => $pay_amount,
                "income" =>  $income,
                "win" => $win_or_lost,
                "entry_id" => $entry_id,
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
            return $response;

        }else{
            $updateGameTransaction = ["win" => 2,'trans_status' => 5];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
            $response = ["status" => "Insuffecient Fund", "uuid" =>  $client_details->player_token];
            $updateTransactionEXt = array(
                "provider_request" =>json_encode($data),
                "mw_response" => json_encode($response),
                'mw_request' => json_encode($client_response->requestoclient),
                'client_response' => json_encode($client_response),
                'transaction_detail' => 'FAILED',
            );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
            return $response;
        }


    }
}