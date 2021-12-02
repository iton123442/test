<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\ProviderHelper;
use App\Helpers\KAHelper;
use App\Helpers\IDNPokerHelper;
use App\Helpers\Helper;
use App\Helpers\TransferWalletHelper;
use App\Helpers\ClientRequestHelper;
use Carbon\Carbon;
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
                "status" => "ok",
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
        $player_id = "TGTW".$client_details->player_id;
        $data = IDNPokerHelper::playerDetails($player_id);
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
            $client_response = KAHelper::playerDetailsCall($client_details);
            Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $client_response);
            if($client_response != "false"){
                if(isset($client_response->playerdetailsresponse->status->code) && $client_response->playerdetailsresponse->status->code == 200){
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
                    if ($balance < $request->amount) {
                        $msg = array(
                                "status" => "error",
                                "message" => "Not Enough Balance",
                            );
                        Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
                        return response($msg, 200)->header('Content-Type', 'application/json');
                    }
                    $client_transaction_id = Carbon::now()->timestamp;
                    /**
                     * -----------------------------------------------
                     *  CREATE TRANSACTION WALLET
                     * -----------------------------------------------
                     */   
                    $refe_client_transaction = "";
                    $bet_transaction = GameTransactionMDB::findGameTransactionDetails($client_details->token_id, 'token_id', false, $client_details);
                    $pay_amount = 0;
                    $bet_amount = 0;
                    if($bet_transaction != "false") {
                        $client_details->connection_name = $bet_transaction->connection_name;
                        $game_trans_id = $bet_transaction->game_trans_id;
                        $refe_client_transaction = $bet_transaction->round_id;
                        $pay_amount = $bet_transaction->pay_amount;
                        $bet_amount = $bet_transaction->bet_amount;
                    } else {
                        $gameTransactionData = array(
                            "provider_trans_id" => $client_transaction_id,
                            "token_id" => $client_details->token_id,
                            "game_id" => $game_details->game_id,
                            "round_id" => $client_transaction_id,
                            "bet_amount" => $request->amount,
                            "win" => 5,
                            "pay_amount" => $pay_amount,
                            "income" => 0,
                            "entry_id" =>1,
                            "trans_status" =>1,
                            "operator_id" => $client_details->operator_id,
                            "client_id" => $client_details->client_id,
                            "player_id" => $client_details->player_id,
                        );
                        $game_trans_id = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
                    }
                    $refe_client_transaction = $refe_client_transaction == "" ? $client_transaction_id : $refe_client_transaction;
                    $gameTransactionEXTData = array(
                        "game_trans_id" => $game_trans_id,
                        "provider_trans_id" => $client_transaction_id,
                        "round_id" => $refe_client_transaction,
                        "amount" => $request->amount,
                        "game_transaction_type"=> 1,
                        "provider_request" =>json_encode($request->all()),
                    );
                    $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
                    Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), "FUNDSTRANSFER HIT");
                    $clientFunds_response = ClientRequestHelper::fundTransfer($client_details, $request->amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_trans_id, "debit",false);
                    Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($clientFunds_response), "FUNDSTRANSFER RESPONSE");
                    if (isset($clientFunds_response->fundtransferresponse->status->code)) {
                        switch ($clientFunds_response->fundtransferresponse->status->code) {
                            case "200":
                                if ($bet_transaction != "false") {
                                    $updateGameTransaction = [
                                       'win' => 5,
                                       'bet_amount' => $bet_transaction->bet_amount + $request->amount,
                                       'entry_id' => 1,
                                       'trans_status' => 1
                                   ];
                                   GameTransactionMDB::updateGametransaction($updateGameTransaction, $bet_transaction->game_trans_id, $client_details);
                                }
                                $msg = array(
                                    "status" => "ok",
                                    "message" => "Transaction success",
                                    "balance" => $clientFunds_response->fundtransferresponse->balance
                                );
                                $update_gametransactionext = array(
                                    "mw_response" =>json_encode($msg),
                                    "mw_request"=> json_encode($clientFunds_response->requestoclient),
                                    "client_response" =>json_encode($clientFunds_response->fundtransferresponse),
                                    "transaction_detail" =>"success",
                                    "general_details" => "success",
                                );
                                GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_id,$client_details);
                                $data_deposit = [
                                    "amount" => $request->amount,
                                    "transaction_id" => $client_transaction_id,
                                    "player_id" => "TGTW".$client_details->player_id
                                ];
                                $provider_response = IDNPokerHelper::deposit($data_deposit);
                                $update_gametransactionext = array(
                                    "transaction_detail" =>json_encode($data_deposit),
                                    "general_details" => json_encode($provider_response),
                                );
                                GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_id,$client_details);
                                if($provider_response != "false"){
                                    if (!isset($provider_response["error"])) {
                                         /**
                                         * -----------------------------------------------
                                         *  RETURN RESPONSE
                                         * -----------------------------------------------
                                         */   
                                        Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), $msg);
                                        return response($msg, 200)->header('Content-Type', 'application/json');
                                    }
                                }
                                /**
                                 * -----------------------------------------------
                                 *  IF NOT SUCCESS ROLLBACK TRANSACTION
                                 * -----------------------------------------------
                                 */   
                                Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), "ROLLBACK HIT");
                                $client_transaction_id = Carbon::now()->timestamp;
                                $gameTransactionEXTData = array(
                                    "game_trans_id" => $game_trans_id,
                                    "provider_trans_id" => $client_transaction_id,
                                    "round_id" => $refe_client_transaction,
                                    "amount" => $request->amount,
                                    "game_transaction_type"=> 3,
                                    "provider_request" =>json_encode($request->all()),
                                );
                                $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
                                $clientFundsRollback_response = ClientRequestHelper::fundTransfer($client_details, $request->amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_trans_id, "credit", true);
                                if (isset($clientFundsRollback_response->fundtransferresponse->status->code)) {
                                    switch ($clientFundsRollback_response->fundtransferresponse->status->code) {
                                        case "200":
                                            $msg = array("status" => "error", "message" => "Deposit Failed");
                                            $win = 5;
                                            if($bet_transaction == "false") {
                                                $win = 4;
                                            }
                                            $updateGameTransaction = [
                                                'pay_amount' => $pay_amount + $request->amount,
                                                'win' => $win
                                            ];
                                            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);

                                            $update_gametransactionext = array(
                                                "mw_response" =>json_encode($msg),
                                                "mw_request"=> json_encode($clientFundsRollback_response->requestoclient),
                                                "client_response" =>json_encode($clientFundsRollback_response->fundtransferresponse),
                                                "transaction_detail" =>"success",
                                                "general_details" => "success",
                                            );
                                            GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_id,$client_details);
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
                                $update_gametransactionext = array(
                                    "mw_response" =>json_encode($msg),
                                    "mw_request"=> json_encode($clientFundsRollback_response->requestoclient),
                                    "client_response" =>json_encode($clientFundsRollback_response->fundtransferresponse),
                                    "transaction_detail" =>"ROLLBACK FAILED",
                                    "general_details" => "ROLLBACK FAILED",
                                );
                                GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_id,$client_details);
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
                                Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), "FAILED 402");
                                $updateTransactionEXt = array(
                                    "mw_response" => json_encode($msg),
                                    "mw_request" => json_encode($clientFunds_response->requestoclient),
                                    "client_response" => json_encode($clientFunds_response->fundtransferresponse),
                                    "transaction_detail" => "FAILED",
                                    "general_details" =>"FAILED",
                                );
                                GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                                if($bet_transaction == "false") {
                                    $updateGameTransaction = [
                                        "win" => 2,
                                        'trans_status' => 5
                                    ];
                                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
                                }
                                Helper::saveLog('IDN DEPOSIT', $this->provider_db_id, json_encode($request->all()), "FAILED NOT SET");
                                return response($msg, 200)->header('Content-Type', 'application/json');
                        }
                    } else {
                        $msg = array("status" => "error", "message" => "Deposit Failed");
                        $updateTransactionEXt = array(
                            "mw_response" => json_encode($msg),
                            "mw_request" => json_encode($clientFunds_response->requestoclient),
                            "client_response" => json_encode($clientFunds_response->fundtransferresponse),
                            "transaction_detail" => "FAILED",
                            "general_details" =>"FAILED",
                        );
                        GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                        if($bet_transaction == "false") {
                            $updateGameTransaction = [
                                "win" => 2,
                                'trans_status' => 5
                            ];
                            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
                        }
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
            $client_response = KAHelper::playerDetailsCall($client_details);
            Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $client_response);
            if($client_response != "false"){
                if(isset($client_response->playerdetailsresponse->status->code) && $client_response->playerdetailsresponse->status->code == 200){
                    /**
                     * -----------------------------------------------
                     *  CHECK AMOUNT FOR PLAYER AND AMOUNT TO DEPOSIT
                     * -----------------------------------------------
                     */   
                    $balance = $client_response->playerdetailsresponse->balance; 
                    $client_transaction_id = Carbon::now()->timestamp;
                    /**
                     * -----------------------------------------------
                     *  GET provder BALANCE
                     * -----------------------------------------------
                     */   
                    $player_id = "TGTW".$client_details->player_id;
                    $data = IDNPokerHelper::playerDetails($player_id); // check balance
                    if ($data != "false") {
                        $bet_transaction = GameTransactionMDB::findGameTransactionDetails($client_details->token_id, 'token_id', false, $client_details);
                        $pay_amount = 0;
                        $bet_amount = 0;
                        $amount_to_withdraw = $data["balance"];
                        if($bet_transaction != "false") {
                            $client_details->connection_name = $bet_transaction->connection_name;
                            $game_trans_id = $bet_transaction->game_trans_id;
                            $refe_client_transaction = $bet_transaction->round_id;
                            $pay_amount = $bet_transaction->pay_amount + $amount_to_withdraw;
                            $bet_amount = $bet_transaction->bet_amount;
                            $msg = array(
                                "status" => "ok",
                                "message" => "Transaction success",
                                "balance" => $amount_to_withdraw
                            );
                            $gameTransactionEXTData = array(
                                "game_trans_id" => $game_trans_id,
                                "provider_trans_id" => $client_transaction_id,
                                "round_id" => $refe_client_transaction,
                                "amount" => $amount_to_withdraw,
                                "game_transaction_type"=> 2,
                                "provider_request" =>json_encode($request->all()),
                                "mw_response" =>json_encode($msg),
                            );
                            $game_trans_ext_id = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

                            /**
                             * -----------------------------------------------
                             *  PROVIDER WITHDRAW
                             * -----------------------------------------------
                             */   
                            $data_deposit = [
                                "amount" => $amount_to_withdraw,
                                "transaction_id" => $client_transaction_id,
                                "player_id" => $player_id
                            ];
                            if($data["balance"] == 0 || $data["balance"] == "0") {
                                $provider_response = [
                                    "balance" => 0,
                                    "status" => "success"
                                ];
                            } else {
                                $provider_response = IDNPokerHelper::withdraw($data_deposit);
                            }
                            $update_gametransactionext = array(
                                "transaction_detail" =>json_encode($data_deposit),
                                "general_details" => json_encode($provider_response),
                            );
                            GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_id,$client_details);
                            
                            if($provider_response != "false"){
                                if (!isset($provider_response["error"])) {
                                    /**
                                     * -----------------------------------------------
                                     *  TO CLIENT PROCESS
                                     * -----------------------------------------------
                                     */   
                                    $clientFundsCredit_response = ClientRequestHelper::fundTransfer($client_details, $amount_to_withdraw, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_trans_id, "credit", false);
                                    Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($clientFundsCredit_response), "clientFundsCredit_response");
                                    if (isset($clientFundsCredit_response->fundtransferresponse->status->code)) {
                                        switch ($clientFundsCredit_response->fundtransferresponse->status->code) {
                                            case "200":
                                                $msg = array(
                                                    "status" => "ok",
                                                    "message" => "Transaction success",
                                                    "balance" => $clientFundsCredit_response->fundtransferresponse->balance
                                                );
                                                $updateGameTransaction = [
                                                    'pay_amount' => $pay_amount,
                                                    'win' => ($pay_amount) > 0 ? 1: 0,
                                                ];
                                                GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_id, $client_details);
    
                                                $update_gametransactionext = array(
                                                    "mw_response" =>json_encode($msg),
                                                    "mw_request"=> json_encode($clientFundsCredit_response->requestoclient),
                                                    "client_response" =>json_encode($clientFundsCredit_response->fundtransferresponse),
                                                );
                                                GameTransactionMDB::updateGametransactionEXT($update_gametransactionext,$game_trans_ext_id,$client_details);
                                                /**
                                                 * -----------------------------------------------
                                                 *  UPDATE LOGS
                                                 * -----------------------------------------------
                                                 */   
                                                Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), "DONE UPDATE SUCCESS");
                                                return response($msg, 200)->header('Content-Type', 'application/json');
                                        }
                                    }
    
                                }
                            }
                            $msg = array("status" => "error", "message" => "Transaction failed to withdraw");
                            Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $msg);
                            return response($msg, 200)->header('Content-Type', 'application/json');
                        } else {
                            $msg = array("status" => "error", "message" => "Transaction not Found");
                            Helper::saveLog('IDN WITHDRAW', $this->provider_db_id, json_encode($request->all()), $msg);
                            return response($msg, 200)->header('Content-Type', 'application/json');
                        }
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
}
