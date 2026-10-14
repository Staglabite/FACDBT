<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Dashboard Progress by Group Location & Detail Location
class FACDBTD002 extends Controller
{
    private const CONN = 'INV_TAKING';
    private const CACHE_TTL = 60;

    // Jumlah warehouse yang ditampilkan saat filter group location tidak dipilih
    private const TOP_LIMIT = 15;

    // Slot filter waktu: [dayOffset, 'jam:menit']. dayOffset 0 = hari pertama taking, 1 = hari kedua.
    private const SLOTS = [
        [0, '07:30'],
        [0, '09:00'],
        [0, '10:00'],
        [0, '11:00'],
        [0, '12:00'],
        [0, '13:00'],
        [0, '14:00'],
        [0, '15:00'],
        [0, '16:15'],
        [0, '19:30'],
        [1, '09:00'],
        [1, '10:00'],
        [1, '11:00'],
    ];

    public function index()
    {
        return view('modules.FACDBT.FACDBT04.FACDBTD002.FACDBTD002');
    }

    /**
     * Daftar tombol filter lokasi (location_group dari SIM_INV_GROUP_LOCATION).
     */
    public function getGroups()
    {
        $groups = DB::connection(self::CONN)
            ->table('SIM_INV_GROUP_LOCATION')
            ->selectRaw('TRIM(location_group) as location_group')
            ->whereRaw("TRIM(location_group) <> ''")
            ->distinct()
            ->orderBy('location_group')
            ->pluck('location_group');

        return response()->json($groups);
    }

    /**
     * Daftar tombol filter waktu. Label memakai tanggal taking sebenarnya, contoh "6-07.30".
     */
    public function getTimeSlots()
    {
        $period = $this->resolvePeriod(DB::connection(self::CONN));
        if ($period === null) {
            return response()->json([]);
        }

        $slots = [];
        foreach ($this->slotTimes($period['baseDay']) as $slotDate) {
            $slots[] = [
                'label'     => $slotDate->format('j') . '-' . $slotDate->format('H.i'),
                'full_time' => $slotDate->format('Y-m-d H:i:s'),
            ];
        }

        return response()->json($slots);
    }

    /**
     * Progress per warehouse (SIM_INV_LOCATION_TEST.warehouse) untuk lokasi di group yang dipilih.
     * - % Item : item (part unik) taking terhadap item book
     * - % Qty  : qty taking terhadap qty book
     * Book & taking dicocokkan per lokasi, taking dihitung kumulatif s/d waktu filter.
     * Tanpa filter group: semua warehouse, diambil progress terbesar (TOP_LIMIT teratas).
     */
    public function getData(Request $request)
    {
        $empty = ['item' => [], 'qty' => []];
        $db = DB::connection(self::CONN);

        $period = $this->resolvePeriod($db);
        if ($period === null) {
            return response()->json($empty);
        }

        $slotTimes    = $this->slotTimes($period['baseDay']);
        $selectedTime = $this->parseTime($request->get('time')) ?? end($slotTimes)->format('Y-m-d H:i:s');
        $group        = trim((string) $request->get('group', ''));

        $takingDate = $period['takingDate'];
        $closeDate  = $period['closeDate'];
        $invListNo  = $period['invListNo'];

        $cacheKey = 'FACDBTD002:data:v1:' . md5(implode('|', [$takingDate, $closeDate, $invListNo, $selectedTime, $group]));

        $result = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($db, $takingDate, $closeDate, $invListNo, $selectedTime, $group, $empty) {
            $book = $this->withWarehouse($db->table('SIM_INV_TAKING_BOOK_TAB as d'), $group)
                ->whereBetween('d.CREATED_DATETIME', [$takingDate, $closeDate])
                ->where('d.INV_LIST_NO', $invListNo)
                ->groupBy('warehouse')
                ->selectRaw('COUNT(DISTINCT d.PART_NO) as item_book, SUM(d.QUANTITY) as qty_book')
                ->get()
                ->keyBy('warehouse');

            if ($book->isEmpty()) {
                return $empty;
            }

            $taking = $this->withWarehouse($db->table('SIM_INV_TAKING_DATA_TAB as d'), $group)
                ->whereBetween('d.CREATED_DATETIME', [$takingDate, $selectedTime])
                ->groupBy('warehouse')
                ->selectRaw('COUNT(DISTINCT d.PART_NO) as item_taking, SUM(d.QTY) as qty_taking')
                ->get()
                ->keyBy('warehouse');

            $rows = $book->map(function ($b, $warehouse) use ($taking) {
                $t        = $taking->get($warehouse);
                $itemBook = (int) $b->item_book;
                $qtyBook  = (float) $b->qty_book;

                return [
                    'warehouse' => (string) $warehouse,
                    'pct_item'  => $itemBook > 0 ? round(((int) ($t->item_taking ?? 0) / $itemBook) * 100, 2) : 0,
                    'pct_qty'   => $qtyBook > 0 ? round(((float) ($t->qty_taking ?? 0) / $qtyBook) * 100, 2) : 0,
                ];
            })->values();

            // Tiap chart diurutkan dari progress terbesar; tanpa filter group hanya TOP_LIMIT teratas
            $limit = $group === '' ? self::TOP_LIMIT : null;
            $sortBy = fn (string $key) => $rows->sortByDesc($key)
                ->when($limit, fn ($c) => $c->take($limit))
                ->map(fn ($r) => ['warehouse' => $r['warehouse'], 'pct' => $r[$key]])
                ->values()
                ->all();

            return [
                'item' => $sortBy('pct_item'),
                'qty'  => $sortBy('pct_qty'),
            ];
        });

