<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransaction;
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
        $formatBalance = (int)str_replace(".","", $get_client_details->balance);
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
        $formatBalance = (int)str_replace(".","", $get_client_details->balance);
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
                ProviderHelper::idenpotencyTable($request->txn_id);
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
           $game_details = Game::find($data["game_id"], $this->provider_db_id);

            $gameTransactionData = array(
                        "provider_trans_id" => $data['txn_id'],
                        "token_id" => $client_details->token_id,
                        "game_id" => $game_details->game_id,
                        "round_id" => $data['txn_id'],
                        "bet_amount" => $bet_amount,
                        "win" => 5,
                        "pay_amount" => 0,
                        "income" => 0,
                        "entry_id" => 1,
                        "flow_status" => 0,
                    );

          
              
                    $game_transaction_id = GameTransaction::createGametransaction($gameTransactionData);
                    $game_trans_ext_id = ProviderHelper::createGameTransExtV2($game_transaction_id, $data['txn_id'], $data['txn_id'], $bet_amount, 1,$data);
                    $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, 'debit');
                    if (isset($client_response->fundtransferresponse->status->code)) {

                        ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                       
                        switch ($client_response->fundtransferresponse->status->code) {
                            case '200':
                            ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                            
                                     $http_status = 200;
                                    $formatBalance = (int)str_replace(".","", $client_details->balance);

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

                       ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, $data, $response, $client_response->requestoclient, $client_response, $data);
                            
                    }
                        
                    Helper::saveLog('PlayStar Debit', $this->provider_db_id, json_encode($data), $response);
                    return response()->json($response, $http_status);


}

    public function getResult(Request $request){


        Helper::saveLog('PlayStar Result', $this->provider_db_id, json_encode($request->all()),"ENDPOINTHIT WIN");

        $data = $request->all();
        $client_details = ProviderHelper::getClientDetails('token',$data['access_token']);

        $bet_amount = $request->total_win/100;
        $balance = $client_details->balance;

            try{
 
                ProviderHelper::idenpotencyTable($request->ts);
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

                $game_details = Game::find($request->game_id, $this->provider_db_id);

                $bet_transaction = DB::select("select game_trans_id,bet_amount, pay_amount from game_transactions where round_id = '".$request->txn_id."'");
                $bet_transaction = $bet_transaction[0];
                $winbBalance = $balance + $bet_amount; 
                $formatWinBalance = $winbBalance;
                $formatBalance = (int)str_replace(".","", $formatWinBalance);

                ProviderHelper::_insertOrUpdate($client_details->token_id, $winbBalance); 
                $response = [
                    'status_code' => 0,
                    'balance' => $formatBalance
                ];
                    $entry_id = $bet_amount > 0 ?  2 : 1;
                    $amount = $bet_amount + $bet_transaction->pay_amount;
                    $income = $bet_transaction->bet_amount -  $amount;    
                    if($bet_transaction->pay_amount > 0){
                        $win_or_lost = 1;
                    }else{
                        $win_or_lost = $bet_amount > 0 ?  1 : 0;
                    }
                    ;
                    ProviderHelper::updateGameTransaction($bet_transaction->game_trans_id, $amount, $income, $win_or_lost, $entry_id, "game_trans_id",$bet_transaction->bet_amount);
                        

                     $game_trans_ext_id = ProviderHelper::createGameTransExtV2($bet_transaction->game_trans_id, $request->txn_id, $request->txn_id, $bet_amount, 2,$data, $response);


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
                                    "provider_trans_id"=> $request->txn_id, #R
                                    "provider_round_id"=> $request->txn_id, #R
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

                  Helper::saveLog('PlayStar Win Result', $this->provider_db_id, json_encode($request->all()),$response);
                    return response($response,200)
                            ->header('Content-Type', 'application/json');
            
        

    }

    public function getRefundBet(Request $request){

         Helper::saveLog('PlayStar refund', $this->provider_db_id, json_encode($request->all()),"ENDPOINTHIT refund");
        $data = $request->all(); 
        $game_transaction = ProviderHelper::findGameTransaction($request->txn_id,'transaction_id', 1); 
        $client_details = ProviderHelper::getClientDetails('token',$data['access_token']);
        try{
                ProviderHelper::idenpotencyTable($request->txn_id);
            }catch(\Exception $e){
                $response = [
                    "status_code" =>1,
                    "description" => "Invalid Token",
                ];
                return $response;
            }

        if ($game_transaction->win == 2) {
                return response()->json($response);
            }

            $game_details = Game::find($request->game_id, $this->provider_db_id);
           

            $win_or_lost = 4;
            $entry_id = 2;
            $income = $game_transaction->bet_amount -  $game_transaction->bet_amount ;
            ProviderHelper::updateGameTransaction($game_transaction->game_trans_id, $game_transaction->bet_amount, $income, $win_or_lost, $entry_id, "game_trans_id", $game_transaction->bet_amount);
            $game_trans_ext_id = ProviderHelper::createGameTransExtV2($game_transaction->game_trans_id, $request->txn_id, $game_transaction->round_id, $game_transaction->bet_amount, 3);

            $client_response = ClientRequestHelper::fundTransfer($client_details, $game_transaction->bet_amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction->game_trans_id, 'credit', "true");


            if (isset($client_response->fundtransferresponse->status->code)) {
                            
                switch ($client_response->fundtransferresponse->status->code) {
                    case '200':
                        // ProviderHelper::updateGameTransactionFlowStatus($game_transaction->game_trans_id, 5);
                        ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                        $formatBalance = (int)str_replace(".", "", $client_response->fundtransferresponse->balance);
                        $response = [
                            "status_code" => 0,
                            "balance" => $formatBalance
                        ];
                        break;
                }

                ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, $data, $response, $client_response->requestoclient, $client_response, $data);

            }

        Helper::saveLog('PlayStar Refund', $this->provider_db_id, json_encode($request->all()),$response);
        return response($response,200)
                ->header('Content-Type', 'application/json');

    }






}
