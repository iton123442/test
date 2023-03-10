<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/
// Home page
$app->get('/', function () use ($app) {
    return $app->version();
});
$app->get('/debug-sentry', function () {
    throw new Exception('My first Sentry erroraaaa!');
});
$app->post('/public/v3api','AlController@v3Api'); // TESTING!
$app->post('/public/v2api','AlController@v2Api'); // TESTING!
$app->get('/public/alraw/{player_info}/{player_id}','AlController@rawRequestToClient'); // TESTING!
$app->post('/public/al','AlController@index'); // TESTING!
$app->post('/public/massresend','AlController@massResend'); // TESTING!
$app->post('/public/alplayer','AlController@checkCLientPlayer'); // TESTING!
$app->post('/public/gg','AlController@tapulan'); // TESTING!
$app->post('/public/aldebug','AlController@debugMe'); // TESTING!
$app->post('/public/manual_resend','AlController@resendTransaction'); // TESTING!
// Posts
$app->get('/public/posts','PostController@index');
$app->post('/public/posts','PostController@store');
$app->get('/public/posts/{post_id}','PostController@show');
$app->put('/public/posts/{post_id}', 'PostController@update');
$app->patch('/public/posts/{post_id}', 'PostController@update');
$app->delete('/public/posts/{post_id}', 'PostController@destroy');
// Users
$app->get('/public/users/', 'UserController@index');
$app->post('/public/users/', 'UserController@store');
$app->get('/public/users/{user_id}', 'UserController@show');
$app->put('/public/users/{user_id}', 'UserController@update');
$app->patch('/public/users/{user_id}', 'UserController@update');
$app->delete('/public/users/{user_id}', 'UserController@destroy');
// Comments
$app->get('/public/comments', 'CommentController@index');
$app->get('/public/comments/{comment_id}', 'CommentController@show');
// Comment(s) of a post
$app->get('/public/posts/{post_id}/comments', 'PostCommentController@index');
$app->post('/public/posts/{post_id}/comments', 'PostCommentController@store');
$app->put('/public/posts/{post_id}/comments/{comment_id}', 'PostCommentController@update');
$app->patch('/public/posts/{post_id}/comments/{comment_id}', 'PostCommentController@update');
$app->delete('/public/posts/{post_id}/comments/{comment_id}', 'PostCommentController@destroy');

// Request an access token
// $app->post('/public/oauth/access_token', function() use ($app){
//     return response()->json($app->make('oauth2-server.authorizer')->issueAccessToken());
// });
$app->post('/public/oauth/access_token', ['middleware' => 'tg_auth:issue_access_token']);


// Client Round History Inquire
$app->post('/public/api/transaction/info', 'TransactionInfoController@getTransaction');

// Player Details Request
$app->post('/public/api/playerdetailsrequest/', 'PlayerDetailsController@show');

// Fund Transfer Request
$app->post('/public/api/fundtransferrequest/', 'FundTransferController@process');

// Solid Gaming Endpoints
$app->post('/public/api/solid/{brand_code}/authenticate', 'SolidGamingController@authPlayer');
$app->post('/public/api/solid/{brand_code}/playerdetails', 'SolidGamingController@getPlayerDetails');
$app->post('/public/api/solid/{brand_code}/balance', 'SolidGamingController@getBalance');
$app->post('/public/api/solid/{brand_code}/debit', 'SolidGamingController@debitProcess');
$app->post('/public/api/solid/{brand_code}/credit', 'SolidGamingController@creditProcess');
$app->post('/public/api/solid/{brand_code}/debitandcredit', 'SolidGamingController@debitAndCreditProcess');
$app->post('/public/api/solid/{brand_code}/rollback', 'SolidGamingController@rollbackTransaction');
$app->post('/public/api/solid/{brand_code}/endround', 'SolidGamingController@endPlayerRound');
$app->post('/public/api/solid/{brand_code}/endsession', 'SolidGamingController@endPlayerSession');

// Oryx Gaming Endpoints
// $app->post('/public/api/oryx/{brand_code}/tokens/{token}/authenticate', 'OryxGamingController@authPlayer');
// $app->post('/public/api/oryx/{brand_code}/players/{player_id}/balance', 'OryxGamingController@getBalance');
// $app->post('/public/api/oryx/{brand_code}/game-transaction', 'OryxGamingController@gameTransaction');
// $app->put('/public/api/oryx/{brand_code}/game-transactions', 'OryxGamingController@gameTransactionV2');
$app->post('/public/api/oryx/{brand_code}/tokens/{token}/authenticate', 'OryxGamingMDBController@authPlayer');
$app->post('/public/api/oryx/{brand_code}/players/{player_id}/balance', 'OryxGamingMDBController@getBalance');
$app->post('/public/api/oryx/{brand_code}/game-transaction', 'OryxGamingMDBController@gameTransaction');
$app->put('/public/api/oryx/{brand_code}/game-transactions', 'OryxGamingMDBController@gameTransactionV2');
$app->post('/public/api/oryx/{brand_code}/free-rounds/finish', 'OryxGamingController@roundFinished');


// SimplePlay Endpoints
$app->post('/public/api/simpleplay/{brand_code}/GetUserBalance', 'SimplePlayController@getBalance');
$app->post('/public/api/simpleplay/{brand_code}/PlaceBet', 'SimplePlayController@debitProcess');
$app->post('/public/api/simpleplay/{brand_code}/PlayerWin', 'SimplePlayController@creditProcess');
$app->post('/public/api/simpleplay/{brand_code}/PlayerLost', 'SimplePlayController@lostTransaction');
$app->post('/public/api/simpleplay/{brand_code}/PlaceBetCancel', 'SimplePlayController@rollBackTransaction');

// MannaPlay Endpoints
// $app->post('/public/api/manna/{brand_code}/fetchbalance', 'MannaPlayController@getBalance');
// $app->post('/public/api/manna/{brand_code}/bet', 'MannaPlayController@debitProcess');
// $app->post('/public/api/manna/{brand_code}/win', 'MannaPlayController@creditProcess');
// $app->post('/public/api/manna/{brand_code}/betrollback', 'MannaPlayController@rollbackTransaction');
// $app->post('/public/api/manna/{brand_code}/fs_win', 'MannaPlayController@freeRound');
//MannaPlay Idempotent update MannaPlayV2Controller
$app->post('/public/api/manna/{brand_code}/fetchbalance', 'MannaPlayV2Controller@getBalance');
$app->post('/public/api/manna/{brand_code}/bet', 'MannaPlayV2Controller@debitProcess');
$app->post('/public/api/manna/{brand_code}/win', 'MannaPlayV2Controller@creditProcess');
$app->post('/public/api/manna/{brand_code}/betrollback', 'MannaPlayV2Controller@rollbackTransaction');
$app->post('/public/api/manna/{brand_code}/fs_win', 'MannaPlayV2Controller@freeRound');

// Ozashiki Single Controller Endpoints
$app->post('/public/api/ozashiki/fetchbalance', 'MannaPlayController@getBalance');
$app->post('/public/api/ozashiki/bet', 'MannaPlayController@debitProcess');
$app->post('/public/api/ozashiki/win', 'MannaPlayController@creditProcess');
$app->post('/public/api/ozashiki/betrollback', 'MannaPlayController@rollbackTransaction');


// QTech Games Endpoints
$app->get('/public/api/qtech/{brand_code}/accounts/{player_id}/session?gameId={game_id}', 'QTechController@authPlayer');
$app->post('/public/api/qtech/{brand_code}/accounts/{player_id}/balance?gameId={game_id}', 'QTechController@getBalance');
$app->post('/public/api/qtech/{brand_code}/transactions', 'QTechController@gameTransaction');
$app->post('/public/api/qtech/{brand_code}/transactions/rollback', 'QTechController@rollbackTransaction');
$app->post('/public/api/qtech/{brand_code}/bonus/status', 'QTechController@bonusStatus');

// Vivo Gaming Endpoints
$app->get('/public/api/vivo/{brand_code}/authenticate', 'VivoController@authPlayer');
$app->get('/public/api/vivo/{brand_code}/changebalance', 'VivoController@gameTransaction');
$app->get('/public/api/vivo/{brand_code}/status', 'VivoController@transactionStatus');
$app->get('/public/api/vivo/{brand_code}/getbalance', 'VivoController@getBalance');

