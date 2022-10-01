<?php
namespace App\Helpers;
use DB;
use GuzzleHttp\Client;
use Carbon\Carbon;
use App\Helpers\Helper;

class ClientHelper
{
	public static function getClientErrorCode($error_code){
		$msg = [
		  1 => 'Client Not Found',
		  2 => 'Client Disabled',
		  3 => 'Game Not Found',
		  4 => 'Game Under Maintenance',
		  5 => 'Provider Not Found',
		  6 => 'Provider Under Maintenance',
		  7 => 'Player Disabled',
		  8 => 'Operator Not Found',
		  9 => 'Operator Disabled',
		  10 => 'Something went wrong',
		  200 => 'Contact the Service Provider'
		];
		return $msg[$error_code];
	}

	// 	  "client_id": "34",
 	//    "client_player_id": "1",
 	//    "username": "charity",
 	//    "email": "charoot@hashen.com",
 	//    "display_name": "charity",
 	//    "game_code": "GHG_DREAMS_OF_GOLD",
 	//    "exitUrl": "demo.freebetrnk.com",
 	//    "game_provider": "Oryx Gaming",
 	//    "token": "c761ba7d338c83ed5f6bb6c6393a7c36",
 	//    "lang": "en"
	
	public static function checkClientID($data){

		// Client Filter [NOT FOUND or DEACTIVATED]
		$client = DB::table('clients')->where('client_id', $data['client_id'])->first();
		if($client == '' || $client == null){ return 1; } 
		if($client->status_id != 1 ){ return 2; }

		// Operator maintenance
		$operator = DB::table('operator')->where('operator_id', $client->operator_id)->first();
		if($operator == '' || $operator == null){ return 8; } 
		if(isset($operator->status_id) && $operator->status_id != 1 ){ return 9; }

		$game_provider = DB::table('sub_provider_code')->where('sub_provider_name', $data['game_provider'])->first();

		// Provider Disabled
		// $sub_provider = DB::table('sub_providers')->where('sub_provider_name', $data['game_provider'])->first();
		$sub_provider = DB::table('sub_providers')->where('sub_provider_id', $game_provider->sub_provider_id)->first();
		if($sub_provider == '' || $sub_provider == null){ return 5; }
		if($sub_provider->on_maintenance != 0){ return 6; }

		// Game Not Found / Game on maintenance
		$games = DB::table('games')->where('game_code', $data['game_code'])->where('sub_provider_id', $game_provider->sub_provider_id)->first();
		if(!$games){
			return 3;
		}
		
		if($games->on_maintenance != 0 ){ return 4; } 
		if($games == '' || $games == null){ return 3; }

		// Player Disabled
		$player= DB::table('players')->where('client_id', $data['client_id'])
				->where('client_player_id', $data['client_player_id'])->first();
		if(isset($player->player_status)){
			if($player != '' || $player != null){
				if($player->player_status == 2){ return 7; }
			}
		}

		return 200; // All Good Request May Proceed!
	}
	
}