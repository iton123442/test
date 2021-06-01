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

class OnlyPlayController extends Controller
{

    public function __construct(){
        $this->secret_key = config('providerlinks.onlyplay.secret_key');
        $this->api_url = config('providerlinks.onlyplay.api_url');
        $this->provider_db_id = config('providerlinks.onlyplay.provider_db_id');
        $this->partner_id = config('providerlinks.onlyplay.partner_id');
    }

    public function getBalance(Request $request){
         // Helper::saveLog('OnlyPlay', $this->provider_db_id, json_encode($request->all()), "ENDPOINTHIT LAUNCH");
        $user_id = explode('TG_',$request->user_id);
        $get_client_details = ProviderHelper::getClientDetails("player_id",$user_id[1]);
        // dd($get_client_details);
        $balance = str_replace(".","", $get_client_details->balance);
        $formatBalance = (int) $balance;
    if(!$request->has('token')){
        $response = [
            'success' => false,
            'code' => 7837,
            'message' => 'Internal error'
        ];
        return response($response,200)
                ->header('Content-Type', 'application/json');
    }

    	$response = [
    		'success' => true,
    		'balance' =>  $formatBalance
    	];
        // Helper::saveLog('OnlyPlay', $this->provider_db_id, json_encode($request->all()), $response);
    	return response($response,200)
				->header('Content-Type', 'application/json');
    }

