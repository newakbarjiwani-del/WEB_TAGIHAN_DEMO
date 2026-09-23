-- =============================================================================
-- QRIS tagihan cicilan (WEB_TAGIHAN_DEMO / DEMO_WS)
-- Jalankan di DB yang sama dengan DEMO_WS (mis. sidoarjo_raudhatul_jannah)
--
-- 1) mst_qris       — header transaksi generate QRIS
-- 2) mst_qris_item  — detail tagihan (AA) yang dibayar di QRIS tsb
-- 3) log_qris_push  — audit trail setiap callback pushNotif
--
-- MySQL lama: DATETIME NULL (hindari error 1293 dual CURRENT_TIMESTAMP).
-- =============================================================================

CREATE TABLE IF NOT EXISTS mst_qris (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  custid VARCHAR(64) NOT NULL,
  nocust VARCHAR(64) NOT NULL,
  namacust VARCHAR(191) NULL,
  vano VARCHAR(64) NULL COMMENT '751000 + nocust (routing pushNotif)',
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
  amount DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'Nominal dibayar untuk baris ini (boleh partial jika cicil)',
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
  source VARCHAR(128) NULL DEFAULT 'qris/pushNotif/tagihanCicilan.php',
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
