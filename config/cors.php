<?php
//
//return [
//    'paths' => ['api/*','*'],
//
//    'allowed_methods' => ['*'],
//
//    'allowed_origins' => ['*', 'https://listenact.vercel.app'],
//
//    'allowed_origins_patterns' => [],
//
//    'allowed_headers' => ['*'],
//
//    'exposed_headers' => [],
//
//    'max_age' => 0,
//
//    'supports_credentials' => false,
//
//];

return [

    'paths' => [
        'api/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => explode(',', env(
        'CORS_ALLOWED_ORIGINS'
    )),

    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],

    // 'allowed_headers' => [
    //     'Content-Type',
    //     'X-Requested-With',
    //     'Authorization',
    //     'Accept',
    //     'Origin',
    // ],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,

];
