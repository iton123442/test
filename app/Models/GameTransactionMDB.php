<?php

namespace App\Models;

//use Illuminate\Database\Eloquent\Model;
use DB;
use App\Helpers\Helper;
class GameTransactionMDB 
{
    //
    private $default_connection = 'mysql';
    public static function checkGameTransactionExist($provider_transaction_id,$round_id=false,$type=false,$client_details){
        $connection = self::getAvailableConnection($client_details->connection_name);
        if($connection != null){
            $select = "SELECT game_transaction_type FROM ";
            $db = "{$connection['db_list'][0]}.game_transaction_ext ";
            if($type&&$round_id){
                $where =  "WHERE round_id = '{$round_id}' AND provider_trans_id='{$provider_transaction_id}' AND game_transaction_type = {$type}";
            }
            elseif($type&&$provider_transaction_id){
                $where =  "WHERE provider_trans_id='{$provider_transaction_id}' AND game_transaction_type={$type} limit 1";
            }
            else{
                $where =  "WHERE provider_trans_id='{$provider_transaction_id}' limit 1";
            }
            $game = DB::connection($connection["connection_name"])->select($select.$db.$where);
            return $game ? true :false;
        }else{
            return false;
        }
    }
    public static function getGameTransactionDataByProviderTransactionId($provider_transaction_id,$client_details){
        $connection = self::getAvailableConnection($client_details->connection_name);
        if($connection != null){
            Helper::saveLog('getGameTransactionDataByProviderTransactionId', 12, json_encode($connection), "createGametransaction");
            $select = "SELECT game_trans_ext_id,mw_response,round_id,game_trans_id,amount FROM ";
            $db = "{$connection['db_list'][0]}.game_transaction_ext ";
            $where = "where provider_trans_id='{$provider_transaction_id}' limit 1";
            $game = DB::connection($connection["connection_name"])->select($select.$db.$where);
            $cnt = count($game);
            if ($cnt > 0){
                return $game[0];
            }else{
                return self::checkAndGetFromOtherServer($select,$where,$connection["connection_name"],'gte');
            }
        }else{
            return null;
        }
        
    }
    public static function getGameTransactionDataByProviderTransactionIdAndEntryType($provider_transaction_id,$entry_type,$client_details){
        $connection = self::getAvailableConnection($client_details->connection_name);
        if($connection != null){
            // $game = DB::connection($connection["connection_name"])->select("SELECT game_trans_ext_id,mw_response,round_id,game_trans_id,amount
            // FROM {$connection['db_list'][0]}.game_transaction_ext
            // where provider_trans_id='{$provider_transaction_id}' AND entry_type={$entry_type} limit 1");
            // $cnt = count($game);
            // return $cnt > 0? $game[0]: null;
            $select = "SELECT game_trans_ext_id,mw_response,round_id,game_trans_id,amount FROM ";
            $db = "{$connection['db_list'][0]}.game_transaction_ext ";
            $where = "where provider_trans_id='{$provider_transaction_id}' AND game_transaction_type={$entry_type} limit 1";
            $game = DB::connection($connection["connection_name"])->select($select.$db.$where);
            $cnt = count($game);
            if ($cnt > 0){
                return $game[0];
            }else{
                return self::checkAndGetFromOtherServer($select,$where,$connection["connection_name"],'gte');
            }
        }else{
            return null;
        }
    }
    public static function getGameTransactionByTokenAndRoundId($player_token,$game_round,$client_details){
        $connection = self::getAvailableConnection($client_details->connection_name);
        if($connection != null){
            // $game = DB::connection($connection["connection_name"])->select("SELECT
            //                 entry_id,bet_amount,game_trans_id,pay_amount
            //                 FROM {$connection['db_list'][1]}.game_transactions g
            //                 WHERE token_id = '".$client_details->token_id."' and round_id = '".$game_round."'");
            $select = "SELECT entry_id,bet_amount,game_trans_id,pay_amount,income FROM ";
            $db = "{$connection['db_list'][1]}.game_transactions g ";
            $where = "WHERE token_id = '{$client_details->token_id}' and round_id = '{$game_round}'";
            $game = DB::connection($connection["connection_name"])->select($select.$db.$where);
            $cnt = count($game);
            if ($cnt > 0){
                return $game[0];
            }else{
                return self::checkAndGetFromOtherServer($select,$where,$connection["connection_name"],'gt');
            }
        }else{
            return null;
        }
    }
    public static function getGameTransactionByRoundId($game_round,$client_details){
        $connection = self::getAvailableConnection($client_details->connection_name);
        if($connection != null){
            // $game = DB::connection($connection["connection_name"])->select("SELECT
            //                 entry_id,bet_amount,game_trans_id,pay_amount,income
            //                 FROM {$connection['db_list'][1]}.game_transactions g
            //                 WHERE  round_id = '".$game_round."'");
            // $cnt = count($game);
            // return $cnt > 0? $game[0]: null;
            $select = "SELECT entry_id,bet_amount,game_trans_id,pay_amount,income FROM ";
            $db = "{$connection['db_list'][1]}.game_transactions g ";
            $where = "WHERE  round_id = '{$game_round}'";
            $game = DB::connection($connection["connection_name"])->select($select.$db.$where);
            $cnt = count($game);
            if ($cnt > 0){
                return $game[0];
            }else{
                return self::checkAndGetFromOtherServer($select,$where,$connection["connection_name"],'gt');
            }
        }else{
            return null;
        }
    }

