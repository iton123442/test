<?php


namespace App\Jobs;
use App\Models\GameTransactionMDB;
use App\Helpers\Helper;

class UpdateGametransactionJobs extends Job
{
     /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;
 
    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;
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
        $payload = $this->data;
        Helper::saveLog('PlayStarQueJobs', 55, json_encode($payload), $payload);
        $client_details = $payload['connection_name'];
        $createGameLog = $payload['column'];
        $game_transaction_id = $payload['game_trans_id'];
        GameTransactionMDB::updateGameTransactionCCMD($createGameLog, $game_transaction_id, $client_details);
    }
}