// ICG Gaming Endpoints
// $app->get('/public/api/icgaming/gamelist','ICGController@getGameList');
// $app->post('/public/api/icgaming/gamelaunch','ICGController@gameLaunchURL');
// $app->get('/public/api/icgaming/authplayer','ICGController@authPlayer');
// $app->get('/public/api/icgaming/playerDetails','ICGController@playerDetails');
// $app->post('/public/api/icgaming/bet','ICGController@betGame');
// $app->delete('/public/api/icgaming/bet','ICGController@cancelBetGame');
// $app->post('/public/api/icgaming/win','ICGController@winGame');
// $app->post('/public/api/icgaming/withdraw','ICGController@withdraw');
// $app->post('/public/api/icgaming/deposit','ICGController@deposit');
//Newflow ICG
$app->get('/public/api/icgaming/gamelist','ICGNewV2Controller@getGameList');
$app->post('/public/api/icgaming/gamelaunch','ICGNewV2Controller@gameLaunchURL');
$app->get('/public/api/icgaming/authplayer','ICGNewV2Controller@authPlayer');
$app->get('/public/api/icgaming/playerDetails','ICGNewV2Controller@playerDetails');
$app->post('/public/api/icgaming/bet','ICGNewV2Controller@betGame');
$app->delete('/public/api/icgaming/bet','ICGNewV2Controller@cancelBetGame');
$app->post('/public/api/icgaming/win','ICGNewV2Controller@winGame');
$app->post('/public/api/icgaming/withdraw','ICGNewV2Controller@withdraw');
$app->post('/public/api/icgaming/deposit','ICGNewV2Controller@deposit');
// EDP Gaming Endpoints
$app->post('/public/api/edp/gamelunch','EDPController@gameLaunchUrl');
$app->get('/public/api/edp/check','EDPController@index');
$app->get('/public/api/edp/session','EDPController@playerSession');
$app->get('/public/api/edp/balance','EDPController@getBalance');
$app->post('/public/api/edp/bet','EDPController@betGame');
$app->post('/public/api/edp/win','EDPController@winGame');
$app->post('/public/api/edp/refund','EDPController@refundGame');
$app->post('/public/api/edp/endSession','EDPController@endGameSession');
// Lottery Gaming Endpoints
$app->post('/public/api/lottery/authenticate', 'LotteryController@authPlayer');
$app->post('/public/api/lottery/balance', 'LotteryController@getBalance'); #/
$app->post('/public/api/lottery/debit', 'LotteryController@debitProcess'); #/
// $app->post('/public/api/lottery/credit', 'LotteryController@creditProcess');
// $app->post('/public/api/lottery/debitandcredit', 'LotteryController@debitAndCreditProcess');
// $app->post('/public/api/lottery/endsession', 'LotteryController@endPlayerSession');
// Mariott Gaming Endpoints
$app->post('/public/api/marriott/authenticate', 'MarriottController@authPlayer');
$app->post('/public/api/marriott/balance', 'MarriottController@getBalance'); #/
$app->post('/public/api/marriott/debit', 'MarriottController@debitProcess'); #/
// RGS Gaming Endpoints
$app->post('/public/rgs/authenticate', 'DigitainController@authenticate');
// $app->post('/public/rgs/creategamesession', 'DigitainController@createGameSession'); // DONT NEED!
$app->post('/public/rgs/getbalance', 'DigitainController@getbalance');
$app->post('/public/rgs/refreshtoken', 'DigitainController@refreshtoken');
$app->post('/public/rgs/bet', 'DigitainController@bet');
$app->post('/public/rgs/win', 'DigitainController@win');
$app->post('/public/rgs/betwin', 'DigitainController@betwin');
$app->post('/public/rgs/refund', 'DigitainController@refund');
$app->post('/public/rgs/amend', 'DigitainController@amend');
$app->post('/public/rgs/promowin', 'DigitainController@PromoWin');
$app->post('/public/rgs/charge', 'DigitainController@makeCharge');
$app->post('/public/rgs/checktxstatus', 'DigitainController@CheckTxStatus');

//newflowforDigitain
// $app->post('/public/rgs/authenticate', 'DigitainNEWController@authenticate');
// // $app->post('/public/rgs/creategamesession', 'DigitainNEWController@createGameSession'); // DONT NEED!
// $app->post('/public/rgs/getbalance', 'DigitainNEWController@getbalance');
// $app->post('/public/rgs/refreshtoken', 'DigitainNEWController@refreshtoken');
// $app->post('/public/rgs/bet', 'DigitainNEWController@bet');
// $app->post('/public/rgs/win', 'DigitainNEWController@win');
// $app->post('/public/rgs/betwin', 'DigitainNEWController@betwin');
// $app->post('/public/rgs/refund', 'DigitainNEWController@refund');
// $app->post('/public/rgs/amend', 'DigitainNEWController@amend');
// $app->post('/public/rgs/promowin', 'DigitainNEWController@PromoWin');
// $app->post('/public/rgs/charge', 'DigitainNEWController@makeCharge');
// $app->post('/public/rgs/checktxstatus', 'DigitainNEWController@CheckTxStatus');
// IA SPORTS
$app->post('/public/api/ia/hash', 'IAESportsController@hashen'); // DEPRECATED
$app->post('/public/api/ia/lunch', 'IAESportsController@userlunch');// DEPRECATED
$app->post('/public/api/ia/register', 'IAESportsController@userRegister');
$app->post('/public/api/ia/userwithdraw', 'IAESportsController@userWithdraw');// DEPRECATED
$app->post('/public/api/ia/userdeposit', 'IAESportsController@userDeposit');// DEPRECATED
$app->post('/public/api/ia/userbalance', 'IAESportsController@userBalance');// DEPRECATED
$app->post('/public/api/ia/wager', 'IAESportsController@userWager'); // DEPRECATED
$app->post('/public/api/ia/hotgames', 'IAESportsController@getHotGames'); // DEPRECATED
$app->post('/public/api/ia/orders', 'IAESportsController@userOrders');// DEPRECATED
$app->post('/public/api/ia/activity_logs', 'IAESportsController@userActivityLog'); // DEPRECATED
$app->post('/public/api/ia/deposit', 'IAESportsController@seamlessDeposit');
$app->post('/public/api/ia/withdrawal', 'IAESportsController@seamlessWithdrawal');
$app->post('/public/api/ia/balance', 'IAESportsController@seamlessBalance');
$app->post('/public/api/ia/searchorder', 'IAESportsController@seamlessSearchOrder');
$app->post('/public/api/ia/debugg', 'IAESportsController@userlaunch');
$app->post('/public/api/ia/settleround', 'IAESportsController@SettleRounds');
// Bole Gaming Endpoints
$app->post('/public/api/bole/register', 'BoleGamingController@playerRegister');
$app->post('/public/api/bole/logout', 'BoleGamingController@playerLogout');
$app->post('/public/api/bole/wallet/player/cost', 'BoleGamingController@playerWalletCost');
$app->post('/public/api/bole/wallet/player/balance', 'BoleGamingController@playerWalletBalance');
// AWS PROVIDER BACKOFFICE ROUTE
$app->post('/public/api/aws/register', 'AWSController@playerRegister');
$app->post('/public/api/aws/launchgame', 'AWSController@launchGame');
$app->post('/public/api/aws/gamelist', 'AWSController@gameList');
$app->post('/public/api/aws/playermanage', 'AWSController@playerManage');
$app->post('/public/api/aws/playerstatus', 'AWSController@playerStatus');
$app->post('/public/api/aws/playerbalance', 'AWSController@playerBalance'); 
$app->post('/public/api/aws/fundtransfer', 'AWSController@fundTransfer'); 
$app->post('/public/api/aws/querystatus', 'AWSController@queryStatus'); 
$app->post('/public/api/aws/orderquery', 'AWSController@queryOrder');
$app->post('/public/api/aws/getday', 'AWSController@getAllWaySpinDayTransaction');
// AWS PROVIDER SINGLE WALLET ROUTE
// $app->post('/public/api/aws/single/wallet/balance', 'AWSController@singleBalance');
// $app->post('/public/api/aws/single/wallet/fund/transfer', 'AWSController@singleFundTransfer');
// $app->post('/public/api/aws/single/wallet/fund/query', 'AWSController@singleFundQuery');
// $app->post('/public/api/aws/single/wallet/altest', 'AWSController@changeAccount');
// AWS NEWFLOW Version 2
$app->post('/public/api/aws/single/wallet/balance', 'AWSNewController@singleBalance');
$app->post('/public/api/aws/single/wallet/fund/transfer', 'AWSNewController@singleFundTransfer');
$app->post('/public/api/aws/single/wallet/fund/query', 'AWSNewController@singleFundQuery');
$app->post('/public/api/aws/single/wallet/altest', 'AWSNewController@changeAccount');
// SILKSTONE ROUTES (SEAMLESS WALLET)
// $app->post('/public/skywind/api/get_ticket', 'SkyWindController@getTicket');
$app->post('/public/api/skywind/api/getgamelist', 'SkyWindController@getGamelist'); // TEST
$app->post('/public/api/skywind/api/getauth', 'SkyWindController@getAuth'); // TEST
$app->post('/public/api/skywind/api/getauth2', 'SkyWindController@getAuth2'); // TEST
$app->post('/public/api/skywind/api/getgames', 'SkyWindController@getGamelist'); // TEST
$app->post('/public/api/skywind/api/gamelaunch', 'SkyWindController@gameLaunch'); // TEST

// Version  Sky Wind 1
$app->post('/public/api/skywind/api/validate_ticket', 'SkyWindController@validateTicket');
$app->post('/public/api/skywind/api/get_ticket', 'SkyWindController@getTicket');
$app->post('/public/api/skywind/api/get_balance', 'SkyWindController@getBalance');
$app->post('/public/api/skywind/api/debit', 'SkyWindController@gameDebit');
$app->post('/public/api/skywind/api/credit', 'SkyWindController@gameCredit');
$app->post('/public/api/skywind/api/rollback', 'SkyWindController@gameRollback');
$app->post('/public/api/skywind/api/get_free_bet', 'SkyWindController@getFreeBet');