    public static function checkAndGetFromOtherServer($select,$where,$connection_name,$type){
        $connection_list = config("serverlist.server_list");
        foreach($connection_list as $connection){
            $status = self::checkDBConnection($connection["connection_name"]);
            if($status && $connection["connection_name"] != $connection_name){
                Helper::saveLog('checkAndGetFromOtherServer', 12, json_encode($connection), "createGametransaction");
                switch($type){
                    case 'gte':
                        $db = "{$connection['db_list'][0]}.game_transaction_ext ";
                        break;
                    case 'gt':
                        $db = "{$connection['db_list'][1]}.game_transactions ";
                        break;
                }
                Helper::saveLog('checkAndGetFromOtherServer2', 12, json_encode($select.$db.$where), $connection["connection_name"]);
                $game = DB::connection($connection["connection_name"])->select($select.$db.$where);
                $cnt = count($game);
                if ($cnt > 0){
                    return $game[0];
                }
            }
        }
        return null;
    }



    public static function createGametransaction($data,$client_details){
        $connection = self::getAvailableConnection($client_details->connection_name);
        $data['operator_id'] = $client_details->operator_id;
        $data['client_id'] = $client_details->client_id;
        $data['player_id'] = $client_details->player_id;
        if($connection != null){
            Helper::saveLog('createGametransaction', 12, json_encode($connection), "createGametransaction");
            return DB::connection($connection["connection_name"])->table($connection['db_list'][1].'.game_transactions')->insertGetId($data);
        }else{
            return null;
        }
    }
    public static function updateGametransaction($data,$game_transaction_id,$client_details){
        $connection = self::getAvailableConnection($client_details->connection_name);
        if($connection != null){
            return DB::connection($connection["connection_name"])->table($connection['db_list'][1].'.game_transactions')->where('game_trans_id',$game_transaction_id)->update($data);
        }else{
            return null;
        }
    }
    public static function createGameTransactionExt($gametransactionext,$client_details){
        Helper::saveLog('createGameTransactionExt(BNG)', 12, json_encode("Hit the createGameTransactionExt"), "");
        $connection = self::getAvailableConnection($client_details->connection_name);
        if($connection != null){
            return DB::connection($connection["connection_name"])->table($connection['db_list'][0].".game_transaction_ext")->insertGetId($gametransactionext);
        }else{
            Helper::saveLog('createGameTransactionExt(BNG)', 12, json_encode("error or null connection"), "");
            return null;
        }
    }
    public static function updateGametransactionEXT($data,$game_trans_ext_id,$client_details){
        $connection = self::getAvailableConnection($client_details->connection_name);
        if($connection != null){
            return DB::connection($connection["connection_name"])->table($connection['db_list'][0].'.game_transaction_ext')->where('game_trans_ext_id',$game_trans_ext_id)->update($data);
        }else{
            return null;
        }
    }    
    /**
     * getAvailableConnection
     *
     * @param  string $pref_connection_name - preffered connection name but return another connection if not available
     * @return array return null if no connection available
     */
    public static function getAvailableConnection($pref_connection_name='mysql'){
        $pref_connection_name = isset($pref_connection_name)?$pref_connection_name:'mysql';
        if(self::checkDBConnection(config("serverlist.server_list.".$pref_connection_name.".connection_name"))){
            return config("serverlist.server_list.".$pref_connection_name);
        }else{
            $connection_list = config("serverlist.server_list");
            foreach($connection_list as $connection){
                $status = self::checkDBConnection($connection["connection_name"]);
                if($status){
                    return $connection;
                }
            }
            return null;
        }
    }    
    /**
     * checkDBConnection
     *
     * @param  string $connection_name default connection 'mysql'
     * @return bool
     */
    public static function checkDBConnection($connection_name='mysql'){
        try {
            DB::connection($connection_name)->getPdo();
            return true;
        } catch (\Exception $e){
            return false;
        }
    }

