<?php

$middleware_url_api = 'https://api.betrnk.games';
$gamelobby_site = 'https://daddy.betrnk.games';
$play_betrnk = 'https://play.betrnk.games';

return [
    'play_betrnk' => $play_betrnk,
    'tigergames' => $gamelobby_site,
    'oauth_mw_api' => [
        'access_url' => $middleware_url_api.'/oauth/access_token',
        'mwurl' => $middleware_url_api,
        'client_id' => 1,
        'client_secret' => 'QPmdvSg3HGFXbsfhi8U2g5FzAOnjpRoF',
        'username' => 'randybaby@gmail.com',
        'password' => '_^+T3chSu4rt+^_',
        'grant_type' => 'password',
    ],
    'icgaminglogin' => 'https://admin.iconic-gaming.com/service/login',
    'icgaminggames' => 'https://admin.iconic-gaming.com/service/api/v1/games?type=all&lang=en',
    'icgagents'=>[
        'jpyagents'=>[
            'username' => 'betrnkjpy',
            'password' => ']WKtkT``mJCe8N3J',
            'secure_code' => '60e7a70e-806a-479c-af0b-d3c83a6616c1',
        ],
        'euragents'=>[
            'username' => 'betrnkeuro',
            'password' => ']WKtkT``mJCe8N3J',
            'secure_code' => '2e40f6fe-b99c-433a-8a56-7c209f9cdb31',
        ],
        'cnyagents'=>[
            'username' => 'betrnkcny',
            'password' => ']WKtkT``mJCe8N3J',
            'secure_code' => '7a640de8-82ce-4fe7-b0a3-0bea404bceb8',
        ],
        'krwagents'=>[
            'username' => 'betrnkkrw',
            'password' => ']WKtkT``mJCe8N3J',
            'secure_code' => 'e2d899ad-7545-4096-8892-3d6c751ee32a',
        ],
        'phpagents'=>[
            'username' => 'betrnkphp',
            'password' => ']WKtkT``mJCe8N3J',
            'secure_code' => '0fa2144d-7529-4483-9306-6515485ce6c7',
        ],
        'thbagents'=>[
            'username' => 'betrnkthb',
            'password' => ']WKtkT``mJCe8N3J',
            'secure_code' => '41aa6cf7-ab5f-49e1-86e0-93ee217b6c79',
        ],
        'tryagents'=>[
            'username' => 'betrnktry',
            'password' => ']WKtkT``mJCe8N3J',
            'secure_code' => '218e1ad5-2745-4ebe-b694-16c4bf3d2828',
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
            'username' => 'tigergames',
            'password' => 'tigergamesxicg',
            'secure_code' => '20c60c1c-fc16-42b7-9269-291d2b2b0346',
        ],
        'rubagents'=>[
            'username' => 'betrnkrub',
            'password' => ']WKtkT``mJCe8N3J',
            'secure_code' => '540aeeaa-4de0-41fa-8195-52b7a4244c9d',
        ],
        'irragents'=>[
            'username' => 'betrnkirr',
            'password' => ']WKtkT``mJCe8N3J',
            'secure_code' => '09f896ae-ada9-42a6-8178-6e8aa517acdd',
        ],
    ],
    'endorphina' => [
        'url' => 'https://test.endorphina.network/api/sessions/seamless/rest/v1',
        'nodeId' => 1004,
        'secretkey' => 'E5A8E26AEA2D4F2C9D2BB9BBC6B9A715',
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
        'api_url' => 'https://papi.awsxpartner.com/b2b',
        '1USD'=> [ // 
            'merchant_id' => 'TG',
            'merchant_key' => 'ff6d8f150ff9a98e218b62c7d10371a659c3431f98dd9d64cbe72d402d74f9fb717c0b0b1ae2c4e0e21f109780ea5ef63d12fb03b52570214d391eea437393fe',
        ], 
        '1TRY'=> [ // 
            'merchant_id' => 'TGBS',
            'merchant_key' => '10e86638c0c6e8d494cecd05dad0b56c59b50bafc7271903a1087ba232bebbec52ce4c752ba4c8f1b0fcd35e5f5a0f08b1f6438d985f23167d85bbbd2bfa52ce',
        ],
        '1KRW' => [ // BESOFTED for TG
            'merchant_id' => 'BETSOFTED',
            'merchant_key' => '57e66f5778cda00a6048772a337f65cef3eac9d7bf44d0ae79748b0e71c31ac16feadbd9caf5cf750a55bc4958aca985bdedf8a2250d6fabf29f58c2cb263ef4',
        ],
        '1RUB' => [ // BESOFTED for TG
            'merchant_id' => 'BETSOFTED',
            'merchant_key' => '57e66f5778cda00a6048772a337f65cef3eac9d7bf44d0ae79748b0e71c31ac16feadbd9caf5cf750a55bc4958aca985bdedf8a2250d6fabf29f58c2cb263ef4',
        ],
        '1EUR' => [ // BESOFTED for TG
            'merchant_id' => 'BETSOFTED',
            'merchant_key' => '57e66f5778cda00a6048772a337f65cef3eac9d7bf44d0ae79748b0e71c31ac16feadbd9caf5cf750a55bc4958aca985bdedf8a2250d6fabf29f58c2cb263ef4',
        ],
        '1IRR' => [ // BESOFTED for TG
            'merchant_id' => 'BETSOFTED',
            'merchant_key' => '57e66f5778cda00a6048772a337f65cef3eac9d7bf44d0ae79748b0e71c31ac16feadbd9caf5cf750a55bc4958aca985bdedf8a2250d6fabf29f58c2cb263ef4',
        ], 
        '2THB' => [ // ASK b2b THB
            'merchant_id' => 'ASKME',
            'merchant_key' => 'a93f62228b46aeac1f4cfcc3bcb98032453cdf93e21d4c601c0e350dcb8afd6e65cfd99f3ac11baad382f310398e5b89fca10b56b102f8b9304e41e5aa3c9bb9',
        ],
        '3USD' => [ // XIGOLO USD
            'merchant_id' => 'XIGOLO',
            'merchant_key' => '44c1c6e19674e57ef7f5dfb0d538cccc2387cb13c3f5277c09876c463e508175a31dba77a8a27c708f67cdec7a24e39c996e2a4ea30dff528134877a8b2884dd',
        ],
        '4USD' => [  // TGC ME THB
            'merchant_id' => 'TGC',
            'merchant_key' => '129d637c1aa5d3f3c6b9ea759d04d00250c9f4be29d71f72abd189f0c8283f263e08a2a99b70663ee28dc4e025cca82a0b955e2a9fcca604c72aa9dc22cf5232',
        ], 
        '6THB' => [ // ASK b2c THB
            'merchant_id' => 'ASKME',
            'merchant_key' => 'a93f62228b46aeac1f4cfcc3bcb98032453cdf93e21d4c601c0e350dcb8afd6e65cfd99f3ac11baad382f310398e5b89fca10b56b102f8b9304e41e5aa3c9bb9',
        ],
        '7USD' => [ // XIGOLO/CAZICAZI
            'merchant_id' => 'XIGOLO',
            'merchant_key' => '44c1c6e19674e57ef7f5dfb0d538cccc2387cb13c3f5277c09876c463e508175a31dba77a8a27c708f67cdec7a24e39c996e2a4ea30dff528134877a8b2884dd',
        ],
        '8USD' => [ // BESOFTED
            'merchant_id' => 'BETSOFTED',
            'merchant_key' => '57e66f5778cda00a6048772a337f65cef3eac9d7bf44d0ae79748b0e71c31ac16feadbd9caf5cf750a55bc4958aca985bdedf8a2250d6fabf29f58c2cb263ef4',
        ],
        '8KRW' => [ // BESOFTED
            'merchant_id' => 'BETSOFTED',
            'merchant_key' => '57e66f5778cda00a6048772a337f65cef3eac9d7bf44d0ae79748b0e71c31ac16feadbd9caf5cf750a55bc4958aca985bdedf8a2250d6fabf29f58c2cb263ef4',
        ],
        '8RUB' => [ // BESOFTED
            'merchant_id' => 'BETSOFTED',
            'merchant_key' => '57e66f5778cda00a6048772a337f65cef3eac9d7bf44d0ae79748b0e71c31ac16feadbd9caf5cf750a55bc4958aca985bdedf8a2250d6fabf29f58c2cb263ef4',
        ],
        '8TRY' => [ // BESOFTED
            'merchant_id' => 'BETSOFTED',
            'merchant_key' => '57e66f5778cda00a6048772a337f65cef3eac9d7bf44d0ae79748b0e71c31ac16feadbd9caf5cf750a55bc4958aca985bdedf8a2250d6fabf29f58c2cb263ef4',
        ],
        '8EUR' => [ // BESOFTED
            'merchant_id' => 'BETSOFTED',
            'merchant_key' => '57e66f5778cda00a6048772a337f65cef3eac9d7bf44d0ae79748b0e71c31ac16feadbd9caf5cf750a55bc4958aca985bdedf8a2250d6fabf29f58c2cb263ef4',
        ],
        '8IRR' => [ // BESOFTED
            'merchant_id' => 'BETSOFTED',
            'merchant_key' => '57e66f5778cda00a6048772a337f65cef3eac9d7bf44d0ae79748b0e71c31ac16feadbd9caf5cf750a55bc4958aca985bdedf8a2250d6fabf29f58c2cb263ef4',
        ],
        '10KRW' => [ // MAS46
            'merchant_id' => 'TGMAS',
            'merchant_key' => '7b03756e2a70143fe0bc36f7b2d5e1c92e011bb1fe49d7beee69b4bea0e5d73341c29d046242826bde925bf0764a9fff95940f6a67c4542cff189f15526711d0',
        ],
    ],
    'justplay' => [
        'provider_db_id' => 49,
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
     'cqgames' => [
        "prefix" => "TG",
        "pdbid"=> 30, // Database ID nothing todo with the provider!
        'api_url' => 'https://apie.cqgame.cc',
        'api_tokens' => [
        	'CNY' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyaWQiOiI1ZjZjNDIwYzkxOGI5ODAwMDE5YzM0ODgiLCJhY2NvdW50IjoidGVzdF9jbnkiLCJvd25lciI6IjVmNjA2MWI0ODU3YTk3MDAwMWJmMDcxNSIsInBhcmVudCI6IjVmNjA2MWI0ODU3YTk3MDAwMWJmMDcxNSIsImN1cnJlbmN5IjoiQ05ZIiwianRpIjoiODI1NTcwOTkwIiwiaWF0IjoxNjAwOTMwMzE2LCJpc3MiOiJDeXByZXNzIiwic3ViIjoiU1NUb2tlbiJ9.fwf2b4i5seyD_cEZyYkmpByTQaaxhPfH_IwEJGDRZ5A',
            'USD' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyaWQiOiI1ZjYwNjFiNDg1N2E5NzAwMDFiZjA3MTUiLCJhY2NvdW50IjoidGlnZXJnYW1lcyIsIm93bmVyIjoiNWY2MDYxYjQ4NTdhOTcwMDAxYmYwNzE1IiwicGFyZW50Ijoic2VsZiIsImN1cnJlbmN5IjoiVVNEIiwianRpIjoiNjE0ODYyMDI5IiwiaWF0IjoxNjAwMTUxOTg4LCJpc3MiOiJDeXByZXNzIiwic3ViIjoiU1NUb2tlbiJ9.XgbWboAdrRtrmKhvYymBXbdVqEwLccry0no0-8blFxI',
            'KRW' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyaWQiOiI2MDllMjk2YTJmZmUzNzAwMDFkYzdhMGYiLCJhY2NvdW50IjoidGdfcHJvZF9rcnciLCJvd25lciI6IjVmNjA2MWI0ODU3YTk3MDAwMWJmMDcxNSIsInBhcmVudCI6IjVmNjA2MWI0ODU3YTk3MDAwMWJmMDcxNSIsImN1cnJlbmN5IjoiS1JXIiwianRpIjoiNDU4NjQwNzIzIiwiaWF0IjoxNjIwOTc4MDI2LCJpc3MiOiJDeXByZXNzIiwic3ViIjoiU1NUb2tlbiJ9.66x2XN6aZgaybUmfxbn7KXo673SfOwK8tGXZuDcgZIA',
            'TRY' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyaWQiOiI2MGI5Nzc4NTA0YTRlOTAwMDExYmZlN2IiLCJhY2NvdW50IjoiVEdfVFJZIiwib3duZXIiOiI1ZjYwNjFiNDg1N2E5NzAwMDFiZjA3MTUiLCJwYXJlbnQiOiI1ZjYwNjFiNDg1N2E5NzAwMDFiZjA3MTUiLCJjdXJyZW5jeSI6IlRSWSIsImp0aSI6IjQ0NDA1MzQ1NyIsImlhdCI6MTYyMjc2NzQ5MywiaXNzIjoiQ3lwcmVzcyIsInN1YiI6IlNTVG9rZW4ifQ.8YhPAkdc7rh9waHd7ExYhd5JJ4zhfZrMaDCm3SD1hmE',
            'RUB' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyaWQiOiI2MGI5NzdjY2ZmNTYxMjAwMDE1M2I2NjgiLCJhY2NvdW50IjoiVEdfUlVCIiwib3duZXIiOiI1ZjYwNjFiNDg1N2E5NzAwMDFiZjA3MTUiLCJwYXJlbnQiOiI1ZjYwNjFiNDg1N2E5NzAwMDFiZjA3MTUiLCJjdXJyZW5jeSI6IlJVQiIsImp0aSI6IjE1OTc3MjE0MyIsImlhdCI6MTYyMjc2NzU2NCwiaXNzIjoiQ3lwcmVzcyIsInN1YiI6IlNTVG9rZW4ifQ.zR14KPImICvaGDVwhh05X2lRt1X2OA3seuT8SrjsyLA',
            'EUR' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyaWQiOiI2MGI5NzgxMzA0YTRlOTAwMDExYmZlN2QiLCJhY2NvdW50IjoiVEdfRVVSIiwib3duZXIiOiI1ZjYwNjFiNDg1N2E5NzAwMDFiZjA3MTUiLCJwYXJlbnQiOiI1ZjYwNjFiNDg1N2E5NzAwMDFiZjA3MTUiLCJjdXJyZW5jeSI6IkVVUiIsImp0aSI6IjIyMDcxNTMwMyIsImlhdCI6MTYyMjc2NzYzNSwiaXNzIjoiQ3lwcmVzcyIsInN1YiI6IlNTVG9rZW4ifQ.c84pLaNnvP5Ht_iFtZoVL2zxviepy6eoJjn0qsbalvE',
        ],
        'wallet_token' => [
        	'CNY' => '6yyn5jQvwEKdEwG2ghlRpqAGgBCziGx6',
            'USD' => 'avF0GzMKaFJuBLwZfEfq2SseM1ZKPLcf',
            'KRW' => 'XtSqayV7PoOh76WlWgRZMUA6KlUeVxbB',
            'TRY' => '6yyn5jQvwEKdEwG2ghlRpqAGgBCziGx6',
            'RUB' => '6yyn5jQvwEKdEwG2ghlRpqAGgBCziGx6',
            'EUR' => '6yyn5jQvwEKdEwG2ghlRpqAGgBCziGx6',
        ]
    ],
    'sagaming' => [
        "pdbid"=> 25, // Database ID nothing todo with the provider!
        "prefix" => "TGSA", // Nothing todo with the provider
        "lobby" => "A3107",
        "API_URL" => "http://api.sa-apisvr.com/api/api.aspx",
        "MD5Key" => "GgaIMaiNNtg",
        "EncryptKey" => "g9G16nTs",
        "SAAPPEncryptKey" =>"M06!1OgI",
        "SecretKey" => "4A96929A87814D89B77115F741C4E8C6",
    ],
    'kagaming' => [
        "pdbid"=> 32, // Database ID nothing todo with the provider!
        "gamelaunch" => "https://gamessea.kaga88.com",
        "ka_api" => "https://rmpsea.kaga88.com/kaga/",
        "access_key" => "A95383137CE37E4E19EAD36DF59D589A",
        "secret_key" => "40C6AB9E806C4940E4C9D2B9E3A0AA25",
        "partner_name" => "TIGER",
    ],
    'iagaming' => [
        'auth_key' => '6230204245ebbf14dfdc0ee40960134d',
        'pch' => 'TG01',
        'prefix' => 'TGAMES',
        'iv' => '1650cbec4319180b',
        'url_lunch' => 'https://api.ilustre-analysis.net/user/lunch',
        'url_register' => 'https://api.ilustre-analysis.net/user/register',
        'url_withdraw' => 'https://api.ilustre-analysis.net/user/withdraw',
        'url_deposit' => 'https://api.ilustre-analysis.net/user/deposit',
        'url_balance' => 'https://api.ilustre-analysis.net/user/balance',
        'url_wager' => 'https://api.ilustre-analysis.net/wager/getproject',
        'url_hotgames' => 'https://api.ilustre-analysis.net/index/gethotgame',
        'url_orders' => 'https://api.ilustre-analysis.net/user/searchprders',
        'url_activity_logs' => 'https://api.ilustre-analysis.net/user/searchprders',
    ],
    'tidygaming' => [
        'url_lunch' => 'https://asia.h93r.com/api/game/outside/link',
        'API_URL' => 'https://asia.h93r.com/',
        'client_id' => 'c9c219fc',
        'usd_invite' => '00688c94',
        'thb_invite' => '3f330a37',
        'try_invite' => 'dbf3d92b',
        'SECRET_KEY' => '7f4c25b95934bf4cfcf6a48d7de80b73',
    ],
    'evoplay' => [
        'api_url' => 'https://api.8provider.com',
        'project_id' => '1045',
        'secretkey' => '900980b4fe8ad2d771713f77cde79333',
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
        'PLATFORM_SERVER_URL'=>'https://gate2.betsrv.com/op/',
        'PROJECT_NAME'=>'tigergames',
        'WL'=>'prod',
        'API_TOKEN'=>'BX0qal8GSe5srFozvnZ2azkYB',
    ],
    'fcgaming'=>[
        'url' => 'http://ap1.fcg1688.net',
        'AgentCode' => 'TG',
        'AgentKey' => 'trU20TV8677Ay45W',
        
    ],
    'tgg' => [
        'api_url' => 'https://api.flexcontentprovider.com',
        'project_id' => '1423',
        'api_key' => 'fff477943dd107dbe9827603a8f2eb48',
    ],
    'pgsoft' => [
        'api_url' => 'http://api.pg-bo.me/external',
        'operator_token' => '8DA94287-7B23-4976-ADC2-BC98B773EDB9',
        'secret_key' => 'AE180CC1E8FF48B08A759F9625D725D6',
    ],
    'tpp' => [
        'gamelaunch_url' => 'https://tigergames-tw.pragmaticplay.net/gs2c/playGame.do',
        // 'gamelaunch_url' => 'https://tigergames-dk2.pragmaticplay.net/gs2c/playGame.do',
        'secureLogin' => 'tg_tigergames', //or stylename
        'secret_key' => 'VV23U65sif2AnZ9d',
        // 'secret_key' => 'uSPij46kVe977JH2',
    ],
    'wazdan'=>[
        'operator_data' =>[
            "1"=>"tigergames",
            "2"=>"askmebet",
            "5"=>"askmebet",
            "6"=>"askmebet",
            "3"=>"xigolo",
            "8"=>"betsofted",
        ],
        'operator' => 'tigergames',
        'license' => 'curacao',
        'hmac_scret_key' => '2QvSiQ2KXX8mBM0LyexfAcNNgI5eXzcx',
        'partnercode'=> 'gd1wiurg',
        'gamelaunchurl' => 'https://gamelaunch.wazdan.com/'
    ],
    'evolution'=>[
        'host' => 'https://babylontgg.evo-games.com',
        'ua2Token' => 'caad3c0956c0522e86419d668a69516c6326ae2e',
        'gameHistoryApiToken' => '943a243607186a48dea41ae1f334979b69f6131f',
        'externalLobbyApiToken'=> '4eb42bf658049ce0adbd90f56aed2afd0bf515a0',
        'owAuthToken' => '&-gTqe8bYGCm#pgFBP6G$!5k4m?DjnPM',
        'ua2AuthenticationUrl' => 'https://babylontgg.evo-games.com/ua/v1/babylontgg000001/caad3c0956c0522e86419d668a69516c6326ae2e',
        'env'=>'production'

    ],
    'png'=>[
        'root_url'=> 'https://agacw.playngonetwork.com',
        'pid' => 8888,
        'channel'=> 'desktop',
        'practice'=>0
    ],
    'microgaming'=>[
        'grant_type'=> 'client_credentials',
        'client_id' => 'Tiger_UPG_USD_MA_Test',
        'client_secret'=> 'd4e59abcbf0b4fd88e3904f12c3dfb',
    ],
    'booming' => [
        'api_url' => 'https://api.asia.booming-games.com',
        'api_secret' => 'FWepCk1Pv+dzC++tO+RbnrSAc7HeRLCWDpddESCN/c9oGp0Bw5jpNZSWYAFHNJT2',
        'api_key' =>'GIvbUT7vjwwWczBi1StdMA==',
        'call_back' => 'https://api.betrnk.games/api/booming/callback',
        'roll_back' => 'https://api.betrnk.games/api/booming/rollback',
        'provider_db_id' => 36,
    ],
    'manna'=>[
        'PROVIDER_ID' => 16,
        'AUTH_URL'=> 'https://api.manna-play.com/agent/specify/betrnk/authenticate/auth_token',
        'GAME_LINK_URL' => 'https://api.manna-play.com/agent/specify/betrnk/gameLink/link',
        'AUTH_API_KEY'=> 'kzHFKTpWG%49vaM&C2BdQcf3$*5mi!NUDwubj#nE',
        'CLIENT_API_KEY'=> 'Az5Rm8K56s3TJVjF',
        'API_KEY'=> 'GkyPIN1mD*yzjxzQumq@cZZC!Vw%b!kIVy&&hk!a',
    ],
    'solid'=>[
        'PROVIDER_ID' => 1,
        'LAUNCH_URL'=> 'https://ingametw.solidgaming.net/api/launch/',
        'API_ENDPOINT' => 'https://inapitw.solidgaming.net/api/wallet/',
        'BRAND' => 'BETRNKMW',
        'AUTH_USER' => 'betrnkmw-prod',
        'AUTH_PASSWORD' => 'svYmaDbh3f2TQY93'
    ],
    '5men' => [
        'api_url' => 'http://api.flexcontentprovider.com',
        'project_id' => '1473',
        'api_key' => '4d66b1747cd34e73c5c64c2889ea70ec',
        'provider_db_id' => 52,
    ],
    'habanero'=>[
        'api_url' => 'https://app-a.insvr.com/go.ashx?',
        'brandID' => '6fc71b15-0ed7-ea11-a522-0050f23870d2',
        'apiKey' => '94FF6FB7-4E01-4EDB-9185-44E3B2BC0AAC',
        'passKey' => '78b5cc87-e0c5-4008-9a7e-dcebfd40642a'
    ],
    'simpleplay' => [
        'PROVIDER_ID' => 35,
        'LOBBY_CODE' => 'S592',
        'SECRET_KEY' => '355860191C1D421B8883D9933CA9ACBA',
        'MD5_KEY' => 'GgaIMaiNNtg',
        'ENCRYPT_KEY' => 'g9G16nTs',
        'API_URL' => 'http://api.sp-connection.com/api/api.aspx'
    ],
    'ygg'=>[
        'api_url' => 'https://static-prod-tw.248ka.com/init/launchClient.html?',
        'Org' => 'TIGERGAMES',
        'topOrg' => 'TIGERGAMESGroup',
        'provider_id' => 38,
    ],
    'spade'=>[
        'prefix' => 'TIGERG', 
        'api_url'=> 'https:/merchantapi.silverkirin88.com/api/',
        'merchantCode' => 'TIGERG',
        'siteId' => 'SITE_1',
        'provider_id' => 37,
    ],
    'majagames'=>[
        'auth' => 'wsLQrQM1OC1bVscK',
        'provider_id' => 39,
        'prefix' => 'MAJA_', 
        'api_url'=> 'https://api.sagq3ktbaxo.com/api/MOGI', //slot api
        'tapbet_api_url'=> 'https://tbb.sagq3ktbaxo.com/api', //tapbet api
    ],
    'spade_curacao'=>[
        'prefix' => 'TIGERG', 
        'api_url'=> 'https://merchantapi.oliveloris.com/api/',
        'lobby_url'=> 'https://lobby.olivelorisplay.com/TIGEREU/auth/?',
        'merchantCode' => 'TIGEREU',
        'siteId' => 'SITE_EU1',
        'provider_id' => 73,
    ],
    'vivo' => [
        'PROVIDER_ID' => 34,
        'OPERATOR_ID' => '3003033',
        'SERVER_ID' => '6401748',
        'PASS_KEY' => 'V0TS97aPNV',
        'VIVO_URL' => 'https://games.vivogaming.com/',
        'BETSOFT_URL' => 'https://2vivo.com/FlashRunGame/RunRngGame.aspx',
        'SPINOMENAL_URL' => 'https://www.2vivo.com/flashRunGame/RunSPNRngGame.aspx',
        'TOMHORN_URL' => 'https:///www.2vivo.com/FlashRunGame/Prod/RunTomHornGame.aspx',
        'NUCLEUS_URL' => 'https://www.2vivo.com/FlashRunGame/set2/RunNucGame.aspx',
        'PLATIPUS_URL' => 'https://www.2vivo.com/flashrungame/set2/RunPlatipusGame.aspx',
        'LEAP_URL' => 'https://www.2vivo.com/flashrungame/RunGenericGame.aspx'
    ],
    'oryx' => [
        'PROVIDER_ID' => 18,
        'GAME_URL' => 'https://play-rghr.igplatform.net/agg_plus_public/launch/wallets/BETRNKMW/games/',
    ],
    'goldenF'=>[
        'provider_id' => 41,
        '1' => [ # Seamless Wallet Wallet Type 1
            'USD' => [
                'api_url'=> 'https://t14u.api.goldenf.me/gf',
                'secret_key' => '2779e483717e1962164ee7e5b1959133',
                'operator_token' => '4436d634017f06638d4faf9dce6deda3',
                'wallet_code' => 'gf_gps_wallet',
            ],
            'CNY' => [
                'api_url'=> 'https://t14c.api.goldenf.me/gf',
                'secret_key' => 'd778a48c6779ef194b6aad7573c2c06d',
                'operator_token' => 'e23103ada31f2038918f7d621e27e871',
                'wallet_code' => 'gf_gps_wallet',
            ],
        ],
        '2' => [ # Transfer Wallet Wallet Type 2
            'USD' => [
                'api_url'=> 'https://t14u.api.goldenf.me/gf',
                'secret_key' => '2779e483717e1962164ee7e5b1959133',
                'operator_token' => '4436d634017f06638d4faf9dce6deda3',
                'wallet_code' => 'gf_gps_wallet',
            ],
            'CNY' => [
                'api_url'=> 'https://t14c.api.goldenf.me/gf',
                'secret_key' => 'd778a48c6779ef194b6aad7573c2c06d',
                'operator_token' => 'e23103ada31f2038918f7d621e27e871',
                'wallet_code' => 'gf_gps_wallet',
            ],
        ],
    ],
    'netent' => [
        'provider_db_id' => 44, //CHANGE THE PROVIDER ID THIS 45 IS FROM STAGING
        'casinoID' => "tigergames",//casinoID
        'merchantId' => "tigergamesws",//soap api login
        'merchantPassword' => "G4Rvxun3BFJjULGp",//soap api login
    ],
    'playstar' => [
        'provider_db_id' => 56,
        'api_url' => 'https://tigergames-api.claretfox.com',
        'host_id' => [
           'USD' => '3d3bfe1c05f600200af031e4c888f8e5',
           'THB' => 'c1e9d133f03b08c29d6d03f3441a59e7',
           'TRY' => 'bb9be14cbf5460a82277797dc39c46d0',
           'IRR' => '79d9b5da1d79cfe588f2db352e617a34',
           'EUR' => 'a119af190f7c8f8e8c236ced2e80b673',
        ],
    ],
    'slotmill'=>[
        'provider_db_id'=> 47,
        'brand' => "TigerGames",
        '19002' => "https://templar-treasures.slotmill.com/",
        '19003' => "https://starspell.slotmill.com/",
        '19005' => "https://wildfire.slotmill.com/",
        '19007' => "https://vikings-creed.slotmill.com/",
        '19008' => "https://outlaws.slotmill.com/",
        '19009' => "https://neon-dreams.slotmill.com/",
        '19010' => "https://lucky-lucifer.slotmill.com",
        '19011' => "https://vegas-gold.slotmill.com",
    ],

    'pgvirtual' => [
         'provider_db_id'=> 48,
         'auth' => 'yDpnkv3UNHRx4zrtsMwrAPRdwWT5swAK',
         'game_url' => 'https://tigergames.pgvirtual.eu/v-ui/?operator=tigergames&init_code=',
    ],
    'onlyplay' => [
        'provider_db_id'=> 54,
        'partner_id' => 515,
        'api_url' => 'https://int.onlyplay.net/api/get_frame',
        'secret_key' => '1a8dNxc7NmZd688z86xRBrfyQbX1mxsW',
    ],
    'ozashiki'=>[
        'PROVIDER_ID' => 58,
        'AUTH_URL'=> 'https://api.manna-play.com/agent/specify/tigergame/authenticate/auth_token',
        'GAME_LINK_URL' => 'https://api.manna-play.com/agent/specify/tigergame/gameLink/link',
        'API_KEY'=> 'UrkiUyLGMUBAMQi25DOtncIMFI1cESXpInc#u9Lm',
        'AUTH_API_KEY'=> 'UrkiUyLGMUBAMQi25DOtncIMFI1cESXpInc#u9Lm',
        'CLIENT_API_KEY' => 'QTm6t5PFehcrCTu4rKL8sPCwv2RwtMrM',
        'PLATFORM_ID' => 'tigergame'
    ],

];

?>
