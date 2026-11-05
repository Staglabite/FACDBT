<?php

namespace App\Http\Controllers\Original\HITUAM\HITUAM01;

use App\DataTables\Original\HITUAM01\HITUAMF004DataTable as RoleDatatable;
use App\DataTables\Original\HITUAM01\HITUAMF005DataTable as UserDatatable;
use App\Http\Controllers\Controller;
use App\Models\HITUAM01\HITUAM_MSHROLE as Role;
use App\Models\HITUAM01\HITUAM_MSHUSER as User;

// User Role Controller
class HITUAMF006 extends Controller
{
    public function index(UserDatatable $userTable, RoleDatatable $roleTable)
    {
        return view('modules.HITUAM.HITUAM01.HITUAMF006.HITUAMF006', [
            'userTable' => $userTable->html(),
            'roleTable' => $roleTable->html(),
        ]);
    }

    public function users(UserDatatable $userTable)
    {
        return $userTable->dataTable($userTable->query(new User))->toJson();
    }

    public function roles(RoleDatatable $roleTable)
    {
        return $roleTable->dataTable($roleTable->query(new Role))->toJson();
    }
}
