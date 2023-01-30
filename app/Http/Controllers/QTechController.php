<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransaction;
use App\Models\GameTransactionMDB;
use App\Helpers\FreeSpinHelper;
use App\Helpers\Game;
use Carbon\Carbon;
use DB;

class QTechController extends Controller
{
   public function verifySession(Request $request, $id){
        Helper::saveLog('QtechSession', 144, json_encode($request->all()),  "HIT_id:". $id );
        $walletSessionId = $request->header('Wallet-Session');
        $passKey = $request->header('Pass-Key');
        $client_details = ProviderHelper::getClientDetails('token',$walletSessionId);
        if(!$client_details){
            $response = [
                "code" => "INVALID_TOKEN",
                "message" => "The given wallet session token has expired"
            ];
            return $response;
        }
        $response = [
            "balance" => $client_details->balance,
            "currency" => $client_details->default_currency
        ];
        return $response;
    }
    
    public function getBalance(Request $request, $id){
        Helper::saveLog('QtechSession', 144, json_encode($request->all()),  "HIT_id:". $id );
        $walletSessionId = $request->header('Wallet-Session');
        $passKey = $request->header('Pass-Key');
        $client_details = ProviderHelper::getClientDetails('token',$walletSessionId);
        if(!$client_details){
            $response = [
                "code" => "LOGIN_FAILED",
                "message" => "The given pass-key is incorrect."
            ];
            return $response;
        }
        $response = [
            "balance" => $client_details->balance,
            "currency" => $client_details->default_currency
        ];
        return $response;
    }
}
