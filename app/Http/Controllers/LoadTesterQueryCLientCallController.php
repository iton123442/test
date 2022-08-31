<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransactionMDB;

class LoadTesterQueryCLientCallController extends Controller
{
    private $prefix = 22;
    public function __construct() {
        // $this->startTime = microtime(true);
    }
    public function ProcessTransaction(Request $request){
        $data = json_decode($request->getContent(),TRUE);
        // Helper::saveLog('BNGMETHOD(BNG)', 12, json_encode(["request_data" => $data]), "");
        $client_details = ProviderHelper::getClientDetails('token', $data["token"]);
        $game_details = ProviderHelper::findGameDetails('game_code', $this->prefix, $data["game_id"]);
        $response =array(
            "uid"=>$data["uid"],
            "balance" => array(
                "value" => (string)$client_details->balance,
                "version" => round(microtime(true) * 1000)//$this->_getExtParameter()
            ),
        );
        return response($response,200)
            ->header('Content-Type', 'application/json');
    }
}
