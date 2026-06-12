<?php

namespace App\Http\Controllers\Original\HITUAM\HITUAM01;

use App\DataTables\Original\HITUAM01\HITUAMF001DataTable;
use App\Exports\Templates\HITUAM\HITUAM01\HITUAMF001 as Template;
use App\Helpers\Response;
use App\Http\Controllers\Controller;
use App\Http\Requests\HITUAM\HITUAM01\ApplicationImportRequest;
use App\Http\Requests\HITUAM\HITUAM01\ApplicationRequest;
use App\Http\Resources\HITUAM\HITUAM01\ApplicationResource;
use App\Imports\HITUAM\HITUAM01\HITUAMF001 as Import;
use App\Models\HITUAM01\HITUAM_MSHAPPLICATION as Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as FacadesExcel;

// Application controller
class HITUAMF001 extends Controller
{
    public function index(HITUAMF001DataTable $dataTable)
    {
        return $dataTable->render('modules.HITUAM.HITUAM01.HITUAMF001.HITUAMF001');
    }

    public function store(ApplicationRequest $request)
    {
        Application::create([
            'VDEPT' => $request->code,
            'VPROJECTDESC' => $request->desc,
            'VPREFIXPROJECT' => $request->prefix,
            'VPIC' => $request->pic,
            'VPORTALNAME' => $request->portal,
            'VOPERATIONAL' => $request->operational,
            'VSTRDZATION' => $request->std,
            'VPORTALACCESS' => $request->portal_access,
            'VHOST' => $request->host,
            'VPUBLISH' => $request->publish,
            'VDATABASE' => $request->database,
            'NORDERPROJECT' => $request->order,
            'VICON' => $request->icon,
            'BIS_EMBED' => $request->is_embedded === 'true' ? true : false,
        ]);

        return Response::success(message: 'Application created successfully');
    }

    public function show(Application $application)
    {
        return Response::success(data: ApplicationResource::make($application));
    }

    public function update(ApplicationRequest $request, Application $application)
    {
        $application->update([
            'VDEPT' => $request->code,
            'VPROJECTDESC' => $request->desc,
            'VPREFIXPROJECT' => $request->prefix,
            'VPIC' => $request->pic,
            'VPORTALNAME' => $request->portal,
            'VOPERATIONAL' => $request->operational,
            'VSTRDZATION' => $request->std,
            'VPORTALACCESS' => $request->portal_access,
            'VHOST' => $request->host,
            'VPUBLISH' => $request->publish,
            'VDATABASE' => $request->database,
            'NORDERPROJECT' => $request->order,
            'VICON' => $request->icon,
            'BIS_EMBED' => $request->is_embedded === 'true' ? true : false,
        ]);

        return Response::success(message: 'Application updated successfully');
    }

    public function destroy(Application $application)
    {
        $menuCount = $application->menus()->count();

        if ($menuCount > 0) {
            return Response::error(
                message: "Application {$application->VPROJECTDESC} can't be deleted because it still has related menus.",
                status: 422
            );
        }

        try {
            $application->delete();

            return Response::success(message: 'Application data deleted successfully');
        } catch (\Throwable $th) {
            return Response::error(message: 'Application data cannot be deleted, it may be related to other data');
        }
    }

    public function destroyMultiple(Request $request)
    {
        $ids = $request->ids;

        $relatedApplications = Application::query()
            ->withCount('menus')
            ->whereIn('IID', $ids)
            ->get()
            ->filter(fn($application) => ($application->menus_count ?? 0) > 0)
            ->pluck('VPROJECTDESC');

        if ($relatedApplications->isNotEmpty()) {
            return Response::error(
                message: "Cannot delete selected applications because some still have related menus: {$relatedApplications->implode(', ')}",
                status: 422
            );
        }

        try {
            Application::whereIn('IID', $ids)->delete();

            return Response::success(message: 'Selected applications deleted successfully');
        } catch (\Throwable $th) {
            return Response::error(message: 'Application data cannot be deleted, it may be related to other data');
        }
    }

    public function downloadTemplate()
    {
        return FacadesExcel::download(new Template, 'Master_Application_Template.xlsx');
    }

    public function import(ApplicationImportRequest $request)
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
                    Application::create([
                        'VDEPT' => $data['code'],
                        'VPROJECTDESC' => $data['description'],
                        'VPREFIXPROJECT' => $data['prefix'],
                        'VPIC' => $data['pic'],
                        'VPORTALNAME' => $data['portal_name'],
                        'VOPERATIONAL' => $data['operational'],
                        'VSTRDZATION' => $data['standardization'],
                        'VPORTALACCESS' => $data['portal_access'],
                        'VHOST' => $data['host'],
                        'VPUBLISH' => $data['publish'],
                        'VDATABASE' => $data['database'],
                        'NORDERPROJECT' => $data['order'],
                        'VICON' => $data['icon'],
                    ]);

                    $insertedCount++;
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported {$insertedCount} application(s).",
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
