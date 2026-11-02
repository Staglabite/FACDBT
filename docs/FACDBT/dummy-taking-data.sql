-- =====================================================================================
-- DATA DUMMY untuk mengetes dashboard FACDBT (menu Dashboard Taking FACDBT04)
-- =====================================================================================
-- PERHATIAN
-- - JANGAN dijalankan di database utama. SIM_INV_TAKING_CONCLUDING_TAB juga dibaca sistem taking/scanner,
--   sehingga periode dummy (ROWSTATE Started) bisa membuat scan asli masuk ke periode yang salah.
-- - Jalankan di database TEST (koneksi INV_TAKING). Periode dummy memakai tanggal HARI INI,
--   sehingga menjadi periode aktif dan dashboard tidak lagi menampilkan periode asli.
-- - Semua data dummy berawalan "DMY-" dan CREATED_BY = 'DUMMY'.
--   Hapus dengan docs/FACDBT/dummy-taking-cleanup.sql.
-- - Bila muncul error "Field ... doesn't have a default value", tambahkan kolom tersebut
--   ke INSERT yang bersangkutan (struktur tabel di server bisa berbeda).
-- - Dashboard memakai cache: tunggu +/- 1-5 menit atau refresh halaman beberapa kali.
--
-- SKENARIO (H1 = hari ini, H2 = besok)
--   Lokasi        Area (group)  Warehouse  Part   Book   Taking (jam scan)              Hasil
--   DMY-ENG-01    DMY-ENG       DMY-WH-A   P01    100    100 (H1 08:00)                 Match
--   DMY-ENG-01    DMY-ENG       DMY-WH-A   P02     50     45 (H1 10:30)                 Var(-) 5
--   DMY-ENG-01    DMY-ENG       DMY-WH-A   P03     30      -  -> di-scan di DMY-PRD-01   Var(-) 30
--   DMY-ENG-02    DMY-ENG       DMY-WH-A   P04     40     20 (H1 09:15) + 20 (H2 09:30)  Match
--   DMY-ENG-02    DMY-ENG       DMY-WH-A   P05     20      -                             Var(-) 20
--   DMY-PRD-01    DMY-PROD      DMY-WH-B   P06     80     85 (H1 11:00)                 Var(+) 5
--   DMY-PRD-01    DMY-PROD      DMY-WH-B   P07     60     60 (H1 13:30)                 Match
--   DMY-PRD-01    DMY-PROD      DMY-WH-B   P03      -     30 (H1 14:00)  salah lokasi    Var(+) 30
--   DMY-PRD-01    DMY-PROD      DMY-WH-B   OIL1   500    480 (H1 16:30)  oil (ml)       Var(-) 20
--   DMY-PRD-02    DMY-PROD      DMY-WH-B   P08     25     25 (H2 10:15)                 Match
--   DMY-PRD-02    DMY-PROD      DMY-WH-B   P09     10     12 (H1 20:00)                 Var(+) 2
--   DMY-WH-01     DMY-WH        DMY-WH-C   P10    200    200 (H1 08:30)                 Match
--   DMY-WH-01     DMY-WH        DMY-WH-C   P01     50     50 (H1 15:00)                 Match
--   DMY-PPC-01    DMY-WH        DMY-WH-C   OIL2   300    300 (H2 09:45)  oil di PPC     Match
--
-- Unit cost (mat + lab + foh): DMY-Pnn = nn x 1.000.000, OIL1 = 50.000/ml, OIL2 = 40.000/ml
-- Accounting group: P01,P04,P07,P10,OIL1,OIL2 = RM | P02,P05,P08 = WIP | P03,P06,P09 = FG
-- Part group: nomor ganjil & oil = 4W | nomor genap = 2W
--
-- HASIL YANG DIHARAPKAN (tanpa filter waktu)
-- Main Dashboard - Counting Progress (by Part)  [% qty]
--   DMY-ENG 77.08% (185/240, item 3/5) | DMY-PROD 104% -> bar 100% (182/175, item 4/4)
--   DMY-WH 100% | OIL DMY-PROD 96% (480/500) | PPC-OIL 100%
--   (P03 yang di-scan di DMY-PRD-01 TIDAK menambah progress DMY-ENG)
-- Variance (Main, Qty & Amount, Part No per Location No)
--   Nett     = -63M      Absolute = 339M
--   2W: nett +20M, abs 40M | 4W: nett -83M, abs 299M
--   RM: nett -1M, abs 1M   | WIP: nett -110M, abs 110M | FG: nett +48M, abs 228M
-- Progress Dashboard (per warehouse, % item / % qty)
--   DMY-WH-A 60% / 77.08% | DMY-WH-B 120% / 102.52% (P03 ikut terhitung di lokasi scan) | DMY-WH-C 100% / 100%
-- Filter waktu Dari H1 00:00 Sampai H1 12:00: hanya scan P01@ENG-01, P02, P04 (20), P06, P10 yang dihitung.
-- =====================================================================================

