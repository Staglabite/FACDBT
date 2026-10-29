@extends('layouts/layoutMaster')

@section('title', 'Dashboard - Counting Progress TWH')

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

<style>
    /* Filter interaktif: chart / card bisa diklik, yang tidak dipilih dipudarkan */
    .fd-clickable { cursor: pointer; }
    .card.fd-dim { opacity: .35; transition: opacity .2s ease; }
</style>

{{--
    Tampilan Main Dashboard saat event periode berjalan = Taking TWH/Indirect (part yang PART_NO-nya mengandung "TW1").
    Atas  : Counting Progress (semua part) & Absolute Variance per jam slot (tidak ikut filter Time)
    Bawah : Counting Progress (by Part), Nett / Absolute Variance, Nett Variance per accounting group (ikut filter Time)
--}}
<div class="d-none" id="dashboard-layout" data-layout="{{ $layout ?? 'twh' }}"></div>

{{-- Filter aktif dari klik chart (jam, area, accounting group); disembunyikan bila tidak ada --}}
<div class="d-none d-flex flex-wrap align-items-center gap-2 mb-2" id="chart-filter-bar"></div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-7">
        <div class="card h-100 shadow-none border border-dark rounded-3">
            <div class="card-header text-center pt-3 pb-0">
                <h5 class="card-title mb-0 fw-semibold">Counting Progress</h5>
            </div>
            <div class="card-body pt-0 pb-2">
                <div id="chart-twh-progress" style="min-height: 280px;"></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="card h-100 shadow-none border border-dark rounded-3">
            <div class="card-header text-center pt-3 pb-0">
                <h5 class="card-title mb-0 fw-semibold">Absolute Variance</h5>
            </div>
            <div class="card-body pt-0 pb-1">
                <div id="chart-variance-trend" style="min-height: 280px;"></div>
            </div>
        </div>
    </div>
</div>

{{-- Filter rentang waktu Dari - Sampai (chart bawah & variance) --}}
<div class="mb-3" id="time-slots-container">
    {{-- Diisi secara dinamis oleh JavaScript --}}
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-7">
        <div class="card h-100 shadow-none border border-dark rounded-3">
            <div class="card-header text-center pt-3 pb-0">
                <h5 class="card-title mb-0 fw-semibold">Counting Progress (by Part)</h5>
            </div>
            <div class="card-body pt-0 pb-2">
                <div id="chart-counting-progress"></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="d-flex flex-column gap-3 h-100">
            <div class="row g-3">
                <div class="col-6">
                    <div class="card h-100 shadow-none rounded-3 text-center text-white" style="background-color: #A6A6A6;">
                        <div class="card-body py-2 px-2">
                            <div class="fw-bold text-nowrap" style="font-size: clamp(1rem, 1.6vw, 1.6rem);" id="variance-nett-total">-</div>
                            <div class="small fw-semibold">Nett Variance</div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card h-100 shadow-none rounded-3 text-center text-white" style="background-color: #5F5F5F;">
                        <div class="card-body py-2 px-2">
                            <div class="fw-bold text-nowrap" style="font-size: clamp(1rem, 1.6vw, 1.6rem);" id="variance-absolute-total">-</div>
                            <div class="small fw-semibold">Absolute Variance</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card flex-fill shadow-none border border-dark rounded-3">
                <div class="card-header text-center pt-3 pb-0">
                    <h5 class="card-title mb-0 fw-semibold">Nett Variance</h5>
                </div>
                <div class="card-body px-2 pt-0 pb-1">
                    {{-- Waterfall per accounting_group part TW1 --}}
                    <div id="chart-nett-variance" style="min-height: 280px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

@vite(['resources/js/pages/FACDBT/FACDBT04/Dashboard-Main/dashboard.js'])

@endsection
