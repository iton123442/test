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
$app->post('/al','AlController@index'); // TESTING!
$app->post('/alplayer','AlController@checkCLientPlayer'); // TESTING!
$app->post('/gg','AlController@tapulan'); // TESTING!
// Posts
$app->get('/posts','PostController@index');
$app->post('/posts','PostController@store');
$app->get('/posts/{post_id}','PostController@show');
$app->put('/posts/{post_id}', 'PostController@update');
$app->patch('/posts/{post_id}', 'PostController@update');
$app->delete('/posts/{post_id}', 'PostController@destroy');
// Users
$app->get('/users/', 'UserController@index');
$app->post('/users/', 'UserController@store');
$app->get('/users/{user_id}', 'UserController@show');
$app->put('/users/{user_id}', 'UserController@update');
$app->patch('/users/{user_id}', 'UserController@update');
$app->delete('/users/{user_id}', 'UserController@destroy');
// Comments
$app->get('/comments', 'CommentController@index');
$app->get('/comments/{comment_id}', 'CommentController@show');
// Comment(s) of a post
$app->get('/posts/{post_id}/comments', 'PostCommentController@index');
$app->post('/posts/{post_id}/comments', 'PostCommentController@store');
$app->put('/posts/{post_id}/comments/{comment_id}', 'PostCommentController@update');
$app->patch('/posts/{post_id}/comments/{comment_id}', 'PostCommentController@update');
$app->delete('/posts/{post_id}/comments/{comment_id}', 'PostCommentController@destroy');

// Request an access token
$app->post('/oauth/access_token', function() use ($app){
    return response()->json($app->make('oauth2-server.authorizer')->issueAccessToken());
});

// Player Details Request
$app->post('/api/playerdetailsrequest/', 'PlayerDetailsController@show');

// Fund Transfer Request
$app->post('/api/fundtransferrequest/', 'FundTransferController@process');

// Solid Gaming Endpoints
$app->post('/api/solid/{brand_code}/authenticate', 'SolidGamingController@authPlayer');
$app->post('/api/solid/{brand_code}/playerdetails', 'SolidGamingController@getPlayerDetails');
$app->post('/api/solid/{brand_code}/balance', 'SolidGamingController@getBalance');
$app->post('/api/solid/{brand_code}/debit', 'SolidGamingController@debitProcess');
$app->post('/api/solid/{brand_code}/credit', 'SolidGamingController@creditProcess');
$app->post('/api/solid/{brand_code}/debitandcredit', 'SolidGamingController@debitAndCreditProcess');
$app->post('/api/solid/{brand_code}/rollback', 'SolidGamingController@rollbackTransaction');
$app->post('/api/solid/{brand_code}/endround', 'SolidGamingController@endPlayerRound');
$app->post('/api/solid/{brand_code}/endsession', 'SolidGamingController@endPlayerSession');

// Oryx Gaming Endpoints
$app->post('/api/oryx/{brand_code}/tokens/{token}/authenticate', 'OryxGamingController@authPlayer');
$app->post('/api/oryx/{brand_code}/players/{player_id}/balance', 'OryxGamingController@getBalance');
$app->post('/api/oryx/{brand_code}/game-transaction', 'OryxGamingController@gameTransaction');
$app->put('/api/oryx/{brand_code}/game-transactions', 'OryxGamingController@gameTransactionV2');

// SimplePlay Endpoints
$app->post('/api/simpleplay/{brand_code}/GetUserBalance', 'SimplePlayController@getBalance');
$app->post('/api/simpleplay/{brand_code}/PlaceBet', 'SimplePlayController@debitProcess');
$app->post('/api/simpleplay/{brand_code}/PlayerWin', 'SimplePlayController@creditProcess');
$app->post('/api/simpleplay/{brand_code}/PlayerLost', 'SimplePlayController@lostTransaction');
$app->post('/api/simpleplay/{brand_code}/PlaceBetCancel', 'SimplePlayController@rollBackTransaction');

