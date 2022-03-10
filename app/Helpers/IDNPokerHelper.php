<?php
namespace App\Helpers;

use App\Helpers\Helper;
use GuzzleHttp\Client;
use DB;

class IDNPokerHelper{

    //REGISTER PLAYER 
    public static function playerDetails($player_id,$auth){
        try {
            $url = config('providerlinks.idnpoker.URL');
           
            $request = '
            <request>
                <secret_key>'.$auth.'</secret_key>
                <id>10</id>
                <userid>'.$player_id.'</userid>
            </request>';
            Helper::saveLog('IDNPOKER GAMELUANCH', 110, json_encode($request),  "CHECK PLAYER DETAILS REQUEST" );
            $client = new Client();
            $guzzle_response = $client->post($url,[
                'body' => '
                        <request>
                            <secret_key>'.config('providerlinks.idnpoker.agent.JFPAA').'</secret_key>
                            <id>10</id>
                            <userid>'.$player_id.'</userid>
                        </request>'
            ]
            );
            $player_details = $guzzle_response->getBody();
            $json = json_encode(simplexml_load_string($player_details));
            $array = json_decode($json,true);
            Helper::saveLog('IDNPOKER GAMELUANCH', 110, json_encode($array),  "CHECK RESPONSE PLAYER DETAILS" );
            return $array;
        } catch (\Exception $e) {
            Helper::saveLog('IDNPOKER GAMELUANCH', 110, json_encode($e->getMessage()),  "CHECK RESPONSE PLAYER DETAILS ERROR" );
            return "false";
        }
        
    }

    public static function registerPlayer($player_id,$auth,$password){
        try {
            $url = config('providerlinks.idnpoker.URL');
            $request = '
            <request>
            <secret_key>'.$auth.'</secret_key>
                <id>1</id>
                <userid>'.$player_id.'</userid>
                <password>'.$password.'</password>
                <confirm_password>'.$password.'</confirm_password>
                <username>'.$player_id.'</username>
            </request>';
            Helper::saveLog('IDNPOKER GAMELUANCH', 110, json_encode($request),  "CHECK REGISTER REQUEST" );
            $client = new Client();
            $guzzle_response = $client->post($url,[
                'body' => '
                        <request>
                        <secret_key>'.$auth.'</secret_key>
                            <id>1</id>
                            <userid>'.$player_id.'</userid>
                            <password>'.$password.'</password>
                            <confirm_password>'.$password.'</confirm_password>
                            <username>'.$player_id.'</username>
                        </request>'
            ]
            );
            $player_details = $guzzle_response->getBody();
            $json = json_encode(simplexml_load_string($player_details));
            $array = json_decode($json,true);
            Helper::saveLog('IDNPOKER GAMELUANCH', 110, json_encode($array),  "CHECK RESPONSE REGISTER PLAYER" );
            return $array;
        } catch (\Exception $e) {
            Helper::saveLog('IDNPOKER GAMELUANCH', 110, json_encode($e->getMessage()),  "CHECK RESPONSE REGISTER PLAYER ERROR" );
            return "false";
        }
        
    }


    public static function gameLaunchURLLogin($data, $player_id, $client_details, $auth,$password) {
        try {
            $url = config('providerlinks.idnpoker.URL');
            $supportlang = [
                'en','cs','id','th','vn','jp','kr'
            ];    
            if (in_array($data['lang'], $supportlang)) {
                $lang = $data['lang'];
            } else {
                $lang = 'en';
            }
            $IP = '127.1.1.1';
            if (isset($data["ip_address"])) {
               $IP = $data["ip_address"];
            }
     
            $client = new Client();
            $request = '
            <request>
            <secret_key>'.$auth.'</secret_key>
                <id>2</id>
                <userid>'.$player_id.'</userid>
                <password>'.$password.'</password>
                <ip>'.$IP.'</ip>
                <secure>1</secure>
                <mobile>1</mobile>
                <game>'.$data['game_code'].'</game>
                <lang>'.$lang.'</lang>
            </request>';
            Helper::saveLog('IDNPOKER GAMELUANCH', 110, json_encode($request),  "CHECK LOGIN REQUEST" );
            $guzzle_response = $client->post($url,[
                'body' => '
                        <request>
                        <secret_key>'.$auth.'</secret_key>
                            <id>2</id>
                            <userid>'.$player_id.'</userid>
                            <password>'.$password.'</password>
                            <ip>'.$IP.'</ip>
                            <secure>1</secure>
                            <mobile>1</mobile>
                            <game>'.$data['game_code'].'</game>
                            <lang>'.$lang.'</lang>
                        </request>'
            ]
            );
            $player_details = $guzzle_response->getBody();
            $json = json_encode(simplexml_load_string($player_details));
            $array = json_decode($json,true);
            Helper::saveLog('IDNPOKER GAMELUANCH', 110, json_encode($array),  "CHECK RESPONSE LOGIN" );
            return $array;
        } catch (\Exception $e) {
            Helper::saveLog('IDNPOKER GAMELUANCH', 110, json_encode($e->getMessage()),  "CHECK RESPONSE LOGIN ERROR" );
            return "false";
        }
    }

