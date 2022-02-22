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
use DateTime;

class SpearHeadController extends Controller
{

    public function __construct(){
      $this->provider_db_id = config('providerlinks.spearhead.provider_db_id');
      $this->api_url = config('providerlinks.spearhead.api_url');
      $this->operator =config('providerlinks.spearhead.operator');
      $this->operator_key = config('providerlinks.spearhead.operator_key');
      $this->opid= config('providerlinks.spearhead.opid');
      $this->loginName = config('providerlinks.spearhead.username');
      $this->password = config('providerlinks.spearhead.password');
      // $this->$processtime = new DateTime('NOW');
    }
    public  function getCountryCode3D($country_code){
        $countryCode = [
          'PH' => 'PHL',
          'US' => 'USD',
          'TH' => 'THA',
          'JP' => 'JPN',
          'KR' => 'KOR'
        ];
        if (array_key_exists($country_code, $countryCode)) {
          return $countryCode[$country_code];
        } else {
          return false;
        }
    }
    public function walletApiReq(Request $req){
      $data = $req->all();
      switch ($data['Request']){
        case "GetAccount":
          return $this->getAccount($req->all());
        break;
        case "GetBalance":
          return $this->getBalance($req->all());
        break;
        case "WalletDebit":
          if($data['TransactionType'] == "Wager"){
            return $this->DebitProcess($req->all());
          }
        break;
        case "WalletCredit":
            if($data['TransactionType'] == "Result"){
              return $this->CreditProcess($req->all());
            }
            if($data['TransactionType'] == "Rollback"){
              return $this->RollbackProcess($req->all());
            }
        break;
      }
      // if($data['Request'] == "GetAccount"){
      //   return $this->getAccount($req->all());
      // }
      // if($data['Request'] == "GetBalance"){
      //   return $this->getBalance($req->all());
      // }
      // if($data['Request'] == "WalletDebit"){
      //   if($data['TransactionType'] == "Wager"){
      //     return $this->DebitProcess($req->all());
      //   }
      // }
      // if($data['Request'] == "WalletCredit"){
      //   if($data['TransactionType'] == "Result"){
      //     return $this->CreditProcess($req->all());
      //   }
      //   if($data['TransactionType'] == "Rollback"){
      //     return $this->RollbackProcess($req->all());
      //   }
      // }
    }
   public function getAccount($req){
      $data = $req;
      Helper::saveLog('Spearhead Verification', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');
      $client_details = ProviderHelper::getClientDetails('token',$data['SessionId']);
      if($client_details != null){

        if($client_details->country_code == null){
          $client_details->country_code = "JP";
          $country_code = $this->getCountryCode3D($client_details->country_code);
        }else{
          $country_code = $this->getCountryCode3D($client_details->country_code);
        }
        $res = [
          "ApiVersion" => "1.0",
          "Request" => "GetAccount",
          "ReturnCode" => 0,
          "Details" => null,
          "AccountId" => (string) $client_details->player_id,
          "SessionId" => $client_details->player_token,
          "ExternalUserId" => (string) $client_details->player_id,
          "Country" => $country_code,
          "Currency" => $client_details->default_currency,
          "Username" => $client_details->username,
          "Birthdate" => "1999-07-10",
          "Message" => "Success"
        ];
      }else{
        $res = [
          "ApiVersion" => "1.0",
          "Request" => "GetAccount",
          "ReturnCode" => 103,
          "Message" => "User not found"
        ];
      }
      Helper::saveLog('Spearhead Verification', $this->provider_db_id, json_encode($data), $res);
      return $res;
   }

public function getBalance($req){
  $data = $req;
  Helper::saveLog('Spearhead  GetBalance', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');
  $client_details = ProviderHelper::getClientDetails('token',$data['SessionId']);
  if($client_details != null){
    $res = [
      "Balance" => (float)$client_details->balance,
      "BonusMoney" => 0.0,
      "RealMoney" => $client_details->balance,
      "Currency" => $client_details->default_currency,
      "SessionId" => $client_details->player_token,
      "ApiVersion" => "1.0",
      "Request" => "GetBalance",
      "ReturnCode" => 0,
      "Message" => "Success",
      "Details" => null
    ];
  }else{
    $res = [
      "ApiVersion" => "1.0",
      "Request" => "GetBalance",
      "ReturnCode" => 103,
      "Message" => "User not found"
    ];
  }
  Helper::saveLog('Spearhead  GetBalance', $this->provider_db_id, json_encode($data), $res);
  return $res;
}

public function DebitProcess($req){
      $data = $req;
      Helper::saveLog('Spearhead  DebitProcess', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');
      $client_details = ProviderHelper::getClientDetails('token',$data['SessionId']);
      $playerId = $data['ExternalUserId'];
      $bet_amount = $data['Amount'];
      $provider_trans_id = $data['TransactionId'];
      $game_code = $data['AdditionalData']['GameSlug'];
      $round_id = $data['RoundId'];
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
              return $res;
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
      Helper::saveLog('Spearhead gameTransactionData', $this->provider_db_id, json_encode($req), 'ENDPOINT HIT');
      $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
      $gameTransactionEXTData = array(
        "game_trans_id" => $game_transaction_id,
        "provider_trans_id" => $provider_trans_id,
        "round_id" => $round_id,
        "amount" => $bet_amount,
        "game_transaction_type"=> 1,
        "provider_request" =>json_encode($req),
        );
        Helper::saveLog('Spearhead  gameTransactionEXTData', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');
        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details); 
        $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount, $game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, 'debit');
        if (isset($client_response->fundtransferresponse->status->code)) {
          ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
          switch ($client_response->fundtransferresponse->status->code) {
              case '200':
                $http_status = 200;
                    $res = [
                            "AccountTransactionId" => $game_transaction_id,
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
                        "provider_request" =>json_encode($req),
                        "mw_response" => json_encode($res),
                        'mw_request' => json_encode($client_response->requestoclient),
                        'client_response' => json_encode($client_response->fundtransferresponse),
                        'transaction_detail' => 'success',
                        'general_details' => 'success',
                    );
                   Helper::saveLog('SpearHead updateTransactionEXt', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');   
                   GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                break;
                case '402':
                    $http_status = 400;
                    $updateGameTransaction = [
                          'win' => 2,
                          'trans_status' => 5
                    ];
                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
                    $res = [
                      "ApiVersion"=>"1.0",
                      "Request" =>"WalletDebit",
                      "ReturnCode" => 104,
                      "Message" => "Insufficient funds"
                    ];

                    $updateTransactionEXt = array(
                        "provider_request" =>json_encode($req),
                        "mw_response" => json_encode($res),
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
          
      Helper::saveLog('Spearhead Debit', $this->provider_db_id, json_encode($data), $res);
      return response()->json($res, $http_status);

    }else{
      $res = [
        "ApiVersion"=>"1.0",
        "Request" =>"WalletDebit",
        "ReturnCode" => 103,
        "Message" => "User not found"
    ];

     return response($res,400)->header('Content-Type', 'application/json');

    } 
}//end debit func=======================================================================================

public function CreditProcess($req){
  $data = $req;
  Helper::saveLog('Spearhead Credit', $this->provider_db_id, json_encode($data), 'ENDPOINT Hit');
  $client_details = ProviderHelper::getClientDetails('token',$data['SessionId']);
  $playerId = $data['ExternalUserId'];
  $pay_amount = $data['Amount'];
  $provider_trans_id = $data['TransactionId'];
  $game_code = $data['AdditionalData']['GameSlug'];
  $round_id = $data['RoundId'];
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
        return $res;
    }
    $game_details = Game::find($game_code, $this->provider_db_id);
    $bet_transaction = GameTransactionMDB::findGameTransactionDetails($round_id,'round_id', false, $client_details);
    $winBalance = $client_details->balance + $pay_amount;
    $win_or_lost = $pay_amount > 0 ?  1 : 0;
    $entry_id = $pay_amount > 0 ?  2 : 1;
    if($bet_transaction == 'false'){
      $gameTransactionData = array(
          "provider_trans_id" => $provider_trans_id,
          "token_id" => $client_details->token_id,
          "game_id" => $game_details->game_id,
          "round_id" => $round_id,
          "bet_amount" => 0,
          "win" => 5,
          "pay_amount" => 0,
          "income" => 0,
          "entry_id" => 1,
      ); 
      Helper::saveLog('Spearhead gameTransactionData', $this->provider_db_id, json_encode($req), 'ENDPOINT HIT');
      $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
      $game_trans_id = $game_transaction_id;
      $income = 0;
      $gameTransactionEXTData = array(
          "game_trans_id" => json_encode($game_trans_id),
          "provider_trans_id" => $provider_trans_id,
          "round_id" => $round_id,
          "amount" => $pay_amount,
          "game_transaction_type"=> 2,
          "provider_request" => json_encode($req),
          "mw_response" => json_encode($res),
      );
      $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
    }else{
      $client_details->connection_name = $bet_transaction->connection_name;
      $income = $bet_transaction->bet_amount - $pay_amount;
      $game_trans_id = $bet_transaction->game_trans_id;
    }
    $res = [
      "ApiVersion" => "1.0",
      "Request" => "WalletCredit",
      "ReturnCode" => 0,
      "Details" => null,
      "SessionId" => $client_details->player_token,
      "ExternalUserId" => $client_details->player_id,
      "AccountTransactionId" => $game_trans_id,
      "Balance" => $winBalance,
      "Currency" => $client_details->default_currency,
      "Message" => "Success"
    ];
    $updateGameTransaction = [
          'win' => 5,
          'pay_amount' => $pay_amount,
          'income' => $income,
          'entry_id' => $entry_id,
          'trans_status' => 2
    ];
    GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
    $gameTransactionEXTData = array(
              "game_trans_id" => json_encode($game_trans_id),
              "provider_trans_id" => $provider_trans_id,
              "round_id" => $round_id,
              "amount" => $pay_amount,
              "game_transaction_type"=> 2,
              "provider_request" => json_encode($req),
              "mw_response" => json_encode($res),
          );
    $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
    ProviderHelper::_insertOrUpdate($client_details->token_id, $winBalance);

    $action_payload = [
          "type" => "custom", #genreral,custom :D # REQUIRED!
          "custom" => [
              "provider" => 'SpearHead',
              "client_connection_name" => $client_details->connection_name,
              "win_or_lost" => $win_or_lost,
              "entry_id" => $entry_id,
              "pay_amount" => $pay_amount,
              "income" => $income,
              "game_trans_ext_id" => $game_trans_ext_id
          ],
          "provider" => [
              "provider_request" => json_encode($req), #R
              "provider_trans_id"=> $provider_trans_id, #R
              "provider_round_id"=> $round_id, #R
          ],
          "mwapi" => [
              "roundId"=>$game_trans_id, #R
              "type"=>2, #R
              "game_id" => $game_details->game_id, #R
              "player_id" => $client_details->player_id, #R
              "mw_response" => $res, #R
          ],
          'fundtransferrequest' => [
              'fundinfo' => [
                  'freespin' => false,
              ]
          ]
    ];
    $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$pay_amount,$game_details->game_code,$game_details->game_name,$game_trans_id,'credit',false,$action_payload);
    if(isset($client_response->fundtransferresponse->status->code) 
    && $client_response->fundtransferresponse->status->code == "200"){
        $updateTransactionEXt = array(
              "provider_request" =>json_encode($req),
              "mw_response" => json_encode($res),
              'mw_request' => json_encode($client_response->requestoclient),
              'client_response' => json_encode($client_response->fundtransferresponse),
              'transaction_detail' => 'success',
              'general_details' => 'success',
        );
        GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
        return $res;
    }elseif (isset($client_response->fundtransferresponse->status->code) 
    && $client_response->fundtransferresponse->status->code == "402") {
      $res = [
        "ApiVersion"=>"1.0",
        "Request" =>"WalletDebit",
        "ReturnCode" => 104,
        "Message" => "Casino session limit exceeded"
      ];
      $updateTransactionEXt = array(
            "provider_request" =>json_encode($req),
            "mw_response" => json_encode($res),
            'mw_request' => json_encode($client_response->requestoclient),
            'client_response' => json_encode($client_response->fundtransferresponse),
            'transaction_detail' => 'FAILED',
            'general_details' => 'FAILED',
      );
      GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
      return $res;

    }
  }//end check client details
}//end credit func


public function RollbackProcess($req){
  $data = $req;
  Helper::saveLog('Spearhead Credit', $this->provider_db_id, json_encode($data), 'ENDPOINT Hit');
  $client_details = ProviderHelper::getClientDetails('token',$data['SessionId']);
  $playerId = $data['ExternalUserId'];
  $rollback_amount = $data['Amount'];
  $provider_trans_id = $data['TransactionId'];
  $rollbackTransactionId = $data['RollbackTransactionId'];
  $game_code = $data['AdditionalData']['GameSlug'];
  $round_id = $data['RoundId'];
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
        return $res;
    }
    $game_details = Game::find($game_code, $this->provider_db_id);
    $bet_transaction = GameTransactionMDB::findGameTransactionDetails($rollbackTransactionId,'transaction_id', false, $client_details);
    $client_details->connection_name = $bet_transaction->connection_name;
    $income = $bet_transaction->bet_amount - $rollback_amount;
    $NewBalance = $client_details->balance + $rollback_amount;
    $win_or_lost = 4;
    $entry_id = 2;
    $updateGameTransaction = [
        'win' => $win_or_lost,
        'pay_amount' => $rollback_amount,
        'income' => $income,
        'entry_id' => $entry_id,
        'trans_status' => 3
    ];
    GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
    $gameTransactionEXTData = array(
        "game_trans_id" => $bet_transaction->game_trans_id,
        "provider_trans_id" => $provider_trans_id,
        "round_id" => $round_id,
        "amount" => $rollback_amount,
        "game_transaction_type"=> 3,
        "provider_request" =>json_encode($req),
        "mw_response" => null,
    );
    $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
    $client_response = ClientRequestHelper::fundTransfer($client_details, $rollback_amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $bet_transaction->game_trans_id, 'credit', "true");
    if (isset($client_response->fundtransferresponse->status->code)) {
                            
            switch ($client_response->fundtransferresponse->status->code) {
                case '200':
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                     $res = [
                        "ApiVersion" => "1.0",
                        "Request" => "WalletCredit",
                        "ReturnCode" => 0,
                        "Details" => null,
                        "SessionId" => $client_details->player_token,
                        "ExternalUserId" => $client_details->player_id,
                        "AccountTransactionId" => $bet_transaction->game_trans_id,
                        "Balance" => $client_response->fundtransferresponse->balance,
                        "Currency" => $client_details->default_currency,
                        "Message" => "Success"
                    ];
                return $res;
            }

            $updateTransactionEXt = array(
                "provider_request" =>json_encode($req),
                "mw_response" => json_encode($res),
                'mw_request' => json_encode($client_response->requestoclient),
                'client_response' => json_encode($client_response->fundtransferresponse),
                'transaction_detail' => 'success',
                'general_details' => 'success',
            );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);

        }
  }//end check player details
}//end rollback func
//   public function index(Request $req){
//     Helper::saveLog('Spearhead  index', $this->provider_db_id, json_encode($req->all()), 'ENDPOINT HIT');
//     $TransactionType = req['TransactionType'];
//     if($request_secretkey != $this->operator_key && $request_opid != $this->opid){
//       $res = [
//         "ApiVersion"=>"v1",
//         "serverTimestamp" => $this->$processtime->format('Y-m-d\TH:i:s.u'),
//         "Code" => 403,
//         "Message" => "Forbidden"
//       ];