// MannaPlay Endpoints
$app->post('/api/manna/{brand_code}/fetchbalance', 'MannaPlayController@getBalance');
$app->post('/api/manna/{brand_code}/bet', 'MannaPlayController@debitProcess');
$app->post('/api/manna/{brand_code}/win', 'MannaPlayController@creditProcess');
$app->post('/api/manna/{brand_code}/betrollback', 'MannaPlayController@rollbackTransaction');

// QTech Games Endpoints
$app->get('/api/qtech/{brand_code}/accounts/{player_id}/session?gameId={game_id}', 'QTechController@authPlayer');
$app->post('/api/qtech/{brand_code}/accounts/{player_id}/balance?gameId={game_id}', 'QTechController@getBalance');
$app->post('/api/qtech/{brand_code}/transactions', 'QTechController@gameTransaction');
$app->post('/api/qtech/{brand_code}/transactions/rollback', 'QTechController@rollbackTransaction');
$app->post('/api/qtech/{brand_code}/bonus/status', 'QTechController@bonusStatus');

// Vivo Gaming Endpoints
$app->get('/api/vivo/{brand_code}/authenticate', 'VivoController@authPlayer');
$app->get('/api/vivo/{brand_code}/changebalance', 'VivoController@gameTransaction');
$app->get('/api/vivo/{brand_code}/status', 'VivoController@transactionStatus');
$app->get('/api/vivo/{brand_code}/getbalance', 'VivoController@getBalance');

