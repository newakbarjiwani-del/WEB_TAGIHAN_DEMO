<?php
date_default_timezone_set("Asia/Bangkok");
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '0');

header('Content-Type: application/json');

require_once '../config/connectQrDummy.php';

if (!isset($dbhandle) || !$dbhandle) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Gagal koneksi database dummy']);
    exit;
}

try {
    $sql = "SELECT id, vano, amount, qris_id, qris_content, transaction_id, description, status, paid_flag, paid_at, created_at, response_payload
            FROM qr_dummy
            ORDER BY id DESC
            LIMIT 20";
    $result = mysqli_query($dbhandle, $sql);

    if (!$result) {
        throw new Exception(mysqli_error($dbhandle));
    }

    $rows = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    echo json_encode(array(
        'success' => true,
        'data' => $rows,
    ));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage(),
    ));
}

if (isset($dbhandle)) {
    mysqli_close($dbhandle);
}
