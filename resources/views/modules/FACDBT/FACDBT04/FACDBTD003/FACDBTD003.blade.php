@extends('layouts/layoutMaster')

@section('title', 'Dashboard - Variance by Area')

@section('content')

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="javascript:void(0);">Dashboard Taking</a>
            </li>
            <li class="breadcrumb-item">
                <a href="javascript:void(0);">Dashboard</a>
            </li>
            <li class="breadcrumb-item active">Variance by Area</li>
        </ol>
    </nav>

@php
    // Dua bagian dengan layout sama: variance amount (atas) dan variance qty (bawah)
    $sections = [
        'amount' => [
            'nett'          => 'Nett Variance by Area',
            'absolute'      => 'Absolute Var by Area',
            'absoluteTotal' => 'Absolute Variance',
        ],
        'qty' => [
            'nett'          => 'Variance Qty by Area',
            'absolute'      => 'Absolute Qty by Area',
            'absoluteTotal' => 'Absolute Var Qty',
        ],
    ];
@endphp

@foreach ($sections as $key => $titles)
    @if ($key === 'qty')
        {{-- Filter Waktu (berlaku untuk semua chart di halaman ini) --}}
        <div class="d-flex align-items-center gap-3 mb-3">
            <div class="d-flex align-items-center gap-2 small text-body flex-shrink-0">
                <span class="d-inline-block bg-dark" style="width: 12px; height: 12px;"></span> Count
            </div>
            <div class="flex-grow-1" id="time-filter-container">
                {{-- Diisi secara dinamis oleh JavaScript --}}
            </div>
        </div>
    @endif

    <div class="row g-3 mb-3">
        {{-- Nett variance per area (waterfall) --}}
        <div class="col-12 col-xl-10">
            <div class="card h-100 shadow-sm border border-dark rounded-3">
                <div class="card-header text-center pt-2 pb-0">
                    <div class="small fw-semibold text-dark">{{ $titles['nett'] }}</div>
                </div>
                <div class="card-body px-2 pt-0 pb-1">
                    <div id="chart-{{ $key }}-nett" style="min-height: 240px;"></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-2">
            <div class="d-flex flex-column gap-3 h-100">
                <div class="card shadow-none rounded-3 text-center text-white flex-fill justify-content-center" style="background-color: #000;">
                    <div class="card-body py-2">
                        <div class="fw-bold fs-3 nett-var-qty">-</div>
                        <div class="small fw-semibold">Nett Var Qty</div>
                    </div>
                </div>
                <div class="card shadow-none rounded-3 text-center text-white flex-fill justify-content-center" style="background-color: #000;">
                    <div class="card-body py-2">
                        <div class="fw-bold fs-3 nett-var-amount">-</div>
                        <div class="small fw-semibold">Nett Var Amount</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Absolute variance per area (waterfall) --}}
        <div class="col-12 col-xl-10">
            <div class="card h-100 shadow-sm border border-dark rounded-3">
                <div class="card-header text-center pt-2 pb-0">
                    <div class="small fw-semibold text-dark">{{ $titles['absolute'] }}</div>
                </div>
                <div class="card-body px-2 pt-0 pb-1">
                    <div id="chart-{{ $key }}-absolute" style="min-height: 240px;"></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-2">
            <div class="d-flex flex-column gap-2 h-100">
                <div class="card shadow-none rounded-3 text-center text-white" style="background-color: #0F1C6E;">
                    <div class="card-body py-2">
                        <div class="fw-bold fs-3" id="total-{{ $key }}-absolute">-</div>
                        <div class="small fw-semibold">{{ $titles['absoluteTotal'] }}</div>
                    </div>
                </div>
                {{-- Var (+) / Var (-) --}}
                <div class="card shadow-sm border border-dark rounded-3 flex-fill">
                    <div class="card-body p-1">
                        <div id="chart-{{ $key }}-posneg" style="min-height: 150px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endforeach

@vite(['resources/js/pages/FACDBT/FACDBT04/Variance-Progress/dashboardvariance.js'])

@endsection
