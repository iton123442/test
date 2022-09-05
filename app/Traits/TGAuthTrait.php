<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use DB;

trait TGAuthTrait {

    private $expiry_time = 15; // in minutes
    private $access_token = 'oauth_tokens'; // custom table

    /**
     * @param string $grantType
     * @return boolean "true" if exist, "false" if not
     */
    public function grantTypes($grantType){
        $availableGrantTypes = ['password'];
        return in_array($grantType, $availableGrantTypes) ? true : false;
    }

    /**
     * @param timestamp $create_at
     */
    public function checkExpiry($created_at){
        $strtotime = strtotime($created_at);
        $time_now = time();
        return $time_now -$strtotime > $this->expiry_time*60 ? true : false;
    }

    /**
     * Issue token
     *
     * @param Illumiante Request
     */
    public function issueAccessToken(Request $request){
        if(!$request->has('client_id') || !$request->has('username') || !$request->has('password') || !$request->has('client_secret') || !$request->has('grant_type')){
            return ['error' => "access_denied", "error_description" => "Missing required parameter."];
        }
        $isVerified = $this->verifyClient($request->username, $request->password, $request->client_id, $request->client_secret);
        if(!$isVerified){
            return ['error' => "access_denied", "error_description" => "Wrong Credential."];
        }

        $isValidGrant = $this->grantTypes($request->grant_type);
        if(!$isValidGrant){
            return ['error' => "access_denied", "error_description" => "Wrong grant type."];
        }

        $storeToken = $this->storeAccessToken($request->client_id);
        if(!$storeToken){
            return ['error' => "error", "error_description" => "System busy please try again"];
        }

        $grantToken = [
            'access_token' => $storeToken,
            'token_type' => "Bearer",
        ];

        return $grantToken;
    }

    /**
     * Store access token
     *
     * @param int $client_id
     * @return string
     */
    public function storeAccessToken($client_id){
        $randomString = Str::random(43);
        try {
            $query = DB::select("insert into `".$this->access_token."` (`client_id`, `access_token`) values ($client_id,'$randomString')");
            return $randomString;
        }catch(\Exception $e){
            return false;
        }
    }

    /**
     * Check if access token exist
     *
     * @param string $access_token
     * @return boolean "true" if exist, "false" if not
     */
    public function verifyAccessToken($access_token){
        $auth =  explode(' ', $access_token);
        if(!isset($auth[1])){
            return false;
        }
        $query = DB::select("SELECT id,created_at FROM `".$this->access_token."` WHERE access_token = '".$auth[1]."' limit 1");
        $auth_token = count($query);
        if($auth_token == 0){
            return false;
        }
        if($this->checkExpiry($query[0]->created_at)){
            return false;
        }
        return $auth_token > 0 ? true : false;
    }

    /**
     * Verify if client account is exsiting
     *
     * @param varchar $username
     * @param varchar $password
     * @return boolean "true" if exist, "false" if not
     */
    public function verifyClient($email, $password, $client_id, $client_secret){
        $clientQuery = DB::select('SELECT id,secret FROM oauth_clients WHERE secret = "'.$client_secret.'" and id = "'.$client_id.'" limit 1');
        $client = count($clientQuery);
        if($client == 0){
            return false;
        }

        if($client_secret != $clientQuery[0]->secret){
           return false;
        }

        $userQuery = DB::select('SELECT password,username FROM users WHERE email = "'.$email.'" limit 1');
        $user = count($userQuery);
        if($user == 0){
            return false;
        }

        if(Hash::check($password, $userQuery['0']->password)) {
          return true;
        } else {
          return  false;
        }
    }


}