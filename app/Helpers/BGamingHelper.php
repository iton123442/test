<?php
namespace App\Helpers;
use App\Helpers\Helper;
use DB;

class BGamingHelper
{
	public static function generateSignature($auth_string, $payload) {
		$secret = config('providerlinks.bgaming.AUTH_TOKEN');
		$signature = hash_hmac('sha256', $secret, json_encode($payload));
		return $signature;
	}


	public static function checkSignature($request_sign, $payload) {
		$secret = config('providerlinks.bgaming.AUTH_TOKEN');
		$signature = hash_hmac('sha256',json_encode($payload),$secret);
		Helper::saveLog('Bgaming signature', 49, json_encode($signature), $request_sign);
		if($signature == $request_sign) {
			$result = true;
		}

		return $result;
	}

}
