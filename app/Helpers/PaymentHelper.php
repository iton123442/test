<?php
namespace App\Helpers;
use DB;
use GuzzleHttp\Client;
use App\PayTransaction;
class PaymentHelper
{
    public static function connectTo(){
        $http = new Client();
        // $response = $http->post('http://localhost:8000/oauth/token', [
        //     'form_params' => [
        //         'grant_type' => 'password',
        //         'client_id' => '3',
        //         'client_secret' => 'uAthPzJR6lk9hrgPljMUjzGHjnPvtT2Ps6eLHRv7',
        //         'username' => 'randybaby@gmail.com',
        //         'password' => 'epointexchange.com',
        //         'scope' => '*',
        //     ],
        // ]);

         $response = $http->post('https://epointexchange.com/oauth/token', [
            'form_params' => [
                'grant_type' => 'password',
                'client_id' => '3',
                'client_secret' => 'uAthPzJR6lk9hrgPljMUjzGHjnPvtT2Ps6eLHRv7',
                'username' => 'd10627627@urhen.com',
                'password' => 'w34KM)!$#',
                'scope' => '*',
            ],
        ]);

        return json_decode((string) $response->getBody(), true)["access_token"];
    }
    public static function paymongo($cardnumber,$exp_year,$exp_month,$cvc,$amount,$currency){

        $http = new Client();
        $response = $http->post('https://epointexchange.com/api/v1/paymentportal/paymongo', [
            'form_params' => [
                'pmcurrency' => $currency,
                'amount' => $amount,
                'cardnumber' => $cardnumber,
                'expmonth' => $exp_month,
                'expyear' => $exp_year,
                'cvc' => $cvc,
            ],
            'headers' =>[
                'Authorization' => 'Bearer '.PaymentHelper::connectTo(),
                'Accept'     => 'application/json' 
            ]
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }
    public static function vprica($cardnumber,$amount){
        $http = new Client();
        // $response = $http->post('http://localhost:8000/api/v1/paymentportal/vprica', [
        $response = $http->post('https://epointexchange.com/api/v1/paymentportal/vprica', [
            'form_params' => [
                'amount' => $amount,
                'cardnumber' => $cardnumber,
            ],
            'headers' =>[
                'Authorization' => 'Bearer '.PaymentHelper::connectTo(),
                'Accept'     => 'application/json' 
            ]
        ]);
        return json_decode($response->getBody(),TRUE);
    }
    //coinspayment
    public static function getCoinspaymentRate(){
        $http = new Client();
        // $response = $http->get('http://localhost:8000/api/v1/paymentportal/coinpayment_rate', [
        $response = $http->get('https://epointexchange.com/api/v1/paymentportal/coinpayment_rate', [
            'headers' =>[
                'Authorization' => 'Bearer '.PaymentHelper::connectTo(),
                'Accept'     => 'application/json' 
            ]
        ]);
        return json_decode((string) $response->getBody(), true);
    }
    public static function coinspayment($amount,$currency){

        // dd('here you go!');

        $http = new Client();
        // $response = $http->post('http://localhost:8000/api/v1/paymentportal/coinpayment_transaction', [
        $response = $http->post('https://epointexchange.com/api/v1/paymentportal/coinpayment_transaction', [
            'form_params' => [
                'amount' => $amount,
                'currency' => $currency,
            ],
            'headers' =>[
                'Authorization' => 'Bearer '.PaymentHelper::connectTo(),
                'Accept'     => 'application/json' 
            ]
        ]);
        return json_decode((string) $response->getBody(), true);
    }
    public static function currency(){
        $client = new Client([
            'headers' => ['x-rapidapi-host' => 'currency-converter5.p.rapidapi.com',
            'x-rapidapi-key' => '8206256315mshcd8655ee7f5800dp1bf51bjsn355caa8858be',
            'Content-Type' => 'application/x-www-form-urlencoded'],
            'http_errors' => false,
        ]);
        $response = $client->get('https://currency-converter5.p.rapidapi.com/currency/convert', [
        ]);
        $data = json_decode($response->getBody(),TRUE);
        $currency = array(
            "main_currency"=>"USD",
            "rates"=>array()
        );
        foreach($data["rates"] as $key=>$rate){
            $usdcurrency = array(
                "currency" =>$key,
                "currency_name"=>$rate["currency_name"],
                "rate" => number_format((1/(float)$rate["rate"])/(1/(float)$data["rates"]["USD"]["rate"]), 5, '.', ''),
            );
            array_push($currency["rates"],$usdcurrency);
        }
        return $currency;
    }

    ///endcoinspayment
    public static function payTransactions($token_id,$purchase_id,$payment_id,$amount,$entry_id,$trans_type_id,$trans_update_url,$status_id){
        $pay_transaction = new PayTransaction();
        $pay_transaction->token_id = $token_id;
        $pay_transaction->identification_id=$purchase_id;
        $pay_transaction->payment_id=$payment_id;
        $pay_transaction->amount=$amount;
        $pay_transaction->entry_id=2;
        $pay_transaction->trans_type_id=1;
        $pay_transaction->status_id=$status_id;
        $pay_transaction->trans_update_url=$trans_update_url;
        $pay_transaction->save();
        return $pay_transaction;
    }
}
