<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\AlHelper;
use App\Helpers\Helper;
use App\Helpers\SAHelper;
use App\Helpers\ProviderHelper;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use Illuminate\Support\Facades\Artisan;
use App\Models\GameTransactionMDB;
use Illuminate\Support\Facades\Hash;
use App\Jobs\DebitRefund;
use App\Jobs\AlJobs;
use Session;
use Queue;
use Auth;
use DB;

/**
 *  DEBUGGING! CALLS! -RiAN ONLY! 10:21:51
 */
class AlController extends Controller
{

    public $hashen = '$2y$10$37VKbBiaJzWh7swxTpy6OOlldjjO9zdoSJSMvMM0.Xi2ToOv1LcSi';

    public function index(Request $request){
      // $token = Helper::tokenCheck('n58ec5e159f769ae0b7b3a0774fdbf80');
        $gg = DB::table('games as g')
            ->where('provider_id', $request->provider_id)
            ->where('sub_provider_id', $request->subprovider)
            ->get();

        $array = array();  
        foreach($gg as $g){
            DB::table('games')
                   ->where('provider_id',$request->provider_id)
                   ->where('sub_provider_id',$request->subprovider)
                   ->where('game_id', $g->game_id)
                   ->update(['icon' => 'https://asset-dev.betrnk.games/images/games/casino/'.$request->prefix.'/'.$g->game_code.'.'.$request->extension.'']);
                   // ->update(['icon' => 'https://asset-dev.betrnk.games/images/casino/'.$request->prefix.'/eng/388x218/'.$g->game_code.'.jpg']);
                    
        }     
        return 'ok';    
    }


