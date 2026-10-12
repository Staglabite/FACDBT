@extends('layouts/layoutMaster')

@section('title', 'Dashboard - Counting Progress')

@section('content')

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="javascript:void(0);">Dashboard Taking</a>
            </li>
            <li class="breadcrumb-item">
                <a href="javascript:void(0);">Dashboard</a>
            </li>
            <li class="breadcrumb-item active">Main Dashboard</li>
        </ol>
    </nav>

<div class="row">

    <div class="col-12 col-xl-8 mb-4">
        <div class="card h-100 shadow-none border border-dark rounded-3">
            <div class="card-header text-center pt-3 pb-0">
                <h5 class="card-title mb-0 fw-semibold">Counting Progress</h5>
            </div>
            <div class="card-body pt-0">
                <div id="chart-non-oil"></div>
            </div>
        </div>
    </div>
    

    <div class="col-12 col-xl-4 mb-4">
        <div class="card h-100 shadow-none border border-dark rounded-3">
            <div class="card-header text-center pt-3 pb-0">
                <h5 class="card-title mb-0 fw-semibold">Counting Progress Oil</h5>
            </div>
            <div class="card-body pt-0">
                <div id="chart-oil"></div>
            </div>
        </div>
    </div>
</div>

{{-- Filter Tombol Slot Waktu --}}
<div class="d-flex align-items-center gap-1 mb-4">
    <div class="d-flex align-items-center gap-2 overflow-auto pb-1 flex-grow-1" id="time-slots-container"
        style="scrollbar-width: none;">
        {{-- Diisi secara dinamis oleh JavaScript --}}
    </div>
</div>

{{-- Container Dua Chart Berdampingan --}}
<div class="row">
    {{-- Chart Kiri: Counting Progress (by Part) --}}
    <div class="col-12 col-xl-8 mb-4">
        <div class="card h-100 shadow-none border border-dark rounded-3">
            <div class="card-header text-center pt-3 pb-0">
                <h5 class="card-title mb-0 fw-semibold">Counting Progress (by Part)</h5>
            </div>
            <div class="card-body pt-0">
                {{-- Legend % Remaining / % Progress dirender oleh ApexCharts --}}
                <div id="chart-counting-progress" style="min-height: 400px;"></div>
            </div>
        </div>
    </div>

    {{-- Chart Kanan: Counting Oil Area --}}
    <div class="col-12 col-xl-4 mb-4">
        <div class="card h-100 shadow-none border border-dark rounded-3">
            <div class="card-header text-center pt-3 pb-0">
                <h5 class="card-title mb-0 fw-semibold">Counting Oil Area</h5>
            </div>
            <div class="card-body pt-0">
                <div id="chart-oil-area" style="min-height: 400px;"></div>
            </div>
        </div>
    </div>
</div>
@vite(['resources/js/pages/FACDBT/FACDBT04/Dashboard-Main/dashboard.js'])

@endsection