SET @d1  = CURDATE();
SET @d2  = CURDATE() + INTERVAL 1 DAY;
SET @m   = MONTH(@d1);
SET @y   = YEAR(@d1);
SET @inv = (SELECT COALESCE(MAX(CAST(INV_LIST_NO AS UNSIGNED)), 0) + 1 FROM SIM_INV_TAKING_BOOK_TAB);

-- ---------------------------------------------------------------- Master Location
INSERT INTO FACDBT_MSHLOCATIONS (VLOCATIONNO, VLOCGROUP, VSITE, VWAREHOUSE, VLOCDESC, VLOCGROUPDESC, VCREA, DCREA) VALUES
('DMY-ENG-01', 'DMY-ENG',  'DMY', 'DMY-WH-A', 'Dummy Engineering 1', 'Dummy Engineering', 'DUMMY', NOW()),
('DMY-ENG-02', 'DMY-ENG',  'DMY', 'DMY-WH-A', 'Dummy Engineering 2', 'Dummy Engineering', 'DUMMY', NOW()),
('DMY-PRD-01', 'DMY-PROD', 'DMY', 'DMY-WH-B', 'Dummy Production 1',  'Dummy Production', 'DUMMY', NOW()),
('DMY-PRD-02', 'DMY-PROD', 'DMY', 'DMY-WH-B', 'Dummy Production 2',  'Dummy Production', 'DUMMY', NOW()),
('DMY-WH-01',  'DMY-WH',   'DMY', 'DMY-WH-C', 'Dummy Warehouse 1',   'Dummy Warehouse', 'DUMMY', NOW()),
('DMY-PPC-01', 'DMY-WH',   'DMY', 'DMY-WH-C', 'Dummy PPC Oil',       'Dummy Warehouse', 'DUMMY', NOW());

-- ---------------------------------------------------------------- Master Group Location (area = location_group)
INSERT INTO FACDBT_MSHGROUPLOCATIONS (VLOCATIONNO, VIDLOC, VLOCGROUP, VSTREAM, VCREA, DCREA) VALUES
('DMY-ENG-01', 'DMY-ENG',  'DMY-ENG',  'DMY-STREAM', 'DUMMY', NOW()),
('DMY-ENG-02', 'DMY-ENG',  'DMY-ENG',  'DMY-STREAM', 'DUMMY', NOW()),
('DMY-PRD-01', 'DMY-PROD', 'DMY-PROD', 'DMY-STREAM', 'DUMMY', NOW()),
('DMY-PRD-02', 'DMY-PROD', 'DMY-PROD', 'DMY-STREAM', 'DUMMY', NOW()),
('DMY-WH-01',  'DMY-WH',   'DMY-WH',   'DMY-STREAM', 'DUMMY', NOW()),
('DMY-PPC-01', 'DMY-WH',   'DMY-WH',   'DMY-STREAM', 'DUMMY', NOW());

-- ---------------------------------------------------------------- Master Part
INSERT INTO FACDBT_MSHPARTS (VPARTNO, VPARTDESC, VACCGROUP, VSEG, VPRODGROUP, VPARTGROUP, VCREA, DCREA) VALUES
('DMY-P01',  'Dummy Part 01', 'RM',  'DMY', 'DMY-FAM-A', '4W', 'DUMMY', NOW()),
('DMY-P02',  'Dummy Part 02', 'WIP', 'DMY', 'DMY-FAM-A', '2W', 'DUMMY', NOW()),
('DMY-P03',  'Dummy Part 03', 'FG',  'DMY', 'DMY-FAM-A', '4W', 'DUMMY', NOW()),
('DMY-P04',  'Dummy Part 04', 'RM',  'DMY', 'DMY-FAM-B', '2W', 'DUMMY', NOW()),
('DMY-P05',  'Dummy Part 05', 'WIP', 'DMY', 'DMY-FAM-B', '4W', 'DUMMY', NOW()),
('DMY-P06',  'Dummy Part 06', 'FG',  'DMY', 'DMY-FAM-B', '2W', 'DUMMY', NOW()),
('DMY-P07',  'Dummy Part 07', 'RM',  'DMY', 'DMY-FAM-C', '4W', 'DUMMY', NOW()),
('DMY-P08',  'Dummy Part 08', 'WIP', 'DMY', 'DMY-FAM-C', '2W', 'DUMMY', NOW()),
('DMY-P09',  'Dummy Part 09', 'FG',  'DMY', 'DMY-FAM-C', '4W', 'DUMMY', NOW()),
('DMY-P10',  'Dummy Part 10', 'RM',  'DMY', 'DMY-FAM-C', '2W', 'DUMMY', NOW()),
('DMY-OIL1', 'Dummy Oil 1',   'RM',  'DMY', 'DMY-OIL',   '4W', 'DUMMY', NOW()),
('DMY-OIL2', 'Dummy Oil 2',   'RM',  'DMY', 'DMY-OIL',   '4W', 'DUMMY', NOW());

