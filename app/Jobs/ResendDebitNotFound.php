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



/**
 * @deprecated
 */
class ResendDebitNotFound extends Job implements ShouldQueue
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
                        // 'timeout' => 2, # 2 seconds
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
                    sleep(3);
                    $retryCount++;
                }else{
                    sleep(3);
                    $retryCount++;
                }

                if($retryCount == 2){ 
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
            }else if(isset($client_response->fundtransferresponse->status->code) && $client_response->fundtransferresponse->status->code == 402){
                $updateTransactionEXt = array(
                    // "provider_request" =>json_encode($payload),
                    "mw_response" => json_encode(['retry' => 'jobs']),
                    'mw_request' => json_encode($requesttocient),
                    'client_response' => json_encode($client_response),
                    'transaction_detail' => 'TRANSACTION_NOT_FOUND',
                    'general_details' => DB::raw('IFNULL(general_details, 0) + 1')
                );
                // ProviderHelper::mandatorySaveLog($round_id, 333,json_encode($client_response), 'NOT_ENOUGH_FUNDS');
                GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$transaction_id,$client_details);

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
                'mw_request' => json_encode(['retry' => 'jobs']),
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
