<?php

namespace App\Services\FACDBT;

use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan hasil taking periode yang sudah ditutup ke FACDBT_HISTAKINGDATAS.
 *
 * Satu baris history = satu (location_no, part_no, LOT_BATCH_NO):
 * - book   : SIM_INV_TAKING_BOOK_TAB, INV_LIST_NO terbaru di rentang TAKING_DATE s/d CLOSE_DATE
 * - taking : SIM_INV_TAKING_DATA_TAB, CREATED_DATETIME di rentang yang sama (tanggal scan)
 * - master (lokasi, area, part, unit cost) disalin saat snapshot supaya history tidak ikut berubah.
 * - event periode (Master Event) membatasi book & taking seperti di dashboard; jenisnya disimpan di VEVENTTYPE.
 *   Pre-Taking: hanya part di daftar part event; setelah history periode tertutup tersimpan, daftar part
 *   event (FACDBT_MSDEVENTPARTS) dihapus (soft delete).
 * - history periode yang dibuat ulang menggantikan history lama (dihapus permanen, bukan soft delete).
 */
class TakingHistoryService
{
    private const CONN = 'INV_TAKING';
    private const TABLE = FacdbtTable::HISTORY;

    // ROWSTATE periode di SIM_INV_TAKING_CONCLUDING_TAB berubah dari Started menjadi Closed saat taking ditutup
    private const CLOSED_ROWSTATE = 'Closed';

    private function db(): Connection
    {
        return DB::connection(self::CONN);
    }