// Version two  Skywind
// $app->post('/public/api/skywind/api/api/validate_ticket', 'SkyWindController@validateTicket');
// $app->post('/public/api/skywind/api/api/get_ticket', 'SkyWindController@getTicket');
// $app->post('/public/api/skywind/api/api/get_balance', 'SkyWindController@getBalance');
// $app->post('/public/api/skywind/api/api/debit', 'SkyWindController@gameDebit');
// $app->post('/public/api/skywind/api/api/credit', 'SkyWindController@gameCredit');
// $app->post('/public/api/skywind/api/api/rollback', 'SkyWindController@gameRollback');
// $app->post('/public/api/skywind/api/api/get_free_bet', 'SkyWindController@getFreeBet');
//Player API
//Operator API
//Lobby API
//Report API

// CQ9 Gaming
// $app->post('/public/api/cq9/transaction/game/bet','CQ9Controller@playerBet'); //
// $app->post('/public/api/cq9/transaction/game/endround','CQ9Controller@playrEndround'); //
// $app->post('/public/api/cq9/transaction/game/rollout','CQ9Controller@playerRollout'); //
// $app->post('/public/api/cq9/transaction/game/takeall','CQ9Controller@playerTakeall');
// $app->post('/public/api/cq9/transaction/game/rollin','CQ9Controller@playerRollin'); //
// $app->post('/public/api/cq9/transaction/game/debit','CQ9Controller@playerDebit');
// $app->post('/public/api/cq9/transaction/game/credit','CQ9Controller@playerCredit');
// $app->post('/public/api/cq9/transaction/game/bonus','CQ9Controller@playerBonus');
// $app->post('/public/api/cq9/transaction/user/payoff','CQ9Controller@playerPayoff');
// $app->post('/public/api/cq9/transaction/game/refund','CQ9Controller@playerRefund');
// $app->get('/public/api/cq9/transaction/record/{mtcode}','CQ9Controller@playerRecord'); 

// $app->post('/public/api/cq9/transaction/game/bets','CQ9Controller@playerBets');
// $app->post('/public/api/cq9/transaction/game/refunds','CQ9Controller@playerRefunds');
// $app->post('/public/api/cq9/transaction/game/cancel','CQ9Controller@playerCancel');
// $app->post('/public/api/cq9/transaction/game/amend','CQ9Controller@playerAmend');
// $app->post('/public/api/cq9/transaction/game/wins','CQ9Controller@playerWins');
// $app->post('/public/api/cq9/transaction/game/amends','CQ9Controller@playerAmends');

// $app->get('/public/api/cq9/transaction/balance/{account}','CQ9Controller@CheckBalance');
// $app->get('/public/api/cq9/gameboy/player/lotto/balance/{account}','CQ9Controller@CheckBalanceLotto'); // New
// $app->get('/public/api/cq9/player/check/{account}','CQ9Controller@CheckPlayer');
// $app->post('/public/api/cq9/player/check','CQ9Controller@noRouteParamPassed'); // TEST
// $app->post('/public/api/cq9/transaction/record','CQ9Controller@noRouteParamPassed');  // TEST
// $app->post('/public/api/cq9/transaction/balance','CQ9Controller@noRouteParamPassed'); // TEST

// $app->post('/public/api/cq9/mw/getlist','CQ9Controller@getGameList');
// $app->post('/public/api/cq9/mw/gamelaunch','CQ9Controller@gameLaunch');
//CQ9 NEWFLOW
$app->post('/public/api/cq9/transaction/game/bet','CQ9NEWController@playerBet'); //
$app->post('/public/api/cq9/transaction/game/endround','CQ9NEWController@playrEndround'); //
$app->post('/public/api/cq9/transaction/game/rollout','CQ9NEWController@playerRollout'); //
$app->post('/public/api/cq9/transaction/game/takeall','CQ9NEWController@playerTakeall');
$app->post('/public/api/cq9/transaction/game/rollin','CQ9NEWController@playerRollin'); //
$app->post('/public/api/cq9/transaction/game/debit','CQ9NEWController@playerDebit');
$app->post('/public/api/cq9/transaction/game/credit','CQ9NEWController@playerCredit');
$app->post('/public/api/cq9/transaction/game/bonus','CQ9NEWController@playerBonus');
$app->post('/public/api/cq9/transaction/user/payoff','CQ9NEWController@playerPayoff');
$app->post('/public/api/cq9/transaction/game/refund','CQ9NEWController@playerRefund');
$app->get('/public/api/cq9/transaction/record/{mtcode}','CQ9NEWController@playerRecord'); 

$app->post('/public/api/cq9/transaction/game/bets','CQ9NEWController@playerBets');
$app->post('/public/api/cq9/transaction/game/refunds','CQ9NEWController@playerRefunds');
$app->post('/public/api/cq9/transaction/game/cancel','CQ9NEWController@playerCancel');
$app->post('/public/api/cq9/transaction/game/amend','CQ9NEWController@playerAmend');
$app->post('/public/api/cq9/transaction/game/wins','CQ9NEWController@playerWins');
$app->post('/public/api/cq9/transaction/game/amends','CQ9NEWController@playerAmends');

$app->get('/public/api/cq9/transaction/balance/{account}','CQ9NEWController@CheckBalance');
$app->get('/public/api/cq9/gameboy/player/lotto/balance/{account}','CQ9NEWController@CheckBalanceLotto'); // New
$app->get('/public/api/cq9/player/check/{account}','CQ9NEWController@CheckPlayer');
$app->post('/public/api/cq9/player/check','CQ9NEWController@noRouteParamPassed'); // TEST
$app->post('/public/api/cq9/transaction/record','CQ9NEWController@noRouteParamPassed');  // TEST
$app->post('/public/api/cq9/transaction/balance','CQ9NEWController@noRouteParamPassed'); // TEST

$app->post('/public/api/cq9/mw/getlist','CQ9NEWController@getGameList');
$app->post('/public/api/cq9/mw/gamelaunch','CQ9NEWController@gameLaunch');



//SAGaming 
$app->post('/public/api/sa/debugme','SAGamingController@debugme');
$app->post('/public/api/sa/GetUserBalance','SAGamingController@GetUserBalance');
$app->post('/public/api/sa/PlaceBet','SAGamingController@PlaceBet');
$app->post('/public/api/sa/PlayerWin','SAGamingController@PlayerWin');
$app->post('/public/api/sa/PlayerLost','SAGamingController@PlayerLost');
$app->post('/public/api/sa/PlaceBetCancel','SAGamingController@PlaceBetCancel');

// KAGaming
$app->post('/public/api/ka/gamelist','KAGamingNFController@index');
$app->post('/public/api/ka/start','KAGamingNFController@gameStart');
$app->post('/public/api/ka/end','KAGamingNFController@gameEnd');
$app->post('/public/api/ka/promo','KAGamingNFController@getPromo');
$app->post('/public/api/ka/play','KAGamingNFController@checkPlay');
$app->post('/public/api/ka/credit','KAGamingNFController@gameCredit');
$app->post('/public/api/ka/balance','KAGamingNFController@playerBalance');
$app->post('/public/api/ka/revoke','KAGamingNFController@gameRevoke');



// 8PROVIDERS
$app->post('/public/api/eightproviderTest', 'EightProviderControllerV2@getClientDetailsEvoPlay'); // Single Route

$app->post('/public/api/eightprovider', 'EightProviderControllerV2@index'); // Single Route
$app->post('/public/api/eightprovider/test', 'EightProviderControllerV2@testcall'); // TEST
$app->post('/public/api/eightprovider/getlist', 'EightProviderControllerV2@getGames');
$app->post('/public/api/eightprovider/geturl', 'EightProviderControllerV2@gameUrl'); // DEPRECATED
$app->post('/public/api/eightprovider/registerbunos', 'EightProviderControllerV2@registerBunos'); // DEPRECATED
$app->post('/public/api/eightprovider/init', 'EightProviderControllerV2@gameInit'); // DEPRECATED
$app->post('/public/api/eightprovider/bet', 'EightProviderControllerV2@gameBet'); // DEPRECATED
$app->post('/public/api/eightprovider/win', 'EightProviderControllerV2@gameWin'); // DEPRECATED
$app->post('/public/api/eightprovider/refund', 'EightProviderControllerV2@gameRefund'); // DEPRECATED
$app->post('/public/api/eightprovider/deposit', 'EightProviderControllerV2@gameDeposit'); // DEPRECATED
$app->post('/public/api/eightprovider/withdrawal', 'EightProviderControllerV2@gameWithdrawal'); // DEPRECATED
$app->post('/public/api/gettransaction', 'AlController@testTransaction');

