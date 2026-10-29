@extends('layouts/layoutMaster')

@section('title', 'FACDBT - Master Event')

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="javascript:void(0);">Dashboard Taking</a>
            </li>
            <li class="breadcrumb-item">
                <a href="javascript:void(0);">Basic Data</a>
            </li>
            <li class="breadcrumb-item active">Master Event</li>
        </ol>
    </nav>

    <div class="card">
        <div class="card-datatable">
            <div class="d-flex justify-content-between align-items-center my-5 flex-column flex-xl-row px-6">
                <div class="d-flex align-items-center gap-2 justify-content-center">
                    @serve('FACDBTF006-Create')
                        <button type="button" class="btn btn-primary d-flex align-items-center gap-2 px-2 py-2"
                            id="btn-create-event">
                            <i class="icon-base ti tabler-plus"></i>
                            <span>Add</span>
                        </button>
                    @endserve
                    <button type="button" class="btn btn-primary d-flex align-items-center gap-2 py-2 px-3"
                        id="download-template">
                        <i class="icon-base ti tabler-download"></i> Download Template Part
                    </button>
                    @serve('FACDBTF006-Delete')
                        <button type="button" class="btn btn-primary waves-effect d-none" id="btn-delete-selected">
                            <i class="icon-base ti tabler-trash"></i>
                            <span>Delete</span>
                        </button>
                    @endserve
                </div>
                <div class="d-flex gap-2 align-items-center flex-column flex-lg-row mt-2 mt-xxl-0">
                    <select class="form-select form-select-sm mx-2" id="entries"
                        style="width: 70px; max-width: 70px; font-size: 1rem; padding: 0.25rem 0.5rem;">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <div class="input-group" style="width: 100%; min-width: 200px;">
                        <span class="input-group-text border-end-0">
                            <i class="icon-base ti tabler-search"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" placeholder="Search"
                            aria-label="Search Event" id="search-input">
                    </div>
                </div>
            </div>

            <div class="px-6 pb-4 small text-muted">
                <div><b>Pre-Taking</b>: semua book ditransfer, dashboard hanya menampilkan part di daftar part event.</div>
                <div><b>Taking TWH/Indirect</b>: hanya Part No yang mengandung "TW1".</div>
                <div><b>Taking Besar/Direct</b>: semua Part No kecuali yang mengandung "TW1".</div>
            </div>

            {{ $dataTable->table(['class' => 'table'], true) }}
        </div>
    </div>

    @include('modules.FACDBT.FACDBT01.FACDBTF006.partials._event-form')
    @include('modules.FACDBT.FACDBT01.FACDBTF006.partials._event-parts-modal')
@endsection

@section('page-script')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
    @vite(['resources/js/pages/FACDBT/FACDBT01/Event/event.js'])
@endsection
