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
{{-- Variance: ringkasan per accounting group, Nett Variance (waterfall per part group), Absolute Variance --}}
<div class="row g-3 mb-4">
    {{-- Kolom kiri: RM / WIP / FG --}}
    <div class="col-12 col-md-3 col-xl-2">
        <div class="d-flex flex-column gap-3 h-100" id="variance-accounting-groups">
            @foreach (['RM', 'WIP', 'FG'] as $group)
                <div class="card shadow-none border border-dark rounded-3 flex-fill">
                    <div class="card-body px-3 py-2">
                        <div class="fs-5 text-dark">{{ $group }}</div>
                        <div class="fs-3 fw-semibold text-dark text-end variance-value" data-group="{{ $group }}">-</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Kolom tengah: chart Nett Variance + total --}}
    <div class="col-12 col-md-5 col-xl-5">
        <div class="d-flex flex-column gap-3 h-100">
            <div class="card shadow-none border border-dark rounded-3 flex-fill">
                <div class="card-header text-center pt-2 pb-0">
                    <h6 class="card-title mb-0 fw-semibold">Nett Variance</h6>
                </div>
                <div class="card-body pt-0 pb-1">
                    <div id="chart-nett-variance" style="min-height: 200px;"></div>
                </div>
            </div>
            <div class="card shadow-none rounded-3 text-center text-white" style="background-color: #000;">
                <div class="card-body py-3">
                    <div class="fs-5 fw-semibold">Nett Variance</div>
                    <div class="fw-bold" style="font-size: 2rem;" id="variance-nett-total">-</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Kolom kanan: Absolute Variance per part group + total --}}
    <div class="col-12 col-md-4 col-xl-5">
        <div class="d-flex flex-column gap-3 h-100">
            <div class="d-flex flex-column gap-3 flex-fill" id="chart-absolute-variance">
                {{-- Card per part group diisi oleh JavaScript --}}
            </div>
            <div class="card shadow-none rounded-3 text-center text-white" style="background-color: #000;">
                <div class="card-body py-3">
                    <div class="fs-5 fw-semibold">Absolute Variance</div>
                    <div class="fw-bold" style="font-size: 2rem;" id="variance-absolute-total">-</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Absolute Variance per slot waktu (tidak terpengaruh filter tombol waktu) --}}
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-none border border-dark rounded-3">
            <div class="card-header text-center pt-3 pb-0">
                <h5 class="card-title mb-0 fw-semibold">Absolute Variance</h5>
            </div>
            <div class="card-body pt-0">
                <div id="chart-variance-trend" style="min-height: 290px;"></div>
            </div>
        </div>
    </div>
</div>

@vite(['resources/js/pages/FACDBT/FACDBT04/Dashboard-Main/dashboard.js'])

@endsection