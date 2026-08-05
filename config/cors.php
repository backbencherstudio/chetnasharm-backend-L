<?php

//
// return [
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
// ];

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'https://listenact.vercel.app',
        'https://listenact.netlify.app',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
