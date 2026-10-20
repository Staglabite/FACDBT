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
    .tr-row { display: flex; gap: .75rem; align-items: stretch; }
    .tr-row > .card { min-width: 0; height: 360px; }
    .tr-chart-main { flex: 4 1 0; }
    .tr-slicer { flex: 1.3 1 0; }
    .tr-chart-side { flex: 1.7 1 0; }
    .tr-title { font-weight: 700; color: #333; }
    /* Slicer seperti Excel: tanpa pilihan semua tombol aktif; dengan pilihan hanya yang dipilih */
    .tr-slicer-list { display: flex; flex-direction: column; gap: 6px; overflow-y: auto; min-height: 0; }
    .tr-slicer-item {
        border: 1px solid #5B9BD5; border-radius: 3px; background: #9DD3F0; color: #1F1F1F;
        padding: 3px 8px; font-size: .8rem; text-align: left; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .tr-slicer-list.has-selection .tr-slicer-item:not(.active) { background: #FFFFFF; border-color: #BFBFBF; color: #7F7F7F; }
    .tr-slicer-clear { border: 0; background: transparent; color: #7F7F7F; padding: 0 2px; line-height: 1; }
    .tr-slicer-clear:disabled { opacity: .35; }
    @media (max-width: 1199.98px) {
        .tr-row { flex-wrap: wrap; }
        .tr-row > .card { flex: 1 1 100%; height: auto; }
        .tr-slicer-list { max-height: 220px; }
    }
</style>

<div class="tr-row mb-4">
    {{-- Item_Book & % Area per area --}}
    <div class="card shadow-none border border-dark rounded-0 tr-chart-main">
        <div class="card-header text-center pt-2 pb-0">
            <h5 class="tr-title mb-0">Taking Data Revision</h5>
        </div>
        <div class="card-body p-1">
            <div id="tr-chart-area"></div>
        </div>
    </div>

    {{-- Filter: boleh pilih lebih dari satu; tanpa pilihan = semua --}}
    @foreach (['modifier' => 'Modified By', 'area' => 'Area'] as $key => $label)
        <div class="card shadow-none border border-dark rounded-0 tr-slicer">
            <div class="card-body p-2 d-flex flex-column" style="min-height: 0;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-semibold text-dark text-uppercase small">{{ $label }}</span>
                    <button type="button" class="tr-slicer-clear" id="tr-{{ $key }}-clear" title="Clear filter" disabled>
                        <i class="icon-base ti tabler-filter-off"></i>
                    </button>
                </div>
                <div class="tr-slicer-list flex-grow-1" id="tr-{{ $key }}-filter"></div>
            </div>
        </div>
    @endforeach

    <div class="card shadow-none border border-dark rounded-0 tr-chart-side">
        <div class="card-header text-center pt-2 pb-0">
            <h5 class="tr-title mb-0">Reason Revision</h5>
        </div>
        <div class="card-body p-1">
            <div id="tr-chart-reason"></div>
        </div>
    </div>

    <div class="card shadow-none border border-dark rounded-0 tr-chart-side">
        <div class="card-header text-center pt-2 pb-0">
            <h5 class="tr-title mb-0">Revision Performance</h5>
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
