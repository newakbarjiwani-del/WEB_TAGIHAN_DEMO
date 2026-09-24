<?php

/**
 * Push notif QRIS → pelunasan tagihan cicilan (mst_qris + scctva).
 *
 * Variabel dari pushNotif.php: $dbhandle, $transactionQrId, $vano, $amount,
 * $transactionId, $responseTimestamp, $data, $token.
 *
 * Alur:
 * 1. Cari transaksi di mst_qris (qris_id / vano pending)
 * 2. Tandai paid
 * 3. Jika top-up (tanpa item / deskripsi TOPUP) → selesai (saldo VA via jalur bank)
 *    Jika ada item tagihan → insert scctva (legacy)
 */

if (! function_exists('tc_push_response')) {
    function tc_push_response(int $httpCode, array $payload): void
    {
        http_response_code($httpCode);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (! function_exists('tc_write_push_log')) {
    /**
     * Audit trail callback → log_qris_push (best-effort, jangan gagalkan flow).
     *
     * @param  array<string, mixed>  $log
     */
    function tc_write_push_log($db, array $log): void
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
                ? json_encode($log['request_payload'], JSON_UNESCAPED_UNICODE)
                : null;
            $resPayload = isset($log['response_payload'])
                ? json_encode($log['response_payload'], JSON_UNESCAPED_UNICODE)
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
                $esc($db, (string) ($log['source'] ?? 'qris/pushNotif/tagihanCicilan.php')),
                $esc($db, substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)),
                $esc($db, substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255))
            );
            @mysqli_query($db, $sql);
        } catch (Throwable $e) {
            // Logging tidak boleh mengganggu flow callback.
        }
    }
}

if (! isset($dbhandle) || ! $dbhandle) {
    tc_push_response(500, [
        'responseCode' => '01',
        'responseMessage' => 'Koneksi DB tagihan cicilan gagal',
        'responseTimestamp' => date('Y-m-d H:i:s'),
    ]);
}

if (! function_exists('tc_find_by_qris_id')) {
    function tc_find_by_qris_id($db, string $qrisId): ?array
    {
        if ($qrisId === '') {
            return null;
        }
        $stmt = mysqli_prepare($db, 'SELECT * FROM mst_qris WHERE qris_id = ? LIMIT 1');
        if (! $stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 's', $qrisId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res) ?: null;
        mysqli_stmt_close($stmt);

        return $row;
    }
}

if (! function_exists('tc_find_by_vano_pending')) {
    function tc_find_by_vano_pending($db, string $vano): ?array
    {
        if ($vano === '') {
            return null;
        }
        $stmt = mysqli_prepare(
            $db,
            "SELECT * FROM mst_qris WHERE vano = ? AND status = 'pending' AND paid_flag = 0 ORDER BY id DESC LIMIT 1"
        );
        if (! $stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 's', $vano);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res) ?: null;
        mysqli_stmt_close($stmt);

        return $row;
    }
}

