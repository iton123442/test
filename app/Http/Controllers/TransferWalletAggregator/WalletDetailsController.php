<?php

namespace App\Http\Controllers\TransferWalletAggregator;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PureTransferWallet\PureTransferWalletHelper;
use App\Http\Controllers\TransferWalletAggregator\TWHelpers;
use Illuminate\Http\Request;
use App\Helpers\ProviderHelper;
use App\Helpers\TransferWalletHelper;
use App\Helpers\Helper;
use App\Models\GameTransactionMDB;
use DB;

class WalletDetailsController extends Controller
{
    public function __construct(){

        $this->middleware('oauth', ['except' => []]);
        /*$this->middleware('authorize:' . __CLASS__, ['except' => ['index', 'store']]);*/
    }

    public function createPlayerBalance(Request $request){
        if(!$request->has('access_token') || !$request->has('client_id') || !$request->has('client_player_id') || !$request->has('username')){
            $mw_response = ["data" => null,"status" => ["code" => "404","message" => TWHelpers::getPTW_Message(404)]];
            return $mw_response;
        }
        Helper::saveLog( $request->client_player_id." TW Logs" , 5 , json_encode($request->all()), "CREATE PLAYER HIT");
        $security = TWHelpers::Client_SecurityHash($request->client_id,$request->access_token);
        if($security !== true){
           $mw_response = ["data" => null,"status" => ["code" => $security ,"message" => TWHelpers::getPTW_Message($security)]];
           return $mw_response;
        }
        $email = ''; $display_name =''; $ip_address = '';
        if ($request->has('email')) {
            $email = $request->email;
        }
        if ($request->has('display_name')) {
            $display_name = $request->display_name;
        }
          if ($request->has('ip_address')) {
            $ip_address = $request->ip_address;
        }
        
        $player_id = TWHelpers::createPlayerWalletAndIfExist($request->client_id,$request->client_player_id,$request->username, $email,$display_name,$ip_address);
        if ($player_id) {
            $getPlayerDetails = TWHelpers::getPlayerDetails('player_id' ,$player_id);
            $mw_response = [
                "data" => [
                    "client_player_id" => $getPlayerDetails->client_player_id,
                    "balance" => (double)$getPlayerDetails->tw_balance
                ],
                "status" => [
                    "code" => 200,
                    "message" => TWHelpers::getPTW_Message(200)
                ]
            ];
        } else {
            $mw_response = ["data" => null,"status" => ["code" => "308" ,"message" => TWHelpers::getPTW_Message(308)]];
        }
        return $mw_response;
    }
  
    public function getPlayerBalance(Request $request){
        if(!$request->has('access_token') || !$request->has('client_id') || !$request->has('client_player_id') ){
            $mw_response = ["data" => null,"status" => ["code" => "404","message" => TWHelpers::getPTW_Message(404)]];
            return $mw_response;
        }
        Helper::saveLog( $request->client_player_id." TW Logs" , 5 , json_encode($request->all()), "PLAYER BALACNE HIT");
        $security = TWHelpers::Client_SecurityHash($request->client_id ,$request->access_token);
        if($security !== true){
           $mw_response = ["data" => null,"status" => ["code" => $security ,"message" => TWHelpers::getPTW_Message($security)]];
           return $mw_response;
        }

        // $client_details = ProviderHelper::getClientDetails('ptw', $request->client_player_id, $request->client_id);
        $getPlayerDetails = TWHelpers::getPlayerDetails('ptw' ,$request->client_player_id ,$request->client_id);
       
        if($getPlayerDetails == null){
            $mw_response = ["data" => null,"status" => ["code" => 303 ,"message" => TWHelpers::getPTW_Message(303)]];
            return $mw_response;
        }

        $mw_response = [
            "data" => [
                "client_player_id" => $getPlayerDetails->client_player_id,
                "balance" => (double)$getPlayerDetails->tw_balance
            ],
            "status" => [
                "code" => 200,
                "message" => TWHelpers::getPTW_Message(200)
            ]
        ]; 
        return $mw_response;
    }
    

