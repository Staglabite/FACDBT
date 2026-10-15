-- =====================================================================
-- Index untuk mempercepat dashboard stock taking (FACDBT04)
-- Database : INV_TAKING (MySQL 5.6+)
--
-- Jalankan oleh DBA di luar jam taking. Script aman dijalankan berulang:
-- index hanya dibuat bila belum ada index dengan nama yang sama.
-- Cek dulu index yang sudah ada (bisa jadi kolomnya sudah ter-index dengan nama lain):
--   SHOW INDEX FROM SIM_INV_TAKING_BOOK_TAB;
--   SHOW INDEX FROM SIM_INV_TAKING_DATA_TAB;
--   SHOW INDEX FROM SIM_INV_UNIT_COST_TEST;
--   SHOW INDEX FROM SIM_INV_GROUP_LOCATION;
--
-- Rollback (bila perlu):
--   ALTER TABLE SIM_INV_TAKING_BOOK_TAB DROP INDEX idx_book_invlist_created;
--   ALTER TABLE SIM_INV_TAKING_DATA_TAB DROP INDEX idx_taking_created;
--   ALTER TABLE SIM_INV_UNIT_COST_TEST  DROP INDEX idx_unitcost_part;
--   ALTER TABLE SIM_INV_GROUP_LOCATION  DROP INDEX idx_grouploc_location;
-- =====================================================================

DROP PROCEDURE IF EXISTS facdbt_add_index_if_missing;

DELIMITER $$
CREATE PROCEDURE facdbt_add_index_if_missing(IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_columns VARCHAR(255))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = p_table AND index_name = p_index
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` (', p_columns, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- Book: semua dashboard memfilter INV_LIST_NO + rentang CREATED_DATETIME
CALL facdbt_add_index_if_missing('SIM_INV_TAKING_BOOK_TAB', 'idx_book_invlist_created', '`INV_LIST_NO`, `CREATED_DATETIME`');

-- Taking: semua dashboard memfilter rentang CREATED_DATETIME (kumulatif s/d jam slot)
CALL facdbt_add_index_if_missing('SIM_INV_TAKING_DATA_TAB', 'idx_taking_created', '`CREATED_DATETIME`');

-- Master yang di-join / dikelompokkan per part & lokasi
CALL facdbt_add_index_if_missing('SIM_INV_UNIT_COST_TEST', 'idx_unitcost_part', '`part_no`');
CALL facdbt_add_index_if_missing('SIM_INV_GROUP_LOCATION', 'idx_grouploc_location', '`LOCATION_NO`');

DROP PROCEDURE IF EXISTS facdbt_add_index_if_missing;