if (! function_exists('tc_load_items')) {
    function tc_load_items($db, int $paymentId): array
    {
        $stmt = mysqli_prepare(
            $db,
            'SELECT aa, billcd, nama_tagihan, amount, is_cicil, sisa_sebelum
             FROM mst_qris_item WHERE payment_id = ? ORDER BY id ASC'
        );
        if (! $stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'i', $paymentId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = $r;
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }
}

if (! function_exists('tc_mark_paid')) {
    function tc_mark_paid($db, int $id, float $paidAmount, string $paidAt): bool
    {
        $sql = "UPDATE mst_qris
                SET status = 'paid',
                    paid_flag = 1,
                    amount = ?,
                    paid_at = ?,
                    updated_at = NOW()
                WHERE id = ?
                  AND paid_flag = 0";
        $stmt = mysqli_prepare($db, $sql);
        if (! $stmt) {
            throw new Exception('Prepare mark paid gagal: '.mysqli_error($db));
        }
        mysqli_stmt_bind_param($stmt, 'dsi', $paidAmount, $paidAt, $id);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        return $affected > 0;
    }
}

if (! function_exists('tc_insert_scctva')) {
    /**
     * Insert scctva agar jalur pelunasan sama dengan generate-VA (Tagihan::doInsertVa).
     * NOVA = NOCUST (konvensi klien DEMO).
     */
    function tc_insert_scctva($db, array $header, array $items): bool
    {
        $custid = (string) ($header['custid'] ?? '');
        $nocust = (string) ($header['nocust'] ?? '');
        $namacust = (string) ($header['namacust'] ?? '');
        if ($custid === '' || $nocust === '' || empty($items)) {
            return false;
        }

        $ids = [];
        $ams = [];
        $total = 0.0;
        foreach ($items as $it) {
            $aa = (int) ($it['aa'] ?? 0);
            $amt = (float) ($it['amount'] ?? 0);
            if ($aa <= 0 || $amt <= 0) {
                continue;
            }
            $ids[] = (string) $aa;
            $ams[] = (string) (int) round($amt);
            $total += $amt;
        }
        if ($total <= 0 || empty($ids)) {
            return false;
        }

        $arrayTagihan = implode(',', $ids);
        $billam = implode(',', $ams);
        $nova = $nocust;
        $billtot = $total;

        // Urutan sama DEMO_WS Tagihan::doInsertVa (tanpa ExpDate dulu, lalu basic)
        $attempts = [
            [
                'sql' => 'INSERT INTO scctva (CUSTID, NOCUST, NMCUST, NOVA, ArrayTagihan, BILLAM, BILLTOT, STATUS, CREATED_AT)
                          VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())',
                'types' => 'ssssssd',
                'with_tot' => true,
            ],
            [
                'sql' => 'INSERT INTO scctva (CUSTID, NOCUST, NMCUST, NOVA, ArrayTagihan, BILLAM, STATUS, CREATED_AT)
                          VALUES (?, ?, ?, ?, ?, ?, 1, NOW())',
                'types' => 'ssssss',
                'with_tot' => false,
            ],
        ];

        foreach ($attempts as $attempt) {
            $stmt = mysqli_prepare($db, $attempt['sql']);
            if (! $stmt) {
                continue;
            }
            if ($attempt['with_tot']) {
                mysqli_stmt_bind_param(
                    $stmt,
                    $attempt['types'],
                    $custid,
                    $nocust,
                    $namacust,
                    $nova,
                    $arrayTagihan,
                    $billam,
                    $billtot
                );
            } else {
                mysqli_stmt_bind_param(
                    $stmt,
                    $attempt['types'],
                    $custid,
                    $nocust,
                    $namacust,
                    $nova,
                    $arrayTagihan,
                    $billam
                );
            }
            $ok = mysqli_stmt_execute($stmt);
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            if ($ok) {
                return true;
            }
            if ($err !== '') {
                error_log('tagihanCicilan scctva: '.$err);
            }
        }

        return false;
    }
}

// --- main ---
$qrisId = (string) ($transactionQrId ?? '');
$vanoCb = (string) ($vano ?? '');
$reqSnapshot = [
    'transactionQrId' => $qrisId,
    'vano' => $vanoCb,
    'amount' => $amount ?? null,
    'transactionId' => $transactionId ?? null,
    'accountNo' => $accountNo ?? null,
    'description' => $description ?? null,
];
$billing = tc_find_by_qris_id($dbhandle, $qrisId);

if (! $billing && $vanoCb !== '') {
    $billing = tc_find_by_vano_pending($dbhandle, $vanoCb);
}

if (! $billing) {
    $payload404 = [
        'responseCode' => '01',
        'responseMessage' => 'Data QRIS tagihan cicilan tidak ditemukan',
        'responseTimestamp' => date('Y-m-d H:i:s'),
        'transactionQrId' => $qrisId,
        'vano' => $vanoCb,
    ];
    tc_write_push_log($dbhandle, [
        'event_type' => 'not_found',
        'qris_id' => $qrisId,
        'transaction_id' => $transactionId ?? null,
        'vano' => $vanoCb,
        'amount' => is_numeric($amount ?? null) ? (float) $amount : null,
        'processed' => 'not_found',
        'response_code' => '01',
        'response_message' => $payload404['responseMessage'],
        'http_code' => 404,
        'request_payload' => $reqSnapshot,
        'response_payload' => $payload404,
    ]);
    tc_push_response(404, $payload404);
}

