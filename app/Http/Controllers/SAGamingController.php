<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use App\Helpers\Helper;
use App\Helpers\ClientRequestHelper;
use App\Helpers\ProviderHelper;
use App\Helpers\SAHelper;
use App\Models\GameTransactionMDB;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use Carbon\Carbon;
use DB;

class SAGamingController extends Controller
{

    public $game_db_id = 1;
    public $game_db_code = 'SAGAMING';

    public function __construct(){
        header('Content-type: text/xml');
    }

    // public function debugme(Request $request){
    //     $user_id = Providerhelper::explodeUsername(config('providerlinks.sagaming.prefix'), $request->username);
    //     $client_details = Providerhelper::getClientDetails('player_id', $user_id);
    //     $time = date('YmdHms'); //20140101123456
    //     $method = $request->method;
    //     $querystring = [
    //         "method" => $method,
    //         "Key" => config('providerlinks.sagaming.SecretKey'),
    //         "Time" => $time,
    //         "Username" => config('providerlinks.sagaming.prefix').$client_details->player_id,
    //     ];
    //     $method == 'RegUserInfo' || $method == 'LoginRequest' ? $querystring['CurrencyType'] = $client_details->default_currency : '';
    //     $data = http_build_query($querystring); // QS
    //     $encrpyted_data = SAHelper::encrypt($data);
    //     $md5Signature = md5($data.config('providerlinks.sagaming.MD5Key').$time.config('providerlinks.sagaming.SecretKey'));
    //     $http = new Client();
    //     $response = $http->post(config('providerlinks.sagaming.API_URL'), [
    //         'form_params' => [
    //             'q' => $encrpyted_data, 
    //             's' => $md5Signature
    //         ],
    //     ]);
    //     $resp = simplexml_load_string($response->getBody()->getContents());
    //     dd($resp);
    // }

    // XML BUILD RECURSIVE FUNCTION
    public function siteMap()
    {
        $test_array = array (
            'bla' => 'blub',
            'foo' => 'bar',
            'another_array' => array (
                'stack' => 'overflow',
            ),
        );

        $xml_template_info = new \SimpleXMLElement("<?xml version=\"1.0\"?><template></template>");

        $this->array_to_xml($test_array,$xml_template_info);
        $xml_template_info->asXML(dirname(__FILE__)."/sitemap.xml") ;
        header('Content-type: text/xml');
        dd(readfile(dirname(__FILE__)."/sitemap.xml"));
    }

    public function array_to_xml(array $arr, \SimpleXMLElement $xml)
    {
      foreach ($arr as $k => $v) {
          is_array($v)
              ? $this->array_to_xml($v, $xml->addChild($k))
              : $xml->addChild($k, $v);
      }
      return $xml;
    }
   
    public function makeArrayXML($array){
        $xml_data = new \SimpleXMLElement('<?xml version="1.0"?><RequestResponse></RequestResponse>');
        $xml_file = $this->array_to_xml($array, $xml_data);
        return $xml_file->asXML();
    }

    public function GetUserBalance(Request $request){
        $enc_body = file_get_contents("php://input");
        $url_decoded = urldecode($enc_body);
        $decrypt_data = SAHelper::decrypt($url_decoded);
        parse_str($decrypt_data, $data);
        ProviderHelper::saveLogWithExeption('SA Gaming Balance', config('providerlinks.sagaming.pdbid'), json_encode($data), $enc_body);

        $user_id = Providerhelper::explodeUsername(config('providerlinks.sagaming.prefix'), $data['username']);
        $client_details = Providerhelper::getClientDetails('player_id', $user_id);
        $player_details = Providerhelper::playerDetailsCall($client_details->player_token);

        $data_response = [
            "username" => config('providerlinks.sagaming.prefix').$client_details->player_id,
            "currency" => $client_details->default_currency,
            "amount" => $player_details->playerdetailsresponse->balance,
            "error" => 0,
        ];

        echo $this->makeArrayXML($data_response);
        return;
    }