//BNG Endpoints
$app->post('/public/api/bng', 'BNGController@index');
$app->post('/public/api/bng/gamelaunch', 'BNGController@gameLaunchUrl');
$app->post('/public/api/bng/generateGame','BNGController@generateGame');
//FC GAMING Endpoints
$app->post('/public/api/fc/encrypt','FCController@SampleEncrypt');
$app->post('/public/api/fc/decode','FCController@SampleDecrypt');
$app->post('/public/api/fc/getbalance','FCController@getBalance');
$app->post('/public/api/fc/transaction','FCController@transactionMake');
$app->post('/public/api/fc/cancelbet','FCController@cancelBet');
$app->post('/public/api/fc/gamelaunch','FCController@gameLaunch');
//PNG Endpoints
$app->post('/public/api/png/authenticate','PNGController@authenticate');
$app->post('/public/api/png/reserve','PNGController@reserve');
$app->post('/public/api/png/release','PNGController@release');
$app->post('/public/api/png/balance','PNGController@balance');
$app->post('/public/api/png/cancelReserve','PNGController@cancelReserve');
//PNG BETSOFTED
$app->post('/public/api/png/betsofted/authenticate','PNGControllerBetsofted@authenticate');
$app->post('/public/api/png/betsofted/reserve','PNGControllerBetsofted@reserve');
$app->post('/public/api/png/betsofted/release','PNGControllerBetsofted@release');
$app->post('/public/api/png/betsofted/balance','PNGControllerBetsofted@balance');
$app->post('/public/api/png/betsofted/cancelReserve','PNGController@cancelReserve');
//Wazdan Endpoints
// $app->post('/public/api/wazdan/authenticate','WazdanControllerNew@authenticate');
// $app->post('/public/api/wazdan/getStake','WazdanControllerNew@getStake');
// $app->post('/public/api/wazdan/rollbackStake','WazdanControllerNew@rollbackState');
// $app->post('/public/api/wazdan/returnWin','WazdanControllerNew@returnWin');
// $app->post('/public/api/wazdan/getFunds','WazdanControllerNew@getFunds');
// $app->post('/public/api/wazdan/gameClose','WazdanControllerNew@gameClose');
// $app->post('/public/api/wazdan/hash','WazdanControllerNew@hashCode');
// $app->post('/public/api/wazdan/authenticate','WazdanController@authenticate');
// $app->post('/public/api/wazdan/getStake','WazdanController@getStake');
// $app->post('/public/api/wazdan/rollbackStake','WazdanController@rollbackState');
// $app->post('/public/api/wazdan/returnWin','WazdanController@returnWin');
// $app->post('/public/api/wazdan/getFunds','WazdanController@getFunds');
// $app->post('/public/api/wazdan/gameClose','WazdanController@gameClose');
// $app->post('/public/api/wazdan/hash','WazdanController@hashCode');
// $app->post('/public/api/wazdan/round/history','WazdanNewV2Controller@getTransactionHistory');
//Newflow V2
$app->post('/public/api/wazdan/authenticate','WazdanNewV2Controller@authenticate');
$app->post('/public/api/wazdan/getStake','WazdanNewV2Controller@getStake');
$app->post('/public/api/wazdan/rollbackStake','WazdanNewV2Controller@rollbackState');
$app->post('/public/api/wazdan/returnWin','WazdanNewV2Controller@returnWin');
$app->post('/public/api/wazdan/getFunds','WazdanNewV2Controller@getFunds');
$app->post('/public/api/wazdan/gameClose','WazdanNewV2Controller@gameClose');
$app->post('/public/api/wazdan/hash','WazdanNewV2Controller@hashCode');
$app->post('/public/api/wazdan/round/history','WazdanNewV2Controller@getTransactionHistory');
// BETRNK LOTTO
$app->post('/public/api/betrnk/lotto', 'BetrnkController@getUrl');

// TIDY
// $app->post('/public/tidy/api/auth', 'TidyController@conecteccc');
$app->post('/public/tidy/api/game/outside/link', 'TidyController@getGameUrl'); // CENTRALIZED
$app->post('/public/tidy/api/checkplayer', 'TidyController@autPlayer');
$app->post('/public/tidy/api/gamelist', 'TidyController@getGamelist');
$app->post('/public/tidy/api/gameurl', 'TidyController@gameUrl');
$app->post('/public/tidy/api/transaction/bet', 'TidyController@gameBet');
$app->post('/public/tidy/api/transaction/rollback', 'TidyController@gameRollback');
$app->post('/public/tidy/api/transaction/win', 'TidyController@gameWin');
$app->post('/public/tidy/api/user/balance', 'TidyController@checkBalance');
$app->get('/public/tidy/api/wagers/outside/list', 'TidyController@checkBet');

//TGG
$app->post('/public/api/tgg/gamelist', 'TGGController@getGamelist'); // launch game 
$app->post('/public/api/tgg/geturl', 'TGGController@getURL');// launch game
$app->post('/public/api/tgg', 'TGGController@index'); // Single Route
$app->post('/public/api/tgg/init', 'TGGController@gameInit');
$app->post('/public/api/tgg/bet', 'TGGController@gameBet');
$app->post('/public/api/tgg/win', 'TGGController@gameWin');
$app->post('/public/api/tgg/refund', 'TGGController@gameRefund');

//PGSoft 
$app->post('/public/api/pgsoft/VerifySession', 'PGSoftController@verifySession');
$app->post('/public/api/pgsoft/Cash/Get', 'PGSoftController@cashGet');
$app->post('/public/api/pgsoft/Cash/TransferOut', 'PGSoftController@transferOut');
$app->post('/public/api/pgsoft/Cash/TransferIn', 'PGSoftController@transferIn');
// PGSoft new flow
$app->post('/public/api/pgsoft/VerifySession', 'PGSoftNewFController@verifySession');
$app->post('/public/api/pgsoft/Cash/Get', 'PGSoftNewFController@cashGet');
$app->post('/public/api/pgsoft/Cash/TransferOut', 'PGSoftNewFController@transferOut');
$app->post('/public/api/pgsoft/Cash/TransferIn', 'PGSoftNewFController@transferIn');

//Booming Games
$app->post('/public/api/booming/gamelist','BoomingGamingController@gameList');
$app->post('/public/api/booming/callback','BoomingGamingController@callBack');
$app->post('/public/api/booming/rollback','BoomingGamingController@rollBack');

// Spade Gaming
// $app->post('/public/api/spade','SpadeController@index');//single route
// $app->post('/public/api/spade/authorize','SpadeController@authorize');
// $app->post('/public/api/spade/getBetCost','SpadeController@getBetCost');
// $app->post('/public/api/spade/getBalance','SpadeController@getBalance');
// $app->post('/public/api/spade/transfer','SpadeController@makeTransfer');
// $app->post('/public/api/spade/getgame','SpadeController@getGameList');
// Spade GamingNEWFLOW
$app->post('/public/api/spade','SpadeNEWController@index');//single route
$app->post('/public/api/spade/authorize','SpadeNEWController@authorize');
$app->post('/public/api/spade/getBetCost','SpadeNEWController@getBetCost');
$app->post('/public/api/spade/getBalance','SpadeNEWController@getBalance');
$app->post('/public/api/spade/transfer','SpadeNEWController@makeTransfer');
$app->post('/public/api/spade/getgame','SpadeNEWController@getGameList');

//MajaGames
$app->post('/public/api/mj/seamless/bet','MajaGamesController@bet');
$app->post('/public/api/mj/seamless/settlement','MajaGamesController@settlement');
$app->post('/public/api/mj/seamless/cancel','MajaGamesController@cancel');
$app->get('/public/api/mj/seamless/getBalance','MajaGamesController@getBalance');

// Spade Curacao Gaming
$app->post('/public/api/spade_curacao','SpadeCuracaoController@index');//single route
$app->post('/public/api/spade_curacao/authorize','SpadeCuracaoController@authorize');
$app->post('/public/api/spade_curacao/getBalance','SpadeCuracaoController@getBalance');
$app->post('/public/api/spade_curacao/transfer','SpadeCuracaoController@makeTransfer');
$app->post('/public/api/spade_curacao/getgame','SpadeCuracaoController@getGameList');
// EPOINT CONTROLLER
// $app->post('/public/api/epoint', 'EpointController@epointAuth'); #/
// $app->post('/public/api/epoint/bitgo', 'EpointController@bitgo'); #/
// EBANCO
// $app->post('/public/api/ebancobanks', 'EbancoController@getAvailableBank'); #/
// $app->post('/public/api/ebancodeposit', 'EbancoController@deposit'); #/
// $app->post('/public/api/ebancodeposithistory', 'EbancoController@deposithistory'); #/
// $app->post('/public/api/ebancodepositinfobyid', 'EbancoController@depositinfo'); #/
// $app->post('/public/api/ebancodepositinfobyselectedid', 'EbancoController@depositinfobyselectedid'); #/
// $app->post('/public/api/ebancodepositreceipt', 'EbancoController@depositReceipt'); #/
$app->post('/public/api/ebancoauth', 'EbancoController@connectTo'); 
$app->post('/public/api/ebancogetbanklist', 'EbancoController@getBankList'); 
$app->post('/public/api/ebancodeposit', 'EbancoController@makeDeposit'); 
$app->post('/public/api/ebancosenddepositreceipt', 'EbancoController@sendReceipt'); 
$app->post('/public/api/ebancodeposittransaction', 'EbancoController@depositInfo'); 
$app->post('/public/api/ebancodeposittransactions', 'EbancoController@depositHistory'); 
$app->post('/public/api/ebancoupdatedeposit', 'EbancoController@updateDeposit'); 
$app->post('/public/api/ebancotest','EbancoController@testrequest');

