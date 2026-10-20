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
            <div class="flex-grow-1" id="time-filter-container">
                {{-- Diisi secara dinamis oleh JavaScript --}}
            </div>
        </div>
    @endif

    @include('modules.FACDBT.FACDBT04.partials._variance-by-area', ['key' => $key, 'titles' => $titles])
@endforeach

@vite(['resources/js/pages/FACDBT/FACDBT04/Variance-Progress/dashboardvariance.js'])

@endsection
