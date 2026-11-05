@extends('layouts/layoutMaster')

@section('title', 'HITUAM - Basic Data Menu')

@section('page-style')
    @vite(['resources/assets/vendor/libs/select2/select2.scss'])
@endsection

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="javascript:void(0);">User Access Management</a>
            </li>
            <li class="breadcrumb-item">
                <a href="javascript:void(0);">Basic Data</a>
            </li>
            <li class="breadcrumb-item active">Master Menu</li>
        </ol>
    </nav>

    <div class="card">
        <div class="card-datatable">
            <div class="d-flex justify-content-between align-items-center my-5 flex-column flex-xl-row px-6">
                <div class="d-flex align-items-center gap-2 justify-content-center">
                    @serve('HITUAMF002-Create')
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary d-flex align-items-center gap-2 px-2 py-2 w-100"
                                id="btn-create-menu">
                                <i class="icon-base ti tabler-plus"></i>
                                <span>Add</span>
                            </button>
                        </div>
                    @endserve
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary d-flex align-items-center gap-2 py-2 px-3"
                            id="export-excel">
                            <i class="icon-base ti tabler-upload"></i> Export
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary d-flex align-items-center gap-2 py-2 px-3"
                            id="import-excel">
                            <i class="icon-base ti tabler-download"></i> Import
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary d-flex align-items-center gap-2 py-2 px-3"
                            id="download-template">
                            <i class="icon-base ti tabler-upload"></i> Download Template
                        </button>
                    </div>
                    @serve('HITUAMF002-Delete')
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
                    <div class="input-group" style="width: 100%; min-width: 200px;">
                        <span class="input-group-text border-end-0">
                            <i class="icon-base ti tabler-search"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" placeholder="Search"
                            aria-label="Search Shift" id="search-input">
                    </div>
                </div>
            </div>

            {{ $dataTable->table(['class' => 'table'], true) }}
        </div>
    </div>

    @include('modules.HITUAM.HITUAM01.HITUAMF002.partials._menu-form')
    @include('modules.HITUAM.HITUAM01.HITUAMF002.partials._menu-import-modal')
    @include('modules.HITUAM.HITUAM01.HITUAMF002.partials._menu-import-error')
@endsection

@section('page-script')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
    @vite(['resources/js/pages/hituam/hituam01/menu/menu.js', 'resources/js/pages/hituam/hituam01/menu/menu-import.js', 'resources/assets/vendor/libs/select2/select2.js'])
@endsection
