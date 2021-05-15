<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ProviderHelper;
use DB;
use App\Helpers\Helper;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use App\Helpers\AWSHelper;

class PragmaticPLayController extends Controller
{
    public $key;
    public $provider_id = 26; //26 


    public function __construct(){
    	$this->key = config('providerlinks.tpp.secret_key');
    }

    public function authenticate(Request $request)
    {
        $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);
        
        Helper::saveLog('PP authenticate', $this->provider_id, json_encode($data) , "");

        // $hash = md5('providerId='.$data->providerId.'&token='.$data->token.$this->key);
        $dataSort = json_decode($json_encode, true);
        $hash = $this->hashParam($dataSort);

        if($hash != $data->hash){
            $response = [
                "error" => 5,
                "decription" => "Hash don't match!"
            ];
            return $response;
            Helper::saveLog("PP hash error", $this->provider_id, json_encode($data), $response);
        }
               
        $providerId = $data->providerId;
        $hash = $data->hash;
        $token = $data->token;
        $client_details = ProviderHelper::getClientDetails('token',$token);
       
        if($client_details != null)
        {
            $player_details = Providerhelper::playerDetailsCall($client_details->player_token);
            $currency = $client_details->default_currency;
            $country = $player_details->playerdetailsresponse->country_code;
            $balance = $player_details->playerdetailsresponse->balance;
            $userid = "TGaming_".$client_details->player_id;
            $save_bal = DB::table("player_session_tokens")->where("token_id","=",$client_details->token_id)->update(["balance" => floatval(number_format($balance, 2, '.', ''))]); #val
            $response = array(
                "userId" => $userid,
                "currency" => $currency,
                "cash" => floatval(number_format($balance, 2, '.', '')),
                "bonus" => 0.00,
                "country" => $country,
                "jurisdiction" => "99",
                "error" => 0,
                "decription" => "Success"
            );

            Helper::saveLog('PP authenticate', $this->provider_id, json_encode($data) , $response);

            
        }else{
            $response = [
                "error" => 4,
                "decription" => "Success"
            ];
        }

