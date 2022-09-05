<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\Helper;
use App\Helpers\ProviderHelper;
use App\Helpers\ClientRequestHelper;
use App\Models\GameTransactionMDB;

class LoadTesterQueryCLientCallController extends Controller
{
    private $prefix = 22;
    public function __construct() {
        // $this->startTime = microtime(true);
    }
    public function ProcessTransaction(Request $request){
        $data = json_decode($request->getContent(),TRUE);
        // Helper::saveLog('BNGMETHOD(BNG)', 12, json_encode(["request_data" => $data]), "");
        $client_details = ProviderHelper::getClientDetails('token', $data["token"]);
        $game_details = ProviderHelper::findGameDetails('game_code', $this->prefix, $data["game_id"]);

        // $win_or_lost = $data["args"]["win"] == 0 ? 0 : 1;
        // $gameTransactionData = array(
        //     "provider_trans_id" => $data["uid"],
        //     "token_id" => $client_details->token_id,
        //     "game_id" => $game_details->game_id,
        //     "round_id" => $data["args"]["round_id"],
        //     "bet_amount" => $data["args"]["bet"],
        //     "win" => 5,
        //     "pay_amount" =>$data["args"]["win"],
        //     "income" =>$data["args"]["bet"]-$data["args"]["win"],
        //     "entry_id" =>$data["args"]["win"] == 0 ? 1 : 2,
        // );
        // $game_transactionid = GameTransactionMDB::createGametransaction($gameTransactionData,$client_details);
        // $betgametransactionext = array(
        //     "game_trans_id" => $game_transactionid,
        //     "provider_trans_id" => $data["uid"],
        //     "round_id" => $data["args"]["round_id"],
        //     "amount" => $data["args"]["bet"],
        //     "game_transaction_type"=>1,
        //     "provider_request" =>json_encode($data),
        // );
        // $betGametransactionExtId = GameTransactionMDB::createGameTransactionExt($betgametransactionext,$client_details);
        // $balance = number_format($client_details->balance,2,'.', '');
        // $client_details->balance = $balance;
        // ProviderHelper::_insertOrUpdate($client_details->token_id, $balance);

        // $response =array(
        //     "uid"=>$data["uid"],
        //     "balance" => array(
        //         "value" => (string)$client_details->balance,
        //         "version" => round(microtime(true) * 1000)//$this->_getExtParameter()
        //     ),
        // );
        return response($response,200)
            ->header('Content-Type', 'application/json');
    }
}