-- ---------------------------------------------------------------- Master Unit Cost (unit cost = mat + lab + foh)
INSERT INTO FACDBT_MSHUNITCOSTS (VPARTNO, VPARTDESC, VUOM, VACCGROUP, NONHANDQTY, MMATCOST, MLABCOST, MFOHCOST, VCREA, DCREA) VALUES
('DMY-P01',  'Dummy Part 01', 'pcs', 'RM',  0,  600000,  250000,  150000, 'DUMMY', NOW()),
('DMY-P02',  'Dummy Part 02', 'pcs', 'WIP', 0, 1200000,  500000,  300000, 'DUMMY', NOW()),
('DMY-P03',  'Dummy Part 03', 'pcs', 'FG',  0, 1800000,  750000,  450000, 'DUMMY', NOW()),
('DMY-P04',  'Dummy Part 04', 'pcs', 'RM',  0, 2400000, 1000000,  600000, 'DUMMY', NOW()),
('DMY-P05',  'Dummy Part 05', 'pcs', 'WIP', 0, 3000000, 1250000,  750000, 'DUMMY', NOW()),
('DMY-P06',  'Dummy Part 06', 'pcs', 'FG',  0, 3600000, 1500000,  900000, 'DUMMY', NOW()),
('DMY-P07',  'Dummy Part 07', 'pcs', 'RM',  0, 4200000, 1750000, 1050000, 'DUMMY', NOW()),
('DMY-P08',  'Dummy Part 08', 'pcs', 'WIP', 0, 4800000, 2000000, 1200000, 'DUMMY', NOW()),
('DMY-P09',  'Dummy Part 09', 'pcs', 'FG',  0, 5400000, 2250000, 1350000, 'DUMMY', NOW()),
('DMY-P10',  'Dummy Part 10', 'pcs', 'RM',  0, 6000000, 2500000, 1500000, 'DUMMY', NOW()),
('DMY-OIL1', 'Dummy Oil 1',   'ml',  'RM',  0,   30000,   12500,    7500, 'DUMMY', NOW()),
('DMY-OIL2', 'Dummy Oil 2',   'ml',  'RM',  0,   24000,   10000,    6000, 'DUMMY', NOW());

-- ---------------------------------------------------------------- Periode taking (aktif)
INSERT INTO SIM_INV_TAKING_CONCLUDING_TAB (MONTH, YEAR, TAKING_DATE, CLOSE_DATE, ROWSTATE, NPK, NAME, CREATED_DATETIME, CREATED_BY) VALUES
(@m, @y, @d1, @d2, 'Started', 'DUMMY', 'Dummy Taking', NOW(), 'DUMMY');

-- ---------------------------------------------------------------- Book (dibuat H1 00:05, di dalam periode)
INSERT INTO SIM_INV_TAKING_BOOK_TAB
    (INV_LIST_NO, SEQ, MONTH, YEAR, CONTRACT, PART_NO, LOCATION_NO, LOT_BATCH_NO, QUANTITY, UNIT_COST, UNIT_MEAS, WAREHOUSE, CREATED_DATETIME, CREATED_BY)
