<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'enabled' => env('TURNSTILE_ENABLED', false),
    ],

    'tagihan_ws' => [
        'url' => env('WS_TAGIHAN_URL', 'http://103.23.103.43/WEB_TAGIHAN_PROJECT/WS_TAGIHAN_SIDOARJO_RAUDHATUL_JANNAH/index.php'),
    ],

    // DB sekolah untuk simpan mst_qris / mst_qris_item (sama dengan DEMO_WS database.php)
    'tagihan_db' => [
        'host' => env('TAGIHAN_DB_HOST', '10.99.23.26'),
        'port' => env('TAGIHAN_DB_PORT', '3306'),
        'database' => env('TAGIHAN_DB_DATABASE', 'sidoarjo_raudhatul_jannah'),
        'username' => env('TAGIHAN_DB_USERNAME', 'root'),
        'password' => env('TAGIHAN_DB_PASSWORD', 'Smartpay1ct'),
    ],

];
