-- =====================================================================
-- Daftar part Pre-Taking per lokasi (Master Event, FACDBTF006)
-- Daftar part event = pasangan Part No + Location No (keduanya wajib). Part yang sama di lokasi lain
-- tidak ikut dihitung dashboard / history.
-- Jalankan di database dbportal SEBELUM deploy kode. Kosongkan dulu daftar part event lama
-- (baris lama tidak punya lokasi dan tidak dipakai lagi).
-- =====================================================================

-- Kosongkan daftar part lama (opsional bila sudah dikosongkan)
-- DELETE FROM FACDBT_MSDEVENTPARTS;

ALTER TABLE FACDBT_MSDEVENTPARTS
    ADD COLUMN VLOCATIONNO VARCHAR(50) NOT NULL COMMENT 'Location No' AFTER VPARTNO;

-- Kunci unik lama (event, part) diganti (event, part, lokasi): satu part boleh di beberapa lokasi
ALTER TABLE FACDBT_MSDEVENTPARTS
    DROP INDEX uq_msdeventparts_event_part,
    ADD UNIQUE KEY uq_msdeventparts_event_part_loc (IEVENTID, VPARTNO, VLOCATIONNO);

-- Cek
-- SHOW CREATE TABLE FACDBT_MSDEVENTPARTS;
