<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT01;

use App\DataTables\Original\FACDBT01\FACDBTF002DataTable;
use App\Exports\FACDBT\FACDBT01\MasterDataExport;
use App\Helpers\Response;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Original\FACDBT\FACDBT01\Concerns\SavesImportRows;
use App\Http\Requests\FACDBT\FACDBT01\PartImportRequest; 
use App\Http\Requests\FACDBT\FACDBT01\PartRequest;
use App\Imports\FACDBT\FACDBT01\FACDBTF002 as Import;
use App\Models\FACDBT01\FACDBT_MSHPART as Part; // Sesuai dengan namespace model Anda
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel as FacadesExcel;

// Part controller
class FACDBTF002 extends Controller
{
    use SavesImportRows;

    // Kolom export CSV [kolom tabel => judul]; judul yang sama dipakai template import
    private const EXPORT_COLUMNS = [
        'VPARTNO' => 'Part No',
        'VPARTDESC' => 'Description',
        'VACCGROUP' => 'Accounting Group',
        'VSEG' => 'Seg',
        'VPRODGROUP' => 'Prod',
        'VPARTGROUP' => 'Part Group',
    ];

    // Kolom form yang disimpan (tabel FACDBT_MSHPARTS)
    private const FIELDS = ['VPARTNO', 'VPARTDESC', 'VACCGROUP', 'VSEG', 'VPRODGROUP', 'VPARTGROUP'];

    // Kolom hasil import (slug judul kolom Excel) => kolom tabel
    private const IMPORT_COLUMNS = [
        'part_no' => 'VPARTNO',
        'inventory_part_description' => 'VPARTDESC',
        'accounting_group' => 'VACCGROUP',
        'seg' => 'VSEG',
        'prod_group' => 'VPRODGROUP',
        'part_group' => 'VPARTGROUP',
    ];

    public function index(FACDBTF002DataTable $dataTable)
    {
        // "Centang semua": cukup kirim ID baris yang lolos filter, bukan seluruh isi tabel
        if (request()->boolean('ids_only')) {
            return response()->json(['ids' => $dataTable->filteredKeys('IID')]);
        }

        return $dataTable->render('modules.FACDBT.FACDBT01.FACDBTF002.FACDBTF002');
    }

    public function store(PartRequest $request)
    {
        // Part No yang pernah dihapus dipulihkan dengan data baru
        Part::saveRestoring(['VPARTNO' => $request->VPARTNO], $request->only(self::FIELDS));

        return Response::success(message: 'Part created successfully');
    }
    
    public function show($id)
    {
        $part = Part::find($id);

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
        $part->update($request->only(self::FIELDS));

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
                message: "Part {$part->VPARTNO} can't be deleted because it is still used in transaction records.",
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
        $ids = (array) $request->ids;

        // Pengecekan relasi untuk multiple delete
        $relatedParts = Part::query()
            ->withCount(['books', 'revisions', 'takingData'])
            ->whereKey($ids)
            ->get()
            ->filter(fn($part) => 
                ($part->books_count ?? 0) > 0 || 
                ($part->revisions_count ?? 0) > 0 || 
                ($part->taking_data_count ?? 0) > 0
            )
            ->pluck('VPARTNO');

        if ($relatedParts->isNotEmpty()) {
            return Response::error(
                message: "Cannot delete selected parts because some are still used in transactions: {$relatedParts->implode(', ')}",
                status: 422
            );
        }

        try {
            Part::softDeleteKeys($ids);

            return Response::success(message: 'Selected parts deleted successfully');
        } catch (\Throwable $th) {
            return Response::error(message: 'Part data cannot be deleted, it may be related to other data');
        }
    }

    /**
     * Export CSV: data yang dicentang saja, atau semua data bila tidak ada yang dicentang.
     */
    public function export(Request $request)
    {
        $ids = array_values(array_filter((array) $request->input('ids', []), fn ($id) => $id !== null && $id !== ''));

        $query = Part::query()
            ->when(!empty($ids), fn ($q) => $q->whereKey($ids))
            ->orderBy('VPARTNO');

        return (new MasterDataExport($query, self::EXPORT_COLUMNS))->download('Master_Part-' . date('YmdHis') . '.csv');
    }

    public function downloadTemplate()
    {
        // Template CSV (judul kolom sama dengan export); template yang diisi / file export bisa langsung di-import
        return MasterDataExport::template(array_values(self::EXPORT_COLUMNS), 'Master_Part_Template.csv');
    }

    public function import(PartImportRequest $request)
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

            $cleaned = array_map(
                fn ($row) => array_combine(array_values(self::IMPORT_COLUMNS), array_map(fn ($column) => $row[$column] ?? null, array_keys(self::IMPORT_COLUMNS))),
                $import->store()
            );
            if (empty($cleaned)) {
                return response()->json(['success' => false, 'message' => 'No valid data to import.'], 400);
            }
            // Hasil baca file (semua baris + log validasi) tidak dipakai lagi; lepas dari memori sebelum menyimpan
            unset($import, $result);

            // Transaksi di koneksi FACDBT (tempat data master disimpan)
            $db = DB::connection('FACDBT');
            $db->beginTransaction();

            try {
                // Part No sudah ada (termasuk yang pernah dihapus) -> ditimpa, belum ada -> dibuat baru
                [$insertedCount, $updatedCount] = $this->saveImportRows(new Part, $cleaned, ['VPARTNO']);

                $db->commit();

                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported {$insertedCount} new and updated {$updatedCount} existing part(s).",
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