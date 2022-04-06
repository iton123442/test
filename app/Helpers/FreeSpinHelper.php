<?php

namespace App\Helpers;

use App\Services\AES;
use GuzzleHttp\Client;
use App\Helpers\ProviderHelper;
use App\Helpers\WazdanHelper;
use App\Helpers\TGGHelper;
use SimpleXMLElement;
use Webpatser\Uuid\Uuid;
use App\Helpers\ClientRequestHelper;
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
        $getFreeRound = DB::select('select freespin_id,player_id,game_id,spin_remaining, status, win,details,provider_trans_id from freespin ' . $where);
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
        $player_prefix = config("providerlinks.slotmill.prefix");
        try{
            $freeroundtransac = [
                "player_id" => $player_details->player_id,
                "game_id" => $game_details->game_id,
                "total_spin" => $data["details"]["rounds"],
                "spin_remaining" => $data["details"]["rounds"],
                "denominations" => $data["details"]["denomination"],
                "date_expire" => $data["details"]["expiration_date"],
                "date_start" => $data["details"]["start_time"],
                "provider_trans_id" => $freeround_id,
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
        $request = $URL. 'uid='.$uid.'&pwd='.$pwd.'&org='.$org.'&nativeId='.$player_prefix.$player_details->player_id.'&currency='.$player_details->default_currency.'&amount='.$data["details"]["denomination"].'&gameid='.$data["game_code"].'&consumebefore='.$endtime.'&ref='.$freeround_id.'&lang=en&createType=Yes&Count='.$data["details"]["rounds"].'&walletid='.$walletid;
        try {
            $provider_response = $client->post( $URL. 'uid='.$uid.'&pwd='.$pwd.'&org='.$org.'&nativeId='.$player_prefix.$player_details->player_id.'&currency='.$player_details->default_currency.'&amount='.$data["details"]["denomination"].'&gameid='.$data["game_code"].'&consumebefore='.$endtime.'&ref='.$freeround_id.'&lang=en&createType=Yes&Count='.$data["details"]["rounds"].'&walletid='.$walletid);
            $dataresponse = json_decode($provider_response->getBody()->getContents());
        } catch (\Exception $e) {
            $toFailed = [
                "status" => 3,
            ];
            FreeSpinHelper::updateFreeRound($toFailed, $id);
            $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($request),
                "client_request" => json_encode($data),
                "mw_response" => $e->getMessage(),
            ];
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
            return 400;
        }
        if ( isset($dataresponse->code) && $dataresponse->code == '0' ){
            $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($request),
                "provider_response" =>json_encode($dataresponse),
                "client_request" => json_encode($data),
                "mw_response" => "200"
            ];
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
            return 200;
        }

        $toFailed = [
            "status" => 3,
        ];
        FreeSpinHelper::updateFreeRound($toFailed, $id);
        $freespinExtenstion = [
            "freespin_id" => $id,
            "mw_request" => json_encode($request),
            "client_request" => json_encode($data),
            "provider_response" =>json_encode($dataresponse),
            "mw_response" => "400",
        ];
        FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
        return 400;
    }

    public static function createFreeRoundPNG($player_details,$data, $sub_provder_id,$freeround_id){
        $game_details = ProviderHelper::getSubGameDetails($sub_provder_id,$data["game_code"]);
       
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
                "date_start" => $data["details"]["start_time"],
                "provider_trans_id" => $freeround_id,
            ];
        } catch (\Exception $e) {
            return 400;
        }
        $id = FreeSpinHelper::createFreeRound($freeroundtransac);
        $game_array = "<arr:int>".$game_details->info."</arr:int>";
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
                                <v1:FreegameExternalId>'.$freeround_id.'</v1:FreegameExternalId>
                                <v1:GameIdList>
                                    '.$game_array.'
                                </v1:GameIdList>
                            </v1:AddFreegameOffers>
                            </soapenv:Body>
                        </soapenv:Envelope>';
            $client = new Client([
                'headers' => [
                    'Content-Type'=> 'text/xml',
                    'SOAPAction'=>config("providerlinks.png.SOAP_URL"),
                ],
                'auth' => [ config("providerlinks.png.username"),config("providerlinks.png.secret_key")],
            ]);
            try {
                $guzzle_response = $client->post(config("providerlinks.png.freeRoundAPI_URL") ,
                    ['body' => $xmldatatopass]
                );
                $client_reponse = $guzzle_response->getBody()->getContents();
                $response = FreeSpinHelper::soapXMLparser($client_reponse);
            } catch (\Exception $e) {
                $getMessage =  $e->getMessage();
            }
            if(isset($response["AddFreegameOffersResponse"]["AddFreegameOffersResult"])){
                $freespinExtenstion = [
                    "freespin_id" => $id,
                    "mw_request" => json_encode($xmldatatopass),
                    "provider_response" => json_encode(FreeSpinHelper::soapXMLparser($client_reponse)),
                    "client_request" => json_encode($data),
                    "mw_response" => "200"
                ];
                FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
                return 200;
            }
            $toFailed = [
                "status" => 3,
            ];
            FreeSpinHelper::updateFreeRound($toFailed, $id);
            $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($xmldatatopass),
                // "provider_response" => json_encode(FreeSpinHelper::soapXMLparser($client_reponse)),
                "client_request" => json_encode($data),
                "mw_response" => $getMessage
            ];
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
            return 400;
        }
        catch(\Exception $e){
            $toFailed = [
                "status" => 3,
            ];
            FreeSpinHelper::updateFreeRound($toFailed, $id);
            $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($xmldatatopass),
                "client_request" => json_encode($data),
                "mw_response" => $e->getMessage(),
            ];
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
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
        if($game_details == false){
            return 400;
        }
        $quickSpinValue = $data["details"]["denomination"] * 100;
        try{
            $freeroundtransac = [
                "player_id" => $player_details->player_id,
                "game_id" => $game_details->game_id,
                "total_spin" => $data["details"]["rounds"],
                "spin_remaining" => $data["details"]["rounds"],
                "provider_trans_id" => $freeround_id,
                "denominations" => $data["details"]["denomination"],
                "date_expire" => $data["details"]["expiration_date"],
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
        $requesttosend = [
            'txid' => $freeround_id,
            'remoteusername' => $player_details->player_id,
            'gameid' => $data['game_code'],
            'amount' => (int)$data["details"]["rounds"],
            'freespinvalue' => (int)$quickSpinValue,
            'promocode' => "TG".$freeround_id
        ];
        $baseUrl = config("providerlinks.quickspinDirect.partner_api_url")."/freespins/add";
        try {
            $response = $httpClient->post($baseUrl,['body' => json_encode($requesttosend)]);
            $dataresponse = json_decode($response->getBody()->getContents());
        } catch (\Exception $e) {
            $msg = array(
                'err_message' => $e->getMessage(),
                'err_line' => $e->getLine(),
                'err_file' => $e->getFile()
            );
            $data = [
                "status" => 3,
                "details" => json_encode("FAILED")
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
            $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($requesttosend),
                "provider_response" => json_encode($msg),
                "client_request" => json_encode($data),
                "mw_response" => "200"
            ];
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
            return 400;
        }
        if ($dataresponse->status == 'ok'){
            $data = [
                "details" => json_encode($dataresponse->freespinids[0][1])
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
            $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($requesttosend),
                "provider_response" => json_encode($dataresponse),
                "client_request" => json_encode($data),
                "mw_response" => "200"
            ];
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
            return 200;
        } else {
           $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($requesttosend),
                "provider_response" => json_encode($dataresponse),
                "client_request" => json_encode($data),
                "mw_response" => "400"
            ];
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
            return 400;
        }
    }
    public static function cancelFreeRoundQuickSpin($freeround_id){
        $getFreespin = FreeSpinHelper::getFreeSpinDetails($freeround_id, "provider_trans_id" );
        $requesttosend = [
            "freespinsid" => json_decode($getFreespin->details)
        ];
        // https://casino-partner-api.extstage.qs-gaming.com:7000/papi/1.0/casino/freespins/cancel
        $api_url = config("providerlinks.quickspinDirect.partner_api_url")."/freespins/cancel";
        $username = "tigergames";
        $password = "stage-4j2UUY5MzGVzKAV";
        $credentials = base64_encode($username.":".$password);
        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . $credentials,
            ]
        ]);
        try {
            $response = $client->post($api_url,['body' => json_encode($requesttosend)]);
            $dataresponse = json_decode($response->getBody()->getContents());
            $data = [
                    "status" => 4,
            ];
            FreeSpinHelper::updateFreeRound($data, $getFreespin->freespin_id);
            return 200;
        } catch (\Exception $e) {
            return 400;
        }
    }
    public static function createFreeRoundSpearHeadEm($player_details,$data, $sub_provder_id,$freeround_id){
        $game_details = ProviderHelper::getSubGameDetails($sub_provder_id,$data["game_code"]);
        if($game_details == false){
            return 400;
        }
        try{
            $freeroundtransac = [
                "player_id" => $player_details->player_id,
                "game_id" => $game_details->game_id,
                "total_spin" => $data["details"]["rounds"],
                "spin_remaining" => $data["details"]["rounds"],
                "provider_trans_id" => $freeround_id,
                "denominations" => $data["details"]["denomination"],
                "date_expire" => $data["details"]["expiration_date"],
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
        $requesttosend = [
            "BonusSource" => 2,
            'OperatorUserId' => $player_details->player_id,
            "GameIds" => [
                $game_details->info
            ],
            'NumberOfFreeRounds' => $data["details"]["rounds"],
            'BonusId' => $freeround_id,
            'FreeRoundsEndDate' => $data["details"]["expiration_date"],
            'DomainId' => config("providerlinks.spearhead.opid"),
            "round_bet" => $data["details"]["denomination"],
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
                "BetValue" => $data['details']['denomination'],
            ],
        ];
        $baseUrl = "https://vendorapi-stage.everymatrix.com/vendorbonus/RGS_Matrix/AwardBonus";
        try {
            $response = $httpClient->post(
                $baseUrl,[
                    'body' => json_encode($requesttosend)]
            );
            $dataresponse = json_decode($response->getBody()->getContents());
        } catch (\Exception $e) {
            $dataresponse = [
                "error" => json_encode($e)
            ];
            $data = [
                "status" => 3,
                "provider_trans_id" => $id,
                "details" => json_encode($dataresponse)
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
        }
        if (isset($dataresponse->error) ){
            //update freeroundtransac
            $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($requesttosend),
                "provider_response" => json_encode($dataresponse),
                "client_request" => json_encode($data),
                "mw_response" => "200"
            ];
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
            return 400;
        } else {
            $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($requesttosend),
                "provider_response" => json_encode($dataresponse),
                "client_request" => json_encode($data),
                "mw_response" => "200"
            ];
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
            return 200;
        }
        Helper::saveLog('Spearhead freespin response', 67, json_encode($data), $response->getBody()->getContents());
    }
    public static function cancelFreeSpinSpearHead($freeround_id){
        $getFreespin = FreeSpinHelper::getFreeSpinDetails($freeround_id, "provider_trans_id" );
        // dd($getFreespin);
        $username = "TigerGamesStageBonus";
        $password = "YoqJcvRHFUcr2un4";
        $credentials = base64_encode($username.":".$password);
        $httpClient = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . $credentials,
            ]
        ]);
        $requesttosend = [
            "BonusSource" => 2,
            "Comment" => "This is Staging Test",
            "OperatorUserId" => $getFreespin->player_id,
            "BonusId" => $freeround_id,
            "DomainId" => config("providerlinks.spearhead.opid")
        ];
        $baseUrl = "https://vendorapi-stage.everymatrix.com/vendorbonus/RGS_Matrix/ForfeitBonus";
        try {
            $response = $httpClient->post(
                $baseUrl,[
                    'body' => json_encode($requesttosend)]
            );
            $dataresponse = json_decode($response->getBody()->getContents());
             $data = [
                    "status" => 4,
            ];
            FreeSpinHelper::updateFreeRound($data, $getFreespin->freespin_id);
            return 200;
        } catch (\Exception $e) {
            $dataresponse = [
                "error" => json_encode($e)
            ];
            $data = [
                "status" => 0,
                "provider_trans_id" => $id,
                "details" => json_encode($dataresponse)
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
            return 400;
        }
    }
    public static function BNGcreateFreeBet($player_details,$data, $sub_provder_id,$freeround_id){
        $game_details = ProviderHelper::getSubGameDetails($sub_provder_id,$data["game_code"]);
        if($game_details == false){
            return 400;
        }
        try{
            if($data["details"]["type"] != "FIXED_FREEBET"){
                $freeroundtransac = [
                    "player_id" => $player_details->player_id,
                    "game_id" => $game_details->game_id,
                    "date_expire" => $data["details"]["expiration_date"],
                    "provider_trans_id" => $freeround_id,
                    "denominations" => $data["details"]["denomination"],
                    "date_start" => $data["details"]["start_time"]
                ];
            }else{
                $freeroundtransac = [
                    "player_id" => $player_details->player_id,
                    "game_id" => $game_details->game_id,
                    "total_spin" => $data["details"]["rounds"],
                    "spin_remaining" => $data["details"]["rounds"],
                    "date_expire" => $data["details"]["expiration_date"],
                    "provider_trans_id" => $freeround_id,
                    "denominations" => $data["details"]["denomination"],
                    "date_start" => $data["details"]["start_time"]
                ];
            }
        } catch (\Exception $e) {
            return 400;
        }
        $id = FreeSpinHelper::createFreeRound($freeroundtransac);
        $client = new Client();
        // $baseUrl = config("providerlinks.boongo.PLATFORM_SERVER_URL").config("providerlinks.boongo.tigergames-stage")."/tigergames-stage/api/v1/bonus/create/";
        $baseUrl = "https://gate-stage.betsrv.com/op/tigergames-stage/api/v1/bonus/create/";
            if($data["details"]["type"] != "FIXED_FREEBET"){
                $requesttosend = [
                    "api_token" => config("providerlinks.boongo.API_TOKEN"),
                    "mode" => "REAL",
                    "campaign" => $freeround_id,
                    "game_id" => $game_details->game_code,
                    "bonus_type" => $data["details"]["type"],
                    "currency" => $player_details->default_currency,
                    "total_bet" => $data["details"]["denomination"],
                    "bonuses" => array([
                        "player_id" => (string) $player_details->player_id,
                        "ext_bonus_id" => (string) $id
                    ])
                ];
                // $response = $client->post(
                //     $baseUrl,[
                //         'body' => json_encode([
                //                 "api_token" => config("providerlinks.boongo.API_TOKEN"),
                //                 "mode" => "REAL",
                //                 "campaign" => "tigergames",
                //                 "game_id" => $game_details->game_code,
                //                 "bonus_type" => $data["details"]["type"],
                //                 "currency" => $player_details->default_currency,
                //                 "total_bet" => $data["details"]["denomination"],
                //                 "bonuses" => array([
                //                     "player_id" => (string) $player_details->player_id,
                //                     "ext_bonus_id" => (string) $id
                //                 ]),
                //             ]
                //     )]
                // );//end client post
            }else{
                $requesttosend = [
                    "api_token" => config("providerlinks.boongo.API_TOKEN"),
                    "mode" => "REAL",
                    "campaign" => $freeround_id,
                    "game_id" => $game_details->game_code,
                    "bonus_type" => $data["details"]["type"],
                    "currency" => $player_details->default_currency,
                    "total_rounds" => (int)$data["details"]["rounds"],
                    "round_bet" => $data["details"]["denomination"],
                    "bonuses" => array([
                        "player_id" => (string) $player_details->player_id,
                        "ext_bonus_id" => (string) $id
                    ]),
                ];
                // $response = $client->post(
                //     $baseUrl,[
                //         'body' => json_encode([
                //                 "api_token" => config("providerlinks.boongo.API_TOKEN"),
                //                 "mode" => "REAL",
                //                 "campaign" => "tigergames",
                //                 "game_id" => $game_details->game_code,
                //                 "bonus_type" => $data["details"]["type"],
                //                 "currency" => $player_details->default_currency,
                //                 "total_rounds" => (int)$data["details"]["rounds"],
                //                 "round_bet" => $data["details"]["denomination"],
                //                 "bonuses" => array([
                //                     "player_id" => (string) $player_details->player_id,
                //                     "ext_bonus_id" => (string) $id
                //                 ]),
                //             ]
                //     )]
                // );//end client post
            }
        try {
            $response = $client->post($baseUrl,['body' => json_encode($requesttosend)]);
            $dataresponse = json_decode($response->getBody()->getContents());
        } catch (\Exception $e) {
            $dataresponse = [
                "error" => json_encode($e)
            ];
            $data = [
                "status" => 3,
                // "details" => json_encode($dataresponse)
            ];
            Helper::saveLog('BNG freespin error', 44, json_encode($data), json_encode($dataresponse));
            FreeSpinHelper::updateFreeRound($data, $id);
        }
        if (isset($dataresponse->error)){
            //update freeroundtransac
            $data = [
                "status" => 3,
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
            $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($requesttosend),
                "provider_response" => json_encode($dataresponse),
                "client_request" => json_encode($data),
                "mw_response" => "400"
            ];
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
            return 400;
        } else {
            $data = [
                "details" => json_encode($dataresponse->items[0]->bonus_id)
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
            $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($requesttosend),
                "provider_response" => json_encode($dataresponse),
                "client_request" => json_encode($data),
                "mw_response" => "200"
            ];
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
            return 200;
        }
        Helper::saveLog('BNG freespin response', 44, json_encode($data), json_encode($dataresponse));
    }  
    public static function cancelFreeRoundBNG($freeround_id){
        $getFreespin = FreeSpinHelper::getFreeSpinDetails($freeround_id, "provider_trans_id" );
        // dd($client_details);
        $requesttosend = [
            "api_token" => config("providerlinks.boongo.API_TOKEN"),
            "bonus_id" => json_decode($getFreespin->details)
        ];
        $api_url = config("providerlinks.boongo.PLATFORM_SERVER_URL")."tigergames-stage/api/v1/bonus/delete";
        $client = new Client();
        try {
            $response = $client->post($api_url,['body' => json_encode($requesttosend)]);
            $dataresponse = json_decode($response->getBody()->getContents());
            $data = [
                    "status" => 4,
            ];
            FreeSpinHelper::updateFreeRound($data, $getFreespin->freespin_id);
            return 200;
        } catch (\Exception $e) {
            return 400;
        }
    } 
    public static function issueFreeSpinBGaming($player_details,$data, $sub_provder_id,$freeround_id){
        $game_details = ProviderHelper::getSubGameDetails($sub_provder_id,$data["game_code"]);
        if($game_details == false){
            return 400;
        }
        try{
            $freeroundtransac = [
                "player_id" => $player_details->player_id,
                "game_id" => $game_details->game_id,
                "total_spin" => $data["details"]["rounds"],
                "spin_remaining" => $data["details"]["rounds"],
                "provider_trans_id" => $freeround_id,
                "denominations" => $data["details"]["denomination"],
                "date_start" => $data["details"]["start_time"],
                "date_expire" => $data["details"]["expiration_date"],
            ];
        } catch (\Exception $e) {
            return 400;
        }
        $id = FreeSpinHelper::createFreeRound($freeroundtransac);
        // list($registration_date, $registration_time) = explode(" ", $player_details->created_at);
        $secret = config('providerlinks.bgaming.AUTH_TOKEN');
        $requesttosend = [
            "casino_id" => config("providerlinks.bgaming.CASINO_ID"),
            "issue_id" => $freeround_id,
            "currency" => $player_details->default_currency,
            "games" => [
                $game_details->game_code
            ],
            "valid_until" => $data["details"]["expiration_date"],
            "bet_level" => $data["details"]["denomination"],
            "freespins_quantity" => $data["details"]["rounds"],
            "user" => [
                "id" => $player_details->player_id,
                "email" => $player_details->email,
                "firstname" => $player_details->username,
                "lastname" => $player_details->username,
                "nickname" => $player_details->display_name,
                "city" => "PH",
                "country" => "PH",
                "date_of_birth" => "2021-01-29",
                "gender" => "m"
            ],
        ];
        // dd(json_encode($requesttosend));
        $signature = hash_hmac('sha256',json_encode($requesttosend),$secret);
        // dd($signature);
        $api_url = config("providerlinks.bgaming.GCP_URL")."/freespins/issue";
        $client = new Client(['headers' => [ 
                    'Content-Type' => 'application/json',
                    'X-REQUEST-SIGN' => $signature
                ]
            ]);
        try {
            $response = $client->post($api_url,['body' => json_encode($requesttosend)]);
            $dataresponse = json_decode($response->getBody()->getContents());
            $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($requesttosend),
                "provider_response" => "SUCCEESSS",
                "client_request" => json_encode($data),
                "mw_response" => "200"
            ];
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
            return 200;
        } catch (\Exception $e) {
            $msg = array(
                'err_message' => $e->getMessage(),
                'err_line' => $e->getLine(),
                'err_file' => $e->getFile()
            );
            $data = [
                "status" => 3,
                "details" => json_encode($msg)
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
            Helper::saveLog('BGAMING freespin error', 44, json_encode($data), json_encode($dataresponse));
            return 400;
        }

    } 
    public static function cancelFreeSpinBGaming($freeround_id){
        $getFreespin = FreeSpinHelper::getFreeSpinDetails($freeround_id, "provider_trans_id" );
        $requesttosend = [
            "casino_id" => config("providerlinks.bgaming.CASINO_ID"),
            "issue_id" => $freeround_id
        ];
        $signature = hash_hmac('sha256',json_encode($requesttosend),config("providerlinks.bgaming.AUTH_TOKEN"));
        $api_url = config("providerlinks.bgaming.GCP_URL")."/freespins/cancel";
        $client = new Client(['headers' => [ 
                'Content-Type' => 'application/json',
                'X-REQUEST-SIGN' => $signature
            ]
        ]);
        try {
            $response = $client->post($api_url,['body' => json_encode($requesttosend)]);
            $dataresponse = json_decode($response->getBody()->getContents());
            $data = [
                    "status" => 4,
            ];
            FreeSpinHelper::updateFreeRound($data, $getFreespin->freespin_id);
            return 200;
        } catch (\Exception $e) {
            $msg = array(
                'err_message' => $e->getMessage(),
                'err_line' => $e->getLine(),
                'err_file' => $e->getFile()
            );
            Helper::saveLog('BGaming Cancelfreespin error', 57, json_encode($freeround_id), json_encode($msg));
            return 400;
        }
    } 
    public static function createFreeRoundWazdan($player_details,$data, $sub_provder_id,$freeround_id){
        Helper::saveLog('freeSpin(Wazdan)'. $sub_provder_id, 33,json_encode($freeround_id), 'HIT');//savelog
        $game_details = ProviderHelper::getSubGameDetails($sub_provder_id,$data["game_code"]);// get game details
        //  dd($game_details);
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
                    $game_link_response = $client->post(config("providerlinks.wazdan.freeround_api_link")."/add/",
                    ['body' => json_encode($requestBody)]);
                    $dataresponse = json_decode($game_link_response->getBody()->getContents()); // get response
                    // dd($dataresponse);
                }catch(\Exception $e){
                    $createFreeround = [
                        "status" => 3,
                        // "provider_trans_id" => $freeround_id
                    ];
                    
                    Helper::saveLog('freeSpin(Wazdan)'. $sub_provder_id, 33,json_encode($requestBody),  json_encode($dataresponse));
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

    public static function cancelFreeRoundWazdan($freeround_id){
        Helper::saveLog('Wazdan CancelFreeRound',57, json_encode($freeround_id), 'Cancel FreeRound HIT!');
        
         $getFreespin = FreeSpinHelper::getFreeSpinDetails($freeround_id, "provider_trans_id" );
         if(isset($getFreespin)) {
            $baseUrl = config("providerlinks.wazdan.freeround_api_link")."/forfeit/";
            $datatosend = [
                "playerId"=> $getFreespin->player_id,
                "txId"=> $freeround_id,
                "operator"=> config("providerlinks.wazdan.operator"),
                "license"=>config("providerlinks.wazdan.license")
            ];
            $api_key = WazdanHelper::generateSignature($datatosend);
            $client = new Client(['headers' => [ 
                'Content-Type' => 'application/json',
                'Signature' => $api_key["hmac"]
                ]
            ]);
            try{
                $game_link_response = $client->post($baseUrl,['body' => json_encode($datatosend)]);
                $dataresponse = json_decode($game_link_response->getBody()->getContents());
                $data = [
                    "status" => 4,
                ];
                Helper::saveLog('Wazdan Cancelfreespin Success', 57, json_encode($data), json_encode($dataresponse));
                FreeSpinHelper::updateFreeRound($data, $getFreespin->freespin_id);

                 return 200;
            }catch (\Exception $e) {
                $dataresponse = [
                    "error" => json_encode($e)
                ];
                $data = [
                    "status" => 0,
                ];

                Helper::saveLog('Wazdan Cancelfreespin error', 57, json_encode($data), json_encode($dataresponse));

                FreeSpinHelper::updateFreeRound($data, $getFreespin->freespin_id);
            
                return 400;
            }
    
         }
        
    }

    public static function createFreeRoundTGG($player_details,$data, $sub_provder_id,$freeround_id){
        Helper::saveLog('TGG Freespin', $sub_provder_id,json_encode($freeround_id), 'Freespin HIT');
        $game_details = ProviderHelper::getSubGameDetails($sub_provder_id,$data["game_code"]);// get game details
        if($game_details){
            try{
                //freeSpin type
                if($data["details"]["type"] == "bonus_spins") {
                    $insertFreespin = [
                        "player_id" => $player_details->player_id,
                        "game_id" => $game_details->game_id,
                        "total_spin" => $data["details"]["rounds"],
                        "spin_remaining" => $data["details"]["rounds"],
                        "denominations" => $data["details"]["denomination"],
                        "date_expire" => $data["details"]["expiration_date"],
                        "provider_trans_id" => $freeround_id,
                    ];
                } else {
                    $insertFreespin = [
                        "player_id" => $player_details->player_id,
                        "game_id" => $game_details->game_id,
                        "denominations" => $data["details"]["denomination"],
                        "date_expire" => $data["details"]["expiration_date"],
                        "provider_trans_id" => $freeround_id,
                    ];
                }
                }catch(\Exception $e){
                    return 400;
                }
                $id = FreeSpinHelper::createFreeRound($insertFreespin);
                $endtime = date("Y-m-d H:i:s", strtotime($data["details"]["expiration_date"]));
                $client_player_details = ProviderHelper::getClientDetails('player_id',  $player_details->player_id);
                $details= ProviderHelper::getPlayerOperatorDetails("player_id", $player_details->player_id);
                $preRequestBody = [
                        "project"=> config("providerlinks.tgg.project_id"),
                        "version"=> 1,
                        "token"=>  $client_player_details->player_token,
                        "game"=> $game_details->game_code,
                        "currency"=> $player_details->default_currency,
                        "extra_bonuses"=> [
                            "bonus_spins"=> [  
                                "spins_count"=> $data["details"]["rounds"],
                                "bet_in_money"=> $data["details"]["denomination"],
                            ],
                        ],
                        "settings"=>[
                            "user_id"=> $player_details->client_player_id,
                            "bypass"=> [
                                "promoCode"=>$freeround_id,
                                ],
                            "expire"=> $endtime
                        ]
                ];
                $signature = TGGHelper::getSignaturess($preRequestBody,config("providerlinks.tgg.api_key"));
                $requestBody = [
                    "project"=> config("providerlinks.tgg.project_id"),
                    "signature"=> $signature,
                    "version"=> 1,
                    "token"=> $client_player_details->player_token,
                    "game"=> $game_details->game_code,
                    "currency"=> $player_details->default_currency,
                    "extra_bonuses"=> [
                        "bonus_spins"=> [  
                            "spins_count"=> $data["details"]["rounds"],
                            "bet_in_money"=> $data["details"]["denomination"],
                        ]
                    ],
                    "settings" => [
                        "user_id"=> $player_details->client_player_id,
                        "bypass"=> [
                            "promoCode"=>$freeround_id,
                            ],
                        "expire"=> $endtime
                    ]
                ];
                

                $client = new Client(['headers' => [ 
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ]
                ]);
                $response = $client->post(config("providerlinks.tgg.api_url")."/game/registerBonus",[
                    'form_params' => $requestBody,
                ]);
                $dataresponse = json_decode($response->getBody(),TRUE);
                if(isset($dataresponse->error)){
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
                else {
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
        }
    }

    public static function createFreeRound5Men($player_details,$data, $sub_provder_id,$freeround_id){
        Helper::saveLog('5Men Freespin', $sub_provder_id,json_encode($freeround_id), 'Freespin HIT');
        $game_details = ProviderHelper::getSubGameDetails($sub_provder_id,$data["game_code"]);// get game details
        if($game_details){
            try{
                //freeSpin type
                if($data["details"]["type"] == "bonus_spins") {
                    $insertFreespin = [
                        "player_id" => $player_details->player_id,
                        "game_id" => $game_details->game_id,
                        "total_spin" => $data["details"]["rounds"],
                        "spin_remaining" => $data["details"]["rounds"],
                        "denominations" => $data["details"]["denomination"],
                        "date_expire" => $data["details"]["expiration_date"],
                        "provider_trans_id" => $freeround_id,
                    ];
                } else {
                    $insertFreespin = [
                        "player_id" => $player_details->player_id,
                        "game_id" => $game_details->game_id,
                        "denominations" => $data["details"]["denomination"],
                        "date_expire" => $data["details"]["expiration_date"],
                        "provider_trans_id" => $freeround_id,
                    ];
                }
                }catch(\Exception $e){
                    return 400;
                }
                $id = FreeSpinHelper::createFreeRound($insertFreespin);
                $endtime = date("Y-m-d H:i:s", strtotime($data["details"]["expiration_date"]));
                $client_player_details = ProviderHelper::getClientDetails('player_id',  $player_details->player_id);
                $details= ProviderHelper::getPlayerOperatorDetails("player_id", $player_details->player_id);
                $preRequestBody = [
                        "project"=> config("providerlinks.5men.project_id"),
                        "version"=> 1,
                        "token"=>  $client_player_details->player_token,
                        "game"=> $game_details->game_code,
                        "currency"=> $player_details->default_currency,
                        "extra_bonuses"=> [
                            "bonus_spins"=> [  
                                "spins_count"=> $data["details"]["rounds"],
                                "bet_in_money"=> $data["details"]["denomination"],
                            ],
                        ],
                        "settings"=>[
                            "user_id"=> $player_details->client_player_id,
                            "bypass"=> [
                                "promoCode"=>$freeround_id,
                                ],
                            "expire"=> $endtime
                        ]
                ];
                $signature = TGGHelper::getSignaturess($preRequestBody,config("providerlinks.5men.api_key"));
                $requestBody = [
                    "project"=> config("providerlinks.5men.project_id"),
                    "signature"=> $signature,
                    "version"=> 1,
                    "token"=> $client_player_details->player_token,
                    "game"=> $game_details->game_code,
                    "currency"=> $player_details->default_currency,
                    "extra_bonuses"=> [
                        "bonus_spins"=> [  
                            "spins_count"=> $data["details"]["rounds"],
                            "bet_in_money"=> $data["details"]["denomination"],
                        ]
                    ],
                    "settings" => [
                        "user_id"=> $player_details->client_player_id,
                        "bypass"=> [
                            "promoCode"=>$freeround_id,
                            ],
                        "expire"=> $endtime
                    ]
                ];
                

                $client = new Client(['headers' => [ 
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ]
                ]);
                $response = $client->post(config("providerlinks.5men.api_url")."/game/registerBonus",[
                    'form_params' => $requestBody,
                ]);
                $dataresponse = json_decode($response->getBody(),TRUE);
                if(isset($dataresponse->error)){
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
                else {
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
        }
    }

    public static function createFreeRoundSpadeGaming($player_details,$data, $sub_provder_id,$freeround_id){
        Helper::saveLog('SpadeGaming Freespin', $sub_provder_id,json_encode($freeround_id), 'Freespin HIT');
        $game_details = ProviderHelper::getSubGameDetails($sub_provder_id,$data["game_code"]);// get game details
        if($game_details){
            try{
                //freeSpin type
                if($data["details"]["type"] == "PROMOTION-02") {
                    $promoCode = "B-FS02";
                    $insertFreespin = [
                        "player_id" => $player_details->player_id,
                        "game_id" => $game_details->game_id,
                        "total_spin" => $data["details"]["rounds"],
                        "spin_remaining" => $data["details"]["rounds"],
                        "denominations" => $data["details"]["denomination"],
                        "date_expire" => $data["details"]["expiration_date"],
                        "provider_trans_id" => $freeround_id,
                    ];
                } else {
                    $promoCode = "B-FS01";
                    $insertFreespin = [
                        "player_id" => $player_details->player_id,
                        "game_id" => $game_details->game_id,
                        "denominations" => $data["details"]["denomination"],
                        "date_expire" => $data["details"]["expiration_date"],
                        "provider_trans_id" => $freeround_id,
                    ];
                }
            }catch(\Exception $e){
                return 400;
            }
                // $explode_freeroundid = explode('_', $freeround_id);
                $id = FreeSpinHelper::createFreeRound($insertFreespin);
                $client_player_details = ProviderHelper::getClientDetails('player_id',  $player_details->player_id);
                $details= ProviderHelper::getPlayerOperatorDetails("player_id", $player_details->player_id);
                $requestBody = [
                        "beginDate"=> $data["details"]["start_time"],
                        "endDate"=> $data["details"]["expiration_date"],
                        "merchantCode"=> config("providerlinks.spade.merchantCode"),
                        "currency"=> $player_details->default_currency,
                        "promotionCode"=> $promoCode,
                        "freeSpinName"=> $data["details"]["type"],
                        "gameList" => array([
                            "gameCode"=> $game_details->game_code,
                            "gameCount"=>  $data["details"]["rounds"],
                            "betCost"=> $data["details"]["denomination"]
                        ]),
                        "acctId" => config("providerlinks.spade.prefix") ."_". $player_details->player_id,
                        "bufferTime" => $data['details']['buffer_time'],
                        "serialNo" => $freeround_id,
                ];
                $client = new Client(['headers' => [ 
                    'API' => 'createLoyalFreeSpin',
                    'DataType' => 'JSON'
                ]
                ]);
                try{
                    $game_link_response = $client->post(config("providerlinks.spade.api_url"),
                    ['body' => json_encode($requestBody)]);
                    $dataresponse = json_decode($game_link_response->getBody()->getContents()); // get response
                }catch(\Exception $e){
                    $createFreeround = [
                        "status" => 3,
                    ];
                    
                    Helper::saveLog('SpadeGamingFS', 59,json_encode($requestBody),  json_encode($dataresponse));
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
                if($dataresponse->code != 0){
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
                else {          
                    $data = [
                        "details" => json_encode($dataresponse->tranId)
                    ];
                    FreeSpinHelper::updateFreeRound($data, $id);
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
        }
    }
    
    public static function cancelFreeRoundSpadeGaming($freeround_id){
        Helper::saveLog('Spade CancelFreeRound',59, json_encode($freeround_id), 'Cancel FreeRound HIT!');
        $originalfreeround_id = explode("_", $freeround_id);
         $getFreespin = FreeSpinHelper::getFreeSpinDetails($freeround_id, "provider_trans_id" );
         if(isset($getFreespin)) {
            $promoCode = "B-FS02";
            $datatosend = [
                "merchantCode"=> config("providerlinks.spade.merchantCode"),
                "tranId"=>  json_decode($getFreespin->details),
                "promotionCode" => $promoCode,
                "serialNo"=>$originalfreeround_id[1]
            ];
            $client = new Client(['headers' => [ 
                'API' => 'cancelFreeSpin',
                'DataType' => 'JSON'
                ]
            ]);
            try{
                $game_link_response = $client->post(config("providerlinks.spade.api_url"),['body' => json_encode($datatosend)]);
                $dataresponse = json_decode($game_link_response->getBody()->getContents());
                $data = [
                    "status" => 4,
                ];
                Helper::saveLog('Spade Cancelfreespin Success', 59, json_encode($data), json_encode($dataresponse));
                FreeSpinHelper::updateFreeRound($data, $getFreespin->freespin_id);
                 return 200;
            }catch (\Exception $e) {
                $dataresponse = [
                    "error" => json_encode($e)
                ];
                $data = [
                    "status" => 0,
                ];

                Helper::saveLog('Spade Cancelfreespin error', 59, json_encode($data), json_encode($dataresponse));

                FreeSpinHelper::updateFreeRound($data, $getFreespin->freespin_id);
            
                return 400;
            }
    
         }
        
    }

    public static function createFreeSpinKA($player_details,$data, $sub_provder_id,$freeround_id){
        // dd($data);
        Helper::saveLog('KAGa Freespin', $sub_provder_id,json_encode($freeround_id), 'Freespin HIT');
        $game_details = ProviderHelper::getSubGameDetails($sub_provder_id,$data["game_code"]);
        if($game_details == false){
            return 400;
        }
        try{
            $insertFreespin = [
                "player_id" => $player_details->player_id,
                "game_id" => $game_details->game_id,
                "total_spin" => $data["details"]["rounds"],
                "spin_remaining" => $data["details"]["rounds"],
                "denominations" => $data["details"]["denomination"],
                "date_expire" => $data["details"]["expiration_date"],
                "provider_trans_id" => $freeround_id,
            ];
        }catch(\Exception $e){
            return 400;
        }

        $id = FreeSpinHelper::createFreeRound($insertFreespin);
        
         $requesttosend = [
            "partnerName" => config("providerlinks.kagaming.partner_name"),
            "currency" => $player_details->default_currency,
            "playerId" => (string) $player_details->player_id,
            "numberSpins" => (int) $data['details']['rounds'],
            "betLevel" => (int) $data['details']['denomination'],
            "endDate" => $data["details"]["expiration_date"],
            "games" =>[$data["game_code"]] 
        ];

        $hash = hash_hmac('sha256', json_encode($requesttosend), config("providerlinks.kagaming.secret_key"));
        $actionUrl = "https://rmpstage.kaga88.com/kaga/promotionspin/create?hash=".$hash;

        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]);

        try{
            $guzzle_response = $client->post($actionUrl,['body' => json_encode($requesttosend)]);
        $dataresponse = json_decode($guzzle_response->getBody()->getContents());

        }catch(\Exception $e) {
            $createFreeround = [
                "status" => 3,
            ];
            FreespinHelper::updateFreeRound($createFreeround, $id);
            $freespinExt = [
                "freespin_id" => $id,
                "mw_request" => json_encode($requesttosend),
                "provider_response" => json_encode($dataresponse),
                "client_request" => json_encode($data),
                "mw_response" => "400"
            ];
            FreespinHelper::createFreeRoundExtenstion($freespinExt);
            return 400;
        }
        if($dataresponse->statusCode != 0){
            Helper::saveLog('KAGaming Freespin Failed',75, json_encode($freeround_id), $dataresponse);
            $createFreeround = [
                "status" => 3,
            ];
            FreeSpinHelper::updateFreeRound($createFreeround, $id);
            $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($requesttosend),
                "provider_response" => json_encode($dataresponse),
                "client_request" => json_encode($data),
                "mw_response" => "400"
            ];
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
            return 400;
        }
        else {      
            $data = [
                "details" => json_encode($dataresponse->promotionSpinId)
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
            $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($requesttosend),
                "provider_response" => json_encode($dataresponse),
                "client_request" => json_encode($data),
                "mw_response" => "200"
            ];  
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
            Helper::saveLog('KAGaming Freespin Success',75, json_encode($freeround_id), $dataresponse);  
            return 200;
        }
    }

    public static function cancelFreeSpinKA($freeround_id){
        Helper::saveLog('KAGaming CancelFreeRound',75, json_encode($freeround_id), 'Cancel FreeRound HIT!');
         $getFreespin = FreeSpinHelper::getFreeSpinDetails($freeround_id, "provider_trans_id" );
         if(isset($getFreespin)) {
            $datatosend = [
                "partnerName"=> config("providerlinks.kagaming.partner_name"),
                "promotionSpinId"=>  json_decode($getFreespin->details)
            ];
            $client = new Client(['headers' => [ 
                'Content-Type' => 'application/json'
                ]
            ]);
            try{
                $hash = hash_hmac('sha256', json_encode($datatosend), config("providerlinks.kagaming.secret_key"));
                $actionUrl = "https://rmpstage.kaga88.com/kaga/promotionspin/cancel?hash=".$hash;

                $game_link_response = $client->post($actionUrl,['body' => json_encode($datatosend)]);
                $dataresponse = json_decode($game_link_response->getBody()->getContents());
                $data = [
                    "status" => 4,
                ];
                Helper::saveLog('KA Cancelfreespin Success', 75, json_encode($data), json_encode($dataresponse));
                FreeSpinHelper::updateFreeRound($data, $getFreespin->freespin_id);
                 return 200;
            }catch (\Exception $e) {
                $dataresponse = [
                    "error" => json_encode($e)
                ];
                $data = [
                    "status" => 0,
                ];

                Helper::saveLog('KA Cancelfreespin error', 75, json_encode($data), json_encode($dataresponse));

                FreeSpinHelper::updateFreeRound($data, $getFreespin->freespin_id);
            
                return 400;
            }
    
         }
        
    }
    public static function createFreeSpinMancala($player_details,$data, $sub_provder_id,$freeround_id){
        // dd($data);
        Helper::saveLog('Mancala Freespin', $sub_provder_id,json_encode($freeround_id), 'Freespin HIT');
        $game_details = ProviderHelper::getSubGameDetails($sub_provder_id,$data["game_code"]);
        // dd($game_details);
        if($game_details == false){
            return 400;
        }
        try{
            $insertFreespin = [
                "player_id" => $player_details->player_id,
                "game_id" => $game_details->game_id,
                "total_spin" => $data["details"]["rounds"],
                "spin_remaining" => $data["details"]["rounds"],
                "denominations" => $data["details"]["denomination"],
                "date_expire" => $data["details"]["expiration_date"],
                "provider_trans_id" => $freeround_id,
            ];
        }catch(\Exception $e){
            return 400;
        }
        $id = FreeSpinHelper::createFreeRound($insertFreespin);
        $hash = md5("AddFreespinRules/".config("providerlinks.mancala.PARTNER_ID").$data["game_code"].$player_details->player_id.$player_details->default_currency.config("providerlinks.mancala.API_KEY"));
        // AppFreespinRules/2641900152036USDQNGIXXAI
        $requesttosend = [
            "Settings" => [
                "BetPerLine" => (int) $data["details"]["denomination"],
                "FreeSpinAmount" => (int) $data["details"]["rounds"]
            ],
            "PartnerId" => config("providerlinks.mancala.PARTNER_ID"),
            "Hash" => $hash,
            "Name" => "freesping grant",
            "GameId" => (int) $data["game_code"],
            "UserId" => (string) $player_details->player_id,
            "StartDate" => $data["details"]["start_time"],
            "EndDate" => $data["details"]["expiration_date"],
            "Currency" => $player_details->default_currency,
            "ExternalId" => $freeround_id,
            "BonusType" => 1
        ];
        // dd($requesttosend);
        $client = new Client(['headers' => [ 
            'Content-Type' => 'application/json'
            ]
        ]);
        Helper::saveLog('Mancala Freespin datas', $sub_provder_id,json_encode($freeround_id), json_encode($requesttosend));
        $sendtoUrl = "https://api.mancalagaming.net/partners/free-spins/v2/AddFreeSpinRules";
        try {
            $guzzle_response = $client->post($sendtoUrl,['body' => json_encode($requesttosend)]);
            $dataresponse = json_decode($guzzle_response->getBody()->getContents());
            Helper::saveLog('Mancala Freespin datas2', $sub_provder_id,json_encode($requesttosend), json_encode($dataresponse));
        } catch (\Exception $e) {
            $msg = array(
                'err_message' => $e->getMessage(),
                'err_line' => $e->getLine(),
                'err_file' => $e->getFile()
            );
            $data = [
                "status" => 3,
                "details" => json_encode($msg)
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
            return 400;
        }
        if(isset($dataresponse->StatusCode) && $dataresponse->StatusCode != 0){
            $createFreeround = [
                "status" => 3,
            ];
            FreeSpinHelper::updateFreeRound($createFreeround, $id);
            $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($requesttosend),
                "provider_response" => json_encode($dataresponse),
                "client_request" => json_encode($data),
                "mw_response" => "400"
            ];
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
            return 400;
        }else{
            $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($requesttosend),
                "provider_response" => json_encode($dataresponse),
                "client_request" => json_encode($data),
                "mw_response" => "200"
            ];
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
            return 200;
        }

    }
    public static function createFreeSpinPragmaticPlay($player_details,$data, $sub_provder_id,$freeround_id){
        // dd($data);
        Helper::saveLog('PragmaticPlay Freespin', $sub_provder_id,json_encode($freeround_id), 'Freespin HIT');
        $game_details = ProviderHelper::getSubGameDetails($sub_provder_id,$data["game_code"]);
        // dd($game_details);
        if($game_details == false){
            return 400;
        }
        try{
            $insertFreespin = [
                "player_id" => $player_details->player_id,
                "game_id" => $game_details->game_id,
                "total_spin" => $data["details"]["rounds"],
                "spin_remaining" => $data["details"]["rounds"],
                "denominations" => $data["details"]["denomination"],
                "date_expire" => $data["details"]["expiration_date"],
                "provider_trans_id" => $freeround_id,
            ];
        }catch(\Exception $e){
            return 400;
        }
        $id = FreeSpinHelper::createFreeRound($insertFreespin);
        $date1 = $data["details"]["expiration_date"];
        $timestamp1 = strtotime($date1);
        $user_id = "TGaming_".$player_details->player_id;
        $hash = md5("bonusCode=".$freeround_id."&currency=".$player_details->default_currency."&expirationDate=".$timestamp1."&gameIDList=".$data["game_code"]."&playerId=".$user_id."&rounds=".$data["details"]["rounds"]."&secureLogin=".config("providerlinks.tpp.secureLogin")."".config("providerlinks.tpp.secret_key"));
        // dd($hash);
        $requesttosend = [
            "secureLogin" => config("providerlinks.tpp.secureLogin"),
            "playerId" => $user_id,
            "currency" => $player_details->default_currency,
            "gameIDList" => $data['game_code'],
            "rounds" => $data['details']['rounds'],
            "bonusCode" => $freeround_id,
            "expirationDate" => $timestamp1,
            "hash" => $hash
        ];
        $client = new Client();
        try {
            $response = $client->post("https://api.prerelease-env.biz/IntegrationService/v3/http/FreeRoundsBonusAPI/createFRB/",[
            'form_params' => $requesttosend,
            ]);
            $dataresponse = json_decode($response->getBody()->getContents());
        } catch (\Exception $e) {
            $msg = array(
                'err_message' => $e->getMessage(),
                'err_line' => $e->getLine(),
                'err_file' => $e->getFile()
            );
            $data = [
                "status" => 3,
                "details" => json_encode($msg)
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
            return 400;
        }
        if(isset($dataresponse->error) && $dataresponse->error == "0" && $dataresponse->description == "OK"){
            $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($requesttosend),
                "provider_response" => json_encode($dataresponse),
                "client_request" => json_encode($data),
                "mw_response" => "200"
            ];
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
            return 200;
        }else{
            $createFreeround = [
                "status" => 3,
            ];
            FreeSpinHelper::updateFreeRound($createFreeround, $id);
            $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($requesttosend),
                "provider_response" => json_encode($dataresponse),
                "client_request" => json_encode($data),
                "mw_response" => "400"
            ];
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
            return 400;
        }
    }

   
    public static function createFreeRoundHabanero($player_details,$data, $sub_provder_id,$freeround_id){
        // dd($data);
        Helper::saveLog('HabaneroGaming Freespin', $sub_provder_id,json_encode($freeround_id), 'Freespin HIT');
        $game_details = ProviderHelper::getSubGameDetails($sub_provder_id,$data["game_code"]);
        if($game_details == false){
            return 400;
        }
        try{
            $insertFreespin = [
                "player_id" => $player_details->player_id,
                "game_id" => $game_details->game_id,
                "total_spin" => $data["details"]["rounds"],
                "spin_remaining" => $data["details"]["rounds"],
                "denominations" => $data["details"]["denomination"],
                "date_expire" => $data["details"]["expiration_date"],
                "date_start" => $data["details"]["start_date"],
                "provider_trans_id" => $freeround_id
            ];
        }catch(\Exception $e){
            return 400;
        }

        $id = FreeSpinHelper::createFreeRound($insertFreespin);
        
         $requesttosend = [
            "BrandId" => config("providerlinks.habanero.brandID"),
            "APIKey" => config("providerlinks.habanero.apiKey"),
            "ReplaceActiveCoupon" => true,
            "CouponTypeId" => 5,
            "DtStartUTC" => $data["details"]["start_date"],
            "DtEndUTC" => $data["details"]["expiration_date"],
            "ExpireAfterDays" => 3,
            "MaxRedemptionsPerPlayer" => 10,
            "MaxRedemptionsForBrand" => 1000,
            "MaxRedemptionIntervalId" => 0,
            "WagerMultiplierRequirement" => 0,
            "MaxConversionToRealMultiplier" => 0,
            "NumberOfFreeSpins" => $data['details']['rounds'],
            "GameKeyNames" => [
                $data["game_code"]
            ],
            "couponCurrencyData" =>[
                    [
                        "CurrencyCode" => "USD",
                        "CoinPosition" => 0
                    ],
                    [
                        "CurrencyCode" => "CNY",
                        "CoinPosition" => 2 
                    ]
                ],

            "QueueUnregisteredPlayers" => true,
            "CreatePlayerIfNotExist" => true,
            "Players" => array([
                "Username" => $player_details->player_id,
                "CurrencyCode" => $player_details->default_currency
            ])
        ];
        $actionUrl = "https://ws-test.insvr.com/jsonapi/createandapplybonusmulti";

        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]);

        try{
            $guzzle_response = $client->post($actionUrl,['body' => json_encode($requesttosend)]);
        $dataresponse = json_decode($guzzle_response->getBody()->getContents());
        }catch(\Exception $e) {
            $createFreeround = [
                "status" => 3,
            ];
            FreespinHelper::updateFreeRound($createFreeround, $id);
            $freespinExt = [
                "freespin_id" => $id,
                "mw_request" => json_encode($requesttosend),
                "provider_response" => json_encode($dataresponse),
                "client_request" => json_encode($data),
                "mw_response" => "400"
            ];
            FreespinHelper::createFreeRoundExtenstion($freespinExt);
            return 400;
        }
        if($dataresponse->Created == false){
            Helper::saveLog('HabaneroGaming Freespin Failed',47, json_encode($freeround_id), $dataresponse);
            $createFreeround = [
                "status" => 3,
            ];
            FreeSpinHelper::updateFreeRound($createFreeround, $id);
            $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($requesttosend),
                "provider_response" => json_encode($dataresponse),
                "client_request" => json_encode($data),
                "mw_response" => "400"
            ];
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
            return 400;
        }
        else {      
            $data = [
                "provider_trans_id" => $dataresponse->Players[0]->BonusBalanceId,
                "denominations" => $dataresponse->Players[0]->BonusValue,
                "details" => json_encode($dataresponse->CouponCodeCreated)
            ];
            FreeSpinHelper::updateFreeRound($data, $id);
            $freespinExtenstion = [
                "freespin_id" => $id,
                "mw_request" => json_encode($requesttosend),
                "provider_response" => json_encode($dataresponse),
                "client_request" => json_encode($data),
                "mw_response" => "200"
            ];  
            FreeSpinHelper::createFreeRoundExtenstion($freespinExtenstion);
            Helper::saveLog('HabaneroGaming Freespin Success',47, json_encode($freeround_id), $dataresponse);  
            return 200;
        }
    }

    
}
?>
 