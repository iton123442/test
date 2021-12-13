<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;

use GuzzleHttp\Client;
use Carbon\Carbon;
use App\Helpers\ClientRequestHelper;
use App\Helpers\TransactionInfo;
use App\Helpers\GameLobby;
use App\Models\GameTransactionMDB;
use DB;


class TransactionInfoController extends Controller
{

	public function __construct(){
    	
    }


    public function getTransaction(Request $request){ 

    	# ALL PARAMETER ARE MANDATORY!
    	if(!$request->has('access_token') || !$request->has('hashkey') || !$request->has('client_id') || !$request->has('client_player_id') || !$request->has('username') || !$request->has('game_provider') || !$request->has('transactionId') || !$request->has('roundId')){
    		$mw_response = ["data" => null,"status" => ["code" => 404,"message" => 'Missing Parameter']];
	    	ProviderHelper::saveLogWithExeption('getTransaction missing parameter', 1223, json_encode($request->all()), $mw_response);
			return $mw_response;
    	}

    	# Player Info
		$player = DB::select('select * from `players` where client_id = "'.$request->client_id.'" and client_player_id = "'.$request->client_player_id.'"');
		$player_data = count($player);

    	if ($player_data < 0 ) {
    		$mw_response = ["data" => null,"status" => ["code" => 404,"message" => 'Player Not Found']];
	    	ProviderHelper::saveLogWithExeption('getTransaction player not found 1', 1223, json_encode($request->all()), $mw_response);
			return $mw_response;
    	}

    	# Client Details
    	$client_details = ProviderHelper::getClientDetails('player_id', $player[0]->player_id); 
    	if ($client_details == "false") {
    		$mw_response = ["data" => null,"status" => ["code" => 404,"message" => 'Player Not Found']];
	    	ProviderHelper::saveLogWithExeption('getTransaction player not found 2', 1223, json_encode($request->all()), $mw_response);
			return $mw_response;
    	}

    	// $hashkey = md5($client_details->client_api_key.$client_details->client_access_token);
    	// if($hashkey != $request->hashkey){
	    //     $mw_response = ["data" => null,"status" => ["code" => 404,"message" => TransactionInfo::TransactionErrorCode(404)]];
	    //     return $mw_response;
    	// }

        $provider_details = GameLobby::checkAndGetProviderId($request->game_provider);
        if($provider_details){
            $sub_provider_id = $provider_details->sub_provider_id;
        }else{
            $mw_response = ["data" => $url,"status" => ["code" => 405,"message" => TransactionInfo::TransactionErrorCode(405)]];
            return $mw_response;
        }

	  	$serverName = ['server1','server2','server3']; // Server Count

    	$serverCount = 1;
    	$isExist = false;
    	$stop = false;
    	# First Check Current Connection
        // $checkTransaction = GameTransactionMDB::findGameExt($request->transactionId, 1,'game_transaction_ext_id', $client_details);
		$checkTransaction = GameTransactionMDB::findGameTransactionDetails($request->roundId,'game_transaction', 1, $client_details);
    	if($checkTransaction == "false"){

    		# Unset the server first used
   			$clientConnection = explode("-", $client_details->connection_name);
   			$connectionNameOffset = count($clientConnection) > 1 ? 1 : 0 ;
   			// if (($key = array_search($clientConnection[$connectionNameOffset], $serverName)) !== false) {
			//     unset($serverName[$key]);
			// }

    		# Loop server
	    	foreach ($serverName as $key) {
				$client_details->connection_name = $key.'-'.$clientConnection[$connectionNameOffset];
                // $checkTransaction = GameTransactionMDB::findGameExt($request->transactionId, 1,'game_transaction_ext_id', $client_details);
				$checkTransaction = GameTransactionMDB::findGameTransactionDetails($request->roundId, 'game_transaction',1, $client_details);
				if ($checkTransaction == 'false'){
					continue;
				}else{
                    $canRequest = $this->winFilter($checkTransaction->win);
                    if($canRequest != 200){
                        $mw_response = ["data" => null,"status" => ["code" => $canRequest,"message" => TransactionInfo::TransactionErrorCode($canRequest)]];
                        return $mw_response;
                    }
					$process = TransactionInfo::TransactionGet($client_details, $request->all(), $checkTransaction,$sub_provider_id);
    				return $process;
				}
			}
    	}else{
            $canRequest = $this->winFilter($checkTransaction->win);
            if($canRequest != 200){
                $mw_response = ["data" => null,"status" => ["code" => $canRequest,"message" => TransactionInfo::TransactionErrorCode($canRequest)]];
                return $mw_response;
            }
    		$process = TransactionInfo::TransactionGet($client_details, $request->all(), $checkTransaction,$sub_provider_id);
    		return $process;
    	}

    }


    public function winFilter($winType){
        switch ($winType) {
            case 2:
                return 401; // Failed
                break;
            case 4:
                return 400; // Rollbacked
                break;
            case 5:
                return 201; // Progressing
                break;
            default:
                return 200; // Success
                break;
        }
    }


}

