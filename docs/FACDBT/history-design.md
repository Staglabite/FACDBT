# Rancangan History Taking (belum dikerjakan)

## Tabel history

- `SIM_INV_UNIT_COST_TEST` tetap menjadi master cost terkini (satu baris per part), tanpa kolom periode.
- Tabel baru (contoh `SIM_INV_TAKING_HISTORY`) diisi sekali saat periode taking ditutup, sebagai foto hasil akhir:
  - Periode: MONTH, YEAR, INV_LIST_NO, TAKING_DATE, CLOSE_DATE
  - Barang: LOCATION_NO, PART_NO, location_group, accounting_group, part_group saat itu
  - Hasil: QTY_BOOK, QTY_TAKING, VAR_QTY
  - Cost: UNIT_COST yang dipakai saat itu, VAR_AMOUNT
  - Audit: CREATED_DATETIME, CREATED_BY
- Opsional: `SIM_INV_UNIT_COST_LOG` untuk riwayat perubahan cost (part_no, cost lama/baru, tanggal berlaku).

## Filter periode: Year -> Month -> Event

- Event = satu baris `SIM_INV_TAKING_CONCLUDING_TAB` (kunci MONTH, YEAR, TAKING_DATE).
- Month hanya berisi bulan yang punya event pada Year terpilih.
- Bila bulan itu punya satu event, event langsung terpilih dan pilihan Event disembunyikan.
- Bila lebih dari satu, tampilkan pilihan Event berlabel urutan + tanggal, contoh "Taking 1 · 6–7 Mar";
  default event terakhir di bulan itu.
- Nilai yang dikirim ke server adalah TAKING_DATE event, bukan bulan.
- Opsional: kolom `EVENT_TYPE` (PRE_TAKING / TAKING_TWH / TAKING_DIRECT) untuk label nama event.

## Mengaitkan data ke event

- `SIM_INV_TAKING_DATA_TAB.TAKING_DATE` berisi tanggal scan, bukan tanggal event, sehingga data taking
  dikaitkan lewat rentang TAKING_DATE s/d CLOSE_DATE event (CREATED_DATETIME / tanggal scan di rentang itu).
- Book dikaitkan lewat CREATED_DATETIME di rentang yang sama + INV_LIST_NO terbaru di rentang itu.
- Syarat: rentang tanggal dua event dalam bulan yang sama tidak boleh tumpang tindih.