// ICG Gaming Endpoints
$app->get('/api/icgaming/gamelist','ICGController@getGameList');
$app->post('/api/icgaming/gamelaunch','ICGController@gameLaunchURL');
$app->get('/api/icgaming/authplayer','ICGController@authPlayer');
$app->get('/api/icgaming/playerDetails','ICGController@playerDetails');
$app->post('/api/icgaming/bet','ICGController@betGame');
$app->delete('/api/icgaming/bet','ICGController@cancelBetGame');
$app->post('/api/icgaming/win','ICGController@winGame');
$app->post('api/icgaming/withdraw','ICGController@withdraw');
$app->post('api/icgaming/deposit','ICGController@deposit');
// EDP Gaming Endpoints
$app->post('/api/edp/gamelunch','EDPController@gameLaunchUrl');
$app->get('/api/edp/check','EDPController@index');
$app->get('/api/edp/session','EDPController@playerSession');
$app->get('/api/edp/balance','EDPController@getBalance');
$app->post('/api/edp/bet','EDPController@betGame');
$app->post('/api/edp/win','EDPController@winGame');
$app->post('/api/edp/refund','EDPController@refundGame');
$app->post('/api/edp/endSession','EDPController@endGameSession');
// Lottery Gaming Endpoints
$app->post('/api/lottery/authenticate', 'LotteryController@authPlayer');
$app->post('/api/lottery/balance', 'LotteryController@getBalance'); #/
$app->post('/api/lottery/debit', 'LotteryController@debitProcess'); #/
// $app->post('/api/lottery/credit', 'LotteryController@creditProcess');
// $app->post('/api/lottery/debitandcredit', 'LotteryController@debitAndCreditProcess');
// $app->post('/api/lottery/endsession', 'LotteryController@endPlayerSession');
// Mariott Gaming Endpoints
$app->post('/api/marriott/authenticate', 'MarriottController@authPlayer');
$app->post('/api/marriott/balance', 'MarriottController@getBalance'); #/
$app->post('/api/marriott/debit', 'MarriottController@debitProcess'); #/
// RGS Gaming Endpoints
$app->post('rsg/authenticate', 'DigitainController@authenticate');
$app->post('rsg/creategamesession', 'DigitainController@createGameSession');
$app->post('rsg/getbalance', 'DigitainController@getbalance');
$app->post('rsg/refreshtoken', 'DigitainController@refreshtoken');
$app->post('rsg/bet', 'DigitainController@bet');
$app->post('rsg/win', 'DigitainController@win');
$app->post('rsg/betwin', 'DigitainController@betwin');
$app->post('rsg/refund', 'DigitainController@refund');
$app->post('rsg/amend', 'DigitainController@amend');
$app->post('rsg/promowin', 'DigitainController@PromoWin');
$app->post('rsg/checktxstatus', 'DigitainController@CheckTxStatus');
// IA SPORTS
$app->post('/api/ia/hash', 'IAESportsController@hashen'); // DEPRECATED
$app->post('/api/ia/lunch', 'IAESportsController@userlunch');// DEPRECATED
$app->post('/api/ia/register', 'IAESportsController@userRegister');
$app->post('/api/ia/userwithdraw', 'IAESportsController@userWithdraw');// DEPRECATED
$app->post('/api/ia/userdeposit', 'IAESportsController@userDeposit');// DEPRECATED
$app->post('/api/ia/userbalance', 'IAESportsController@userBalance');// DEPRECATED
$app->post('/api/ia/wager', 'IAESportsController@userWager'); // DEPRECATED
$app->post('/api/ia/hotgames', 'IAESportsController@getHotGames'); // DEPRECATED
$app->post('/api/ia/orders', 'IAESportsController@userOrders');// DEPRECATED
$app->post('/api/ia/activity_logs', 'IAESportsController@userActivityLog'); // DEPRECATED
$app->post('/api/ia/deposit', 'IAESportsController@seamlessDeposit');
$app->post('/api/ia/withdrawal', 'IAESportsController@seamlessWithdrawal');
$app->post('/api/ia/balance', 'IAESportsController@seamlessBalance');
$app->post('/api/ia/searchorder', 'IAESportsController@seamlessSearchOrder');
$app->post('/api/ia/debugg', 'IAESportsController@userlaunch');
// Bole Gaming Endpoints
$app->post('/api/bole/register', 'BoleGamingController@playerRegister');
$app->post('/api/bole/logout', 'BoleGamingController@playerLogout');
$app->post('/api/bole/wallet/player/cost', 'BoleGamingController@playerWalletCost');
$app->post('/api/bole/wallet/player/balance', 'BoleGamingController@playerWalletBalance');
// AWS PROVIDER BACKOFFICE ROUTE
$app->post('/api/aws/register', 'AWSController@playerRegister');
$app->post('/api/aws/launchgame', 'AWSController@launchGame');
$app->post('/api/aws/gamelist', 'AWSController@gameList');
$app->post('/api/aws/playermanage', 'AWSController@playerManage');
$app->post('/api/aws/playerstatus', 'AWSController@playerStatus');
$app->post('/api/aws/playerbalance', 'AWSController@playerBalance'); 
$app->post('/api/aws/fundtransfer', 'AWSController@fundTransfer'); 
$app->post('/api/aws/querystatus', 'AWSController@queryStatus'); 
$app->post('/api/aws/orderquery', 'AWSController@queryOrder');
// AWS PROVIDER SINGLE WALLET ROUTE
$app->post('api/aws/single/wallet/balance', 'AWSController@singleBalance');
$app->post('api/aws/single/wallet/fund/transfer', 'AWSController@singleFundTransfer');
$app->post('api/aws/single/wallet/fund/query', 'AWSController@singleFundQuery');
$app->post('api/aws/single/wallet/altest', 'AWSController@changeAccount');
// SILKSTONE ROUTES (SEAMLESS WALLET)
// $app->post('skywind/api/get_ticket', 'SkyWindController@getTicket');
$app->post('api/skywind/api/getgamelist', 'SkyWindController@getGamelist'); // TEST
$app->post('api/skywind/api/getauth', 'SkyWindController@getAuth'); // TEST
$app->post('api/skywind/api/getauth2', 'SkyWindController@getAuth2'); // TEST
$app->post('api/skywind/api/getgames', 'SkyWindController@getGamelist'); // TEST
$app->post('api/skywind/api/gamelaunch', 'SkyWindController@gameLaunch'); // TEST

