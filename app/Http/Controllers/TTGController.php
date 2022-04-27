<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransaction;
use App\Models\GameTransactionMDB;
use App\Helpers\Game;
use Carbon\Carbon;
use DB;
// use DOMDocument;
// use SimpleXMLElement;

class TTGController extends Controller
{ 
  public function __construct(){
        $this->provider_db_id = config('providerlinks.toptrendgaming.provider_db_id');
    }

   

    public function testing(Request $request){
      //converting xml to json
      $fileContents = file_get_contents("php://input");
      // dd($fileContents);
      $json = json_encode(simplexml_load_string($fileContents));
    $array = json_decode($json,true);
    //getting value
    // dd($array);
    $val = $array;

    //response 
    $response = '<cw type="fundTransferResp" cur="EUR" amt="100.00" err="0" />';
    return response($response,200) 
        ->header('Content-Type', 'application/xml');
        // ->header('Content-Type', 'text/xml');
    
    }

    public function getBalance(Request $request){

      Helper::saveLog("TTG get Bal", $this->provider_db_id, json_encode($request->all()), "ENDPOINT HIT"); 
      $fileContents = file_get_contents("php://input");
      $json = json_encode(simplexml_load_string($fileContents));
      $array = json_decode($json,true);
      $user_id = explode('TGR_',$array['@attributes']['acctid']);
      $get_client_details = ProviderHelper::getClientDetails("player_id",$user_id[1]);
      if($get_client_details == null || $get_client_details->player_id != $user_id[1]){

          $response = '<cw type="getBalanceResp" err="1000" />';

         return response($response,200) 
        ->header('Content-Type', 'application/xml');

      }
      if($get_client_details->default_currency != $array['@attributes']['cur']){

        $response = '<cw type="getBalanceResp" err="1001" />';

         return response($response,200) 
        ->header('Content-Type', 'application/xml');
      }
      
      $formatBalance = number_format($get_client_details->balance,2,'.','');
      header("Content-type: application/xml; charset=UTF-8");
     
      $response = '<cw type="getBalanceResp" cur="'.$get_client_details->default_currency.'" amt="'.$formatBalance.'" err="0" />';
    
       return response($response,200) 
       ->header('Content-Type', 'application/xml');
      
    }

