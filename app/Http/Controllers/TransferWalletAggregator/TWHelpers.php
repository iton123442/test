<?php

namespace App\Http\Controllers\TransferWalletAggregator;

use App\Helpers\Helper;
use Carbon\CarbonPeriod;
use App\Models\GameTransactionMDB;

use DB;



/**
 * Transfer Wallet Helper
 * @author's note please add comment if you change something
 * 
 * 
 */
class TWHelpers {


     /**
     * GLOBAL
     * Client Info
     * @return [Object]
     * @param $[type] [<token, player_id, site_url, username>]
     * @param $[value] [<value to be searched>]
     * 
     */
    public static function getClientDetails($type = "", $value = "", $client_id=1, $providerfilter='all') {
        // DB::enableQueryLog();
        if ($type == 'token') {
            $where = 'where pst.player_token = "'.$value.'"';
        }
        if($providerfilter=='fachai'){
            if ($type == 'player_id') {
                $where = 'where '.$type.' = "'.$value.'" AND pst.status_id = 1 ORDER BY pst.token_id desc';
            }
        }else{
            if ($type == 'player_id') {
               $where = 'where '.$type.' = "'.$value.'"';
            }
        }
        if ($type == 'username') {
            $where = 'where p.username = "'.$value.'"';
        }
        if ($type == 'token_id') {
            $where = 'where pst.token_id = "'.$value.'"';
        }
        if ($type == 'ptw') {
            $where = 'where p.client_player_id = "'.$value.'"  AND  p.client_id = "'.$client_id.'"';
        }
        if($providerfilter=='fachai'){
            $filter = 'LIMIT 1';
        }else{
            // $result= $query->latest('token_id')->first();
            $filter = 'order by token_id desc LIMIT 1';
        }

        $filter = 'order by token_id desc LIMIT 1';

        $query = DB::select(
            'select 
                `p`.`client_id`,`c`.`country_code`,`p`.`player_id`, `p`.`email`, `p`.`client_player_id`,`p`.`language`,`p`.`balance`, `p`.`currency`, `p`.`test_player`, `p`.`username`,`p`.`created_at`,`pst`.`token_id`,`pst`.`player_token`,`tw_p`.`balance` as `tw_balance`,`c`.`client_url`,`c`.`default_currency`,`c`.`wallet_type`,`pst`.`status_id`,`p`.`display_name`,`op`.`client_api_key`,`op`.`client_code`,`op`.`client_access_token`,`op`.`operator_id`,`ce`.`player_details_url`,`ce`.`fund_transfer_url`,`ce`.`transaction_checker_url`,`p`.`created_at`, `c`.`connection_name` , `tw_p`.`tw_player_bal_id`
            from player_session_tokens pst 
            inner join players as p using(player_id)
            inner join tw_player_balance tw_p using (player_id)
            inner join clients as c using (client_id) 
            inner join client_endpoints as ce using (client_id) 
            inner join operator as op using (operator_id) '.$where.' '.$filter.'');
    
         $client_details = count($query);
         // Helper::saveLog('GET CLIENT LOG', 999, json_encode(DB::getQueryLog()), "TIME GET CLIENT");
         return $client_details > 0 ? $query[0] : null;
    }

    //use gameluanch to create player sesion token and player balacne
    public static function checkPlayerExist($client_id, $client_player_id, $username,  $email, $display_name,$token,$player_ip_address=false){
        $player = DB::table('players')
                    ->where('client_id',$client_id)
                    ->where('client_player_id',$client_player_id)
                    ->first();
        if($player){
            $player_account = DB::table('tw_player_balance')
                    ->where('player_id',$player->player_id)
                    ->first();
            if (!$player_account) {
                TWHelpers::createPlayerBalance($player->player_id);
            }

            return Helper::createPlayerSessionToken($player->player_id,$token,$player_ip_address);
        }
        else{
            $player_id = Helper::save_player($client_id,$client_player_id,$username,$email,$display_name);
            TWHelpers::createPlayerBalance($player_id);
            return Helper::createPlayerSessionToken($player_id,$token,$player_ip_address);
        }
    }

    public static function createPlayerBalance($player_id){
        $data = array(
            "player_id" => $player_id,
            "balance" => 0
        );
        return DB::table('tw_player_balance')->insertGetId($data);
    }

     public static function updateTWBalance($balance, $tw_player_bal_id) {
        $data = array(
            "balance" => $balance
        );
        return DB::table('tw_player_balance')->where('tw_player_bal_id',$tw_player_bal_id)->update($data);
     }


    public static function Client_SecurityHash($client_id, $access_token){
        $client_id = DB::table('clients')->where('client_id', $client_id)->first();
        if(!$client_id){ return 301;}
        if($client_id->status_id != 1 ){ return 202; }

        $operator = DB::table('operator')->where('operator_id', $client_id->operator_id)->first();
        if($operator == '' || $operator == null){ return 203; } 
        if(isset($operator->status_id) && $operator->status_id != 1 ){ return 204; }

        if($operator->client_access_token != $access_token){ return 302;}
        return true;
    }


     /**
     * Error Message
     * 
     */
    public static function getPTW_Message($code) {
        $message = [
         "200" => 'Success',
         "201" => 'No Session Found',
         "202" => 'Client Disabled',
         "301" => 'Client Not Found',
         "203" => 'Operator Not Found',
         "204" => 'Operator Disabled',
         "302" => 'Access Denied',
         "303" => 'Player Not Found',
         "304" => 'Invalid Data',
         "305" => 'Not Enough Balance',
         "306" => 'Trace ID Not Found',
         "307" => 'Game Not Found',
         "308" => 'Player Already Exist',
         "309" => 'Invalid Amount',
         "402" => 'Session Expired',
         "403" => 'Multiple Session',
         "404" => 'Parameter Error',
         "405" => 'Trace Id No Longer Available',
         "406" => 'Transaction Id Already Exist',
         "407" => 'Transaction ID Not Found',
         "400" => 'Services Not Available',
        ];
        if(array_key_exists($code, $message)){
            return $message[$code];
        }else{
            return 'Something went Wrong';
        }
    }


    //LIMIT AVAILABLE
    public static function getLimitAvailable($limit){
        $limits = [
            "10" => 10,
            "25" => 25,
            "50" => 50,
            "100" => 100,
            "500" => 500,
            "1000" => 1000,
        ];
        if(array_key_exists($limit, $limits)){
            return $limits[$limit];
        }else{
            return $limits["10"];
        }
    }

    //use gameluanch to create player sesion token and player balacne
    public static function createPlayerWalletAndIfExist($client_id, $client_player_id, $username,  $email, $display_name,$player_ip_address=false){
        $player = DB::table('players')
                    ->where('client_id',$client_id)
                    ->where('client_player_id',$client_player_id)
                    ->first();
        $player_token = substr("abcdefghijklmnopqrstuvwxyz1234567890", mt_rand(0, 25), 1).substr(md5(time()), 1);
        if($player){
            $player_account = DB::table('tw_player_balance')
                    ->where('player_id',$player->player_id)
                    ->first();
            Helper::createPlayerSessionToken($player->player_id,$player_token,$player_ip_address);
            if (!$player_account) {
                TWHelpers::createPlayerBalance($player->player_id);
                return $player->player_id;
            }
            return false;// already exist

        } else {
           
            $player_id = Helper::save_player($client_id,$client_player_id,$username,$email,$display_name);
            TWHelpers::createPlayerBalance($player_id);
            Helper::createPlayerSessionToken($player_id,$player_token,$player_ip_address);
            return $player_id;
        }
        
    }

    public static function getPlayerDetails($type = "", $value = "", $client_id = false){

        if ($type == 'ptw') {
            $where = 'where p.client_player_id = "'.$value.'"  AND  p.client_id = '.$client_id.' ';
        }

        if ($type == 'player_id') {
            $where = 'where p.player_id = '.$value.' ';
        }
        $query = DB::select(
            'select 
                `p`.`client_id`,`c`.`country_code`,`p`.`player_id`, `p`.`email`, `p`.`client_player_id`,`p`.`language`,`p`.`balance`, `p`.`currency`, `p`.`test_player`, `p`.`username`,`p`.`created_at`,`tw_p`.`balance` as `tw_balance`,`c`.`client_url`,`c`.`default_currency`,`c`.`wallet_type`,`p`.`display_name`,`op`.`client_api_key`,`op`.`client_code`,`op`.`client_access_token`,`op`.`operator_id`,`p`.`created_at`, `c`.`connection_name` , `tw_p`.`tw_player_bal_id`
            from players p 
            inner join tw_player_balance tw_p using (player_id)
            inner join clients as c using (client_id) 
            inner join operator as op using (operator_id) '.$where.' ');
    
         $client_details = count($query);
         return $client_details > 0 ? $query[0] : null;
    }

    public static function createTWPlayerAccounts($data){
       return DB::table('tw_player_accounts')->insertGetId($data);
    }

    public static function createTWPlayerAccountsRequestLogs($data){
       return DB::table('tw_player_account_request_logs')->insertGetId($data);
    }

    public static function updatePlayerAccount($data, $tw_account_id){
        return DB::table('tw_player_accounts')->where('tw_account_id',$tw_account_id)->update($data);
    }

    public static function updateTWPlayerAccountsRequestLogs($data, $tw_log_id){
        return DB::table('tw_player_account_request_logs')->where('tw_log_id',$tw_log_id)->update($data);
    }

    public static function idenpotencyTable($provider_trans_id){
		return DB::select("INSERT INTO  tw_player_transaction_idom (tw_transaction_id) VALUES (".$provider_trans_id.")");
	}

    public static function multiplePartition($start,$end){
		$period = CarbonPeriod::create(date("Y-m-d", strtotime($start)), date("Y-m-d", strtotime($end)) );
        $partition_date = '';
        foreach($period as $pdate){
            if($pdate->format("Y-m-d") == date("Y-m-d", strtotime($start))){
                $partition_date .= "p".$pdate->format("Ymd");
            }else{
                $partition_date .= ",p".$pdate->format("Ymd");
            }
        }
        return  "partition ($partition_date)";
	}


    public static function getPlayerSessionDetails($type = "", $value = "", $client_id=1, $providerfilter='all') {
        // DB::enableQueryLog();
        if ($type == 'token') {
            $where = 'where pst.player_token = "'.$value.'"';
        }
        $filter = 'order by token_id desc LIMIT 1';
        $query = DB::select('select 
        `c`.`default_currency`,
        `p`.`client_player_id`,
        `p`.`email`,
        `p`.`player_id`,
        `tw`.`balance`,
        `tw`.`tw_player_bal_id`,
        `op`.`status_id`
        from (select player_id from player_session_tokens pst '.$where.' '.$filter.') pst 
        inner join players as p using(player_id) 
        inner join tw_player_balance as tw using(player_id)
        inner join clients as c using (client_id)
        inner join operator as op using (operator_id)');
        $client_details = count($query);
        // Helper::saveLog('GET CLIENT LOG', 999, json_encode(DB::getQueryLog()), "TIME GET CLIENT");
        return $client_details > 0 ? $query[0] : null;
    }

    public static function getPlayerBalance($player_id) {
        $query = DB::select('SELECT * FROM tw_player_balance where player_id = '. $player_id);
        $client_details = count($query);
        // Helper::saveLog('GET CLIENT LOG', 999, json_encode(DB::getQueryLog()), "TIME GET CLIENT");
        return $client_details > 0 ? $query[0] : null;
    }

    public static function getPlayerBalanceUsingTwID($tw_player_bal_id) {
        $query = DB::select('SELECT * FROM tw_player_balance where tw_player_bal_id = '. $tw_player_bal_id);
        $client_details = count($query);
        // Helper::saveLog('GET CLIENT LOG', 999, json_encode(DB::getQueryLog()), "TIME GET CLIENT");
        return $client_details > 0 ? $query[0] : null;
    }


    public static function playerDetails($type = "", $value = "", $client_id = false){

        if ($type == 'ptw') {
            $where = 'where p.client_player_id = "'.$value.'"  AND  p.client_id = '.$client_id.' ';
        }

        if ($type == 'player_id') {
            $where = 'where p.player_id = '.$value.' ';
        }
        $query = DB::select(
            'select 
                `p`.`client_id`,`c`.`country_code`,`p`.`player_id`, `p`.`email`, `p`.`client_player_id`,`p`.`language`,`p`.`balance`, `p`.`currency`, `p`.`test_player`, `p`.`username`,`p`.`created_at`,`c`.`client_url`,`c`.`default_currency`,`c`.`wallet_type`,`p`.`display_name`,`op`.`client_api_key`,`op`.`client_code`,`op`.`client_access_token`,`op`.`operator_id`,`p`.`created_at`, `c`.`connection_name`, `op`.`status_id`
            from players p 
            inner join clients as c using (client_id) 
            inner join operator as op using (operator_id) '.$where.' ');
         $client_details = count($query);
         return $client_details > 0 ? $query[0] : null;
    }

    public static function createTWPlayerAccountsMDB($data, $client_details){
        $connection = GameTransactionMDB::getAvailableConnection($client_details->connection_name);
        if($connection != null){
            Helper::saveLog('createTWPlayerAccountsMDB', 789, json_encode($connection), "createTWPlayerAccountsMDB");
            return DB::connection($connection["connection_name"])->table($connection['db_list'][1].".tw_player_accounts")->insertGetId($data);
        }else{
            return null;
        }
        // return DB::table('tw_player_accounts')->insertGetId($data);
    }
 
    public static function updatePlayerAccountMDB($data, $tw_account_id, $client_details){
        $connection = GameTransactionMDB::getAvailableConnection($client_details->connection_name);
        if($connection != null){
            return DB::connection($connection["connection_name"])->table($connection['db_list'][1].".tw_player_accounts")->where('tw_account_id',$tw_account_id)->update($data);
        }else{
            return null;
        }
        // return DB::table('tw_player_accounts')->where('tw_account_id',$tw_account_id)->update($data);
    }

    public static function createTWPlayerAccountsRequestLogsMDB($data, $client_details){
        $connection = GameTransactionMDB::getAvailableConnection($client_details->connection_name);
        if($connection != null){
            Helper::saveLog('createTWPlayerAccountsRequestLogsMDB', 789, json_encode($connection), "createTWPlayerAccountsRequestLogsMDB");
            return DB::connection($connection["connection_name"])->table($connection['db_list'][1].".tw_player_account_request_logs")->insertGetId($data);
        }else{
            return null;
        }
        // return DB::table('tw_player_account_request_logs')->insertGetId($data);
    }
 
    public static function updateTWPlayerAccountsRequestLogsMDB($data, $tw_log_id, $client_details){
        $connection = GameTransactionMDB::getAvailableConnection($client_details->connection_name);
        if($connection != null){
            return DB::connection($connection["connection_name"])->table($connection['db_list'][1].".tw_player_account_request_logs")->where('tw_log_id',$tw_log_id)->update($data);
        }else{
            return null;
        }
        // return DB::table('tw_player_account_request_logs')->where('tw_log_id',$tw_log_id)->update($data);
    }

}

?>