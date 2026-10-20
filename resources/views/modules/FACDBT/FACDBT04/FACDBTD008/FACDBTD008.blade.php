@extends('layouts/layoutMaster')

@section('title', 'Dashboard - Taking Data Revision')

@section('content')

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="javascript:void(0);">Dashboard Taking</a>
            </li>
            <li class="breadcrumb-item">
                <a href="javascript:void(0);">Dashboard</a>
            </li>
            <li class="breadcrumb-item active">Taking Data Revision</li>
        </ol>
    </nav>

<style>
    .tr-row { display: flex; gap: .75rem; align-items: stretch; }
    .tr-row > .card { min-width: 0; height: 360px; }
    .tr-chart-main { flex: 4 1 0; }
    .tr-slicer { flex: 1.3 1 0; }
    .tr-chart-side { flex: 1.7 1 0; }
    .tr-title { font-weight: 700; color: #333; }
    /* Slicer seperti Excel: tanpa pilihan semua tombol aktif; dengan pilihan hanya yang dipilih */
    /* Daftar mengisi sisa tinggi card dan bisa di-scroll; tombol tidak boleh menyusut (menumpuk) */
    .tr-slicer-list { display: flex; flex-direction: column; gap: 6px; overflow-y: auto; flex: 1 1 0; min-height: 0; padding-right: 2px; }
    .tr-slicer-item {
        flex-shrink: 0;
        border: 1px solid #5B9BD5; border-radius: 3px; background: #9DD3F0; color: #1F1F1F;
        padding: 3px 8px; font-size: .8rem; text-align: left; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .tr-slicer-list.has-selection .tr-slicer-item:not(.active) { background: #FFFFFF; border-color: #BFBFBF; color: #7F7F7F; }
    .tr-slicer-clear { border: 0; background: transparent; color: #7F7F7F; padding: 0 2px; line-height: 1; }
    .tr-slicer-clear:disabled { opacity: .35; }
    @media (max-width: 1199.98px) {
        .tr-row { flex-wrap: wrap; }
        .tr-row > .card { flex: 1 1 100%; height: auto; }
        .tr-slicer-list { max-height: 220px; }
    }
</style>

<div class="tr-row mb-4">
    {{-- Item_Book & % Area per area --}}
    <div class="card shadow-none border border-dark rounded-0 tr-chart-main">
        <div class="card-header text-center pt-2 pb-0">
            <h5 class="tr-title mb-0">Taking Data Revision</h5>
        </div>
        <div class="card-body p-1">
            <div id="tr-chart-area"></div>
        </div>
    </div>

    {{-- Filter: boleh pilih lebih dari satu; tanpa pilihan = semua --}}
    @foreach (['modifier' => 'Modified By', 'area' => 'Area'] as $key => $label)
        <div class="card shadow-none border border-dark rounded-0 tr-slicer">
            <div class="card-body p-2 d-flex flex-column" style="min-height: 0;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-semibold text-dark text-uppercase small">{{ $label }}</span>
                    <button type="button" class="tr-slicer-clear" id="tr-{{ $key }}-clear" title="Clear filter" disabled>
                        <i class="icon-base ti tabler-filter-off"></i>
                    </button>
                </div>
                <div class="tr-slicer-list flex-grow-1" id="tr-{{ $key }}-filter"></div>
            </div>
        </div>
    @endforeach

    <div class="card shadow-none border border-dark rounded-0 tr-chart-side">
        <div class="card-header text-center pt-2 pb-0">
            <h5 class="tr-title mb-0">Reason Revision</h5>
        </div>
        <div class="card-body p-1">
            <div id="tr-chart-reason"></div>
        </div>
    </div>

    <div class="card shadow-none border border-dark rounded-0 tr-chart-side">
        <div class="card-header text-center pt-2 pb-0">
            <h5 class="tr-title mb-0">Revision Performance</h5>
        </div>
        <div class="card-body p-1">
            <div id="tr-chart-performance"></div>
        </div>
    </div>
</div>

{{-- Tabel semua revisi: seluruh periode taking (tidak hanya yang sedang berjalan), filter Year -> Month -> Event --}}
<div class="card">
    <div class="card-datatable">
        <div class="d-flex justify-content-between align-items-center my-5 flex-column flex-xl-row gap-3 px-6">
            <div class="d-flex align-items-center gap-2 justify-content-center flex-wrap">
                <button type="button" class="btn btn-primary d-flex align-items-center gap-2 py-2 px-3" id="export-excel">
                    <i class="icon-base ti tabler-upload"></i> Export
                </button>

                {{-- Event hanya tampil bila 1 bulan punya >1 taking --}}
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
                        aria-label="Search Taking Data Revision" id="search-input">
                </div>
            </div>
        </div>

        {{ $dataTable->table(['class' => 'table'], true) }}
    </div>
</div>
@endsection

@section('page-script')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
    @vite([
        'resources/js/pages/FACDBT/FACDBT04/Taking-Revision/takingrevision.js',
        'resources/js/pages/FACDBT/FACDBT04/Taking-Revision/revisiontable.js',
    ])
@endsection
