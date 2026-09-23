-- DEPRECATED untuk produksi.
-- Multi akun sekarang disimpan di database WS/billing.
-- Jalankan:
--   DEMO_WS_TAGIHAN_CICILAN/sql/multi_account_tables.sql
--   atau DEMO_WS_TAGIHAN_CICILAN/sql/install_missing_tables.sql

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
