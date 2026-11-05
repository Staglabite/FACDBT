@extends('layouts/layoutMaster')

@section('title', 'HITUAM - Basic Data Configurations')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="javascript:void(0);">User Access Management</a>
        </li>
        <li class="breadcrumb-item">
            <a href="javascript:void(0);">Basic Data</a>
        </li>
        <li class="breadcrumb-item active">Configurations</li>
    </ol>
</nav>

<div class="card">
    <div class="card-datatable">
        <div class="d-flex justify-content-between align-items-center my-5 flex-column flex-xl-row px-6">
            <div class="d-flex align-items-center gap-2 justify-content-center">
                @serve('HITUAMF010-Create')
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary d-flex align-items-center gap-2 px-2 py-2 w-100"
                        id="btn-create-configuration">
                        <i class="icon-base ti tabler-plus"></i>
                        <span>Add</span>
                    </button>
                </div>
                @endserve
                @serve('HITUAMF010-Delete')
                <button type="button" class="btn btn-danger waves-effect waves-effect d-none" id="btn-delete-selected">
                    <i class="icon-base ti tabler-trash"></i>
                    <span>Delete</span>
                </button>
                @endserve
            </div>
            <div class="d-flex gap-2 align-items-center flex-column flex-lg-row mt-2 mt-xxl-0">
                <div class="d-flex align-items-center gap-1 justify-content-center">
                    <select class="form-select form-select-sm mx-2" id="entries"
                        style="width: 70px; max-width: 70px; font-size: 1rem; padding: 0.25rem 0.5rem;">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="-1">All</option>
                    </select>
                </div>
                {{-- <div class="input-group" style="width: 100%; min-width: 200px;">
                    <span class="input-group-text border-end-0">
                        <i class="icon-base ti tabler-search"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" placeholder="Search"
                        aria-label="Search Shift" id="search-input">
                </div> --}}
            </div>
        </div>

        {{ $dataTable->table() }}
    </div>
</div>

@include('modules.HITUAM.HITUAM01.HITUAMF010.partials._configuration-form')
@endsection

@section('page-script')
{{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@vite(['resources/js/pages/hituam/hituam01/configuration/configuration.js'])
@endsection