// Request an access token
// $app->post('/public/oauth/access_token', function() use ($app){
//     return response()->json($app->make('oauth2-server.authorizer')->issueAccessToken());
// });
//paymentgateway routes
$app->get('/public/paymentgateways','Payments\PaymentGatewayController@index');
$app->post('/public/payment','Payments\PaymentGatewayController@paymentPortal');
$app->get('/public/coinpaymentscurrencies','Payments\PaymentGatewayController@getCoinspaymentRate');
$app->get('/public/currencyconversion','CurrencyController@currency');
$app->post('/public/updatetransaction','Payments\PaymentGatewayController@updatetransaction');
$app->post('/public/updatepayouttransaction','Payments\PaymentGatewayController@updatePayoutTransaction');
$app->post('/public/qaicash/depositmethods','Payments\PaymentGatewayController@getQAICASHDepositMethod');
$app->post('/public/qaicash/deposit','Payments\PaymentGatewayController@makeDepositQAICASH');
$app->post('/public/qaicash/payoutmethods','Payments\PaymentGatewayController@getQAICASHPayoutMethod');
$app->post('/public/qaicash/payout','Payments\PaymentGatewayController@makePayoutQAICASH');
$app->post('/public/qaicash/payout/approve','Payments\PaymentGatewayController@approvedPayoutQAICASH');
$app->post('/public/qaicash/payout/reject','Payments\PaymentGatewayController@rejectPayoutQAICASH');
///CoinsPayment Controller
///new payment gateways api
$app->post('/public/payment/launchurl','Payments\PaymentLobbyController@paymentLobbyLaunchUrl');
$app->post('/public/payout/launchurl','Payments\PaymentLobbyController@payoutLobbyLaunchUrl');
$app->post('/public/payment/portal','Payments\PaymentLobbyController@payment');
$app->post('/public/payout/portal','Payments\PaymentLobbyController@payout');
$app->post('/public/payment/tokencheck','Payments\PaymentLobbyController@checkTokenExist');
$app->get('/public/payment/list','Payments\PaymentLobbyController@getPaymentMethod');
$app->get('/public/payout/list','Payments\PaymentLobbyController@getPayoutMethod');
$app->post('/public/payment/check','Payments\PaymentLobbyController@minMaxAmountChecker');
$app->post('/public/payment/paymongoupdate','Payments\PaymentLobbyController@paymongoUpdateTransaction');
$app->post('/public/payment/checktransaction','Payments\PaymentLobbyController@checkPayTransactionContent');

$app->post('/public/payment/transaction','Payments\PaymentLobbyController@getPayTransactionDetails');
$app->post('/public/payment/cancel','Payments\PaymentLobbyController@cancelPayTransaction');
$app->post('/public/currency/convert','Payments\PaymentLobbyController@currencyConverter');
//GameLobby
$app->post('/public/game/demo','GameLobby\DemoGameController@GameDemo');
$app->get('/public/game/launchurl/playforfun', 'GameLobby\GameDemoClientController@gameLaunchDemo');

# Tiger Games API
$app->post('/public/game/providers','GameLobby\GameLobbyController@getProviderList');
$app->get('/public/game/upcoming','GameLobby\GameLobbyController@getUpcomingGames');
$app->get('/public/game/list','GameLobby\GameLobbyController@getGameList');
$app->get('/public/game/provider/{provider_name}','GameLobby\GameLobbyController@getProviderDetails');
$app->post('/public/game/launchurl','GameLobby\GameLobbyController@gameLaunchUrl');
$app->post('/public/gamelobby/launchurl','GameLobby\GameLobbyController@gameLobbyLaunchUrl');
// $app->get('game/balance','GameLobby\GameLobbyController@getPlayerBalance'); # Commented 4-8-21 -Al
// $app->post('/public/game/addfavorite','GameLobby\GameFavoriteController@index'); # Commented 4-8-21 -Al
// $app->post('/public/game/playerinfo','GameLobby\GameFavoriteController@playerInfo'); # Commented 4-8-21 -Al
// $app->post('/public/game/playerfavoritelist','GameLobby\GameFavoriteController@playerFavorite'); # Commented 4-8-21 -Al
// $app->get('game/newestgames','GameLobby\GameInfoController@getNewestGames'); # Commented 4-8-21 -Al
// $app->get('game/mostplayed','GameLobby\GameInfoController@getMostPlayed'); # Commented 4-8-21 -Al
// $app->post('/public/game/demogame','GameLobby\GameInfoController@getDemoGame'); # Commented 4-8-21 -Al
// $app->post('/public/game/suggestions','GameLobby\GameInfoController@getGameSuggestions'); // DEPRECATED 
// $app->get('game/topcharts','GameLobby\GameInfoController@getTopGames'); # Commented 4-8-21 -Al 
$app->get('/public/game/topcharts/numberone','GameLobby\GameInfoController@getTopProvider'); # Commented 4-8-21 -Al 
$app->post('/public/game/playerdetailsrequest','GameLobby\GameInfoController@getClientPlayerDetails'); # Commented 4-8-21 -Al
// $app->post('/public/game/betlist','GameLobby\GameInfoController@getBetList'); # Commented 4-8-21 -Al
$app->post('/public/game/query','GameLobby\QueryController@queryData');
// IWallet
// $app->post('/public/api/iwallet/makedeposit','IWalletController@makeDeposit');
$app->post('/public/api/iwallet/makesettlement','IWalletController@makeSettlement');
// $app->post('/public/api/iwallet/makepayment','IWalletController@makePayment');
$app->post('/public/api/iwallet/makeremittance','IWalletController@makeRemittance');
//WMT
$app->post('/public/api/wmt/makesettlement','Payments\WMTController@makeSettlement');

$app->post('/public/game/lang','GameLobby\GameLobbyController@getLanguage');

$app->post('/public/payment/catpay/callBack','Payments\PaymentGatewayController@catpayCallback');


// Habanero 
$app->post('/public/hbn/api/auth','HabaneroController@playerdetailrequest');
$app->post('/public/hbn/api/tx','HabaneroController@fundtransferrequest');
$app->post('/public/hbn/api/query','HabaneroController@queryrequest');


// Pragmatic PLay
$app->post('/public/api/pp/authenticate','PragmaticPLayController@authenticate');
$app->post('/public/api/pp/balance','PragmaticPLayController@balance');
$app->post('/public/api/pp/bet','PragmaticPLayController@bet');
$app->post('/public/api/pp/result','PragmaticPLayController@result');
$app->post('/public/api/pp/refund','PragmaticPLayController@refund');
$app->post('/public/api/pp/bonusWin','PragmaticPLayController@bonusWin');
$app->post('/public/api/pp/jackpotWin','PragmaticPLayController@jackpotWin');
$app->post('/public/api/pp/promoWin','PragmaticPLayController@promoWin');
$app->post('/public/api/pp/endRound','PragmaticPLayController@endRound');
$app->post('/public/api/pp/getBalancePerGame','PragmaticPLayController@getBalancePerGame');
$app->post('/public/api/pp/session/expired','PragmaticPLayController@sessionExpired');
//NEWFLOW PRAGMATIOC PLAY
// Pragmatic PLay
// $app->post('/public/api/pp/authenticate','PragmaticPlayNEWController@authenticate');
// $app->post('/public/api/pp/balance','PragmaticPlayNEWController@balance');
// $app->post('/public/api/pp/bet','PragmaticPlayNEWController@bet');
// $app->post('/public/api/pp/result','PragmaticPlayNEWController@result');
// $app->post('/public/api/pp/refund','PragmaticPlayNEWController@refund');
// $app->post('/public/api/pp/bonusWin','PragmaticPlayNEWController@bonusWin');
// $app->post('/public/api/pp/jackpotWin','PragmaticPlayNEWController@jackpotWin');
// $app->post('/public/api/pp/promoWin','PragmaticPlayNEWController@promoWin');
// $app->post('/public/api/pp/endRound','PragmaticPlayNEWController@endRound');
// $app->post('/public/api/pp/getBalancePerGame','PragmaticPlayNEWController@getBalancePerGame');
// $app->post('/public/api/pp/session/expired','PragmaticPlayNEWController@sessionExpired');




// $app->get('al-games','AlController@insertGamesTapulanMode');


// Yggdrasil 
$app->get('/public/api/ygg/playerinfo.json','YGGController@playerinfo');
$app->get('/public/api/ygg/wager.json','YGGController@wager');
$app->get('/public/api/ygg/cancelwager.json','YGGController@cancelwager');
$app->get('/public/api/ygg/appendwagerresult.json','YGGController@appendwagerresult');
$app->get('/public/api/ygg/endwager.json','YGGController@endwager');
$app->get('/public/api/ygg/campaignpayout.json','YGGController@campaignpayout');
$app->get('/public/api/ygg/getbalance.json','YGGController@getbalance');
// ygg local
$app->post('/public/api/ygg/playerinfo/test','YGGController@playerinfo');
$app->post('/public/api/ygg/wager/test','YGGController@wager');
$app->post('/public/api/ygg/cancelwager/test','YGGController@cancelwager');
$app->post('/public/api/ygg/appendwagerresult/test','YGGController@appendwagerresult');
$app->post('/public/api/ygg/endwager/test','YGGController@endwager');
$app->post('/public/api/ygg/campaignpayout/test','YGGController@campaignpayout');
$app->post('/public/api/ygg/getbalance/test','YGGController@getbalance');


//IFRAME URL ENDPOINTS
$app->post('/public/iframe/auth/token','Iframe\AuthenticationController@checkTokenExist');
$app->post('/public/iframe/close','Iframe\AuthenticationController@iframeClosed');
//MicroGaming EndPoints
$app->post('/public/api/microgaming/launch','MicroGamingController@launchGame');
$app->post('/public/api/microgaming/makeDeposit','MicroGamingController@makeDeposit');
$app->post('/public/api/microgaming/makeWithdraw','MicroGamingController@makeWithdraw');
$app->post('/public/api/microgaming/getPlayerBalance','MicroGamingController@getPlayerBalance');

