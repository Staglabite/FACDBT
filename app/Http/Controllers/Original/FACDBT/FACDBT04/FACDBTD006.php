<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04;

use App\Services\FACDBT\AreaName;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns\CachesDashboard;
use App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns\FiltersTakingRange;
use App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns\UsesTakingEvent;
use App\Services\FACDBT\FacdbtTable;
use App\Services\FACDBT\RunningTakingPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard Taking Data Revision: revisi data taking (SIM_INV_TAKING_DATA_REVISION_TAB) periode yang sedang berjalan.
 *
 * Revisi = baris dengan REV_NO > 1.
 * - Taking Data Revision : per area, Item Book (jumlah baris book) dan % Area (jumlah revisi di area / Item Book area x 100)
 * - Reason Revision      : porsi revisi per REASON_REVISION
 * - Revision Performance : % Revision per MODIFIED_BY (jumlah revisi / Item Book x 100; Item Book area terpilih bila difilter)
 * Area = Master Group Location (VLOCGROUP) dari LOCATION_NO. Filter dengan klik chart (lihat getData()).
 */
class FACDBTD006 extends Controller
{
    use CachesDashboard;
    use FiltersTakingRange;
    use UsesTakingEvent;

    private const CONN = 'FACDBT';
    private const CACHE_TTL = 60;

    private const UNKNOWN = 'UNKNOWN';

    public function index()
    {
        return view('modules.FACDBT.FACDBT04.FACDBTD006.FACDBTD006');
    }

    /**
     * Pilihan hari untuk filter rentang waktu (Dari - Sampai).
     */
    public function getTimeRange()
    {
        $period = RunningTakingPeriod::find(DB::connection(self::CONN));
        if (!$period) {
            return response()->json(['days' => []]);
        }

        return response()->json($this->timeRangePayload(
            Carbon::parse($period->TAKING_DATE)->startOfDay()->format('Y-m-d H:i:s'),
            Carbon::parse($period->CLOSE_DATE)->endOfDay()->format('Y-m-d H:i:s')
        ));
    }

    /**
     * Data semua chart dengan filter interaktif `area` (klik chart Taking Data Revision) dan `modifier`
     * (klik chart Revision Performance); kosong = semua. Chart yang menjadi sumber filter tetap menampilkan
     * semua kategorinya (yang lain dipudarkan di browser):
     * - Taking Data Revision : semua area, jumlah revisi difilter modifier
     * - Revision Performance : semua modifier, difilter area
     * - Reason Revision      : difilter area & modifier
     * Dengan `demo=1` dipakai data contoh (tidak dari database) untuk presentasi, lihat demoSource().
     */
    public function getData(Request $request)
    {
        $source = $request->boolean('demo') ? $this->demoSource() : $this->source($request);

        $area     = trim((string) $request->input('area', ''));
        $modifier = trim((string) $request->input('modifier', ''));

        $revisionsPerArea = [];
        $perReason        = [];
        $perModifier      = [];
        $reasonTotal      = 0;
        foreach ($source['revisions'] as [$rowArea, $rowModifier, $reason, $count]) {
            $areaMatch     = $area === '' || $area === $rowArea;
            $modifierMatch = $modifier === '' || $modifier === $rowModifier;

            if ($modifierMatch) {
                $revisionsPerArea[$rowArea] = ($revisionsPerArea[$rowArea] ?? 0) + $count;
            }
            if ($areaMatch) {
                $perModifier[$rowModifier] = ($perModifier[$rowModifier] ?? 0) + $count;
            }
            if ($areaMatch && $modifierMatch) {
                $perReason[$reason] = ($perReason[$reason] ?? 0) + $count;
                $reasonTotal += $count;
            }
        }

        $byArea = [];
        foreach (array_keys($source['book'] + $revisionsPerArea) as $rowArea) {
            $book      = $source['book'][$rowArea] ?? 0;
            $revisions = $revisionsPerArea[$rowArea] ?? 0;
            $byArea[]  = [
                'area'      => (string) $rowArea,
                'item_book' => $book,
                'revisions' => $revisions,
                // Tanpa book persentase tidak bisa dihitung
                'percent'   => $book > 0 ? round($revisions / $book * 100, 1) : null,
            ];
        }
        usort($byArea, fn ($a, $b) => [$b['percent'] ?? -1, $b['item_book']] <=> [$a['percent'] ?? -1, $a['item_book']]);

        return response()->json([
            'by_area'              => $byArea,
            'reason_revision'      => $this->shares($perReason, $reasonTotal),
            // % Revision terhadap Item Book (semua area, atau area yang dipilih)
            'revision_performance' => $this->shares($perModifier, (int) ($area === '' ? array_sum($source['book']) : ($source['book'][$area] ?? 0))),
        ]);
    }

