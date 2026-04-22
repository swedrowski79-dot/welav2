ALTER TABLE `raw_extra_attribute_translations`
    ADD COLUMN `afs_artikel_id` INT NULL AFTER `row_id`,
    ADD COLUMN `sku` VARCHAR(255) NULL AFTER `afs_artikel_id`,
    ADD COLUMN `sort_order` INT NULL AFTER `sku`,
    ADD COLUMN `attribute_value` VARCHAR(255) NULL AFTER `attribute_name`,
    ADD COLUMN `source_directory` VARCHAR(255) NULL AFTER `language_code_normalized`,
    ADD COLUMN `translated_value` VARCHAR(255) NULL AFTER `translated_name`,
    DROP INDEX `idx_raw_extra_attribute_translations_name_lang`,
    ADD KEY `idx_raw_extra_attribute_translations_afs_artikel_id` (`afs_artikel_id`),
    ADD KEY `idx_raw_extra_attribute_translations_sku` (`sku`),
    ADD KEY `idx_raw_extra_attribute_translations_name_lang` (`attribute_name`, `attribute_value`, `language_code_normalized`);
