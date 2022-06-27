<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use GuzzleHttp\Client;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransaction;
use App\Helpers\FreeSpinHelper;
use App\Models\GameTransactionMDB;
use App\Helpers\Game;
use Carbon\Carbon;
use App\Jobs\UpdateGametransactionJobs;
use App\Jobs\CreateGameTransactionLog;
use DB;

class NolimitCityController extends Controller

{
    
        public function __construct(){
        $this->provider_db_id = config('providerlinks.nolimit.provider_db_id');
        $this->api_url = config('providerlinks.nolimit.api_url');
        $this->operator =config('providerlinks.nolimit.operator');
        $this->operator_key = config('providerlinks.nolimit.operator_key');
        $this->groupid = config('providerlinks.nolimit.Group_ID');
    
        
    
    
      }
    public function index(Request $request)
    {
        
         
   }//End Function


}//End Class controller
