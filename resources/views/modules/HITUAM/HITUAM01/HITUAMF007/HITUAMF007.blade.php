@extends('layouts/layoutMaster')

@section('title', 'HITUAM - Basic Role Access')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="javascript:void(0);">User Access Management</a>
        </li>
        <li class="breadcrumb-item">
            <a href="javascript:void(0);">Basic Data</a>
        </li>
        <li class="breadcrumb-item active">Master Role Access</li>
    </ol>
</nav>

<div class="card">
    <div class="card-datatable">
        <div class="d-flex justify-content-end align-items-center my-4 px-4 gap-3">
            <div class="d-flex align-items-center">
                <select class="form-select form-select-sm mx-2" id="entries" style="width: 70px; max-width: 70px; font-size: 1rem; padding: 0.25rem 0.5rem;">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="-1">All</option>
                </select>
            </div>

            <div class="input-group" style="width: 250px;">
                <span class="input-group-text border-end-0">
                    <i class="icon-base ti tabler-search"></i>
                </span>
                <input type="text" class="form-control border-start-0" placeholder="Search" aria-label="Search" id="search-input">
            </div>
        </div>

        {{ $dataTable->table(['class' => 'table'], true) }}
    </div>
</div>
@endsection

@section('page-script')
{{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@vite(['resources/js/pages/hituam/hituam01/role-access/role-access.js'])
@endsection