//Evolution Gaming Endpoints
$app->post('/public/api/evogaming2/check','EvolutionController@authentication');
$app->post('/public/api/evogaming2/balance','EvolutionController@balance');
$app->post('/public/api/evogaming2/debit','EvolutionController@debit');
$app->post('/public/api/evogaming2/credit','EvolutionController@credit');
$app->post('/public/api/evogaming2/cancel','EvolutionController@cancel');
$app->post('/public/api/evogaming2/sid','EvolutionController@sid');
$app->post('/public/api/evogaming2/launch','EvolutionController@gameLaunch');
$app->post('/public/api/evogaming2/internalrefund','EvolutionController@internalrefund');

//Evolution Gaming Endpoints (IN-USED)
$app->post('/public/api/evogaming/check','EvolutionMDBController@authentication');
$app->post('/public/api/evogaming/balance','EvolutionMDBController@balance');
$app->post('/public/api/evogaming/debit','EvolutionMDBController@debit');
$app->post('/public/api/evogaming/credit','EvolutionMDBController@credit');
$app->post('/public/api/evogaming/cancel','EvolutionMDBController@cancel');
$app->post('/public/api/evogaming/sid','EvolutionMDBController@sid');
$app->post('/public/api/evogaming/launch','EvolutionMDBController@gameLaunch');
$app->post('/public/api/evogaming/internalrefund','EvolutionMDBController@internalrefund');

//Golden F Game System
$app->post('/public/api/gf/Player/Create','GoldenFController@auth');
$app->post('/public/api/gf/GetPlayerBalance','GoldenFController@GetPlayerBalance');
$app->post('/public/api/gf/TransferIn','GoldenFController@TransferIn');
$app->post('/public/api/gf/TransferOut','GoldenFController@TransferOut');
$app->post('/public/api/gf/Bet/Record/Get','GoldenFController@BetRecordGet');
$app->post('/public/api/gf/Bet/Record/Player/Get','GoldenFController@BetRecordPlayerGet');
$app->post('/public/api/gf/Transaction/Record/Get','GoldenFController@TransactionRecordGet');
$app->post('/public/api/gf/Transaction/Record/Player/Get','GoldenFController@TransactionRecordPlayerGet');
$app->post('/public/api/gf/Bet/Record/Detail','GoldenFController@BetRecordDetail');

# Golden F Seamless Wallet
$app->post('/public/api/gf/sw/player-balance', 'GoldenFController@swPlayerBalance');
$app->post('/public/api/gf/sw/transfer-out', 'GoldenFController@swTransferOut');
$app->post('/public/api/gf/sw/transfer-in', 'GoldenFController@swTransferIn');
$app->post('/public/api/gf/sw/force-transfer-out', 'GoldenFController@swforceTransferOut');
$app->post('/public/api/gf/sw/query-translog', 'GoldenFController@swQuerytranslog');


// MancalaGaming Endpoints
$app->post('/public/api/mancala/Balance', 'MancalaGamingController@getBalance');
$app->post('/public/api/mancala/Credit', 'MancalaGamingController@debitProcess');
$app->post('/public/api/mancala/Debit', 'MancalaGamingController@creditProcess');
$app->post('/public/api/mancala/Refund', 'MancalaGamingController@rollbackTransaction');

$app->post('/public/api/currency','AlController@currency');


// NETENT Direct
$app->get('/public/api/netent/walletserver/players/{player}/account/currency','NetEntController@currency');
$app->get('/public/api/netent/walletserver/players/{player}/account/balance','NetEntController@balance');
$app->post('/public/api/netent/walletserver/players/{player}/account/withdraw','NetEntController@withdraw');//debit
$app->post('/public/api/netent/walletserver/players/{player}/account/deposit','NetEntController@deposit');
$app->delete('/public/api/netent/walletserver/players/{player}/account/withdraw','NetEntController@withdraw');//debit


//ORXY FUNDSRANFER
$app->post('/public/api/oryx/readWriteProcess', 'OryxGamingController@readWriteProcess');
$app->post('/public/api/oryx/fundTransfer', 'OryxGamingController@fundTransfer');
$app->post('/public/tigergames/{type}/bg-fundtransfer','FundtransferProcessorController@backgroundProcessDebitCreditFund');
$app->post('/public/tigergames/bg-fundtransfer','FundtransferProcessorController@bgFundTransfer');


$app->post('/public/tigergames/fundtransfer','FundtransferProcessorController@fundTransfer');
$app->post('/public/tigergames/fundtransfer-timeout','FundtransferProcessorController@fundTransferTimout');


// SLOTMILL
$app->post('/public/api/slotmill/playerinfo.json','SlotmillNew@playerinfo');
$app->post('/public/api/slotmill/wager.json','SlotmillNew@wager'); // bet 
$app->post('/public/api/slotmill/cancelwager.json','SlotmillNew@cancelwager');
$app->post('/public/api/slotmill/appendwagerresult.json','SlotmillNew@appendwagerresult'); //bonus
$app->post('/public/api/slotmill/appendwagergoods.json','SlotmillNew@appendwagergoods'); //bonus
$app->post('/public/api/slotmill/endwager.json','SlotmillNew@endwager'); // win 
$app->post('/public/api/slotmill/reverse.json','SlotmillNew@reverse');


//PGVIRTUAL
$app->get('/public/api/pgvirtual/validate/{auth_key}/{game_session_token}','PGCompanyController@auth_player');
$app->get('/public/api/pgvirtual/keepalive/{auth_key}/{game_session_token}','PGCompanyController@keepalive');
$app->post('/public/api/pgvirtual/placebet/{auth_key}/{game_session_token}','PGCompanyController@placebet');
$app->post('/public/api/pgvirtual/cancelbet/{auth_key}','PGCompanyController@cancelbet');
$app->post('/public/api/pgvirtual/syncbet/{auth_key}','PGCompanyController@syncbet');
$app->post('/public/api/pgvirtual/paybet/{auth_key}','PGCompanyController@paybet');

// CUT CALL FOR THE WIN CREDIT PROCESS
$app->post('/public/tigergames/bg-fundtransferV2','FundtransferProcessorController@bgFundTransferV2');
$app->post('/public/tigergames/bg-bgFundTransferV2MultiDB','FundtransferProcessorController@bgFundTransferV2MultiDB');
// ONLYPLAY
$app->post('/public/api/onlyplay/info','OnlyPlayController@getBalance');
$app->post('/public/api/onlyplay/bet','OnlyPlayController@debitProcess');
$app->post('/public/api/onlyplay/win','OnlyPlayController@creditProcess');
$app->post('/public/api/onlyplay/cancel','OnlyPlayController@rollbackProcess');
$app->post('/public/signature','OnlyPlayController@createSignature');

//JUSTPLAY
$app->get('/public/api/justplay/callback', 'JustPlayController@callback'); 

// BGaming Single Controller Endpoints
$app->post('/public/api/bgaming/play', 'BGamingController@gameTransaction');
$app->post('/public/api/bgaming/freespins', 'BGamingController@freeSpinSettlement');
$app->post('/public/api/bgaming/rollback', 'BGamingController@gameTransaction');
$app->post('/public/api/bgaming/checkSign', 'BGamingController@signatureChecker');

// Five Men
$app->post('/public/api/5men','FiveMenController@index');
$app->post('/public/api/5men/rtp','FiveMenController@getRTP');

// $app->post('/public/api/5men/gamelist', 'FiveMenController@getGamelist'); // launch game 
// $app->post('/public/api/5men/geturl', 'FiveMenController@getURL');// launch game
// $app->post('/public/api/5men', 'FiveMenController@index'); // Single Route
// $app->post('/public/api/5men/init', 'FiveMenController@gameInit');
// $app->post('/public/api/5men/bet', 'FiveMenController@gameBet');
// $app->post('/public/api/5men/win', 'FiveMenController@gameWin');
// $app->post('/public/api/5men/refund', 'FiveMenController@gameRefund');

// Playstar
$app->get('/public/api/playstar/auth','PlayStarController@getAuth');
$app->get('/public/api/playstar/bet','PlayStarController@getBet');
$app->get('/public/api/playstar/result','PlayStarController@getResult');
$app->get('/public/api/playstar/refundbet','PlayStarController@getRefundBet');
$app->get('/public/api/playstar/getbalance','PlayStarController@getBalance');



// TTG/TopTrendGaming
$app->post('/public/api/toptrendgaming/test','TTGController@testing');
$app->post('/public/api/toptrendgaming/getbalance','TTGController@getBalance');
$app->post('/public/api/toptrendgaming/fundstransfer','TTGController@fundTransferTTG');
$app->post('/public/api/login','TTGController@privateLogin');




// Ozashiki Seamless and Semi Transfer Endpoints
// $app->post('/public/api/ozashiki/fetchbalance', 'Ozashiki\MainController@getBalance');
// $app->post('/public/api/ozashiki/bet', 'Ozashiki\MainController@debitProcess');
// $app->post('/public/api/ozashiki/win', 'Ozashiki\MainController@creditProcess');
// $app->post('/public/api/ozashiki/betrollback', 'Ozashiki\MainController@rollbackTransaction');