    /**
     * checkGameTransaction
     *
     * @param  client_details = [client_details]{object}
     * @param  identifier = [game_transaction]{string}
     * @param  entry_id = [false], [1],[2]{int}
     * @param  type = [transaction_id], [round_id],[game_transaction]{string}
     * @return [details]{object},[false]
     */
    public static  function findGameTransactionDetails($identifier, $type, $entry_id= false, $client_details) {
        $connection_name = $client_details->connection_name;
        $entry_type = "";
        if ($entry_id) {
            $entry_type = "AND gt.entry_id = ". $entry_id;
        }

        if ($type == 'transaction_id') {
            $where = 'where gt.provider_trans_id = "'.$identifier.'" '.$entry_type.'';
        } elseif ( $type == 'game_transaction') {
            $where = 'where gt.game_trans_id = '.$identifier;
        } elseif ( $type == "round_id") {
            $where = 'where gt.round_id = "'.$identifier.'" '.$entry_type.'';
        }
        try {
            $connection_name = $client_details->connection_name;
            $details = [];
            $connection = config("serverlist.server_list.".$client_details->connection_name.".connection_name");
            $status = self::checkDBConnection($connection);
            if ( ($connection != null) && $status) {
                $connection = config("serverlist.server_list.".$client_details->connection_name);
                $details = DB::connection( $connection["connection_name"])->select('select game_id,entry_id,bet_amount,game_trans_id,pay_amount,round_id,provider_trans_id,income,win,trans_status from `'.$connection['db_list'][1].'`.`game_transactions` gt '.$where.' LIMIT 1');
            }
            if ( !(count($details) > 0 )) {
                $connection_list = config("serverlist.server_list");
                foreach($connection_list as $key => $connection){
                    $status = self::checkDBConnection($connection["connection_name"]);
                    if($status && $connection_name != $connection["connection_name"]){
                        $data = DB::connection( $connection["connection_name"] )->select('select game_id,entry_id,bet_amount,game_trans_id,pay_amount,round_id,provider_trans_id,income,win from `'.$connection['db_list'][1].'`.`game_transactions` gt '.$where.' LIMIT 1');
                        if ( count($data) > 0  ) {
                            $connection_name = $key;
                            $details = $data;
                            break;
                        }
                    }
                }
            }
            $count = count($details);
            if ($count > 0 ) {
                $details[0]->connection_name = $connection_name;
            }
            return $count > 0 ? $details[0] : 'false';
        } catch (\Exception $e) {
            return 'false';
        }

    }
    

