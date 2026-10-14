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

{{--
    Layout 2 kolom:
    - Kiri  : Counting Progress -> tombol slot waktu -> Counting Progress (by Part)
    - Kanan : Counting Progress Oil + Counting Oil Area -> Absolute Variance per slot -> ringkasan variance
--}}
<div class="row g-3 mb-4">
    {{-- ===================== Kolom Kiri ===================== --}}
    <div class="col-12 col-xl-6">
      {{-- Tinggi natural kolom (tidak di-stretch) dipakai JS untuk menyamakan bawah kedua kolom --}}
      <div class="d-flex flex-column gap-3" id="dashboard-left">
        <div class="card shadow-none border border-dark rounded-3">
            <div class="card-header text-center pt-3 pb-0">
                <h5 class="card-title mb-0 fw-semibold">Counting Progress</h5>
            </div>
            <div class="card-body pt-0 pb-2">
                <div id="chart-non-oil" style="min-height: 260px;"></div>
            </div>
        </div>

        {{-- Filter Tombol Slot Waktu --}}
        <div class="d-flex align-items-center gap-2">
            <div class="border border-secondary px-3 py-2 text-body small flex-shrink-0 bg-white">Count</div>
            <div class="d-flex align-items-center gap-2 overflow-auto flex-grow-1" id="time-slots-container"
                style="scrollbar-width: none;">
                {{-- Diisi secara dinamis oleh JavaScript --}}
            </div>
        </div>

        <div class="card shadow-none border border-dark rounded-3">
            <div class="card-header text-center pt-3 pb-0">
                <h5 class="card-title mb-0 fw-semibold">Counting Progress (by Part)</h5>
            </div>
            <div class="card-body pt-0 pb-2">
                {{-- Legend % Remaining / % Progress dirender oleh ApexCharts.
                     Tinggi chart diatur JS supaya sejajar dengan bawah kolom kanan. --}}
                <div id="chart-counting-progress"></div>
            </div>
        </div>
      </div>
    </div>

    {{-- ===================== Kolom Kanan ===================== --}}
    <div class="col-12 col-xl-6">
      <div class="d-flex flex-column gap-3" id="dashboard-right">
        <div class="row g-3">
            <div class="col-12 col-md-7">
                <div class="card h-100 shadow-none border border-dark rounded-3">
                    <div class="card-header text-center pt-3 pb-0">
                        <h5 class="card-title mb-0 fw-semibold">Counting Progress Oil</h5>
                    </div>
                    <div class="card-body pt-0 pb-2">
                        <div id="chart-oil" style="min-height: 260px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-5">
                <div class="card h-100 shadow-none border border-dark rounded-3">
                    <div class="card-header text-center pt-3 pb-0">
                        <h6 class="card-title mb-0 fw-semibold">Counting Oil Area</h6>
                    </div>
                    <div class="card-body pt-0 pb-2">
                        <div id="chart-oil-area" style="min-height: 260px;"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Absolute Variance per slot waktu (tidak terpengaruh filter tombol waktu) --}}
        <div class="card shadow-none border border-dark rounded-3">
            <div class="card-header text-center pt-2 pb-0">
                <h6 class="card-title mb-0 fw-semibold">Absolute Variance</h6>
            </div>
            <div class="card-body pt-0 pb-1">
                <div id="chart-variance-trend" style="min-height: 190px;"></div>
            </div>
        </div>

        {{-- Variance: ringkasan per accounting group, Nett Variance (waterfall per part group), Absolute Variance --}}
        <div class="row g-2">
            {{-- RM / WIP / FG --}}
            <div class="col-12 col-md-3">
                <div class="d-flex flex-column gap-2 h-100" id="variance-accounting-groups">
                    @foreach (['RM', 'WIP', 'FG'] as $group)
                        <div class="card shadow-none border border-dark rounded-3 flex-fill">
                            <div class="card-body px-2 py-1">
                                <div class="small fw-semibold text-dark">{{ $group }}</div>
                                <div class="fs-5 fw-semibold text-dark text-center variance-value" data-group="{{ $group }}">-</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Chart Nett Variance + total --}}
            <div class="col-12 col-md-5">
                <div class="d-flex flex-column gap-2 h-100">
                    <div class="card shadow-none border border-dark rounded-3 flex-fill">
                        <div class="card-header text-center pt-1 pb-0">
                            <div class="small fw-semibold text-dark">Nett Variance</div>
                        </div>
                        <div class="card-body px-2 pt-0 pb-0">
                            <div id="chart-nett-variance" style="min-height: 130px;"></div>
                        </div>
                    </div>
                    <div class="card shadow-none rounded-3 text-center text-white" style="background-color: #000;">
                        <div class="card-body py-1">
                            <div class="small fw-semibold">Nett Variance</div>
                            <div class="fw-bold" style="font-size: 1.25rem;" id="variance-nett-total">-</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Absolute Variance per part group + total --}}
            <div class="col-12 col-md-4">
                <div class="d-flex flex-column gap-2 h-100">
                    <div class="d-flex flex-column gap-2 flex-fill" id="chart-absolute-variance">
                        {{-- Card per part group diisi oleh JavaScript --}}
                    </div>
                    <div class="card shadow-none rounded-3 text-center text-white" style="background-color: #000;">
                        <div class="card-body py-1">
                            <div class="small fw-semibold">Absolute Variance</div>
                            <div class="fw-bold" style="font-size: 1.25rem;" id="variance-absolute-total">-</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
      </div>
    </div>
</div>

@vite(['resources/js/pages/FACDBT/FACDBT04/Dashboard-Main/dashboard.js'])

@endsection