<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Helpers\ClientRequestHelper;
use App\Helpers\TransactionHelper;
use App\Helpers\DOWINNHelper;
use App\Models\GameTransactionMDB;
use App\Models\GameTransaction;
use DB;
use Exception;

class DOWINNController extends Controller{

    protected $startTime;
    public function __construct() {
        $this->startTime = microtime(true);
        $this->provider_db_id = config('providerlinks.DOWINN.provider_db_id'); //sub provider ID
        $this->api_key = config('providerlinks.DOWINN.api_key');
        $this->api_url = config('providerlinks.DOWINN.api_url');
        $this->prefix = config('providerlinks.DOWINN.prefix');
        $this->providerID = 72; //Real provider ID
        $this->dateToday = date("Y/m/d");
    }

    public function index(Request $request){
        $data = json_decode($request->getContent(),TRUE);
        Helper::saveLog('DOWINN Auth INDEX', 139, json_encode($data), 'INDEX HIT!');
        $client_details = ProviderHelper::getClientDetails('token', $data['token']);
        if($client_details){
            $msg = array(
                "status" =>'OK',
                "balance"=> number_format($client_details->balance,2,'.', ''),
                "uuid" => $data['uuid'],
            );
            return response($msg,200)->header('Content-Type', 'application/json');
        }
    }

    public function _getBalance($data,$client_details){
        Helper::saveLog('DOWINN GetBALANCE', $this->provider_db_id, json_encode($data), 'Balance HIT!');
        if($client_details){
            $msg = array(
                "status" =>'OK',
                "balance"=>(int) number_format($client_details->balance,2,'.', ''),
                "uuid" => $data['uuid'],
            );
            return response($msg,200)->header('Content-Type', 'application/json');
        }
    }
}
?>