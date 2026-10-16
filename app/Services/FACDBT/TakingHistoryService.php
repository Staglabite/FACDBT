<?php

namespace App\Services\FACDBT;

use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan hasil taking periode yang sudah ditutup ke SIM_INV_TAKING_DATA_HISTORY.
 *
 * Satu baris history = satu (location_no, part_no, LOT_BATCH_NO):
 * - book   : SIM_INV_TAKING_BOOK_TAB, INV_LIST_NO terbaru di rentang TAKING_DATE s/d CLOSE_DATE
 * - taking : SIM_INV_TAKING_DATA_TAB, CREATED_DATETIME di rentang yang sama (tanggal scan)
 * - master (lokasi, area, part, unit cost) disalin saat snapshot supaya history tidak ikut berubah.
 */
class TakingHistoryService
{
    private const CONN = 'INV_TAKING';
    private const TABLE = 'SIM_INV_TAKING_DATA_HISTORY';

    // Periode yang masih berjalan; selain ini dianggap sudah ditutup
    private const ACTIVE_ROWSTATE = 'Started';

    private function db(): Connection
    {
        return DB::connection(self::CONN);
    }

    /**
     * Periode yang sudah ditutup (ROWSTATE bukan Started dan CLOSE_DATE sudah lewat) tetapi belum ada di history.
     */
    public function pendingPeriods(): array
    {
        return $this->db()->table('SIM_INV_TAKING_CONCLUDING_TAB as c')
            ->where(fn ($q) => $q->whereNull('c.ROWSTATE')->orWhere('c.ROWSTATE', '<>', self::ACTIVE_ROWSTATE))
            ->whereNotNull('c.TAKING_DATE')
            ->whereNotNull('c.CLOSE_DATE')
            ->where('c.CLOSE_DATE', '<', now()->startOfDay())
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from(self::TABLE . ' as h')
                ->whereRaw('h.TAKING_DATE = DATE(c.TAKING_DATE)'))
            ->orderBy('c.TAKING_DATE')
            ->get(['c.MONTH', 'c.YEAR', 'c.TAKING_DATE', 'c.CLOSE_DATE'])
            ->all();
    }

    public function findPeriod(string $takingDate): ?object
    {
        return $this->db()->table('SIM_INV_TAKING_CONCLUDING_TAB')
            ->whereDate('TAKING_DATE', Carbon::parse($takingDate)->toDateString())
            ->first(['MONTH', 'YEAR', 'TAKING_DATE', 'CLOSE_DATE']);
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
        $closeDate  = Carbon::parse($period->CLOSE_DATE)->endOfDay()->format('Y-m-d H:i:s');

        $invListNo = $db->table('SIM_INV_TAKING_BOOK_TAB')
            ->whereBetween('CREATED_DATETIME', [$takingDate, $closeDate])
            ->orderByRaw('CAST(INV_LIST_NO AS UNSIGNED) DESC')
            ->value('INV_LIST_NO');

        $rows = $this->buildRows($period, $takingDate, $closeDate, $invListNo, $createdBy);

        $db->transaction(function () use ($db, $takingDay, $rows) {
            $db->table(self::TABLE)->whereDate('TAKING_DATE', $takingDay->toDateString())->delete();

            foreach (array_chunk($rows, 1000) as $chunk) {
                $db->table(self::TABLE)->insert($chunk);
            }
        });

        return count($rows);
    }

    private function buildRows(object $period, string $takingDate, string $closeDate, $invListNo, ?string $createdBy): array
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
                'INV_LIST_NO'          => (string) ($invListNo ?? ''),
                'TAKING_DATE'          => Carbon::parse($period->TAKING_DATE)->toDateString(),
                'CLOSE_DATE'           => Carbon::parse($period->CLOSE_DATE)->toDateString(),
                'MONTH'                => (int) $period->MONTH,
                'YEAR'                 => (int) $period->YEAR,
                'time'                 => $line['time'] ?? $line['book_time'],
                'site'                 => $location->site ?? null,
                'warehouse'            => $this->filled($location->warehouse ?? null) ?? $line['warehouse'],
                'location_group'       => $location->location_group ?? null,
                'location_no'          => $line['location_no'],
                'area'                 => $areas[$line['location_no']] ?? null,
                'product_family'       => $part->prod_group ?? null,
                'accounting_group'     => $part->accounting_group ?? null,
                'part_no'              => $line['part_no'],
                'part_name'            => $part->inventory_part_description ?? null,
                'seg'                  => $part->seg ?? null,
                'part_group'           => $part->part_group ?? null,
                'uom'                  => $line['uom'],
                'LOT_BATCH_NO'         => $line['lot'] !== '' ? $line['lot'] : null,
                'qty_book'             => $line['qty_book'],
                'qty_taking'           => $line['qty_taking'],
                'qty_variance'         => $variance,
                'mat_cost'             => $cost['mat'],
                'lab_cost'             => $cost['lab'],
                'foh_cost'             => $cost['foh'],
                'amount_book'          => round($line['qty_book'] * $unitCost, 2),
                'amount_taking'        => round($line['qty_taking'] * $unitCost, 2),
                'amount_variance'      => round($variance * $unitCost, 2),
                'item_book'            => $line['item_book'],
                'item_taking'          => $line['item_taking'],
                'remark_part_location' => self::remark($varPartLocation[$line['location_no'] . '|' . $line['part_no']]),
                'remark_part_no'       => self::remark($varPart[$line['part_no']]),
                'CREATED_DATETIME'     => $now,
                'CREATED_BY'           => $createdBy,
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
     * @return array<string, object> [location_no => {site, warehouse, location_group}]
     */
    private function locations(): array
    {
        $map = [];
        foreach ($this->db()->table('SIM_INV_LOCATION_TEST')->get(['location_no', 'site', 'warehouse', 'location_group']) as $r) {
            $map[trim((string) $r->location_no)] = $r;
        }

        return $map;
    }

    /**
     * @return array<string, string> [location_no => location_group SIM_INV_GROUP_LOCATION]; lokasi di beberapa group memakai group pertama
     */
    private function areas(): array
    {
        $map = [];
        foreach ($this->db()->table('SIM_INV_GROUP_LOCATION')->orderBy('IID')->get(['LOCATION_NO', 'location_group']) as $r) {
            $loc   = trim((string) $r->LOCATION_NO);
            $group = trim((string) $r->location_group);
            if ($group !== '' && !isset($map[$loc])) {
                $map[$loc] = $group;
            }
        }

        return $map;
    }

    /**
     * @return array<string, object> [part_no => {prod_group, accounting_group, inventory_part_description, seg, part_group}]
     */
    private function parts(): array
    {
        $map = [];
        foreach ($this->db()->table('SIM_INV_PART_TEST')->get(['part_no', 'prod_group', 'accounting_group', 'inventory_part_description', 'seg', 'part_group']) as $r) {
            $map[trim((string) $r->part_no)] = $r;
        }

        return $map;
    }

    /**
     * @return array<string, array{mat: float, lab: float, foh: float}>
     */
    private function unitCosts(): array
    {
        $map = [];
        foreach ($this->db()->table('SIM_INV_UNIT_COST_TEST')->get(['part_no', 'mat_cost', 'lab_cost', 'foh_cost']) as $r) {
            $map[trim((string) $r->part_no)] = [
                'mat' => (float) $r->mat_cost,
                'lab' => (float) $r->lab_cost,
                'foh' => (float) $r->foh_cost,
            ];
        }

        return $map;
    }
}
