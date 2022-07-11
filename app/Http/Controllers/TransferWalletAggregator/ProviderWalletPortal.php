<?php

namespace App\Http\Controllers\TransferWalletAggregator;

use App\Helpers\Helper;
use Carbon\CarbonPeriod;
use App\Models\GameTransactionMDB;
use App\Helpers\IDNPokerHelper;
use App\Http\Controllers\IDNPokerController;
use DB;



/**
 * Transfer Wallet Helper
 * @author's note please add comment if you change something
 * 
 * 
 */
class ProviderWalletPortal {

    public static function providerCreatePlayer($playerDetails, $sub_provider_details, $type){
        if($sub_provider_details->sub_provider_id ==  config('providerlinks.idnpoker.sub_provider_id') ){
            $player_id = $playerDetails->username;
            $auth_token = IDNPokerHelper::getAuthPerOperator($playerDetails, config('providerlinks.idnpoker.type')); 
            $register = IDNPokerHelper::registerPlayer($player_id,$auth_token);
            if($register != 'false'){
                if(isset($register["status"]) && $register["status"] == 1 ){
                    $data = ["code" => 200, "balance" => 0];
                    return $data;
                } elseif (isset($register["status"]) && $register["status"] == 0 && isset($register["error"]) && $register["error"] == 12 ) {
                    $data = ["code" => 308];// user availaable
                    return $data;
                }
            }
        }
        $data = ["code" => 400];
        return $data;
    }

    public static function providerDepositPlayer($playerDetails, $sub_provider_details, $details){
        if($sub_provider_details->sub_provider_id ==  config('providerlinks.idnpoker.sub_provider_id') ){
            $deposit_response = IDNPokerController::CreateDepositWallet($playerDetails, $sub_provider_details, $details);
            return $deposit_response;
        }
        $response = ["code" => 400];
        return $response;
    }

    public static function providerWithdrawPlayer($playerDetails, $sub_provider_details, $details){
        if($sub_provider_details->sub_provider_id ==  config('providerlinks.idnpoker.sub_provider_id') ){
            $deposit_response = IDNPokerController::CreateWithdrawWallet($playerDetails, $sub_provider_details, $details);
            return $deposit_response;
        }
        $response = ["code" => 400];
        return $response;
    }

    



}

?>