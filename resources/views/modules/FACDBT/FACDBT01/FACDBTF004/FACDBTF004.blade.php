@extends('layouts/layoutMaster')

@section('title', 'FACDBT - Taking Book')

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="javascript:void(0);">Dashboard Taking</a>
            </li>
            <li class="breadcrumb-item">
                <a href="javascript:void(0);">Basic Data</a>
            </li>
            <li class="breadcrumb-item active">Taking Book</li>
        </ol>
    </nav>

    {{-- Ringkasan Status Taking Bookkeeping --}}
    <div class="card mb-4">
        <div class="card-body py-3">
            <div class="table-responsive">
                <table class="table table-borderless table-sm mb-0">
                    <tbody>
                        <tr>
                            <td class="fw-semibold text-nowrap" style="width: 280px;">Transfer Taking Bookeeping Success On</td>
                            <td style="width: 15px;">:</td>
                            <td class="text-primary fw-medium">{{ $summary['dateTransferred'] ?? '-' }}</td>
                        </tr>
                        <!-- <tr>
                            <td class="fw-semibold text-nowrap">Total Data Count Per Count Report</td>
                            <td>:</td>
                            <td>{{ $summary['dataCountReport'] ?? 0 }}</td>
                        </tr> -->
                        <tr>
                            <td class="fw-semibold text-nowrap">Total Data Bookeeping</td>
                            <td>:</td>
                            <td>{{ $summary['countBookeeping'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-nowrap">Variance Data Bookeeping</td>
                            <td>:</td>
                            <td>
                                @php
                                    $variance = (float) str_replace(',', '', $summary['varianceBookeeping'] ?? 0);
                                @endphp
                                <span class="{{ $variance != 0 ? 'text-danger fw-bold' : 'text-success' }}">
                                    {{ $summary['varianceBookeeping'] ?? 0 }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-semibold text-nowrap">Total Qty Bookeeping</td>
                            <td>:</td>
                            <td class="fw-bold">{{ $summary['qtyBookeeping'] ?? 0 }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-datatable">
            <div class="d-flex justify-content-between align-items-center my-5 flex-column flex-xl-row px-6">
                <div class="d-flex align-items-center gap-2 justify-content-center">
                    {{-- Sesuaikan dengan penamaan service permission Taking Book --}}
                    @serve('FACDBTF004-Create')
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary d-flex align-items-center gap-2 px-2 py-2 w-100"
                                id="btn-create-takingbook">
                                <i class="icon-base ti tabler-plus"></i>
                                <span>Add</span>
                            </button>
                        </div>
                    @endserve
                    
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary d-flex align-items-center gap-2 py-2 px-3"
                            id="import-excel">
                            <i class="icon-base ti tabler-upload"></i> Import
                        </button>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary d-flex align-items-center gap-2 py-2 px-3"
                            id="download-template">
                            <i class="icon-base ti tabler-download"></i> Download Template
                        </button>
                    </div>

                    @serve('FACDBTF004-Delete')
                        <button type="button" class="btn btn-primary waves-effect waves-effect d-none"
                            id="btn-delete-selected">
                            <i class="icon-base ti tabler-trash"></i>
                            <span>Delete</span>
                        </button>
                    @endserve
                </div>
                
                <div class="d-flex gap-2 align-items-center flex-column flex-lg-row mt-2 mt-xxl-0">
                    <div class="d-flex align-items-center gap-1 justify-content-center">
                        <select class="form-select form-select-sm mx-2" id="entries"
                            style="width: 70px; max-width: 70px; font-size: 1rem; padding: 0.25rem 0.5rem;">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="-1">All</option>
                        </select>
                    </div>
                    <div class="input-group" style="width: 100%; min-width: 200px;">
                        <span class="input-group-text border-end-0">
                            <i class="icon-base ti tabler-search"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" placeholder="Search"
                            aria-label="Search Taking Book" id="search-input">
                    </div>
                </div>
            </div>

            {{ $dataTable->table(['class' => 'table'], true) }}
        </div>
    </div>

    {{-- Path include disesuaikan ke folder FACDBT01/FACDBT004 --}}
    @include('modules.FACDBT.FACDBT01.FACDBTF004.partials._takingbook-form')
    @include('modules.FACDBT.FACDBT01.FACDBTF004.partials._takingbook-import-modal')
    @include('modules.FACDBT.FACDBT01.FACDBTF004.partials._takingbook-import-error')
@endsection

@section('page-script')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
    {{-- Path vite disesuaikan ke folder js takingbook --}}
    @vite([
        'resources/js/pages/facdbt/facdbt01/takingbook/takingbook.js', 
        'resources/js/pages/facdbt/facdbt01/takingbook/takingbook-import.js'
    ])
@endsection