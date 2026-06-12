<?php

namespace App\Http\Controllers\Original\HITUAM\HITUAM01;

use App\Exports\Templates\HITUAM\HITUAM01\HITUAMF005 as Template;
use App\Helpers\Response;
use App\Http\Controllers\Controller;
use App\Http\Requests\HITUAM\HITUAM01\PasswordRequest;
use App\Http\Requests\HITUAM\HITUAM01\UserImportRequest;
use App\Http\Requests\HITUAM\HITUAM01\UserRequest;
use App\Imports\HITUAM\HITUAM01\HITUAMF005 as Import;
// use App\Models\FACTWM01\FACTWM_MSHSUPPLIER as Supplier;
// use App\Models\FACTWM01\FACTWM_MSHSUPPLIER_COMMUNICATION_METHOD as SupplierUser;
use App\Models\HITUAM01\HITUAM_MSHROLE as Role;
use App\Models\HITUAM01\HITUAM_MSHUSER as User;
use App\Services\HITUAM\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as FacadesExcel;
use Illuminate\Support\Facades\Auth;

// User Controller
class HITUAMF005 extends Controller
{
    public function __construct(private UserService $userService) {}

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
    public function store(UserRequest $request)
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $userData = [
                'VUSERNAME' => $data['username'],
                'VEMPNO' => $data['npk'],
                'VEMAIL' => $data['email'],
            ];

            // if ($data['user_type'] === 'external') {
            //     $userData['VPASSWORD'] = Hash::make('password');
            // } else {
            $userData['VPASSWORD'] = Hash::make($data['password']);
            // }

            $user = User::create($userData);

            $roles = Role::whereIn('NID', $data['role'] ?? [])->pluck('VROLENAME')->toArray();

            if (! empty($roles)) {
                $user->assignRoles($roles);
            }

            // if ($data['user_type'] === 'external') {
            //     $this->userService->syncSupplierUser($user, $data);
            // }

            return $user;
        });

        return Response::success(message: "User {$user->VNAME} created successfully");
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $user->load(['roles']);

        return Response::success($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UserRequest $request, User $user)
    {
        $data = $request->validated();
        $user = DB::transaction(function () use ($data, $user) {
            $update = [
                'VUSERNAME' => $data['username'],
                'VEMPNO' => $data['npk'],
                'VEMAIL' => $data['email'],
                'VMODI'     => Auth::user()?->VUSERNAME ?? 'SYSTEM',
                'DMODI'     => now(),
            ];

            if (isset($data['password'])) {
                $update['VPASSWORD'] = Hash::make($data['password']);
            }

            $user->update($update);

            $roles = Role::whereIn('NID', $data['role'] ?? [])->pluck('VROLENAME')->toArray();

            // if ($data['user_type'] === 'external') {
            //     $this->userService->syncSupplierUser($user, $data);
            // } else {
            //     // Set VUSERNAME ke null ketika user diubah ke internal
            //     if ($user->supplierUser()) {
            //         $user->supplierUser()->update(['VUSERNAME' => null, 'IUSER_ID' => null]);
            //     }
            // }

            if (array_key_exists('role', $data)) {
                $user->syncRoles($roles);
            }

            return $user;
        });

        return Response::success(message: "User {$user->VUSERNAME} updated successfully");
    }

    public function profile(User $user)
    {
        $user->load(['roles']);
        $supplier = null;

        // if ($user->supplierUser?->VSUPPLIER_CODE) {
        //     $supplier = Supplier::query()
        //         ->where('VSUPPLIER_CODE', $user->supplierUser->VSUPPLIER_CODE)
        //         ->first();
        // }

        return view('modules.HITUAM.HITUAM01.HITUAMF005.partials._user-profile', [
            'user' => $user,
            'supplier' => $supplier,
        ]);
    }

    public function changePassword(PasswordRequest $request, User $user)
    {
        $data = $request->validated();
        $user = DB::transaction(function () use ($data, $user) {
            $update = [
                'VUSERNAME' => $user->VUSERNAME,
                'VPASSWORD' => Hash::make($data['new_password']),
            ];
            $user->update($update);
        });

        return Response::success(message: 'User Password updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        try {
            // $isSuplierUser = SupplierUser::query()
            //     ->where('VVALUE', $user->VEMAIL)
            //     ->exists();

            // if ($isSuplierUser) {
            //     return Response::error(message: "Can't delete {$user->VNAME}, it is associated with vendor data. Please delete it in Change Request Supplier before deletion.");
            // }

            $user->delete();

            return Response::success(message: "User {$user->VNAME} deleted successfully");
        } catch (\Throwable $th) {
            return Response::error(message: "Can't delete {$user->VNAME}, it may have related records");
        }
    }

    /**
     * Remove multiple users from storage.
     */
    public function destroyMultiple(Request $request)
    {
        $ids = $request->ids;

        try {
            $users = User::query()
                ->whereIn('IID', $ids)
                ->with('supplierUser')
                ->get();

            $supplierUsers = SupplierUser::query()
                ->whereIn('VVALUE', $users->pluck('VEMAIL'))
                ->get();

            $relatedUsers = $users->filter(function ($user) use ($supplierUsers) {
                return $supplierUsers->contains('VVALUE', $user->VEMAIL);
            })->pluck('VUSERNAME');

            if ($relatedUsers->isNotEmpty()) {
                return Response::error(
                    message: "Can't delete selected users because they are associated with vendor data: " . $relatedUsers->implode(', ') . '. Please delete them in Change Request Supplier before deletion.',
                    status: 422
                );
            }

            $users->each(function ($user) {
                $user->delete();
            });

            return Response::success(message: 'Selected users deleted successfully');
        } catch (\Throwable $th) {
            return Response::error(message: "Can't delete selected users, they may have related records");
        }
    }

    public function downloadTemplate()
    {
        return FacadesExcel::download(new Template, 'Master_User_Template.xlsx');
    }

    public function import(UserImportRequest $request)
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
                    $userData = [
                        'VUSERNAME' => $data['username'],
                        'VEMPNO' => $data['npk'],
                        'VEMAIL' => $data['email'],
                    ];

                    // if ($data['user_type'] === 'external') {
                    //     $userData['VPASSWORD'] = Hash::make('password');
                    // } else {
                    //     $userData['VPASSWORD'] = Hash::make($data['password']);
                    // }

                    $userData['VPASSWORD'] = Hash::make($data['password']);

                    $user = User::create($userData);

                    $roles = Role::query()
                        ->whereIn('VROLENAME', collect(explode(',', $data['role_names']))->map(fn($role) => trim($role))->filter()->toArray())
                        ->pluck('VROLENAME')
                        ->toArray();

                    $user->assignRoles($roles);

                    if ($data['user_type'] === 'external') {
                        $this->userService->syncSupplierUser($user, [
                            'supplier' => $data['supplier'] ?? null,
                            'user_supplier' => $data['user_supplier'] ?? null,
                        ]);
                    }

                    $insertedCount++;
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported {$insertedCount} user(s).",
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