    public static function deposit($data,$auth){
        try {
            $url = config('providerlinks.idnpoker.URL');
            $client = new Client();
            $guzzle_response = $client->post($url,[
                'body' => '
                        <request>
                            <secret_key>'.$auth.'</secret_key>
                            <id>3</id>
                            <userid>'.$data["player_id"].'</userid>
                            <id_transaction>'.$data["transaction_id"].'</id_transaction>
                            <deposit>'.$data["amount"].'</deposit>
                        </request>'
            ]
            );
            $details = $guzzle_response->getBody();
            $json = json_encode(simplexml_load_string($details));
            $array = json_decode($json,true);
            Helper::saveLog('IDNPOKER DEPOSIT', 110, json_encode($array),  "CHECK RESPONSE DEPOSIT" );
            return $array;
        } catch (\Exception $e) {
            Helper::saveLog('IDNPOKER DEPOSIT', 110, json_encode($e->getMessage()),  "CHECK RESPONSE DEPOSIT ERROR" );
            return "false";
        }
        
    }

    public static function withdraw($data,$auth){
        try {
            $url = config('providerlinks.idnpoker.URL');
            $client = new Client();
            $guzzle_response = $client->post($url,[
                'body' => '
                        <request>
                            <secret_key>'.$auth.'</secret_key>
                            <id>4</id>
                            <userid>'.$data["player_id"].'</userid>
                            <id_transaction>'.$data["transaction_id"].'</id_transaction>
                            <withdraw>'.$data["amount"].'</withdraw>
                        </request>'
            ]
            );
            $details = $guzzle_response->getBody();
            $json = json_encode(simplexml_load_string($details));
            $array = json_decode($json,true);
            Helper::saveLog('IDNPOKER WITHDRAW', 110, json_encode($array),  "CHECK RESPONSE WITHDRAW" );
            return $array;
        } catch (\Exception $e) {
            Helper::saveLog('IDNPOKER WITHDRAW', 110, json_encode($e->getMessage()),  "CHECK RESPONSE WITHDRAW ERROR" );
            return "false";
        }
        
    }

    public static function getAuthPerOperator($client_details, $type = false){
        $auth = "";
        if($type == "staging") {
            $auth = config('providerlinks.idnpoker.agent.JFPAA');
        }
        if($type == "production"){
            if($client_details->operator_id == 1 ){
                $auth = config('providerlinks.idnpoker.agent')["JFPAA"]; //TESTING
            } else {
                $auth = config('providerlinks.idnpoker.agent')[$client_details->operator_id][$client_details->client_id];
            }
        }
        return $auth;
    }

    public static function getRate($auth){
        try {
            $url = config('providerlinks.idnpoker.URL');
            $client = new Client();
            $guzzle_response = $client->post($url,[
                'body' => '
                        <request>
                            <secret_key>'.$auth.'</secret_key>
                            <id>9</id>
                            <currency>JPY</currency>
                        </request>'
            ]
            );
            $details = $guzzle_response->getBody();
            $json = json_encode(simplexml_load_string($details));
            $currency_rate = json_decode($json,true);
            if(isset($currency_rate["rate"])){
                $rate = $currency_rate["rate"]; 
                return $rate;
            }
            Helper::saveLog('IDNPOKER getRate', 110, json_encode($currency_rate),  "CHECK RESPONSE getRate" );
            return "false";
        } catch (\Exception $e) {
            Helper::saveLog('IDNPOKER getRate', 110, json_encode($e->getMessage()),  "CHECK RESPONSE getRate ERROR" );
            return "false";
        }
    }

    public static function getTransactionHistory($data,$auth){
        try {
            $url = config('providerlinks.idnpoker.URL');
            $client = new Client();
            $guzzle_response = $client->post($url,[
                'body' => '
                <request>
                    <secret_key>'.$auth.'</secret_key>
                    <id>15</id>
                    <date>'.$data["date"].'</date>
                    <start_time>'.$data["start_time"].'</start_time>
                    <end_time>23:59</end_time>
                </request>'
            ]
            );
            $details = $guzzle_response->getBody();
            $json = json_encode(simplexml_load_string($details));
            $response = json_decode($json,true);
            if(isset($response["numrow"]) && $response["numrow"] > 0 ){
                return $response;
            }
            Helper::saveLog('IDNPOKER getTransactionHistory', 110, json_encode($response),  "CHECK RESPONSE getTransactionHistory" );
            return "false";
        } catch (\Exception $e) {
            Helper::saveLog('IDNPOKER getTransactionHistory', 110, json_encode($e->getMessage()),  "CHECK RESPONSE getTransactionHistory ERROR" );
            return "false";
        }
    }

    public static function checkPlayerRestricted($player_id){
		$query = DB::select('select * from tw_player_restriction where player_id = '.$player_id);
		$data = count($query);
		return $data > 0 ? $query[0] : "false";
	}

    public static function createPlayerRestricted($data) {
		$data_saved = DB::table('tw_player_restriction')->insertGetId($data);
		return $data_saved;
	}

    public static function deletePlayerRestricted($identifier){
		$where = 'where idtw_player_restriction = '.$identifier;
		DB::select('delete from tw_player_restriction '.$where);
	}
}