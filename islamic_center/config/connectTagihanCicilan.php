<?php

/**
 * DB WEB_TAGIHAN_DEMO / DEMO_WS — log_qris_push & mst_qris.
 * Host publik: 103.23.103.36 (demo_smartpayment_installment)
 */
$host = '103.23.103.36';
$port = 3306;
$base = 'demo_smartpayment_installment';
$user = 'root';
$pawd = 'Smartpay1ct';

mysqli_report(MYSQLI_REPORT_OFF);
$dbhandle = null;
$mysqli = mysqli_init();
if ($mysqli) {
    @$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    if (@$mysqli->real_connect($host, $user, $pawd, $base, $port)) {
        $mysqli->set_charset('utf8mb4');
        $dbhandle = $mysqli;
    }
}

$tagihanCicilanQrisConfig = [
    'vano_prefix' => '751000',
    'nova_bank_prefix' => '751000',
];
