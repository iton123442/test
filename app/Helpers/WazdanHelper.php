<?php
namespace App\Helpers;
use DB;
use GuzzleHttp\Client;
class WazdanHelper
{
    public static function getGameTransaction($player_token,$game_round){
		$game = DB::select("SELECT
						entry_id,bet_amount,game_trans_id,pay_amount
						FROM game_transactions g
						INNER JOIN player_session_tokens USING (token_id)
						WHERE player_token = '".$player_token."' and round_id = '".$game_round."'");
		$count = count($game);
		return $count > 0 ? $game[0]:null;
    }
    public static function getGameTransactionById($game_trans_id){
        $game = DB::table("game_transactions")
                ->where("game_trans_id",$game_trans_id)
				->first();
		return $game;
    }
    public static function getTransactionExt($provider_trans_id){
        $game = DB::select("SELECT game_trans_ext_id,mw_response
        FROM game_transaction_ext
		where provider_trans_id='".$provider_trans_id."' limit 1");
		$count = count($game);
		return $count > 0 ? $game[0]:null;
    }
    
    public static function gameTransactionExtChecker($provider_trans_id){
        $game = DB::select("SELECT game_trans_ext_id,mw_response
        FROM game_transaction_ext
		where provider_trans_id='".$provider_trans_id."' limit 1");
		$count = count($game);
		return $count > 0 ? true:false;
    }
    public static function updateGameTransaction($existingdata,$request_data,$type){
		switch ($type) {
			case "debit":
                    $trans_data["win"] = 0;
                    $trans_data["bet_amount"] = $existingdata->bet_amount+$request_data["amount"];
					$trans_data["pay_amount"] = 0;
					$trans_data["income"]=0;
					$trans_data["entry_id"] = 1;
				break;
			case "credit":
					$trans_data["win"] = $request_data["win"];
					$trans_data["pay_amount"] = abs($request_data["amount"]);
					$trans_data["income"]=$existingdata->bet_amount-$request_data["amount"];
					$trans_data["entry_id"] = 2;
					$trans_data["payout_reason"] = $request_data["payout_reason"];
				break;
			case "refund":
					$trans_data["win"] = 4;
					$trans_data["pay_amount"] = $request_data["amount"];
					$trans_data["entry_id"] = 2;
					$trans_data["income"]= $existingdata->bet_amount-$request_data["amount"];
					$trans_data["payout_reason"] = "Refund of this transaction ID: ".$request_data["transid"]."of GameRound ".$request_data["roundid"];
				break;

			default:
		}
		/*var_dump($trans_data); die();*/
		return DB::table('game_transactions')->where("game_trans_id",$existingdata->game_trans_id)->update($trans_data);
	}
    public static function createWazdanGameTransactionExt($gametransaction_id,$provider_request,$mw_request,$mw_response,$client_response,$game_transaction_type){
		$gametransactionext = array(
			"provider_trans_id" => $provider_request["transactionId"],
			"game_trans_id" => $gametransaction_id,
			"round_id" =>$provider_request["roundId"],
			"amount" =>$provider_request["amount"],
			"game_transaction_type"=>$game_transaction_type,
			"provider_request" =>json_encode($provider_request),
			"mw_request"=>json_encode($mw_request),
			"mw_response" =>json_encode($mw_response),
			"client_response" =>json_encode($client_response),
		);
		$gamestransaction_ext_ID = DB::table("game_transaction_ext")->insertGetId($gametransactionext);
		return $gamestransaction_ext_ID;
    }
}