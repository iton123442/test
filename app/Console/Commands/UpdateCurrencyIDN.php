<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\IDNPokerController;
class UpdateCurrencyIDN extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:currencyIDN';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This is to update the currency every thursday';

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
     * @return mixed
     */
    public function handle()
    {
        IDNPokerController::updateCurrencyRate();
    }
}
 