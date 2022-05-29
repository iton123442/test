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
        $originalPlayerID = explode('_', $data["user"]);
        $client_details = ProviderHelper::getClientDetails('player_id', $originalPlayerID[1]);
        if($client_details){
            $msg = array(
                "status" =>'OK',
                "balance"=>(int) number_format($client_details->balance,2,'.', ''),
                "uuid" => $data['uuid'],
            );
            return response($msg,200)->header('Content-Type', 'application/json');
        //     $playerChecker = DOWINNHelper::DOWINNPlayerChecker($client_details,'Verify');//Auth
        //     if(isset($playerChecker->result_code) && $playerChecker->result_code == 1){
        //         $msg = [
        //             "result_code" => "1",
        //             "result_msg" =>(string) "(no account)"
        //         ];
        //         return response($msg,200)->header('Content-Type', 'application/json');
        //         Helper::saveLog('DOWINN NOT FOUND PLAYER',$this->provider_db_id, json_encode($msg), $data);
                    
        //     }else{
        //         //flow structure
        //         if($data['types'] == "balance") {
        //             $result = $this->_getBalance($data,$client_details);
        //             Helper::saveLog('DOWINN Balance', $this->provider_db_id, json_encode($result), 'BALANCE HIT!');
        //             return $result;
        //         }
        //         elseif($data['types'] == "bet") {
        //                 $result = $this->_betProcess($data,$client_details);
        //                 Helper::saveLog('DOWINN BET',$this->provider_db_id, json_encode($result), 'BET HIT');
        //                 return $result;
        //         }
        //         elseif($data['types'] == "win") {
        //             $result = $this->_winProcess($data,$client_details);
        //             Helper::saveLog('DOWINN WIN', $this->provider_db_id, json_encode($result), 'WIN HIT');
        //             return $result;
        //         }
        //         elseif($data['types'] == "cancel") {
        //             $result = $this->_cancelProcess($data,$client_details);
        //             Helper::saveLog('DOWINN CANCEL', $this->provider_db_id, json_encode($result), 'CANCEL HIT');
        //             return $result;
        //         }
        //         else {
        //             exit;
        //         }
        //         return response($data,200)->header('Content-Type', 'application/json');
        //     }
        // }
        // else{
        //     $msg = [
        //         "result_code" => "1",
        //         "result_msg" =>(string) "(no account)"
        //     ];
        //     return response($msg,200)->header('Content-Type', 'application/json');
        //     Helper::saveLog('DOWINN TG NOTFOUND CLIENT DETAILS',$this->provider_db_id, json_encode($msg), $data);
        // }
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