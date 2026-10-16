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

{{-- Filter Group Location (SIM_INV_GROUP_LOCATION.location_group) --}}
<div class="d-flex align-items-center gap-1 px-1 py-2 mb-3 border border-dark rounded-3 shadow-sm"
    style="background-color: #E7D9A0;">
    <button type="button" class="btn btn-sm btn-link text-dark px-1 group-scroller" data-direction="-1"
        aria-label="Previous location groups">
        <i class="icon-base ti tabler-chevron-left"></i>
    </button>
    <div class="d-flex align-items-center gap-2 overflow-auto flex-grow-1" id="group-filter-container"
        style="scrollbar-width: none;">
        {{-- Diisi secara dinamis oleh JavaScript --}}
    </div>
    <button type="button" class="btn btn-sm btn-link text-dark px-1 group-scroller" data-direction="1"
        aria-label="Next location groups">
        <i class="icon-base ti tabler-chevron-right"></i>
    </button>
</div>

{{-- Filter Waktu --}}
<div class="d-flex align-items-center gap-3 mb-4">
    <div class="flex-grow-1 border border-dark rounded-3 shadow-sm bg-white px-3 py-2">
        {{-- Filter rentang waktu Dari - Sampai --}}
        <div id="time-filter-container">
            {{-- Diisi secara dinamis oleh JavaScript --}}
        </div>
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
