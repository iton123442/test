<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ProviderHelper;
use App\Helpers\TransferWalletHelper;
use App\Helpers\Helper;
use App\Helpers\SessionWalletHelper;
use App\Helpers\ClientRequestHelper;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use Carbon\Carbon;
use DB;


/**
 * CronTab will run every 30seconds and update all session_time deduct it with 30seconds
 * Playbetrnk - FrontEnd will try to renew its session every 30 seconds, using JavaScipt
 * 
 */
class TransferWalletController extends Controller
{

	/**
	 * PLAYER ACTUAL BALANCE
	 */
	public function getPlayerBalance(Request $request){
		$client_details = TransferWalletHelper::getClientDetails('token', $request->token);
		if ($client_details == 'false') {
			$msg = ["status" => "error", "message" => "Invalid Token or Token not found"];
			TransferWalletHelper::saveLog('TransferWallet getPlayerBalance FAILED', 666666, json_encode($request->all()), $msg);
			return response($msg, 200)->header('Content-Type', 'application/json');
		}

		$player_details = ProviderHelper::clientPlayerDetailsCall($client_details);
		if($player_details != 'false'){
			$msg = ["status" => "ok","message" => "Balance Request Success","balance" => $player_details->playerdetailsresponse->balance];
		}else{
			TransferWalletHelper::saveLog('TransferWallet Failed Player Details', 666666, json_encode($request->all()), $msg);
			$msg = ["status" => "error", "message" => "Invalid Token or Token not found"];
		}
		return response($msg, 200)->header('Content-Type', 'application/json');
	}

	/**
	 * DEPOSIT BALANCE
	 */
	public function getPlayerWalletBalance(Request $request){
		$client_details = TransferWalletHelper::getClientDetails('token', $request->token);
		if ($client_details == 'false') {
			$msg = ["status" => "error", "message" => "Invalid Token or Token not found"];
			TransferWalletHelper::saveLog('TransferWallet TransferOut FAILED', 666666, json_encode($request->all()), $msg);
			return response($msg, 200)->header('Content-Type', 'application/json');
		}

		$session = SessionWalletHelper::hasWalletSession($client_details->player_id);
		if (!$session) {
			$msg = ["status" => "error","message" => "No Session","balance" => 0];
			return response($msg, 200)->header('Content-Type', 'application/json');
		}

		$msg = ["status" => "ok","message" => "Balance Request Success","balance" => $client_details->tw_balance];
		return response($msg, 200)->header('Content-Type', 'application/json');
	}

