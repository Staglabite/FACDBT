@php
$configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'News')

@section('page-style')
<style>
    /* News header image */
    .news-header-image {
        width: 100%;
        height: 400px;
        object-fit: cover;
        border-radius: 8px;
        margin-bottom: 2rem;
    }
</style>
@endsection

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="javascript:void(0);">Dashboard</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('hituam.master-news.index') }}">News</a>
        </li>
        <li class="breadcrumb-item active">Detail</li>
    </ol>
</nav>
<div class="card">
    <div class="card-body">
        <!-- Header Image -->
        @if($news->VIMAGE_PATH)
        <img src="{{ asset('storage/news/images/' . $news->VIMAGE_PATH) }}"
            alt="{{ $news->VTITLE }}"
            class="news-header-image">
        @endif
        <h3 class="mb-3">{{ $news->VTITLE }}</h3>
        <p class="text-muted mb-4">Published on {{ \Carbon\Carbon::parse($news->DPOSTED)->format('F j, Y') }}</p>
        {!! $news->VCONTENT !!}
        <a href="{{ asset('storage/news/files/' . $news->VFILE_PATH) }}" target="_blank">Attachment</a>
    </div>
</div>
@endsection