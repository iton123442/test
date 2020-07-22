<?php
namespace App\Helpers;
use DB;

class DESHelper
{
	var $key;
    var $iv;
    public static function __construct( $key, $iv=0 ) {
        $this->key = $key;
        if( $iv == 0 ) {
            $this->iv = $key;
        } else {
            $this->iv = $iv;
        }
    }
    public static function encrypt($str) {
		return base64_encode( openssl_encrypt($str, 'DES-CBC', $this->key, OPENSSL_RAW_DATA, $this->iv  ) );
	}
    public static function decrypt($str) {
		$str = openssl_decrypt(base64_decode($str), 'DES-CBC', $this->key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $this->iv);
		return rtrim($str, "\x01..\x1F");
    }
    public static function pkcs5Pad($text, $blocksize) {
        $pad = $blocksize - (strlen ( $text ) % $blocksize);
        return $text . str_repeat ( chr ( $pad ), $pad );
    }

}