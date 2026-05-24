SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `talk_messages` (
  `code` varchar(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ciphertext` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `iv` varchar(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `salt` varchar(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `kdf` varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'PBKDF2-SHA256',
  `iterations` int unsigned NOT NULL,
  `access_token_hmac` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `hint` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `one_time` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  `opened_at` datetime DEFAULT NULL,
  PRIMARY KEY (`code`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `talk_rate_limits` (
  `bucket` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `hits` int unsigned NOT NULL,
  `reset_at` datetime NOT NULL,
  PRIMARY KEY (`bucket`),
  KEY `idx_reset_at` (`reset_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
