-- Master Event taking (FACDBTF006).
-- Jalankan sekali di database INV_TAKING.
--
-- SIM_INV_TAKING_EVENT      : jenis event untuk satu periode taking (TAKING_DATE di SIM_INV_TAKING_CONCLUDING_TAB).
--   EVENT_TYPE PRE    = Pre-Taking            -> dashboard hanya menghitung part di SIM_INV_TAKING_EVENT_PART
--              TWH    = Taking TWH / Indirect -> hanya lokasi yang LOCATION_NO-nya mengandung "TWH"
--              DIRECT = Taking Besar / Direct -> semua lokasi kecuali yang mengandung "TWH"
--   Periode tanpa event: dashboard menghitung semua data.
-- SIM_INV_TAKING_EVENT_PART : daftar part yang di-taking pada event Pre-Taking (di-import dari menu Master Event).

CREATE TABLE IF NOT EXISTS SIM_INV_TAKING_EVENT (
    IID               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    TAKING_DATE       DATE          NOT NULL COMMENT 'Periode: SIM_INV_TAKING_CONCLUDING_TAB.TAKING_DATE',
    EVENT_TYPE        VARCHAR(10)   NOT NULL COMMENT 'PRE / TWH / DIRECT',
    DESCRIPTION       VARCHAR(255)  NULL,
    CREATED_BY        VARCHAR(100)  NULL,
    CREATED_DATETIME  DATETIME      NULL,
    MODIFIED_BY       VARCHAR(100)  NULL,
    MODIFIED_DATETIME DATETIME      NULL,
    PRIMARY KEY (IID),
    UNIQUE KEY uq_taking_event_date (TAKING_DATE)
);

CREATE TABLE IF NOT EXISTS SIM_INV_TAKING_EVENT_PART (
    IID               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    EVENT_ID          INT UNSIGNED  NOT NULL COMMENT 'SIM_INV_TAKING_EVENT.IID',
    PART_NO           VARCHAR(100)  NOT NULL,
    CREATED_BY        VARCHAR(100)  NULL,
    CREATED_DATETIME  DATETIME      NULL,
    PRIMARY KEY (IID),
    UNIQUE KEY uq_taking_event_part (EVENT_ID, PART_NO),
    KEY idx_taking_event_part_no (PART_NO)
);

-- Kolom event di History Taking (FACDBTD005): jenis event periode saat history disimpan
ALTER TABLE SIM_INV_TAKING_DATA_HISTORY
    ADD COLUMN EVENT_TYPE VARCHAR(10) NULL COMMENT 'PRE / TWH / DIRECT (SIM_INV_TAKING_EVENT)' AFTER CLOSE_DATE;
