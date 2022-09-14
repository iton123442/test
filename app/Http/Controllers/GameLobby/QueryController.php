<?php

namespace App\Http\Controllers\GameLobby;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\ProviderHelper;
use DB;
class QueryController extends Controller
{
    //
    public function __construct(){

        // $this->middleware('oauth', ['except' => []]);
		$this->middleware('tg_auth', ['except' => []]);
		/*$this->middleware('authorize:' . __CLASS__, ['except' => ['index', 'store']]);*/
	}

    /**
     * Used by GameLobby
     */
    public function queryData(Request $request){

        #filterd key word
        $array = array('delete','update','alter','truncate','drop','schema','show','database','--');
        #loop filtered key word
        foreach($array as $item){
            #find filterd key word from request
            $contains = str_contains($request->input("query"),$item);
            #check if exist then stop the process
            if($contains == true){
              ProviderHelper::mandatorySaveLog('SOMEONE_USE_RESTRICTED_KEYWORD', 666,json_encode($request->all()), $request->input("query"));
              return array('status' => 'error', 'messages' => 'Invalid Values '.$item);
            }
        }

        if($request->table_name != "users"){
            $query = DB::select(DB::raw($request->input("query")));
            return $query;
        }
    }
}