// Version  Sky Wind 1
$app->post('api/skywind/api/validate_ticket', 'SkyWindController@validateTicket');
$app->post('api/skywind/api/get_ticket', 'SkyWindController@getTicket');
$app->post('api/skywind/api/get_balance', 'SkyWindController@getBalance');
$app->post('api/skywind/api/debit', 'SkyWindController@gameDebit');
$app->post('api/skywind/api/credit', 'SkyWindController@gameCredit');
$app->post('api/skywind/api/rollback', 'SkyWindController@gameRollback');
$app->post('api/skywind/api/get_free_bet', 'SkyWindController@getFreeBet');

// Version two  Skywind
// $app->post('api/skywind/api/api/validate_ticket', 'SkyWindController@validateTicket');
// $app->post('api/skywind/api/api/get_ticket', 'SkyWindController@getTicket');
// $app->post('api/skywind/api/api/get_balance', 'SkyWindController@getBalance');
// $app->post('api/skywind/api/api/debit', 'SkyWindController@gameDebit');
// $app->post('api/skywind/api/api/credit', 'SkyWindController@gameCredit');
// $app->post('api/skywind/api/api/rollback', 'SkyWindController@gameRollback');
// $app->post('api/skywind/api/api/get_free_bet', 'SkyWindController@getFreeBet');
//Player API
//Operator API
//Lobby API
//Report API

// CQ9 Gaming
$app->post('api/cq9/transaction/game/bet','CQ9Controller@playerBet'); //
$app->post('api/cq9/transaction/game/endround','CQ9Controller@playrEndround'); //
$app->post('api/cq9/transaction/game/rollout','CQ9Controller@playerRollout'); //
$app->post('api/cq9/transaction/game/takeall','CQ9Controller@playerTakeall');
$app->post('api/cq9/transaction/game/rollin','CQ9Controller@playerRollin'); //
$app->post('api/cq9/transaction/game/debit','CQ9Controller@playerDebit');
$app->post('api/cq9/transaction/game/credit','CQ9Controller@playerCredit');
$app->post('api/cq9/transaction/game/bonus','CQ9Controller@playerBonus');
$app->post('api/cq9/transaction/user/payoff','CQ9Controller@playerPayoff');
$app->post('api/cq9/transaction/game/refund','CQ9Controller@playerRefund');
$app->get('api/cq9/transaction/record/{mtcode}','CQ9Controller@playerRecord'); 

$app->post('api/cq9/transaction/game/bets','CQ9Controller@playerBets');
$app->post('api/cq9/transaction/game/refunds','CQ9Controller@playerRefunds');
$app->post('api/cq9/transaction/game/cancel','CQ9Controller@playerCancel');
$app->post('api/cq9/transaction/game/amend','CQ9Controller@playerAmend');
$app->post('api/cq9/transaction/game/wins','CQ9Controller@playerWins');
$app->post('api/cq9/transaction/game/amends','CQ9Controller@playerAmends');

$app->get('api/cq9/transaction/balance/{account}','CQ9Controller@CheckBalance');
$app->get('api/cq9/gameboy/player/lotto/balance/{account}','CQ9Controller@CheckBalanceLotto'); // New
$app->get('api/cq9/player/check/{account}','CQ9Controller@CheckPlayer');
$app->post('api/cq9/player/check','CQ9Controller@noRouteParamPassed'); // TEST
$app->post('api/cq9/transaction/record','CQ9Controller@noRouteParamPassed');  // TEST
$app->post('api/cq9/transaction/balance','CQ9Controller@noRouteParamPassed'); // TEST

$app->post('api/cq9/mw/getlist','CQ9Controller@getGameList');
$app->post('api/cq9/mw/gamelaunch','CQ9Controller@gameLaunch');


