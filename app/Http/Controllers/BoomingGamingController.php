<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use Carbon\Carbon;
use DB;
use App\Models\GameTransactionMDB;


class BoomingGamingController extends Controller
{

    public function __construct(){
        $this->api_key = config('providerlinks.booming.api_key');
        $this->api_secret = config('providerlinks.booming.api_secret');
        $this->api_url = config('providerlinks.booming.api_url');
        $this->provider_db_id = config('providerlinks.booming.provider_db_id');
    }
    
    public function gameList(){
        $nonce = date('mdYhisu');
        $url =  $this->api_url.'/v2/games';
        $requesttosend = "";
        $sha256 =  hash('sha256', $requesttosend);
        $concat = '/v2/games'.$nonce.$sha256;
        $secrete = hash_hmac('sha512', $concat, $this->api_secret);

        
        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/vnd.api+json',
                'X-Bg-Api-Key' => $this->api_key,
                'X-Bg-Nonce'=> $nonce,
                'X-Bg-Signature' => $secrete
            ]
        ]);
       $guzzle_response = $client->get($url);
       $client_response = json_decode($guzzle_response->getBody()->getContents());
       return json_encode($client_response);
    }

    //THIS IS PART OF GAMELAUNCH GET SESSION AND URL
    public function callBack(Request $request){
        $data = $request->all();
        $playersid = explode('_', $data["player_id"]);
        $client_details = ProviderHelper::getClientDetails('player_id',$playersid[1]);
        Helper::saveLog('Booming', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT),"Callback");
        if($client_details == null){
            $errormessage = array(
                'error' => '2012',
                'message' => 'Invalid Player ID'
            );
            Helper::saveLog('Booming Callback error', $this->provider_db_id, json_encode($request->all(), JSON_FORCE_OBJECT),  $errormessage);
            return json_encode($errormessage, JSON_FORCE_OBJECT); 
        }
        $round_id = $data["session_id"]."_".$data["round"];
        $provider_trans_id = $data["session_id"]."_".$data["round"];
        try{
            ProviderHelper::idenpotencyTable("BOOMING_".$provider_trans_id);
        }catch(\Exception $e){
            //create filter here exte 1 and 2
            $bet_transaction = GameTransactionMDB::findGameExt($provider_trans_id, 1,'round_id', $client_details);
            if($bet_transaction != null) {
                if ($bet_transaction->transaction_detail == "success" ) {
                    $response =  [
                        "balance" => (string)$client_details->balance
                    ];
                } else {
                    $response = array(
                        'error' => 'low_balance',
                        'message' => 'You have insufficient balance to place a bet'
                    );
                }
               
            }
            Helper::saveLog('Booming Callback idom', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return json_encode($response, JSON_FORCE_OBJECT);
        }
   
        $game_details = Helper::getInfoPlayerGameRound($client_details->player_token);
        if($game_details == null){
            return   $response = ['error' => '2010','message' => 'Unsupported parameters provided'];
        }
            $game_code = $game_details->game_code;
            $bet_amount = $data["bet"];
            $pay_amount = $data["win"];
            $income = $bet_amount - $pay_amount;
            $win_or_lost = $data["win"] == 0.0 ? 0 : 1;  /// 1win 0lost
            $entry_id = 2;// 1/bet/debit , 2//win/credit
            //Create GameTransaction, GameExtension
            $gameTransactionData = array(
                "provider_trans_id" => $provider_trans_id,
                "token_id" => $client_details->token_id,
                "game_id" => $game_details->game_code,
                "round_id" => $round_id,
                "bet_amount" => $bet_amount,
                "win" => 5,
                "pay_amount" => $pay_amount,
                "income" => $income,
                "entry_id" => $entry_id,
            ); 
            $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
            $gameTransactionEXTData= array(
                "game_trans_id" => $game_transaction_id,
                "provider_trans_id" => $provider_trans_id,
                "round_id" =>  $round_id,
                "amount" => $bet_amount,
                "game_transaction_type"=> 1,
                "provider_request" =>json_encode($request->all()),
                );
             $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
            
            //requesttosend, and responsetoclient client side
            $fund_extra_data = [
	            'provider_name' => $game_details->provider_name
	        ];
            try {
				$client_response = ClientRequestHelper::fundTransferFunta($client_details,$bet_amount,$game_code,$game_details->game_name,$game_trans_ext_id,$game_transaction_id,"debit",false,$fund_extra_data);
	        } catch (\Exception $e) {
	            $response = array(
                    'error' => '2099',
                    'message' => 'Generic validation error'
                );
		        $updateTransactionEXt = array(
		            "mw_response" => json_encode($response),
		            'mw_request' => $client_response,
		            'client_response' => $client_response,
		            "transaction_detail" => "FATAL",
					"general_details" =>$e->getMessage(),
		        );
		        GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
				$updateGameTransaction = [
	                "win" => 2
	            ];
	            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_transaction_id, $client_details);
				Helper::saveLog('Bomming BET FATAL ERROR', $this->provider_db_id, json_encode($request->all()), $response);
			    return $response;
	        }
            if (isset($client_response->fundtransferresponse->status->code)) {
                ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                switch ($client_response->fundtransferresponse->status->code) {
                    case "200":
                        $response =  [
                            "balance" => (string)$client_response->fundtransferresponse->balance
                        ];
                        
                        $updateTransactionEXt = array(
							"mw_response" =>json_encode($response),
							"mw_request"=>json_encode($client_response->requestoclient),
							"client_response" =>json_encode($client_response->fundtransferresponse),
							"transaction_detail" =>"success",
							"general_details" => "success",
						);
                        GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                        break;
                    
                    default:
                        $updateGameTransaction = [
                            "win" => 2
                        ];
                        GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_transaction_id, $client_details);
                        $response = array(
                            'error' => 'low_balance',
                            'message' => 'You have insufficient balance to place a bet'
                        );
                        $updateTransactionEXt = array(
							"mw_response" =>json_encode($response),
							"mw_request"=>json_encode($client_response->requestoclient),
							"client_response" =>json_encode($client_response->fundtransferresponse),
							"transaction_detail" =>"402",
							"general_details" => "FAILED",
						);
                        GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                        return json_encode($response, JSON_FORCE_OBJECT); 
                        break;
                }

            }
        // WIN PROCESS
        $balance = $client_response->fundtransferresponse->balance + $pay_amount;
        ProviderHelper::_insertOrUpdate($client_details->token_id, $balance); 
        $response =  [
            "balance" => (string)$balance
        ];
        $gameTransactionEXTData = array(
            "game_trans_id" => $game_transaction_id,
            "provider_trans_id" => $provider_trans_id,
            "round_id" =>  $round_id,
            "amount" => $pay_amount,
            "game_transaction_type"=> 2,
            "provider_request" =>json_encode($request->all()),
            "mw_response" =>json_encode($response),
            "transaction_detail" =>"success",
            "general_details" => "success",
        );
        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details); 
        $body_details = [
            "type" => "credit",
            "win" => $win_or_lost,
            "token" => $client_details->player_token,
            "rollback" => false,
            "game_details" => [
                "game_id" => $game_details->game_id
            ],
            "game_transaction" => [
                "amount" => $pay_amount
            ],
            "connection_name" => $client_details->connection_name,
            "game_trans_ext_id" => $game_trans_ext_id,
            "game_transaction_id" => $game_transaction_id

        ];
        try {
            $client = new Client();
             $guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-bgFundTransferV2MultiDB',
                 [ 'body' => json_encode($body_details), 'timeout' => '2.00']
             );
             //THIS RESPONSE IF THE TIMEOUT NOT FAILED
            Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($request->all()), $response);
            return $response;
        } catch (\Exception $e) {
            Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($request->all()), $response);
            return $response;
        }
    }
    
    public function rollBack(Request $request){
        Helper::saveLog('Booming', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT),"Rollback");
        $data = $request->all();
        $playersid = explode('_', $data["player_id"]);
        $client_details = ProviderHelper::getClientDetails('player_id',$playersid[1]);
        if($client_details == null){
            $errormessage = array(
                'error' => '2012',
                'message' => 'Invalid Player ID'
            );
            Helper::saveLog('Booming Callback error', $this->provider_db_id, json_encode($request->all(), JSON_FORCE_OBJECT),  $errormessage);
            return json_encode($errormessage, JSON_FORCE_OBJECT); 
        }
        $round_id = $data["session_id"]."_".$data["round"];
        $provider_trans_id = $data["session_id"]."_".$data["round"];
        try{
            ProviderHelper::idenpotencyTable("BOOMING_".$provider_trans_id);
        }catch(\Exception $e){
            //create filter here exte 1 and 2
            $bet_transaction = GameTransactionMDB::findGameExt($provider_trans_id, 1,'round_id', $client_details);
            if($bet_transaction != null) {
                if ($bet_transaction->transaction_detail == "success" ) {
                    $response =  [
                        "balance" => (string)$client_details->balance
                    ];
                } else {
                    $response = array(
                        'error' => 'low_balance',
                        'message' => 'You have insufficient balance to place a bet'
                    );
                }
               
            }
            Helper::saveLog('Booming Callback idom', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return json_encode($response, JSON_FORCE_OBJECT);
        }

    }

    
}
