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
    | Model pembayaran
    |--------------------------------------------------------------------------
    | BRAND_PAYMENT_MODEL:
    |   1 / bills  → pilih tagihan + bayar lewat VA (nominal, centang)
    |   2 / saldo  → tagihan hanya informasi; VA tampil sebagai kartu isi saldo
    |                (mirip top up QRIS). Default: 2
    |
    | payment_qris = true → tombol Top up saldo via QRIS (boleh digabung model 1/2)
    */
    'payment_model' => (static function () {
        $raw = strtolower(trim((string) env('BRAND_PAYMENT_MODEL', '2')));
        if (in_array($raw, ['1', 'bills', 'bill', 'tagihan'], true)) {
            return 'bills';
        }

        return 'saldo'; // 2, saldo, info, info_only, …
    })(),
    'payment_can_pay_bills' => (static function () {
        $raw = strtolower(trim((string) env('BRAND_PAYMENT_MODEL', '2')));

        return in_array($raw, ['1', 'bills', 'bill', 'tagihan'], true);
    })(),
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
    | Primary = biru ICT yang lebih dalam (kontras baik di UI)
    | Highlight = kuning titik "i" logo
    | Soft cyan logo (#3AB4F2) dipakai sebagai aksen dekoratif di CSS
    */
    'colors' => [
        'primary' => env('BRAND_COLOR_PRIMARY', '#0B7EB8'),
        'primary_hover' => env('BRAND_COLOR_PRIMARY_HOVER', '#086693'),
        'primary_soft' => env('BRAND_COLOR_PRIMARY_SOFT', '#D6EEF8'),
        'theme' => env('BRAND_COLOR_THEME', '#0B7EB8'),
        'highlight' => env('BRAND_COLOR_HIGHLIGHT', '#E89B0C'),
        'highlight_soft' => env('BRAND_COLOR_HIGHLIGHT_SOFT', '#FFF3D1'),
        'bg' => env('BRAND_COLOR_BG', '#e4e9ee'),
        'surface' => env('BRAND_COLOR_SURFACE', '#ffffff'),
        'text' => env('BRAND_COLOR_TEXT', '#0a1f2e'),
        // Dark mode
        'dark_bg' => env('BRAND_COLOR_DARK_BG', '#0a1218'),
        'dark_surface' => env('BRAND_COLOR_DARK_SURFACE', '#15202a'),
        'dark_primary' => env('BRAND_COLOR_DARK_PRIMARY', '#4DB8E8'),
        'dark_primary_hover' => env('BRAND_COLOR_DARK_PRIMARY_HOVER', '#3AB4F2'),
        'dark_primary_soft' => env('BRAND_COLOR_DARK_PRIMARY_SOFT', '#163548'),
        'dark_text' => env('BRAND_COLOR_DARK_TEXT', '#e8f1f6'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bentuk UI
    |--------------------------------------------------------------------------
    */
    'radius' => (int) env('BRAND_RADIUS', 8), // px — app-like, tidak berlebihan
    'font_family' => env(
        'BRAND_FONT',
        "'Plus Jakarta Sans', system-ui, sans-serif"
    ),
    'font_display' => env(
        'BRAND_FONT_DISPLAY',
        "'Plus Jakarta Sans', system-ui, sans-serif"
    ),
    // Bunny Fonts biasanya lebih andal di jaringan lokal/ID
    'font_url' => env(
        'BRAND_FONT_URL',
        'https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap'
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
        'background_color' => env('BRAND_PWA_BG', '#0a1620'),
    ],

];
