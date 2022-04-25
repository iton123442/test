<?php

namespace App\Jobs;

use App\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\GameTransactionMDB;
 

class CreateTransLog extends Job implements ShouldQueue
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

        $client_details = $payload['connection_name'];
        $createGameLog = $payload['column'];
        GameTransactionMDB::createGametransactionLogCCMD($createGameLog,$client_details);
        
    }
}
