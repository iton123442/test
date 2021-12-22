<?php
namespace App\Helpers;

use App\Helpers\Helper;
use GuzzleHttp\Client;

class IDNPokerHelper{

    //REGISTER PLAYER 
    public static function playerDetails($player_id){
        try {
            $url = config('providerlinks.idnpoker.URL');
           
            $request = '
            <request>
                <secret_key>'.config('providerlinks.idnpoker.agent.JFPAA').'</secret_key>
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

    public static function registerPlayer($player_id){
        try {
            $url = config('providerlinks.idnpoker.URL');
            $request = '
            <request>
            <secret_key>'.config('providerlinks.idnpoker.agent.JFPAA').'</secret_key>
                <id>1</id>
                <userid>'.$player_id.'</userid>
                <password>'.$player_id.'</password>
                <confirm_password>'.$player_id.'</confirm_password>
                <username>'.$player_id.'</username>
            </request>';
            Helper::saveLog('IDNPOKER GAMELUANCH', 110, json_encode($request),  "CHECK REGISTER REQUEST" );
            $client = new Client();
            $guzzle_response = $client->post($url,[
                'body' => '
                        <request>
                        <secret_key>'.config('providerlinks.idnpoker.agent.JFPAA').'</secret_key>
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
            Helper::saveLog('IDNPOKER GAMELUANCH', 110, json_encode($array),  "CHECK RESPONSE REGISTER PLAYER" );
            return $array;
        } catch (\Exception $e) {
            Helper::saveLog('IDNPOKER GAMELUANCH', 110, json_encode($e->getMessage()),  "CHECK RESPONSE REGISTER PLAYER ERROR" );
            return "false";
        }
        
    }


    public static function gameLaunchURLLogin($data, $player_id, $client_details) {
        try {
            $url = config('providerlinks.idnpoker.URL');
            $lang = $data["lang"] != '' ? $data["lang"] : 'en';
            // $lang = 'ja';
            
            $client = new Client();
            $request = '
            <request>
            <secret_key>'.config('providerlinks.idnpoker.agent.JFPAA').'</secret_key>
                <id>2</id>
                <userid>'.$player_id.'</userid>
                <password>'.$player_id.'</password>
                <ip>'.$data["ip_address"].'</ip>
                <secure>1</secure>
                <mobile>1</mobile>
                <game>'.$data['game_code'].'</game>
                <lang>'.$lang.'</lang>
            </request>';
            Helper::saveLog('IDNPOKER GAMELUANCH', 110, json_encode($request),  "CHECK LOGIN REQUEST" );
            $guzzle_response = $client->post($url,[
                'body' => '
                        <request>
                        <secret_key>'.config('providerlinks.idnpoker.agent.JFPAA').'</secret_key>
                            <id>2</id>
                            <userid>'.$player_id.'</userid>
                            <password>'.$player_id.'</password>
                            <ip>'.$data["ip_address"].'</ip>
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

    public static function deposit($data){
        try {
            $url = config('providerlinks.idnpoker.URL');
            $client = new Client();
            $guzzle_response = $client->post($url,[
                'body' => '
                        <request>
                            <secret_key>'.config('providerlinks.idnpoker.agent.JFPAA').'</secret_key>
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

    public static function withdraw($data){
        try {
            $url = config('providerlinks.idnpoker.URL');
            $client = new Client();
            $guzzle_response = $client->post($url,[
                'body' => '
                        <request>
                            <secret_key>'.config('providerlinks.idnpoker.agent.JFPAA').'</secret_key>
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

}