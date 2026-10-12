<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT01;

use App\DataTables\Original\FACDBT01\FACDBTF002DataTable;
use App\Exports\Templates\FACDBT\FACDBT01\FACDBTF002 as Template;
use App\Helpers\Response;
use App\Http\Controllers\Controller;
use App\Http\Requests\FACDBT\FACDBT01\PartImportRequest; 
use App\Http\Requests\FACDBT\FACDBT01\PartRequest;
use App\Imports\FACDBT\FACDBT01\FACDBTF002 as Import;
use App\Models\FACDBT01\FACDBT_MSHPART as Part; // Sesuai dengan namespace model Anda
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as FacadesExcel;

// Part controller
class FACDBTF002 extends Controller
{
    public function index(FACDBTF002DataTable $dataTable)
    {
        return $dataTable->render('modules.FACDBT.FACDBT01.FACDBTF002.FACDBTF002');
    }

    public function store(PartRequest $request)
    {
        Part::create([
            'part_no'                    => $request->part_no,
            'inventory_part_description' => $request->inventory_part_description,
            'accounting_group'           => $request->accounting_group,
            'stream'                     => $request->stream,
            'seg'                        => $request->seg,
            'prod_group'                 => $request->prod_group,
            'part_group'                 => $request->part_group,
        ]);

        return Response::success(message: 'Part created successfully');
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
        $part = Part::where('part_no', $id)->first();

        if (!$part) {
            return response()->json([
                'status' => 'error',
                'message' => 'Part not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $part
        ]);
    }

    public function update(PartRequest $request, Part $part)
    {
        $part->update([
            'inventory_part_description' => $request->inventory_part_description,
            'accounting_group'           => $request->accounting_group,
            'stream'                     => $request->stream,
            'seg'                        => $request->seg,
            'prod_group'                 => $request->prod_group,
            'part_group'                 => $request->part_group,
        ]);

        return Response::success(message: 'Part updated successfully');
    }

    public function destroy(Part $part)
    {
        // Mengecek relasi sebelum menghapus agar tidak terjadi error foreign key
        $relatedBooksCount = $part->books()->count();
        $relatedRevisionsCount = $part->revisions()->count();
        $relatedTakingDataCount = $part->takingData()->count();

        if ($relatedBooksCount > 0 || $relatedRevisionsCount > 0 || $relatedTakingDataCount > 0) {
            return Response::error(
                message: "Part {$part->part_no} can't be deleted because it is still used in transaction records.",
                status: 422
            );
        }

        try {
            $part->delete();

            return Response::success(message: 'Part data deleted successfully');
        } catch (\Throwable $th) {
            return Response::error(message: 'Part data cannot be deleted, it may be related to other data');
        }
    }

    public function destroyMultiple(Request $request)
    {
        $ids = $request->ids;

        // Pengecekan relasi untuk multiple delete
        $relatedParts = Part::query()
            ->withCount(['books', 'revisions', 'takingData'])
            ->whereIn('part_no', $ids)
            ->get()
            ->filter(fn($part) => 
                ($part->books_count ?? 0) > 0 || 
                ($part->revisions_count ?? 0) > 0 || 
                ($part->taking_data_count ?? 0) > 0
            )
            ->pluck('part_no'); 

        if ($relatedParts->isNotEmpty()) {
            return Response::error(
                message: "Cannot delete selected parts because some are still used in transactions: {$relatedParts->implode(', ')}",
                status: 422
            );
        }

        try {
            Part::whereIn('part_no', $ids)->delete();

            return Response::success(message: 'Selected parts deleted successfully');
        } catch (\Throwable $th) {
            return Response::error(message: 'Part data cannot be deleted, it may be related to other data');
        }
    }

    public function downloadTemplate()
    {
        return FacadesExcel::download(new Template, 'Master_Part_Template.xlsx');
    }

    public function import(PartImportRequest $request)
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
                Part::upsert(
                    $cleaned, 
                    ['part_no'], 
                    [ 
                        'inventory_part_description',
                        'accounting_group',
                        'stream',
                        'seg',
                        'prod_group',
                        'part_group'
                    ]
                );

                $insertedCount = count($cleaned);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported and/or updated {$insertedCount} part(s).",
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