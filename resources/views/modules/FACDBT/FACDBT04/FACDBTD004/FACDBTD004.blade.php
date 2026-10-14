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
    .nv-filter-item { cursor: pointer; user-select: none; }
    .nv-filter-item .nv-box { width: 13px; height: 13px; border: 1px solid #555; display: inline-block; flex-shrink: 0; }
    .nv-filter-item.active .nv-box { background-color: #252423; border-color: #252423; }
    .nv-table { font-size: 0.8rem; }
    .nv-table thead th { position: sticky; top: 0; z-index: 1; background-color: #E6E6E6; font-weight: 500; }
    .nv-table td, .nv-table th { padding: 0.3rem 0.6rem; border-color: #FFFFFF; }
    .nv-table .nv-group { background-color: #7F7F7F; color: #FFFFFF; vertical-align: top; cursor: pointer; white-space: nowrap; }
    .nv-positive tr.nv-odd td:not(.nv-group) { background-color: #A9D2F5; }
    .nv-negative tr.nv-odd td:not(.nv-group) { background-color: #F4C2AE; }
</style>

<div class="row g-3 mb-4">
    {{-- ===================== Filter ===================== --}}
    <div class="col-12 col-xl-2">
        <div class="d-flex flex-column gap-3 h-100">
            <div class="card shadow-none border border-dark rounded-0">
                <div class="card-body p-2">
                    <div class="text-center fw-semibold text-dark mb-1">TIME</div>
                    {{-- Satu slot; klik lagi untuk menghapus filter waktu --}}
                    <div class="d-flex flex-column gap-1 overflow-auto" style="max-height: 180px;" id="nv-time-filter"></div>
                </div>
            </div>
            <div class="card shadow-none border border-dark rounded-0 flex-fill">
                <div class="card-body p-2">
                    <div class="text-center fw-semibold text-dark mb-1">AREA</div>
                    {{-- Bisa pilih lebih dari satu area; tanpa pilihan = semua area --}}
                    <div class="d-flex flex-column gap-1 overflow-auto" style="max-height: 560px;" id="nv-area-filter"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Konten ===================== --}}
    <div class="col-12 col-xl-10">
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-4 col-xl-3">
                <div class="card h-100 shadow-sm border border-dark rounded-3">
                    <div class="card-header text-center pt-2 pb-0">
                        <div class="fw-semibold text-dark">Nett Variance</div>
                    </div>
                    <div class="card-body p-1">
                        <div id="nv-chart-posneg" style="min-height: 230px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-8 col-xl-7">
                <div class="card h-100 shadow-sm border border-dark rounded-3">
                    <div class="card-header text-center pt-2 pb-0">
                        <div class="fw-semibold text-dark">Nett Variance by Area</div>
                    </div>
                    <div class="card-body px-2 pt-0 pb-1">
                        <div id="nv-chart-area" style="min-height: 230px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-2">
                <div class="d-flex flex-column gap-3 h-100">
                    <div class="card shadow-none rounded-3 text-center text-white flex-fill justify-content-center" style="background-color: #000;">
                        <div class="card-body py-2">
                            <div class="fw-bold fs-3" id="nv-nett-qty">-</div>
                            <div class="small fw-semibold">Nett Var Qty</div>
                        </div>
                    </div>
                    <div class="card shadow-none rounded-3 text-center text-white flex-fill justify-content-center" style="background-color: #000;">
                        <div class="card-body py-2">
                            <div class="fw-bold fs-3" id="nv-nett-amount">-</div>
                            <div class="small fw-semibold">Nett Var Amount</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            @foreach (['positive' => 'Positive', 'negative' => 'Negative'] as $key => $label)
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

                    {{-- Detail part --}}
                    <div class="overflow-auto bg-white" style="max-height: 460px;">
                        <table class="table table-sm mb-0 nv-table nv-{{ $key }}">
                            <thead>
                                <tr>
                                    <th class="text-center">Part Name</th>
                                    <th class="text-center">Part No</th>
                                    <th class="text-end">Var Qty</th>
                                    <th class="text-end">Var Amount</th>
                                </tr>
                            </thead>
                            <tbody id="nv-{{ $key }}-table"></tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-1 d-none" id="nv-{{ $key }}-note"></div>
                </div>
            @endforeach
        </div>
    </div>
</div>

@vite(['resources/js/pages/FACDBT/FACDBT04/Nett-Variance/dashboardnettvariance.js'])

@endsection
