<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransactionMDB;
use App\Support\RouteParam;
use Illuminate\Http\Request;
use App\Helpers\PNGHelper;

use DB;

class VivoController extends Controller
{
    public $provider_db_id, $prefix;
    public function __construct(){
    	$this->provider_db_id = config("providerlinks.vivo.PROVIDER_ID");
        $this->prefix = "VIVOTRANSID_"; // for idom name
	}

	public function authPlayer(Request $request)
	{
        header("Content-type: text/xml; charset=utf-8");
		$client_details = ProviderHelper::getClientDetails('token', $request->token);
		$hash = md5($request->token.config("providerlinks.vivo.PASS_KEY"));
        $response = [
            "REQUEST" => [
                "TOKEN" => $request->token,
                "HASH" => $request->hash,
            ],
            "TIME" => Helper::datesent(),
            "RESPONSE" => [
                "RESULT" => "FAILED",
                "CODE" => 400,
            ]
        ];
		if($hash == $request->hash) {
			if ($client_details) {
                $response = [
                    "REQUEST" =>  [
                        "TOKEN" => $request->token,
                        "HASH" => $request->hash,
                    ],
                    "TIME" => Helper::datesent(),
                    "RESPONSE" => [
                        "RESULT" => "OK",
                        "USERID" => $client_details->player_id,
                        "USERNAME" => $client_details->username,
                        "EMAIL" => $client_details->email,
                        "CURRENCY" => $client_details->default_currency,
                        "BALANCE" => $client_details->balance,
                    ]
                ];
			}
		} 
		Helper::errorDebug('vivo_authentication', config("providerlinks.vivo.PROVIDER_ID"), json_encode($request->all()), $response);
        return PNGHelper::arrayToXml($response,"<VGSSYSTEM/>");
	}

    public function getBalance(Request $request) 
	{
        header("Content-type: text/xml; charset=utf-8");
		$client_details = ProviderHelper::getClientDetails('player_id', $request->userId);
		$hash = md5($request->userId.config("providerlinks.vivo.PASS_KEY"));
        $response = [
            "REQUEST" => [
                "USERID" => $request->userId,
                "HASH" => $request->hash,
            ],
            "TIME" => Helper::datesent(),
            "RESPONSE" => [
                "RESULT" => "FAILED",
                "CODE" => 500,
            ]
        ];
		if($hash == $request->hash) {
			if ($client_details) {
                $response = [
                    "REQUEST" => [
                        "USERID" => $request->userId,
                        "HASH" => $request->hash,
                    ],
                    "TIME" => Helper::datesent(),
                    "RESPONSE" => [
                        "RESULT" => "OK",
                        "BALANCE" => $client_details->balance,
                    ]
                ];
			}
		} 
		Helper::errorDebug('vivo_balance', config("providerlinks.vivo.PROVIDER_ID"), json_encode($request->all()), $response);
        return PNGHelper::arrayToXml($response,"<VGSSYSTEM/>");
	}

	public function gameTransaction(Request $request) 
	{
        $response = [
            "REQUEST" => [
                "USERID" => $request->userId,
                "AMOUNT" => $request->Amount,
                "TRANSACTIONID" => $request->TransactionID,
                "TRNTYPE" => $request->TrnType,
                "GAMEID" => $request->gameId,
                "ROUNDID" => $request->roundId,
                "TRNDESCRIPTION" => $request->TrnDescription,
                "HISTORY" => $request->History,
                "ISROUNDFINISHED" => $request->isRoundFinished,
                "HASH" => $request->hash,
            ],
            "TIME" => Helper::datesent(),
            "RESPONSE" => [
                "RESULT" => "FAILED",
                "CODE" => 310,
            ]
        ];
        switch ($request->TrnType) {
            case 'BET':
                $response = $this->_BET($request->all());
                break;
            case 'WIN':
                $response = $this->_WIN($request->all());
                break;
            case 'CANCELED_BET':
                $response = $this->_CANCEL_BET($request->all());
                break;
        }
        Helper::errorDebug('vivo_'.$request->TrnType , config("providerlinks.vivo.PROVIDER_ID"), json_encode($request->all()), $response);
        header("Content-type: text/xml; charset=utf-8");
        return PNGHelper::arrayToXml($response,"<VGSSYSTEM/>");
	}

