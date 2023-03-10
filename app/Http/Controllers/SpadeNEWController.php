<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use GuzzleHttp\Client;
use App\Models\GameTransactionMDB;
use App\Helpers\ClientRequestHelper;
use App\Helpers\FreeSpinHelper;
use DB;

class SpadeNEWController extends Controller
{
	
    public function __construct(){
    	$this->prefix = config('providerlinks.spade.prefix');
    	$this->merchantCode = config('providerlinks.spade.merchantCode');
		$this->siteId = config('providerlinks.spade.siteId');
		$this->api_url = config('providerlinks.spade.api_url');
		$this->provider_db_id = config('providerlinks.spade.provider_id');
	}

	public function generateSerialNo(){
    	// $guid = vsprintf('%s%s-%s-4000-8%.3s-%s%s%s0',str_split(dechex( microtime(true) * 1000 ) . bin2hex( random_bytes(8) ),4));
    	$guid = substr("abcdefghijklmnopqrstuvwxyz1234567890", mt_rand(0, 25), 1).substr(md5(time()), 1);;
    	return $guid;
	}
	
	public function getGameList(Request $request){
		$api = $this->api_url;
		
		$requesttosend = [
			'serialNo' =>  $this->generateSerialNo(),
			'merchantCode' => $this->merchantCode,
			'currency' => 'USD'	
		];
		$client = new Client([
            'headers' => [ 
                'API' => "getGames",
                'DataType' => "JSON"
            ]
        ]);
		$guzzle_response = $client->post($api,['body' => json_encode($requesttosend)]);
		$client_response = json_decode($guzzle_response->getBody()->getContents());
		return json_encode($client_response);
	
	}
	
	public function getBetCost(Request $request){
//Auto Bulk insert in table FreeRound Denomination!!
		$api = $this->api_url;
		$games = DB::select("Select * FROM games as g where g.sub_provider_id = ". $this->provider_db_id.";");
		$results =array();
		foreach($games as $item){
			$gametocompare = DB::select("select * FROM  free_round_denominations WHERE game_id = ".$item->game_id.";");

			if(count($gametocompare) == 0){
				try{
					$requesttosend = [
						'serialNo' =>  $this->generateSerialNo(),
						'merchantCode' => $this->merchantCode,
						'currency' => $request->currency,
						'gameCode' => $item->game_code
					];
					$client = new Client([
						'headers' => [ 
							'API' => "getBetCost",
							'DataType' => "JSON"
						]
					]);
					$guzzle_response = $client->post($api,['body' => json_encode($requesttosend)]);
					$client_response = json_decode($guzzle_response->getBody()->getContents());
					if (isset($client_response->betCosts)){
						$responsetoarray = json_decode(json_encode($client_response->betCosts));
						// dd($responsetoarray->betCosts[0]->betCost);
						$availBet = $responsetoarray->betCosts[0]->betCost;	
						// dd($game_details);
						$denominations = '';
						foreach ($availBet as $value) {
							$denominations .= $value . " ";
						}
						$arraydenom = array(
							'game_id' => $item->game_id,
							'denominations' => $denominations,
							'currency' => $request->currency
						) ;
						$result[] = $arraydenom;
						DB::table('free_round_denominations')->insert($arraydenom);
					}
				}
				catch(\Exception $e) {
					$msg = $e->getMessage().' '.$e->getLine().' '.$e->getFile();
					$arr = array(
						'Message' => $msg,
						'Game Code' => $item->game_code
					);
					return $arr;
				}
			}
		}

		return $result;
		
	}

	public function index(Request $request){
		if(!$request->header('API')){
			$response = [
				"msg" => "Missing Parameters",
				"code" => 105
			];
			Helper::saveLog('Spade error API', $this->provider_db_id,  '', $response);
			return $response;
		}
		$header = [
            'API' => $request->header('API'),
        ];
		$data = file_get_contents("php://input");
		$details = json_decode($data);
		Helper::saveLog('Spade '.$header['API'], $this->provider_db_id,  json_encode($details), $header);
		
		if($details->merchantCode != $this->merchantCode){
			$response = [
				"msg" => "Merchant Not Found",
				"code" => 10113
			];
			Helper::saveLog('Spade index error', $this->provider_db_id,  json_encode($details), $response);
			return response($response,200)
                    ->header('Content-Type', 'application/json');
		}

		if($header['API'] == 'authorize'){
			$response = $this->_authorize($details,$header);
		}elseif($header['API'] == 'getBalance'){
			$response = $this->_getBalance($details,$header);
		}elseif($header['API'] == 'transfer'){
			$response = $this->_transfer($details,$header);
		}
		return response($response,200)
                    ->header('Content-Type', 'application/json');
	}

