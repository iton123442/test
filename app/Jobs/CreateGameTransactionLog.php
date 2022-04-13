<?php

namespace App\Jobs;

use App\Models\GameTransactionMDB;

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
        $client_details = $payload['client_details'];
        if($payload["type"] == "create") {
            $createGameLog = $payload['column'];
            GameTransactionMDB::createGametransactionLog($createGameLog,$client_details);
        }

        if($payload["type"] == "update") {
            $client_details = $payload['client_details'];
            $game_trans_ext_id = $payload["game_trans_ext_id"];
            $updateDAta = $payload['column'];
            GameTransactionMDB::updateGametransactionLog($updateDAta,$game_trans_ext_id,false,$client_details);
        }
        
    }
}
