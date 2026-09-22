<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/JwtSimple.php';

class QrisPayment
{
    private $pdo;
    private $cfg;

    public function __construct()
    {
        $db = new Database();
        $this->pdo = $db->getConnection();
        $this->cfg = require __DIR__ . '/../config/qris.php';
    }

    /**
     * Generate QRIS via Lazizmu JWT server, simpan header + item (cicil/non-cicil).
     *
     * @param array $meta custid, nocust, namacust, description
     * @param array $items [{aa, amount, is_cicil, sisa_sebelum, nama_tagihan, billcd}]
     * @return array
     */
    public function generate(array $meta, array $items)
    {
        $total = 0;
        $normalized = [];
        foreach ($items as $item) {
            $aa = (int) ($item['aa'] ?? $item['AA'] ?? 0);
            $amount = (int) ($item['amount'] ?? 0);
            if ($aa <= 0 || $amount <= 0) {
                continue;
            }
            $normalized[] = [
                'aa' => $aa,
                'amount' => $amount,
                'is_cicil' => !empty($item['is_cicil']) ? 1 : 0,
                'sisa_sebelum' => isset($item['sisa_sebelum']) ? (float) $item['sisa_sebelum'] : null,
                'nama_tagihan' => (string) ($item['nama_tagihan'] ?? ''),
                'billcd' => (string) ($item['billcd'] ?? $item['BILLCD'] ?? ''),
            ];
            $total += $amount;
        }

        if ($total <= 0 || empty($normalized)) {
            throw new InvalidArgumentException('Item tagihan QRIS tidak valid');
        }

        $vaNumber = '222222' . str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        $transactionId = str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        $amountStr = (string) $total;

        $jwtPayload = [
            'accountNo' => $this->cfg['account_no'],
            'amount' => $amountStr,
            'mitraCustomerId' => $this->cfg['mitra_customer_id'],
            'transactionId' => $transactionId,
            'tipeTransaksi' => $this->cfg['tipe_transaksi'],
            'vano' => $vaNumber,
        ];

        $jwtToken = JwtSimple::encode($jwtPayload, $this->cfg['jwt_secret']);
        $url = rtrim($this->cfg['server_url'], '?&') . '?token=' . urlencode($jwtToken);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL Error: ' . $err);
        }
        curl_close($ch);

        $responseData = json_decode($response, true);
        if (!is_array($responseData)) {
            throw new RuntimeException('Invalid response from QRIS server');
        }

        $code = (string) ($responseData['responseCode'] ?? '');
        if ($code !== '' && $code !== '00') {
            throw new RuntimeException($responseData['responseMessage'] ?? ('QRIS gagal: ' . $code));
        }

        $detail = is_array($responseData['transactionDetail'] ?? null)
            ? $responseData['transactionDetail']
            : [];

        $transactionQrId = $detail['transactionQrId']
            ?? $responseData['transactionQrId']
            ?? null;
        $rawQrData = $detail['rawQrData']
            ?? $responseData['rawQrData']
            ?? null;
        $expiredTime = $detail['expiredTime'] ?? null;
        $serverTrxId = $responseData['transactionId'] ?? $transactionId;

        if (!$transactionQrId) {
            throw new RuntimeException('Transaction QR ID tidak ditemukan dalam response');
        }
        if (!$rawQrData) {
            throw new RuntimeException('Qris content tidak ditemukan dalam response');
        }

