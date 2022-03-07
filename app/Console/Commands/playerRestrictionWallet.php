<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\IDNPokerController;

class playerRestrictionWallet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:playerRestriction';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'RUN PER MINUTE TO CHECK THE PLAYER RESTRICTION WITHDRAWAL PENDING';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        
        IDNPokerController::callRetryPlayerRestricted();
    }
}