//          return response($response,403)->header('Content-Type', 'application/json');
//     }
//     if($TransactionType == 'wager'){
//         $data = $req->all();
//         $playerId = $data['ExternalUserId'];
//         $bet_amount = $data['Amount'];
//         $provider_trans_id = $data['TransactionId'];
//         $game_code = $data['GPGameId'];
//         $round_id = $data['RoundId'];
//         $client_details = ProviderHelper::getClientDetails('player_id', $playerId);
//       if($client_details != null){

//         try{
//             ProviderHelper::idenpotencyTable($provider_trans_id);
//           }catch(\Exception $e){
//               $res = [
//                 "ApiVersion"=>"1.0",
//                 "Request" =>"WalletDebit",
//                 "ReturnCode" => 107,
//                 "Message" => "Transaction is processing"
//             ];
//                 return $response;
//             }
        
//        $game_details = Game::find($game_code, $this->provider_db_id);  
//           $gameTransactionData = array(
//             "provider_trans_id" => $provider_trans_id,
//             "token_id" => $client_details->token_id,
//             "game_id" => $game_details->game_id,
//             "round_id" => $round_id,
//             "bet_amount" => $bet_amount,
//             "win" => 5,
//             "pay_amount" => 0,
//             "income" => 0,
//             "entry_id" => 1,
//         ); 
//         Helper::saveLog('Spearhead gameTransactionData', $this->provider_db_id, json_encode($request->all()), 'ENDPOINT HIT');
//         $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
//         $gameTransactionEXTData = array(
//           "game_trans_id" => $game_transaction_id,
//           "provider_trans_id" => $provider_trans_id,
//           "round_id" => $round_id,
//           "amount" => $bet_amount,
//           "game_transaction_type"=> 1,
//           "provider_request" =>json_encode($request->all()),
//           );
//           Helper::saveLog('Spearhead  gameTransactionEXTData', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');
//           $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details); 
//           $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount, $game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, 'debit');
//           if (isset($client_response->fundtransferresponse->status->code)) {
//             ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
//             switch ($client_response->fundtransferresponse->status->code) {
//                 case '200':
                
