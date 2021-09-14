<?php

namespace App\Http\Controllers;

use App\Helpers\ClientRequestHelper;
use Illuminate\Http\Request;
use App\Helpers\ProviderHelper;
use DB;
use App\Helpers\Helper;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use App\Helpers\AWSHelper;
use App\Models\GameTransactionMDB;

class HabaneroController extends Controller
{
    
    public $passkey ;
    public $provider_id = 24; //provider_id from database
    public function __construct(){
    	$this->passkey = config('providerlinks.habanero.passKey');
    }

    public static function sessionExpire($token){
		$token = DB::table('player_session_tokens')
			        ->select("*", DB::raw("NOW() as IMANTO"))
			    	->where('player_token', $token)
			    	->first();
		if($token != null){
			$check_token = DB::table('player_session_tokens')
			->selectRaw("TIME_TO_SEC(TIMEDIFF( NOW(), '".$token->created_at."'))/60 as `time`")
			->first();
		    if(1440 > $check_token->time) {  // TIMEGAP IN MINUTES!
		        $token = true; // True if Token can still be used!
		    }else{
		    	$token = false; // Expired Token
		    }
		}else{
			$token = false; // Not Found Token
		}
	    return $token;
	}

    public function playerdetailrequest(Request $request){
        $data = file_get_contents("php://input");
        $details = json_decode($data);
        Helper::saveLog('HBN playerdetailrequest', 24, json_encode($details), "RECIEVED");
        $client_details = Providerhelper::getClientDetails('token', $details->playerdetailrequest->token);
        if($client_details == null) {
            $response = [
                "playerdetailresponse" => [
                    "status" => [
                        "success" => false,
                        "message" => "Player does not exist"
                    ]
                ]
            ];
            Helper::saveLog('HBN player not exist', 24, json_encode($details), $response);
            return $response;
        }else{
            try{

                $response = [
                    "playerdetailresponse" => [
                        "status" => [
                            "success" => true,
                            "autherror" => false,
                            "message" => ""
                        ],
                        "accountid" => $client_details->player_id,
                        "accountname" => $client_details->username,
                        "balance" => floatval(number_format($client_details->balance, 2, '.', '')),
                        "currencycode" => $client_details->default_currency
                    ]
                ];
                if($details->playerdetailrequest->gamelaunch == true):
                    AWSHelper::saveLog("Habanero Request", $this->provider_id, json_encode($details,JSON_FORCE_OBJECT),"Recieved");
                endif;
                Helper::saveLog('HBN Response BALANCE' , 24, json_encode($details), $response);
                return $response;
            }catch(\Exception $e){
                $msg = array(
                    'message' => $e->getMessage(),
                );
                Helper::saveLog('HBN auth error', $this->provider_id, json_encode($details,JSON_FORCE_OBJECT), $msg);
                return json_encode($msg, JSON_FORCE_OBJECT); 
            }
        }
    }


    
    public function fundtransferrequest(Request $request){
        $data = file_get_contents("php://input");
        $details = json_decode($data);
        AWSHelper::saveLog("Habanero Request", $this->provider_id, json_encode($details,JSON_FORCE_OBJECT),"Recieved");
        $client_details = Providerhelper::getClientDetails('token', $details->fundtransferrequest->token);
        $game_details = Helper::findGameDetails('game_code', $this->provider_id, $details->basegame->keyname);
       
        $checktoken = $this->sessionExpire($client_details->player_token);
        if($details->auth->passkey != $this->passkey){
            $response = [
                "fundtransferresponse" => [
                    "status" => [
                        "success" => false,
                        "message" => "Passkey don't match!"
                    ]
                ]
            ];
            Helper::saveLog('HBN trans passkey', 24, json_encode($details), $response);
            return $response;
        }
        if($checktoken == false){ //session check
            $response = [
                "fundtransferresponse" => [
                    "status" => [
                        "success" => false,
                        "autherror" => true,
                    ]
                ]
            ];
            Helper::saveLog('HBN trans session', 24, json_encode($details), $response);
            return $response;
        }
        
        $round_id = $details->fundtransferrequest->gameinstanceid;
        if(isset($details->fundtransferrequest->funds->debitandcredit)){
            $data = $details->fundtransferrequest->funds->fundinfo;
            if(isset($data->{0})){
                $data = $data->{0};
            }elseif(isset($data[0])){
                $data = $data[0];
            }
            if($details->fundtransferrequest->isretry == true ){

                if($details->fundtransferrequest->isrefund == true){
                    $refund_data = $details->fundtransferrequest->funds->refund;
                    $checkRefundStatus = DB::table('game_transaction_ext')->where('round_id',$round_id)->get();
                    return $this->refund($details,$refund_data,$checkRefundStatus[0],$client_details->player_token,$game_details,$round_id);
                }
            
                if($details->fundtransferrequest->isrecredit == true){
                    return $this->reCredit($details,$data,$client_details->player_token,$game_details,$round_id);
                }
            }
            
            $findGameTransactionDetails = GameTransactionMDB::findGameTransactionDetails($data->transferid,'transaction_id',false,$client_details);
            if($details->fundtransferrequest->funds->debitandcredit == true){
                if($findGameTransactionDetails != 'false' ){
                    $response = [
                        "fundtransferresponse" => [
                            "status" => [
                                "success" => true,
                                "successdebit" => true,
                                "successcredit" => true
                            ],
                            "balance" => floatval(number_format($client_details->balance, 2, '.', '')),
                            "currencycode" => $client_details->default_currency,
                            ]
                    ];
                    Helper::saveLog('HBN trans duplicate', 24, json_encode($details), $response);
                    return $response;
                }
                $data = $details->fundtransferrequest->funds->fundinfo;
                return  $this->debitAndCredit($details,$data,$client_details,$game_details,$round_id);
            }
            if($details->fundtransferrequest->funds->debitandcredit == false){
                if($findGameTransactionDetails != 'false' ){
                    $response = [
                        "fundtransferresponse" => [
                            "status" => [
                                "success" => true,
                            ],
                            "balance" => floatval(number_format($client_details->balance, 2, '.', '')),
                            "currencycode" => $client_details->default_currency,
                        ]
                    ];
                    return $response;
                }
                if($data->gamestatemode == 1){
                    $trans = GameTransactionMDB::findGameTransactionDetails($round_id,'round_id',false,$client_details);
                    if($trans != 'false'){
                        return $this->newBet($details,$data,$client_details,$trans,$game_details,$round_id);
                    }else{
                        return $this->tableBet($details,$data,$client_details,$game_details,$round_id);
                    }
                }
                if($data->gamestatemode == 0){
                    $trans = GameTransactionMDB::findGameTransactionDetails($round_id,'round_id',false,$client_details);
                    if($data->gameinfeature == true){
                        return $this->newCredit($data,$client_details->player_token,$trans,$game_details,$round_id);
                    }else{
                        return $this->newBet($details,$data,$client_details,$trans,$game_details,$round_id);
                    }
                }
                if($data->gamestatemode == 2){
                    $trans = GameTransactionMDB::findGameTransactionDetails($round_id,'round_id',false,$client_details);
                    if($data->gameinfeature == true){
                        return $this->newCredit($data,$client_details->player_token,$trans,$game_details,$round_id);
                    }else{
                        return $this->newCredit($data,$client_details->player_token,$trans,$game_details,$round_id);
                    }
                }
                
            }
        }

    }
 
