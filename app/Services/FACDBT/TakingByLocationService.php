<?php

namespace App\Services\FACDBT;

use App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns\CachesDashboard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Overview Taking Data by Location (FACDBTD007): data taking periode yang sedang berjalan.
 *
 * Satu baris = satu (location_no, part_no, LOT_BATCH_NO) yang ada di book dan/atau taking:
 * - book   : SIM_INV_TAKING_BOOK_TAB, INV_LIST_NO terbaru di rentang TAKING_DATE s/d CLOSE_DATE
 * - taking : SIM_INV_TAKING_DATA_TAB, CREATED_DATETIME di rentang yang sama
 * - Site dari SIM_INV_LOCATION_TEST, Location Group dari SIM_INV_GROUP_LOCATION,
 *   Product Family & Acc Group dari SIM_INV_PART_TEST
 * - Month, Year, Warehouse, Part Name, UoM dari data taking (bila belum di-scan: dari book / master)
 * - Progress % = qty taking / qty book x 100
 */
class TakingByLocationService
{
    use CachesDashboard;

    private const CONN = 'INV_TAKING';
    private const CACHE_TTL = 60;

    /** @var array<string, string> [key baris => judul kolom], dipakai DataTable & export */
    public const COLUMNS = [
        'site'             => 'Site',
        'MONTH'            => 'Month',
        'YEAR'             => 'Year',
        'warehouse'        => 'Warehouse',
        'location_group'   => 'Location Group',
        'location_no'      => 'Location No',
        'product_family'   => 'Product Family',
        'accounting_group' => 'Acc Group',
        'part_no'          => 'Part No',
        'part_name'        => 'Part Name',
        'qty_book'         => 'Qty Bookkeeping',
        'qty_taking'       => 'Qty Taking',
        'uom'              => 'UoM',
        'progress'         => 'Progress %',
        'LOT_BATCH_NO'     => 'Lot Batch No',
    ];

    // Urutan isi tiap baris: key baris lalu kolom COLUMNS. Baris disimpan sebagai list (bukan array
    // asosiatif) supaya hemat memori; puluhan ribu baris asosiatif melewati memory_limit saat di-cache.
    public const KEY = 0;

    // Kolom yang dicari oleh kotak Search
    private const SEARCH_COLUMNS = [
        'site', 'warehouse', 'location_group', 'location_no', 'product_family',
        'accounting_group', 'part_no', 'part_name', 'uom', 'LOT_BATCH_NO',
    ];

    /**
     * Semua baris periode taking aktif (di-cache; dihitung ulang bila ada scan / book baru).
     *
     * @return array<int, array<int, mixed>> [[key, site, MONTH, ...], ...] sesuai urutan fields()
     */
    public function rows(): array
    {
        $db = DB::connection(self::CONN);

        $period = RunningTakingPeriod::find($db);

        if (!$period) {
            return [];
        }

        $takingDate = Carbon::parse($period->TAKING_DATE)->startOfDay()->format('Y-m-d H:i:s');
        $closeDate  = Carbon::parse($period->CLOSE_DATE)->endOfDay()->format('Y-m-d H:i:s');

        // Perhitungan pertama (atau menunggu perhitungan request lain) bisa lebih dari 30 detik,
        // dan satu periode bisa puluhan ribu baris
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        return $this->dashboardCache("FACDBTD007:rows:v3:{$takingDate}:{$closeDate}", function () use ($db, $period, $takingDate, $closeDate) {
            return $this->build($db, $period, $takingDate, $closeDate);
        });
    }

    /**
     * @return array<int, string> ['key', 'site', 'MONTH', ...]: nama isi baris sesuai posisinya
     */
    public static function fields(): array
    {
        return ['key', ...array_keys(self::COLUMNS)];
    }

    /**
     * Satu baris list -> array asosiatif (hanya untuk baris yang ditampilkan).
     */
    public static function assoc(array $row): array
    {
        return array_combine(self::fields(), $row);
    }