//                         $http_status = 200;
//                         $response = [
//                                 "AccountTransactionId" => $game_transaction_id,
//                                 "Currency" => $client_details->default_currency,
//                                 "Balance" => (float)$client_response->fundtransferresponse->balance,
//                                 "SessionId" => $data['SessionId'],
//                                 "BonusMoneyAffected" => 0.0,
//                                 "RealMoneyAffected" => $bet_amount,
//                                 "ApiVersion" => "1.0",
//                                 "Request" => 'WalletDebit',
//                                 "ReturnCode" => 0,
//                                 "Message" => 'Success',
//                                 "Details" => null,
//                                 ];

//                 $updateTransactionEXt = array(
//                     "provider_request" =>json_encode($request->all()),
//                     "mw_response" => json_encode($response),
//                     'mw_request' => json_encode($client_response->requestoclient),
//                     'client_response' => json_encode($client_response->fundtransferresponse),
//                     'transaction_detail' => 'success',
//                     'general_details' => 'success',
//                 );
//                  Helper::saveLog('SpearHead updateTransactionEXt', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');   
//                  GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
//                     break;
//                 case '402':
//                     ProviderHelper::updateGameTransactionStatus($game_transaction_id, 2, 99);
//                     $http_status = 400;
//                     $res = [
//                       "ApiVersion"=>"1.0",
//                       "Request" =>"WalletDebit",
//                       "ReturnCode" => 104,
//                       "Message" => "Insufficient funds"
//                   ];

