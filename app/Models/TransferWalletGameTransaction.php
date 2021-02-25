<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
class TransferWalletGameTransaction extends Model
{
    //
    public static function getGameTransactionByTokenAndRoundId($player_token,$game_round){
        $game = DB::select("SELECT
						entry_id,bet_amount,tw_transaction_id,pay_amount,income
						FROM tw_game_transactions g
						INNER JOIN player_session_tokens USING (token_id)
						WHERE player_token = '".$player_token."' and round_id = '".$game_round."'");
		$cnt = count($game);
        return $cnt > 0? $game[0]: null;
    }
    public static function getGameTransactionByRoundId($game_round){
        $game = DB::select("SELECT
						entry_id,bet_amount,tw_transaction_id,pay_amount,income
						FROM tw_game_transactions g
						WHERE  round_id = '".$game_round."'");
		$cnt = count($game);
        return $cnt > 0? $game[0]: null;
    }
	public static function getGameTransactionByProviderTransactionId($provider_trans_id){
        $game = DB::select("SELECT
						entry_id,bet_amount,tw_transaction_id,pay_amount,income
						FROM tw_game_transactions g
						WHERE  provider_trans_id = '".$provider_trans_id."'");
		$cnt = count($game);
        return $cnt > 0? $game[0]: null;
    }
    public static function createGametransaction($data){
        return DB::table('tw_game_transactions')->insertGetId($data);
    }
    public static function updateGametransaction($data,$game_transaction_id){
        return DB::table('tw_game_transactions')->where('tw_transaction_id',$game_transaction_id)->update($data);
    }
    public static function createGameTransactionExt($gametransactionext){
        return DB::table("game_transaction_ext")->insertGetId($gametransactionext);
    }
	
	public static function getTWPlayerBalance($player_id){
		$tw_balance = DB::select("SELECT balance FROM players WHERE  player_id = '".$player_id."'");
		$cnt = count($tw_balance);
		return $cnt > 0? $tw_balance[0]->balance: null;
    }
    
    public static function findGameTWTransaction($tw_game_trans_id){
        $game = DB::select("select `gt`.*, `gte`.`transaction_detail` 
                          from `tw_game_transactions` as `gt` left join `game_transaction_ext` as `gte` on `gte`.`game_trans_id` = `gt`.`tw_transaction_id` 
                          where `gt`.`tw_transaction_id` = '".$tw_game_trans_id."' limit 1");
        $cnt = count($game);
        return $cnt > 0? $game[0]: null;
    }


    
}