    public function massResend(Request $request){
        if(!$request->header('hashen')){
          return ['al' => 'OOPS RAINDROPS'];
        }
        if(!Hash::check($request->header('hashen'),$this->hashen)){
          return ['al' => 'OOPS RAINDROPS'];
        }

        $client_response_data = [];
        $request_data = json_decode(json_encode($request->all()));

        $al = 1;
        foreach($request_data as $request){
          $client_details = Providerhelper::getClientDetails('player_id',  $request->player_id);
          if($client_details == 'false'){
            $response = ['status'=>'failed', ['msg'=>'Player Not Found']];
             $client_response_data[$al++-1] = $response;
             continue;
          }
          $player_details = Providerhelper::playerDetailsCall($client_details->player_token);
          if($player_details == 'false'){
             $response = ["status" => "failed", "msg" =>  'Server Timeout PlayerDetails END Failed'];
             $client_response_data[$al++-1] = $response;
             continue;
          }

          $round_id = ProviderHelper::findGameTransaction($request->round_id, 'game_transaction');
          $provider_trans_id = $round_id->provider_trans_id;
          $provider_round_id = $round_id->round_id;
  
          $general_details['client']['before_balance'] = ProviderHelper::amountToFloat($player_details->playerdetailsresponse->balance);
  
          $amount = $request->amount;
          // $bet_amount = $request->has('bet_amount') ? $request->bet_amount : $round_id->bet_amount;
          $bet_amount = $round_id->bet_amount;
  
          $win_type = $request->win_type;  // 0 Lost, 1 win, 2 failed, 3 draw, 4 refund, 5 processing
          $entry_type = $win_type == 1 ? 2 : 1;
          $game_ext_type = $request->game_ext_type; // 1 ber, 2 win, 3 refund
  
          $transaction_type = $request->transaction_type;
          // $rollback = $request->has('rollback') ? $request->rollback : false;
          $rollback = false;
  
          $game_information = DB::table('games')->where('game_id', $round_id->game_id)->first();
          if($game_information == null){ 
              $response = ["status" => "failed", "msg" =>  'game not found'];
              $client_response_data[$al++-1] = $response;
              continue;
          }
  
          $pay_amount = $round_id->pay_amount + $amount;
          $income = $bet_amount - $pay_amount;
          $identifier_type = 'game_trans_id';
          $update_bet = false;
          $update_bet_amount = $round_id->bet_amount;
        
          if($win_type == 4){
            if(!$request->has('rollback') || !$request->has('rollback_type')){
              $response = ["status" => "failed", "msg" =>  'When type 4 it should have rollback parameter and rollback type parameter [round,bet,win])'];
              $client_response_data[$al++-1] = $response;
              continue;
            }
            if($request->rollback == 'false' || $request->rollback == false){
              $response = ["status" => "failed", "msg" =>  'rollback parameter must be true'];
              $client_response_data[$al++-1] = $response;
              continue;
            }
            if($request->game_ext_type != 3){
              $response = ["status" => "failed", "msg" =>  'Game Extension type should be 3'];
              $client_response_data[$al++-1] = $response;
              continue;
            }
            if($request->rollback_type == 'bet'){
              if($transaction_type == 'debit'){
                 $response = ["status" => "failed", "msg" =>  'refunding bet should be credit transaction type'];
                 $client_response_data[$al++-1] = $response;
                 continue;
              }
              // $update_bet = true;
              // $update_bet_amount = $update_bet_amount - $amount;
              $pay_amount = $round_id->pay_amount + $amount;
              $income = $update_bet_amount - $pay_amount ;
            }
            if($request->rollback_type == 'win'){
              if($transaction_type == 'credit'){
                 $response = ["status" => "failed", "msg" => "refunding win should be debit transaction type"];
                 $client_response_data[$al++-1] = $response;
                 continue;
              }
              $pay_amount = $round_id->pay_amount - $amount;
              $income = $update_bet_amount - $pay_amount ;
            }
            if($request->rollback_type == 'custom'){
                // $bet = $request->custom_bet;
                $pay_amount = $request->custom_pay_amount;
                $income = $request->custom_income;
                $win_type = $request->custom_win_type;
                $entry_type = $request->custom_entry_type;
            }
          }
  
          $game_transextension = ProviderHelper::createGameTransExtV2($round_id->game_trans_id,$provider_trans_id, $provider_round_id, $amount, $game_ext_type);
  
          try {
            $client_response = ClientRequestHelper::fundTransfer($client_details,abs($amount),$game_information->game_code,$game_information->game_name,$game_transextension, $round_id->game_trans_id, $transaction_type, $rollback);
            // Helper::saveLog('RESEND CRID '.$round_id->game_trans_id, 999,json_encode($request->all()), $client_response);
          } catch (\Exception $e) {
            $response = ["status" => "failed", "msg" => $e->getMessage()];
            ProviderHelper::updatecreateGameTransExt($game_transextension, 'FAILED', $response, 'FAILED', $e->getMessage(), 'FAILED', $general_details);
            // Helper::saveLog('RESEND - FATAL ERROR', 999, $response, Helper::datesent());
            $client_response_data[$al++-1] = $response;
            continue;
          }
  
          if(isset($client_response->fundtransferresponse->status->code) 
              && $client_response->fundtransferresponse->status->code == "200"){
              $general_details['client']['after_balance'] = ProviderHelper::amountToFloat($client_response->fundtransferresponse->balance);
              $response = ["status" => "success", "msg" => 'transaction success', 'general_details' => $general_details,'data' => $client_response];      
  
             ProviderHelper::updateGameTransaction($round_id->game_trans_id, $pay_amount, $income, $win_type, $entry_type,$identifier_type,$update_bet_amount,$update_bet);
             ProviderHelper::updatecreateGameTransExt($game_transextension, $request, $response, $client_response->requestoclient, $client_response, 'SUCCESS',$general_details);
             $client_response_data[$al++-1] = $response;
             continue;
            }elseif(isset($client_response->fundtransferresponse->status->code) 
              && $client_response->fundtransferresponse->status->code == "402"){
              $response = ["status" => "failed", "msg" => 'transaction failed', 'general_details' => $general_details, "data" => $client_response];
              ProviderHelper::updatecreateGameTransExt($game_transextension, $request, $response, $client_response->requestoclient, $client_response, 'FAILED',$general_details);
              $client_response_data[$al++-1] = $response;
              continue;
            }else{ // Unknown Response Code
              $response = ["status" => "failed", "msg" => 'Unknown Status Code', 'general_details' => $general_details, "data" => $client_response];
              ProviderHelper::updatecreateGameTransExt($game_transextension, $request, $response, $client_response->requestoclient, $client_response, 'FAILED',$general_details);
              $client_response_data[$al++-1] = $response;
              continue;
          }  
  
          Helper::saveLog('RESEND TRIGGERED', 999, json_encode($response), Helper::datesent());
          
        }

        return json_encode($client_response_data);
    }


