<?php

namespace App\Http\Controllers;


use App\Models\PlayerDetail;
use App\Models\PlayerSessionToken;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Helpers\GameTransaction;
use App\Helpers\GameSubscription;
use App\Helpers\GameRound;
use App\Helpers\Game;
use App\Helpers\ClientRequestHelper;
use App\Helpers\TransactionHelper;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Models\GameTransaction as GMT;
use App\Models\GameTransactionMDB;
use DB;

class ICGNewV2Controller extends Controller
{
    //
    private $prefix = 12;
    public function index(){
        $http = new Client();

        $response = $http->post('https://admin-stage.iconic-gaming.com/service/login', [
            'form_params' => [
                'username' => 'betrnk',
                'password' => 'betrnk168!^*',
            ],
        ]);

        return json_decode((string) $response->getBody(), true)["token"];
    }
    public function getGameList(){
        $http = new Client();

        $response = $http->get('https://admin-stage.iconic-gaming.com/service/api/v1/games?type=all&lang=zh', [
            'headers' =>[
                'Authorization' => 'Bearer '.$this->index(),
                'Accept'     => 'application/json' 
            ]
        ]);
        return json_decode((string) $response->getBody(), true);
    }
    public function gameLaunchURL(Request $request){
        if($request->has('token')&&$request->has('client_id')
        &&$request->has('client_player_id')
        &&$request->has('username')
        &&$request->has('email')
        &&$request->has('display_name')
        &&$request->has('game_code')){
            if($token=Helper::checkPlayerExist($request->client_id,$request->client_player_id,$request->username,$request->email,$request->display_name,$request->token,$request->game_code)){
                
                $game_list =$this->getGameList();
                foreach($game_list["data"] as $game){
                        if($game["productId"] == $request->game_code){
                            Helper::savePLayerGameRound($game["productId"],$token);
                            $msg = array(
                                "url" => $game["href"].'&token='.$token.'&lang=zh&home_URL=http://demo.freebetrnk.com/icgaming',
                                "game_launch" => true
                            );
                            return response($msg,200)
                            ->header('Content-Type', 'application/json');
                        }
                    }
            }
        }
        else{
            $msg = array("error"=>"Invalid input or missing input");
            return response($msg,200)
            ->header('Content-Type', 'application/json');
        }
    }
    public function authPlayer(Request $request){
        Helper::saveLog('AuthPlayer(ICG)', 2, json_encode(array("token"=>$request->token)), "test");
        if($request->has("token")){
            $client_details = ProviderHelper::getClientDetailsCache('token', $request->token);
            Helper::saveLog('AuthPlayer(ICG)', 12, json_encode(array("token"=>$request->token)), $client_details);
            if($client_details){
                $balance = round($client_details->balance*100,2);
                $msg = array(
                    "data" => array(
                        "statusCode" => 0,
                        "username" => $client_details->username,
                        "balance" => $balance,
                        "hash" => md5($this->changeSecurityCode($client_details->default_currency).$client_details->username."".$balance),
                    ),
                );
                Helper::saveLog('AuthPlayer(ICG)', 12, json_encode(array("token"=>$request->token)), $client_details);
                return response($msg,200)->header('Content-Type', 'application/json');
            }
            else{
                $msg = array(
                    "data" => array(
                        "statusCode" => 999,
                    ),
                    "error" => array(
                        "title"=> "Undefined Errors",
                        "description"=> "Undefined Errors"
                    )
                );
                return response($msg,400)->header('Content-Type', 'application/json');
            }
        }
        else{
            $msg = array(
                "data" => array(
                    "statusCode" => 1,
                ),
                "error" => array(
                    "title"=>"TOKEM_IS_NULL",
                    "description"=>"Token is nil"
                )
            );
            return response($msg,400)->header('Content-Type', 'application/json');
        }
        
    }
    public function playerDetails(Request $request){
        if($request->has("token")){
            $client_details = ProviderHelper::getClientDetailsCache('token', $request->token);
            Helper::saveLog('PlayerDetails(ICG)', 12, json_encode(array("token"=>$request->all())), $client_details);
            if($client_details){
                $balance = round($client_details->balance*100,2);
                $msg = array(
                    "data" => array(
                        "statusCode" => 0,
                        "username" => $client_details->username,
                        "balance" => $balance,
                        "hash" => md5($this->changeSecurityCode($client_details->default_currency).$client_details->username."".$balance),
                    ),
                );
                return response($msg,200)->header('Content-Type', 'application/json');
            }
            else{
                $msg = array(
                    "data" => array(
                        "statusCode" => 999,
                    ),
                    "error" => array(
                        "title"=> "Undefined Errors",
                        "description"=> "Undefined Errors"
                    )
                );
                return response($msg,400)->header('Content-Type', 'application/json');
            }
        }
        else{
            $msg = array(
                "data" => array(
                    "statusCode" => 1,
                ),
                "error" => array(
                    "title"=>"TOKEN_IS_NULL",
                    "description"=>"Token is nil"
                )
            );
            return response($msg,400)->header('Content-Type', 'application/json');
        }
    }
    public function betGame(Request $request){
        $json = json_decode($request->getContent(),TRUE);
        if($json["token"]){
            $client_details = ProviderHelper::getClientDetailsCache('token', $json["token"]);
            if($client_details){
                try{
                    ProviderHelper::idenpotencyTable($this->prefix.'_'.$json["transactionId"].'_1');
                }catch(\Exception $e){
                    $betIdempotent = GameTransactionMDB::findGameExt($json['transaction_id'], 1,'transaction_id', $client_details);
                    $balance = round($client_details->balance * 100,2);
                        if ($betIdempotent != 'false') {
                            $balance = round($client_details->balance * 100,2);
                            if ($betIdempotent->transaction_detail == "SUCCESS"){
                                $response =array(
                                    "data" => array(
                                        "statusCode"=>0,
                                        "username" => $client_details->username,
                                        "balance" =>$balance,
                                        "hash" => md5($this->changeSecurityCode($client_details->default_currency).$client_details->username."".$balance),
                                    ),
                                );
                            return response($response,400)
                            ->header('Content-Type', 'application/json');
                            }
                        }
                    $response =array(
                        "data" => array(
                            "statusCode"=>2,
                            "username" => $client_details->username,
                            "balance" =>$balance,
                            "hash" => md5($this->changeSecurityCode($client_details->default_currency).$client_details->username."".$balance),
                        ),
                        "error" => array(
                            "title"=> "Not Enough Balance",
                            "description"=>"Not Enough Balance"
                        )
                    );
                    return response($response,400)
                    ->header('Content-Type', 'application/json');
                }
                $game_details = ProviderHelper::findGameDetailsCache('game_code', $this->prefix, $json["productId"]);
                $betGametransactionExtId = ProviderHelper::idGenerate($client_details->connection_name,2);
                $game_transactionid = ProviderHelper::idGenerate($client_details->connection_name,1);
                try{
                    $fund_extra_data = [
                        'provider_name' => $game_details->provider_name
                    ];
                    $client_response = ClientRequestHelper::fundTransfer($client_details,round($json["amount"]/100,2),$game_details->game_code,$game_details->game_name,$betGametransactionExtId,$game_transactionid,"debit",false,$fund_extra_data);
                }catch(\Exception $e){
                    $gameTransactionData = array(
                        "provider_trans_id" => $json["transactionId"],
                        "token_id" => $client_details->token_id,
                        "game_id" => $game_details->game_id,
                        "round_id" => $json["roundId"],
                        "bet_amount" => round($json["amount"]/100,2),
                        "pay_amount" =>0,
                        "income" =>round($json["amount"]/100,2),
                        "win" => 2,
                        "entry_id" =>1,
                    );
                    GameTransactionMDB::createGametransactionV2($gameTransactionData,$game_transactionid,$client_details);
                    $response =array(
                        "data" => array(
                            "statusCode"=>2,
                            "username" => $client_details->username,
                            "balance" =>$client_details->balance,
                            "hash" => md5($this->changeSecurityCode($client_details->default_currency).$client_details->username."".$client_details->balance),
                        ),
                        "error" => array(
                            "title"=> "Not Enough Balance",
                            "description"=>"Not Enough Balance"
                        )
                    );
                    $betgametransactionext = array(
                        "game_trans_id" => $game_transactionid,
                        "provider_trans_id" => $json["transactionId"],
                        "round_id" => $json["roundId"],
                        "amount" => round($json["amount"]/100,2),
                        "game_transaction_type"=>1,
                        "provider_request" =>json_encode($json),
                        "mw_response" => json_encode($response),
                        "mw_request" => "FAILED",
                        "general_details" => "FAILED",
                        "client_response" => "FAILED",
                        "transaction_detail" => "FAILED",
                    );
                    Helper::saveLog('Bet Fundtransfer failed', 12, json_encode($e->getMessage().' '.$e->getLine()), "Failed Fundtransfer Hit!");
                    GameTransactionMDB::createGameTransactionExtV2($betgametransactionext,$betGametransactionExtId,$client_details);
                    return $response;
                }
                if(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "200"){
                    $balance = round($client_response->fundtransferresponse->balance * 100,2);
                    $gameTransactionData = array(
                        "provider_trans_id" => $json["transactionId"],
                        "token_id" => $client_details->token_id,
                        "game_id" => $game_details->game_id,
                        "round_id" => $json["roundId"],
                        "bet_amount" => round($json["amount"]/100,2),
                        "pay_amount" =>0,
                        "income" =>round($json["amount"]/100,2),
                        "win" => 5,
                        "entry_id" =>1,
                    );
                    GameTransactionMDB::createGametransactionV2($gameTransactionData,$game_transactionid,$client_details);
                    $response =array(
                        "data" => array(
                            "statusCode"=>0,
                            "username" => $client_details->username,
                            "balance" =>$balance,
                            "hash" => md5($this->changeSecurityCode($client_details->default_currency).$client_details->username."".$balance),
                        ),
                    );
                    $betgametransactionext = array(
                        "game_trans_id" => $game_transactionid,
                        "provider_trans_id" => $json["transactionId"],
                        "round_id" => $json["roundId"],
                        "amount" => round($json["amount"]/100,2),
                        "game_transaction_type"=>1,
                        "provider_request" =>json_encode($json),
                        "mw_response" => json_encode($response),
                        "mw_request" => json_encode($client_response->requestoclient),
                        "client_response" => json_encode($client_response),
                        "general_details" => "SUCCESS",
                        "transaction_detail" => "SUCCESS"
                    );
                    GameTransactionMDB::createGameTransactionExtV2($betgametransactionext,$betGametransactionExtId,$client_details);
                    ProviderHelper::_insertOrUpdateCache($client_details->token_id, $client_response->fundtransferresponse->balance);
                    // sleep(10);
                    return response($response,200)
                        ->header('Content-Type', 'application/json');
                }
                elseif(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "402"){
                    $balance = round(0*100,2);
                    $response =array(
                        "data" => array(
                            "statusCode"=>2,
                            "username" => $client_details->username,
                            "balance" =>$client_details->balance,
                            "hash" => md5($this->changeSecurityCode($client_details->default_currency).$client_details->username."".$client_details->balance),
                        ),
                        "error" => array(
                            "title"=> "Not Enough Balance",
                            "description"=>"Not Enough Balance"
                        )
                    );
                    try{
                        $gameTransactionData = array(
                            "provider_trans_id" => $json["transactionId"],
                            "token_id" => $client_details->token_id,
                            "game_id" => $game_details->game_id,
                            "round_id" => $json["roundId"],
                            "bet_amount" => round($json["amount"]/100,2),
                            "pay_amount" =>0,
                            "income" =>round($json["amount"]/100,2),
                            "win" => 2,
                            "entry_id" =>1,
                        );
                        GameTransactionMDB::createGametransactionV2($gameTransactionData,$game_transactionid,$client_details);
                        $betgametransactionext = array(
                            "game_trans_id" => $game_transactionid,
                            "provider_trans_id" => $json["transactionId"],
                            "round_id" => $json["roundId"],
                            "amount" => round($json["amount"]/100,2),
                            "game_transaction_type"=>1,
                            "provider_request" =>json_encode($json),
                            "mw_response" => json_encode($response),
                            "mw_request" => json_encode($client_response->requestoclient),
                            "general_details" => "FAILED",
                            "client_response" => json_encode($client_response),
                            "transaction_detail" => "FAILED",
                        );
                        GameTransactionMDB::createGameTransactionExtV2($betgametransactionext,$betGametransactionExtId,$client_details);
                        Helper::saveLog('Bet402 Try hit(ICG)', 12, json_encode($response), $client_response->fundtransferresponse->status->message);
                    }catch(\Exception $e){
                        Helper::saveLog('betGameInsuficient(ICG)', 12, json_encode($e->getMessage().' '.$e->getLine()), $client_response->fundtransferresponse->status->message);
                    }
                    return response($response,400)
                    ->header('Content-Type', 'application/json');
                }
            }
        } 
    }
    public function cancelBetGame(Request $request){
        $json = json_decode($request->getContent(),TRUE);
        // Helper::saveLog('winGame(ICG)', 2, json_encode($json), "data");
        if($json["token"]){
            $client_details = ProviderHelper::getClientDetailsCache('token', $json["token"]);
            if($client_details){
                try{
                    ProviderHelper::idenpotencyTable($this->prefix.'_'.$json["transactionId"].'_3');
                }catch(\Exception $e){
                    $msg = array(
                        "data" => array(
                            "statusCode" => 3,
                        ),
                        "error" => array(
                            "title"=> "Refund Already Exist",
                            "description"=> "Refund Already Exist"
                        )
                    );
                    return response($msg,400)->header('Content-Type', 'application/json');
                }
                $game = GameTransactionMDB::getGameTransactionByTokenAndRoundId($json["token"],$json["transactionId"],$client_details);
                if($game){
                    $winGametransactionExtId = ProviderHelper::idGenerate($client_details->connection_name,2);
                    try{
                        $game_details = Helper::getInfoPlayerGameRound($json["token"]);
                        $fund_extra_data = [
                            'provider_name' => $game_details->provider_name
                        ];
                        $client_response = ClientRequestHelper::fundTransfer($client_details,round($json["amount"]/100,2),$game_details->game_code,$game_details->game_name,$winGametransactionExtId,$game->game_trans_id,"credit",true,$fund_extra_data);    
                    }catch(\Exception $e){
                        $game_details = Helper::getInfoPlayerGameRound($json["token"]);
                        $createGametransaction = array(
                            "win" =>2,
                            "pay_amount" =>0,
                            "income" =>0,
                            "entry_id" =>2,
                        );
                        $game_transactionid = GameTransactionMDB::updateGametransaction($createGametransaction,$game->game_trans_id,$client_details);
                        $msg = array(
                            "data" => array(
                                "statusCode" => 3,
                            ),
                            "error" => array(
                                "title"=> "Refund Already Exist",
                                "description"=> "Refund Already Exist"
                            )
                        );
                        $wingametransactionext = array(
                            "game_trans_id" => $game->game_trans_id,
                            "provider_trans_id" => $json["transactionId"],
                            "round_id" =>$json["roundId"],
                            "amount" =>round($json["amount"]/100,2),
                            "game_transaction_type"=>3,
                            "provider_request" =>json_encode($json),
                            "mw_response" => json_encode($msg),
                            "mw_request" => "FAILED",
                            "general_details" => "FAILED",
                            "client_response" => "FAILED",
                            "transaction_detail" => "FAILED",
                        );
                        GameTransactionMDB::createGameTransactionExtV2($wingametransactionext,$winGametransactionExtId,$client_details);
                        // Helper::updateGameTransactionExt($winGametransactionExtId,$client_response->requestoclient,"OK",$client_response);
                        return response($msg,200)
                            ->header('Content-Type', 'application/json');
                    }
                    if(isset($client_response->fundtransferresponse->status->code) 
                    && $client_response->fundtransferresponse->status->code == "200"){
                        $balance = round($client_response->fundtransferresponse->balance,2);
                        ProviderHelper::_insertOrUpdateCache($client_details->token_id, $client_response->fundtransferresponse->balance);
                        $createGametransaction = array(
                            "win" =>4,
                            "pay_amount" =>round($json["amount"]/100,2),
                            "income" =>$game->bet_amount-round($json["amount"]/100,2),
                            "entry_id" =>2,
                        );
                        GameTransactionMDB::updateGametransactionV2($createGametransaction,$game->game_trans_id,$client_details);
                        $wingametransactionext = array(
                            "game_trans_id" => $game->game_trans_id,
                            "provider_trans_id" => $json["transactionId"],
                            "round_id" =>$json["roundId"],
                            "amount" =>round($json["amount"]/100,2),
                            "game_transaction_type"=>3,
                            "provider_request" =>json_encode($json),
                            "mw_response" => json_encode("OK"),
                            "mw_request" => json_encode($client_response->requestoclient),
                            "client_response" => json_encode($client_response),
                            "general_details" => "SUCCESS",
                            "transaction_detail" => "SUCCESS"
                        );
                        GameTransactionMDB::createGameTransactionExtV2($wingametransactionext,$winGametransactionExtId,$client_details);
                        // Helper::updateGameTransactionExt($winGametransactionExtId,$client_response->requestoclient,"OK",$client_response);
                        return response("OK",200)
                            ->header('Content-Type', 'application/json');
                    }
                    else{
                        Helper::saveLog($winGametransactionExtId, 12, json_encode(array("provider"=>$json,"client"=>$client_response)), "cancel");
                        return response("OK",200)
                                ->header('Content-Type', 'application/json');
                    }
                } 
                else{
                    Helper::saveLog('nogamecancelBetGame(ICG)', 12, json_encode($request->getContent()), "cancel");
                    return response("OK",200)
                            ->header('Content-Type', 'application/json');
                }
            }
        } 
        
    }
    public function winGame(Request $request){
        $json = json_decode($request->getContent(),TRUE);
        // Helper::saveLog('winGame(ICG)', 2, json_encode($json), "data");
        if($json["token"]){
            try{
                ProviderHelper::idenpotencyTable($this->prefix.'_'.$json["transactionId"].'_2');
            }catch(\Exception $e){
                $response =array(
                    "data" => array(
                        "statusCode"=>3,
                    ),
                    "error" => array(
                        "title"=> "Something Wrong In Parameters",
                        "description"=>"Something Wrong In Parameters"
                    )
                );
                return response($response,400)
                ->header('Content-Type', 'application/json');
            }
            $client_details = ProviderHelper::getClientDetailsCache('token', $json["token"]);
            if($client_details){
                //$game_transaction = Helper::checkGameTransaction($json["transactionId"]);
                // 
                // $win = $json["amount"] == 0 ? 0 : 1;
                // $game_details = Helper::getInfoPlayerGameRound($json["token"]);
                // $json_data = array(
                //     "transid" => $json["transactionId"],
                //     "amount" => round($json["amount"]/100,2),
                //     "roundid" => $json["roundId"],
                //     "payout_reason" => null,
                //     "win" => $win,
                // );
                $game = GameTransactionMDB::getGameTransactionByRoundId($json["roundId"],$client_details);
                if($game){
                    $game_details = ProviderHelper::findGameDetailsCache('game_code', $this->prefix, $json["productId"]);
                    $win_or_lost =round($json["amount"]/100,2) == 0 && $game->pay_amount == 0 ? 0 : 1;
                    $winGametransactionExtId = ProviderHelper::idGenerate($client_details->connection_name,2);
                    
                    $pay_amount = $game->pay_amount+round($json["amount"]/100,2);
                    $income = $game->income - round($json["amount"]/100,2);
                    $entry_id = round($json["amount"]/100,2) == 0 && $game->pay_amount == 0 ? 1 : 2;
                    try{
                        $balanceTOsave = $client_details->balance;
                        $response =array(
                            "data" => array(
                                "statusCode"=>0,
                                "username" => $client_details->username,
                                "balance" =>$balanceTOsave,
                                "hash" => md5($this->changeSecurityCode($client_details->default_currency).$client_details->username."".$balanceTOsave),
                            ),
                        );
                        $action_payload = [
                            "type" => "custom", #genreral,custom :D # REQUIRED!
                            "custom" => [
                                "provider" => 'icg',
                                "game_transaction_ext_id" => $winGametransactionExtId,
                                "client_connection_name" => $client_details->connection_name,
                                "win_or_lost" => $win_or_lost,
                                "pay_amount" => $pay_amount,
                                "income" => $income,
                                "entry_id" => $entry_id,
                            ],
                            "provider" => [
                                "provider_request" => $json, #R
                                "provider_trans_id"=>$json["transactionId"], #R
                                "provider_round_id"=>$json["roundId"], #R
                                'provider_name' => $game_details->provider_name
                            ],
                            "mwapi" => [
                                "roundId"=>$game->game_trans_id, #R
                                "type"=>2, #R
                                "game_id" => $game_details->game_id, #R
                                "player_id" => $client_details->player_id, #R
                                "mw_response" => $response, #R
                            ]
                        ];
                        $client_response = ClientRequestHelper::fundTransfer_TG($client_details,round($json["amount"]/100,2),$game_details->game_code,$game_details->game_name,$game->game_trans_id,'credit',false,$action_payload);
                    }catch(\Exception $e){
                        $createGametransaction = array(
                            "win" => 2,
                            "pay_amount" =>$game->pay_amount+round($json["amount"]/100,2),
                            "income" =>$game->income - round($json["amount"]/100,2),
                            "entry_id" =>round($json["amount"]/100,2) == 0 && $game->pay_amount == 0 ? 1 : 2,
                        );
                        $game_transactionid = GameTransactionMDB::updateGametransaction($createGametransaction,$game->game_trans_id,$client_details);
                        $balanceTOsave = $client_details->balance;
                        $response =array(
                            "data" => array(
                                "statusCode"=>0,
                                "username" => $client_details->username,
                                "balance" =>$balanceTOsave,
                                "hash" => md5($this->changeSecurityCode($client_details->default_currency).$client_details->username."".$balanceTOsave),
                            ),
                        );
                        $wingametransactionext = array(
                            "game_trans_id" => $game->game_trans_id,
                            "provider_trans_id" => $json["transactionId"],
                            "round_id" => $json["roundId"],
                            "amount" => round($json["amount"]/100,2),
                            "game_transaction_type"=>2,
                            "provider_request" =>json_encode($json),
                            "mw_response" => json_encode($response),
                            "mw_request" => "FAILED",
                            "client_response" => "FAILED",
                            "transaction_detail" => "FAILED",
                        );
                        GameTransactionMDB::createGameTransactionExtV2($wingametransactionext,$winGametransactionExtId,$client_details);
                        return response($response,400)
                        ->header('Content-Type', 'application/json');
                    }
                    if(isset($client_response->fundtransferresponse->status->code) 
                    && $client_response->fundtransferresponse->status->code == "200"){
                        $balance = round($client_response->fundtransferresponse->balance * 100,2);
                        ProviderHelper::_insertOrUpdateCache($client_details->token_id, $client_response->fundtransferresponse->balance);
                        $response =array(
                            "data" => array(
                                "statusCode"=>0,
                                "username" => $client_details->username,
                                "balance" =>$balance,
                                "hash" => md5($this->changeSecurityCode($client_details->default_currency).$client_details->username."".$balance),
                            ),
                        );
                        $wingametransactionext = array(
                            "game_trans_id" => $game->game_trans_id,
                            "provider_trans_id" => $json["transactionId"],
                            "round_id" => $json["roundId"],
                            "amount" => round($json["amount"]/100,2),
                            "game_transaction_type"=>2,
                            "provider_request" =>json_encode($json),
                            "mw_response" => json_encode($response),
                            "mw_request" => json_encode($client_response->requestoclient),
                            "client_response" => json_encode($client_response),
                            "general_details" => "SUCCESS",
                            "transaction_detail" => "SUCCESS"
                        );
                        GameTransactionMDB::createGameTransactionExtV2($wingametransactionext,$winGametransactionExtId,$client_details);
                        //Helper::updateICGGameTransactionExt($transactionId,$client_response->requestoclient,$response,$client_response);  
                        sleep(10);
                        return response($response,200)
                            ->header('Content-Type', 'application/json');
                    }
                    elseif(isset($client_response->fundtransferresponse->status->code) 
                    && $client_response->fundtransferresponse->status->code == "402"){
                        $balance = round($client_response->fundtransferresponse->balance * 100,2);
                        $response =array(
                            "data" => array(
                                "statusCode"=>1,
                                "username" => $client_details->username,
                                "balance" =>$balance,
                            ),
                            "error" => array(
                                "title"=> "Not Enough Balance",
                                "description"=>"Not Enough Balance"
                            )
                        ); 
                        $betgametransactionext = array(
                            "game_trans_id" => $game_transactionid,
                            "provider_trans_id" => $json["transactionId"],
                            "round_id" => $json["roundId"],
                            "amount" => round($json["amount"]/100,2),
                            "game_transaction_type"=>2,
                            "provider_request" =>json_encode($json),
                            "mw_response" => json_encode($response),
                            "mw_request" => json_encode($client_response->requestoclient),
                            "general_details" => "FAILED",
                            "client_response" => json_encode($client_response),
                            "transaction_detail" => "FAILED",
                        );
                        GameTransactionMDB::createGameTransactionExtV2($betgametransactionext,$betGametransactionExtId,$client_details);
                        $gametransactiondata = array(
                            "win" => 2,
                            "pay_amount" => 0,
                            "income" => $game->bet_amount,
                        );
                        GameTransactionMDB::updateGametransaction($gametransactiondata,$game->game_trans_id,$client_details);
                        return response($response,400)
                        ->header('Content-Type', 'application/json');
                    }
                    else{
                        $balance = round($client_response->fundtransferresponse->balance * 100,2);
                        $response =array(
                            "data" => array(
                                "statusCode"=>1,
                                "username" => $client_details->username,
                                "balance" =>$balance,
                            ),
                            "error" => array(
                                "title"=> "Not Enough Balance",
                                "description"=>"Not Enough Balance"
                            )
                        ); 
                        $betgametransactionext = array(
                            "game_trans_id" => $game_transactionid,
                            "provider_trans_id" => $json["transactionId"],
                            "round_id" => $json["roundId"],
                            "amount" => round($json["amount"]/100,2),
                            "game_transaction_type"=>2,
                            "provider_request" =>json_encode($json),
                            "mw_response" => json_encode($response),
                            "mw_request" => json_encode($client_response->requestoclient),
                            "general_details" => "FAILED",
                            "client_response" => json_encode($client_response),
                            "transaction_detail" => "FAILED",
                        );
                        GameTransactionMDB::createGameTransactionExtV2($betgametransactionext,$betGametransactionExtId,$client_details);
                        $gametransactiondata = array(
                            "win" => 2,
                            "pay_amount" => 0,
                            "income" => $game->bet_amount,
                        );
                        GameTransactionMDB::updateGametransaction($gametransactiondata,$game->game_trans_id,$client_details);
                        return response($response,400)
                        ->header('Content-Type', 'application/json');
                    }
                }else{
                    $response =array(
                        "data" => array(
                            "statusCode"=>3,
                        ),
                        "error" => array(
                            "title"=> "Something Wrong In Parameters",
                            "description"=>"Something Wrong In Parameters"
                        )
                    );
                    return response($response,400)
                    ->header('Content-Type', 'application/json');
                }
            }
        } 
    }
    private function _getClientDetails($type = "", $value = "") {

        $query = DB::table("clients AS c")
                 ->select('p.client_id', 'p.player_id', 'p.client_player_id','p.username', 'p.email', 'p.language', 'p.currency', 'pst.token_id', 'pst.player_token' , 'pst.status_id', 'p.display_name','c.default_currency', 'c.client_api_key', 'cat.client_token AS client_access_token', 'ce.player_details_url', 'ce.fund_transfer_url')
                 ->leftJoin("players AS p", "c.client_id", "=", "p.client_id")
                 ->leftJoin("player_session_tokens AS pst", "p.player_id", "=", "pst.player_id")
                 ->leftJoin("client_endpoints AS ce", "c.client_id", "=", "ce.client_id")
                 ->leftJoin("client_access_tokens AS cat", "c.client_id", "=", "cat.client_id");
                 
                if ($type == 'token') {
                    $query->where([
                        ["pst.player_token", "=", $value],
                        ["pst.status_id", "=", 1]
                    ]);
                }

                if ($type == 'player_id') {
                    $query->where([
                        ["p.player_id", "=", $value],
                        ["pst.status_id", "=", 1]
                    ]);
                }

                 $result= $query->first();

        return $result;

    }
    public function withdraw(Request $request){
        Helper::saveLog('withdrawReq(ICG)', 12, $request->getContent(), "");
        $json = json_decode($request->getContent(),TRUE);
        if($json["token"]){
            try{
                ProviderHelper::idenpotencyTable($this->prefix.'_'.$json["transactionId"].'_2');
            }catch(\Exception $e){
                $response =array(
                    "data" => array(
                        "statusCode"=>3,
                    ),
                    "error" => array(
                        "title"=> "Something Wrong In Parameters",
                        "description"=>"Something Wrong In Parameters"
                    )
                );
                return response($response,400)
                ->header('Content-Type', 'application/json');
            }
            $client_details = ProviderHelper::getClientDetailsCache('token', $json["token"]);
            if($client_details){
                //$game_transaction = Helper::checkGameTransaction($json["transactionId"]);
                $game = GameTransactionMDB::getGameTransactionByTokenAndRoundId($request->token,0,$client_details);
                if($game){
                    $game_details = Helper::getInfoPlayerGameRound($json["token"]);
                    $win_or_lost = round($json["amount"]/100,2) == 0 && $game->pay_amount == 0 ? 0 : 1;
                    $balanceTOsave = $client_details->balance +round($json["amount"]/100,2);
                    $winGametransactionExtId = ProviderHelper::idGenerate($client_details->connection_name,2);
                    try{
                        $response =array(
                            "data" => array(
                                "statusCode"=>0,
                                "username" => $client_details->username,
                                "balance" =>$balanceTOsave,
                                "hash" => md5($this->changeSecurityCode($client_details->default_currency).$client_details->username."".$balanceTOsave),
                            ),
                        );
                        $action_payload = [
                            "type" => "custom", #genreral,custom :D # REQUIRED!
                            "custom" => [
                                "provider" => 'icg',
                                "game_transaction_ext_id" => $winGametransactionExtId,
                                "client_connection_name" => $client_details->connection_name,
                                "win_or_lost" => $win_or_lost,
                                "pay_amount" => $game->pay_amount+round($json["amount"]/100,2),
                                "income" => $game->income - round($json["amount"]/100,2),
                                "entry_id" => round($json["amount"]/100,2) == 0 && $game->pay_amount == 0 ? 1 : 2,
                            ],
                            "provider" => [
                                "provider_request" => $json, #R
                                "provider_trans_id"=>$json["transactionId"], #R
                                "provider_round_id"=>0, #R
                                'provider_name' => $game_details->provider_name
                            ],
                            "mwapi" => [
                                "roundId"=>$game->game_trans_id, #R
                                "type"=>2, #R
                                "game_id" => $game_details->game_id, #R
                                "player_id" => $client_details->player_id, #R
                                "mw_response" => $response, #R
                            ]
                        ];
                        $client_response = ClientRequestHelper::fundTransfer_TG($client_details,round($json["amount"]/100,2),$game_details->game_code,$game_details->game_name,$game->game_trans_id,'credit',false,$action_payload);
                    }catch(\Exception $e){
                        $createGametransaction = array(
                            "win" => 2,
                            "pay_amount" =>$game->pay_amount+round($json["amount"]/100,2),
                            "income" =>$game->income - round($json["amount"]/100,2),
                            "entry_id" =>round($json["amount"]/100,2) == 0 && $game->pay_amount == 0 ? 1 : 2,
                        );
                        $game_transactionid = GameTransactionMDB::updateGametransaction($createGametransaction,$game->game_trans_id,$client_details);
                        $balanceTOsave = $client_details->balance;
                        $response =array(
                            "data" => array(
                                "statusCode"=>0,
                                "username" => $client_details->username,
                                "balance" =>$balanceTOsave,
                                "hash" => md5($this->changeSecurityCode($client_details->default_currency).$client_details->username."".$balanceTOsave),
                            ),
                        );
                        $wingametransactionext = array(
                            "game_trans_id" => $game->game_trans_id,
                            "provider_trans_id" => $json["transactionId"],
                            "round_id" => 0,
                            "amount" => round($json["amount"]/100,2),
                            "game_transaction_type"=>2,
                            "provider_request" =>json_encode($json),
                            "mw_response" => json_encode($response),
                            "mw_request" => "FAILED",
                            "client_response" => "FAILED",
                            "transaction_detail" => "FAILED",
                        );
                        GameTransactionMDB::createGameTransactionExtV2($wingametransactionext,$winGametransactionExtId,$client_details);

                    }
                    if(isset($client_response->fundtransferresponse->status->code) 
                    && $client_response->fundtransferresponse->status->code == "200"){
                        $balance = round($client_response->fundtransferresponse->balance * 100,2);
                        ProviderHelper::_insertOrUpdateCache($client_details->token_id, $client_response->fundtransferresponse->balance);
                        $response =array(
                            "data" => array(
                                "statusCode"=>0,
                                "username" => $client_details->username,
                                "balance" =>$balance,
                                "hash" => md5($this->changeSecurityCode($client_details->default_currency).$client_details->username."".$balance),
                            ),
                        );
                        $wingametransactionext = array(
                            "game_trans_id" => $game->game_trans_id,
                            "provider_trans_id" => $json["transactionId"],
                            "round_id" => 0,
                            "amount" => round($json["amount"]/100,2),
                            "game_transaction_type"=>2,
                            "provider_request" =>json_encode($json),
                            "mw_response" => json_encode($response),
                            "mw_request" => json_encode($client_response->requestoclient),
                            "client_response" => json_encode($client_response),
                            "general_details" => "SUCCESS",
                            "transaction_detail" => "SUCCESS"
                        );
                        GameTransactionMDB::createGameTransactionExtV2($wingametransactionext,$winGametransactionExtId,$client_details);
                        //Helper::updateICGGameTransactionExt($transactionId,$client_response->requestoclient,$response,$client_response);  
                        return response($response,200)
                            ->header('Content-Type', 'application/json');
                    }
                }
                elseif(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "402"){
                    $balance = round($client_response->fundtransferresponse->balance * 100,2);
                    $response =array(
                        "data" => array(
                            "statusCode"=>1,
                            "username" => $client_details->username,
                            "balance" =>$balance,
                        ),
                        "error" => array(
                            "title"=> "Not Enough Balance",
                            "description"=>"Not Enough Balance"
                        )
                    ); 
                    $betgametransactionext = array(
                        "game_trans_id" => $game->game_trans_id,
                        "provider_trans_id" => $json["transactionId"],
                        "round_id" => 0,
                        "amount" => round($json["amount"]/100,2),
                        "game_transaction_type"=>2,
                        "provider_request" =>json_encode($json),
                        "mw_response" => json_encode($response),
                        "mw_request" => json_encode($client_response->requestoclient),
                        "general_details" => "FAILED",
                        "client_response" => json_encode($client_response),
                        "transaction_detail" => "FAILED",
                    );
                    GameTransactionMDB::createGameTransactionExtV2($betgametransactionext,$betGametransactionExtId,$client_details);
                    $gametransactiondata = array(
                        "win" => 2,
                        "pay_amount" => 0,
                        "income" => $game->bet_amount,
                    );
                    GameTransactionMDB::updateGametransactionV2($gametransactiondata,$game->game_trans_id,$client_details);
                    return response($response,400)
                    ->header('Content-Type', 'application/json');
                }
                // $win = $json["amount"] == 0 ? 0 : 1;
                // $game_details = Helper::getInfoPlayerGameRound($json["token"]);
                // $json_data = array(
                //     "transid" => $json["transactionId"],
                //     "amount" => round($json["amount"]/100,2),
                //     "roundid" => 0,
                //     "payout_reason" => null,
                //     "win" => $win,
                // );
                // $game = TransactionHelper::getGameTransaction($request->token,0);
                // if(!$game){
                //     $gametransactionid=Helper::createGameTransaction('credit', $json_data, $game_details, $client_details); 
                // }
                // else{
                //     $gameupdate = TransactionHelper::updateGameTransaction($game,$json_data,"credit");
                //     $gametransactionid = $game[0]->game_trans_id;
                // }
                // $transactionId=Helper::createICGGameTransactionExt($gametransactionid,$json,null,null,null,2);
                // $client_response = ClientRequestHelper::fundTransfer($client_details,round($json["amount"]/100,2),$game_details->game_code,$game_details->game_name,$transactionId,$gametransactionid,"credit");
                // $balance = round($client_response->fundtransferresponse->balance * 100,2);
                
                // if(isset($client_response->fundtransferresponse->status->code) 
                // && $client_response->fundtransferresponse->status->code == "200"){
                    
                //     $response =array(
                //         "data" => array(
                //             "statusCode"=>0,
                //             "username" => $client_details->username,
                //             "balance" =>$balance,
                //             "hash" => md5($this->changeSecurityCode($client_details->default_currency).$client_details->username."".$balance),
                //         ),
                //     );
                //     Helper::updateICGGameTransactionExt($transactionId,$client_response->requestoclient,$response,$client_response);
                //     return response($response,200)
                //         ->header('Content-Type', 'application/json');
                // }
                // elseif(isset($client_response->fundtransferresponse->status->code) 
                // && $client_response->fundtransferresponse->status->code == "402"){
                //     $response =array(
                //         "data" => array(
                //             "statusCode"=>1,
                //             "username" => $client_details->username,
                //             "balance" =>$balance,
                //         ),
                //         "error" => array(
                //             "title"=> "Not Enough Balance",
                //             "description"=>"Not Enough Balance"
                //         )
                //     ); 
                //     return response($response,400)
                //     ->header('Content-Type', 'application/json');
                // }
                

            }
        }
       
    }
    public function deposit(Request $request){
        $json = json_decode($request->getContent(),TRUE);
        if($json["token"]){
            $client_details = ProviderHelper::getClientDetailsCache('token', $json["token"]);
            if($client_details){
                try{
                    ProviderHelper::idenpotencyTable($this->prefix.'_'.$json["transactionId"].'_1');
                }catch(\Exception $e){
                    $response =array(
                        "data" => array(
                            "statusCode"=>3,
                        ),
                        "error" => array(
                            "title"=> "Something Wrong In Parameters",
                            "description"=>"Something Wrong In Parameters"
                        )
                    );
                    return response($response,400)
                    ->header('Content-Type', 'application/json');
                }
                $game_details = Helper::getInfoPlayerGameRound($json["token"]);
                $game_transactionid = ProviderHelper::idGenerate($client_details->connection_name,1);
                $betGametransactionExtId = ProviderHelper::idGenerate($client_details->connection_name,2);
                try {
                    $fund_extra_data = [
                        'provider_name' => $game_details->provider_name
                    ];
                    $client_response = ClientRequestHelper::fundTransfer($client_details,round($json["amount"]/100,2),$game_details->game_code,$game_details->game_name,$betGametransactionExtId,$game_transactionid,"debit",false,$fund_extra_data);
                }catch(\Exception $e){
                    $gameTransactionData = array(
                        "provider_trans_id" => $json["transactionId"],
                        "token_id" => $client_details->token_id,
                        "game_id" => $game_details->game_id,
                        "round_id" => $json["roundId"],
                        "bet_amount" => round($json["amount"]/100,2),
                        "pay_amount" =>0,
                        "income" =>round($json["amount"]/100,2),
                        "win" => 2,
                        "entry_id" =>1,
                    );
                    GameTransactionMDB::createGametransactionV2($gameTransactionData,$game_transactionid,$client_details);
                    $response =array(
                        "data" => array(
                            "statusCode"=>2,
                            "username" => $client_details->username,
                            "balance" =>$client_details->balance,
                            "hash" => md5($this->changeSecurityCode($client_details->default_currency).$client_details->username."".$client_details->balance),
                        ),
                        "error" => array(
                            "title"=> "Not Enough Balance",
                            "description"=>"Not Enough Balance"
                        )
                    );
                    $betgametransactionext = array(
                        "game_trans_id" => $game_transactionid,
                        "provider_trans_id" => $json["transactionId"],
                        "round_id" => $json["roundId"],
                        "amount" => round($json["amount"]/100,2),
                        "game_transaction_type"=>1,
                        "provider_request" =>json_encode($json),
                        "mw_response" => json_encode($response),
                        "mw_request" => "FAILED",
                        "general_details" => "FAILED",
                        "client_response" => "FAILED",
                        "transaction_detail" => "FAILED",
                    );
                    Helper::saveLog('Bet Fundtransfer failed', 12, json_encode($e->getMessage().' '.$e->getLine()), "Failed Fundtransfer Hit!");
                    GameTransactionMDB::createGameTransactionExtV2($betgametransactionext,$betGametransactionExtId,$client_details);
                    return $response;

                }
                if(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "200"){
                    $balance = round($client_response->fundtransferresponse->balance * 100,2);
                    ProviderHelper::_insertOrUpdateCache($client_details->token_id, $client_response->fundtransferresponse->balance);
                    $gameTransactionData = array(
                        "provider_trans_id" => $json["transactionId"],
                        "token_id" => $client_details->token_id,
                        "game_id" => $game_details->game_id,
                        "round_id" => 0,
                        "bet_amount" => round($json["amount"]/100,2),
                        "win" => 5,
                        "pay_amount" =>0,
                        "income" =>0,
                        "entry_id" =>1,
                    );
                    GameTransactionMDB::createGametransactionV2($gameTransactionData,$game_transactionid,$client_details);
                    $response =array(
                        "data" => array(
                            "statusCode"=>0,
                            "username" => $client_details->username,
                            "balance" =>$balance,
                            "hash" => md5($this->changeSecurityCode($client_details->default_currency).$client_details->username."".$balance),
                        ),
                    );
                    $betgametransactionext = array(
                        "game_trans_id" => $game_transactionid,
                        "provider_trans_id" => $json["transactionId"],
                        "round_id" => 0,
                        "amount" => round($json["amount"]/100,2),
                        "game_transaction_type"=>1,
                        "provider_request" =>json_encode($json),
                        "mw_response" => json_encode($response),
                        "mw_request" => json_encode($client_response->requestoclient),
                        "client_response" => json_encode($client_response),
                        "general_details" => "SUCCESS",
                        "transaction_detail" => "SUCCESS"
                    );
                    GameTransactionMDB::createGameTransactionExtV2($betgametransactionext,$betGametransactionExtId,$client_details);
                    return response($response,200)
                        ->header('Content-Type', 'application/json');
                }
                elseif(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "402"){
                    $response =array(
                        "data" => array(
                            "statusCode"=>2,
                            "username" => $client_details->username,
                            "balance" =>1,
                        ),
                        "error" => array(
                            "title"=> "Not Enough Balance",
                            "description"=>"Not Enough Balance"
                        )
                    ); 
                    try{
                        $gameTransactionData = array(
                            "provider_trans_id" => $json["transactionId"],
                            "token_id" => $client_details->token_id,
                            "game_id" => $game_details->game_id,
                            "round_id" => 0,
                            "bet_amount" => round($json["amount"]/100,2),
                            "win" => 2,
                            "pay_amount" =>0,
                            "income" =>0,
                            "entry_id" =>1,
                        );
                        GameTransactionMDB::createGametransactionV2($gameTransactionData,$game_transactionid,$client_details);
                        $betgametransactionext = array(
                            "game_trans_id" => $game_transactionid,
                            "provider_trans_id" => $json["transactionId"],
                            "round_id" => 0,
                            "amount" => round($json["amount"]/100,2),
                            "game_transaction_type"=>1,
                            "provider_request" =>json_encode($json),
                            "mw_response" => json_encode($response),
                            "mw_request" => json_encode($client_response->requestoclient),
                            "client_response" => json_encode($client_response),
                            "general_details" => "FAILED",
                            "transaction_detail" => "FAILED"
                        );
                        GameTransactionMDB::createGameTransactionExtV2($betgametransactionext,$betGametransactionExtId,$client_details);
                        //Helper::updateICGGameTransactionExt($betGametransactionExtId,$client_response->fundtransferresponse->status->message,$response,'FAILED');
                    }catch(\Exception $e){
                        Helper::saveLog('betGameInsuficient(ICG)', 12, json_encode($e->getMessage().' '.$e->getLine()), $client_response->fundtransferresponse->status->message);
                    }
                    return response($response,400)
                    ->header('Content-Type', 'application/json');
                }
                

            }
            else{
                $response =array(
                    "data" => array(
                        "statusCode"=>3,
                    ),
                    "error" => array(
                        "title"=> "Something Wrong In Parameters",
                        "description"=>"Something Wrong In Parameters"
                    )
                ); 
                return response($response,400)
                ->header('Content-Type', 'application/json');
            }
        } 
    }
    public function changeSecurityCode($currency){
        if($currency == "USD"){
            return config("providerlinks.icgagents.usdagents.secure_code");
        }
        elseif($currency == "JPY"){
            return config("providerlinks.icgagents.jpyagents.secure_code");
        }
        elseif($currency == "CNY"){
            return config("providerlinks.icgagents.cnyagents.secure_code");
        }
        elseif($currency == "EUR"){
            return config("providerlinks.icgagents.euragents.secure_code");
        }
        elseif($currency == "KRW"){
            return config("providerlinks.icgagents.krwagents.secure_code");
        }
        elseif($currency == "PHP"){
            return config("providerlinks.icgagents.phpagents.secure_code");
        }
        elseif($currency == "THB"){
            return config("providerlinks.icgagents.thbagents.secure_code");
        }
        elseif($currency == "TRY"){
            return config("providerlinks.icgagents.tryagents.secure_code");
        }
        elseif($currency == "TWD"){
            return config("providerlinks.icgagents.twdagents.secure_code");
        }
        elseif($currency == "VND"){
            return config("providerlinks.icgagents.vndagents.secure_code");
        }
        elseif($currency == "RUB"){
            return config("providerlinks.icgagents.rubagents.secure_code");
        }
        elseif($currency == "IRR"){
            return config("providerlinks.icgagents.irragents.secure_code");
        }
        elseif($currency == "MMK"){
            return config("providerlinks.icgagents.mmkagents.secure_code");
        }
    }
}