        $description = (string) ($meta['description'] ?? ('Pembayaran tagihan ' . ($meta['namacust'] ?? '')));
        $requestJson = json_encode($jwtPayload);
        $responseJson = json_encode($responseData);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO mst_qris (
                    custid, nocust, namacust, vano, amount, qris_id, qris_content,
                    transaction_id, account_no, mitra_customer_id, description,
                    status, paid_flag, request_payload, response_payload, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    'pending', 0, ?, ?, NOW(), NOW()
                )"
            );
            $stmt->execute([
                (string) ($meta['custid'] ?? ''),
                (string) ($meta['nocust'] ?? ''),
                (string) ($meta['namacust'] ?? ''),
                $vaNumber,
                $total,
                $transactionQrId,
                $rawQrData,
                $transactionId,
                $this->cfg['account_no'],
                $this->cfg['mitra_customer_id'],
                $description,
                $requestJson,
                $responseJson,
            ]);
            $paymentId = (int) $this->pdo->lastInsertId();

            $itemStmt = $this->pdo->prepare(
                "INSERT INTO mst_qris_item (
                    payment_id, aa, billcd, nama_tagihan, amount, is_cicil, sisa_sebelum, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            foreach ($normalized as $row) {
                $itemStmt->execute([
                    $paymentId,
                    $row['aa'],
                    $row['billcd'] !== '' ? $row['billcd'] : null,
                    $row['nama_tagihan'] !== '' ? $row['nama_tagihan'] : null,
                    $row['amount'],
                    $row['is_cicil'],
                    $row['sisa_sebelum'],
                ]);
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return [
            'id' => $paymentId,
            'vano' => $vaNumber,
            'amount' => $total,
            'qris_id' => $transactionQrId,
            'transactionQrId' => $transactionQrId,
            'rawQrData' => $rawQrData,
            'qris_content' => $rawQrData,
            'status' => 'pending',
            'transaction_id' => (string) $serverTrxId,
            'expiredTime' => $expiredTime,
            'responseCode' => $code !== '' ? $code : '00',
            'items' => $normalized,
        ];
    }

    /**
     * Simpan hasil generate QRIS yang sudah dibuat di luar (Laravel).
     */
    public function saveGenerated(array $meta, array $items, array $qris)
    {
        $total = (float) ($qris['amount'] ?? 0);
        $normalized = [];
        foreach ($items as $item) {
            $aa = (int) ($item['aa'] ?? $item['AA'] ?? 0);
            $amount = (int) ($item['amount'] ?? 0);
            if ($aa <= 0 || $amount <= 0) {
                continue;
            }
            $normalized[] = [
                'aa' => $aa,
                'amount' => $amount,
                'is_cicil' => !empty($item['is_cicil']) ? 1 : 0,
                'sisa_sebelum' => isset($item['sisa_sebelum']) ? (float) $item['sisa_sebelum'] : null,
                'nama_tagihan' => (string) ($item['nama_tagihan'] ?? ''),
                'billcd' => (string) ($item['billcd'] ?? ''),
            ];
            if ($total <= 0) {
                $total += $amount;
            }
        }

        $rawQrData = $qris['rawQrData'] ?? $qris['qris_content'] ?? null;
        $transactionQrId = $qris['qris_id'] ?? $qris['transactionQrId'] ?? null;
        if (!$rawQrData || !$transactionQrId) {
            throw new InvalidArgumentException('Data QRIS tidak lengkap untuk disimpan');
        }

        $vaNumber = (string) ($qris['vano'] ?? '');
        $transactionId = (string) ($qris['transaction_id'] ?? '');
        $description = (string) ($meta['description'] ?? '');
        $requestJson = json_encode($qris['request_payload'] ?? $qris);
        $responseJson = json_encode($qris['serverResponse'] ?? $qris);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO mst_qris (
                    custid, nocust, namacust, vano, amount, qris_id, qris_content,
                    transaction_id, account_no, mitra_customer_id, description,
                    status, paid_flag, request_payload, response_payload, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    'pending', 0, ?, ?, NOW(), NOW()
                )"
            );
            $stmt->execute([
                (string) ($meta['custid'] ?? ''),
                (string) ($meta['nocust'] ?? ''),
                (string) ($meta['namacust'] ?? ''),
                $vaNumber,
                $total,
                $transactionQrId,
                $rawQrData,
                $transactionId,
                (string) ($qris['account_no'] ?? $this->cfg['account_no']),
                (string) ($qris['mitra_customer_id'] ?? $this->cfg['mitra_customer_id']),
                $description,
                $requestJson,
                $responseJson,
            ]);
            $paymentId = (int) $this->pdo->lastInsertId();

            $itemStmt = $this->pdo->prepare(
                "INSERT INTO mst_qris_item (
                    payment_id, aa, billcd, nama_tagihan, amount, is_cicil, sisa_sebelum, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            foreach ($normalized as $row) {
                $itemStmt->execute([
                    $paymentId,
                    $row['aa'],
                    $row['billcd'] !== '' ? $row['billcd'] : null,
                    $row['nama_tagihan'] !== '' ? $row['nama_tagihan'] : null,
                    $row['amount'],
                    $row['is_cicil'],
                    $row['sisa_sebelum'],
                ]);
            }
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['id' => $paymentId, 'status' => 'pending'];
    }
}
