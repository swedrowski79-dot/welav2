CREATE TABLE IF NOT EXISTS `raw_extra_attribute_translations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `row_id` BIGINT NULL,
    `attribute_name` VARCHAR(255) NULL,
    `attribute_value` VARCHAR(255) NULL,
    `language_code` VARCHAR(10) NULL,
    `language_code_normalized` VARCHAR(10) NULL,
    `translated_name` VARCHAR(255) NULL,
    `translated_value` VARCHAR(255) NULL,
    `is_auto_generated` TINYINT NULL,
    `translation_source` VARCHAR(50) NULL,
    KEY `idx_raw_extra_attribute_translations_name` (`attribute_name`),
    KEY `idx_raw_extra_attribute_translations_lang` (`language_code_normalized`),
    KEY `idx_raw_extra_attribute_translations_name_lang` (`attribute_name`, `attribute_value`, `language_code_normalized`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