    /**
     * Data dasar periode berjalan (di-cache; dihitung ulang bila ada scan / revisi / book baru):
     * jumlah baris book per area (seluruh periode) dan jumlah revisi per (area, modified by, reason) yang
     * dibuat (CREATED_DATETIME) di rentang filter waktu `from` / `to`; tanpa filter: seluruh periode.
     */
    private function source(Request $request): array
    {
        $empty = ['book' => [], 'revisions' => []];

        $db     = DB::connection(self::CONN);
        $period = RunningTakingPeriod::find($db);
        if (!$period) {
            return $empty;
        }

        $takingDate = Carbon::parse($period->TAKING_DATE)->startOfDay()->format('Y-m-d H:i:s');
        $closeDate  = Carbon::parse($period->CLOSE_DATE)->endOfDay()->format('Y-m-d H:i:s');
        [$takingFrom, $takingTo] = $this->takingRange($request, $takingDate, $closeDate);

        return $this->dashboardCache("FACDBTD006:source:v7:{$takingDate}:{$closeDate}:{$takingFrom}:{$takingTo}", function () use ($db, $takingDate, $closeDate, $takingFrom, $takingTo) {
            $areaOf = $this->locationAreas($db);
            $area   = fn ($loc) => $areaOf[trim((string) $loc)] ?? self::UNKNOWN;

            $book = [];
            $invListNo = $db->table('SIM_INV_TAKING_BOOK_TAB')
                ->whereBetween('CREATED_DATETIME', [$takingDate, $closeDate])
                ->orderByRaw('CAST(INV_LIST_NO AS UNSIGNED) DESC')
                ->value('INV_LIST_NO');
            if ($invListNo !== null && $invListNo !== '') {
                $rows = $db->table('SIM_INV_TAKING_BOOK_TAB')
                    ->whereBetween('CREATED_DATETIME', [$takingDate, $closeDate])
                    ->where('INV_LIST_NO', $invListNo)
                    ->tap(fn ($q) => $this->eventScope($q, $db))
                    ->groupBy('LOCATION_NO')
                    ->select('LOCATION_NO', DB::raw('COUNT(*) as n'))
                    ->get();
                foreach ($rows as $r) {
                    $a = $area($r->LOCATION_NO);
                    $book[$a] = ($book[$a] ?? 0) + (int) $r->n;
                }
            }

            // [[area, modified by, reason, jumlah revisi], ...]
            $revisions = [];
            // Revisi = REV_NO > 1 (REV_NO bertambah 1 tiap revisi; baris REV_NO 1 = data awal)
            $rows = $db->table('SIM_INV_TAKING_DATA_REVISION_TAB')
                ->whereBetween('CREATED_DATETIME', [$takingFrom, $takingTo])
                ->where('REV_NO', '>', 1)
                ->tap(fn ($q) => $this->eventScope($q, $db))
                ->groupBy('LOCATION_NO', 'MODIFIED_BY', 'REASON_REVISION')
                ->select('LOCATION_NO', 'MODIFIED_BY', 'REASON_REVISION', DB::raw('COUNT(*) as n'))
                ->get();
            foreach ($rows as $r) {
                $modifier = trim((string) $r->MODIFIED_BY) ?: self::UNKNOWN;
                $reason   = trim((string) $r->REASON_REVISION) ?: self::UNKNOWN;
                $key      = $area($r->LOCATION_NO) . "\0{$modifier}\0{$reason}";

                $revisions[$key] ??= [$area($r->LOCATION_NO), $modifier, $reason, 0];
                $revisions[$key][3] += (int) $r->n;
            }

            return [
                'book'      => $book,
                'revisions' => array_values($revisions),
            ];
        });
    }