    public function makeTransferWallerDeposit(Request $request){

        if(!$request->has('access_token') || !$request->has('client_id') || !$request->has('client_player_id') || !$request->has('amount') || !$request->has('transaction_id') ){
            $mw_response = ["data" => null,"status" => ["code" => "404","message" => TWHelpers::getPTW_Message(404)]];
            return $mw_response;
        }
        Helper::saveLog( $request->client_player_id." TW Logs" , 5 , json_encode($request->all()), "DEPOSIT HIT");
        $data = [
            "client_transaction_id" => $request->client_id."_".$request->transaction_id, //UNIQUE TRANSACTION
            "client_request" => json_encode($request->all()),
            "wallet_type" => 1
        ];
        $security = TWHelpers::Client_SecurityHash($request->client_id ,$request->access_token);
        if($security !== true){
            $mw_response = ["data" => null,"status" => ["code" => $security ,"message" => TWHelpers::getPTW_Message($security)]];
            $data["mw_response"] = json_encode($mw_response);
            $data["status_code"]= $security;
            TWHelpers::createTWPlayerAccountsRequestLogs($data);
            return $mw_response;
        }
        $getPlayerDetails = TWHelpers::getPlayerDetails('ptw' ,$request->client_player_id ,$request->client_id);

        if($getPlayerDetails == null){
            $mw_response = ["data" => null,"status" => ["code" => 303 ,"message" => TWHelpers::getPTW_Message(303)]];
            $data["mw_response"] = json_encode($mw_response);
            $data["status_code"] = "303";
            TWHelpers::createTWPlayerAccountsRequestLogs($data);
            return $mw_response;
        }
        if(!is_numeric($request->amount) || $request->amount < 0){
            $mw_response = ["data" => null,"status" => ["code" => 309 ,"message" => TWHelpers::getPTW_Message(309)]];
            $data["mw_response"] = json_encode($mw_response);
            $data["status_code"] = "309";
            TWHelpers::createTWPlayerAccountsRequestLogs($data);
            return $mw_response;
        }
       
        //create depost accounts
        try {
            $data_accounts = [
                "player_id" => $getPlayerDetails->player_id,
                "type" => 1,
                "amount" => $request->amount,
                "client_id" => $getPlayerDetails->client_id,
                "operator_id" => $getPlayerDetails->operator_id,
                "client_transaction_id" => $getPlayerDetails->client_id."_".$request->transaction_id //UNIQUE TRANSACTION
            ];
            $account_id = TWHelpers::createTWPlayerAccounts($data_accounts);
        } catch (\Exception $e) {
            $mw_response = ["data" => null,"status" => ["code" => 406 ,"message" => TWHelpers::getPTW_Message(406)]];
            $data["mw_response"] = json_encode($mw_response);
            $data["status_code"] = "406";
            TWHelpers::createTWPlayerAccountsRequestLogs($data);
            return $mw_response;
        }

        //update player account balance
        $balance = $getPlayerDetails->tw_balance + $request->amount;
        TWHelpers::updateTWBalance($balance, $getPlayerDetails->tw_player_bal_id);

        $mw_response = [
            "data" => [
                "client_player_id" => $getPlayerDetails->client_player_id,
                "balance" => (double)$balance,
                "transaction_id" => $request->transaction_id
            ],
            "status" => [
                "code" => 200,
                "message" => TWHelpers::getPTW_Message(200)
            ]
        ];

        $general_details = [
            "data" => [
                "client_player_id" => $getPlayerDetails->client_player_id,
                "transaction_id" => $request->transaction_id,
                "after_balance" =>  $balance,
                "before_balance" => $getPlayerDetails->tw_balance,
            ],
        ];

        $data = [
            "client_transaction_id" => $getPlayerDetails->client_id."_".$request->transaction_id, //UNIQUE TRANSACTION
            "client_request" => json_encode($request->all()),
            "mw_response" => json_encode($mw_response),
            "wallet_type" => 1,
            "status_code" => "200",
            "general_details" => json_encode($general_details)
        ];
        TWHelpers::createTWPlayerAccountsRequestLogs($data);
        
        return $mw_response;
    }

