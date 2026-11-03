<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT01;

use App\DataTables\Original\FACDBT01\FACDBTF005DataTable;
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

    // Kolom export CSV [kolom tabel => judul]; judul yang sama dipakai template import
    private const EXPORT_COLUMNS = [
        'VLOCATIONNO' => 'Location No',
        'VIDLOC' => 'ID Loc',
        'VLOCGROUP' => 'Location Group',
        'VSTREAM' => 'Stream',
    ];

    // Kolom form yang disimpan (tabel FACDBT_MSHGROUPLOCATIONS)
    private const FIELDS = ['VLOCATIONNO', 'VIDLOC', 'VLOCGROUP', 'VSTREAM'];

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
        // Kombinasi Location No, ID Loc & Location Group yang pernah dihapus dipulihkan dengan data baru
        GroupLocation::saveRestoring(
            $request->only(['VLOCATIONNO', 'VIDLOC', 'VLOCGROUP']),
            $request->only(self::FIELDS)
        );

        return Response::success(message: 'Group Location created successfully');
    }

    public function show($id)
    {
        $groupLocation = GroupLocation::find($id);

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
        $grouplocation->update($request->only(self::FIELDS));

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
        $ids = (array) $request->ids;

        try {
            GroupLocation::softDeleteKeys($ids);

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
            ->when(!empty($ids), fn ($q) => $q->whereKey($ids))
            ->orderBy('VLOCGROUP')->orderBy('VLOCATIONNO')->orderBy('IID');

        return (new MasterDataExport($query, self::EXPORT_COLUMNS))->download('Master_Group_Location-' . date('YmdHis') . '.csv');
    }

    public function downloadTemplate()
    {
        // Template CSV (judul kolom sama dengan export); template yang diisi / file export bisa langsung di-import
        return MasterDataExport::template(array_values(self::EXPORT_COLUMNS), 'Master_Group_Location_Template.csv');
    }

    public function import(GroupLocationImportRequest $request)
    {
        // File besar butuh waktu lebih dari batas default PHP (30 detik)
        set_time_limit(300);
        // File besar: daftar baris & hasil validasi disimpan di memori sampai seluruh file selesai dibaca
        ini_set('memory_limit', '512M');

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
            // Hasil baca file (semua baris + log validasi) tidak dipakai lagi; lepas dari memori sebelum menyimpan
            unset($import, $result);

            DB::connection('FACDBT')->beginTransaction();

            try {
                // Kombinasi Location No, ID Loc & Location Group yang sudah ada (termasuk yang pernah dihapus)
                // tidak ditambah lagi, hanya stream-nya yang ditimpa; kombinasi baru dibuat sebagai baris baru.
                [$insertedCount, $updatedCount] = $this->saveImportRows(
                    new GroupLocation,
                    $cleaned,
                    ['VLOCATIONNO', 'VIDLOC', 'VLOCGROUP']
                );

                DB::connection('FACDBT')->commit();

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
                DB::connection('FACDBT')->rollBack();

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
