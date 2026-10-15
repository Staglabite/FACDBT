@extends('layouts/layoutMaster')

@section('title', 'FACDBT - Master Parts')

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="javascript:void(0);">Dashboard Taking</a>
            </li>
            <li class="breadcrumb-item">
                <a href="javascript:void(0);">Basic Data</a>
            </li>
            <li class="breadcrumb-item active">Master Parts</li>
        </ol>
    </nav>

    <div class="card">
        <div class="card-datatable">
            <div class="d-flex justify-content-between align-items-center my-5 flex-column flex-xl-row px-6">
                <div class="d-flex align-items-center gap-2 justify-content-center">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary d-flex align-items-center gap-2 px-2 py-2 w-100"
                            id="btn-create-part">
                            <i class="icon-base ti tabler-plus"></i>
                            <span>Add</span>
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary d-flex align-items-center gap-2 py-2 px-3"
                            id="export-excel">
                            <i class="icon-base ti tabler-upload"></i> Export
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary d-flex align-items-center gap-2 py-2 px-3"
                            id="import-excel">
                            <i class="icon-base ti tabler-upload"></i> Import
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary d-flex align-items-center gap-2 py-2 px-3"
                            id="download-template">
                            <i class="icon-base ti tabler-download"></i> Download Template
                        </button>
                    </div>
                    @serve('FACDBTF002-Delete')
                        <button type="button" class="btn btn-primary waves-effect waves-effect d-none"
                            id="btn-delete-selected">
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
                            aria-label="Search Part" id="search-input">
                    </div>
                </div>
            </div>

            {{ $dataTable->table(['class' => 'table'], true) }}
        </div>
    </div>

    {{-- Path include sudah disesuaikan dengan folder FACDBT01/FACDBT002 --}}
    @include('modules.FACDBT.FACDBT01.FACDBTF002.partials._part-form')
    @include('modules.FACDBT.FACDBT01.FACDBTF002.partials._part-import-modal')
    @include('modules.FACDBT.FACDBT01.FACDBTF002.partials._part-import-error')
@endsection

@section('page-script')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
    {{-- Path vite juga disesuaikan ke folder facdbt01 --}}
    @vite([
        'resources/js/pages/facdbt/facdbt01/part/part.js', 
        'resources/js/pages/facdbt/facdbt01/part/part-import.js'
    ])
@endsection