    public function makeTransferWallerWithdraw(Request $request){

        if(!$request->has('access_token') || !$request->has('client_id') || !$request->has('client_player_id') || !$request->has('amount') || !$request->has('transaction_id') ){
            $mw_response = ["data" => null,"status" => ["code" => "404","message" => TWHelpers::getPTW_Message(404)]];
            return $mw_response;
        }
        Helper::saveLog( $request->client_player_id." TW Logs" , 5 , json_encode($request->all()), "WITHDRAW HIT");
        $data = [
            "client_transaction_id" => $request->client_id."_".$request->transaction_id, //UNIQUE TRANSACTION
            "client_request" => json_encode($request->all()),
            "wallet_type" => 2
        ];
        $security = TWHelpers::Client_SecurityHash($request->client_id ,$request->access_token);
        if($security !== true){
            $mw_response = ["data" => null,"status" => ["code" => $security ,"message" => TWHelpers::getPTW_Message($security)]];
            $data["mw_response"] = json_encode($mw_response);
            $data["status_code"]= $security;
            TWHelpers::createTWPlayerAccountsRequestLogs($data);
            return $mw_response;
        }
        $getPlayerDetails = TWHelpers::getPlayerDetails('ptw' ,$request->client_player_id ,$request->client_id);
        if($getPlayerDetails == null){
            $mw_response = ["data" => null,"status" => ["code" => 303 ,"message" => TWHelpers::getPTW_Message(303)]];
            $data["mw_response"] = json_encode($mw_response);
            $data["status_code"] = "303";
            TWHelpers::createTWPlayerAccountsRequestLogs($data);
            return $mw_response;
        }
        if(!is_numeric($request->amount) || $request->amount < 0){
            $mw_response = ["data" => null,"status" => ["code" => 309 ,"message" => TWHelpers::getPTW_Message(309)]];
            $data["mw_response"] = json_encode($mw_response);
            $data["status_code"] = "309";
            TWHelpers::createTWPlayerAccountsRequestLogs($data);
            return $mw_response;
        }
        //if 0 amount then withraw all
        $amountToWithdraw = $request->amount == 0 ? $getPlayerDetails->tw_balance : $request->amount;
        if ($amountToWithdraw > $getPlayerDetails->tw_balance ) {
            $mw_response = ["data" => null,"status" => ["code" => 305 ,"message" => TWHelpers::getPTW_Message(305)]];
            $data["mw_response"] = json_encode($mw_response);
            $data["status_code"] = "309";
            TWHelpers::createTWPlayerAccountsRequestLogs($data);
            return $mw_response;
        }
         //create withdraw accounts
        try {
            $data_accounts = [
                "player_id" => $getPlayerDetails->player_id,
                "type" => 2,
                "amount" => $amountToWithdraw,
                "client_id" => $getPlayerDetails->client_id,
                "operator_id" => $getPlayerDetails->operator_id,
                "client_transaction_id" => $getPlayerDetails->client_id."_".$request->transaction_id //UNIQUE TRANSACTION
            ];
            $account_id = TWHelpers::createTWPlayerAccounts($data_accounts);
        } catch (\Exception $e) {
            $mw_response = ["data" => null,"status" => ["code" => 406 ,"message" => TWHelpers::getPTW_Message(406)]];
            $data["mw_response"] = json_encode($mw_response);
            $data["status_code"] = "406";
            TWHelpers::createTWPlayerAccountsRequestLogs($data);
            return $mw_response;
        }

        //update player account balance
        $balance = $getPlayerDetails->tw_balance - $amountToWithdraw;
        TWHelpers::updateTWBalance($balance, $getPlayerDetails->tw_player_bal_id);

        $mw_response = [
            "data" => [
                "client_player_id" => $getPlayerDetails->client_player_id,
                "balance" => (double)$balance,
                "transaction_id" => $request->transaction_id
            ],
            "status" => [
                "code" => 200,
                "message" => TWHelpers::getPTW_Message(200)
            ]
        ];

        $general_details = [
            "data" => [
                "client_player_id" => $getPlayerDetails->client_player_id,
                "transaction_id" => $request->transaction_id,
                "after_balance" => (double)$balance,
                "before_balance" => $getPlayerDetails->tw_balance,
            ],
        ];

        $data = [
            "client_transaction_id" => $request->client_id."_".$request->transaction_id, //UNIQUE TRANSACTION
            "client_request" => json_encode($request->all()),
            "mw_response" => json_encode($mw_response),
            "wallet_type" => 2,
            "status_code" => "200",
            "general_details" => json_encode($general_details)
        ];
        TWHelpers::createTWPlayerAccountsRequestLogs($data);
        return $mw_response;
    }