    /**
     * Baris setelah filter Search (`keyword`) dan pencarian per kolom (`columns[i][data]` / `columns[i][search][value]`
     * seperti yang dikirim DataTables), diurutkan per lokasi, part, lot.
     */
    public function filtered(Request $request, ?array $rows = null): Collection
    {
        $rows = collect($rows ?? $this->rows());

        $index = array_flip(self::fields());

        $keyword = mb_strtolower(trim((string) $request->input('keyword', '')));
        if ($keyword !== '') {
            $searchIndexes = array_map(fn ($column) => $index[$column], self::SEARCH_COLUMNS);
            $rows = $rows->filter(function ($row) use ($keyword, $searchIndexes) {
                foreach ($searchIndexes as $i) {
                    if (str_contains(mb_strtolower((string) $row[$i]), $keyword)) {
                        return true;
                    }
                }

                return false;
            });
        }

        foreach ((array) $request->input('columns', []) as $column) {
            $name  = $column['data'] ?? null;
            $value = mb_strtolower(trim((string) ($column['search']['value'] ?? '')));
            if ($value === '' || !is_string($name) || !array_key_exists($name, self::COLUMNS)) {
                continue;
            }
            $i = $index[$name];
            $rows = $rows->filter(fn ($row) => str_contains(mb_strtolower((string) $row[$i]), $value));
        }

        return $rows->values();
    }

    /**
     * Urutkan baris sesuai `order[]` DataTables; tanpa urutan: per lokasi, part, lot (urutan bawaan).
     */
    public function sorted(Collection $rows, Request $request): Collection
    {
        $names = array_keys(self::COLUMNS);
        $order = (array) $request->input('order', []);
        $first = $order[0] ?? null;
        $name  = $first ? ($request->input('columns.' . ($first['column'] ?? '') . '.data')) : null;

        if (!is_string($name) || !in_array($name, $names, true)) {
            return $rows;
        }

        $numeric = in_array($name, ['qty_book', 'qty_taking', 'progress', 'MONTH', 'YEAR'], true);
        $desc    = strtolower((string) ($first['dir'] ?? 'asc')) === 'desc';
        $i       = array_flip(self::fields())[$name];

        return $rows->sort(function ($a, $b) use ($i, $numeric) {
            return $numeric
                ? ((float) $a[$i]) <=> ((float) $b[$i])
                : strnatcasecmp((string) $a[$i], (string) $b[$i]);
        })->pipe(fn ($c) => $desc ? $c->reverse() : $c)->values();
    }