	public function _authorize($details,$header){
		$acctId =  ProviderHelper::explodeUsername('_', $details->acctId);
		$client_details = Providerhelper::getClientDetails('player_id', $acctId);
		if ($client_details != null) {
			$response = [
				"acctInfo" => [
					"acctId" => $this->prefix.'_'.$acctId,
					"balance" => floatval(number_format((float)$client_details->balance, 2, '.', '')),
					"userName" => $this->prefix.$acctId,
					"currency" => $client_details->default_currency,
					"siteId" => $this->siteId
				],
				"merchantCode" => $this->merchantCode,
				"msg" => "success",
				"code" => 0,
				"serialNo" => $details->serialNo
			];
			Helper::saveLog('Spade '.$header['API'].' process', $this->provider_db_id, json_encode($details), $response);
			return $response;
		} else {
			$response = [
				"msg" => "Invalid Acct ID",
				"code" => 113
			];
			Helper::saveLog('Spade '.$header['API'].' _authorize error', $this->provider_db_id,  json_encode($details), $response);
			return $response;
		}
		
	}

	public function _getBalance($details,$header){
		$acctId =  ProviderHelper::explodeUsername('_', $details->acctId);
		$client_details = Providerhelper::getClientDetails('player_id', $acctId);
		if ($client_details != null) {
			$response = [
				"acctInfo" => [
					"acctId" => $this->prefix.'_'.$acctId,
					"balance" => floatval(number_format((float)$client_details->balance, 2, '.', '')),
					"userName" => $this->prefix.$acctId,
					"currency" => $client_details->default_currency,
				],
				"merchantCode" => $this->merchantCode,
				"msg" => "success",
				"code" => 0,
				"serialNo" => $details->serialNo
			];
			Helper::saveLog('Spade '.$header['API'].' process', $this->provider_db_id, json_encode($details), $response);
			return $response;
		} else {
			$response = [
				"msg" => "Invalid Acct ID",
				"code" => 113
			];
			Helper::saveLog('Spade '.$header['API'].' error', $this->provider_db_id,  json_encode($details), $response);
			return $response;
		}
    	
	}

	public function _transfer($details,$header){
		$acctId =  ProviderHelper::explodeUsername('_', $details->acctId);
		$client_details = Providerhelper::getClientDetails('player_id', $acctId);
		if ($client_details == null) {
			$response = [
				"msg" => "Invalid Acct ID",
				"code" => 113
			];
			Helper::saveLog('Spade '.$header['API'].'', $this->provider_db_id,  json_encode($details), $response);
			return $response;
		}

		$game_details = $this->findGameDetails('game_code', $this->provider_db_id, $details->gameCode);
		if ($game_details == null) {
			$response = [
				"msg" => "System Error",
				"code" => 1
			];
			Helper::saveLog('Spade '.$header['API'].'', $this->provider_db_id,  json_encode($details), $response);
			return $response;
		}

		if($details->type == 1){
			return $this->_placeBet($details,$header,$client_details,$game_details);
		}else if($details->type == 2){
			return $this->_cancelBet($details,$header,$client_details,$game_details);
		}else if($details->type == 7){
			return $this->_bonus($details,$header,$client_details,$game_details);
		}else if($details->type == 4){
			return $this->_payout($details,$header,$client_details,$game_details);
		}
	}

