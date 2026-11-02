-- =====================================================================
-- FACDBT: penyesuaian nama tabel & kolom dengan Design Architecture Guidance Database (Rev01).
-- Database : dbportal (koneksi INV_TAKING, MySQL)
--
-- - 7 tabel milik FACDBT dibuat ulang dengan nama FACDBT_<kode tabel><deskripsi>S dan kolom berawalan tipe data,
--   ditambah kolom wajib VCREA, DCREA, VMODI, DMODI, VDELETE, DDELETE (soft delete).
-- - Data lama disalin; tabel lama TIDAK dihapus, hanya diganti nama menjadi <nama lama>_OLD sebagai cadangan.
-- - Tabel sistem taking / scanner (SIM_INV_TAKING_BOOK_TAB, _DATA_TAB, _DATA_REVISION_TAB, _CONCLUDING_TAB,
--   _BOOK_TEMP_TAB) tidak diubah.
--
-- Jalankan BERSAMAAN dengan deploy kode versi ini (kode lama membaca nama tabel lama), di luar jam taking.
-- Charset / collation tabel baru mengikuti default database, sama seperti tabel lama, supaya join dengan
-- tabel scanner tidak error "Illegal mix of collations".
--
-- Sebelum menjalankan, cocokkan tipe kolom dengan tabel lama (SHOW CREATE TABLE <tabel lama>), terutama
-- panjang VARCHAR dan presisi DECIMAL master Unit Cost.
--
-- Setelah aplikasi dicek berjalan normal, tabel *_OLD boleh dihapus (lihat bagian paling bawah).
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Master Location (FACDBTF001): SIM_INV_LOCATION_TEST -> FACDBT_MSHLOCATIONS
-- ---------------------------------------------------------------------
CREATE TABLE FACDBT_MSHLOCATIONS (
    IID             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    VLOCATIONNO     VARCHAR(100)  NOT NULL COMMENT 'Location No',
    VLOCGROUP       VARCHAR(50)   NULL     COMMENT 'Location Group',
    VSITE           VARCHAR(50)   NULL     COMMENT 'Site',
    VWAREHOUSE      VARCHAR(100)  NULL     COMMENT 'Warehouse',
    VLOCDESC        VARCHAR(255)  NULL     COMMENT 'Location Description',
    VLOCGROUPDESC   VARCHAR(255)  NULL     COMMENT 'Location Group Description',
    VCREA           VARCHAR(100)  NOT NULL,
    DCREA           DATETIME      NOT NULL,
    VMODI           VARCHAR(100)  NULL,
    DMODI           DATETIME      NULL,
    VDELETE         VARCHAR(100)  NULL,
    DDELETE         DATETIME      NULL,
    PRIMARY KEY (IID),
    UNIQUE KEY uq_mshlocations_locno (VLOCATIONNO)
);

INSERT INTO FACDBT_MSHLOCATIONS (VLOCATIONNO, VLOCGROUP, VSITE, VWAREHOUSE, VLOCDESC, VLOCGROUPDESC, VCREA, DCREA)
SELECT location_no, location_group, site, warehouse, location_description, location_group_description, 'MIGRASI', NOW()
FROM SIM_INV_LOCATION_TEST;

-- ---------------------------------------------------------------------
-- 2. Master Part (FACDBTF002): SIM_INV_PART_TEST -> FACDBT_MSHPARTS
-- ---------------------------------------------------------------------
CREATE TABLE FACDBT_MSHPARTS (
    IID             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    VPARTNO         VARCHAR(100)  NOT NULL COMMENT 'Part No',
    VPARTDESC       VARCHAR(255)  NULL     COMMENT 'Inventory Part Description',
    VACCGROUP       VARCHAR(100)  NULL     COMMENT 'Accounting Group',
    VSEG            VARCHAR(100)  NULL     COMMENT 'Seg (4W / 2W)',
    VPRODGROUP      VARCHAR(100)  NULL     COMMENT 'Prod Group (Product Family)',
    VPARTGROUP      VARCHAR(100)  NULL     COMMENT 'Part Group',
    VCREA           VARCHAR(100)  NOT NULL,
    DCREA           DATETIME      NOT NULL,
    VMODI           VARCHAR(100)  NULL,
    DMODI           DATETIME      NULL,
    VDELETE         VARCHAR(100)  NULL,
    DDELETE         DATETIME      NULL,
    PRIMARY KEY (IID),
    UNIQUE KEY uq_mshparts_partno (VPARTNO)
);

