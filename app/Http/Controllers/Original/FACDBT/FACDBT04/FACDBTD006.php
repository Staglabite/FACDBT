<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04;

use App\Services\FACDBT\AreaName;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns\CachesDashboard;
use App\Services\FACDBT\RunningTakingPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard Taking Data Revision: revisi data taking (SIM_INV_TAKING_DATA_REVISION_TAB) periode yang sedang berjalan.
 *
 * - Taking Data Revision : per area, Item_Book (jumlah baris book) dan % Area (jumlah revisi / Item_Book x 100)
 * - Reason Revision      : porsi revisi per REASON_REVISION
 * - Revision Performance : porsi revisi per MODIFIED_BY
 * Area = SIM_INV_GROUP_LOCATION.location_group dari LOCATION_NO. Filter dengan klik chart (lihat getData()).
 */
class FACDBTD006 extends Controller
{
    use CachesDashboard;

    private const CONN = 'INV_TAKING';
    private const CACHE_TTL = 60;

    private const UNKNOWN = 'UNKNOWN';

    public function index()
    {
        return view('modules.FACDBT.FACDBT04.FACDBTD006.FACDBTD006');
    }

    /**
     * Data semua chart dengan filter interaktif `area` (klik chart Taking Data Revision) dan `modifier`
     * (klik chart Revision Performance); kosong = semua. Chart yang menjadi sumber filter tetap menampilkan
     * semua kategorinya (yang lain dipudarkan di browser):
     * - Taking Data Revision : semua area, jumlah revisi difilter modifier
     * - Revision Performance : semua modifier, difilter area
     * - Reason Revision      : difilter area & modifier
     */
    public function getData(Request $request)
    {
        $source = $this->source();

        $area     = trim((string) $request->input('area', ''));
        $modifier = trim((string) $request->input('modifier', ''));

        $revisionsPerArea = [];
        $perReason        = [];
        $perModifier      = [];
        $reasonTotal      = 0;
        $modifierTotal    = 0;
        foreach ($source['revisions'] as [$rowArea, $rowModifier, $reason, $count]) {
            $areaMatch     = $area === '' || $area === $rowArea;
            $modifierMatch = $modifier === '' || $modifier === $rowModifier;

            if ($modifierMatch) {
                $revisionsPerArea[$rowArea] = ($revisionsPerArea[$rowArea] ?? 0) + $count;
            }
            if ($areaMatch) {
                $perModifier[$rowModifier] = ($perModifier[$rowModifier] ?? 0) + $count;
                $modifierTotal += $count;
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
            'revision_performance' => $this->shares($perModifier, $modifierTotal),
        ]);
    }

    /**
     * Data dasar periode berjalan (di-cache; dihitung ulang bila ada scan / revisi / book baru):
     * jumlah baris book per area dan jumlah revisi per (area, modified by, reason).
     */
    private function source(): array
    {
        $empty = ['book' => [], 'revisions' => []];

        $db     = DB::connection(self::CONN);
        $period = RunningTakingPeriod::find($db);
        if (!$period) {
            return $empty;
        }

        $takingDate = Carbon::parse($period->TAKING_DATE)->startOfDay()->format('Y-m-d H:i:s');
        $closeDate  = Carbon::parse($period->CLOSE_DATE)->endOfDay()->format('Y-m-d H:i:s');

        return $this->dashboardCache("FACDBTD006:source:v4:{$takingDate}:{$closeDate}", function () use ($db, $takingDate, $closeDate) {
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
            // Satu revisi = REV_NO bertambah 1, jadi baris REV_NO 0 (data awal) tidak dihitung
            $rows = $db->table('SIM_INV_TAKING_DATA_REVISION_TAB')
                ->whereBetween('CREATED_DATETIME', [$takingDate, $closeDate])
                ->where('REV_NO', '>', 0)
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
     * @return array<string, string> [location_no => location_group]; lokasi di beberapa group memakai group pertama
     */
    private function locationAreas($db): array
    {
        $map = [];
        foreach ($db->table('SIM_INV_GROUP_LOCATION')->orderBy('IID')->get(['LOCATION_NO', 'location_group']) as $r) {
            $loc   = trim((string) $r->LOCATION_NO);
            $group = AreaName::of($r->location_group);
            if ($group !== '' && !isset($map[$loc])) {
                $map[$loc] = $group;
            }
        }

        return $map;
    }

    /**
     * Porsi tiap nilai terhadap total revisi, diurutkan dari terbesar.
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
