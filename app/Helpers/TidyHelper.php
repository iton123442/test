<?php
namespace App\Helpers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use Carbon\Carbon;

use Firebase\JWT\JWT;

use DB;

class TidyHelper{


	public static function updateGameTransactionBet($game_trans_id, $bet_amount) {

        $update = DB::table('game_transactions')
                ->where('game_trans_id', $game_trans_id)
                ->update(['bet_amount' => $bet_amount]);
                
		return ($update);
	}

	public static function generateToken(Array $data) {
		 $data['iat'] = (int)microtime(true);
		 $jwt = JWT::encode($data, config('providerlinks.tidygaming.SECRET_KEY'));
		 return $jwt;
	}

	public static function generateTokenTransfer(Array $data) {
         $data['iat'] = (int)microtime(true);
         $jwt = JWT::encode($data, config('providerlinks.tidygaming.TransferWallet.SECRET_KEY'));
         return $jwt;
    }

	// JWT VERIFICATION
    public static function decodeToken($token){
		$token = $token;
		try {
			$decoded = JWT::decode($token, config('providerlinks.tidygaming.SECRET_KEY'), array('HS256'));
			// return json_encode($decoded);
			return 'true';
		} catch(\Exception $e) {
			// $response = [
			// 			"errorcode" =>  "authorization_error",
			// 			"errormessage" => "Verification is failed.",
			// 		];
			// return json_encode($response);
			return 'false';
		}
	}

	 public static function currencyCode($currency){
	 	$code = '';
	 	switch ($currency) {
	 		case 'CNY':
	 			$code = '156';
	 			break;
	 		case 'THB':
	 			$code = '764';
	 			break;
	 		case 'IDR':
	 			$code = '360';
	 			break;
	 		case 'MYR':
	 			$code = '458';
	 			break;
	 		case 'VND':
	 			$code = '704';
	 			break;
	 		case 'KRW':
	 			$code = '410';
	 			break;
	 		case 'JPY':
	 			$code = '392';
	 			break;
	 		case 'BND':
	 			$code = '096';
	 			break;
	 		case 'HKD':
	 			$code = '344';
	 			break;
	 		case 'SGD':
	 			$code = '702';
	 			break;
	 		case 'PHP':
	 			$code = '608';
	 			break;
	 		case 'TRY':
	 			$code = '949';
	 			break;
	 		case 'USD':
	 			$code = '840';
	 			break;
	 		case 'GBP':
	 			$code = '826';
	 			break;
	 		case 'EUR':
	 			$code = '978';
	 			break;
	 		case 'INR':
	 			$code = '356';
	 			break;
	 		case 'MMK':
	 			$code = '104';
	 			break;
	 		case 'KHR':
	 			$code = '116';
	 			break;
	 		case 'CAD':
	 			$code = '124';
	 			break;
	 		case 'LAK':
	 			$code = '418';
	 			break;
	 		case 'AUD':
	 			$code = '036';
	 			break;
	 		case 'UAH':
	 			$code = '980';
	 			break;
	 		case 'NOK':
	 			$code = '578';
	 			break;
	 		case 'SEK':
	 			$code = '752';
	 			break;
	 		case 'ZAR':
	 			$code = '710';
	 			break;
	 		case 'BDT':
	 			$code = '050';
	 			break;
	 		case 'LKR':
	 			$code = '144';
	 			break;
	 		case 'RUB':
	 			$code = '643';
	 			break;
	 		case 'PLN':
	 			$code = '985';
	 			break;
	 		case 'AED':
	 			$code = '784';
	 			break;
	 		case 'BRL':
	 			$code = '986';
	 			break;
	 		case 'CHF':
	 			$code = '756';
	 			break;
	 		case 'NZD':
	 			$code = '554';
	 			break;
	 		case 'HUF':
	 			$code = '348';
	 			break;
	 		case 'DKK':
	 			$code = '208';
	 			break;
	 		case 'IRR':
	 			$code = '364';
	 			break;
			case 'kVND':
	 			$code = '10002';
	 			break;
			case 'kIDR':
	 			$code = '10003';
	 			break;
			case 'kMMK':
				$code = '10011';
				break;
			case 'kKHR':
				$code = '10012';
				break;
			case 'kLAK':
				$code = '10013';
				break;

	 	}

	 	return $code;
	 }

}