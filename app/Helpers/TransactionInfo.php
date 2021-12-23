<?php
namespace App\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Helpers\GameLobby;
use App\Services\AES;
use Webpatser\Uuid\Uuid;

use DB;             
use Carbon\Carbon;
class TransactionInfo{
  
     /**
      * Error Codes
      * 
      */
    public static function TransactionErrorCode($code) {
        $message = [
         200 => 'Success',
         201 => 'Progressing',
         400 => 'Transaction Rollbacked',
         401 => 'Transaction Failed',
         402 => 'Something Went Wrong',
         403 => 'Access Denied',
         404 => 'Transaction No Longer Exist',
         405 => 'Provider Not Found',
        ];
        if(array_key_exists($code, $message)){
            return $message[$code];
        }else{
            return 'Something went Wrong';
        }
    }

    public static function TransactionGet($clientDetails, $requestData, $transactionData,$subProviderId){
        switch ($subProviderId) { // temporary game provider name to be converted to sub provider id
            case 35: //'iconic gaming':
                return TransactionInfo::IconicGaming($clientDetails, $requestData, $transactionData);
                break;
            case 75: //'kagaming':
                return TransactionInfo::KAGaming($clientDetails, $requestData, $transactionData);
                break;
            case 44: //'booongo':
                return TransactionInfo::Booongo($clientDetails, $requestData, $transactionData);
                break;
            default:
                # code...
                break;
        }
    }


    public static function Booongo($clientDetails, $requestData, $transactionData){
        $url = ''.config('providerlinks.boongo.PLATFORM_SERVER_URL').config('providerlinks.boongo.PROJECT_NAME').'/history/draw/'.$transactionData->provider_trans_id;
        $mw_response = ["data" => $url,"status" => ["code" => 200,"message" => TransactionInfo::TransactionErrorCode(200)]];
        return $mw_response;
    }


    public static function KAGaming($clientDetails, $requestData, $transactionData){
        $time = time();

        if (isset($transactionData->mw_request) && $transactionData->mw_request == null){
            $mw_response = ["data" => null,"status" => ["code" => 404,"message" => TransactionInfo::TransactionErrorCode(404)]];
            return $mw_response;
        }
            
        $game_code = ProviderHelper::findGameID($transactionData->game_id)->game_code;

        $lang = isset($clientDetails->default_language) ? $clientDetails->default_language : 'en';
        $hash =  hash_hmac('SHA256', $transactionData->provider_trans_id.$time, config('providerlinks.kagaming.secret_key'));
        $url = ''.config('providerlinks.kagaming.gamelaunch').'/?g='.$game_code.'&p='.config('providerlinks.kagaming.partner_name').'&loc='.$lang.'&ak='.config('providerlinks.kagaming.access_key').'&grid='.$transactionData->provider_trans_id.'&grts='.$time.'&grha='.$hash;
      
        $mw_response = ["data" => $url,"status" => ["code" => 200,"message" => TransactionInfo::TransactionErrorCode(200)]];
        return $mw_response;
    }


    public static function IconicGaming($clientDetails, $requestData, $transactionData){
        $http = new Client();
        try {
            $response = $http->get(config("providerlinks.icgamingapi").'/api/v1/profile/info/link?id='.$transactionData->round_id.'&lang=en', [
                'headers' =>[
                    'Authorization' => 'Bearer '.GameLobby::icgConnect($clientDetails->default_currency),
                    'Accept'     => 'application/json'
                ],
            ]);
            $iconicResponse = json_decode((string) $response->getBody(), true);
            ProviderHelper::saveLogWithExeption('IconicGaming', 1223, json_encode($clientDetails), $iconicResponse);
            if(isset($iconicResponse['data']) && $iconicResponse['data'] != null){
                $mw_response = ["data" => $iconicResponse['data'],"status" => ["code" => 200, "message" => TransactionInfo::TransactionErrorCode(200)]];
            }else{
                $mw_response = ["data" => null,"status" => ["code" => 404,"message" => TransactionInfo::TransactionErrorCode(404)]];
            }
        } catch (\Exception $e) {
            ProviderHelper::saveLogWithExeption('IconicGaming', 1223, json_encode($request->all()), $e->getMessage());
            $mw_response = ["data" => null,"status" => ["code" => 402,"message" => TransactionInfo::TransactionErrorCode(402)]];
        }
        return $mw_response;
    }
}

?>