//SAGaming 
$app->post('api/sa/debugme','SAGamingController@debugme');
$app->post('api/sa/GetUserBalance','SAGamingController@GetUserBalance');
$app->post('api/sa/PlaceBet','SAGamingController@PlaceBet');
$app->post('api/sa/PlayerWin','SAGamingController@PlayerWin');
$app->post('api/sa/PlayerLost','SAGamingController@PlayerLost');
$app->post('api/sa/PlaceBetCancel','SAGamingController@PlaceBetCancel');

// KAGaming
$app->post('api/ka/gamelist','KAGamingController@index');
$app->post('api/ka/start','KAGamingController@gameStart');
$app->post('api/ka/end','KAGamingController@gameEnd');
$app->post('api/ka/play','KAGamingController@checkPlay');
$app->post('api/ka/credit','KAGamingController@gameCredit');
$app->post('api/ka/balance','KAGamingController@playerBalance');
$app->post('api/ka/revoke','KAGamingController@gameRevoke');



// 8PROVIDERS
$app->post('/api/eightprovider', 'EightProviderController@index'); // Single Route

$app->post('/api/eightprovider/test', 'EightProviderController@testcall'); // TEST
$app->post('/api/eightprovider/getlist', 'EightProviderController@getGames');
$app->post('/api/eightprovider/geturl', 'EightProviderController@gameUrl'); // DEPRECATED
$app->post('/api/eightprovider/registerbunos', 'EightProviderController@registerBunos'); // DEPRECATED
$app->post('/api/eightprovider/init', 'EightProviderController@gameInit'); // DEPRECATED
$app->post('/api/eightprovider/bet', 'EightProviderController@gameBet'); // DEPRECATED
$app->post('/api/eightprovider/win', 'EightProviderController@gameWin'); // DEPRECATED
$app->post('/api/eightprovider/refund', 'EightProviderController@gameRefund'); // DEPRECATED
$app->post('/api/eightprovider/deposit', 'EightProviderController@gameDeposit'); // DEPRECATED
$app->post('/api/eightprovider/withdrawal', 'EightProviderController@gameWithdrawal'); // DEPRECATED
$app->post('/api/gettransaction', 'AlController@testTransaction');

//BNG Endpoints
$app->post('/api/bng', 'BNGController@index');
$app->post('/api/bng/gamelaunch', 'BNGController@gameLaunchUrl');
$app->post('/api/bng/generateGame','BNGController@generateGame');
//FC GAMING Endpoints
$app->post('/api/fc/encrypt','FCController@SampleEncrypt');
$app->post('/api/fc/decode','FCController@SampleDecrypt');
$app->post('/api/fc/getbalance','FCController@getBalance');
$app->post('/api/fc/transaction','FCController@transactionMake');
$app->post('/api/fc/cancelbet','FCController@cancelBet');
$app->post('/api/fc/gamelaunch','FCController@gameLaunch');
//PNG Endpoints
$app->post('/api/png/authenticate','PNGController@authenticate');
$app->post('/api/png/reserve','PNGController@reserve');
$app->post('/api/png/release','PNGController@release');
$app->post('/api/png/balance','PNGController@balance');
$app->post('/api/png/cancelReserve','PNGController@cancelReserve');
//Wazdan Endpoints
$app->post('/api/wazdan/authenticate','WazdanController@authenticate');
$app->post('/api/wazdan/getStake','WazdanController@getStake');
$app->post('/api/wazdan/rollbackStake','WazdanController@rollbackState');
$app->post('/api/wazdan/returnWin','WazdanController@returnWin');
$app->post('/api/wazdan/getFunds','WazdanController@getFunds');
$app->post('/api/wazdan/gameClose','WazdanController@gameClose');
$app->post('/api/wazdan/hash','WazdanController@hashCode');
// BETRNK LOTTO
$app->post('/api/betrnk/lotto', 'BetrnkController@getUrl');