    public function debitAndCredit($details,$data,$client_details,$game_details,$round_id)
    {   
        AWSHelper::saveLog("Habanero Request debitAndCredit", $this->provider_id, json_encode($data,JSON_FORCE_OBJECT),"debit credit");
        if(isset($data->{0})){$bet_details = $data->{0};}elseif(isset($data[0])){$bet_details = $data[0];}
        if($bet_details->gamestatemode == 1){$bet_amount = abs($bet_details->amount);$transferid = $bet_details->transferid;}
        $gameTransactionData = array(
            "provider_trans_id" => $transferid,
            "token_id" => $client_details->token_id,
            "game_id" => $game_details->game_id,
            "round_id" => $round_id,
            "bet_amount" => $bet_amount,
            "win" => 5,
            "pay_amount" => 0,
            "income" => 0,
            "entry_id" =>1,
            "trans_status" =>1,
        );
        $gamerecord = GameTransactionMDB::createGametransaction($gameTransactionData,$client_details);
        foreach($data as $item){
            if($item->gamestatemode == 1){
                $bet = $item;
                AWSHelper::saveLog("Habanero Request bet ", $this->provider_id, json_encode($data,JSON_FORCE_OBJECT),"BET BET BET");
                $this->bet($details,$bet,$client_details,$game_details,$round_id,$gamerecord);
            }
            if($item->gamestatemode == 2){
                $win = $item;
                AWSHelper::saveLog("Habanero Request win ", $this->provider_id, json_encode($data,JSON_FORCE_OBJECT),"WIN WIN WIN");
                return $this->win($win,$client_details->player_token,$game_details,$round_id,$gamerecord,$bet_amount);
            }
            if($item->gamestatemode == 0){
                $win = $item;
                AWSHelper::saveLog("Habanero Request win ", $this->provider_id, json_encode($data,JSON_FORCE_OBJECT),"WIN WIN WIN");
                return $this->win($win,$client_details->player_token,$game_details,$round_id,$gamerecord,$bet_amount);
            }
            
        }
    }

