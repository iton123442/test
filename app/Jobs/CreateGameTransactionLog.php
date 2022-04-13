<?php

namespace App\Jobs;

use App\Models\GameTransactionMDB;
use App\Helpers\ClientRequestHelper;

class CreateGameTransactionLog extends Job
{
     /**
     * Create a new job instance.
     *
     * @return void
     */

    private $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
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
        //
        $payload = $this->data;
        
        if($payload["type"] == "create") {
            $client_details = $payload['client_details'];
            $createGameLog = $payload['column'];
            GameTransactionMDB::createGametransactionLog($createGameLog,$client_details);
        }

        if($payload["type"] == "update") {
            $client_details = $payload['client_details'];
            $game_trans_ext_id = $payload["game_trans_ext_id"];
            $updateDAta = $payload['column'];
            GameTransactionMDB::updateGametransactionLog($updateDAta,$game_trans_ext_id,false,$client_details);
        }

        if($payload["type"] == "cutcall_update") {
            $connection = $payload['connection'];
            $game_trans_ext_id = $payload["game_trans_ext_id"];
            $updateDAta = $payload['column'];
            ClientRequestHelper::updateGametransactionLogEXTCCMD($updateDAta, $game_trans_ext_id, $connection);
        }
        
    }
}
