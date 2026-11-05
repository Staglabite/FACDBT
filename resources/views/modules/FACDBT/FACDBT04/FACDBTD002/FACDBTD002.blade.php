@extends('layouts/layoutMaster')

@section('title', 'Dashboard - Progress by Location')

@section('content')

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="javascript:void(0);">Dashboard Taking</a>
            </li>
            <li class="breadcrumb-item">
                <a href="javascript:void(0);">Dashboard</a>
            </li>
            <li class="breadcrumb-item active">Progress by Location</li>
        </ol>
    </nav>

{{-- Filter Waktu: berlaku untuk semua chart --}}
<div class="d-flex align-items-center gap-3 mb-3">
    <div class="flex-grow-1 border border-dark rounded-3 shadow-sm bg-white px-3 py-2">
        {{-- Filter rentang waktu Dari - Sampai --}}
        <div id="time-filter-container">
            {{-- Diisi secara dinamis oleh JavaScript --}}
        </div>
    </div>
</div>

{{-- Counting Progress (by Part), sama dengan Main Dashboard. Klik area = filter area chart Progress by Loc --}}
<div class="card shadow-sm border border-dark rounded-3 mb-3">
    <div class="card-header pt-3 pb-0 d-flex align-items-center justify-content-between gap-2">
        <h5 class="card-title mb-0 fw-semibold">Counting Progress (by Part)</h5>
        <button type="button" class="btn btn-sm btn-dark py-0 px-2 d-none align-items-center gap-1" id="selected-area" title="Hapus filter area">
            <span></span><i class="icon-base ti tabler-x"></i>
        </button>
    </div>
    <div class="card-body pt-0 pb-2">
        <div class="small text-muted">Klik area pada chart untuk memfilter Progress by Loc.</div>
        <div id="chart-counting-progress"></div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-xl-6">
        <div class="card h-100 shadow-sm border border-dark rounded-3">
            <div class="card-header pt-3 pb-0">
                <h5 class="card-title mb-0 fw-semibold">Progress by Loc (% Item)</h5>
            </div>
            <div class="card-body pt-0">
                <div id="chart-progress-item" style="min-height: 300px;"></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card h-100 shadow-sm border border-dark rounded-3">
            <div class="card-header pt-3 pb-0">
                <h5 class="card-title mb-0 fw-semibold">Progress by Loc (%Qty)</h5>
            </div>
            <div class="card-body pt-0">
                <div id="chart-progress-qty" style="min-height: 300px;"></div>
            </div>
        </div>
    </div>
</div>

@vite(['resources/js/pages/FACDBT/FACDBT04/Dashboard-Progress/dashboardprogress.js'])

@endsection
