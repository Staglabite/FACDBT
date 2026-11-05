<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT01;

use App\DataTables\Original\FACDBT01\FACDBTF006DataTable;
use App\Exports\FACDBT\FACDBT01\MasterDataExport;
use App\Helpers\Response;
use App\Http\Controllers\Controller;
use App\Http\Requests\FACDBT\FACDBT01\TakingEventPartImportRequest;
use App\Http\Requests\FACDBT\FACDBT01\TakingEventRequest;
use App\Imports\FACDBT\FACDBT01\FACDBTF006 as Import;
use App\Models\FACDBT01\FACDBT_SIMINVTAKINGEVENT as TakingEventModel;
use App\Models\FACDBT01\FACDBT_SIMINVTAKINGEVENTPART as TakingEventPart;
use App\Services\FACDBT\FacdbtTable;
use App\Services\FACDBT\TakingEvent;
use App\Services\FACDBT\TakingHistoryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel as FacadesExcel;

/**
 * Master Event: jenis taking tiap periode, menentukan data yang dihitung dashboard (lihat TakingEvent).
 * Event Pre-Taking punya daftar part (import CSV / Excel kolom "Part No" & "Location No"); hanya pasangan part +
 * lokasi itu yang dihitung dashboard.
 */
class FACDBTF006 extends Controller
{
    private const CONN = 'FACDBT';

    private const PART_TITLE = 'Part No';
    private const LOCATION_TITLE = 'Location No';

    public function index(FACDBTF006DataTable $dataTable, TakingHistoryService $history)
    {
        // Daftar part Pre-Taking yang periodenya sudah ditutup & tersimpan di history dikosongkan
        if (!request()->ajax()) {
            try {
                $history->clearFinishedEventParts();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if (request()->boolean('ids_only')) {
            return response()->json(['ids' => $dataTable->filteredKeys('IID')]);
        }

        return $dataTable->render('modules.FACDBT.FACDBT01.FACDBTF006.FACDBTF006', [
            'types' => TakingEvent::TYPES,
        ]);
    }

    /**
     * Pilihan periode (TAKING_DATE) dari SIM_INV_TAKING_CONCLUDING_TAB, terbaru dulu.
     */
    public function periods()
    {
        $events = TakingEventModel::query()->pluck('IID', 'DTAKINGDATE')
            ->mapWithKeys(fn ($id, $date) => [Carbon::parse($date)->toDateString() => $id]);

        $periods = DB::connection(self::CONN)->table('SIM_INV_TAKING_CONCLUDING_TAB')
            ->whereNotNull('TAKING_DATE')
            ->orderByDesc('TAKING_DATE')
            ->limit(100)
            ->get(['MONTH', 'YEAR', 'TAKING_DATE', 'CLOSE_DATE', 'ROWSTATE'])
            ->map(function ($p) use ($events) {
                $date = Carbon::parse($p->TAKING_DATE)->toDateString();

                return [
                    'taking_date' => $date,
                    'close_date'  => $p->CLOSE_DATE ? Carbon::parse($p->CLOSE_DATE)->toDateString() : null,
                    'month'       => $p->MONTH,
                    'year'        => $p->YEAR,
                    'rowstate'    => $p->ROWSTATE,
                    'event_id'    => $events[$date] ?? null,
                ];
            })
            ->unique('taking_date')
            ->values();

        return response()->json(['data' => $periods]);
    }

    public function store(TakingEventRequest $request)
    {
        // Periode yang event-nya pernah dihapus: baris lama dipulihkan dengan data baru
        TakingEventModel::saveRestoring(['DTAKINGDATE' => $request->DTAKINGDATE], $this->fields($request));

        return Response::success(message: 'Event created successfully');
    }

    public function show($id)
    {
        $event = TakingEventModel::withCount('parts')->find($id);

        if (!$event) {
            return response()->json(['status' => 'error', 'message' => 'Event not found'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $event]);
    }

    public function update(TakingEventRequest $request, TakingEventModel $event)
    {
        $event->update($this->fields($request));

        return Response::success(message: 'Event updated successfully');
    }

    public function destroy(TakingEventModel $event)
    {
        try {
            $this->deleteEvents([$event->IID]);

            return Response::success(message: 'Event deleted successfully');
        } catch (\Throwable $th) {
            return Response::error(message: 'Event cannot be deleted');
        }
    }

    public function destroyMultiple(Request $request)
    {
        try {
            $this->deleteEvents((array) $request->input('ids', []));

            return Response::success(message: 'Selected events deleted successfully');
        } catch (\Throwable $th) {
            return Response::error(message: 'Event cannot be deleted');
        }
    }

    /**
     * Ganti daftar part Pre-Taking event dengan isi file (CSV / Excel, kolom "Part No" & "Location No").
     * Location No yang tidak ada di Master Location tetap disimpan, dan dikembalikan sebagai peringatan.
     */
    public function importParts(TakingEventPartImportRequest $request, TakingEventModel $event)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        try {
            $import = new Import;
            FacadesExcel::import($import, $request->file('file'));
        } catch (\Throwable $e) {
            return Response::error(message: 'Error reading the file: ' . $e->getMessage());
        }

        if (!$import->hasColumns()) {
            return response()->json(['success' => false, 'message' => 'Column "' . self::PART_TITLE . '" and "' . self::LOCATION_TITLE . '" are required. Please use the template.'], 422);
        }
        if (!empty($import->errors())) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error in ' . count($import->errors()) . ' record(s)',
                'data' => ['error_data' => $import->errors()],
            ], 400);
        }