//                 $updateTransactionEXt = array(
//                     "provider_request" =>json_encode($request->all()),
//                     "mw_response" => json_encode($response),
//                     'mw_request' => json_encode($client_response->requestoclient),
//                     'client_response' => json_encode($client_response->fundtransferresponse),
//                     'transaction_detail' => 'failed',
//                     'general_details' => 'failed',
//                 );
//                  Helper::saveLog('after 402 updateTransactionEXt', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');   
//             GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
//                     break;
//             }
//         }
            
//         Helper::saveLog('Spearhead Debit', $this->provider_db_id, json_encode($data), $response);
//         return response()->json($response, $http_status);

//       }else{
//         $res = [
//           "ApiVersion"=>"1.0",
//           "Request" =>"WalletDebit",
//           "ReturnCode" => 103,
//           "Message" => "User not found"
//       ];

//        return response($response,400)->header('Content-Type', 'application/json');

//       }    
//   }//End of Debit


//     if($TransactionType == 'Result'){
//       Helper::saveLog('SpearHead credit process', $this->provider_db_id, json_encode($request->all()),"ENDPOINTHIT WIN");
//       $data = $req->all();
//       $client_details = ProviderHelper::getClientDetails('player_id', $data['ExternalUserId']);
//       $pay_amount = $data['Amount'];
//       $provider_trans_id = $data['TransactionId'];
//       $round_id = $data['RoundId'];
//       $game_code = $data['GPGameId'];
//       $bet_transaction = GameTransactionMDB::findGameTransactionDetails($round_id,'round_id', 1, $client_details);
//       try{
//         ProviderHelper::idenpotencyTable($provider_trans_id);
//       }catch(\Exception $e){
//           $res = [
//             "ApiVersion"=>"1.0",
//             "Request" =>"WalletCredit",
//             "ReturnCode" => 107,
//             "Message" => "Transaction is processing"
//         ];
//             return $response;
//         }

