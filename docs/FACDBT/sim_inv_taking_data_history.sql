-- History hasil taking (FACDBTD006).
-- Satu baris = satu (location_no, part_no, LOT_BATCH_NO) dalam satu periode taking.
-- Diisi sekali saat periode di SIM_INV_TAKING_CONCLUDING_TAB ditutup (php artisan facdbt:taking-history).
-- Nilai master (site, area, part, cost) disalin saat itu supaya history tidak berubah bila master diubah.

CREATE TABLE IF NOT EXISTS SIM_INV_TAKING_DATA_HISTORY (
    IID                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Periode taking (SIM_INV_TAKING_CONCLUDING_TAB + book)
    INV_LIST_NO           VARCHAR(50)    NOT NULL,
    TAKING_DATE           DATE           NOT NULL,
    CLOSE_DATE            DATE           NOT NULL,
    MONTH                 SMALLINT       NOT NULL,
    YEAR                  SMALLINT       NOT NULL,

    `time`                DATETIME       NULL COMMENT 'CREATED_DATETIME scan taking terakhir (bila tidak ada taking: CREATED_DATETIME book)',

    -- Lokasi
    site                  VARCHAR(50)    NULL COMMENT 'SIM_INV_LOCATION_TEST.site',
    warehouse             VARCHAR(50)    NULL COMMENT 'SIM_INV_LOCATION_TEST.warehouse, fallback WAREHOUSE book/taking',
    location_group        VARCHAR(100)   NULL COMMENT 'SIM_INV_LOCATION_TEST.location_group',
    location_no           VARCHAR(50)    NOT NULL,
    area                  VARCHAR(100)   NULL COMMENT 'SIM_INV_GROUP_LOCATION.location_group',

    -- Part
    product_family        VARCHAR(100)   NULL COMMENT 'SIM_INV_PART_TEST.prod_group',
    accounting_group      VARCHAR(50)    NULL COMMENT 'SIM_INV_PART_TEST.accounting_group',
    part_no               VARCHAR(100)   NOT NULL,
    part_name             VARCHAR(255)   NULL COMMENT 'SIM_INV_PART_TEST.inventory_part_description',
    seg                   VARCHAR(50)    NULL COMMENT 'SIM_INV_PART_TEST.seg',
    part_group            VARCHAR(50)    NULL COMMENT 'SIM_INV_PART_TEST.part_group',
    uom                   VARCHAR(20)    NULL COMMENT 'UNIT_MEAS book/taking',
    LOT_BATCH_NO          VARCHAR(100)   NULL,

    -- Qty
    qty_book              DECIMAL(18,4)  NOT NULL DEFAULT 0,
    qty_taking            DECIMAL(18,4)  NOT NULL DEFAULT 0,
    qty_variance          DECIMAL(18,4)  NOT NULL DEFAULT 0 COMMENT 'qty_taking - qty_book',

    -- Cost & amount (unit cost = mat_cost + lab_cost + foh_cost)
    mat_cost              DECIMAL(18,4)  NOT NULL DEFAULT 0 COMMENT 'SIM_INV_UNIT_COST_TEST.mat_cost',
    lab_cost              DECIMAL(18,4)  NOT NULL DEFAULT 0 COMMENT 'SIM_INV_UNIT_COST_TEST.lab_cost',
    foh_cost              DECIMAL(18,4)  NOT NULL DEFAULT 0 COMMENT 'SIM_INV_UNIT_COST_TEST.foh_cost',
    amount_book           DECIMAL(20,2)  NOT NULL DEFAULT 0,
    amount_taking         DECIMAL(20,2)  NOT NULL DEFAULT 0,
    amount_variance       DECIMAL(20,2)  NOT NULL DEFAULT 0 COMMENT 'amount_taking - amount_book',

    -- Item (1 bila baris ada di book / taking; dijumlahkan = jumlah item)
    item_book             TINYINT        NOT NULL DEFAULT 0,
    item_taking           TINYINT        NOT NULL DEFAULT 0,

    -- Match / Var(-) / Var(+) dari qty variance
    remark_part_location  VARCHAR(10)    NULL COMMENT 'variance part_no di location_no tersebut',
    remark_part_no        VARCHAR(10)    NULL COMMENT 'variance part_no di semua lokasi',

    CREATED_DATETIME      DATETIME       NOT NULL,
    CREATED_BY            VARCHAR(50)    NULL,

    PRIMARY KEY (IID),
    KEY idx_history_period (YEAR, MONTH, TAKING_DATE),
    KEY idx_history_part (part_no),
    KEY idx_history_location (location_no)
);