VALUES
(@inv,  1, @m, @y, 'DMY', 'DMY-P01',  'DMY-ENG-01', '*', 100, 0, 'pcs', 'DMY-WH-A', TIMESTAMP(@d1, '00:05:00'), 'DUMMY'),
(@inv,  2, @m, @y, 'DMY', 'DMY-P02',  'DMY-ENG-01', '*',  50, 0, 'pcs', 'DMY-WH-A', TIMESTAMP(@d1, '00:05:00'), 'DUMMY'),
(@inv,  3, @m, @y, 'DMY', 'DMY-P03',  'DMY-ENG-01', '*',  30, 0, 'pcs', 'DMY-WH-A', TIMESTAMP(@d1, '00:05:00'), 'DUMMY'),
(@inv,  4, @m, @y, 'DMY', 'DMY-P04',  'DMY-ENG-02', '*',  40, 0, 'pcs', 'DMY-WH-A', TIMESTAMP(@d1, '00:05:00'), 'DUMMY'),
(@inv,  5, @m, @y, 'DMY', 'DMY-P05',  'DMY-ENG-02', '*',  20, 0, 'pcs', 'DMY-WH-A', TIMESTAMP(@d1, '00:05:00'), 'DUMMY'),
(@inv,  6, @m, @y, 'DMY', 'DMY-P06',  'DMY-PRD-01', '*',  80, 0, 'pcs', 'DMY-WH-B', TIMESTAMP(@d1, '00:05:00'), 'DUMMY'),
(@inv,  7, @m, @y, 'DMY', 'DMY-P07',  'DMY-PRD-01', '*',  60, 0, 'pcs', 'DMY-WH-B', TIMESTAMP(@d1, '00:05:00'), 'DUMMY'),
(@inv,  8, @m, @y, 'DMY', 'DMY-OIL1', 'DMY-PRD-01', '*', 500, 0, 'ml',  'DMY-WH-B', TIMESTAMP(@d1, '00:05:00'), 'DUMMY'),
(@inv,  9, @m, @y, 'DMY', 'DMY-P08',  'DMY-PRD-02', '*',  25, 0, 'pcs', 'DMY-WH-B', TIMESTAMP(@d1, '00:05:00'), 'DUMMY'),
(@inv, 10, @m, @y, 'DMY', 'DMY-P09',  'DMY-PRD-02', '*',  10, 0, 'pcs', 'DMY-WH-B', TIMESTAMP(@d1, '00:05:00'), 'DUMMY'),
(@inv, 11, @m, @y, 'DMY', 'DMY-P10',  'DMY-WH-01',  '*', 200, 0, 'pcs', 'DMY-WH-C', TIMESTAMP(@d1, '00:05:00'), 'DUMMY'),
(@inv, 12, @m, @y, 'DMY', 'DMY-P01',  'DMY-WH-01',  '*',  50, 0, 'pcs', 'DMY-WH-C', TIMESTAMP(@d1, '00:05:00'), 'DUMMY'),
(@inv, 13, @m, @y, 'DMY', 'DMY-OIL2', 'DMY-PPC-01', '*', 300, 0, 'ml',  'DMY-WH-C', TIMESTAMP(@d1, '00:05:00'), 'DUMMY');

-- ---------------------------------------------------------------- Data taking (scan)
INSERT INTO SIM_INV_TAKING_DATA_TAB
    (MONTH, YEAR, ID_TAKING, REV_NO, CONTRACT, LOCATION_NO, LOCATION_NAME, WAREHOUSE, LOCATION_GROUP, PART_NO, DESCRIPTION,
     QTY, LOT_BATCH_NO, UNIT_MEAS, TAKING_DATE, ACTIVE_STATE, CREATED_BY, CREATED_DATETIME)
