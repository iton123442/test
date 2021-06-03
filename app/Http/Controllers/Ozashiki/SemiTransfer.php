<?php

namespace App\Http\Controllers\Ozashiki;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Helpers\ClientRequestHelper;
use App\Helpers\TransactionHelper;
use App\Models\GameTransaction;
use App\Models\TransferWalletGameTransaction;
use App\Helpers\Game;
use DB;

trait SemiTransfer 
{
	public function debitProcess($json_data,$client_details) 
	{
		
		try{
			ProviderHelper::idenpotencyTable($json_data['round_id']);
		}catch(\Exception $e){
			$response = [
				"errorCode" =>  10209,
				"message" => "Round id is exists!",
			];
			return $response;
		}

		$response = [
			"errorCode" =>  10100,
			"message" => "Server is not ready!",
		];

		$json_data['income'] = $json_data['amount'];
		$json_data['roundid'] = $json_data['round_id'];
		$json_data['transid'] = $json_data['transaction_id'];
		$game_details = Game::find($json_data["game_id"], $this->provider_db_id);

		$gameTransactionData = array(
            "provider_trans_id" => $json_data['transaction_id'],
            "token_id" => $client_details->token_id,
            "game_id" => $game_details->game_id,
            "round_id" => $json_data['round_id'],
            "bet_amount" => $json_data["amount"],
            "win" => 5,
            "pay_amount" => 0,
            "income" => 0,
            "entry_id" =>1,
        );

        $game_transactionid = TransferWalletGameTransaction::createGametransaction($gameTransactionData);

        $afterbetbalance = $client_details->tw_balance - $json_data["amount"];
		$betgametransactionext = array(
            "game_trans_id" => $game_transactionid,
            "provider_trans_id" => $json_data['transaction_id'],
            "round_id" => $json_data['round_id'],
            "amount" => $json_data["amount"],
            "game_transaction_type"=>1,
            "provider_request" =>json_encode($json_data),
        );

        $betGametransactionExtId = GameTransaction::createGameTransactionExt($betgametransactionext);

        //UPDATE PLAYER BALANCE TW
        ProviderHelper::updateTWBalance($client_details->player_id, $afterbetbalance);

		$http_status = 200;
		$response = [
			"transaction_id" => $json_data['transaction_id'],
			"balance" => bcdiv($afterbetbalance, 1, 2) 
		];
        Helper::updateBNGGameTransactionExt($betGametransactionExtId,null,$response,null);

		Helper::saveLog('ozashiki_debit_tw', $this->provider_db_id, json_encode($json_data), $response);
		return $response;
	}


	public function creditProcess($json_data, $client_details)
	{
		
		try{
			ProviderHelper::idenpotencyTable($json_data['transaction_id']);
		}catch(\Exception $e){
			$response = [
				"errorCode" =>  10208,
				"message" => "Transaction id is exists!",
			];
			return $response;
		}

		if($json_data['amount'] < 0) {
			$response = [
				"errorCode" =>  10201,
				"message" => "Warning value must not be less 0.",
			];
		}
		else
		{
			
			$game_details = Game::find($json_data["game_id"], $this->provider_db_id);
			$existing_bet = TransferWalletGameTransaction::getGameTransactionByRoundId($json_data['round_id']);

			$updateGametransactiondata = array(
                "win" => $json_data["amount"] == 0 ? 0 : 1,
                "pay_amount" => $json_data["amount"],
                "income" =>$existing_bet->bet_amount - $json_data["amount"],
                "entry_id" => $json_data["amount"] == 0 ? 1 : 2
            );
            $game_transactionid = TransferWalletGameTransaction::updateGametransaction($updateGametransactiondata,$existing_bet->tw_transaction_id);

            $wingametransactionext = array(
                "game_trans_id" => $existing_bet->tw_transaction_id,
                "provider_trans_id" => $json_data["transaction_id"],
                "round_id" => $json_data["round_id"],
                "amount" => $json_data["amount"],
                "game_transaction_type"=>2,
                "provider_request" =>json_encode($json_data),
            );
            $winGametransactionExtId = GameTransaction::createGameTransactionExt($wingametransactionext);

            
            $afterwinbalance = $client_details->tw_balance + $json_data["amount"];
            ProviderHelper::updateTWBalance($client_details->player_id, $afterwinbalance);
        	
        	$response = [
				"transaction_id" => $json_data['transaction_id'],
				"balance" => bcdiv($afterwinbalance, 1, 2) 
			];
        	
		  	Helper::updateBNGGameTransactionExt($winGametransactionExtId,null,$response,null);
		  	Helper::saveLog('ozashiki_credit TW', $this->provider_db_id, json_encode($json_data), $response);
            return $response;

		}
					
		Helper::saveLog('ozashiki_credit', $this->provider_db_id, json_encode($json_data), $response);
		return $response;

	}

	public function rollbackTransaction($json_data, $client_details){

		try{
			ProviderHelper::idenpotencyTable($json_data['transaction_id']);
		}catch(\Exception $e){
			$response = [
				"errorCode" =>  10208,
				"message" => "Transaction id is exists!",
			];
			return $response;
		}
		
		$existing_bet = TransferWalletGameTransaction::getGameTransactionByRoundId($json_data['round_id']);
		$response = [
			"errorCode" =>  10210,
			"message" => "Target transaction id not found!",
		];

		if ($existing_bet != null) {
			$game_details = Game::find($json_data["game_id"], $this->provider_db_id);
			
			$updateGametransactiondata = array(
                "win" => 4,
                "pay_amount" => $existing_bet->bet_amount,
                "income" =>$existing_bet->bet_amount - $existing_bet->bet_amount,
                "entry_id" => 2,
                "transaction_reason" => ProviderHelper::updateReason(4),
                "payout_reason" => ProviderHelper::updateReason(4)
            );

            $game_transactionid = TransferWalletGameTransaction::updateGametransaction($updateGametransactiondata,$existing_bet->tw_transaction_id);

            $wingametransactionext = array(
                "game_trans_id" => $existing_bet->tw_transaction_id,
                "provider_trans_id" => $json_data["transaction_id"],
                "round_id" => $json_data["round_id"],
                "amount" => $existing_bet->bet_amount,
                "game_transaction_type"=> 3,
                "provider_request" =>json_encode($json_data),
            );
            $winGametransactionExtId = GameTransaction::createGameTransactionExt($wingametransactionext);


			$rollbackbBalance = $client_details->tw_balance + $existing_bet->bet_amount;
			ProviderHelper::_insertOrUpdate($client_details->token_id, $rollbackbBalance); 
			$response = [
				"transaction_id" => $json_data['transaction_id'],
				"balance" => bcdiv($rollbackbBalance, 1, 2) 
			];
			Helper::updateBNGGameTransactionExt($winGametransactionExtId,null,$response,null);
			
		}
		Helper::saveLog('ozashiki_rollback', $this->provider_db_id, json_encode($json_data), $response);
		return $response;
	}
}