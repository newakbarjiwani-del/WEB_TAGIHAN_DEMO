-- Tabel subscription Web Push (Fase 2)
-- Jalankan di DB demo_smartpayment_installment (103.23.103.36)

DROP TABLE IF EXISTS `push_subscriptions`;

CREATE TABLE `push_subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `endpoint_hash` varchar(64) NOT NULL,
  `endpoint` text NOT NULL,
  `public_key` varchar(255) DEFAULT NULL,
  `auth_token` varchar(255) DEFAULT NULL,
  `content_encoding` varchar(32) NOT NULL DEFAULT 'aesgcm',
  `nocust` varchar(50) DEFAULT NULL,
  `vano` varchar(80) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `push_subscriptions_endpoint_hash_unique` (`endpoint_hash`),
  KEY `push_subscriptions_nocust_index` (`nocust`),
  KEY `push_subscriptions_vano_index` (`vano`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