    private function _BET($data){ 
        try{
            ProviderHelper::idenpotencyTable($this->prefix.$data["TransactionID"]);
            Helper::errorDebug('vivo_gameTransaction', config("providerlinks.vivo.PROVIDER_ID"), json_encode($data), "INDEX");
        }catch(\Exception $e){
            $client_details = ProviderHelper::getClientDetails('player_id', $data["userId"]);
            $bet_transaction = GameTransactionMDB::findGameExt($data["TransactionID"], 1,'transaction_id', $client_details);
            if ($bet_transaction != 'false') {
                if( $bet_transaction->transaction_detail == "SUCCESS" ){
                    $response = [
                        "REQUEST" => [
                            "USERID" => $data["userId"],
                            "AMOUNT" => $data["Amount"],
                            "TRANSACTIONID" => $data["TransactionID"],
                            "TRNTYPE" => $data["TrnType"],
                            "GAMEID" => $data["gameId"],
                            "ROUNDID" => $data["roundId"],
                            "TRNDESCRIPTION" => $data["TrnDescription"],
                            "HISTORY" => $data["History"],
                            "ISROUNDFINISHED" => $data["isRoundFinished"],
                            "HASH" => $data["hash"],
                        ],
                        "TIME" => Helper::datesent(),
                        "RESPONSE" => [
                            "RESULT" => "OK",
                            "ECSYSTEMTRANSACTIONID" => $bet_transaction->game_trans_ext_id,
                            "BALANCE" => $client_details->balance,
                        ]
                    ];
                    return $response;
                }
            } 
            $response = [
                "REQUEST" => [
                    "USERID" => $data["userId"],
                    "AMOUNT" => $data["Amount"],
                    "TRANSACTIONID" => $data["TransactionID"],
                    "TRNTYPE" => $data["TrnType"],
                    "GAMEID" => $data["gameId"],
                    "ROUNDID" => $data["roundId"],
                    "TRNDESCRIPTION" => $data["TrnDescription"],
                    "HISTORY" => $data["History"],
                    "ISROUNDFINISHED" => $data["isRoundFinished"],
                    "HASH" => $data["hash"],
                ],
                "TIME" => Helper::datesent(),
                "RESPONSE" => [
                    "RESULT" => "FAILED",
                    "CODE" => 300,
                ]
            ];
            return $response;
        }
        $client_details = ProviderHelper::getClientDetails('player_id', $data["userId"]);
        $game_details = Helper::getInfoPlayerGameRound($client_details->player_token);
        $bet_transaction = GameTransactionMDB::findGameTransactionDetails($data["roundId"],'round_id', false, $client_details);
        if($bet_transaction == 'false'){
            $gameTransactionData = array(
                "provider_trans_id" => $data["TransactionID"],
                "token_id" => $client_details->token_id,
                "game_id" => $game_details->game_id,
                "round_id" => $data["roundId"],
                "bet_amount" => $data["Amount"],
                "win" => 5,
                "pay_amount" => 0,
                "entry_id" => 1,
            );
            $game_transactionid = GameTransactionMDB::createGametransaction($gameTransactionData,$client_details);
        } else {
            $client_details->connection_name = $bet_transaction->connection_name;
            $game_transactionid = $bet_transaction->game_trans_id;
        }
        $game_transaction_extension = array(
            "game_trans_id" => $game_transactionid,
            "provider_trans_id" => $data["TransactionID"],
            "round_id" => $data["roundId"],
            "amount" => $data["Amount"],
            "game_transaction_type"=>1,
            "provider_request" => json_encode($data),
            "mw_response" => 'null',
            "transaction_detail" => "FAILED"
        );
        $transactionId = GameTransactionMDB::createGameTransactionExt($game_transaction_extension,$client_details);
        $body_details = [
            'provider_name' => $game_details->provider_name,
            'connection_timeout' => 2,
        ];
        $client_response = ClientRequestHelper::fundTransfer($client_details, $data["Amount"] ,$game_details->game_code,$game_details->game_name,$transactionId,$game_transactionid,"debit", false, $body_details);
        if(isset($client_response->fundtransferresponse->status->code) && $client_response->fundtransferresponse->status->code == "200"){
            $balance = round($client_response->fundtransferresponse->balance,2);
            if($bet_transaction != 'false'){
                $updateGameTransaction = [
                    "bet_amount" => $data["Amount"] + $bet_transaction->bet_amount,
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_transactionid, $client_details);
            }
            ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);
            $response = [
                "REQUEST" => [
                    "USERID" => $data["userId"],
                    "AMOUNT" => $data["Amount"],
                    "TRANSACTIONID" => $data["TransactionID"],
                    "TRNTYPE" => $data["TrnType"],
                    "GAMEID" => $data["gameId"],
                    "ROUNDID" => $data["roundId"],
                    "TRNDESCRIPTION" => $data["TrnDescription"],
                    "HISTORY" => $data["History"],
                    "ISROUNDFINISHED" => $data["isRoundFinished"],
                    "HASH" => $data["hash"],
                ],
                "TIME" => Helper::datesent(),
                "RESPONSE" => [
                    "RESULT" => "OK",
                    "ECSYSTEMTRANSACTIONID" => $transactionId,
                    "BALANCE" => $client_details->balance,
                ]
            ];
            $dataToUpdate = array(
                "mw_response" => json_encode($response),
                "mw_request" => json_encode($client_response->requestoclient),
                "client_response" => json_encode($client_response),
                "transaction_detail" => "SUCCESS"
            );
            GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$transactionId,$client_details);
            if($data['isRoundFinished'] == "true" || $data['isRoundFinished'] == "1"){
	         	return $this->loseTransaction($data);
	        }
        } elseif(isset($client_response->fundtransferresponse->status->code) && $client_response->fundtransferresponse->status->code == "402") {
            if($bet_transaction == 'false'){
                $updateGameTransaction = [
                    "win" => 2
                ];
                GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_transactionid, $client_details);
            }
            $response = [
                "REQUEST" => [
                    "USERID" => $data["userId"],
                    "AMOUNT" => $data["Amount"],
                    "TRANSACTIONID" => $data["TransactionID"],
                    "TRNTYPE" => $data["TrnType"],
                    "GAMEID" => $data["gameId"],
                    "ROUNDID" => $data["roundId"],
                    "TRNDESCRIPTION" => $data["TrnDescription"],
                    "HISTORY" => $data["History"],
                    "ISROUNDFINISHED" => $data["isRoundFinished"],
                    "HASH" => $data["hash"],
                ],
                "TIME" => Helper::datesent(),
                "RESPONSE" => [
                    "RESULT" => "FAILED",
                    "CODE" => 300,
                ]
            ];
            $dataToUpdate = array(
                "mw_response" => json_encode($response),
                "mw_request" => json_encode($client_response->requestoclient),
                "client_response" => json_encode($client_response),
                "general_details" => "failed"
            );
            GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$transactionId,$client_details);
        }
        return $response;
    }

    private function _WIN($data){ 
        try{
            ProviderHelper::idenpotencyTable($this->prefix.$data["TransactionID"]);
            Helper::errorDebug('vivo_gameTransaction', config("providerlinks.vivo.PROVIDER_ID"), json_encode($data), "INDEX");
        }catch(\Exception $e){
            $client_details = ProviderHelper::getClientDetails('player_id', $data["userId"]);
            $bet_transaction = GameTransactionMDB::findGameExt($data["TransactionID"], 2,'transaction_id', $client_details);
            if ($bet_transaction != 'false') {
                $response = [
                    "REQUEST" => [
                        "USERID" => $data["userId"],
                        "AMOUNT" => $data["Amount"],
                        "TRANSACTIONID" => $data["TransactionID"],
                        "TRNTYPE" => $data["TrnType"],
                        "GAMEID" => $data["gameId"],
                        "ROUNDID" => $data["roundId"],
                        "TRNDESCRIPTION" => $data["TrnDescription"],
                        "HISTORY" => $data["History"],
                        "ISROUNDFINISHED" => $data["isRoundFinished"],
                        "HASH" => $data["hash"],
                    ],
                    "TIME" => Helper::datesent(),
                    "RESPONSE" => [
                        "RESULT" => "OK",
                        "ECSYSTEMTRANSACTIONID" => $bet_transaction->game_trans_ext_id,
                        "BALANCE" => $client_details->balance,
                    ]
                ];
                return $response;
            } 
            $response = [
                "REQUEST" => [
                    "USERID" => $data["userId"],
                    "AMOUNT" => $data["Amount"],
                    "TRANSACTIONID" => $data["TransactionID"],
                    "TRNTYPE" => $data["TrnType"],
                    "GAMEID" => $data["gameId"],
                    "ROUNDID" => $data["roundId"],
                    "TRNDESCRIPTION" => $data["TrnDescription"],
                    "HISTORY" => $data["History"],
                    "ISROUNDFINISHED" => $data["isRoundFinished"],
                    "HASH" => $data["hash"],
                ],
                "TIME" => Helper::datesent(),
                "RESPONSE" => [
                    "RESULT" => "FAILED",
                    "CODE" => 399,
                ]
            ];
            return $response;
        }
        $client_details = ProviderHelper::getClientDetails('player_id', $data["userId"]);
        $game_details = Helper::getInfoPlayerGameRound($client_details->player_token);
        $bet_transaction = GameTransactionMDB::findGameTransactionDetails($data["roundId"],'round_id', false, $client_details);
        if($bet_transaction == 'false'){
            $response = [
                "REQUEST" => [
                    "USERID" => $data["userId"],
                    "AMOUNT" => $data["Amount"],
                    "TRANSACTIONID" => $data["TransactionID"],
                    "TRNTYPE" => $data["TrnType"],
                    "GAMEID" => $data["gameId"],
                    "ROUNDID" => $data["roundId"],
                    "TRNDESCRIPTION" => $data["TrnDescription"],
                    "HISTORY" => $data["History"],
                    "ISROUNDFINISHED" => $data["isRoundFinished"],
                    "HASH" => $data["hash"],
                ],
                "TIME" => Helper::datesent(),
                "RESPONSE" => [
                    "RESULT" => "FAILED",
                    "CODE" => 299,
                ]
            ];
            return $response;
        }
        $client_details->connection_name = $bet_transaction->connection_name;
        
        $game_transaction_extension = array(
            "game_trans_id" => $bet_transaction->game_trans_id,
            "provider_trans_id" => $data["TransactionID"],
            "round_id" => $data["roundId"],
            "amount" => $data["Amount"],
            "game_transaction_type"=> 2,
            "provider_request" => json_encode($data),
        );
        $transactionId = GameTransactionMDB::createGameTransactionExt($game_transaction_extension,$client_details);
        
        $updateGameTransaction = [
            "pay_amount" => $data["Amount"] + $bet_transaction->pay_amount,
            "income" => $bet_transaction->bet_amount - ( $data["Amount"] + $bet_transaction->pay_amount ),
            "entry_id" => 2,
        ];
        GameTransactionMDB::updateGametransaction($updateGameTransaction,$bet_transaction->game_trans_id, $client_details);

        $balance = round($client_details->balance,2) + $data["Amount"];
        ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);

        $win = ($data["Amount"] + $bet_transaction->pay_amount) == 0 ? 0 : 1;
        $response = [
            "REQUEST" => [
                "USERID" => $data["userId"],
                "AMOUNT" => $data["Amount"],
                "TRANSACTIONID" => $data["TransactionID"],
                "TRNTYPE" => $data["TrnType"],
                "GAMEID" => $data["gameId"],
                "ROUNDID" => $data["roundId"],
                "TRNDESCRIPTION" => $data["TrnDescription"],
                "HISTORY" => $data["History"],
                "ISROUNDFINISHED" => $data["isRoundFinished"],
                "HASH" => $data["hash"],
            ],
            "TIME" => Helper::datesent(),
            "RESPONSE" => [
                "RESULT" => "OK",
                "ECSYSTEMTRANSACTIONID" => $transactionId,
                "BALANCE" => $client_details->balance,
            ]
        ];
        $action_payload = [
            "type" => "custom", #genreral,custom :D # REQUIRED!
            "custom" => [
                "provider" => "VivoGaming",
                "client_connection_name" => $client_details->connection_name,
                "win_or_lost" => $win,
                "pay_amount" => $data["Amount"],
                "game_transaction_ext_id" => $transactionId
            ],
            "provider" => [
                "provider_request" => $data, #R
                "provider_trans_id"=> $data["TransactionID"], #e
                "provider_round_id"=> $data["roundId"], #R
            ],
            "mwapi" => [
                "roundId"=> $bet_transaction->game_trans_id, #R
                "type"=>2, #R
                "game_id" => $game_details->game_id, #R
                "player_id" => $client_details->player_id, #R
                "mw_response" => $response, #R
            ]
        ];
        $client_response = ClientRequestHelper::fundTransfer_TG($client_details, $data["Amount"],$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',false,$action_payload);
        if(isset($client_response->fundtransferresponse->status->code) ){
            // $updateGameTransaction = [
            //     "pay_amount" => $data["Amount"] + $bet_transaction->pay_amount,
            //     "income" => $bet_transaction->bet_amount - ( $data["Amount"] + $bet_transaction->pay_amount ),
            //     "entry_id" => 2,
            // ];
            // GameTransactionMDB::updateGametransaction($updateGameTransaction,$bet_transaction->game_trans_id, $client_details);
            $dataToUpdate = array(
                "mw_request" => json_encode($client_response->requestoclient),
                "client_response" => json_encode($client_response),
                "mw_response" => json_encode($response),
                "transaction_detail" => "SUCCESS"
            );
            GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$transactionId,$client_details);
        }
        return $response;
    }

    private function _CANCEL_BET($data){ 
    	try{
            ProviderHelper::idenpotencyTable($this->prefix.$data["TransactionID"]);
            Helper::errorDebug('vivo_gameTransaction', config("providerlinks.vivo.PROVIDER_ID"), json_encode($data), "INDEX");
        }catch(\Exception $e){
            $client_details = ProviderHelper::getClientDetails('player_id', $data["userId"]);
            $bet_transaction = GameTransactionMDB::findGameExt($data["TransactionID"], 3,'transaction_id', $client_details);
            if ($bet_transaction != 'false') {
                $response = [
                    "REQUEST" => [
                        "USERID" => $data["userId"],
                        "AMOUNT" => $data["Amount"],
                        "TRANSACTIONID" => $data["TransactionID"],
                        "TRNTYPE" => $data["TrnType"],
                        "GAMEID" => $data["gameId"],
                        "ROUNDID" => $data["roundId"],
                        "TRNDESCRIPTION" => $data["TrnDescription"],
                        "HISTORY" => $data["History"],
                        "ISROUNDFINISHED" => $data["isRoundFinished"],
                        "HASH" => $data["hash"],
                    ],
                    "TIME" => Helper::datesent(),
                    "RESPONSE" => [
                        "RESULT" => "OK",
                        "ECSYSTEMTRANSACTIONID" => $bet_transaction->game_trans_ext_id,
                        "BALANCE" => $client_details->balance,
                    ]
                ];
                return $response;
            } 
            $response = [
                "REQUEST" => [
                    "USERID" => $data["userId"],
                    "AMOUNT" => $data["Amount"],
                    "TRANSACTIONID" => $data["TransactionID"],
                    "TRNTYPE" => $data["TrnType"],
                    "GAMEID" => $data["gameId"],
                    "ROUNDID" => $data["roundId"],
                    "TRNDESCRIPTION" => $data["TrnDescription"],
                    "HISTORY" => $data["History"],
                    "ISROUNDFINISHED" => $data["isRoundFinished"],
                    "HASH" => $data["hash"],
                ],
                "TIME" => Helper::datesent(),
                "RESPONSE" => [
                    "RESULT" => "FAILED",
                    "CODE" => 399,
                ]
            ];
            return $response;
        }
        $client_details = ProviderHelper::getClientDetails('player_id', $data["userId"]);
        $game_details = Helper::getInfoPlayerGameRound($client_details->player_token);
        $bet_transaction = GameTransactionMDB::findGameTransactionDetails($data["roundId"],'round_id', false, $client_details);
        if($bet_transaction == 'false'){
            $response = [
                "REQUEST" => [
                    "USERID" => $data["userId"],
                    "AMOUNT" => $data["Amount"],
                    "TRANSACTIONID" => $data["TransactionID"],
                    "TRNTYPE" => $data["TrnType"],
                    "GAMEID" => $data["gameId"],
                    "ROUNDID" => $data["roundId"],
                    "TRNDESCRIPTION" => $data["TrnDescription"],
                    "HISTORY" => $data["History"],
                    "ISROUNDFINISHED" => $data["isRoundFinished"],
                    "HASH" => $data["hash"],
                ],
                "TIME" => Helper::datesent(),
                "RESPONSE" => [
                    "RESULT" => "FAILED",
                    "CODE" => 299,
                ]
            ];
            return $response;
        }
        $client_details->connection_name = $bet_transaction->connection_name;
        
        $game_transaction_extension = array(
            "game_trans_id" => $bet_transaction->game_trans_id,
            "provider_trans_id" => $data["TransactionID"],
            "round_id" => $data["roundId"],
            "amount" => $data["Amount"],
            "game_transaction_type"=> 2,
            "provider_request" => json_encode($data),
        );
        $transactionId = GameTransactionMDB::createGameTransactionExt($game_transaction_extension,$client_details);
        
        $updateGameTransaction = [
            "pay_amount" => $data["Amount"] + $bet_transaction->pay_amount,
            "income" => $bet_transaction->bet_amount - $data["Amount"],
            "entry_id" => 2,
        ];
        GameTransactionMDB::updateGametransaction($updateGameTransaction,$bet_transaction->game_trans_id, $client_details);

        $balance = round($client_details->balance,2) + $data["Amount"];
        ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);

        $win = 4;
        
        // $action_payload = [
        //     "type" => "custom", #genreral,custom :D # REQUIRED!
        //     "custom" => [
        //         "provider" => "VivoGaming",
        //         "client_connection_name" => $client_details->connection_name,
        //         "win_or_lost" => $win,
        //         "pay_amount" => $data["Amount"],
        //         "game_transaction_ext_id" => $transactionId
        //     ],
        //     "provider" => [
        //         "provider_request" => $data, #R
        //         "provider_trans_id"=> $data["TransactionID"], #e
        //         "provider_round_id"=> $data["roundId"], #R
        //     ],
        //     "mwapi" => [
        //         "roundId"=> $bet_transaction->game_trans_id, #R
        //         "type"=>2, #R
        //         "game_id" => $game_details->game_id, #R
        //         "player_id" => $client_details->player_id, #R
        //         "mw_response" => $response, #R
        //     ]
        // ];

        $fund_extra_data = [
            'provider_name' => $game_details->provider_name
        ];
        $client_response = ClientRequestHelper::fundTransfer($client_details,$data["Amount"],$game_details->game_code,$game_details->game_name,$transactionId,$bet_transaction->game_trans_id,"credit",true,$fund_extra_data);
        // $client_response = ClientRequestHelper::fundTransfer_TG($client_details, $data["Amount"],$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',true,$action_payload);
        if(isset($client_response->fundtransferresponse->status->code) && $client_response->fundtransferresponse->status->code == "200"){
        	$response = [
	            "REQUEST" => [
	                "USERID" => $data["userId"],
	                "AMOUNT" => $data["Amount"],
	                "TRANSACTIONID" => $data["TransactionID"],
	                "TRNTYPE" => $data["TrnType"],
	                "GAMEID" => $data["gameId"],
	                "ROUNDID" => $data["roundId"],
	                "TRNDESCRIPTION" => $data["TrnDescription"],
	                "HISTORY" => $data["History"],
	                "ISROUNDFINISHED" => $data["isRoundFinished"],
	                "HASH" => $data["hash"],
	            ],
	            "TIME" => Helper::datesent(),
	            "RESPONSE" => [
	                "RESULT" => "OK",
	                "ECSYSTEMTRANSACTIONID" => $transactionId,
	                "BALANCE" => $client_details->balance,
	            ]
	        ];
            $dataToUpdate = array(
                "mw_request" => json_encode($client_response->requestoclient),
                "client_response" => json_encode($client_response),
                "mw_response" => json_encode($response),
                "transaction_detail" => "SUCCESS"
            );
            GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$transactionId,$client_details);
        }
        return $response;
    }
    public function transactionStatus(Request $request){
    	$json_data = json_decode(file_get_contents("php://input"), true);
		header("Content-type: text/xml; charset=utf-8");
		$response = '<?xml version="1.0" encoding="utf-8"?>';
		$response .= '<VGSSYSTEM>
						<REQUEST>
							<USERID>'.$request->userId.'</USERID>
							<HASH>'.$request->hash.'</HASH>
						</REQUEST>
						<TIME>'.Helper::datesent().'</TIME>
						<RESPONSE>
							<RESULT>FAILED</RESULT>
							<CODE>302</CODE>
						</RESPONSE>
					</VGSSYSTEM>';

		$client_details = ProviderHelper::getClientDetails('player_id', $request->userId);

		$hash = md5($request->userId.$request->casinoTransactionId.config("providerlinks.vivo.PASS_KEY"));

		if($hash != $request->hash || $client_details == NULL) {
			header("Content-type: text/xml; charset=utf-8");
			$response = '<?xml version="1.0" encoding="utf-8"?>';
			$response .= '<VGSSYSTEM>
							<REQUEST>
								<USERID>'.$request->userId.'</USERID>
								<HASH>'.$request->hash.'</HASH>
							</REQUEST>
							<TIME>'.Helper::datesent().'</TIME>
							<RESPONSE>
								<RESULT>FAILED</RESULT>
								<CODE>500</CODE>
							</RESPONSE>
						</VGSSYSTEM>';
		}
		else
		{
			
			// Check if the transaction exist
			$game_transaction = GameTransactionMDB::getGameTransactionDataByProviderTransactionId($request->casinoTransactionId, $client_details);
			/*$game_transaction = GameTransaction::getGameTransactionDataByProviderTransactionId($request->casinoTransactionId);*/

			// If transaction is not found
			if($game_transaction) {
				header("Content-type: text/xml; charset=utf-8");
				$response = '<?xml version="1.0" encoding="utf-8"?>';
				$response .= '<VGSSYSTEM>
								<REQUEST>
									<USERID>'.$request->userId.'</USERID>
									<CASINOTRANSACTIONID>'.$request->casinoTransactionId.'</CASINOTRANSACTIONID>
									<HASH>'.$request->hash.'</HASH>
								</REQUEST>
								<TIME>'.Helper::datesent().'</TIME>
								<RESPONSE>
									<RESULT>OK</RESULT>
									<ECSYSTEMTRANSACTIONID>'.$game_transaction->game_trans_ext_id.'</ECSYSTEMTRANSACTIONID>
								</RESPONSE>
							</VGSSYSTEM>';
			}

		}

		return $response;
    }
    private function loseTransaction($data){
    	$client_details = ProviderHelper::getClientDetails('player_id', $data["userId"]);
    	$game_details = Helper::getInfoPlayerGameRound($client_details->player_token);
    	$bet_transaction = GameTransactionMDB::findGameTransactionDetails($data["roundId"],'round_id', false, $client_details);
    	if($data["Amount"] < 0) {
			$response = [
				"errorCode" =>  10201,
				"message" => "Warning value must not be less 0.",
			];
		}
		$response = '<VGSSYSTEM>
						<REQUEST>
							<USERID>'.$data["userId"].'</USERID>
							<AMOUNT>'.$data["Amount"].'</AMOUNT>
							<TRANSACTIONID>'.$data["TransactionID"].'</TRANSACTIONID>
							<TRNTYPE>'.$data["TrnType"].'</TRNTYPE>
							<GAMEID>'.$data["gameId"].'</GAMEID>
							<ROUNDID>'.$data["roundId"].'</ROUNDID>
							<TRNDESCRIPTION>'.$data["TrnDescription"].'</TRNDESCRIPTION>
							<HISTORY>'.$data["History"].'</HISTORY>
							<ISROUNDFINISHED>'.$data["isRoundFinished"].'</ISROUNDFINISHED>
							<HASH>'.$data["hash"].'</HASH>
						</REQUEST>
							<TIME>'.Helper::datesent().'</TIME>
						<RESPONSE>
							<RESULT>OK</RESULT>
							<ECSYSTEMTRANSACTIONID>'.$bet_transaction->game_trans_id.'</ECSYSTEMTRANSACTIONID>
							<BALANCE>'.$client_details->balance.'</BALANCE>
						</RESPONSE>
					</VGSSYSTEM>';
		$win_game_transaction_ext = array(
	            "game_trans_id" => $bet_transaction->game_trans_id,
	            "provider_trans_id" => $data["TransactionID"],
	            "round_id" => $data["roundId"],
	            "amount" => 0,
	            "game_transaction_type"=> 2,
	            "provider_request" =>json_encode($data),
	            "mw_response" => json_encode($response),
	            "general_details" => $data["History"],
	        );		                
		$game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($win_game_transaction_ext, $client_details);
		$action_payload = [
            "type" => "custom", #genreral,custom :D # REQUIRED!
            "custom" => [
            	"win_or_lost" => 0,
                "provider" => 'VivoGaming',
                "game_transaction_ext_id" => $game_trans_ext_id,
        		"client_connection_name" => $client_details->connection_name
            ],
            "provider" => [
                "provider_request" => $data, #R
                "provider_trans_id"=> $data["TransactionID"], #R
                "provider_round_id"=> $data["roundId"], #R
                "provider_name"=> $game_details->provider_name
            ],
            "mwapi" => [
                "roundId"=>$bet_transaction->game_trans_id, #R
                "type"=>2, #R
                "game_id" => $game_details->game_id, #R
                "player_id" => $client_details->player_id, #R
                "mw_response" => $response, #R
            ]
        ];
        try {
        	$client_response = ClientRequestHelper::fundTransfer_TG($client_details,0,$game_dteails->game_code,$game_details->game_name,$game_trans_id,'credit',false,$action_payload);
        	$dataToUpdate = array(
                "mw_request" => json_encode($client_response->requestoclient),
                "client_response" => json_encode($client_response),
                "mw_response" => json_encode($response),
                "transaction_detail" => "SUCCESS"
            );
            GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$transactionId,$client_details);
        } catch (\Exception $e) {
        	$dataToUpdate = array(
                "mw_request" => json_encode($client_response->requestoclient),
                "client_response" => json_encode($client_response),
                "mw_response" => json_encode($response),
                "transaction_detail" => "FAILED"
            );
            GameTransactionMDB::updateGametransactionEXT($dataToUpdate,$transactionId,$client_details);
        }
        header("Content-type: text/xml; charset=utf-8");
		$final_response =  '<?xml version="1.0" encoding="utf-8"?>';
		$final_response .= $response;
		return $final_response;
    }
}
