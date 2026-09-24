<?php
/**
 * vano 751000 — forward token ke DEMO_INSTALLMENT (103.23.103.43),
 * update mst_qris → paid/success + log_qris_push di DB 103.23.103.36.
 *
 * Dari pushNotif.php: $token, $transactionQrId, $vano, $amount,
 * $transactionId, $accountNo, $description, $responseTimestamp
 *
 * Notifikasi sistem ke user: Fase 1 (poll di PWA saat dibuka) — tidak ada Web Push di sini.
 */

$forwardBase = 'http://103.23.103.43/sikeu_ws_mysql/DEMO_INSTALLMENT/QRIS.php?token=';

if (! function_exists('di_write_push_log')) {
    /**
     * @param  mysqli|null  $db
     * @param  array<string, mixed>  $log
     */
    function di_write_push_log($db, array $log): void
    {
        if (! $db) {
            return;
        }

        try {
            $esc = static function ($db, $v): string {
                if ($v === null) {
                    return 'NULL';
                }
                if (is_bool($v)) {
                    return $v ? '1' : '0';
                }
                if (is_int($v) || is_float($v)) {
                    return (string) $v;
                }

                return "'".mysqli_real_escape_string($db, (string) $v)."'";
            };

            $paymentId = isset($log['payment_id']) && (int) $log['payment_id'] > 0
                ? (int) $log['payment_id']
                : null;
            $amount = isset($log['amount']) && is_numeric($log['amount'])
                ? (float) $log['amount']
                : null;
            $paidFlag = array_key_exists('paid_flag', $log) && $log['paid_flag'] !== null
                ? (int) $log['paid_flag']
                : null;
            $httpCode = isset($log['http_code']) ? (int) $log['http_code'] : null;
            $reqPayload = isset($log['request_payload'])
                ? json_encode($log['request_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null;
            $resPayload = isset($log['response_payload'])
                ? (is_string($log['response_payload'])
                    ? $log['response_payload']
                    : json_encode($log['response_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                : null;
            $respMsg = isset($log['response_message'])
                ? substr((string) $log['response_message'], 0, 255)
                : null;

            $sql = sprintf(
                'INSERT INTO log_qris_push (
                    payment_id, event_type, qris_id, transaction_id, vano,
                    custid, nocust, amount, paid_flag, processed, scctva_status,
                    response_code, response_message, http_code,
                    request_payload, response_payload, source, ip_address, user_agent, created_at
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW())',
                $esc($db, $paymentId),
                $esc($db, (string) ($log['event_type'] ?? 'push_notif')),
                $esc($db, $log['qris_id'] ?? null),
                $esc($db, $log['transaction_id'] ?? null),
                $esc($db, $log['vano'] ?? null),
                $esc($db, $log['custid'] ?? null),
                $esc($db, $log['nocust'] ?? null),
                $esc($db, $amount),
                $esc($db, $paidFlag),
                $esc($db, $log['processed'] ?? null),
                $esc($db, $log['scctva_status'] ?? null),
                $esc($db, $log['response_code'] ?? null),
                $esc($db, $respMsg),
                $esc($db, $httpCode),
                $esc($db, $reqPayload),
                $esc($db, $resPayload),
                $esc($db, 'qris/pushNotif/demoInstallment.php'),
                $esc($db, substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)),
                $esc($db, substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255))
            );
            @mysqli_query($db, $sql);
        } catch (Throwable $e) {
            error_log('demoInstallment log_qris_push: '.$e->getMessage());
        }
    }
}

if (! function_exists('di_mark_qris_paid')) {
    /**
     * Tandai mst_qris paid/success. Return: newly_paid|already_paid|not_found|error
     *
     * @param  mysqli|null  $db
     * @return array{result:string,payment_id:?int,custid:?string,nocust:?string,row:?array}
     */
    function di_mark_qris_paid($db, string $qrisId, string $vanoVal, $amountVal, string $paidAt): array
    {
        $out = [
            'result' => 'not_found',
            'payment_id' => null,
            'custid' => null,
            'nocust' => ($vanoVal !== '' && strlen($vanoVal) > 6) ? substr($vanoVal, 6) : null,
            'row' => null,
        ];

        if (! $db) {
            $out['result'] = 'error';

            return $out;
        }

        $vanoEsc = mysqli_real_escape_string($db, $vanoVal);
        $qrisEsc = mysqli_real_escape_string($db, $qrisId);

        $sqlFind = "SELECT id, custid, nocust, amount, status, paid_flag, qris_id, vano
            FROM mst_qris
            WHERE (qris_id = '{$qrisEsc}' AND qris_id <> '')
               OR (vano = '{$vanoEsc}' AND vano <> '')
            ORDER BY id DESC LIMIT 1";
        $resPay = @mysqli_query($db, $sqlFind);
        if (! $resPay || ! ($row = mysqli_fetch_assoc($resPay))) {
            return $out;
        }

        $paymentId = (int) $row['id'];
        $out['payment_id'] = $paymentId;
        $out['custid'] = $row['custid'] ?? null;
        if (! empty($row['nocust'])) {
            $out['nocust'] = $row['nocust'];
        }
        $out['row'] = $row;

        if ((int) ($row['paid_flag'] ?? 0) === 1 || strtolower((string) ($row['status'] ?? '')) === 'paid') {
            $out['result'] = 'already_paid';

            return $out;
        }

        $paidAmount = is_numeric($amountVal) ? (float) $amountVal : (float) ($row['amount'] ?? 0);
        $paidAtEsc = mysqli_real_escape_string($db, $paidAt);
        $sqlUp = "UPDATE mst_qris
            SET status = 'paid',
                paid_flag = 1,
                amount = ".(float) $paidAmount.",
                paid_at = '{$paidAtEsc}',
                updated_at = NOW()
            WHERE id = {$paymentId}
              AND paid_flag = 0";

        if (! @mysqli_query($db, $sqlUp)) {
            error_log('demoInstallment mark paid fail: '.mysqli_error($db));
            $out['result'] = 'error';

            return $out;
        }

        $out['result'] = mysqli_affected_rows($db) > 0 ? 'newly_paid' : 'already_paid';

        return $out;
    }
}

$qrisId = isset($transactionQrId) ? (string) $transactionQrId : '';
$vanoVal = isset($vano) ? (string) $vano : '';
$amountVal = isset($amount) ? $amount : null;
$trxId = isset($transactionId) ? (string) $transactionId : null;
$tokenRaw = isset($token) ? (string) $token : '';
$forwardUrl = $forwardBase.$tokenRaw;
$paidAt = ! empty($responseTimestamp) ? (string) $responseTimestamp : date('Y-m-d H:i:s');

$requestSnapshot = [
    'vano' => $vanoVal,
    'amount' => $amountVal,
    'transactionQrId' => $qrisId,
    'transactionId' => $trxId,
    'accountNo' => isset($accountNo) ? $accountNo : null,
    'description' => isset($description) ? $description : null,
    'forward_url' => $forwardBase,
    'forwarded_token' => $tokenRaw,
];

// 1) DB dulu — tandai success (callback bank sudah responseCode 00)
require_once __DIR__.'/../../config/connectTagihanCicilan.php';
$db = (isset($dbhandle) && $dbhandle instanceof mysqli) ? $dbhandle : null;

$mark = di_mark_qris_paid($db, $qrisId, $vanoVal, $amountVal, $paidAt);
$paymentId = $mark['payment_id'];
$custid = $mark['custid'];
$nocust = $mark['nocust'];
$markResult = $mark['result'];

// 2) Forward token ke DEMO_INSTALLMENT
$ch = curl_init($forwardUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 50,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPGET => true,
    CURLOPT_NOSIGNAL => 1,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json, text/plain, */*',
    ],
]);

$response = curl_exec($ch);
$errno = curl_errno($ch);
$error = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$decodedRes = null;
if (is_string($response) && $response !== '') {
    $decodedRes = json_decode($response, true);
}

if ($errno || $response === false) {
    $failPayload = [
        'responseCode' => '01',
        'responseMessage' => 'Gagal forward DEMO_INSTALLMENT: '.($error ?: 'timeout'),
        'responseTimestamp' => date('Y-m-d H:i:s'),
        'localPaid' => in_array($markResult, ['newly_paid', 'already_paid'], true),
        'markResult' => $markResult,
    ];
    di_write_push_log($db, [
        'event_type' => 'error',
        'payment_id' => $paymentId,
        'qris_id' => $qrisId !== '' ? $qrisId : null,
        'transaction_id' => $trxId,
        'vano' => $vanoVal !== '' ? $vanoVal : null,
        'custid' => $custid,
        'nocust' => $nocust,
        'amount' => $amountVal,
        'paid_flag' => in_array($markResult, ['newly_paid', 'already_paid'], true) ? 1 : 0,
        'processed' => $markResult === 'newly_paid' ? 'paid_forward_failed' : 'error',
        'scctva_status' => 'skipped',
        'response_code' => '01',
        'response_message' => $failPayload['responseMessage'],
        'http_code' => 502,
        'request_payload' => $requestSnapshot,
        'response_payload' => $failPayload,
    ]);

    // Tetap 200 jika lokal sudah paid — bank sudah sukses; forward gagal tidak batalkan settlement lokal
    if (in_array($markResult, ['newly_paid', 'already_paid'], true)) {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode([
            'responseCode' => '00',
            'responseMessage' => 'Pembayaran dicatat (forward DEMO_INSTALLMENT gagal: '.($error ?: 'timeout').')',
            'responseTimestamp' => date('Y-m-d H:i:s'),
            'markResult' => $markResult,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode($failPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$resCode = is_array($decodedRes) ? ($decodedRes['responseCode'] ?? null) : null;
$resMsg = is_array($decodedRes)
    ? ($decodedRes['responseMessage'] ?? $decodedRes['message'] ?? null)
    : substr((string) $response, 0, 255);

$forwardOk = ($httpCode === 200) || ($resCode === '00') || (is_string($response) && $response !== '' && $errno === 0);
$eventType = ($markResult === 'already_paid')
    ? 'already_paid'
    : (($markResult === 'newly_paid' || $forwardOk) ? 'push_notif' : 'error');

di_write_push_log($db, [
    'event_type' => $eventType,
    'payment_id' => $paymentId,
    'qris_id' => $qrisId !== '' ? $qrisId : null,
    'transaction_id' => $trxId,
    'vano' => $vanoVal !== '' ? $vanoVal : null,
    'custid' => $custid,
    'nocust' => $nocust,
    'amount' => $amountVal,
    'paid_flag' => in_array($markResult, ['newly_paid', 'already_paid'], true) ? 1 : 0,
    'processed' => $markResult,
    'scctva_status' => 'skipped',
    'response_code' => $resCode ?: ($forwardOk ? '00' : '01'),
    'response_message' => $resMsg ?: ('mark='.$markResult),
    'http_code' => $httpCode > 0 ? $httpCode : 200,
    'request_payload' => $requestSnapshot,
    'response_payload' => [
        'http_code' => $httpCode,
        'markResult' => $markResult,
        'body' => $decodedRes ?? $response,
    ],
]);

if ($httpCode > 0) {
    http_response_code($httpCode);
}

header('Content-Type: application/json');
echo is_string($response) ? $response : '';
exit;