    private function build($db, object $period, string $takingDate, string $closeDate): array
    {
        $invListNo = $db->table('SIM_INV_TAKING_BOOK_TAB')
            ->whereBetween('CREATED_DATETIME', [$takingDate, $closeDate])
            ->orderByRaw('CAST(INV_LIST_NO AS UNSIGNED) DESC')
            ->value('INV_LIST_NO');

        // [lokasi|part|lot => data] dari book & taking, diagregasi di database tanpa join
        $lines = [];
        $key = fn ($r) => trim((string) $r->LOCATION_NO) . '|' . trim((string) $r->PART_NO) . '|' . trim((string) $r->LOT_BATCH_NO);
        $blank = fn ($r) => [
            'location_no' => trim((string) $r->LOCATION_NO),
            'part_no'     => trim((string) $r->PART_NO),
            'lot'         => trim((string) $r->LOT_BATCH_NO),
            'qty_book'    => 0.0,
            'qty_taking'  => 0.0,
            'month'       => null,
            'year'        => null,
            'warehouse'   => null,
            'part_name'   => null,
            'uom'         => null,
        ];

        // Taking dulu supaya Month, Year, Warehouse, Part Name, UoM diambil dari data taking
        $taking = $db->table('SIM_INV_TAKING_DATA_TAB')
            ->whereBetween('CREATED_DATETIME', [$takingDate, $closeDate])
            ->groupBy('LOCATION_NO', 'PART_NO', 'LOT_BATCH_NO')
            ->select(
                'LOCATION_NO', 'PART_NO', 'LOT_BATCH_NO',
                DB::raw('SUM(QTY) as qty'),
                DB::raw('MAX(MONTH) as month'),
                DB::raw('MAX(YEAR) as year'),
                DB::raw('MAX(WAREHOUSE) as warehouse'),
                DB::raw('MAX(DESCRIPTION) as part_name'),
                DB::raw('MAX(UNIT_MEAS) as uom')
            )
            ->get();

        foreach ($taking as $r) {
            $k = $key($r);
            $lines[$k] ??= $blank($r);
            $lines[$k]['qty_taking'] += (float) $r->qty;
            foreach (['month', 'year', 'warehouse', 'part_name', 'uom'] as $field) {
                $lines[$k][$field] ??= $this->filled($r->{$field});
            }
        }
        unset($taking);

        if ($invListNo !== null && $invListNo !== '') {
            $book = $db->table('SIM_INV_TAKING_BOOK_TAB')
                ->whereBetween('CREATED_DATETIME', [$takingDate, $closeDate])
                ->where('INV_LIST_NO', $invListNo)
                ->groupBy('LOCATION_NO', 'PART_NO', 'LOT_BATCH_NO')
                ->select(
                    'LOCATION_NO', 'PART_NO', 'LOT_BATCH_NO',
                    DB::raw('SUM(QUANTITY) as qty'),
                    DB::raw('MAX(MONTH) as month'),
                    DB::raw('MAX(YEAR) as year'),
                    DB::raw('MAX(WAREHOUSE) as warehouse'),
                    DB::raw('MAX(UNIT_MEAS) as uom')
                )
                ->get();

            foreach ($book as $r) {
                $k = $key($r);
                $lines[$k] ??= $blank($r);
                $lines[$k]['qty_book'] += (float) $r->qty;
                foreach (['month', 'year', 'warehouse', 'uom'] as $field) {
                    $lines[$k][$field] ??= $this->filled($r->{$field});
                }
            }
            unset($book);
        }

        if (empty($lines)) {
            return [];
        }

        ksort($lines);

        $sites  = $this->sites($db);
        $groups = $this->locationGroups($db);
        $parts  = $this->parts($db);

        // Isi baris mengikuti urutan fields()
        $rows = [];
        foreach ($lines as $k => $line) {
            $part = $parts[$line['part_no']] ?? null;

            $rows[] = [
                $k,
                $sites[$line['location_no']] ?? null,
                $line['month'] ?? $period->MONTH,
                $line['year'] ?? $period->YEAR,
                $line['warehouse'],
                $groups[$line['location_no']] ?? null,
                $line['location_no'],
                $this->filled($part->prod_group ?? null),
                $this->filled($part->accounting_group ?? null),
                $line['part_no'],
                $line['part_name'] ?? $this->filled($part->inventory_part_description ?? null),
                $line['qty_book'],
                $line['qty_taking'],
                $line['uom'],
                // Tanpa book progress tidak bisa dihitung
                $line['qty_book'] != 0.0 ? round($line['qty_taking'] / $line['qty_book'] * 100, 2) : null,
                $line['lot'] !== '' ? $line['lot'] : null,
            ];
            unset($lines[$k]);
        }

        return $rows;
    }

    private function filled($value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * @return array<string, string> [location_no => site] dari SIM_INV_LOCATION_TEST
     */
    private function sites($db): array
    {
        $map = [];
        foreach ($db->table('SIM_INV_LOCATION_TEST')->get(['location_no', 'site']) as $r) {
            $map[trim((string) $r->location_no)] = $this->filled($r->site);
        }

        return $map;
    }

    /**
     * @return array<string, string> [location_no => location_group] dari SIM_INV_GROUP_LOCATION; lokasi di beberapa group memakai group pertama
     */
    private function locationGroups($db): array
    {
        $map = [];
        foreach ($db->table('SIM_INV_GROUP_LOCATION')->orderBy('IID')->get(['LOCATION_NO', 'location_group']) as $r) {
            $loc   = trim((string) $r->LOCATION_NO);
            $group = trim((string) $r->location_group);
            if ($group !== '' && !isset($map[$loc])) {
                $map[$loc] = $group;
            }
        }

        return $map;
    }

    /**
     * @return array<string, object> [part_no => {prod_group, accounting_group, inventory_part_description}]
     */
    private function parts($db): array
    {
        $map = [];
        foreach ($db->table('SIM_INV_PART_TEST')->get(['part_no', 'prod_group', 'accounting_group', 'inventory_part_description']) as $r) {
            $map[trim((string) $r->part_no)] = $r;
        }

        return $map;
    }
}
