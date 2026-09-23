<?php
//define koneksi variable
$host = "localhost";
$base = "qr_dummy";
$user = "qr_dummy";
$pawd = "qr_dummy";

mysqli_report(MYSQLI_REPORT_OFF);
$dbhandle = @mysqli_connect($host, $user, $pawd, $base);
if ($dbhandle) {
    mysqli_set_charset($dbhandle, 'utf8mb4');
}
