<?php
namespace App\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use DB;


/**
 * @author's note : No longer used
 * 
 */
class RefundHelper{

	/**
	 *  @author's note : Unused
	 * 
	 */
	public static function createDebitRefund($data){
        return DB::table('transaction_refund')->insert($data);
    }

}
