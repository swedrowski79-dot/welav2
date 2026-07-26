ALTER TABLE `raw_afs_articles`
    ADD COLUMN `attribute_name1` VARCHAR(255) NULL AFTER `variant_flag`,
    ADD COLUMN `attribute_name2` VARCHAR(255) NULL AFTER `attribute_name1`,
    ADD COLUMN `attribute_name3` VARCHAR(255) NULL AFTER `attribute_name2`,
    ADD COLUMN `attribute_name4` VARCHAR(255) NULL AFTER `attribute_name3`,
    ADD COLUMN `attribute_value1` VARCHAR(255) NULL AFTER `attribute_name4`,
    ADD COLUMN `attribute_value2` VARCHAR(255) NULL AFTER `attribute_value1`,
    ADD COLUMN `attribute_value3` VARCHAR(255) NULL AFTER `attribute_value2`,
    ADD COLUMN `attribute_value4` VARCHAR(255) NULL AFTER `attribute_value3`;