    // @return
    // client_id
    // player id
    // round id
    // bet amount 
    // pay amount
    // game name
    // game_id
    // game_code
    // sub provider
    // status (win lose refund failed progressing)
    // create_at
    public function getBetHistory(Request $request)
    {
        // if($request->has('client_id')&&$request->has('player_id')&&$request->has('dateStart')&&$request->has('dateEnd')){
        Helper::saveLog('TW Logs', 5 , json_encode($request->all()), "BET HISTORY HIT");
        if(!$request->has('access_token') || !$request->has('client_id') || !$request->has('date') || !$request->has('page') || !$request->has('limit') ){
            $mw_response = ["data" => null,"status" => ["code" => "404","message" => TWHelpers::getPTW_Message(404)]];
            Helper::saveLog('TW Logs', 5 , json_encode($request->all()), $mw_response);
            return $mw_response;
        }

        $security = TWHelpers::Client_SecurityHash($request->client_id ,$request->access_token);
        if($security !== true){
           $mw_response = ["data" => null,"status" => ["code" => $security ,"message" => TWHelpers::getPTW_Message($security)]];

           Helper::saveLog('TW Logs', 5 , json_encode($request->all()), $mw_response);
           return $mw_response;
        }

        $from = date("Y-m-d H:i:s", strtotime($request->date));
        $to = date("Y-m-d H:i:s", strtotime($request->date." 23:59:59"));
       
        if($request->has('start_time')){ 
            $from = date("Y-m-d H:i:s", strtotime($request->date." ".$request->start_time));
        }
        if($request->has('end_time')){ 
            $to = date("Y-m-d H:i:s", strtotime($request->date." ".$request->end_time));
        }
       
        $partition = TWHelpers::multiplePartition($from,$to);
        $and_player = "and player_id  = (SELECT player_id FROM players WHERE client_id = ".$request->client_id." AND client_player_id = '".$request->client_player_id."' LIMIT 1)  ";
        if ($request->client_player_id == "all") {
            $and_player = '';
        }
        $client_details = DB::select("select * from clients c where client_id = ". $request->client_id)[0];

        $connection = config("serverlist.server_list.".$client_details->connection_name.".connection_name");

        $status = GameTransactionMDB::checkDBConnection($connection);

        if ( ($connection != null) && $status) {

            try {
                $connection = config("serverlist.server_list.".$client_details->connection_name);

                if ($connection["connection_name"] == "mysql" || $connection["connection_name"] == "server1" ) {
                    //default
                    $connection["TG_GameInfo"] = $connection["db_list"][1];
                    $connection["TG_PlayerInfo"] = $connection["db_list"][1];
                    $connection["TG_ClientInfo"] = $connection["db_list"][1];

                    //trans_id
                    // $connection["Trans_DB"] = "summary_report";
                    // $connection["Trans_Table"] = "trans_start_end";
                    // $transDate = "tse_date = '".$request->date."' ";
                } else {
                    $connection["TG_GameInfo"] = "TG_GameInfo";
                    $connection["TG_PlayerInfo"] = "TG_PlayerInfo";
                    $connection["TG_ClientInfo"] = "TG_ClientInfo";

                    //trans id
                    // $connection["Trans_DB"] = $connection["db_list"][1];
                    // $connection["Trans_Table"] = "trans_id_tracer";
                    // $transDate = "trans_id_tracer = '".$request->date."' ";
                }

                // api -test db
                // $get_trans_id = DB::connection( $connection["connection_name"] )->select('select min(start_id) start_id, max(end_id) end_id from '.$connection["Trans_DB"].'.'.$connection["Trans_Table"].' where '.$transDate.' limit 1 ')[0];

                //return if not empty
                // if (count($get_trans_id) == 0) {
                //     $details = ["data" => null,"status" => ["code" => "200","message" => TWHelpers::getPTW_Message(200)]];
                //     Helper::saveLog('TW Logs', 5 , json_encode($request->all()), $details);
                //     return response()->json($details); 
                // }
              
                $total_data = DB::connection( $connection["connection_name"] )->select("
                    select 
                    count(game_trans_id) total
                    from ".$connection["db_list"][1].".game_transactions $partition c 
                    where convert_tz(c.created_at,'+00:00', '+08:00') BETWEEN '".$from."' AND '".$to."' AND c.client_id = ".$request->client_id."  ".$and_player.";
                    ")[0];
              

                $query = "
                    select 
                    game_trans_id,
                    (select client_name from ".$connection["TG_ClientInfo"].".clients where client_id = c.client_id) as client_name,
                    (select client_player_id from ".$connection["TG_PlayerInfo"].".players where player_id = c.player_id) as client_player_id,
                    (select sub_provider_name from ".$connection["TG_GameInfo"].".sub_providers where sub_provider_id = (SELECT sub_provider_id FROM ".$connection["TG_GameInfo"].".games where game_id = c.game_id) ) as provider_name,
                    (SELECT game_name FROM ".$connection["TG_GameInfo"].".games where game_id = c.game_id) game_name,
                    (SELECT game_code FROM ".$connection["TG_GameInfo"].".games where game_id = c.game_id) game_code,
                    bet_amount,
                    pay_amount,
                    bet_amount - pay_amount as income,
                    case
                        when win = 1 then 'win'
                        when win = 2 then 'failed'
                        when win = 0 then 'lose'
                        when win = 5 then 'progressing'
                        when win = 4 then 'refunded'
                    end as status,
                    convert_tz(c.created_at,'+00:00', '+08:00') created_at
                    from ".$connection["db_list"][1].".game_transactions ".$partition." c 
                    where convert_tz(c.created_at,'+00:00', '+08:00') BETWEEN '".$from."' AND '".$to."' AND c.client_id = ".$request->client_id." ".$and_player."
                    order by game_trans_id desc
                    limit ".$request->page.", ".TWHelpers::getLimitAvailable($request->limit).";
                ";
                $details = DB::connection( $connection["connection_name"] )->select($query);
                if (count($details) == 0) {
                    $details = ["data" => null,"status" => ["code" => "200","message" => TWHelpers::getPTW_Message(200)]];
                    Helper::saveLog('TW Logs', 5 , json_encode($request->all()), $query);
                    return response()->json($details); 
                }

                $data = array();//this is to add data and reformat the $table object to datatables standard array
                foreach($details as $datas){
                    $datatopass['game_trans_id']=$datas->game_trans_id;
                    $datatopass['client_name']=$datas->client_name;
                    $datatopass['client_player_id']=$datas->client_player_id;
                    $datatopass['provider_name']=$datas->provider_name;
                    $datatopass['game_name']=$datas->game_name;
                    $datatopass['game_code']=$datas->game_code;
                    $datatopass['bet_amount']=$datas->bet_amount;
                    $datatopass['pay_amount']=$datas->pay_amount;
                    $datatopass['income']=$datas->income;
                    $datatopass['status']=$datas->status;
                    $datatopass['created_at']=$datas->created_at;
                    $data[]=$datatopass;
                }
                $mw_response = [
                    "data" => $data,
                    "Total" => $total_data->total,
                    "Page" => $request->page,
                    "Limit" =>  TWHelpers::getLimitAvailable($request->limit),
                    "status" => [
                        "code" => 200,
                        "message" => TWHelpers::getPTW_Message(200)
                    ]
                ];
                Helper::saveLog('TW Logs', 5 , json_encode($request->all()), $query);
                return response()->json($mw_response); 
            } catch (\Exception $e) {
                $mw_response = ["data" => null,"status" => ["code" => "400","message" => TWHelpers::getPTW_Message(400)]];
                Helper::saveLog('TW Logs', 5 , json_encode($request->all()), $e->getMessage());
                return $mw_response;
            }
        } else {
            $mw_response = ["data" => null,"status" => ["code" => "400","message" => TWHelpers::getPTW_Message(400)]];
            Helper::saveLog('TW Logs', 5 , json_encode($request->all()), $mw_response);
            return $mw_response;
        }
    }

   
    public function checkTransactionDetails(Request $request)
    {
        // if($request->has('client_id')&&$request->has('player_id')&&$request->has('dateStart')&&$request->has('dateEnd')){
        Helper::saveLog('TW Logs', 5 , json_encode($request->all()), "TRANSACTION CHECKER HIT");
        if(!$request->has('access_token') || !$request->has('client_id') || !$request->has('client_player_id') ){
            $mw_response = ["data" => null,"status" => ["code" => "404","message" => TWHelpers::getPTW_Message(404)]];
            Helper::saveLog('TW Logs', 5 , json_encode($request->all()), $mw_response);
            return $mw_response;
        }

        $security = TWHelpers::Client_SecurityHash($request->client_id ,$request->access_token);
        if($security !== true){
           $mw_response = ["data" => null,"status" => ["code" => $security ,"message" => TWHelpers::getPTW_Message($security)]];

           Helper::saveLog('TW Logs', 5 , json_encode($request->all()), $mw_response);
           return $mw_response;
        }
        
        $getPlayerDetails = TWHelpers::getPlayerDetails('ptw' ,$request->client_player_id ,$request->client_id);
        if($getPlayerDetails == null){
            $mw_response = ["data" => null,"status" => ["code" => 303 ,"message" => TWHelpers::getPTW_Message(303)]];
            return $mw_response;
        }

        if($request->has('reference_id') ){
            $reference_id = $request->reference_id;
        } else {
            $mw_response = ["data" => null,"status" => ["code" => "404","message" => TWHelpers::getPTW_Message(404)]];
            Helper::saveLog('TW Logs', 5 , json_encode($request->all()), $mw_response);
            return $mw_response;
        }

        
       
        // $client_details = DB::select("select * from clients c where client_id = ". $request->client_id)[0];
        // $connection = config("serverlist.server_list.".$client_details->connection_name.".connection_name");
        // $status = GameTransactionMDB::checkDBConnection($connection);
        // if ( ($connection != null) && $status) {

            try {
                // $connection = config("serverlist.server_list.".$client_details->connection_name);
                $client_transaction_id = $request->client_id."_".$reference_id;

                // if ($connection["connection_name"] == "mysql" || $connection["connection_name"] == "server1" ) {
                //     //default
                //     $connection["TG_GameInfo"] = $connection["db_list"][1];
                //     $connection["TG_PlayerInfo"] = $connection["db_list"][1];
                //     $connection["TG_ClientInfo"] = $connection["db_list"][1];
                // } else {
                //     $connection["TG_GameInfo"] = "TG_GameInfo";
                //     $connection["TG_PlayerInfo"] = "TG_PlayerInfo";
                //     $connection["TG_ClientInfo"] = "TG_ClientInfo";
                // }
              
                $details = DB::select("
                    SELECT 
                        tw_account_id as id,
                        amount,
                        case 
                            when type = 1 then 'deposit'
                            when type = 2 then 'withdraw'
                        end as type
                    FROM tw_player_accounts
                    where client_transaction_id = '".$client_transaction_id."' limit 1; ");
                if (count($details) == 0) {
                    $mw_response = [
                        "data" => null,
                        "status" => [
                            "code" => 407,
                            "message" => TWHelpers::getPTW_Message(407)
                        ]
                    ];
                    Helper::saveLog('TW Logs', 5 , json_encode($request->all()), $mw_response);
                    return response()->json($mw_response); 
                }

                $mw_response = [
                    "data" => [
                        "client_player_id" => $request->client_player_id,
                        "username" => $getPlayerDetails->username,
                        "amount" => (double)$details[0]->amount,
                        "transaction_id" => $request->reference_id,
                        "type" => $details[0]->type,
                        "balance" => $getPlayerDetails->tw_balance
                    ],
                    "status" => [
                        "code" => 200,
                        "message" => TWHelpers::getPTW_Message(200)
                    ]
                ];
                return response()->json($mw_response); 
            } catch (\Exception $e) {
                $mw_response = ["data" => null,"status" => ["code" => "400","message" => TWHelpers::getPTW_Message(400)]];
                Helper::saveLog('TW Logs', 5 , json_encode($request->all()), $e->getMessage());
                return $mw_response;
            }
        // } else {
        //     $mw_response = ["data" => null,"status" => ["code" => "400","message" => TWHelpers::getPTW_Message(400)]];
        //     Helper::saveLog('TW Logs', 5 , json_encode($request->all()), $mw_response);
        //     return $mw_response;
        // }
    }

    
}