	public function makeDeposit(Request $request){
		 $data = $request->all();
		 $game_details = TransferWalletHelper::getInfoPlayerGameRound($request->token);
		 if ($game_details == false) {
			 $msg = ["status" => "error", "message" => "Game Not Found"];
			 TransferWalletHelper::saveLog('TransferWallet TransferOut FAILED', 666666, json_encode($request->all()), $msg);
			 return response($msg, 200)->header('Content-Type', 'application/json');
		 }
 
		 $client_details = TransferWalletHelper::getClientDetails('token', $request->token);
		 if ($client_details == 'false') {
			 $msg = ["status" => "error", "message" => "Invalid Token or Token not found"];
			 TransferWalletHelper::saveLog('TransferWallet TransferOut FAILED', 666666, json_encode($request->all()), $msg);
			 return response($msg, 200)->header('Content-Type', 'application/json');
		 }
 
		 # Check Multiple user Session
		 $session_count = SessionWalletHelper::isMultipleSession($client_details->player_id, $request->token);
		 if ($session_count) {
			 $msg = ["status" =>'error', "message" => "Multiple Session Detected!"]; 
			 return response($msg, 200)->header('Content-Type', 'application/json');
		 }
 
		 if(!is_numeric($request->amount)){
			$msg = ["status" =>'error', "message" => "Undefined Amount!"]; 
			TransferWalletHelper::saveLog('TransferIn Undefined Amount', 666666,json_encode($request->all()), $msg);
			return response($msg,200)->header('Content-Type', 'application/json');
		 }
		 
		//  $player_details->playerdetailsresponse->balance

		 $player_details = ProviderHelper::clientPlayerDetailsCall($client_details);
		 if($player_details == 'false'){
			TransferWalletHelper::saveLog('TransferWallet TransferIn Failed Player Details', 666666, json_encode($request->all()), $msg);
			$msg = ["status" => "error", "message" => "Invalid Token or Token not found"];
		 }

		 if(abs($player_details->playerdetailsresponse->balance) < abs($request->amount)){
			$msg = ["status" =>'error', "message" => "Insufficient funds"]; 
			TransferWalletHelper::saveLog('TransferIn Insufficient funds', 666666,json_encode($request->all()), $msg);
			return response($msg,200)->header('Content-Type', 'application/json');
		 }
 
		 $json_data = array(
			 "transid" => $request->token,
			 "amount" => $request->amount,
			 "roundid" => 0,
		 );
 
		 $token_id = $client_details->token_id;
		 $bet_amount = $request->amount;
		 $pay_amount = 0;
		 $win_or_lost = 0;
		 $method = 1; 
		 $payout_reason = 'Transfer IN Debit';
		 $income = $bet_amount - $pay_amount;
		 $round_id = $json_data['roundid'];
		 $provider_trans_id = $json_data['transid'];
		 $game_transaction_type = 1;
 
		 TransferWalletHelper::saveLog('TransferIn', 666666,json_encode($request->all()), 'Golden IF HIT');
		 $game = TransferWalletHelper::getGameTransaction($request->token,$json_data["roundid"]);
		 if(!$game){
			 $gamerecord = TransferWalletHelper::createGameTransaction('debit', $json_data, $game_details, $client_details); 
		 }
		 else{
			 $gameupdate = TransferWalletHelper::updateGameTransaction($game,$json_data,"debit");
			 $gamerecord = $game->game_trans_id;
		 }

		 $game_transextension = TransferWalletHelper::createGameTransExtV2($gamerecord,$provider_trans_id, $round_id, $bet_amount, $game_transaction_type,$data);
		 try {
			TransferWalletHelper::saveLog('TransferIn fundTransfer', 666666,json_encode($request->all()), 'Client Request');
			 // $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount,$game_details->game_code,$game_details->game_name,$game_transextension,$gamerecord,"debit");
			$client_response = ClientRequestHelper::fundTransfer($client_details,$request->amount,$game_details->game_code,$game_details->game_name,$game_transextension,$gamerecord,"debit");
			TransferWalletHelper::saveLog('TransferIn fundTransfer', 666666,json_encode($request->all()), 'Client Responsed');
		  } catch (\Exception $e) {
			  $response = ["status" => "error", 'message' => $e->getMessage()];
			  if(isset($gamerecord)){
				  TransferWalletHelper::updateGameTransactionStatus($gamerecord, 2, 99);
				  TransferWalletHelper::updatecreateGameTransExt($game_transextension, 'FAILED', json_encode($response), 'FAILED', $e->getMessage(), 'FAILED', 'FAILED');
			  }
			  TransferWalletHelper::saveLog('TransferIn Failed FATAL ERROR', 666666,json_encode($request->all()), $response);
			  return $response;
		  }

		  if(isset($client_response->fundtransferresponse->status->code) && $client_response->fundtransferresponse->status->code == "200"){
			# TransferWallet
			$token = SessionWalletHelper::checkIfExistWalletSession($request->token);
			if ($token == false) { // This token doesnt exist in wallet_session
				SessionWalletHelper::createWalletSession($request->token, $request->all());
			}

			SessionWalletHelper::updateSessionTime($request->token);
			$this->makeDepositOrWithdraw('deposit',$client_details->player_id, $request->amount);
			$msg = array(
				"status" => "ok",
				"message" => "Transaction success",
				"balance" => round($client_details->tw_balance,2)
			);

			TransferWalletHelper::updatecreateGameTransExt($game_transextension, $data, $msg, $client_response->requestoclient, $client_response, $msg, 'NO DATA');
		  }elseif(isset($client_response->fundtransferresponse->status->code) && $client_response->fundtransferresponse->status->code == "402"){
			TransferWalletHelper::saveLog('TransferIn fundTransfer Failed', 666666,json_encode($request->all()), '402');
            $msg = ["status" => 'error',"message" => "Insufficient funds"]; 
            return response($msg,200)->header('Content-Type', 'application/json');
          }

		// $game_transextension = TransferWalletHelper::createGameTransExtV2($gamerecord,$provider_trans_id, $round_id, $bet_amount, $game_transaction_type,$data,$request->all(), $request->all(), $msg, $msg, 'SUCCESS');
		return response($msg,200)->header('Content-Type', 'application/json');
	
	}