    public function checkCLientPlayer(Request $request){
        if(!$request->header('hashen')){
          return ['al' => 'OOPS RAINDROPS'];
        }
        if(!Hash::check($request->header('hashen'),$this->hashen)){
          return ['al' => 'OOPS RAINDROPS'];
        }
        if($request->debugtype == 1){
          $client_details = Providerhelper::getClientDetails($request->type,  $request->identifier);
          if($client_details == 'false'){
            return ['al' => 'NO PLAYER FOUND'];
          }else{
            $client = new Client([
                'headers' => [ 
                  'Content-Type' => 'application/json',
                  'Authorization' => 'Bearer '.$client_details->client_access_token
                ]
            ]);
            $datatosend = ["access_token" => $client_details->client_access_token,
              "hashkey" => md5($client_details->client_api_key.$client_details->client_access_token),
              "type" => "playerdetailsrequest",
              "datesent" => Helper::datesent(),
              "clientid" => $client_details->client_id,
              "playerdetailsrequest" => [
                "player_username"=>$client_details->username,
                "client_player_id" => $client_details->client_player_id,
                "token" => $client_details->player_token,
                "gamelaunch" => true,
                "refreshtoken" => $request->has('refreshtoken') ? true : false,
              ]
            ];
            try{  
              $guzzle_response = $client->post($client_details->player_details_url,
                  ['body' => json_encode($datatosend)]
              );
              $client_response = json_decode($guzzle_response->getBody()->getContents());
              $client_response->request_body = $datatosend;
              return json_encode($client_response);
            }catch (\Exception $e){
               $message = [
                'request_body' => $datatosend,
                'al' => $e->getMessage(),
               ];
               return $message;
            } 
          }
        }elseif($request->debugtype == 2){
            $gg = DB::table('seamless_request_logs');
            if ($request->type == 'provider_id') {
              $gg->where([
                ["provider_id", "=", $request->identifier],
              ]);
            } 
            if ($request->type == 'method_name') {
              $gg->where([
                ["method_name", "LIKE", "%$request->identifier%"],
              ]);
            } 
            $result = $gg->limit($request->has('limit') ? $request->limit : 1);
            $result = $gg->latest()->get(); // Added Latest (CQ9) 08-12-20 - Al
            return $result ? $result : 'false';
        }elseif($request->debugtype == 3){
            $gg = DB::table('game_transaction_ext');
            if ($request->type == 'game_trans_id') {
              $gg->where([
                ["game_trans_id", "=", $request->identifier],
              ]);
            } 
            if ($request->type == 'game_trans_ext_id') {
              $gg->where([
                ["game_trans_ext_id", "=", $request->identifier],
              ]);
            } 
            if ($request->type == 'round_id') {
              $gg->where([
                ["round_id", "=", $request->identifier],
              ]);
            } 
            if ($request->type == 'provider_trans_id') {
              $gg->where([
                ["provider_trans_id", "=", $request->identifier],
              ]);
            } 
            $result = $gg->limit($request->has('limit') ? $request->limit : 1);
            $result = $gg->latest()->get(); // Added Latest (CQ9) 08-12-20 - Al
            return $result ? $result : 'false';
        }elseif($request->debugtype == 4){
            $query = DB::select(DB::raw($request->input("query")));
            return $query;
        }elseif($request->debugtype == 5){
            $client_details = Providerhelper::getClientDetails($request->type,  $request->identifier);
            return $this->checkTransaction($client_details,$request->roundId,$request->transactionId);
        }

    }


