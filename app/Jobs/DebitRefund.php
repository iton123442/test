<?php

namespace App\Jobs;
use App\Helpers\ProviderHelper;
use App\Models\GameTransactionMDB;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\TransferStats;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use App\Jobs\Job;
use DB;



class DebitRefund extends Job implements ShouldQueue
{
    /**
     * Create a new job instance.
     *
     * @return void
     */

    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $payload = $this->data;

        $client_details = $payload['client_details'];
        $client_callback_url = $client_details->fund_transfer_url;
        $client_access_token = $client_details->client_access_token;

        # Operator with custom data type!
        $custom_operator = config('clientcustom.data_type.transaction.string');
        if(isset($client_details->operator_id) && in_array($client_details->operator_id, $custom_operator)){
            $transaction_id = (string)$payload['transaction_id'];
            $round_id = (string)$payload['payload']['fundtransferrequest']['fundinfo']['roundId'];
        }else{
            $transaction_id = $payload['transaction_id'];
            $round_id = $payload['payload']['fundtransferrequest']['fundinfo']['roundId'];
        }


        // Modify the payload use the generated extension
        $payload['payload']['fundtransferrequest']['fundinfo']['transactionId'] = $transaction_id; // use the same generated game ext id every call
        $payload['payload']['fundtransferrequest']['fundinfo']['rollback'] = true; // change the type to true
        $payload['payload']['fundtransferrequest']['fundinfo']['transactiontype'] = 'credit'; // change the type to true
        $requesttocient = $payload['payload'];

        // $data = [
        //     "method_name" => "jobs",
        //     "provider_id" => 123,
        //     "request_data" => json_encode($this->data),
        //     "response_data" => json_encode($requesttocient)
        // ];
        // DB::connection('savelog')->table('seamless_request_logs')->insert($data);


        sleep(1); // Let the client process things for 1 second then request again!