    /**
     * Data contoh untuk presentasi (halaman dibuka dengan ?demo=1): bentuknya sama dengan source(), tidak
     * membaca / menulis data taking dan tidak ditandai di halaman. Area dari Master Group Location;
     * angka acak tetapi selalu sama.
     */
    private function demoSource(): array
    {
        $areas = AreaName::list(FacdbtTable::active(DB::connection(self::CONN), FacdbtTable::GROUP_LOCATIONS)->distinct()->pluck('VLOCGROUP'))
            ?: ['PPC OIL', 'PPC-D', 'PPC-E', 'PPC-F', 'PROD-1A', 'PROD-1B', 'PROD-2A', 'PROD-2B', 'QA', 'SUPPLIER'];
        $modifiers = [
            'ARIF SUDARMAJI' => 30, 'ALFI IBRAHIM YAHYA' => 10, 'YOHANES ENDI ROSARI' => 7, 'DEMITRIS' => 6,
            'TRI WINARNO' => 4, 'SUDIBYO' => 3, 'SUSANTO WIBISONO K.' => 2, 'IRSYAD' => 1, 'PULUNG PUJIONO' => 1,
        ];
        $reasons = [
            'Revisi Qty' => 50, 'Revisi Part' => 37, 'Double Entry' => 10, 'Revisi Location' => 9,
            'Tools Error' => 1, 'Human Error' => 1,
        ];

        mt_srand(20260306);
        $book      = [];
        $revisions = [];
        foreach ($areas as $area) {
            $book[$area] = mt_rand(50, 1600);
            // Rata-rata 2% - 12% item direvisi per area
            $areaRevisions = (int) round($book[$area] * mt_rand(20, 120) / 1000);

            for ($i = 0; $i < $areaRevisions; $i++) {
                $revisions[] = [$area, self::weighted($modifiers), self::weighted($reasons), 1];
            }
        }
        mt_srand();

        return ['book' => $book, 'revisions' => $revisions];
    }

    /**
     * Pilih satu kunci secara acak sesuai bobotnya.
     */
    private static function weighted(array $weights): string
    {
        $pick = mt_rand(1, array_sum($weights));
        foreach ($weights as $key => $weight) {
            $pick -= $weight;
            if ($pick <= 0) {
                return (string) $key;
            }
        }

        return (string) array_key_first($weights);
    }

    /**
     * @return array<string, string> [location_no => location_group]; lokasi di beberapa group memakai group pertama
     */
    private function locationAreas($db): array
    {
        $map = [];
        foreach (FacdbtTable::active($db, FacdbtTable::GROUP_LOCATIONS)->orderBy('IID')->get(['VLOCATIONNO', 'VLOCGROUP']) as $r) {
            $loc   = trim((string) $r->VLOCATIONNO);
            $group = AreaName::of($r->VLOCGROUP);
            if ($group !== '' && !isset($map[$loc])) {
                $map[$loc] = $group;
            }
        }

        return $map;
    }

    /**
     * Persentase tiap nilai terhadap $total (total revisi, atau Item Book untuk % Revision), diurutkan dari terbesar.
     */
    private function shares(array $counts, int $total): array
    {
        arsort($counts);

        $result = [];
        foreach ($counts as $label => $count) {
            $result[] = [
                'label'   => (string) $label,
                'count'   => $count,
                'percent' => $total > 0 ? round($count / $total * 100, 1) : 0,
            ];
        }

        return $result;
    }
}
