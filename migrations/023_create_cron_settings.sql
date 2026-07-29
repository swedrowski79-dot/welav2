CREATE TABLE IF NOT EXISTS `cron_settings` (
    `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    `enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `interval_minutes` INT UNSIGNED NOT NULL DEFAULT 5,
    `last_started_at` DATETIME NULL,
    `last_finished_at` DATETIME NULL,
    `last_status` VARCHAR(20) NOT NULL DEFAULT 'never',
    `last_message` VARCHAR(1000) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `cron_settings` (`id`, `enabled`, `interval_minutes`)
VALUES (1, 1, 5);
