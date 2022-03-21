<?php

namespace App\Jobs;
use App\Helpers\ProviderHelper;
use App\Models\GameTransactionMDB;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\TransferStats;
use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;


// NOT USED
class DebitRefund extends Job
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
        $transaction_id = $payload['transaction_id'];

        // Modify the payload use the generated extension
        $payload['payload']['fundtransferrequest']['fundinfo']['transactionId'] = $transaction_id; // use the same generated game ext id every call
        $payload['payload']['fundtransferrequest']['fundinfo']['rollback'] = true; // change the type to true
        $payload['payload']['fundtransferrequest']['fundinfo']['transactiontype'] = 'credit'; // change the type to true
        $requesttocient = $payload['payload'];

        $data = [
            "method_name" => "jobs",
            "provider_id" => 123,
            "request_data" => json_encode($this->data),
            "response_data" => json_encode($requesttocient)
        ];
        DB::connection('savelog')->table('seamless_request_logs')->insert($data);

        try {
            $sendtoclient =  microtime(true);
            $client = new Client([
                'headers' => [ 
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$client_access_token
                ]
            ]);
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
                    'transaction_detail' => 'NOT ENOUGH FUNDS',
                    'general_details' => DB::raw('IFNULL(general_details, 0) + 1')
                );
                GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$transaction_id,$client_details);
            }else{
                $updateTransactionEXt = array(
                    // "provider_request" =>json_encode($payload),
                    "mw_response" => json_encode(['retry' => 'jobs']),
                    'mw_request' => json_encode($requesttocient),
                    'client_response' => json_encode($client_response),
                    'transaction_detail' => 'FAILED',
                    'general_details' => DB::raw('IFNULL(general_details, 0) + 1')
                );
                GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$transaction_id,$client_details);
                throw new ModelNotFoundException('UNKNOWN_ERROR');
            }

        } catch (\Exception $e) {

            // $data = [
            //     "method_name" => "jobs",
            //     "provider_id" => 123,
            //     "request_data" => json_encode($this->data),
            //     "response_data" => json_encode($e->getMessage().' '.$e->getLine())
            // ];
            // DB::connection('savelog')->table('seamless_request_logs')->insert($data);
            $updateTransactionEXt = array(
                // "provider_request" =>json_encode(['gg' => 'gg']),
                'mw_request' => json_encode(['retry' => 'jobs']),
                'client_response' => json_encode($e->getMessage().' '.$e->getLine()),
                'transaction_detail' => 'FAILED',
            );
            GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$transaction_id,$client_details);
            throw new ModelNotFoundException($e->getMessage());

        }

        
    }
}