    public function debitProcess(Request $request){
        $data = $request->all();
        Helper::saveLog('OnlyPlay', $this->provider_db_id, json_encode($request->all()), "ENDPOINTHIT BET");
        $user_id = explode('TG_',$request->user_id);
    	$get_client_details = ProviderHelper::getClientDetails("player_id",$user_id[1]);
        $bet_amount = $request->amount/100;
        if($get_client_details != null){
            try{
                ProviderHelper::idenpotencyTable($request->tx_id);
            }catch(\Exception $e){
                $response = [
                    "success" =>  false,
                    "code" => 7837,
                    "message" => "Internal Error",
                ];
                return $response;
            }

            $game_details = Game::find($request->game_bundle, $this->provider_db_id);
            // dd($game_details);

            $gameTransactionData = array(
                        "provider_trans_id" => $request->tx_id,
                        "token_id" => $get_client_details->token_id,
                        "game_id" => $game_details->game_id,
                        "round_id" => $request->round_id,
                        "bet_amount" => $bet_amount,
                        "win" => 5,
                        "pay_amount" => 0,
                        "income" => 0,
                        "entry_id" => 1,
                        
                    );
            $game_transaction_id = GameTransaction::createGametransaction($gameTransactionData);

            $game_trans_ext_id = ProviderHelper::createGameTransExtV2($game_transaction_id, $request->tx_id, $request->round_id, $bet_amount, 1,$data);

            $client_response = ClientRequestHelper::fundTransfer($get_client_details, $bet_amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, 'debit');
            $fundtransfer_bal = number_format($client_response->fundtransferresponse->balance,2,'.','');
            $balance = str_replace(".","", $fundtransfer_bal);
            $formatBalance = (int) $balance;
                        if (isset($client_response->fundtransferresponse->status->code)) {
                            // ProviderHelper::updateGameTransactionFlowStatus($game_transaction_id, 1);
                            ProviderHelper::_insertOrUpdate($get_client_details->token_id, $client_response->fundtransferresponse->balance);
                            switch ($client_response->fundtransferresponse->status->code) {
                                case '200':
                                    $response = [
                                                'success' => true,
                                                'balance' => $formatBalance
                                            ];
                                    break;
                                case '402':
                                    ProviderHelper::updateGameTransactionStatus($game_transaction_id, 2, 3);
                                    $response = [
                                            "success" =>  false,
                                            "code" => 7837,
                                            "message" => "Internal Error",
                                            ];
                                    break;
                            }

                            ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, $data, $response, $client_response->requestoclient, $client_response, $data);

                        }
        	
            Helper::saveLog('OnlyPlay', $this->provider_db_id, json_encode($request->all()), $response);
        	return response($response,200)
    				->header('Content-Type', 'application/json');
        }
    }

    public function creditProcess(Request $request){
        $data = $request->all();
        Helper::saveLog('OnlyPlay', $this->provider_db_id, json_encode($request->all()),"ENDPOINTHIT WIN");
         $user_id = explode('TG_',$request->user_id);
    	$get_client_details = ProviderHelper::getClientDetails("player_id",$user_id[1]);
        $pay_amount = $request->amount/100;
        $balance = str_replace(".","", $get_client_details->balance);
        $formatBalance = (int) $balance;
        if($get_client_details != null){

            // $query = DB::select("select game_trans_id, pay_amount from game_transactions where round_id = '".$request->round_id."' and provider_trans_id = '".$request->tx_id."'");


            try{
                ProviderHelper::idenpotencyTable($request->tx_id);
            }catch(\Exception $e){
                $response = [
                    "success" =>  false,
                    "code" => 7837,
                    "message" => "Internal Error",
                ];
                return $response;
            }
            if($get_client_details == null) {
                $response = [
                    "success" =>  false,
                    "code" => 2401,
                    "message" => "Session not found or expired",
                ];
                return $response;
            }
                $game_details = Game::find($request->game_bundle, $this->provider_db_id);

                $bet_transaction = DB::select("select game_trans_id,bet_amount, pay_amount from game_transactions where round_id = '".$request->round_id."'");
                $bet_transaction = $bet_transaction[0];
                // dd($bet_transaction->pay_amount);
                $winbBalance = ($formatBalance/100) + $pay_amount;
                $formatWinBalance = $winbBalance*100;
                
                ProviderHelper::_insertOrUpdate($get_client_details->token_id, $winbBalance); 
                $response = [
                    'success' => true,
                    'balance' => $formatWinBalance
                ];
                $entry_id = $pay_amount > 0 ?  2 : 1;
            
                // if(count($query) > 0){
                    // $amount = $pay_amount;
                    // $income = $bet_transaction->bet_amount -  $amount; 

                // }else{
                    $amount = $pay_amount + $bet_transaction->pay_amount;
                    $income = $bet_transaction->bet_amount -  $amount; 
                // }
                    if($bet_transaction->pay_amount > 0){
                        $win_or_lost = 1;
                    }else{
                        $win_or_lost = $pay_amount > 0 ?  1 : 0;
                    }
                ProviderHelper::updateGameTransaction($bet_transaction->game_trans_id, $amount, $income, $win_or_lost, $entry_id, "game_trans_id",$bet_transaction->bet_amount); 
                $game_trans_ext_id = ProviderHelper::createGameTransExtV2($bet_transaction->game_trans_id, $request->tx_id, $request->round_id, $pay_amount, 2,$data, $response);


                            $action_payload = [
                                "type" => "custom", #genreral,custom :D # REQUIRED!
                                "custom" => [
                                    "provider" => 'OnlyPlay',
                                    "win_or_lost" => $win_or_lost,
                                    "entry_id" => $entry_id,
                                    "pay_amount" => $pay_amount,
                                    "income" => $income,
                                    "game_trans_ext_id" => $game_trans_ext_id
                                ],
                                "provider" => [
                                    "provider_request" => $data, #R
                                    "provider_trans_id"=> $request->tx_id, #R
                                    "provider_round_id"=> $request->round_id, #R
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
            

        }

        Helper::saveLog('OnlyPlay', $this->provider_db_id, json_encode($request->all()),$response);
    	return response($response,200)
				->header('Content-Type', 'application/json');
    }

    public function rollbackProcess(Request $request){
        $data = $request->all();
         Helper::saveLog('OnlyPlay', $this->provider_db_id, json_encode($request->all()),"ENDPOINTHIT ROLLBACK");
        // $user_id = explode('TG_',$request->user_id);
        // $bet_transaction = DB::select("select game_trans_id,bet_amount, pay_amount from game_transactions where round_id = '".$request->round_id."'");
        // $bet_transaction = $bet_transaction[0];
        $game_transaction = ProviderHelper::findGameTransaction($request->ref_tx_id,'transaction_id', 1);
        $get_client_details = ProviderHelper::getClientDetails("token_id",$game_transaction->token_id);

            try{
                ProviderHelper::idenpotencyTable($request->tx_id);
            }catch(\Exception $e){
                $response = [
                    "success" =>  false,
                    "code" => 7837,
                    "message" => "Internal Error",
                ];
                return $response;
            }

            
            if ($game_transaction->win == 2) {
                return response()->json($response);
            }

            $game_details = Game::find($request->game_bundle, $this->provider_db_id);

            $win_or_lost = 4;
            $entry_id = 2;
            $income = $game_transaction->bet_amount -  $game_transaction->bet_amount ;

            ProviderHelper::updateGameTransaction($game_transaction->game_trans_id, $game_transaction->bet_amount, $income, $win_or_lost, $entry_id, "game_trans_id",$game_transaction->bet_amount);
            $game_trans_ext_id = ProviderHelper::createGameTransExtV2($game_transaction->game_trans_id, $request->tx_id, $game_transaction->round_id, $game_transaction->bet_amount, 3);
            $client_response = ClientRequestHelper::fundTransfer($get_client_details, $game_transaction->bet_amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction->game_trans_id, 'credit', "true");

            if (isset($client_response->fundtransferresponse->status->code)) {
                            
                switch ($client_response->fundtransferresponse->status->code) {
                    case '200':
                        // ProviderHelper::updateGameTransactionFlowStatus($game_transaction->game_trans_id, 5);
                        ProviderHelper::_insertOrUpdate($get_client_details->token_id, $client_response->fundtransferresponse->balance);
                        $balance = str_replace(".", "", $client_response->fundtransferresponse->balance);
                        $formatBalance = (int) $balance;
                        $response = [
                            "success" => true,
                            "balance" => $formatBalance
                        ];
                        break;
                }

                ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, $data, $response, $client_response->requestoclient, $client_response, $data);

            }

        // $response = [
        //  'success' => true,
        //  'balance' => $get_client_details->balance
        // ];
        Helper::saveLog('OnlyPlay', $this->provider_db_id, json_encode($request->all()),$response);
        return response($response,200)
                ->header('Content-Type', 'application/json');

    }

    public function createSignature(Request $request){
    	$data = "partner_id515tokenw2b7b9b6ad52d3304d40cd766ccbacf23";
    	return ProviderHelper::onlyplaySignature($data,$this->secret_key);
    }
    


}
