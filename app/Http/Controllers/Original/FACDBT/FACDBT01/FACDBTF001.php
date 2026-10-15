<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT01;

use App\DataTables\Original\FACDBT01\FACDBTF001DataTable;
use App\Exports\Templates\FACDBT\FACDBT01\FACDBTF001 as Template;
use App\Exports\FACDBT\FACDBT01\MasterDataExport;
use App\Helpers\Response;
use App\Http\Controllers\Controller;
use App\Http\Requests\FACDBT\FACDBT01\LocationImportRequest;
use App\Http\Requests\FACDBT\FACDBT01\LocationRequest;
use App\Imports\FACDBT\FACDBT01\FACDBTF001 as Import;
use App\Models\FACDBT01\FACDBT_MSHLOCATION as Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as FacadesExcel;

// Location controller
class FACDBTF001 extends Controller
{
    public function index(FACDBTF001DataTable $dataTable)
    {
        return $dataTable->render('modules.FACDBT.FACDBT01.FACDBTF001.FACDBTF001');
    }

    public function store(LocationRequest $request)
    {
        Location::create([
            'location_no' => $request->location_no,
            'location_group' => $request->location_group,
            'site' => $request->site,
            'warehouse' => $request->warehouse,
            'location_description' => $request->location_description,
            'location_group_description' => $request->location_group_description,
        ]);

        return Response::success(message: 'Location created successfully');
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

        // Gunakan where secara eksplisit untuk primary key string
        $location = Location::where('location_no', $id)->first();

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
        $location->update([
            'location_no' => $request->location_no,
            'location_group' => $request->location_group,
            'site' => $request->site,
            'warehouse' => $request->warehouse,
            'location_description' => $request->location_description,
            'location_group_description' => $request->location_group_description,
        ]);

        return Response::success(message: 'Location updated successfully');
    }

    public function destroy(Location $location)
    {
        // Mengecek relasi dengan 'lines' (FACDBT_MASHSUBAREALINES) sebelum menghapus
        $lineCount = $location->lines()->count();

        if ($lineCount > 0) {
            return Response::error(
                message: "Location {$location->location_description} can't be deleted because it still has related sub area lines.",
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
        $ids = $request->ids;

        // Pengecekan relasi lines untuk multiple delete
        $relatedLocations = Location::query()
            ->withCount('lines')
            ->whereIn('location_no', $ids) // Menggunakan location_no sebagai identifier
            ->get()
            ->filter(fn($location) => ($location->lines_count ?? 0) > 0)
            ->pluck('location_description'); // Mengambil nama deskripsi untuk pesan error

        if ($relatedLocations->isNotEmpty()) {
            return Response::error(
                message: "Cannot delete selected locations because some still have related sub area lines: {$relatedLocations->implode(', ')}",
                status: 422
            );
        }

        try {
            Location::whereIn('location_no', $ids)->delete();

            return Response::success(message: 'Selected locations deleted successfully');
        } catch (\Throwable $th) {
            return Response::error(message: 'Location data cannot be deleted, it may be related to other data');
        }
    }

    /**
     * Export Excel: data yang dicentang saja, atau semua data bila tidak ada yang dicentang.
     */
    public function export(Request $request)
    {
        $ids = array_values(array_filter((array) $request->input('ids', []), fn ($id) => $id !== null && $id !== ''));

        $query = Location::query()
            ->when(!empty($ids), fn ($q) => $q->whereIn('location_no', $ids))
            ->orderBy('location_no');

        return FacadesExcel::download(new MasterDataExport($query, [
            'location_no' => 'Location No',
            'location_group' => 'Location Group',
            'site' => 'Site',
            'warehouse' => 'Warehouse',
            'location_description' => 'Description',
            'location_group_description' => 'Group Description',
        ]), 'Master_Location-' . date('YmdHis') . '.xlsx');
    }

    public function downloadTemplate()
    {
        return FacadesExcel::download(new Template, 'Master_Location_Template.xlsx');
    }

    public function import(LocationImportRequest $request)
    {
        try {
            $import = new Import; // Pastikan class import Anda benar
            FacadesExcel::import($import, $request->file, Excel::XLSX);

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

            DB::beginTransaction();

            try {
                // MENGGANTI FOREACH DAN CREATE MENJADI UPSERT
                Location::upsert(
                    $cleaned, // 1. Array data yang akan di-insert/update
                    ['location_no'], // 2. Primary Key / Kolom Unik sebagai acuan pengecekan
                    [ // 3. Kolom apa saja yang di-update jika location_no sudah ada di database
                        'location_group', 
                        'site', 
                        'warehouse', 
                        'location_description', 
                        'location_group_description'
                    ]
                );

                // Menghitung jumlah data yang diproses (karena kita tidak pakai loop lagi)
                $insertedCount = count($cleaned);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported and/or updated {$insertedCount} location(s).",
                    'data' => [
                        'total_imported' => $insertedCount,
                        'total_processed' => count($cleaned),
                    ],
                ], 200);
            } catch (\Exception $e) {
                DB::rollBack();

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