INSERT INTO FACDBT_MSHPARTS (VPARTNO, VPARTDESC, VACCGROUP, VSEG, VPRODGROUP, VPARTGROUP, VCREA, DCREA)
SELECT part_no, inventory_part_description, accounting_group, seg, prod_group, part_group, 'MIGRASI', NOW()
FROM SIM_INV_PART_TEST;

-- ---------------------------------------------------------------------
-- 3. Master Unit Cost (FACDBTF003): SIM_INV_UNIT_COST_TEST -> FACDBT_MSHUNITCOSTS
--    IID lama (kolom id) dipertahankan.
-- ---------------------------------------------------------------------
CREATE TABLE FACDBT_MSHUNITCOSTS (
    IID             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    VPARTNO         VARCHAR(100)   NOT NULL COMMENT 'Part No',
    VPARTDESC       VARCHAR(255)   NULL     COMMENT 'Inventory Part Description',
    VUOM            VARCHAR(50)    NULL     COMMENT 'Inventory UoM (ml = oil)',
    VACCGROUP       VARCHAR(100)   NULL     COMMENT 'Accounting Group',
    NONHANDQTY      DECIMAL(20,6)  NULL     COMMENT 'On Hand Qty',
    MMATCOST        DECIMAL(20,6)  NULL     COMMENT 'Material Cost',
    MLABCOST        DECIMAL(20,6)  NULL     COMMENT 'Labor Cost',
    MFOHCOST        DECIMAL(20,6)  NULL     COMMENT 'FOH Cost',
    VCREA           VARCHAR(100)   NOT NULL,
    DCREA           DATETIME       NOT NULL,
    VMODI           VARCHAR(100)   NULL,
    DMODI           DATETIME       NULL,
    VDELETE         VARCHAR(100)   NULL,
    DDELETE         DATETIME       NULL,
    PRIMARY KEY (IID),
    KEY idx_mshunitcosts_partno (VPARTNO)
);

-- Kolom angka lama bisa berupa teks (mis. on_hand_qty kosong / '1,234'): kosong -> NULL, koma ribuan dibuang
INSERT INTO FACDBT_MSHUNITCOSTS (IID, VPARTNO, VPARTDESC, VUOM, VACCGROUP, NONHANDQTY, MMATCOST, MLABCOST, MFOHCOST, VCREA, DCREA)
SELECT id, part_no, inventory_part_description, inventory_uom, accounting_group,
       NULLIF(TRIM(REPLACE(on_hand_qty, ',', '')), ''),
       NULLIF(TRIM(REPLACE(mat_cost, ',', '')), ''),
       NULLIF(TRIM(REPLACE(lab_cost, ',', '')), ''),
       NULLIF(TRIM(REPLACE(foh_cost, ',', '')), ''),
       'MIGRASI', NOW()
FROM SIM_INV_UNIT_COST_TEST;

-- ---------------------------------------------------------------------
-- 4. Master Group Location (FACDBTF005): SIM_INV_GROUP_LOCATION -> FACDBT_MSHGROUPLOCATIONS
--    IID lama dipertahankan (urutan IID menentukan area pertama lokasi di beberapa group).
-- ---------------------------------------------------------------------
CREATE TABLE FACDBT_MSHGROUPLOCATIONS (
    IID             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    VLOCATIONNO     VARCHAR(50)   NOT NULL COMMENT 'Location No',
    VIDLOC          VARCHAR(50)   NOT NULL COMMENT 'ID Loc',
    VLOCGROUP       VARCHAR(50)   NOT NULL COMMENT 'Location Group (area dashboard)',
    VSTREAM         VARCHAR(100)  NULL     COMMENT 'Stream',
    VCREA           VARCHAR(100)  NOT NULL,
    DCREA           DATETIME      NOT NULL,
    VMODI           VARCHAR(100)  NULL,
    DMODI           DATETIME      NULL,
    VDELETE         VARCHAR(100)  NULL,
    DDELETE         DATETIME      NULL,
    PRIMARY KEY (IID),
    KEY idx_mshgrouplocations_locno (VLOCATIONNO)
);

INSERT INTO FACDBT_MSHGROUPLOCATIONS (IID, VLOCATIONNO, VIDLOC, VLOCGROUP, VSTREAM, VCREA, DCREA)
SELECT IID, LOCATION_NO, ID_LOC, location_group, stream, 'MIGRASI', NOW()
FROM SIM_INV_GROUP_LOCATION;

