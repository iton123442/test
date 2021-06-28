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

class PlayStarController extends Controller
{

    public function __construct(){
        $this->api_url = config('providerlinks.playstar.api_url');
        $this->provider_db_id = config('providerlinks.playstar.provider_db_id');
        $this->host_id = config('providerlinks.playstar.host_id');
    }


    public function getAuth(Request $request){

        Helper::saveLog("PlayStar", $this->provider_db_id, json_encode($request->all()), "ENDPOINT HIT");
        $data = $request->all();
        $get_client_details = ProviderHelper::getClientDetails("token",$data['access_token']);

        if($get_client_details == null ){
            $response = [
                'status_code' => 1,
                'message' => 'Ivalid Token'
            ];
            return response($response,200)
                    ->header('Content-Type', 'application/json');
        }
        $balance = str_replace(".","", $get_client_details->balance);
        $formatBalance = (int) $balance;
        $response = [
            'status_code' => 0,
            'member_id' => "TG_". $get_client_details->player_id,
            'member_name' => $get_client_details->username,
            'balance' =>$formatBalance
        ];
        return response($response,200)
                ->header('Content-Type', 'application/json');
    }

    public function getBalance(Request $request){


        Helper::saveLog("PlayStar get Bal", $this->provider_db_id, json_encode($request->all()), "ENDPOINT HIT"); 
        $data = $request->all();
        $get_client_details = ProviderHelper::getClientDetails("token",$data['access_token']);

        if($get_client_details == null ){
            $response = [
                'status_code' => 1,
                'message' => 'Ivalid Token'
            ];
            return response($response,200)
                    ->header('Content-Type', 'application/json');
        }
        $balance = str_replace(".","", $get_client_details->balance);
        $formatBalance = (int) $balance;
        $response = [
            'status_code' => 0,
            'balance' =>$formatBalance
        ];
        return response($response,200)
                ->header('Content-Type', 'application/json');
    }

