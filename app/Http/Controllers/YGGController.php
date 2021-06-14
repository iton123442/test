<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ProviderHelper;
use DB;
use App\Helpers\Helper;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;

class YGGController extends Controller
{
    public $provider_id;
    public $org;

    public function __construct(){
        $this->provider_id = config("providerlinks.ygg.provider_id");
        $this->org = config("providerlinks.ygg.Org");
        $this->topOrg = config("providerlinks.ygg.topOrg");
    }

    public function playerinfo(Request $request)
    {
        Helper::saveLog("YGG playerinfo req", $this->provider_id, json_encode($request->all()), "");

        $client_details = ProviderHelper::getClientDetails('token',$request->sessiontoken);
        if($client_details == null){ 
            $response = array(
                "code" => 1000,
                "msg" => "Session expired. Please log in again."
            );
            return $response;
            Helper::saveLog("YGG playerinfo response", $this->provider_id,json_encode($request->all(),JSON_FORCE_OBJECT), $response);
        }
        $player_details = Providerhelper::playerDetailsCall($client_details->player_token);  
        $player_id = "TGaming_".$client_details->player_id;
        $balance = floatval(number_format($player_details->playerdetailsresponse->balance, 2, '.', ''));
        $save_bal = DB::table("player_session_tokens")->where("token_id","=",$client_details->token_id)->update(["balance" => $balance]); #new method
        $response = array(
            "code" => 0,
            "data" => array(
                "gender" => "",
                "playerId" => $player_id,
                "organization" => $this->org,
                "balance" => $balance,
                "applicableBonus" => "",
                "currency" => $client_details->default_currency,
                "homeCurrency" => $client_details->default_currency,
                "nickName" => $client_details->display_name,
                "country" => $player_details->playerdetailsresponse->country_code
            ),
            "msg" => "Success"
        );
        Helper::saveLog("YGG playerinfo response", $this->provider_id, json_encode($request->all()), $response);
        return $response;   
    }

