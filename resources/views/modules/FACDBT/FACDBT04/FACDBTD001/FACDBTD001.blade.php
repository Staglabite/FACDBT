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

    <div class="col-12 col-xl-6 mb-4">
        <div class="card h-100">
            <div class="card-header text-center pb-0">
                <h5 class="card-title mb-0 fw-bold">Counting Progress</h5>
            </div>
            <div class="card-body">
                <div id="chart-non-oil"></div>
            </div>
        </div>
    </div>
    

    <div class="col-12 col-xl-6 mb-4">
        <div class="card h-100">
            <div class="card-header text-center pb-0">
                <h5 class="card-title mb-0 fw-bold">Counting Progress Oil</h5>
            </div>
            <div class="card-body">
                <div id="chart-oil"></div>
            </div>
        </div>
    </div>
</div>

{{-- Filter Tombol Slot Waktu --}}
<div class="d-flex align-items-center gap-2 mb-4 overflow-auto pb-2" id="time-slots-container">
    {{-- Diisi secara dinamis oleh JavaScript --}}
</div>

{{-- Container Dua Chart Berdampingan --}}
<div class="row">
    {{-- Chart Kiri: Counting Progress (by Part) --}}
    <div class="col-12 col-xl-8 mb-4">
        <div class="card h-100 shadow-sm border">
            <div class="card-header d-flex justify-content-between align-items-center py-3">
                <h5 class="card-title mb-0 fw-bold">Counting Progress (by Part)</h5>
                <div class="d-flex align-items-center gap-3">
                    <span class="d-flex align-items-center gap-1 fs-tiny fw-semibold" style="color: #14229B;">
                        <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#14229B;"></span> % Remaining
                    </span>
                    <span class="d-flex align-items-center gap-1 fs-tiny fw-semibold" style="color: #E63946;">
                        <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#E63946;"></span> % Progress
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div id="chart-counting-progress" style="min-height: 380px;"></div>
            </div>
        </div>
    </div>

    {{-- Chart Kanan: Counting Oil Area --}}
    <div class="col-12 col-xl-4 mb-4">
        <div class="card h-100 shadow-sm border">
            <div class="card-header py-3">
                <h5 class="card-title mb-0 fw-bold text-center">Counting Oil Area</h5>
            </div>
            <div class="card-body">
                <div id="chart-oil-area" style="min-height: 380px;"></div>
            </div>
        </div>
    </div>
</div>
@vite(['resources/js/pages/FACDBT/FACDBT04/Dashboard-Main/dashboard.js'])

@endsection