$paymentId = (int) $billing['id'];
$alreadyPaid = ((int) ($billing['paid_flag'] ?? 0) === 1) || (($billing['status'] ?? '') === 'paid');
$paymentTime = date('Y-m-d H:i:s');
$paidAmount = is_numeric($amount ?? null)
    ? (float) $amount
    : (float) ($billing['amount'] ?? 0);

$items = tc_load_items($dbhandle, $paymentId);
$desc = (string) ($billing['description'] ?? '');
$isTopup = empty($items) || stripos($desc, 'topup') !== false || stripos($desc, 'top up') !== false;
$scctvaOk = false;
$newlyPaid = false;
$scctvaStatus = 'skipped';

if (! $alreadyPaid) {
    mysqli_begin_transaction($dbhandle);
    try {
        $newlyPaid = tc_mark_paid($dbhandle, $paymentId, $paidAmount, $paymentTime);
        if ($newlyPaid) {
            if ($isTopup) {
                // Top-up VA: cukup tandai paid. Kredit saldo VA lewat jalur bank/vano.
                $scctvaStatus = 'skipped_topup';
            } else {
                $scctvaOk = tc_insert_scctva($dbhandle, $billing, $items);
                $scctvaStatus = $scctvaOk ? 'inserted' : 'failed';
                if (! $scctvaOk) {
                    error_log('tagihanCicilan: scctva insert gagal untuk mst_qris id='.$paymentId);
                }
            }
        }
        mysqli_commit($dbhandle);
    } catch (Throwable $e) {
        mysqli_rollback($dbhandle);
        $payload500 = [
            'responseCode' => '01',
            'responseMessage' => 'Gagal memproses pelunasan: '.$e->getMessage(),
            'responseTimestamp' => date('Y-m-d H:i:s'),
            'transactionQrId' => $qrisId,
            'vano' => $vanoCb,
        ];
        tc_write_push_log($dbhandle, [
            'payment_id' => $paymentId,
            'event_type' => 'error',
            'qris_id' => $billing['qris_id'] ?? $qrisId,
            'transaction_id' => $transactionId ?? null,
            'vano' => $billing['vano'] ?? $vanoCb,
            'custid' => $billing['custid'] ?? null,
            'nocust' => $billing['nocust'] ?? null,
            'amount' => $paidAmount,
            'paid_flag' => 0,
            'processed' => 'error',
            'scctva_status' => null,
            'response_code' => '01',
            'response_message' => $payload500['responseMessage'],
            'http_code' => 500,
            'request_payload' => $reqSnapshot,
            'response_payload' => $payload500,
        ]);
        tc_push_response(500, $payload500);
    }
}

$processed = $alreadyPaid ? 'already_processed' : ($newlyPaid ? 'new_processing' : 'no_change');
if ($alreadyPaid) {
    $scctvaStatus = 'skipped';
}
$payloadOk = [
    'responseCode' => '00',
    'responseMessage' => 'TRANSACTION SUCCESS',
    'responseTimestamp' => $responseTimestamp ?? date('Y-m-d H:i:s'),
    'transactionId' => $transactionId ?? null,
    'paymentTime' => $paymentTime,
    'amount' => $paidAmount,
    'vano' => $billing['vano'] ?? $vanoCb,
    'qrisId' => $billing['qris_id'] ?? $qrisId,
    'custid' => $billing['custid'] ?? null,
    'nocust' => $billing['nocust'] ?? null,
    'items' => count($items),
    'scctva' => $scctvaStatus,
    'processed' => $processed,
    'paymentType' => $isTopup ? 'topup' : 'tagihan_cicilan',
];

tc_write_push_log($dbhandle, [
    'payment_id' => $paymentId,
    'event_type' => $alreadyPaid ? 'already_paid' : 'push_notif',
    'qris_id' => $billing['qris_id'] ?? $qrisId,
    'transaction_id' => $transactionId ?? null,
    'vano' => $billing['vano'] ?? $vanoCb,
    'custid' => $billing['custid'] ?? null,
    'nocust' => $billing['nocust'] ?? null,
    'amount' => $paidAmount,
    'paid_flag' => 1,
    'processed' => $processed,
    'scctva_status' => $scctvaStatus,
    'response_code' => '00',
    'response_message' => 'TRANSACTION SUCCESS',
    'http_code' => 200,
    'request_payload' => $reqSnapshot,
    'response_payload' => $payloadOk,
]);

tc_push_response(200, $payloadOk);