    /**
     * Periode yang sudah ditutup (ROWSTATE = Closed) tetapi belum ada di history.
     * CLOSE_DATE boleh kosong (lihat closeDay()).
     */
    public function pendingPeriods(): array
    {
        return $this->db()->table('SIM_INV_TAKING_CONCLUDING_TAB as c')
            ->where('c.ROWSTATE', self::CLOSED_ROWSTATE)
            ->whereNotNull('c.TAKING_DATE')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from(self::TABLE . ' as h')
                ->whereRaw('h.DTAKINGDATE = DATE(c.TAKING_DATE)')
                ->whereNull('h.DDELETE'))
            ->orderBy('c.TAKING_DATE')
            ->get(['c.MONTH', 'c.YEAR', 'c.TAKING_DATE', 'c.CLOSE_DATE', 'c.ROWSTATE'])
            ->all();
    }

    public function findPeriod(string $takingDate): ?object
    {
        return $this->db()->table('SIM_INV_TAKING_CONCLUDING_TAB')
            ->whereDate('TAKING_DATE', Carbon::parse($takingDate)->toDateString())
            ->first(['MONTH', 'YEAR', 'TAKING_DATE', 'CLOSE_DATE', 'ROWSTATE']);
    }

    /**
     * Simpan history satu periode. Data history periode yang sama (bila ada) diganti.
     *
     * @return int jumlah baris history
     */
    public function snapshot(object $period, ?string $createdBy = null): int
    {
        $db = $this->db();

        $takingDay  = Carbon::parse($period->TAKING_DATE)->startOfDay();
        $takingDate = $takingDay->format('Y-m-d H:i:s');
        $closeDay   = $this->closeDay($period);
        $closeDate  = $closeDay->copy()->endOfDay()->format('Y-m-d H:i:s');
        $period->CLOSE_DATE = $closeDay->toDateString();

        $invListNo = $db->table('SIM_INV_TAKING_BOOK_TAB')
            ->whereBetween('CREATED_DATETIME', [$takingDate, $closeDate])
            ->orderByRaw('CAST(INV_LIST_NO AS UNSIGNED) DESC')
            ->value('INV_LIST_NO');

        $event = TakingEvent::forPeriod($db, $period);
        $isPre = $event?->type === TakingEvent::PRE;
        $createdBy ??= FacdbtTable::user();
        if ($isPre) {
            $this->restoreEventParts($event, $takingDay, $createdBy);
        }

        $rows = $this->buildRows($period, $event, $takingDate, $closeDate, $invListNo, $createdBy);

        $closed = strcasecmp(trim((string) ($period->ROWSTATE ?? '')), self::CLOSED_ROWSTATE) === 0;

        $db->transaction(function () use ($db, $takingDay, $rows, $isPre, $closed, $event, $createdBy) {
            // History lama periode ini diganti (hapus permanen)
            $db->table(self::TABLE)->whereDate('DTAKINGDATE', $takingDay->toDateString())->delete();

            foreach (array_chunk($rows, 1000) as $chunk) {
                $db->table(self::TABLE)->insert($chunk);
            }

            // Pre-Taking selesai: daftar part event tidak dipakai lagi (part-nya sudah tercatat di history)
            if ($isPre && $closed) {
                FacdbtTable::active($db, TakingEvent::PART_TABLE)
                    ->where('IEVENTID', $event->id)
                    ->update(FacdbtTable::deleteValues($createdBy));
            }
        });

        return count($rows);
    }

    /**
     * History periode Pre-Taking dibuat ulang (command --taking-date) setelah daftar part event dihapus:
     * part di history periode itu dipulihkan (atau ditambahkan) di daftar part event supaya hasilnya tetap sama.
     */
    private function restoreEventParts(TakingEvent $event, Carbon $takingDay, string $user): void
    {
        $db = $this->db();
        if (FacdbtTable::active($db, TakingEvent::PART_TABLE)->where('IEVENTID', $event->id)->exists()) {
            return;
        }

        $key = fn ($part) => mb_strtolower(rtrim((string) $part));
        $parts = [];
        foreach (FacdbtTable::active($db, self::TABLE)->whereDate('DTAKINGDATE', $takingDay->toDateString())->distinct()->pluck('VPARTNO') as $part) {
            if (trim((string) $part) !== '') {
                $parts[$key($part)] ??= trim((string) $part);
            }
        }
        if (empty($parts)) {
            return;
        }

        $now = now()->format('Y-m-d H:i:s');

        // Baris part yang pernah dihapus dipulihkan; part yang belum pernah ada ditambahkan
        $deleted = [];
        foreach ($db->table(TakingEvent::PART_TABLE)->where('IEVENTID', $event->id)->get(['IID', 'VPARTNO']) as $row) {
            $deleted[$key($row->VPARTNO)] = $row->IID;
        }

        $restore = array_values(array_intersect_key($deleted, $parts));
        foreach (array_chunk($restore, 1000) as $ids) {
            $db->table(TakingEvent::PART_TABLE)->whereIn('IID', $ids)->update([
                FacdbtTable::DELETED_AT => null,
                FacdbtTable::DELETED_BY => null,
                'VMODI' => $user,
                'DMODI' => $now,
            ]);
        }

        $new = array_values(array_diff_key($parts, $deleted));
        foreach (array_chunk($new, 1000) as $chunk) {
            $db->table(TakingEvent::PART_TABLE)->insert(array_map(fn ($part) => [
                'IEVENTID' => $event->id,
                'VPARTNO' => $part,
                'VCREA' => $user,
                'DCREA' => $now,
            ], $chunk));
        }
    }

    /**
     * Hapus (soft delete) daftar part event Pre-Taking yang periodenya sudah ditutup dan sudah ada di history
     * (mis. history disimpan sebelum aturan ini ada).
     *
     * @return int jumlah baris part yang dihapus
     */
    public function clearFinishedEventParts(): int
    {
        $db = $this->db();
        if (!TakingEvent::installed($db)) {
            return 0;
        }

        $eventIds = FacdbtTable::active($db, TakingEvent::TABLE, 'e')
            ->where('e.VEVENTTYPE', TakingEvent::PRE)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('SIM_INV_TAKING_CONCLUDING_TAB as c')
                ->whereRaw('DATE(c.TAKING_DATE) = e.DTAKINGDATE')
                ->where('c.ROWSTATE', self::CLOSED_ROWSTATE))
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from(self::TABLE . ' as h')
                ->whereRaw('h.DTAKINGDATE = e.DTAKINGDATE')
                ->whereNull('h.DDELETE'))
            ->pluck('e.IID');

        return $eventIds->isEmpty() ? 0 : FacdbtTable::active($db, TakingEvent::PART_TABLE)
            ->whereIn('IEVENTID', $eventIds)
            ->update(FacdbtTable::deleteValues());
    }

    /**
     * Hari terakhir periode. CLOSE_DATE kosong: sehari sebelum periode berikutnya dimulai, atau hari ini bila
     * belum ada periode berikutnya (supaya data periode berikutnya tidak ikut masuk).
     */
    private function closeDay(object $period): Carbon
    {
        if (!empty($period->CLOSE_DATE)) {
            return Carbon::parse($period->CLOSE_DATE)->startOfDay();
        }

        $next = $this->db()->table('SIM_INV_TAKING_CONCLUDING_TAB')
            ->where('TAKING_DATE', '>', Carbon::parse($period->TAKING_DATE)->endOfDay()->format('Y-m-d H:i:s'))
            ->min('TAKING_DATE');

        return $next ? Carbon::parse($next)->subDay()->startOfDay() : now()->startOfDay();
    }

    /**
     * Simpan semua periode tertutup yang belum ada di history. Dipanggil saat halaman History Taking dibuka
     * (tidak perlu scheduler) dan oleh command facdbt:taking-history. Satu proses dalam satu waktu.
     *
     * @return int jumlah periode yang disimpan
     */
    public function snapshotPending(?string $createdBy = null): int
    {
        $periods = $this->pendingPeriods();

        return Cache::lock('FACDBTD005:snapshot', 600)->get(function () use ($periods, $createdBy) {
            if (!empty($periods)) {
                set_time_limit(600);
                ini_set('memory_limit', '1024M');
            }

            foreach ($periods as $period) {
                $this->snapshot($period, $createdBy);
            }

            $this->clearFinishedEventParts();

            return count($periods);
        }) ?: 0;
    }

    private function buildRows(object $period, ?TakingEvent $event, string $takingDate, string $closeDate, $invListNo, ?string $createdBy): array
    {
        $db = $this->db();

        // [lokasi|part|lot => data] dari book & taking, diagregasi di database tanpa join
        $lines = [];
        $key = fn ($r) => trim((string) $r->LOCATION_NO) . '|' . trim((string) $r->PART_NO) . '|' . trim((string) $r->LOT_BATCH_NO);
        $blank = fn ($r) => [
            'location_no' => trim((string) $r->LOCATION_NO),
            'part_no'     => trim((string) $r->PART_NO),
            'lot'         => trim((string) $r->LOT_BATCH_NO),
            'qty_book'    => 0.0,
            'qty_taking'  => 0.0,
            'item_book'   => 0,
            'item_taking' => 0,
            'uom'         => null,
            'warehouse'   => null,
            'time'        => null,
            'book_time'   => null,
        ];

        if ($invListNo !== null && $invListNo !== '') {
            $book = $db->table('SIM_INV_TAKING_BOOK_TAB')
                ->whereBetween('CREATED_DATETIME', [$takingDate, $closeDate])
                ->where('INV_LIST_NO', $invListNo)
                ->tap(fn ($q) => TakingEvent::scope($q, $event))
                ->groupBy('LOCATION_NO', 'PART_NO', 'LOT_BATCH_NO')
                ->select(
                    'LOCATION_NO', 'PART_NO', 'LOT_BATCH_NO',
                    DB::raw('SUM(QUANTITY) as qty'),
                    DB::raw('MAX(UNIT_MEAS) as uom'),
                    DB::raw('MAX(WAREHOUSE) as warehouse'),
                    DB::raw('MIN(CREATED_DATETIME) as created')
                )
                ->get();

            foreach ($book as $r) {
                $k = $key($r);
                $lines[$k] ??= $blank($r);
                $lines[$k]['qty_book'] += (float) $r->qty;
                $lines[$k]['item_book'] = 1;
                $lines[$k]['uom'] ??= $r->uom;
                $lines[$k]['warehouse'] ??= $r->warehouse;
                $lines[$k]['book_time'] = $r->created;
            }
        }

        $taking = $db->table('SIM_INV_TAKING_DATA_TAB')
            ->whereBetween('CREATED_DATETIME', [$takingDate, $closeDate])
            ->tap(fn ($q) => TakingEvent::scope($q, $event))
            ->groupBy('LOCATION_NO', 'PART_NO', 'LOT_BATCH_NO')
            ->select(
                'LOCATION_NO', 'PART_NO', 'LOT_BATCH_NO',
                DB::raw('SUM(QTY) as qty'),
                DB::raw('MAX(UNIT_MEAS) as uom'),
                DB::raw('MAX(WAREHOUSE) as warehouse'),
                DB::raw('MAX(CREATED_DATETIME) as created')
            )
            ->get();

        foreach ($taking as $r) {
            $k = $key($r);
            $lines[$k] ??= $blank($r);
            $lines[$k]['qty_taking'] += (float) $r->qty;
            $lines[$k]['item_taking'] = 1;
            $lines[$k]['uom'] ??= $r->uom;
            $lines[$k]['warehouse'] ??= $r->warehouse;
            $lines[$k]['time'] = $r->created;
        }

        if (empty($lines)) {
            return [];
        }

        // Total variance qty per (lokasi, part) dan per part untuk remark
        $varPartLocation = [];
        $varPart = [];
        foreach ($lines as $line) {
            $variance = $line['qty_taking'] - $line['qty_book'];
            $pl = $line['location_no'] . '|' . $line['part_no'];
            $varPartLocation[$pl] = ($varPartLocation[$pl] ?? 0) + $variance;
            $varPart[$line['part_no']] = ($varPart[$line['part_no']] ?? 0) + $variance;
        }

        $locations = $this->locations();
        $areas     = $this->areas();
        $parts     = $this->parts();
        $costs     = $this->unitCosts();
        $now       = now()->format('Y-m-d H:i:s');

        $rows = [];
        foreach ($lines as $line) {
            $location = $locations[$line['location_no']] ?? null;
            $part     = $parts[$line['part_no']] ?? null;
            $cost     = $costs[$line['part_no']] ?? ['mat' => 0.0, 'lab' => 0.0, 'foh' => 0.0];
            $unitCost = $cost['mat'] + $cost['lab'] + $cost['foh'];
            $variance = $line['qty_taking'] - $line['qty_book'];

            $rows[] = [
                'VINVLISTNO'      => (string) ($invListNo ?? ''),
                'DTAKINGDATE'     => Carbon::parse($period->TAKING_DATE)->toDateString(),
                'DCLOSEDATE'      => Carbon::parse($period->CLOSE_DATE)->toDateString(),
                'VEVENTTYPE'      => $event?->type,
                'IMONTH'          => (int) $period->MONTH,
                'IYEAR'           => (int) $period->YEAR,
                'DTAKINGTIME'     => $line['time'] ?? $line['book_time'],
                'VSITE'           => $location->VSITE ?? null,
                'VWAREHOUSE'      => $this->filled($location->VWAREHOUSE ?? null) ?? $line['warehouse'],
                'VLOCGROUP'       => $location->VLOCGROUP ?? null,
                'VLOCATIONNO'     => $line['location_no'],
                'VAREA'           => $areas[$line['location_no']] ?? null,
                'VPRODFAMILY'     => $part->VPRODGROUP ?? null,
                'VACCGROUP'       => $part->VACCGROUP ?? null,
                'VPARTNO'         => $line['part_no'],
                'VPARTNAME'       => $part->VPARTDESC ?? null,
                'VSEG'            => $part->VSEG ?? null,
                'VPARTGROUP'      => $part->VPARTGROUP ?? null,
                'VUOM'            => $line['uom'],
                'VLOTBATCHNO'     => $line['lot'] !== '' ? $line['lot'] : null,
                'NQTYBOOK'        => $line['qty_book'],
                'NQTYTAKING'      => $line['qty_taking'],
                'NQTYVARIANCE'    => $variance,
                'MMATCOST'        => $cost['mat'],
                'MLABCOST'        => $cost['lab'],
                'MFOHCOST'        => $cost['foh'],
                'MAMOUNTBOOK'     => round($line['qty_book'] * $unitCost, 2),
                'MAMOUNTTAKING'   => round($line['qty_taking'] * $unitCost, 2),
                'MAMOUNTVARIANCE' => round($variance * $unitCost, 2),
                // Boolean varchar(1): '1' = baris ada di book / taking
                'BITEMBOOK'       => $line['item_book'] ? '1' : '0',
                'BITEMTAKING'     => $line['item_taking'] ? '1' : '0',
                'VREMARKPARTLOC'  => self::remark($varPartLocation[$line['location_no'] . '|' . $line['part_no']]),
                'VREMARKPARTNO'   => self::remark($varPart[$line['part_no']]),
                'VCREA'           => $createdBy,
                'DCREA'           => $now,
            ];
        }

        return $rows;
    }

    /**
     * Match bila variance 0, Var(-) bila taking kurang dari book, Var(+) bila lebih.
     */
    public static function remark(float $variance): string
    {
        $variance = round($variance, 4);

        return $variance == 0.0 ? 'Match' : ($variance < 0 ? 'Var(-)' : 'Var(+)');
    }

    private function filled($value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * @return array<string, object> [Location No => {VSITE, VWAREHOUSE, VLOCGROUP}] dari Master Location
     */
    private function locations(): array
    {
        $map = [];
        foreach (FacdbtTable::active($this->db(), FacdbtTable::LOCATIONS)->get(['VLOCATIONNO', 'VSITE', 'VWAREHOUSE', 'VLOCGROUP']) as $r) {
            $map[trim((string) $r->VLOCATIONNO)] = $r;
        }

        return $map;
    }

    /**
     * @return array<string, string> [Location No => area (Master Group Location VLOCGROUP)]; lokasi di beberapa area memakai area pertama
     */
    private function areas(): array
    {
        $map = [];
        foreach (FacdbtTable::active($this->db(), FacdbtTable::GROUP_LOCATIONS)->orderBy('IID')->get(['VLOCATIONNO', 'VLOCGROUP']) as $r) {
            $loc   = trim((string) $r->VLOCATIONNO);
            $group = AreaName::of($r->VLOCGROUP);
            if ($group !== '' && !isset($map[$loc])) {
                $map[$loc] = $group;
            }
        }

        return $map;
    }

    /**
     * @return array<string, object> [Part No => {VPRODGROUP, VACCGROUP, VPARTDESC, VSEG, VPARTGROUP}] dari Master Part
     */
    private function parts(): array
    {
        $map = [];
        foreach (FacdbtTable::active($this->db(), FacdbtTable::PARTS)->get(['VPARTNO', 'VPRODGROUP', 'VACCGROUP', 'VPARTDESC', 'VSEG', 'VPARTGROUP']) as $r) {
            $map[trim((string) $r->VPARTNO)] = $r;
        }

        return $map;
    }

    /**
     * @return array<string, array{mat: float, lab: float, foh: float}> dari Master Unit Cost
     */
    private function unitCosts(): array
    {
        $map = [];
        foreach (FacdbtTable::active($this->db(), FacdbtTable::UNIT_COSTS)->get(['VPARTNO', 'MMATCOST', 'MLABCOST', 'MFOHCOST']) as $r) {
            $map[trim((string) $r->VPARTNO)] = [
                'mat' => (float) $r->MMATCOST,
                'lab' => (float) $r->MLABCOST,
                'foh' => (float) $r->MFOHCOST,
            ];
        }

        return $map;
    }
}
