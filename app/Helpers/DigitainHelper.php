<?php
namespace App\Helpers;

use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Helpers\GameLobby;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use Carbon\Carbon;
use DB;

class DigitainHelper{

    public static $timeout = 2; // Seconds

    public static function datesent()
    {
        $date = Carbon::now();
        return $date->toDateTimeString();
    }

    public static function tokenCheck($token){
        $token = DB::table('player_session_tokens')
                    ->select("*", DB::raw("NOW() as IMANTO"))
                    ->where('player_token', $token)
                    ->first();
        if($token != null){
            $check_token = DB::table('player_session_tokens')
            ->selectRaw("TIME_TO_SEC(TIMEDIFF( NOW(), '".$token->created_at."'))/60 as `time`")
            ->first();
            if(1440 > $check_token->time) {  // TIMEGAP IN MINUTES!
                $token = true; // True if Token can still be used!
            }else{
                $token = false; // Expired Token
            }
        }else{
            $token = false; // Not Found Token
        }
        return $token;
    }

    
    public static function increaseTokenLifeTime($seconds, $token,$type=1){
         $token = DB::table('player_session_tokens')
                    ->select("*", DB::raw("NOW() as IMANTO"))
                    ->where('player_token', $token)
                    ->first();
         $date_now = $token->created_at;

         if($type==1){
            $newdate = date("Y-m-d H:i:s", (strtotime(date($date_now)) + $seconds));
            $update = DB::table('player_session_tokens')
            ->where('token_id', $token->token_id)
            ->update(['created_at' => $newdate]);
        }else{
           $newdate = date('Y-m-d H:i:s', strtotime($date_now .' -1 day'));
           $update = DB::table('player_session_tokens')
            ->where('token_id', $token->token_id)
            ->update(['created_at' => $newdate]); 
        }
    }

    public  static function updateBetToWin($game_trans_id, $pay_amount, $income, $win, $entry_id, $type=1,$bet_amount=0) {
        if($type == 1){
            $update = DB::table('game_transactions')
            ->where('game_trans_id', $game_trans_id)
            ->update(['pay_amount' => $pay_amount, 
                  'income' => $income, 
                  'win' => $win, 
                  'entry_id' => $entry_id,
                  'transaction_reason' => 'Bet updated to win'
            ]);
        }else{
            $update = DB::table('game_transactions')
            ->where('game_trans_id', $game_trans_id)
            ->update(['pay_amount' => $pay_amount, 
                  'income' => $income, 
                  'bet_amount' => $bet_amount, 
                  'win' => $win, 
                  'entry_id' => $entry_id,
                  'transaction_reason' => 'Bet updated to win'
            ]);
        }
        return ($update ? true : false);
    }


     /**
      * 
      *  Check if game if the gamecode is mobile type then convert it to desktop value
      *
      */
    public  static function findGameDetails($provider_id, $game_code){
        
            # If game code is mobile convert to desktop game code
            $is_exist_gameid = DigitainHelper::IsMobileGameCode($game_code);
            if($is_exist_gameid != false){
                $gameId = $is_exist_gameid;
            }else{
                $gameId = $game_code;
            }

            $query = DB::Select("SELECT game_id,game_code,game_name,sub_provider_name as provider_name FROM games inner join sub_providers sp using (sub_provider_id) WHERE game_code = '" . $$gameId . "' AND sp.provider_id = '" . $provider_id . "' order by sp.sub_provider_id desc");
            $result = count($query);
            return $result > 0 ? $query[0] : null;
    }

     /**
      *  Check if game has mobile
      *  Key is either desktop and mobile
      *  Value is always desktop gamecode
      *  if provider send mobile game code, send desktop gamecode back to client
      */
    public static function HasMobileGameCode($gameCode){
        $game_ids = [
            'desktop' => 'mobile', 

            '8105' => '8106',  // Backgammon Asian New
            '8103' => '8104',  // Backgammon Long
            '8101' => '8102',  // Backgammon Short
            '6274' => '6273',  // Skill Games Lobby

        ];  
        if (array_key_exists($gameCode, $game_ids)) {
            return $game_ids[$gameCode];
        } else {
            return false;
        }
    }


     /**
      *  Mobile Game Code to Desktop
      */
    public static function IsMobileGameCode($gameCode){
        $game_ids = [
            'mobile' => 'desktop', 

            '8106' => '8105',  // Backgammon Asian New
            '8104' => '8103',  // Backgammon Long
            '8102' => '8101',  // Backgammon Short
            '6273' => '6274',  // Skill Games Lobby

        ];  
        if (array_key_exists($gameCode, $game_ids)) {
            return $game_ids[$gameCode];
        } else {
            return false;
        }
    }

    public static function saveLog($method, $provider_id = 0, $request_data, $response_data) {
            $data = [
                        "method_name" => $method,
                        "provider_id" => $provider_id,
                        "request_data" => json_encode(json_decode($request_data)),
                        "response_data" => json_encode($response_data)
                    ];
            // return DB::table('debug')->insertGetId($data);
            return DB::table('seamless_request_logs')->insertGetId($data);
    }

