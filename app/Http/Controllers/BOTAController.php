<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Helpers\ClientRequestHelper;
use App\Helpers\TransactionHelper;
use App\Models\GameTransactionMDB;
use App\Models\GameTransaction;
use DB;
class BOTAController extends Controller{

    protected $startTime;
    public function __construct() {
        $this->startTime = microtime(true);
        $this->provider_db_id = config('providerlinks.bota.provider_db_id');
        $this->api_key = config('providerlinks.bota.provider_db_id');
        $this->api_url = config('providerlinks.bota.provider_db_id');
        $this->prefix = config('providerlinks.bota.provider_db_id');
    }

    public function index(Request $request){
        $data = json_decode($request->getContent(),TRUE);
        Helper::saveLog('BOTA Auth INDEX', $this->provider_db_id, $data, 'INDEX HIT!');
        $originalPlayerID = explode('_', $data["user"]);
        $client_details = ProviderHelper::getClientDetails('player_id', $originalPlayerID[1]);
        //auth
        if($client_details != null){
            return $this->_authenticate($data,$client_details);
            Helper::saveLog('BOTA Auth Success',$this->provider_db_id,$client_details,$data);
        }
        else{
            $msg = array(
                "result_code" => "1.Invalid Reqeust"
            );
            Helper::saveLog('BOTA Auth FAILED',$this->provider_db_id,$msg,$data);
            return response($msg,200)->header('Content-Type', 'application/json');
        }
        //flow structure
        foreach($_POST as $key=>$request) {
            $json = json_decode($key);
            if($json->types == "balance") {
                $data = $this->_getBalance($data,$client_details);
                Helper::saveLog('BOTA Balance', $this->provider_db_id, json_encode($data), $data);
                return $data;
                // $data["user"] = $json->user;
                // $data["balance"] = "100000"; 
            }
            elseif($json->types == "bet") {
                $data = $this->_betProcess($data,$client_details);
                Helper::saveLog('BOTA BET',$this->provider_db_id, json_encode($data), $data);
                return $data;
                // $data["user"] = $json->user;
                // $data["balance"] = "200000"; // 
                // $data["confirm"] = "ok";
            }
            elseif($json->types == "win") {
                $data = $this->_winProcess($data,$client_details);
                Helper::saveLog('BOTA WIN', $this->provider_db_id, json_encode($data), $data);
                return $data;
                // $data["user"] = $json->user;
                // $data["balance"] = "200000"; // 
                // $data["confirm"] = "ok";
            }
            elseif($json->types == "cancel") {
                $data = $this->_cancelProcess($data,$client_details);
                Helper::saveLog('BOTA CANCEL', $this->provider_db_id, json_encode($data), $data);
                return $data;
                // $data["user"] = $json->user;
                // $data["balance"] = "200000"; // 베
                // $data["confirm"] = "ok";
            }
            else {
                exit;
            }
        }
    return response($data,200)->header('Content-Type', 'application/json');
    }
    private function _authenticate($data,$client_details){
        Helper::saveLog('BOTA Auth HIT', $this->provider_db_id, $data, $client_details);
        // $getgame_token = ProviderHelper::botaGenerateGametoken($client_details);
        if($data["token "]){
            // $client_details = ProviderHelper::getClientDetails('token', $data["token"]);
            if($client_details){
                $msg = array(
                    "user_id" => $data["user"],
                    "game_token"=>$data["token"]
                );
                Helper::saveLog('BOTA Auth Successful', $this->provider_db_id, $msg, $client_details);
                return response($msg,200)->header('Content-Type', 'application/json');
            }
            else{
                $msg = array(
                    "result_code" => "1.Invalid Reqeust"
                );
                Helper::saveLog('BOTA Auth FAILED', $this->provider_db_id, $msg, $client_details);
                return response($msg,200)->header('Content-Type', 'application/json');
            }
        }    
    }
    private function _getBalance($data,$client_details){
        Helper::saveLog('BOTA GetBALANCE', $this->provider_db_id, json_encode($data), 'Balance HIT!');
        if($data["token"]){
            if($client_details){
                $msg = array(
                    "user" => $data["user"],
                    "balance"=>number_format($client_details->balance,2,'.', ''),
                );
                return response($msg,200)->header('Content-Type', 'application/json');
            }
        }
    }
}
?>