// TIDY
// $app->post('/tidy/api/auth', 'TidyController@conecteccc');
$app->post('/api/tidy/api/game/outside/link', 'TidyController@getGameUrl'); // CENTRALIZED
$app->post('/api/tidy/api/checkplayer', 'TidyController@autPlayer');
$app->post('/api/tidy/api/gamelist', 'TidyController@getGamelist');
$app->post('/api/tidy/api/gameurl', 'TidyController@gameUrl');
$app->post('/api/tidy/api/transaction/bet', 'TidyController@gameBet');
$app->post('/api/tidy/api/transaction/rollback', 'TidyController@gameRollback');
$app->post('/api/tidy/api/transaction/win', 'TidyController@gameWin');
$app->post('/api/tidy/api/user/balance', 'TidyController@checkBalance');

//TGG
$app->post('/api/tgg/gamelist', 'TGGController@getGamelist'); // launch game 
$app->post('/api/tgg/geturl', 'TGGController@getURL');// launch game
$app->post('/api/tgg', 'TGGController@index'); // Single Route
$app->post('/api/tgg/init', 'TGGController@gameInit');
$app->post('/api/tgg/bet', 'TGGController@gameBet');
$app->post('/api/tgg/win', 'TGGController@gameWin');
$app->post('/api/tgg/refund', 'TGGController@gameRefund');

//PGSoft 
$app->post('/api/pgsoft/VerifySession', 'PGSoftController@verifySession');
$app->post('/api/pgsoft/Cash/Get', 'PGSoftController@cashGet');
$app->post('/api/pgsoft/Cash/TransferOut', 'PGSoftController@transferOut');
$app->post('/api/pgsoft/Cash/TransferIn', 'PGSoftController@transferIn');

//Booming Games
$app->post('api/booming/gamelist','BoomingGamingController@gameList');
$app->post('api/booming/callback','BoomingGamingController@callBack');
$app->post('api/booming/rollback','BoomingGamingController@rollBack');

// Spade Gaming
$app->post('api/spade','SpadeController@index');//single route
$app->post('api/spade/authorize','SpadeController@authorize');
$app->post('api/spade/getBalance','SpadeController@getBalance');
$app->post('api/spade/transfer','SpadeController@makeTransfer');
$app->post('api/spade/getgame','SpadeController@getGameList');

//MajaGames
$app->post('api/mj/seamless/bet','MajaGamesController@bet');
$app->post('api/mj/seamless/settlement','MajaGamesController@settlement');
$app->post('api/mj/seamless/cancel','MajaGamesController@cancel');
$app->get('api/mj/seamless/getBalance','MajaGamesController@getBalance');

// Spade Curacao Gaming
$app->post('api/spade_curacao','SpadeCuracaoController@index');//single route
$app->post('api/spade_curacao/authorize','SpadeCuracaoController@authorize');
$app->post('api/spade_curacao/getBalance','SpadeCuracaoController@getBalance');
$app->post('api/spade_curacao/transfer','SpadeCuracaoController@makeTransfer');
$app->post('api/spade_curacao/getgame','SpadeCuracaoController@getGameList');
// EPOINT CONTROLLER
// $app->post('/api/epoint', 'EpointController@epointAuth'); #/
// $app->post('/api/epoint/bitgo', 'EpointController@bitgo'); #/
// EBANCO
// $app->post('/api/ebancobanks', 'EbancoController@getAvailableBank'); #/
// $app->post('/api/ebancodeposit', 'EbancoController@deposit'); #/
// $app->post('/api/ebancodeposithistory', 'EbancoController@deposithistory'); #/
// $app->post('/api/ebancodepositinfobyid', 'EbancoController@depositinfo'); #/
// $app->post('/api/ebancodepositinfobyselectedid', 'EbancoController@depositinfobyselectedid'); #/
// $app->post('/api/ebancodepositreceipt', 'EbancoController@depositReceipt'); #/
$app->post('/api/ebancoauth', 'EbancoController@connectTo'); 
$app->post('/api/ebancogetbanklist', 'EbancoController@getBankList'); 
$app->post('/api/ebancodeposit', 'EbancoController@makeDeposit'); 
$app->post('/api/ebancosenddepositreceipt', 'EbancoController@sendReceipt'); 
$app->post('/api/ebancodeposittransaction', 'EbancoController@depositInfo'); 
$app->post('/api/ebancodeposittransactions', 'EbancoController@depositHistory'); 
$app->post('/api/ebancoupdatedeposit', 'EbancoController@updateDeposit'); 
$app->post('/api/ebancotest','EbancoController@testrequest');

