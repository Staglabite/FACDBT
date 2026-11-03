<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT01;

use App\DataTables\Original\FACDBT01\FACDBTF001DataTable;
use App\Exports\FACDBT\FACDBT01\MasterDataExport;
use App\Helpers\Response;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Original\FACDBT\FACDBT01\Concerns\SavesImportRows;
use App\Http\Requests\FACDBT\FACDBT01\LocationImportRequest;
use App\Http\Requests\FACDBT\FACDBT01\LocationRequest;
use App\Imports\FACDBT\FACDBT01\FACDBTF001 as Import;
use App\Models\FACDBT01\FACDBT_MSHLOCATION as Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel as FacadesExcel;

// Location controller
class FACDBTF001 extends Controller
{
    use SavesImportRows;

    // Kolom form / tabel FACDBT_MSHLOCATIONS
    private const FIELDS = ['VLOCATIONNO', 'VLOCGROUP', 'VSITE', 'VWAREHOUSE', 'VLOCDESC', 'VLOCGROUPDESC'];

    // Kolom export CSV [kolom tabel => judul]; judul yang sama dipakai template import
    private const EXPORT_COLUMNS = [
        'VLOCATIONNO' => 'Location No',
        'VLOCGROUP' => 'Location Group',
        'VSITE' => 'Site',
        'VWAREHOUSE' => 'Warehouse',
        'VLOCDESC' => 'Location Description',
        'VLOCGROUPDESC' => 'Location Group Description',
    ];

    // Kolom hasil import (slug judul kolom Excel) => kolom tabel
    private const IMPORT_COLUMNS = [
        'location_no' => 'VLOCATIONNO',
        'location_group' => 'VLOCGROUP',
        'site' => 'VSITE',
        'warehouse' => 'VWAREHOUSE',
        'location_description' => 'VLOCDESC',
        'location_group_description' => 'VLOCGROUPDESC',
    ];

    public function index(FACDBTF001DataTable $dataTable)
    {
        // "Centang semua": cukup kirim ID baris yang lolos filter, bukan seluruh isi tabel
        if (request()->boolean('ids_only')) {
            return response()->json(['ids' => $dataTable->filteredKeys('IID')]);
        }

        return $dataTable->render('modules.FACDBT.FACDBT01.FACDBTF001.FACDBTF001');
    }

    public function store(LocationRequest $request)
    {
        // Location No yang pernah dihapus dipulihkan dengan data baru
        Location::saveRestoring(['VLOCATIONNO' => $request->VLOCATIONNO], $request->only(self::FIELDS));

        return Response::success(message: 'Location created successfully');
    }

    public function show($id)
    {
        $location = Location::find($id);

        if (!$location) {
            return response()->json([
                'status' => 'error',
                'message' => 'Location not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $location
        ]);
    }

    public function update(LocationRequest $request, Location $location)
    {
        $location->update($request->only(self::FIELDS));

        return Response::success(message: 'Location updated successfully');
    }

    public function destroy(Location $location)
    {
        // Mengecek relasi dengan 'lines' (FACDBT_MASHSUBAREALINES) sebelum menghapus
        if ($location->lines()->count() > 0) {
            return Response::error(
                message: "Location {$location->VLOCDESC} can't be deleted because it still has related sub area lines.",
                status: 422
            );
        }

        try {
            $location->delete();

            return Response::success(message: 'Location data deleted successfully');
        } catch (\Throwable $th) {
            return Response::error(message: 'Location data cannot be deleted, it may be related to other data');
        }
    }

    public function destroyMultiple(Request $request)
    {
        $ids = (array) $request->ids;

        // Pengecekan relasi lines untuk multiple delete
        $relatedLocations = Location::query()
            ->withCount('lines')
            ->whereKey($ids)
            ->get()
            ->filter(fn ($location) => ($location->lines_count ?? 0) > 0)
            ->pluck('VLOCDESC');

        if ($relatedLocations->isNotEmpty()) {
            return Response::error(
                message: "Cannot delete selected locations because some still have related sub area lines: {$relatedLocations->implode(', ')}",
                status: 422
            );
        }

        try {
            Location::softDeleteKeys($ids);

            return Response::success(message: 'Selected locations deleted successfully');
        } catch (\Throwable $th) {
            return Response::error(message: 'Location data cannot be deleted, it may be related to other data');
        }
    }

    /**
     * Export CSV: data yang dicentang saja, atau semua data bila tidak ada yang dicentang.
     */
    public function export(Request $request)
    {
        $ids = array_values(array_filter((array) $request->input('ids', []), fn ($id) => $id !== null && $id !== ''));

        $query = Location::query()
            ->when(!empty($ids), fn ($q) => $q->whereKey($ids))
            ->orderBy('VLOCATIONNO');

        return (new MasterDataExport($query, self::EXPORT_COLUMNS))->download('Master_Location-' . date('YmdHis') . '.csv');
    }

    public function downloadTemplate()
    {
        // Template CSV (judul kolom sama dengan export); template yang diisi / file export bisa langsung di-import
        return MasterDataExport::template(array_values(self::EXPORT_COLUMNS), 'Master_Location_Template.csv');
    }

    public function import(LocationImportRequest $request)
    {
        // File besar butuh waktu lebih dari batas default PHP (30 detik)
        set_time_limit(300);

        try {
            $import = new Import;
            // Jenis file (xlsx / xls / csv) dideteksi dari ekstensi file yang diupload
            FacadesExcel::import($import, $request->file('file'));

            $result = $import->getResult();
            if ($result->totalError > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Validation error in {$result->totalErrorLogs} out of {$result->totalLogs} records",
                    'data' => [
                        'total_data' => $result->totalLogs,
                        'total_error_data' => $result->totalErrorLogs,
                        'total_error' => $result->totalError,
                        'error_data' => $result->errorLogs->values(),
                    ],
                ], 400);
            }

            $cleaned = array_map(
                fn ($row) => array_combine(array_values(self::IMPORT_COLUMNS), array_map(fn ($column) => $row[$column] ?? null, array_keys(self::IMPORT_COLUMNS))),
                $import->store()
            );
            if (empty($cleaned)) {
                return response()->json(['success' => false, 'message' => 'No valid data to import.'], 400);
            }

            $db = DB::connection('FACDBT');
            $db->beginTransaction();

            try {
                // Location No sudah ada (termasuk yang pernah dihapus) -> ditimpa, belum ada -> dibuat baru
                [$insertedCount, $updatedCount] = $this->saveImportRows(new Location, $cleaned, ['VLOCATIONNO']);

                $db->commit();

                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported {$insertedCount} new and updated {$updatedCount} existing location(s).",
                    'data' => [
                        'total_imported' => $insertedCount,
                        'total_updated' => $updatedCount,
                        'total_processed' => count($cleaned),
                    ],
                ], 200);
            } catch (\Exception $e) {
                $db->rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to import data: ' . $e->getMessage(),
                ], 500);
            }
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            return Response::error(message: 'Error reading the Excel file: ' . $e->getMessage());
        }
    }
}