VALUES
(@m, @y, 990001, 0, 'DMY', 'DMY-ENG-01', 'Dummy Engineering 1', 'DMY-WH-A', 'DMY-ENG',  'DMY-P01',  'Dummy Part 01', 100, '*', 'pcs', TIMESTAMP(@d1, '08:00:00'), 'Active', 'DUMMY', TIMESTAMP(@d1, '08:00:00')),
(@m, @y, 990002, 0, 'DMY', 'DMY-ENG-01', 'Dummy Engineering 1', 'DMY-WH-A', 'DMY-ENG',  'DMY-P02',  'Dummy Part 02',  45, '*', 'pcs', TIMESTAMP(@d1, '10:30:00'), 'Active', 'DUMMY', TIMESTAMP(@d1, '10:30:00')),
(@m, @y, 990003, 0, 'DMY', 'DMY-ENG-02', 'Dummy Engineering 2', 'DMY-WH-A', 'DMY-ENG',  'DMY-P04',  'Dummy Part 04',  20, '*', 'pcs', TIMESTAMP(@d1, '09:15:00'), 'Active', 'DUMMY', TIMESTAMP(@d1, '09:15:00')),
(@m, @y, 990004, 0, 'DMY', 'DMY-ENG-02', 'Dummy Engineering 2', 'DMY-WH-A', 'DMY-ENG',  'DMY-P04',  'Dummy Part 04',  20, '*', 'pcs', TIMESTAMP(@d2, '09:30:00'), 'Active', 'DUMMY', TIMESTAMP(@d2, '09:30:00')),
(@m, @y, 990005, 0, 'DMY', 'DMY-PRD-01', 'Dummy Production 1',  'DMY-WH-B', 'DMY-PROD', 'DMY-P06',  'Dummy Part 06',  85, '*', 'pcs', TIMESTAMP(@d1, '11:00:00'), 'Active', 'DUMMY', TIMESTAMP(@d1, '11:00:00')),
(@m, @y, 990006, 0, 'DMY', 'DMY-PRD-01', 'Dummy Production 1',  'DMY-WH-B', 'DMY-PROD', 'DMY-P07',  'Dummy Part 07',  60, '*', 'pcs', TIMESTAMP(@d1, '13:30:00'), 'Active', 'DUMMY', TIMESTAMP(@d1, '13:30:00')),
(@m, @y, 990007, 0, 'DMY', 'DMY-PRD-01', 'Dummy Production 1',  'DMY-WH-B', 'DMY-PROD', 'DMY-P03',  'Dummy Part 03',  30, '*', 'pcs', TIMESTAMP(@d1, '14:00:00'), 'Active', 'DUMMY', TIMESTAMP(@d1, '14:00:00')),
(@m, @y, 990008, 0, 'DMY', 'DMY-PRD-01', 'Dummy Production 1',  'DMY-WH-B', 'DMY-PROD', 'DMY-OIL1', 'Dummy Oil 1',   480, '*', 'ml',  TIMESTAMP(@d1, '16:30:00'), 'Active', 'DUMMY', TIMESTAMP(@d1, '16:30:00')),
(@m, @y, 990009, 0, 'DMY', 'DMY-PRD-02', 'Dummy Production 2',  'DMY-WH-B', 'DMY-PROD', 'DMY-P08',  'Dummy Part 08',  25, '*', 'pcs', TIMESTAMP(@d2, '10:15:00'), 'Active', 'DUMMY', TIMESTAMP(@d2, '10:15:00')),
(@m, @y, 990010, 0, 'DMY', 'DMY-PRD-02', 'Dummy Production 2',  'DMY-WH-B', 'DMY-PROD', 'DMY-P09',  'Dummy Part 09',  12, '*', 'pcs', TIMESTAMP(@d1, '20:00:00'), 'Active', 'DUMMY', TIMESTAMP(@d1, '20:00:00')),
(@m, @y, 990011, 0, 'DMY', 'DMY-WH-01',  'Dummy Warehouse 1',   'DMY-WH-C', 'DMY-WH',   'DMY-P10',  'Dummy Part 10', 200, '*', 'pcs', TIMESTAMP(@d1, '08:30:00'), 'Active', 'DUMMY', TIMESTAMP(@d1, '08:30:00')),
(@m, @y, 990012, 0, 'DMY', 'DMY-WH-01',  'Dummy Warehouse 1',   'DMY-WH-C', 'DMY-WH',   'DMY-P01',  'Dummy Part 01',  50, '*', 'pcs', TIMESTAMP(@d1, '15:00:00'), 'Active', 'DUMMY', TIMESTAMP(@d1, '15:00:00')),
(@m, @y, 990013, 0, 'DMY', 'DMY-PPC-01', 'Dummy PPC Oil',       'DMY-WH-C', 'DMY-WH',   'DMY-OIL2', 'Dummy Oil 2',   300, '*', 'ml',  TIMESTAMP(@d2, '09:45:00'), 'Active', 'DUMMY', TIMESTAMP(@d2, '09:45:00'));

-- ---------------------------------------------------------------- (Opsional) tes History Taking
-- UPDATE SIM_INV_TAKING_CONCLUDING_TAB SET ROWSTATE = 'Closed' WHERE CREATED_BY = 'DUMMY';
-- lalu jalankan: php artisan facdbt:taking-history
-- History dummy: 14 baris (13 baris book + DMY-P03 di DMY-PRD-01); remark DMY-P03:
--   DMY-ENG-01 -> Remark Part Location Var(-), DMY-PRD-01 -> Var(+), Remark Part No keduanya Match
