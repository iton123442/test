<?php
namespace App\Helpers;

use App\Helpers\Helper;
use GuzzleHttp\Client;
use Webpatser\Uuid\Uuid;
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
            ProviderHelper::saveLogWithExeption('IDNPOKER GAMELUANCH', 110, json_encode($request),  "CHECK PLAYER DETAILS REQUEST" );
            $client = new Client();
            $guzzle_response = $client->post($url,[
                'body' => '
                        <request>
                            <secret_key>'.$auth.'</secret_key>
                            <id>10</id>
                            <userid>'.$player_id.'</userid>
                        </request>'
            ]
            );
            $player_details = $guzzle_response->getBody();
            $json = json_encode(simplexml_load_string($player_details));
            $array = json_decode($json,true);
            ProviderHelper::saveLogWithExeption('IDNPOKER GAMELUANCH', 110, json_encode($array),  "CHECK RESPONSE PLAYER DETAILS" );
            return $array;
        } catch (\Exception $e) {
            ProviderHelper::saveLogWithExeption('IDNPOKER GAMELUANCH', 110, json_encode($e->getMessage()),  "CHECK RESPONSE PLAYER DETAILS ERROR" );
            return "false";
        }
        
    }

    public static function registerPlayer($player_id,$auth){
        try {
            $url = config('providerlinks.idnpoker.URL');
            $request = '
            <request>
            <secret_key>'.$auth.'</secret_key>
                <id>1</id>
                <userid>'.$player_id.'</userid>
                <password>'.$player_id.'</password>
                <confirm_password>'.$player_id.'</confirm_password>
                <username>'.$player_id.'</username>
            </request>';
            ProviderHelper::saveLogWithExeption('IDNPOKER GAMELUANCH', 110, json_encode($request),  "CHECK REGISTER REQUEST" );
            $client = new Client();
            $guzzle_response = $client->post($url,[
                'body' => '
                        <request>
                        <secret_key>'.$auth.'</secret_key>
                            <id>1</id>
                            <userid>'.$player_id.'</userid>
                            <password>'.$player_id.'</password>
                            <confirm_password>'.$player_id.'</confirm_password>
                            <username>'.$player_id.'</username>
                        </request>'
            ]
            );
            $player_details = $guzzle_response->getBody();
            $json = json_encode(simplexml_load_string($player_details));
            $array = json_decode($json,true);
            ProviderHelper::saveLogWithExeption('IDNPOKER GAMELUANCH', 110, json_encode($array),  "CHECK RESPONSE REGISTER PLAYER" );
            return $array;
        } catch (\Exception $e) {
            ProviderHelper::saveLogWithExeption('IDNPOKER GAMELUANCH', 110, json_encode($e->getMessage()),  "CHECK RESPONSE REGISTER PLAYER ERROR" );
            return "false";
        }
        
    }


    public static function gameLaunchURLLogin($data, $player_id, $client_details, $auth) {
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
            
            $mobile = 1;
            if (isset($data["device"])) {
               $mobile = $data["device"] == 'desktop' ? 1 : 0;
            }
            $mobile = 1;
            $client = new Client();
            $request = '
            <request>
            <secret_key>'.$auth.'</secret_key>
                <id>2</id>
                <userid>'.$player_id.'</userid>
                <password>'.$player_id.'</password>
                <ip>'.$IP.'</ip>
                <secure>1</secure>
                <mobile>'.$mobile.'</mobile>
                <game>'.$data['game_code'].'</game>
                <lang>'.$lang.'</lang>
            </request>';
            ProviderHelper::saveLogWithExeption('IDNPOKER GAMELUANCH', 110, json_encode($request),  "CHECK LOGIN REQUEST" );
            $guzzle_response = $client->post($url,[
                'body' => '
                        <request>
                        <secret_key>'.$auth.'</secret_key>
                            <id>2</id>
                            <userid>'.$player_id.'</userid>
                            <password>'.$player_id.'</password>
                            <ip>'.$IP.'</ip>
                            <secure>1</secure>
                            <mobile>'.$mobile.'</mobile>
                            <game>'.$data['game_code'].'</game>
                            <lang>'.$lang.'</lang>
                        </request>'
            ]
            );
            $player_details = $guzzle_response->getBody();
            $json = json_encode(simplexml_load_string($player_details));
            $array = json_decode($json,true);
            ProviderHelper::saveLogWithExeption('IDNPOKER GAMELUANCH', 110, json_encode($array),  "CHECK RESPONSE LOGIN" );
            return $array;
        } catch (\Exception $e) {
            ProviderHelper::saveLogWithExeption('IDNPOKER GAMELUANCH', 110, json_encode($e->getMessage()),  "CHECK RESPONSE LOGIN ERROR" );
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
            ProviderHelper::saveLogWithExeption('IDNPOKER DEPOSIT', 110, json_encode($array),  "CHECK RESPONSE DEPOSIT" );
            return $array;
        } catch (\Exception $e) {
            ProviderHelper::saveLogWithExeption('IDNPOKER DEPOSIT', 110, json_encode($e->getMessage()),  "CHECK RESPONSE DEPOSIT ERROR" );
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
            ProviderHelper::saveLogWithExeption('IDNPOKER WITHDRAW', 110, json_encode($array),  "CHECK RESPONSE WITHDRAW" );
            return $array;
        } catch (\Exception $e) {
            ProviderHelper::saveLogWithExeption('IDNPOKER WITHDRAW', 110, json_encode($e->getMessage()),  "CHECK RESPONSE WITHDRAW ERROR" );
            return "false";
        }
        
    }

    public static function getAuthPerOperator($client_details, $type = false){
        $auth = "";
        if($type == "staging") {
            $auth = config('providerlinks.idnpoker.agent.DSPAA');
        }
        if($type == "production"){
            if($client_details->operator_id == 1 ){
                $auth = config('providerlinks.idnpoker.agent')["DSPAA"]; //TESTING
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
                            <currency>USD</currency>
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
            ProviderHelper::saveLogWithExeption('IDNPOKER getRate', 110, json_encode($currency_rate),  "CHECK RESPONSE getRate" );
            return "false";
        } catch (\Exception $e) {
            ProviderHelper::saveLogWithExeption('IDNPOKER getRate', 110, json_encode($e->getMessage()),  "CHECK RESPONSE getRate ERROR" );
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
            ProviderHelper::saveLogWithExeption('IDNPOKER getTransactionHistory', 110, json_encode($response),  "CHECK RESPONSE getTransactionHistory" );
            return "false";
        } catch (\Exception $e) {
            ProviderHelper::saveLogWithExeption('IDNPOKER getTransactionHistory', 110, json_encode($e->getMessage()),  "CHECK RESPONSE getTransactionHistory ERROR" );
            return "false";
        }
    }

    public static function getCheckTransactionIDAvailable($data,$auth, $client_transaction_id = false){
        $x = 1; // limit 5 times retry
        $id_transaction = false;// false or id
        do {
            // $id = Uuid::generate()->string;
            if($client_transaction_id){
                $id = $client_transaction_id;
            }else {
                $id = Uuid::generate()->string;
            }
            try {
                $url = config('providerlinks.idnpoker.URL');
                $client = new Client();
                $guzzle_response = $client->post($url,[
                    'body' => '
                            <request>
                                <secret_key>'.$auth.'</secret_key>
                                <id>7</id>
                                <userid>'.$data["player_id"].'</userid>
                                <id_transaction>'.$id.'</id_transaction>
                                <action>'.$data["action"].'</action>
                            </request>'
                ]
                );
                $details = $guzzle_response->getBody();
                $json = json_encode(simplexml_load_string($details));
                $response = json_decode($json,true);
                if(isset($response["status"])  && $response["status"] == 0 && isset($response["id_transaction"]) ){
                    $id_transaction = $response["id_transaction"]; 
                    $x = 6;
                }
                ProviderHelper::saveLogWithExeption('IDNPOKER checkTransactionID', 110, json_encode($response),  "CHECK RESPONSE checkTransactionID" );
            } catch (\Exception $e) {
                ProviderHelper::saveLogWithExeption('IDNPOKER DEPOSIT', 110, json_encode($e->getMessage()),  "CHECK RESPONSE DEPOSIT ERROR" );
            }
            $x++;
        } while ($x <= 5);
        return $id_transaction;
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
        try {
            $where = 'where idtw_player_restriction = '.$identifier;
            DB::select('delete from tw_player_restriction '.$where);
        } catch (\Exception $e) {
            // ProviderHelper::saveLogWithExeption('IDNPOKER getRate', 110, json_encode($e->getMessage()),  "CHECK RESPONSE getRate ERROR" );
            return "false";
        }
    }

    public static function getPlayerID($client_player_id,$client_ids){
        $query = DB::select('select * from players where username = "'.$client_player_id.'"');
        $player_id = 1;
        foreach($query as $item){
            if (in_array($item->client_id, $client_ids)) {
               $player_id = $item->player_id;
            }
        }   
        // $query = DB::select('select * from players where client_id = '.$client_id.' and client_player_id = "'.$player_id.'"');
        $data = count($query);
        return $data > 0 ? $player_id : "false";
    }

    
    /**
	 * [updateSessionTime - update set session to default $session_time]
	 * 
	 */
    public static function updatePlayerRestricted($data,$idtw_player_restriction){
       return DB::table('tw_player_restriction')->where('idtw_player_restriction',$idtw_player_restriction)->update($data);
    }

    public static function createIDNTransaction($data) {
        $data_saved = DB::table('idn_transaction_list')->insertGetId($data);
        return $data_saved;
    }

    public static function updateCurrencyRate(){
        try {
            $url = config('providerlinks.idnpoker.URL');
            $auth = config('providerlinks.idnpoker.rate_auth');
            $client = new Client();

            $details = DB::select('select * from currencies');
            foreach($details as $datas){
                sleep(3);
                $guzzle_response = $client->post($url,[
                    'body' => '
                            <request>
                                <secret_key>'.$auth.'</secret_key>
                                <id>9</id>
                                <currency>'.$datas->code.'</currency>
                            </request>'
                ]
                );
                $details = $guzzle_response->getBody();
                $json = json_encode(simplexml_load_string($details));
                $currency_rate = json_decode($json,true);
                if(isset($currency_rate["rate"])){
                    $rate = $currency_rate["rate"]; 
                    $getCurrency = DB::select('select * from idn_currency_rate where currency_code = "'.$datas->code.'" ');
                    if (count($getCurrency) > 0) {
                        DB::table('idn_currency_rate')->where('id', $getCurrency[0]->id)->update(['rate' => $rate]);
                    } else {
                        $insert_data = [
                            "currency_code" => $datas->code,
                            "rate" => $rate
                        ];
                        DB::table('idn_currency_rate')->insertGetId($insert_data);
                    }
                }
            }
            ProviderHelper::saveLogWithExeption('IDNPOKER updateRATE', 110, json_encode(["update"]),  "CHECK RESPONSE updateRATE" );
            return "false";
        } catch (\Exception $e) {
            ProviderHelper::saveLogWithExeption('IDNPOKER updateRATE', 110, json_encode($e->getMessage()),  "CHECK RESPONSE getRate ERROR" );
            return "false";
        }
    }

}