//         $game_details = Game::find($game_code, $this->provider_db_id);
//         $winbBalance = $client_details->balance + $pay_amount; 
//         ProviderHelper::_insertOrUpdate($client_details->token_id, $winbBalance);

     
//         $entry_id = $pay_amount > 0 ?  2 : 1;
//         $amount = $pay_amount + $bet_transaction->pay_amount;
//         $income = $bet_transaction->bet_amount -  $amount; 
//         if($bet_transaction->pay_amount > 0){
//           $win_or_lost = 1;
//           }else{
//               $win_or_lost = $pay_amount > 0 ?  1 : 0;
//           }
//           $updateGameTransaction = [
//             'win' => 5,
//             'pay_amount' => $amount,
//             'income' => $income,
//             'entry_id' => $entry_id,
//             'trans_status' => 2
//         ]; 
//         GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
//         $gameTransactionEXTData = array(
//           "game_trans_id" => $bet_transaction->game_trans_id,
//           "provider_trans_id" => $provider_trans_id,
//           "round_id" => $round_id,
//           "amount" => $pay_amount,
//           "game_transaction_type"=> 2,
//           "provider_request" =>json_encode($request->all()),
//           "mw_response" => json_encode($response),
//       );
//       $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
//             $response = [
//               "ApiVersion" => "1.0",
//               "Request" => "WalletCredit",
//               "ReturnCode" => 0,
//               "Message" => "Success",
//               "AccountTransactionId" => $game_trans_ext_id,
//               "Currency" => $client_details->default_currency,
//               "Balance" => $winbBalance,
//               "SessionId" => $client_details->token
//             ];        
//             $action_payload = [
//               "type" => "custom", #genreral,custom :D # REQUIRED!
//               "custom" => [
//                   "provider" => 'SpearHead',
//                   "client_connection_name" => $client_details->connection_name,
//                   "win_or_lost" => $win_or_lost,
//                   "entry_id" => $entry_id,
//                   "pay_amount" => $pay_amount,
//                   "income" => $income,
//                   "game_trans_ext_id" => $game_trans_ext_id
//               ],
//               "provider" => [
//                   "provider_request" => $data, #R
//                   "provider_trans_id"=> $provider_trans_id, #R
//                   "provider_round_id"=> $round_id, #R
//               ],
//               "mwapi" => [
//                   "roundId"=>$bet_transaction->game_trans_id, #R
//                   "type"=>2, #R
//                   "game_id" => $game_details->game_id, #R
//                   "player_id" => $client_details->player_id, #R
//                   "mw_response" => $response, #R
//               ],
//               'fundtransferrequest' => [
//                   'fundinfo' => [
//                       'freespin' => false,
//                   ]
//               ]
//            ];
//           $updateTransactionEXt = array(
//             "provider_request" =>json_encode($request->all()),
//             "mw_response" => json_encode($response),
//              );
//              GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details); 
//              $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$pay_amount,$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',false,$action_payload);
//              Helper::saveLog('SpearHead Win Result', $this->provider_db_id, json_encode($request->all()),$response);  
//              return response($response,200)->header('Content-Type', 'application/json');
//      }//End of Credit

