<?php

namespace App\Http\Controllers\Original\HITUAM\HITUAM01;

use App\DataTables\Original\HITUAM01\HITUAMF007DataTable;
use App\Http\Controllers\Controller;

// Master Role Access Controller
class HITUAMF007 extends Controller
{
    public function index(HITUAMF007DataTable $dataTable)
    {
        return $dataTable->render('modules.HITUAM.HITUAM01.HITUAMF007.HITUAMF007');
    }
}
