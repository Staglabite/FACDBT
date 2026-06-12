<?php

namespace App\Http\Controllers\Original\HITUAM\HITUAM01;

use App\DataTables\Original\HITUAM01\HITUAMF008DataTable;
use App\Http\Controllers\Controller;

// Master Role Service Controller
class HITUAMF008 extends Controller
{
    public function index(HITUAMF008DataTable $dataTable)
    {
        return $dataTable->render('modules.HITUAM.HITUAM01.HITUAMF008.HITUAMF008');
    }
}
