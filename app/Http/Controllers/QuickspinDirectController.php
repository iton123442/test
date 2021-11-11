<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransaction;
use App\Models\GameTransactionMDB;
use App\Helpers\Game;
use Carbon\Carbon;
use DB;

class QuickspinDirectController extends Controller
{
    public function Authenticate(Request $req){
        Helper::saveLog('QuickSpinDirect verifyToken', 65, json_encode($req),  "HIT" );
        $client_details = ProviderHelper::getClientDetails('token',$req['token']);
        if($client_details != null){
            $response = [
                "customerid" => $client_details->player_id,
                "countrycode" => "PH",
                "cashiertoken" => $client_details->player_token,
                "customercurrency" => $client_details->default_currency,
                "balance" => (int)$client_details->balance/100,
                "jurisdiction" => "PH",
                "classification" => "vip",
                "playermessage" => [
                    "title" => "",
                    "message" => "",
                    "nonintrusive" => false
                ],
            ];
            return $response;
        }else{
            $response = [
                "errorcode" => "INVALID_TOKEN",
                "errormessage" => "authentication failed"
            ];
            return $response;
        }
    }
}
