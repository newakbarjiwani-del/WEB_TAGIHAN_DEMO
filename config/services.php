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
        'host' => env('TAGIHAN_DB_HOST', '103.23.103.36'),
        'port' => env('TAGIHAN_DB_PORT', '3306'),
        'database' => env('TAGIHAN_DB_DATABASE', 'demo_smartpayment_installment'),
        'username' => env('TAGIHAN_DB_USERNAME', 'root'),
        'password' => env('TAGIHAN_DB_PASSWORD', 'Smartpay1ct'),
    ],

    /*
    | Web Push (Fase 2) — notifikasi saat tab/PWA tertutup
    | Generate keys: php artisan webpush:vapid
    */
    'webpush' => [
        'public_key' => env('VAPID_PUBLIC_KEY', ''),
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@ict.local'),
        'notify_secret' => env('WEBPUSH_NOTIFY_SECRET', ''),
    ],

];
