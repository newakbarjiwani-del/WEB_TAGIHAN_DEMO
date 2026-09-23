<?php

if (!function_exists('metaschool_push_notif_response')) {
    function metaschool_push_notif_response(int $httpCode, array $payload): void
    {
        http_response_code($httpCode);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('metaschool_log_qris_payment')) {
    function metaschool_log_qris_payment(?PDO $pdo, array $log): void
    {
        if (!$pdo) {
            return;
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO log_qris (
                    event_type, payment_type, cust_id, bill_id, order_id, qris_id, transaction_id,
                    vano, amount, paid_flag, status_text, response_code, response_message, source,
                    request_payload, response_payload, ip_address, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );

            $stmt->execute([
                'payment',
                $log['payment_type'] ?? null,
                $log['cust_id'] ?? null,
                $log['bill_id'] ?? null,
                $log['order_id'] ?? null,
                $log['qris_id'] ?? null,
                $log['transaction_id'] ?? null,
                $log['vano'] ?? null,
                $log['amount'] ?? null,
                $log['paid_flag'] ?? null,
                $log['status_text'] ?? null,
                $log['response_code'] ?? null,
                $log['response_message'] ?? null,
                'qris/pushNotif/metaschool.php',
                json_encode($log['request_payload'] ?? [], JSON_UNESCAPED_UNICODE),
                json_encode($log['response_payload'] ?? [], JSON_UNESCAPED_UNICODE),
                substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
        } catch (Throwable $e) {
            // Logging tidak boleh mengganggu flow callback.
        }
    }
}

if (!function_exists('metaschool_normalize_phone')) {
    function metaschool_normalize_phone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        $digits = ltrim($digits, '0');

        if ($digits === '') {
            return '';
        }

        if (strpos($digits, '62') === 0) {
            return $digits;
        }

        return '62' . $digits;
    }
}

if (!function_exists('metaschool_find_registration_by_qris_id')) {
    function metaschool_find_registration_by_qris_id(PDO $pdo, string $transactionQrId): ?array
    {
        if ($transactionQrId === '') {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT * FROM scctverif WHERE qris_id = ? LIMIT 1'
        );
        $stmt->execute([$transactionQrId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}

if (!function_exists('metaschool_find_registration_by_vano')) {
    function metaschool_find_registration_by_vano(PDO $pdo, string $vano): ?array
    {
        $target = metaschool_normalize_phone($vano);
        if ($target === '') {
            return null;
        }

        $stmt = $pdo->query(
            'SELECT * FROM scctverif WHERE paidflag = 0 ORDER BY CUSTID DESC'
        );
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            $whatsapp = metaschool_normalize_phone((string) ($row['whatsapp_admin'] ?? ''));
            if ($whatsapp !== '' && $whatsapp === $target) {
                return $row;
            }
        }

        return null;
    }
}

if (!function_exists('metaschool_mark_registration_paid')) {
    function metaschool_mark_registration_paid(PDO $pdo, int $custId): bool
    {
        $stmt = $pdo->prepare(
            'UPDATE scctverif
             SET paidflag = 1,
                 status = ?,
                 updated_at = NOW()
             WHERE CUSTID = ?
               AND paidflag = 0'
        );
        $stmt->execute(['active', $custId]);

        return $stmt->rowCount() > 0;
    }
}

if (!function_exists('metaschool_copy_verif_to_customer')) {
    function metaschool_copy_verif_to_customer(PDO $pdo, int $custId): void
    {
        $stmt = $pdo->prepare('CALL sp_copy_verif_to_cust2(?)');
        $stmt->execute([$custId]);

        do {
            $stmt->fetchAll();
        } while ($stmt->nextRowset());
    }
}

if (!function_exists('metaschool_process_paid_registration')) {
    function metaschool_process_paid_registration(PDO $pdo, array $row): array
    {
        $custId = (int) $row['CUSTID'];
        $alreadyPaid = (int) ($row['paidflag'] ?? 0) === 1;
        $newlyPaid = false;

        if (!$alreadyPaid) {
            $newlyPaid = metaschool_mark_registration_paid($pdo, $custId);
        }

        metaschool_copy_verif_to_customer($pdo, $custId);

        return [
            'custId'        => $custId,
            'alreadyPaid'   => $alreadyPaid,
            'newlyPaid'     => $newlyPaid,
            'namaSekolah'   => $row['nama_sekolah'] ?? null,
            'whatsappAdmin' => $row['whatsapp_admin'] ?? null,
        ];
    }
}

$rawRequest = [
    'responseCode'      => $responseCode ?? null,
    'responseMessage'   => $responseMessage ?? null,
    'transactionId'     => $transactionId ?? null,
    'responseTimestamp' => $responseTimestamp ?? null,
    'vano'              => $vano ?? null,
    'amount'            => $amount ?? null,
    'transactionQrId'   => $transactionQrId ?? null,
];

try {
    $pdo->beginTransaction();

    $registration = metaschool_find_registration_by_qris_id($pdo, $transactionQrId);

    if (!$registration && $vano !== '') {
        $registration = metaschool_find_registration_by_vano($pdo, $vano);
    }

    if (!$registration) {
        $pdo->rollBack();
        $notFoundPayload = [
            'responseCode'      => '01',
            'responseMessage'   => 'Data registrasi MetaSchool tidak ditemukan',
            'responseTimestamp' => date('Y-m-d H:i:s'),
            'transactionQrId'   => $transactionQrId,
            'vano'              => $vano,
        ];
        metaschool_log_qris_payment($pdo, [
            'payment_type'     => 'unknown',
            'qris_id'          => $transactionQrId,
            'transaction_id'   => $transactionId,
            'vano'             => $vano,
            'amount'           => is_numeric($amount) ? (float) $amount : null,
            'paid_flag'        => 0,
            'status_text'      => 'not_found',
            'response_code'    => '404',
            'response_message' => 'Data registrasi MetaSchool tidak ditemukan',
            'request_payload'  => $rawRequest,
            'response_payload' => $notFoundPayload,
        ]);
        metaschool_push_notif_response(404, $notFoundPayload);
    }

    $result = metaschool_process_paid_registration($pdo, $registration);
    $pdo->commit();

    $responsePayload = [
        'responseCode'      => '00',
        'responseMessage'   => 'TRANSACTION SUCCESS',
        'responseTimestamp' => $responseTimestamp,
        'transactionId'     => $transactionId,
        'paymentTime'       => date('Y-m-d H:i:s'),
        'amount'            => $amount,
        'custId'            => $result['custId'],
        'namaSekolah'       => $result['namaSekolah'],
        'processed'         => $result['alreadyPaid'] ? 'already_processed' : 'new_processing',
        'paymentType'       => 'registration',
    ];
    metaschool_log_qris_payment($pdo, [
        'payment_type'     => 'registration',
        'cust_id'          => $result['custId'],
        'qris_id'          => $transactionQrId,
        'transaction_id'   => $transactionId,
        'vano'             => $vano,
        'amount'           => is_numeric($amount) ? (float) $amount : null,
        'paid_flag'        => 1,
        'status_text'      => $result['alreadyPaid'] ? 'already_processed' : 'new_processing',
        'response_code'    => '00',
        'response_message' => 'TRANSACTION SUCCESS',
        'request_payload'  => $rawRequest,
        'response_payload' => $responsePayload,
    ]);

    metaschool_push_notif_response(200, $responsePayload);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $message = 'Gagal memproses notifikasi pembayaran';
    if (strpos($e->getMessage(), 'sp_copy_verif_to_cust') !== false) {
        $message = 'Stored procedure sp_copy_verif_to_cust belum tersedia di database';
    }

    error_log('MetaSchool pushNotif failed: ' . $e->getMessage());
    metaschool_log_qris_payment($pdo, [
        'payment_type'     => 'unknown',
        'qris_id'          => $rawRequest['transactionQrId'] ?? null,
        'transaction_id'   => $rawRequest['transactionId'] ?? null,
        'vano'             => $rawRequest['vano'] ?? null,
        'amount'           => isset($rawRequest['amount']) && is_numeric($rawRequest['amount']) ? (float) $rawRequest['amount'] : null,
        'paid_flag'        => 0,
        'status_text'      => 'error',
        'response_code'    => '500',
        'response_message' => $message,
        'request_payload'  => $rawRequest,
        'response_payload' => ['error' => $e->getMessage()],
    ]);
    metaschool_push_notif_response(500, [
        'responseCode'      => '01',
        'responseMessage'   => $message,
        'responseTimestamp' => date('Y-m-d H:i:s'),
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('MetaSchool pushNotif error: ' . $e->getMessage());
    metaschool_log_qris_payment($pdo, [
        'payment_type'     => 'unknown',
        'qris_id'          => $rawRequest['transactionQrId'] ?? null,
        'transaction_id'   => $rawRequest['transactionId'] ?? null,
        'vano'             => $rawRequest['vano'] ?? null,
        'amount'           => isset($rawRequest['amount']) && is_numeric($rawRequest['amount']) ? (float) $rawRequest['amount'] : null,
        'paid_flag'        => 0,
        'status_text'      => 'error',
        'response_code'    => '500',
        'response_message' => 'Invalid token or data',
        'request_payload'  => $rawRequest,
        'response_payload' => ['error' => $e->getMessage()],
    ]);
    metaschool_push_notif_response(500, [
        'responseCode'      => '01',
        'responseMessage'   => 'Gagal memproses notifikasi pembayaran: ' . $e->getMessage(),
        'responseTimestamp' => date('Y-m-d H:i:s'),
    ]);
}