        try {
            $sendtoclient =  microtime(true);
            $client = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$client_access_token
                ]
            ]);

            $retryCount = 0;
            $canProceed = false;
            do {
                $guzzle_response = $client->post($client_callback_url,
                    [
                        'on_stats' => function (TransferStats $stats) use ($requesttocient){
                            $data = [
                                'http_body' => $stats->getHandlerStats(),
                                'request_body' => $requesttocient
                            ];
                            ProviderHelper::saveLogLatency($requesttocient['fundtransferrequest']['fundinfo']['roundId'], 999, json_encode($data), $stats->getTransferTime());
                        },
                        'timeout' => 10, # 10 seconds
                        'body' => json_encode($requesttocient)
                    ],
                    ['defaults' => [ 'exceptions' => false ]]
                );
                $client_response = json_decode($guzzle_response->getBody()->getContents());
                $client_response_time = microtime(true) - $sendtoclient;

                if(isset($client_response->fundtransferresponse->status->code) && $client_response->fundtransferresponse->status->code == 200){
                    $canProceed = true;
                    continue;
                }else if(isset($client_response->fundtransferresponse->status->status) && $client_response->fundtransferresponse->status->status == 'TRANSACTION_NOT_FOUND'){
                    // sleep(3);
                    if($this->attempts() == 1){
                        $this->release(1800); // 30minutes in seconds
                        try {
                            $data = [
                                "round_id" => $round_id,
                                "player_id" => $client_details->player_id,
                                "connection_name" => $client_details->connection_name,
                                "metadata" => "30min"
                            ];
                            $data_saved = DB::table('retry_not_found')->insert($data);
                        } catch (\Exception $e) {
                           throw new \ErrorException('retry_not_found');
                        }
                        $canProceed = true;
                    }else{
                        try {
                            $data = [
                                "round_id" => $round_id,
                                "player_id" => $client_details->player_id,
                                "connection_name" => $client_details->connection_name,
                                "metadata" => "nomin"
                            ];
                            $data_saved = DB::table('retry_not_found')->insert($data);
                        } catch (\Exception $e) {
                           throw new \ErrorException('retry_not_found');
                        }
                        $canProceed = true;
                    }
                    $retryCount++;
                }else{
                    // sleep(3);
                    $canProceed = true;
                    $retryCount++;
                }

                if($retryCount == 2){ 
                    // $debitRefund = ["payload" => $requesttocient, "client_details" => $client_details, "transaction_id" => $transaction_id];
                    // ProviderHelper::resendDebitNotFound($debitRefund);
                    // $this->release(60);
                    $canProceed = true;
                }

            } while (!$canProceed);


            if(isset($client_response->fundtransferresponse->status->code) && $client_response->fundtransferresponse->status->code == 200){
                $updateTransactionEXt = array(
                    // "provider_request" =>json_encode($payload),
                    "mw_response" => json_encode(['retry' => 'jobs']),
                    'mw_request' => json_encode($requesttocient),
                    'client_response' => json_encode($client_response),
                    'transaction_detail' => 'SUCCESS',
                    'general_details' => DB::raw('IFNULL(general_details, 0) + 1')
                );
                GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$transaction_id,$client_details);

                $countGameExt = GameTransactionMDB::CountGameExtAll($round_id, 'game_trans_id', $client_details);
                if (isset($countGameExt[0]->total) && $countGameExt[0]->total == 2){
                    // Update to refund
                    $updateGameTransaction = ["win" => 4, "pay_amount" => $payload['payload']['fundtransferrequest']['fundinfo']['amount']];
                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $round_id, $client_details);
                }
                return;

            }else if(isset($client_response->fundtransferresponse->status->code) && $client_response->fundtransferresponse->status->code == 402){


                if(isset($client_response->fundtransferresponse->status->status) && $client_response->fundtransferresponse->status->status == 'DUPLICATE_TRANSACTION'){
                    $updateTransactionEXt = array(
                        "mw_response" => json_encode(['retry' => 'jobs']),
                        'mw_request' => json_encode($requesttocient),
                        'client_response' => json_encode($client_response),
                        'transaction_detail' => 'DUPLICATE_TRANSACTION',
                        'general_details' => DB::raw('IFNULL(general_details, 0) + 1')
                    );
                    GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$transaction_id,$client_details);

                    $countGameExt = GameTransactionMDB::CountGameExtAll($round_id, 'game_trans_id', $client_details);
                    if (isset($countGameExt[0]->total) && $countGameExt[0]->total == 2){
                        // Update to refund
                        $updateGameTransaction = ["win" => 4, "pay_amount" => $payload['payload']['fundtransferrequest']['fundinfo']['amount']];
                        GameTransactionMDB::updateGametransaction($updateGameTransaction, $round_id, $client_details);
                    }
                }else{
                    $updateTransactionEXt = array(
                        "mw_response" => json_encode(['retry' => 'jobs']),
                        'mw_request' => json_encode($requesttocient),
                        'client_response' => json_encode($client_response),
                        'transaction_detail' => 'TRANSACTION_NOT_FOUND',
                        'general_details' => DB::raw('IFNULL(general_details, 0) + 1')
                    );
                    GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$transaction_id,$client_details);

                    // No need further update the transaction is not found on client!
                }


                return;

                // try {

                //     if(isset($client_response->fundtransferresponse->status->status) && $client_response->fundtransferresponse->status->status == 'TRANSACTION_NOT_FOUND'){

                //         sleep(2);

                //         $datatosend = [
                //             "access_token" => $client_details->client_access_token,
                //             "hashkey" => md5($client_details->client_api_key.$client_details->client_access_token),
                //             "player_username"=>$client_details->username,
                //             "client_player_id" => $client_details->client_player_id,
                //             "transactionId" => $transaction_id,
                //             "roundId" =>  $round_id
                //         ];

                //         $guzzle_response = $client->post($client_details->transaction_checker_url,
                //             [
                //                 'body' => json_encode($datatosend),
                //             ]
                //         );
                //         $client_checker_response = json_decode($guzzle_response->getBody()->getContents());
                //         if (isset($client_checker_response->code)) {
                //             $is_success = 2;
                //             if ($client_checker_response->code == '200' || $client_checker_response->code == '403') {
                //                 # Not found refund but progressing need to add back to jobs table!
                //                 $updateTransactionEXt = array(
                //                     // "provider_request" =>json_encode($payload),
                //                     "mw_response" => json_encode(['retry' => 'jobs']),
                //                     'mw_request' => json_encode($requesttocient),
                //                     'client_response' => json_encode($client_response),
                //                     'transaction_detail' => 'RESEND_TRANSACTION',
                //                     'general_details' => DB::raw('IFNULL(general_details, 0) + 1')
                //                 );
                //                 // ProviderHelper::mandatorySaveLog($round_id, 333,json_encode($client_response), 'NOT_ENOUGH_FUNDS');
                //                 GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$transaction_id,$client_details);
                //                throw new \ErrorException('RESEND_TRANSACTION');
                //             }else{
                //                 $updateTransactionEXt = array(
                //                     // "provider_request" =>json_encode($payload),
                //                     "mw_response" => json_encode(['retry' => 'jobs']),
                //                     'mw_request' => json_encode($requesttocient),
                //                     'client_response' => json_encode($client_response),
                //                     'transaction_detail' => 'CHECK_NOT_FOUND',
                //                     'general_details' => DB::raw('IFNULL(general_details, 0) + 1')
                //                 );
                //                 // ProviderHelper::mandatorySaveLog($round_id, 333,json_encode($client_response), 'NOT_ENOUGH_FUNDS');
                //                 GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$transaction_id,$client_details);
                //                 // throw new \ErrorException('CHECK_NOT_FOUND');

                //                 try {
                //                     if ($this->attempts() == 1){
                //                        return $this->release(10);
                //                     }
                //                 } catch (\Exception $e) {
                //                     ProviderHelper::mandatorySaveLog($round_id, 333,json_encode($client_response), 'ATTEMPT_NOT_FOUND_FAILED');
                //                 }

                //                 // else{
                //                 //     try {
                //                 //         $data = [
                //                 //             "round_id" => $round_id,
                //                 //             "player_id" => $client_details->player_id,
                //                 //             "connection_name" => $client_details->connection_name
                //                 //         ];
                //                 //         $data_saved = DB::table('retry_not_found')->insert($data);
                //                 //     } catch (\Exception $e) {
                //                 //        throw new \ErrorException('retry_not_found');
                //                 //     }
                //                 // }

                //             } 
                //         }else{
                //             $updateTransactionEXt = array(
                //                 // "provider_request" =>json_encode($payload),
                //                 "mw_response" => json_encode(['retry' => 'jobs']),
                //                 'mw_request' => json_encode($requesttocient),
                //                 'client_response' => json_encode($client_response),
                //                 'transaction_detail' => 'FAILED_CHECK',
                //                 'general_details' => DB::raw('IFNULL(general_details, 0) + 1')
                //             );
                //             // ProviderHelper::mandatorySaveLog($round_id, 333,json_encode($client_response), 'NOT_ENOUGH_FUNDS');
                //             GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$transaction_id,$client_details);
                //            throw new \ErrorException('FAILED_CHECK');
                //         }

                //     }else{
                //         $updateTransactionEXt = array(
                //             // "provider_request" =>json_encode($payload),
                //             "mw_response" => json_encode(['retry' => 'jobs']),
                //             'mw_request' => json_encode($requesttocient),
                //             'client_response' => json_encode($client_response),
                //             'transaction_detail' => 'TRANSACTION_NOT_FOUND',
                //             'general_details' => DB::raw('IFNULL(general_details, 0) + 1')
                //         );
                //         // ProviderHelper::mandatorySaveLog($round_id, 333,json_encode($client_response), 'NOT_ENOUGH_FUNDS');
                //         GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$transaction_id,$client_details);
                //     }
                    
                // } catch (\Exception $e) {
                //     $updateTransactionEXt = array(
                //         // "provider_request" =>json_encode($payload),
                //         "mw_response" => json_encode(['retry' => 'jobs']),
                //         'mw_request' => json_encode($requesttocient),
                //         'client_response' => json_encode($e->getMessage().' '.$e->getLine()),
                //         'transaction_detail' => 'FAILED_EXCEPTION',
                //         'general_details' => DB::raw('IFNULL(general_details, 0) + 1')
                //     );
                //     // ProviderHelper::mandatorySaveLog($round_id, 333,json_encode($client_response), 'NOT_ENOUGH_FUNDS');
                //     GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$transaction_id,$client_details);
                //     throw new \ErrorException('FAILED_EXCEPTION');
                // }
            }else{
                $updateTransactionEXt = array(
                    // "provider_request" =>json_encode($payload),
                    "mw_response" => json_encode(['retry' => 'jobs']),
                    'mw_request' => json_encode($requesttocient),
                    'client_response' => json_encode($client_response),
                    'transaction_detail' => 'UNKNOWN_STATUS_CODE',
                    'general_details' => DB::raw('IFNULL(general_details, 0) + 1')
                );
                GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$transaction_id,$client_details);
                // ProviderHelper::mandatorySaveLog($round_id, 333,json_encode($client_response), 'UNKNOWN_STATUS_CODE');
                throw new \ErrorException('UNKNOWN_ERROR');
            }

        } catch (\Exception $e) {

            $updateTransactionEXt = array(
                // "provider_request" =>json_encode(['gg' => 'gg']),
                'mw_request' => json_encode($requesttocient),
                'client_response' => json_encode($e->getMessage().' '.$e->getLine()),
                'transaction_detail' => 'FAILED_EXCEPTION',
                'general_details' => DB::raw('IFNULL(general_details, 0) + 1')
            );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$transaction_id,$client_details);
            // ProviderHelper::mandatorySaveLog($round_id, 333,json_encode($updateTransactionEXt), 'FAILED_EXCEPTION');
            throw new \ErrorException($e->getMessage());

        }

        
    }
}
