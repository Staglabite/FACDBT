<?php

namespace App\Http\Controllers\Original\HITUAM\HITUAM01;

use App\Exports\Templates\HITUAM\HITUAM01\HITUAMF002 as Template;
use App\Http\Controllers\Controller;
use App\Http\Requests\HITUAM\HITUAM01\MenuImportRequest;
use App\Http\Requests\HITUAM\HITUAM01\MenuRequest;
use App\Imports\HITUAM\HITUAM01\HITUAMF002 as Import;
use App\Models\HITUAM01\HITUAM_MSHMENU as Menu;
use App\Services\HITUAM\MenuService;
use App\DataTables\Original\HITUAM01\HITUAMF002DataTable as MenuDataTable;
use App\Helpers\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as FacadesExcel;
use Illuminate\Support\Facades\Auth;

class HITUAMF002 extends Controller
{
    public function __construct(private MenuService $menuService) {}

    public function index(MenuDataTable $dataTable)
    {
        return $dataTable->render('modules.HITUAM.HITUAM01.HITUAMF002.HITUAMF002');
    }

    public function store(MenuRequest $request)
    {
        $data = $request->validated();

        $menu = DB::transaction(function () use ($data) {

            $menu = Menu::withTrashed()
                ->where('VAPPID', $data['app_id'])
                ->lockForUpdate()
                ->first();

            if ($menu) {
                // kalau data lama soft delete → restore
                if ($menu->trashed()) {
                    $menu->forceDelete();
                }
            }

            // kalau belum pernah ada → create baru
            return Menu::create([
                'VAPPID'        => $data['app_id'],
                'VFLAG'         => $data['flag'],
                'VICON'         => $data['icon'],
                'VURL'          => $data['url'],
                'VDESC'         => $data['description'],
                'VAPPDESC'      => $data['name'],
                'VENVAPP'       => $data['env_app'],
                'VTYPEAPP'      => $data['type'],
                'NSORTAPP'      => $data['order'],
                'NSORTPROJECT'  => $data['application'],
                'VPARENT'       => $data['parent'] ?? null,
            ]);
        });

        return Response::success($menu, "Menu {$menu->VAPPDESC} created successfully");
    }

    public function show(Menu $menu)
    {
        $menu->load(['application', 'parent']);

        return Response::success($menu);
    }

    public function update(MenuRequest $request, Menu $menu)
    {
        $data = $request->validated();

        $menu = DB::transaction(function () use ($data, $menu) {
            $menu->update([
                'VAPPID'            => $data['app_id'],
                'VFLAG'             => $data['flag'],
                'VICON'             => $data['icon'],
                'VURL'              => $data['url'],
                'VDESC'             => $data['description'],
                'VAPPDESC'          => $data['name'],
                'VENVAPP'           => $data['env_app'],
                'VTYPEAPP'          => $data['type'],
                'NSORTAPP'          => $data['order'],
                'NSORTPROJECT'      => $data['application'],
                'VPARENT'           => $data['parent'] ?? null,
            ]);

            return $menu;
        });

        return Response::success($menu, "Menu {$menu->VAPPDESC} updated successfully");
    }

    public function destroy(Menu $menu)
    {
        $childCount = Menu::query()
            ->where('VPARENT', (string) $menu->IID)
            ->count();

        $serviceCount = $menu->services()->count();
        $accessCount = $menu->accesses()->count();

        $relatedLabels = collect([
            'child menu' => $childCount,
            'service' => $serviceCount,
            'role access' => $accessCount,
        ])->filter(fn($count) => $count > 0)->keys()->values();

        if ($relatedLabels->isNotEmpty()) {
            return Response::error(
                message: "Menu {$menu->VAPPDESC} can't be deleted because it still has related " . $relatedLabels->implode(', ') . '.',
                status: 422
            );
        }

        try {
            $menu->delete();

            return Response::success(message: "Menu {$menu->VAPPDESC} deleted successfully");
        } catch (\Throwable $th) {
            return Response::error(message: "Can't delete {$menu->VAPPDESC}, it may have related records");
        }
    }

    public function destroyMultiple(Request $request)
    {
        $ids = $request->ids;

        $menus = Menu::query()
            ->whereIn('IID', $ids)
            ->get();

        $relatedMenus = $menus->filter(function ($menu) {
            $childCount = Menu::query()
                ->where('VPARENT', (string) $menu->IID)
                ->count();

            $serviceCount = $menu->services()->count();
            $accessCount = $menu->accesses()->count();

            return $childCount > 0 || $serviceCount > 0 || $accessCount > 0;
        })->pluck('VAPPDESC');

        if ($relatedMenus->isNotEmpty()) {
            return Response::error(
                message: "Cannot delete selected menus because some still have related data: {$relatedMenus->implode(', ')}",
                status: 422
            );
        }

        try {
            Menu::whereIn('IID', $ids)->delete();

            return Response::success(message: 'Selected menus deleted successfully');
        } catch (\Throwable $th) {
            return Response::error(message: "Can't delete selected menus, they may have related records");
        }
    }

    public function downloadTemplate()
    {
        return FacadesExcel::download(new Template, 'Master_Menu_Template.xlsx');
    }

    public function import(MenuImportRequest $request)
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

                    $application = \App\Models\HITUAM01\HITUAM_MSHAPPLICATION
                        ::where('VPROJECTDESC', $data['application_name'])
                        ->first();

                    $parentMenu = !empty($data['parent_menu'])
                        ? Menu::where('VAPPDESC', $data['parent_menu'])->first()
                        : null;

                    $menu = Menu::withTrashed()
                        ->where('VAPPID', $data['app_id'])
                        ->lockForUpdate()
                        ->first();

                    if ($menu) {
                        // kalau soft delete → restore
                        if ($menu->trashed()) {
                            $menu->forceDelete();
                        }
                    } else {
                        // create baru
                        Menu::create([
                            'VAPPID'        => $data['app_id'],
                            'VFLAG'         => $data['flag'],
                            'VICON'         => $data['icon'],
                            'VURL'          => $data['url'],
                            'VDESC'         => $data['description'],
                            'VAPPDESC'      => $data['name'],
                            'VENVAPP'       => $data['env_app'],
                            'VTYPEAPP'      => $data['type'],
                            'NSORTAPP'      => (int) $data['order'],
                            'NSORTPROJECT'  => $application?->IID,
                            'VPARENT'       => $parentMenu?->IID,
                        ]);
                    }

                    $insertedCount++;
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported {$insertedCount} menu(s).",
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
