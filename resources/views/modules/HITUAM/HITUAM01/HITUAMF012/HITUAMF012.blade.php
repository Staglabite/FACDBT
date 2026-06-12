@php
$configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Home')

@section('page-style')
<style>
    /* News card styling */
    .news-card-item {
        transition: all 0.3s ease;
        border-radius: 8px;
    }

    .news-card-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    }

    .news-card-item img {
        border-radius: 8px 8px 0 0;
        height: 200px;
        object-fit: cover;
    }

    .news-sidebar {
        max-height: 600px;
        overflow-y: auto;
    }

    .news-sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .news-sidebar::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .news-sidebar::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 10px;
    }

    .news-sidebar::-webkit-scrollbar-thumb:hover {
        background: #555;
    }

    .sidebar-news-card {
        transition: all 0.2s ease;
    }

    .sidebar-news-card:hover {
        background-color: #f8f9fa;
        cursor: pointer;
    }

    .sidebar-news-card img {
        border-radius: 8px;
        width: 100%;
        height: 100px;
        object-fit: cover;
    }

    .main-news-image {
        border-radius: 8px;
        width: 100%;
        height: 300px;
        object-fit: cover;
    }

    .status-badge {
        font-size: 0.875rem;
        padding: 0.25rem 0.75rem;
        border-radius: 4px;
    }

    .dt-empty {
        text-align: center;
    }
</style>
@endsection

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="{{ route('hituam.news.index') }}">User Access Management</a>
        </li>
        <li class="breadcrumb-item active">News</li>
    </ol>
</nav>

<!-- Main Dashboard -->
<div class="row" id="main-dashboard">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-body">
                @if ($latestNews)
                <h2 class="mb-3">{{ $latestNews->VTITLE }}</h2>
                <p class="text-muted small mb-3">Post Date:
                    {{ \Carbon\Carbon::parse($latestNews->DPUBLISHED_AT)->timezone('Asia/Jakarta')->format('d M Y') }}
                </p>
                @php
                $latestImage = $latestNews->VIMAGE_PATH
                ? asset('storage/news/images/' . $latestNews->VIMAGE_PATH)
                : asset('storage/default-astemo.png');
                @endphp
                <img src="{{ $latestImage }}" class="main-news-image mb-4" alt="News Image">

                <div class="news-content">
                    {!! $latestNews->VCONTENT !!}
                </div>

                <div class="mt-auto text-end">
                    <a href="{{ route('hituam.news.show', [$idViewers, $latestNews->VSUBJECT]) }}"
                        class="btn btn-outline-secondary gap-2 px-2 py-2">
                        <i class="icon-base ti tabler-book"></i> Read More
                    </a>
                </div>
                @else
                <div class="text-center py-5 mt-6">
                    <i class="icon-base ti tabler-file-x" style="font-size: 48px; color: #999;"></i>
                    <p class="text-muted mt-3">No news found.</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header border-bottom mb-5">
                <div class="input-group input-group-merge">
                    <input type="text" class="form-control" placeholder="Select Range Date" id="date-range-picker"
                        autocomplete="off">
                    <span class="input-group-text">
                        <i class="icon-base ti tabler-calendar"></i>
                    </span>
                </div>
            </div>

            <div class="card-body">
                @if ($olderNews && $olderNews->count() > 0)
                @foreach ($olderNews as $news)
                <div class="card sidebar-news-card mb-4">
                    <div class="card-body p-3">
                        @php
                        $newsImage = $news->VIMAGE_PATH
                        ? asset('storage/news/images/' . $news->VIMAGE_PATH)
                        : asset('storage/default-astemo.png');
                        @endphp
                        <img src="{{ $newsImage }}" alt="News">
                        <a href="{{ route('hituam.news.show', [$idViewers, $news->VSUBJECT]) }}"
                            class="stretched-link">
                            <h6 class="mt-3 mb-2">{{ $news->VTITLE }}</h6>
                        </a>
                        <p class="text-muted small mb-0">
                            {!! \Illuminate\Support\Str::limit(strip_tags($news->VCONTENT), 50, '...') !!}
                        </p>
                    </div>
                </div>
                @endforeach
                @endif

                <div class="text-center mt-3">
                    <button class="btn btn-light w-100 d-flex align-items-center gap-2 px-2 py-2" id="more-news">
                        <i class="icon-base ti tabler-book"></i> More News
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Spinner -->
<div class="row d-none" id="dashboard-loading">
    <div class="col-12">
        <div class="text-center my-5 py-5">
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Loading news...</p>
        </div>
    </div>
</div>

<!-- News List View -->
<div class="row d-none" id="for-list-news">
    <div class="col-lg-12">
        <div class="card mb-4">
            <div class="card-header border-bottom mb-5">
                <div class="row">
                    <div class="col-md-12">
                        <div class="input-group input-group-merge">
                            <input type="text" class="form-control" placeholder="Select Range Date"
                                id="date-range-picker" autocomplete="off">
                            <span class="input-group-text">
                                <i class="icon-base ti tabler-calendar"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body" id="second-dashboard"></div>
        </div>
    </div>
</div>
{{-- @include('modules.FACTWM.FACTWM04.FACTWMF016.FACTWMF016') --}}
@endsection

@section('page-script')
{{ $dataTable->scripts() }}
@vite(['resources/js/pages/hituam/hituam01/dashboard-news/dashboard.js'])
@vite(['resources/js/pages/hituam/hituam01/dashboard-news/dashboard-filter.js'])
@vite(['resources/js/pages/hituam/hituam01/dashboard-information/dashboard.js'])
<script>
    window.STORAGE_URL = "{{ asset('storage') }}"
</script>
@endsection