    public function resendTransaction(Request $request){
        $general_details = ["aggregator" => [], "provider" => [], "client" => []];
        $data = $request->all();
        
        if(!$request->header('hashen')){
          return ['status'=>'failed', ['msg'=>'ACCESS DENIED']];
        }
        if(!Hash::check($request->header('hashen'),$this->hashen)){
          return ['status'=>'failed', ['msg'=>'ACCESS DENIED']];
        }
       
        if(!$request->has('round_id') || !$request->has('player_id') || !$request->has('win_type') || !$request->has('game_ext_type') || !$request->has('transaction_type') || !$request->has('amount')){
          return ['status'=>'failed', ['msg'=>'Missing Required Parameters']];
        }

        $client_details_time =  microtime(true);
        $client_details = Providerhelper::getClientDetails('player_id',  $request->player_id);
        if($client_details == 'false'){
           return ['status'=>'failed', ['msg'=>'Player Not Found']];
        }
        $client_details_process_time = microtime(true) - $client_details_time;

        $player_details = Providerhelper::playerDetailsCall($client_details->player_token);
        if($player_details == 'false'){
           return  $response = ["status" => "failed", "msg" =>  'Server Timeout'];
        }

        $round_id = ProviderHelper::findGameTransaction($request->round_id, 'game_transaction');
        $provider_trans_id = $round_id->provider_trans_id;
        $provider_round_id = $round_id->round_id;

        $general_details['client']['before_balance'] = ProviderHelper::amountToFloat($player_details->playerdetailsresponse->balance);

        $amount = $request->amount;
        $bet_amount = $request->has('bet_amount') ? $request->bet_amount : $round_id->bet_amount;

        $win_type = $request->win_type;  // 0 Lost, 1 win, 2 failed, 3 draw, 4 refund, 5 processing
        $entry_type = $win_type == 1 ? 2 : 1;
        $game_ext_type = $request->game_ext_type; // 1 ber, 2 win, 3 refund

        $transaction_type = $request->has('transaction_type') ? $request->transaction_type : 'credit';
        $rollback = $request->has('rollback') ? $request->rollback : false;

        $game_information = DB::table('games')->where('game_id', $round_id->game_id)->first();
        if($game_information == null){ 
            return  $response = ["status" => "failed", "msg" =>  'game not found'];
        }

        $pay_amount = $round_id->pay_amount + $amount;
        $income = $bet_amount - $pay_amount;
        $identifier_type = 'game_trans_id';
        $update_bet = false;
        $update_bet_amount = $round_id->bet_amount;
      
        if($win_type == 4){
          if(!$request->has('rollback') || !$request->has('rollback_type')){
            return  $response = ["status" => "failed", "msg" =>  'When type 4 it should have rollback parameter and rollback type parameter [round,bet,win])'];
          }
          if($request->rollback == 'false' || $request->rollback == false){
            return  $response = ["status" => "failed", "msg" =>  'rollback parameter must be true'];
          }
          if($request->game_ext_type != 3){
            return  $response = ["status" => "failed", "msg" =>  'Game Extension type should be 3'];
          }
          if($request->rollback_type == 'bet'){
            if($transaction_type == 'debit'){
               return  $response = ["status" => "failed", "msg" =>  'refunding bet should be credit transaction type'];
            }
            // $update_bet = true;
            // $update_bet_amount = $update_bet_amount - $amount;
            $pay_amount = $round_id->pay_amount + $amount;
            $income = $update_bet_amount - $pay_amount ;
          }
          if($request->rollback_type == 'win'){
            if($transaction_type == 'credit'){
               return  $response = ["status" => "failed", "msg" => "refunding win should be debit transaction type"];
            }
            $pay_amount = $round_id->pay_amount - $amount;
            $income = $update_bet_amount - $pay_amount ;
          }
          if($request->rollback_type == 'custom'){
              // $bet = $request->custom_bet;
              $pay_amount = $request->custom_pay_amount;
              $income = $request->custom_income;
              $win_type = $request->custom_win_type;
              $entry_type = $request->custom_entry_type;
          }
        }

        $game_transextension = ProviderHelper::createGameTransExtV2($round_id->game_trans_id,$provider_trans_id, $provider_round_id, $amount, $game_ext_type);

        try {
          $client_response = ClientRequestHelper::fundTransfer($client_details,abs($amount),$game_information->game_code,$game_information->game_name,$game_transextension, $round_id->game_trans_id, $transaction_type, $rollback);
          Helper::saveLog('RESEND CRID '.$round_id->game_trans_id, 999,json_encode($request->all()), $client_response);
        } catch (\Exception $e) {
          $response = ["status" => "failed", "msg" => $e->getMessage()];
          ProviderHelper::updatecreateGameTransExt($game_transextension, 'FAILED', $response, 'FAILED', $e->getMessage(), 'FAILED', $general_details);
          Helper::saveLog('RESEND - FATAL ERROR', 999, $response, Helper::datesent());
          return $response;
        }

        if(isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "200"){
            $general_details['client']['after_balance'] = ProviderHelper::amountToFloat($client_response->fundtransferresponse->balance);
            $response = ["status" => "success", "msg" => 'transaction success', 'general_details' => $general_details,'data' => $client_response];      

           ProviderHelper::updateGameTransaction($round_id->game_trans_id, $pay_amount, $income, $win_type, $entry_type,$identifier_type,$update_bet_amount,$update_bet);
           ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $response, $client_response->requestoclient, $client_response, 'SUCCESS',$general_details);
        }elseif(isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "402"){
            $response = ["status" => "failed", "msg" => 'transaction failed', 'general_details' => $general_details, "data" => $client_response];
            ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $response, $client_response->requestoclient, $client_response, 'FAILED',$general_details);
        }else{ // Unknown Response Code
            $response = ["status" => "failed", "msg" => 'Unknown Status Code', 'general_details' => $general_details, "data" => $client_response];
            ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $response, $client_response->requestoclient, $client_response, 'FAILED',$general_details);
        }  

        Helper::saveLog('RESEND TRIGGERED', 999, json_encode($response), Helper::datesent());
        return $response;
    }

    public function queryData($query){
        $query = DB::select(DB::raw($query));
        return json_encode($query);
    }

    public function checkTransaction($client_details,$roundId,$transactionId){
          $client = new Client([
              'headers' => [ 
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$client_details->client_access_token
              ]
          ]);
          $datatosend = [
            "access_token" => $client_details->client_access_token,
            "hashkey" => md5($client_details->client_api_key.$client_details->client_access_token),
            "player_username"=>$client_details->username,
            "client_player_id" => $client_details->client_player_id,
            "transactionId" => $transactionId,
            "roundId" =>  $roundId
          ];
          try{  
            $guzzle_response = $client->post($client_details->transaction_checker_url,
                ['body' => json_encode($datatosend)]
            );
            $client_response = json_decode($guzzle_response->getBody()->getContents());
            $client_response->request_body = $datatosend;
            return json_encode($client_response);
          }catch (\Exception $e){
             $message = [
              'request_body' => $datatosend,
              'al' => $e->getMessage(),
             ];
             return $message;
          } 
    }


    public function tapulan(Request $request){


      $debitRefund = ["payload" => "AWIT"];
      Queue::push(new DebitRefund($debitRefund));


      $job = (new AlJobs($debitRefund))->onQueue('al');
      $this->dispatch($job);
      dd( $job);

      $client_details = Providerhelper::getClientDetails('player_id', 10210);
      // GameTransactionMDB::createGametransactionLog(123,'genera_details', json_encode($request->all()),$client_details);

    
      $data = [
        'logs' => json_encode(["GG"=>"OH YEAH"])
      ];  
      GameTransactionMDB::updateGametransactionLog($data, 123, "general_details",$client_details);

      $gg = GameTransactionMDB::findGameTransactionLogs(123,'general_details',$client_details);
      dd($gg);
      // $client_details = Providerhelper::getClientDetails('player_id',  98);
      // $player= DB::table('players')->where('client_id', $client_details->client_id)
      //     ->where('player_id', $client_details->player_id)->first();
      // if(isset($player->player_status)){
      //   if($player != '' || $player != null){
      //     if($player->player_status == 2|| $player->player_status == 3){
      //      return 'false';
      //     }
      //   }
      // }


      // $hashed_password = password_hash('kirill', PASSWORD_DEFAULT);
      // dd($hashed_password);

      $gameDetails = ProviderHelper::findGameDetails('game_code', 1, 1);
      dd($gameDetails);
      $client_details = Providerhelper::getClientDetails('player_id', 10210);



      

      $updateTransactionEXt = array(
            // "provider_request" =>json_encode($payload),
            "mw_response" => json_encode(['retry' => 'jobs']),
            'mw_request' => json_encode(['retry' => 'jobs']),
            'client_response' => json_encode(['retry' => 'jobs']),
            'transaction_detail' => 'SUCCESS',
            'general_details' => DB::raw('IFNULL(general_details, 0) + 1')
      );
      GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,21021376,$client_details);

      // return 1;

      if(isset($_SERVER["HTTP_X_FORWARDED_FOR"])){
        if($_SERVER["HTTP_X_FORWARDED_FOR"] == '119.92.151.236'){
          $msg = 'your whilisted';
        }else{
          $msg = 'your blocked';
        }
      }else{
        $msg = 'not set';
      }



      // return $this->getUserIpAddr();
      // Helper::saveLog('IP LOG', 999, json_encode($request->ip()), $_SERVER["REMOTE_ADDR"].' '.$request->ip().' '.$this->getUserIpAddr());

      //  if(isset($_SERVER['HTTP_CLIENT_IP'])):
      //       Helper::saveLog('IP LOG2', 999, json_encode($request->ip()), $_SERVER["HTTP_CLIENT_IP"]);
      //  elseif(isset($_SERVER['HTTP_X_FORWARDED_FOR'])):
      //      Helper::saveLog('IP LOG3', 999, json_encode($request->ip()), $_SERVER["HTTP_X_FORWARDED_FOR"]);
      //  elseif(isset($_SERVER['HTTP_X_FORWARDED'])):
      //      Helper::saveLog('IP LOG4', 999, json_encode($request->ip()), $_SERVER["HTTP_X_FORWARDED"]);
      //  elseif(isset($_SERVER['HTTP_FORWARDED_FOR'])):
      //      Helper::saveLog('IP LOG5', 999, json_encode($request->ip()), $_SERVER["HTTP_FORWARDED_FOR"]);
      //  elseif(isset($_SERVER['HTTP_FORWARDED'])):
      //       Helper::saveLog('IP LOG6', 999, json_encode($request->ip()), $_SERVER["HTTP_FORWARDED"]);
      //  elseif(isset($_SERVER['REMOTE_ADDR'])):
      //       Helper::saveLog('IP LOG7', 999, json_encode($request->ip()), $_SERVER["REMOTE_ADDR"]);
      //  else:

      //  endif;

      return  $msg;
      
      // $client_details = Providerhelper::getClientDetails('player_id',  98);
      // dd($client_details);

      // return response()
      //       ->json(['name' => 'Abigail', 'state' => 'CA'])
      //       ->Artisan::call('al:riandraft');
      //       // ->withCallback(Artisan::call('al:riandraft'));

      // Artisan::call('al:riandraft');

      return $this->callMe();
      // return self::callMe();
    }


    private static function callMe(){
      return 'HAHAHAHHAaaaaaaaaaaaaaa';
    }

    public function testTransaction(){
      return ClientRequestHelper::getTransactionId("43210","87654321");
    }


    public function debugMe(Request $request){

        $client_details = ProviderHelper::getClientDetails('player_id',$request->player_id);
        $requesttocient = $request->getContent();
        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$client_details->client_access_token
            ]
        ]);

        try {
             $guzzle_response = $client->post($client_details->fund_transfer_url,
                [
                    'body' => $requesttocient
                ],
                ['defaults' => [ 'exceptions' => false ]]
            );
             $client_reponse = json_decode($guzzle_response->getBody()->getContents());
             return json_encode($client_reponse);
        } catch (\Exception $e) {
              $response = array(
                    "fundtransferresponse" => array(
                        "status" => array(
                            "code" => 402,
                            "status" => "Exception",
                            "message" => $e->getMessage().' '.$e->getLine(),
                        ),
                        'balance' => 0.0
                    )
              );
              return $response;
        }


        // SAGAMING
        // $client_details = Providerhelper::getClientDetails('player_id', 98);
        // $time = date('YmdHms'); //20140101123456
        // $method = 'VerifyUsername';
        // $querystring = [
        //     "method" => $method,
        //     "Key" => config('providerlinks.sagaming.SecretKey'),
        //     "Time" => $time,
        //     "Username" => config('providerlinks.sagaming.prefix').$client_details->player_id,
        // ];
        // $method == 'RegUserInfo' || $method == 'LoginRequest' ? $querystring['CurrencyType'] = $client_details->default_currency : '';
        // $data = http_build_query($querystring); // QS
        // $encrpyted_data = SAHelper::encrypt($data);
        // $md5Signature = md5($data.config('providerlinks.sagaming.MD5Key').$time.config('providerlinks.sagaming.SecretKey'));
        // $http = new Client();
        // $response = $http->post(config('providerlinks.sagaming.API_URL'), [
        //     'form_params' => [
        //         'q' => $encrpyted_data, 
        //         's' => $md5Signature
        //     ],
        // ]);
        // Helper::saveLog('ALDEBUG '.$method, config('providerlinks.sagaming.pdbid'), json_encode(['for_params' => ['q'=>$encrpyted_data, 's'=>$md5Signature]]), $querystring);
        // return $response->getBody()->getContents();
    }

    public function currency(){
      return ClientRequestHelper::currencyRateConverter("USD",12829967);
    }



}