-- ---------------------------------------------------------------------
-- 5. Master Event (FACDBTF006): SIM_INV_TAKING_EVENT -> FACDBT_MSHEVENTS
--    IID lama dipertahankan (dipakai daftar part event).
-- ---------------------------------------------------------------------
CREATE TABLE FACDBT_MSHEVENTS (
    IID             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    DTAKINGDATE     DATE          NOT NULL COMMENT 'Periode: SIM_INV_TAKING_CONCLUDING_TAB.TAKING_DATE',
    VEVENTTYPE      VARCHAR(10)   NOT NULL COMMENT 'PRE / TWH / DIRECT',
    VDESC           VARCHAR(255)  NULL,
    VCREA           VARCHAR(100)  NOT NULL,
    DCREA           DATETIME      NOT NULL,
    VMODI           VARCHAR(100)  NULL,
    DMODI           DATETIME      NULL,
    VDELETE         VARCHAR(100)  NULL,
    DDELETE         DATETIME      NULL,
    PRIMARY KEY (IID),
    UNIQUE KEY uq_mshevents_takingdate (DTAKINGDATE)
);

INSERT INTO FACDBT_MSHEVENTS (IID, DTAKINGDATE, VEVENTTYPE, VDESC, VCREA, DCREA, VMODI, DMODI)
SELECT IID, TAKING_DATE, EVENT_TYPE, DESCRIPTION, COALESCE(NULLIF(CREATED_BY, ''), 'MIGRASI'), COALESCE(CREATED_DATETIME, NOW()), MODIFIED_BY, MODIFIED_DATETIME
FROM SIM_INV_TAKING_EVENT;

-- ---------------------------------------------------------------------
-- 6. Daftar part Pre-Taking: SIM_INV_TAKING_EVENT_PART -> FACDBT_MSDEVENTPARTS
-- ---------------------------------------------------------------------
CREATE TABLE FACDBT_MSDEVENTPARTS (
    IID             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    IEVENTID        INT UNSIGNED  NOT NULL COMMENT 'FACDBT_MSHEVENTS.IID',
    VPARTNO         VARCHAR(100)  NOT NULL COMMENT 'Part No',
    VCREA           VARCHAR(100)  NOT NULL,
    DCREA           DATETIME      NOT NULL,
    VMODI           VARCHAR(100)  NULL,
    DMODI           DATETIME      NULL,
    VDELETE         VARCHAR(100)  NULL,
    DDELETE         DATETIME      NULL,
    PRIMARY KEY (IID),
    UNIQUE KEY uq_msdeventparts_event_part (IEVENTID, VPARTNO),
    KEY idx_msdeventparts_partno (VPARTNO)
);

INSERT INTO FACDBT_MSDEVENTPARTS (IID, IEVENTID, VPARTNO, VCREA, DCREA)
SELECT IID, EVENT_ID, PART_NO, COALESCE(NULLIF(CREATED_BY, ''), 'MIGRASI'), COALESCE(CREATED_DATETIME, NOW())
FROM SIM_INV_TAKING_EVENT_PART;

