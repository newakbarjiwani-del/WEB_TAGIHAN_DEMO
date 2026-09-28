-- =============================================================================
-- Rename tabel PWA lama -> prefix pwa_ (MySQL 5.5+)
-- Jalankan di DB WS/billing yang SUDAH punya tabel versi lama.
--
--   login_tokens           -> pwa_login_tokens
--   multi_account_groups   -> pwa_multi_account_groups
--   multi_account_members  -> pwa_multi_account_members
--   mst_qris               -> pwa_qris
--   mst_qris_item          -> pwa_qris_item
--   log_qris_push          -> pwa_log_qris_push
--   push_subscriptions     -> pwa_push_subscriptions
--
-- Data, index, dan foreign key ikut pindah (FK pwa_qris_item -> pwa_qris dan
-- pwa_multi_account_members -> pwa_multi_account_groups otomatis menyesuaikan).
--
-- Deploy kode baru bersamaan dengan rename ini: kode lama masih membaca nama
-- tabel lama, kode baru hanya membaca nama pwa_.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- LANGKAH 1: cek tabel lama mana yang ada (jalankan dulu, lihat hasilnya)
-- -----------------------------------------------------------------------------
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'login_tokens',
    'multi_account_groups',
    'multi_account_members',
    'mst_qris',
    'mst_qris_item',
    'log_qris_push',
    'push_subscriptions'
  );

-- -----------------------------------------------------------------------------
-- LANGKAH 2: rename (satu statement per tabel)
-- Lewati baris untuk tabel yang tidak muncul di hasil LANGKAH 1,
-- karena RENAME TABLE error jika tabel sumber tidak ada.
-- -----------------------------------------------------------------------------
RENAME TABLE login_tokens TO pwa_login_tokens;
RENAME TABLE multi_account_groups TO pwa_multi_account_groups;
RENAME TABLE multi_account_members TO pwa_multi_account_members;
RENAME TABLE mst_qris TO pwa_qris;
RENAME TABLE mst_qris_item TO pwa_qris_item;
RENAME TABLE log_qris_push TO pwa_log_qris_push;
RENAME TABLE push_subscriptions TO pwa_push_subscriptions;

-- -----------------------------------------------------------------------------
-- LANGKAH 3: verifikasi
-- -----------------------------------------------------------------------------
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME LIKE 'pwa\_%';

-- -----------------------------------------------------------------------------
-- ROLLBACK (jika perlu kembali ke nama lama)
-- -----------------------------------------------------------------------------
-- RENAME TABLE pwa_login_tokens TO login_tokens;
-- RENAME TABLE pwa_multi_account_groups TO multi_account_groups;
-- RENAME TABLE pwa_multi_account_members TO multi_account_members;
-- RENAME TABLE pwa_qris TO mst_qris;
-- RENAME TABLE pwa_qris_item TO mst_qris_item;
-- RENAME TABLE pwa_log_qris_push TO log_qris_push;
-- RENAME TABLE pwa_push_subscriptions TO push_subscriptions;