	public function _placeBet($details,$header,$client_details,$game_details){
		try{
	 		ProviderHelper::idenpotencyTable($this->prefix.'_'.$details->serialNo);
		}catch(\Exception $e){
			$bet_transaction = GameTransactionMDB::findGameExt($details->serialNo, 1,'round_id', $client_details);
			if ($bet_transaction != 'false') {
                if ($bet_transaction->mw_response == 'null') {
                    $response = [
						"msg" => "Acct Exist",
						"code" => 50099
					];
					Helper::saveLog('Spade '.$header['API'].' null idom', $this->provider_db_id,  json_encode($details), $response);
                }else {
                    $response = $bet_transaction->mw_response;
                    Helper::saveLog('Spade '.$header['API'].' found dubplicate', $this->provider_db_id,  json_encode($details), json_decode($response));
                }
            } else {
                $response = [
					"msg" => "Acct Not Found",
					"code" => 50100
				];
				Helper::saveLog('Spade '.$header['API'].' not found dubplicate', $this->provider_db_id,  json_encode($details), $response);
            } 
			return $response;
		}
		try{
            $game_trans_id = ProviderHelper::idGenerate($client_details->connection_name,1);
            $game_trans_ext_id = ProviderHelper::idGenerate($client_details->connection_name,2);
			$bet_amount = $details->amount;
			$provider_trans_id = $details->transferId;
			$bet_id = $details->serialNo;
			$gameTransactionData = array(
	            "provider_trans_id" => $bet_id,
	            "token_id" => $client_details->token_id,
	            "game_id" => $game_details->game_id,
	            "round_id" => $provider_trans_id,
	            "bet_amount" => $bet_amount,
	            "win" => 5,
	            "pay_amount" => 0,
	            "income" => 0,
	            "entry_id" => 1,
	            "trans_status" => 1,
	            "operator_id" => $client_details->operator_id,
	            "client_id" => $client_details->client_id,
	            "player_id" => $client_details->player_id,
	        );
	        GameTransactionMDB::createGametransactionV2($gameTransactionData,$game_trans_id,$client_details);
	        $gameTransactionEXTData = array(
	            "game_trans_id" => $game_trans_id,
	            "provider_trans_id" => $provider_trans_id,
	            "round_id" => $bet_id,
	            "amount" => $bet_amount,
	            "game_transaction_type"=> 1,
	        );
	        GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$game_trans_ext_id,$client_details);
			//requesttosend, and responsetoclient client side
			try {
				$type = "debit";
				$rollback = false;
				$client_response = ClientRequestHelper::fundTransfer($client_details,$details->amount,$game_details->game_code,$game_details->game_name,$game_trans_ext_id,$game_trans_id,$type,$rollback);
	        } catch (\Exception $e) {
	        	$response = [
					"msg" => "System Error",
					"code" => 1
				];
                $createGameTransactionLog = [
                    "connection_name" => $client_details->connection_name,
                    "column" =>[
                        "game_trans_ext_id" => $game_trans_ext_id,
                        "request" => json_encode($details),
                        "response" => json_encode($response),
                        "log_type" => "provider_details",
                        "transaction_detail" => "failed",
                        "general_details" => "failed",
                    ]
                ];
                ProviderHelper::queTransactionLogs($createGameTransactionLog);
	            // $updateTransactionEXt = array(
	            //     "mw_response" => json_encode($response),
	            //     'mw_request' => json_encode("FAILED"),
	            //     'client_response' => json_encode("FAILED"),
	            //     "transaction_detail" => "FAILED",
	            //     "general_details" => "FAILED"
	            // );
	            // GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
	            $updateGameTransaction = [
	                "win" => 2,
	                'trans_status' => 5
	            ];
	            GameTransactionMDB::updateGametransactionV2($updateGameTransaction, $game_trans_id, $client_details);
			    return $response;
	        }
	        if (isset($client_response->fundtransferresponse->status->code)) {
	        	ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
	        	switch ($client_response->fundtransferresponse->status->code) {
					case "200":
						$num = $client_response->fundtransferresponse->balance;
						$response = [
							"transferId" => (string)$provider_trans_id,
							"merchantCode" => $this->merchantCode,
							"acctId" => $details->acctId,
							"balance" => floatval(number_format((float)$num, 2, '.', '')),
							"msg" => "success",
							"code" => 0,
							"serialNo" => $details->serialNo,
						];
                        $createGameTransactionLog = [
                            "connection_name" => $client_details->connection_name,
                            "column" =>[
                                "game_trans_ext_id" => $game_trans_ext_id,
                                "request" => json_encode($details),
                                "response" => json_encode($response),
                                "log_type" => "provider_details",
                                "transaction_detail" => "success",
                                "general_details" => "success",
                            ]
                        ];
                        ProviderHelper::queTransactionLogs($createGameTransactionLog);
						break;
					default:
						$response = [
							"msg" => "INSUFFICIENT BALANCE",
							"code" => 50110
						];
	          			
                        $createGameTransactionLog = [
                            "connection_name" => $client_details->connection_name,
                            "column" =>[
                                "game_trans_ext_id" => $game_trans_ext_id,
                                "request" => json_encode('failed'),
                                "response" => json_encode($response),
                                "log_type" => "provider_details",
                                "transaction_detail" => "failed",
                                "general_details" => "failed",
                            ]
                        ];
                        ProviderHelper::queTransactionLogs($createGameTransactionLog);
			            $updateGameTransaction = [
			                "win" => 2,
			                'trans_status' => 5
			            ];
			            GameTransactionMDB::updateGametransactionV2($updateGameTransaction, $game_trans_id, $client_details);
				}
	        }
		    return $response;
		}catch(\Exception $e){
			$response = [
				"msg" => "System Error",
				"code" => 1
			];
			Helper::saveLog('Spade '.$header['API'].' bet error = '.$e->getMessage(), $this->provider_db_id,  json_encode($details), $response);
			return $response;
		}		
	}

	public function _payout($details,$header,$client_details,$game_details){
		try{
	 		ProviderHelper::idenpotencyTable($this->prefix.'_'.$details->serialNo);
		}catch(\Exception $e){
			$bet_transaction = GameTransactionMDB::findGameExt($details->serialNo, 2,'round_id', $client_details);
			if ($bet_transaction != 'false') {
                if ($bet_transaction->mw_response == 'null') {
                    $response = [
						"msg" => "Acct Exist",
						"code" => 50099
					];
					Helper::saveLog('Spade '.$header['API'].' null idom', $this->provider_db_id,  json_encode($details), $response);
                }else {
                    $response = $bet_transaction->mw_response;
                    Helper::saveLog('Spade '.$header['API'].' found dubplicate', $this->provider_db_id,  json_encode($details), json_decode($response));
                }
            } else {
                $response = [
					"msg" => "Acct Not Found",
					"code" => 50100
				];
				Helper::saveLog('Spade '.$header['API'].' not found dubplicate', $this->provider_db_id,  json_encode($details), $response);
            } 
			return $response;
		}
		$bet_transaction = GameTransactionMDB::findGameTransactionDetails($details->referenceId, 'round_id',false, $client_details);
        if($bet_transaction == 'false'){
			$response = [
				"msg" => "Reference No Not found",
				"code" => 109
			];
			return $response;
		}
		$client_details->connection_name = $bet_transaction->connection_name;
		try{
			//get details on game_transaction
			$round_id = $bet_transaction->game_trans_id;
			$num = $client_details->balance + $details->amount;
			ProviderHelper::_insertOrUpdate($client_details->token_id, $num); 
			//temporary
			$response = [
				"transferId" => (string)$details->transferId,
				"merchantCode" => $this->merchantCode,
				"acctId" => $details->acctId,
				"balance" => floatval(number_format((float)$num, 2, '.', '')),
				"msg" => "success",
				"code" => 0,
				"serialNo" => $details->serialNo
			];
            $game_trans_ext_id = ProviderHelper::idGenerate($client_details->connection_name,2);
			$gameTransactionEXTData = array(
	            "game_trans_id" => $bet_transaction->game_trans_id,
	            "provider_trans_id" => $details->transferId,
	            "round_id" => $details->serialNo,
	            "amount" => $details->amount,
	            "game_transaction_type"=> 2,
	        );
	        GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$game_trans_ext_id,$client_details);
			$total_payamount_win = $bet_transaction->pay_amount + $details->amount;
			//Initialize data to pass
			$win = $total_payamount_win > 0  ?  1 : 0;  /// 1win 0lost
			$entry_id = $total_payamount_win > 0  ?  2 : 1; 
			$updateGameTransaction = [
	            'win' => 5,
	            'pay_amount' => $total_payamount_win,
	            'income' => $total_payamount_win - $bet_transaction->bet_amount,
	            'entry_id' => $entry_id,
	            'trans_status' => 2
	        ];
	        GameTransactionMDB::updateGametransactionV2($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
	        $body_details = [
	            "type" => "credit",
	            "win" => $win,
	            "token" => $client_details->player_token,
	            "rollback" => false,
	            "game_details" => [
	                "game_id" => $game_details->game_id
	            ],
	            "game_transaction" => [
	                "amount" => $details->amount
	            ],
	            "connection_name" => $bet_transaction->connection_name,
	            "game_trans_ext_id" => $game_trans_ext_id,
	            "game_transaction_id" => $bet_transaction->game_trans_id

	        ];

	        try {
	            $client = new Client();
	            $guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-bgFundTransferV2MultiDB',
	                [ 'body' => json_encode($body_details), 'timeout' => '2.00']
	            );
	            //THIS RESPONSE IF THE TIMEOUT NOT FAILED
	            Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($details), $response);
                
                $createGameTransactionLog = [
                    "connection_name" => $client_details->connection_name,
                    "column" =>[
                        "game_trans_ext_id" => $game_trans_ext_id,
                        "request" => json_encode($details),
                        "response" => json_encode($response),
                        "log_type" => "provider_details",
                        "transaction_detail" => "success",
                        "general_details" => "success",
                    ]
                ];
                ProviderHelper::queTransactionLogs($createGameTransactionLog);
	            return $response;
	        } catch (\Exception $e) {
	            Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($details), $response);
	            return $response;
	        }
		}catch(\Exception $e){
			$response = [
				"msg" => "System Error",
				"code" => 1
			];
			return $response;
		}
	}

	public function _cancelbet($details,$header,$client_details,$game_details){
		try{
			ProviderHelper::idenpotencyTable($this->prefix.'_'.$details->transferId);
		}catch(\Exception $e){
			$bet_transaction = GameTransactionMDB::findGameExt($details->transferId, 3,'transaction_id', $client_details);
			if ($bet_transaction != 'false') {
				if ($bet_transaction->mw_response == 'null') {
					$response = [
						"msg" => "Acct Exist",
						"code" => 50099
					];
					Helper::saveLog('Spade '.$header['API'].' null idom', $this->provider_db_id,  json_encode($details), $response);
				}else {
					$response = $bet_transaction->mw_response;
					Helper::saveLog('Spade '.$header['API'].' found dubplicate', $this->provider_db_id,  json_encode($details), json_decode($response));
				}
			} else {
				$response = [
					"msg" => "Acct Not Found",
					"code" => 50100
				];
				Helper::saveLog('Spade '.$header['API'].' not found dubplicate', $this->provider_db_id,  json_encode($details), $response);
			} 
			return $response;
		}

		//CHECKING if BET EXISTING game_transaction_ext IF FALSE no bet record
		$bet_transaction = GameTransactionMDB::findGameTransactionDetails($details->referenceId, 'round_id',false, $client_details);
        if($bet_transaction == 'false'){
			$response = [
				"msg" => "Reference No Not found",
				"code" => 109
			];
			return $response;
		}
		$client_details->connection_name = $bet_transaction->connection_name;
		try{
			//get details on game_transaction
			$round_id = $bet_transaction->game_trans_id;
			$num = $client_details->balance + $details->amount;
			ProviderHelper::_insertOrUpdate($client_details->token_id, $num); 
			//temporary
			$response = [
				"transferId" => (string)$details->transferId,
				"merchantCode" => $this->merchantCode,
				"acctId" => $details->acctId,
				"balance" => floatval(number_format((float)$num, 2, '.', '')),
				"msg" => "success",
				"code" => 0,
				"serialNo" => $details->serialNo
			];
			$gameTransactionEXTData = array(
	            "game_trans_id" => $bet_transaction->game_trans_id,
	            "provider_trans_id" => $details->transferId,
	            "round_id" => $details->serialNo,
	            "amount" => $details->amount,
	            "game_transaction_type"=> 3,
	            "provider_request" =>json_encode($details),
	            "mw_response" => json_encode($response),
	        );
            $game_trans_ext_id = ProviderHelper::idGenerate($client_details->connection_name,2);
	        GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$game_trans_ext_id,$client_details);
			$total_payamount_win = $bet_transaction->pay_amount + $details->amount;
			//Initialize data to pass
			$win = 4;  /// 1win 0lost
			$updateGameTransaction = [
	            'win' => 5,
	            'pay_amount' => $total_payamount_win,
	            'income' => $total_payamount_win - $bet_transaction->bet_amount,
	            'entry_id' => 2,
	            'trans_status' => 2
	        ];
	        GameTransactionMDB::updateGametransactionV2($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
	        $body_details = [
	            "type" => "credit",
	            "win" => $win,
	            "token" => $client_details->player_token,
	            "rollback" => true,
	            "game_details" => [
	                "game_id" => $game_details->game_id
	            ],
	            "game_transaction" => [
	                "amount" => $details->amount
	            ],
	            "connection_name" => $bet_transaction->connection_name,
	            "game_trans_ext_id" => $game_trans_ext_id,
	            "game_transaction_id" => $bet_transaction->game_trans_id

	        ];

	        try {
	            $client = new Client();
	            $guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-bgFundTransferV2MultiDB',
	                [ 'body' => json_encode($body_details), 'timeout' => '2.00']
	            );
	            //THIS RESPONSE IF THE TIMEOUT NOT FAILED
                
	            Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($details), $response);
                $createGameTransactionLog = [
                    "connection_name" => $client_details->connection_name,
                    "column" =>[
                        "game_trans_ext_id" => $game_trans_ext_id,
                        "request" => json_encode($details),
                        "response" => json_encode($response),
                        "log_type" => "provider_details",
                        "transaction_detail" => "success",
                        "general_details" => "success",
                    ]
                ];
                ProviderHelper::queTransactionLogs($createGameTransactionLog);
	            return $response;
	        } catch (\Exception $e) {
	            Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($details), $response);
	            return $response;
	        }
		}catch(\Exception $e){
			$response = [
				"msg" => "System Error",
				"code" => 1
			];
			return $response;
		}
	}
	public function _bonus($details,$header,$client_details,$game_details){
		$bet_amount = 0;
		$win_amount = $details->amount;
		$provider_trans_id = $details->transferId;
		$bet_id = $details->serialNo;
		try{
			ProviderHelper::idenpotencyTable($this->prefix.'_'.$details->transferId); //ticket id
		}catch(\Exception $e){
			$bet_transaction = GameTransactionMDB::findGameTransactionDetails($details->transferId, 'round_id',false, $client_details);
			if ($bet_transaction != 'false') {
				$response = [
					"transferId" => (string)$provider_trans_id,
					"merchantCode" => $this->merchantCode,
					"acctId" => $details->acctId,
					"balance" => floatval(number_format((float)$client_details->balance, 2, '.', '')),
					"msg" => "success",
					"code" => 0,
					"serialNo" => $details->serialNo,
				];
				return $response;
			} // end fist if game_transaction not found
		}
		$gameTransactionData = array(
			"provider_trans_id" => $bet_id,
			"token_id" => $client_details->token_id,
			"game_id" => $game_details->game_id,
			"round_id" => $provider_trans_id,
			"bet_amount" => 0,
			"win" => 5,
			"pay_amount" => $win_amount,
			"income" => $bet_amount - $win_amount,
			"entry_id" => 2,
			"trans_status" => 1,
			"operator_id" => $client_details->operator_id,
			"client_id" => $client_details->client_id,
			"player_id" => $client_details->player_id,
		);
        $game_trans_id = ProviderHelper::idGenerate($client_details->connection_name,1);
        $game_trans_ext_id = ProviderHelper::idGenerate($client_details->connection_name,2);
		GameTransactionMDB::createGametransactionV2($gameTransactionData,$game_trans_id ,$client_details);
		$gameTransactionEXTData = array(
			"game_trans_id" => $game_trans_id,
			"provider_trans_id" => $provider_trans_id,
			"round_id" => $bet_id,
			"amount" => $bet_amount,
			"game_transaction_type"=> 1,
		);
		GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$game_trans_ext_id,$client_details);
		$fund_extra_data = [
			'fundtransferrequest' => [
				'fundinfo' => [
					'freespin' => true
				]
			]
		];
		$client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount,$game_details->game_code,$game_details->game_name,$game_trans_ext_id,$game_trans_id,"debit",false, $fund_extra_data);
		if (isset($client_response->fundtransferresponse->status->code)) {
			ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
			switch ($client_response->fundtransferresponse->status->code) {
				case "200":
					$num = $client_response->fundtransferresponse->balance;
					$response = [
						"transferId" => (string)$provider_trans_id,
						"merchantCode" => $this->merchantCode,
						"acctId" => $details->acctId,
						"balance" => floatval(number_format((float)$num, 2, '.', '')),
						"msg" => "success",
						"code" => 0,
						"serialNo" => $details->serialNo,
					];
					$balance = $client_response->fundtransferresponse->balance + $details->amount;
					ProviderHelper::_insertOrUpdate($client_details->token_id, $balance); 
					$response = [
						"transferId" => (string)$details->transferId,
						"merchantCode" => $this->merchantCode,
						"acctId" => $details->acctId,
						"balance" => floatval(number_format((float)$balance, 2, '.', '')),
						"msg" => "success",
						"code" => 0,
						"serialNo" => $details->serialNo
					];
					$gameTransactionEXTData = array(
						"game_trans_id" => $game_trans_id,
						"provider_trans_id" => $details->transferId,
						"round_id" => $details->ticketId,
						"amount" => $details->amount,
						"game_transaction_type"=> 2,
					);
					GameTransactionMDB::createGameTransactionExtV2($gameTransactionEXTData,$game_trans_ext_id,$client_details);
					$createGameTransactionLog = [
                        "connection_name" => $client_details->connection_name,
                        "column" =>[
                            "game_trans_ext_id" => $game_trans_ext_id,
                            "request" => json_encode($details),
                            "response" => json_encode($response),
                            "log_type" => "provider_details",
                            "transaction_detail" => "success",
                            "general_details" => "success",
                        ]
                    ];
                    ProviderHelper::queTransactionLogs($createGameTransactionLog);
					//Initialize data to pass
					$win = $win_amount > 0  ?  1 : 0;  /// 1win 0lost
					$body_details = [
						"type" => "credit",
						"win" => $win,
						"token" => $client_details->player_token,
						"rollback" => false,
						"game_details" => [
							"game_id" => $game_details->game_id
						],
						"game_transaction" => [
							"amount" => $details->amount
						],
						"connection_name" => $client_details->connection_name,
						"game_trans_ext_id" => $game_trans_ext_id,
						"game_transaction_id" => $game_trans_id
		
					];
					
					if($details->gameCode == "B-FS02") {
						$freeroundID = $details->serialNo;
						$getFreespin = FreeSpinHelper::getFreeSpinDetails($freeroundID, "provider_trans_id" );
						Helper::saveLog('Spade FreeRound', $this->provider_db_id, json_encode($details),$freeroundID);
						if($getFreespin){	
							$getOrignalfreeroundID = explode("_",$freeroundID);
							$body_details["fundtransferrequest"]["fundinfo"]["freeroundId"] = $getOrignalfreeroundID[1]; //explod the provider trans use the original
							$status = ($getFreespin->spin_remaining - 1) == 0 ? 2 : 1;
							$updateFreespinData = [
								"status" => $status,
								"win" => $getFreespin->win + $details->amount,
								"spin_remaining" => $getFreespin->spin_remaining - 1
							];
							$updateFreespin = FreeSpinHelper::updateFreeSpinDetails($updateFreespinData, $getFreespin->freespin_id);
							if($status == 2 ){
								$body_details["fundtransferrequest"]["fundinfo"]["freeroundend"] = true; //explod the provider trans use the original
							} else {
								$body_details["fundtransferrequest"]["fundinfo"]["freeroundend"] = false; //explod the provider trans use the original
							}
							//create transction 
							$createFreeRoundTransaction = array(
								"game_trans_id" => $$bet_transaction->game_trans_id,
								'freespin_id' => $getFreespin->freespin_id
							);
							FreeSpinHelper::createFreeRoundTransaction($createFreeRoundTransaction);
						}
					}

					try {
						$client = new Client();
						$guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-bgFundTransferV2MultiDB',
							[ 'body' => json_encode($body_details), 'timeout' => '2.00']
						);
						//THIS RESPONSE IF THE TIMEOUT NOT FAILED
					} catch (\Exception $e) {
						Helper::saveLog($game_trans_ext_id, $this->provider_db_id, json_encode($details), $response);
	            		return $response;
					}
					Helper::saveLog('Spade '.$header['API'].' RESPONSE', $this->provider_db_id, json_encode($details), $response);
					return $response;
					break;
				default:
					$response = [
						"msg" => "System Error",
						"code" => 1
					];
			}
		}
		Helper::saveLog('Spade '.$header['API'].' RESPONSE', $this->provider_db_id,  json_encode($details), $response);
		return $response;
	}
    public static function findGameDetails($type, $provider_id, $identification) {
		    $game_details = DB::table("games as g")
				->join("providers as p","g.provider_id","=","p.provider_id");
				
		    if ($type == 'game_code') {
				$game_details->where([
			 		["g.sub_provider_id", "=", $provider_id],
			 		["g.game_code",'=', $identification],
			 	]);
			}
			$result= $game_details->first();
	 		return $result;
	}
}
