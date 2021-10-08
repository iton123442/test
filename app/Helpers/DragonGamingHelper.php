<?php
namespace App\Helpers;
use DB;

class DragonGamingHelper
{
	public static function getGameType($game_code, $provider_id){
		$game_type_id = DB::table("games as g")
				->select("gmt.game_type_id")
				->leftJoin("game_types as gmt","gmt.game_type_id","=","g.game_type_id")
				->where("g.game_code", $game_code)
				->where("g.provider_id", $provider_id)
				->first();

		return $game_type_id ? $game_type_id->game_type_id : false;
	}
}
