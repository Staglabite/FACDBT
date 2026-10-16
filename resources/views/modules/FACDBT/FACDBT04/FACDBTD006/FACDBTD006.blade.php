@extends('layouts/layoutMaster')

@section('title', 'Dashboard - History Taking')

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="javascript:void(0);">Dashboard Taking</a>
            </li>
            <li class="breadcrumb-item">
                <a href="javascript:void(0);">Dashboard</a>
            </li>
            <li class="breadcrumb-item active">History Taking</li>
        </ol>
    </nav>

    <div class="card">
        <div class="card-datatable">
            <div class="d-flex justify-content-between align-items-center my-5 flex-column flex-xl-row gap-3 px-6">
                <div class="d-flex align-items-center gap-2 justify-content-center flex-wrap">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary d-flex align-items-center gap-2 py-2 px-3"
                            id="export-excel">
                            <i class="icon-base ti tabler-upload"></i> Export
                        </button>
                    </div>

                    {{-- Filter periode: Year -> Month -> Event (Event hanya tampil bila 1 bulan punya >1 taking) --}}
                    <select class="form-select form-select-sm" id="filter-year" style="width: 110px;" aria-label="Year">
                        <option value="">All Year</option>
                    </select>
                    <select class="form-select form-select-sm" id="filter-month" style="width: 140px;" aria-label="Month" disabled>
                        <option value="">All Month</option>
                    </select>
                    <select class="form-select form-select-sm d-none" id="filter-event" style="width: 190px;" aria-label="Event"></select>
                </div>
                <div class="d-flex gap-2 align-items-center flex-column flex-lg-row mt-2 mt-xxl-0">
                    <div class="d-flex align-items-center gap-1 justify-content-center">
                        <select class="form-select form-select-sm mx-2" id="entries"
                            style="width: 70px; max-width: 70px; font-size: 1rem; padding: 0.25rem 0.5rem;">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="500">500</option>
                        </select>
                    </div>
                    <div class="input-group" style="width: 100%; min-width: 200px;">
                        <span class="input-group-text border-end-0">
                            <i class="icon-base ti tabler-search"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" placeholder="Search"
                            aria-label="Search History Taking" id="search-input">
                    </div>
                </div>
            </div>

            {{ $dataTable->table(['class' => 'table'], true) }}
        </div>
    </div>
@endsection

@section('page-script')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
    @vite(['resources/js/pages/FACDBT/FACDBT04/Taking-History/takinghistory.js'])
@endsection
