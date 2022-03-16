<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ProviderHelper;
use DB;
use App\Helpers\Helper;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransactionMDB;

class YGG002Controller extends Controller
{
    public $provider_id;
    public $org;

    public function __construct(){
        $this->provider_id = config("providerlinks.ygg002.provider_db_id");
        $this->org = config("providerlinks.ygg002.Org");
        $this->topOrg = config("providerlinks.ygg002.topOrg");
    }

    public function playerinfo(Request $request){
        Helper::saveLog("YGG 002 playerinfo req", $this->provider_id, json_encode($request->all()), "");
    }

    public function wager(Request $request){
        Helper::saveLog("YGG 002 wager req", $this->provider_id, json_encode($request->all()), "");
    }

    public function cancelwager(Request $request){
        Helper::saveLog("YGG 002 cancelwager req", $this->provider_id, json_encode($request->all()), "");
    }

    public function appendwagerresult(Request $request){
        Helper::saveLog('YGG 002 appendwagerresult request', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), "" );
    }

    public function endwager(Request $request){
        Helper::saveLog("YGG 002 endwager", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), "recieved");
        
    }

    public function campaignpayout(Request $request){
        Helper::saveLog('YGG 002 campaignpayout request', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), "");
    }

    public function getbalance(Request $request){
        Helper::saveLog('YGG 002 getbalance request', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), "");
    }
    
   

}
