<?php

namespace App\Http\Controllers\Ozashiki;

use Illuminate\Http\Request;
use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Helpers\ClientRequestHelper;
use App\Helpers\TransactionHelper;
// use App\Models\GameTransaction;
use App\Helpers\GameTransaction;
use App\Helpers\GameRound;
use App\Helpers\Game;
use DB;

trait Seamless 
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

		$game_transaction_id = GameTransaction::save('debit', $json_data, $game_details, $client_details, $client_details);

		$game_trans_ext_id = ProviderHelper::createGameTransExtV2($game_transaction_id, $json_data['transaction_id'], $json_data['round_id'], $json_data['amount'], 1);

		// change $json_data['round_id'] to $game_transaction_id
        $client_response = ClientRequestHelper::fundTransfer($client_details, $json_data['amount'], $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, 'debit');
		
		if (isset($client_response->fundtransferresponse->status->code)) {

			switch ($client_response->fundtransferresponse->status->code) {
				case '200':
					ProviderHelper::updateGameTransactionStatus($game_transaction_id, 5, 5);
					ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
					$http_status = 200;
					$response = [
						"transaction_id" => $json_data['transaction_id'],
						"balance" => bcdiv($client_response->fundtransferresponse->balance, 1, 2) 
					];
					break;
				case '402':
					ProviderHelper::updateGameTransactionStatus($game_transaction_id, 2, 99);
					$http_status = 200;
					$response = [
						"errorCode" =>  10203,
						"message" => "Insufficient balance",
					];
					break;
			}

			ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, $json_data, $response, $client_response->requestoclient, $client_response, $json_data);

		}
			
		Helper::saveLog('ozashiki_debit', $this->provider_db_id, json_encode($json_data), $response);
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
			$bet_transaction = ProviderHelper::findGameTransaction($json_data['round_id'], 'round_id',1);
			
			$winbBalance = $client_details->balance + $json_data["amount"];
			ProviderHelper::_insertOrUpdate($client_details->token_id, $winbBalance); 
			$response = [
					"transaction_id" => $json_data['transaction_id'],
					"balance" => bcdiv($winbBalance, 1, 2) 
				];
			
            $win_or_lost = $json_data["amount"] > 0 ?  1 : 0;
            $entry_id = $json_data["amount"] > 0 ?  2 : 1;
           	$income = $bet_transaction->bet_amount -  $json_data["amount"] ;

			$action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "provider" => 'Ozashiki',
                    "win_or_lost" => $win_or_lost,
                    "entry_id" => $entry_id,
                    "pay_amount" => $json_data["amount"],
                    "income" => $income,
                ],
                "provider" => [
                    "provider_request" => $json_data, #R
                    "provider_trans_id"=> $json_data['transaction_id'], #R
                    "provider_round_id"=> $json_data['round_id'], #R
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
            $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$json_data["amount"],$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',false,$action_payload);

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

		$game_transaction = ProviderHelper::findGameTransaction($json_data['target_transaction_id'],'transaction_id', 1);
		
		$response = [
			"errorCode" =>  10210,
			"message" => "Target transaction id not found!",
		];

		if ($game_transaction != 'false') {
			$game_details = Game::find($json_data["game_id"], $this->provider_db_id);
			
			$rollbackbBalance = $client_details->balance + $game_transaction->bet_amount;
			ProviderHelper::_insertOrUpdate($client_details->token_id, $rollbackbBalance); 
			$response = [
				"transaction_id" => $json_data['transaction_id'],
				"balance" => bcdiv($rollbackbBalance, 1, 2) 
			];

			$win_or_lost = 4;
            $entry_id = 2;
           	$income = $game_transaction->bet_amount -  $game_transaction->bet_amount ;

			$action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "provider" => 'Ozashiki',
                    "win_or_lost" => $win_or_lost,
                    "entry_id" => $entry_id,
                    "pay_amount" => $game_transaction->bet_amount,
                    "income" => $income,
                ],
                "provider" => [
                    "provider_request" => $json_data, #R
                    "provider_trans_id"=> $json_data['transaction_id'], #R
                    "provider_round_id"=> $json_data['round_id'], #R
                ],
                "mwapi" => [
                    "roundId"=>$game_transaction->game_trans_id, #R
                    "type"=>3, #R
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
            $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$game_transaction->bet_amount,$game_details->game_code,$game_details->game_name,$game_transaction->game_trans_id,'credit',true,$action_payload);
		}
		Helper::saveLog('ozashiki_rollback', $this->provider_db_id, json_encode($json_data), $response);
		return $response;
	}

}