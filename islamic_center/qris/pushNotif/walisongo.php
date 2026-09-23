<?php 
$billingQuery = "SELECT * FROM pembayaran_formulir WHERE qris_id = ? and status != 'success'";
$billingStmt = mysqli_prepare($dbhandle, $billingQuery);
mysqli_stmt_bind_param($billingStmt, 's', $transactionQrId);
mysqli_stmt_execute($billingStmt);
$billingResult = mysqli_stmt_get_result($billingStmt);
$billing = mysqli_fetch_assoc($billingResult);
mysqli_stmt_close($billingStmt);

 // Ambil user
 $userQuery = "SELECT * FROM users WHERE id=?";
 $userStmt = mysqli_prepare($dbhandle, $userQuery);
 mysqli_stmt_bind_param($userStmt, 'i', $billing['user_id']);
 mysqli_stmt_execute($userStmt);
 $userResult = mysqli_stmt_get_result($userStmt);
 $user = mysqli_fetch_assoc($userResult);
 mysqli_stmt_close($userStmt);

if (mysqli_num_rows($billingResult) > 0) {
    // update status pembayaran 
    $updateQuery = "UPDATE pembayaran_formulir SET status='success', updated_at=NOW() WHERE qris_id=? AND status != 'success'";
                $updateStmt = mysqli_prepare($dbhandle, $updateQuery);
                if ($updateStmt) {
                    mysqli_stmt_bind_param($updateStmt, 's', $transactionQrId);
                    mysqli_stmt_execute($updateStmt);
                    $affectedRows = mysqli_stmt_affected_rows($updateStmt);
                    error_log("Payment status update affected rows: {$affectedRows}");
                    mysqli_stmt_close($updateStmt);
                }
    // update users
     $updateQueryUsers = "UPDATE users SET status_pembayaran_formulir='sudah_bayar' WHERE va_number=? AND status_pembayaran_formulir != 'sudah_bayar'";
     $updateStmtUsers = mysqli_prepare($dbhandle, $updateQueryUsers);
     if ($updateStmtUsers) {
         mysqli_stmt_bind_param($updateStmtUsers, 's', $vano);
         mysqli_stmt_execute($updateStmtUsers);
         $affectedRowsUsers = mysqli_stmt_affected_rows($updateStmtUsers);
         error_log("User status update affected rows: {$affectedRowsUsers}");
         mysqli_stmt_close($updateStmtUsers);
     }
     // Update payment time
     $paymentTime = date('Y-m-d H:i:s');
     $paymentTimeUpdateQuery = "UPDATE pembayaran_formulir SET payment_time=? WHERE qris_id=? AND payment_time IS NULL";
     $paymentTimeUpdateStmt = mysqli_prepare($dbhandle, $paymentTimeUpdateQuery);
     if ($paymentTimeUpdateStmt) {
         mysqli_stmt_bind_param($paymentTimeUpdateStmt, 'ss', $paymentTime, $transactionQrId);
         mysqli_stmt_execute($paymentTimeUpdateStmt);
         $affectedRowsTime = mysqli_stmt_affected_rows($paymentTimeUpdateStmt);
         error_log("Payment time update affected rows: {$affectedRowsTime}");
         mysqli_stmt_close($paymentTimeUpdateStmt);
     }
    //  update gelombang
    $updateKuotaQuery = "UPDATE gelombang SET sisa = CASE 
    WHEN sisa > 1 THEN sisa - 1 
    WHEN sisa = 1 THEN 0 
    ELSE sisa 
    END,
    status = CASE 
        WHEN sisa = 1 THEN 'nonaktif' 
        ELSE status 
    END 
    WHERE id = ? AND sisa > 0";
    
    $updateKuotaStmt = mysqli_prepare($dbhandle, $updateKuotaQuery);
    if ($updateKuotaStmt) {
        mysqli_stmt_bind_param($updateKuotaStmt, 'i', $user['gelombang_id']);
        mysqli_stmt_execute($updateKuotaStmt);
        $affectedRows = mysqli_stmt_affected_rows($updateKuotaStmt);
        mysqli_stmt_close($updateKuotaStmt);
    }
    // log traffic
    // try {
    //     $servernamelog = '103.23.103.39';
    //     $usernamelog = 'root';
    //     $passwordlog = 'Smartpay1ct';
    //     $databaselog = 'farrelep_broadcaster';
    //     $portlog = 3307;
        
    //     $dbTraffic = mysqli_connect($servernamelog, $usernamelog, $passwordlog, $databaselog, $portlog);

    //     if (!mysqli_connect_errno()) {
    //         $transactionQrIdLog = $transactionQrId;
    //         $transactionIdLog = $data->transactionId;

    //         // Cek apakah sudah pernah dilogging
    //         $checkLog = mysqli_prepare($dbTraffic, "SELECT COUNT(*) as cnt FROM log_payment_qr WHERE transactionQRID = ? AND Client = 'YYS_DARUL_IQRA'");
    //         if ($checkLog) {
    //             mysqli_stmt_bind_param($checkLog, 's', $transactionQrIdLog);
    //             mysqli_stmt_execute($checkLog);
    //             $res = mysqli_stmt_get_result($checkLog);
    //             $row = mysqli_fetch_assoc($res);
    //             mysqli_stmt_close($checkLog);

    //             if ($row['cnt'] == 0) {
    //                 $CUSTNM = 'ISLAMIC CENTER (WALISONGO)';
    //                 $NOMINALFee1 = (int) round($amount * 0.007, 0, PHP_ROUND_HALF_UP);
    //                 $NOMINALFee2 = ($amount < 100000) ? $amount * 0.025 : 3000;
    //                 $GetValue = (string) ($amount + $NOMINALFee1 + $NOMINALFee2);
    //                 $accountNoLog = '5080010295';
    //                 $mitraCustomerId = 'ISLAMIC CENTER SMG451061';
    //                 $vanoLog = $vano ?? '-';

    //                 $queryLog = "CALL LogPaymentQR ('".$CUSTNM."', '".$mitraCustomerId."', '".$accountNoLog."', '".$transactionIdLog."', '".$transactionId."', '".$transactionQrIdLog."', '".$vanoLog."', '".$GetValue."', '".$amount."', '".$NOMINALFee1."', '".$NOMINALFee2."', '".$token."', '-')";

    //                 mysqli_query($dbTraffic, $queryLog);
    //                 error_log("Traffic logged for NEW payment (with billing): {$transactionQrId}");
    //             } else {
    //                 error_log("Traffic already logged for (with billing): {$transactionQrId} - skipping");
    //             }
    //         }
    //         mysqli_close($dbTraffic);
    //     }
    // } catch (Exception $e) {
    //     error_log('Traffic logging failed: ' . $e->getMessage());
    // }

     // === Response ke PG (cepat) ===
     $response = [
        'responseCode'=>'00',
        'responseMessage'=>'TRANSACTION SUCCESS',
        'responseTimestamp'=>$responseTimestamp,
        'transactionId'=>$transactionId,
        'paymentTime'=>$transactionId,
        'amount'=>$amount,
        'user'=>$user['username'] ?? 'Unknown',
    ];
    echo json_encode($response);
    exit;
} else {
    http_response_code(400);
    $errorResponse = [
        'responseCode'=>'01',
        'responseMessage'=>'Tidak ada tagihan formulir',
        'responseTimestamp'=>date('Y-m-d H:i:s')
    ];
    echo json_encode($errorResponse);
    exit;
}
?>