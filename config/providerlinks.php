<?php


return [
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
            'username' => 'tigergames',
            'password' => 'Mm010935468-',
            'secure_code' => '20c60c1c-fc16-42b7-9269-291d2b2b0346',
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
        'api_url' => 'https://papi.awsxpartner.com/b2b/',
        'merchant_id' => 'TG',
        'merchant_key' => 'ff6d8f150ff9a98e218b62c7d10371a659c3431f98dd9d64cbe72d402d74f9fb717c0b0b1ae2c4e0e21f109780ea5ef63d12fb03b52570214d391eea437393fe',
    ],
    'cqgames' => [
        "prefix" => "TG",
        "pdbid"=> 30, // Database ID nothing todo with the provider!
        'api_url' => 'https://apie.cqgame.cc',
        'api_tokens' => [
            'USD' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyaWQiOiI1ZjYwNjFiNDg1N2E5NzAwMDFiZjA3MTUiLCJhY2NvdW50IjoidGlnZXJnYW1lcyIsIm93bmVyIjoiNWY2MDYxYjQ4NTdhOTcwMDAxYmYwNzE1IiwicGFyZW50Ijoic2VsZiIsImN1cnJlbmN5IjoiVVNEIiwianRpIjoiNjE0ODYyMDI5IiwiaWF0IjoxNjAwMTUxOTg4LCJpc3MiOiJDeXByZXNzIiwic3ViIjoiU1NUb2tlbiJ9.XgbWboAdrRtrmKhvYymBXbdVqEwLccry0no0-8blFxI',
        ],
        'wallet_token' => [
            'USD' => 'avF0GzMKaFJuBLwZfEfq2SseM1ZKPLcf',
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
        "gamelaunch" => "https://gamesstage.kaga88.com",
        "ka_api" => "https://rmpstage.kaga88.com/kaga/",
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
    ],
    'tidygaming' => [
        'url_lunch' => 'https://asia.h93r.com/api/game/outside/link',
        'API_URL' => 'https://asia.h93r.com/',
        'client_id' => 'c9c219fc',
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
        'operator_token' => '642052d1627c8cae4a288fc82a8bf892',
        'secret_key' => '02f314db35a0dfe4635dff771b607f34',
    ],
    'tpp' => [
        'gamelaunch_url' => 'https://tigergames-dk2.pragmaticplay.net/gs2c/playGame.do',
        'secureLogin' => 'tg_tigergames', //or stylename
        'secret_key' => 'uSPij46kVe977JH2',
    ],
    'wazdan'=>[
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
        'api_url' => 'https://api.asia.booming-games.com/',
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
        'api_url'=> 'https://merchantapi.silverkirin88.com/api/',
        'merchantCode' => 'TIGERG',
        'siteId' => 'SITE_USD1',
        'provider_id' => 37,
    ],
    'majagames'=>[
        'auth' => 'wsLQrQM1OC1bVscK',
        'provider_id' => 39,
        'prefix' => 'MAJA_', 
        'api_url'=> 'https://api.sagq3ktbaxo.com/api/MOGI/', //slot api
        'tapbet_api_url'=> 'https://tbb.sagq3ktbaxo.com/api', //tapbet api
    ],
    'spade_curacao'=>[
        'prefix' => 'TIGERG', 
        'api_url'=> 'https://merchantapi.oliveloris.com/api/',
        'lobby_url'=> 'https://lobby.olivelorisplay.com/TIGEREU/auth/?',
        'merchantCode' => 'TIGERGEU',
        'siteId' => 'SITE_EU1',
        'provider_id' => 37,
    ],
    'vivo' => [
        'PROVIDER_ID' => 34,
        'OPERATOR_ID' => '75674',
        'SERVER_ID' => '51681981',
        'PASS_KEY' => '7f1c5d',
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
        'SERVER_ID' => '51681981',
        'PASS_KEY' => '7f1c5d',
        'VIVO_URL' => 'https://games.vivogaming.com/',
        'BETSOFT_URL' => 'https://1vivo.com/FlashRunGame/RunRngGame.aspx',
        'SPINOMENAL_URL' => 'https://www.1vivo.com/flashRunGame/RunSPNRngGame.aspx',
        'TOMHORN_URL' => 'https:///www.1vivo.com/FlashRunGame/Prod/RunTomHornGame.aspx',
        'NUCLEUS_URL' => 'https://2vivo.com/FlashRunGame/set2/RunNucGame.aspx',
        'PLATIPUS_URL' => 'https://www.1vivo.com/flashrungame/set2/RunPlatipusGame.aspx',
        'LEAP_URL' => 'https://www.2vivo.com/flashrungame/RunGenericGame.aspx'
    ]
];

?>