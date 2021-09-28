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

class FunkyGamesController extends Controller
{   
	public function __construct(){
        $this->Auth = "23d828a8-0bba-49e8-90a6-9007bb50de1f";
        $this->Agent = "funky";
        $this->provider_db_id = config('providerlinks.funkygames.provider_db_id');
    }

	public function gameList(Request $r){
		$paramsToSend = [
            'language' => 'en',
            'gameType' => 0,
        ];
        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json',
                'Authentication' => config('providerlinks.funkygames.Authentication'),
                'User-Agent' => config('providerlinks.funkygames.User-Agent'),
                'X-Request-ID' => $r->player_id,
            ]
        ]);
        $url = config('providerlinks.funkygames.api_url').'Funky/Game/GetGameList';
        $guzzle_response = $client->post($url,['body' => json_encode($paramsToSend)]
            );
            $gameList = json_decode($guzzle_response->getBody()->getContents());
            Helper::saveLog('funky games launch',config('providerlinks.funkygames.provider_db_id'), json_encode($paramsToSend), $gameList);

       	dd($gameList);
        return $gameList->url;
	}

	public function GetBalance(Request $req){
		Helper::saveLog('FunkyG GetBalance', $this->provider_db_id, json_encode($req->all()), "ENDPOINT HIT");
		try {
				if($req->header('Authentication') != $this->Auth){
					$response = [
						"errorCode" => 401,
						"errorMessage" => "Player Is Not Logged In"
					];
					return response($response,200)->header('Content-Type', 'application/json');
				}
				
				$client_details = ProviderHelper::getClientDetails('player_id', $req['playerId']);
				$playerBal = sprintf('%.2f', $client_details->balance);
				if($client_details == null){
					$response = [
						"errorCode" => 401,
						"errorMessage" => "Player Is Not Logged In"
					];
					return response($response,200)->header('Content-Type', 'application/json');
				}else{
					$response = [
						"errorCode" => 0,
						"errorMessage" => "NoError",
						"data" => [
							"balance" => floatval($playerBal)
						],
					];
				}
				Helper::saveLog('FunkyG GetBalance', $this->provider_db_id, json_encode($req->all()), json_encode($response));
				return response($response,200)
						->header('Content-Type','application/json');
		} catch (\Exception $e) {

			$response = [
				"errorCode" => 401,
				"errorMessage" => "Player Is Not Logged In"
			];
			Helper::saveLog('FunkyG GetBalance', $this->provider_db_id, json_encode($req->all()), json_encode($response));
			return response($response,200)
						->header('Content-Type','application/json');
	
		}	

	}

	public function CheckBet(Request $req){
			try {

				Helper::saveLog('FunkyG checkBet', $this->provider_db_id, json_encode($req->all()), "checkbet HIT");
					$transaction_id = $req['id'];
					$client_details = ProviderHelper::getClientDetails('player_id', $req['playerId']);
					$bet_transaction = $this->findGameTransactionDetails($transaction_id,'transaction_id',false,$client_details);
					if($bet_transaction != 'false'){
						$newDateFormat = new DateTime($bet_transaction->created_at);
						$strip = $newDateFormat->format('Y-m-d');
						$status = '';
						if($bet_transaction->win == 5){
							$status = "R";
						}else if($bet_transaction->win == 4){
							$status = "C";
						}else if($bet_transaction->win == 3){
							$status = "D";
						}else if($bet_transaction->win == 1){
							$status = "W";
						}else{
							$status = "L";
						}
						$response = [
							"errorCode" => 0,
							"errorMessage" => "NoError",
							"data" => [
								"refNo" => $bet_transaction->provider_trans_id,
								"stake" => $bet_transaction->bet_amount,
								"winAmount" => $bet_transaction->pay_amount,
								"status" => $status,
								"statementDate" => $strip
							],
						];
					}else{
						$response = [
							"errorCode" => 404,
							"errorMessage" => "Bet was not found"
						];
					}
						Helper::saveLog('FunkyG checkBet', $this->provider_db_id, json_encode($req->all()), $response);
					return response($response,200)
							->header('Content-Type','application/json');

			} catch (\Exception $e) {

				return $e->getMessage();
			
			}
	}

	public function PlaceBet(Request $req){

		Helper::saveLog('FunkyG PlaceBet', $this->provider_db_id, json_encode($req->all()), "Placebet HIT");
		$game_code = $req['bet']['gameCode'];
		$round_id = "D-".$req['bet']['refNo'];
		$bet_amount = $req['bet']['stake'];
		$provider_trans_id = $req['bet']['refNo'];
		$token_id = $req['sessionId'];
		$client_details = ProviderHelper::getClientDetails('token', $token_id);
        $game_details = Game::find($game_code, $this->provider_db_id);
		try{
            ProviderHelper::idenpotencyTable($round_id);
        }catch(\Exception $e){
            $response = [
				"errorCode" => 403,
				"errorMessage" => "Bet already exists"
			];
            return $response;
        }
	 	try{  
 			if($client_details != null ){
				Helper::saveLog('FunkyG Check CLient Deatails', $this->provider_db_id, json_encode($req->all()),$client_details);
		      	$find_bet = GameTransactionMDB::findGameTransactionDetails($round_id,'round_id',false,$client_details);
	        		if($find_bet  != 'false'){
				        	$client_details->connection_name = $find_bet->connection_name;
				        	$amount = $find_bet->bet_amount + $bet_amount;
				        	$game_transaction_id = $find_bet->game_trans_id;
				        	$updateGameTransaction = [
				        		'win'=>5,
				        		'bet_amount'=>$amount,
				        		'entry_id' => 1,
				        		'trans_status'=>1

				        	];
				        	Helper::savelog('funky Sidebet success', $this->provider_db_id,json_encode($req->all()),$updateGameTransaction);
				        	GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_transaction_id,$client_details);
	        		}else{
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
					        $game_transaction_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
					        Helper::saveLog('FunkyG Check gameTransactionData', $this->provider_db_id, json_encode($req->all()), $gameTransactionData);
					        $gameTransactionEXTData = array(
					            "game_trans_id" => $game_transaction_id,
					            "provider_trans_id" => $provider_trans_id,
					            "round_id" => $round_id,
					            "amount" => $bet_amount,
					            "game_transaction_type"=> 1,
					            "provider_request" =>json_encode($req->all()),
				            );
					        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details); 
					        $client_response = ClientRequestHelper::fundTransfer($client_details,$bet_amount, $game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, 'debit');
							        if(isset($client_response->fundtransferresponse->status->code)){
							        	$playerBal = sprintf('%.2f', $client_response->fundtransferresponse->balance);
							        	ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
							        	switch ($client_response->fundtransferresponse->status->code) {
							                    case '200':
								                     $http_status = 200;
								                     $response = [
								                          "errorCode" => 0,
								                          "errorMessage" => "NoError",
								                          "data" => [
								                          	"Balance" => floatval($playerBal)
								                          ]
							                        ];

							                        $updateTransactionEXt = array(
							                            "provider_request" =>json_encode($req->all()),
							                            "mw_response" => json_encode($response),
							                            'mw_request' => json_encode($client_response->requestoclient),
							                            'client_response' => json_encode($client_response->fundtransferresponse),
							                            'transaction_detail' => 'success',
							                            'general_details' => 'success',
							                        
							                        );
							                    	GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
							                    break;
							                    case '402':
							                            // ProviderHelper::updateGameTransactionStatus($game_transaction_id, 2, 99);
							                        $http_status = 200;
							                        $response = [
							                            
							                             "errorCodee" => 402,
							                             "errorMessage" => "Insufficient Balance"
							                          
							                        ];

							                        $updateTransactionEXt = array(
							                            "provider_request" =>json_encode($req->all()),
							                            "mw_response" => json_encode($response),
							                            'mw_request' => json_encode($client_response->requestoclient),
							                            'client_response' => json_encode($client_response->fundtransferresponse),
							                            'transaction_detail' => 'failed',
							                            'general_details' => 'failed',
							                        );
							                        GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
							    				break;
						                }
							        }


							Helper::saveLog('FunkyG Debit success', $this->provider_db_id, json_encode($req->all()), $response);
					        return response()->json($response, $http_status);
      				}//end else find bet

     	 	}else{
		       	$response = [
						"errorCode" => 401,
						"errorMessage" => "Player Is Not Logged In"
				];
				Helper::saveLog('FunkyG Error Client details', $this->provider_db_id, json_encode($req->all()), json_encode($response));
				return response($response,200)
								->header('Content-Type','application/json');
	       	}//end else client details 
     	}catch(\Exception $e){
        	return $e->getMessage();
   		}
	}

	public function SettleBet(Request $req){
		Helper::saveLog('FunkyG Settlebet', $this->provider_db_id, json_encode($req->all()), "settlebet HIT");
		$game_code = $req['betResultReq']['gameCode'];
		$round_id = "C-".$req['refNo'];
		$bet_amount = $req['betResultReq']['stake'];
		$trans_id = $req['refNo'];
		$pay_amount = $req['betResultReq']['winAmount'];
		$effectiveStake = $req['betResultReq']['effectiveStake'];
		$client_details = ProviderHelper::getClientDetails('player_id', $req['betResultReq']['playerId']);
		// dd($client_details);

        $game_details = Game::find($game_code, $this->provider_db_id);
        $bet_transaction = $this->findGameTransactionDetails($trans_id, 'transaction_id', false,$client_details);
        if($bet_transaction == 'false'){
        	$response = [
				"errorCode" => 404,
				"errorMessage" => "Bet was not found"
			];
	        return $response;
        }
        try {
        	ProviderHelper::idenpotencyTable($round_id);
        } catch (\Exception $e) {
        	if($bet_transaction->win == 4){
        		$response = [
					"errorCode" => 410,
					"errorMessage" => "Bet was already cancelled"
				];
	            return $response;
        	}
	        if($bet_transaction->win != 5){
	        	$response = [
					"errorCode" => 409,
					"errorMessage" => "Bet was already settled"
				];
	            return $response;
	        }

        }


        $newDateFormat = new DateTime($bet_transaction->created_at);
		$strip = $newDateFormat->format('Y-m-d');
        $client_details->connection_name = $bet_transaction->connection_name;
        $win_or_lost = $pay_amount > 0 ?  1 : 0;
        $entry_id = $pay_amount > 0 ?  2 : 1;
        $bet_amount = $bet_transaction->bet_amount;
        $game_transaction_id = $bet_transaction->game_trans_id;
        $income = $bet_transaction->bet_amount - $pay_amount;

        $winbBalance = $client_details->balance + $pay_amount;
        // dd($winbBalance);
        ProviderHelper::_insertOrUpdate($client_details->token_id, $winbBalance); 
        $playerBal = sprintf('%.2f', $winbBalance);
        $response = [
        	"errorCode" => 0,
        	"errorMessage" => "NoError",
        	"data" => [
        		"refNo" => $req['refNo'],
        		"balance" => floatval($playerBal),
        		"playerId" => $req['betResultReq']['playerId'],
        		"currency" => $client_details->default_currency,
        		"statementDate" => $strip
        	]
        ];


        $updateGameTransaction = [
			  'win' => $win_or_lost,
	          'pay_amount' => $pay_amount,
	          'income' => $income,
	          'entry_id' => $entry_id,
	          'trans_status' => 2
        ];
        GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
        $gameTransactionEXTData = array(
	              "game_trans_id" => json_encode($bet_transaction->game_trans_id),
	              "provider_trans_id" => $trans_id,
	              "round_id" => $round_id,
	              "amount" => $pay_amount,
	              "game_transaction_type"=> 2,
	              "provider_request" => json_encode($req->all()),
	              "mw_response" => json_encode($response),
	          );
      	$game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

  		$action_payload = [
            "type" => "custom", #genreral,custom :D # REQUIRED!
            "custom" => [
                "provider" => 'FunkyGames',
                "client_connection_name" => $client_details->connection_name,
                "win_or_lost" => $win_or_lost,
                "entry_id" => $entry_id,
                "pay_amount" => $pay_amount,
                "income" => $income,
                "game_trans_ext_id" => $game_trans_ext_id
            ],
            "provider" => [
                "provider_request" => json_encode($req->all()), #R
                "provider_trans_id"=> $trans_id, #R
                "provider_round_id"=> $round_id, #R
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
                          
        $client_response = ClientRequestHelper::fundTransfer_TG($client_details,$pay_amount,$game_details->game_code,$game_details->game_name,$bet_transaction->game_trans_id,'credit',false,$action_payload);

        $updateTransactionEXt = array(
                  "provider_request" =>json_encode($req->all()),
                  "mw_response" => json_encode($response),
                  'mw_request' => json_encode($client_response->requestoclient),
                  'client_response' => json_encode($client_response->fundtransferresponse),
                  'transaction_detail' => 'success',
                  'general_details' => 'success',
      	);
      	GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
      Helper::saveLog('FunkyG Settlebet success', $this->provider_db_id, json_encode($req->all()), $response);
      	return response($response,200)
                ->header('Content-Type', 'application/json');

	}

	public function CancelBet(Request $req){

		Helper::saveLog('FunkyG cancel', $this->provider_db_id, json_encode($req->all()), "cancel HIT");
		// dd($req->all());
		$trans_id = $req['refNo'];
		$round_id = "R-".$req['refNo'];
		$client_details = ProviderHelper::getClientDetails('player_id',$req['playerId']);
		$bet_transaction = $this->findGameTransactionDetails($trans_id, 'transaction_id', 1,$client_details);

		if($bet_transaction == 'false'){
        	$response = [
				"errorCode" => 404,
				"errorMessage" => "Bet was not found"
			];
	        return $response;
        }
        try {
        	ProviderHelper::idenpotencyTable($round_id);
        } catch (\Exception $e) {
        	if($bet_transaction->win == 4){
        		$response = [
					"errorCode" => 410,
					"errorMessage" => "Bet was already cancelled"
				];
	            return $response;
        	}
	        if($bet_transaction->win != 5){
	        	$response = [
					"errorCode" => 409,
					"errorMessage" => "Bet was already settled"
				];
	            return $response;
	        }

        }

        $game_details = Game::findbyid($bet_transaction->game_id);
        $client_details->connection_name = $bet_transaction->connection_name;
        $win_or_lost = 4;
        $entry_id = 2;
        $income = $bet_transaction->bet_amount -  $bet_transaction->bet_amount ;

        $updateGameTransaction = [
            'win' => $win_or_lost,
            'pay_amount' => $bet_transaction->bet_amount,
            'income' => $income,
            'entry_id' => $entry_id,
            'trans_status' => 3
        ];
        GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
        $gameTransactionEXTData = array(
                "game_trans_id" => $bet_transaction->game_trans_id,
                "provider_trans_id" => $trans_id,
                "round_id" => $round_id,
                "amount" => $bet_transaction->bet_amount,
                "game_transaction_type"=> 3,
                "provider_request" =>json_encode($req->all()),
                "mw_response" => null,
        );
        $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

        $client_response = ClientRequestHelper::fundTransfer($client_details, $bet_transaction->bet_amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $bet_transaction->game_trans_id, 'credit', "true");
        if (isset($client_response->fundtransferresponse->status->code)) {
                            
            switch ($client_response->fundtransferresponse->status->code) {
                case '200':
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    $response = [
                        "errorCode" => 0,
                        "errorMessage" => "NoError",
                        "data" => [
                        	"refNo" => $trans_id
                        ]
                    ];
                    break;
            }

            $updateTransactionEXt = array(
                "provider_request" =>json_encode($req->all()),
                "mw_response" => json_encode($response),
                'mw_request' => json_encode($client_response->requestoclient),
                'client_response' => json_encode($client_response->fundtransferresponse),
                'transaction_detail' => 'success',
                'general_details' => 'success',
            );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);

        }
        Helper::saveLog('OnlyPlay', $this->provider_db_id, json_encode($req->all()),$response);
        return response($response,200)
                ->header('Content-Type', 'application/json');

	}


	public static  function findGameTransactionDetails($identifier, $type, $entry_id= false, $client_details) {
        $entry_type = "";
        if ($entry_id) {
            $entry_type = "AND gt.entry_id = ". $entry_id;
        }

        if ($type == 'transaction_id') {
            $where = 'where gt.provider_trans_id = "'.$identifier.'" '.$entry_type.'';
        } elseif ( $type == 'game_transaction') {
            $where = 'where gt.game_trans_id = '.$identifier;
        } elseif ( $type == "round_id") {
            $where = 'where gt.round_id = "'.$identifier.'" '.$entry_type.'';
        }
        try {
            $details = [];
            $connection = config("serverlist.server_list.".$client_details->connection_name.".connection_name");
            $status = GameTransactionMDB::checkDBConnection($connection);
            if ( ($connection != null) && $status) {
                $connection = config("serverlist.server_list.".$client_details->connection_name);
                $details = DB::connection( $connection["connection_name"])->select('select created_at,game_id,entry_id,bet_amount,game_trans_id,pay_amount,round_id,provider_trans_id,income,win,trans_status from `'.$connection['db_list'][1].'`.`game_transactions` gt '.$where.' LIMIT 1');
            }
            if ( !(count($details) > 0 )) {

                if(GameTransactionMDB::checkDBConnection(config("serverlist.server_list.default.connection_name"))){
                    $connection_default = config("serverlist.server_list.default");
                    $data = DB::connection( $connection_default["connection_name"])->select('select created_at,game_id,entry_id,bet_amount,game_trans_id,pay_amount,round_id,provider_trans_id,income,win,trans_status from `'.$connection_default['db_list'][1].'`.`game_transactions` gt '.$where.' LIMIT 1');
                    
                    if ( count($data) > 0  ) {
                        $connection_name = "default";
                        $details = $data;
                        
                    }
                } 
   
            }
            $count = count($details);
            $connection_name = $client_details->connection_name;
            if ($count > 0 ) {
                $details[0]->connection_name = $connection_name;
            }
            return $count > 0 ? $details[0] : 'false';
        } catch (\Exception $e) {
            return 'false';
        }

    }

	
}