	public function makeWithdraw(Request $request){
		TransferWalletHelper::saveLog('TransferOut Success', 666666,json_encode($request->all()), 'Closed triggered');
        $data = $request->all();
        $game_details = TransferWalletHelper::getInfoPlayerGameRound($request->token);
        if ($game_details == false) {
            $msg = array("status" => "error", "message" => "Game Not Found");
            TransferWalletHelper::saveLog('TransferWallet TransferOut FAILED', 666666, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }

        $json_data = array(
            "transid" => "GFTID".Carbon::now()->timestamp,
            // "amount" => $request->amount,
            "roundid" => 0,
        );

        $client_details = TransferWalletHelper::getClientDetails('token', $request->token);
        if ($client_details == 'false') {
            $msg = array("status" => "error", "message" => "Invalid Token or Token not found");
            TransferWalletHelper::saveLog('TransferWallet TransferOut FAILED', 666666, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }

        if($request->has("token")&&$request->has("player_id")){
                $client_details = TransferWalletHelper::getClientDetails('token', $request->token);
				$game_details = TransferWalletHelper::getInfoPlayerGameRound($request->token);
				
				if($client_details->tw_balance != 0){
					$TransferOut_amount = $client_details->tw_balance;
				}else{
					$response = ["status" => "error", 'message' => 'cant connect'];
					return $response;
				}

                $json_data = array(
                    "transid" => Carbon::now()->timestamp,
                    "amount" => $TransferOut_amount,
                    "roundid" => 0,
                    "win"=>1,
                    "payout_reason" => "TransferOut from round",
				);
				
                $game = TransferWalletHelper::getGameTransaction($request->token,$json_data["roundid"]);
                if($game){
                    $gamerecord = $game->game_trans_id;
                }else{
                    SessionWalletHelper::deleteSession($request->token);
                    $response = ["status" => "error", 'message' => 'No Transaction Recorded'];
                    TransferWalletHelper::saveLog('TransferOut Failed', 666666,json_encode($request->all()), $response);
                    return $response;
				}

				$token_id = $client_details->token_id;
                $bet_amount = $game->bet_amount;
                $pay_amount = $TransferOut_amount;
                $win_or_lost = 1;
                $method = 2; 
                $payout_reason = 'TransferOut Credit';
                $income = $bet_amount - $pay_amount;
                $round_id = $json_data['roundid'];
                $provider_trans_id = $json_data['transid'];
                $game_transaction_type = 2;

				try {
					$game_transextension = TransferWalletHelper::createGameTransExtV2($gamerecord,$provider_trans_id, $round_id, $bet_amount, $game_transaction_type, $data);
					$client_response = ClientRequestHelper::fundTransfer($client_details,$TransferOut_amount,$game_details->game_code,$game_details->game_name,$game_transextension,$gamerecord,"credit");
				   TransferWalletHelper::saveLog('GoldenF TransferOut Client Request', 666666,json_encode($request->all()), 'Request to client');
				} catch (\Exception $e) {
					$response = ["status" => "error", 'message' => $e->getMessage()];
				   TransferWalletHelper::saveLog('GoldenF TransferOut client_response failed', 666666,json_encode($request->all()), $response);
					return $response;
				}

				if(isset($client_response->fundtransferresponse->status->code) && $client_response->fundtransferresponse->status->code == "200"){
					$msg = array(
						"status" => "ok",
						"message" => "Transaction success",
						"balance"   =>  round($client_response->fundtransferresponse->balance,2)
					);
					$this->makeDepositOrWithdraw('withdraw',$client_details->player_id);
					$gameupdate = TransferWalletHelper::updateGameTransaction($game,$json_data,"credit");
					TransferWalletHelper::updatecreateGameTransExt($game_transextension, $data, $msg, $client_response->requestoclient, $client_response, $msg, 'SUCCESS');
					SessionWalletHelper::deleteSession($request->token);
					return response($msg,200)
						->header('Content-Type', 'application/json');
				}else{
					$msg = array(
						"status" => "error",
						"message" => "Transaction Failed Unknown Client Response",
					);
					return response($msg,200)->header('Content-Type', 'application/json');
				}

				return response($msg,200)->header('Content-Type', 'application/json');
            
        }else{
			$response = ["status" => "error", 'message' => 'No Transaction Recorded'];
			return response($response,200)->header('Content-Type', 'application/json');
		}
	}

	/**
	 * $type = withdraw|deposit
	 * 
	 */
	public function makeDepositOrWithdraw($type,$player_id, $balance=0){
		if($type == 'deposit'){
			$client_details = ProviderHelper::getClientDetails('player_id', $player_id);
			$balance = abs($client_details->tw_balance)+$balance;
			$update = DB::table('players')->where('player_id', $player_id)->update(['balance' => $balance]);
		}else{
			$balance = 0;
			$update = DB::table('players')->where('player_id', $player_id)->update(['balance' => $balance]);
		}
		return ($update ? true : false);
	}


	/**
	 * Transfer Waller and Semi Transfer Wallet
	 * [updateSession - update set session to default $session_time]
	 * 
	 */
    public function createWalletSession(Request $request){

    	$data = $request->all();
    	if($request->has('token')){

    		$token = SessionWalletHelper::checkIfExistWalletSession($request->token);
            if($token == false){
                SessionWalletHelper::createWalletSession($request->token, $request->all());
            }else{
            	SessionWalletHelper::updateSessionTime($request->token);
            }

    	}
    	$response = ["status" => "success", 'message' => 'Session Updated!'];
    	SessionWalletHelper::saveLog('TW updateSession', 1223, json_encode($data), 1223);
    	return $response;
    }


	/**
	 * Transfer Waller and Semi Transfer Wallet
	 * [updateSession - update set session to default $session_time]
	 * 
	 */
    public function renewSession(Request $request){

    	$data = $request->all();
    	if($request->has('token')){
    		SessionWalletHelper::updateSessionTime($request->token);
    	}
    	$response = ["status" => "error", 'message' => 'Success Renew!'];
    	SessionWalletHelper::saveLog('TW updateSession', 1223, json_encode($data), 1223);
    	return $response;
    }


	/**
	 * Transfer Waller and Semi Transfer Wallet
	 * [updateSession - deduct all session time with $time_deduction]
	 * 
	 */
    public function updateSession(){
    	try {
    		SessionWalletHelper::deductSession();
    		$this->withdrawAllExpiredWallet();
    		SessionWalletHelper::saveLog('TW updateSession', 1223, json_encode(['msg'=>'success']), 1223);
    	} catch (\Exception $e) {
    		SessionWalletHelper::saveLog('TW updateSession Failed', 1223, json_encode(['msg'=>$e->getMesage()]), 1223);
    	}
    }


    /**
	 * Transfer Waller and Semi Transfer Wallet
	 * [withdrawAllExpiredWallet - withdraw all 0 or negative session_time]
	 * Frondend Player Failed to renew it session considered expired
	 * 
	 */
    public function withdrawAllExpiredWallet(){
    	try {
    		$wallet_session = DB::select('SELECT * FROM wallet_session WHERE session_time <= 0');
    		if(count($wallet_session) > 0){
				foreach ($wallet_session as $key) {
    				$metadata = json_decode($key->metadata);
	    			try {
	    				$http = new Client();
				        $response = $http->post($metadata->callback_transfer_out, [
				            'form_params' => [
				                'token' => $metadata->token,
				                'player_id'=> $metadata->player_id,
				            ],
				            'headers' =>[
				                'Authorization' => 'Bearer '.SessionWalletHelper::tokenizer(),
				                'Accept'     => 'application/json'
				            ]
				        ]);
				        SessionWalletHelper::deleteSession($metadata->token);
				        SessionWalletHelper::saveLog('TW withdrawAllExpiredWallet Success', 1223, json_encode([count($wallet_session)]), 'WITHDRAW EXPIRED SUCCESS');
	    			} catch (\Exception $e) {
						SessionWalletHelper::saveLog('TW withdrawAllExpiredWallet Success', 1223, json_encode([count($wallet_session)]), $e->getMessage());
	    				continue;
	    			}
				}
    		}
    	} catch (\Exception $e) {
    		SessionWalletHelper::saveLog('TW withdrawAllExpiredWallet Failed', 1223, json_encode([count($wallet_session)]), $e->getMessage());
    	}
    }


}
