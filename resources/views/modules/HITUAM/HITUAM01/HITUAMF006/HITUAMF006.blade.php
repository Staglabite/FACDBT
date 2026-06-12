@extends('layouts/layoutMaster')

@section('title', 'HITUAM - Basic Data User Roles')

@section('page-style')
@vite([
'resources/assets/vendor/libs/select2/select2.scss',
])
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
        <li class="breadcrumb-item active">Master User Role</li>
    </ol>
</nav>

<div class="row g-6">
    <div class="col-12">
        @include('modules.HITUAM.HITUAM01.HITUAMF005.partials._user-table', [
        'userTable' => $userTable,
        ])
    </div>
    <div class="col-12">
        @include('modules.HITUAM.HITUAM01.HITUAMF004.partials._role-table', [
        'roleTable' => $roleTable,
        ])
    </div>
</div>

@include('modules.HITUAM.HITUAM01.HITUAMF004.partials._role-form')
@include('modules.HITUAM.HITUAM01.HITUAMF004.partials._role-import-modal')
@include('modules.HITUAM.HITUAM01.HITUAMF004.partials._role-import-error')
@include('modules.HITUAM.HITUAM01.HITUAMF005.partials._user-form')
@include('modules.HITUAM.HITUAM01.HITUAMF005.partials._user-import-modal')
@include('modules.HITUAM.HITUAM01.HITUAMF005.partials._user-import-error')
@endsection

@section('page-script')
{{ $userTable->scripts(attributes: ['type' => 'module']) }}
{{ $roleTable->scripts(attributes: ['type' => 'module']) }}
@vite([
'resources/js/pages/hituam/hituam01/user-role/user-role.js',
'resources/assets/vendor/libs/select2/select2.js'
])
@endsection
