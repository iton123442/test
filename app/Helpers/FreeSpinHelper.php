<?php

namespace App\Helpers;

use App\Services\AES;
use GuzzleHttp\Client;
use App\Helpers\ProviderHelper;
use App\Helpers\WazdanHelper;
use SimpleXMLElement;
use Webpatser\Uuid\Uuid;
use DB;
class FreeSpinHelper{

    public static function getFreeSpinBalance($player_id,$game_id){
        $query = DB::select("SELECT * FROM freespin WHERE player_id=".$player_id." AND game_id = ".$game_id." AND status = 1 AND bonus_type = 1 ORDER BY created_at ASC LIMIT 1");
        $result = count($query);
        $bonusdata= array();
        if($result > 0 ){
            $bonusfreespin["spins"] = array(
                "id" => "code_".$query[0]->freespin_id."_FREESPIN ".$query[0]->total_spin,
                "amount" => $query[0]->spin_remaining,
                "options" => array(
                    "gambleEnabled" => true,
                    "betPerLine" => $query[0]->coins,
                    "denomination" => $query[0]->denominations * 1000
                )
            );
            return $bonusfreespin["spins"];
        }
        else{
            return null;
        }
    }
    public static function getFreeSpinBalanceByFreespinId($freespin_id){
        $exploded_value = explode('_', $freespin_id);
        $query = DB::select("SELECT * FROM freespin WHERE freespin_id=".$exploded_value[1]." AND status = 1");
        $result = count($query);
        $bonusdata= array();
        if($result > 0 ){
            $bonusfreespin["spins"] = array(
                "id" => "code_".$query[0]->freespin_id."_FREESPIN ".$query[0]->total_spin,
                "amount" => $query[0]->spin_remaining,
                "options" => array(
                    "gambleEnabled" => true,
                    "betPerLine" => $query[0]->coins,
                    "denomination" => $query[0]->denominations * 1000
                )
            );
            return $bonusfreespin["spins"];
        }
        else{
            return null;
        }
    }
    public static function updateFreeSpinBalance($freespin_id){
        $exploded_value = explode('_', $freespin_id);
        $beforeupdatequery = DB::select("SELECT * FROM freespin WHERE freespin_id=".$exploded_value[1]." AND status = 1");
        $count = count($beforeupdatequery);
        if($count > 0){
            $spin_remaining = $beforeupdatequery[0]->spin_remaining - 1;
            if($spin_remaining == 0){
                $update_freespin = DB::select("UPDATE freespin SET spin_remaining=".$spin_remaining.",status=0 WHERE freespin_id = ".$exploded_value[1]."");
            }
            else{
                $update_freespin = DB::select("UPDATE freespin SET spin_remaining=".$spin_remaining." WHERE freespin_id = ".$exploded_value[1]."");
            }
            $query = DB::select("SELECT * FROM freespin WHERE freespin_id=".$exploded_value[1]."");
                $bonusfreespin["spins"] = array(
                    "id" => "code_".$query[0]->freespin_id."_FREESPIN ".$query[0]->total_spin,
                    "amount" => $query[0]->spin_remaining,
                    "options" => array(
                        "gambleEnabled" => true,
                        "betPerLine" => $query[0]->coins,
                        "denomination" => $query[0]->denominations * 1000
                    )
                );
                return $bonusfreespin["spins"];
        }
        else{
            return null;
        }
    }

    public static function getFreeSpinDetails($transaction_id, $type = false){
        $where = "";
        if($type){
            switch ($type) {
                case 'provider_trans_id':
                    $where = " where provider_trans_id = '".$transaction_id."' limit 1";
                    break;
                
                default:
                    $where = " where provider_trans_id = '".$transaction_id."' limit 1";
                    break;
            }

        }
        $getFreeRound = DB::select('select freespin_id,game_id,spin_remaining, status, provider_trans_id from freespin ' . $where);
        $data_rows = count($getFreeRound);
		return $data_rows > 0? $getFreeRound[0] : false;

    }