    public static function savePLayerGameRound($game_code,$player_token,$sub_provider_name){
        $sub_provider_id = DB::table("sub_providers")->where("sub_provider_name",$sub_provider_name)->first();
        Helper::saveLog('SAVEPLAYERGAME(ICG)', 12, json_encode($sub_provider_id), $sub_provider_name);
        $game = DB::table("games")->where("game_code",$game_code)->where("sub_provider_id",$sub_provider_id->sub_provider_id)->first();
        $player_game_round = array(
            "player_token" => $player_token,
            "game_id" => $game->game_id,
            "status_id" => 1
        );
        DB::table("player_game_rounds")->insert($player_game_round);
    }


    public static function getInfoPlayerGameRound($player_token){
        $game = DB::table("player_game_rounds as pgr")
                ->leftJoin("player_session_tokens as pst","pst.player_token","=","pgr.player_token")
                ->leftJoin("games as g" , "g.game_id","=","pgr.game_id")
                ->leftJoin("players as ply" , "pst.player_id","=","ply.player_id")
                ->where("pgr.player_token",$player_token)
                ->first();
        return $game ? $game : false;
    }

    /**
     * [isolated provider class helper for digitain]
     * 
     */
    public  static function findGameExt($provider_identifier, $game_transaction_type, $type) {
        // DB::enableQueryLog();
        $transaction_db = DB::table('game_transaction_ext as gte');
        if ($type == 'transaction_id') {
            if($game_transaction_type == 123){
                $transaction_db->where([
                    ["gte.provider_trans_id", "=", $provider_identifier],
                    ["gte.transaction_detail", "!=", '"FAILED"'], // TEST OVER ALL
                ]);
            }else{
                $transaction_db->where([
                    ["gte.provider_trans_id", "=", $provider_identifier],
                    ["gte.game_transaction_type", "=", $game_transaction_type],
                    ["gte.transaction_detail", "!=", '"FAILED"'], // TEST OVER ALL
                ]);
            }
        }
        if ($type == 'round_id') {
            $transaction_db->where([
                ["gte.round_id", "=", $provider_identifier],
                ["gte.game_transaction_type", "=", $game_transaction_type],
                ["gte.transaction_detail", "!=", '"FAILED"'], // TEST OVER ALL
            ]);
        }  
        if ($type == 'game_transaction_ext_id') {
            $transaction_db->where([
                ["gte.game_transaction_type", "=", $game_transaction_type],
                ["gte.game_trans_ext_id", "=", $provider_identifier],
            ]);
        } 
        if ($type == 'game_trans_id') {
            $transaction_db->where([
                ["gte.game_transaction_type", "=", $game_transaction_type],
                ["gte.game_trans_id", "=", $provider_identifier],
            ]);
        } 
        $result = $transaction_db->latest()->first(); // Added Latest (CQ9) 08-12-20 - Al
        // Helper::saveLog('Find Game Extension', 999, json_encode(DB::getQueryLog()), "TIME Find Game Extension");
        return $result ? $result : 'false';
    }


    public  static function findGameTransaction($identifier, $type, $entry_type='') {
        // DB::enableQueryLog();
        $transaction_db = DB::table('game_transactions as gt')
                        ->select('gt.*', 'gte.transaction_detail')
                        ->leftJoin("game_transaction_ext AS gte", "gte.game_trans_id", "=", "gt.game_trans_id");
                       
        if ($type == 'transaction_id') {
            $transaction_db->where([
                ["gt.provider_trans_id", "=", $identifier],
                ["gt.entry_id", "=", $entry_type],
            ]);
        }
        if ($type == 'game_transaction') {
            $transaction_db->where([
                ["gt.game_trans_id", "=", $identifier],
            ]);
        }
        if ($type == 'round_id') {
            $transaction_db->where([
                ["gt.round_id", "=", $identifier],
                ["gt.entry_id", "=", $entry_type],
            ]);
        }
        if ($type == 'refundbet') { // TEST
            $transaction_db->where([
                ["gt.round_id", "=", $identifier],
                ["gt.entry_id", "=", $entry_type],
            ]);
        }
        $result= $transaction_db
            ->first();
        // Helper::saveLog('Find Game Transaction', 999, json_encode(DB::getQueryLog()), "TIME Find Game Transaction");
        return $result ? $result : 'false';
    }


    public static function playerDetailsCall($client_details, $refreshtoken=false){
        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$client_details->client_access_token
            ]
        ]);
        $datatosend = ["access_token" => $client_details->client_access_token,
            "hashkey" => md5($client_details->client_api_key.$client_details->client_access_token),
            "type" => "playerdetailsrequest",
            "datesent" => Helper::datesent(),
            "clientid" => $client_details->client_id,
            "playerdetailsrequest" => [
                "player_username"=>$client_details->username,
                "client_player_id" => $client_details->client_player_id,
                "token" => $client_details->player_token,
                "gamelaunch" => true,
                "refreshtoken" => $refreshtoken
            ]
        ];
        try{    
            $guzzle_response = $client->post($client_details->player_details_url,
                ['body' => json_encode($datatosend)]
            );
            $client_response = json_decode($guzzle_response->getBody()->getContents());
            return $client_response;
        }catch (\Exception $e){
           Helper::saveLog('ALDEBUG client_player_id = '.$client_details->client_player_id,  99, json_encode($datatosend), $e->getMessage());
           return 'false';
        }
    }

}