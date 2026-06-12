<?php

namespace App\Http\Controllers\Original\HITUAM\HITUAM01;

use App\Exports\Templates\HITUAM\HITUAM01\HITUAMF004 as Template;
use App\Helpers\Response;
use App\Http\Controllers\Controller;
use App\Http\Requests\HITUAM\HITUAM01\RoleImportRequest;
use App\Http\Requests\HITUAM\HITUAM01\RoleRequest;
use App\Imports\HITUAM\HITUAM01\HITUAMF004 as Import;
use App\Models\HITUAM01\HITUAM_MSHMENU;
use Illuminate\Http\Request;
use App\Services\HITUAM\RoleService;
use App\Models\HITUAM01\HITUAM_MSHROLE as Role;
use App\Models\HITUAM01\HITUAM_MSHROLE_ACCESS as RoleAccess;
use App\Models\HITUAM01\HITUAM_MSHROLE_SERVICE as RolePermission;
use App\Models\HITUAM01\HITUAM_MSHSERVICE;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as FacadesExcel;
use Illuminate\Support\Facades\Auth;

// Role Controller
class HITUAMF004 extends Controller
{
    public function __construct(private RoleService $roleService) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RoleRequest $request)
    {
        $data = $request->validated();

        $role = DB::transaction(function () use ($data) {
            $role = $this->restoreOrCreateRole($data['name'], $data['description'], (bool) $data['status']);

            $menuIds = $data['menus'] ?? [];
            $serviceIds = $data['services'] ?? [];

            $mapMenuName = HITUAM_MSHMENU::query()->whereIn('IID', $menuIds)->pluck('VAPPID')->toArray();
            $mapServiceName = HITUAM_MSHSERVICE::query()->whereIn('IID', $serviceIds)->pluck('VNAME')->toArray();

            $this->roleService->updateAccess($mapMenuName, [], $role->VROLENAME);
            $this->roleService->updateServices($mapServiceName, [], $role->VROLENAME);

            return $role;
        });

        return Response::success($role, "Role {$role->VROLENAME} created successfully");
    }

    /**
     * Display the specified resource.
     */
    public function show(Role $role)
    {
        $role->load(['services', 'accesses']);

        return Response::success($role, "Get role data successfully");
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(RoleRequest $request, Role $role)
    {
        $data = $request->validated();

        $role = DB::transaction(function () use ($data, $role) {
            $role->update([
                'VROLENAME' => $data['name'],
                'VROLEDESC' => $data['description'],
                'BSTATUS' => (bool) $data['status'],
                'VMODI' => Auth::user()->VUSERNAME ?? 'SYSTEM',
                'DMODI' => now(),
            ]);

            $mapMenuNameChecked = HITUAM_MSHMENU::query()->whereIn('IID', $data['menus'])->pluck('VAPPID')->toArray();
            $mapMenuNameUnChecked = HITUAM_MSHMENU::query()->whereIn('IID', $data['unmenus'])->pluck('VAPPID')->toArray();

            $mapServiceNameChecked = HITUAM_MSHSERVICE::query()->whereIn('IID', $data['services'])->pluck('VNAME')->toArray();
            $mapServiceNameUnChecked = HITUAM_MSHSERVICE::query()->whereIn('IID', $data['unservices'])->pluck('VNAME')->toArray();
            // dd($mapServiceNameUnChecked);

            $this->roleService->updateAccess($mapMenuNameChecked, $mapMenuNameUnChecked, $role->VROLENAME);
            $this->roleService->updateServices($mapServiceNameChecked, $mapServiceNameUnChecked, $role->VROLENAME);

            return $role;
        });

        return Response::success($role, "Role {$role->VROLENAME} updated successfully");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        $hasRelations = $this->hasRelations($role);

        if ($hasRelations) {
            return Response::error(message: "Can't delete {$role->VROLENAME}, it still has related users, menu access, or services.");
        }

        try {
            $role->delete();

            return Response::success(message: "{$role->VROLENAME} deleted successfully");
        } catch (\Throwable $th) {
            return Response::error(message: "Can't delete {$role->VROLENAME}, it may have related records");
        }
    }

    public function destroyMultiple(Request $request)
    {
        $ids = $request->ids;

        $relatedRoleNames = Role::query()
            ->whereIn('NID', $ids)
            ->get()
            ->filter(fn($role) => $this->hasRelations($role))
            ->pluck('VROLENAME')
            ->values();

        if ($relatedRoleNames->isNotEmpty()) {
            return Response::error(message: "Can't delete selected roles. Related data still exists for: " . $relatedRoleNames->implode(', '));
        }

        try {
            Role::whereIn('NID', $ids)->delete();

            return Response::success(message: "Selected roles deleted successfully");
        } catch (\Throwable $th) {
            return Response::error(message: "Can't delete selected roles, they may have related records");
        }
    }

    public function downloadTemplate()
    {
        return FacadesExcel::download(new Template, 'Master_Role_Template.xlsx');
    }

    public function import(RoleImportRequest $request)
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
                    $this->restoreOrCreateRole($data['role_name'], $data['description']);

                    $insertedCount++;
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported {$insertedCount} role(s).",
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

    private function hasRelations(Role $role): bool
    {
        $userCount = DB::connection('hituam')->table('HITUAM_MSHUSERROLES')
            ->whereNull('DDELETE')
            ->where('VROLE', $role->VROLENAME)
            ->count();

        $accessCount = DB::connection('hituam')->table('HITUAM_MSHROLEACCESS')
            ->whereNull('DDELETE')
            ->where('BSTATUS', true)
            ->where('VROLE', $role->VROLENAME)
            ->count();

        $serviceCount = DB::connection('hituam')->table('HITUAM_MSHROLESERVICES')
            ->whereNull('DDELETE')
            ->where('VROLE', $role->VROLENAME)
            ->count();

        return $userCount > 0 || $accessCount > 0 || $serviceCount > 0;
    }

    private function restoreOrCreateRole(string $roleName, ?string $description, bool $status = true): Role
    {
        $role = Role::withTrashed()->where('VROLENAME', $roleName)->first();
        $user = Auth::user()?->VUSERNAME ?? Auth::user()?->username ?? 'SYSTEM';

        if ($role) {
            if ($role->trashed()) {
                $role->forceDelete();
            } else {
                $role->update([
                    'VROLEDESC' => $description,
                    'BSTATUS' => $status,
                    'VMODI' => $user,
                    'DMODI' => now(),
                ]);

                return $role;
            }

            // $role->update([
            //     'VROLEDESC' => $description,
            // ]);

            // return $role;
        }

        return Role::create([
            'VROLENAME' => $roleName,
            'VROLEDESC' => $description,
            'BSTATUS' => $status,
        ]);
    }
}
