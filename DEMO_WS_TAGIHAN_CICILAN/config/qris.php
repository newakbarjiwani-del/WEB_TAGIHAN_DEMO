<?php

/**
 * Konfigurasi generate QRIS (Lazizmu / BMI).
 * Sesuaikan nilai di sini atau via environment server.
 */
return [
    'server_url' => getenv('QRIS_SERVER_URL') ?: 'http://103.23.103.43/qris/lazizmu_diy/server.php',
    'jwt_secret' => getenv('QRIS_JWT_SECRET') ?: 'TokenJWT_BMI_ICT',
    'account_no' => getenv('QRIS_ACCOUNT_NO') ?: '5080010295',
    'mitra_customer_id' => getenv('QRIS_MITRA_CUSTOMER_ID') ?: 'ISLAMIC CENTER SMG451061',
    'tipe_transaksi' => getenv('QRIS_TIPE_TRANSAKSI') ?: 'MTR-GENERATE-QRIS-DYNAMIC',
];
