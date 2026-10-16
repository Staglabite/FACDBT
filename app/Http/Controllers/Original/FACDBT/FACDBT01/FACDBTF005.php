<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT01;

use App\DataTables\Original\FACDBT01\FACDBTF005DataTable;
use App\Exports\Templates\FACDBT\FACDBT01\FACDBTF005 as Template;
use App\Exports\FACDBT\FACDBT01\MasterDataExport;
use App\Helpers\Response;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Original\FACDBT\FACDBT01\Concerns\SavesImportRows;
use App\Http\Requests\FACDBT\FACDBT01\GroupLocationImportRequest;
use App\Http\Requests\FACDBT\FACDBT01\GroupLocationRequest;
use App\Imports\FACDBT\FACDBT01\FACDBTF005 as Import;
use App\Models\FACDBT01\FACDBT_SIMINVGROUPLOCATION as GroupLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel as FacadesExcel;

// Group Location controller
class FACDBTF005 extends Controller
{
    use SavesImportRows;

    public function index(FACDBTF005DataTable $dataTable)
    {
        // "Centang semua": cukup kirim ID baris yang lolos filter, bukan seluruh isi tabel
        if (request()->boolean('ids_only')) {
            return response()->json(['ids' => $dataTable->filteredKeys('IID')]);
        }

        return $dataTable->render('modules.FACDBT.FACDBT01.FACDBTF005.FACDBTF005');
    }

    public function store(GroupLocationRequest $request)
    {
        GroupLocation::create([
            'LOCATION_NO' => $request->LOCATION_NO,
            'ID_LOC' => $request->ID_LOC,
            'location_group' => $request->location_group,
            'stream' => $request->stream,
        ]);

        return Response::success(message: 'Group Location created successfully');
    }

    public function show($id)
    {
        // Cegah pencarian jika ID yang dikirim JS adalah string 'undefined'
        if ($id === 'undefined' || empty($id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid ID provided'
            ], 400);
        }

        $groupLocation = GroupLocation::where('IID', $id)->first();

        if (!$groupLocation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Group Location not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $groupLocation
        ]);
    }

    public function update(GroupLocationRequest $request, GroupLocation $grouplocation)
    {
        $grouplocation->update([
            'LOCATION_NO' => $request->LOCATION_NO,
            'ID_LOC' => $request->ID_LOC,
            'location_group' => $request->location_group,
            'stream' => $request->stream,
        ]);

        return Response::success(message: 'Group Location updated successfully');
    }

    public function destroy(GroupLocation $grouplocation)
    {
        try {
            $grouplocation->delete();

            return Response::success(message: 'Group Location data deleted successfully');
        } catch (\Throwable $th) {
            return Response::error(message: 'Group Location data cannot be deleted, it may be related to other data');
        }
    }

    public function destroyMultiple(Request $request)
    {
        $ids = $request->ids;

        try {
            GroupLocation::whereIn('IID', $ids)->delete();

            return Response::success(message: 'Selected group locations deleted successfully');
        } catch (\Throwable $th) {
            return Response::error(message: 'Group Location data cannot be deleted, it may be related to other data');
        }
    }

    /**
     * Export CSV: data yang dicentang saja, atau semua data bila tidak ada yang dicentang.
     */
    public function export(Request $request)
    {
        $ids = array_values(array_filter((array) $request->input('ids', []), fn ($id) => $id !== null && $id !== ''));

        $query = GroupLocation::query()
            ->when(!empty($ids), fn ($q) => $q->whereIn('IID', $ids))
            ->orderBy('location_group')->orderBy('LOCATION_NO')->orderBy('IID');

        return (new MasterDataExport($query, [
            'LOCATION_NO' => 'Location No',
            'ID_LOC' => 'ID Loc',
            'location_group' => 'Location Group',
            'stream' => 'Stream',
        ]))->download('Master_Group_Location-' . date('YmdHis') . '.csv');
    }

    public function downloadTemplate()
    {
        return FacadesExcel::download(new Template, 'Master_GroupLocation_Template.xlsx');
    }

    public function import(GroupLocationImportRequest $request)
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

            $cleaned = $import->store();
            if (empty($cleaned)) {
                return response()->json(['success' => false, 'message' => 'No valid data to import.'], 400);
            }

            DB::connection('INV_TAKING')->beginTransaction();

            try {
                // Kombinasi LOCATION_NO, ID_LOC & location_group yang sudah ada tidak ditambah lagi,
                // hanya stream-nya yang ditimpa; kombinasi baru dibuat sebagai baris baru.
                [$insertedCount, $updatedCount] = $this->saveImportRows(
                    new GroupLocation,
                    $cleaned,
                    ['LOCATION_NO', 'ID_LOC', 'location_group']
                );

                DB::connection('INV_TAKING')->commit();

                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported {$insertedCount} new and updated {$updatedCount} existing group location(s).",
                    'data' => [
                        'total_imported' => $insertedCount,
                        'total_updated' => $updatedCount,
                        'total_processed' => count($cleaned),
                    ],
                ], 200);
            } catch (\Exception $e) {
                DB::connection('INV_TAKING')->rollBack();

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