        return $response;
    }

    public function balance(Request $request)
    {
        
        $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);


        Helper::saveLog('PP balance', $this->provider_id, json_encode($data) , "balance");
        // $hash = md5('providerId='.$data->providerId.'&userId='.$data->userId.$this->key);

        $dataSort = json_decode($json_encode, true);
        $hash = $this->hashParam($dataSort);


        if($hash != $data->hash){
            $response = [
                "error" => 5,
                "decription" => "Hash don't match!"
            ];
            return $response;
            Helper::saveLog("PP hash error", $this->provider_id, json_encode($data), $response);
        }

        $playerId = ProviderHelper::explodeUsername('_',$data->userId);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        // $player_details = Providerhelper::playerDetailsCall($client_details->player_token);
   
        $response = array(
            "currency" => $client_details->default_currency,
            "cash" => floatval(number_format($client_details->balance, 2, '.', '')),
            "bonus" => 0.00,
            "error" => 0,
            "description" => "Success"
        );
        
        return $response;
    }

    public function bet(Request $request)
    {
        $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);

        $dataSort = json_decode($json_encode, true);

        $hash = $this->hashParam($dataSort);

        // $hash = md5('amount='.$data->amount.'&gameId='.$data->gameId.'&providerId='.$data->providerId.'&reference='.$data->reference.'&roundDetails='.$data->roundDetails.'&roundId='.$data->roundId.'&timestamp='.$data->timestamp.'&userId='.$data->userId.$this->key);
        if($hash != $data->hash){
            $response = [
                "error" => 5,
                "decription" => "Invalid Hash"
            ];
            return $response;
            Helper::saveLog("PP hash error", $this->provider_id, json_encode($data), $response);
        }

        AWSHelper::saveLog('TPP bet recieved', $this->provider_id, json_encode($data), "recieved");
        $game_details = Helper::findGameDetails('game_code', $this->provider_id, $data->gameId);
        $playerId = ProviderHelper::explodeUsername('_',$data->userId);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);

        # Check Game Restricted
		$restricted_player = ProviderHelper::checkGameRestricted($game_details->game_id, $client_details->player_id);
		if($restricted_player){
			$response = array(
                "error" => 3,
                "description" => "Player has been blocked on This game"
            );
            AWSHelper::saveLog('TPP bet restricted_player', $this->provider_id, json_encode($data), $response);
			return $response;
		}

        // $player_details = Providerhelper::playerDetailsCall($client_details->player_token);
   

        $tokenId = $client_details->token_id;
        $game_code = $data->gameId;
        $bet_amount = $data->amount;
        $roundId = $data->roundId;
        $provider_trans_id = $data->reference;
        $bet_payout = 0; // Bet always 0 payout!
        $method = 1; // 1 bet, 2 win
        $win_or_lost = 0; // 0 Lost, 1 win, 3 draw, 4 refund, 5 processing
        $payout_reason = 'Bet';
        $income = $data->amount;

        if($bet_amount > $client_details->balance){

            $response = array(
                "cash" => floatval(number_format($client_details->balance, 2, '.', '')),
                "error" => 1,
                "description" => "Not Enough Balance"
            );
            Helper::saveLog('PP bet not enough balance', $this->provider_id,json_encode($data) , $response);
            return $response;
        }
       
        $checkGameTrans = DB::select("SELECT game_trans_id FROM game_transactions WHERE provider_trans_id = '".$data->reference."' AND round_id = '".$data->roundId."' ");
        // $checkGameTrans = DB::table('game_transactions')->select('game_trans_id')->where('provider_trans_id','=',$data->reference)->where("round_id","=",$data->roundId)->get();
        if(count($checkGameTrans) > 0){
            
            $response = array(
                "transactionId" => $checkGameTrans[0]->game_trans_id,
                "currency" => $client_details->default_currency,
                "cash" => floatval(number_format($client_details->balance, 2, '.', '')),
                "bonus" => 0.00,
                "usedPromo" => 0,
                "error" => 0,
                "description" => "Success"
            );

            Helper::saveLog('PP bet duplicate', $this->provider_id,json_encode($data) , $response);
            return $response;
        }
        
        // $checkDoubleBet = DB::table('game_transactions')->select('game_trans_id')->where("round_id","=",$data->roundId)->get();
        $checkDoubleBet = DB::select("SELECT game_trans_id,bet_amount FROM game_transactions WHERE round_id = '".$data->roundId."' ");
        if(count($checkDoubleBet) > 0){
            // $checkDuplicate = DB::table('game_transaction_ext')->where("round_id","=",$data->roundId)->where('provider_trans_id','=',$data->reference)->get();
            $checkDuplicate = DB::select("SELECT game_transaction_type FROM game_transaction_ext WHERE provider_trans_id = '".$data->reference."' ");
            if(count($checkDuplicate) > 0){
                $response = array(
                    "transactionId" => $checkDoubleBet[0]->game_trans_id,
                    "currency" => $client_details->default_currency,
                    "cash" => floatval(number_format($client_details->balance, 2, '.', '')),
                    "bonus" => 0.00,
                    "usedPromo" => 0,
                    "error" => 0,
                    "description" => "Success"
                );
                Helper::saveLog('PP bet duplicate in double', $this->provider_id,json_encode($data) , $response);
                return $response;
            }
     


            $amount = $checkDoubleBet[0]->bet_amount + $data->amount;
            $game_transextension = ProviderHelper::createGameTransExtV2($checkDoubleBet[0]->game_trans_id,$provider_trans_id, $roundId, $data->amount, 1);

            $client_response = ClientRequestHelper::fundTransfer($client_details, $data->amount,$game_details->game_code,$game_details->game_name,$game_transextension,$checkDoubleBet[0]->game_trans_id,'debit');
            
            $updateDoubleBet = DB::table('game_transactions')->where('game_trans_id','=',$checkDoubleBet[0]->game_trans_id)->update(["bet_amount" => $amount, "transaction_reason" => "Double Bet"]);

            $response_log = array(
                "transactionId" => $checkDoubleBet[0]->game_trans_id,
                "currency" => $client_details->default_currency,
                "cash" => floatval(number_format($client_response->fundtransferresponse->balance, 2, '.', '')),
                "bonus" => 0.00,
                "usedPromo" => 0,
                "error" => 0,
                "description" => "Success"
            );
            $trans_details = array(
                "game_trans_id" => $checkDoubleBet[0]->game_trans_id,
                "bet_amount" => $amount,
                "win" => false,
                "response" => $response_log
            );
            // $game_trans_ext = ProviderHelper::createGameTransExt( $checkDoubleBet[0]->game_trans_id, $data->reference, $data->roundId, $amount, 1, $data, $response_log, $responseDetails['requesttosend'], $responseDetails['client_response'], $trans_details);
            $response = array(
                "transactionId" => $game_transextension,
                "currency" => $client_details->default_currency,
                "cash" => floatval(number_format($client_response->fundtransferresponse->balance, 2, '.', '')),
                "bonus" => 0.00,
                "usedPromo" => 0,
                "error" => 0,
                "description" => "Success"
            );

            $updateGameTransExt = DB::table('game_transaction_ext')->where('game_trans_ext_id','=',$game_transextension)->update(["amount" => $data->amount,"game_transaction_type" => '1',"provider_request" => json_encode($data),"mw_response" => json_encode($response),"mw_request" => json_encode($client_response->requestoclient),"client_response" => json_encode($client_response),"transaction_detail" => json_encode($response) ]);


            Helper::saveLog('PP bet additional', $this->provider_id,json_encode($data) , $response);
            return $response;
        }
        // $responseDetails = $this->responsetosend($client_details->client_access_token, $client_details->client_api_key, $game_details->game_code, $game_details->game_name, $client_details->client_player_id, $client_details->player_token, $bet_amount, $client, $client_details->fund_transfer_url, "debit",$client_details->default_currency );
        
        $game_trans = ProviderHelper::createGameTransaction($tokenId, $game_details->game_id, $bet_amount,  $bet_payout, $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id, $roundId);
      
        $game_transextension = ProviderHelper::createGameTransExtV2($game_trans,$provider_trans_id, $roundId, $bet_amount, 1);

        try {
            $client_response = ClientRequestHelper::fundTransfer($client_details, $bet_amount,$game_details->game_code,$game_details->game_name,$game_transextension,$game_trans,'debit');
            // $balance = $client_details->balance - $bet_amount;
            $response = array(
                "transactionId" => $game_trans,
                "currency" => $client_details->default_currency,
                "cash" => floatval(number_format($client_response->fundtransferresponse->balance, 2, '.', '')),
                // "cash" => floatval(number_format($balance, 2, '.', '')),
                "bonus" => 0.00,
                "usedPromo" => 0,
                "error" => 0,
                "description" => "Success"
            );
          
            ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $response, $client_response->requestoclient, $client_response, $response);
            $save_bal = DB::table("player_session_tokens")->where("token_id","=",$tokenId)->update(["balance" => $client_response->fundtransferresponse->balance]);
            AWSHelper::saveLog('TPP bet response', $this->provider_id, json_encode($data), "response");
            return $response;
        } catch (\Exception $e) {
            $msg = array("status" => 'error',"message" => $e->getMessage());
            ProviderHelper::updateGameTransactionStatus($game_trans, 99, 99);
            ProviderHelper::updatecreateGameTransExt($game_transextension, 'FAILED', $msg, 'FAILED', $e->getMessage(), 'FAILED', 'FAILED');
            Helper::saveLog('PP bet - FATAL ERROR', $this->provider_id, json_encode($data), Helper::datesent());
            return $msg;
        }

        
    }

    public function result(Request $request)
    {
        $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true); 
        $data = json_decode($json_encode);
        
        AWSHelper::saveLog('TPP result recieved', $this->provider_id, json_encode($data), "recieved");
        
        // $hash = md5('amount='.$data->amount.'&gameId='.$data->gameId.'&providerId='.$data->providerId.'&reference='.$data->reference.'&roundDetails='.$data->roundDetails.'&roundId='.$data->roundId.'&timestamp='.$data->timestamp.'&userId='.$data->userId.$this->key);
        $dataSort = json_decode($json_encode, true);
        $hash = $this->hashParam($dataSort);
        if($hash != $data->hash){
            $response = [
                "error" => 5,
                "decription" => "Hash don't match!"
            ];
            AWSHelper::saveLog('TPP result Hash', $this->provider_id, json_encode($data), $response);
            return $response;
        }
        $game_trans = DB::select("SELECT game_trans_id, bet_amount FROM game_transactions WHERE round_id = '".$data->roundId."' ");

        $playerId = ProviderHelper::explodeUsername('_',$data->userId);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        $game_details = Helper::findGameDetails('game_code', $this->provider_id, $data->gameId);
        $checkExt = ProviderHelper::findGameExt($data->roundId, '2', 'round_id');
        
        if($checkExt  != 'false'){
            $response_log = array(
                "transactionId" => $game_trans[0]->game_trans_id,
                "currency" => $client_details->default_currency,
                "cash" => floatval(number_format($client_details->balance, 2, '.', '')),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success",
            );
            AWSHelper::saveLog('TPP result ext not found', $this->provider_id, json_encode($data), $response);
            return $response_log;
        }

        $token_id = $client_details->token_id;
        $game_id = $game_details->game_id;
        $game_name = $game_details->game_name;
        $game_code = $game_details->game_code;
        $bet_amount = $data->amount;
        $payout = $data->amount;
        $income = $game_trans[0]->bet_amount - $payout;
       
        $win = $bet_amount > 0 ? 1 : 0;
         
        $entry_id = $win == 0 ? '1' : '2';
        $provider_trans_id = $data->reference;
        $round_id = $data->roundId;

        $game_trans_ext_v2 = ProviderHelper::createGameTransExtV2( $game_trans[0]->game_trans_id, $provider_trans_id, $round_id, $bet_amount, $entry_id);
        try {

            $income = $game_trans[0]->bet_amount - $data->amount;
            
            $update = DB::table('game_transactions')->where("game_trans_id","=",$game_trans[0]->game_trans_id)->update(["round_id" => $round_id, "pay_amount" => $payout, "income" => $income, "win" => $win, "entry_id" => $entry_id ]);
            $balance = $client_details->balance + $data->amount;
           
            $response = array(
                "transactionId" => $game_trans_ext_v2,
                "currency" => $client_details->default_currency,
                "cash" => floatval(number_format($balance, 2, '.', '')),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success",
            );
            // ProviderHelper::updateGameTransactionFlowStatus($game_trans[0]->game_trans_id, 2);
            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "provider" => 'tpp',
                    "game_trans_ext_id" => $game_trans_ext_v2,
                ],
                "provider" => [
                    "provider_request" => $data,
                    "provider_trans_id"=>$provider_trans_id,
                    "provider_round_id"=>$round_id,
                ],
                "mwapi" => [
                    "roundId"=> $game_trans[0]->game_trans_id,
                    "type"=>2,
                    "game_id" => $game_details->game_id,
                    "player_id" => $client_details->player_id,
                    "mw_response" => $response,
                ]
            ];
            
            $client_response2 = ClientRequestHelper::fundTransfer_TG($client_details, $bet_amount, $game_code, $game_name, $game_trans[0]->game_trans_id, 'credit', false, $action_payload);
            $updateGameTransExt = DB::table('game_transaction_ext')->where('game_trans_ext_id','=',$game_trans_ext_v2)->update(["amount" => $bet_amount ,"game_transaction_type" => 2, "provider_request" => json_encode($data),"mw_response" => json_encode($response),"mw_request" => json_encode($client_response2->requestoclient),"client_response" => json_encode($client_response2->fundtransferresponse),"transaction_detail" => "Credit" ]);
            $save_bal = DB::table("player_session_tokens")->where("token_id","=",$token_id)->update(["balance" => $balance]);
            AWSHelper::saveLog('TPP result response', $this->provider_id, json_encode($data), "response");
            return $response;

        } catch (\Exception $e) {
            $msg = array("status" => 'error',"message" => $e->getMessage());
            ProviderHelper::updatecreateGameTransExt($game_trans[0]->game_trans_id, 'FAILED', $msg, 'FAILED', $e->getMessage(), 'FAILED', 'FAILED');
            Helper::saveLog('PP gameWin - FATAL ERROR', $this->provider_id, json_encode($data), Helper::datesent());
            return $msg;
        }
    }

    public function endRound(Request $request)
    {
        $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);

        AWSHelper::saveLog('PP endRound request', $this->provider_id, json_encode($data) ,"endRound");

        $dataSort = json_decode($json_encode, true);
        $hash = $this->hashParam($dataSort);
        if($hash != $data->hash){
            $response = [
                "error" => 5,
                "decription" => "Hash dont match!"
            ];
            AWSHelper::saveLog('PP endRound hash', $this->provider_id, json_encode($data) ,$response);
            return $response;
        }
        $game_trans = DB::select("SELECT game_trans_id, bet_amount, win FROM game_transactions WHERE round_id = '".$data->roundId."' ");
        $playerId = ProviderHelper::explodeUsername('_',$data->userId);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        $game_details = Helper::findGameDetails('game_code', $this->provider_id, $data->gameId);
        $checkExt = ProviderHelper::findGameExt($data->roundId, '1', 'round_id');
        $provider_trans_id = $checkExt->provider_trans_id;
        $round_id = $data->roundId;
        $bet_amount = 0;
        $entry_id = 2;
        $game_name = $game_details->game_name;
        $game_code = $game_details->game_code;
        $token_id = $client_details->token_id;
        if($game_trans[0]->win == 1){
            $response = array(
                "cash" => floatval(number_format($client_details->balance, 2, '.', '')),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success",
            );
        }

        $game_trans_ext_v2 = ProviderHelper::createGameTransExtV2( $game_trans[0]->game_trans_id, $provider_trans_id, $round_id, $bet_amount, $entry_id);
        try {
            $balance = $client_details->balance;
           
            $response = array(
                "cash" => floatval(number_format($balance, 2, '.', '')),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success",
            );
            // ProviderHelper::updateGameTransactionFlowStatus($game_trans[0]->game_trans_id, 2);
            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "provider" => 'tpp',
                    "game_trans_ext_id" => $game_trans_ext_v2,
                ],
                "provider" => [
                    "provider_request" => $data,
                    "provider_trans_id"=>$provider_trans_id,
                    "provider_round_id"=>$round_id,
                ],
                "mwapi" => [
                    "roundId"=> $game_trans[0]->game_trans_id,
                    "type"=>2,
                    "game_id" => $game_details->game_id,
                    "player_id" => $client_details->player_id,
                    "mw_response" => $response,
                ]
            ];
         
            $client_response2 = ClientRequestHelper::fundTransfer_TG($client_details, $bet_amount, $game_code, $game_name, $game_trans[0]->game_trans_id, 'credit', false, $action_payload);
            $updateGameTransExt = DB::table('game_transaction_ext')->where('game_trans_ext_id','=',$game_trans_ext_v2)->update(["amount" => $bet_amount ,"game_transaction_type" => 2, "provider_request" => json_encode($data),"mw_response" => json_encode($response),"mw_request" => json_encode($client_response2->requestoclient),"client_response" => json_encode($client_response2->fundtransferresponse),"transaction_detail" => "Credit" ]);
            $save_bal = DB::table("player_session_tokens")->where("token_id","=",$token_id)->update(["balance" => $balance]);
            AWSHelper::saveLog('TPP endRound response', $this->provider_id, json_encode($data), "response");
            return $response;

        } catch (\Exception $e) {
            $msg = array("status" => 'error',"message" => $e->getMessage());
            ProviderHelper::updatecreateGameTransExt($game_trans[0]->game_trans_id, 'FAILED', $msg, 'FAILED', $e->getMessage(), 'FAILED', 'FAILED');
            Helper::saveLog('PP endRound - FATAL ERROR', $this->provider_id, json_encode($data), Helper::datesent());
            return $msg;
        }
    }
    
    public function getBalancePerGame(Request $request)
    {
        $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);
        
        Helper::saveLog('PP getBalancePerGame request', $this->provider_id, json_encode($data) ,"getBalancePerGame");

        $playerId = ProviderHelper::explodeUsername('_',$data->userId);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        $player_details = Providerhelper::playerDetailsCall($client_details->player_token);

        $gameIdList = explode(",", $data->gameIdList);

        $response = array();
        foreach($gameIdList as $item):

            $games = DB::select('select g.game_code, FORMAT(sum(gt.pay_amount- gt.bet_amount),2) as sub_total, case when  FORMAT(sum(gt.pay_amount- gt.bet_amount),2) > 0 then  FORMAT(sum(gt.pay_amount- gt.bet_amount),2) else 0 end as total from game_transactions gt inner join games g using (game_id) where g.provider_id = '.$this->provider_id.' and g.game_code = "'.$item.'"');
            $data = array(
                "gameID" => $item,
                // "cash" => floatval(number_format($games[0]->total, 2, '.', '')),
                "cash" => floatval(number_format($player_details->playerdetailsresponse->balance, 2, '.', '')),
                "bonus" => 0.00
            );
            array_push($response,$data);
        endforeach; 
        $response = array(
             "gamesBalances" => $response
        );

        // Helper::saveLog('PP getBalancePerGame response', $this->provider_id, json_encode($data) ,$response);
        // return $game_bal;

        // $response = [
        //     "gamesBalances" => [
        //         "gameID" => $data->gameIdList,
        //         "cash" => floatval(number_format($player_details->playerdetailsresponse->balance, 2, '.', '')),
        //         "bonus" => 0.00
        //     ]
        // ];
        return $response;
    }

    public function sessionExpired(Request $request)
    {
        $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);
        
        Helper::saveLog('PP sessionExpired request', $this->provider_id, json_encode($data) ,"sessionExpired");

        $dataSort = json_decode($json_encode, true);
        $hash = $this->hashParam($dataSort);

        if($hash != $data->hash){
            $response = [
                "error" => 5,
                "decription" => "Hash don't match!"
            ];
            return $response;
            Helper::saveLog("PP hash error", $this->provider_id, json_encode($data), $response);
        }

        $response = array(
            "error" => 0,
            "description" => "Success"
        );

        Helper::saveLog('PP sessionExpired request', $this->provider_id, json_encode($data) , $response);
        return $response;

    }

    public function refund(Request $request)
    {
        $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);

        Helper::saveLog('PP refund request', $this->provider_id, json_encode($data) , "");

        // $hash = md5('amount='.$data->amount.'&gameId='.$data->gameId.'&providerId='.$data->providerId.'&reference='.$data->reference.'&roundDetails='.$data->roundDetails.'&roundId='.$data->roundId.'&timestamp='.$data->timestamp.'&userId='.$data->userId.$this->key);
        $dataSort = json_decode($json_encode, true);
        $hash = $this->hashParam($dataSort);

        if($hash != $data->hash){
            $response = [
                "error" => 5,
                "decription" => "Hash don't match!"
            ];
            return $response;
            Helper::saveLog("PP hash error", $this->provider_id, json_encode($data), $response);
        }

        $game_trans = DB::table("game_transactions")->select('game_trans_id','game_id','bet_amount','win')->where("round_id","=",$data->roundId)->get();

        // return count($game_trans);
        if(count($game_trans) > 0){

            if($game_trans[0]->win == 4){

                $response = array(
                    "error" => 0,
                    "description" => "Success"
                );
                return $response;
            }

            $game_details = DB::table("games")->where("game_id","=",$game_trans[0]->game_id)->first();
            
            $playerId = ProviderHelper::explodeUsername('_',$data->userId);
            $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
            $player_details = Providerhelper::playerDetailsCall($client_details->player_token);
            // $game_details = Helper::findGameDetails('game_code', 25, $gameDetails->game_code);
    
            $client = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$client_details->client_access_token
                ]
            ]);
            
            $roundId = $data->roundId;
            $provider_trans_id = $data->reference;

            $bet_amount = $game_trans[0]->bet_amount;
    
            $game_trans_ext_v2 = ProviderHelper::createGameTransExtV2( $game_trans[0]->game_trans_id, $provider_trans_id, $roundId, $bet_amount, '3');

            $client_response = ClientRequestHelper::fundTransfer($client_details, $bet_amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_v2, $game_trans[0]->game_trans_id, 'credit', 'true');

            // $responseDetails = $this->responsetosend($client_details->client_access_token, $client_details->client_api_key, $game_details->game_code, $game_details->game_name, $client_details->client_player_id, $client_details->player_token, $bet_amount, $client, $client_details->fund_transfer_url, "credit",$client_details->default_currency, true );
    
            $refund_update = DB::table('game_transactions')->where("round_id","=",$data->roundId)->update(['win' => '4']);
                    
            //
            $response = array(
                // "transactionId" => $game_trans[0]->game_trans_id,
                "transactionId" => $game_trans_ext_v2,
                "error" => 0,
                "description" => "Success"
            );
            
            $updateGameTransExt = DB::table('game_transaction_ext')->where('game_trans_ext_id','=',$game_trans_ext_v2)->update(["amount" => $bet_amount,"game_transaction_type" => '3',"provider_request" => json_encode($data),"mw_response" => json_encode($response),"mw_request" => json_encode($client_response->requestoclient),"client_response" => json_encode($client_response),"transaction_detail" => json_encode($response) ]);
    
            Helper::saveLog('PP refund request', $this->provider_id, json_encode($data) , $response);
            // $response_log = array(
            //     "transactionId" => $game_trans[0]->game_trans_id,
            //     "error" => 0,
            //     "description" => "Success"
            // );
    
            return $response;

        }else{
            $response = array(
                "error" => 0,
                "description" => "Success"
            );
            return $response;
        }

    }

    public function bonusWin(Request $request)
    {
        $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);

        Helper::saveLog('PP bonus', $this->provider_id, json_encode($data) , "");

        $game_trans = DB::table("game_transactions")->where("round_id","=",$data->roundId)->first();
        $game_details = DB::table("games")->where("game_id","=",$game_trans->game_id)->first();
        
        $playerId = ProviderHelper::explodeUsername('_',$data->userId);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        $player_details = Providerhelper::playerDetailsCall($client_details->player_token);

        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$client_details->client_access_token
            ]
        ]);
        
    }

    public function promoWin(Request $request){

        $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);

        Helper::saveLog('PP promoWin request', $this->provider_id, json_encode($data) , "");

        $dataSort = json_decode($json_encode, true);
        $hash = "amount=$data->amount&campaignId=$data->campaignId&campaignType=$data->campaignType&currency=$data->currency&providerId=$data->providerId&reference=$data->reference&timestamp=$data->timestamp&userId=$data->userId$this->key";
        $hash = md5($hash);
        if($hash != $data->hash){
            $response = [
                "error" => 5,
                "decription" => "Hash don't match!"
            ];
            return $response;
            Helper::saveLog("PP hash error", $this->provider_id, json_encode($data), $response);
        }

        $playerId = ProviderHelper::explodeUsername('_',$data->userId);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        $player_details = Providerhelper::playerDetailsCall($client_details->player_token);
        
        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$client_details->client_access_token
            ]
        ]);


        try {
        $game_details = Helper::findGameDetails('game_code', $this->provider_id, 'vs25pyramid');
        $tokenId = $client_details->token_id;
        $roundId = $data->campaignId;
        $checkGameTrans = DB::table('game_transactions')->where("round_id","=",$roundId)->where("provider_trans_id","=", $data->reference)->get();
        // return count($checkGameTrans);
        // $checkExt = ProviderHelper::findGameExt($roundId, '2', 'round_id');

        if(count($checkGameTrans) > 0){
            $response_log = array(
                "transactionId" => $checkGameTrans[0]->game_trans_id,
                "currency" => $client_details->default_currency,
                "cash" => floatval(number_format($player_details->playerdetailsresponse->balance, 2, '.', '')),
                "bonus" => 0,
                "error" => 0,
                "description" => "Success",
            );

            return $response_log;
        }

        // vs25pyramid
        // Pyramid King
        $responseDetails = $this->responsetosend($client_details->client_access_token, $client_details->client_api_key, "vs25pyramid", "Pyramid King", $client_details->client_player_id, $client_details->player_token, $data->amount, $client, $client_details->fund_transfer_url, "credit",$client_details->default_currency);

        $gametrans = ProviderHelper::createGameTransaction($tokenId, $game_details->game_id, 0.00, $data->amount, 2, 1, "Tournament", "Promo Win ", 0- $data->amount, $data->reference, $roundId);
        
        $response_log = array(
            "transactionId" => $gametrans,
            "currency" => $client_details->default_currency,
            "cash" => floatval(number_format($responseDetails['client_response']->fundtransferresponse->balance, 2, '.', '')),
            "bonus" => 0,
            "error" => 0,
            "description" => "Success",
        );

        $game_trans_ext = ProviderHelper::createGameTransExt( $gametrans, $data->reference, $roundId, $data->amount, 2, $data, $response_log, $responseDetails['requesttosend'], $responseDetails['client_response'], "Promo Win Tournament");
        
        $response = array(
            "transactionId" => $game_trans_ext,
            "currency" => $client_details->default_currency,
            "cash" => floatval(number_format($responseDetails['client_response']->fundtransferresponse->balance, 2, '.', '')),
            "bonus" => 0,
            "error" => 0,
            "description" => "Success",
        );
        Helper::saveLog('PP promoWin response', $this->provider_id, json_encode($data) , $response);
        return $response;
        }catch(\Exception $e){
            $error = [
                'error' => $e->getMessage()
            ];
            Helper::saveLog('PP ERROR', $this->provider_id, json_encode($data), $e->getMessage());
            return $error;
        }
    }

    public function jackpotWin(Request $request){
        $enc_body = file_get_contents("php://input");
        parse_str($enc_body, $data);
        $json_encode = json_encode($data, true);
        $data = json_decode($json_encode);

        Helper::saveLog('PP jackpotWin request', $this->provider_id, json_encode($data) , "");

        $dataSort = json_decode($json_encode, true);
        $hash = $this->hashParam($dataSort);
        if($hash != $data->hash){
            $response = [
                "error" => 5,
                "decription" => "Hash don't match!"
            ];
            return $response;
            Helper::saveLog("PP hash error", $this->provider_id, json_encode($data), $response);
        }

        $game_trans = DB::table("game_transactions")->where("round_id","=",$data->roundId)->first();
        $game_details = DB::table("games")->where("game_id","=",$game_trans->game_id)->first();
        
        $playerId = ProviderHelper::explodeUsername('_',$data->userId);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        $player_details = Providerhelper::playerDetailsCall($client_details->player_token);

        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$client_details->client_access_token
            ]
        ]);

        $responseDetails = $this->responsetosend($client_details->client_access_token,          $client_details->client_api_key, $game_details->game_code, $game_details->game_name, $client_details->client_player_id, $client_details->player_token, $data->amount, $client, $client_details->fund_transfer_url, "credit", $client_details->default_currency );

        $game_trans = DB::table('game_transactions')->where("round_id","=",$data->roundId)->get();

        $income = $game_trans[0]->bet_amount - $data->amount;
        $win = 1;
        
        $updateGameTrans = DB::table('game_transactions')
            ->where("round_id","=",$data->roundId)
            ->update([
                "win" => $win,
                "pay_amount" => $data->amount,
                "income" => $income,
                "entry_id" => 2,
                "payout_reason" => "Jackpot Win"
            ]);
    
        $response = array(
            "transactionId" => $game_trans[0]->game_trans_id,
            "currency" => $client_details->default_currency,
            "cash" => floatval(number_format($responseDetails['client_response']->fundtransferresponse->balance, 2, '.', '')),
            "bonus" => 0,
            "error" => 0,
            "description" => "Success",
        );

        $trans_details = array(
            "game_trans_id" => $game_trans[0]->game_trans_id,
            "bet_amount" => $game_trans[0]->bet_amount,
            "pay_amount" => $data->amount,
            "win" => true,
            "response" => $response
            );

        $game_trans_ext = ProviderHelper::createGameTransExt($game_trans[0]->game_trans_id, $game_trans[0]->provider_trans_id, $game_trans[0]->round_id, $data->amount, 2, $data, $response, $responseDetails['requesttosend'], $responseDetails['client_response'], $trans_details);

        return $response;

    }

   

    public function responsetosend($client_access_token,$client_api_key,$game_code,$game_name,$client_player_id,$player_token,$amount,$client,$fund_transfer_url,$transtype,$currency,$rollback=false){
        $requesttosend = [
            "access_token" => $client_access_token,
            "hashkey" => md5($client_api_key.$client_access_token),
            "type" => "fundtransferrequest",
            "datesent" => Helper::datesent(),
            "gamedetails" => [
            "gameid" => $game_code, // $game_code
            "gamename" => $game_name
            ],
            "fundtransferrequest" => [
                "playerinfo" => [
                "client_player_id" => $client_player_id,
                "token" => $player_token,
                ],
                "fundinfo" => [
                        "gamesessionid" => "",
                        "transactiontype" => $transtype,
                        "transferid" => "",
                        "rollback" => $rollback,
                        "currencycode" => $currency,
                        "amount" => $amount
                ],
            ],
        ];
        
        $guzzle_response = $client->post($fund_transfer_url,
            ['body' => json_encode($requesttosend)]
        );

        $client_response = json_decode($guzzle_response->getBody()->getContents());
        $data = [
            'requesttosend' => $requesttosend,
            'client_response' => $client_response,
        ];
        return $data;
    }
    

    public function hashParam($sortData){
        ksort($sortData);
        $param = "";
        $i = 0;
        foreach($sortData as $key => $item){
            if($key != 'hash'){
                if($i == 0){
                    $param .= $key ."=". $item;
                }else{
                    $param .= "&".$key ."=". $item;
                }
                $i++;
            }
        }
        $str = str_replace("\n","",$param.$this->key);
        $clean = str_replace("\r","",$str);
        return $hash = md5($clean);
    }

    public function checkGameTrans($round_id, $game_code){
        $check = DB::table('game_transactions as gs')
                ->select('*')
                ->leftJoin('games as g','gs.game_id','=','g.game_id')
                ->where('g.game_code','=',$game_code)
                ->where('gs.round_id','=',$round_id)->get();
        if(count($check) > 0){
            return "true";
        }else{
            return "false";
        }

    }
}
