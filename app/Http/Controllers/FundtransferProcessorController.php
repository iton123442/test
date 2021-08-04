<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Hash;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
// use App\Helpers\AWSHelper;
use App\Helpers\ClientRequestHelper;
use GuzzleHttp\Client;
use GuzzleHttp\TransferStats;
use GuzzleHttp\Exception\GuzzleException;
use App\Helpers\Game;
use App\Models\GameTransactionMDB;
use DB;



/**
 * [TEST ONLY XD]
 * Make Call To The Client Asynchronous BackGround  (BETA) -RiAN
 * 
 */
class FundtransferProcessorController extends Controller
{
    public static function fundTransfer(Request $request){

        // Helper::saveLog('fundTransfer', 999, json_encode([]), "MAGIC END HIT");
        $payload = json_decode(file_get_contents("php://input"));
        self::saveLogFund(env("CURRENT_SERVER", "NO SERVER"), 100,  json_encode($payload),$payload->request_body->fundtransferrequest->fundinfo->roundId);
        Helper::saveLog($payload->request_body->fundtransferrequest->fundinfo->roundId, 12345, json_encode($payload), 'TG_ARRIVED');

        if($payload->request_body->fundtransferrequest->fundinfo->transactiontype == 'credit'){
            $game_transaction_type = 2;
        }else{
            $game_transaction_type = 1;
        }
        // sleep(10);
        try{
            if ($payload->action->custom->provider == 'hbn' || $payload->action->custom->provider == 'tpp' || $payload->action->custom->provider == 'ygg'){
                $gteid = $payload->action->custom->game_trans_ext_id;
            }else if ($payload->action->custom->provider == "OnlyPlay") {
                $gteid = $payload->action->custom->game_trans_ext_id;
            }else if ($payload->action->custom->provider == "PlayStar") {
                $gteid = $payload->action->custom->game_transaction_ext_id;
            }else if ($payload->action->custom->provider == "Nolimit City") {
                $gteid = $payload->action->custom->game_trans_ext_id;
            }else if ($payload->action->custom->provider == "Smartsoft Gaming") {
                $gteid = $payload->action->custom->game_trans_ext_id;
            }else if ($payload->action->custom->provider == "kagaming") {
                $gteid = $payload->action->custom->game_transaction_ext_id;
            }else if ($payload->action->custom->provider == "allwayspin") {
                $gteid = $payload->action->custom->game_transaction_ext_id;
            }else if ($payload->action->custom->provider == "evoplay") {
                $gteid = $payload->action->custom->game_transaction_ext_id;
            }else if ($payload->action->custom->provider == "sagaming") {
                $gteid = $payload->action->custom->game_transaction_ext_id;
            }else if ($payload->action->custom->provider == "cqgames") {
                $gteid = $payload->action->custom->game_transaction_ext_id;
            }else if ($payload->action->custom->provider == "evolution") {
                $gteid = $payload->action->custom->game_transaction_ext_id;
            }else if ($payload->action->custom->provider == "bng") {
                $gteid = $payload->action->custom->game_transaction_ext_id;
            }else if ($payload->action->custom->provider == "icg") {
                $gteid = $payload->action->custom->game_transaction_ext_id;
            }else if ($payload->action->custom->provider == "wazdan") {
                $gteid = $payload->action->custom->game_transaction_ext_id;
            }else if ($payload->action->custom->provider == "TopTrendGaming") {
                $gteid = $payload->action->custom->game_trans_ext_id;
            }else if ($payload->action->custom->provider == "MannaPlay") {
                $gteid = $payload->action->custom->game_trans_ext_id;
            }else if ($payload->action->custom->provider == "Ozashiki") {
                $gteid = $payload->action->custom->game_trans_ext_id;
            }else if ($payload->action->custom->provider == "SG") {
                $gteid = $payload->action->custom->game_trans_ext_id;
            }
            else{
                $gteid = ClientRequestHelper::generateGTEID(
                    $payload->request_body->fundtransferrequest->fundinfo->roundId,
                    $payload->action->provider->provider_trans_id, 
                    $payload->action->provider->provider_round_id, 
                    $payload->request_body->fundtransferrequest->fundinfo->amount,
                    $game_transaction_type, 
                    $payload->action->provider->provider_request, 
                    $payload->action->mwapi->mw_response
                );
            }
        }catch(\Exception $e){
            Helper::saveLog($payload->request_body->fundtransferrequest->fundinfo->roundId, 12345, json_encode([]), $e->getMessage().' '.$e->getLine().' '.$e->getFile());
        }

        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json',
                'Authorization' => $payload->header->auth
            ]
        ]);
        $requesttocient = [
                "access_token" => $payload->request_body->access_token,
                "hashkey" => $payload->request_body->hashkey,
                "type" => "fundtransferrequest",
                "datetsent" => $payload->request_body->datetsent,
                "gamedetails" => [
                    "gameid" => $payload->request_body->gamedetails->gameid,
                    "gamename" => $payload->request_body->gamedetails->gamename
                ],
                "fundtransferrequest" => [
                "playerinfo" => [
                    "player_username" => $payload->request_body->fundtransferrequest->playerinfo->player_username,
                    "client_player_id" => $payload->request_body->fundtransferrequest->playerinfo->client_player_id,
                    "token" => $payload->request_body->fundtransferrequest->playerinfo->token,
                ],
                "fundinfo" => [
                    "gamesessionid" => "",
                    "transactiontype" => $payload->request_body->fundtransferrequest->fundinfo->transactiontype,
                    "transactionId" => $gteid, # Generated Here!
                    "roundId" => $payload->request_body->fundtransferrequest->fundinfo->roundId,
                    "rollback" => $payload->request_body->fundtransferrequest->fundinfo->rollback,
                    // "freespin" =>$payload->request_body->fundtransferrequest->fundinfo->freespin,
                    "currencycode" => $payload->request_body->fundtransferrequest->fundinfo->currencycode,
                    "amount" => $payload->request_body->fundtransferrequest->fundinfo->amount,
                ]
            ]
        ];

        if(isset($payload->action->provider->provider_name)){
            $requesttocient["gamedetails"]['provider_name'] = $payload->action->provider->provider_name;
        }

        $attempt_count = 1; # Number Of Re Attempt
        $is_succes = false; # Transaction Succeed First Try
        $re_attempt = false; # Re Attempt after Failed
        $api_error = false; # Check if API Logic Error
        $restrict_id = Providerhelper::createRestrictGame($payload->action->mwapi->game_id,$payload->action->mwapi->player_id,$gteid, $requesttocient);

        do {
            if($api_error === false){
                try{
                    if($re_attempt == true){
                        Helper::saveLog($requesttocient['fundtransferrequest']['fundinfo']['roundId'], 12345, json_encode($requesttocient), "RE_ATTEMPT");
                    }
                    $guzzle_response = $client->post($payload->header->endpoint,
                    [
                        'on_stats' => function (TransferStats $stats) use ($requesttocient){
                            $data = [
                                'http_body' => $stats->getHandlerStats(),
                                'request_body' => $requesttocient
                            ];
                            Helper::saveLog($requesttocient['fundtransferrequest']['fundinfo']['roundId'], 12345, json_encode($data), $stats->getTransferTime() .' TG_SUCCESS');
                        },
                        'body' => json_encode($requesttocient)
                    ],
                    ['defaults' => [ 'exceptions' => false ]]
                    );
                    $client_response = json_decode($guzzle_response->getBody()->getContents());

                    if(isset($client_response->fundtransferresponse->status->code) 
                    && $client_response->fundtransferresponse->status->code == "200"){
                        $is_succes = true;
                        Providerhelper::deleteGameRestricted('id', $restrict_id);
                        if($re_attempt == true){
                            Helper::saveLog($requesttocient['fundtransferrequest']['fundinfo']['roundId'], 12345, json_encode($requesttocient), "RE_ATTEMPT_SUCCESS");
                        }
                        # NOTE DEBIT AND CREDIT SOMETIMES HAS DIFFERENT WAY OF UPDATING JUST USE YOUR CUSTOM!!

                        # You can add your own helper for custom gametransaction update like general_details etc!
                        # If you dont want to use custom update change payload type to general!
                        if($payload->action->type == 'custom'){
                            if($payload->action->custom->provider == 'allwayspin'){
                                # No need to update my gametransaction data :) 1 way flight, only the gametransaction extension
                                // $gteid = ClientRequestHelper::updateGTEID($gteid,$requesttocient,$client_response,'success','success' );
                                // ProviderHelper::updateGameTransaction($payload->action->mwapi->roundId, $payload->action->custom->pay_amount, $payload->action->custom->income,  $payload->action->custom->win_or_lost, $payload->action->custom->entry_id);
                                $updateGameTransaction = [
                                    "pay_amount" => $payload->action->custom->pay_amount,
                                    "income" =>  $payload->action->custom->income,
                                    "win" => $payload->action->custom->win_or_lost,
                                    "entry_id" => $payload->action->custom->entry_id,
                                    "trans_status" => 2,
                                ];
                                ClientRequestHelper::updateGameTransactionCCMD($updateGameTransaction, $payload->action->mwapi->roundId, $payload->action->custom->client_connection_name);
                                $ext_Data = ['mw_request' => json_encode($requesttocient),'client_response' => json_encode($client_response), 'mw_response'=> json_encode($payload->action->mwapi->mw_response)];
                                ClientRequestHelper::updateGametransactionEXTCCMD($ext_Data, $gteid, $payload->action->custom->client_connection_name);
                            }
                            elseif($payload->action->custom->provider == 'kagaming'){
                                // if($payload->action->custom->is_multiple){
                                //     ProviderHelper::updateGameTransaction($payload->action->mwapi->roundId, $payload->action->custom->pay_amount, $payload->action->custom->income, $payload->action->custom->win_or_lost, $payload->action->custom->entry_id, 'game_trans_id',$payload->action->custom->bet_amount,$multi_bet=true);
                                // }else{
                                //     ProviderHelper::updateGameTransaction($payload->action->mwapi->roundId, $payload->action->custom->pay_amount, $payload->action->custom->income,  $payload->action->custom->win_or_lost, $payload->action->custom->entry_id);
                                // }
                                // $gteid = ClientRequestHelper::updateGTEID($gteid,$requesttocient,$client_response,'success','success' );

                                if(isset($payload->action->custom->is_multiple) && $payload->action->custom->is_multiple == true){
                                        $updateGameTransaction = [
                                            "bet_amount" => $payload->action->custom->bet_amount,
                                            "pay_amount" => $payload->action->custom->pay_amount,
                                            "income" =>  $payload->action->custom->income,
                                            "win" => $payload->action->custom->win_or_lost,
                                            "entry_id" => $payload->action->custom->entry_id,
                                            "trans_status" => 2,
                                        ];
                                    ClientRequestHelper::updateGameTransactionCCMD($updateGameTransaction, $payload->action->mwapi->roundId, $payload->action->custom->client_connection_name);
                                }else{
                                    $updateGameTransaction = [
                                        "pay_amount" => $payload->action->custom->pay_amount,
                                        "income" =>  $payload->action->custom->income,
                                        "win" => $payload->action->custom->win_or_lost,
                                        "entry_id" => $payload->action->custom->entry_id,
                                        "trans_status" => 2,
                                    ];
                                    ClientRequestHelper::updateGameTransactionCCMD($updateGameTransaction, $payload->action->mwapi->roundId, $payload->action->custom->client_connection_name);
                                }
                                $ext_Data = ['mw_request' => json_encode($requesttocient),'client_response' => json_encode($client_response),'transaction_detail' => "SUCCESS"];
                                ClientRequestHelper::updateGametransactionEXTCCMD($ext_Data, $gteid, $payload->action->custom->client_connection_name);
                            }
                            elseif($payload->action->custom->provider == 'evoplay'){
                                // $gteid = ClientRequestHelper::updateGTEID($gteid,$requesttocient,$client_response,'success','success' );
                                // ProviderHelper::updateGameTransaction($payload->action->mwapi->roundId, $payload->action->custom->pay_amount, $payload->action->custom->income,  $payload->action->custom->win_or_lost, $payload->action->custom->entry_id);
                                $updateGameTransaction = [
                                    "pay_amount" => $payload->action->custom->pay_amount,
                                    "income" =>  $payload->action->custom->income,
                                    "win" => $payload->action->custom->win_or_lost,
                                    "entry_id" => $payload->action->custom->entry_id,
                                    "trans_status" => 2,
                                ];
                                ClientRequestHelper::updateGameTransactionCCMD($updateGameTransaction, $payload->action->mwapi->roundId, $payload->action->custom->client_connection_name);
                                $ext_Data = ['mw_request' => json_encode($requesttocient),'client_response' => json_encode($client_response), 'mw_response'=> json_encode($payload->action->mwapi->mw_response)];
                                ClientRequestHelper::updateGametransactionEXTCCMD($ext_Data, $gteid, $payload->action->custom->client_connection_name);
                            }
                            elseif($payload->action->custom->provider == 'cqgames'){
                                // $gteid = ClientRequestHelper::updateGTEID($gteid,$requesttocient,$client_response,'success','success' );
                                // ProviderHelper::updateGameTransaction($payload->action->mwapi->roundId, $payload->action->custom->pay_amount, $payload->action->custom->income,  $payload->action->custom->win_or_lost, $payload->action->custom->entry_id);
                                $updateGameTransaction = [
                                    "pay_amount" => $payload->action->custom->pay_amount,
                                    "income" =>  $payload->action->custom->income,
                                    "win" => $payload->action->custom->win_or_lost,
                                    "entry_id" => $payload->action->custom->entry_id,
                                    "trans_status" => 2,
                                ];
                                ClientRequestHelper::updateGameTransactionCCMD($updateGameTransaction, $payload->action->mwapi->roundId, $payload->action->custom->client_connection_name);
                                $ext_Data = ['mw_request' => json_encode($requesttocient),'client_response' => json_encode($client_response), 'mw_response'=> json_encode($payload->action->mwapi->mw_response)];
                                ClientRequestHelper::updateGametransactionEXTCCMD($ext_Data, $gteid, $payload->action->custom->client_connection_name);
                            }
                            elseif($payload->action->custom->provider == 'sagaming'){
                                if(!isset($payload->action->custom->update_transaction)){
                                    $updateGameTransaction = [
                                        "pay_amount" => $payload->action->custom->pay_amount,
                                        "income" =>  $payload->action->custom->income,
                                        "win" => $payload->action->custom->win_or_lost,
                                        "entry_id" => $payload->action->custom->entry_id,
                                        "trans_status" => 2,
                                    ];
                                }
                                ClientRequestHelper::updateGameTransactionCCMD($updateGameTransaction, $payload->action->mwapi->roundId, $payload->action->custom->client_connection_name);
                                $ext_Data = ['mw_request' => json_encode($requesttocient),'client_response' => json_encode($client_response), 'mw_response'=> json_encode($payload->action->mwapi->mw_response)];
                                ClientRequestHelper::updateGametransactionEXTCCMD($ext_Data, $gteid, $payload->action->custom->client_connection_name);
                            }
                            elseif($payload->action->custom->provider == 'evolution'){
                                $gteid = ClientRequestHelper::updateGTEIDMDB($gteid,$requesttocient,$client_response,'success','success',$payload->action->custom->client_connection_name);
                            }
                            elseif($payload->action->custom->provider == 'bng'){
                                $gteid = ClientRequestHelper::updateGTEIDMDB($gteid,$requesttocient,$client_response,'success','success',$payload->action->custom->client_connection_name);
                            }
                            elseif($payload->action->custom->provider == 'wazdan'){
                                $gteid = ClientRequestHelper::updateGTEIDMDB($gteid,$requesttocient,$client_response,'success','success',$payload->action->custom->client_connection_name);
                            }
                            elseif($payload->action->custom->provider == 'icg'){
                                $gteid = ClientRequestHelper::updateGTEIDMDB($gteid,$requesttocient,$client_response,'success','success',$payload->action->custom->client_connection_name);
                            }
                            elseif ($payload->action->custom->provider == 'MannaPlay') {
                                $ext_data = array(
                                    "mw_request"=>json_encode($requesttocient),
                                    "client_response" =>json_encode($client_response),
                                    "transaction_detail" =>json_encode("success"),
                                    "general_details" =>json_encode("success")
                                );

                                ClientRequestHelper::updateGametransactionEXTCCMD($ext_data, $gteid, $payload->action->custom->client_connection_name);
                            }
                            elseif ($payload->action->custom->provider == 'Ozashiki') {
                                $ext_data = array(
                                    "mw_request"=>json_encode($requesttocient),
                                    "client_response" =>json_encode($client_response),
                                    "transaction_detail" =>json_encode("success"),
                                    "general_details" =>json_encode("success")
                                );
                                
                                ClientRequestHelper::updateGametransactionEXTCCMD($ext_data, $gteid, $payload->action->custom->client_connection_name);
                            }elseif ($payload->action->custom->provider == 'OnlyPlay') {
                                $ext_data = array(
                                    "mw_request"=>json_encode($requesttocient),
                                    "client_response" =>json_encode($client_response),
                                    "transaction_detail" =>json_encode("success"),
                                    "general_details" =>json_encode("success")
                                );
                                ClientRequestHelper::updateGametransactionEXTCCMD($ext_data, $gteid, $payload->action->custom->client_connection_name);
                            }
                            elseif ($payload->action->custom->provider == 'PlayStar') {
                                $ext_data = array(
                                    "mw_request"=>json_encode($requesttocient),
                                    "client_response" =>json_encode($client_response),
                                    "transaction_detail" =>json_encode("success"),
                                    "general_details" =>json_encode("success")
                                );
                                ClientRequestHelper::updateGametransactionEXTCCMD($ext_data, $gteid, $payload->action->custom->client_connection_name);
                            }elseif ($payload->action->custom->provider == 'Nolimit City') {
                                 $ext_data = array(
                                    "mw_request"=>json_encode($requesttocient),
                                    "client_response" =>json_encode($client_response),
                                    "transaction_detail" =>json_encode("success"),
                                    "general_details" =>json_encode("success")
                                );
                                ClientRequestHelper::updateGametransactionEXTCCMD($ext_data, $gteid, $payload->action->custom->client_connection_name);
                                
                            }elseif ($payload->action->custom->provider == 'Smartsoft Gaming') {
                                 $ext_data = array(
                                    "mw_request"=>json_encode($requesttocient),
                                    "client_response" =>json_encode($client_response),
                                    "transaction_detail" =>json_encode("success"),
                                    "general_details" =>json_encode("success")
                                );
                                ClientRequestHelper::updateGametransactionEXTCCMD($ext_data, $gteid, $payload->action->custom->client_connection_name);
                                
                            }elseif ($payload->action->custom->provider == 'TopTrendGaming') {
                                $ext_data = array(
                                    "mw_request"=>json_encode($requesttocient),
                                    "client_response" =>json_encode($client_response),
                                    "transaction_detail" =>json_encode("success"),
                                    "general_details" =>json_encode("success")
                                );
                                ClientRequestHelper::updateGametransactionEXTCCMD($ext_data, $gteid, $payload->action->custom->client_connection_name);
                            }
                            elseif($payload->action->custom->provider == 'SG'){
                                $updateGameTransaction = [
                                    "win" => $payload->action->custom->win_or_lost,
                                ];
                                ClientRequestHelper::updateGameTransactionCCMD($updateGameTransaction, $payload->action->custom->game_trans_id, $payload->action->custom->client_connection_name);
                                $gteid = ClientRequestHelper::updateGTEIDMDB($gteid,$requesttocient,$client_response,'success','success',$payload->action->custom->client_connection_name);
                            }
                            if($payload->action->custom->provider == 'hbn' || $payload->action->custom->provider == 'tpp' || $payload->action->custom->provider == 'ygg'){
                                $gteid = ClientRequestHelper::updateGTEID($gteid,$requesttocient,$client_response,'success','success' );
                            }
                        }else{
                            # Normal/general Update Game Transaction if you need to update your gametransaction you can add new param to the action payload!
                            $gteid = ClientRequestHelper::updateGTEID($gteid,$requesttocient,$client_response,'success','success' );
                        }
                        Helper::saveLog($requesttocient['fundtransferrequest']['fundinfo']['roundId'], 200, json_encode($requesttocient), " TG_DB_UPDATED");
                    }elseif(isset($client_response->fundtransferresponse->status->code) 
                    && $client_response->fundtransferresponse->status->code == "402"){

                        // $method, $provider_id = 9999, $request_data, $response_data
                        $api_error = false; // true if stop on API CODE 402, false re rerun the 5 times resend
                        $re_attempt = true;
                        if($payload->action->custom->provider == 'bng' || $payload->action->custom->provider == 'wazdan'){
                            $updateGameTransaction = [
                                "win" => 5,
                            ];
                            ClientRequestHelper::updateGameTransactionCCMD($updateGameTransaction, $payload->action->mwapi->roundId, $payload->action->custom->client_connection_name);
                        }
                        Providerhelper::criticalGameRestriction($restrict_id);
                        Helper::saveLog($requesttocient['fundtransferrequest']['fundinfo']['roundId'], 402, json_encode($client_response), "CLIENT_API_ERROR");
                    }else{
                        $api_error = false; // true if stop on API CODE not 402, false re rerun the 5 times resend
                        $re_attempt = true;
                        if($payload->action->custom->provider == 'bng' || $payload->action->custom->provider == 'wazdan'){
                            $updateGameTransaction = [
                                "win" => 5,
                            ];
                            ClientRequestHelper::updateGameTransactionCCMD($updateGameTransaction, $payload->action->mwapi->roundId, $payload->action->custom->client_connection_name);
                        }
                        Providerhelper::criticalGameRestriction($restrict_id);
                        Helper::saveLog($requesttocient['fundtransferrequest']['fundinfo']['roundId'], 402, json_encode($client_response), "CLIENT_API_ERROR");
                    }
                }catch(\Exception $e){
                    # Only HTTP Error Should Be Resended
                    if($payload->action->custom->provider == 'bng' || $payload->action->custom->provider == 'wazdan'){
                        $updateGameTransaction = [
                            "win" => 5,
                        ];
                        ClientRequestHelper::updateGameTransactionCCMD($updateGameTransaction, $payload->action->mwapi->roundId, $payload->action->custom->client_connection_name);
                    }
                    $re_attempt = true;
                    Helper::saveLog($requesttocient['fundtransferrequest']['fundinfo']['roundId'], 504, json_encode($requesttocient), $e->getMessage().' '.$e->getLine().' '.$e->getFile());
                    // Providerhelper::createRestrictGame($payload->action->mwapi->game_id,$payload->action->mwapi->player_id,$gteid, $requesttocient);
                }

                if($attempt_count++ == 5){ // if the last five attempt not success will be stop requesting
                    $is_succes = true;
                    Providerhelper::criticalGameRestriction($restrict_id);
                    Helper::saveLog($requesttocient['fundtransferrequest']['fundinfo']['roundId'], 55555, json_encode($requesttocient), "ATTEMP ADD ++");
                } 

            }else{
                # IF API ERROR IS THE PROBLEM NO NEED TO RETRY DIRECT CONSULT THE CLIENT WHAT IS HAPPENING!
                $is_succes = true;
                // Helper::saveLog(123, 402, json_encode(['msg'=>123]), "API ERROR");
            } # End API ERROR
        } while (!$is_succes);
        self::saveLogFund(env("CURRENT_SERVER", "NO SERVER"), 200,  json_encode($payload),$payload->request_body->fundtransferrequest->fundinfo->roundId);
    }

    public static function saveLogFund($method, $provider_id = 9999, $request_data, $response_data) {
            try{
                if(env('SAVELOGFUND')){
                    $data = [
                        "method_name" => $method,
                        "provider_id" => $provider_id,
                        "request_data" => json_encode(json_decode($request_data)),
                        "response_data" => json_encode($response_data)
                    ];
                    return DB::connection('savelog')->table('seamless_request_logs')->insert($data);
                }else{
                    return 8888888;
                }
            }catch(\Exception $e){
                return 99999999;
            }
    }

    public static function fundTransferTimout(Request $request){

        Helper::saveLog('fundTransfer', 999, json_encode([]), "MAGIC END HIT");
        $payload = json_decode(file_get_contents("php://input"));

        if($payload->request_body->fundtransferrequest->fundinfo->transactiontype == 'credit'){
            $game_transaction_type = 2;
        }else{
            $game_transaction_type = 1;
        }
        $gteid = $payload->request_body->fundtransferrequest->fundinfo->transactionId;
        // sleep(10);

        $client = new Client([
            'headers' => [ 
                'Content-Type' => 'application/json',
                'Authorization' => $payload->header->auth
            ]
        ]);
        $requesttocient = [
                "access_token" => $payload->request_body->access_token,
                "hashkey" => $payload->request_body->hashkey,
                "type" => "fundtransferrequest",
                "datetsent" => $payload->request_body->datetsent,
                "gamedetails" => [
                    "gameid" => $payload->request_body->gamedetails->gameid,
                    "gamename" => $payload->request_body->gamedetails->gamename
                ],
                "fundtransferrequest" => [
                "playerinfo" => [
                    "player_username" => $payload->request_body->fundtransferrequest->playerinfo->player_username,
                    "client_player_id" => $payload->request_body->fundtransferrequest->playerinfo->client_player_id,
                    "token" => $payload->request_body->fundtransferrequest->playerinfo->token,
                ],
                "fundinfo" => [
                    "gamesessionid" => "",
                    "transactiontype" => $payload->request_body->fundtransferrequest->fundinfo->transactiontype,
                    "transactionId" => $gteid, 
                    "roundId" => $payload->request_body->fundtransferrequest->fundinfo->roundId,
                    "rollback" => $payload->request_body->fundtransferrequest->fundinfo->rollback,
                    "freespin" =>$payload->request_body->fundtransferrequest->fundinfo->freespin,
                    "currencycode" => $payload->request_body->fundtransferrequest->fundinfo->currencycode,
                    "amount" => $payload->request_body->fundtransferrequest->fundinfo->amount,
                ]
            ]
        ];


        try{
            $guzzle_response = $client->post($payload->header->endpoint,
            [
                'on_stats' => function (TransferStats $stats) use ($requesttocient){
                    $data = [
                        'http_body' => $stats->getHandlerStats(),
                        'request_body' => $requesttocient
                    ];
                    Helper::saveLog($requesttocient['fundtransferrequest']['fundinfo']['roundId'], 999, json_encode($data), $stats->getTransferTime());
                },
                'body' => json_encode($requesttocient)
            ],
            ['defaults' => [ 'exceptions' => false ]]
            );
            $client_response = json_decode($guzzle_response->getBody()->getContents());

            if(isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "200"){
                # NOTE DEBIT AND CREDIT SOMETIMES HAS DIFFERENT WAY OF UPDATING JUST USE YOUR CUSTOM!!

                # You can add your own helper for custom gametransaction update like general_details etc!
                # If you dont want to use custom update change payload type to general!
                if($payload->action->type == 'custom'){
                   
                    if($payload->action->custom->provider == 'tpp'){
                        $updateGameTransExt = DB::table('game_transaction_ext')->where('game_trans_ext_id','=',$gteid)->update(["amount" => $payload->request_body->fundtransferrequest->fundinfo->amount ,"game_transaction_type" => $game_transaction_type, "provider_request" => json_encode($payload->action->provider->provider_request),"mw_response" => json_encode($payload->action->mwapi->mw_response),"mw_request" => json_encode($requesttocient),"client_response" => json_encode($client_response),"transaction_detail" => "success" ]);
                    }
                }else{
                    # Normal/general Update Game Transaction if you need to update your gametransaction you can add new param to the action payload!
                    $gteid = ClientRequestHelper::updateGTEID($gteid,$requesttocient,$client_response,'success','success' );
                }

            }elseif(isset($client_response->fundtransferresponse->status->code) 
            && $client_response->fundtransferresponse->status->code == "402"){
                # Create a Restriction Entry
                # Sidenote
                Providerhelper::createRestrictGame($payload->action->mwapi->game_id,$payload->action->mwapi->player_id,$gteid, $requesttocient);
                //  ProviderHelper::updatecreateGameTransExt($payload->request_body->fundtransferrequest->fundinfo->transactionId, 'FAILED', 'FAILED', $client_response->requestoclient, $client_response,'success');

            }
            Helper::saveLog($requesttocient['fundtransferrequest']['fundinfo']['roundId'], 200, json_encode([]), "MAGIC END HIT RECEIVED");
        }catch(\Exception $e){
            Helper::saveLog($requesttocient['fundtransferrequest']['fundinfo']['roundId'], 504, json_encode($requesttocient), $e->getMessage().' '.$e->getLine().' '.$e->getFile());
            Providerhelper::createRestrictGame($payload->action->mwapi->game_id,$payload->action->mwapi->player_id,$gteid, $requesttocient);
        }
    }

    /**
     * THIS METHOD FROM TWO CALLBACK DEBIT AND CREDIT AND FUNDSTRANFER PROCESS 
     * METHOD USE INSERT/UPDATE/FUNDSTRANFER
     * REQUEST FORMAT EXAMPLE
     * body => [
          "token" => "n58ec5e159f769ae0b7b3a0774fdbf80"
          "rollback" => false
          "game_details" => [
            "game_code" => "GHG_HAWAIIAN_DREAM"
            "provider_id" => 18
          ]
          "game_transaction" => [
            "provider_trans_id" => "ORYX2P168_1614181"
            "round_id" => "ORYX2P168_2040870"
            "amount" => 4
          ]
          "provider_request" => [
            "sessionToken" => "n58ec5e159f769ae0b7b3a0774fdbf80"
            "playerId" => "4411"
            "roundId" => "ORYX2P168_1614181"
            "roundAction" => "CLOSE"
            "gameCode" => "GHG_HAWAIIAN_DREAM"
            "win" => [
              "transactionId" => "ORYX2P168_2040870"
              "amount" => 400
              "timestamp" => 1609070687
            ]
          ]
          //this for the extension for credit process
          "existing_bet" => [
            "game_trans_id" => 158
          ]
        ]
     */

    public function backgroundProcessDebitCreditFund(Request $request, $type)
    {
        $response = [];
        $details = json_decode(file_get_contents("php://input"), true);
        Helper::saveLog('backgroundProcessDebitCreditFund', 88, json_encode($details), "ENDPOINT HIT");
        $client_details = ProviderHelper::getClientDetails('token', $details["token"]);
       
        
        $game_details = Game::find($details["game_details"]["game_code"], $details["game_details"]["provider_id"]);
        
        $provider_trans_id = $details["game_transaction"]["provider_trans_id"];
        $round_id =  $details["game_transaction"]["round_id"];
        $amount = $details["game_transaction"]["amount"]; // amount should be fixed after sending data
        $provider_request = $details["provider_request"];
        
        
        if ($type == "debit") {
            $pay_amount = 0;
            $income = 0;
            $method = 1;
            $win_or_lost = 5; // 0 lost,  5 processing
            $payout_reason = ProviderHelper::updateReason(2);
            $game_transaction_id  = ProviderHelper::createGameTransaction($client_details->token_id, $game_details->game_id, $amount,  $pay_amount, $method, $win_or_lost, $payout_reason, $payout_reason, $income, $provider_trans_id, $round_id);
            $game_trans_ext_id = ProviderHelper::createGameTransExtV2($game_transaction_id, $provider_trans_id, $round_id, $amount, 1, $provider_request);
        } elseif ($type == "credit") {
            $game_transaction_id = $details["existing_bet"]["game_trans_id"];
            $existing_bet = ProviderHelper::findGameTransaction($game_transaction_id, "game_transaction");
            $win = $amount > 0  ?  1 : 0;  /// 1win 0lost
            $type_transaction = $amount > 0  ? "credit" : "debit";
            $request_data = [
                'win' => $win,
                'amount' => $amount,
                'payout_reason' => ProviderHelper::updateReason(1),
            ];
           
            $game_trans_ext_id = ProviderHelper::createGameTransExtV2($existing_bet->game_trans_id, $provider_trans_id, $round_id, $amount, 2, $provider_request);
            Helper::updateGameTransaction($existing_bet,$request_data,$type_transaction);
        }
        
        $body_details = [
            "type" => $type,
            "token" => $client_details->player_token,
            "rollback" => false,
            "game_details" => [
                "game_id" => $game_details->game_id
            ],
            "game_transaction" => [
                "provider_trans_id" => $provider_trans_id,
                "round_id" => $round_id,
                "amount" => $amount
            ],
            "provider_request" => $provider_request,
            "game_trans_ext_id" => $game_trans_ext_id,
            "game_transaction_id" => $game_transaction_id
        ];
       
        try{
            $client = new Client();
            $guzzle_response = $client->post(config('providerlinks.oauth_mw_api.mwurl') . '/tigergames/bg-fundtransfer',
                [ 'body' => json_encode($body_details), 'timeout' => '0.20']
            );
        } catch(\Exception $e){
            Helper::saveLog('readWriteProcess passing_data', 88, json_encode($details), $body_details);
        }

       
    }

    public function bgFundTransfer(Request $request){
        $response = [];
        $details = json_decode(file_get_contents("php://input"), true);
        Helper::saveLog('backgroundProcesstFund', 88, json_encode($details), "ENDPOINT HIT");
        $client_details = ProviderHelper::getClientDetails('token', $details["token"]);
        $game_details = Game::findbyid($details["game_details"]["game_id"]);

        $provider_trans_id = $details["game_transaction"]["provider_trans_id"];
        $round_id =  $details["game_transaction"]["round_id"];
        $amount = $details["game_transaction"]["amount"]; // amount should be fixed after sending data
        $provider_request = $details["provider_request"];  

        $game_trans_ext_id = $details["game_trans_ext_id"];
        $game_transaction_id = $details["game_transaction_id"];
        $type = $details["type"];
        try {
            $client_response = ClientRequestHelper::fundTransfer($client_details, $amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, $type, $details["rollback"]);
        } catch (\Exception $e) {
            ProviderHelper::updatecreateGameTransExt($game_trans_ext_id, 'FAILED', 'FAILED', 'FAILED', 'FAILED', 'FAILED', 'FAILED');
            // ProviderHelper::updateGameTransactionStatus($game_transaction_id, 2, 99);
            $mw_payload = ProviderHelper::fundTransfer_requestBody($client_details,$amount,$game_details->game_code,$game_details->game_name,$game_trans_ext_id,$game_transaction_id,$type);
            ProviderHelper::createRestrictGame($game_details->game_id, $client_details->player_id, $game_trans_ext_id, $mw_payload);
            Helper::saveLog('backgroundProcesstFund FATAL ERROR', 88, json_encode($details), Helper::datesent());
        }
       
        if(isset($client_response->fundtransferresponse->status->code) && $client_response->fundtransferresponse->status->code == "200") 
        {
            // updateting balance
            // ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance); 
            
            // AND HERE PROVIDER LOGIC USE FUNCTION MAKE ORGANIZE
            // if (array_key_exists('provider_name', $details) ) {
                
            //     if ($details["provider_name"] == "TGG") {
            //         // prcoess fresspin
                    
            //     }

            // }
            // DEFAULT MW-RESPONSE
            $response = [
                "status" => "ok",
                "balance" => $client_response->fundtransferresponse->balance,
            ];
            $this->updateGameTransactionExt($game_trans_ext_id,$client_response->requestoclient,$response,$client_response->fundtransferresponse);
            Helper::saveLog('backgroundProcesstFund', 88, json_encode($details), $response);
        } 
        elseif (isset($client_response->fundtransferresponse->status->code) && $client_response->fundtransferresponse->status->code == "402")
        {
            // ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance); 
            
            // AND HERE PROVIDER LOGIC USE FUNCTION MAKE ORGANIZE
            // if (array_key_exists('provider_name', $details) ) {
                
            //     if ($details["provider_name"] == "TGG") {
            //         // prcoess fresspin
                    
            //     }

            // }

            // DEFAULT MW-RESPONSE
            $response = [
                "status" => "error",
                "message" => "not enough money",
                "balance" => $client_response->fundtransferresponse->balance,
            ];
            $this->updateGameTransactionExt($game_trans_ext_id,$client_response->requestoclient,$response,$client_response->fundtransferresponse);
            // ProviderHelper::updateGameTransactionStatus($game_transaction_id, 2, 99);
            ProviderHelper::createRestrictGame($game_details->game_id, $client_details->player_id, $game_trans_ext_id, $client_response->requestoclient);
            Helper::saveLog('backgroundProcesstFund', 88, json_encode($details), $response);
        }
    }

    public static function updateGameTransactionExt($gametransextid,$mw_request,$mw_response,$client_response){
        $gametransactionext = array(
            "mw_request"=>json_encode($mw_request),
            "mw_response" =>json_encode($mw_response),
            "client_response" =>json_encode($client_response),
            "transaction_detail" => "success",
        );
        DB::table('game_transaction_ext')->where("game_trans_ext_id",$gametransextid)->update($gametransactionext);
    }

  /**
    * VERSION TWO IF
    * @author NOTE:::: THIS FUNCTION USING FOR THE CREDIT CUT CALL
    *  [PROVIDER LIST ]
    *  FUNTA , SLOTMILL
    *
    *
    */
    public function bgFundTransferV2(Request $request){
        $details = json_decode(file_get_contents("php://input"), true);
        // Helper::saveLog('backgroundProcesstFund', 88, json_encode($details), "ENDPOINT HIT");
        $client_details = ProviderHelper::getClientDetails('token', $details["token"]);
        $game_details = Game::findbyid($details["game_details"]["game_id"]);

        $provider_trans_id = $details["game_transaction"]["provider_trans_id"];
        $round_id =  $details["game_transaction"]["round_id"];
        $amount = $details["game_transaction"]["amount"]; // amount should be fixed after sending data
        $provider_request = $details["provider_request"];  

        $game_trans_ext_id = $details["game_trans_ext_id"];
        $game_transaction_id = $details["game_transaction_id"];
        $type = $details["type"];

        $is_not_proceess = false;

        // FIVE ATTEMPT IF NOT sucess then stop if failed 5
        $attempt_count = 1;
        $is_succes = false;

        $mw_payload = ProviderHelper::fundTransfer_requestBody($client_details,$amount,$game_details->game_code,$game_details->game_name,$game_trans_ext_id,$game_transaction_id,$type);

        do {
            
            try {
                $client_response = ClientRequestHelper::fundTransfer($client_details, $amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, $type, $details["rollback"]);
            } catch (\Exception $e) {
                $response = [
                    "status" => "error",
                    "msg" => "FATAL ERROR",
                ];
                $this->updateGameTransactionExtV2Sucess($game_trans_ext_id,$details["provider_response"], $mw_payload, $response, "FATAL ERROR");
                ProviderHelper::createRestrictGame($game_details->game_id, $client_details->player_id, $game_trans_ext_id, $mw_payload);
                Helper::saveLog("FATAL ERROR", $game_trans_ext_id, json_encode($mw_payload), $response);
                $is_succes = true;
                // return $response;
            }
            if (isset($client_response->fundtransferresponse->status->code)) {

                    switch ($client_response->fundtransferresponse->status->code) {
                        case '200':
                            $this->updateGameTransactionExtV2Sucess($game_trans_ext_id,$details["provider_response"], $client_response->requestoclient, $client_response->fundtransferresponse, "success");
                            ProviderHelper::updateGameTransactionStatus($game_transaction_id, $details["win"], $details["win"]);
                            Helper::saveLog("success", $game_trans_ext_id, json_encode($client_response->requestoclient), $client_response->fundtransferresponse);
                            $this->deleteGameRestrictedGame($game_trans_ext_id);
                            $is_succes = true;
                            break;
                        case '402':
                            $this->updateGameTransactionExtV2Sucess($game_trans_ext_id, $details["provider_response"], $client_response->requestoclient, $client_response->fundtransferresponse,"need to settlement");
                            ProviderHelper::createRestrictGame($game_details->game_id, $client_details->player_id, $game_trans_ext_id, $mw_payload);
                            Helper::saveLog("bad response attempt_count = " . $attempt_count , $game_trans_ext_id, json_encode($client_response->requestoclient), $client_response->fundtransferresponse);
                            break;
                    }
            }

            if($attempt_count++ == 5){ // if the last five attempt not success will be stop requesting
                $is_succes = true;
            } 
        } while (!$is_succes);

        $response =[ "status" => "ok" , "msg" => "proccess complete"];
        return $response;
    }

    public static function updateGameTransactionExtV2Sucess($gametransextid,$mw_response,$mw_request,$client_response,$details){
        $gametransactionext = array(
            "mw_response" =>json_encode($mw_response),
            "mw_request"=>json_encode($mw_request),
            "client_response" =>json_encode($client_response),
            "transaction_detail" => $details,
        );
        DB::table('game_transaction_ext')->where("game_trans_ext_id",$gametransextid)->update($gametransactionext);
    }

    public static function deleteGameRestrictedGame($identifier){
        DB::select('delete from game_player_restriction where game_trans_ext_id = '.$identifier.' ');
    }

    public function bgFundTransferV2MultiDB(Request $request){
        $details = json_decode(file_get_contents("php://input"), true);
        // Helper::saveLog('backgroundProcesstFund', 88, json_encode($details), "ENDPOINT HIT");
        $client_details = ProviderHelper::getClientDetails('token', $details["token"]);
        $game_details = Game::findbyid($details["game_details"]["game_id"]);

        $amount = $details["game_transaction"]["amount"]; // amount should be fixed after sending data

        $game_trans_ext_id = $details["game_trans_ext_id"];
        $game_transaction_id = $details["game_transaction_id"];
        $connection_name = $details["connection_name"];
        $client_details->connection_name = $connection_name;
        $type = $details["type"];

        $is_not_proceess = false;

        // FIVE ATTEMPT IF NOT sucess then stop if failed 5
        $attempt_count = 1;
        $is_succes = false;

        $fund_extra_data = [
            'provider_name' => $game_details->provider_name
        ];

        do {
            
            try {
                $client_response = ClientRequestHelper::fundTransfer($client_details, $amount, $game_details->game_code, $game_details->game_name, $game_trans_ext_id, $game_transaction_id, $type, $details["rollback"], $fund_extra_data );
            } catch (\Exception $e) {
                $is_succes = true;
            }
            if (isset($client_response->fundtransferresponse->status->code)) {

                    switch ($client_response->fundtransferresponse->status->code) {
                        case '200':
                             $updateTransactionEXt = array(
                                'mw_request' => json_encode($client_response->requestoclient),
                                'client_response' => json_encode($client_response->fundtransferresponse),
                                'transaction_detail' => 'success',
                                'general_details' => 'success',
                            );
                            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                            $updateGameTransaction = [
                                'win' => $details["win"],
                            ];
                            GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_transaction_id, $client_details);
                            Helper::saveLog("success", $game_trans_ext_id, json_encode($client_response->requestoclient), $client_response->fundtransferresponse);
                            $is_succes = true;
                            break;
                        default:
                            $updateTransactionEXt = array(
                                'mw_request' => json_encode($client_response->requestoclient),
                                'client_response' => json_encode($client_response->fundtransferresponse),
                                'transaction_detail' => 'NEED TO SETTLEMENT',
                                'general_details' => 'NEED TO SETTLEMENT',
                            );
                            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_trans_ext_id,$client_details);
                            Helper::saveLog("bad response attempt_count = " . $attempt_count , $game_trans_ext_id, json_encode($client_response->requestoclient), $client_response->fundtransferresponse);
                    }
            }

            if($attempt_count++ == 10){ // if the last five attempt not success will be stop requesting
                $is_succes = true;
            } 
        } while (!$is_succes);

        $response =[ "status" => "ok" , "msg" => "proccess complete"];
        return $response;
    }
   
}