        return response()->json($result);
    }

    /**
     * Join tabel book / taking (alias d) ke group location dan master location untuk mendapat warehouse.
     * Bila $group diisi, hanya lokasi di location_group tersebut.
     */
    private function withWarehouse($query, string $group)
    {
        $groupSub = DB::connection(self::CONN)
            ->table('SIM_INV_GROUP_LOCATION')
            ->selectRaw('TRIM(LOCATION_NO) as loc_no')
            ->when($group !== '', fn ($q) => $q->whereRaw('TRIM(location_group) = ?', [$group]))
            ->distinct();

        return $query
            ->joinSub($groupSub, 'gl', DB::raw('TRIM(d.LOCATION_NO)'), '=', 'gl.loc_no')
            ->leftJoin('SIM_INV_LOCATION_TEST as l', DB::raw('TRIM(l.location_no)'), '=', 'gl.loc_no')
            ->selectRaw("COALESCE(NULLIF(TRIM(l.warehouse), ''), 'UNKNOWN') as warehouse");
    }

    /**
     * Periode taking aktif (TAKING_DATE s/d CLOSE_DATE terbaru) + INV_LIST_NO terbaru.
     */
    private function resolvePeriod($db): ?array
    {
        $concluding = $db->table('SIM_INV_TAKING_CONCLUDING_TAB')
            ->select('TAKING_DATE', 'CLOSE_DATE')
            ->orderByDesc('TAKING_DATE')
            ->first();

        if (!$concluding) {
            return null;
        }

        $baseDay    = Carbon::parse($concluding->TAKING_DATE)->startOfDay();
        $takingDate = $baseDay->format('Y-m-d H:i:s');
        $closeDate  = Carbon::parse($concluding->CLOSE_DATE)->endOfDay()->format('Y-m-d H:i:s');

        // INV_LIST_NO dibandingkan sebagai string supaya index tetap terpakai
        $invListNo = $db->table('SIM_INV_TAKING_BOOK_TAB')
            ->whereBetween('CREATED_DATETIME', [$takingDate, $closeDate])
            ->orderByRaw('CAST(INV_LIST_NO AS UNSIGNED) DESC')
            ->value('INV_LIST_NO');

        if ($invListNo === null || $invListNo === '') {
            return null;
        }

        return [
            'baseDay'    => $baseDay,
            'takingDate' => $takingDate,
            'closeDate'  => $closeDate,
            'invListNo'  => (string) $invListNo,
        ];
    }

    /**
     * @return Carbon[]
     */
    private function slotTimes(Carbon $baseDay): array
    {
        return array_map(function ($slot) use ($baseDay) {
            [$dayOffset, $hm] = $slot;
            [$h, $m] = explode(':', $hm);

            return $baseDay->copy()->addDays($dayOffset)->setTime((int) $h, (int) $m, 0);
        }, self::SLOTS);
    }

    private function parseTime($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
