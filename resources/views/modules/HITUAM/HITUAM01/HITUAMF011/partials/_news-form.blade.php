@extends('layouts/layoutMaster')

@section('title', isset($newsData) ? 'HITUAM - Edit News' : 'HITUAM - Create News')

@section('page-style')
@vite(['resources/assets/vendor/libs/select2/select2.scss'])
<style>
    .file-preview-item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
    }

    .file-preview-item i {
        font-size: 18px;
        color: #0d6efd;
    }

    .file-preview-item .file-name {
        font-size: 14px;
        color: #495057;
    }

    .file-preview-item .btn-delete {
        margin-left: 8px;
        padding: 2px 8px;
        font-size: 12px;
    }
</style>
@endsection

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="javascript:void(0);">User Access Management</a>
        </li>
        <li class="breadcrumb-item">
            <a href="javascript:void(0);">Basic Data</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('hituam.master-news.index') }}">News</a>
        </li>
        <li class="breadcrumb-item active">{{ isset($newsData) ? 'Edit' : 'Create' }} News</li>
    </ol>
</nav>

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">{{ isset($newsData) ? 'Edit' : 'Create' }} News</h5>
    </div>
    <div class="card-body">
        <form id="application-form">
            @csrf
            <div class="row">
                <div class="col-12 col-lg-12">
                    <div class="mb-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="title" name="title" placeholder="Title"
                            required aria-required="true" autofocus value="{{ $newsData->VTITLE ?? '' }}">
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                {{-- <div class="col-12 col-lg-6">
                    <div class="mb-3">
                        <label for="publish_to_vendor" class="form-label">Publish to Supplier</label>
                        <select class="form-select" id="publish_to_vendor" required aria-required="true"
                            name="publish_to_vendor[]" multiple aria-label="Publish to Vendor">
                            @php
                            // Parse selected vendors
                            $selectedVendors = [];
                            if ($newsData && $newsData->AVIEWERS) {
                            $selectedVendors = is_array($newsData->AVIEWERS)
                            ? $newsData->AVIEWERS
                            : explode(',', $newsData->AVIEWERS);
                            }

                            // Check if "all" is selected
                            $isAllSelected = in_array('all', $selectedVendors);
                            @endphp

                            <option value="all" {{ $isAllSelected ? 'selected' : '' }}>All Vendors</option>

                            @foreach ($vendorData as $vendor)
                            <option value="{{ $vendor->IID }}"
                                {{ !$isAllSelected && in_array($vendor->IID, $selectedVendors) ? 'selected' : '' }}>
                                [{{ $vendor->VSUPPLIER_CODE }}] - {{ $vendor->VNAME }}
                            </option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback"></div>
                    </div>
                </div> --}}
                <div class="col-12 col-lg-6">
                    <div class="mb-3">
                        <label for="upload_file" class="form-label">Upload File</label>
                        <div class="d-flex gap-3 flex-column flex-lg-row mt-2 mt-xxl-0">
                            <div class="d-flex gap-2">
                                <input type="file" id="upload_file" name="upload_file" aria-label="Upload File"
                                    style="display: none;">
                                <button type="button" class="btn btn-outline-primary waves-effect"
                                    onclick="document.getElementById('upload_file').click()">
                                    <i class='icon-xs icon-base ti tabler-file me-2'></i>
                                    <span>Upload File</span>
                                </button>
                            </div>
                            <div id="file_preview" class="d-flex gap-2">
                                @if ($newsData && $newsData->VFILE_PATH)
                                <div class="file-preview-item">
                                    <i class="bx bx-file"></i>
                                    <span class="file-name">{{ $newsData->VFILE_PATH }}</span>
                                    {{-- data-existing="true" → menandai ini adalah file dari server --}}
                                    <button type="button" class="btn btn-sm btn-danger btn-delete"
                                        data-input-id="upload_file" data-preview-id="file_preview"
                                        data-existing="true">
                                        <i class="bx bx-trash"></i> Delete
                                    </button>
                                </div>
                                @endif
                            </div>
                        </div>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="mb-3">
                        <label for="upload_foto" class="form-label">Upload Foto</label>
                        <div class="d-flex gap-3 flex-column flex-lg-row mt-2 mt-xxl-0">
                            <div class="d-flex gap-2">
                                <input type="file" id="upload_foto" name="upload_foto" aria-label="Upload Foto"
                                    accept="image/*" style="display: none;">
                                <button type="button" class="btn btn-outline-primary waves-effect"
                                    onclick="document.getElementById('upload_foto').click()">
                                    <i class='icon-xs icon-base ti tabler-file-type-jpg me-2'></i>
                                    <span>Upload Image</span>
                                </button>
                            </div>
                            <small class="text-muted align-self-center">Optional.</small>
                            <div id="foto_preview" class="d-flex gap-2">
                                @if ($newsData && $newsData->VIMAGE_PATH)
                                <div class="file-preview-item">
                                    <i class="bx bx-file"></i>
                                    <span
                                        class="file-name">{{ \Illuminate\Support\Str::limit($newsData->VIMAGE_PATH, 40, '...') }}</span>
                                    {{-- data-existing="true" → menandai ini adalah foto dari server --}}
                                    <button type="button" class="btn btn-sm btn-danger btn-delete"
                                        data-input-id="upload_foto" data-preview-id="foto_preview"
                                        data-existing="true">
                                        <i class="bx bx-trash"></i> Delete
                                    </button>
                                </div>
                                @endif
                            </div>
                        </div>
                        <div class="invalid-feedback"></div>
                    </div>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12">
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <label for="content" class="form-label mb-0">Content</label>
                        <div class="d-flex align-items-center gap-2" style="margin-right: 4.8vh;">
                            <span>Publish</span>
                            <label class="switch mb-0">
                                <input type="checkbox" class="switch-input" id="publish" name="publish"
                                    {{ isset($newsData) && $newsData->BSTATUS ? 'checked' : '' }}>
                                <span class="switch-toggle-slider">
                                    <span class="switch-on"></span>
                                    <span class="switch-off"></span>
                                </span>
                            </label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div id="content-editor" style="height: 300px;"></div>
                        @if ($newsData)
                        <script>
                            window.newsData = @json($newsData);
                        </script>
                        @endif
                    </div>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12 text-end">
                    <button type="button" class="btn btn-secondary me-2" id="btn-reset">Reset</button>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection

@section('page-script')
<script>
    window.APP_CONFIG = {
        news: @json($newsData),
    };
</script>
@vite(['resources/js/pages/hituam/hituam01/news/news-form.js', 'resources/assets/vendor/libs/select2/select2.js'])
@endsection
