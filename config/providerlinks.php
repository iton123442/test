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
        "pdbid"=> 30, // Database ID nothing todo with the provider!
        'api_url' => 'http://api.cqgame.games',
        'api_tokens' => [
            'USD' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyaWQiOiI1ZjIyODY1ZWNkNTY1ZjAwMDEzZjUyZDAiLCJhY2NvdW50IjoidGlnZXJnYW1lcyIsIm93bmVyIjoiNWYyMjg2NWVjZDU2NWYwMDAxM2Y1MmQwIiwicGFyZW50Ijoic2VsZiIsImN1cnJlbmN5IjoiVVNEIiwianRpIjoiMjQ3NDQ1MTQzIiwiaWF0IjoxNTk2MDk4MTQyLCJpc3MiOiJDeXByZXNzIiwic3ViIjoiU1NUb2tlbiJ9.fdoQCWGPkYNLoROGR9jzMs4axnZbRJCnnLZ8T2UDCwU',
            'CNY' => '7CBCiX3qf5zMfYijIAmanbP2JB2HiBAi',
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
        'gamelaunch_url' => 'https://tigergames-sg0.prerelease-env.biz/gs2c/playGame.do',
        'secureLogin' => 'tg_tigergames', //or stylename
        'secret_key' => 'testKey',
    ],
    'wazdan'=>[
        'operator' => 'tigergames',
        'license' => 'curacao',
        'hmac_scret_key' => 'uTDVNr4wu6Y78SNbr36bqsSCH904Rcn1',
        'partnercode'=> 'gd1wiurg',
        'gamelaunchurl' => 'https://gl-staging.wazdanep.com/'
    ],
    'png'=>[
        'root_url'=> 'https://agastage.playngonetwork.com',
        'pid' => 8888,
        'channel'=> 'desktop',
        'practice'=>0
    ],
    'booming' => [
        'api_url' => 'https://api.intgr.booming-games.com',
        'api_secret' => 'NQGRafUDbe/esU8r+zVWWW7cx6xZKE2gpqWXv4Fs17j88u0djV6NBi9Tgdtc0R6w',
        'api_key' =>'xvkwXPp52AUPLBGCXmD5UA==',
        'call_back' => 'https://api-test.betrnk.games/public/api/booming/callback',
        'roll_back' => 'https://api-test.betrnk.games/public/api/booming/rollback'
    ],
    'manna'=>[
<<<<<<< HEAD
        'AUTH_URL'=> 'https://api.manna-play.com/agent/specify/betrnk/authenticate/auth_token',
        'GAME_LINK_URL' => 'https://api.manna-play.com/agent/specify/betrnk/gameLink/link',
        'AUTH_API_KEY'=> 'kzHFKTpWG%49vaM&C2BdQcf3$*5mi!NUDwubj#nE',
        'CLIENT_API_KEY'=> 'Az5Rm8K56s3TJVjF'
=======
        'AUTH_URL'=> 'https://api.mannagaming.com/agent/specify/betrnk/authenticate/auth_token',
        'GAME_LINK_URL' => 'https://api.mannagaming.com/agent/specify/betrnk/gameLink/link',
        'API_KEY'=> 'GkyPIN1mD*yzjxzQumq@cZZC!Vw%b!kIVy&&hk!a',
        'AUTH_API_KEY'=> 'GkyPIN1mD*yzjxzQumq@cZZC!Vw%b!kIVy&&hk!a',
        'CLIENT_API_KEY' => '4dtXHSekHaFkAqbGcsWV2es4BTRLADQP'
>>>>>>> 81f17ebb1a4a4779a735d16e0228f8f7ddc555f9
    ]
];

?>