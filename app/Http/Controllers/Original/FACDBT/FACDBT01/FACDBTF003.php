<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT01;

use App\DataTables\Original\FACDBT01\FACDBTF003DataTable;
use App\Exports\FACDBT\FACDBT01\MasterDataExport;
use App\Helpers\Response;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Original\FACDBT\FACDBT01\Concerns\SavesImportRows;
use App\Http\Requests\FACDBT\FACDBT01\UnitCostImportRequest; 
use App\Http\Requests\FACDBT\FACDBT01\UnitCostRequest;
use App\Imports\FACDBT\FACDBT01\FACDBTF003 as Import;
use App\Models\FACDBT01\FACDBT_MSHUNITCOST as UnitCost; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel as FacadesExcel;

// Unit Cost controller
class FACDBTF003 extends Controller
{
    use SavesImportRows;

    // Kolom export CSV [kolom tabel => judul]; judul yang sama dipakai template import
    private const EXPORT_COLUMNS = [
        'VPARTNO' => 'Part No',
        'VPARTDESC' => 'Description',
        'VUOM' => 'UOM',
        'VACCGROUP' => 'Accounting Group',
        'NONHANDQTY' => 'On Hand Qty',
        'MMATCOST' => 'Material Cost',
        'MLABCOST' => 'Labor Cost',
        'MFOHCOST' => 'FOH Cost',
    ];

    // Kolom form yang disimpan (tabel FACDBT_MSHUNITCOSTS)
    private const FIELDS = ['VPARTNO', 'VPARTDESC', 'VUOM', 'VACCGROUP', 'NONHANDQTY', 'MMATCOST', 'MLABCOST', 'MFOHCOST'];

    // Kolom hasil import (slug judul kolom Excel) => kolom tabel
    private const IMPORT_COLUMNS = [
        'part_no' => 'VPARTNO',
        'inventory_part_description' => 'VPARTDESC',
        'inventory_uom' => 'VUOM',
        'accounting_group' => 'VACCGROUP',
        'on_hand_qty' => 'NONHANDQTY',
        'mat_cost' => 'MMATCOST',
        'lab_cost' => 'MLABCOST',
        'foh_cost' => 'MFOHCOST',
    ];

    public function index(FACDBTF003DataTable $dataTable)
    {
        // "Centang semua": cukup kirim ID baris yang lolos filter, bukan seluruh isi tabel
        if (request()->boolean('ids_only')) {
            return response()->json(['ids' => $dataTable->filteredKeys('IID')]);
        }

        return $dataTable->render('modules.FACDBT.FACDBT01.FACDBTF003.FACDBTF003');
    }

    public function store(UnitCostRequest $request)
    {
        // Part No yang pernah dihapus dipulihkan dengan data baru
        UnitCost::saveRestoring(['VPARTNO' => $request->VPARTNO], $request->only(self::FIELDS));

        return Response::success(message: 'Unit Cost created successfully');
    }
    
    public function show($id)
    {
        $unitCost = UnitCost::find($id);

        if (!$unitCost) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unit Cost not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $unitCost
        ]);
    }

    public function update(UnitCostRequest $request, UnitCost $unitcost)
    {
        $unitcost->update($request->only(self::FIELDS));

        return Response::success(message: 'Unit Cost updated successfully');
    }

    public function destroy(UnitCost $unitcost)
    {
        // TODO: Jika Unit Cost memiliki relasi ke tabel lain yang melarang penghapusan, tambahkan pengecekannya di sini

        try {
            $unitcost->delete();

            return Response::success(message: 'Unit Cost data deleted successfully');
        } catch (\Throwable $th) {
            return Response::error(message: 'Unit Cost data cannot be deleted, it may be related to other data');
        }
    }

    public function destroyMultiple(Request $request)
    {
        $ids = (array) $request->ids;

        try {
            UnitCost::softDeleteKeys($ids);

            return Response::success(message: 'Selected unit costs deleted successfully');
        } catch (\Throwable $th) {
            return Response::error(message: 'Unit Cost data cannot be deleted, it may be related to other data');
        }
    }

    /**
     * Export CSV: data yang dicentang saja, atau semua data bila tidak ada yang dicentang.
     */
    public function export(Request $request)
    {
        $ids = array_values(array_filter((array) $request->input('ids', []), fn ($id) => $id !== null && $id !== ''));

        $query = UnitCost::query()
            ->when(!empty($ids), fn ($q) => $q->whereKey($ids))
            ->orderBy('VPARTNO')->orderBy('IID');

        return (new MasterDataExport($query, self::EXPORT_COLUMNS))->download('Master_Unit_Cost-' . date('YmdHis') . '.csv');
    }

    public function downloadTemplate()
    {
        // Template CSV (judul kolom sama dengan export); template yang diisi / file export bisa langsung di-import
        return MasterDataExport::template(array_values(self::EXPORT_COLUMNS), 'Master_Unit_Cost_Template.csv');
    }

    public function import(UnitCostImportRequest $request)
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

            // Transaksi di koneksi INV_TAKING (tempat data master disimpan)
            $db = DB::connection('INV_TAKING');
            $db->beginTransaction();

            try {
                // Part No sudah ada (termasuk yang pernah dihapus) -> ditimpa, belum ada -> dibuat baru
                [$insertedCount, $updatedCount] = $this->saveImportRows(new UnitCost, $cleaned, ['VPARTNO']);

                $db->commit();

                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported {$insertedCount} new and updated {$updatedCount} existing unit cost(s).",
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