-- ---------------------------------------------------------------------
-- 7. History Taking (FACDBTD005): SIM_INV_TAKING_DATA_HISTORY -> FACDBT_HISTAKINGDATAS
--    BITEMBOOK / BITEMTAKING: boolean varchar(1) '0' / '1'.
-- ---------------------------------------------------------------------
CREATE TABLE FACDBT_HISTAKINGDATAS (
    IID              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    VINVLISTNO       VARCHAR(50)    NOT NULL COMMENT 'INV_LIST_NO book',
    DTAKINGDATE      DATE           NOT NULL,
    DCLOSEDATE       DATE           NOT NULL,
    VEVENTTYPE       VARCHAR(10)    NULL     COMMENT 'PRE / TWH / DIRECT (FACDBT_MSHEVENTS)',
    IMONTH           SMALLINT       NOT NULL,
    IYEAR            SMALLINT       NOT NULL,
    DTAKINGTIME      DATETIME       NULL     COMMENT 'Scan taking terakhir (tanpa taking: CREATED_DATETIME book)',
    VSITE            VARCHAR(50)    NULL,
    VWAREHOUSE       VARCHAR(100)   NULL,
    VLOCGROUP        VARCHAR(100)   NULL     COMMENT 'FACDBT_MSHLOCATIONS.VLOCGROUP',
    VLOCATIONNO      VARCHAR(100)   NOT NULL,
    VAREA            VARCHAR(100)   NULL     COMMENT 'FACDBT_MSHGROUPLOCATIONS.VLOCGROUP',
    VPRODFAMILY      VARCHAR(100)   NULL,
    VACCGROUP        VARCHAR(100)   NULL,
    VPARTNO          VARCHAR(100)   NOT NULL,
    VPARTNAME        VARCHAR(255)   NULL,
    VSEG             VARCHAR(100)   NULL,
    VPARTGROUP       VARCHAR(100)   NULL,
    VUOM             VARCHAR(20)    NULL,
    VLOTBATCHNO      VARCHAR(100)   NULL,
    NQTYBOOK         DECIMAL(18,4)  NOT NULL DEFAULT 0,
    NQTYTAKING       DECIMAL(18,4)  NOT NULL DEFAULT 0,
    NQTYVARIANCE     DECIMAL(18,4)  NOT NULL DEFAULT 0 COMMENT 'NQTYTAKING - NQTYBOOK',
    MMATCOST         DECIMAL(18,4)  NOT NULL DEFAULT 0,
    MLABCOST         DECIMAL(18,4)  NOT NULL DEFAULT 0,
    MFOHCOST         DECIMAL(18,4)  NOT NULL DEFAULT 0,
    MAMOUNTBOOK      DECIMAL(20,2)  NOT NULL DEFAULT 0,
    MAMOUNTTAKING    DECIMAL(20,2)  NOT NULL DEFAULT 0,
    MAMOUNTVARIANCE  DECIMAL(20,2)  NOT NULL DEFAULT 0 COMMENT 'MAMOUNTTAKING - MAMOUNTBOOK',
    BITEMBOOK        VARCHAR(1)     NOT NULL DEFAULT '0' COMMENT '1 = baris ada di book',
    BITEMTAKING      VARCHAR(1)     NOT NULL DEFAULT '0' COMMENT '1 = baris ada di taking',
    VREMARKPARTLOC   VARCHAR(10)    NULL     COMMENT 'Match / Var(-) / Var(+) part di lokasi',
    VREMARKPARTNO    VARCHAR(10)    NULL     COMMENT 'Match / Var(-) / Var(+) part di semua lokasi',
    VCREA            VARCHAR(100)   NOT NULL,
    DCREA            DATETIME       NOT NULL,
    VMODI            VARCHAR(100)   NULL,
    DMODI            DATETIME       NULL,
    VDELETE          VARCHAR(100)   NULL,
    DDELETE          DATETIME       NULL,
    PRIMARY KEY (IID),
    KEY idx_histakingdatas_period (IYEAR, IMONTH, DTAKINGDATE),
    KEY idx_histakingdatas_partno (VPARTNO),
    KEY idx_histakingdatas_locno (VLOCATIONNO)
);

INSERT INTO FACDBT_HISTAKINGDATAS (
    IID, VINVLISTNO, DTAKINGDATE, DCLOSEDATE, VEVENTTYPE, IMONTH, IYEAR, DTAKINGTIME,
    VSITE, VWAREHOUSE, VLOCGROUP, VLOCATIONNO, VAREA,
    VPRODFAMILY, VACCGROUP, VPARTNO, VPARTNAME, VSEG, VPARTGROUP, VUOM, VLOTBATCHNO,
    NQTYBOOK, NQTYTAKING, NQTYVARIANCE, MMATCOST, MLABCOST, MFOHCOST,
    MAMOUNTBOOK, MAMOUNTTAKING, MAMOUNTVARIANCE, BITEMBOOK, BITEMTAKING,
    VREMARKPARTLOC, VREMARKPARTNO, VCREA, DCREA
)
SELECT
    IID, INV_LIST_NO, TAKING_DATE, CLOSE_DATE, EVENT_TYPE, MONTH, YEAR, `time`,
    site, warehouse, location_group, location_no, area,
    product_family, accounting_group, part_no, part_name, seg, part_group, uom, LOT_BATCH_NO,
    qty_book, qty_taking, qty_variance, mat_cost, lab_cost, foh_cost,
    amount_book, amount_taking, amount_variance,
    IF(item_book > 0, '1', '0'), IF(item_taking > 0, '1', '0'),
    remark_part_location, remark_part_no, COALESCE(NULLIF(CREATED_BY, ''), 'MIGRASI'), COALESCE(CREATED_DATETIME, NOW())
FROM SIM_INV_TAKING_DATA_HISTORY;