// NolimitCity Single Controller Endpoints
$app->post('/public/api/nolimitcity', 'NolimitController@index');
$app->post('/public/api/nolimitcity/eldoah', 'NolimitController@index');
$app->post('/public/api/nolimitcity/konibet', 'NolimitController@index');


//SmartSoft Gaming
$app->post('/public/api/smartsoft_gaming/ActivateSession', 'SmartsoftGamingNFController@ActiveSession');
$app->get('/public/api/smartsoft_gaming/GetBalance', 'SmartsoftGamingNFController@GetBalance');
$app->post('/public/api/smartsoft_gaming/Deposit', 'SmartsoftGamingNFController@Deposit');
$app->post('/public/api/smartsoft_gaming/Withdraw', 'SmartsoftGamingNFController@Withdraw');
$app->post('/public/api/smartsoft_gaming/RollbackTransaction', 'SmartsoftGamingNFController@Rollback');
$app->post('/public/api/smartsoft_gaming/HashValue','SmartSoftGamingNFController@getHashValue');


//TIGER GAMES TRANSFER WALLET
$app->group(['prefix' => '/public/tw/api/'], function () use ($app) {
    //transfer wallet endpoint
    $app->post('game/launchurl', 'TransferWalletAggregator\GameLobbyController@gameLaunchUrl');
    $app->post('tw_wallet/createPlayer', 'TransferWalletAggregator\WalletDetailsController@createPlayerBalance');
    $app->post('tw_wallet/getBalance', 'TransferWalletAggregator\WalletDetailsController@getPlayerBalance');
    $app->post('tw_wallet/deposit', 'TransferWalletAggregator\WalletDetailsController@makeTransferWallerDeposit');
    $app->post('tw_wallet/withdraw', 'TransferWalletAggregator\WalletDetailsController@makeTransferWallerWithdraw');
    $app->post('tw_wallet/bethistory', 'TransferWalletAggregator\WalletDetailsController@getBetHistory');
    $app->post('tw_wallet/transactionchecker', 'TransferWalletAggregator\WalletDetailsController@checkTransactionDetails');
    $app->post('tw_wallet/gamesummary', 'TransferWalletAggregator\WalletDetailsController@gameSummaryPlayerDaily');
    $app->post('tw_wallet/getstatusTransaction', 'TransferWalletAggregator\WalletDetailsController@getstatusTransaction');

    //seamless wallet fundster endpoint OPERATOR
    $app->post('sm_wallet/getPlayerDetails', 'TransferWalletAggregator\DetailsAndFundTransferController@getPlayerDetails');
    $app->post('sm_wallet/fundTransfer', 'TransferWalletAggregator\DetailsAndFundTransferController@fundTransfer');
    $app->post('sm_wallet/transactionchecker', 'TransferWalletAggregator\DetailsAndFundTransferController@transactionchecker');


    //EXPEREMENT
    $app->post('sm_wallet/getPlayerDetailsExpo', 'TransferWalletAggregator\DetailsAndFundTransferControllerEXPERIMENT@getPlayerDetails');
    $app->post('sm_wallet/fundTransferExpo', 'TransferWalletAggregator\DetailsAndFundTransferControllerEXPERIMENT@fundTransfer');
    $app->post('sm_wallet/transactioncheckerExpo', 'TransferWalletAggregator\DetailsAndFundTransferControllerEXPERIMENT@transactionchecker');

});

// DragonGaming Endpoints
$app->post('/public/api/dragongaming/get_session', 'DragonGamingController@getSession');
$app->post('/public/api/dragongaming/get_balance', 'DragonGamingController@getBalance');
$app->post('/public/api/dragongaming/debit', 'DragonGamingController@debitProcess');
$app->post('/public/api/dragongaming/credit', 'DragonGamingController@creditProcess');
$app->post('/public/api/dragongaming/refund', 'DragonGamingController@rollbackTransaction');


// PlayTech
$app->post('/public/api/playtech/auth', 'PlayTechController@auth');
$app->post('/public/api/playtech/balance', 'PlayTechController@getBalance');
$app->post('/public/api/playtech/transaction', 'PlayTechController@transaction');
// FunkyGames
$app->post('/public/FunkyGames/GetGameList', 'FunkyGamesController@gameList');
$app->post('/public/Funky/User/GetBalance','FunkyGamesController@GetBalance');
$app->post('/public/Funky/Bet/CheckBet','FunkyGamesController@CheckBet');
$app->post('/public/Funky/Bet/PlaceBet','FunkyGamesController@PlaceBet');
$app->post('/public/Funky/Bet/SettleBet','FunkyGamesController@SettleBet');
$app->post('/public/Funky/Bet/CancelBet','FunkyGamesController@CancelBet');

// Amuse Gaming
$app->post('/public/GetPlayerBalance', 'AmuseGamingNew@GetPlayerBalance');
$app->post('/public/WithdrawAndDeposit', 'AmuseGamingNew@WithdrawAndDeposit');
$app->post('/public/Cancel', 'AmuseGamingNew@Cancel');
$app->post('/public/api/AmuseGaming/getGamelist', 'AmuseGamingNew@getGamelist');
//FREGAME OR FREEROUND BY PROVIDER
$app->post('/public/game/freeround/give','FreeRound\FreeRoundController@freeRoundController');
$app->post('/public/game/freeround/getQuery','FreeRound\FreeRoundController@getQuery');
$app->post('/public/game/freeround/cancel','FreeRound\CancelFreeRoundController@cancelfreeRoundController');


// Crash Gaming (TigerGames)
$app->post('/public/api/crashgame/balance', 'CrashGameController@Balance');
$app->post('/public/api/crashgame/debit', 'CrashGameController@Debit');
$app->post('/public/api/crashgame/credit', 'CrashGameController@Credit');
$app->post('/public/api/crashgame/refund', 'CrashGameController@Refund');
$app->post('/public/api/crashgame/cancel', 'CrashGameController@Cancel');

// QUickSpins Direct
$app->post('/public/api/quickspin/verifyToken', 'QuickspinDirectController@Authenticate');
$app->post('/public/api/quickspin/getBalance', 'QuickspinDirectController@getBalance');
$app->post('/public/api/quickspin/withdraw', 'QuickspinDirectController@betProcess');
$app->post('/public/api/quickspin/deposit', 'QuickspinDirectController@winProcess');
$app->post('/public/api/quickspin/rollback', 'QuickspinDirectController@rollbackProcess');
$app->post('/public/api/quickspin/fs_win', 'QuickspinDirectController@freeRound');

// SpearHead
$app->post('/public/api/spearhead/GetAccount', 'SpearHeadControllerOld@getAccount');
$app->post('/public/api/spearhead/GetBalance', 'SpearHeadControllerOld@getBalance');
$app->post('/public/api/spearhead','SpearHeadControllerOld@walletApiReq');

//IDNPOKER
$app->post('/public/api/idnpoker/makeDeposit', 'IDNPokerController@makeDeposit');
$app->post('/public/api/idnpoker/makeWithdraw', 'IDNPokerController@makeWithdraw');
$app->post('/public/api/idnpoker/getPlayerBalance', 'IDNPokerController@getPlayerBalance');
$app->post('/public/api/idnpoker/getPlayerWalletBalance', 'IDNPokerController@getPlayerWalletBalance');
$app->post('/public/api/idnpoker/getTransaction', 'IDNPokerController@getTransactionHistory');
$app->post('/public/api/idnpoker/localtransaction', 'IDNPokerController@TransactionHistory'); // testing
$app->post('/public/api/idnpoker/summaryplayer', 'IDNPokerController@TransactionPlayerSummary'); // testing
$app->post('/public/api/idnpoker/retryWithdrawalWallet', 'IDNPokerController@retryWithdrawalRestriction');
$app->post('/public/api/idnpoker/renewSession', 'IDNPokerController@renewSession');
// Transfer Wallet New Update
$app->post('/public/api/transferwallet/renewsession','TransferWalletController@renewSession');
$app->post('/public/api/transferwallet/createsession','TransferWalletController@createWalletSession');
$app->post('/public/api/transferwallet/getPlayerBalance','TransferWalletController@getPlayerBalance');
$app->post('/public/api/transferwallet/getPlayerWalletBalance','TransferWalletController@getPlayerWalletBalance');
$app->post('/public/api/transferwallet/makeWithdraw','TransferWalletController@makeWithdraw');
$app->post('/public/api/transferwallet/makeDeposit','TransferWalletController@makeDeposit');

// PLAYER OPERATOR DETAILS
$app->group(['prefix' => 'public/api', 'middleware' => ['oauth', 'json_accept']], function() use ($app) {
    $app->post('player-operator-details','PlayerOperatorPortController@getPlayerOperatorDetails');
});


