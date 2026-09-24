-- =============================================================================
-- WEB_TAGIHAN PWA — tabel tambahan (jalankan di DB sekolah / WS / billing)
-- =============================================================================
-- Bukan database Laravel lokal (kecuali TAGIHAN_DB_* mengarah ke DB yang sama).
-- Contoh DB demo: demo_smartpayment_installment @ 103.23.103.36
--
-- Isi:
--   A) multi_account_groups + multi_account_members  → Multi akun PWA
--   B) login_tokens                                   → Login via link token (opsional)
--   C) mst_qris + mst_qris_item + log_qris_push        → QRIS top up + audit callback
--   D) push_subscriptions                             → Web Push notifikasi sistem
--
-- Cara jalankan (MySQL CLI / phpMyAdmin / HeidiSQL):
--   USE nama_database_sekolah;
--   SOURCE database/sql/pwa_extra_tables.sql;
--
-- Atau file per fitur:
--   DEMO_WS_TAGIHAN_CICILAN/sql/multi_account_tables.sql
--   DEMO_WS_TAGIHAN_CICILAN/sql/login_tokens.sql
--   DEMO_WS_TAGIHAN_CICILAN/sql/qris_payment_tables.sql
--   database/sql/push_subscriptions.sql
--
-- MySQL lama: DATETIME NULL (hindari error 1293 dual CURRENT_TIMESTAMP).
-- =============================================================================