    /**
     * checkGameTransactionExtenstion
     *
     * @param  client_details = [client_details]{object}
     * @param  provider_identifier = [unique_transaction_provider]{string}
     * @param  game_transaction_type = [false], [1], [2],[3]{int}
     * @param  type = [transaction_id], [round_id],[game_transaction_ext_id],[game_trans_id]{string}
     * @return [details]{object},[false]
     */
    public  static function findGameExt($provider_identifier, $game_transaction_type=false, $type,$client_details)
    {
        $game_trans_type = '';
        if($game_transaction_type != false){
            $game_trans_type = "and gte.game_transaction_type = ". $game_transaction_type;
        }
        if ($type == 'transaction_id') {
            $where = 'where gte.provider_trans_id = "'.$provider_identifier.'" '.$game_trans_type;
        }
        if ($type == 'round_id') {
            $where = 'where gte.round_id = "' . $provider_identifier.'" '.$game_trans_type;
        }
        if ($type == 'game_transaction_ext_id') {
            $where = 'where gte.provider_trans_id = "' . $provider_identifier . '" ';
        }
        if ($type == 'game_trans_id') {
            $where = 'where gte.game_trans_id = "' . $provider_identifier . '" ';
        }
        try {
            $connection_name = $client_details->connection_name;
            $details = [];
            $connection = config("serverlist.server_list.".$client_details->connection_name.".connection_name");
            $status = self::checkDBConnection($connection);
            if ( ($connection != null) && $status) {
                $connection = config("serverlist.server_list.".$client_details->connection_name);
                $details = DB::connection($connection["connection_name"])->select('select * from `'.$connection['db_list'][0].'`.`game_transaction_ext` as gte ' . $where . ' LIMIT 1');
            }
            if ( !(count($details) > 0) )  {
                $connection_list = config("serverlist.server_list");
                foreach($connection_list as $key => $connection){
                    $status = self::checkDBConnection($connection["connection_name"]);
                    if($status && $connection_name != $connection["connection_name"]){
                        $data = DB::connection( $connection["connection_name"] )->select('select * from `'.$connection['db_list'][0].'`.`game_transaction_ext` as gte ' . $where . ' LIMIT 1');
                        if ( count($data) > 0  ) {
                            $connection_name = $key;// key is the client connection_name
                            $details = $data;
                            break;
                        }
                    }
                }
            }

            $count = count($details);
            if ($count > 0) {
                //apend on the details the connection which mean to rewrite the client_details
                $details[0]->connection_name = $connection_name;
            }
            return $count > 0 ? $details[0] : 'false';
        } catch (\Exception $e) {
            return 'false';
        }

    }

    /** 
     * Find all game ext extensions MDB
     */
    public  static function findGameExtAll($provider_identifier, $type,$client_details)
    {
        $connection_name = $client_details->connection_name;
        if ($type == 'all') {
            $where = 'where gte.provider_trans_id = "' . $provider_identifier  . '" AND gte.transaction_detail != "FAILED"';
        }
        if ($type == 'transaction_id') {
            $where = 'where gte.provider_trans_id = "' . $provider_identifier . '" ';
        }
        if ($type == 'round_id') {
            $where = 'where gte.round_id = "' . $provider_identifier . '" ';
        }
        if ($type == 'game_transaction_ext_id') {
            $where = 'where gte.provider_trans_id = "' . $provider_identifier . '"';
        }
        if ($type == 'game_trans_id') {
            $where = 'where gte.game_trans_id = "' . $provider_identifier . '"';

        }
        
        try {
            $details = [];
            $connection = self::getAvailableConnection($client_details->connection_name);
            if ($connection != null) {
                $details = DB::connection($connection["connection_name"])->select('select * from `'.$connection['db_list'][0].'`.`game_transaction_ext` as gte ' . $where . ' LIMIT 1');
                $connection_name = $connection["connection_name"];
                if ( !(count($details) > 0) )  {
                    $connection_list = config("serverlist.server_list");
                    foreach($connection_list as $connection){
                        $status = self::checkDBConnection($connection["connection_name"]);
                        if($status && $connection_name != $connection["connection_name"]){
                            $data = DB::connection( $connection["connection_name"] )->select('select * from `'.$connection['db_list'][0].'`.`game_transaction_ext` as gte ' . $where . ' LIMIT 1');
                            if ( count($data) > 0  ) {
                                $connection_name = $connection["connection_name"];
                                $details = $data;
                                break;
                            }
                        }
                    }
                }

                $count = count($details);
                if ($count > 0) {
                    $details[0]->connection_name = $connection_name;
                }
                return $count > 0 ? $details : 'false';
            }
            return 'false';
        } catch (\Exception $e) {
           return 'false';
        }
        

    }
    
