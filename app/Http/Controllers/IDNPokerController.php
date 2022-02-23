<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ProviderHelper;
use App\Helpers\KAHelper;
use App\Helpers\IDNPokerHelper;
use App\Helpers\Helper;
use App\Helpers\TransferWalletHelper;
use App\Helpers\ClientRequestHelper;
use App\Http\Controllers\TransferWalletAggregator\TWHelpers;
use Carbon\Carbon;
use DB;
use App\Models\GameTransactionMDB;

class IDNPokerController extends Controller
{

    public function __construct(){
        // $this->middleware('oauth', ['except' => []]);
        $this->provider_db_id = 110;
    }

    public static function getPlayerBalance(Request $request) {
        Helper::saveLog('IDN TW GetPlayerBalance', 110, json_encode($request->all()), 'HIT');
        if (!$request->has("token")) {
            $msg = array("status" => "error", "message" => "Token Invalid");
            Helper::saveLog('IDN TW GetPlayerBalance', 110, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }

        $client_details = ProviderHelper::getClientDetails('token', $request->token);
        
        if ($client_details == null || $client_details == 'false') {
            $msg = array("status" => "error", "message" => "Token Invalid");
            Helper::saveLog('IDN TW GetPlayerBalance', 110, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }

        try {
            $client_response = KAHelper::playerDetailsCall($client_details);
            if ($client_response != "false") {
                $balance = round($client_response->playerdetailsresponse->balance, 2);
                $msg = array(
                    "status" => "ok",
                    "message" => "Balance Request Success",
                    "balance" => $balance
                );

                Helper::saveLog('IDN TW GetPlayerBalance', 110, json_encode($request->all()), $msg);
                return response($msg, 200)->header('Content-Type', 'application/json');
            } 

            $msg = array(
                "status" => "error",
                "message" => "Player Not found",
                "balance" => "0.00"
            );
            Helper::saveLog('IDN TW GetPlayerBalance', 110, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $msg = array(
                "status" => "error",
                "message" => $e->getMessage(),
                "balance" => 0.00
            );
            Helper::saveLog('IDN TW GetPlayerBalance', 110, json_encode($request->all()), $msg);
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

        $game_details = TransferWalletHelper::getInfoPlayerGameRound($request->token);
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
        if ($client_details == 'false') {
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
        $game_details = TransferWalletHelper::getInfoPlayerGameRound($request->token);
        if($game_details != "false") {
            /**
             * -----------------------------------------------
             *  CLIENT ENDPOINT PLAYER DETAILS CALL
             * -----------------------------------------------
             */
            // --------------------------------------
            $client_response = KAHelper::playerDetailsCall($client_details);
            Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $client_response);
            if($client_response != "false"){
                if(isset($client_response->playerdetailsresponse->status->code) && $client_response->playerdetailsresponse->status->code == 200){
            // --------------------------------------
                    // /**
                    //  * -----------------------------------------------
                    //  *  CHECK MULTIPLE SESSION
                    //  * -----------------------------------------------
                    //  */   
                    // $session_count = SessionWalletHelper::isMultipleSession($client_details->player_id, $request->token);
                    
                    // if ($session_count) {
                    //     $msg = array(
                    //         "status" => "error",
                    //         "message" => "Multiple Session Detected!"
                    //     );
                    //     Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
                    //     return response($msg, 200)->header('Content-Type', 'application/json');
                    // }
                    /**
                     * -----------------------------------------------
                     *  CHECK AMOUNT FOR PLAYER AND AMOUNT TO DEPOSIT
                     * -----------------------------------------------
                     */   
                    $balance = $client_response->playerdetailsresponse->balance; 
                    // $balance = 100;
                    if ($balance < $request->amount) {
                        $msg = array(
                                "status" => "error",
                                "message" => "Not Enough Balance",
                            );
                        Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
                        return response($msg, 200)->header('Content-Type', 'application/json');
                    }
                    $client_transaction_id_provider = Carbon::now()->timestamp;
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
                        $mw_response = ["data" => null,"status" => ["code" => 406 ,"message" => TWHelpers::getPTW_Message(406)]];
                        $data["mw_response"] = json_encode($mw_response);
                        $data["status_code"] = "406";
                        TWHelpers::createTWPlayerAccountsRequestLogs($data);
                        return $mw_response;
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
                    Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($clientFunds_response), "FUNDSTRANSFER RESPONSE");
                    if (isset($clientFunds_response->fundtransferresponse->status->code)) {
                        $auth_token = IDNPokerHelper::getAuthPerOperator($client_details, config('providerlinks.idnpoker.type')); 
                        $player_id = config('providerlinks.idnpoker.prefix').$client_details->player_id;
                        switch ($clientFunds_response->fundtransferresponse->status->code) {
                            case "200":
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
                                
                                // $update_gametransactionext = array(
                                //     "transaction_detail" =>json_encode($data_deposit),
                                //     "general_details" => json_encode($provider_response),
                                // );
                                // GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_id,$client_details);
                               
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
                                    "status_code" => "404"
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
                                if (isset($clientFundsRollback_response->fundtransferresponse->status->code)) {
                                    switch ($clientFundsRollback_response->fundtransferresponse->status->code) {
                                        case "200":
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
                                            break;
                                    }
                                }
                                $msg = array("status" => "error", "message" => "Deposit Failed");
                                $log_data = [
                                    "client_request" => json_encode($clientFundsRollback_response->requestoclient),
                                    "mw_response" =>  json_encode($clientFundsRollback_response->fundtransferresponse),
                                    "status_code" => "404",
                                    "general_details" => json_encode($msg)
                                ];
                                TWHelpers::updateTWPlayerAccountsRequestLogs($log_data, $game_trans_ext_id);
                                $account_data = [
                                    "status" => 3
                                ];
                                TWHelpers::updatePlayerAccount($account_data, $game_trans_id);
                                 /**
                                 * -----------------------------------------------
                                 *  RETURN RESPONSE
                                 * -----------------------------------------------
                                 */   
                                Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
                                return response($msg, 200)->header('Content-Type', 'application/json');
                                break;
                            default :
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
                    } else {
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
        // try {
            
        // } catch (\Exception $e) {
        //     $msg = array("status" => "error", "message" => $e->getMessage() . $e->getLine());
        //     Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
        //     return response($msg, 200)->header('Content-Type', 'application/json');
        // }
        
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
        if ($client_details == 'false') {
            $msg = array("status" => "error", "message" => "Invalid Token or Token not found");
            Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }
        /**
         * -----------------------------------------------
         *  GET GAME DETAILS
         * -----------------------------------------------
         */  
          
        $game_details = TransferWalletHelper::getInfoPlayerGameRound($request->token);
        if ($game_details != "false") {
            // ======================
            $client_response = KAHelper::playerDetailsCall($client_details);
            Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $client_response);
            if($client_response != "false"){
                if(isset($client_response->playerdetailsresponse->status->code) && $client_response->playerdetailsresponse->status->code == 200){
            // ======================
                    /**
                     * -----------------------------------------------
                     *  CHECK AMOUNT FOR PLAYER AND AMOUNT TO DEPOSIT
                     * -----------------------------------------------
                     */   
                    $client_transaction_id_provider = Carbon::now()->timestamp;
                    $client_transaction_id =  $client_details->client_id."_".$client_transaction_id_provider;
                    /**
                     * -----------------------------------------------
                     *  GET provder BALANCE
                     * -----------------------------------------------
                     */   
                    $auth_token = IDNPokerHelper::getAuthPerOperator($client_details, config('providerlinks.idnpoker.type')); 
                    $player_id = config('providerlinks.idnpoker.prefix').$client_details->player_id;
                    $data = IDNPokerHelper::playerDetails($player_id,$auth_token); // check balance
                    if ($data != "false") {
                        $amount_to_withdraw = $data["balance"];
                        /**
                         * -----------------------------------------------
                         *  PROVIDER WITHDRAW
                         * -----------------------------------------------
                         */   
                        $data_deposit = [
                            "amount" => $amount_to_withdraw,
                            "transaction_id" => $client_transaction_id_provider,
                            "player_id" => $player_id
                        ];
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
                            return $msg;
                        } else {
                             /**
                             * -----------------------------------------------
                             *  CREATE TRANSACTION WALLET
                             * -----------------------------------------------
                             */   
                            
                            try {
                                $data_accounts = [
                                    "player_id" => $client_details->player_id,
                                    "type" => 2,
                                    "amount" => $amount_to_withdraw,
                                    "client_id" => $client_details->client_id,
                                    "operator_id" => $client_details->operator_id,
                                    "client_transaction_id" => $client_transaction_id, //UNIQUE TRANSACTION
                                    "status" => 1,
                                ];
                                $game_trans_id = TWHelpers::createTWPlayerAccounts($data_accounts);
                            } catch (\Exception $e) {
                                // $mw_response = ["data" => null,"status" => ["code" => 406 ,"message" => TWHelpers::getPTW_Message(406)]];
                                $msg = array("status" => "error", "message" => "Failed Request");
                                $data["mw_response"] = json_encode($msg);
                                $data["status_code"] = "406";
                                TWHelpers::createTWPlayerAccountsRequestLogs($data);
                                return $msg;
                            }
                            $provider_response = IDNPokerHelper::withdraw($data_deposit, $auth_token);
                        }
                        $log_data = [
                            "client_request" => json_encode($data_deposit),
                            "mw_response" =>  json_encode($provider_response),
                            "wallet_type" => 2,
                            "client_transaction_id" => $client_transaction_id, //UNIQUE TRANSACTION
                            "tw_account_id" => $game_trans_id
                        ];
                        $game_trans_ext_id = TWHelpers::createTWPlayerAccountsRequestLogs($log_data);
                       
                        if($provider_response != "false"){
                            if (!isset($provider_response["error"])) {
                                $log_data = [
                                    "status_code" => "200"
                                ];
                                TWHelpers::updateTWPlayerAccountsRequestLogs($log_data, $game_trans_ext_id);
                                /**
                                 * -----------------------------------------------
                                 *  TO CLIENT PROCESS
                                 * -----------------------------------------------
                                 */   
                                $log_data = [
                                    "wallet_type" => 2,
                                    "client_transaction_id" => $client_transaction_id, //UNIQUE TRANSACTION
                                    "tw_account_id" => $game_trans_id,
                                ];
                                $game_trans_ext_id = TWHelpers::createTWPlayerAccountsRequestLogs($log_data);
                                $fund_extra_data = [
                                    'provider_name' => $game_details->provider_name
                                ]; 
                                $clientFundsCredit_response = ClientRequestHelper::fundTransfer($client_details, $amount_to_withdraw, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_trans_id, "credit", false,$fund_extra_data);
                                Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($clientFundsCredit_response), "clientFundsCredit_response");
                                if (isset($clientFundsCredit_response->fundtransferresponse->status->code)) {
                                    switch ($clientFundsCredit_response->fundtransferresponse->status->code) {
                                        case "200":
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
                                            /**
                                             * -----------------------------------------------
                                             *  UPDATE LOGS
                                             * -----------------------------------------------
                                             */   
                                            Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), "DONE UPDATE SUCCESS");
                                            return response($msg, 200)->header('Content-Type', 'application/json');
                                    }
                                }

                                // ERROR HANDLE

                            }
                        }
                        
                        $msg = array("status" => "error", "message" => "Transaction failed to withdraw");
                        Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $msg);
                        $account_data = [
                            "status" => 2
                        ];
                        TWHelpers::updatePlayerAccount($account_data, $game_trans_id);
                        $log_data = [
                            "status_code" => "404",
                            "general_details" => json_encode($msg)
                        ];
                        TWHelpers::updateTWPlayerAccountsRequestLogs($log_data, $game_trans_ext_id);
                        return response($msg, 200)->header('Content-Type', 'application/json');
                       
                    }
                    $msg = array("status" => "error", "message" => "Toekn not Found");
                    Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $msg);
                    return response($msg, 200)->header('Content-Type', 'application/json');
                }
            }
            $msg = array("status" => "error", "message" => "Invalid Token or Token not found");
            Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $msg);
            return response($msg, 200)->header('Content-Type', 'application/json');
        }
        // try {
            /**
             * -----------------------------------------------
             *  CLIENT ENDPOINT PLAYER DETAILS CALL
             * -----------------------------------------------
             */   
          
        // } catch (\Exception $e) {
        //     $msg = array("status" => "error", "message" => $e->getMessage() . $e->getLine());
        //     Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
        //     return response($msg, 200)->header('Content-Type', 'application/json');
        // }
    }

    public static function getTransactionHistory(Request $request) {
        Helper::saveLog('IDN TW getTransactionHistory', 110, json_encode($request->all()), 'getTransactionHistory');
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
                                    "operator_id" => $getClientDetails->operator_id,
                                    "client_id" => $getClientDetails->client_id,
                                    "player_id" => $getClientDetails->player_id,
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
}