    public function fundTransferTTG(Request $request){
      $fileContents = file_get_contents("php://input");
      // $xml = new \SimpleXMLElement($fileContents);
      // $array = (array)$xml;
      $json = json_encode(simplexml_load_string($fileContents));
      $array = json_decode($json,true);
      $acctid = $array['@attributes']['acctid'];
      $data = $array['@attributes'];
      $user_id = explode('TGR_',$acctid);
      $get_client_details = ProviderHelper::getClientDetails("player_id",$user_id[1]);
     
        try{
                ProviderHelper::idenpotencyTable($array['@attributes']['txnid']);
        }catch(\Exception $e){

                header("Content-type: application/xml; charset=utf-8");
                $response = '<?xml version="1.0" encoding="utf-8"?>';
                $response .= '<cw type="fundTransferResp" err="9999" />';
                return response($response,200) 
                  ->header('Content-Type', 'application/xml');
               
        }
        if($get_client_details == null){

            $response = '<cw type="fundTransferResp" err="1000" />';

            return response($response,200) 
           ->header('Content-Type', 'application/xml');


        }
        $game_details = Helper::findGameDetails("game_code",$this->provider_db_id, $data['gameid']);
       
            if($data['txnsubtypeid'] == '400'){//bet start
                return $this->debitProcess($data,$get_client_details);
            }//end bet call

            if($data['txnsubtypeid'] == '410'){//win call start
                return $this->creditProcess($data,$get_client_details);
            }//end win call

            if($data['txnsubtypeid'] == '150'){
              $provider_trans_id = $array['@attributes']['txnid'];
              $bonus_amt = $data['amt'];
              $new_balance = $get_client_details->balance + $bonus_amt;
              $bet_amount = 0;
              $income = $bet_amount - $bonus_amt;
              $game_id = 0;
              $round_id = 0;

              $entry_id = $bonus_amt > 0 ?  2 : 1;
              $payout_reason = "Bonus";
              ProviderHelper::_insertOrUpdate($get_client_details->token_id, $new_balance); 

              $response = '<?xml version="1.0" encoding="utf-8"?>';
              $response .= '<cw type="fundTransferResp" cur="'.$get_client_details->default_currency.'" amt="'.$new_balance.'" err="0" />';

              $gameTransactionData = array(
                  "provider_trans_id" => $provider_trans_id,
                  "token_id" => $get_client_details->token_id,
                  "game_id" => $game_id,
                  "round_id" => $round_id,
                  "bet_amount" => $bet_amount,
                  "win" => 2,
                  "pay_amount" => $bonus_amt,
                  "income" => $income,
                  "entry_id" =>1,
                  "operator_id" => $get_client_details->operator_id,
                  "client_id" => $get_client_details->client_id,
                  "player_id" => $get_client_details->player_id,
              );
              $game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionData, $get_client_details);

              // $game_trans_id  = ProviderHelper::createGameTransaction($get_client_details->token_id, $game_id, $bet_amount,  $bonus_amt, $entry_id, 1 , null, $payout_reason, $income, $provider_trans_id, 0);

              $gameTransactionEXTData = array(
                        "game_trans_id" => $game_trans_id,
                        "provider_trans_id" => $provider_trans_id,
                        "round_id" => $round_id,
                        "amount" => $bonus_amt,
                        "game_transaction_type"=> 1,
                        "provider_request" =>json_encode($request->all()),
                        "mw_response" => json_encode($response),
                    );
              $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$get_client_details);

              // $game_trans_ext_id = $this->createGameTransExt($game_trans_id,$provider_trans_id, $round_id, $bonus_amt, 1, $data, $data_response = null, $requesttosend = null, $client_response = null, $data_response = null);

              $client_response = ClientRequestHelper::fundTransfer($get_client_details, $bonus_amt, null, null, $game_trans_ext_id, $game_trans_id, 'credit');
          