    // public static  function findGameTransactionDetailsOP($identifier, $type, $entry_id= false, $client_details) {
    //     $connection_name = $client_details->connection_name;
    //     $entry_type = "";
    //     if ($entry_type) {
    //         $entry_type = "AND gt.entry_id = ". $entry_id;
    //     }

    //     if ($type == 'transaction_id') {
    //         $where = 'where gt.provider_trans_id = "'.$identifier.'" '.$entry_type.'';
    //     } elseif ( $type == 'game_transaction') {
    //         $where = 'where gt.game_trans_id = '.$identifier;
    //     } elseif ( $type == "round_id") {
    //         $where = 'where gt.round_id = "'.$identifier.'" '.$entry_type.'';
    //     }
        

        

    //     try {
    //         $connection = config('serverlist.server_list.'.$connection_name);
    //         $statement = 'select entry_id,bet_amount,game_trans_id,pay_amount from '.$connection['db_list'][1].'.game_transactions gt '.$where.' LIMIT 1';
    //         if (self::checkDBConnection($connection["connection_name"]) ) {
    //             $details = DB::connection( $connection["connection_name"])->select($statement);
    //             if ( !(count($details) > 0 )) {
    //                 $connection_list = config("serverlist.server_list");
    //                 foreach($connection_list as $connection){
    //                     $status = self::checkDBConnection($connection["connection_name"]);
    //                     if($status && $connection_name != $connection["connection_name"]){
    //                         $data = DB::connection( $connection["connection_name"] )->select($statement);
    //                         if ( count($data) > 0  ) {
    //                             $connection_name = $connection["connection_name"];
    //                             $details = $data;
    //                             break;
    //                         }
    //                     }
    //                 }
    //             }

    //             $count = count($details);
    //             if ($count > 0 ) {
    //                 $details[0]->connection_name = $connection_name;
    //             }

    //             return $count > 0 ? $details[0] : 'false';
    //         }
            
            
    //         return 'false';
            

    //     } catch (\Exception $e) {
    //         return $e->getMessage();
    //     }

    // }


    # Queries That Query Default Server and Schema

    /**
     * Selector for provider who dont give the player details for other endpoints
     * 
     */
    public  static function getProviderRoundTracer($provider_identifier, $type)
    {
        try {
            if ($type == 'transaction_id') {
                $where = 'where pt.provider_trans_id = "' . $provider_identifier . '" ';
            }
            if ($type == 'round_id') {
                $where = 'where pt.round_id = "' . $provider_identifier . '" ';
            }
            $filter = 'LIMIT 1';
            $query = DB::select('select * from provider_transactions as pt ' . $where . ' ' . $filter . '');
            $data = count($query);
            return $data > 0 ? $query[0] : 'false';
        } catch (\Exception $e) {
           return 'false';
        }
    }

    /**
     * Store the provider transaction identifier for selection later (Provider who dont send player details in the request body)
     * 
     */
    public static function storeProviderRoundTracer($data){
        return DB::table('provider_transactions')->insertGetId($data);
    }
    # END Queries That Query Default Server and Schema

}
