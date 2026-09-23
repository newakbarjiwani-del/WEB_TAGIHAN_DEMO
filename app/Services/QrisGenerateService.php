<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class QrisGenerateService
{
    /**
     * Panggil server Lazizmu QRIS (JWT) — pola sama seperti script generate sample (cURL POST + token query).
     *
     * @param  array  $meta  custid, nocust, namacust, description
     * @param  array  $items [{aa, amount, is_cicil, ...}]
     * @return array
     */
    public function generate(array $meta, array $items): array
    {
        $cfg = config('brand.qris', []);
        $serverUrl = (string) ($cfg['server_url'] ?? '');
        $secret = (string) ($cfg['jwt_secret'] ?? '');
        $accountNo = (string) ($cfg['account_no'] ?? '');
        $mitraId = (string) ($cfg['mitra_customer_id'] ?? '');
        $tipe = (string) ($cfg['tipe_transaksi'] ?? 'MTR-GENERATE-QRIS-DYNAMIC');

        if ($serverUrl === '' || $secret === '' || $accountNo === '') {
            throw new RuntimeException('Konfigurasi QRIS belum lengkap di .env');
        }

        $normalized = [];
        $total = 0;
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
                'sisa_sebelum' => $item['sisa_sebelum'] ?? null,
                'nama_tagihan' => (string) ($item['nama_tagihan'] ?? ''),
                'billcd' => (string) ($item['billcd'] ?? $item['BILLCD'] ?? ''),
            ];
            $total += $amount;
        }

        if ($total <= 0 || empty($normalized)) {
            throw new RuntimeException('Item tagihan QRIS tidak valid');
        }

        $nocust = preg_replace('/\s+/', '', (string) ($meta['nocust'] ?? ''));
        foreach (['751000', '757777', '797766'] as $prefix) {
            if (strpos($nocust, $prefix) === 0) {
                $nocust = substr($nocust, strlen($prefix));
                break;
            }
        }
        if ($nocust === '') {
            throw new RuntimeException('nocust wajib diisi untuk vano QRIS');
        }
        // VA bank: 751000 + NOCUST (routing pushNotif by prefix 751000)
        $vano = '751000'.$nocust;
        $transactionId = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        $amountStr = (string) $total;

        $jwtPayload = [
            'accountNo' => $accountNo,
            'amount' => $amountStr,
            'mitraCustomerId' => $mitraId,
            'transactionId' => $transactionId,
            'tipeTransaksi' => $tipe,
            'vano' => $vano,
        ];

        $token = $this->encodeJwt($jwtPayload, $secret);
        $url = rtrim($serverUrl, '?&').'?token='.urlencode($token);

        // Harus cURL POST tanpa body JSON `[]` — Http::asJson()->post(..., []) merusak response server
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $raw = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        Log::info('QRIS Lazizmu response', [
            'http' => $http,
            'curl_error' => $cerr,
            'raw_len' => is_string($raw) ? strlen($raw) : 0,
            'raw_preview' => is_string($raw) ? substr($raw, 0, 400) : null,
        ]);

        if ($raw === false || $cerr !== '') {
            throw new RuntimeException('cURL Error: '.($cerr ?: 'unknown'));
        }

        $body = json_decode((string) $raw, true);
        if (!is_array($body)) {
            throw new RuntimeException('Invalid response from QRIS server');
        }

        $code = (string) ($body['responseCode'] ?? '');
        if ($code !== '' && $code !== '00') {
            throw new RuntimeException($body['responseMessage'] ?? ('QRIS gagal: '.$code));
        }

        $detail = is_array($body['transactionDetail'] ?? null) ? $body['transactionDetail'] : [];
        $rawQr = $detail['rawQrData'] ?? $body['rawQrData'] ?? null;
        $qrisId = $detail['transactionQrId'] ?? $body['transactionQrId'] ?? null;
        $trxId = (string) ($body['transactionId'] ?? $transactionId);
        $expired = $detail['expiredTime'] ?? null;

        if (!$rawQr) {
            throw new RuntimeException('Qris content (rawQrData) tidak ditemukan dalam response');
        }
        if (!$qrisId) {
            throw new RuntimeException('Transaction QR ID tidak ditemukan dalam response');
        }

        return [
            'vano' => $vano,
            'amount' => $total,
            'qris_id' => (string) $qrisId,
            'transactionQrId' => (string) $qrisId,
            'rawQrData' => (string) $rawQr,
            'qris_content' => (string) $rawQr,
            'status' => 'pending',
            'transaction_id' => $trxId,
            'expiredTime' => $expired,
            'responseCode' => $code !== '' ? $code : '00',
            'responseMessage' => $body['responseMessage'] ?? 'Success',
            'account_no' => $accountNo,
            'mitra_customer_id' => $mitraId,
            'items' => $normalized,
            'serverResponse' => $body,
            'request_payload' => $jwtPayload,
        ];
    }

    private function encodeJwt(array $payload, string $key): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $segments = [
            $this->b64(json_encode($header, JSON_UNESCAPED_SLASHES)),
            $this->b64(json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ];
        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $key, true);
        $segments[] = $this->b64($signature);

        return implode('.', $segments);
    }

    private function b64(string $input): string
    {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }
}
