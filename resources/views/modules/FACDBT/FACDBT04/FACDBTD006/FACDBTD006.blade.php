@extends('layouts/layoutMaster')

@section('title', 'Dashboard - Taking Data Revision')

@section('content')

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="javascript:void(0);">Dashboard Taking</a>
            </li>
            <li class="breadcrumb-item">
                <a href="javascript:void(0);">Dashboard</a>
            </li>
            <li class="breadcrumb-item active">Taking Data Revision</li>
        </ol>
    </nav>

<style>
    /* Baris 1: chart Taking Data Revision (lebar penuh); baris 2: Reason Revision & Revision Performance */
    .tr-row { display: flex; gap: .75rem; align-items: stretch; }
    .tr-row > .card { min-width: 0; flex: 1 1 0; }
    .tr-row-main > .card { height: 500px; }
    .tr-row-side > .card { height: 440px; }
    .tr-title { font-weight: 700; color: #333; }
    @media (max-width: 1199.98px) {
        .tr-row { flex-wrap: wrap; }
        .tr-row > .card { flex: 1 1 100%; height: auto; }
    }
</style>

{{-- Tampil bila halaman dibuka dengan ?demo=1 --}}
<div class="alert alert-warning py-2 mb-3 d-none" id="tr-demo-badge">
    <strong>DATA DEMO</strong> &mdash; angka contoh untuk presentasi, bukan data taking asli.
</div>

<div class="tr-row tr-row-main mb-3">
    {{-- Item_Book & % Area per area. Klik area = filter area chart lain --}}
    <div class="card shadow-none border border-dark rounded-0">
        <div class="card-header pt-2 pb-0 d-flex justify-content-center align-items-center gap-2">
            <h5 class="tr-title mb-0">Taking Data Revision</h5>
            <button type="button" class="btn btn-sm btn-dark py-0 px-2 d-none" id="tr-area-selected" title="Hapus filter area">
                <span></span> <i class="icon-base ti tabler-x"></i>
            </button>
        </div>
        <div class="card-body p-1">
            <div id="tr-chart-area"></div>
        </div>
    </div>
</div>

<div class="tr-row tr-row-side mb-4">
    <div class="card shadow-none border border-dark rounded-0">
        <div class="card-header text-center pt-2 pb-0">
            <h5 class="tr-title mb-0">Reason Revision</h5>
        </div>
        <div class="card-body p-1">
            <div id="tr-chart-reason"></div>
        </div>
    </div>

    <div class="card shadow-none border border-dark rounded-0">
        <div class="card-header pt-2 pb-0 d-flex justify-content-center align-items-center gap-2">
            <h5 class="tr-title mb-0">Revision Performance</h5>
            {{-- Klik nama = filter modified by chart lain --}}
            <button type="button" class="btn btn-sm btn-dark py-0 px-2 d-none" id="tr-modifier-selected" title="Hapus filter modified by">
                <span></span> <i class="icon-base ti tabler-x"></i>
            </button>
        </div>
        <div class="card-body p-1">
            <div id="tr-chart-performance"></div>
        </div>
    </div>
</div>

@endsection

@section('page-script')
    @vite(['resources/js/pages/FACDBT/FACDBT04/Taking-Revision/takingrevision.js'])
@endsection
