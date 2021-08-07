<?php

namespace App\Http\Controllers\TransferWalletAggregator;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TransferWalletAggregator\TWHelpers;
use Illuminate\Http\Request;
use DB;

class DetailsAndFundTransferController extends Controller
{   

    private $access_token;
    private $api_key;

    public function __construct(){
        $this->access_token = 'WDmHCoq8Iu4Ja1OtwsW3r0nfgUuuPeIaChglB3BJ';
        $this->api_key = 'HGFXbsfhi8U2g5Fz';
    }

    public function getPlayerDetails(Request $request){
        // $hashkey = md5( $this->api_key  . $this->access_token );
        $decodedrequest = json_decode($request->getContent(),TRUE);
        try {
            // if($decodedrequest["hashkey"]== $hashkey){
            $security = TWHelpers::Client_SecurityHash($decodedrequest["clientid"], $decodedrequest["access_token"]);
            if($security){
                $details = TWHelpers::getClientDetails('token', $decodedrequest["playerdetailsrequest"]["token"]);

                if ($details) {
                    $response = array(
                        "playerdetailsresponse" => array(
                            "status" => array(
                                "code"=>200,
                                "status" => "OK",
                                "message" => "The request was successfully completed."
                            ),
                            'accountid' =>  $details->tw_player_bal_id,
                            'accountname' =>  $details->client_player_id,
                            'username' =>  $details->username,
                            'email' =>  $details->email,
                            'balance' =>   number_format((float)$details->tw_balance, 2, '.', ''),   //AlHelper::checkBal(AlHelper::accCheck()['user_id']),
                            'currencycode' =>  $details->default_currency,
                            'country_code' =>  $details->country_code,
                            'birthday' => '',
                            'firstname' =>'',
                            'lastname' => '',
                            'gender' => '',
                            'refreshtoken' => 'false'
                        )
                    );
                    return response($response,200)
                       ->header('Content-Type', 'application/json');
                } else {
                    $response = array(
                        "error_code"=>"UNAUTHENTICATED_REQUEST",
                        "error_message"=> "request is unauthenticated/missing a required input"
                    );
                    return response($response,400)
                       ->header('Content-Type', 'application/json');
                }
                
            } else {
                $response = array(
                    "error_code"=>"UNAUTHENTICATED_REQUEST",
                    "error_message"=> "request is unauthenticated/missing a required input"
                );
                return response($response,400)
                   ->header('Content-Type', 'application/json');
            }
        } catch (Exception $e) {
            $response = array(
                "error_code"=>"SERVER NOT READY",
                "error_message"=> "request is unauthenticated/missing a required input"
            );
            return response($response,400)
               ->header('Content-Type', 'application/json');
        }
    }

    public function fundTransfer(Request $request){

        // $hashkey = md5( $this->api_key  . $this->access_token );
        $decodedrequest = json_decode($request->getContent(),TRUE);

        try {
            // $security = TWHelpers::Client_SecurityHash($decodedrequest["clientid"], $decodedrequest["access_token"]);
            $details = TWHelpers::getClientDetails('token', $decodedrequest["fundtransferrequest"]["playerinfo"]["token"]);
            if($details){

                $balance = $details->tw_balance;

                


                if($decodedrequest["fundtransferrequest"]["fundinfo"]["transactiontype"]=="debit"){

                    if ( !($balance >= $decodedrequest["fundtransferrequest"]["fundinfo"]["amount"]) ) {
                        $response = array(
                            "fundtransferresponse" => array(
                                "status" => array(
                                    "code"=>402,
                                    "status" => "Failed",
                                    "message" => "Insufficient funds."
                                ),
                                'balance'=> $balance,
                                'currencycode' =>  $details->default_currency
                            )
                        );
                        return response($response,200)
                           ->header('Content-Type', 'application/json');
                    }

                    $current_balance = $balance - $decodedrequest["fundtransferrequest"]["fundinfo"]["amount"];
                }
                else{
                    $current_balance = $balance + $decodedrequest["fundtransferrequest"]["fundinfo"]["amount"];

                    try{
                        TWHelpers::idenpotencyTable($decodedrequest["fundtransferrequest"]["fundinfo"]["transactionId"]);
                    }catch(\Exception $e){
                        $response = array(
                            "fundtransferresponse" => array(
                                "status" => array(
                                    "code"=> 200,
                                    "status" => "OK",
                                    "message" => "The request was successfully completed."
                                ),
                                'accountid' =>  $details->tw_player_bal_id,
                                'accountname' =>  $details->client_player_id,
                                'email' =>  $details->email,
                                'balance' => $balance,
                                'currencycode' =>  $details->default_currency,
                            )
                        );
    
                        return response($response,200)
                        ->header('Content-Type', 'application/json');
                    }

                    
                } 

                TWHelpers::updateTWBalance($current_balance, $details->tw_player_bal_id);
                if($current_balance >= 0){
                    $response = array(
                        "fundtransferresponse" => array(
                            "status" => array(
                                "code"=>200,
                                "status" => "OK",
                                "message" => "The request was successfully completed."
                            ),
                            'accountid' =>  $details->tw_player_bal_id,
                            'accountname' =>  $details->client_player_id,
                            'email' =>  $details->email,
                            'balance' => $current_balance,
                            'currencycode' =>  $details->default_currency,
                        )
                    );
                
                    return response($response,200)
                       ->header('Content-Type', 'application/json');
                } else { 
                    $response = array(
                        "fundtransferresponse" => array(
                            "status" => array(
                                "code"=>402,
                                "status" => "Failed",
                                "message" => "Insufficient funds."
                            ),
                            'balance'=> $balance,
                            'currencycode' =>  $details->default_currency
                        )
                    );
                    return response($response,200)
                       ->header('Content-Type', 'application/json');
                }

            } else {
                $response = array(
                    "fundtransferresponse" => array(
                        "status" => array(
                            "code"=>402,
                            "status" => "Failed",
                            "message" => "Player Not Found"
                        ),
                        'balance'=> "0.00",
                        'currencycode' => ""
                    )
                );
                return response($response,200)
                   ->header('Content-Type', 'application/json');
            }
        } catch (Exception $e) {
           $response = array(
                "fundtransferresponse" => array(
                    "status" => array(
                        "code"=>402,
                        "status" => "Failed",
                        "message" => "UNAUTHENTICATED_REQUEST"
                    ),
                    'balance'=> "0.00",
                    'currencycode' => ""
                )
            );
            return response($response,200)
               ->header('Content-Type', 'application/json');
        }
    }

    public function transactionChecker(Request $request){
         $decodedrequest = json_decode($request->getContent(),TRUE);
         // dd($decodedrequest);

        $response = array(
            'code' =>  200,
            'status' =>  'TRANSACTION_SUCCESS',
        );
         // if($checkWallet){
         //    $response = array(
         //        'code' =>  200,
         //        'status' =>  'TRANSACTION_SUCCESS',
         //    );
         // }else{
         //    $response = array(
         //        'code' =>  404,
         //        'status' =>  'TRANSACTION_NOT_FOUND',
         //    );
         // }
         
        return response($response,200)->header('Content-Type', 'application/json');
    }


   
}