        $parts = $import->parts();
        if (empty($parts)) {
            return response()->json(['success' => false, 'message' => 'No part found in the file.'], 422);
        }

        $count = $this->replaceParts($event, $parts);

        return Response::success(
            data: ['unknown_locations' => $this->unknownLocations(array_column($parts, 1))],
            message: 'Part list replaced with ' . number_format($count) . ' part / location row(s)'
        );
    }

    /**
     * Location No yang tidak ada (aktif) di Master Location.
     *
     * @param  array<int, string>  $locations
     * @return array<int, string>
     */
    private function unknownLocations(array $locations): array
    {
        $wanted = [];
        foreach ($locations as $loc) {
            $wanted[mb_strtolower($loc)] ??= $loc;
        }

        $known = [];
        foreach (array_chunk(array_values($wanted), 1000) as $chunk) {
            foreach (FacdbtTable::active(DB::connection(self::CONN), FacdbtTable::LOCATIONS)->whereIn('VLOCATIONNO', $chunk)->pluck('VLOCATIONNO') as $loc) {
                $known[mb_strtolower(trim((string) $loc))] = true;
            }
        }

        $unknown = array_values(array_diff_key($wanted, $known));
        sort($unknown);

        return $unknown;
    }

    /**
     * Daftar part Pre-Taking event dalam CSV (format sama dengan template, bisa di-import kembali).
     */
    public function exportParts(TakingEventModel $event)
    {
        $query = TakingEventPart::query()->where('IEVENTID', $event->IID)->orderBy('VPARTNO')->orderBy('VLOCATIONNO');

        return (new MasterDataExport($query, ['VPARTNO' => self::PART_TITLE, 'VLOCATIONNO' => self::LOCATION_TITLE]))
            ->download('Event_Part_' . Carbon::parse($event->DTAKINGDATE)->format('Ymd') . '.csv');
    }

    public function downloadTemplate()
    {
        return MasterDataExport::template([self::PART_TITLE, self::LOCATION_TITLE], 'Event_Part_Template.csv');
    }

    private function fields(Request $request): array
    {
        return $request->only(['DTAKINGDATE', 'VEVENTTYPE', 'VDESC']);
    }

    /**
     * Ganti daftar part event (soft delete): pasangan part + lokasi yang tidak ada di file dihapus, yang pernah
     * dihapus dan ada lagi di file dipulihkan, yang baru ditambahkan.
     *
     * @param  array<string, array{0: string, 1: string}>  $wanted  [kunci => [part_no, location_no]] (Import::key())
     * @return int jumlah baris part aktif
     */
    private function replaceParts(TakingEventModel $event, array $wanted): int
    {
        DB::connection(self::CONN)->transaction(function () use ($event, $wanted) {
            $table = fn () => DB::connection(self::CONN)->table(TakingEvent::PART_TABLE)->where('IEVENTID', $event->IID);
            $user  = FacdbtTable::user();
            $now   = now()->format('Y-m-d H:i:s');

            // [part|lokasi => [IID, sudah dihapus?]] termasuk baris yang sudah dihapus
            $existing = [];
            foreach ($table()->get(['IID', 'VPARTNO', 'VLOCATIONNO', 'DDELETE']) as $row) {
                $existing[Import::key($row->VPARTNO, $row->VLOCATIONNO)] = [$row->IID, $row->DDELETE !== null];
            }

            $remove  = [];
            $restore = [];
            foreach ($existing as $part => [$id, $deleted]) {
                if (!isset($wanted[$part]) && !$deleted) {
                    $remove[] = $id;
                } elseif (isset($wanted[$part]) && $deleted) {
                    $restore[] = $id;
                }
            }

            foreach (array_chunk($remove, 1000) as $ids) {
                $table()->whereIn('IID', $ids)->update(FacdbtTable::deleteValues($user));
            }
            foreach (array_chunk($restore, 1000) as $ids) {
                $table()->whereIn('IID', $ids)->update([
                    FacdbtTable::DELETED_AT => null,
                    FacdbtTable::DELETED_BY => null,
                    'VMODI' => $user,
                    'DMODI' => $now,
                ]);
            }

            $new = array_diff_key($wanted, $existing);
            foreach (array_chunk(array_values($new), 1000) as $chunk) {
                DB::connection(self::CONN)->table(TakingEvent::PART_TABLE)->insert(array_map(fn ($line) => [
                    'IEVENTID' => $event->IID,
                    'VPARTNO' => $line[0],
                    'VLOCATIONNO' => $line[1],
                    'VCREA' => $user,
                    'DCREA' => $now,
                ], $chunk));
            }

            $event->touchAudit();
        });

        return count($wanted);
    }

    /**
     * Soft delete event beserta daftar part-nya.
     */
    private function deleteEvents(array $ids): void
    {
        DB::connection(self::CONN)->transaction(function () use ($ids) {
            DB::connection(self::CONN)->table(TakingEvent::PART_TABLE)
                ->whereIn('IEVENTID', $ids)
                ->whereNull(FacdbtTable::DELETED_AT)
                ->update(FacdbtTable::deleteValues());
            TakingEventModel::softDeleteKeys($ids);
        });
    }
}
