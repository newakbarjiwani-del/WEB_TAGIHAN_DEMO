<?php

/**
 * Brand / white-label settings for this master tagihan project.
 *
 * Ubah via .env (disarankan) atau langsung di file ini.
 * Setelah ganti logo, regenerate icon PWA: php public/icons/generate-icons.php
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Identitas sistem
    |--------------------------------------------------------------------------
    */
    'name' => env('BRAND_NAME', 'DEMO_WS_TAGIHAN_CICCILAN'),
    'short_name' => env('BRAND_SHORT_NAME', 'Demo Cicilan'),
    'tagline' => env('BRAND_TAGLINE', 'Cek & bayar tagihan'),
    'description' => env('BRAND_DESCRIPTION', 'Demo cek dan bayar tagihan cicilan'),
    'footer' => env('BRAND_FOOTER', null), // null = "© {year} {name}"

    /*
    |--------------------------------------------------------------------------
    | Aset visual (relatif ke /public)
    |--------------------------------------------------------------------------
    */
    'logo' => env('BRAND_LOGO', 'logo.jpeg'),
    'favicon' => env('BRAND_FAVICON', 'icon-jannah.jpeg'),
    'icon_192' => env('BRAND_ICON_192', 'icons/icon-192.png'),
    'icon_512' => env('BRAND_ICON_512', 'icons/icon-512.png'),
    'guide_image' => env('BRAND_GUIDE_IMAGE', 'Gambar_Panduan_Bayar.jpeg'),
    'guide_pdf' => env('BRAND_GUIDE_PDF', 'Booklet_Panduan_Bayar.pdf'),
    'show_guide' => filter_var(env('BRAND_SHOW_GUIDE', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Metode pembayaran
    |--------------------------------------------------------------------------
    | payment_qris = false → hanya VA
    | payment_qris = true  → pilihan VA atau QRIS di modal bayar
    */
    'payment_qris' => filter_var(env('BRAND_PAYMENT_QRIS', false), FILTER_VALIDATE_BOOLEAN),
    'qris' => [
        'server_url' => env('QRIS_SERVER_URL', 'http://103.23.103.43/qris/lazizmu_diy/server.php'),
        'jwt_secret' => env('QRIS_JWT_SECRET', 'TokenJWT_BMI_ICT'),
        'account_no' => env('QRIS_ACCOUNT_NO', '5080010295'),
        'mitra_customer_id' => env('QRIS_MITRA_CUSTOMER_ID', 'ISLAMIC CENTER SMG451061'),
        'tipe_transaksi' => env('QRIS_TIPE_TRANSAKSI', 'MTR-GENERATE-QRIS-DYNAMIC'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Warna (hex). Dipakai sebagai CSS variables + PWA theme-color
    |--------------------------------------------------------------------------
    | Primary = tombol / aksen utama (institutional green by default)
    */
    'colors' => [
        'primary' => env('BRAND_COLOR_PRIMARY', '#1b6b3a'),
        'primary_hover' => env('BRAND_COLOR_PRIMARY_HOVER', '#155530'),
        'primary_soft' => env('BRAND_COLOR_PRIMARY_SOFT', '#e8f3ec'),
        'theme' => env('BRAND_COLOR_THEME', '#14532d'),
        'bg' => env('BRAND_COLOR_BG', '#e9eeea'),
        'surface' => env('BRAND_COLOR_SURFACE', '#ffffff'),
        'text' => env('BRAND_COLOR_TEXT', '#1a2420'),
        // Dark mode
        'dark_bg' => env('BRAND_COLOR_DARK_BG', '#101814'),
        'dark_surface' => env('BRAND_COLOR_DARK_SURFACE', '#18241e'),
        'dark_primary' => env('BRAND_COLOR_DARK_PRIMARY', '#5ecf84'),
        'dark_primary_hover' => env('BRAND_COLOR_DARK_PRIMARY_HOVER', '#3db866'),
        'dark_primary_soft' => env('BRAND_COLOR_DARK_PRIMARY_SOFT', '#143524'),
        'dark_text' => env('BRAND_COLOR_DARK_TEXT', '#e8f0eb'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bentuk UI
    |--------------------------------------------------------------------------
    */
    'radius' => (int) env('BRAND_RADIUS', 3), // px — tajam, institusional
    'font_family' => env(
        'BRAND_FONT',
        "'IBM Plex Sans', 'Segoe UI', Tahoma, sans-serif"
    ),
    // Satu keluarga font — hindari serif display yang terasa template AI
    'font_display' => env(
        'BRAND_FONT_DISPLAY',
        "'IBM Plex Sans', 'Segoe UI', Tahoma, sans-serif"
    ),
    'font_url' => env(
        'BRAND_FONT_URL',
        'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap'
    ),

    /*
    |--------------------------------------------------------------------------
    | PWA
    |--------------------------------------------------------------------------
    */
    'pwa' => [
        'enabled' => filter_var(env('BRAND_PWA_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'display' => env('BRAND_PWA_DISPLAY', 'standalone'),
        'orientation' => env('BRAND_PWA_ORIENTATION', 'portrait-primary'),
        'start_url' => env('BRAND_PWA_START_URL', '/?source=pwa'),
        'background_color' => env('BRAND_PWA_BG', '#101814'),
    ],

];
