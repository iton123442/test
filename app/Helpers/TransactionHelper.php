<?php
namespace App\Helpers;
use DB;
use GuzzleHttp\Client;
use Carbon\Carbon;

class TransactionHelper
{

    public static function checkGameTransactionData($provider_transaction_id){
		$game = DB::select("SELECT game_trans_ext_id,mw_response
        FROM game_transaction_ext
        where provider_trans_id='".$provider_transaction_id."' limit 1");
		return $game;
    }
    public static function getGameTransaction($player_token,$game_round){
		$game = DB::select("SELECT
						entry_id,bet_amount,game_trans_id,pay_amount
						FROM game_transactions g
						INNER JOIN player_session_tokens USING (token_id)
						WHERE player_token = '".$player_token."' and round_id = '".$game_round."'");
		return $game;
    }
    public static function updateGameTransaction($existingdata,$request_data,$type){
		switch ($type) {
			case "debit":
					$trans_data["win"] = 0;
					$trans_data["pay_amount"] = 0;
					$trans_data["income"]=$existingdata[0]->bet_amount-$request_data["amount"];
					$trans_data["entry_id"] = 1;
				break;
			case "credit":
					$trans_data["win"] = $request_data["win"];
					$trans_data["pay_amount"] = abs($request_data["amount"]);
					$trans_data["income"]=$existingdata[0]->bet_amount-$request_data["amount"];
					$trans_data["entry_id"] = 2;
					$trans_data["payout_reason"] = $request_data["payout_reason"];
				break;
			case "refund":
					$trans_data["win"] = 4;
					$trans_data["pay_amount"] = $request_data["amount"];
					$trans_data["entry_id"] = 2;
					$trans_data["income"]= $existingdata[0]->bet_amount-$request_data["amount"];
					$trans_data["payout_reason"] = "Refund of this transaction ID: ".$request_data["transid"]."of GameRound ".$request_data["roundid"];
				break;
			case "fail":
				$trans_data["win"] = 2;
				$trans_data["pay_amount"] = $request_data["amount"];
				$trans_data["entry_id"] = 1;
				$trans_data["income"]= 0;
				$trans_data["payout_reason"] = "Fail  transaction ID: ".$request_data["transid"]."of GameRound ".$request_data["roundid"] .":Insuffecient Balance";
			break;
			default:
		}
		/*var_dump($trans_data); die();*/
		return DB::table('game_transactions')->where("game_trans_id",$existingdata[0]->game_trans_id)->update($trans_data);
	}
	public static function CheckTransactionToClient($client_details,$transactionID,$roundID){
		$client = new Client([
			'headers' => [ 
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer '.$client_details->client_access_token,
			]
		]);
		$check_transaction = [
			"access_token" => $client_details->client_access_token,
			"hashkey" => md5($client_details->client_api_key.$client_details->client_access_token),
			"player_username" => $client_details->username,
			"client_player_id " => $client_details->client_player_id,
			"transactionId" => $transactionID,
			"roundId" => $roundID,
		];
		try{
			$check_transaction_response = $client->post($client_details->transaction_checker_url,
                    [
                        'body' => json_encode($check_transaction)
                    ],
                    ['defaults' => ['exceptions' => false]]
                );
			$checker_responser = json_decode($check_transaction_response->getBody()->getContents());
			// if client responded a 200 transaction success then response will be true
			if(isset($checker_responser)&& $checker_responser->code == 200){
				return true;
			}
			// if client responded a 404 transaction not found thenresponse will be false
			elseif(isset($checker_responser)&& $checker_responser->code == 404){
				return false;
			}
			// if client responded other than 200 and 404 then  the response will be false
			else{
				return false;
			}

		}catch(\Exception $e){
			return false;
		}
	}
}
?>