-- ---------------------------------------------------------------------
-- 8. Tabel lama disimpan sebagai cadangan
-- ---------------------------------------------------------------------
RENAME TABLE
    SIM_INV_LOCATION_TEST       TO SIM_INV_LOCATION_TEST_OLD,
    SIM_INV_PART_TEST           TO SIM_INV_PART_TEST_OLD,
    SIM_INV_UNIT_COST_TEST      TO SIM_INV_UNIT_COST_TEST_OLD,
    SIM_INV_GROUP_LOCATION      TO SIM_INV_GROUP_LOCATION_OLD,
    SIM_INV_TAKING_EVENT        TO SIM_INV_TAKING_EVENT_OLD,
    SIM_INV_TAKING_EVENT_PART   TO SIM_INV_TAKING_EVENT_PART_OLD,
    SIM_INV_TAKING_DATA_HISTORY TO SIM_INV_TAKING_DATA_HISTORY_OLD;

-- ---------------------------------------------------------------------
-- Pengecekan: jumlah baris tabel baru harus sama dengan tabel lama
-- ---------------------------------------------------------------------
-- SELECT 'LOCATIONS', (SELECT COUNT(*) FROM FACDBT_MSHLOCATIONS), (SELECT COUNT(*) FROM SIM_INV_LOCATION_TEST_OLD)
-- UNION ALL SELECT 'PARTS', (SELECT COUNT(*) FROM FACDBT_MSHPARTS), (SELECT COUNT(*) FROM SIM_INV_PART_TEST_OLD)
-- UNION ALL SELECT 'UNITCOSTS', (SELECT COUNT(*) FROM FACDBT_MSHUNITCOSTS), (SELECT COUNT(*) FROM SIM_INV_UNIT_COST_TEST_OLD)
-- UNION ALL SELECT 'GROUPLOCATIONS', (SELECT COUNT(*) FROM FACDBT_MSHGROUPLOCATIONS), (SELECT COUNT(*) FROM SIM_INV_GROUP_LOCATION_OLD)
-- UNION ALL SELECT 'EVENTS', (SELECT COUNT(*) FROM FACDBT_MSHEVENTS), (SELECT COUNT(*) FROM SIM_INV_TAKING_EVENT_OLD)
-- UNION ALL SELECT 'EVENTPARTS', (SELECT COUNT(*) FROM FACDBT_MSDEVENTPARTS), (SELECT COUNT(*) FROM SIM_INV_TAKING_EVENT_PART_OLD)
-- UNION ALL SELECT 'HISTORY', (SELECT COUNT(*) FROM FACDBT_HISTAKINGDATAS), (SELECT COUNT(*) FROM SIM_INV_TAKING_DATA_HISTORY_OLD);

-- ---------------------------------------------------------------------
-- Rollback (sebelum tabel *_OLD dihapus; deploy ulang kode versi sebelumnya):
-- ---------------------------------------------------------------------
-- DROP TABLE FACDBT_MSHLOCATIONS, FACDBT_MSHPARTS, FACDBT_MSHUNITCOSTS, FACDBT_MSHGROUPLOCATIONS,
--            FACDBT_MSHEVENTS, FACDBT_MSDEVENTPARTS, FACDBT_HISTAKINGDATAS;
-- RENAME TABLE
--     SIM_INV_LOCATION_TEST_OLD       TO SIM_INV_LOCATION_TEST,
--     SIM_INV_PART_TEST_OLD           TO SIM_INV_PART_TEST,
--     SIM_INV_UNIT_COST_TEST_OLD      TO SIM_INV_UNIT_COST_TEST,
--     SIM_INV_GROUP_LOCATION_OLD      TO SIM_INV_GROUP_LOCATION,
--     SIM_INV_TAKING_EVENT_OLD        TO SIM_INV_TAKING_EVENT,
--     SIM_INV_TAKING_EVENT_PART_OLD   TO SIM_INV_TAKING_EVENT_PART,
--     SIM_INV_TAKING_DATA_HISTORY_OLD TO SIM_INV_TAKING_DATA_HISTORY;

-- ---------------------------------------------------------------------
-- Hapus cadangan (setelah aplikasi dicek berjalan normal):
-- ---------------------------------------------------------------------
-- DROP TABLE SIM_INV_LOCATION_TEST_OLD, SIM_INV_PART_TEST_OLD, SIM_INV_UNIT_COST_TEST_OLD, SIM_INV_GROUP_LOCATION_OLD,
--            SIM_INV_TAKING_EVENT_OLD, SIM_INV_TAKING_EVENT_PART_OLD, SIM_INV_TAKING_DATA_HISTORY_OLD;
