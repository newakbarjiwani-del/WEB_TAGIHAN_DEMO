-- Tabel subscription Web Push (notifikasi sistem PWA)
-- Jalankan di DB sekolah / WS / billing (sama TAGIHAN_DB_*), bukan DB Laravel lokal.
-- Unique pakai endpoint_hash karena URL endpoint bisa > 191 chars.

CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `endpoint_hash` varchar(64) NOT NULL COMMENT 'sha256(endpoint)',
  `endpoint` text NOT NULL,
  `public_key` varchar(255) DEFAULT NULL,
  `auth_token` varchar(255) DEFAULT NULL,
  `content_encoding` varchar(32) NOT NULL DEFAULT 'aes128gcm',
  `nocust` varchar(50) DEFAULT NULL,
  `vano` varchar(80) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `push_subscriptions_endpoint_hash_unique` (`endpoint_hash`),
  KEY `push_subscriptions_nocust_index` (`nocust`),
  KEY `push_subscriptions_vano_index` (`vano`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
