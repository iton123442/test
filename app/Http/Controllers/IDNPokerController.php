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
use Webpatser\Uuid\Uuid;

class IDNPokerController extends Controller
{

    public function __construct(){
        // $this->middleware('oauth', ['except' => []]);
        $this->provider_db_id = config('providerlinks.idnpoker.PROVIDER_ID');
    }

    public static function getPlayerBalance(Request $request) {
        Helper::saveLog('IDN TW GetPlayerBalance', config('providerlinks.idnpoker.PROVIDER_ID'), json_encode($request->all()), 'HIT');
        if (!$request->has("token")) {
            $msg = array("status" => "error", "message" => "Token Invalid");
            Helper::saveLog('IDN TW GetPlayerBalance', config('providerlinks.idnpoker.PROVIDER_ID'), json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }

        $client_details = ProviderHelper::getClientDetails('token', $request->token);
        
        if ($client_details == null || $client_details == 'false') {
            $msg = array("status" => "error", "message" => "Token Invalid");
            Helper::saveLog('IDN TW GetPlayerBalance', config('providerlinks.idnpoker.PROVIDER_ID'), json_encode($request->all()), $msg);
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

                Helper::saveLog('IDN TW GetPlayerBalance', config('providerlinks.idnpoker.PROVIDER_ID'), json_encode($request->all()), $msg);
                return response($msg, 200)->header('Content-Type', 'application/json');
            } 

            $msg = array(
                "status" => "error",
                "message" => "Player Not found",
                "balance" => "0.00"
            );
            Helper::saveLog('IDN TW GetPlayerBalance', config('providerlinks.idnpoker.PROVIDER_ID'), json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $msg = array(
                "status" => "error",
                "message" => $e->getMessage(),
                "balance" => 0.00
            );
            Helper::saveLog('IDN TW GetPlayerBalance', config('providerlinks.idnpoker.PROVIDER_ID'), json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }
    }


    public function getPlayerWalletBalance(Request $request)
    {
        Helper::saveLog('IDN getPlayerWalletBalance', $this->provider_db_id, json_encode($request->all()), "HIT");
        $client_details = ProviderHelper::getClientDetails('token', $request->token);
        if ($client_details == 'false') {
            $msg = array("status" => "error", "message" => "Token Invalid");
            Helper::saveLog('IDN getPlayerWalletBalance', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }

        $game_details = Helper::getInfoPlayerGameRound($request->token);
        if ($game_details == false) {
            $msg = array("status" => "error", "message" => "Game Not Found");
            Helper::saveLog('IDN getPlayerWalletBalance', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }

        // $player_id = "TGTW_". $client_details->player_id;
        $auth_token = IDNPokerHelper::getAuthPerOperator($client_details, config('providerlinks.idnpoker.type')); 
        $player_id = config('providerlinks.idnpoker.prefix').$client_details->player_id;
        $data = IDNPokerHelper::playerDetails($player_id,$auth_token);
        if ($data != "false") {
            $msg = array(
                "status" => "success",
                "balance" => $data["balance"],
            );
            Helper::saveLog('IDN getPlayerWalletBalance', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');

        }
        $msg = array(
            "status" => "error",
            "balance" => "0.00",
        );
        Helper::saveLog('IDN getPlayerWalletBalance', $this->provider_db_id, json_encode($request->all()), $msg);
        return response($msg, 200)->header('Content-Type', 'application/json');
        
    }

    public function makeDeposit(Request $request){
        Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), "HIT ENDPOINT");
        // $msg = array(
        //     "status" => "ok",
        //     "message" => "Transaction success",
        //     "balance" => "0.00"
        // );
        // return response($msg, 200)->header('Content-Type', 'application/json');
        if (!$request->has("token") || !$request->has("player_id") || !$request->has("amount")) {
            $msg = array("status" => "error", "message" => "Missing Required Fields!");
            Helper::saveLog('IDN DEPOSIT Missing Fields', $this->provider_db_id, json_encode($request->all()), $msg);
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
            Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
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
            Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
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
            Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }
         /**
         * -----------------------------------------------
         *  GET GAME DETAILS
         * -----------------------------------------------
         */    
        $game_details = Helper::getInfoPlayerGameRound($request->token);
        if($game_details != "false") {
            /**
             * -----------------------------------------------
             *  CLIENT ENDPOINT PLAYER DETAILS CALL
             * -----------------------------------------------
             */
            // --------------------------------------
            $client_response = Providerhelper::playerDetailsCall($client_details->player_token);
            Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $client_response);
            if($client_response != "false"){
                if(isset($client_response->playerdetailsresponse->status->code) && $client_response->playerdetailsresponse->status->code == 200){
                    $client_transaction_id_provider = Uuid::generate()->string;
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
                            "status" => 1,
                        ];
                        $game_trans_id = TWHelpers::createTWPlayerAccounts($data_accounts);
                    } catch (\Exception $e) {
                        $msg = array(
                            "status" => "error",
                            "message" => "Transaction Id Already Exist",
                        );
                        return $msg;
                    }
                    $data = [
                        "client_transaction_id" => $client_transaction_id, //UNIQUE TRANSACTION
                        "wallet_type" => 1,
                        "tw_account_id" => $game_trans_id
                    ];
                    $game_trans_ext_id = TWHelpers::createTWPlayerAccountsRequestLogs($data);
                    
                    Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), "FUNDSTRANSFER HIT");
                    $fund_extra_data = [
	                    'provider_name' => $game_details->provider_name
	                ]; 
                    $clientFunds_response = ClientRequestHelper::fundTransfer($client_details, $request->amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_trans_id, "debit",false,$fund_extra_data);
                    $transactionDetails = [
                        "game_trans_ext_id" => $game_trans_ext_id,
                        "game_trans_id" => $game_trans_id
                    ];
                    $checkTransaction =  ClientRequestHelper::CheckerTransactionRequest($client_details, $transactionDetails);
                    if(!$checkTransaction){
                        $msg = array("status" => "error", "message" => "Deposit Failed");
                        $account_data = [
                            "status" => 2
                        ];
                        TWHelpers::updatePlayerAccount($account_data, $game_trans_id);
                        $log_data = [
                            "client_request" => json_encode($clientFunds_response->requestoclient),
                            "mw_response" =>  json_encode($clientFunds_response->fundtransferresponse),
                            "status_code" => "404",
                            "general_details" => json_encode($msg)
                        ];
                        TWHelpers::updateTWPlayerAccountsRequestLogs($log_data, $game_trans_ext_id);
                        Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), "FAILED NOT SET");
                        return response($msg, 200)->header('Content-Type', 'application/json');
                    }
                    Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($clientFunds_response), "FUNDSTRANSFER RESPONSE");
                    if (isset($clientFunds_response->fundtransferresponse->status->code)) {
                        $auth_token = IDNPokerHelper::getAuthPerOperator($client_details, config('providerlinks.idnpoker.type')); 
                        $player_id = config('providerlinks.idnpoker.prefix').$client_details->player_id;
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
                            "amount" => $request->amount,
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
                                 /**
                                 * -----------------------------------------------
                                 *  RETURN RESPONSE
                                 * -----------------------------------------------
                                 */   
                                Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
                                return response($msg, 200)->header('Content-Type', 'application/json');
                            }
                            $message = "Deposit Failed";
                            if (isset($provider_response["message"])) {
                                $message = $provider_response["message"];
                            }
                        }
                        $data = [
                            "client_transaction_id" => $client_transaction_id, //UNIQUE TRANSACTION
                            "wallet_type" => 1,
                            "tw_account_id" => $game_trans_id,
                            "client_request" => json_encode($data_deposit),
                            "mw_response" =>  json_encode($provider_response),
                            "status_code" => "404",
                            "general_details" => json_encode($message),
                        ];
                        $game_trans_ext_id = TWHelpers::createTWPlayerAccountsRequestLogs($data);
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
                        Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), "ROLLBACK HIT");
                        $fund_extra_data = [
                            'provider_name' => $game_details->provider_name
                        ]; 
                        $clientFundsRollback_response = ClientRequestHelper::fundTransfer($client_details, $request->amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_trans_id, "credit", true,$fund_extra_data);
                        $transactionDetails = [
                            "game_trans_ext_id" => $game_trans_ext_id,
                            "game_trans_id" => $game_trans_id
                        ];
                        $checkTransaction =  ClientRequestHelper::CheckerTransactionRequest($client_details, $transactionDetails);
                        if(!$checkTransaction){
                            $clientFundsRollback_response = ClientRequestHelper::fundTransfer($client_details, $request->amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_trans_id, "credit", true,$fund_extra_data);
                            $transactionDetails = [
                                "game_trans_ext_id" => $game_trans_ext_id,
                                "game_trans_id" => $game_trans_id
                            ];
                            $checkTransaction =  ClientRequestHelper::CheckerTransactionRequest($client_details, $transactionDetails);
                            if(!$checkTransaction){
                                $msg = array("status" => "error", "message" => "Deposit Failed");
                                $log_data = [
                                    "client_request" => json_encode($clientFundsRollback_response->requestoclient),
                                    "mw_response" =>  json_encode($clientFundsRollback_response->fundtransferresponse),
                                    "status_code" => "404",
                                    "general_details" => json_encode($msg)
                                ];
                                TWHelpers::updateTWPlayerAccountsRequestLogs($log_data, $game_trans_ext_id);
                                $account_data = [
                                    "status" => 5 // nee to sent to client
                                ];
                                TWHelpers::updatePlayerAccount($account_data, $game_trans_id);
                                /**
                                 * -----------------------------------------------
                                 *  RETURN RESPONSE
                                 * -----------------------------------------------
                                 */   
                                Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
                                return response($msg, 200)->header('Content-Type', 'application/json');
                            }
                        }
                        if (isset($clientFundsRollback_response->fundtransferresponse->status->code)) {
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
                            Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
                            return response($msg, 200)->header('Content-Type', 'application/json');
                        }
                    } 
                } 
            }
            $msg = array("status" => "error", "message" => "Invalid Token or Token not found");
            Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        } else {
            $msg = array("status" => "error", "message" => "Invalid Token or Game not found");
            Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }
    }

    public function makeWithdraw(Request $request)
    {
        Helper::saveLog('IDN WITHDRAW ', $this->provider_db_id, json_encode($request->all()), "HIT WITHDRAW");
        if (!$request->has("token") || !$request->has("player_id") ) {
            $msg = array("status" => "error", "message" => "Missing Required Fields!");
            Helper::saveLog('IDN WITHDRAW Missing Fields', $this->provider_db_id, json_encode($request->all()), $msg);
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
            Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }
         /**
         * -----------------------------------------------
         *  CHECK IF PLAYER HAS RESTRICTION
         * -----------------------------------------------
         */     
        $checkPlayerRestricted = IDNPokerHelper::checkPlayerRestricted($client_details->player_id);
        if($checkPlayerRestricted != "false"){
            $msg = array("status" => "error", "message" => "Please retry again!");
            Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }
        /**
         * -----------------------------------------------
         *  GET GAME DETAILS
         * -----------------------------------------------
         */  
        $game_details = Helper::getInfoPlayerGameRound($request->token);
        if ($game_details != "false") {
            $client_response = Providerhelper::playerDetailsCall($client_details->player_token);
            Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $client_response);
            if($client_response != "false"){
                if(isset($client_response->playerdetailsresponse->status->code) && $client_response->playerdetailsresponse->status->code == 200){
                    /**
                     * -----------------------------------------------
                     *  GENERATE TRANSACTION ID UUID
                     * -----------------------------------------------
                     */   
                    $client_transaction_id_provider = Uuid::generate()->string; // USE FOR THE PROVIDER MAX 36
                    $client_transaction_id =  $client_details->client_id."_".$client_transaction_id_provider; // USE ACCOUNT AND LOGS TABLE NAME client_transaction_id
                    try{
                        ProviderHelper::idenpotencyTable($client_transaction_id);
                    }catch(\Exception $e){
                        $msg = array("status" => "error", "message" => "Please retry again!");
                        Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $msg);
                        return response($msg, 200)->header('Content-Type', 'application/json');
                    }
                    /**
                     * -----------------------------------------------
                     *  GET provder BALANCE
                     * -----------------------------------------------
                     */   
                    $auth_token = IDNPokerHelper::getAuthPerOperator($client_details, config('providerlinks.idnpoker.type')); 
                    $player_id = config('providerlinks.idnpoker.prefix').$client_details->player_id;
                    $data = IDNPokerHelper::playerDetails($player_id,$auth_token); // check balance
                    if(isset($data["userid"]) && isset($data["username"]) &&  isset($data["balance"]) ) {
                        if($data["balance"] == 0 || $data["balance"] == "0") {
                            $provider_response = [
                                "balance" => 0,
                                "status" => "success"
                            ];

                            $msg = array(
                                "status" => "ok",
                                "message" => "Transaction success",
                                "balance" => $client_response->playerdetailsresponse->balance
                            );
                            Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $msg);
                            return response($msg, 200)->header('Content-Type', 'application/json');
                        } 
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
                        $provider_response = IDNPokerHelper::withdraw($data_deposit, $auth_token);
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
                                    "tw_account_id" => $game_trans_id,
                                ];
                                $game_trans_ext_id = TWHelpers::createTWPlayerAccountsRequestLogs($log_data);
                                $fund_extra_data = [
                                    'provider_name' => $game_details->provider_name
                                ]; 
                                $clientFundsCredit_response = ClientRequestHelper::fundTransfer($client_details, $amount_to_withdraw, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_trans_id, "credit", false,$fund_extra_data);
                                Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($clientFundsCredit_response), "clientFundsCredit_response");
                                $transactionDetails = [
                                    "game_trans_ext_id" => $game_trans_ext_id,
                                    "game_trans_id" => $game_trans_id
                                ];
                                $checkTransaction =  ClientRequestHelper::CheckerTransactionRequest($client_details, $transactionDetails);
                                if(!$checkTransaction){
                                    /**
                                     * -----------------------------------------------
                                     *  RETRY ONE TIME TO CLIENT
                                     * -----------------------------------------------
                                     */   
                                    $clientFundsCredit_response = ClientRequestHelper::fundTransfer($client_details, $request->amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_trans_id, "credit", false,$fund_extra_data);
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
                                    $log_data = [
                                        "client_request" => json_encode($clientFundsCredit_response->requestoclient),
                                        "mw_response" =>  json_encode($clientFundsCredit_response->fundtransferresponse),
                                        "status_code" => "200",
                                        "general_details" => json_encode($msg)
                                    ];
                                    TWHelpers::updateTWPlayerAccountsRequestLogs($log_data, $game_trans_ext_id);
                                    Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), "DONE UPDATE SUCCESS");
                                    return response($msg, 200)->header('Content-Type', 'application/json');
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
                                Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), "DONE UPDATE SUCCESS");
                                return response($msg, 200)->header('Content-Type', 'application/json');
                            } 
                        }
                        /***************************************************************
                        *
                        * FALED TO WITHDRAW CREATE PLAYER RESTRICTION
                        *
                        ****************************************************************/
                        $createRestricted = [
                            "player_id" => $client_details->player_id,
                            "status" => 5,
                            "client_transaction_id" => $client_transaction_id_provider,
                        ];
                        IDNPokerHelper::createPlayerRestricted($createRestricted);
                        $message = "Transaction failed to withdraw";
                        if (isset($provider_response["message"])) {
                            $message = $provider_response["message"];
                        }
                        $msg = array("status" => "error", "message" => $message);
                        Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $msg);
                        return response($msg, 200)->header('Content-Type', 'application/json');
                    }
                }
            }
        }
        $msg = array("status" => "error", "message" => "Invalid Token or Token not found");
        Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $msg);
        return response($msg, 200)->header('Content-Type', 'application/json');
    }

    public static function getTransactionHistory(Request $request) {
        Helper::saveLog('IDN TW getTransactionHistory', config('providerlinks.idnpoker.PROVIDER_ID'), json_encode($request->all()), 'getTransactionHistory');
        $getTime = DB::select('SELECT DATE_FORMAT(SUBTIME(now(), "- 06:55:00"),"%m/%d/%Y")    as date ,DATE_FORMAT(SUBTIME(now(), "- 06:55:00"),"%H:%i") time');
        $time = "00:00";  //$getTime[0]->getTime 
        $date = $getTime[0]->date; //$getTime[0]->date
        $data = [
            "start_time" => $time,
            "date" => $date,
        ];
        $key = config('providerlinks.idnpoker');
        foreach ($key["keys"] as  $keyVal) {
            $rate = IDNPokerHelper::getRate($keyVal); //TESTINGn); // check balance
            $transactionList = IDNPokerHelper::getTransactionHistory($data,$keyVal);
            if($transactionList != "false"){
                foreach ($transactionList["row"] as  $value) {
                    try {
                        ProviderHelper::idenpotencyTable('IDN-ID'.$value["game"].$value["transaction_no"]);
                        if($value["status"] != "Withdraw" && $value["status"] != "Deposit") {
                            $gameDetails = self::getSubGameDetails(config('providerlinks.idnpoker.PROVIDER_ID'), $value["game"]);
                            $playerID = substr($value["userid"],4);
                            $getClientDetails = ProviderHelper::getClientDetails("player_id", $playerID);
                            if($getClientDetails != null){
                                $pay_amount = 0;
                                $bet_amount = 0;
                                $win = 5;
                                // $pay_amount =  ($value["status"] == "Lose"  || $value["status"] == "Fold") ? 0 :  $value["curr_amount"];
                                if($value["status"] == "Lose" || $value["status"] == "Fold"){
                                    $bet_amount = (isset($value["r_bet"])) ? ($value["r_bet"] / $rate)  : $value["curr_bet"]  ;
                                    $pay_amount = 0;
                                    $win = 0;
                                } elseif ($value["status"] == "Win" || $value["status"] == "Draw" || $value["status"] == "Refund" ) {
                                    // $bet_amount = ($value["r_bet"] / $rate);
                                    $bet_amount = (isset($value["r_bet"])) ? ($value["r_bet"] / $rate)  : $value["curr_bet"]  ;
                                    $pay_amount = $value["curr_amount"];
                                    $win =  ($value["status"] == "Refund") ? 4 : 1;
                                } elseif ($value["status"] == "Buy Jackpot" ) {
                                    $bet_amount = $value["curr_amount"]; // already calculated from IDN system
                                    $pay_amount = 0;
                                    $win = 0;
                                } elseif ($value["status"] == "Win Global Jackpot" ) {
                                    $bet_amount = 0; // already calculated from IDN system
                                    $pay_amount = $value["curr_amount"];
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
                    } catch (\Exception $e) {
                        echo "ALREADY INSERTED THE DATA". $e->getMessage();
                        // return $e->getMessage();
                    }
                    sleep(2);
                }
            }

            return 1;
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
        Helper::saveLog('IDN WITHDRAW RETRY ', $this->provider_db_id, json_encode($request->all()), "HIT WITHDRAW");
        if (!$request->has("player_id") ) {
            $msg = array("status" => "error", "message" => "Missing Required Fields!");
            Helper::saveLog('IDN WITHDRAW Missing Fields', $this->provider_db_id, json_encode($request->all()), $msg);
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
            Helper::saveLog('IDN WITHDRAW RETRY', $this->provider_db_id, json_encode($request->all()), $msg);
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
                $auth_token = IDNPokerHelper::getAuthPerOperator($client_details, config('providerlinks.idnpoker.type')); 
                $player_id = config('providerlinks.idnpoker.prefix').$client_details->player_id;
                $data = IDNPokerHelper::playerDetails($player_id,$auth_token); // check balance
                if(isset($data["userid"]) && isset($data["username"]) &&  isset($data["balance"]) ) {
                    if($data["balance"] == 0 || $data["balance"] == "0") {
                        IDNPokerHelper::deletePlayerRestricted($checkPlayerRestricted->idtw_player_restriction);
                        $provider_response = [
                            "balance" => 0,
                            "status" => "success"
                        ];

                        $msg = array(
                            "status" => "ok",
                            "message" => "Transaction success",
                            "balance" => 0.00
                        );
                        Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $msg);
                        return response($msg, 200)->header('Content-Type', 'application/json');
                    } 
                    /**
                     * -----------------------------------------------
                     *  PROVIDER WITHDRAW
                     * -----------------------------------------------
                     */   
                    
                    $amount_to_withdraw = $data["balance"];
                    $data_deposit = [
                        "amount" => $amount_to_withdraw,
                        "transaction_id" => $checkPlayerRestricted->client_transaction_id,
                        "player_id" => $player_id
                    ];
                    $provider_response = IDNPokerHelper::withdraw($data_deposit, $auth_token);
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
                                "client_transaction_id" => $checkPlayerRestricted->client_transaction_id, //UNIQUE TRANSACTION
                                "status" => 5,
                            ];
                            $game_trans_id = TWHelpers::createTWPlayerAccounts($data_accounts);
                            $log_data = [
                                "client_request" => json_encode($data_deposit),
                                "mw_response" =>  json_encode($provider_response),
                                "wallet_type" => $wallet_type,
                                "client_transaction_id" => $checkPlayerRestricted->client_transaction_id, //UNIQUE TRANSACTION
                                "tw_account_id" => $game_trans_id,
                                "status_code" => "200",
                                "general_details" => json_encode("Provider Success Response")
                            ];
                            TWHelpers::createTWPlayerAccountsRequestLogs($log_data);
                            /**
                             * -----------------------------------------------
                             *  DELETE PLAYER RESTRICTED
                             * -----------------------------------------------
                             */   
                            IDNPokerHelper::deletePlayerRestricted($checkPlayerRestricted->idtw_player_restriction);
                            /**
                             * -----------------------------------------------
                             *  TO CLIENT PROCESS
                             * -----------------------------------------------
                             */   
                            $log_data = [
                                "wallet_type" => $wallet_type,
                                "client_transaction_id" => $checkPlayerRestricted->client_transaction_id, //UNIQUE TRANSACTION
                                "tw_account_id" => $game_trans_id,
                            ];
                            $game_trans_ext_id = TWHelpers::createTWPlayerAccountsRequestLogs($log_data);
                            $fund_extra_data = [
                                'provider_name' => $game_details->provider_name
                            ]; 
                            $clientFundsCredit_response = ClientRequestHelper::fundTransfer($client_details, $amount_to_withdraw, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_trans_id, "credit", false,$fund_extra_data);
                            Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($clientFundsCredit_response), "clientFundsCredit_response");
                            $transactionDetails = [
                                "game_trans_ext_id" => $game_trans_ext_id,
                                "game_trans_id" => $game_trans_id
                            ];
                            $checkTransaction =  ClientRequestHelper::CheckerTransactionRequest($client_details, $transactionDetails);
                            if(!$checkTransaction){
                                /**
                                 * -----------------------------------------------
                                 *  RETRY ONE TIME TO CLIENT
                                 * -----------------------------------------------
                                 */   
                                $clientFundsCredit_response = ClientRequestHelper::fundTransfer($client_details, $amount_to_withdraw, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_trans_id, "credit", false,$fund_extra_data);
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
                                $log_data = [
                                    "client_request" => json_encode($clientFundsCredit_response->requestoclient),
                                    "mw_response" =>  json_encode($clientFundsCredit_response->fundtransferresponse),
                                    "status_code" => "200",
                                    "general_details" => json_encode($msg)
                                ];
                                TWHelpers::updateTWPlayerAccountsRequestLogs($log_data, $game_trans_ext_id);
                                Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), "DONE UPDATE SUCCESS");
                                return response($msg, 200)->header('Content-Type', 'application/json');
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
                            Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), "DONE UPDATE SUCCESS");
                            return response($msg, 200)->header('Content-Type', 'application/json');
                        } 
                    }
                }
            }
        }
        $msg = array("status" => "error", "message" => "Invalid Token or Token not found");
        Helper::saveLog('IDN WITHDRAW RETRY', $this->provider_db_id, json_encode($request->all()), $msg);
        return response($msg, 200)->header('Content-Type', 'application/json');
    }

    public static function callRetryPlayerRestricted() {
        $getRestricted = DB::select('select * from tw_player_restriction');
        foreach($getRestricted as $item)
        {
            try {
                $http = new Client();
                $response = $http->post(config('providerlinks.oauth_mw_api.mwurl').'/api/idnpoker/retryWithdrawalWallet', [
                    'form_params' => [
                        'player_id'=> $item->player_id,
                    ],
                    'timeout' => '0.50',
                    'headers' =>[
                        'Accept'     => 'application/json'
                    ]
                ]);
                $iframe_data = json_decode((string) $response->getBody(), true);
                Helper::saveLog('IDNPOKER GAMELUANCH MAKEDEPOSIT RETRY', 110, json_encode($iframe_data),  json_encode($iframe_data) );
                if (isset($iframe_data['status']) && $iframe_data['status'] != 'ok' ) {
                    return "false";
                }
            } catch (\Exception $e) {
                Helper::saveLog('IDNPOKER GAMELUANCH MAKEDEPOSIT RETRY', 110, json_encode("error"),  $e->getMessage() );
                return "false";
            }
        }
    }
}
