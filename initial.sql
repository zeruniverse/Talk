SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `talk_messages` (
  `code` varchar(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ciphertext` mediumblob NOT NULL,
  `ciphertext_bytes` int unsigned NOT NULL,
  `salt` varchar(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `access_token_hmac` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `hint` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `kdf` varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'PBKDF2-SHA256',
  `pbkdf2_iterations` int unsigned NOT NULL DEFAULT 210000,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expire_at` datetime NOT NULL,
  `opened_at` datetime DEFAULT NULL,
  PRIMARY KEY (`code`),
  KEY `idx_expire_at` (`expire_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `talk_rate_limits` (
  `scope` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `bucket` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `hits` int unsigned NOT NULL DEFAULT 0,
  `reset_at` datetime NOT NULL,
  PRIMARY KEY (`scope`, `bucket`),
  KEY `idx_reset_at` (`reset_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
