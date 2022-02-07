<?php

$middleware_url_api = 'https://api-test.betrnk.games/public'; 
// $middleware_url_api = 'http://localhost:1010'; 
$gamelobby_site = 'https://daddy.betrnk.games';
$play_betrnk = 'https://play-test.betrnk.games';
$play_tigergames = 'https://play-test.tigergames.io';
$iframe_url = 'https://play-test.betrnk.games/loadgame/transferwallet?param=';
$puretransferwallet_iframe = 'https://play-test.betrnk.games/api/tw/loadgame?param=';
$cut_call ='https://api-test.betrnk.games/public';
return [
    'cut_call_server' => $cut_call,
    'play_betrnk' => $play_betrnk,
    'play_tigergames' => $play_tigergames,
    'tigergames' => $gamelobby_site,
    'demo_api_url' => $middleware_url_api,
    'iframe' =>$iframe_url,
    'puretransferwallet_iframe' =>$puretransferwallet_iframe,
    'oauth_mw_api' => [
        'access_url' => $middleware_url_api.'/oauth/access_token',
        'mwurl' => $middleware_url_api,
        'client_id' => 1,
        'client_secret' => 'QPmdvSg3HGFXbsfhi8U2g5FzAOnjpRoF',
        'username' => 'randybaby@gmail.com',
        'password' => '_^+T3chSu4rt+^_',
        'grant_type' => 'password',
    ],
    'icgamingapi' => 'https://admin-stage.iconic-gaming.com/service',
    'icgaminglogin' => 'https://admin-stage.iconic-gaming.com/service/login',
    'icgaminggames' => 'https://admin-stage.iconic-gaming.com/service/api/v1/games?type=all&lang=en',
    'icgagents'=>[
        'jpyagents'=>[
            'username' => 'betrnkjpy',
            'password' => ']WKtkT``mJCe8N3J',   
            'secure_code' => '60e7a70e-806a-479c-af0b-d3c83a6616c1',
        ],
        'euragents'=>[
            'username' => 'betrnkeuro',
            'password' => ']WKtkT``mJCe8N3J',
            'secure_code' => '4c7aa6fe-5559-4006-b995-b2414a472d0b',
        ],
        'cnyagents'=>[
            'username' => 'betrnkcny',
            'password' => ']WKtkT``mJCe8N3J',
            'secure_code' => '7a640de8-82ce-4fe7-b0a3-0bea404bceb8',
        ],
        'krwagents'=>[
            'username' => 'betrnkkrw',
            'password' => ']WKtkT``mJCe8N3J',
            'secure_code' => '0d18064c-cd77-4a04-9f17-2dc27bdb903a',
        ],
        'phpagents'=>[
            'username' => 'betrnkphp',
            'password' => ']WKtkT``mJCe8N3J',
            'secure_code' => '0fa2144d-7529-4483-9306-6515485ce6c7',
        ],
        'thbagents'=>[
            'username' => 'betrnkthb',
            'password' => ']WKtkT``mJCe8N3J',
            'secure_code' => 'e2d411bd-ddea-41b1-a173-483d2f98f7cf',
        ],
        'tryagents'=>[
            'username' => 'betrnktry',
            'password' => ']WKtkT``mJCe8N3J',
            'secure_code' => '93928542-4014-4736-a72e-3d99786df5ea',
        ],
        'twdagents'=>[
            'username' => 'betrnkTWD',
            'password' => ']WKtkT``mJCe8N3J',
            'secure_code' => '99d78fd7-d342-4fa5-932a-029a65b8a1f1',
        ],
        'vndagents'=>[
            'username' => 'betrnkvnd',
            'password' => ']WKtkT``mJCe8N3J',
            'secure_code' => '99d78fd7-d342-4fa5-932a-029a65b8a1f1',
        ],
        'usdagents'=>[
            'username' => 'betrnk',
            'password' => 'betrnk168!^*',
            'secure_code' => '2c00c099-f32b-4fc1-a69d-661d8c51c6ae',
        ],
    ],
    'endorphina' => [
        'url' => 'https://test.endorphina.network/api/sessions/seamless/rest/v1',
        'nodeId' => 1002,
        'secretkey' => '67498C0AD6BD4D2DB8FDFE59BD9039EB',
    ],
    'bolegaming' => [
        "CNY" => [
            'AccessKeyId' => '9048dbaa-b489-4b32-9a29-149240a5cefe',
            'access_key_secret' => '4A55C539E93B189EAA5A76A8BD92B99B87B76B80',
            'app_key' => 'R14NDR4FT',
            'login_url' => 'https://api.cdmolo.com:16800/v1/player/login',
            'logout_url' => 'https://api.cdmolo.com:16800/v1/player/logout',
        ],
        "USD" => [
            'AccessKeyId' => '912784c6-6f1a-4a0c-a64c-f01f815f8c31',
            'access_key_secret' => '8D0EAD434478F1D487165C9E27F7A93FC9451FFF',
            'app_key' => 'RiANDRAFT',
            'login_url' => 'https://api.bole-game.com:16800/v1/player/login',
            'logout_url' => 'https://api.bole-game.com:16800/v1/player/logout',
        ],
    ],
    'aws' => [
        'api_url' => 'https://sapi.awsxpartner.com/b2b',
        '24USD'=> [ //
            'merchant_id' => 'TG',
            'merchant_key' => '5819e7a6d0683606e60cd6294edfc4c557a2dd8c9128dd6fbe1d58e77cd8067fead68c48cdb3ea85dcb2e05518bac60412a0914d156a36b4a2ecab359c7adfad',
        ],
        '1USD'=> [ // 
            'merchant_id' => 'TG',
            'merchant_key' => '5819e7a6d0683606e60cd6294edfc4c557a2dd8c9128dd6fbe1d58e77cd8067fead68c48cdb3ea85dcb2e05518bac60412a0914d156a36b4a2ecab359c7adfad',
        ], 
        '6THB' => [ // ASK THB
            'merchant_id' => 'ASKME',
            'merchant_key' => 'a44c3ca52ef01f55b0a8b3859610f554b05aa57ca36e4a508addd9ddae539a84d43f9407c72d555bc3093bf3516663d504e98b989f3ec3e3ff8407171f43ccdc',
        ],
        '32THB' => [  // ASK ME USD
            'merchant_id' => 'ASKME',
            'merchant_key' => 'a44c3ca52ef01f55b0a8b3859610f554b05aa57ca36e4a508addd9ddae539a84d43f9407c72d555bc3093bf3516663d504e98b989f3ec3e3ff8407171f43ccdc',
        ],
        '5USD' => [ // XIGOLO USD
            'merchant_id' => 'XIGOLO',
            'merchant_key' => 'b7943fc2e48c3b74a2c31514aebdce25364bd2b1a97855f290c01831052b25478c35bdebdde8aa7a963e140a8c1e6401102321a2bd237049f9e675352c35c4cc',
        ],
        '15EUR' => [ // EVERYMATRIX
            'merchant_id' => 'TGEM',
            'merchant_key' => '0d403e21c44d857d2c1847cb35ea16b5c4a8acbb3b669bda2e45ced7c736d4d51f3b23486e64a0a4b9ff2b3359e53af6c4ab8caa59ba728479114a2cc51096be',
        ],
        '7USD' => [  // ASK ME USD
            'merchant_id' => 'TGC',
            'merchant_key' => 'cb1bc0a2fc16bddfd549bdd8aae0954fba28c9b11c6a25e6ef886b56e846b033ae5fe29880be69fd8741ab400e6c4cb2f8c0f05e49dcc4568362370278ba044d',
        ],
        '8USD' => [  // ASK ME USD
            'merchant_id' => 'TG',
            'merchant_key' => '5819e7a6d0683606e60cd6294edfc4c557a2dd8c9128dd6fbe1d58e77cd8067fead68c48cdb3ea85dcb2e05518bac60412a0914d156a36b4a2ecab359c7adfad',
        ]
    ],
    'cqgames' => [
        "prefix" => "TG",
        "pdbid"=> 30, // Database ID nothing todo with the provider!
        'api_url' => 'https://api.cqgame.games',
        // 'api_token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyaWQiOiI1ZjIyODY1ZWNkNTY1ZjAwMDEzZjUyZDAiLCJhY2NvdW50IjoidGlnZXJnYW1lcyIsIm93bmVyIjoiNWYyMjg2NWVjZDU2NWYwMDAxM2Y1MmQwIiwicGFyZW50Ijoic2VsZiIsImN1cnJlbmN5IjoiVVNEIiwianRpIjoiMjQ3NDQ1MTQzIiwiaWF0IjoxNTk2MDk4MTQyLCJpc3MiOiJDeXByZXNzIiwic3ViIjoiU1NUb2tlbiJ9.fdoQCWGPkYNLoROGR9jzMs4axnZbRJCnnLZ8T2UDCwU',
        'api_tokens' => [
            'USD' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyaWQiOiI1ZjIyODY1ZWNkNTY1ZjAwMDEzZjUyZDAiLCJhY2NvdW50IjoidGlnZXJnYW1lcyIsIm93bmVyIjoiNWYyMjg2NWVjZDU2NWYwMDAxM2Y1MmQwIiwicGFyZW50Ijoic2VsZiIsImN1cnJlbmN5IjoiVVNEIiwianRpIjoiMjQ3NDQ1MTQzIiwiaWF0IjoxNTk2MDk4MTQyLCJpc3MiOiJDeXByZXNzIiwic3ViIjoiU1NUb2tlbiJ9.fdoQCWGPkYNLoROGR9jzMs4axnZbRJCnnLZ8T2UDCwU',
            'CNY' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyaWQiOiI1ZjM0ZDg1YWNkNTY1ZjAwMDE0MDBjZTYiLCJhY2NvdW50IjoidGlnZXJnYW1lc19jbnkiLCJvd25lciI6IjVmMjI4NjVlY2Q1NjVmMDAwMTNmNTJkMCIsInBhcmVudCI6IjVmMjI4NjVlY2Q1NjVmMDAwMTNmNTJkMCIsImN1cnJlbmN5IjoiQ05ZIiwianRpIjoiNjY2MjE3MzgxIiwiaWF0IjoxNTk3Mjk4Nzc4LCJpc3MiOiJDeXByZXNzIiwic3ViIjoiU1NUb2tlbiJ9.5xRfW4vHJLi7PeBmZGckSAIw9KoeL_al-dwcnV5dYL4',
            'KRW' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyaWQiOiI2MDVhOTM3YmZlMjZkZjAwMDFiYmNkNDEiLCJhY2NvdW50IjoidGlnZXJnYW1lc19rcnciLCJvd25lciI6IjVmMjI4NjVlY2Q1NjVmMDAwMTNmNTJkMCIsInBhcmVudCI6IjVmMjI4NjVlY2Q1NjVmMDAwMTNmNTJkMCIsImN1cnJlbmN5IjoiS1JXIiwianRpIjoiMjUxOTU3MjI4IiwiaWF0IjoxNjE2NTQ4NzMxLCJpc3MiOiJDeXByZXNzIiwic3ViIjoiU1NUb2tlbiJ9.VYaJzPWsVglRzQeiwmEjXIrY5aJCBIbqEFcOz0T3xqU',
            'EUR' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyaWQiOiI2MWRlMmU3MTk5ZjgxNjAwMDEwZTA0ZmYiLCJhY2NvdW50IjoidGlnZXJnYW1lX2V1ciIsIm93bmVyIjoiNWYyMjg2NWVjZDU2NWYwMDAxM2Y1MmQwIiwicGFyZW50IjoiNWYyMjg2NWVjZDU2NWYwMDAxM2Y1MmQwIiwiY3VycmVuY3kiOiJFVVIiLCJqdGkiOiI0NDMwMTkyMzkiLCJpYXQiOjE2NDE5NTA4MzMsImlzcyI6IkN5cHJlc3MiLCJzdWIiOiJTU1Rva2VuIn0.zl3xt-kghj-Q-dq04pESy3uA6RD0lAMDfBbFWbFSZEQ',
        ],
        'wallet_token' => [
            'USD' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyaWQiOiI1ZjIyODY1ZWNkNTY1ZjAwMDEzZjUyZDAiLCJhY2NvdW50IjoidGlnZXJnYW1lcyIsIm93bmVyIjoiNWYyMjg2NWVjZDU2NWYwMDAxM2Y1MmQwIiwicGFyZW50Ijoic2VsZiIsImN1cnJlbmN5IjoiVVNEIiwianRpIjoiMjQ3NDQ1MTQzIiwiaWF0IjoxNTk2MDk4MTQyLCJpc3MiOiJDeXByZXNzIiwic3ViIjoiU1NUb2tlbiJ9.fdoQCWGPkYNLoROGR9jzMs4axnZbRJCnnLZ8T2UDCwU',
            'CNY' => '7CBCiX3qf5zMfYijIAmanbP2JB2HiBAi',
            'KRW' => '7CBCiX3qf5zMfYijIAmanbP2JB2HiBAi',
            'EUR' => '7CBCiX3qf5zMfYijIAmanbP2JB2HiBAi',
        ]
    ],
    'sagaming' => [
        "pdbid"=> 25, // Database ID nothing todo with the provider!
        "prefix" => "TGSA", // Nothing todo with the provider
        "lobby" => "A3107",
        "API_URL" => "http://sai-api.sa-apisvr.com/api/api.aspx",
        "MD5Key" => "GgaIMaiNNtg",
        "EncryptKey" => "g9G16nTs",
        "SAAPPEncryptKey" =>"M06!1OgI",
        "SecretKey" => "87B41ED0FB20437E85DE569B16EAA1DB",
    ],
    'kagaming' => [
        "pdbid"=> 32, // Database ID nothing todo with the provider!
        "gamelaunch" => "https://gamesstage.kaga88.com",
        "ka_api" => "https://rmpstage.kaga88.com/kaga/",
        "access_key" => "A95383137CE37E4E19EAD36DF59D589A",
        "secret_key" => "40C6AB9E806C4940E4C9D2B9E3A0AA25",
        "partner_name" => "TIGER",
        "tw_gamelaunch" => "https://gamesstage.kaga88.com",
        "tw_ka_api" => "https://rmpstage.kaga88.com/kaga/",
        "tw_access_key" => "40762F59A507E9182F28D6893975E0FD",
        "tw_secret_key" => "40C6AB9E806C4940E4C9D2B9E3A0AA25",
        "tw_partner_name" => "TIGER2",
    ],
    'iagaming' => [
        'auth_key' => '54bc08c471ae3d656e43735e6ffc9bb6',
        'pch' => 'BRNK', 
        'prefix' => 'TGAMES', // Nothing todo with the provider
        'iv' => '45b80556382b48e5',
        // 'url_lunch' => 'http://apitest.ilustretest.com/user/lunch',
        // 'url_register' => 'http://apitest.ilustretest.com/user/register',
        'url_lunch' => 'http://api.ilustretest.com/user/lunch',
        'url_register' => 'http://api.ilustretest.com/user/register',
        'url_withdraw' => 'http://api.ilustretest.com/user/withdraw',
        'url_deposit' => 'http://api.ilustretest.com/user/deposit',
        'url_balance' => 'http://api.ilustretest.com/user/balance',
        'url_wager' => 'http://api.ilustretest.com/user/getproject',
        'url_hotgames' => 'http://api.ilustretest.com/user/gethotgame',
        'url_orders' => 'http://api.ilustretest.com/user/searchprders',
        'url_activity_logs' => 'http://api.ilustretest.com/user/searchprders',
    ],
    'tidygaming' => [
        'url_lunch' => 'https://api.laksjd.net/api/game/outside/link',
        // 'API_URL' => 'http://staging-v1-api.tidy.zone',
        'API_URL' => 'https://api.laksjd.net',
        'client_id' => '8440a5b6',
        'currency' => [
            'USD' => 'af4f3164',
            'MMK' => 'af4f3164',
            'THB' => '6d31ece9',
            'EUR' => 'aa73348e',
            'TRY' => 'a8a26728',
            'CNY' => '969c2910',
            'RUB' => '50679e6c',
            'KRW' => 'bdafae9a',
            'IRR' => 'da104cef',
            'kIDR' => 'b5ebfb6f',
            'MYR' => '531afbdd',
            'kVND' => '34f7085a',
            'kLAK' => '951a128d',
            'kMMK' => '1d69f90a',
            'kKHR' => '3d7edfac',
        ],
        'support_1to1_denomination_prefixK' => [168,245,247,248,249,250,185], //THIS IS THE CLIENT ID THAT SUPPOR PREFIX K OR 1:1 EXAMPLE currency LAK concant for kLAK
        'SECRET_KEY' => 'f83c8224b07f96f41ca23b3522c56ef1',
        'TransferWallet' => [
            "client_id" => '2efa763b',
            "API_URL" => 'https://api.laksjd.net',
            "SECRET_KEY" => '9448cb4264f631cfcb6d1fb9109fc967',
        ]
    ],
    'evoplay' => [
        'api_url' => 'http://api.8provider.com',
        'project_id' => '1042',
        'secretkey' => 'c270d53d4d83d69358056dbca870c0ce',
    ],
    'skywind' => [
        'provider_db_id' => 28, // Database ID nothing todo with the provider!
        'prefix_user' => 'TG', // Developer Logic nothing todo with the provider!
        'api_url' => 'https://api.gcpstg.m27613.com/v1',
        'seamless_key' => '47138d18-6b46-4bd4-8ae1-482776ccb82d',
        'seamless_username' => 'TGAMESU_USER',
        'seamless_password' => 'Tgames1234',
        'merchant_data' => 'TIGERGAMESU',
        'merchant_password' => 'LmJfpioowcD8gspb',
    ],
    'digitain' => [
        'api_url' => 'https://launchdigi.stgdigitain.com', 
        'provider_db_id' => 14, // Database ID nothing todo with the provider!
        'provider_and_sub_name' => 'Digitain', // Nothing todo with the provider
        'digitain_key' => 'BetRNK3184223',
        'operator_id' => '6BC95607',  // old B9EC7C0A
    ],
    'payment'=>[
        'catpay'=>[
            'url_order'=>'http://celpay.vip/platform/submit/order',
            'url_redirect'=>'http://celpay.vip',
            'platformId' => 'WamRAOjZxH8vYG4rJU1',
            'platformToken'=>'azETahcH',
            'platformKey'=>'3a3343c316d947f68841fd7fd7c35636',
            'sign'=> 'WamRAOjZxH8vYG4rJU1',
        ]
        ],
    'boongo'=>[
        'PLATFORM_SERVER_URL'=>'https://gate-stage.betsrv.com/op/',
        'PROJECT_NAME'=>'tigergames-stage',
        'WL'=>'prod',
        'WL2'=>'prod1',
        'API_TOKEN'=>'hj1yPYivJmIX4X1I1Z57494re',
    ],
    'fcgaming'=>[
        'url' => 'http://api.fcg666.net',
        'AgentCode' => 'TG',
        'AgentKey' => '8t4A17537S1d5rwz',
        
    ],
    'tgg' => [
        'api_url' => 'http://api.flexcontentprovider.com',
        'project_id' => '1421',
        'api_key' => '29abd3790d0a5acd532194c5104171c8',
        'provider_id' => 29,
    ],
    '5men' => [
        'api_url' => 'http://api.flexcontentprovider.com',
        'project_id' => '1471',
        'api_key' => '4516cf2200fec6953f8bce3547c3a6cc',
        'provider_id' => 53,
    ],
    'bgaming' => [
        'PROVIDER_ID'=> 49,
        'CASINO_ID' => 'tigergames-int',
        'GCP_URL' => 'https://int.bgaming-system.com/a8r/tigergames-int',
        'AUTH_TOKEN' => 'HZhPwLMXtHrmQUxjmMvBmCPM'
   ],
    'pgsoft' => [
        'api_url' => 'http://api.pg-bo.me/external',
        'operator_token' => '642052d1627c8cae4a288fc82a8bf892',
        'secret_key' => '02f314db35a0dfe4635dff771b607f34',
    ],
    'tpp' => [
        'gamelaunch_url' => 'https://tigergames-sg0.prerelease-env.biz/gs2c/playGame.do',
        'secureLogin' => 'tg_tigergames', //or stylename
        'secret_key' => 'testKey',
        'casinoId' => 'ppcdk00000004874',
        'wsUri' => 'wss://prelive-dga.pragmaticplaylive.net/ws',
        'host' => 'https://api.prerelease-env.biz/IntegrationService/v3/http/CasinoGameAPI/game/url',
    ],
    'wazdan'=>[
        'operator' => 'tigergames',
        'license' => 'curacao',
        'hmac_scret_key' => 'uTDVNr4wu6Y78SNbr36bqsSCH904Rcn1',
        'partnercode'=> 'gd1wiurg',
        'gamelaunchurl' => 'https://gl-staging.wazdanep.com/',
        'api_freeRound' => 'https://service-stage.wazdanep.com/add/',
        'operator_data' =>[
            "1"=>"tigergames",
            "5"=>"xigolo",
            "6"=>"askmebet",
            "7"=>"tgc",
            "8"=>"askmebet",
            "9"=>"tigergames",
            "32"=>"askmebet",
        ]
    ],
    'evolution'=>[
        'host' => 'https://babylontgg.uat1.evo-test.com',
        'ua2Token' => 'test123',
        'gameHistoryApiToken' => 'test123',
        'externalLobbyApiToken'=> 'test123',
        'owAuthToken' => 'TigerGames@2020',
        'ua2AuthenticationUrl' => 'https://babylontgg.uat1.evo-test.com/ua/v1/babylontgg000001/test123',
        'env'=>'production'

    ],
    'png'=>[
        'root_url'=> 'https://agastage.playngonetwork.com',
        'pid' => 8888,
        'pid2'=>8956,
        'channel'=> 'desktop',
        'practice'=>0,
        'provider_id' => 32,
    ],
    'microgaming'=>[
        'grant_type'=> 'client_credentials',
        'client_id' => 'Tiger_USD_Agent_Test',
        'client_secret'=> '204973dbe37949cfbae301f545ba0e',
    ],
    'upg'=>[
        'grant_type'=> 'client_credentials',
        'client_id' => 'Tiger_UPG_USD_MA_Test',
        'client_secret'=> 'd4e59abcbf0b4fd88e3904f12c3dfb',
    ],
    'booming' => [
        'api_url' => 'https://api.intgr.booming-games.com',
        'api_secret' => 'NQGRafUDbe/esU8r+zVWWW7cx6xZKE2gpqWXv4Fs17j88u0djV6NBi9Tgdtc0R6w',
        'api_key' =>'xvkwXPp52AUPLBGCXmD5UA==',
        'call_back' => 'https://api-test.betrnk.games/public/api/booming/callback',
        'roll_back' => 'https://api-test.betrnk.games/public/api/booming/rollback',
        'provider_db_id' => 36,
    ],
    'justplay' => [
        'provider_db_id' => 52,
        'api_url' => 'http://api.justplay-gaming.com',
        'USD' => [
            'id_user' => 658,
            'password' => '14d6a01e05c6a468ae01390b3eda1a7c'
        ],
        'EUR' => [
            'id_user' => 657,
            'password' => 'a92922dc006fded3236ad20183735838'
        ],
        'JPY' => [
            'id_user' => 659,
            'password' => '8b7a2cbdc87bfcb26d832dae51935a16'
        ],
        'PHP' => [
            'id_user' => 660,
            'password' => 'ac8f4a58d24442cb74bbc289709bc095'
        ],
        'THB' => [
            'id_user' => 661,
            'password' => 'f16eb1a45619280532c430a9f2a17adb'
        ],
          'INR' => [
            'id_user' => 743,
            'password' => 'd6efe4a276b41a4830580befaad096fc'
        ],
    ],
    'playstar' => [
        'provider_db_id' => 55,
        'api_url' => 'https://stage-api.iplaystar.net',
        'host_id' => [
           'USD' => '3d3bfe1c05f600200af031e4c888f8e5',
           'THB' => 'c1e9d133f03b08c29d6d03f3441a59e7',
           'TRY' => 'bb9be14cbf5460a82277797dc39c46d0',
           'IRR' => '79d9b5da1d79cfe588f2db352e617a34',
           'EUR' => 'a119af190f7c8f8e8c236ced2e80b673',
           'KRW' => '13c11ee300417eeb892643b07b224e53',
           'JPY' => '91e006399cad8230d2d97091d8300412'
        ],
    ],
    // 'manna'=>[
    //     'PROVIDER_ID' => 16,
    //     'AUTH_URL'=> 'https://api.mannagaming.com/agent/specify/',
    //     'GAME_LINK_URL' => 'https://api.mannagaming.com/agent/specify/',
    //     'API_KEY'=> 'GkyPIN1mD*yzjxzQumq@cZZC!Vw%b!kIVy&&hk!a',
    //     'AUTH_API_KEY'=> 'GkyPIN1mD*yzjxzQumq@cZZC!Vw%b!kIVy&&hk!a',
    //     'CLIENT_API_KEY' => '4dtXHSekHaFkAqbGcsWV2es4BTRLADQP',
    //     'IDN_API_KEY'=> 'cxOhILwXLjhhxiUfBv86depT&4HaRjrb',
    // ],
    // 'ozashiki'=>[
    //     'PROVIDER_ID' => 58,
    //     'AUTH_URL'=> 'https://api.mannagaming.com/agent/specify/tigergame/authenticate/auth_token',
    //     'GAME_LINK_URL' => 'https://api.mannagaming.com/agent/specify/tigergame/gameLink/link',
    //     'API_KEY'=> 'Oj3TE7wztwWnKc#!SaQhaIRA8S8mUv1v#3cy5zOs',
    //     'AUTH_API_KEY'=> 'Oj3TE7wztwWnKc#!SaQhaIRA8S8mUv1v#3cy5zOs',
    //     'CLIENT_API_KEY' => 'a5ebcf7ee7268c116b508136d50c1d40',
    //     'PLATFORM_ID' => 'tigergame'
    // ],
    'mannaplay'=>[
        'PROVIDER_ID' => 58,
        'PROVIDER_ID' => 16,
        'AUTH_URL'=> 'https://api.mannagaming.com/agent/specify/',
        'GAME_LINK_URL' => 'https://api.mannagaming.com/agent/specify/',
        'FREE_ROUND_ADD' => 'https://api.mannagaming.com/agent/marketing_tool/Freeround/General/give',
        'default' => [
            'API_KEY'=> 'GkyPIN1mD*yzjxzQumq@cZZC!Vw%b!kIVy&&hk!a',
            'AUTH_API_KEY'=> 'GkyPIN1mD*yzjxzQumq@cZZC!Vw%b!kIVy&&hk!a',
            'CLIENT_API_KEY' => '4dtXHSekHaFkAqbGcsWV2es4BTRLADQP',
            'PLATFORM_ID' => 'betrnk'
        ],
        "15" => [ // Operator id 15 - Everymatrix
            'API_KEY'=> 'Oj3TE7wztwWnKc#!SaQhaIRA8S8mUv1v#3cy5zOs',
            'AUTH_API_KEY'=> 'Oj3TE7wztwWnKc#!SaQhaIRA8S8mUv1v#3cy5zOs',
            'CLIENT_API_KEY' => 'a5ebcf7ee7268c116b508136d50c1d40',
            'PLATFORM_ID' => 'tigergame'
        ],
        "30" => [  // Operator id 30 - IDNPLAY
            'API_KEY'=> 'cxOhILwXLjhhxiUfBv86depT&4HaRjrb',
            'AUTH_API_KEY'=> 'cxOhILwXLjhhxiUfBv86depT&4HaRjrb',
            'CLIENT_API_KEY' => '4dtXHSekHaFkAqbGcsWV2es4BTRLADQP',
            'PLATFORM_ID' => 'idnplay'
        ],
    ],
    'solid'=>[
        'PROVIDER_ID' => 1,
        'LAUNCH_URL'=> 'https://instage.solidgaming.net/api/launch/',
        'API_ENDPOINT' => 'https://instage.solidgaming.net/api/wallet/',
        'BRAND' => 'BETRNKMW',
        'AUTH_USER' => 'betrnkmw-stage',
        'AUTH_PASSWORD' => 'wyE4PEGHWkWyU5TjdNk2g'
    ],
    'habanero'=>[
        'api_url' => 'https://app-test.insvr.com/go.ashx?',
        'brandID' => '2416208c-f3cb-ea11-8b03-281878589203',
        'apiKey' => '3C3C5A48-4FE0-4E27-A727-07DE6610AAC8',
        'passKey' => 'Rja5ZK4kN1GA0R8C',
        'tw_game_launch_url' => 'https://app-test.insvr.com/play?',
        'tw_api_url' => 'https://ws-test.insvr.com/jsonapi',
        'tw_brandID' => '5e2b7240-ac7d-eb11-b566-00155db545d0',
        'tw_apiKey' => '54806FDE-3622-43B3-A407-46ED64B517B0',
        'provider_id' => '24'
    ],
    'simpleplay' => [
        'PROVIDER_ID' => 35,
        'LOBBY_CODE' => 'S592',
        'SECRET_KEY' => 'A872BAFDFA8349CC824A460E7AC02515',
        'MD5_KEY' => 'GgaIMaiNNtg',
        'ENCRYPT_KEY' => 'g9G16nTs',
        'API_URL' => 'http://api.sp-portal.com/api/api.aspx'
    ],
    'ygg'=>[
        'api_url' => 'https://static-stage-tw.248ka.com/init/launchClient.html?',
        'Org' => 'TIGERGAMES',
        'topOrg' => 'TIGERGAMESGroup',
        'provider_id' => 38,
    ],
    'spade'=>[
        'prefix' => 'TIGERG', 
        'api_url'=> 'https://api-egame-staging.sgplay.net/api',
        'merchantCode' => 'TIGERG',  
        'siteId' => 'SITE_USD1',
        'provider_id' => 59, // sub_provider_id
    ],
    'spadetw'=>[
        'prefix' => 'TIGERGTW', 
        'api_url'=> 'https://api-egame-staging.sgplay.net/api',
        'game_url' => 'https://lobby-egame-staging.sgplay.net/TGTW/auth/',
        'merchantCode' => 'TGTW',  
        'currency' => "JPY",
    ],
    'majagames'=>[
        'auth' => 'wsLQrQM1OC1bVscK',
        'provider_id' => 39,
        'prefix' => 'MAJA_', 
        'api_url'=> 'http://api-integration.mj-02.com/api/MOGI', //slot api
        'tapbet_api_url'=> 'https://tbb-integration.mj-02.com/api', //tapbet api
    ],
    'spade_curacao'=>[
        'prefix' => 'TIGERG', 
        'api_url'=> 'https://api-egame-staging.spadecasino777.com/api',
        'lobby_url'=> 'https://lobby-egame-staging.spadecasino777.com/TIGEREU/auth/?',
        'merchantCode' => 'TIGEREU',
        'siteId' => 'SITE_EU1',
        'provider_id' => 73, // sub_provider_id
    ],
    'vivo' => [
        'PROVIDER_ID' => 34,
        'OPERATOR_ID' => '3003616',
        'OPERATOR_TOKEN' => 'ODHr6OYKEblDZbQd23vNTfdjNXrriazJ',
        'SERVER_ID' => '6401748',
        'PASS_KEY' => '7f1c5d',
        'VIVO_URL' => 'https://games.vivogaming.com/',
        'BETSOFT_URL' => 'https://2vivo.com/FlashRunGame/RunRngGame.aspx',
        'SPINOMENAL_URL' => 'https://www.2vivo.com/flashRunGame/RunSPNRngGame.aspx',
        'TOMHORN_URL' => 'https:///www.2vivo.com/FlashRunGame/Prod/RunTomHornGame.aspx',
        'NUCLEUS_URL' => 'https://2vivo.com/FlashRunGame/set2/RunNucGame.aspx',
        'PLATIPUS_URL' => 'https://www.2vivo.com/flashrungame/set2/RunPlatipusGame.aspx',
        'LEAP_URL' => 'https://www.2vivo.com/flashrungame/RunGenericGame.aspx',
        '7MOJOS_URL' => 'https://www.2vivo.com/flashrungame/RunGenericGame.aspx',
    ],
    'oryx' => [
        'PROVIDER_ID' => 18,
        'GAME_URL' => 'https://play-prodcopy.oryxgaming.com/agg_plus_public/launch/wallets/WELLTREASURETECH/games/'
    ],
    'netent' => [
        'provider_db_id' => 45,
        'casinoID' => "tigergames",//casinoID
        'merchantId' => "testmerchant",//soap api login
        'merchantPassword' => "testing",//soap api login
    ],
    'goldenF'=>[
        'provider_id' => 41,
        '1' => [ # Seamless Wallet Wallet Type 1
            'USD' => [
                'api_url'=> 'http://t14u.test.gf-gaming.com/gf',
                'secret_key' => '2779e483717e1962164ee7e5b1959133',
                'operator_token' => '4436d634017f06638d4faf9dce6deda3',
                'wallet_code' => 'gf_gps_wallet',
            ],
            'CNY' => [
                'api_url'=> 'http://t14c.test.gf-gaming.com/gf',
                'secret_key' => 'd778a48c6779ef194b6aad7573c2c06d',
                'operator_token' => 'e23103ada31f2038918f7d621e27e871',
                'wallet_code' => 'gf_gps_wallet',
            ],
        ],
        '2' => [ # Transfer Wallet Wallet Type 2
            'USD' => [
                'api_url'=> 'http://tigu.test.gf-gaming.com/gf',
                'secret_key' => '33ba14b74a26e3fea1bf87db53a9f371',
                'operator_token' => 'b18ba7f03f11c602ae4e001ae0be0c20',
                'wallet_code' => 'gf_gps_wallet',
            ],
            'CNY' => [
                'api_url'=> 'http://tgr.test.gf-gaming.com/gf',
                'secret_key' => 'b18d99f11861042e2c66f11a1f9a62cb',
                'operator_token' => '009583d3138a9e3934787112c345ef10',
                'wallet_code' => 'gf_gps_wallet',
            ],
        ],
        '3' => [ # Pure Transfer Wallet Wallet Type 3
            'USD' => [
                'api_url'=> 'http://tigu.test.gf-gaming.com/gf',
                'secret_key' => '33ba14b74a26e3fea1bf87db53a9f371',
                'operator_token' => 'b18ba7f03f11c602ae4e001ae0be0c20',
                'wallet_code' => 'gf_gps_wallet',
            ],
            'CNY' => [
                'api_url'=> 'http://tgr.test.gf-gaming.com/gf',
                'secret_key' => 'b18d99f11861042e2c66f11a1f9a62cb',
                'operator_token' => '009583d3138a9e3934787112c345ef10',
                'wallet_code' => 'gf_gps_wallet',
            ],
        ],
    ],
    'ultraplay'=>[
        'domain_url'=> 'https://stage-tet.ultraplay.net',
    ],
    'slotmill'=>[
        'provider_db_id'=> 46,
        'brand' => "TigerGames",
        '19002' => "https://templar-treasures.stage.slotmill.com/",
        '19003' => "https://starspell.stage.slotmill.com/",
        '19005' => "https://wildfire.stage.slotmill.com/",
        '19007' => "https://vikings-creed.stage.slotmill.com/",
        '19008' => "https://outlaws.stage.slotmill.com/",
        '19009' => "https://neon-dreams.stage.slotmill.com/",
        "19010" => "https://lucky-lucifer.stage.slotmill.com",
        "19011" => "https://vegas-gold.stage.slotmill.com",
        "19012" => "https://three-samurai.stage.slotmill.com",
        "19013" => "https://thunder-wheel.stage.slotmill.com",
        "TransferWallet" => [
            "provider_db_id" => 51,
            "secret_key" => "490efb5be3435334282833e1afd0514a",
            "host" => "https://stageapi.slotmill.com",
            "org" => "TigerGames",
        ],
    ],
    'pgvirtual' => [
         'provider_db_id'=> 47,
         'auth' => 'b5d687d6e639f305a14ccd6acafcd284',
         'game_url' => 'https://staging.tigergames.pgvirtual.pg.company/v-ui/?operator=tigergames&init_code=',
        //  'game_url' => 'https://tigergames.pg.company/v-ui/?operator=tigergames&init_code=',
         
    ],
    'dragongaming'=>[
        'PROVIDER_ID' => 50,
        'API_BASE_URL'=> 'https://staging-api.dragongaming.com/v1/',
        'API_KEY'=> '9jipwlTSmds3vtGs',
    ],
    'onlyplay' => [
        'provider_db_id'=> 54,
        'partner_id' => 515,
        'api_url' => 'https://int.stage.onlyplay.net/api/get_frame',
        'secret_key' => '1242aVGdpeZRDiG5aS8iss6E5dE5sLOe',
    ],
    'toptrendgaming' => [
        'provider_db_id' => 56,
        'api_url' => 'https://ams-api.stg.ttms.co:8443/cip/gametoken/',
    ],
    'nolimit'=>[
        'provider_db_id' => 59,
        'api_url' => 'https://partner.nolimitcdn.com/loader/game-loader.html?',
        'api_freebet' => 'https://partner.nolimitcity.com/api/v1/json',
        'operator' =>'TG_DEV',
        'operator_key' => 'on2ha5xie7Hu',
        '20' => [// APLUS
            'operator' =>'ELDOAH_CUR ',
            'operator_key' => 'TG_DEV'
        ],
        '15' => [// EveryMatrix
            'operator' =>'BETRNK ',
            'operator_key' => 'TG_DEV',
        ],
        '1' => [// TigerGames
            'operator' =>'BETRNK ',
            'operator_key' => 'TG_DEV',
        ],
    ],
    'bgaming' => [
         'PROVIDER_ID'=> 49,
         'CASINO_ID' => 'tigergames-int',
         'GCP_URL' => 'https://int.bgaming-system.com/a8r/tigergames-int',
         'AUTH_TOKEN' => 'HZhPwLMXtHrmQUxjmMvBmCPM'
    ],
     'smartsoft'=>[
        'provider_db_id' => 60,
        'api_url' => 'https://eu-staging.ssgportal.com/GameLauncher',
        'PortalName' => 'tigergames',
        'SecretHashKey' => '97bce50a-86bd-4b88-b052-32cf7059afa5'
    ],
    'mancala' => [
         'PROVIDER_ID'=> 61,
         'BRAND_NAME' => 'Tiger Conexion',
         'PARTNER_ID' => 264,
         'RGS_URL' => 'https://slots-test.mancalagroup.net/api/partners/partners',
         'API_KEY' => 'QNGIXXAI'
    ],
    'idnpoker' => [
        'PROVIDER_ID'=> 110,
        'URL' => 'https://scr.idngame.com:2800/',
        "agent" => [
            "JFPAA" => "5e2c5dc120e4ae6aeeae4000e",
        ]
   ],  
   'funkygames' => [
        'provider_db_id'=> 63,
        'Authentication' => '558b0897-67f3-4b50-8fd0-fbf9503522c5',
        'User-Agent' =>'tg',
        'api_url' => 'http://trial-gp-api.funkytest.com/',
        'api_report_url' => 'http://trial-gp-api-report.funkytest.com/',
   ],
   'crashgaming' => [
        'pdbid'=> 68, // TG DB ID
        'authToken' => 'HtVTkjRFi87fIF7QgixJPYx1lNkBBpkD', // We generated this one!
   ],
   'vcci' => [
        'PROVIDER_ID'=> 000,
        'API_KEY' => 'eIPk0Bxz-R4crhhPY-zL5SHSpC-MQ8xkICD',
   ],
   'playtech' => [
        'provider_db_id'=> 64,
        'secret_key' => 'L9MfZ3iLdaD3hiT7cv2W',
        'brand_id' => 239,
        'brand_name' => 'TIGER_GAMES',
        'api_url' => 'https://api-stg.gmgiantgold.com',
   ],
   'amusegaming' => [
        'provider_db_id'=> 65,
        'operator' => [
            'TEST' => [
                'operator_id' => 'betrnkUSDtest',
                'public_key' => 'EI4gh2M62f7V9mmH9SNI',
                'secret_key' => 'dkzJ2WSYxDCxLYk0qT9S'
            ],
        ],
        'launch_url' => 'https://static.slotomatic.net/launch/index.html',
        'api_url' => 'https://api4.slotomatic.net/api/',
        'modetype' => 'TEST',
    ],
    'quickspinDirect' => [
        'provider_db_id'=> 66,
        'api_url' => 'https://d1oij17g4yikkz.cloudfront.net',
        'partner_id' => 2076,
    ],
    'spearhead' => [
        'provider_db_id'=> 67,
        'api_url' => 'https://gamelaunch-stage.everymatrix.com/Loader/Start/',
        'operator' => 'TigerGamesUser',
        'operator_key' => 'nXxOCeSCh4ko4q6q',
        'opid'=> 2540
    ],

];

?>