    public function getBet(Request $request){

        Helper::saveLog('PlayStar', $this->provider_db_id, json_encode($request->all()), "ENDPOINTHIT BET");
        $data = $request->all();
        $client_details = ProviderHelper::getClientDetails('token',$data['access_token']);
        $bet_amount = $request->total_bet/100;


        try{
            ProviderHelper::idenpotencyTable($data['txn_id']);
        }catch(\Exception $e){
            $response = [
                "status_code" =>  1,
                "message" => "Invalid Token",
            ];
            return $response;
        }

        if($client_details == null){

              $response = [
                    "status_code" =>  1,
                    "message" => "Invalid Token",
                ];

                return $response;
        
        }

        try{
           $game_details = Game::find($data["game_id"], $this->provider_db_id);
            $gameTransactionData = array(
                        "provider_trans_id" => $data['ts'],
                        "token_id" => $client_details->token_id,
                        "game_id" => $game_details->game_id,
                        "round_id" => $data['txn_id'],
                        "bet_amount" => $bet_amount,
                        "win" => 5,
                        "pay_amount" => 0,
                        "income" => 0,
                        "entry_id" => 1,
                    ); 
            
            $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
              
            $gameTransactionEXTData = array(
                "game_trans_id" => $game_transaction_id,
                "provider_trans_id" => $data['ts'],
                "round_id" => $data['txn_id'],
                "amount" => $bet_amount,
                "game_transaction_type"=> 1,
                "provider_request" =>json_encode($request->all()),
                );


            $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details); 
            $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, 'debit');
                   
                    
                    if (isset($client_response->fundtransferresponse->status->code)) {

                        ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                       
                        switch ($client_response->fundtransferresponse->status->code) {
                            case '200':
                            ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                            
                                     $http_status = 200;
                                    $formatBalance = (int) str_replace(".","", $client_details->balance);
                                

                                $response = [
                                    "status_code" => 0,
                                    "balance" => $formatBalance,
                                    
                                ];

                                break;
                            case '402':
                                ProviderHelper::updateGameTransactionStatus($game_transaction_id, 2, 99);
                                $http_status = 200;
                                $response = [
                                    "status_code" => 3,
                                    "message" => "Insufficient Funds",

                                  
                                ];
                                break;
                        }
                            $updateTransactionEXt = array(
                                "provider_request" =>json_encode($request->all()),
                                "mw_response" => json_encode($response),
                                'mw_request' => json_encode($client_response->requestoclient),
                                'client_response' => json_encode($client_response->fundtransferresponse),
                                'transaction_detail' => 'success',
                                'general_details' => 'success',
                            );

                        GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);


                    }
                        
                    Helper::saveLog('PlayStar Debit', $this->provider_db_id, json_encode($data), $response);
                    return response()->json($response, $http_status);
        }catch(\Exception $e){
            $msg = array(
                'error' => '1',
                'message' => $e->getMessage(),
            );
            Helper::saveLog('Playstar bet error', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT), $msg);
            return json_encode($msg, JSON_FORCE_OBJECT); 
        }


}

    public function getResult(Request $request){


        Helper::saveLog('PlayStar Result', $this->provider_db_id, json_encode($request->all()),"ENDPOINTHIT WIN");

        $data = $request->all();
        $client_details = ProviderHelper::getClientDetails('token',$data['access_token']);
        $bet_amount = $data["total_win"] / 100;
        $balance = $client_details->balance;
            try{
                ProviderHelper::idenpotencyTable($data["ts"]);
            }catch(\Exception $e){
                $response = [
                    "status_code" =>  1,
                    "description" => "Invalid Token",
                ];
                return $response;
            }
              
            if($client_details == null){

                    $response = [
                    "status_code" =>  1,
                    "description" => "Invalid Token",
                ];
                return $response;
            }
            try{
                $game_details = Game::find($request->game_id, $this->provider_db_id);
                $bet_transaction = GameTransactionMDB::findGameTransactionDetails($data["txn_id"],'round_id', 1, $client_details);
                $client_details->connection_name = $bet_transaction->connection_name;
                $winbBalance = $balance + $bet_amount; 
                $formatWinBalance = $winbBalance;
                $formatBalance = (int)str_replace(".","", $formatWinBalance);
                ProviderHelper::_insertOrUpdate($client_details->token_id, $winbBalance); 
                $response = [
                    'status_code' => 0,
                    'balance' => $formatBalance
                ];
                $entry_id = $bet_amount > 0 ?  2 : 1;
            
                // if(count($query) > 0){
                    // $amount = $pay_amount;
                    // $income = $bet_transaction->bet_amount -  $amount; 

                // }else{
                    $amount = $bet_amount + $bet_transaction->pay_amount;
                    $income = $bet_transaction->bet_amount -  $amount; 
                // }
                    if($bet_transaction->pay_amount > 0){
                        $win_or_lost = 1;
                    }else{
                        $win_or_lost = $bet_amount > 0 ?  1 : 0;
                    }
                
                   $updateGameTransaction = [
                        'win' => $win_or_lost,
                        'pay_amount' => $amount,
                        'income' => $income,
                        'entry_id' => $entry_id,
                        'trans_status' => 2
                    ];
              GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);

                    $gameTransactionEXTData = array(
                        "game_trans_id" => $bet_transaction->game_trans_id,
                        "provider_trans_id" => $data["ts"],
                        "round_id" => $data["txn_id"],
                        "amount" => $bet_amount,
                        "game_transaction_type"=> 2,
                        "provider_request" =>json_encode($request->all()),
                        "mw_response" => json_encode($response),
                    );

                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);


                            $action_payload = [
                                "type" => "custom", #genreral,custom :D # REQUIRED!
                                "custom" => [
                                    "provider" => 'PlayStar',
                                    "win_or_lost" => $win_or_lost,
                                    "entry_id" => $entry_id,
                                    "pay_amount" => $bet_amount,
                                    "income" => $income,
                                    "game_trans_ext_id" => $game_trans_ext_id
                                ],
                                "provider" => [
                                    "provider_request" => $data, #R
                                    "provider_trans_id"=> $data["ts"], #R
                                    "provider_round_id"=> $data["txn_id"], #R
                                ],
                                "mwapi" => [
                                    "roundId"=>$bet_transaction->game_trans_id, #R
                                    "type"=>2, #R
                                    "game_id" => $game_details->game_id, #R
                                    "player_id" => $client_details->player_id, #R
                                    "mw_response" => $response, #R
                                ],
                                'fundtransferrequest' => [
                                    'fundinfo' => [
                                        'freespin' => false,
                                    ]
                                ]
                            ];
                 $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$bet_amount,$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',false,$action_payload);

                 // $client_response = ClientRequestHelper::fundTransfer($client_details, $bet_amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $bet_transaction->game_trans_id, 'credit');
                 $updateTransactionEXt = array(
                    "provider_request" =>json_encode($request->all()),
                    "mw_response" => json_encode($response),
                    'mw_request' => json_encode($client_response->requestoclient),
                    'client_response' => json_encode($client_response->fundtransferresponse),
                    'transaction_detail' => 'success',
                    'general_details' => 'success',
                );
                GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                  Helper::saveLog('PlayStar Win Result', $this->provider_db_id, json_encode($request->all()),$response);
                    return response($response,200)
                            ->header('Content-Type', 'application/json');

            }catch(\Exception $e){
            $msg = array(
                'error' => '1',
                'message' => $e->getMessage(),
            );
            Helper::saveLog('Playstar Callback error', $this->provider_db_id, json_encode($request->all(),JSON_FORCE_OBJECT), $msg);
            return json_encode($msg, JSON_FORCE_OBJECT); 
        }

            
        

    }

    public function getRefundBet(Request $request){

         Helper::saveLog('PlayStar refund', $this->provider_db_id, json_encode($request->all()),"ENDPOINTHIT refund");
        $data = $request->all();
        $client_details = ProviderHelper::getClientDetails('token',$data['access_token']);
        $game_transaction = GameTransactionMDB::findGameTransactionDetails($data["txn_id"],'round_id', 2, $client_details);
 
        
            try{
                ProviderHelper::idenpotencyTable($data["txn_id"]);
            }catch(\Exception $e){
                $response = [
                    "status_code" =>1,
                    "description" => "Invalid Token",
                ];
                return $response;
            }
            $win_or_lost = 4;
            $entry_id = 2;
            $income = $game_transaction->bet_amount -  $game_transaction->bet_amount ;
            //dd($income);
            $updateGameTransaction = [
                'win' => $win_or_lost,
                'pay_amount' => $game_transaction->bet_amount,
                'income' => $income,
                'entry_id' => $entry_id,
                'trans_status' => 3
            ];
            $game_details = Game::find($data["game_id"], $this->provider_db_id);
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_transaction->game_trans_id,$client_details);
            $gameTransactionEXTData = array(
                    "game_trans_id" => $game_transaction->game_trans_id,
                    "provider_trans_id" => $data["ts"],
                    "round_id" => $data["txn_id"],
                    "amount" => $game_transaction->bet_amount,
                    "game_transaction_type"=> 3,
                    "provider_request" =>json_encode($request->all()),
                    "mw_response" => null,
            );
            $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

            $client_response = ClientRequestHelper::fundTransfer($client_details, $game_transaction->bet_amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction->game_trans_id, 'credit', "true");


            if (isset($client_response->fundtransferresponse->status->code)) {
                            
                switch ($client_response->fundtransferresponse->status->code) {
                    case '200':
                        ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                        $balance = str_replace(".", "", $client_response->fundtransferresponse->balance);
                        $formatBalance = (int) $balance;
                        $response = [
                            "status_code" => 0,
                            "balance" => $formatBalance
                        ];
                        break;
                }

                $updateTransactionEXt = array(
                    "provider_request" =>json_encode($request->all()),
                    "mw_response" => json_encode($response),
                    'mw_request' => json_encode($client_response->requestoclient),
                    'client_response' => json_encode($client_response->fundtransferresponse),
                    'transaction_detail' => 'success',
                    'general_details' => 'success',
                );
                GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);

            }

        Helper::saveLog('PlayStar Hit Refund Success', $this->provider_db_id, json_encode($request->all()),$response);
        return response($response,200)
                ->header('Content-Type', 'application/json');

    }






}