    public function wager(Request $request)
    {
        $playerId = ProviderHelper::explodeUsername('_',$request->playerid);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        $game_details = Helper::findGameDetails('game_code', $this->provider_id, $request->cat5);
        # Check Game Restricted
		$restricted_player = ProviderHelper::checkGameRestricted($game_details->game_id, $client_details->player_id);
		if($restricted_player){
			$response = array(
                "code" => 1007,
                "msg" => "Player has been blocked on This game"
            );
			return $response;
        }
        // $client_details = DB::select("select p.client_id, p.player_id, p.email, p.client_player_id,p.language, p.currency, p.test_player, p.username,p.created_at,pst.token_id,pst.player_token,c.client_url,c.default_currency,pst.status_id,p.display_name,op.client_api_key,op.client_code,op.client_access_token,ce.player_details_url,ce.fund_transfer_url,p.created_at from player_session_tokens pst inner join players as p using(player_id) inner join clients as c using (client_id) inner join client_endpoints as ce using (client_id) inner join operator as op using (operator_id) WHERE player_id = '$playerId' ORDER BY token_id desc LIMIT 1");
        if($client_details == null){ 
            $response = array(
                "code" => 1000,
                "msg" => "Session expired. Please log in again."
            );
            Helper::saveLog("YGG wager response", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return $response;
        }
  
        $balance = $client_details->balance;
        
        $tokenId = $client_details->token_id;
        $game_code = $game_details->game_code;
        $game_id = $game_details->game_id;
        $bet_amount = $request->amount;
        $roundId = $request->reference;
        $provider_trans_id = $request->subreference;
        $bet_payout = 0; // Bet always 0 payout!
        $method = 1; // 1 bet, 2 win
        $win_or_lost = 0; // 0 Lost, 1 win, 3 draw, 4 refund, 5 processing
        $payout_reason = 'Bet';
        $income = $request->amount;
        $checkTrans = DB::select("SELECT game_trans_id FROM game_transactions WHERE provider_trans_id = '$request->subreference' AND round_id = '$request->reference' ");
        // $checkTrans = DB::table('game_transactions')->where('provider_trans_id','=',$request->reference)->where('round_id','=',$request->reference)->get();

        // $gametrans = ProviderHelper::createGameTransaction($tokenId, $game_details->game_id, $bet_amount, 0.00, 1, 0, null, null, $bet_amount, $provider_trans_id, $round_id);
        // $game_trans_ext = ProviderHelper::createGameTransExt( $gametrans, $provider_trans_id, $round_id, $bet_amount, 1, json_encode($request->all()), $response, , $client_response['client_response'], "");  

        try{
          
            if(count($checkTrans) > 0){
                $response = array(
                    "code" => 0,
                    "data" => array(
                        "currency" => $client_details->default_currency,
                        "applicableBonus" => 0.00,
                        "homeCurrency" => $client_details->default_currency,
                        "organization" => $this->org,
                        "balance" => floatval(number_format($client_details->balance, 2, '.', '')),
                        "nickName" => $client_details->display_name,
                        "playerId" => "TGaming_".$client_details->player_id
                    ),
                );
                Helper::saveLog("YGG wager dubplicate", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
                return $response;
            }
           

            $game_trans = ProviderHelper::createGameTransaction($tokenId, $game_id, $bet_amount,  $bet_payout, $method, $win_or_lost, null, $payout_reason, $income, $provider_trans_id, $roundId);
      
            $game_transextension = ProviderHelper::createGameTransExtV2($game_trans,$provider_trans_id, $roundId, $bet_amount, 1);

            if($balance < $request->amount){
                $response = array(
                    "code" => 1006,
                    "msg" => "You do not have sufficient fundsfor the bet."
                );
                Helper::saveLog("YGG wager response", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
                return $response;
            }
            
            $client_response = ClientRequestHelper::fundTransfer($client_details, $bet_amount,$game_details->game_code,$game_details->game_name,$game_transextension,$game_trans,'debit');
            if(isset($client_response->fundtransferresponse->status->code) 
             && $client_response->fundtransferresponse->status->code == "200"){
                 $response = array(
                     "code" => 0,
                     "data" => array(
                         "currency" => $client_details->default_currency,
                         "applicableBonus" => 0.00,
                         "homeCurrency" => $client_details->default_currency,
                         "organization" => $this->org,
                         "balance" => floatval(number_format($client_response->fundtransferresponse->balance, 2, '.', '')),
                         "nickName" => $client_details->display_name,
                         "playerId" => "TGaming_".$client_details->player_id
                     ),
                 );
                 ProviderHelper::updatecreateGameTransExt($game_transextension, $request->all(), $response, $client_response->requestoclient, $client_response, $response);
                $save_bal = DB::table("player_session_tokens")->where("token_id","=",$tokenId)->update(["balance" => $client_response->fundtransferresponse->balance]);
                Helper::saveLog('Yggdrasil wager', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
                return $response;
            }elseif(isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "402"){
                $response = array(
                    "code" => 1006,
                    "msg" => "You do not have sufficient fundsfor the bet."
                );
                ProviderHelper::updateGameTransactionStatus($game_trans, 2, 6);
                ProviderHelper::updatecreateGameTransExt($game_transextension, $request->all(), $response, $client_response->requestoclient, $client_response, $response);
                Helper::saveLog("YGG wager response", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
                return $response;
            }else{
                $response = array(
                    "code" => 1006,
                    "msg" => "You do not have sufficient fundsfor the bet."
                );
                ProviderHelper::updateGameTransactionStatus($game_trans, 2, 99);
                ProviderHelper::updatecreateGameTransExt($game_transextension, $request->all(), $response, $client_response->requestoclient, $client_response, $response);
                Helper::saveLog("YGG wager response", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
                return $response;
            }
        }catch(\Exception $e){
            $msg = array(
                'error' => '1',
                'message' => $e->getMessage(),
            );
            $response = array(
                "code" => 1006,
                "msg" => "You do not have sufficient fundsfor the bet."
            );
            ProviderHelper::updatecreateGameTransExt($game_transextension, $request->all(), $response, $msg, $msg, $response);
            ProviderHelper::updateGameTransactionStatus($game_trans, 2, 99);
            Helper::saveLog('Yggdrasil wager error', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $msg);
            return json_encode($response, JSON_FORCE_OBJECT); 
        }

        
    }

    public function cancelwager(Request $request)
    {
        $playerId = ProviderHelper::explodeUsername('_',$request->playerid);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        Helper::saveLog("YGG cancelwager request", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), "");
        if($client_details == null){ 
            $response = array(
                "code" => 1000,
                "msg" => "Session expired. Please log in again."
            );
            Helper::saveLog("YGG cancelwager login", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return $response;
        }
        $provider_trans_id = $request->subreference;
        $round_id = $request->reference;
        $checkTrans = DB::select("SELECT game_trans_id,amount FROM game_transaction_ext WHERE provider_trans_id = '$request->subreference' AND round_id = '$request->reference' ");;
         # DB::table("games")->where("game_id","=",$checkTrans[0]->game_id)->first();
      
        

        
       


        if(count($checkTrans) > 0){
            $game_trans_ext_v2 = ProviderHelper::createGameTransExtV2( $checkTrans[0]->game_trans_id, $provider_trans_id, $round_id, $checkTrans[0]->amount, '3');
            $bet_amount = $checkTrans[0]->bet_amount;
            $game_details = DB::select("SELECT game_name, game_code FROM games WHERE game_id = '$checkTrans[0]->game_id' ");
            $gamecode = $game_details[0]->game_code;
            $game_name = $game_details[0]->game_name;
            $player_details = Providerhelper::playerDetailsCall($client_details->player_token);
            if($checkTrans[0]->win == 4){
                $response = array(
                    "code" => 0,
                    "data" => array(
                        "playerId" => "TGaming_".$client_details->player_id,
                        "organization" => $this->org,
                        "balance" => floatval(number_format($player_details->playerdetailsresponse->balance, 2, '.', '')),
                        "currency" => $client_details->default_currency,
                    )
                );
                Helper::saveLog('Yggdrasil cancelwager duplicate call', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
                return $response;
            }
            $client_response = ClientRequestHelper::fundTransfer($client_details, $bet_amount, $game_details[0]->game_code, $game_details[0]->game_name, $game_trans_ext_v2, $checkTrans[0]->game_trans_id, 'credit', 'true');
           
            $update = DB::table('game_transactions')
                        ->where('game_trans_id','=',$checkTrans[0]->game_trans_id)
                        ->update(["win" => 4, "entry_id" => 2, "transaction_reason" => "refund"]);
            $response = array(
                "code" => 0,
                "data" => array(
                    "playerId" => "TGaming_".$client_details->player_id,
                    "organization" => $this->org,
                    "balance" => floatval(number_format($client_response->fundtransferresponse->balance, 2, '.', '')),
                    "currency" => $client_details->default_currency
                )
            );
            $updateGameTransExt = DB::table('game_transaction_ext')->where('game_trans_ext_id','=',$game_trans_ext_v2)->update(["amount" => $bet_amount,"game_transaction_type" => '3',"provider_request" => json_encode($request->all(),JSON_FORCE_OBJECT),"mw_response" => json_encode($response),"mw_request" => json_encode($client_response->requestoclient),"client_response" => json_encode($client_response),"transaction_detail" => json_encode($response) ]);

            return $response;
        }else{
            $player_details = Providerhelper::playerDetailsCall($client_details->player_token);
            $response = array(
                "code" => 0,
                "data" => array(
                    "playerId" => "TGaming_".$client_details->player_id,
                    "organization" => $this->org,
                    "balance" => floatval(number_format($player_details->playerdetailsresponse->balance, 2, '.', '')),
                    "currency" => $client_details->default_currency
                )
            );
            Helper::saveLog('Yggdrasil cancelwager not exist', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return $response;
        }
    }

    public function appendwagerresult(Request $request)
    {
        
        Helper::saveLog('Yggdrasil appendwagerresult request', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), "" );
        $playerId = ProviderHelper::explodeUsername('_',$request->playerid);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);

        if($client_details == null){ 
            $response = array(
                "code" => 1000,
                "msg" => "Session expired. Please log in again."
            );
            Helper::saveLog("YGG appendwagerresult login", $this->provider_id,json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return $response;
        }
        $gamecode = '';
        $game_name = '';
        for ($x = 1; $x <= 9; $x++) {
            if($request['cat'.$x] != ''){
                $qry = "select * from games where provider_id = ".$this->provider_id." and game_code = '".$request['cat'.$x]."'" ;
                $game_details = DB::select($qry);
            }else{
                break;
            }
            if(count($game_details) > 0){
                $gamecode = $game_details[0]->game_code;
                $game_name = $game_details[0]->game_name;
            }
        } 
        $checkTrans = DB::select("SELECT game_trans_id FROM game_transactions WHERE provider_trans_id = '$request->subreference' AND round_id = '$request->reference' ");
        // $checkTrans = DB::table('game_transactions')->where('provider_trans_id','=',$request->reference)->where('round_id','=',$request->reference)->get();
        $player_details = Providerhelper::playerDetailsCall($client_details->player_token);
        $balance = $player_details->playerdetailsresponse->balance;
        $tokenId = $client_details->token_id;
        $bet_amount = $request->amount;
        $provider_trans_id = $request->subreference;
        $round_id = $request->reference;

        
       

        try{
            

            if(count($checkTrans) > 0){

                $response = array(
                    "code" => 0,
                    "data" => array(
                        "currency" => $client_details->default_currency,
                        "applicableBonus" => floatval(number_format($player_details->playerdetailsresponse->balance, 2, '.', '')),
                        "homeCurrency" => $client_details->default_currency,
                        "organization" => $this->org,
                        "balance" => floatval(number_format($player_details->playerdetailsresponse->balance, 2, '.', '')),
                        "nickName" => $client_details->display_name,
                        "playerId" => "TGaming_".$client_details->player_id,
                        "bonus" => 0
                    ),
                );
                Helper::saveLog("YGG appendwagerresult dubplicate", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
                return $response;
            }
            
            $gametrans = ProviderHelper::createGameTransaction($tokenId, $game_details[0]->game_id, $bet_amount, 0.00, 1, 0, null, null, $bet_amount, $provider_trans_id, $round_id);

            $game_trans_ext_v2 = ProviderHelper::createGameTransExtV2( $gametrans, $provider_trans_id, $round_id, $bet_amount, '1');


            $client_response = ClientRequestHelper::fundTransfer($client_details, $bet_amount, $game_details[0]->game_code, $game_details[0]->game_name, $game_trans_ext_v2, $gametrans, 'credit');

            $bonus = 'getbonusprize';
            Helper::saveLog('Yggdrasil appendwagerresult bonus', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $bonus );


            $response = array(
                "code" => 0,
                "data" => array(
                    "currency" => $client_details->default_currency,
                    "applicableBonus" =>  floatval(number_format($client_response->fundtransferresponse->balance, 2, '.', '')),
                    "homeCurrency" => $client_details->default_currency,
                    "organization" => $this->org,
                    "balance" =>  floatval(number_format($client_response->fundtransferresponse->balance, 2, '.', '')),
                    "nickName" => $client_details->display_name,
                    "playerId" => "TGaming_".$client_details->player_id,
                    "bonus" => 0
                ),
            );
            
            
            $updateGameTransExt = DB::table('game_transaction_ext')->where('game_trans_ext_id','=',$game_trans_ext_v2)->update(["amount" => $bet_amount,"game_transaction_type" => '2',"provider_request" => json_encode($request->all(),JSON_FORCE_OBJECT),"mw_response" => json_encode($response),"mw_request" => json_encode($client_response->requestoclient),"client_response" => json_encode($client_response),"transaction_detail" => json_encode($response) ]);

            Helper::saveLog('Yggdrasil appendwagerresult', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return $response;

        }catch(\Exception $e){
            $msg = array(
                'error' => '1',
                'message' => $e->getMessage(),
            );
            Helper::saveLog('Yggdrasil appendwagerresult error', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $msg);
            return json_encode($msg, JSON_FORCE_OBJECT); 
        }

    }

    public function endwager(Request $request)
    {
        $playerId = ProviderHelper::explodeUsername('_',$request->playerid);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        if($client_details == null){ 
            $response = array(
                "code" => 1000,
                "msg" => "Session expired. Please log in again."
            );
            Helper::saveLog("YGG endwager login", $this->provider_id,json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return $response;
        }

        // $checkTrans = DB::table('game_transaction_ext')->where('provider_trans_id','=',$request->reference)->where('round_id','=',$request->reference)->get();
        $checkTrans = DB::select("SELECT game_trans_ext_id, game_trans_id FROM game_transaction_ext WHERE provider_trans_id = '$request->subreference' AND round_id = '$request->reference' ");
        // $player_details = Providerhelper::playerDetailsCall($client_details->player_token);
        $game_details = Helper::findGameDetails('game_code', $this->provider_id, $request->cat5);

        $balance = $client_details->balance;
        $tokenId = $client_details->token_id;
        $win_amount = $request->amount;
        $provider_trans_id = $request->subreference;
        $round_id = $request->reference;
        $getTrans = DB::select("SELECT * FROM game_transactions WHERE round_id = '$round_id' ");
        // $getTrans = DB::table('game_transactions')->where('provider_trans_id','=',$provider_trans_id)->get();
        $income = $getTrans[0]->bet_amount - $win_amount;
        $entry_id = $win_amount > 0 ? 2 : 1;
        $win = $win_amount > 0 ? 1 : 0;
        
        if(count($checkTrans) > 0){
            $response = array(
                "code" => 0,
                "data" => array(
                    "currency" => $client_details->default_currency,
                    "applicableBonus" => 0.00,
                    "homeCurrency" => $client_details->default_currency,
                    "organization" => $this->org,
                    "balance" => floatval(number_format($client_details->balance, 2, '.', '')),
                    "nickName" => $client_details->display_name,
                    "playerId" => "TGaming_".$client_details->player_id,
                    "balik" => true
                ),
            );
            Helper::saveLog("YGG endwager(win) dubplicate", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return $response;
        }
        try{
            $balance = $client_details->balance + $win_amount;
            $response = array(
                "code" => 0,
                "data" => array(
                    "currency" => $client_details->default_currency,
                    "applicableBonus" => 0.00,
                    "homeCurrency" => $client_details->default_currency,
                    "organization" => $this->org,
                    "balance" => floatval(number_format($balance, 2, '.', '')),
                    "nickName" => $client_details->display_name,
                    "playerId" => "TGaming_".$client_details->player_id
                ),
            );
            $game_trans_ext_v2 = ProviderHelper::createGameTransExtV2( $getTrans[0]->game_trans_id, $provider_trans_id, $round_id, $win_amount, 2);
            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED! 
                "custom" => [
                    "game_trans_ext_id" => $game_trans_ext_v2,
                    "provider" => 'ygg',
                ],
                "provider" => [
                    "provider_request" => $request->all(),
                    "provider_trans_id"=>$provider_trans_id,
                    "provider_round_id"=>$round_id,
                ],
                "mwapi" => [
                    "roundId"=> $getTrans[0]->game_trans_id,
                    "type"=>2,
                    "game_id" => $game_details->game_id,
                    "player_id" => $client_details->player_id,
                    "mw_response" => $response,
                ]
            ];
            $update = DB::table('game_transactions')
                        ->where('game_trans_id','=',$getTrans[0]->game_trans_id)
                        ->update(["win" => $win, "pay_amount" => $win_amount, "entry_id" => $entry_id, "income" => $income ]);
            $client_response2 = ClientRequestHelper::fundTransfer_TG($client_details, $win_amount, $game_details->game_code, $game_details->game_name, $getTrans[0]->game_trans_id, 'credit', false, $action_payload);
            $updateGameTransExt = DB::table('game_transaction_ext')->where('game_trans_ext_id','=',$game_trans_ext_v2)->update(["amount" => $win_amount ,"game_transaction_type" => 2, "provider_request" => json_encode($request->all()),"mw_response" => json_encode($response),"mw_request" => json_encode($client_response2->requestoclient),"client_response" => json_encode($client_response2->fundtransferresponse),"transaction_detail" => "Credit" ]);
            $save_bal = DB::table("player_session_tokens")->where("token_id","=",$tokenId)->update(["balance" => $balance]);
            Helper::saveLog("YGG endwager (win)", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
            return $response;

        }catch(\Exception $e){
            $msg = array(
                'error' => '1',
                'message' => $e->getMessage(),
            );
            Helper::saveLog('Yggdrasil endwager error', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $msg);
            return json_encode($msg, JSON_FORCE_OBJECT); 
        }
    }

    public function campaignpayout(Request $request)
    {
        Helper::saveLog('Yggdrasil campaignpayout request', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), "");
        $playerId = ProviderHelper::explodeUsername('_',$request->playerid);
        $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
        $player_details = Providerhelper::playerDetailsCall($client_details->player_token);
        $response = array(
            "code" => 0,
            "data" => array(
                "currency" => $client_details->default_currency,
                "applicableBonus" => floatval(number_format($player_details->playerdetailsresponse->balance, 2, '.', '')),
                "homeCurrency" => $client_details->default_currency,
                "organization" => $this->org,
                "balance" => floatval(number_format($player_details->playerdetailsresponse->balance, 2, '.', '')),
                "nickName" => $client_details->display_name,
                "playerId" => "TGaming_".$client_details->player_id
            ),
        );
        Helper::saveLog("YGG campaignpayout response", $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), $response);
        return $response;
    }

    public function getbalance(Request $request)
    {
        Helper::saveLog('Yggdrasil getbalance request', $this->provider_id, json_encode($request->all(),JSON_FORCE_OBJECT), "");
        $client_details = ProviderHelper::getClientDetails('token',$request->sessiontoken);
        if($client_details == null){ 
            $response = array(
                "code" => 1000,
                "msg" => "Session expired. Please log in again."
            );
            return $response;
            Helper::saveLog("YGG playerinfo response", $this->provider_id,json_encode($request->all(),JSON_FORCE_OBJECT), $response);
        }
        $player_details = Providerhelper::playerDetailsCall($client_details->player_token);  
        $player_id = "TGaming_".$client_details->player_id;
        $balance = floatval(number_format($player_details->playerdetailsresponse->balance, 2, '.', ''));

        $response = array(
            "code" => 0,
            "data" => array(
                "currency" => $client_details->default_currency,
                "applicableBonus" => 0,
                "homeCurrency" => $client_details->default_currency,
                "organization" => $this->org,
                "balance" => $balance,
                "nickName" => $client_details->display_name,
                "playerId" => $player_id,
            )
        );
        Helper::saveLog("YGG getbalance response", $this->provider_id, json_encode($request->all()), $response);
        return $response; 
        
    }
    
    public function fundTransferRequest($client_access_token,$client_api_key,$game_code,$game_name,$client_player_id,$player_token,$amount,$fund_transfer_url,$transtype,$currency,$rollback=false){
        try {
            $client = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$client_access_token
                ]
            ]);
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
            // return $requesttosend;
            $guzzle_response = $client->post($fund_transfer_url,
                ['body' => json_encode($requesttosend)]
            );
            $client_response = json_decode($guzzle_response->getBody()->getContents());
            $data = [
                'requesttosend' => $requesttosend,
                'client_response' => $client_response,
            ];
            return $data;
            //
        } catch (\Exception $e) {
            Helper::saveLog('Called Failed!', $this->provider_db_id, json_encode($requesttosend), $e->getMessage());
            return 'false';
        }
    }

}
