<?php

namespace App\Services\FACDBT;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tabel detail Taking Data Revision (FACDBTD008): semua kolom SIM_INV_TAKING_DATA_REVISION_TAB + Area
 * (SIM_INV_GROUP_LOCATION.location_group), untuk seluruh periode taking dengan filter Year -> Month -> Event.
 * Dipakai DataTable dan export supaya isi tabel dan file selalu sama.
 */
class TakingRevisionTable
{
    private const CONN = 'INV_TAKING';

    /** @var array<string, string> [kolom (alias di query) => judul kolom] */
    public const COLUMNS = [
        'MONTH' => 'Month',
        'YEAR' => 'Year',
        'ID_TAKING' => 'ID Taking',
        'REV_NO' => 'Rev No',
        'CONTRACT' => 'Contract',
        'LOCATION_NO' => 'Location No',
        'LOCATION_NAME' => 'Location Name',
        'WAREHOUSE' => 'Warehouse',
        'LOCATION_GROUP' => 'Location Group',
        'area' => 'Area',
        'ID_SCANN' => 'ID Scann',
        'QTY' => 'Qty',
        'TAKING_DATE' => 'Taking Date',
        'PART_NO' => 'Part No',
        'DESCRIPTION' => 'Description',
        'DIM_QUALITY' => 'Dim Quality',
        'REVISE_DATE' => 'Revise Date',
        'NPK' => 'NPK',
        'ROWVERSION' => 'Rowversion',
        'ACTIVE_STATE' => 'Active State',
        'LOT_BATCH_NO' => 'Lot Batch No',
        'UNIT_MEAS' => 'Unit Meas',
        'INPUT_UNIT_MEAS_GROUP_ID' => 'Input Unit Meas Group ID',
        'UNIT_CODE' => 'Unit Code',
        'CONVERSION_FACTOR' => 'Conversion Factor',
        'CREATED_BY' => 'Created By',
        'CREATED_DATETIME' => 'Created Datetime',
        'MODIFIED_BY' => 'Modified By',
        'MODIFIED_DATETIME' => 'Modified Datetime',
        'HANDLING_UNIT_ID' => 'Handling Unit ID',
        'HANDLING_UNIT_TYPE' => 'Handling Unit Type',
        'WDR' => 'WDR',
        'REASON_REVISION' => 'Reason Revision',
    ];

    // Kolom yang dicari oleh kotak Search
    private const SEARCH_COLUMNS = [
        'r.LOCATION_NO', 'r.LOCATION_NAME', 'r.WAREHOUSE', 'r.PART_NO', 'r.DESCRIPTION', 'r.LOT_BATCH_NO',
        'r.NPK', 'r.CREATED_BY', 'r.MODIFIED_BY', 'r.REASON_REVISION', 'g.location_group',
    ];

    // Primary key gabungan dijadikan satu string untuk centang baris & export data terpilih
    public const ROW_KEY_SQL = "CONCAT_WS('|', r.MONTH, r.YEAR, r.ID_TAKING, r.REV_NO, r.CONTRACT, r.LOCATION_NO)";

    /**
     * Nama kolom database untuk alias di COLUMNS (dipakai pencarian & urutan DataTable).
     */
    public static function columnName(string $column): string
    {
        return $column === 'area' ? 'g.location_group' : "r.{$column}";
    }

    /**
     * Query dasar: revisi + area, difilter periode (`year`, `month`, `taking_date`) dan Search (`keyword`).
     */
    public function query(Request $request): Builder
    {
        $db = DB::connection(self::CONN);

        // Area per lokasi: location_group pertama (IID terkecil) yang tidak kosong
        $areas = $db->table('SIM_INV_GROUP_LOCATION as gl')
            ->whereIn('gl.IID', fn ($q) => $q->from('SIM_INV_GROUP_LOCATION')
                ->selectRaw('MIN(IID)')
                ->whereRaw("TRIM(location_group) <> ''")
                ->groupBy('LOCATION_NO'))
            ->select('gl.LOCATION_NO', DB::raw(AreaName::sql('gl.location_group') . ' as location_group'));

        $select = [DB::raw(self::ROW_KEY_SQL . ' as row_key')];
        foreach (array_keys(self::COLUMNS) as $column) {
            $select[] = $column === 'area' ? 'g.location_group as area' : "r.{$column}";
        }

        $year       = $request->input('year');
        $month      = $request->input('month');
        $takingDate = $request->input('taking_date');
        $keyword    = trim((string) $request->input('keyword', ''));

        return $db->table('SIM_INV_TAKING_DATA_REVISION_TAB as r')
            ->leftJoinSub($areas, 'g', 'g.LOCATION_NO', '=', 'r.LOCATION_NO')
            ->select($select)
            ->when($year, fn ($q) => $q->where('r.YEAR', (int) $year))
            ->when($month, fn ($q) => $q->where('r.MONTH', (int) $month))
            ->when($takingDate, function ($q) use ($db, $takingDate) {
                // Event = satu periode taking: data yang dibuat di rentang TAKING_DATE s/d CLOSE_DATE
                $period = $db->table('SIM_INV_TAKING_CONCLUDING_TAB')
                    ->whereDate('TAKING_DATE', Carbon::parse($takingDate)->toDateString())
                    ->first(['TAKING_DATE', 'CLOSE_DATE']);
                if ($period) {
                    $q->whereBetween('r.CREATED_DATETIME', [
                        Carbon::parse($period->TAKING_DATE)->startOfDay()->format('Y-m-d H:i:s'),
                        Carbon::parse($period->CLOSE_DATE)->endOfDay()->format('Y-m-d H:i:s'),
                    ]);
                }
            })
            ->when($keyword !== '', fn ($q) => $q->whereAny(self::SEARCH_COLUMNS, 'LIKE', "%{$keyword}%"));
    }

    /**
     * Daftar periode taking untuk filter Year -> Month -> Event; satu bulan bisa punya lebih dari satu event.
     */
    public function periods(): array
    {
        $periods = DB::connection(self::CONN)
            ->table('SIM_INV_TAKING_CONCLUDING_TAB')
            ->select('YEAR', 'MONTH', 'TAKING_DATE', 'CLOSE_DATE')
            ->whereNotNull('TAKING_DATE')
            ->orderByDesc('YEAR')
            ->orderByDesc('MONTH')
            ->orderBy('TAKING_DATE')
            ->get();

        $events = [];
        foreach ($periods->groupBy(fn ($p) => $p->YEAR . '-' . $p->MONTH) as $items) {
            foreach ($items->values() as $i => $p) {
                $taking = Carbon::parse($p->TAKING_DATE);
                $close  = $p->CLOSE_DATE ? Carbon::parse($p->CLOSE_DATE) : null;
                $range  = match (true) {
                    $close === null           => $taking->format('j M') . ' – now',
                    $taking->isSameDay($close) => $taking->format('j M'),
                    default                   => $taking->format('j') . '–' . $close->format('j M'),
                };

                $events[] = [
                    'year'        => (int) $p->YEAR,
                    'month'       => (int) $p->MONTH,
                    'taking_date' => $taking->toDateString(),
                    'label'       => 'Taking ' . ($i + 1) . ' · ' . $range,
                ];
            }
        }

        return $events;
    }
}