-- -----------------------------------------------------------------------------
-- A. Multi akun PWA
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS multi_account_groups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS multi_account_members (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_id BIGINT UNSIGNED NOT NULL,
  no_cust VARCHAR(50) NOT NULL COMMENT 'VA/NIS sudah dinormalisasi (normalizeVa)',
  va_display VARCHAR(80) NULL COMMENT 'VA asli yang diinput user',
  nama VARCHAR(150) NULL,
  kelas VARCHAR(100) NULL,
  jenjang VARCHAR(50) NULL,
  last_academic_year VARCHAR(50) NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_member_no_cust (no_cust),
  KEY idx_members_group (group_id),
  CONSTRAINT fk_members_group
    FOREIGN KEY (group_id) REFERENCES multi_account_groups(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- B. Login token (link sekali pakai dari dashboard admin) — opsional
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  token CHAR(64) NOT NULL COMMENT 'Hex 64 karakter; path URL GET /{token}',
  no_cust VARCHAR(50) NOT NULL COMMENT 'NIS/VA sudah dinormalisasi (tanpa prefix bank)',
  custid INT NULL COMMENT 'CUSTID jika diketahui',
  tahun_akademik VARCHAR(50) NOT NULL DEFAULT 'all',
  expires_at DATETIME NOT NULL COMMENT 'Link mati setelah waktu ini',
  used_at DATETIME NULL COMMENT 'NULL = belum dipakai; diisi saat login sukses',
  created_by VARCHAR(100) NULL COMMENT 'Id/nama admin dari dashboard admin',
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_login_tokens_token (token),
  KEY idx_login_tokens_no_cust (no_cust),
  KEY idx_login_tokens_expires (expires_at),
  KEY idx_login_tokens_used (used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- C. QRIS (generate + callback paid + audit)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mst_qris (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  custid VARCHAR(64) NOT NULL,
  nocust VARCHAR(64) NOT NULL,
  namacust VARCHAR(191) NULL,
  vano VARCHAR(64) NULL COMMENT 'prefix bank + nocust (routing pushNotif)',
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  qris_id VARCHAR(128) NULL,
  qris_content TEXT NULL COMMENT 'rawQrData EMV QR string',
  transaction_id VARCHAR(64) NULL,
  account_no VARCHAR(64) NULL,
  mitra_customer_id VARCHAR(191) NULL,
  description VARCHAR(255) NULL,
  status ENUM('pending','paid','expired','failed') NOT NULL DEFAULT 'pending',
  paid_flag TINYINT(1) NOT NULL DEFAULT 0,
  request_payload MEDIUMTEXT NULL,
  response_payload MEDIUMTEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  paid_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_mst_qris_custid (custid),
  KEY idx_mst_qris_nocust (nocust),
  KEY idx_mst_qris_status (status),
  KEY idx_mst_qris_qris_id (qris_id),
  KEY idx_mst_qris_vano (vano)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mst_qris_item (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id BIGINT UNSIGNED NOT NULL,
  aa BIGINT UNSIGNED NOT NULL COMMENT 'ID tagihan (AA)',
  billcd VARCHAR(64) NULL,
  nama_tagihan VARCHAR(255) NULL,
  amount DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'Nominal dibayar untuk baris ini',
  is_cicil TINYINT(1) NOT NULL DEFAULT 0,
  sisa_sebelum DECIMAL(18,2) NULL COMMENT 'Sisa tagihan saat generate',
  created_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_mst_qris_item_payment (payment_id),
  KEY idx_mst_qris_item_aa (aa),
  CONSTRAINT fk_mst_qris_item_payment
    FOREIGN KEY (payment_id) REFERENCES mst_qris(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS log_qris_push (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  payment_id BIGINT UNSIGNED NULL COMMENT 'mst_qris.id (null jika not found)',
  event_type VARCHAR(32) NOT NULL DEFAULT 'push_notif'
    COMMENT 'push_notif | already_paid | not_found | error',
  qris_id VARCHAR(128) NULL,
  transaction_id VARCHAR(64) NULL,
  vano VARCHAR(64) NULL,
  custid VARCHAR(64) NULL,
  nocust VARCHAR(64) NULL,
  amount DECIMAL(18,2) NULL,
  paid_flag TINYINT(1) NULL COMMENT '1=sudah paid setelah proses',
  processed VARCHAR(32) NULL
    COMMENT 'new_processing | already_processed | not_found | no_change | error',
  scctva_status VARCHAR(32) NULL
    COMMENT 'inserted | failed | skipped | null',
  response_code VARCHAR(8) NULL COMMENT '00 sukses / 01 gagal',
  response_message VARCHAR(255) NULL,
  http_code SMALLINT UNSIGNED NULL,
  request_payload MEDIUMTEXT NULL COMMENT 'payload JWT decoded / raw callback',
  response_payload MEDIUMTEXT NULL COMMENT 'JSON response ke gateway',
  source VARCHAR(128) NULL DEFAULT 'qris/pushNotif/demoInstallment.php',
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_log_qris_push_qris_id (qris_id),
  KEY idx_log_qris_push_vano (vano),
  KEY idx_log_qris_push_payment (payment_id),
  KEY idx_log_qris_push_custid (custid),
  KEY idx_log_qris_push_created (created_at),
  KEY idx_log_qris_push_event (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- D. Web Push (notifikasi sistem PWA)
-- Unique pakai endpoint_hash (SHA-256) karena URL endpoint bisa > 191 chars
-- (batas index MySQL 767 bytes / utf8mb4).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS push_subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  endpoint_hash VARCHAR(64) NOT NULL COMMENT 'sha256(endpoint) untuk unique pendek',
  endpoint TEXT NOT NULL COMMENT 'URL push service browser (FCM/Mozilla/dll)',
  public_key VARCHAR(255) NULL,
  auth_token VARCHAR(255) NULL,
  content_encoding VARCHAR(32) NOT NULL DEFAULT 'aes128gcm',
  nocust VARCHAR(50) NULL COMMENT 'NIS/userlogin (normalizeVa)',
  vano VARCHAR(80) NULL COMMENT 'VA penuh jika ada',
  user_agent VARCHAR(255) NULL,
  last_used_at DATETIME NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY push_subscriptions_endpoint_hash_unique (endpoint_hash),
  KEY push_subscriptions_nocust_index (nocust),
  KEY push_subscriptions_vano_index (vano)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Verifikasi cepat (opsional)
-- =============================================================================
-- SHOW TABLES LIKE 'multi_account%';
-- SHOW TABLES LIKE 'login_tokens';
-- SHOW TABLES LIKE 'mst_qris%';
-- SHOW TABLES LIKE 'log_qris_push';
-- SHOW TABLES LIKE 'push_subscriptions';
--
-- SELECT COUNT(*) AS n FROM push_subscriptions;
-- SELECT COUNT(*) AS n FROM multi_account_members;
-- SELECT id, nocust, amount, status, paid_flag, paid_at FROM mst_qris ORDER BY id DESC LIMIT 10;