    public static function updateFreeSpinDetails($data, $freespin_id){
        return DB::table('freespin')->where('freespin_id',$freespin_id)->update($data);
    }

    public static function idenpotencyFreespinGamesTransID($game_trans_id){
		return DB::select("INSERT INTO  free_rounds_transaction (game_trans_id) VALUEs (".$game_trans_id.")");
        // return error
	}
    public static function createFreeRoundTransaction($data){
        return DB::table("free_rounds_transaction")->insertGetId($data);
    }

    public static function updateFreeRoundExtenstion($data, $freespin_exit_id){
        return DB::table('freespin_ext')->where('freespin_exit_id',$freespin_exit_id)->update($data);
    }

    public static function createFreeRoundExtenstion($data){
        return DB::table("freespin_ext")->insertGetId($data);
    }

    // /SAVE Freespin
    public static function createFreeRound($freeroundtransac){
        return DB::table("freespin")->insertGetId($freeroundtransac);
    }

    //SAVE Freespin
    public static function updateFreeRound($data,$id){
        return DB::table('freespin')->where('freespin_id',$id)->update($data);
    }
    
    public static function unique_code($limit)
	{
		return substr(base_convert(sha1(uniqid(mt_rand())), 16, 36), 0, $limit);
	}

    public static function createFreeRoundSlotmill($player_details,$data, $sub_provder_id, $freeround_id){
        $game_details = ProviderHelper::getSubGameDetails($sub_provder_id,$data["game_code"]);
        $player_prefix = "TG_";
        try{
            $freeroundtransac = [
                "player_id" => $player_details->player_id,
                "game_id" => $game_details->game_id,
                "total_spin" => $data["details"]["rounds"],
                "spin_remaining" => $data["details"]["rounds"],
                "denominations" => $data["details"]["denomination"],
                "date_expire" => $data["details"]["expiration_date"]
            ];
        } catch (\Exception $e) {
            return 400;
        }
        $id = FreeSpinHelper::createFreeRound($freeroundtransac);
        $endtime = date("Y-m-d H:i:s", strtotime($data["details"]["expiration_date"]));
        $client = new Client();

        $uid = "admin1";
        $pwd = "MNKWwPjL8w";
        $org = "TigerGames";
        $walletid = "TigerGamesTransfer";
        $URL = "https://stageapi.slotmill.com/game.admin.web/services/game/createprepaid.json?";

        try {
            $provider_response = $client->post( $URL. 'uid='.$uid.'&pwd='.$pwd.'&org='.$org.'&nativeId='.$player_prefix.$player_details->player_id.'&currency='.$player_details->default_currency.'&amount='.$data["details"]["denomination"].'&gameid='.$data["game_code"].'&consumebefore='.$endtime.'&ref='.$freeround_id.'&lang=en&createType=Yes&Count='.$data["details"]["rounds"].'&walletid='.$walletid);
            $dataresponse = json_decode($provider_response->getBody()->getContents());
        } catch (\Exception $e) {
            $data = [
                "status" => 3,
                "provider_trans_id" => $freeround_id,
                "details" => json_encode($dataresponse)
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
            return 400;
        }
        if ( isset($dataresponse->code) && $dataresponse->code == '0' ){
            //update freeroundtransac
            $data = [
                "provider_trans_id" => $freeround_id,
                "details" => json_encode($dataresponse)
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
            return 200;
        } else {
            $data = [
                "status" => 3,
                "provider_trans_id" => $freeround_id,
                "details" => json_encode($dataresponse)
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
            return 400;
        }
    }

    public static function createFreeRoundPNG($player_details,$data, $sub_provder_id,$freeround_id){
        $game_details = ProviderHelper::getSubGameDetails($sub_provder_id,$data["game_code"]);
        // $prefix = "TG_".FreeSpinHelper::unique_code(14)."-";//transaction
        try{
            $freeroundtransac = [
                "player_id" => $player_details->player_id,
                "game_id" => $game_details->game_id,
                "total_spin" => $data["details"]["rounds"],
                "spin_remaining" => $data["details"]["rounds"],
                "denominations" => $data["details"]["denomination"],
                "date_expire" => $data["details"]["expiration_date"],
                "lines" => $data["details"]["lines"],
                "coins" => $data["details"]["coins"],
            ];
        } catch (\Exception $e) {
            return 400;
        }
        $id = FreeSpinHelper::createFreeRound($freeroundtransac);
        $client = new Client();
        $game_array = "<arr:int>".$game_details->info."</arr:int>";
        $transaction_id = $freeround_id;
        try{
            $xmldatatopass = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:v1="http://playngo.com/v1" xmlns:arr="http://schemas.microsoft.com/2003/10/Serialization/Arrays">
                            <soapenv:Header/>
                            <soapenv:Body>
                            <v1:AddFreegameOffers>
                                <v1:UserId>'.$player_details->player_id.'</v1:UserId>
                                <v1:Lines>'.$data["details"]["lines"].'</v1:Lines>
                                <v1:Coins>'.$data["details"]["coins"].'</v1:Coins>
                                <v1:Denomination>'.$data["details"]["denomination"].'</v1:Denomination>
                                <v1:Rounds>'.$data["details"]["rounds"].'</v1:Rounds>
                                <v1:ExpireTime>'.$data["details"]["expiration_date"] .'z</v1:ExpireTime>
                                <v1:Turnover>0</v1:Turnover>
                                <v1:FreegameExternalId>'.$transaction_id.'</v1:FreegameExternalId>
                                <v1:GameIdList>
                                    '.$game_array.'
                                </v1:GameIdList>
                            </v1:AddFreegameOffers>
                            </soapenv:Body>
                        </soapenv:Envelope>';
            $client = new Client([
                'headers' => [
                    'Content-Type'=> 'text/xml',
                    'SOAPAction'=>'http://playngo.com/v1/CasinoGameTPService/AddFreegameOffers',
                ],
                'auth' => ['qdxapi','TwZbbsgmvdKhOalSyBoKpSIwK'],
            ]);
            $guzzle_response = $client->post('https://agastage.playngonetwork.com:33001/CasinoGameTPService',
                ['body' => $xmldatatopass]
            );
            $client_reponse = $guzzle_response->getBody()->getContents();
            $data = [
                "provider_trans_id" => $transaction_id,
                "details" => json_encode(FreeSpinHelper::soapXMLparser($client_reponse))
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
            return 200;
        }
        catch(\Exception $e){
            $data = [
                "status" => 3,
                "provider_trans_id" => $transaction_id,
                "details" =>json_encode( $e->getMessage())
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
            return 400;
        }
        
    }
    
    public static function createFreeRoundMannaplay($player_details,$data, $sub_provder_id,$freeround_id){
        $game_details = ProviderHelper::getSubGameDetails($sub_provder_id,$data["game_code"]);
        Helper::saveLog('Freespin '.  $sub_provder_id, $sub_provder_id,json_encode($freeround_id), 'HIT');
        try{
            $freeroundtransac = [
                "player_id" => $player_details->player_id,
                "game_id" => $game_details->game_id,
                "total_spin" => $data["details"]["rounds"],
                "spin_remaining" => $data["details"]["rounds"],
                "denominations" => $data["details"]["denomination"],
                "date_expire" => $data["details"]["expiration_date"],
                "provider_trans_id" =>$freeround_id,
            ];
        } catch (\Exception $e) {
            return 400;
        }
        $id = FreeSpinHelper::createFreeRound($freeroundtransac);
        // $endtime = date("Y-m-d H:i:s", strtotime($data["details"]["expiration_date"]));
        $details = ProviderHelper::getPlayerOperatorDetails("player_id", $player_details->player_id);
        if ($details->operator_id == 15){ // EveryMatix Config
            $api_key = config("providerlinks.mannaplay.15.API_KEY");
            $platform_id = config("providerlinks.mannaplay.15.PLATFORM_ID");
        }elseif($details->operator_id == 30){ // IDNPLAY
            $api_key = config("providerlinks.mannaplay.30.API_KEY");
            $platform_id = config("providerlinks.mannaplay.30.PLATFORM_ID");
        }else{
            $api_key = config("providerlinks.mannaplay.default.API_KEY");
            $platform_id = config("providerlinks.mannaplay.default.PLATFORM_ID");
        }
        $URL = config("providerlinks.mannaplay.FREE_ROUND_ADD");
        
        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json',
                'apiKey' => $api_key
            ]
        ]);
       
        try {
            $requestBody =   [
                "account" => "$player_details->player_id",
                "id" => $platform_id,
                "channel" =>"",
                "game_ids" => [
                    $data["game_code"]
                ],
                "numrounds" => $data["details"]["rounds"],
                "currency" => $player_details->default_currency,
                "bet" =>$data["details"]["denomination"],
                "opref" => $freeround_id,
                "expiretime" => $data["details"]["expiration_date"]
            ];
            Helper::saveLog('Freespin '.  $sub_provder_id, $sub_provder_id,json_encode($requestBody), 'HIT');
            $game_link_response = $client->post( $URL,
                    ['body' => json_encode($requestBody)]
                );
            $dataresponse = json_decode($game_link_response->getBody()->getContents());
            Helper::saveLog('Freespin '.  $sub_provder_id, $sub_provder_id,json_encode($requestBody),  json_encode($dataresponse));
      
        } catch (\Exception $e) {
            $createFreeround = [
                "status" => 3,
            ];
            FreeSpinHelper::updateFreeRound($createFreeround, $id);
            $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($requestBody),
                "provider_response" => json_encode($dataresponse),
                "client_request" => json_encode($data),
                "mw_response" => "400"
            ];
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
            return 400;
        }
        if ( !isset($dataresponse->errorCode) ){
            //update freeroundtransac
            $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($requestBody),
                "provider_response" => json_encode($dataresponse),
                "client_request" => json_encode($data),
                "mw_response" => "200"
            ];
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
            return 200;
        } else {
            $createFreeround = [
                "status" => 3,
            ];
            FreeSpinHelper::updateFreeRound($createFreeround, $id);
            $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($requestBody),
                "provider_response" => json_encode($dataresponse),
                "client_request" => json_encode($data),
                "mw_response" => "400"
            ];
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
            return 400;
        }
    }


    public static function createFreeRounNolimitCity($player_details,$data, $sub_provder_id,$freeround_id){
        Helper::saveLog('NOLIMIT CITY LOGS '.  $sub_provder_id, $sub_provder_id,json_encode($freeround_id), 'HIT');
        $game_details = ProviderHelper::getSubGameDetails($sub_provder_id,$data["game_code"]);// get game details
        if ($game_details){
            try{
                $insertFreespin = [
                    "player_id" => $player_details->player_id,
                    "game_id" => $game_details->game_id,
                    "total_spin" => $data["details"]["rounds"],
                    "spin_remaining" => $data["details"]["rounds"],
                    "denominations" => $data["details"]["denomination"],
                    "date_expire" => $data["details"]["expiration_date"],
                    "provider_trans_id" =>$freeround_id,
                ];
            } catch (\Exception $e) {
                return 400;
            }
            $id = FreeSpinHelper::createFreeRound($insertFreespin);// mu retunr og ID  sa databse
            $details = ProviderHelper::getPlayerOperatorDetails("player_id", $player_details->player_id);
            $requestBody = [
                "jsonrpc" => "2.0",
                "method" => "freebets.add",
                "params" => [
                    "identification" => [
                        "name" => config("providerlinks.nolimit.operator"),
                        "key" => config("providerlinks.nolimit.operator_key")
                    ],
                    "userId" => "$player_details->player_id",
                    "promoName" => $freeround_id,
                    "game" => $game_details->game_code,
                    "rounds" => $data["details"]["rounds"],
                    "amount" => [
                        "amount" => $data["details"]["denomination"], // denomination
                        "currency" => $details->default_currency
                    ],
                    "expires" => $data["details"]["expiration_date"]
                ],
                "id" => Uuid::generate()->string
            ];
            $client = new Client();
            try {
                $game_link_response = $client->post( config("providerlinks.nolimit.api_freebet"),
                    ['body' => json_encode($requestBody)]
                );
                $dataresponse = json_decode($game_link_response->getBody()->getContents()); // get response
                Helper::saveLog('Freespin '.  $sub_provder_id, $sub_provder_id,json_encode($requestBody),  json_encode($dataresponse));
            } catch (\Exception $e) {
                $createFreeround = [
                    "status" => 3,
                    // "provider_trans_id" => $freeround_id
                ];
                FreeSpinHelper::updateFreeRound($createFreeround, $id);
                $freespinExtenstion = [
                    "freespin_id" => $id,
                    "mw_request" => json_encode($requestBody),
                    "provider_response" => json_encode($dataresponse),
                    "client_request" => json_encode($data),
                    "mw_response" => "400"
                ];
                FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
                return 400;
            }
            if ( isset($dataresponse->error) ){
                //update freeroundtransac
                $createFreeround = [
                    "status" => 3,
                    // "provider_trans_id" => $freeround_id
                ];
                FreeSpinHelper::updateFreeRound($createFreeround, $id);
                $freespinExtenstion = [
                    "freespin_id" => $id,
                    "mw_request" => json_encode($requestBody),
                    "provider_response" => json_encode($dataresponse),
                    "client_request" => json_encode($data),
                    "mw_response" => "400"
                ];
                FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
                return 400;
            } else {
                // $createFreeround = [
                //     "provider_trans_id" => $freeround_id
                // ];
                // FreeSpinHelper::updateFreeRound($createFreeround, $id);
                $freespinExtenstion = [
                    "freespin_id" => $id,
                    "mw_request" => json_encode($requestBody),
                    "provider_response" => json_encode($dataresponse),
                    "client_request" => json_encode($data),
                    "mw_response" => "200"
                ];
                FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
                return 200;
            }
            
        } else {
            return 400;
        }
       
    }

    public static function soapXMLparser($data){
        $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $data);
        $xml = new SimpleXMLElement($response);
        $body = $xml->xpath('//sBody')[0];
        $array = json_decode(json_encode((array)$body), TRUE);
        return $array;
    }
    public static function createFreeRoundQuickSpinD($player_details,$data, $sub_provder_id, $freeround_id){
        $game_details = ProviderHelper::getSubGameDetails($sub_provder_id,$data["game_code"]);
        $prefix = "TG_".FreeSpinHelper::unique_code(14)."-";//transaction
        try{
            $freeroundtransac = [
                "player_id" => $player_details->player_id,
                "game_id" => $game_details->game_id,
                "total_spin" => $data["details"]["rounds"],
                "spin_remaining" => $data["details"]["rounds"],
                "denominations" => $data["details"]["freespinvalue"],
            ];
        } catch (\Exception $e) {
            return 400;
        }
        $id = FreeSpinHelper::createFreeRound($freeroundtransac);
        $username = "tigergames";
        $password = "stage-4j2UUY5MzGVzKAV";
        $credentials = base64_encode($username.":".$password);
        $httpClient = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . $credentials,
            ]
        ]);
        $baseUrl = "https://casino-partner-api.extstage.qs-gaming.com:7000/papi/1.0/casino/freespins/add";
        $response = $httpClient->post(
            $baseUrl,[
                'body' => json_encode([
                    'txid' => FreeSpinHelper::unique_code(14),
                    'remoteusername' => $player_details->player_id,
                    'gameid' => $data['game_code'],
                    'amount' => $data["details"]["rounds"],
                    'freespinvalue' => $data['details']['freespinvalue'],
                ]
            )]
        );
        $dataresponse = json_decode($response->getBody()->getContents());
        $data = [
            "status" => 3,
            "provider_trans_id" => $prefix.$id,
            "details" => json_encode($dataresponse)
        ];
        FreeSpinHelper::updateFreeRound($data, $id);
        if ( !isset($dataresponse->errorCode) ){
            //update freeroundtransac
            $data = [
                "provider_trans_id" => $prefix.$id,
                "details" => json_encode($dataresponse)
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
            return 200;
        } else {
            $data = [
                "status" => 3,
                "provider_trans_id" => $prefix.$id,
                "details" => json_encode($dataresponse)
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
            return 400;
        }
    }
    public static function createFreeRoundSpearHeadEm($player_details,$data, $sub_provder_id,$freeround_id){
        $game_details = ProviderHelper::getSubGameDetails($sub_provder_id,$data["game_code"]);
        $prefix = "TG_".FreeSpinHelper::unique_code(14)."-";//transaction
        try{
            $freeroundtransac = [
                "player_id" => $player_details->player_id,
                "game_id" => $game_details->game_id,
                "total_spin" => $data["details"]["rounds"],
                "spin_remaining" => $data["details"]["rounds"],
                "denominations" => $data["details"]["AdditionalParameters"]["BetValue"],
                "date_expire" => $data["details"]["FreeRoundsEndDate"],
            ];
        } catch (\Exception $e) {
            return 400;
        }
        $id = FreeSpinHelper::createFreeRound($freeroundtransac);
        $username = "TigerGamesStageBonus";
        $password = "YoqJcvRHFUcr2un4";
        $credentials = base64_encode($username.":".$password);
        $httpClient = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . $credentials,
            ]
        ]);
        $baseUrl = "https://vendorapi-stage.everymatrix.com/vendorbonus/RGS_Matrix/AwardBonus";
        try {
            $response = $httpClient->post(
                $baseUrl,[
                    'body' => json_encode([
                        'BonusSource' => 2,
                        'OperatorUserId' => $player_details->player_id,
                        'GameIds' => [
                            $game_details->info
                        ],
                        'NumberOfFreeRounds' => $data["details"]["rounds"],
                        'BonusId' => $id,
                        'FreeRoundsEndDate' => $data["details"]["FreeRoundsEndDate"],
                        'DomainId' => config("providerlinks.spearhead.opid"),
                        'UserDetails' => [
                            "CountryAlpha3Code" => "JPN",
                            "Gender" => "Male",
                            "Alias" => $player_details->display_name,
                            "Currency" => $player_details->default_currency,
                            "Firstname" => $player_details->username,
                            "Lastname" => $player_details->username,
                            "OperatorUserId" => $player_details->player_id,
                        ],
                        "AdditionalParameters" => [
                            "BetValue" => $data['details']['AdditionalParameters']['BetValue'],
                        ],
                    ]
                )]
            );
            $dataresponse = json_decode($response->getBody()->getContents());
        } catch (\Exception $e) {
            $dataresponse = [
                "error" => json_encode($e)
            ];
            $data = [
                "status" => 3,
                "provider_trans_id" => $prefix.$id,
                "details" => json_encode($dataresponse)
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
        }
        
        if (isset($dataresponse->error) ){
            //update freeroundtransac
            $data = [
                "status" => 3,
                "provider_trans_id" => $prefix.$id,
                "details" => json_encode($dataresponse)
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
            return 400;
        } else {
            $data = [
                "provider_trans_id" => $prefix.$id,
                "details" => json_encode($dataresponse)
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
            return 200;
        }
        Helper::saveLog('Spearhead freespin response', 67, json_encode($data), $response->getBody()->getContents());
    }
    public static function BNGcreateFreeBet($player_details,$data, $sub_provder_id,$round_id){
        $game_details = ProviderHelper::getSubGameDetails($sub_provder_id,$data["game_code"]);
        $prefix = "TG_".FreeSpinHelper::unique_code(14)."-";//transaction
        try{
            if($data["details"]["bonus_type"] != "FIXED_FREEBET"){
                $freeroundtransac = [
                    "player_id" => $player_details->player_id,
                    "game_id" => $game_details->game_id,
                ];
            }else{
                $freeroundtransac = [
                    "player_id" => $player_details->player_id,
                    "game_id" => $game_details->game_id,
                    "total_spin" => $data["details"]["rounds"],
                    "spin_remaining" => $data["details"]["rounds"],
                ];
            }
        } catch (\Exception $e) {
            return 400;
        }
        $id = FreeSpinHelper::createFreeRound($freeroundtransac);
        $client = new Client();
        // $baseUrl = config("providerlinks.boongo.PLATFORM_SERVER_URL").config("providerlinks.boongo.tigergames-stage")."/tigergames-stage/api/v1/bonus/create/";
        $baseUrl = "https://gate-stage.betsrv.com/op/tigergames-stage/api/v1/bonus/create/";
        try {
            if($data["details"]["bonus_type"] != "FIXED_FREEBET"){
                $response = $client->post(
                    $baseUrl,[
                        'body' => json_encode([
                                "api_token" => config("providerlinks.boongo.API_TOKEN"),
                                "mode" => "REAL",
                                "campaign" => "tigergames",
                                "game_id" => $game_details->game_code,
                                "bonus_type" => $data["details"]["bonus_type"],
                                "currency" => $player_details->default_currency,
                                "total_bet" => $data["details"]["bet_value"],
                                "bonuses" => array([
                                    "player_id" => (string) $player_details->player_id,
                                    "ext_bonus_id" => (string) $id
                                ]),
                            ]
                    )]
                );//end client post
            }else{
                $response = $client->post(
                    $baseUrl,[
                        'body' => json_encode([
                                "api_token" => config("providerlinks.boongo.API_TOKEN"),
                                "mode" => "REAL",
                                "campaign" => "tigergames",
                                "game_id" => $game_details->game_code,
                                "bonus_type" => $data["details"]["bonus_type"],
                                "currency" => $player_details->default_currency,
                                "total_rounds" => (int)$data["details"]["rounds"],
                                "round_bet" => $data["details"]["bet_value"],
                                "bonuses" => array([
                                    "player_id" => (string) $player_details->player_id,
                                    "ext_bonus_id" => (string) $id
                                ]),
                            ]
                    )]
                );//end client post
            }
            $dataresponse = json_decode($response->getBody()->getContents());
        } catch (\Exception $e) {
            $dataresponse = [
                "error" => json_encode($e)
            ];
            $data = [
                "status" => 3,
                "provider_trans_id" => $prefix.$id,
                "details" => json_encode($dataresponse)
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
        }
        if (isset($dataresponse->error)){
            //update freeroundtransac
            $data = [
                "status" => 3,
                "provider_trans_id" => $prefix.$id,
                "details" => json_encode($dataresponse)
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
            return 400;
        } else {
            $data = [
                "provider_trans_id" => $prefix.$id,
                "details" => json_encode($dataresponse)
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
            return 200;
        }
        Helper::saveLog('BNG freespin response', 44, json_encode($data), $response->getBody()->getContents());
    }   
    public static function createFreeRoundWazdan($player_details,$data, $sub_provder_id,$freeround_id){
        Helper::saveLog('freeSpin(Wazdan)'. $sub_provder_id, $sub_provder_id,json_encode($freeround_id), 'HIT');//savelog
        $game_details = ProviderHelper::getSubGameDetails($sub_provder_id,$data["game_code"]);// get game details
        //  dd($game_details);.
        if($game_details){
            try{
                //freeSpin type
                if($data["details"]["type"] != "regular") {
                    $insertFreespin = [
                        "player_id" => $player_details->player_id,
                        "game_id" => $game_details->game_id,
                        "denominations" => $data["details"]["denomination"],
                        "date_expire" => $data["details"]["expiration_date"],
                        "provider_trans_id" => $freeround_id,
                        "date_start" => $data["details"]["start_time"]
                    ];
                } else {
                    $insertFreespin = [
                        "player_id" => $player_details->player_id,
                        "game_id" => $game_details->game_id,
                        "total_spin" => $data["details"]["rounds"],
                        "spin_remaining" => $data["details"]["rounds"],
                        "denominations" => $data["details"]["denomination"],
                        "date_expire" => $data["details"]["expiration_date"],
                        "provider_trans_id" => $freeround_id,
                        "date_start" => $data["details"]["start_time"]
                    ];
                }
                }catch(\Exception $e){
                    return 400;
                }
                $id = FreeSpinHelper::createFreeRound($insertFreespin);//insert Freespin
                // dd($id);
                $startDate = date("Y-m-d H:i:s", strtotime($data["details"]["start_time"]));
                $endtime = date("Y-m-d H:i:s", strtotime($data["details"]["expiration_date"]));
                $details= ProviderHelper::getPlayerOperatorDetails("player_id", $player_details->player_id);//getoperatorDetails
                // dd($details);
                //freeSpin type
                if($data["details"]["type"] != "regular") {
                    $requestBody = [
                        "playerId"=> $player_details->player_id,
                        "type"=> $data["details"]["type"],
                        "currency"=> $player_details->default_currency,
                        "txId"=> $freeround_id,
                        "gameId"=> $game_details->game_code,
                        "operator"=> config("providerlinks.wazdan.operator"),
                        "license"=> config("providerlinks.wazdan.license"),
                        "stake"=> $data["details"]["denomination"],
                        "value"=> $data["details"]["denomination"], //total amount
                        "startDate"=> $startDate,
                        "endDate" => $endtime
                    ];
                } else {
                    $requestBody = [
                        "playerId"=> $player_details->player_id,
                        "type"=> $data["details"]["type"],
                        "currency"=> $player_details->default_currency,
                        "count"=> $data["details"]["rounds"],
                        "txId"=> $freeround_id,
                        "gameId"=> $game_details->game_code,
                        "operator"=> config("providerlinks.wazdan.operator"),
                        "license"=> config("providerlinks.wazdan.license"),
                        "stake"=> $data["details"]["denomination"],
                        "startDate"=> $startDate,
                        "endDate" => $endtime
                    ];
                }
                $api_key = WazdanHelper::generateSignature($requestBody);
                $client = new Client(['headers' => [ 
                    'Content-Type' => 'application/json',
                    'Signature' => $api_key["hmac"]
                    ]
                ]);
                try{
                    $game_link_response = $client->post(config("providerlinks.wazdan.api_freeRound"),
                    ['body' => json_encode($requestBody)]);
                    $dataresponse = json_decode($game_link_response->getBody()->getContents()); // get response
                    // dd($dataresponse);
                    Helper::saveLog('freeSpin(Wazdan)'. $sub_provder_id, $sub_provder_id,json_encode($requestBody),  json_encode($dataresponse));
                }catch(\Exception $e){
                    $createFreeround = [
                        "status" => 3,
                        // "provider_trans_id" => $freeround_id
                    ];
                    FreeSpinHelper::updateFreeRound($createFreeround, $id);
                    $freespinExtenstion = [
                        "freespin_id" => $id,
                        "mw_request" => json_encode($requestBody),
                        "provider_response" => json_encode($dataresponse),
                        "client_request" => json_encode($data),
                        "mw_response" => "400"
                    ];
                    FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
                    return 400;
                }
                if ( isset($dataresponse->error) ){
                    //update freeroundtransac
                    $createFreeround = [
                        "status" => 3,
                        // "provider_trans_id" => $freeround_id
                    ];
                    FreeSpinHelper::updateFreeRound($createFreeround, $id);
                    $freespinExtenstion = [
                        "freespin_id" => $id,
                        "mw_request" => json_encode($requestBody),
                        "provider_response" => json_encode($dataresponse),
                        "client_request" => json_encode($data),
                        "mw_response" => "400"
                    ];
                    FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
                    return 400;
                } else {
                    // $createFreeround = [
                    //     "provider_trans_id" => $freeround_id
                    // ];
                    // FreeSpinHelper::updateFreeRound($createFreeround, $id);
                    $freespinExtenstion = [
                        "freespin_id" => $id,
                        "mw_request" => json_encode($requestBody),
                        "provider_response" => json_encode($dataresponse),
                        "client_request" => json_encode($data),
                        "mw_response" => "200"
                    ];
                    FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
                    return 200;
                }
        } else {
            return 400;
        }
    }
}
?>