//     if($TransactionType == 'Rollback'){

//       $data = $req->all();
//       $provider_trans_id = $data['TransactionId'];
//       $round_id = $data['RoundId'];
//       $game_code = $data['GPGameId'];
//       $game_details = Game::find($game_code, $this->provider_db_id);
//       $client_details = ProviderHelper::getClientDetails('player_id', $data['ExternalUserId']);
//         try{

//           ProviderHelper::idenpotencyTable($provider_trans_id);

//         }catch(\Exception $e){

//          $bet_transaction = GameTransactionMDB::findGameExt($round_id, 2,'round_id', $client_details);
//           if($bet_transaction != 'false'){
//             if($bet_transaction->mw_response == 'null'){

//                 $response = [
//                       "ApiVersion" => '1.0',
//                       "Request" => "WalletRollback",
//                       "ReturnCode" => 108,
//                       "Message" => 'Transaction not found'
//                   ];

//               return $response;

//               }else{
//                 $response = [
//                   "ApiVersion" => '1.0',
//                   "Request" => "WalletRollback",
//                   "ReturnCode" => 107,
//                   "Message" => 'Transaction is processing'
//               ];
//               }
//           }else{
//             $response = [
//               "ApiVersion" => '1.0',
//               "Request" => "WalletRollback",
//               "ReturnCode" => 107,
//               "Message" => 'Transaction is processing'
//           ];

