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
        $data = $request->all();
        $method = $data['method'];
        $client_details = ProviderHelper::getClientDetails('token', $data['params']['token']);
        if($client_details == null){
            $response = [
                "jsonrpc" =>  '2.0',
                "error" => [
                        'code' => '',
                        'message' => 'Server Error',
                        'data' => [
                            'code' => 15001,
                            'message' => 'Authentication failed',
                        ],
                    ],
                "id" => $data['id'],
            ];
            ProviderHelper::saveLogWithExeption('Nolimit Gameluanch client details error', $this->provider_db_id, json_encode($data), $response );
            return response($response,200)
                ->header('Content-Type', 'application/json');
        }
        if($method == 'wallet.validate-token'){
            $response = $this->Auth($request->all(), $client_details);
			return response($response,200)
                ->header('Content-Type', 'application/json');	   
        }if($method == 'wallet.balance'){
            $response = $this->Balance($request->all(), $client_details);
            return response($response,200)
                ->header('Content-Type', 'application/json');   
        }if($method == 'wallet.withdraw'){
             $response = $this->Withdraw($request->all(), $client_details);
            return response($response,200)
                ->header('Content-Type', 'application/json');   
        }if($method == 'wallet.deposit'){
             $response = $this->Deposit($request->all(), $client_details);
            return response($response,200)
                ->header('Content-Type', 'application/json');   
        }

   }//End Function
   public function Auth($request, $client_details)
   {
    $data = $request;
    if($this->operator_key != $data['params']['identification']['key']){
        $response = [
            "jsonrpc" =>  '2.0',
            "error" => [
                    'code' => '',
                    'message' => 'Server Error',
                    'data' => [
                        'code' => 15001,
                        'message' => 'Authentication failed',
                    ],
                ],
            "id" => $data['id'],
        ];
        ProviderHelper::saveLogWithExeption('Nolimit Gameluanch operator key error', $this->provider_db_id, json_encode($data), $response );
        return $response;
    }
    $response = [
        "jsonrpc" => "2.0",
        "result" => [
            "userId" => $client_details->player_id,
            "username" => $client_details->username,
            "balance" => [
                "amount" => $client_details->balance,
                "currency" => $client_details->default_currency,
            ],
        ],
        "id" => $data['id']
    ];   
    ProviderHelper::saveLogWithExeption('Nolimit validate end', $this->provider_db_id, json_encode($data), $response );
    return $response;
   }// Validate function
    public function Balance($request, $client_details){
      $data = $request; 
      ProviderHelper::saveLogWithExeption('Nolimit getbalance', $this->provider_db_id, json_encode($data), 'ENDPOINT HIT');
      $response = [
                    "jsonrpc" => "2.0",
                    "result" => [
                        "balance" => [
                            "amount" => $client_details->balance,
                            "currency" => $client_details->default_currency,
                        ],
                    ],
                "id" => $data['id']
            ];
            return $response;   
    }//End of Balance
     public function Withdraw($request, $client_details){

     }//End Bet

      public function Balance($request, $client_details){

      }// End Win
}//End Class controller
