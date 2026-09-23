<?php

function dummy_push_notif_response(int $httpCode, array $payload): void
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function dummy_find_by_qris_id($dbhandle, string $transactionQrId): ?array
{
    if ($transactionQrId === '') {
        return null;
    }

    $stmt = mysqli_prepare($dbhandle, 'SELECT * FROM qr_dummy WHERE qris_id = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Failed to prepare dummy qris lookup: ' . mysqli_error($dbhandle));
    }

    mysqli_stmt_bind_param($stmt, 's', $transactionQrId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result) ?: null;
    mysqli_stmt_close($stmt);

    return $row;
}

function dummy_find_by_vano($dbhandle, string $vano): ?array
{
    if ($vano === '') {
        return null;
    }

    $stmt = mysqli_prepare(
        $dbhandle,
        "SELECT * FROM qr_dummy WHERE vano = ? AND status = 'pending' ORDER BY id DESC LIMIT 1"
    );
    if (!$stmt) {
        throw new Exception('Failed to prepare dummy vano lookup: ' . mysqli_error($dbhandle));
    }

    mysqli_stmt_bind_param($stmt, 's', $vano);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result) ?: null;
    mysqli_stmt_close($stmt);

    return $row;
}

$billing = dummy_find_by_qris_id($dbhandle, (string) $transactionQrId);

if (!$billing && $vano !== '') {
    $billing = dummy_find_by_vano($dbhandle, (string) $vano);
}

if (!$billing) {
    dummy_push_notif_response(404, [
        'responseCode'      => '01',
        'responseMessage'   => 'Data QR dummy tidak ditemukan',
        'responseTimestamp' => date('Y-m-d H:i:s'),
        'transactionQrId'   => $transactionQrId,
        'vano'              => $vano,
    ]);
}

$alreadyPaid = ((int) ($billing['paid_flag'] ?? 0) === 1) || (($billing['status'] ?? '') === 'paid');
$paymentTime = date('Y-m-d H:i:s');

if (!$alreadyPaid) {
    $paidAmount = is_numeric($amount) ? (float) $amount : (float) $billing['amount'];
    $updateQuery = "UPDATE qr_dummy
                    SET status = 'paid',
                        paid_flag = 1,
                        amount = ?,
                        paid_at = ?,
                        updated_at = NOW()
                    WHERE id = ?
                      AND paid_flag = 0";
    $updateStmt = mysqli_prepare($dbhandle, $updateQuery);
    if (!$updateStmt) {
        throw new Exception('Failed to prepare dummy payment update: ' . mysqli_error($dbhandle));
    }

    $billingId = (int) $billing['id'];
    mysqli_stmt_bind_param($updateStmt, 'dsi', $paidAmount, $paymentTime, $billingId);
    mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);
}

dummy_push_notif_response(200, [
    'responseCode'      => '00',
    'responseMessage'   => 'TRANSACTION SUCCESS',
    'responseTimestamp' => $responseTimestamp,
    'transactionId'     => $transactionId,
    'paymentTime'       => $paymentTime,
    'amount'            => $amount,
    'vano'              => $billing['vano'] ?? $vano,
    'qrisId'            => $billing['qris_id'] ?? $transactionQrId,
    'processed'         => $alreadyPaid ? 'already_processed' : 'new_processing',
    'paymentType'       => 'dummy',
]);