//           }
//           return response($response,200)->header('Content-Type', 'application/json');
//        }
//         $existing_bet = GameTransactionMDB::findGameExt($round_id, 1,'round_id', $client_details); 
//         if($existing_bet != 'false'){
//           $amount = $existing_bet->amount;    
//           $balance = $client_details->balance + $amount;
//           ProviderHelper::_insertOrUpdate($client_details->token_id, $balance); 
//           $response = [
//               "ApiVersion" => "1.0",
//               "Request" => "WalletRollback",
//               "ReturnCode" => 0,
//               "Message" => "Success"
//           ];
//           $gameTransactionEXTData = array(
//             "game_trans_id" => $existing_bet->game_trans_id,
//             "provider_trans_id" => $provider_trans_id,
//             "round_id" => $round_id,
//             "amount" => $amount,
//             "game_transaction_type"=> 3,
//             "provider_request" =>json_encode($data),
//             "mw_response" =>json_encode($response),
//           );
//           $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
//           $win_or_lost = 4;
//           $entry_id = 2;
//           $income = 0 ;
//           $updateGameTransaction = [
//             'win' => 5,
//             "pay_amount" => $amount,
//             'income' => $income,
//             'entry_id' => $entry_id,
//             'trans_status' => 3
//          ];
//          GameTransactionMDB::updateGametransaction($updateGameTransaction, $existing_bet->game_trans_id, $client_details);
//             $body_details = [
//               "type" => "credit",
//               "win" => $win_or_lost,
//               "token" => $client_details->player_token,
//               "rollback" => "true",
//               "game_details" => [
//                   "game_id" => $game_details->game_id
//               ],
//               "game_transaction" => [
//                   "amount" => $amount
//               ],
//               "connection_name" => $existing_bet->connection_name,
//               "game_trans_ext_id" => $game_trans_ext_id,
//               "game_transaction_id" => $existing_bet->game_trans_id

//             ];
//             $client = new Client();
//             $guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-bgFundTransferV2MultiDB',
//                 [ 'body' => json_encode($body_details), 'timeout' => '2.00']
//             );
//             //THIS RESPONSE IF THE TIMEOUT NOT FAILED
//             Helper::saveLog("Success Nolimit Refund", $this->provider_db_id, json_encode($data), $response);  
//             return response($response,200)->header('Content-Type', 'application/json');
//         }else{
          
//           $response = [
//             "ApiVersion" => '1.0',
//             "Request" => "WalletRollback",
//             "ReturnCode" => 108,
//             "Message" => 'Transaction not found'
//          ];
//             Helper::saveLog("SpearHead not found", $this->provider_db_id, json_encode($data), $response);
//             return response($response,200)->header('Content-Type', 'application/json');

//         }

//       }//End of Rollback

// }//End of Index


}
