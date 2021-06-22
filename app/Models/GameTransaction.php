<?php

namespace App\Models;

//use Illuminate\Database\Eloquent\Model;
use DB;
class GameTransaction 
{
    //

    public static function checkGameTransactionExist($provider_transaction_id,$round_id=false,$type=false){
        if($type&&$round_id){
			$game = DB::select("SELECT game_transaction_type
								FROM game_transaction_ext
								WHERE round_id = '".$round_id."' AND provider_trans_id='".$provider_transaction_id."' AND game_transaction_type = ".$type."");
		}
        elseif($type&&$provider_transaction_id){
            $game = DB::select("SELECT game_trans_ext_id
			FROM game_transaction_ext
			where provider_trans_id='".$provider_transaction_id."' AND game_transaction_type={$type} limit 1");
        }
		else{
			$game = DB::select("SELECT game_trans_ext_id
			FROM game_transaction_ext
			where provider_trans_id='".$provider_transaction_id."' limit 1");
		}
		return $game ? true :false;
    }
    public static function getGameTransactionDataByProviderTransactionId($provider_transaction_id){
		$game = DB::select("SELECT game_trans_ext_id,mw_response,round_id,game_trans_id,amount
        FROM game_transaction_ext
        where provider_trans_id='".$provider_transaction_id."' limit 1");
		$cnt = count($game);
        return $cnt > 0? $game[0]: null;
    }
    // public static function getGameTransactionDataByProviderTransactionIdAndEntryType($provider_transaction_id,$entry_type){
	// 	$game = DB::select("SELECT game_trans_ext_id,mw_response,round_id,game_trans_id,amount
    //     FROM game_transaction_ext
    //     where provider_trans_id='{$provider_transaction_id}' AND entry_type={$entry_type} limit 1");
	// 	$cnt = count($game);
    //     return $cnt > 0? $game[0]: null;
    // }
    public static function getGameTransactionDataByProviderTransactionIdAndEntryType($provider_transaction_id,$entry_type){
		$game = DB::select("SELECT game_trans_ext_id,mw_response,round_id,game_trans_id,amount
        FROM game_transaction_ext
        where provider_trans_id='{$provider_transaction_id}' AND game_transaction_type={$entry_type} limit 1");
		$cnt = count($game);
        return $cnt > 0? $game[0]: null;
    }
    public static function getGameTransactionByTokenAndRoundId($player_token,$game_round){
        $game = DB::select("SELECT
						entry_id,bet_amount,game_trans_id,pay_amount
						FROM game_transactions g
						INNER JOIN player_session_tokens USING (token_id)
						WHERE player_token = '".$player_token."' and round_id = '".$game_round."'");
		$cnt = count($game);
        return $cnt > 0? $game[0]: null;
    }
    public static function getGameTransactionByRoundId($game_round){
        $game = DB::select("SELECT
						entry_id,bet_amount,game_trans_id,pay_amount,income
						FROM game_transactions g
						WHERE  round_id = '".$game_round."'");
		$cnt = count($game);
        return $cnt > 0? $game[0]: null;
    }
    public static function createGametransaction($data){
        return DB::table('game_transactions')->insertGetId($data);
    }
    public static function updateGametransaction($data,$game_transaction_id){
        return DB::table('game_transactions')->where('game_trans_id',$game_transaction_id)->update($data);
    }
    public static function createGameTransactionExt($gametransactionext){
        return DB::table("game_transaction_ext")->insertGetId($gametransactionext);
    }
    public static function updateGametransactionEXT($data,$game_trans_ext_id){
        return DB::table('game_transaction_ext')->where('game_trans_ext_id',$game_trans_ext_id)->update($data);
    }
}