// Request an access token
$app->post('/oauth/access_token', function() use ($app){
    return response()->json($app->make('oauth2-server.authorizer')->issueAccessToken());
});
//paymentgateway routes
$app->get('/paymentgateways','Payments\PaymentGatewayController@index');
$app->post('/payment','Payments\PaymentGatewayController@paymentPortal');
$app->get('/coinpaymentscurrencies','Payments\PaymentGatewayController@getCoinspaymentRate');
$app->get('/currencyconversion','CurrencyController@currency');
$app->post('/updatetransaction','Payments\PaymentGatewayController@updatetransaction');
$app->post('/updatepayouttransaction','Payments\PaymentGatewayController@updatePayoutTransaction');
$app->post('qaicash/depositmethods','Payments\PaymentGatewayController@getQAICASHDepositMethod');
$app->post('qaicash/deposit','Payments\PaymentGatewayController@makeDepositQAICASH');
$app->post('qaicash/payoutmethods','Payments\PaymentGatewayController@getQAICASHPayoutMethod');
$app->post('qaicash/payout','Payments\PaymentGatewayController@makePayoutQAICASH');
$app->post('qaicash/payout/approve','Payments\PaymentGatewayController@approvedPayoutQAICASH');
$app->post('qaicash/payout/reject','Payments\PaymentGatewayController@rejectPayoutQAICASH');
///CoinsPayment Controller
///new payment gateways api
$app->post('payment/launchurl','Payments\PaymentLobbyController@paymentLobbyLaunchUrl');
$app->post('payout/launchurl','Payments\PaymentLobbyController@payoutLobbyLaunchUrl');
$app->post('payment/portal','Payments\PaymentLobbyController@payment');
$app->post('payout/portal','Payments\PaymentLobbyController@payout');
$app->post('payment/tokencheck','Payments\PaymentLobbyController@checkTokenExist');
$app->get('payment/list','Payments\PaymentLobbyController@getPaymentMethod');
$app->get('payout/list','Payments\PaymentLobbyController@getPayoutMethod');
$app->post('payment/check','Payments\PaymentLobbyController@minMaxAmountChecker');
$app->post('payment/paymongoupdate','Payments\PaymentLobbyController@paymongoUpdateTransaction');
$app->post('payment/checktransaction','Payments\PaymentLobbyController@checkPayTransactionContent');

$app->post('payment/transaction','Payments\PaymentLobbyController@getPayTransactionDetails');
$app->post('payment/cancel','Payments\PaymentLobbyController@cancelPayTransaction');
$app->post('currency/convert','Payments\PaymentLobbyController@currencyConverter');
//GameLobby
$app->get('game/list','GameLobby\GameLobbyController@getGameList');
$app->get('game/provider/{provider_name}','GameLobby\GameLobbyController@getProviderDetails');
$app->post('game/launchurl','GameLobby\GameLobbyController@gameLaunchUrl');
$app->post('gamelobby/launchurl','GameLobby\GameLobbyController@gameLobbyLaunchUrl');
$app->get('game/balance','GameLobby\GameLobbyController@getPlayerBalance');
$app->post('game/addfavorite','GameLobby\GameFavoriteController@index');
$app->post('game/playerinfo','GameLobby\GameFavoriteController@playerInfo');
$app->post('game/playerfavoritelist','GameLobby\GameFavoriteController@playerFavorite');
$app->get('game/newestgames','GameLobby\GameInfoController@getNewestGames');
$app->get('game/mostplayed','GameLobby\GameInfoController@getMostPlayed');
$app->post('game/demogame','GameLobby\GameInfoController@getDemoGame');
$app->post('game/suggestions','GameLobby\GameInfoController@getGameSuggestions'); // DEPRECATED
$app->get('game/topcharts','GameLobby\GameInfoController@getTopGames');
$app->get('game/topcharts/numberone','GameLobby\GameInfoController@getTopProvider');
$app->post('game/playerdetailsrequest','GameLobby\GameInfoController@getClientPlayerDetails');
$app->post('game/betlist','GameLobby\GameInfoController@getBetList');
$app->post('game/query','GameLobby\QueryController@queryData');
// IWallet
// $app->post('api/iwallet/makedeposit','IWalletController@makeDeposit');
$app->post('api/iwallet/makesettlement','IWalletController@makeSettlement');
// $app->post('api/iwallet/makepayment','IWalletController@makePayment');
$app->post('api/iwallet/makeremittance','IWalletController@makeRemittance');
//WMT
$app->post('api/wmt/makesettlement','Payments\WMTController@makeSettlement');

