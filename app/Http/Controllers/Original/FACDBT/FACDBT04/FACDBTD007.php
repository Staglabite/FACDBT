<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04;

use App\DataTables\Original\FACDBT04\FACDBTD007DataTable;
use App\Exports\FACDBT\FACDBT04\FACDBTD007 as Export;
use App\Http\Controllers\Controller;
use App\Services\FACDBT\TakingByLocationService;
use Illuminate\Http\Request;

// Overview Taking Data by Location: data taking periode yang sedang berjalan per lokasi, part, lot
class FACDBTD007 extends Controller
{
    public function index(FACDBTD007DataTable $dataTable, TakingByLocationService $service)
    {
        // "Centang semua": cukup kirim key baris yang lolos filter, bukan seluruh isi tabel
        if (request()->boolean('ids_only')) {
            return response()->json(['ids' => $service->filtered(request())->pluck('key')->all()]);
        }

        return $dataTable->render('modules.FACDBT.FACDBT04.FACDBTD007.FACDBTD007');
    }

    /**
     * Export CSV: data yang dicentang saja, atau semua data pada pencarian saat ini.
     */
    public function export(Request $request, TakingByLocationService $service)
    {
        $ids = array_flip(array_map('strval', array_filter((array) $request->input('ids', []), fn ($id) => $id !== null && $id !== '')));

        $rows = empty($ids)
            ? $service->filtered($request)
            : collect($service->rows())->filter(fn ($row) => isset($ids[$row['key']]))->values();

        return Export::download($rows);
    }
}