    public function PlaceBet(){
        $enc_body = file_get_contents("php://input");
        $url_decoded = urldecode($enc_body);
        $decrypt_data = SAHelper::decrypt($url_decoded);
        parse_str($decrypt_data, $data);
        ProviderHelper::saveLogWithExeption('SA PlaceBet EH', config('providerlinks.sagaming.pdbid'), json_encode($data), $enc_body);

        // LOCAL TEST
        // $enc_body = file_get_contents("php://input");
        // parse_str($enc_body, $data);
        // dd($data);
        $username = $data['username'];
        $playersid = Providerhelper::explodeUsername(config('providerlinks.sagaming.prefix'), $username);
        $currency = $data['currency'];
        $amount = $data['amount'];
        $txnid = $data['txnid'];
        // $ip = $data['ip'];
        $gametype = $data['gametype'];
        $game_id = $this->game_db_code;
        // $betdetails = $data['betdetails'];
        $round_id = $data['gameid']; // gameId is unique per table click

        $client_details = ProviderHelper::getClientDetails('player_id',$playersid);
        if($client_details == null){
            $data_response = ["username" => $username,"currency" => $currency, "error" => 10051]; // 1000
            ProviderHelper::saveLogWithExeption('SA PlaceBet - client_details Failed', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
            echo $this->makeArrayXML($data_response);
            return;
        }
        $game_details = Helper::findGameDetails('game_code', config('providerlinks.sagaming.pdbid'), $game_id);
        if($game_details == null){
            $data_response = ["username" => $username,"currency" => $currency, "error" => 10053]; // 134 
            ProviderHelper::saveLogWithExeption('SA PlaceBet - Game Not Found', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
            echo $this->makeArrayXML($data_response);
            return;
        }
        $provider_reg_currency = ProviderHelper::getProviderCurrency(config('providerlinks.sagaming.pdbid'), $client_details->default_currency);
        if($provider_reg_currency == 'false' || $currency != $provider_reg_currency){ // currency not in the provider currency agreement
            $data_response = ["username" => $username,"currency" => $currency, "error" => 10054]; // 1001
            ProviderHelper::saveLogWithExeption('SA PlaceBet - Currency Failed', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
            echo $this->makeArrayXML($data_response);
            return;
        }

            $transaction_check = GameTransactionMDB::findGameExt($txnid, 1,'transaction_id', $client_details);
            if($transaction_check != 'false'){
                $data_response = ["username" => $username,"currency" => $currency, "amount" => $client_details->balance, "error" => 0]; // 122 // transaction not found!
                ProviderHelper::saveLogWithExeption('SA PlaceBet - Transaction Not Found', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
                echo $this->makeArrayXML($data_response);
                return;
            }

            try {
               
                $transaction_type = 'debit';
                $game_transaction_type = 1; // 1 Bet, 2 Win
                $game_code = $game_details->game_id;
                $token_id = $client_details->token_id;
                $bet_amount = $amount; 
                $pay_amount = 0;
                $income = 0;
                $win_type = 0;
                $method = 1;
                $win_or_lost = 5; // 0 lost,  5 processing
                $payout_reason = 'Bet';
                $provider_trans_id = $txnid;
                $round_id = $round_id;  // gameId is unique per table click

                $game_trans_ext = GameTransactionMDB::findGameExt($round_id, 1,'round_id', $client_details);
                if($game_trans_ext == 'false'){
                    $gameTransactionData = array(
                        "provider_trans_id" => $provider_trans_id,
                        "token_id" => $token_id,
                        "game_id" => $game_code,
                        "round_id" => $round_id,
                        "bet_amount" =>  $bet_amount,
                        "win" => $win_or_lost,
                        "pay_amount" => $pay_amount,
                        "income" =>  $income,
                        "entry_id" =>$method,
                    );
                    $gamerecord = GameTransactionMDB::createGametransaction($gameTransactionData, $client_details);
                    $gameTransactionEXTData = array(
                        "game_trans_id" => $gamerecord,
                        "provider_trans_id" => $provider_trans_id,
                        "round_id" => $round_id,
                        "amount" => $bet_amount,
                        "game_transaction_type"=> $game_transaction_type,
                        "provider_request" =>json_encode($data),
                    );
                    $game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
                }else{
                    $game_transaction = GameTransactionMDB::findGameTransactionDetails($game_trans_ext->game_trans_id, 'game_transaction',false, $client_details);
                    $bet_amount = $game_transaction->bet_amount + $amount;
                    $gamerecord = $game_trans_ext->game_trans_id;
                    $gameTransactionEXTData = array(
                        "game_trans_id" => $gamerecord,
                        "provider_trans_id" => $provider_trans_id,
                        "round_id" => $round_id,
                        "amount" => $bet_amount,
                        "game_transaction_type"=> $game_transaction_type,
                        "provider_request" =>json_encode($data),
                    );
                    $game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);
                }

                try {
                    $client_response = ClientRequestHelper::fundTransfer($client_details,abs($amount),$game_details->game_code,$game_details->game_name,$game_transextension,$gamerecord,$transaction_type);
                    ProviderHelper::saveLogWithExeption('SA PlaceBet CRID = '.$provider_trans_id, config('providerlinks.sagaming.pdbid'), json_encode($data), $client_response);
                } catch (\Exception $e) {
                    if(isset($gamerecord)){
                        if($game_trans_ext == 'false'){
                            $updateGameTransaction = [
                                "win" => 2,
                                'trans_status' => 5
                            ];
                            GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
                        }
                    }
                    $data_response = ["username" => $username,"error" => 1005];
                    $updateTransactionEXt = array(
                        "provider_request" =>json_encode($data),
                        "mw_response" => json_encode($data_response),
                        'client_response' => $e->getMessage().' '.$e->getLine().' '.$e->getFile(),
                    );
                    GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
                    echo $this->makeArrayXML($data_response);
                    return;
                }

                if(isset($client_response->fundtransferresponse->status->code) 
                    && $client_response->fundtransferresponse->status->code == "200"){
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_response->fundtransferresponse->balance);
                    if($game_trans_ext != 'false'){
                        //  $this->updateBetTransaction($gamerecord, $game_transaction->pay_amount, $bet_amount, $game_transaction->income, 5, $game_transaction->entry_id);
                        $updateGameTransaction = [
                            "bet_amount" => $bet_amount,
                            "pay_amount" => $game_transaction->pay_amount,
                            "income" =>  $game_transaction->income,
                        ];
                        GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
                    }
                    $data_response = [
                        "username" => $username,
                        "currency" => $client_details->default_currency,
                        "amount" => $client_response->fundtransferresponse->balance,
                        "error" => 0
                    ];
                    $updateTransactionEXt = array(
                        "mw_response" => json_encode($data_response),
                        'mw_request' => json_encode($client_response->requestoclient),
                        'client_response' => json_encode($client_response),
                        'transaction_detail' => json_encode($data_response),
                    );
                    GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
                }elseif(isset($client_response->fundtransferresponse->status->code) 
                    && $client_response->fundtransferresponse->status->code == "402"){
                    if($game_trans_ext == 'false'){
                        $updateGameTransaction = ["win" => 1];
                        GameTransactionMDB::updateGametransaction($updateGameTransaction, $gamerecord, $client_details);
                    }
                    $data_response = ["username" => $username,"currency" => $currency, "amount" => $client_details->balance, "error" => 1004];  // Low Balance1
                    $updateTransactionEXt = array(
                        "mw_response" => json_encode($data_response),
                        'mw_request' => json_encode($client_response->requestoclient),
                        'client_response' => json_encode($client_response),
                        'transaction_detail' => 'FAILED',
                    );
                    GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
                }else{
                    $data_response = ["username" => $username,"error" => 1005];
                    $updateTransactionEXt = array(
                        "mw_response" => json_encode($data_response),
                        'mw_request' => json_encode($client_response->requestoclient),
                        'client_response' => json_encode($client_response),
                        'transaction_detail' => 'FAILED',
                    );
                    GameTransactionMDB::updateGametransactionEXT($updateTransactionEXt,$game_transextension,$client_details);
                    ProviderHelper::saveLogWithExeption('SA PlaceBet - FATAL ERROR', config('providerlinks.sagaming.pdbid'), json_encode($data), 'UNKNOWN STATUS CODE');
                }
                ProviderHelper::saveLogWithExeption('SA PlaceBet', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
                echo $this->makeArrayXML($data_response);
                return;
            } catch (\Exception $e) {
                $data_response = ["username" => $username,"error" => 1005];
                ProviderHelper::saveLogWithExeption('SA PlaceBet - FATAL ERROR', config('providerlinks.sagaming.pdbid'), json_encode($data), $e->getMessage());
                echo $this->makeArrayXML($data_response);
                return;
            }
    }

    public function PlayerWin(){
        $enc_body = file_get_contents("php://input");
        $url_decoded = urldecode($enc_body);
        $decrypt_data = SAHelper::decrypt($url_decoded);
        parse_str($decrypt_data, $data);
        ProviderHelper::saveLogWithExeption('SA PlayerWin EH', config('providerlinks.sagaming.pdbid'), json_encode($data), $enc_body);

        // LOCAL TEST
        // $enc_body = file_get_contents("php://input");
        // parse_str($enc_body, $data); 

        $username = $data['username'];
        $playersid = Providerhelper::explodeUsername(config('providerlinks.sagaming.prefix'), $username);
        $currency = $data['currency'];
        $amount = $data['amount'];
        $txnid = $data['txnid'];
        $gametype = $data['gametype'];
        $game_id = $this->game_db_code;
        $round_id = $data['gameid']; // gameId is unique per table click

        $client_details = ProviderHelper::getClientDetails('player_id',$playersid);
        if($client_details == null){
            $data_response = ["username" => $username, "error" => 1005]; // 1000
            ProviderHelper::saveLogWithExeption('SA PlayerWin - client_details Failed', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
            echo $this->makeArrayXML($data_response);
            return;
        }
        // $getPlayer = ProviderHelper::playerDetailsCall($client_details->player_token); 
        // if($getPlayer == 'false'){
        //     $data_response = ["username" => $username, "error" => 1005];  // 9999
        //     ProviderHelper::saveLogWithExeption('SA PlayerWin - getPlayer Failed', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
        //     echo $this->makeArrayXML($data_response);
        //     return;
        // }
        $game_details = Helper::findGameDetails('game_code', config('providerlinks.sagaming.pdbid'), $game_id);
        if($game_details == null){
            $data_response = ["username" => $username,"currency" => $currency, "error" => 1005];  // 134
            ProviderHelper::saveLogWithExeption('SA PlayerWin - game_details Failed', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
            echo $this->makeArrayXML($data_response);
            return;
        }
        $data_response = ["username" => $username,"currency" => $currency, "error" => 1005]; // 1001
        $provider_reg_currency = ProviderHelper::getProviderCurrency(config('providerlinks.sagaming.pdbid'), $client_details->default_currency);
        if($provider_reg_currency == 'false' || $currency != $provider_reg_currency){ // currency not in the provider currency agreement
            $data_response = ["username" => $username,"currency" => $currency, "error" => 10054]; // 1001
            ProviderHelper::saveLogWithExeption('SA PlayerWin - provider_reg_currency Failed', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
            echo $this->makeArrayXML($data_response);
            return;
        }

            $check_win_entry = GameTransactionMDB::findGameExt($round_id, 2,'round_id', $client_details);
            if($check_win_entry != 'false'){
                $data_response = ["username" => $username,"currency" => $currency, "amount" => $client_details->balance, "error" => 0]; //122 win already exist!
                ProviderHelper::saveLogWithExeption('SA PlayerWin - Win Duplicate', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
                echo $this->makeArrayXML($data_response);
                return;
            }

            $transaction_check = GameTransactionMDB::findGameExt($round_id, 1,'round_id', $client_details);
            if($transaction_check == 'false'){
                $data_response = ["username" => $username,"currency" => $currency, "amount" => $client_details->balance, "error" => 0]; //152
                ProviderHelper::saveLogWithExeption('SA PlayerWin - Bet not Found', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
                echo $this->makeArrayXML($data_response);
                return;
            }


            try {
                $game_trans = GameTransactionMDB::findGameTransactionDetails($transaction_check->game_trans_id, 'game_transaction',false, $client_details);

                $transaction_type = 'credit';
                $game_transaction_type = 2; // 1 Bet, 2 Win
                $game_code = $game_details->game_id;
                $token_id = $client_details->token_id;
                $bet_amount = $game_trans->bet_amount; 
                $pay_amount = $amount;
                $income = $bet_amount - $pay_amount;
                $win_type = 0;
                $method = 1;
                $win_or_lost = 1; // 0 lost,  5 processing
                $payout_reason = 'Win';
                $provider_trans_id = $txnid;

                $data_response = [
                    "username" => $username,
                    "currency" => $client_details->default_currency,
                    "amount" => $client_details->balance+$pay_amount,
                    "error" => 0
                ];

                $gameTransactionEXTData = array(
                    "game_trans_id" => $game_trans->game_trans_id,
                    "provider_trans_id" => $provider_trans_id,
                    "round_id" => $round_id,
                    "amount" => $pay_amount,
                    "game_transaction_type"=> $game_transaction_type,
                    "provider_request" =>json_encode($data),
                );
                $game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

                $action_payload = [
                    "type" => "custom", #genreral,custom :D # REQUIRED!
                    "custom" => [
                        "game_transaction_ext_id" => $game_transextension,
                        "client_connection_name" => $client_details->connection_name,
                        "provider" => 'sagaming',
                        "win_or_lost" => $win_or_lost,
                        "entry_id" => 2,
                        "pay_amount" => abs($amount),
                        "income" => $income,
                    ],
                    "provider" => [
                        "provider_request" => $data, #R
                        "provider_trans_id"=> $provider_trans_id, #R
                        "provider_round_id"=> $round_id, #R
                    ],
                    "mwapi" => [
                        "roundId"=>$transaction_check->game_trans_id, #R
                        "type"=>2, #R
                        "game_id" => $game_details->game_id, #R
                        "player_id" => $client_details->player_id, #R
                        "mw_response" => $data_response, #R
                    ],
                    'fundtransferrequest' => [
                        'fundinfo' => [
                            'freespin' => false,
                        ]
                    ]
                ];

                $client_response = ClientRequestHelper::fundTransfer_TG($client_details,abs($amount),$game_details->game_code,$game_details->game_name,$transaction_check->game_trans_id,$transaction_type,false,$action_payload);
                
                if(isset($client_response->fundtransferresponse->status->code) 
                    && $client_response->fundtransferresponse->status->code == "200"){
                    ProviderHelper::_insertOrUpdate($client_details->token_id, $client_details->balance+$pay_amount);
                    $data_response = [
                        "username" => $username,
                        "currency" => $client_details->default_currency,
                        "amount" => $client_details->balance+$pay_amount,
                        "error" => 0
                    ];
                }elseif(isset($client_response->fundtransferresponse->status->code) 
                    && $client_response->fundtransferresponse->status->code == "402"){
                     $data_response = ["username" => $username,"currency" => $currency, "amount" => $client_details->balance, "error" => 1004];  // Low Balance1
                }
                ProviderHelper::saveLogWithExeption('SA PlayerWin', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
                echo $this->makeArrayXML($data_response);
                return;
            } catch (\Exception $e) {
                $data_response = [
                    "username" => $username,
                    "error" => 1005 // 9999
                ];
                ProviderHelper::saveLogWithExeption('SA PlayerWin - FATAL ERROR', config('providerlinks.sagaming.pdbid'), json_encode($data), $e->getMessage().' '.$e->getLine().' '.$e->getFile());
                echo $this->makeArrayXML($data_response);
                return;
            }
    }

    public function PlayerLost(){
        $enc_body = file_get_contents("php://input");
        $url_decoded = urldecode($enc_body);
        $decrypt_data = SAHelper::decrypt($url_decoded);
        parse_str($decrypt_data, $data);
        ProviderHelper::saveLogWithExeption('SA PlayerLost EH', config('providerlinks.sagaming.pdbid'), json_encode($data), $enc_body);
        // LOCAL TEST
        // $enc_body = file_get_contents("php://input");
        // parse_str($enc_body, $data);
            
        try {
         
            $username = $data['username'];
            $playersid = Providerhelper::explodeUsername(config('providerlinks.sagaming.prefix'), $username);
            $currency = $data['currency'];
            $txnid = $data['txnid'];
            $gametype = $data['gametype'];
            $game_id = $this->game_db_code;
            $round_id = $data['gameid']; // gameId is unique per table click

            $client_details = ProviderHelper::getClientDetails('player_id',$playersid);
            if($client_details == null){
                $data_response = ["username" => $username,"error" => 1005]; // 1000
                ProviderHelper::saveLogWithExeption('SA PlayerLost - Client Error', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
                echo $this->makeArrayXML($data_response);
                return;
            }
            $game_details = Helper::findGameDetails('game_code', config('providerlinks.sagaming.pdbid'), $game_id);
            if($game_details == null){
                $data_response = ["username" => $username, "error" => 1005];  // 134
                ProviderHelper::saveLogWithExeption('SA PlayerLost - Game Error', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
                echo $this->makeArrayXML($data_response);
                return;
            }
            $game_trans_ext = GameTransactionMDB::findGameExt($round_id, 1,'round_id', $client_details);
            if($game_trans_ext == 'false'){
                $data_response = ["username" => $username,"currency" => $client_details->default_currency, "amount" => $client_details->balance, "error" => 0];
                 ProviderHelper::saveLogWithExeption('SA PlayerLost - Round Not Found', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
                echo $this->makeArrayXML($data_response);
                return;
            }
            // $game_trans = GameTransactionMDB::findGameTransactionDetails($transaction_check->game_trans_id, 'game_transaction',false, $client_details);

            // $player_details = Providerhelper::playerDetailsCall($client_details->player_token);
            $data_response = [
                "username" => $username,
                "currency" => $client_details->default_currency,
                "amount" => $client_details->balance,
                "error" => 0
            ];

            $game_transaction = GameTransactionMDB::findGameTransactionDetails($game_trans_ext->game_trans_id, 'game_transaction',false, $client_details);

            $gameTransactionEXTData = array(
                "game_trans_id" => $game_trans_ext->game_trans_id,
                "provider_trans_id" => $txnid,
                "round_id" => $round_id,
                "amount" => 0,
                "game_transaction_type"=> 0,
                "provider_request" =>json_encode($data),
            );
            $game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);

            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "game_transaction_ext_id" => $game_transextension,
                    "client_connection_name" => $client_details->connection_name,
                    "provider" => 'sagaming',
                    "win_or_lost" => 0,
                    "entry_id" => 2,
                    "pay_amount" => $game_transaction->pay_amount,
                    "income" => $game_transaction->income,
                ],
                "provider" => [
                    "provider_request" => $data, #R
                    "provider_trans_id"=> $txnid, #R
                    "provider_round_id"=> $round_id, #R
                ],
                "mwapi" => [
                    "roundId"=>$game_trans_ext->game_trans_id, #R
                    "type"=>2, #R
                    "game_id" => $game_details->game_id, #R
                    "player_id" => $client_details->player_id, #R
                    "mw_response" => $data_response, #R
                ],
                'fundtransferrequest' => [
                    'fundinfo' => [
                        'freespin' => false,
                    ]
                ]
            ];

            $client_response = ClientRequestHelper::fundTransfer_TG($client_details,0,$game_details->game_code,$game_details->game_name,$game_trans_ext->game_trans_id,"credit",false,$action_payload);
            

            // When Player Lost Auto Callback 0 winning
            // try {
            //     $client_response = ClientRequestHelper::fundTransfer($client_details,0,$game_details->game_code,$game_details->game_name,$game_transextension,$transaction_check->game_trans_id, 'credit');
            //     ProviderHelper::saveLogWithExeption('SA PlayerLost CRID', config('providerlinks.sagaming.pdbid'), json_encode($data), $client_response);
            // } catch (\Exception $e) {
            //     $data_response = ["username" => $username,"error" => 1005];
            //     ProviderHelper::updatecreateGameTransExt($game_transextension, 'FAILED', $data_response, 'FAILED', $e->getMessage(), 'FAILED', 'FAILED');
            //     ProviderHelper::saveLogWithExeption('SA PlayerLost - FATAL ERROR', config('providerlinks.sagaming.pdbid'), json_encode($data), $e->getMessage());
            //     echo $this->makeArrayXML($data_response);
            //     return;
            // }

            if(isset($client_response->fundtransferresponse->status->code) 
                    && $client_response->fundtransferresponse->status->code == "200"){
                // ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $data_response, $client_response->requestoclient, $client_response, $data_response);
                // ProviderHelper::updateBetTransaction($transaction_check->game_trans_id, $game_transaction->pay_amount, $game_transaction->bet_amount, 0, $game_transaction->entry_id);
            }

            ProviderHelper::saveLogWithExeption('SA PlayerLost SUCCESS', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
            echo $this->makeArrayXML($data_response);
            return;

        } catch (\Exception $e) {
            $data_response = [
                "username" => $username,
                "error" => 1005 // 9999
            ];
            ProviderHelper::saveLogWithExeption('SA PlayerLost - FATAL ERROR', config('providerlinks.sagaming.pdbid'), json_encode($decrypt_data), $e->getMessage().' '.$e->getLine().' '.$e->getFile());
            echo $this->makeArrayXML($data_response);
            return;
        }
        
        
    }

    public function PlaceBetCancel(){
        $enc_body = file_get_contents("php://input");
        $url_decoded = urldecode($enc_body);
        $decrypt_data = SAHelper::decrypt($url_decoded);
        parse_str($decrypt_data, $data);
        ProviderHelper::saveLogWithExeption('SA PlaceBetCancel EH', config('providerlinks.sagaming.pdbid'), json_encode($data), $enc_body);
     
        // LOCAL TEST
        // $enc_body = file_get_contents("php://input");
        // parse_str($enc_body, $data);

            $username = $data['username'];
            $playersid = Providerhelper::explodeUsername(config('providerlinks.sagaming.prefix'), $username);
            $currency = $data['currency'];
            $amount = $data['amount'];
            $txnid = $data['txnid'];
            $gametype = $data['gametype'];
            $gamecancel = $data['gamecancel'];
            $txn_reverse_id = $data['txn_reverse_id'];
            $game_id = $this->game_db_code;
            $round_id = $data['gameid']; // gameId is unique per table click
            $transaction_type = 'credit';

            $client_details = ProviderHelper::getClientDetails('player_id',$playersid);
            if($client_details == null){
                $data_response = ["username" => $username, "error" => 1005]; // 1000
                ProviderHelper::saveLogWithExeption('SA PlaceBetCancel - Player Not Found', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
                echo $this->makeArrayXML($data_response);
                return;
            }
            $game_details = Helper::findGameDetails('game_code', config('providerlinks.sagaming.pdbid'), $game_id);
            if($game_details == null){
                $data_response = ["username" => $username,"currency" => $currency, "error" => 1005];  // 134
                ProviderHelper::saveLogWithExeption('SA PlaceBetCancel - Game Not Found', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
                echo $this->makeArrayXML($data_response);
                return;
            }
            // $getPlayer = ProviderHelper::playerDetailsCall($client_details->player_token);
            // if($getPlayer == 'false'){
            //     $data_response = ["username" => $username, "error" => 1005]; 
            //     ProviderHelper::saveLogWithExeption('SA PlaceBetCancel - Client Failed', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
            //     echo $this->makeArrayXML($data_response);
            //     return;
            // }
            // $transaction_check = ProviderHelper::findGameExt($round_id, 1,'round_id');
            $transaction_check = GameTransactionMDB::findGameExt($round_id, 1,'round_id', $client_details);
            if($transaction_check == 'false'){
                $data_response = ["username" => $username,"currency" => $client_details->default_currency,"amount" => $client_details->balance, "error" => 0]; // 152 // 1005
                // RETURN ERROR CODE 0 to stop the callbacks
                ProviderHelper::saveLogWithExeption('SA PlaceBetCancel - Transaction Not Found', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
                echo $this->makeArrayXML($data_response);
                return;
            }

            // $existing_refund_call = $this->GameTransactionExt($txnid, $round_id, 3);
            $existing_refund_call = GameTransactionMDB::findGameExt($txnid, 3,'transaction_id', $client_details);
            if($existing_refund_call != null){
                $data_response = ["username" => $username,"currency" => $client_details->default_currency,"amount" => $client_details->balance, "error" => 0]; // 122 // 1005
                // RETURN ERROR CODE 0 to stop the callbacks
                ProviderHelper::saveLogWithExeption('SA PlaceBetCancel - Existing Refund', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
                echo $this->makeArrayXML($data_response);
                return;
            }
            
            try {

            // $game_trans = ProviderHelper::findGameTransaction($transaction_check->game_trans_id, 'game_transaction');
            // $game_trans_ext = ProviderHelper::findGameExt($round_id, 1, 'round_id');
            // $game_transaction = ProviderHelper::findGameTransaction($game_trans_ext->game_trans_id,'game_transaction');

            $game_transaction = GameTransactionMDB::findGameTransactionDetails($transaction_check->game_trans_id, 'game_transaction',false, $client_details);
            $game_trans_ext = GameTransactionMDB::findGameExt($round_id, 1,'round_id', $client_details);

            // $all_bet = $this->getAllBet($round_id, 1);
            $all_bet = GameTransactionMDB::findGameExtAll($round_id, 1,'round_id', $client_details);
            
            // $transaction_check = ProviderHelper::findGameExt($txnid, 1,'transaction_id');
            // dd($all_bet);
            if(count($all_bet) > 0){ // MY BETS!
                if(count($all_bet) == 1){

                    $updateGameTransaction = ["win" => 4];
                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_ext->game_trans_id, $client_details);
                    // ProviderHelper::updateBetTransaction($game_trans_ext->game_trans_id, $game_transaction->pay_amount, $game_transaction->bet_amount, 4, $game_transaction->entry_id);
             
                }else{
                    $sum = 0;
                    foreach($all_bet as $key=>$value){
                      if(isset($value->amount)){
                         $sum += $value->amount;
                      }
                    }
                    $bet_amount = $sum - $amount;
                    if($game_transaction->pay_amount > 0){
                        $pay_amount = $game_transaction->pay_amount - $amount;
                    }else{
                        $pay_amount = $game_transaction->pay_amount;
                    }
                    if($game_transaction->income > 0){
                        $income = $bet_amount - $pay_amount;
                    }else{
                        $income = $game_transaction->income;
                    }
                    // $this->updateBetTransaction($game_trans_ext->game_trans_id, $pay_amount, $bet_amount, $income, $game_transaction->win, $game_transaction->entry_id);
                    // $this->updateBetTransaction($round_id, $game_transaction->pay_amount, $bet_amount, $game_transaction->income, $game_transaction->win, $game_transaction->entry_id);
                    $updateGameTransaction = [
                        "bet_amount" => $bet_amount,
                        "pay_amount" => $pay_amount,
                        "income" =>  $income,
                        "win" => $game_transaction->win,
                    ];
                    GameTransactionMDB::updateGametransaction($updateGameTransaction, $game_trans_ext->game_trans_id, $client_details);
                }
            }

            $data_response = [
                "username" => $username,
                "currency" => $client_details->default_currency,
                "amount" => $client_details->balance+0,
                "error" => 0
            ];

            $gameTransactionEXTData = array(
                "game_trans_id" => $game_trans_ext->game_trans_id,
                "provider_trans_id" => $txnid,
                "round_id" => $round_id,
                "amount" => $amount,
                "game_transaction_type"=> 3,
                "provider_request" =>json_encode($data),
            );
            $game_transextension = GameTransactionMDB::createGameTransactionExt($gameTransactionEXTData,$client_details);


            $action_payload = [
                "type" => "custom", #genreral,custom :D # REQUIRED!
                "custom" => [
                    "update_transaction" => false,
                    "game_transaction_ext_id" => $game_transextension,
                    "client_connection_name" => $client_details->connection_name,
                    "provider" => 'sagaming',
                    "win_or_lost" => 4,
                    "entry_id" => 2,
                    "pay_amount" => $pay_amount,
                    "income" => $income,
                ],
                "provider" => [
                    "provider_request" => $data, #R
                    "provider_trans_id"=> $txnid, #R
                    "provider_round_id"=> $round_id, #R
                ],
                "mwapi" => [
                    "roundId"=>$game_trans_ext->game_trans_id, #R
                    "type"=>2, #R
                    "game_id" => $game_details->game_id, #R
                    "player_id" => $client_details->player_id, #R
                    "mw_response" => $data_response, #R
                ],
                'fundtransferrequest' => [
                    'fundinfo' => [
                        'freespin' => false,
                    ]
                ]
            ];

            $client_response = ClientRequestHelper::fundTransfer_TG($client_details,0,$game_details->game_code,$game_details->game_name,$game_trans_ext->game_trans_id,"credit",false,$action_payload);

            ProviderHelper::saveLogWithExeption('SA PlaceBetCancel - SUCCESS', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
            echo $this->makeArrayXML($data_response);
            return;

            // $game_transextension = ProviderHelper::createGameTransExtV2($game_trans->game_trans_id,$txnid, $round_id, $amount, 3);

            // try {
            //     $client_response = ClientRequestHelper::fundTransfer($client_details,abs($amount),$game_details->game_code,$game_details->game_name,$game_transextension,$game_trans->game_trans_id,$transaction_type, true);
            //     ProviderHelper::saveLogWithExeption('SA PlaceBetCancel CRID', config('providerlinks.sagaming.pdbid'), json_encode($data), $client_response);
            // } catch (\Exception $e) {
            //     $data_response = ["username" => $username,"error" => 1005];
            //     ProviderHelper::updatecreateGameTransExt($game_transextension, 'FAILED', $data_response, 'FAILED', $e->getMessage(), 'FAILED', 'FAILED');
            //     ProviderHelper::saveLogWithExeption('SA PlaceBetCancel - FATAL ERROR', config('providerlinks.sagaming.pdbid'), json_encode($data), $e->getMessage());
            //     echo $this->makeArrayXML($data_response);
            //     return;
            // }
      
            // if(isset($client_response->fundtransferresponse->status->code) 
            //         && $client_response->fundtransferresponse->status->code == "200"){
            //     $data_response = [
            //         "username" => $username,
            //         "currency" => $client_details->default_currency,
            //         "amount" => $client_response->fundtransferresponse->balance,
            //         "error" => 0
            //     ];

            //     ProviderHelper::updatecreateGameTransExt($game_transextension, $data, $data_response, $client_response->requestoclient, $client_response, $data_response);

            //     ProviderHelper::saveLogWithExeption('SA PlaceBetCancel - SUCCESS', config('providerlinks.sagaming.pdbid'), json_encode($data), $data_response);
            //     echo $this->makeArrayXML($data_response);
            //     return;
            // }
           
        

          } catch (\Exception $e) {
            $data_response = [
                "username" => $username,
                // "currency" => $client_details->default_currency,
                // "amount" => $client_response->fundtransferresponse->balance,
                "error" => 1005
            ];
            ProviderHelper::saveLogWithExeption('SA PlaceBetCancel - FATAL ERROR', config('providerlinks.sagaming.pdbid'), json_encode($data), $e->getMessage());
            echo $this->makeArrayXML($data_response);
            return;
          }

      

    }


    public function GameTransactionExt($provider_trans_id, $round_id, $type){
        $game_ext = DB::table('game_transaction_ext as gte')
                    ->where('gte.provider_trans_id', $provider_trans_id)
                    ->where('gte.round_id', $round_id)
                    ->where('gte.game_transaction_type', $type)
                    ->first();
        return $game_ext;
    }


    public function updateBetTransaction($round_id, $pay_amount, $bet_amount, $income, $win, $entry_id){
        DB::table('game_transactions')
            // ->where('round_id', $round_id)
            ->where('game_trans_id', $round_id)
            ->update(['pay_amount' => $pay_amount, 
                  'bet_amount' => $bet_amount, 
                  'income' => $income, 
                  'win' => $win, 
                  'entry_id' => $entry_id,
                  'transaction_reason' => ProviderHelper::updateReason($win),
            ]);
    }

    public function getAllBet($round_id, $gtt){
        $game_ext = DB::table('game_transaction_ext as gte')
                    // ->select(DB::raw('SUM(gte.amount) as total_bet'))
                    ->select('gte.amount')
                    ->where('gte.round_id', $round_id)
                    ->where('gte.game_transaction_type', $gtt)
                    ->get();
        return $game_ext;

    }
}
