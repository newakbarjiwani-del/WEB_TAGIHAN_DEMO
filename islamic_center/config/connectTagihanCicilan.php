<?php

/**
 * Koneksi DB tagihan cicilan (WEB_TAGIHAN_DEMO / DEMO_WS).
 * Samakan host/db dengan DEMO_WS_TAGIHAN_CICILAN/config/database.php.
 *
 * Dipakai oleh qris/pushNotif/tagihanCicilan.php saat callback bayar QRIS.
 */
$host = '10.99.23.26';
$port = 3306;
$base = 'sidoarjo_raudhatul_jannah';
$user = 'root';
$pawd = 'Smartpay1ct';

mysqli_report(MYSQLI_REPORT_OFF);
$dbhandle = @mysqli_connect($host, $user, $pawd, $base, $port);
if ($dbhandle) {
    mysqli_set_charset($dbhandle, 'utf8mb4');
}

/** Prefix vano 6 digit untuk routing pushNotif.php (harus sama dengan generator). */
$tagihanCicilanQrisConfig = [
    'vano_prefix' => '751000', // vano QRIS = 751000 + nocust
    'nova_bank_prefix' => '751000',
];