// Yggdrasil 
// $app->get('/public/playerinfo.json','YGG002Controller@playerinfo');
// $app->get('/public/wager.json','YGG002Controller@wager');
// $app->get('/public/cancelwager.json','YGG002Controller@cancelwager');
// $app->get('/public/appendwagerresult.json','YGG002Controller@appendwagerresult');
// $app->get('/public/endwager.json','YGG002Controller@endwager');
// $app->get('/public/campaignpayout.json','YGG002Controller@campaignpayout');
// $app->get('/public/getbalance.json','YGG002Controller@getbalance');
// ygg local
$app->post('/public/playerinfo/test','YGG002Controller@playerinfo');
$app->post('/public/wager/test','YGG002Controller@wager');
$app->post('/public/cancelwager/test','YGG002Controller@cancelwager');
$app->post('/public/appendwagerresult/test','YGG002Controller@appendwagerresult');
$app->post('/public/endwager/test','YGG002Controller@endwager');
$app->post('/public/campaignpayout/test','YGG002Controller@campaignpayout');
$app->post('/public/getbalance/test','YGG002Controller@getbalance');
//Yggdrasil Newflow v2 

$app->get('/public/playerinfo.json','YGG002NewV2Controller@playerinfo');
$app->get('/public/wager.json','YGG002NewV2Controller@wager');
$app->get('/public/cancelwager.json','YGG002NewV2Controller@cancelwager');
$app->get('/public/appendwagerresult.json','YGG002NewV2Controller@appendwagerresult');
$app->get('/public/endwager.json','YGG002NewV2Controller@endwager');
$app->get('/public/campaignpayout.json','YGG002NewV2Controller@campaignpayout');
$app->get('/public/getbalance.json','YGG002NewV2Controller@getbalance');
// Yggdrasil NEWLOW
// $app->get('/public/playerinfo.json','YGG002ControllerNEW@playerinfo');
// $app->get('/public/wager.json','YGG002ControllerNEW@wager');
// $app->get('/public/cancelwager.json','YGG002ControllerNEW@cancelwager');
// $app->get('/public/appendwagerresult.json','YGG002ControllerNEW@appendwagerresult');
// $app->get('/public/endwager.json','YGG002ControllerNEW@endwager');
// $app->get('/public/campaignpayout.json','YGG002ControllerNEW@campaignpayout');
// $app->get('/public/getbalance.json','YGG002ControllerNEW@getbalance');
// // ygg local NEWFLOW
// $app->post('/public/playerinfo/test','YGG002ControllerNEW@playerinfo');
// $app->post('/public/wager/test','YGG002ControllerNEW@wager');
// $app->post('/public/cancelwager/test','YGG002ControllerNEW@cancelwager');
// $app->post('/public/appendwagerresult/test','YGG002ControllerNEW@appendwagerresult');
// $app->post('/public/endwager/test','YGG002ControllerNEW@endwager');
// $app->post('/public/campaignpayout/test','YGG002ControllerNEW@campaignpayout');
// $app->post('/public/getbalance/test','YGG002ControllerNEW@getbalance');

//BOTA
// $app->post('/public/api/bota', 'BOTAController@index');
// $app->post('/public/api/bota/callbackBalance', 'BOTAControllerNEW@index');
$app->post('/public/api/bota/callbackBalance', 'BOTAController@index');
//DOWINN


$app->post('/public/api/dowinn', 'DOWINNController@index');//SINGLE END POINT
$app->post('/public/api/dowinn/balance', 'DOWINNController@balance');
$app->post('/public/api/dowinn/debit', 'DOWINNController@bet');
$app->post('/public/api/dowinn/credit', 'DOWINNController@payment');
$app->post('/public/api/dowinn/cancel', 'DOWINNController@cancel');
$app->post('/public/api/dowinn/tip', 'DOWINNController@tip');
$app->post('/public/api/dowinn/limits', 'DOWINNController@limitList');
$app->post('/public/api/dowinn/history', 'DOWINNController@viewHistory');
//NEWFLOW DOWINN
// $app->post('/public/api/dowinn', 'DOWINNNEWController@index');//SINGLE END POINT
// $app->post('/public/api/dowinn/balance', 'DOWINNNEWController@balance');
// $app->post('/public/api/dowinn/debit', 'DOWINNNEWController@bet');
// $app->post('/public/api/dowinn/credit', 'DOWINNNEWController@payment');
// $app->post('/public/api/dowinn/cancel', 'DOWINNNEWController@cancel');
// $app->post('/public/api/dowinn/tip', 'DOWINNNEWController@tip');
// $app->post('/public/api/dowinn/limits', 'DOWINNNEWController@limitList');
// $app->post('/public/api/dowinn/history', 'DOWINNNEWController@viewHistory');
//Load tester
$app->post('/public/api/loadtesting', 'LoadTesterQueryCLientCallController@ProcessTransaction');
//Naga Games
$app->post('/public/nagagames/api/auth', 'NagaGamesController@auth');
$app->post('/public/nagagames/api/getBalance', 'NagaGamesController@getBalance');
$app->post('/public/nagagames/api/balance', 'NagaGamesController@getBalance');
$app->post('/public/nagagames/api/payout', 'NagaGamesController@payout');
$app->post('/public/nagagames/api/placeBet', 'NagaGamesController@placeBet');
$app->post('/public/nagagames/api/cancelBet', 'NagaGamesController@cancelBet');
$app->post('/public/nagagames/api/betStatus', 'NagaGamesController@betStatus');
//newflow naga
// $app->post('/public/nagagames/api/auth', 'NagaGamesNewController@auth');
// $app->post('/public/nagagames/api/getBalance', 'NagaGamesNewController@getBalance');
// $app->post('/public/nagagames/api/balance', 'NagaGamesNewController@getBalance');
// $app->post('/public/nagagames/api/payout', 'NagaGamesNewController@payout');
// $app->post('/public/nagagames/api/placeBet', 'NagaGamesNewController@placeBet');
// $app->post('/public/nagagames/api/cancelBet', 'NagaGamesNewController@cancelBet');
// $app->post('/public/nagagames/api/betStatus', 'NagaGamesNewController@betStatus');
//GamingCorps
$app->post('/public/api/gamingcorps/authenticate', 'GamingCorpsController@Verify');
// Qtech 
$app->get('/public/qtech/api/accounts/{id}/session', 'QTechController@verifySession');
$app->get('/public/qtech/api/accounts/{id}/balance', 'QTechController@getBalance');
$app->post('/public/qtech/api/transactions', 'QTechController@transactions');
$app->post('/public/qtech/api/transactions/rollback', 'QTechController@rollback');
$app->post('/public/qtech/api/bonus/status', 'QTechController@promoStatus');
$app->post('/public/qtech/api/bonus/rewards', 'QTechController@bonusRewards');

$app->get('/public/qtech/api/accounts/{id}/session', 'QTechController@verifySession');
$app->get('/public/qtech/api/accounts/{id}/balance', 'QTechController@getBalance');
$app->post('/public/qtech/api/transactions', 'QTechController@transactions');
$app->post('/public/qtech/api/transactions/rollback', 'QTechController@rollback');
$app->post('/public/qtech/api/bonus/status', 'QTechController@promoStatus');
$app->post('/public/qtech/api/bonus/rewards', 'QTechController@promoStatus');
//Hacksaw
$app->post('/public/api/hacksaw', 'HacksawGamingController@hacksawIndex');
$app->get('/public/api/hacksaw/gamelist', 'HacksawGamingController@Gamelist');

//Micro Gaming Seamless Wallet
$app->post('/public/api/mg/login', 'MicroGamingSeamlessController@Login');
$app->post('/public/api/mg/getbalance', 'MicroGamingSeamlessController@GetBalance');
$app->post('/public/api/mg/updatebalance', 'MicroGamingSeamlessController@Transactions');
$app->post('/public/api/mg/rollback', 'MicroGamingSeamlessController@Rollback');


//Pragmatic Play Version 2
$app->post('/public/api/v2/pp/authenticate','PragmaticPlayV2Controller@authenticate');
$app->post('/public/api/v2/pp/balance','PragmaticPlayV2Controller@balance');
$app->post('/public/api/v2/pp/bet','PragmaticPlayV2Controller@bet');
$app->post('/public/api/v2/pp/result','PragmaticPlayV2Controller@result');
$app->post('/public/api/v2/pp/refund','PragmaticPlayV2Controller@refund');
$app->post('/public/api/v2/pp/bonusWin','PragmaticPlayV2Controller@bonusWin');
$app->post('/public/api/v2/pp/jackpotWin','PragmaticPlayV2Controller@jackpotWin');
$app->post('/public/api/v2/pp/promoWin','PragmaticPlayV2Controller@promoWin');
$app->post('/public/api/v2/pp/endRound','PragmaticPlayV2Controller@endRound');
$app->post('/public/api/v2/pp/getBalancePerGame','PragmaticPlayV2Controller@getBalancePerGame');
$app->post('/public/api/v2/pp/session/expired','PragmaticPlayV2Controller@sessionExpired');


//Relax Gaming
$app->post('/public/api/relax/verifyToken','RelaxGamingController@verifyToken');
$app->post('/public/api/relax/getBalance','RelaxGamingController@getBalance');
$app->post('/public/api/relax/withdraw','RelaxGamingController@Bet');
$app->post('/public/api/relax/deposit','RelaxGamingController@Win');
$app->post('/public/api/relax/rollback','RelaxGamingController@rollback');
$app->post('/public/api/relax/freespins/add','RelaxGamingController@FreeRounds');
$app->post('/public/api/relax/freespins/querypossiblecounts','RelaxGamingController@FreeRoundsCounts');
$app->post('/public/api/relax/featuretriggers/add','RelaxGamingController@FeatureTrigger');
$app->post('/public/api/relax/notifyFreespinsCancel','RelaxGamingController@FreeSpinCancel'); 