                        if (isset($client_response->fundtransferresponse->status->code)) {
                            $response = '<?xml version="1.0" encoding="utf-8"?>';
                            ProviderHelper::_insertOrUpdate($get_client_details->token_id, $client_response->fundtransferresponse->balance);
                            switch ($client_response->fundtransferresponse->status->code) {
                                case '200':
                                    $response .= '<cw type="fundTransferResp" cur="'.$get_client_details->default_currency.'" amt="'.$client_response->fundtransferresponse->balance.'" err="0" />';
                                  
                                    break;
                                case '402':
                                    $response .= '<cw type="fundTransferResp" err="9999" />';
                                    break;
                            }
                            
                            $updateTransactionEXt = array(
                                "provider_request" =>json_encode($request->all()),
                                "mw_response" => json_encode($response),
                                'mw_request' => json_encode($client_response->requestoclient),
                                'client_response' => json_encode($client_response->fundtransferresponse),
                                'transaction_detail' => 'THIS IS BONUS!',
                                'general_details' => 'success',
                            );
                            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$get_client_details);
                            // ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, $data, $response, $client_response->requestoclient, $client_response, $data);

                        }
              
              Helper::saveLog('TTGaming Bonus', $this->provider_db_id, $array, $response);
              return response($response,200) 
                ->header('Content-Type', 'application/xml');
            }

    }

    public function debitProcess($data,$get_client_details){
        try {
                 $provider_trans_id = $data['txnid'];
                 $game_code = $game_details->game_code;
                 $amount = $data["amt"]; 
                 $bet_amount = abs($amount);
                 $pay_amount = 0;
                 $income = abs($bet_amount) - $pay_amount;
                 // $win_or_lost = $data["win"] == 0.0 ? 0 : 1;  /// 1win 0lost
                 $entry_id = 1 == 0.0 ? 1 : 2;// 1/bet/debit , 2//win/credit
                 // $provider_trans_id = $data['id_stat']; // 
                 $round_id = $data['handid'];// this is round
                 $gen_game_trans_id = ProviderHelper::idGenerate($get_client_details->connection_name,1);
                 $gen_game_extid = ProviderHelper::idGenerate($get_client_details->connection_name,2);
                try {
                    $client_response = ClientRequestHelper::fundTransfer($get_client_details, $bet_amount, $game_details->game_code, $game_details->game_name, $gen_game_extid, $gen_game_trans_id, 'debit');
                } catch (\Exception $e) {
                    return $e->getMessage().' '.$e->getLine().' '.$e->getFile(); 
                }
                            if (isset($client_response->fundtransferresponse->status->code)) {
                            $fundT_bal = number_format($client_response->fundtransferresponse->balance,2,'.','');
                                $response = '<?xml version="1.0" encoding="utf-8"?>';
                                ProviderHelper::_insertOrUpdate($get_client_details->token_id, $client_response->fundtransferresponse->balance);
                                switch ($client_response->fundtransferresponse->status->code) {
                                    case '200':
                                        $response .= '<cw type="fundTransferResp" cur="'.$get_client_details->default_currency.'" amt="'.$fundT_bal.'" err="0" />';
                                    break;
                                    case '402':
                                        $updateGameTransaction = [
                                                    'win' => 2,
                                                ];
                                        GameTransactionMDB::updateGametransaction($updateGameTransaction, $gen_game_trans_id, $get_client_details);
                                        $response .= '<cw type="fundTransferResp" err="9999" />';
                                        $createGameTransactionLog = [
                                          "connection_name" => $get_client_details->connection_name,
                                          "column" =>[
                                              "game_trans_ext_id" => $gen_game_extid,
                                              "request" => json_encode($request->all()),
                                              "response" => json_encode($response),
                                              "log_type" => "provider_details",
                                              "transaction_detail" => "FAILED",
                                          ]
                                        ];
                                        ProviderHelper::queTransactionLogs($createGameTransactionLog);
                                    break;
                                    default:
                                        $updateGameTransaction = [
                                                    'win' => 2,
                                                ];
                                        GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $get_client_details);
                                        $response .= '<cw type="fundTransferResp" err="9999" />';
                                        $createGameTransactionLog = [
                                          "connection_name" => $get_client_details->connection_name,
                                          "column" =>[
                                              "game_trans_ext_id" => $gen_game_extid,
                                              "request" => json_encode($request->all()),
                                              "response" => json_encode($response),
                                              "log_type" => "provider_details",
                                              "transaction_detail" => "FAILED",
                                          ]
                                        ];
                                        ProviderHelper::queTransactionLogs($createGameTransactionLog);
                                }// end switch
                            
                            }
                            $checkTransaction = GameTransactionMDB::findGameTransactionDetails($data['handid'],'round_id', 1, $get_client_details);
                             if($checkTransaction == 'false'){
                                  $gameTransactionData = array(
                                        "provider_trans_id" => $provider_trans_id,
                                        "token_id" => $get_client_details->token_id,
                                        "game_id" => $game_details->game_id,
                                        "round_id" => $round_id,
                                        "bet_amount" => $bet_amount,
                                        "win" => 5,
                                        "pay_amount" => 0,
                                        "income" => 0,
                                        "entry_id" =>1,
                                    );
                                    $game_trans_id = GameTransactionMDB::createGametransactionV2($gameTransactionData,$gen_game_trans_id, $get_client_details);
                              }else{
                                  // GameTransactionMDB
                                    // $bet_amount = $checkTransaction->bet_amount + $bet_amount;
                                    $updateGameTransaction = [
                                        "bet_amount" => $checkTransaction->bet_amount + $bet_amount,
                                    ];
                                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $checkTransaction->game_trans_id, $get_client_details);
                                    $game_trans_id = $checkTransaction->game_trans_id;
                              }
                              $gameTransactionEXTData = array(
                                "game_trans_id" => $game_trans_id,
                                "provider_trans_id" => $provider_trans_id,
                                "round_id" => $round_id,
                                "amount" => $bet_amount,
                                "game_transaction_type"=> 1,
                                // "provider_request" =>json_encode($data),
                                );
                              $game_trans_ext_id = GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$gen_game_extid, $get_client_details);
                              $createGameTransactionLog = [
                                  "connection_name" => $get_client_details->connection_name,
                                  "column" =>[
                                      "game_trans_ext_id" => $gen_game_extid,
                                      "request" => json_encode($request->all()),
                                      "response" => json_encode($response),
                                      "log_type" => "provider_details",
                                      "transaction_detail" => "SUCCESS",
                                  ]
                                ];
                                ProviderHelper::queTransactionLogs($createGameTransactionLog);

                  Helper::saveLog('TTGaming bet', $this->provider_db_id, $array, $response);
                  return response($response,200) 
                    ->header('Content-Type', 'application/xml');
                } catch (\Exception $e) {
                    $msg = array(
                          'error' => '1',
                          'message' => $e->getMessage(),
                      );
                   return json_encode($msg,JSON_FORCE_OBJECT);
                }
    }
    public function creditProcess($data,$get_client_details){
        $gen_game_extid = ProviderHelper::idGenerate($get_client_details->connection_name,2);
              try {
                  // $bet_transaction = GameTransactionMDB::findGameTransactionDetails($array['@attributes']['handid'],'round_id', 1, $get_client_details);
                  $bet_transaction = GameTransactionMDB::findGameTransactionDetailsV2($data['handid'],'round_id', 1, $get_client_details);
                  $get_client_details->connection_name = $bet_transaction->connection_name;
                  // $bet_transaction = DB::select("select game_trans_id,bet_amount, pay_amount from game_transactions where round_id = '".$array['@attributes']['handid']."'");
                  // $bet_transaction = $bet_transaction[0];
               
                  $provider_trans_id = $data['txnid'];
                  $game_code = $game_details->game_code;
                  $pay_amount = $data["amt"];
                  $bet_amount = $bet_transaction->bet_amount;
                  $income = abs($bet_amount) - $pay_amount;
                  $round_id = $data['handid'];
                  $winbBalance = $get_client_details->balance + $pay_amount;
                  $format_winbBalance = number_format($winbBalance,2,'.','');
                  $entry_id = $pay_amount > 0 ?  2 : 1;
                  $win_or_lost = $pay_amount > 0 ?  1 : 0;
                  ProviderHelper::_insertOrUpdate($get_client_details->token_id, $winbBalance); 
                  
                  $response = '<?xml version="1.0" encoding="utf-8"?>';
                  $response .= '<cw type="fundTransferResp" cur="'.$get_client_details->default_currency.'" amt="'.$format_winbBalance.'" err="0" />';

                   $updateGameTransaction = [
                          'win' => 5,
                          'pay_amount' => $pay_amount,
                          'income' => $income,
                          'entry_id' => $entry_id,
                          'trans_status' => 2
                      ];
                  GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $get_client_details);

                  $gameTransactionEXTData = array(
                          "game_trans_id" => $bet_transaction->game_trans_id,
                          "provider_trans_id" => $data['txnid'],
                          "round_id" => $data['handid'],
                          "amount" => $pay_amount,
                          "game_transaction_type"=> 2,
                          // "provider_request" =>json_encode($data),
                          // "mw_response" => json_encode($response),
                      );
                  $game_trans_ext_id = GameTransactionMDB::createGameTransactionExtv2($gameTransactionEXTData,$gen_game_extid,$get_client_details);
                  // $createGameTransactionLog = [
                  //       "connection_name" => $get_client_details->connection_name,
                  //       "column" =>[
                  //           "game_trans_ext_id" => $gen_game_extid,
                  //           "request" => json_encode($data),
                  //           "response" => json_encode($response),
                  //           "log_type" => "provider_details",
                  //           "transaction_detail" => "success",
                  //       ]
                  //   ];
                  //   ProviderHelper::queTransactionLogs($createGameTransactionLog);
                                $action_payload = [
                                    "type" => "custom", #genreral,custom :D # REQUIRED!
                                    "custom" => [
                                        "provider" => 'TopTrendGaming',
                                        "client_connection_name" => $get_client_details->connection_name,
                                        "win_or_lost" => $win_or_lost,
                                        "entry_id" => $entry_id,
                                        "pay_amount" => $pay_amount,
                                        "income" => $income,
                                        "game_trans_ext_id" => $gen_game_extid
                                    ],
                                    "provider" => [
                                        "provider_request" => $data, #R
                                        "provider_trans_id"=> $data['txnid'], #R
                                        "provider_round_id"=> $data['handid'], #R
                                    ],
                                    "mwapi" => [
                                        "roundId"=>$bet_transaction->game_trans_id, #R
                                        "type"=>2, #R
                                        "game_id" => $game_details->game_id, #R
                                        "player_id" => $get_client_details->player_id, #R
                                        "mw_response" => $response, #R
                                    ],
                                    'fundtransferrequest' => [
                                        'fundinfo' => [
                                            'freespin' => false,
                                        ]
                                    ]
                                ];
                          
                    $client_response = ClientRequestHelper::fundTransfer_TG($get_client_details,$pay_amount,$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',false,$action_payload);

                  //   $updateTransactionEXt = array(
                  //         "provider_request" =>json_encode($data),
                  //         "mw_response" => json_encode($response),
                  //         'mw_request' => json_encode($client_response->requestoclient),
                  //         'client_response' => json_encode($client_response->fundtransferresponse),
                  //         'transaction_detail' => 'success',
                  //         'general_details' => 'success',
                  // );
                  // GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$get_client_details);
                  
                  Helper::saveLog('TTGaming credit', $this->provider_db_id, $array, $response);
                  return response($response,200) 
                    ->header('Content-Type', 'application/xml');

              } catch (\Exception $e) {
                $msg = array(
                          'error' => '1',
                          'message' => $e->getMessage(),
                      );
                   return json_encode($msg,JSON_FORCE_OBJECT);
              }
    }
    public function generateId($type){
        if($type == "ext"){
            return shell_exec('date +%s%N');
        }
        if($type == "transid"){
            return shell_exec('date +%s%N');
        }
    }

    public static function createGameTransExt($game_trans_id, $provider_trans_id, $round_id, $amount, $game_type, $provider_request, $mw_response, $mw_request, $client_response, $transaction_detail){
        $gametransactionext = array(
            "game_trans_id" => $game_trans_id,
            "provider_trans_id" => $provider_trans_id,
            "round_id" => $round_id,
            "amount" => $amount,
            "game_transaction_type"=>$game_type,
            "provider_request" => json_encode($provider_request),
            "mw_response" =>json_encode($mw_response),
            "mw_request"=>json_encode($mw_request),
            "client_response" =>json_encode($client_response),
            "transaction_detail" =>json_encode($transaction_detail)
        );
        $gamestransaction_ext_ID = DB::table("game_transaction_ext")->insertGetId($gametransactionext);
        return $gamestransaction_ext_ID;
    }

        public static function updateGameTransactionExt($gametransextid,$mw_request,$mw_response,$client_response,$time=null){
        $gametransactionext = array(
            "mw_request"=>json_encode($mw_request),
            "mw_response" =>json_encode($mw_response),
            "client_response" =>json_encode($client_response),
            "general_details"=>$time,
        );
        DB::table('game_transaction_ext')->where("game_trans_ext_id",$gametransextid)->update($gametransactionext);
    }
}