$app->post('game/lang','GameLobby\GameLobbyController@getLanguage');

$app->post('payment/catpay/callBack','Payments\PaymentGatewayController@catpayCallback');


// Habanero 
$app->post('hbn/api/auth','HabaneroController@playerdetailrequest');
$app->post('hbn/api/tx','HabaneroController@fundtransferrequest');
$app->post('hbn/api/query','HabaneroController@queryrequest');


// Pragmatic PLay
$app->post('api/pp/authenticate','PragmaticPLayController@authenticate');
$app->post('api/pp/balance','PragmaticPLayController@balance');
$app->post('api/pp/bet','PragmaticPLayController@bet');
$app->post('api/pp/result','PragmaticPLayController@result');
$app->post('api/pp/refund','PragmaticPLayController@refund');
$app->post('api/pp/bonusWin','PragmaticPLayController@bonusWin');
$app->post('api/pp/jackpotWin','PragmaticPLayController@jackpotWin');
$app->post('api/pp/promoWin','PragmaticPLayController@promoWin');
$app->post('api/pp/endRound','PragmaticPLayController@endRound');
$app->post('api/pp/getBalancePerGame','PragmaticPLayController@getBalancePerGame');
$app->post('api/pp/session/expired','PragmaticPLayController@sessionExpired');



// $app->get('al-games','AlController@insertGamesTapulanMode');


// Yggdrasil 
$app->get('api/ygg/playerinfo.json','YGGController@playerinfo');
$app->get('api/ygg/wager.json','YGGController@wager');
$app->get('api/ygg/cancelwager.json','YGGController@cancelwager');
$app->get('api/ygg/appendwagerresult.json','YGGController@appendwagerresult');
$app->get('api/ygg/endwager.json','YGGController@endwager');
$app->get('api/ygg/campaignpayout.json','YGGController@campaignpayout');
$app->get('api/ygg/getbalance.json','YGGController@getbalance');


//IFRAME URL ENDPOINTS
$app->post('/iframe/auth/token','Iframe\AuthenticationController@checkTokenExist');
$app->post('/iframe/close','Iframe\AuthenticationController@iframeClosed');
//MicroGaming EndPoints
$app->post('/api/microgaming/launch','MicroGamingController@launchGame');
$app->post('/api/microgaming/makeDeposit','MicroGamingController@makeDeposit');
$app->post('/api/microgaming/makeWithdraw','MicroGamingController@makeWithdraw');
$app->post('/api/microgaming/getPlayerBalance','MicroGamingController@getPlayerBalance');

//Evolution Gaming Endpoints

$app->post('/api/evogaming/check','EvolutionController@authentication');
$app->post('/api/evogaming/balance','EvolutionController@balance');
$app->post('/api/evogaming/debit','EvolutionController@debit');
$app->post('/api/evogaming/credit','EvolutionController@credit');
$app->post('/api/evogaming/cancel','EvolutionController@cancel');
$app->post('/api/evogaming/sid','EvolutionController@sid');
$app->post('/api/evogaming/launch','EvolutionController@gameLaunch');