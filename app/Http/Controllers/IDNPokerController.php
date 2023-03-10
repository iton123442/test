<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Helpers\ProviderHelper;
use App\Helpers\IDNPokerHelper;
use App\Helpers\Helper;
use App\Helpers\ClientRequestHelper;
use App\Http\Controllers\TransferWalletAggregator\TWHelpers;
use DB;
use App\Models\GameTransactionMDB;
use PhpParser\Node\Stmt\ElseIf_;
use Webpatser\Uuid\Uuid;

class IDNPokerController extends Controller
{

    public function __construct(){
        // $this->middleware('oauth', ['except' => []]);
        $this->provider_db_id = config('providerlinks.idnpoker.PROVIDER_ID');
    }

    public static function getPlayerBalance(Request $request) {
        ProviderHelper::saveLogWithExeption('IDN TW GetPlayerBalance', config('providerlinks.idnpoker.PROVIDER_ID'), json_encode($request->all()), 'HIT');
        if (!$request->has("token")) {
            $msg = array("status" => "error", "message" => "Token Invalid");
            ProviderHelper::saveLogWithExeption('IDN TW GetPlayerBalance', config('providerlinks.idnpoker.PROVIDER_ID'), json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }

        $client_details = ProviderHelper::getClientDetails('token', $request->token);
        
        if ($client_details == null || $client_details == 'false') {
            $msg = array("status" => "error", "message" => "Token Invalid");
            ProviderHelper::saveLogWithExeption('IDN TW GetPlayerBalance', config('providerlinks.idnpoker.PROVIDER_ID'), json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }

        try {
            $client_response = Providerhelper::playerDetailsCall($client_details->player_token);
            if ($client_response != "false") {
                $balance = round($client_response->playerdetailsresponse->balance, 2);
                $msg = array(
                    "status" => "ok",
                    "message" => "Balance Request Success",
                    "balance" => $balance
                );

                ProviderHelper::saveLogWithExeption('IDN TW GetPlayerBalance', config('providerlinks.idnpoker.PROVIDER_ID'), json_encode($request->all()), $msg);
                return response($msg, 200)->header('Content-Type', 'application/json');
            } 

            $msg = array(
                "status" => "error",
                "message" => "Player Not found",
                "balance" => "0.00"
            );
            ProviderHelper::saveLogWithExeption('IDN TW GetPlayerBalance', config('providerlinks.idnpoker.PROVIDER_ID'), json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $msg = array(
                "status" => "error",
                "message" => $e->getMessage(),
                "balance" => 0.00
            );
            ProviderHelper::saveLogWithExeption('IDN TW GetPlayerBalance', config('providerlinks.idnpoker.PROVIDER_ID'), json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }
    }


    public function getPlayerWalletBalance(Request $request)
    {
        ProviderHelper::saveLogWithExeption('IDN getPlayerWalletBalance', $this->provider_db_id, json_encode($request->all()), "HIT");
        $client_details = ProviderHelper::getClientDetails('token', $request->token);
        if ($client_details == 'false') {
            $msg = array("status" => "error", "message" => "Token Invalid");
            ProviderHelper::saveLogWithExeption('IDN getPlayerWalletBalance', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }

        $game_details = Helper::getInfoPlayerGameRound($request->token);
        if ($game_details == false) {
            $msg = array("status" => "error", "message" => "Game Not Found");
            ProviderHelper::saveLogWithExeption('IDN getPlayerWalletBalance', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }

        // $player_id = "TGTW_". $client_details->player_id;
        $auth_token = IDNPokerHelper::getAuthPerOperator($client_details, config('providerlinks.idnpoker.type')); 
        // $player_id = config('providerlinks.idnpoker.prefix').$client_details->player_id;
        $player_id = $client_details->username;
        $data = IDNPokerHelper::playerDetails($player_id,$auth_token);
        if ($data != "false") {
            $msg = array(
                "status" => "success",
                "balance" => $data["balance"],
            );
            ProviderHelper::saveLogWithExeption('IDN getPlayerWalletBalance', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');

        }
        $msg = array(
            "status" => "error",
            "balance" => "0.00",
        );
        ProviderHelper::saveLogWithExeption('IDN getPlayerWalletBalance', $this->provider_db_id, json_encode($request->all()), $msg);
        return response($msg, 200)->header('Content-Type', 'application/json');
        
    }

    public function makeDeposit(Request $request){
        ProviderHelper::saveLogWithExeption('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), "HIT ENDPOINT");
        // $msg = array(
        //     "status" => "ok",
        //     "message" => "Transaction success",
        //     "balance" => "0.00"
        // );
        // return response($msg, 200)->header('Content-Type', 'application/json');
        if (!$request->has("token") || !$request->has("player_id") || !$request->has("amount")) {
            $msg = array("status" => "error", "message" => "Missing Required Fields!");
            ProviderHelper::saveLogWithExeption('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }
        /**
         * -----------------------------------------------
         *  GET DETAILS PLAYER AND CLIENTS
         * -----------------------------------------------
         */        
        $client_details = ProviderHelper::getClientDetails('token', $request->token);
        if ($client_details == null) {
            $msg = array("status" => "error", "message" => "Invalid Token or Token not found");
            ProviderHelper::saveLogWithExeption('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }
         /**
         * -----------------------------------------------
         *  CHECK IF LEGIT AMOUNT 
         * -----------------------------------------------
         */    
        if (!is_numeric($request->amount) || $request->amount < 0) {
            $msg = array(
                "status" => "error",
                "message" => "Undefined Amount!"
            );
            ProviderHelper::saveLogWithExeption('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }

        /**
         * -----------------------------------------------
         *  IF DEPOSIT EQUAL TO 0
         * -----------------------------------------------
         */    
        if ($request->amount == 0) {
            $msg = array(
                "status" => "ok",
                "message" => "Transaction success",
                "balance" => "0.00"
            );
            ProviderHelper::saveLogWithExeption('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }
         /**
         * -----------------------------------------------
         *  GET GAME DETAILS
         * -----------------------------------------------
         */    
        $game_details = Helper::getInfoPlayerGameRound($request->token);
        if($game_details) {
            /**
             * -----------------------------------------------
             *  CLIENT ENDPOINT PLAYER DETAILS CALL
             * -----------------------------------------------
             */
            // --------------------------------------
            $client_response = Providerhelper::clientPlayerDetailsCall($client_details);
            ProviderHelper::saveLogWithExeption('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $client_response);
            if($client_response != "false"){
                if(isset($client_response->playerdetailsresponse->status->code) && $client_response->playerdetailsresponse->status->code == 200){
                    $auth_token = IDNPokerHelper::getAuthPerOperator($client_details, config('providerlinks.idnpoker.type')); 
                    // $player_id = config('providerlinks.idnpoker.prefix').$client_details->player_id;
                    $balance = $client_response->playerdetailsresponse->balance;
                    $player_id = $client_details->username;
                    $transactionChecker = [
                        "action" => 1,
                        "player_id" =>  $player_id,
                    ];
                    $client_transaction_id_provider = IDNPokerHelper::getCheckTransactionIDAvailable($transactionChecker,$auth_token);
                    if(!$client_transaction_id_provider){
                        $msg = array(
                            "status" => "error",
                            "message" => "Id transaction already exist!"
                        );
                        ProviderHelper::saveLogWithExeption('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
                        return response($msg, 200)->header('Content-Type', 'application/json');
                    }
                    // $client_transaction_id_provider = Uuid::generate()->string;
                    $client_transaction_id =  $client_details->client_id."_".$client_transaction_id_provider;
                    /**
                     * -----------------------------------------------
                     *  CREATE TRANSACTION WALLET
                     * -----------------------------------------------
                     */   
                    try {
                        $data_accounts = [
                            "player_id" => $client_details->player_id,
                            "type" => 1,
                            "amount" => $request->amount,
                            "client_id" => $client_details->client_id,
                            "operator_id" => $client_details->operator_id,
                            "client_transaction_id" => $client_transaction_id, //UNIQUE TRANSACTION
                            "status" => 5,
                        ];
                        $game_trans_id = TWHelpers::createTWPlayerAccounts($data_accounts);
                    } catch (\Exception $e) {
                        $msg = array(
                            "status" => "error",
                            "message" => "Transaction Id Already Exist",
                        );
                        return response($msg, 200)->header('Content-Type', 'application/json');
                    }
                    
                    $data = [
                        "client_transaction_id" => $client_transaction_id, //UNIQUE TRANSACTION
                        "wallet_type" => 1, // DEPOSIT
                        "tw_account_id" => $game_trans_id
                    ];
                    $game_trans_ext_id = TWHelpers::createTWPlayerAccountsRequestLogs($data);
                    
                    ProviderHelper::saveLogWithExeption('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), "FUNDSTRANSFER HIT");
                    $fund_extra_data = [
                        'provider_name' => $game_details->provider_name,
                        'connection_timeout' => 30,
                    ]; 
                    $clientFunds_response = ClientRequestHelper::fundTransfer($client_details, $balance, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_trans_id, "debit",false,$fund_extra_data);
                    if(isset($clientFunds_response->fundtransferresponse->status->code) 
                        && $clientFunds_response->fundtransferresponse->status->code == "200"){
                        $msg = array(
                            "status" => "ok",
                            "message" => "Transaction success",
                            "balance" => $clientFunds_response->fundtransferresponse->balance
                        );
                        $log_data = [
                            "client_request" => json_encode($clientFunds_response->requestoclient),
                            "mw_response" =>  json_encode($clientFunds_response->fundtransferresponse),
                            "status_code" => "200",
                            "general_details" => json_encode($msg)
                        ];
                        TWHelpers::updateTWPlayerAccountsRequestLogs($log_data, $game_trans_ext_id);
                        
                        $data_deposit = [
                            "amount" => $balance,
                            "transaction_id" => $client_transaction_id_provider,
                            "player_id" => $player_id
                        ];
                        $provider_response = IDNPokerHelper::deposit($data_deposit,$auth_token);
                        if($provider_response != "false"){
                            if (!isset($provider_response["error"])) {
                                $data = [
                                    "client_transaction_id" => $client_transaction_id, //UNIQUE TRANSACTION
                                    "wallet_type" => 1,
                                    "tw_account_id" => $game_trans_id,
                                    "client_request" => json_encode($data_deposit),
                                    "mw_response" =>  json_encode($provider_response),
                                    "status_code" => "200",
                                    "general_details" => json_encode($msg)
                                ];
                                $game_trans_ext_id = TWHelpers::createTWPlayerAccountsRequestLogs($data);
                                //INITIATE WITHDDRAW
                                $createRestricted = [
                                    "player_id" => $client_details->player_id,
                                    "status" => 1, //
                                    "game_trans_id" => $game_trans_id,
                                    "player_token" => $client_details->player_token
                                    // "client_transaction_id" => $client_transaction_id_provider,
                                ];
                                IDNPokerHelper::createPlayerRestricted($createRestricted);
                                /**
                                 * -----------------------------------------------
                                 *  RETURN RESPONSE
                                 * -----------------------------------------------
                                 */   
                                $account_data = [
                                    "status" => 1
                                ];
                                TWHelpers::updatePlayerAccount($account_data, $game_trans_id);
                                 /**
                                 * -----------------------------------------------
                                 *  RETURN RESPONSE
                                 * -----------------------------------------------
                                 */   
                                ProviderHelper::saveLogWithExeption('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
                                return response($msg, 200)->header('Content-Type', 'application/json');
                            }
                            $message = "Deposit Failed";
                            if (isset($provider_response["message"])) {
                                $message = $provider_response["message"];
                            }
                        }
                        // $provider_response = [];
                        // $message = "dasfdasf";
                        $data = [
                            "client_transaction_id" => $client_transaction_id, //UNIQUE TRANSACTION
                            "wallet_type" => 1,
                            "tw_account_id" => $game_trans_id,
                            "client_request" => json_encode($data_deposit),
                            "mw_response" =>  json_encode($provider_response),
                            "status_code" => "404",
                            "general_details" => json_encode($message),
                        ];
                        TWHelpers::createTWPlayerAccountsRequestLogs($data);
                        $data = [
                            "client_transaction_id" => $client_transaction_id, //UNIQUE TRANSACTION
                            "wallet_type" => 1,
                            "tw_account_id" => $game_trans_id,
                        ];
                        $game_trans_ext_id = TWHelpers::createTWPlayerAccountsRequestLogs($data);
                        /**
                         * -----------------------------------------------
                         *  IF NOT SUCCESS ROLLBACK TRANSACTION
                         * -----------------------------------------------
                         */   
                        ProviderHelper::saveLogWithExeption('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), "ROLLBACK HIT");
                        $fund_extra_data = [
                            'provider_name' => $game_details->provider_name,
                            'connection_timeout' => 30,
                        ]; 
                        $clientFundsRollback_response = ClientRequestHelper::fundTransfer($client_details, $balance, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_trans_id, "credit", true,$fund_extra_data);
                        if (isset($clientFundsRollback_response->fundtransferresponse->status->code) 
                            && $clientFundsRollback_response->fundtransferresponse->status->code == "200"){
                            $msg = array("status" => "error", "message" => $message);
                            $account_data = [
                                "status" => 2
                            ];
                            TWHelpers::updatePlayerAccount($account_data, $game_trans_id);
                            $log_data = [
                                "client_request" => json_encode($clientFundsRollback_response->requestoclient),
                                "mw_response" =>  json_encode($clientFundsRollback_response->fundtransferresponse),
                                "status_code" => "200",
                                "general_details" => json_encode($msg)
                            ];
                            TWHelpers::updateTWPlayerAccountsRequestLogs($log_data, $game_trans_ext_id);
                             /**
                             * -----------------------------------------------
                             *  RETURN RESPONSE
                             * -----------------------------------------------
                             */   
                            ProviderHelper::saveLogWithExeption('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
                            return response($msg, 200)->header('Content-Type', 'application/json');
                        } else {
                            $log_data = [
                                "client_request" => json_encode($clientFundsRollback_response->requestoclient),
                                "mw_response" =>  json_encode($clientFundsRollback_response->fundtransferresponse),
                                "status_code" => "404",
                                "general_details" => json_encode("INITIATE REFUND")
                            ];
                            TWHelpers::updateTWPlayerAccountsRequestLogs($log_data, $game_trans_ext_id);
                            $transactionDetails = [
                                "game_trans_ext_id" => $game_trans_ext_id,
                                "game_trans_id" => $game_trans_id
                            ];
                            $checkTransaction =  ClientRequestHelper::CheckerTransactionRequest($client_details, $transactionDetails);
                            if(!$checkTransaction){
                                $account_data = [
                                    "status" => 5 // nee to sent to client
                                ];
                                TWHelpers::updatePlayerAccount($account_data, $game_trans_id);
                                // $clientFundsRollback_response = ClientRequestHelper::fundTransfer($client_details, $balance, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_trans_id, "credit", true,$fund_extra_data);
                                // $transactionDetails = [
                                //     "game_trans_ext_id" => $game_trans_ext_id,
                                //     "game_trans_id" => $game_trans_id
                                // ];
                                // $checkTransaction =  ClientRequestHelper::CheckerTransactionRequest($client_details, $transactionDetails);
                                // if(!$checkTransaction){
                                //     $msg = array("status" => "error", "message" => "Deposit Failed");
                                //     $log_data = [
                                //         "client_request" => json_encode($clientFundsRollback_response->requestoclient),
                                //         "mw_response" =>  json_encode($clientFundsRollback_response->fundtransferresponse),
                                //         "status_code" => "404",
                                //         "general_details" => json_encode($msg)
                                //     ];
                                //     TWHelpers::updateTWPlayerAccountsRequestLogs($log_data, $game_trans_ext_id);
                                //     $account_data = [
                                //         "status" => 5 // nee to sent to client
                                //     ];
                                //     TWHelpers::updatePlayerAccount($account_data, $game_trans_id);
                                //     /**
                                //      * -----------------------------------------------
                                //      *  RETURN RESPONSE
                                //      * -----------------------------------------------
                                //      */   
                                //     ProviderHelper::saveLogWithExeption('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
                                //     return response($msg, 200)->header('Content-Type', 'application/json');
                                // }
                            }
                            $account_data = [
                                "status" => 2 // nee to sent to client
                            ];
                            TWHelpers::updatePlayerAccount($account_data, $game_trans_id);
                        }
                    } else {
                        $log_data = [
                            "client_request" => json_encode($clientFunds_response->requestoclient),
                            "mw_response" =>  json_encode($clientFunds_response->fundtransferresponse),
                            "status_code" => "402",
                            "general_details" => json_encode("FAILED RESPONSE")
                        ];
                        TWHelpers::updateTWPlayerAccountsRequestLogs($log_data, $game_trans_ext_id);
                        $transactionDetails = [
                            "game_trans_ext_id" => $game_trans_ext_id,
                            "game_trans_id" => $game_trans_id
                        ];
                        
                        $checkTransaction =  ClientRequestHelper::CheckerTransactionRequest($client_details, $transactionDetails);
                        if(!$checkTransaction){
                            $msg = array("status" => "error", "message" => "Deposit Failed");
                            $account_data = [
                                "status" => 5
                            ];
                            TWHelpers::updatePlayerAccount($account_data, $game_trans_id);
                            ProviderHelper::saveLogWithExeption('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), "FAILED NOT SET");
                            return response($msg, 200)->header('Content-Type', 'application/json');
                        }
                        ProviderHelper::saveLogWithExeption('IDN DEPOSIT', $this->provider_db_id, json_encode($clientFunds_response), "FUNDSTRANSFER RESPONSE");
                        $account_data = [
                            "status" => 2 // nee to sent to client
                        ];
                        TWHelpers::updatePlayerAccount($account_data, $game_trans_id);
                    }
                } 
            }
            $msg = array("status" => "error", "message" => "Invalid Token or Token not found");
            ProviderHelper::saveLogWithExeption('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        } else {
            $msg = array("status" => "error", "message" => "Invalid Token or Game not found");
            ProviderHelper::saveLogWithExeption('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }
    }

    public function makeWithdraw(Request $request)
    {
        ProviderHelper::saveLogWithExeption('IDN WITHDRAW ', $this->provider_db_id, json_encode($request->all()), "HIT WITHDRAW");
        if (!$request->has("token") || !$request->has("player_id") ) {
            $msg = array("status" => "error", "message" => "Missing Required Fields!");
            ProviderHelper::saveLogWithExeption('IDN WITHDRAW Missing Fields', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }
        /**
         * -----------------------------------------------
         *  GET DETAILS PLAYER AND CLIENTS
         * -----------------------------------------------
         */        
        $client_details = ProviderHelper::getClientDetails('token', $request->token);
        if ($client_details == null) {
            $msg = array("status" => "error", "message" => "Invalid Token or Token not found");
            ProviderHelper::saveLogWithExeption('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }
         /**
         * -----------------------------------------------
         *  CHECK IF PLAYER HAS RESTRICTION
         * -----------------------------------------------
         */     
        $checkPlayerRestricted = IDNPokerHelper::checkPlayerRestricted($client_details->player_id);
        if($checkPlayerRestricted == "false"){
            $msg = array("status" => "error", "message" => "Please retry again!");
            ProviderHelper::saveLogWithExeption('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }
        /**
         * -----------------------------------------------
         *  GET GAME DETAILS
         * -----------------------------------------------
         */  
        $game_details = Helper::getInfoPlayerGameRound($request->token);
        if ($game_details) {
            $client_response = Providerhelper::clientPlayerDetailsCall($client_details);
            ProviderHelper::saveLogWithExeption('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $client_response);
            if($client_response != "false"){
                if(isset($client_response->playerdetailsresponse->status->code) && $client_response->playerdetailsresponse->status->code == 200){
                    /**
                     * -----------------------------------------------
                     *  GENERATE TRANSACTION ID UUID
                     * -----------------------------------------------
                     */
                    $auth_token = IDNPokerHelper::getAuthPerOperator($client_details, config('providerlinks.idnpoker.type')); 
                    // $player_id = config('providerlinks.idnpoker.prefix').$client_details->player_id;
                    $player_id = $client_details->username;
                    $transactionChecker = [
                        "action" => 2,
                        "player_id" =>  $player_id,
                    ];
                    $client_transaction_id_provider = IDNPokerHelper::getCheckTransactionIDAvailable($transactionChecker,$auth_token);
                    if(!$client_transaction_id_provider){
                        $msg = array(
                            "status" => "error",
                            "message" => "Id transaction already exist!"
                        );
                        ProviderHelper::saveLogWithExeption('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
                        return response($msg, 200)->header('Content-Type', 'application/json');
                    }
                    // $client_transaction_id_provider = '123213123123';
                    $client_transaction_id =  $client_details->client_id."_".$client_transaction_id_provider;   
                  
                    // $client_transaction_id_provider = Uuid::generate()->string; // USE FOR THE PROVIDER MAX 36
                    // $client_transaction_id =  $client_details->client_id."_".$client_transaction_id_provider; // USE ACCOUNT AND LOGS TABLE NAME client_transaction_id
                    // try{
                    //     ProviderHelper::idenpotencyTable($client_transaction_id);
                    // }catch(\Exception $e){
                    //     $msg = array("status" => "error", "message" => "Please retry again!");
                    //     ProviderHelper::saveLogWithExeption('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $msg);
                    //     return response($msg, 200)->header('Content-Type', 'application/json');
                    // }
                    /**
                     * -----------------------------------------------
                     *  GET provder BALANCE
                     * -----------------------------------------------
                     */   
                    // $auth_token = IDNPokerHelper::getAuthPerOperator($client_details, config('providerlinks.idnpoker.type')); 
                    // // $player_id = config('providerlinks.idnpoker.prefix').$client_details->player_id;
                    // $player_id = $client_details->username;
                    $data = IDNPokerHelper::playerDetails($player_id,$auth_token); // check balance
                    if(isset($data["userid"]) && isset($data["username"]) &&  isset($data["balance"]) ) {
                        /**
                         * -----------------------------------------------
                         *  PROVIDER WITHDRAW
                         * -----------------------------------------------
                         */
                        $amount_to_withdraw = $data["balance"];
                        $data_deposit = [
                            "amount" => $amount_to_withdraw,
                            "transaction_id" => $client_transaction_id_provider,
                            "player_id" => $player_id
                        ];

                        /**
                         * -----------------------------------------------
                         *  CREATE ACCOUNT AND LOGS
                         * -----------------------------------------------
                         */   
                        $wallet_type = 2;// withdraw 
                        $data_accounts = [
                            "player_id" => $client_details->player_id,
                            "type" => $wallet_type,
                            "amount" => $amount_to_withdraw,
                            "client_id" => $client_details->client_id,
                            "operator_id" => $client_details->operator_id,
                            "client_transaction_id" => $client_transaction_id, //UNIQUE TRANSACTION
                            "status" => 5,
                        ];
                        $game_trans_id = TWHelpers::createTWPlayerAccounts($data_accounts);

                        if($data["balance"] == 0 || $data["balance"] == "0") {
                            $provider_response = [];
                        } else {
                            $provider_response = IDNPokerHelper::withdraw($data_deposit, $auth_token);
                        } 
                        if($provider_response != "false"){
                            if (!isset($provider_response["error"])) {
                                $log_data = [
                                    "client_request" => json_encode($data_deposit),
                                    "mw_response" =>  json_encode($provider_response),
                                    "wallet_type" => $wallet_type,
                                    "client_transaction_id" => $client_transaction_id, //UNIQUE TRANSACTION
                                    "tw_account_id" => $game_trans_id,
                                    "status_code" => "200",
                                    "general_details" => json_encode("Provider Success Response")
                                ];
                                TWHelpers::createTWPlayerAccountsRequestLogs($log_data);
                                /**
                                 * -----------------------------------------------
                                 *  TO CLIENT PROCESS
                                 * -----------------------------------------------
                                 */   
                                $log_data = [
                                    "wallet_type" => $wallet_type,
                                    "client_transaction_id" => $client_transaction_id, //UNIQUE TRANSACTION
                                    "tw_account_id" => $checkPlayerRestricted->game_trans_id,
                                ];
                                $game_trans_ext_id = TWHelpers::createTWPlayerAccountsRequestLogs($log_data);
                                /**
                                 * -----------------------------------------------
                                 *  DELETE PLAYER RESTRICTED
                                 * -----------------------------------------------
                                 */   
                                IDNPokerHelper::deletePlayerRestricted($checkPlayerRestricted->idtw_player_restriction);
                                $fund_extra_data = [
                                    'provider_name' => $game_details->provider_name,
                                    'connection_timeout' => 30,
                                ]; 
                                $clientFundsCredit_response = ClientRequestHelper::fundTransfer($client_details, $amount_to_withdraw, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $checkPlayerRestricted->game_trans_id, "credit", false,$fund_extra_data);
                                ProviderHelper::saveLogWithExeption('IDN WITHDRAW', $this->provider_db_id, json_encode($clientFundsCredit_response), "clientFundsCredit_response");
                                if (isset($clientFundsCredit_response->fundtransferresponse->status->code) 
                                    && $clientFundsCredit_response->fundtransferresponse->status->code == "200"){
                                        $account_data = [
                                            "status" => 1
                                        ];
                                        TWHelpers::updatePlayerAccount($account_data, $game_trans_id);
                                        $msg = array(
                                            "status" => "ok",
                                            "message" => "Transaction success",
                                            "balance" => $clientFundsCredit_response->fundtransferresponse->balance
                                        );
                                        $log_data = [
                                            "client_request" => json_encode($clientFundsCredit_response->requestoclient),
                                            "mw_response" =>  json_encode($clientFundsCredit_response->fundtransferresponse),
                                            "status_code" => "200",
                                            "general_details" => json_encode($msg)
                                        ];
                                        TWHelpers::updateTWPlayerAccountsRequestLogs($log_data, $game_trans_ext_id);
                                        ProviderHelper::saveLogWithExeption('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), "DONE UPDATE SUCCESS");
                                        return response($msg, 200)->header('Content-Type', 'application/json');

                                }else{
                                    $transactionDetails = [
                                        "game_trans_ext_id" => $game_trans_ext_id,
                                        "game_trans_id" => $checkPlayerRestricted->game_trans_id
                                    ];
                                    $checkTransaction =  ClientRequestHelper::CheckerTransactionRequest($client_details, $transactionDetails);
                                    if(!$checkTransaction){
                                        /**
                                         * -----------------------------------------------
                                         *  RETRY ONE TIME TO CLIENT
                                         * -----------------------------------------------
                                         */   
                                        $clientFundsCredit_response = ClientRequestHelper::fundTransfer($client_details, $request->amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $checkPlayerRestricted->game_trans_id, "credit", false,$fund_extra_data);
                                        $checkTransaction =  ClientRequestHelper::CheckerTransactionRequest($client_details, $transactionDetails);
                                    }

                                    if($checkTransaction){
                                        $account_data = [
                                            "status" => 1
                                        ];
                                        TWHelpers::updatePlayerAccount($account_data, $game_trans_id);
                                        $msg = array(
                                            "status" => "ok",
                                            "message" => "Transaction success",
                                            "balance" => $clientFundsCredit_response->fundtransferresponse->balance
                                        );
                                        $log_data = [
                                            "client_request" => json_encode($clientFundsCredit_response->requestoclient),
                                            "mw_response" =>  json_encode($clientFundsCredit_response->fundtransferresponse),
                                            "status_code" => "403",
                                            "general_details" => json_encode($msg)
                                        ];
                                        TWHelpers::updateTWPlayerAccountsRequestLogs($log_data, $game_trans_ext_id);
                                        ProviderHelper::saveLogWithExeption('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), "DONE UPDATE SUCCESS");
                                        return response($msg, 200)->header('Content-Type', 'application/json');
                                    }
                                }
                                /**
                                 * -----------------------------------------------
                                 *  UPDATE LOGS PROGRSSING NEED TO SETTLEDMENT
                                 * -----------------------------------------------
                                 */   
                                $msg = array("status" => "error", "message" => "Fundstransfer Error!");
                                $log_data = [
                                    "client_request" => json_encode($clientFundsCredit_response->requestoclient),
                                    "mw_response" =>  json_encode($clientFundsCredit_response->fundtransferresponse),
                                    "status_code" => "500",
                                    "general_details" => json_encode($msg)
                                ];
                                TWHelpers::updateTWPlayerAccountsRequestLogs($log_data, $game_trans_ext_id);
                                ProviderHelper::saveLogWithExeption('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), "DONE UPDATE SUCCESS");
                                return response($msg, 200)->header('Content-Type', 'application/json');
                            } 
                        }
                        $message = "Transaction failed to withdraw";
                        if (isset($provider_response["message"])) {
                            $message = $provider_response["message"];
                        }
                        $message = "?????????????????????????????????????????????????????? reflection ???????????????1 minute trip ???????????????. ??????????????????????????????.";
                        $msg = array("status" => "error", "message" => $message);
                        $log_data = [
                            "client_request" => json_encode($data_deposit),
                            "mw_response" =>  json_encode($provider_response),
                            "wallet_type" => $wallet_type,
                            "client_transaction_id" => $client_transaction_id, //UNIQUE TRANSACTION
                            "tw_account_id" => $game_trans_id,
                            "status_code" => "402",
                            "general_details" => json_encode($msg)
                        ];
                        TWHelpers::createTWPlayerAccountsRequestLogs($log_data);
                        $account_data = [
                            "status" => 2
                        ];
                        TWHelpers::updatePlayerAccount($account_data, $game_trans_id);
                        ProviderHelper::saveLogWithExeption('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $msg);
                        return response($msg, 200)->header('Content-Type', 'application/json');
                    }
                }
            }
        }
        $msg = array("status" => "error", "message" => "Invalid Token or Token not found");
        ProviderHelper::saveLogWithExeption('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $msg);
        return response($msg, 200)->header('Content-Type', 'application/json');
    }

    public static function getTransactionHistory(Request $request) {
        ProviderHelper::saveLogWithExeption('IDN TW getTransactionHistory', config('providerlinks.idnpoker.PROVIDER_ID'), json_encode($request->all()), 'getTransactionHistory');
        $getTime = DB::select('SELECT now(), convert_tz(now(), "+00:00", "+07:00" ) "GMT +7",  DATE_FORMAT(SUBTIME(convert_tz(now(), "+00:00", "+07:00" ), "01:59:59"),"%m/%d/%Y")  as "date", DATE_FORMAT(SUBTIME(convert_tz(now(), "+00:00", "+07:00" ), "01:59:59"),"%H:%i")  as "getTime"');
        $time = $getTime[0]->getTime; 
        $date = $getTime[0]->date; //$getTime[0]->date
        $data = [
            "start_time" => $time,
            "date" => $date,
        ];
        $key = config('providerlinks.idnpoker');
        foreach ($key["keys"] as  $keyVal) {
            // $rate = IDNPokerHelper::getRate($keyVal); //TESTINGn); // check balance
            $transactionList = IDNPokerHelper::getTransactionHistory($data,$keyVal);
            if($transactionList != "false"){
                foreach ($transactionList["row"] as  $value) {
                
                    try {
                        if( isset($value["status"]) ) {
                            if($value["status"] != "Withdraw" && $value["status"] != "Deposit"){
                                ProviderHelper::idenpotencyTable('IDN-ID'.$value["date"].$value["game"].$value["transaction_no"]);
                                $gameDetails = self::getSubGameDetails(config('providerlinks.idnpoker.PROVIDER_ID'), $value["game"]);
                                // $playerID = substr($value["userid"],4);
                                $playerDetails = IDNPokerHelper::getPlayerID($value["userid"],config('providerlinks.idnpoker')[$keyVal] ); //TESTINGn); // check balance
                                $getClientDetails = ProviderHelper::getClientDetails("player_id", $playerDetails);
                                if($getClientDetails == null){
                                    $getClientDetails = ProviderHelper::getPlayerOperatorDetails("player_id", $playerDetails);
                                }
                                if($getClientDetails != null){
                                    $pay_amount = 0;
                                    $bet_amount = 0;
                                    $win = 5;
                                    // $pay_amount =  ($value["status"] == "Lose"  || $value["status"] == "Fold") ? 0 :  $value["curr_amount"];
                                    if($value["status"] == "Lose" || $value["status"] == "Fold" || $value["status"] == 'Buy Jackpot' || $value["status"] == 'Tournament-register' ){
                                        // $bet_amount = (isset($value["r_bet"])) ? ($value["r_bet"] / $rate)  : $value["curr_bet"]  ;
                                        $bet_amount = $value['curr_bet'];
                                        $pay_amount = 0;
                                        $win = 0;
                                    } elseif ($value["status"] == "Win" || $value["status"] == "Tournament-unregister" ) {
                                        // $bet_amount = ($value["r_bet"] / $rate);
                                        // $bet_amount = (isset($value["r_bet"])) ? ($value["r_bet"] / $rate)  : $value["curr_bet"]  ;
                                        $bet_amount = $value["curr_bet"];
                                        // $bet_amount = 0;
                                        $pay_amount = $value["curr_amount"];
                                        $win = 1;
                                    } elseif ($value["status"] == "Win Global Jackpot" ) {
                                        // $bet_amount = 0; // already calculated from IDN system
                                        $bet_amount = $value["curr_bet"];
                                        $pay_amount = $value["curr_amount"];
                                        $win = 1;
                                    } elseif ($value["status"] == "Draw" || $value["status"] == "Refund" ) {
                                        $bet_amount =  $value["curr_bet"]; // already calculated from IDN system
                                        $pay_amount =  $value["curr_amount"];
                                        $win = 1;
                                    }
                                    $income = $bet_amount - $pay_amount;
                                    $entry = $pay_amount > 0 ? 2 : 1;
                                    // $date = $month."/".$date[1]."/".$date[2];
                                    $gameTransactionData = [
                                        "token_id" => $getClientDetails->token_id,
                                        "game_id" => $gameDetails->game_id,
                                        "round_id" => $value["transaction_no"],
                                        "bet_amount" => $bet_amount,
                                        "provider_trans_id" => $value["transaction_no"],
                                        "pay_amount" => $pay_amount,
                                        "income" => $income,
                                        "entry_id" => $entry,
                                        "win" => $win,
                                    ];
                                    $game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionData, $getClientDetails);

                                    $idn_transaciton = [
                                        "game_trans_id" => $game_trans_id,
                                        "transaction_no" => $value["transaction_no"],
                                        "userid" => $value["userid"],
                                        "tableno" => $value["tableno"],
                                        "date" => $value["date"],
                                        "game" => $value["game"],
                                        "table" => $value["table"],
                                        "periode" => $value["periode"],
                                        "room" => $value["room"],
                                        "bet" => isset($value["bet"]) ? $value["bet"] : 0,
                                        "curr_bet" => isset($value["curr_bet"]) ? $value["curr_bet"] : 0,
                                        "r_bet" => isset($value["r_bet"]) ? $value["r_bet"] : 0,
                                        "status" => $value["status"],
                                        "hand" => isset($value["hand"]) ? $value["hand"] : '',
                                        "card" => isset($value["card"]) ? $value["card"] : '',
                                        "prize" =>  isset($value["prize"]) ? $value["prize"] : '',
                                        "curr" => $value["curr"],
                                        "curr_player" => $value["curr_player"],
                                        "amount" => $value["amount"],
                                        "curr_amount" => $value["curr_amount"],
                                        "total" => $value["total"],
                                        "agent_comission" => $value["agent_comission"],
                                        "agent_bill" => $value["agent_bill"],

                                    ];
                                    IDNPokerHelper::createIDNTransaction($idn_transaciton);

                                    $bet_game_transaction_ext = [
                                        "game_trans_id" => $game_trans_id,
                                        "provider_trans_id" => $value["transaction_no"],
                                        "round_id" => $value["transaction_no"],
                                        "amount" => $bet_amount,
                                        "game_transaction_type" => 1,
                                        "provider_request" => json_encode($value)
                                    ];
                                    GameTransactionMDB::createGameTransactionExt($bet_game_transaction_ext, $getClientDetails); 
                                    $win_game_transaction_ext = [
                                        "game_trans_id" => $game_trans_id,
                                        "provider_trans_id" => $value["transaction_no"],
                                        "round_id" => $value["transaction_no"],
                                        "amount" => $pay_amount,
                                        "game_transaction_type" => 2,
                                        "provider_request" => json_encode($value)
                                    ];
                                    GameTransactionMDB::createGameTransactionExt($win_game_transaction_ext, $getClientDetails); 
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        echo "ALREADY INSERTED THE DATA". $e->getMessage();
                        // return $e->getMessage();
                    }
                    // sleep(2);
                }
            }

            // return 1;
        }
    }

     /**
     * GLOBAL
     * @param $[sub_provider_id], $[game_code], 
     * 
     */
    public static function getSubGameDetails($sub_provider_id, $game_code){
        $query = DB::select('select * from games where sub_provider_id = "'.$sub_provider_id.'" and game_code = "'.$game_code.'"');
        $game_details = count($query);
        return $game_details > 0 ? $query[0] : false;
    }


     /**
     * GLOBAL
     * @param $[playerID], $[client_id ],$[client_player_id] 
     * 
     */
    public function retryWithdrawalRestriction(Request $request)
    {
        ProviderHelper::saveLogWithExeption('IDN WITHDRAW RETRY ', $this->provider_db_id, json_encode($request->all()), "HIT WITHDRAW");
        if (!$request->has("player_id") ) {
            $msg = array("status" => "error", "message" => "Missing Required Fields!");
            ProviderHelper::saveLogWithExeption('IDN WITHDRAW Missing Fields', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }
        /**
         * -----------------------------------------------
         *  GET DETAILS PLAYER AND CLIENTS
         * -----------------------------------------------
         */        
        $client_details = ProviderHelper::getClientDetails('player_id', $request->player_id);
        if ($client_details == null) {
            $msg = array("status" => "error", "message" => "Invalid Token or Token not found");
            ProviderHelper::saveLogWithExeption('IDN WITHDRAW RETRY', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }
        /**
         * -----------------------------------------------
         *  GET GAME DETAILS
         * -----------------------------------------------
         */  
        $game_details = Helper::getInfoPlayerGameRound($client_details->player_token);
        if ($game_details != "false") {
             /**
             * -----------------------------------------------
             *  CHECK IF PLAYER HAS RESTRICTION
             * -----------------------------------------------
             */     
            $checkPlayerRestricted = IDNPokerHelper::checkPlayerRestricted($client_details->player_id);
            if($checkPlayerRestricted != 'false'){
                /**
                 * -----------------------------------------------
                 *  GET provder BALANCE
                 * -----------------------------------------------
                 */   
                // $auth_token = IDNPokerHelper::getAuthPerOperator($client_details, config('providerlinks.idnpoker.type')); 
                // // $player_id = config('providerlinks.idnpoker.prefix').$client_details->player_id;
                // $player_id = $client_details->username;
                $auth_token = IDNPokerHelper::getAuthPerOperator($client_details, config('providerlinks.idnpoker.type')); 
                    // $player_id = config('providerlinks.idnpoker.prefix').$client_details->player_id;
                $player_id = $client_details->username;
                $transactionChecker = [
                    "action" => 2,
                    "player_id" =>  $player_id,
                ];
                $client_transaction_id_provider = IDNPokerHelper::getCheckTransactionIDAvailable($transactionChecker,$auth_token);
                if(!$client_transaction_id_provider){
                    $msg = array(
                        "status" => "error",
                        "message" => "Id transaction already exist!"
                    );
                    ProviderHelper::saveLogWithExeption('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
                    return response($msg, 200)->header('Content-Type', 'application/json');
                }
                $client_transaction_id =  $client_details->client_id."_".$client_transaction_id_provider;
                $data = IDNPokerHelper::playerDetails($player_id,$auth_token); // check balance
                if(isset($data["userid"]) && isset($data["username"]) &&  isset($data["balance"]) ) {
                    // if($data["balance"] == 0 || $data["balance"] == "0") {
                    //     IDNPokerHelper::deletePlayerRestricted($checkPlayerRestricted->idtw_player_restriction);
                    //     $provider_response = [
                    //         "balance" => 0,
                    //         "status" => "success"
                    //     ];

                    //     $msg = array(
                    //         "status" => "ok",
                    //         "message" => "Transaction success",
                    //         "balance" => 0.00
                    //     );
                    //     ProviderHelper::saveLogWithExeption('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $msg);
                    //     return response($msg, 200)->header('Content-Type', 'application/json');
                    // } 
                    /**
                     * -----------------------------------------------
                     *  PROVIDER WITHDRAW
                     * -----------------------------------------------
                     */   
                    
                    $amount_to_withdraw = $data["balance"];
                    $data_to_withdraw = [
                        "amount" => $amount_to_withdraw,
                        "transaction_id" => $client_transaction_id_provider,
                        "player_id" => $player_id
                    ];
                    if($data["balance"] == 0 || $data["balance"] == "0") {
                        $provider_response = [];
                    } else {
                        $provider_response = IDNPokerHelper::withdraw($data_to_withdraw, $auth_token);
                    } 
                    if($provider_response != "false"){
                        if (!isset($provider_response["error"])) {
                            /**
                             * -----------------------------------------------
                             *  CREATE ACCOUNT AND LOGS
                             * -----------------------------------------------
                             */   
                            $wallet_type = 2;// withdraw 
                            $data_accounts = [
                                "player_id" => $client_details->player_id,
                                "type" => $wallet_type,
                                "amount" => $amount_to_withdraw,
                                "client_id" => $client_details->client_id,
                                "operator_id" => $client_details->operator_id,
                                "client_transaction_id" => $client_transaction_id, //UNIQUE TRANSACTION
                                "status" => 5,
                            ];
                            $game_trans_id = TWHelpers::createTWPlayerAccounts($data_accounts);
                            $log_data = [
                                "client_request" => json_encode($data_to_withdraw),
                                "mw_response" =>  json_encode($provider_response),
                                "wallet_type" => $wallet_type,
                                "client_transaction_id" => $client_transaction_id, //UNIQUE TRANSACTION
                                "tw_account_id" => $game_trans_id,
                                "status_code" => "200",
                                "general_details" => json_encode("Provider Success Response")
                            ];
                            TWHelpers::createTWPlayerAccountsRequestLogs($log_data);
                            
                            /**
                             * -----------------------------------------------
                             *  TO CLIENT PROCESS
                             * -----------------------------------------------
                             */   
                            $log_data1 = [
                                "wallet_type" => $wallet_type,
                                "client_transaction_id" => $client_transaction_id, //UNIQUE TRANSACTION
                                "tw_account_id" => $checkPlayerRestricted->game_trans_id,
                            ];
                            $game_trans_ext_id = TWHelpers::createTWPlayerAccountsRequestLogs($log_data1);
                            $fund_extra_data = [
                                'provider_name' => $game_details->provider_name,
                                'connection_timeout' => 30,
                            ]; 

                            /**
                             * -----------------------------------------------
                             *  DELETE PLAYER RESTRICTED
                             * -----------------------------------------------
                             */   
                            IDNPokerHelper::deletePlayerRestricted($checkPlayerRestricted->idtw_player_restriction);

                            $clientFundsCredit_response = ClientRequestHelper::fundTransfer($client_details, $amount_to_withdraw, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $checkPlayerRestricted->game_trans_id, "credit", false,$fund_extra_data);
                            ProviderHelper::saveLogWithExeption('IDN WITHDRAW', $this->provider_db_id, json_encode($clientFundsCredit_response), "clientFundsCredit_response");
                            $transactionDetails = [
                                "game_trans_ext_id" => $game_trans_ext_id,
                                "game_trans_id" => $checkPlayerRestricted->game_trans_id,
                            ];
                            $checkTransaction =  ClientRequestHelper::CheckerTransactionRequest($client_details, $transactionDetails);
                            if(!$checkTransaction){
                                /**
                                 * -----------------------------------------------
                                 *  RETRY ONE TIME TO CLIENT
                                 * -----------------------------------------------
                                 */   
                                $clientFundsCredit_response = ClientRequestHelper::fundTransfer($client_details, $amount_to_withdraw, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $checkPlayerRestricted->game_trans_id, "credit", false,$fund_extra_data);
                                $checkTransaction =  ClientRequestHelper::CheckerTransactionRequest($client_details, $transactionDetails);
                            }
                            if (isset($clientFundsCredit_response->fundtransferresponse->status->code) && $checkTransaction) {
                                $account_data = [
                                    "status" => 1
                                ];
                                TWHelpers::updatePlayerAccount($account_data, $game_trans_id);
                                $msg = array(
                                    "status" => "ok",
                                    "message" => "Transaction success",
                                    "balance" => $clientFundsCredit_response->fundtransferresponse->balance
                                );
                                $log_data2 = [
                                    "client_request" => json_encode($clientFundsCredit_response->requestoclient),
                                    "mw_response" =>  json_encode($clientFundsCredit_response->fundtransferresponse),
                                    "status_code" => "200",
                                    "general_details" => json_encode($msg)
                                ];
                                TWHelpers::updateTWPlayerAccountsRequestLogs($log_data2, $game_trans_ext_id);
                                ProviderHelper::saveLogWithExeption('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), "DONE UPDATE SUCCESS");
                                return response($msg, 200)->header('Content-Type', 'application/json');
                            }
                            /**
                            * -----------------------------------------------
                            *  UPDATE LOGS PROGRSSING NEED TO SETTLEDMENT
                            * -----------------------------------------------
                            */   
                            $msg = array("status" => "error", "message" => "Fundstransfer Error!");
                            $log_data3 = [
                                "client_request" => json_encode($clientFundsCredit_response->requestoclient),
                                "mw_response" =>  json_encode($clientFundsCredit_response->fundtransferresponse),
                                "status_code" => "500",
                                "general_details" => json_encode($msg)
                            ];
                            TWHelpers::updateTWPlayerAccountsRequestLogs($log_data3, $game_trans_ext_id);
                            ProviderHelper::saveLogWithExeption('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), "DONE UPDATE SUCCESS");
                            return response($msg, 200)->header('Content-Type', 'application/json');
                        } 
                    }
                }
            }
        }
        $msg = array("status" => "error", "message" => "Invalid Token or Token not found");
        ProviderHelper::saveLogWithExeption('IDN WITHDRAW RETRY', $this->provider_db_id, json_encode($request->all()), $msg);
        return response($msg, 200)->header('Content-Type', 'application/json');
    }

    public static function callRetryPlayerRestricted() {
        $getRestricted = DB::select('SELECT * FROM tw_player_restriction where (DATE_SUB(now(), INTERVAL 1 MINUTE)) > updated_at'); // get all interval 1minute then sent
        foreach($getRestricted as $item)
        {
            try {
                $http = new Client();
                $response = $http->post(config('providerlinks.oauth_mw_api.mwurl').'/api/idnpoker/retryWithdrawalWallet', [
                    'form_params' => [
                        'player_id'=> $item->player_id,
                    ],
                    'timeout' => '2.50',
                    'headers' =>[
                        'Accept'     => 'application/json'
                    ]
                ]);
                $iframe_data = json_decode((string) $response->getBody(), true);
                ProviderHelper::saveLogWithExeption('IDNPOKER GAMELUANCH MAKEDEPOSIT RETRY', 110, json_encode($iframe_data),  json_encode($iframe_data) );
                if (isset($iframe_data['status']) && $iframe_data['status'] != 'ok' ) {
                    // return "false";
                }
            } catch (\Exception $e) {
                ProviderHelper::saveLogWithExeption('IDNPOKER GAMELUANCH MAKEDEPOSIT RETRY', 110, json_encode("error"),  $e->getMessage() );
                // return "false";
            }
        }
    }

    /**
	 * Transfer Waller and Semi Transfer Wallet
	 * [updateSession - update set session to default $session_time]
	 *  1 - still active
     *  2 - not active
	 */
    public function renewSession(Request $request){
    	// $data = $request->all();
        ProviderHelper::saveLogWithExeption('IDNPOKER RENEWSESSION', 110, json_encode($request->all()),  "renewSession");
    	if($request->has('token') && $request->has('player_id')){
           
            $checkPlayerRestricted = IDNPokerHelper::checkPlayerRestricted($request->player_id);
            if($checkPlayerRestricted != 'false'){
                $updateStatus = [
                    'status' => $checkPlayerRestricted->status + 1, // active session
                ];
                IDNPokerHelper::updatePlayerRestricted($updateStatus, $checkPlayerRestricted->idtw_player_restriction);
            }
    	}
    	$response = ["status" => "error", 'message' => 'Success Renew!'];
    	// SessionWalletProviderHelper::saveLogWithExeption('TW updateSession', 1223, json_encode($data), 1223);
    	return $response;
    }


    /**
	 * Transfer Waller and Semi Transfer Wallet
	 * [updateSession - deduct all session time with $time_deduction]
	 * 
	 */
    public static function updateCurrencyRate(){
    	IDNPokerHelper::updateCurrencyRate();
    }

    /**
	 * Transfer Waller PROCESS  CREATE DEPOSIT
	 * 
	 * 
	 */
    public static function CreateDepositWallet($playerDetails, $sub_provider_details, $details){
        ProviderHelper::saveLogWithExeption('IDNPOKER CreateDepositWallet', 110, json_encode($details),  "INIT" );
        $player_id = $playerDetails->username;
        $amount = $details["amount"];
        $idn_transaciton = $details["transaction_id"];
        $auth_token = IDNPokerHelper::getAuthPerOperator($playerDetails, config('providerlinks.idnpoker.type')); 
        $transactionChecker = [
            "action" => 1,
            "player_id" =>  $player_id,
        ];
        $client_transaction_id_provider = IDNPokerHelper::getCheckTransactionIDAvailable($transactionChecker,$auth_token,$idn_transaciton );
         if(!$client_transaction_id_provider){
            $response = ["code" => 405];
            ProviderHelper::saveLogWithExeption('IDNPOKER CreateDepositWallet', 110, json_encode($details),  "Transaction Already Exist Provider" );
            return $response;
        }
        $client_transaction_id = $playerDetails->client_id."_".$idn_transaciton;
        $type = 1; //deposit
        try {
            $data_accounts = [
                "player_id" => $playerDetails->player_id,
                "type" => $type,
                "amount" => $amount,
                "client_id" => $playerDetails->client_id,
                "operator_id" => $playerDetails->operator_id,
                "client_transaction_id" => $client_transaction_id, //UNIQUE TRANSACTION
                'status' => 5
            ];
            $account_id = TWHelpers::createTWPlayerAccountsMDB($data_accounts, $playerDetails);
        } catch (\Exception $e) {
            $response = ["code" => 405];
            ProviderHelper::saveLogWithExeption('IDNPOKER CreateDepositWallet', 110, json_encode($details),  "Transaction Already Exist TG" );
            return $response;
        }

        $data_deposit = [
            "amount" => $amount,
            "transaction_id" => $client_transaction_id_provider,
            "player_id" => $player_id
        ];

        $data = [
            "client_transaction_id" => $client_transaction_id,
            "client_request" => json_encode($details),
            "mw_response" => json_encode($data_deposit),
            "wallet_type" => $type,
            "status_code" => "200",
            "tw_account_id" => $account_id,
            "general_details" => json_encode("SENDING THE REQUEST TO PROVIDER")
        ];
        TWHelpers::createTWPlayerAccountsRequestLogsMDB($data, $playerDetails);

        $provider_response = IDNPokerHelper::deposit($data_deposit,$auth_token);
        if($provider_response != "false"){
            if (!isset($provider_response["error"])) {
                $data = [
                    "client_transaction_id" => $client_transaction_id, //UNIQUE TRANSACTION
                    "wallet_type" => $type,
                    "tw_account_id" => $account_id,
                    "client_request" => json_encode($data_deposit),
                    "mw_response" =>  json_encode($provider_response),
                    "status_code" => "200",
                    "general_details" => json_encode("RESPONSE PROVIDER")
                ];
                TWHelpers::createTWPlayerAccountsRequestLogsMDB($data, $playerDetails);
                
                $account_data = [
                    "status" => 1
                ];
                TWHelpers::updatePlayerAccountMDB($account_data, $account_id, $playerDetails);
                 /**
                 * -----------------------------------------------
                 *  RETURN RESPONSE
                 * -----------------------------------------------
                 */   
                ProviderHelper::saveLogWithExeption('IDNPOKER CreateDepositWallet', 110, json_encode($details),  "success" );
                $response = ["code" => 200, "balance" => $provider_response["balance"]];
                return $response;
            }

            $code = 413;
            if (isset($provider_response["error"])) {
                if($provider_response["error"] == 1 ){
                    $code = 412;
                } elseif ($provider_response["error"] == 2){
                    $code = 413;
                } elseif ($provider_response["error"] == 4){
                    $code = 411;
                } elseif ($provider_response["error"] == 8){
                    $code = 410;
                } elseif ($provider_response["error"] == 5){
                    $code = 303;
                } elseif ($provider_response["error"] == 2){
                    $code = 309;
                }
            }
        }
        $data = [
            "client_transaction_id" => $client_transaction_id,
            "client_request" => json_encode($details),
            "mw_response" => json_encode($provider_response),
            "status_code" => "$code",
            "wallet_type" => $type,
            "tw_account_id" => $account_id,
            "general_details" => json_encode("RESPONSE PROVIDER")
        ];
        TWHelpers::createTWPlayerAccountsRequestLogsMDB($data, $playerDetails);
        $account_data = [
            "status" => 2
        ];
        TWHelpers::updatePlayerAccountMDB($account_data, $account_id, $playerDetails);
        $response = ["code" => $code];
        return $response;
     
    }



     /**
	 * Transfer Waller PROCESS  CREATE WITHDRAW
	 * 
	 * 
	 */
    public static function CreateWithdrawWallet($playerDetails, $sub_provider_details, $details){
        ProviderHelper::saveLogWithExeption('IDNPOKER CreateWithdrawWallet', 110, json_encode($details),  "INIT" );
        $player_id = $playerDetails->username;
        $amount = $details["amount"];
        $idn_transaciton = $details["transaction_id"];
        $auth_token = IDNPokerHelper::getAuthPerOperator($playerDetails, config('providerlinks.idnpoker.type')); 
        $transactionChecker = [
            "action" => 1,
            "player_id" =>  $player_id,
        ];
        $client_transaction_id_provider = IDNPokerHelper::getCheckTransactionIDAvailable($transactionChecker,$auth_token,$idn_transaciton );
         if(!$client_transaction_id_provider){
            $response = ["code" => 405];
            ProviderHelper::saveLogWithExeption('IDNPOKER CreateWithdrawWallet', 110, json_encode($details),  "Transaction Already Exist Provider" );
            return $response;
        }
        $client_transaction_id = $playerDetails->client_id."_".$idn_transaciton;
        $type = 2; //deposit
        try {
            $data_accounts = [
                "player_id" => $playerDetails->player_id,
                "type" => $type,
                "amount" => $amount,
                "client_id" => $playerDetails->client_id,
                "operator_id" => $playerDetails->operator_id,
                "client_transaction_id" => $client_transaction_id, //UNIQUE TRANSACTION
                'status' => 5
            ];
            $account_id = TWHelpers::createTWPlayerAccountsMDB($data_accounts, $playerDetails);
        } catch (\Exception $e) {
            $response = ["code" => 405];
            ProviderHelper::saveLogWithExeption('IDNPOKER CreateWithdrawWallet', 110, json_encode($details),  "Transaction Already Exist TG" );
            return $response;
        }
       
        $data_deposit = [
            "amount" => $amount,
            "transaction_id" => $client_transaction_id_provider,
            "player_id" => $player_id
        ];

        $data = [
            "client_transaction_id" => $client_transaction_id,
            "client_request" => json_encode($details),
            "mw_response" => json_encode($data_deposit),
            "wallet_type" => $type,
            "status_code" => "200",
            "tw_account_id" => $account_id,
            "general_details" => json_encode("SENDING THE REQUEST TO PROVIDER")
        ];
        TWHelpers::createTWPlayerAccountsRequestLogsMDB($data, $playerDetails);

        $provider_response = IDNPokerHelper::withdraw($data_deposit,$auth_token);
        $code = 414;
        if($provider_response != "false"){
            if (!isset($provider_response["error"])) {
                $data = [
                    "client_transaction_id" => $client_transaction_id, //UNIQUE TRANSACTION
                    "wallet_type" => $type,
                    "tw_account_id" => $account_id,
                    "client_request" => json_encode($data_deposit),
                    "mw_response" =>  json_encode($provider_response),
                    "status_code" => "200",
                    "general_details" => json_encode("RESPONSE PROVIDER")
                ];
                TWHelpers::createTWPlayerAccountsRequestLogsMDB($data, $playerDetails);
                
                $account_data = [
                    "status" => 1
                ];
                TWHelpers::updatePlayerAccountMDB($account_data, $account_id, $playerDetails);
                 /**
                 * -----------------------------------------------
                 *  RETURN RESPONSE
                 * -----------------------------------------------
                 */   
                ProviderHelper::saveLogWithExeption('IDNPOKER CreateWithdrawWallet', 110, json_encode($details),  "success" );
                $response = ["code" => 200, "balance" => $provider_response["balance"]];
                return $response;
            }

            if (isset($provider_response["error"])) {
                if($provider_response["error"] == 1 ){
                    $code = 412;
                } elseif ($provider_response["error"] == 2){
                    $code = 414;
                } elseif ($provider_response["error"] == 3){
                    $code = 309;
                } elseif ($provider_response["error"] == 4){
                    $code = 411;
                } elseif ($provider_response["error"] == 5){
                    $code = 410;
                } elseif ($provider_response["error"] == 6){
                    $code = 410;
                } elseif ($provider_response["error"] == 7){
                    $code = 303;
                }
            }
        }
        $data = [
            "client_transaction_id" => $client_transaction_id,
            "client_request" => json_encode($details),
            "mw_response" => json_encode($provider_response),
            "status_code" => "$code",
            "wallet_type" => $type,
            "tw_account_id" => $account_id,
            "general_details" => json_encode("RESPONSE PROVIDER")
        ];
        TWHelpers::createTWPlayerAccountsRequestLogsMDB($data, $playerDetails);
        $account_data = [
            "status" => 2
        ];
        TWHelpers::updatePlayerAccountMDB($account_data, $account_id, $playerDetails);
        $response = ["code" => $code];
        return $response;
     
    }


    public static function TransactionHistory(Request $request) {
        
        $time = '00:00'; 
        $date = $request->date;  //$getTime[0]->date
        $datedb = $request->datedb;  //$getTime[0]->date
        $data = [
            "start_time" => $time,
            "date" => $date,
        ];
        $key = config('providerlinks.idnpoker');
       
        foreach ($key["localhost"] as  $keyVal) {
            // $rate = IDNPokerHelper::getRate($keyVal); //TESTINGn); // check balance
            $true = false;
            do {
                $check = DB::select("SELECT count(*) as `page` FROM api_test.production_idn_transaction_duplicate where `date` BETWEEN '".$datedb." 00:00:00' and '".$datedb." 23:59:59'");
                $page = (int) ($check[0]->page / 1000) + 1;
                $transactionList = IDNPokerHelper::TransactionHistory($data,$keyVal,$page);
                if($transactionList != "false"){
                    echo 'Total Transaction ====>>>>>>>>>>>>>>>>>>>>>>>>  '.$transactionList["numrow"].'       <<<<<<<<<<<<<<<<<<<<<<<<<';
                    foreach ($transactionList["row"] as  $value) {
                        try {
                            $date = str_replace('/', '-', $value["date"] );
                            $date =  date('Y-m-d H:i:s', strtotime($date));
                            ProviderHelper::idenpotencyTable('IDN-ID'.$value["game"].$value["transaction_no"].$date);
                            
                            $idn_transaciton = [
                                "game_trans_id" => 1,
                                "transaction_no" => $value["transaction_no"],
                                "userid" => $value["userid"],
                                "tableno" => isset($value["tableno"]) ? $value["tableno"] : 0,
                                "date" => $value["date"],
                                "game" => isset($value["game"]) ? $value["game"] : 0,
                                "table" => isset($value["table"]) ? $value["table"] : 0,
                                "periode" => isset($value["periode"]) ? $value["periode"] : 0,
                                "room" => isset($value["room"]) ? $value["room"] : 0,
                                "bet" => isset($value["bet"]) ? $value["bet"] : 0,
                                "curr_bet" => isset($value["curr_bet"]) ? $value["curr_bet"] : 0,
                                "r_bet" => isset($value["r_bet"]) ? $value["r_bet"] : 0,
                                "status" => isset($value["status"]) ? $value["status"] : 0,
                                "hand" => isset($value["hand"]) ? $value["hand"] : '',
                                "card" => isset($value["card"]) ? $value["card"] : '',
                                "prize" =>  isset($value["prize"]) ? $value["prize"] : '',
                                "curr" => isset($value["curr"]) ? $value["curr"] : 0 ,
                                "curr_player" => isset($value["curr_player"]) ? $value["curr_player"] : 0 ,
                                "amount" => isset($value["amount"]) ? $value["amount"] : 0,
                                "curr_amount" => isset($value["curr_amount"]) ? $value["curr_amount"] : 0,
                                "total" => isset($value["total"]) ? $value["total"] : 0,
                                "agent_comission" =>  isset($value["agent_comission"]) ? $value["agent_comission"] : 0,
                                "agent_bill" => isset($value["agent_bill"]) ? $value["agent_bill"] : 0,
                                "created_at" => $date
        
                            ];
                            IDNPokerHelper::createIDNTransactionLocalhost($idn_transaciton);

                        } catch (\Exception $e) {
                            echo $e->getMessage();
                        }
                    }
                    // sleep(1);
                    $true = true;
                } else {
                    $true = false;
                    echo "falseeeeee";
                }
            } while ($true);

            

           
        }
    }

    public static function TransactionPlayerSummary(Request $request) {
        
        $date = $request->date;  //$getTime[0]->date
        $key = config('providerlinks.idnpoker');
        foreach ($key["localhost"] as  $keyVal) {
            try {
                $url = config('providerlinks.idnpoker.URL');
                $client = new Client();
                $guzzle_response = $client->post($url,[
                    'body' => '
                    <request>
                        <secret_key>'.$keyVal.'</secret_key>
                        <id>8</id>
                        <date>'.$date.'</date>
                    </request>'
                ]
                );
                $details = $guzzle_response->getBody();
                $json = json_encode(simplexml_load_string($details));
                $response = json_decode($json,true);
                if(isset($response["date"])){
                    if(isset($response["data"])){
                        $date = str_replace('/', '-',  $request->datebb );
                        $date =  date('Y-m-d', strtotime($date));
                        // $date = $response["date"];
                        foreach ($response["data"]["detail"] as $value) {
                            try {
                                DB::beginTransaction();
                                ProviderHelper::idenpotencyTable('SUMMARY-'. $date.$value["userid"] );
                                $summary_data = [
                                    "userid" => $value["userid"],
                                    "total_turnover" => $value["total_turnover"],
                                    "turnover_poker" => $value["turnover_poker"],
                                    "turnover_domino" => $value["turnover_domino"],
                                    "turnover_ceme" => $value["turnover_ceme"],
                                    "turnover_blackjack" => $value["turnover_blackjack"],
                                    "turnover_capsa" => $value["turnover_capsa"],
                                    "turnover_ceme_keliling" => $value["turnover_ceme_keliling"],
                                    "turnover_superten" => $value["turnover_superten"],
                                    "turnover_omaha" => $value["turnover_omaha"],
                                    "turnover_super_bull" => $value["turnover_super_bull"],
                                    "turnover_capsa_susun" => $value["turnover_capsa_susun"],
                                    "turnover_qq_spirit" => $value["turnover_qq_spirit"],
                                    "turnover_domino_dealer" => $value["turnover_domino_dealer"],
                                    "capsa" => $value["capsa"],
                                    "texaspoker" => $value["texaspoker"],
                                    "domino" => $value["domino"],
                                    "ceme" => $value["ceme"],
                                    "blackjack" => $value["blackjack"],
                                    "poker_tournament" => $value["poker_tournament"],
                                    "ceme_keliling" => $value["ceme_keliling"],
                                    "superten" => $value["superten"],
                                    "omaha" => $value["omaha"],
                                    "super_bull" => $value["super_bull"],
                                    "capsa_susun" => $value["capsa_susun"],
                                    "qq_spirit" => $value["qq_spirit"],
                                    "domino_dealer" => $value["domino_dealer"],
                                    "agent_commission" => $value["agent_commission"],
                                    "agent_bill"  => $value["agent_bill"],
                                    "date" => $date
                                ];
                                DB::table('idn_player_summary')->insertGetId($summary_data);
                                DB::commit();
                            } catch (\Exception $e) {
                                DB::rollBack();
                                echo $e->getMessage(). $e->getLine();
                            }
                        }
                    }
                }
                ProviderHelper::saveLogWithExeption('IDNPOKER TransactionHistory', 110, json_encode($response),  "CHECK RESPONSE TransactionHistory" );
                return "false";
            } catch (\Exception $e) {
                ProviderHelper::saveLogWithExeption('IDNPOKER TransactionHistory', 110, json_encode($e->getMessage()),  "CHECK RESPONSE TransactionHistory ERROR" );
                return "false";
            }
            
        }
    }
}
