<?php

namespace App\Http\Controllers\Original\HITUAM\HITUAM01;

use App\Http\Controllers\Controller;
use App\DataTables\Original\HITUAM01\HITUAMF003DataTable as ServiceDataTable;
use App\Exports\Templates\HITUAM\HITUAM01\HITUAMF003 as Template;
use App\Helpers\Response;
use App\Http\Requests\HITUAM\HITUAM01\ServiceImportRequest;
use App\Http\Requests\HITUAM\HITUAM01\ServiceRequest;
use App\Imports\HITUAM\HITUAM01\HITUAMF003 as Import;
use App\Models\HITUAM01\HITUAM_MSHAPPLICATION as Application;
use App\Models\HITUAM01\HITUAM_MSHMENU;
use App\Services\HITUAM\PermissionService;
use App\Models\HITUAM01\HITUAM_MSHSERVICE as Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as FacadesExcel;
// use PhpOffice\PhpSpreadsheet\Calculation\Statistical\Distributions\F;
use Illuminate\Support\Facades\Auth;

class HITUAMF003 extends Controller
{
    public function __construct(private PermissionService $permissionService) {}

    public function index(ServiceDataTable $dataTable)
    {
        return $dataTable->render('modules.HITUAM.HITUAM01.HITUAMF003.HITUAMF003');
    }

    public function store(ServiceRequest $request)
    {
        $data = $request->validated();

        $service = DB::transaction(function () use ($data) {

            // ambil menu (handle soft delete juga)
            $menu = HITUAM_MSHMENU::withTrashed()
                ->where('IID', $data['menu'])
                ->first();

            if ($menu && $menu->trashed()) {
                $menu->restore();
            }

            // 🔥 cek service berdasarkan UNIQUE constraint
            $service = Service::withTrashed()
                // ->where('VMENUID', $menu?->VAPPID)
                ->where('VNAME', $data['name'])
                ->lockForUpdate()
                ->first();

            if ($service) {
                // kalau soft delete → restore
                if ($service->trashed()) {
                    $service->forceDelete();
                }
            }

            // create baru kalau belum ada
            $application = Application::find($data['application']);

            $service = Service::create([
                'VNAME'             => $data['name'],
                'VDESC'             => $data['description'],
                'VURL'              => $data['url'],
                'VMETHOD'           => $data['method'],
                'DBEGINEFF'         => $data['begin'],
                'DENDEFF'           => $data['end'],
                'VMENUID'           => $menu?->VAPPID,
                'IAPPLICATIONID'    => $application?->IID
            ]);

            return $service;
        });

        return Response::success($service, "Service {$service->VNAME} created successfully!");
    }

    public function show(Service $service)
    {
        $service->load(['menu', 'application']);

        return Response::success($service);
    }

    public function update(ServiceRequest $request, Service $service)
    {
        $data = $request->validated();

        $service = DB::transaction(function () use ($data, $service) {

            $menuId = HITUAM_MSHMENU::find($data['menu']);

            $application = Application::find($data['application']);

            $service->update([
                'VNAME'          => $data['name'],
                'VDESC'          => $data['description'],
                'VURL'           => $data['url'],
                'VMETHOD'        => $data['method'],
                'DBEGINEFF'      => $data['begin'],
                'DENDEFF'        => $data['end'],
                'VMENUID'        => $menuId->VAPPID ?? null,
                'IAPPLICATIONID' => $application?->IID
            ]);

            return $service;
        });

        return Response::success($service, "Service {$service->VNAME} updated successfully!");
    }

    public function destroy(Service $service)
    {
        $roleServiceCount = DB::connection('hituam')->table('HITUAM_MSHROLESERVICES')
            ->whereNull('DDELETE')
            ->where('VSERVICE', $service->VNAME)
            ->count();

        if ($roleServiceCount > 0) {
            return Response::error(
                message: "Service {$service->VNAME} can't be deleted because it is still used in role service mapping.",
                status: 422
            );
        }

        try {
            $service->delete();

            return Response::success(message: "Service {$service->VNAME} deleted successfully");
        } catch (\Throwable $th) {
            return Response::error(message: "Can't delete {$service->VNAME}, it may have related records");
        }
    }

    public function destroyMultiple(Request $request)
    {
        $data = $request->ids;

        $relatedServices = Service::query()
            ->select('HITUAM_MSHSERVICES.VNAME')
            ->whereIn('IID', $data)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('HITUAM_MSHROLESERVICES')
                    ->whereNull('HITUAM_MSHROLESERVICES.DDELETE')
                    ->whereColumn('HITUAM_MSHROLESERVICES.VSERVICE', 'HITUAM_MSHSERVICES.VNAME');
            })
            ->pluck('VNAME');

        if ($relatedServices->isNotEmpty()) {
            return Response::error(
                message: 'Cannot delete selected services because some are still used in role service mapping: ' . $relatedServices->implode(', '),
                status: 422
            );
        }

        try {
            Service::whereIn('IID', $data)->delete();

            return Response::success(message: 'Selected services deleted successfully');
        } catch (\Throwable $th) {
            return Response::error(message: "Can't delete selected services, they may have related records : " . $th);
        }
    }

    public function downloadTemplate()
    {
        return FacadesExcel::download(new Template, 'Master_Service_Template.xlsx');
    }

    public function import(ServiceImportRequest $request)
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
                $insertedCount = 0;

                foreach ($cleaned as $data) {
                    // Map application_name dan menu_name ke menu
                    $application = Application::where('VPROJECTDESC', $data['application_name'])
                        ->whereNull('DDELETE')
                        ->first();
                    $menu = null;

                    if ($application) {
                        $menu = HITUAM_MSHMENU::where('NSORTPROJECT', $application->IID)
                            ->where('VAPPDESC', $data['menu_name'])
                            ->whereNull('DDELETE')
                            ->first();
                    }

                    if (! $application || ! $menu) {
                        // Skip jika aplikasi atau menu tidak ditemukan (seharusnya sudah divalidasi di mapRelations)
                        continue;
                    }

                    $service = Service::withTrashed()
                        ->where('VNAME', $data['service_name'])
                        ->lockForUpdate()
                        ->first();

                    if ($service) {
                        // kalau soft delete → restore
                        if ($service->trashed()) {
                            $service->forceDelete();
                        }
                    }

                    $service = Service::create([
                        'VNAME'          => $data['service_name'],
                        'VDESC'          => $data['service_description'],
                        'VURL'           => $data['service_url'],
                        'VMETHOD'        => $data['http_method'],
                        'DBEGINEFF'      => $data['begin_effective_date'],
                        'DENDEFF'        => $data['end_effective_date'],
                        'VMENUID'        => $menu->VAPPID,
                        'IAPPLICATIONID' => $application->IID
                    ]);

                    $insertedCount++;
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported {$insertedCount} service(s).",
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
