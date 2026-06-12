@extends('layouts/layoutMaster')

@section('title', 'HITUAM - Master News')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="javascript:void(0);">User Access Management</a>
        </li>
        <li class="breadcrumb-item">
            <a href="javascript:void(0);">Basic Data</a>
        </li>
        <li class="breadcrumb-item active">News</li>
    </ol>
</nav>

<div class="card">
    <div class="card-datatable">
        <div class="d-flex justify-content-between align-items-center my-5 flex-column flex-xl-row px-6">
            <div class="d-flex align-items-center gap-2 justify-content-center">
                <div class="d-flex gap-2">
                    @serve('HITUAMF011-Create')
                    <button type="button" class="btn btn-primary d-flex align-items-center gap-2 px-2 py-2 w-100" id="btn-create">
                        <i class="icon-base ti tabler-plus"></i>
                        <span>Create</span>
                    </button>
                    @endserve
                    @serve('HITUAMF011-Delete')
                    <button type="button" class="btn btn-primary d-flex align-items-center gap-2 px-2 py-2 w-100 disabled" id="btn-delete-selected-service">
                        <i class="icon-base ti tabler-trash"></i>
                        <span>Delete</span>
                    </button>
                    @endserve
                </div>
            </div>
            <div class="d-flex gap-2 align-items-center flex-column flex-lg-row mt-2 mt-xxl-0">
                <div class="d-flex align-items-center gap-1 justify-content-center">
                    <select class="form-select form-select-sm mx-2" id="entries" style="width: 70px; max-width: 70px; font-size: 1rem; padding: 0.25rem 0.5rem;">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>
        </div>
        {{ $dataTable->table() }}
    </div>
</div>
@endsection

@section('page-script')
{{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@vite(['resources/js/pages/hituam/hituam01/news/news.js'])
@endsection
