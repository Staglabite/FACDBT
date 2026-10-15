<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT01;

use App\DataTables\Original\FACDBT01\FACDBTF003DataTable;
use App\Exports\Templates\FACDBT\FACDBT01\FACDBTF003 as Template;
use App\Exports\FACDBT\FACDBT01\MasterDataExport;
use App\Helpers\Response;
use App\Http\Controllers\Controller;
use App\Http\Requests\FACDBT\FACDBT01\UnitCostImportRequest; 
use App\Http\Requests\FACDBT\FACDBT01\UnitCostRequest;
use App\Imports\FACDBT\FACDBT01\FACDBTF003 as Import;
use App\Models\FACDBT01\FACDBT_MSHUNITCOST as UnitCost; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as FacadesExcel;

// Unit Cost controller
class FACDBTF003 extends Controller
{
    public function index(FACDBTF003DataTable $dataTable)
    {
        return $dataTable->render('modules.FACDBT.FACDBT01.FACDBTF003.FACDBTF003');
    }

    public function store(UnitCostRequest $request)
    {
        UnitCost::create([
            'part_no'                    => $request->part_no,
            'inventory_part_description' => $request->inventory_part_description,
            'inventory_uom'              => $request->inventory_uom,
            'accounting_group'           => $request->accounting_group,
            'on_hand_qty'                => $request->on_hand_qty,
            'mat_cost'                   => $request->mat_cost,
            'lab_cost'                   => $request->lab_cost,
            'foh_cost'                   => $request->foh_cost,
        ]);

        return Response::success(message: 'Unit Cost created successfully');
    }
    
    public function show($id)
    {
        if ($id === 'undefined' || empty($id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid ID provided'
            ], 400);
        }

        // Gunakan part_no sebagai acuan pencarian
        $unitCost = UnitCost::where('part_no', $id)->first();

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
        $unitcost->update([
            'inventory_part_description' => $request->inventory_part_description,
            'inventory_uom'              => $request->inventory_uom,
            'accounting_group'           => $request->accounting_group,
            'on_hand_qty'                => $request->on_hand_qty,
            'mat_cost'                   => $request->mat_cost,
            'lab_cost'                   => $request->lab_cost,
            'foh_cost'                   => $request->foh_cost,
        ]);

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
        $ids = $request->ids;

        try {
            // Asumsi menghapus berdasarkan part_no
            UnitCost::whereIn('part_no', $ids)->delete();

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
            ->when(!empty($ids), fn ($q) => $q->whereIn('part_no', $ids))
            ->orderBy('part_no')->orderBy('id');

        return (new MasterDataExport($query, [
            'part_no' => 'Part No',
            'inventory_part_description' => 'Description',
            'inventory_uom' => 'UOM',
            'accounting_group' => 'Accounting Group',
            'on_hand_qty' => 'On Hand Qty',
            'mat_cost' => 'Material Cost',
            'lab_cost' => 'Labor Cost',
            'foh_cost' => 'FOH Cost',
        ]))->download('Master_Unit_Cost-' . date('YmdHis') . '.csv');
    }

    public function downloadTemplate()
    {
        return FacadesExcel::download(new Template, 'Master_UnitCost_Template.xlsx');
    }

    public function import(UnitCostImportRequest $request)
    {
        try {
            $import = new Import; 
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
                // Upsert untuk menimpa data lama jika part_no sudah ada
                UnitCost::upsert(
                    $cleaned, 
                    ['part_no'], // Primary Key
                    [ 
                        'inventory_part_description',
                        'inventory_uom',
                        'accounting_group',
                        'on_hand_qty',
                        'mat_cost',
                        'lab_cost',
                        'foh_cost',
                    ]
                );

                $insertedCount = count($cleaned);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported and/or updated {$insertedCount} unit cost(s).",
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