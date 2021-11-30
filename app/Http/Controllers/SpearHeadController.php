<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SpearHeadController extends Controller
{

      public function __construct(){
        $this->provider_db_id = config('providerlinks.spearhead.provider_db_id');
        $this->api_url = config('providerlinks.spearhead.api_url');
        $this->operator =config('providerlinks.spearhead.operator');
        $this->operator_key = config('providerlinks.spearhead.operator_key');
      }
   public function getBalance(Request $request){

    $data = $request->all();
    $playerId = $data['ExternalUserId'];
    $client_details = ProviderHelper::getClientDetails('player_id',$playerId);
    if($client_details != null){
          $res = [
              "balance" => (float)$client_details->balance,
              "BonusMoney" => 0.0,
              "RealMoney" => (int)$client_details->balance,
              "Currency" => $client_details->default_currency,
              "SessionId" => $data['SessionId'],
              "ApiVersion" => "1.0",
              "Request" => "GetBalance",
              "ReturnCode" => 0,
              "Message" => "Success",
              "Details" => null,
          ];
      }else{
          $res = [
              "ApiVersion"=>"1.0",
              "Request" =>"GetBalance",
              "ReturnCode" => 103,
              "Message" => "User not found"
          ];
      }
      return $res;
          
  }

  public function index(Request $req){

    $TransactionType = req['TransactionType'];

    if($TransactionType == 'wager'){
        $data = $req->all();
        $playerId = $data['ExternalUserId'];
        $bet_amount = $data['Amount'];
        $provider_trans_id = $data['TransactionId'];
        $game_code = $data['GPGameId'];
        $round_id = $data['RoundId'];
        $client_details = ProviderHelper::getClientDetails('player_id', $playerId);
      if($client_details != null){

        try{
            ProviderHelper::idenpotencyTable($provider_trans_id);
            }catch(\Exception $e){
              $res = [
                "ApiVersion"=>"1.0",
                "Request" =>"WalletDebit",
                "ReturnCode" => 107,
                "Message" => "Transaction is processing"
            ];
                return $response;
            }
        
       $game_details = Game::find($game_code, $this->provider_db_id);  
          $gameTransactionData = array(
            "provider_trans_id" => $provider_trans_id,
            "token_id" => $client_details->token_id,
            "game_id" => $game_details->game_id,
            "round_id" => $round_id,
            "bet_amount" => $bet_amount,
            "win" => 5,
            "pay_amount" => 0,
            "income" => 0,
            "entry_id" => 1,
        ); 
        Helper::saveLog('Spearhead gameTransactionData', $this->provider_db_id, json_encode($request->all()), 'ENDPOINT HIT');
        $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
        $gameTransactionEXTData = array(
          "game_trans_id" => $game_transaction_id,
          "provider_trans_id" => $provider_trans_id,
          "round_id" => $round_id,
          "amount" => $bet_amount,
          "game_transaction_type"=> 1,
          "provider_request" =>json_encode($request->all()),
          );
          Helper::saveLog('Spearhead  gameTransactionEXTData', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');
          $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details); 
          $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount, $game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, 'debit');
          if (isset($client_response->fundtransferresponse->status->code)) {
            ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
            switch ($client_response->fundtransferresponse->status->code) {
                case '200':
                
                        $http_status = 200;
                        $response = [
                                "AccountTransactionId" => '',
                                "Currency" => $client_details->default_currency,
                                "Balance" => (float)$client_response->fundtransferresponse->balance,
                                "SessionId" => $data['SessionId'],
                                "BonusMoneyAffected" => 0.0,
                                "RealMoneyAffected" => $bet_amount,
                                "ApiVersion" => "1.0",
                                "Request" => 'WalletDebit',
                                "ReturnCode" => 0,
                                "Message" => 'Success',
                                "Details" => null,
                                ];

                $updateTransactionEXt = array(
                    "provider_request" =>json_encode($request->all()),
                    "mw_response" => json_encode($response),
                    'mw_request' => json_encode($client_response->requestoclient),
                    'client_response' => json_encode($client_response->fundtransferresponse),
                    'transaction_detail' => 'success',
                    'general_details' => 'success',
                );
                 Helper::saveLog('SpearHead updateTransactionEXt', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');   
                 GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                    break;
                case '402':
                    ProviderHelper::updateGameTransactionStatus($game_transaction_id, 2, 99);
                    $http_status = 400;
                    $res = [
                      "ApiVersion"=>"1.0",
                      "Request" =>"WalletDebit",
                      "ReturnCode" => 104,
                      "Message" => "Insufficient funds"
                  ];

                $updateTransactionEXt = array(
                    "provider_request" =>json_encode($request->all()),
                    "mw_response" => json_encode($response),
                    'mw_request' => json_encode($client_response->requestoclient),
                    'client_response' => json_encode($client_response->fundtransferresponse),
                    'transaction_detail' => 'failed',
                    'general_details' => 'failed',
                );
                 Helper::saveLog('after 402 updateTransactionEXt', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');   
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                    break;
            }
        }
            
        Helper::saveLog('Spearhead Debit', $this->provider_db_id, json_encode($data), $response);
        return response()->json($response, $http_status);

      }else{
        $res = [
          "ApiVersion"=>"1.0",
          "Request" =>"WalletDebit",
          "ReturnCode" => 103,
          "Message" => "User not found"
      ];

       return response($response,400)->header('Content-Type', 'application/json');

      }    
    }//End of Debit

    
  }//End of Index
}