    public function bet($details,$data,$client_details,$game_details,$round_id,$gamerecord){
        AWSHelper::saveLog("Habanero Request BET", $this->provider_id, json_encode($data,JSON_FORCE_OBJECT),"bet");
        $gameTransactionEXTData = array(
            "game_trans_id" => $gamerecord,
            "provider_trans_id" => $data->transferid,
            "round_id" => $round_id,
            "amount" => $data->amount,
            "game_transaction_type"=> 1,
            "provider_request" =>json_encode($details),
        );
        $game_trans_ext = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
        try{
            $client_response = ClientRequestHelper::fundTransfer($client_details, abs($data->amount), $game_details->game_code, $game_details->game_name, $game_trans_ext, $gamerecord, 'debit');
            if(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "200"){
                    $response = [
                        "fundtransferresponse" => [
                            "status" => [
                                "success" => true,
                                "successdebit" => true,
                                "successcredit" => true
                            ],
                            "balance" => floatval(number_format($client_response->fundtransferresponse->balance, 2, '.', '')), #old_method
                            "currencycode" => $client_details->default_currency,
                        ]
                    ];
                    $update_gametransactionext = array(
                        "mw_response" =>json_encode($response),
                        "mw_request"=>json_encode($client_response->requestoclient),
                        "client_response" =>json_encode($client_response->fundtransferresponse),
                        "transaction_detail" =>json_encode("success"),
                        "general_details" =>json_encode("success"),
                    );
                    GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext,$client_details);
                    $save_bal = DB::table("player_session_tokens")->where("token_id","=",$client_details->token_id)->update(["balance" => $client_response->fundtransferresponse->balance]);
                    AWSHelper::saveLog("Habanero Response BET", $this->provider_id, json_encode($data,JSON_FORCE_OBJECT),$response);
            }elseif(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "402"){
                    $response = [
                        "fundtransferresponse" => [
                            "status" => [
                                "success" => false,
                                "nofunds" => true,
                            ],
                            "balance" => $client_details->balance,
                            "currencycode" => $client_details->default_currency
                        ]
                    ];
                    Helper::saveLog('HBN trans balance not enough', 24, json_encode($data), $response);
                    $update_gametransactionext = array(
                        "mw_response" =>json_encode($response),
                        "mw_request"=>json_encode($client_response->requestoclient),
                        "client_response" =>json_encode($client_response->fundtransferresponse),
                        "transaction_detail" =>json_encode("FAILED"),
                        "general_details" =>json_encode("FAILED")
                    );
                    GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext,$client_details);
                    $updateGameTransaction = [
                        "win" => 2,
                        'trans_status' => 5
                    ];
                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
                    return $response;
            }else{
                $response = [
                    "fundtransferresponse" => [
                        "status" => [
                            "success" => false,
                            "nofunds" => true,
                        ],
                        "balance" => $client_details->balance,
                        "currencycode" => $client_details->default_currency
                    ]
                ];
                Helper::saveLog('HBN trans ERRRRR', 24, json_encode($data), $response);
                $update_gametransactionext = array(
                    "mw_response" =>json_encode($response),
                    "mw_request"=>json_encode($client_response->requestoclient),
                    "client_response" =>json_encode($client_response->fundtransferresponse),
                    "transaction_detail" =>json_encode("FAILED"),
                    "general_details" =>json_encode("FAILED")
                );
                GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext,$client_details);
                $updateGameTransaction = [
                    "win" => 2,
                    'trans_status' => 5
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
                return $response;
            }
        }catch (\Exception $e) {
            $msg = array("status" => 'error',"message" => $e->getMessage());
            $response = [
                "fundtransferresponse" => [
                    "status" => [
                        "success" => false,
                        "nofunds" => true,
                    ],
                    "balance" => $client_details->balance,
                    "currencycode" => $client_details->default_currency
                ]
            ];
            $update_gametransactionext = array(
                "mw_response" =>json_encode($response),
                "mw_request"=>$msg,
                "client_response" =>$msg,
                "transaction_detail" =>json_encode("FAILED"),
                "general_details" =>json_encode("FAILED")
            );
            GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext,$client_details);
            $updateGameTransaction = [
                "win" => 2,
                'trans_status' => 5
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
            Helper::saveLog('PP gameWin - FATAL ERROR', $this->provider_id, json_encode($data), Helper::datesent());
            return $response;
        }
        
        
    }

    public function win($data,$token,$game_details,$round_id,$gamerecord,$bet_amount){
        AWSHelper::saveLog("Habanero Request WIN", $this->provider_id, json_encode($data,JSON_FORCE_OBJECT),"Recieved");
        $client_details = Providerhelper::getClientDetails('token', $token);
        $checkDuplicateCall = GameTransactionMDB::findGameExt($data->transferid,2,'transaction_id',$client_details);
        if($checkDuplicateCall != 'false'){
            $response = [
                "fundtransferresponse" => [
                    "status" => [
                        "success" => true,
                        "successdebit" => true,
                        "successcredit" => true
                    ],
                    "balance" => floatval(number_format($client_details->balance, 2, '.', '')),
                    "currencycode" => $client_details->default_currency,
                    ]
                ];  
                Helper::saveLog('HBN duplicate call newCredit', 24, json_encode($data), $response);
            return $response;
        }
        $balance = $client_details->balance + $data->amount;
        $response = [
            "fundtransferresponse" => [
                "status" => [
                    "success" => true,
                    "successdebit" => true,
                    "successcredit" => true
                ],
                "balance" => floatval(number_format($balance, 2, '.', '')),
                "currencycode" => $client_details->default_currency,
                ]
        ];
        $create_gametransactionext = array(
            "game_trans_id" =>$gamerecord,
            "provider_trans_id" => $data->transferid,
            "round_id" => $round_id,
            "amount" => abs($data->amount),
            "game_transaction_type"=> 2,
            "provider_request" => json_encode($data),
            "mw_response" => json_encode($response)
        );
        $game_trans_ext_v2 = GameTransactionMDB::createGameTransactionExt($create_gametransactionext,$client_details);
        $action_payload = [
            "type" => "custom", #genreral,custom :D # REQUIRED!
            "custom" => [
                "provider" => 'hbn',
                "game_trans_ext_id" => $game_trans_ext_v2,
                "client_connection_name" => $client_details->connection_name,
            ],
            "provider" => [
                "provider_request" => $data,
                "provider_trans_id"=>$data->transferid,
                "provider_round_id"=>$round_id,
            ],
            "mwapi" => [
                "roundId"=> $gamerecord,
                "type"=>2,
                "game_id" => $game_details->game_id,
                "player_id" => $client_details->player_id,
                "mw_response" => $response,
            ]
        ];
        $income = $bet_amount - $data->amount;
        $win = $data->amount > 0 ? 1 : 0;
        $entry_id = $win == 0 ? '1' : '2';
        $client_response2 = ClientRequestHelper::fundTransfer_TG($client_details, $data->amount, $game_details->game_code, $game_details->game_name, $gamerecord, 'credit', false, $action_payload);
        $updateGameTransaction = [
            'win' => $win,
            'pay_amount' => abs($data->amount),
            'income' => $income,
            'entry_id' => $entry_id,
            'trans_status' => 2
        ];
        GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
        $save_bal = DB::table("player_session_tokens")->where("token_id","=",$client_details->token_id)->update(["balance" => $balance]);
        Helper::saveLog('HBN Response WIN' , 24, json_encode($data), $response);
        return $response;
    }
    public function freespin($data,$token,$game_details,$round_id,$gamerecord,$bet_amount){
        AWSHelper::saveLog("Habanero Request FREE SPIN", $this->provider_id, json_encode($data,JSON_FORCE_OBJECT),"Recieved");
        $client_details = Providerhelper::getClientDetails('token', $token);
        $checkDuplicateCall = GameTransactionMDB::findGameExt($data->transferid,2,'transaction_id',$client_details);
        if($checkDuplicateCall != 'false'){
            $response = [
                "fundtransferresponse" => [
                    "status" => [
                        "success" => true,
                        "successdebit" => true,
                        "successcredit" => true
                    ],
                    "balance" => floatval(number_format($client_details->balance, 2, '.', '')),
                    "currencycode" => $client_details->default_currency,
                    ]
                ];  
                Helper::saveLog('HBN duplicate call newCredit', 24, json_encode($data), $response);
            return $response;
        }
        $balance = $client_details->balance + $data->amount;
        $response = [
            "fundtransferresponse" => [
                "status" => [
                    "success" => true,
                    "successdebit" => true,
                    "successcredit" => true
                ],
                "balance" => floatval(number_format($balance, 2, '.', '')),
                "currencycode" => $client_details->default_currency,
                ]
        ];
        $create_gametransactionext = array(
            "game_trans_id" =>$gamerecord,
            "provider_trans_id" => $data->transferid,
            "round_id" => $round_id,
            "amount" => abs($data->amount),
            "game_transaction_type"=> 2,
            "provider_request" => json_encode($data),
            "mw_response" => json_encode($response)
        );
        $game_trans_ext_v2 = GameTransactionMDB::createGameTransactionExt($create_gametransactionext,$client_details);
        $action_payload = [
            "type" => "custom", #genreral,custom :D # REQUIRED!
            "custom" => [
                "provider" => 'hbn',
                "game_trans_ext_id" => $game_trans_ext_v2,
                "client_connection_name" => $client_details->connection_name,
            ],
            "provider" => [
                "provider_request" => $data,
                "provider_trans_id"=>$data->transferid,
                "provider_round_id"=>$round_id,
                
            ],
            "mwapi" => [
                "roundId"=> $gamerecord,
                "type"=>2,
                "game_id" => $game_details->game_id,
                "player_id" => $client_details->player_id,
                "mw_response" => $response,
            ]
        ];
        $income = $bet_amount - $data->amount;
        $win = $data->amount > 0 ? 1 : 0;
        $entry_id = $win == 0 ? '1' : '2';
        $updateGameTransaction = [
            'win' => $win,
            'pay_amount' => abs($data->amount),
            'income' => $income,
            'entry_id' => $entry_id,
            'trans_status' => 2
        ];
        GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
        $client_response2 = ClientRequestHelper::fundTransfer_TG($client_details, $data->amount, $game_details->game_code, $game_details->game_name, $gamerecord, 'credit', false, $action_payload);
        $save_bal = DB::table("player_session_tokens")->where("token_id","=",$client_details->token_id)->update(["balance" => $balance]);
        Helper::saveLog('HBN Free Spin Response' , 24, json_encode($data), $response);
        return $response;
    }
    public function newBet($details,$data,$client_details,$gamerecord,$game_details,$round_id){
        AWSHelper::saveLog("Habanero Request New Bet", $this->provider_id, json_encode($data,JSON_FORCE_OBJECT),"Recieved");
        $checkDuplicateCall = GameTransactionMDB::findGameExt($data->transferid,1,'transaction_id',$client_details);
        if($checkDuplicateCall != 'false'){
            $response = [
                "fundtransferresponse" => [
                    "status" => [
                        "success" => true,
                    ],
                    "balance" => floatval(number_format($client_details->balance, 2, '.', '')),
                    "currencycode" => $client_details->default_currency,
                    ]
                ];  
                Helper::saveLog('HBN Duplicate call NewBET', 24, json_encode($data), $response);
            return $response;
        }
        if($client_details->balance < $data->amount){// check balance
            $response = [
                "fundtransferresponse" => [
                    "status" => [
                        "success" => false,
                        "nofunds" => true,
                    ],
                    "balance" => $client_details->balance,
                    "currencycode" => $client_details->default_currency
                    ]
            ];
            Helper::saveLog('HBN trans balance not enough', 24, json_encode($data), $response);
            return $response;
        }

        $gameTransactionEXTData = array(
            "game_trans_id" => $gamerecord->game_trans_id,
            "provider_trans_id" => $data->transferid,
            "round_id" => $round_id,
            "amount" => $data->amount,
            "game_transaction_type"=> 1,
            "provider_request" =>json_encode($details),
        );
        $game_trans_ext_v2 = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
        // $game_trans_ext_v2 = ProviderHelper::createGameTransExtV2( $gamerecord->game_trans_id, $data->transferid, $round_id, abs($data->amount), '1');
        try{
            $client_response = ClientRequestHelper::fundTransfer($client_details, abs($data->amount), $game_details->game_code, $game_details->game_name, $game_trans_ext_v2, $gamerecord->game_trans_id, 'debit');
            if(isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "200"){
                $response = [
                    "fundtransferresponse" => [
                        "status" => [
                            "success" => true,
                        ],
                        "balance" => floatval(number_format($client_response->fundtransferresponse->balance, 2, '.', '')),
                        "currencycode" => $client_details->default_currency,
                        ]
                    ];
                $update_gametransactionext = array(
                    "mw_response" =>json_encode($response),
                    "mw_request"=>json_encode($client_response->requestoclient),
                    "client_response" =>json_encode($client_response->fundtransferresponse),
                    "transaction_detail" =>json_encode("FAILED"),
                    "general_details" =>json_encode("FAILED")
                );
                GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_v2,$client_details);
                $updateGameTransaction = [
                    "bet_amount" => abs($data->amount) + $gamerecord->bet_amount,
                    "win" => 5,
                    'trans_status' => 5
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord->game_trans_id, $client_details);
                // $update = DB::table('game_transactions')->where("game_trans_id","=",$gamerecord->game_trans_id)->update(["round_id" => $round_id, "bet_amount" => abs($data->amount) + $gamerecord->bet_amount, "pay_amount" => $gamerecord->pay_amount, "income" => abs($data->amount) + $gamerecord->bet_amount, "win" => 5 ]);
                // $updateGameTransExt = DB::table('game_transaction_ext')->where('game_trans_ext_id','=',$game_trans_ext_v2)->update(["amount" =>abs($data->amount),"game_transaction_type" => 1,"provider_request" => json_encode($details),"mw_response" => json_encode($response),"mw_request" => json_encode($client_response->requestoclient),"client_response" => json_encode($client_response),"transaction_detail" => json_encode($response) ]);
                $save_bal = DB::table("player_session_tokens")->where("token_id","=",$client_details->token_id)->update(["balance" => $client_response->fundtransferresponse->balance]);
                Helper::saveLog('HBN trans double', 24, json_encode($data), $response);
                return $response;
            }elseif(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "402"){
                    $response = [
                        "fundtransferresponse" => [
                            "status" => [
                                "success" => false,
                                "nofunds" => true,
                            ],
                            "balance" => $client_details->balance,
                            "currencycode" => $client_details->default_currency
                        ]
                    ];
                    Helper::saveLog('HBN trans balance not enough', 24, json_encode($data), $response);
                    $update_gametransactionext = array(
                        "mw_response" =>json_encode($response),
                        "mw_request"=>json_encode($client_response->requestoclient),
                        "client_response" =>json_encode($client_response->fundtransferresponse),
                        "transaction_detail" =>json_encode("FAILED"),
                        "general_details" =>json_encode("FAILED")
                    );
                    GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_v2,$client_details);
                    $updateGameTransaction = [
                        "win" => 2,
                        'trans_status' => 5
                    ];
                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord->game_trans_id, $client_details);
                    return $response;
            }else{
                $response = [
                    "fundtransferresponse" => [
                        "status" => [
                            "success" => false,
                            "nofunds" => true,
                        ],
                        "balance" => $client_details->balance,
                        "currencycode" => $client_details->default_currency
                        ]
                ];
                Helper::saveLog('HBN trans balance not enough', 24, json_encode($data), $response);
                $update_gametransactionext = array(
                    "mw_response" =>json_encode($response),
                    "mw_request"=>json_encode($client_response->requestoclient),
                    "client_response" =>json_encode($client_response->fundtransferresponse),
                    "transaction_detail" =>json_encode("FAILED"),
                    "general_details" =>json_encode("FAILED")
                );
                GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_v2,$client_details);
                $updateGameTransaction = [
                    "win" => 2,
                    'trans_status' => 5
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord->game_trans_id, $client_details);
                return $response;
            }
        }catch (\Exception $e) {
            $msg = array("status" => 'error',"message" => $e->getMessage());
            $response = [
                "fundtransferresponse" => [
                    "status" => [
                        "success" => false,
                        "nofunds" => true,
                    ],
                    "balance" => $client_details->balance,
                    "currencycode" => $client_details->default_currency
                ]
            ];
            $update_gametransactionext = array(
                "mw_response" =>json_encode($response),
                "mw_request"=>$msg,
                "client_response" =>$msg,
                "transaction_detail" =>json_encode("FATAL ERROR FAILED"),
                "general_details" =>json_encode("FAILED")
            );
            GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_v2,$client_details);
            $updateGameTransaction = [
                "win" => 2,
                'trans_status' => 5
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord->game_trans_id, $client_details);
            Helper::saveLog('Habanero FATAL ERROR', $this->provider_id, json_encode($msg), Helper::datesent());
            return $response;
        }
                
      
    }
    public function newCredit($data,$token,$gamerecord,$game_details,$round_id){
        AWSHelper::saveLog("Habanero Request New WIN", $this->provider_id, json_encode($data,JSON_FORCE_OBJECT),"Recieved");
        $client_details = Providerhelper::getClientDetails('token', $token);
        $checkDuplicateCall = GameTransactionMDB::findGameExt($data->transferid,2,'transaction_id',$client_details);
        if($checkDuplicateCall != 'false'){
            $response = [
                "fundtransferresponse" => [
                    "status" => [
                        "success" => true,
                    ],
                    "balance" => floatval(number_format($client_details->balance, 2, '.', '')),
                    "currencycode" => $client_details->default_currency,
                    ]
                ];  
                Helper::saveLog('HBN duplicate call newCredit', 24, json_encode($data), $response);
            return $response;
        }
        
        $balance = $client_details->balance + abs($data->amount);
        $response = [
            "fundtransferresponse" => [
                "status" => [
                    "success" => true,
                ],
                "balance" => floatval(number_format($balance, 2, '.', '')),
                "currencycode" => $client_details->default_currency,
            ]
        ];
        $create_gametransactionext = array(
            "game_trans_id" =>$gamerecord->game_trans_id,
            "provider_trans_id" => $data->transferid,
            "round_id" => $round_id,
            "amount" => abs($data->amount),
            "game_transaction_type"=> 2,
            "provider_request" => json_encode($data),
            "mw_response" => json_encode($response)
        );
        $game_trans_ext_v2 = GameTransactionMDB::createGameTransactionExt($create_gametransactionext,$client_details);
        $payout = $gamerecord->pay_amount + abs($data->amount);
        $income = $gamerecord->bet_amount - $payout;
        $win = $payout > 0 ? 1 : 0;
        $entry_id = $win == 0 ? '1' : '2';
        $action_payload = [
            "type" => "custom", #genreral,custom :D # REQUIRED!
            "custom" => [
                "provider" => 'hbn',
                "game_trans_ext_id" => $game_trans_ext_v2,
                "client_connection_name" => $client_details->connection_name,
            ],
            "provider" => [
                "provider_request" => $data,
                "provider_trans_id"=>$data->transferid,
                "provider_round_id"=>$round_id,
            ],
            "mwapi" => [
                "roundId"=> $gamerecord->game_trans_id,
                "type"=>2,
                "game_id" => $game_details->game_id,
                "player_id" => $client_details->player_id,
                "mw_response" => $response,
            ]
        ];
        $client_response2 = ClientRequestHelper::fundTransfer_TG($client_details, abs($data->amount), $game_details->game_code, $game_details->game_name, $gamerecord->game_trans_id, 'credit', false, $action_payload);
        $updateGameTransaction = [
            'win' => $win,
            'pay_amount' => $payout,
            'income' => $income,
            'entry_id' => $entry_id,
            'trans_status' => 2
        ];
        GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord->game_trans_id, $client_details);
        $save_bal = DB::table("player_session_tokens")->where("token_id","=",$client_details->token_id)->update(["balance" => $balance]);
        Helper::saveLog('HBN trans win 2', 24, json_encode($data), $response);
        return $response;
    }
    public function refund($details,$data,$gamerecord,$token,$game_details,$round_id){
        AWSHelper::saveLog("Habanero Request REFUND", $this->provider_id, json_encode($data,JSON_FORCE_OBJECT),"Recieved");
        $client_details = Providerhelper::getClientDetails('token', $token);
        $checkDuplicateCall = DB::table('game_transaction_ext')->where('round_id',$round_id)->where('provider_trans_id',$data->transferid)->get();
        $checkDuplicateCall = GameTransactionMDB::findGameExt($data->transferid,3,'transaction_id',$client_details);
        if($checkDuplicateCall != 'false'){
            $response = [
                "fundtransferresponse" => [
                    "status" => [
                        "success" => true,
                    ],
                    "balance" => floatval(number_format($client_details->balance, 2, '.', '')),
                    "currencycode" => $client_details->default_currency,
                    ]
                ];  
                Helper::saveLog('HBN duplicate call newCredit', 24, json_encode($data), $response);
            return $response;
        }
        $balance = $client_details->balance - abs($data->amount);
        $response = [
            "fundtransferresponse" => [
                "status" => [
                    "success" => true,
                    "refundstatus" => 1,
                ],
                "balance" => floatval(number_format($balance, 2, '.', '')),
                "currencycode" => $client_details->default_currency,
            ]
        ];
        $create_gametransactionext = array(
            "game_trans_id" =>$gamerecord->game_trans_id,
            "provider_trans_id" => $data->transferid,
            "round_id" => $round_id,
            "amount" => abs($data->amount),
            "game_transaction_type"=> 3,
            "provider_request" => json_encode($data),
            "mw_response" => json_encode($response)
        );
        $game_trans_ext_v2 = GameTransactionMDB::createGameTransactionExt($create_gametransactionext,$client_details);
        $action_payload = [
            "type" => "custom", #genreral,custom :D # REQUIRED!
            "custom" => [
                "provider" => 'hbn',
                "game_trans_ext_id" => $game_trans_ext_v2,
                "client_connection_name" => $client_details->connection_name,
            ],
            "provider" => [
                "provider_request" => $details,
                "provider_trans_id"=>$data->transferid,
                "provider_round_id"=>$round_id,
            ],
            "mwapi" => [
                "roundId"=> $gamerecord->game_trans_id,
                "type"=>3,
                "game_id" => $game_details->game_id,
                "player_id" => $client_details->player_id,
                "mw_response" => $response,
            ]
        ];
        $client_response2 = ClientRequestHelper::fundTransfer_TG($client_details, abs($data->amount), $game_details->game_code, $game_details->game_name, $gamerecord->game_trans_id, 'credit', true, $action_payload);
        $updateGameTransaction = [
            'win' => 4,
            'pay_amount' => abs($data->amount),
            'income' => 0,
            'entry_id' => 2,
            'trans_status' => 2
        ];
        GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord->game_trans_id, $client_details);
        $save_bal = DB::table("player_session_tokens")->where("token_id","=",$client_details->token_id)->update(["balance" => $balance]);
        return $response;
    }
    public function tableBet($details,$data,$client_details,$game_details,$round_id){
        AWSHelper::saveLog("Habanero Request tableBet", $this->provider_id, json_encode($data,JSON_FORCE_OBJECT),"Recieved");
        $gameTransactionData = array(
            "provider_trans_id" => $data->transferid,
            "token_id" => $client_details->token_id,
            "game_id" => $game_details->game_id,
            "round_id" => $round_id,
            "bet_amount" => abs($data->amount),
            "win" => 5,
            "pay_amount" => 0,
            "income" => 0,
            "entry_id" =>1,
            "trans_status" =>1,
        );
        $gamerecord = GameTransactionMDB::createGametransaction($gameTransactionData,$client_details);
        $gameTransactionEXTData = array(
            "game_trans_id" => $gamerecord,
            "provider_trans_id" => $data->transferid,
            "round_id" => $round_id,
            "amount" => $data->amount,
            "game_transaction_type"=> 1,
            "provider_request" =>json_encode($details),
        );
        $game_trans_ext = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
        try{
            $client_response = ClientRequestHelper::fundTransfer($client_details, abs($data->amount), $game_details->game_code, $game_details->game_name, $game_trans_ext, $gamerecord, 'debit');
            if(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "200"){
                    $response = [
                        "fundtransferresponse" => [
                            "status" => [
                                "success" => true,
                                "successdebit" => true,
                                "successcredit" => true
                            ],
                            // "balance" => floatval(number_format($bet_balance, 2, '.', '')), #new_method
                            "balance" => floatval(number_format($client_response->fundtransferresponse->balance, 2, '.', '')), #old_method
                            "currencycode" => $client_details->default_currency,
                            ]
                    ];
                    $update_gametransactionext = array(
                        "mw_response" =>json_encode($response),
                        "mw_request"=>json_encode($client_response->requestoclient),
                        "client_response" =>json_encode($client_response->fundtransferresponse),
                        "transaction_detail" =>json_encode("success"),
                        "general_details" =>json_encode("success"),
                    );
                    GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext,$client_details);
                    $save_bal = DB::table("player_session_tokens")->where("token_id","=",$client_details->token_id)->update(["balance" => $client_response->fundtransferresponse->balance]);
                    AWSHelper::saveLog("Habanero Request tableBet", $this->provider_id, json_encode($data,JSON_FORCE_OBJECT),$response);
                    return $response;
            }elseif(isset($client_response->fundtransferresponse->status->code) 
                && $client_response->fundtransferresponse->status->code == "402"){
                    $response = [
                        "fundtransferresponse" => [
                            "status" => [
                                "success" => false,
                                "nofunds" => true,
                            ],
                            "balance" => $client_details->balance,
                            "currencycode" => $client_details->default_currency
                        ]
                    ];
                    Helper::saveLog('HBN trans balance not enough', 24, json_encode($data), $response);
                    $update_gametransactionext = array(
                        "mw_response" =>json_encode($response),
                        "mw_request"=>json_encode($client_response->requestoclient),
                        "client_response" =>json_encode($client_response->fundtransferresponse),
                        "transaction_detail" =>json_encode("FAILED"),
                        "general_details" =>json_encode("FAILED")
                    );
                    GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext,$client_details);
                    $updateGameTransaction = [
                        "win" => 2,
                        'trans_status' => 5
                    ];
                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
                    return $response;
            }else{
                $response = [
                    "fundtransferresponse" => [
                        "status" => [
                            "success" => false,
                            "nofunds" => true,
                        ],
                        "balance" => $client_details->balance,
                        "currencycode" => $client_details->default_currency
                    ]
                ];
                Helper::saveLog('HBN trans balance not enough', 24, json_encode($data), $response);
                $update_gametransactionext = array(
                    "mw_response" =>json_encode($response),
                    "mw_request"=>json_encode($client_response->requestoclient),
                    "client_response" =>json_encode($client_response->fundtransferresponse),
                    "transaction_detail" =>json_encode("FAILED"),
                    "general_details" =>json_encode("FAILED")
                );
                GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext,$client_details);
                $updateGameTransaction = [
                    "win" => 2,
                    'trans_status' => 5
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
                return $response;

            }
        }catch (\Exception $e) {
            $msg = array("status" => 'error',"message" => $e->getMessage());
            $response = [
                "fundtransferresponse" => [
                    "status" => [
                        "success" => false,
                        "nofunds" => true,
                    ],
                    "balance" => $client_details->balance,
                    "currencycode" => $client_details->default_currency
                ]
            ];
            $update_gametransactionext = array(
                "mw_response" =>json_encode($response),
                "mw_request"=>$msg,
                "client_response" =>$msg,
                "transaction_detail" =>json_encode("FAILED - FATAL ERROR"),
                "general_details" =>json_encode("FAILED")
            );
            GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext,$client_details);
            $updateGameTransaction = [
                "win" => 2,
                'trans_status' => 5
            ];
            GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
            Helper::saveLog('PP gameWin - FATAL ERROR', $this->provider_id, json_encode($data), Helper::datesent());
            return $response;
        }
        
    }

    public function reCredit($details,$data,$token,$game_details,$round_id){
        AWSHelper::saveLog("Habanero Request ReCredit", $this->provider_id, json_encode($data,JSON_FORCE_OBJECT),"Recieved");
        $client_details = Providerhelper::getClientDetails('token', $token);
        $checkStatus = GameTransactionMDB::findGameExt($data->originaltransferid,2,'transaction_id',$client_details);
        $gamerecord = GameTransactionMDB::findGameTransactionDetails($round_id,'round_id',false,$client_details);
        if($checkStatus != 'false'){
            $response = [
                "fundtransferresponse" => [
                    "status" => [
                        "success" => true,
                    ],
                    "balance" => $client_details->balance,
                    "currencycode" => $client_details->default_currency
                ]
            ];
            return $response;
        }
        
        $balance = $client_details->balance + abs($data->amount);
        $response = [
            "fundtransferresponse" => [
                "status" => [
                    "success" => true,
                ],
                "balance" => floatval(number_format($balance, 2, '.', '')),
                "currencycode" => $client_details->default_currency,
            ]
        ];
        $create_gametransactionext = array(
            "game_trans_id" =>$gamerecord->game_trans_id,
            "provider_trans_id" => $data->transferid,
            "round_id" => $round_id,
            "amount" => abs($data->amount),
            "game_transaction_type"=> 2,
            "provider_request" => json_encode($data),
            "mw_response" => json_encode($response)
        );
        $game_trans_ext_v2 = GameTransactionMDB::createGameTransactionExt($create_gametransactionext,$client_details);
        $payout = $gamerecord->pay_amount + abs($data->amount);
        $income = $gamerecord->bet_amount - $payout;
        $win = $payout > 0 ? 1 : 0;
        $entry_id = $win == 0 ? '1' : '2';
        $action_payload = [
            "type" => "custom", #genreral,custom :D # REQUIRED!
            "custom" => [
                "provider" => 'hbn',
                "game_trans_ext_id" => $game_trans_ext_v2,
                "client_connection_name" => $client_details->connection_name,
            ],
            "provider" => [
                "provider_request" => $data,
                "provider_trans_id"=>$data->transferid,
                "provider_round_id"=>$round_id,
            ],
            "mwapi" => [
                "roundId"=> $gamerecord->game_trans_id,
                "type"=>2,
                "game_id" => $game_details->game_id,
                "player_id" => $client_details->player_id,
                "mw_response" => $response,
            ]
        ];
        $updateGameTransaction = [
            'win' => $win,
            'pay_amount' => $payout,
            'income' => $income,
            'entry_id' => $entry_id,
            'trans_status' => 2
        ];
        GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord->game_trans_id, $client_details);
        $client_response2 = ClientRequestHelper::fundTransfer_TG($client_details, abs($data->amount), $game_details->game_code, $game_details->game_name, $gamerecord->game_trans_id, 'credit', false, $action_payload);
        $save_bal = DB::table("player_session_tokens")->where("token_id","=",$client_details->token_id)->update(["balance" => $balance]);
        Helper::saveLog('HBN RECREDIT RESPONSE', 24, json_encode($details), $response);
        return $response;
    }

    public function queryrequest(Request $request){
        $data = file_get_contents("php://input");
        $details = json_decode($data);
        $client_details = Providerhelper::getClientDetails('token', $details->queryrequest->token);
        $queryRequest = GameTransactionMDB::findGameExt($data->originaltransferid,2,'transaction_id',$client_details);
        Helper::saveLog('queryrequest HBN', $this->provider_id,$data," ");
        if($queryRequest != 'false'){

            $response = [
                "fundtransferresponse" => [
                    "status" => [
                        "success" => true,
                    ]
                ]
            ];
            Helper::saveLog('queryrequest HBN', $this->provider_id,$data,$response);
        }else{
            $response = [
                "fundtransferresponse" => [
                    "status" => [
                        "success" => false,
                    ]
                ]
            ];
            Helper::saveLog('queryrequest HBN', $this->provider_id,$data,$response);
        }

        return $response;
    }

    
    
}
