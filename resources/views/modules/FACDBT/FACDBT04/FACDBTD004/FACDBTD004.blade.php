@extends('layouts/layoutMaster')

@section('title', 'Dashboard - Nett Variance')

@section('content')

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="javascript:void(0);">Dashboard Taking</a>
            </li>
            <li class="breadcrumb-item">
                <a href="javascript:void(0);">Dashboard</a>
            </li>
            <li class="breadcrumb-item active">Nett Variance</li>
        </ol>
    </nav>

<style>
    /* Kolom filter TIME / AREA: lebar tetap, nama panjang dipotong dengan "..." */
    .nv-filter-col { width: 140px; flex: 0 0 140px; min-width: 0; }
    .nv-filter-item { cursor: pointer; user-select: none; min-width: 0; }
    .nv-filter-item .nv-box { width: 12px; height: 12px; border: 1px solid #555; display: inline-block; flex-shrink: 0; }
    .nv-filter-item.active .nv-box { background-color: #252423; border-color: #252423; }
    /* Baris atas: tinggi chart & card mengikuti tinggi kotak TIME */
    .nv-top { height: 250px; }
    .nv-card-cols { width: 170px; flex: 0 0 170px; }
    /* Warna baris berselang: biru untuk tabel tidak minus, merah untuk tabel minus */
    #facdbtd004-positive-table tbody tr:nth-child(odd) td { background-color: #A9D2F5; }
    #facdbtd004-negative-table tbody tr:nth-child(odd) td { background-color: #F4C2AE; }
    @media (max-width: 1199.98px) {
        .nv-top { height: auto; }
        .nv-filter-col, .nv-card-cols { width: 100%; flex: 1 1 auto; }
    }
</style>

{{-- ===================== Baris atas: TIME + chart + card ===================== --}}
<div class="d-flex flex-column flex-xl-row gap-3 mb-3 nv-top">
    <div class="card shadow-none border border-dark rounded-0 nv-filter-col">
        <div class="card-body p-2 d-flex flex-column" style="min-height: 0;">
            <div class="text-center fw-semibold text-dark mb-1">TIME</div>
            {{-- Rentang waktu Dari - Sampai; Reset = seluruh periode taking --}}
            <div class="d-flex flex-column gap-1 overflow-auto flex-grow-1" style="min-height: 0;" id="nv-time-filter"></div>
        </div>
    </div>

    <div class="card shadow-sm border border-dark rounded-3" style="flex: 3 1 0; min-width: 0;">
        <div class="card-header text-center pt-2 pb-0">
            <div class="fw-semibold text-dark">Nett Variance</div>
        </div>
        <div class="card-body p-1">
            <div id="nv-chart-posneg"></div>
        </div>
    </div>

    <div class="card shadow-sm border border-dark rounded-3" style="flex: 7 1 0; min-width: 0;">
        <div class="card-header text-center pt-2 pb-0">
            <div class="fw-semibold text-dark">Nett Variance by Area</div>
        </div>
        <div class="card-body px-2 pt-0 pb-1">
            <div id="nv-chart-area"></div>
        </div>
    </div>

    <div class="d-flex flex-column gap-3 nv-card-cols">
        <div class="card shadow-none rounded-3 text-center text-white flex-fill" style="background-color: #000;">
            <div class="card-body py-2 d-flex flex-column justify-content-center">
                <div class="fw-bold fs-3" id="nv-nett-qty">-</div>
                <div class="small fw-semibold">Nett Var Qty</div>
            </div>
        </div>
        <div class="card shadow-none rounded-3 text-center text-white flex-fill" style="background-color: #000;">
            <div class="card-body py-2 d-flex flex-column justify-content-center">
                <div class="fw-bold fs-3" id="nv-nett-amount">-</div>
                <div class="small fw-semibold">Nett Var Amount</div>
            </div>
        </div>
    </div>
</div>

{{-- ===================== Baris bawah: AREA + tabel detail part ===================== --}}
<div class="d-flex flex-column flex-xl-row gap-3 mb-4">
    <div class="card shadow-none border border-dark rounded-0 nv-filter-col align-self-xl-start">
        <div class="card-body p-2">
            <div class="text-center fw-semibold text-dark mb-1">AREA</div>
            {{-- Bisa pilih lebih dari satu area; tanpa pilihan = semua area --}}
            <div class="d-flex flex-column gap-1 overflow-auto" style="max-height: 640px;" id="nv-area-filter"></div>
        </div>
    </div>

    <div class="row g-3 flex-grow-1" style="min-width: 0;">
        @foreach (['positive' => 'Positive', 'negative' => 'Negative'] as $key => $label)
            @php($table = $key === 'positive' ? $positiveTable : $negativeTable)
            <div class="col-12 col-lg-6">
                {{-- Ringkasan --}}
                <div class="d-flex justify-content-center gap-5 mb-2 text-center">
                    <div>
                        <div class="fs-3 {{ $key === 'negative' ? 'text-danger' : 'text-dark' }}" id="nv-{{ $key }}-qty">-</div>
                        <div class="small text-muted">{{ $label }} Var Qty</div>
                    </div>
                    <div>
                        <div class="fs-3 {{ $key === 'negative' ? 'text-danger' : 'text-dark' }}" id="nv-{{ $key }}-amount">-</div>
                        <div class="small text-muted">{{ $label }} Var Amount</div>
                    </div>
                </div>

                {{-- Detail part (DataTable, template seperti FACDBT01 tanpa tombol aksi) --}}
                <div class="card shadow-none border">
                    <div class="card-datatable">
                        <div class="d-flex justify-content-between align-items-center gap-2 my-3 px-3">
                            <select class="form-select form-select-sm nv-entries" data-table="facdbtd004-{{ $key }}-table"
                                style="width: 70px;">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <div class="input-group input-group-sm" style="max-width: 240px;">
                                <span class="input-group-text border-end-0">
                                    <i class="icon-base ti tabler-search"></i>
                                </span>
                                <input type="text" class="form-control border-start-0 nv-search" placeholder="Search"
                                    aria-label="Search {{ $label }} Part" data-table="facdbtd004-{{ $key }}-table">
                            </div>
                        </div>

                        {{ $table->table(['class' => 'table table-sm'], true) }}
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

@endsection

@section('page-script')
    {{ $positiveTable->scripts(attributes: ['type' => 'module']) }}
    {{ $negativeTable->scripts(attributes: ['type' => 'module']) }}
    @vite(['resources/js/pages/FACDBT/FACDBT04/Nett-Variance/dashboardnettvariance.js'])
@endsection
