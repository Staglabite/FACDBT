<?php

namespace App\Http\Controllers;

use App\Helpers\Response;
// use App\Models\FACTWM01\FACTWM_MSHSUPPLIER_COMMUNICATION_METHOD;
use App\Services\HITUAM\MenuService;
use App\Services\HITUAM\RoleService;
// use App\Services\FACTWM\SupplierService;
// use App\Services\FACTWM\VerifyPoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class GeneralController extends Controller
{
    public function __construct(
        private MenuService $menuService,
        private RoleService $roleService,
        // private SupplierService $supplierService,
        // private VerifyPoService $verifyPoService
    ) {}

    public function allRoles(Request $request)
    {
        $roles = $this->roleService->getAllRoles($request);

        return Response::success($roles);
    }

    public function allMenus(Request $request)
    {
        $menus = $this->menuService->getAllMenus();

        return Response::success($menus);
    }

    // public function allSuppliers(Request $request)
    // {
    //     $supplier = $this->supplierService->getAllSuppliers($request);

    //     return Response::success($supplier);
    // }

    // public function supplierUsers(Request $request)
    // {
    //     $supplier = $this->supplierService->getSupplierUsers($request);

    //     $currentUserId = $request->user_id ?? null;

    //     $assignedSupplierUsers = FACTWM_MSHSUPPLIER_COMMUNICATION_METHOD::query()
    //         ->whereNotNull('IUSER_ID')
    //         ->when($currentUserId, fn($q) => $q->where('IUSER_ID', '!=', $currentUserId))
    //         ->pluck('IID')
    //         ->toArray();

    //     $supplierArray = $supplier instanceof \Illuminate\Database\Eloquent\Collection
    //         ? $supplier->toArray()
    //         : $supplier;

    //     $supplierArray = array_map(function ($item) use ($assignedSupplierUsers) {
    //         $item['disabled'] = in_array($item['IID'], $assignedSupplierUsers);
    //         return $item;
    //     }, $supplierArray);

    //     return Response::success($supplierArray);
    // }

    public function applications(Request $request)
    {
        $applications = $this->menuService->getApplications($request);

        return Response::success($applications);
    }

    public function menus(Request $request)
    {
        $menus = $this->menuService->getMenus($request);

        return Response::success($menus);
    }

    // public function pphList(Request $request)
    // {
    //     // $list = $this->verifyPoService->getPPhList($request);

    //     // return Response::success($list);

    //     try {
    //         $list = $this->verifyPoService->getPPhList($request);
    //         if (!$list['success']) {
    //             $message = is_array($list['message'])
    //                 ? ($list['message']['message'] ?? 'Get List Objek Pajak Failed')
    //                 : $list['message'];

    //             throw new \Exception(
    //                 $message,
    //                 (int) ($postSI['status'] ?? 500)
    //             );
    //         }

    //         return Response::success([
    //             'message' => $list['message'],
    //             'data'    => $list['data']
    //         ]);
    //     } catch (\Throwable $e) {
    //         $code = (int) $e->getCode();
    //         $status = ($code >= 400 && $code <= 599) ? $code : 500;

    //         return Response::error($e->getMessage(), $status);
    //     }
    // }

    public function storeCache(Request $request)
    {
        $key = $request->key . '_' . Auth::user()->IID;
        $payload = $request->payload;
        $expiresAt = now()->endOfDay();

        Cache::put($key, $payload, $expiresAt);

        return Response::success();
    }
}
